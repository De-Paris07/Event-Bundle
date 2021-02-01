<?php

namespace ClientEventBundle\Socket;

/**
 * Interface SocketServerInterface
 *
 * @package ClientEventBundle\Socket
 */
interface SocketServerInterface extends SocketInterface
{
    /**
     * @return int | null
     */
    public function getTimeoutClose(): ?int;

    /**
     * @param int | null $timeoutClose
     *
     * @return SocketServerInterface
     */
    public function setTimeoutClose(?int $timeoutClose): SocketServerInterface;

    /**
     * @return string
     */
    public function getMode(): string;

    /**
     * @param string $mode
     *
     * @return SocketServerInterface
     */
    public function setMode(string $mode): SocketServerInterface;
}
