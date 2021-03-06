<?php

namespace App\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Exception;

/**
 * @ORM\Entity(repositoryClass="App\Repository\CommunicationRepository")
 */
class Communication
{
    const TYPE_SMS   = 'sms';
    const TYPE_EMAIL = 'email';

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @var Campaign
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Campaign", inversedBy="communications")
     */
    private $campaign;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $label;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=20)
     */
    private $type;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=80, nullable=true)
     */
    private $subject;

    /**
     * @var string
     *
     * @ORM\Column(type="text")
     */
    private $body;

    /**
     * @var DateTime
     *
     * @ORM\Column(type="datetime")
     */
    private $createdAt;

    /**
     * Contains volunteer's message statuses, the message itself is inside body.
     *
     * @var array
     *
     * @ORM\OneToMany(targetEntity="App\Entity\Message", mappedBy="communication", cascade={"persist"})
     */
    private $messages = [];

    /**
     * @var Choice[]
     *
     * @ORM\OneToMany(targetEntity="App\Entity\Choice", mappedBy="communication", cascade={"persist"})
     */
    private $choices;

    /**
     * @var boolean
     *
     * @ORM\Column(type="boolean")
     */
    private $geoLocation = false;

    /**
     * @var boolean
     *
     * @ORM\Column(type="boolean")
     */
    private $multipleAnswer = false;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Volunteer", inversedBy="communications")
     */
    private $volunteer;

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $id
     *
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return Campaign
     */
    public function getCampaign(): Campaign
    {
        return $this->campaign;
    }

    /**
     * @param Campaign $campaign
     *
     * @return $this
     */
    public function setCampaign($campaign): self
    {
        $this->campaign = $campaign;

        return $this;
    }

    /**
     * @return string
     */
    public function getLabel(): ?string
    {
        return $this->label;
    }

    /**
     * @param string $label
     *
     * @return $this
     */
    public function setLabel(?string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @param string $type
     *
     * @return $this
     */
    public function setType($type): self
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getSubject(): ?string
    {
        return $this->subject;
    }

    /**
     * @param string|null $subject
     *
     * @return Communication
     */
    public function setSubject(?string $subject): Communication
    {
        $this->subject = $subject;

        return $this;
    }

    /**
     * @return string
     */
    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * @param string $body
     *
     * @return $this
     */
    public function setBody($body): self
    {
        $this->body = $body;

        return $this;
    }

    /**
     * @return Message[] $messages
     */
    public function getMessages()
    {
        return $this->messages;
    }

    /**
     * @param Message[] $messages
     *
     * @return $this
     */
    public function setMessages($messages): self
    {
        $this->messages = $messages;

        return $this;
    }

    /**
     * @param Message $message
     *
     * @return $this
     */
    public function addMessage(Message $message): self
    {
        $this->messages[] = $message;
        $message->setCommunication($this);

        return $this;
    }

    /**
     * @return DateTime
     */
    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    /**
     * @param DateTime $createdAt
     *
     * @return $this
     */
    public function setCreatedAt($createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * @return Choice[]
     */
    public function getChoices()
    {
        return $this->choices;
    }

    /**
     * @param $choices
     *
     * @return $this
     */
    public function setChoices($choices): self
    {
        $this->choices = $choices;

        return $this;
    }

    /**
     * @param Choice $choice
     *
     * @return $this
     */
    public function addChoice(Choice $choice)
    {
        $this->choices[] = $choice;
        $choice->setCommunication($this);

        return $this;
    }

    /**
     * @param string|null $prefix
     * @param string      $code
     *
     * @return Choice|null
     */
    public function getChoiceByCode(?string $prefix, string $code): ?Choice
    {
        if (!$prefix) {
            return null;
        }

        $codes = explode(' ', trim($code));
        foreach ($codes as $code) {

            $matches = [];
            preg_match('/^([a-zA-Z]+)(\d)/', $code, $matches);
            if (3 === count($matches)) {

                // Invalid prefix: do not take any choice
                $codePrefix = strtoupper($matches[1]);
                if ($prefix !== $codePrefix) {
                    continue;
                }

                /**
                 * @var Choice $choice
                 */
                foreach ($this->getChoices() as $choice) {
                    if ($choice->getCode() == $matches[2]) {
                        return $choice;
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param string|null $prefix
     * @param string      $raw
     *
     * @return array
     */
    public function getAllChoicesInText(?string $prefix, string $raw): array
    {
        if (!$prefix) {
            return [];
        }

        $choices = [];

        foreach (array_filter(explode(' ', trim($raw))) as $split) {
            $choices[] = $this->getChoiceByCode($prefix, $split);
        }

        return array_filter($choices);
    }

    /**
     * @return bool
     */
    public function hasGeoLocation(): bool
    {
        return $this->geoLocation;
    }

    /**
     * @param bool $geoLocation
     *
     * @return Communication
     */
    public function setGeoLocation(bool $geoLocation): Communication
    {
        $this->geoLocation = $geoLocation;

        return $this;
    }

    /**
     * @return bool
     */
    public function isMultipleAnswer(): bool
    {
        return $this->multipleAnswer;
    }

    /**
     * @param bool $multipleAnswer
     *
     * @return Communication
     */
    public function setMultipleAnswer(bool $multipleAnswer): Communication
    {
        $this->multipleAnswer = $multipleAnswer;

        return $this;
    }

    /**
     * @return float
     */
    public function getCost(): float
    {
        $cost = 0.0;

        foreach ($this->getMessages() as $message) {
            $cost += $message->getCost();
        }

        return $cost;
    }

    /**
     * Returns true if message body doesn't exactly match expected choices.
     *
     * @param string|null $prefix
     * @param string      $message
     *
     * @return bool
     *
     * @throws Exception
     */
    public function isUnclear(?string $prefix, string $message): bool
    {
        if (!$prefix) {
            return false;
        }

        $words   = explode(' ', trim($message));
        $choices = [];
        foreach ($words as $index => $word) {
            // No prefix
            preg_match('/^([a-zA-Z]+)(\d)/', $word, $matches);
            if (count($matches) !== 3) {
                return true;
            }

            // Invalid prefix
            $givenPrefix = strtoupper($matches[1]);
            if ($givenPrefix !== $prefix) {
                return true;
            }

            $choice = $this->getChoiceByCode($prefix, $word);

            // Answer contain something else than expected choice codes
            if (!$choice) {
                return true;
            }

            // Answer repeated
            if (in_array($choice, $choices)) {
                return true;
            }

            $choices[] = $choice;
        }

        // Answer does not match any choice
        if (count($choices) == 0) {
            return false;
        }

        // Communication requires 1 answer, but several were given
        if (!$this->multipleAnswer && count($choices) > 1) {
            return false;
        }

        return false;
    }

    public function getVolunteer(): ?Volunteer
    {
        return $this->volunteer;
    }

    public function setVolunteer(?Volunteer $volunteer): self
    {
        $this->volunteer = $volunteer;

        return $this;
    }
}
