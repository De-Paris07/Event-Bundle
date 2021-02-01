<?php

namespace ClientEventBundle\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Exception;

/**
 * @ORM\Entity
 * @ORM\Table(name="event_server")
 * @ORM\HasLifecycleCallbacks()
 */
class EventServer
{
    public const SERVER_AUTH_HEADER = 'Server-Token';
    public const CLIENT_AUTH_HEADER = 'Client-Token';

    public const TOKEN_LENGTH = 32;

    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="client_header", type="string", length=20, nullable=false)
     */
    private $clientHeader;

    /**
     * @var string
     *
     * @ORM\Column(name="server_header", type="string", length=20, nullable=false)
     */
    private $serverHeader;

    /**
     * @var string
     *
     * @ORM\Column(name="client_token", type="string", length=100, nullable=false)
     */
    private $clientToken;

    /**
     * @var string
     *
     * @ORM\Column(name="server_token", type="string", length=100, nullable=false)
     */
    private $serverToken;

    /**
     * @var string
     *
     * @ORM\Column(name="server_host", type="string", length=100, nullable=false)
     */
    private $serverHost;

    /**
     * @var string
     *
     * @ORM\Column(name="current_host", type="string", length=100, nullable=false)
     */
    private $currentHost;

    /**
     * @var DateTime
     *
     * @ORM\Column(type="datetime")
     */
    private $createdAt;

    /**
     * @var DateTime
     *
     * @ORM\Column(type="datetime")
     */
    private $updatedAt;

    /**
     * @return int
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getClientHeader(): string
    {
        return $this->clientHeader;
    }

    /**
     * @param string $clientHeader
     */
    public function setClientHeader(string $clientHeader): void
    {
        $this->clientHeader = $clientHeader;
    }

    /**
     * @return string
     */
    public function getServerHeader(): string
    {
        return $this->serverHeader;
    }

    /**
     * @param string $serverHeader
     */
    public function setServerHeader(string $serverHeader): void
    {
        $this->serverHeader = $serverHeader;
    }

    /**
     * @return string
     */
    public function getServerHost(): string
    {
        return $this->serverHost;
    }

    /**
     * @param string $serverHost
     */
    public function setServerHost(string $serverHost): void
    {
        $this->serverHost = $serverHost;
    }

    /**
     * @return string
     */
    public function getCurrentHost(): string
    {
        return $this->currentHost;
    }

    /**
     * @param string $currentHost
     */
    public function setCurrentHost(string $currentHost): void
    {
        $this->currentHost = $currentHost;
    }

    /**
     * @return string
     */
    public function getClientToken(): string
    {
        return $this->clientToken;
    }

    /**
     * @param string $clientToken
     */
    public function setClientToken(string $clientToken): void
    {
        $this->clientToken = $clientToken;
    }

    /**
     * @return string
     */
    public function getServerToken(): string
    {
        return $this->serverToken;
    }

    /**
     * @param string $serverToken
     */
    public function setServerToken(string $serverToken): void
    {
        $this->serverToken = $serverToken;
    }

    /**
     * @return DateTime
     */
    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }

    /**
     * @param DateTime $createdAt
     */
    public function setCreatedAt(DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    /**
     * @return DateTime | null
     */
    public function getUpdatedAt(): ?DateTime
    {
        return $this->updatedAt;
    }

    /**
     * @param DateTime $updatedAt
     */
    public function setUpdatedAt(DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
    
    /**
     * @ORM\PrePersist
     * @ORM\PreUpdate
     *
     * @throws Exception
     */
    public function updatedTimestamps(): void
    {
        $dateTimeNow = new DateTime();

        $this->setUpdatedAt($dateTimeNow);

        if (null === $this->getCreatedAt()) {
            $this->setCreatedAt($dateTimeNow);
        }
    }
}
