<?php

namespace Bundles\SandboxBundle\Provider;

use App\Entity\Volunteer;
use App\Provider\SMS\SMSProvider;
use App\Tools\Random;
use Bundles\SandboxBundle\Entity\FakeSms;
use Doctrine\Bundle\DoctrineBundle\Registry;
use LogicException;

class FakeSmsProvider implements SMSProvider
{
    private $volunteerRepository;
    private $fakeSmsRepository;

    public function __construct(Registry $registry)
    {
        $this->volunteerRepository = $registry->getRepository(Volunteer::class);
        $this->fakeSmsRepository   = $registry->getRepository(FakeSms::class);
    }

    public function send(string $phoneNumber, string $message, array $context = []): ?string
    {
        $volunteer = $this->volunteerRepository->findOneByPhoneNumber($phoneNumber);

        if (!$volunteer) {
            throw new LogicException('Cannot send fake SMS to unknown volunteer.');
        }

        $this->fakeSmsRepository->save($volunteer, $message, FakeSms::DIRECTION_RECEIVED);

        return sprintf('FAKE-%s', Random::generate(15));
    }

    public function getProviderCode(): string
    {
        return 'fake';
    }
}