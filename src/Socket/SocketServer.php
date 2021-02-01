<?php

namespace ClientEventBundle\Socket;

use ClientEventBundle\Loop\LoopFactory;
use ClientEventBundle\Socket\Server\TcpServer;
use ClientEventBundle\Socket\Server\UnixServer;
use React\EventLoop\TimerInterface;
use React\Socket\ConnectionInterface;
use React\Socket\ServerInterface;

/**
 * Class SocketServer
 *
 * @package ClientEventBundle\Socket
 */
class SocketServer extends AbstractSocket implements SocketServerInterface
{
    public const MODE_TCP = 'tcp';
    public const MODE_UNIX = 'unix';
    
    /** @var int | null $timeoutClose */
    private $timeoutClose;
    
    /** @var string $mode */
    private $mode = 'tcp';
    
    /** @var ServerInterface $server */
    private $server;

    /**
     * @param string|null $uri
     * @param array $options
     *
     * @return SocketInterface
     *
     * @throws \Exception
     */
    public function connect(string $uri = null, array $options = []): SocketInterface
    {
        if (is_null($this->loop)) {
            $this->loop = LoopFactory::getLoop();
        }

        if (is_null($this->uri) && !is_null($uri)) {
            $this->uri = $uri;
        }

        if (is_null($this->uri)) {
            throw new \Exception('Not found uri socket.');
        }
        
        switch ($this->mode) {
            case self::MODE_TCP:
                $this->server = new TcpServer($this->uri, $this->loop, $options);
                break;
            case self::MODE_UNIX:
                if (file_exists($this->uri)) {
                    unlink($this->uri);
                }
                
                $this->server = new UnixServer($this->uri, $this->loop, $options);
                break;
        }
        
        if (is_null($this->server)) {
            throw new \RuntimeException('Problem to create socket server');
        }

        $this->server->on('connection', function (ConnectionInterface $connection) {
            $this->connection = $connection;
            $connection->on('data', function ($data) use ($connection) {
                $this->handleDataSocket($connection, $data);
            });

            $connection->on('error', function (\Exception $e) {
                if ($this->isDebug()) {
                    echo 'error: ' . $e->getMessage();
                }
                
                $this->emit(self::SOCKET_ERROR_CHANNEL, [$e->getMessage()]);
            });

            $connection->on('close', function () {
                $this->emit(self::SOCKET_DISCONNECT_CLIENT_CHANNEL);
            });
            
            $this->emit(self::SOCKET_CONNECT_CLIENT_CHANNEL, [$connection]);
        });

        $this->server->on('error', function (\Exception $e) {
            if ($this->isDebug()) {
                echo 'error: ' . $e->getMessage() . PHP_EOL;   
            }
        });

        if (!is_null($this->timeoutClose)) {
            $this->loop->addTimer($this->timeoutClose, function (TimerInterface $timer) {
                $this->close();
                $this->loopStop();
                $this->emit(self::SOCKET_CLOSE_SERVER_CHANNEL);
            });
        }

        return $this;
    }
    
    public function close()
    {
        if (!is_null($this->server)) {
            $this->server->close();
            $this->server = null;
        }
    }

    /**
     * @return int | null
     */
    public function getTimeoutClose(): ?int
    {
        return $this->timeoutClose;
    }

    /**
     * @param int | null $timeoutClose
     *
     * @return SocketServerInterface
     */
    public function setTimeoutClose(?int $timeoutClose): SocketServerInterface
    {
        $this->timeoutClose = $timeoutClose;
        
        return $this;
    }

    /**
     * @return string
     */
    public function getMode(): string
    {
        return $this->mode;
    }

    /**
     * @param string $mode
     *
     * @return SocketServerInterface
     */
    public function setMode(string $mode): SocketServerInterface
    {
        $this->mode = $mode;
        
        return $this;
    }
}
