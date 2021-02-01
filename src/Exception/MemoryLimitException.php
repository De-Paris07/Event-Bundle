<?php

namespace ClientEventBundle\Exception;

use Throwable;

/**
 * Class MemoryLimitException
 *
 * @package ClientEventBundle\Exception
 */
class MemoryLimitException extends \RuntimeException
{
    protected $message = 'Process memory limit exceeded.';

    /**
     * MemoryLimitException constructor.
     *
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message ? $message : $this->message, $code, $previous);
    }
}
