<?php

namespace ClientEventBundle\Exception;

use Throwable;

/**
 * Class NoEventServer
 *
 * @package ClientEventBundle\Exception
 */
class NoEventServer extends \RuntimeException
{
    protected $message = 'Event server not found. Run the command "php bin/console event:subscribe" to subscribe.';

    /**
     * MemoryLimitException constructor.
     *
     * @param string $message
     * @param int $code
     * @param Throwable | null $previous
     */
    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message ? $message : $this->message, $code, $previous);
    }
}
