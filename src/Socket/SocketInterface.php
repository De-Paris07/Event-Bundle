<?php

namespace ClientEventBundle\Socket;

use App\Services\SocketService;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionInterface;

/**
 * Interface SocketInterface
 *
 * @package ClientEventBundle\Socket
 */
interface SocketInterface
{
    /**
     * @param string|null $uri
     * @param array $options
     *
     * @return SocketInterface
     */
    public function connect(string $uri = null, array $options = []): SocketInterface;

    /**
     * @return void
     */
    public function close();

    /**
     * @param SocketMessageInterface $message
     * @param callable | null $callback
     * @param callable | null $timeoutCallback
     *
     * @return bool
     */
    public function write(SocketMessageInterface $message, callable $callback = null, callable $timeoutCallback = null): bool;

    /**
     * @param ConnectionInterface $connection
     * @param SocketMessageInterface $message
     * @param callable | null $callback
     * @param callable | null $timeoutCallback
     *
     * @return bool
     */
    public function writeByConnection(
        ConnectionInterface $connection,
        SocketMessageInterface $message,
        callable $callback = null, 
        callable $timeoutCallback = null
    ): bool;

    /**
     * @return ConnectionInterface | null
     */
    public function getConnection(): ?ConnectionInterface;

    /**
     * @param ConnectionInterface $conn
     *
     * @return SocketInterface
     */
    public function setConnection(ConnectionInterface $conn): SocketInterface;

    /**
     * @return string | null
     */
    public function getUri(): ?string;

    /**
     * @param string $uri
     *
     * @return $this
     */
    public function setUri(string $uri): SocketInterface;

    /**
     * @return bool
     */
    public function isWaitForAnAnswer(): bool;

    /**
     * @param bool $value
     *
     * @return SocketInterface
     */
    public function setWaitForAnAnswer(bool $value): SocketInterface;

    /**
     * @return LoopInterface | null
     */
    public function getLoop(): ?LoopInterface;

    /**
     * @param LoopInterface $loop
     *
     * @return SocketInterface
     */
    public function setLoop(LoopInterface $loop): SocketInterface;

    /**
     * @return int
     */
    public function getTimeoutSocketWrite(): int;

    /**
     * @param int $timeoutSocketWrite
     *
     * @return SocketInterface
     */
    public function setTimeoutSocketWrite(int $timeoutSocketWrite): SocketInterface;

    /**
     * @return bool
     */
    public function isAsyncWrite(): bool;

    /**
     * @param bool $asyncWrite
     *
     * @return SocketInterface
     */
    public function setAsyncWrite(bool $asyncWrite): SocketInterface;

    /**
     * @return bool
     */
    public function isDebug(): bool;

    /**
     * @param bool $debug
     *
     * @return SocketInterface
     */
    public function setDebug(bool $debug): SocketInterface;

    /**
     * @return string
     */
    public function getMessageDelimiter(): string;

    /**
     * @param string $messageDelimiter
     *
     * @return SocketInterface
     */
    public function setMessageDelimiter(string $messageDelimiter): SocketInterface;

    /**
     * @return string
     */
    public function getMessageObject(): string;

    /**
     * @param string $messageObject
     * 
     * @return SocketInterface
     */
    public function setMessageObject(string $messageObject): SocketInterface;

    /**
     * @return callable | null
     */
    public function getParseMessageFun(): ?callable;

    /**
     * @param callable $parseMessageFun
     *
     * @return SocketInterface
     */
    public function setParseMessageFun(callable $parseMessageFun): SocketInterface;

    /**
     * @return bool
     */
    public function isRunning(): bool;
}
