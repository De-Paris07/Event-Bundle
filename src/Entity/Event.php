<?php

namespace ClientEventBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\Table(name="remote_event", indexes={
 *      @ORM\Index(name="status_count_idx", columns={"status", "count_attempts"})
 * })
 */
class Event
{
    const STATUS_DISPATH_SUCCESS = 0;
    const STATUS_DISPATH_FAIL = 1;

    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var integer
     *
     * @ORM\Column(name="status", type="integer", length=1, nullable=false)
     */
    private $status;

    /**
     * @var integer
     * 
     * @ORM\Column(name="count_attempts", type="integer", length=3, nullable=false)
     */
    private $countAttempts = 0;

    /**
     * @var string
     *
     * @ORM\Column(name="hash", type="string", length=255, nullable=false)
     */
    private $hash;

    /**
     * @var string
     *
     * @ORM\Column(name="event_name", type="string", length=255, nullable=false)
     */
    private $eventName;

    /**
     * @var string
     *
     * @ORM\Column(name="data", type="text", nullable=false)
     */
    private $data;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created", type="datetime", length=6, nullable=false)
     */
    private $created;

    /**
     * @var integer
     * 
     * @ORM\Column(name="retry_date", type="integer", nullable=true)
     */
    private $retryDate;

    /**
     * Event constructor.
     *
     * @throws \Exception
     */
    public function __construct()
    {
        $this->created = new \DateTime();
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId(int $id): void
    {
        $this->id = $id;
    }

    /**
     * @return int
     */
    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * @param int $status
     */
    public function setStatus(int $status): void
    {
        $this->status = $status;
    }

    /**
     * @return int
     */
    public function getCountAttempts(): int
    {
        return $this->countAttempts;
    }

    /**
     * @param int $countAttempts
     */
    public function setCountAttempts(int $countAttempts): void
    {
        $this->countAttempts = $countAttempts;
    }

    /**
     * @return string
     */
    public function getHash(): string
    {
        return $this->hash;
    }

    /**
     * @param string $hash
     */
    public function setHash(string $hash): void
    {
        $this->hash = $hash;
    }

    /**
     * @return string
     */
    public function getEventName(): string
    {
        return $this->eventName;
    }

    /**
     * @param string $eventName
     */
    public function setEventName(string $eventName): void
    {
        $this->eventName = $eventName;
    }

    /**
     * @return string
     */
    public function getData(): string
    {
        return $this->data;
    }

    /**
     * @param string $data
     */
    public function setData(string $data): void
    {
        $this->data = $data;
    }

    /**
     * @return \DateTime
     */
    public function getCreated(): \DateTime
    {
        return $this->created;
    }

    /**
     * @param \DateTime $created
     */
    public function setCreated(\DateTime $created): void
    {
        $this->created = $created;
    }

    /**
     * @return int
     */
    public function getRetryDate(): ?int
    {
        return $this->retryDate;
    }

    /**
     * @param int $retryDate
     */
    public function setRetryDate(int $retryDate): void
    {
        $this->retryDate = $retryDate;
    }
}
