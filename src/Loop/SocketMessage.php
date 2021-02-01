<?php

namespace ClientEventBundle\Loop;

use ClientEventBundle\Socket\SocketMessageInterface;
use React\Socket\ConnectionInterface;

/**
 * Class SocketMessage
 *
 * @package ClientEventBundle\Loop
 */
class SocketMessage implements SocketMessageInterface
{
    /** @var string $channel */
    protected $channel;
    
    /** @var string $xid */
    protected $xid;
    
    /** @var int $pid */
    protected $pid;
    
    /** @var array $data */
    protected $data;
    
    /** @var string $rawData */
    protected $rawData;
    
    /** @var ConnectionInterface $conn */
    protected $conn; 

    /**
     * @return string
     */
    public static function generateXid(): string
    {
        return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
            mt_rand( 0, 0xffff ),
            mt_rand( 0, 0x0fff ) | 0x4000,
            mt_rand( 0, 0x3fff ) | 0x8000,
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
        );
    }

    /**
     * SocketMessage constructor.
     *
     * @param string $channel
     * @param array $data
     * @param string | null $xid
     * @param int | null $pid
     */
    public function __construct(string $channel, array $data = [], string $xid = null, int $pid = null)
    {
        $this->channel = $channel;
        $this->data = $data;
        $this->xid = $xid ?? self::generateXid();
        $this->pid = $pid;
    }

    /**
     * @return false | string
     */
    public function __toString(): string
    {
        return json_encode([
            'channel' => $this->getChannel(),
            'pid' => getmypid(),
            'xid' => $this->getXid(),
            'payload' => $this->getData(),
        ]) . "\n";
    }

    /**
     * @param string $name
     *
     * @return mixed | null
     */
    public function getField(string $name)
    {
        return isset($this->getData()[$name]) ? $this->getData()[$name] : null;
    }

    /**
     * @return string
     */
    public function getChannel(): string
    {
        return $this->channel;
    }

    /**
     * @param string $channel
     */
    public function setChannel(string $channel): void
    {
        $this->channel = $channel;
    }

    /**
     * @return string
     */
    public function getXid(): string
    {
        return $this->xid;
    }

    /**
     * @param string $xid
     */
    public function setXid(string $xid): void
    {
        $this->xid = $xid;
    }

    /**
     * @return int | null
     */
    public function getPid(): ?int
    {
        return $this->pid;
    }

    /**
     * @param int | null $pid
     */
    public function setPid(?int $pid): void
    {
        $this->pid = $pid;
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param array $data
     */
    public function setData(array $data): void
    {
        $this->data = $data;
    }

    /**
     * @return string
     */
    public function getRawData(): string
    {
        return $this->rawData;
    }

    /**
     * @param string $rawData
     */
    public function setRawData(string $rawData): void
    {
        $this->rawData = $rawData;
    }

    /**
     * @return ConnectionInterface
     */
    public function getConnection(): ?ConnectionInterface
    {
        return $this->conn;
    }

    /**
     * @param ConnectionInterface $conn
     */
    public function setConnection(ConnectionInterface $conn): void
    {
        $this->conn = $conn;
    }
}
