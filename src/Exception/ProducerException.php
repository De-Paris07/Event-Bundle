<?php

namespace ClientEventBundle\Exception;

use Throwable;

/**
 * Class ProducerException
 *
 * @package ClientEventBundle\Exception
 */
class ProducerException extends \RuntimeException
{
    /** @var string $eventId */
    private $eventId;

    public function __construct($message = "", $eventId, $code = 0, Throwable $previous = null)
    {
        $this->eventId = $eventId;
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return mixed
     */
    public function getEventId()
    {
        return $this->eventId;
    }
}
