<?php

namespace ClientEventBundle\Socket;

use ClientEventBundle\Loop\SocketMessage;
use ClientEventBundle\Services\SubscriptionService;
use Evenement\EventEmitterTrait;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use React\Socket\ConnectionInterface;

/**
 * Class AbstractSocket
 *
 * @package ClientEventBundle\Socket
 */
abstract class AbstractSocket implements SocketInterface
{
    use EventEmitterTrait;
    
    const SOCKET_CONNECT_CHANNEL = 'socket.connect';
    const SOCKET_DISCONNECT_CHANNEL = 'socket.disconnect';
    const SOCKET_CLOSE_CHANNEL = 'socket.close';
    const SOCKET_CONNECT_CLIENT_CHANNEL = 'socket.connect.client';
    const SOCKET_DISCONNECT_CLIENT_CHANNEL = 'socket.disconnect.client';
    const SOCKET_CLOSE_SERVER_CHANNEL = 'socket.close.server';
    const SOCKET_ERROR_CHANNEL = 'socket.error';
    const SOCKET_TIMEOUT_CONNECT_CHANNEL = 'socket.timeout.connect';
    const SOCKET_RAW_MESSAGE = 'socket.raw.message';
    
    const SOCKET_QUERY_REQUEST_CHANNEL = 'socket.query.request';
    const SOCKET_QUERY_RESPONSE_CHANNEL = 'socket.query.response';
    const SOCKET_QUERY_CALLBACK_CHANNEL = 'socket.query.callback';
    
    const SOCKET_LOAD_ROUTES_CHANNEL = 'socket.load.routes';
    
    /** @var LoopInterface $loop */
    protected $loop;

    /** @var ConnectionInterface | null $connection */
    protected $connection;

    /** @var string $uri */
    protected $uri;

    /** @var bool $waitForAnAnswer */
    protected $waitForAnAnswer = true;

    /** @var bool $running */
    protected $running = false;
    
    /** @var int $timeoutSocketWrite */
    protected $timeoutSocketWrite = 30;
    
    /** @var string $messageDelimiter */
    protected $messageDelimiter = "\n";
    
    /** @var callable | null $parseMessageFun */
    protected $parseMessageFun;
    
    /** @var string $messageObject */
    protected $messageObject = SocketMessage::class;

    /** @var array<string> | null $buffer */
    private $buffer;

    /** @var bool $asyncWrite */
    private $asyncWrite = false;

    /** @var bool $debug */
    private $debug = true;

    public abstract function close();

    /**
     * @param string | null $uri
     * @param array $options
     *
     * @return $this
     */
    public abstract function connect(string $uri = null, array $options = []): SocketInterface;

    /**
     * AbstractSocket constructor.
     *
     * @param null $uri
     */
    public function __construct($uri = null)
    {
        $this->uri = $uri;
    }

    /**
     * @param SocketMessageInterface $message
     * @param callable|null $callback
     * @param callable|null $timeoutCallback
     *
     * @return bool
     */
    public function write(
        SocketMessageInterface $message,
        callable $callback = null,
        callable $timeoutCallback = null,
        int $timeout = null
    ): bool {
        $timer = null;

        if (is_null($this->getConnection())) {
            return false;
        }

        if (!$this->loop instanceof LoopInterface) {
            throw new \RuntimeException('To write to the socket, you must pass the "' . LoopInterface::class .  '" interface object');
        }

        if ($this->waitForAnAnswer) {
            // ставим таймер на ответ, если за это время не придет ответ, то вызовем колбэк
            $timer = $this->loop->addTimer(
                $timeout ?? $this->timeoutSocketWrite,
                function (TimerInterface $timer) use ($timeoutCallback, $message) {
                    $this->removeAllListeners($message->getXid());

                    if (!is_null($timeoutCallback) && is_callable($timeoutCallback)) {
                        $timeoutCallback();
                    }
                });

            // подписываемся на ответ запроса
            $this->on($message->getXid(), function (SocketMessageInterface $response) use ($callback, $timer, $message) {
                if (!is_null($timer) && !is_null($this->loop)) {
                    $this->loop->cancelTimer($timer);
                }

                $this->removeAllListeners($message->getXid());

                if (!is_null($callback) && is_callable($callback)) {
                    $callback($response);
                }
            });
        }

        return $this->connection
            ->setAsyncWrite($this->isAsyncWrite())
            ->write((string) $message);
    }

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
    ): bool {
        return $this
            ->setConnection($connection)
            ->write($message, $callback, $timeoutCallback);
    }

    public function start()
    {
        if (is_null($this->loop)) {
            return;
        }
        
        $this->loop->run();
    }

    public function stop()
    {
        $this->loopStop();
    }

    /**
     * @return ConnectionInterface | null
     */
    public function getConnection(): ?ConnectionInterface
    {
        return $this->connection;
    }

    /**
     * @param ConnectionInterface $conn
     *
     * @return SocketInterface
     */
    public function setConnection(ConnectionInterface $conn): SocketInterface
    {
        $this->connection = $conn;
        
        return $this;
    }

    /**
     * @return LoopInterface | null
     */
    public function getLoop(): ?LoopInterface
    {
        return $this->loop;
    }

    /**
     * @param LoopInterface $loop
     *
     * @return SocketInterface
     */
    public function setLoop(LoopInterface $loop): SocketInterface
    {
        $this->loop = $loop;

        return $this;
    }

    /**
     * @return string | null
     */
    public function getUri(): ?string
    {
        return $this->uri;
    }

    /**
     * @param string $uri
     *
     * @return SocketInterface
     */
    public function setUri(string $uri): SocketInterface
    {
        $this->uri = $uri;

        return $this;
    }

    /**
     * @return int
     */
    public function getTimeoutSocketWrite(): int
    {
        return $this->timeoutSocketWrite;
    }

    /**
     * @param int $timeoutSocketWrite
     *
     * @return SocketInterface
     */
    public function setTimeoutSocketWrite(int $timeoutSocketWrite): SocketInterface
    {
        $this->timeoutSocketWrite = $timeoutSocketWrite;

        return $this;
    }

    /**
     * @return bool
     */
    public function isWaitForAnAnswer(): bool
    {
        return $this->waitForAnAnswer;
    }

    /**
     * @param bool $value
     *
     * @return SocketInterface
     */
    public function setWaitForAnAnswer(bool $value): SocketInterface
    {
        $this->waitForAnAnswer = $value;

        return $this;
    }

    /**
     * @return string
     */
    public function getMessageDelimiter(): string
    {
        return $this->messageDelimiter;
    }

    /**
     * @param string $messageDelimiter
     * 
     * @return SocketInterface
     */
    public function setMessageDelimiter(string $messageDelimiter): SocketInterface
    {
        $this->messageDelimiter = $messageDelimiter;
        
        return $this;
    }

    /**
     * @return callable | null
     */
    public function getParseMessageFun(): ?callable
    {
        return $this->parseMessageFun;
    }

    /**
     * @param callable $parseMessageFun
     * 
     * @return SocketInterface
     */
    public function setParseMessageFun(callable $parseMessageFun): SocketInterface
    {
        $this->parseMessageFun = $parseMessageFun;
        
        return $this;
    }

    /**
     * @return string
     */
    public function getMessageObject(): string
    {
        return $this->messageObject;
    }

    /**
     * @param string $messageObject
     * 
     * @return SocketInterface
     */
    public function setMessageObject(string $messageObject): SocketInterface
    {
        if (!is_a($messageObject, SocketMessageInterface::class, true)) {
            throw new \RuntimeException(sprintf('The "%s" object of the message must implement the "%s" interface', $messageObject, SocketMessageInterface::class));
        }
        
        $this->messageObject = $messageObject;
        
        return $this;
    }

    /**
     * @return array | null
     */
    public function getBuffer(): ?array
    {
        return $this->buffer;
    }

    /**
     * @param string $buffer
     */
    public function addBuffer(string $buffer): self
    {
        $this->buffer[] = $buffer;

        return $this;
    }

    /**
     * @return bool
     */
    public function isAsyncWrite(): bool
    {
        return $this->asyncWrite;
    }

    /**
     * @param bool $asyncWrite
     *
     * @return SocketInterface
     */
    public function setAsyncWrite(bool $asyncWrite): SocketInterface
    {
        $this->asyncWrite = $asyncWrite;
        
        return $this;
    }

    /**
     * @return bool
     */
    public function isDebug(): bool
    {
        return $this->debug;
    }

    /**
     * @param bool $debug
     *
     * @return SocketInterface
     */
    public function setDebug(bool $debug): SocketInterface
    {
        $this->debug = $debug;

        return $this;
    }

    /**
     * @return bool
     */
    public function isRunning(): bool
    {
        return $this->running;
    }

    /**
     * @param ConnectionInterface $connection
     * @param string $data
     */
    protected function handleDataSocket(ConnectionInterface $connection, string $data)
    {
        $this->running = true;

        // если пришло одно сообщение и оно является не полным, кладем в буфер и ожидаем следующих частей
        if (!mb_stripos($data, $this->getMessageDelimiter()) && count($result = explode($this->getMessageDelimiter(), $data)) === 1) {
            $this->buffer[] = $data;
            $this->running = false;

            if ($this->isDebug()) {
                echo 'Положили в буфер часть сообщения: ' . $data . PHP_EOL;
            }
            
            return;
        }

        $data = explode($this->getMessageDelimiter(), $data);

        foreach ($data as $key => $rawMessage) {
            $item = $this->parseMessage($rawMessage);

            if (!is_array($item)) {
                continue;
            }

            $channel = $item['channel'] ?? null;
            $payload = $item['payload'] ?? [];
            $pid = $item['pid'] ?? null;
            $xid = $item['xid'] ?? null;

            if (!is_array($payload) || empty($channel)) {
                continue;
            }

            $this->setConnection($connection);
            /** @var SocketMessageInterface $message */
            $message = new $this->messageObject($channel, $payload, $xid, $pid);
            $message->setConnection($connection);
            $message->setRawData($rawMessage);

            if (!is_null($xid)) {
                $this->emit($xid, [$message]);
            }

            $this->emit($channel, [$message]);
            $this->emit(self::SOCKET_RAW_MESSAGE, [$item]);
        }

        $this->running = false;
    }

    /**
     * @param string $message
     *
     * @return array | null
     */
    protected function parseMessage(string $message): ?array
    {
        if ('' === $message) {
            return null;
        }

        if (!is_null($fun = $this->parseMessageFun)) {
            return $fun($message);
        }
        
        if (SubscriptionService::isJson($message)) {
           return json_decode($message, true);
        }

        if (is_null($this->buffer)) {
            $this->buffer[] = $message;
            
            if ($this->isDebug()) {
                echo 'Положили в буфер часть сообщения: ' . $message . PHP_EOL;   
            }

            return null;
        }

        $this->buffer[] = $message;
        $message = implode('', $this->buffer);

        if (SubscriptionService::isJson($message)) {
            $this->buffer = null;

            return json_decode($message, true);
        }

        if ($this->isDebug()) {
            echo 'Положили в буфер часть сообщения: ' . $message . PHP_EOL;
        }

        return null;
    }
    
    protected function loopStop()
    {
        if (is_null($this->loop)) {
            return;
        }

        $this->loop->stop();
        $this->loop = null;
    }
}
