<?php

namespace ClientEventBundle\Socket;

use React\Socket\ConnectionInterface;

/**
 * Interface SocketMessageInterface
 * 
 * @package ClientEventBundle\Socket
 */
interface SocketMessageInterface
{
    /**
     * @return string
     */
    public function __toString(): string;

    /**
     * @param string $name
     * 
     * @return mixed | null
     */
    public function getField(string $name);

    /**
     * @return string
     */
    public function getChannel(): string;

    /**
     * @param string $channel
     */
    public function setChannel(string $channel): void;

    /**
     * @return string
     */
    public function getXid(): string;

    /**
     * @param string $xid
     */
    public function setXid(string $xid): void;

    /**
     * @return int | null
     */
    public function getPid(): ?int;

    /**
     * @param int | null $pid
     */
    public function setPid(?int $pid): void;

    /**
     * @return array
     */
    public function getData(): array;

    /**
     * @param array $data
     */
    public function setData(array $data): void;

    /**
     * @return string
     */
    public function getRawData(): string;

    /**
     * @param string $rawData
     */
    public function setRawData(string $rawData): void;

    /**
     * @return ConnectionInterface|null
     */
    public function getConnection(): ?ConnectionInterface;

    /**
     * @param ConnectionInterface $conn
     */
    public function setConnection(ConnectionInterface $conn): void;
}
