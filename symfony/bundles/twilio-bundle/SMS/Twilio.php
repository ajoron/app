<?php

namespace Bundles\TwilioBundle\SMS;

use Bundles\TwilioBundle\Entity\TwilioMessage;
use Bundles\TwilioBundle\Event\TwilioEvent;
use Bundles\TwilioBundle\Manager\TwilioMessageManager;
use Bundles\TwilioBundle\TwilioEvents;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\RouterInterface;
use Twilio\Rest\Client;

class Twilio
{
    /**
     * @var Client|null
     */
    private $client;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var TwilioMessageManager
     */
    private $messageManager;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param RouterInterface          $router
     * @param EventDispatcherInterface $eventDispatcher
     * @param TwilioMessageManager     $messageManager
     * @param LoggerInterface          $logger
     */
    public function __construct(RouterInterface $router,
        EventDispatcherInterface $eventDispatcher,
        TwilioMessageManager $messageManager,
        LoggerInterface $logger)
    {
        $this->router          = $router;
        $this->eventDispatcher = $eventDispatcher;
        $this->messageManager  = $messageManager;
        $this->logger          = $logger;
    }

    /**
     * Send an SMS to the given phone number and store outbound information
     * on the database for further tracking.
     *
     * @param string $phoneNumber
     * @param string $message
     * @param array  $context
     *
     * @return TwilioMessage
     *
     * @throws \Twilio\Exceptions\ConfigurationException
     * @throws \Twilio\Exceptions\TwilioException
     */
    public function sendMessage(string $phoneNumber, string $message, array $context = []): TwilioMessage
    {
        $entity = new TwilioMessage();
        $entity->setUuid(Uuid::uuid4());
        $entity->setDirection(TwilioMessage::DIRECTION_OUTBOUND);
        $entity->setMessage($message);
        $entity->setFromNumber(getenv('TWILIO_NUMBER'));
        $entity->setToNumber($phoneNumber);
        $entity->setContext($context);
        $this->messageManager->save($entity);

        try {
            $outbound = $this->getClient()->messages->create($phoneNumber, [
                'from'           => getenv('TWILIO_NUMBER'),
                'body'           => $message,
                'statusCallback' => trim(getenv('WEBSITE_URL'), '/').$this->router->generate('twilio_status', ['uuid' => $entity->getUuid()]),
            ]);

            $entity->setSid($outbound->sid);
            $entity->setStatus($outbound->status);

            $this->messageManager->save($entity);
            $this->eventDispatcher->dispatch(new TwilioEvent($entity), TwilioEvents::MESSAGE_SENT);
        } catch (\Exception $e) {
            $entity->setStatus('error');

            $this->logger->error('Unable to send SMS', [
                'phoneNumber' => $entity->getToNumber(),
                'context' => $context,
                'exception' => $e,
            ]);
        }

        $this->messageManager->save($entity);

        return $entity;
    }

    /**
     * Fetch prices of messages on Twilio when they are missing on our database.
     */
    public function fetchPrices()
    {
        $entities = $this->messageManager->findMessagesWithoutPrice();
        foreach ($entities as $entity) {
            $message = $this->getClient()->messages($entity->getSid())->fetch();
            if ($message->price) {
                $entity->setPrice($message->price);
                $entity->setUnit($message->priceUnit);

                $this->messageManager->save($entity);
                $this->eventDispatcher->dispatch(new TwilioEvent($entity), TwilioEvents::PRICE_UPDATED);
                $this->messageManager->save($entity);
            }
            usleep(100000);
        }
    }

    /**
     * Handles an incoming message
     *
     * @param string $sid
     *
     * @throws \Twilio\Exceptions\ConfigurationException
     * @throws \Twilio\Exceptions\TwilioException
     */
    public function handleReply(string $sid)
    {
        $inbound = $this->getClient()->messages($sid)->fetch();

        $entity = new TwilioMessage();
        $entity->setUuid(Uuid::uuid4());
        $entity->setDirection(TwilioMessage::DIRECTION_INBOUND);
        $entity->setMessage($inbound->body);
        $entity->setFromNumber(ltrim($inbound->from, '+'));
        $entity->setToNumber(ltrim($inbound->to, '+'));
        $entity->setSid($inbound->sid);
        $entity->setStatus($inbound->status);
        $entity->setPrice($inbound->price);
        $entity->setUnit($inbound->priceUnit);

        $this->messageManager->save($entity);
        $this->eventDispatcher->dispatch(new TwilioEvent($entity), TwilioEvents::MESSAGE_RECEIVED);
        $this->messageManager->save($entity);
    }

    /**
     * @return Client
     * @throws \Twilio\Exceptions\ConfigurationException
     */
    private function getClient()
    {
        if ($this->client) {
            return $this->client;
        }

        $this->client = new Client(getenv('TWILIO_ACCOUNT_SID'), getenv('TWILIO_AUTH_TOKEN'));

        return $this->client;
    }
}