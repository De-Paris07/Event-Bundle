<?php

declare(strict_types=1);

namespace ClientEventBundle\Event;

use ClientEventBundle\Event;
use Pheanstalk\PheanstalkInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class RetryEvent
 *
 * @package ClientEventBundle\Event
 */
class RetryEvent extends Event
{
    public const RETRY_ALL_ERROR_EVENTS = 'error';
    public const RETRY_LAST_EVENT = 'last';
    
    /**
     * @var string
     *
     * @Assert\NotBlank()
     * @Assert\Length(min=3)
     */
    protected $eventName = 'system.retry';

    /**
     * @var string
     * 
     * @Assert\NotNull()
     */
    protected $retryEventId;

    /**
     * @var int
     *
     * @Assert\NotNull()
     */
    protected $retryPriority = PheanstalkInterface::DEFAULT_PRIORITY;

    /**
     * Get retryEventId
     *
     * @return string
     */
    public function getRetryEventId(): string
    {
        return $this->retryEventId;
    }

    /**
     * Set retryEventId
     *
     * @param string $retryEventId
     * 
     * @return RetryEvent
     */
    public function setRetryEventId(string $retryEventId): self
    {
        $this->retryEventId = $retryEventId;
        
        return $this;
    }

    /**
     * Get retryPriority
     *
     * @return int
     */
    public function getRetryPriority(): int
    {
        return $this->retryPriority;
    }

    /**
     * Set retryPriority
     *
     * @param int $retryPriority
     * 
     * @return RetryEvent
     */
    public function setRetryPriority(int $retryPriority): self
    {
        $this->retryPriority = $retryPriority;
        
        return $this;
    }
}
