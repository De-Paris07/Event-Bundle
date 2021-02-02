<?php

namespace ClientEventBundle\Command;

use ClientEventBundle\Loop\ClientTrait;
use ClientEventBundle\Loop\Constants;
use ClientEventBundle\Loop\LoopFactory;
use ClientEventBundle\Loop\SocketMessage;
use ClientEventBundle\Services\TelegramLogger;
use ClientEventBundle\Socket\SocketClient;
use ClientEventBundle\Socket\SocketClientInterface;
use ClientEventBundle\Socket\SocketMessageInterface;
use ClientEventBundle\Socket\SocketServer;
use ClientEventBundle\Socket\SocketServerInterface;
use React\EventLoop\TimerInterface;
use React\Socket\ConnectionInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Throwable;

/**
 * Class EventAndQuerySocketCommand
 *
 * @package ClientEventBundle\Command
 */
class EventAndQuerySocketCommand extends Command
{
    use ClientTrait;
    
    protected static $defaultName = 'event:socket:handler';

    /** @var ContainerInterface $container */
    private $container;
    
    /** @var array<ConnectionInterface> $connections */
    private $callbacks;
    
    /** @var SocketClientInterface $eventServer */
    private $eventServerClient;
    
    /** @var SocketServerInterface $tcpServer */
    private $tcpServer;
    
    /** @var SocketServerInterface $unixServer */
    private $unixServer;

    /** @var TelegramLogger $telegramLogger */
    private $telegramLogger;
    
    /** @var array<ConnectionInterface> $clientConnections */
    private $clientConnections = [];
    
    /** @var int $serverHealthSendInterval */
    private $serverHealthSendInterval;
    
    /** @var int $serviceName */
    private $serviceName;

    /**
     * EventAndQuerySocketCommand constructor.
     *
     * @param ContainerInterface $container
     * @param TelegramLogger $telegramLogger
     */
    public function __construct(ContainerInterface $container, TelegramLogger $telegramLogger)
    {
        parent::__construct();

        $this->container = $container;
        $this->telegramLogger = $telegramLogger;
        $this->eventServerClient = new SocketClient($this->getEventServerSocketUri());
        $this->tcpServer = new SocketServer($this->getTcpSocketUri());
        $this->unixServer = new SocketServer($this->getUnixSocketUri());
        $this->unixServer->setMode(SocketServer::MODE_UNIX);
        
        $this->serverHealthSendInterval = $container->getParameter('client_event.server_health_send_interval');
        $this->serviceName = $container->getParameter('client_event.service_name');
    }

    protected function configure()
    {
        $this->setDescription('Сокет клиента, на который приходят ответы по запросам и общение с эвент-сервером');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int|void|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initClient();
        
        try {
            $this->tcpServer
                ->connect();
            $this->unixServer
                ->connect();

            if ($this->container->getParameter('client_event.server_health_send')) {
                $this->eventServerClient
                    ->setTimeoutConnect(10)
                    ->connect(null, ['happy_eyeballs' => false]);
            }
        } catch (Throwable $exception) {
            $this->telegramLogger->setFail($exception);
        }

        $this->handleEvent();
        $this->sendServerInfoToEventServer();

        try {
            $this->start();   
        } catch (Throwable $throwable) {
            $this->telegramLogger->setFail($throwable);
            throw $throwable;
        }

        return 0;
    }

    private function handleEvent()
    {
        $callbacks = &$this->callbacks;

        // подписка на регистрацию колбэка на получение ответа
        $this->unixServer->on(SocketServer::SOCKET_QUERY_CALLBACK_CHANNEL, function (SocketMessageInterface $message) use (&$callbacks) {
            $callbacks[$message->getField('id')] = $message->getConnection();
            echo 'Зарегистрировали новый колбэк - ' . $message->getField('id') . ' ' . PHP_EOL;
                            
            $this->unixServer
                ->setWaitForAnAnswer(false)
                ->setConnection($message->getConnection())
                ->write(new SocketMessage($message->getChannel(), [
                    'success' => true,
                ], $message->getXid()));
            echo ' Отправили в ответ ok ' . PHP_EOL;
        });
        
        // подписка на ответ на запрос
        $this->tcpServer->on(SocketServer::SOCKET_QUERY_RESPONSE_CHANNEL, function (SocketMessageInterface $message) use (&$callbacks) {
            // отправляем в ответ что данные получили, и отправитель может закрывать соединение
//            $this->tcpServer
//                ->setConnection($message->getConnection())
//                ->write(new SocketMessage($message->getChannel(), ['status' => 'ok'], $message->getXid()));

            if (!key_exists($message->getXid(), $callbacks)) {
                return;
            }

            // меняем подключение и отправляем ответ тому, кто делал запрос в систему
            $this->unixServer
                ->setConnection($callbacks[$message->getXid()])
                ->setWaitForAnAnswer(false)
                ->write(new SocketMessage($message->getXid(), $message->getData()));

            echo 'Отправили ответ на запрос ' . PHP_EOL;
            unset($callbacks[$message->getXid()]);
        });

        // канал для получения состояния сервера
        $this->tcpServer->on(Constants::SOCKET_CHANNEL_HEALTH_CHECK, function (SocketMessageInterface $message) {
            echo 'Пришел запрос о состоянии сервера ' . PHP_EOL;
            
            $this->clientSocket
                ->setWaitForAnAnswer(true)
                ->write(new SocketMessage(Constants::SOCKET_CHANNEL_HEALTH_CHECK),
                    function (SocketMessageInterface $response) use ($message) {
                        $this->tcpServer
                            ->setConnection($message->getConnection())
                            ->setWaitForAnAnswer(false)
                            ->write(new SocketMessage($message->getChannel(), $response->getData(), $message->getXid()));

                        echo 'Отправили состояние сервера ' . PHP_EOL;
                    },
                    function () use ($message) {
                        $this->tcpServer
                            ->setConnection($message->getConnection())
                            ->setWaitForAnAnswer(false)
                            ->write(new SocketMessage(
                                $message->getChannel(),
                                ['error' => 'The master process did not overlook the request'],
                                $message->getXid()
                            ));
                        echo 'Мастер сокет не ответил ' . PHP_EOL;
                    }
                );
        });
        
        $this->tcpServer->on(Constants::SOCKET_CHANNEL_PING, function (SocketMessageInterface $message) {
            $this->tcpServer
                ->setConnection($message->getConnection())
                ->setWaitForAnAnswer(false)
                ->write(new SocketMessage($message->getChannel(), [
                    'success' => true,
                ], $message->getXid()));
        });
    }

    /**
     * Переодическая отправка метрик процессора и памяти на эвент-сервер
     */
    private function sendServerInfoToEventServer()
    {
        if (!$this->container->getParameter('client_event.server_health_send')) {
            return;
        }
        
        $this->eventServerClient->on(SocketClient::SOCKET_CONNECT_CHANNEL, function () {
            $send = function () {
                $this->clientSocket
                    ->setWaitForAnAnswer(true)
                    ->write(new SocketMessage(Constants::SOCKET_CHANNEL_HEALTH_CHECK),
                        function (SocketMessageInterface $response) {
                            $this->eventServerClient
                                ->setWaitForAnAnswer(false)
                                ->write(new SocketMessage(
                                    Constants::SOCKET_CHANNEL_HEALTH_CHECK_DATA,
                                    [
                                        'serviceName' => $this->serviceName,
                                        'memory' => $response->getField('memory'),
                                        'cpu' => $response->getField('cpu'),
                                    ]
                                ));

                            echo 'Отправили состояние сервера по расписанию ' . PHP_EOL;
                        },
                        function () {
                            echo 'Мастер сокет не ответил на запрос состояния сервиса ' . PHP_EOL;
                        }
                    );
            };
            
            LoopFactory::getLoop()->addPeriodicTimer($this->serverHealthSendInterval, function (TimerInterface $timer) use ($send) {
                $send();
            });
            
            $send();
        });
    }

    /**
     * @return string
     */
    private function getTcpSocketUri(): string
    {
        return $this->container->getParameter('client_event.host') . ':' 
            . $this->container->getParameter('client_event.tcp_socket_port');
    }

    /**
     * @return string
     */
    private function getUnixSocketUri(): string
    {
        return "/tmp/{$this->container->getParameter('client_event.service_name')}_unix_event.sock";
    }

    /**
     * @return string
     */
    private function getEventServerSocketUri(): string
    {
        return $this->container->getParameter('client_event.event_server_address') . ':' 
            . $this->container->getParameter('client_event.event_server_tcp_socket_port');
    }

    /**
     * @return bool
     */
    private function isFreePort($port): bool
    {
        $cmd = 'ss -lntu | grep ":' . $port . '"';

        return empty(shell_exec($cmd));
    }
}
