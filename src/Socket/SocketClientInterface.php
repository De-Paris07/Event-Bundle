<?php

namespace ClientEventBundle\Socket;

use Exception;

/**
 * Interface SocketClientInterface
 *
 * @package ClientEventBundle\Socket
 */
interface SocketClientInterface extends SocketInterface
{
    /**
     * @param SocketMessageInterface $message
     * @param callable|null $callback
     * @param callable|null $timeoutCallback
     * @param bool $isAsync
     * @param array $options
     *
     * @return void
     *
     * @throws Exception
     */
    public function writeWithoutListening(
        SocketMessageInterface $message,
        callable $callback = null,
        callable $timeoutCallback = null,
        bool $isAsync = false,
        array $options = []
    ): void;

    /**
     * @return int
     */
    public function getTimeoutConnect(): int;

    /**
     * @param float $timeoutConnect
     *
     * @return SocketClientInterface
     */
    public function setTimeoutConnect(float $timeoutConnect): SocketClientInterface;

    /**
     * @return float
     */
    public function getReconnectTime();

    /**
     * @param float $reconnectTime
     *
     * @return SocketClientInterface
     */
    public function setReconnectTime($reconnectTime): SocketClientInterface;

    /**
     * @return bool
     */
    public function isReconnect(): bool;

    /**
     * @param bool $isReconnect
     *
     * @return SocketClientInterface
     */
    public function setIsReconnect(bool $isReconnect): SocketClientInterface;

    /**
     * @return bool
     */
    public function isConnect(): bool;
}
