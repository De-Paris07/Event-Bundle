<?php

namespace ClientEventBundle\Event;

use ClientEventBundle\Annotation\ExtractField;
use ClientEventBundle\Event;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class QueryEvent
 *
 * @package ClientEventBundle\Event
 */
class QueryEvent extends Event
{
    /**
     * @var string
     *
     * @Assert\NotBlank()
     * @Assert\Length(min=3)
     */
    protected $eventName = 'queue.query';
    
    /** 
     * @var string
     * 
     * @Assert\NotBlank()
     * @Assert\Length(min=3)
     */
    private $route;
    
    /** 
     * @var string 
     * 
     * @Assert\NotBlank()
     * @Assert\Length(min=3)
     */
    private $adress;

    /** @var bool $responseToCache */
    private $responseToCache = false;
    
    /** @var array $queryData */
    private $queryData = [];

    /**
     * @var integer
     * 
     * @Assert\NotBlank()
     * @Assert\NotNull()
     */
    private $timeout;

    /**
     * @return string
     */
    public function getRoute(): string
    {
        return $this->route;
    }

    /**
     * @param string $route
     */
    public function setRoute(string $route): void
    {
        $this->route = $route;
    }

    /**
     * @return string
     */
    public function getAdress(): string
    {
        return $this->adress;
    }

    /**
     * @param string $adress
     */
    public function setAdress(string $adress): void
    {
        $this->adress = $adress;
    }

    /**
     * @return array
     */
    public function getQueryData(): array
    {
        return $this->queryData;
    }

    /**
     * @param array $queryData
     */
    public function setQueryData(array $queryData): void
    {
        $this->queryData = $queryData;
    }

    /**
     * @return int
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * @param int $expires
     */
    public function setTimeout(int $timeout): void
    {
        $this->timeout = $timeout;
    }

    /**
     * @return bool
     */
    public function isResponseToCache(): bool
    {
        return $this->responseToCache;
    }

    /**
     * @param bool $responseToCache
     */
    public function setResponseToCache(bool $responseToCache): void
    {
        $this->responseToCache = $responseToCache;
    }

    /**
     * @return int | null
     */
    public function getExpires()
    {
        return $this->getCreated() + $this->getTimeout();
    }
}
