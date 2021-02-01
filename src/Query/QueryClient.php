<?php

namespace ClientEventBundle\Query;

use ClientEventBundle\Event\QueryEvent;
use ClientEventBundle\Exception\ConnectTimeoutException;
use ClientEventBundle\Exception\NoEventServer;
use ClientEventBundle\Loop\LoopFactory;
use ClientEventBundle\Loop\SocketMessage;
use ClientEventBundle\Producer\EventProducer;
use ClientEventBundle\Producer\ProducerInterface;
use ClientEventBundle\Services\CacheService;
use ClientEventBundle\Services\EventServerService;
use ClientEventBundle\Services\QueueService;
use ClientEventBundle\Socket\SocketClient;
use React\EventLoop\Factory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class QueryClient
 *
 * @package ClientEventBundle\Query
 */
class QueryClient implements QueryClientInterface
{
    public const QUERY_EVENT_NAME = 'queue.query';
    
    /** @var ProducerInterface $producer */
    private $producer;

    /** @var EventServerService $eventServerService */
    private $eventServerService;
    
    /** @var CacheService $cacheService */
    private $cacheService;

    /** @var SocketClient $socket */
    private $socket;
    
    /** @var bool $useQuery */
    private $useQuery = false;
    
    /** @var ContainerInterface $container */
    private $container;

    /** @var int $timeout */
    private $timeout = 30;

    /** @var bool $blokking */
    private $blokking = true;

    /**
     * QueryClient constructor.
     *
     * @param ProducerInterface $producer
     * @param EventServerService $eventServerService
     * @param CacheService $cacheService
     * @param ContainerInterface $container
     */
    public function __construct(
        ProducerInterface $producer,
        EventServerService $eventServerService,
        CacheService $cacheService,
        ContainerInterface $container
    ) {
        $this->container = $container;
        $this->producer = $producer;
        $this->eventServerService = $eventServerService;
        $this->cacheService = $cacheService;
        $this->useQuery = $this->container->getParameter('client_event.use_query');
        $this->socket = (new SocketClient($this->getSocketUri()))
            ->setIsReconnect(false)
            ->setTimeoutConnect(1)
            ->setTimeoutSocketWrite(30);
    }

    public function __destruct()
    {
        $this->socket->close();
        $this->socket->stop();
        $this->socket->removeAllListeners();
    }

    /**
     * @param string $route
     * @param $data
     *
     * @return QueryResponse
     *
     * @throws \Exception
     */
    public function query(string $route, $data = null): QueryResponse
    {
        if (!$this->useQuery) {
            throw new \RuntimeException('To use the query function in the queue, you must enable the "client_event.use_query"');
        }

        $this->doDispatch();
        $response = null;
        $producer = $this->producer;
        
        $event = new QueryEvent();
        $event->setEventId(EventProducer::generateEventId(QueryClient::QUERY_EVENT_NAME));
        $event->setTimeout($this->timeout);
        $event->setRoute($route);
        $event->setQueryData($data);
        $event->setAdress($this->getTcpSocketUri());
        
        if (!$this->cacheService->checkRoute($route)) {
            return $this->createResponse([
                'status' => QueryResponse::STATUS_ERROR,
                'message' => 'Rout not found',
                'code' => Response::HTTP_NOT_FOUND,
            ]);
        }

        $loop = $this->blokking ? Factory::create() : LoopFactory::getLoop();
        $this->socket->setLoop($loop);

//        $this->validate($event->getQueryData(), $this->cacheService->getRouteValidateSchema($route));

        $this->socket->on(SocketClient::SOCKET_CONNECT_CHANNEL, function () use ($event, $producer, $route, &$response) {
            $this->socket->setTimeoutSocketWrite($this->timeout)
                ->write(
                    new SocketMessage(SocketClient::SOCKET_QUERY_CALLBACK_CHANNEL, [
                        'id' => $event->getEventId(), 
                        'route' => $route, 
                        'timeout' => $this->timeout,
                        'token' => $this->eventServerService->getServerToken(),
                    ]),
                    function (SocketMessage $socketMessage) use ($event, $producer, &$response) {
                        $this->socket->removeAllListeners(SocketClient::SOCKET_CONNECT_CHANNEL);

                        if (!$socketMessage->getField('success') && !is_null($error = $socketMessage->getField('error'))) {
                            if (ob_get_length() > 0) {
                                ob_clean();
                            }

                            $response = $this->createResponse([
                                'status' => QueryResponse::STATUS_ERROR,
                                'message' => $error,
                                'code' => Response::HTTP_INTERNAL_SERVER_ERROR,
                            ]);

                            $this->socket->close();
                            $this->socket->stop();
                            $this->socket->removeAllListeners();
                        }
        
                        $producer->setTube(QueueService::QUERY_TUBE)
                            ->produce(QueryClient::QUERY_EVENT_NAME, $event);
                    },
                    function () use (&$response) {
                        $response = $this->createResponse([
                            'status' => QueryResponse::STATUS_ERROR,
                            'message' => 'Not found result',
                            'code' => Response::HTTP_GATEWAY_TIMEOUT,
                        ]);

                        $this->socket->close();
                        $this->socket->stop();
                        $this->socket->removeAllListeners();

                        if (ob_get_length() > 0) {
                            ob_clean();
                        }
                    }
            );
        });

        $this->socket->connect();
            
        $this->socket->on($event->getEventId(), function (SocketMessage $message) use (&$response) {
            echo "Получили результат сокет" . PHP_EOL;
            $response = $this->createResponse($message->getData());
            $this->socket->close();
            $this->socket->stop();
            $this->socket->removeAllListeners();
        });

        $cacheTimer = $loop->addPeriodicTimer(0.001, function ($timer) use (&$response, $event) {
            if (is_null($data = $this->cacheService->getQueryResponse($event->getEventId()))) {
                return;
            }

            echo "Получили результат кэш " . PHP_EOL;
            $response = $this->createResponse($data);
            $this->socket->close();
            $this->socket->stop();
        });

        $timeoutTimer = $loop->addTimer($this->timeout, function ($timer) use ($cacheTimer, $loop, &$response) {
            $response = $this->createResponse([
                'status' => QueryResponse::STATUS_ERROR,
                'message' => 'Not found result',
                'code' => Response::HTTP_GATEWAY_TIMEOUT,
            ]);

            $this->socket->removeAllListeners();
            $loop->cancelTimer($cacheTimer);
            $this->socket->stop();
        });

        try {
            $this->socket->start();
        } catch (ConnectTimeoutException $exception) {
            $this->socket->stop();
            $this->socket->setLoop($loop);
            $event->setResponseToCache(true);
            $producer->setTube(QueueService::QUERY_TUBE)
                ->produce(QueryClient::QUERY_EVENT_NAME, $event);
            
            $loop->run();
        }

        $this->socket->removeAllListeners();
        $loop->cancelTimer($cacheTimer);
        $loop->cancelTimer($timeoutTimer);

        if (is_null($response) || !($response instanceof QueryResponse)) {
            $response = new QueryResponse([]);
        }

        echo 'Ответ: ' . $event->getEventId() . ' ' . PHP_EOL;

        if (ob_get_length() > 0) {
            ob_clean();
        }

        return $response;
    }

    /**
     * @return int
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * @param int $timeout
     *
     * @return QueryClientInterface
     */
    public function setTimeout(int $timeout): QueryClientInterface
    {
        $this->timeout = $timeout;

        return $this;
    }

    /**
     * @return bool
     */
    public function isBlokking(): bool
    {
        return $this->blokking;
    }

    /**
     * @param bool $blokking
     * 
     * @return QueryClient
     */
    public function setBlokking(bool $blokking): QueryClientInterface
    {
        $this->blokking = $blokking;
        
        return $this;
    }

    /**
     * @param array $data
     * 
     * @return QueryResponse
     */
    private function createResponse(array $data): QueryResponse
    {
        if (!isset($data['status']) || !in_array($data['status'], [QueryResponse::STATUS_OK, QueryResponse::STATUS_ERROR])) {
            return new QueryResponse([]);
        }
        
        if (QueryResponse::STATUS_OK === $data['status']) {
            $response = new QueryResponse($data['data']);
            $response->setStatus($data['status'])
                ->setCode($data['code']);
            
            return $response;
        }
        
        $response = new QueryResponse([]);
        $response->setStatus(QueryResponse::STATUS_ERROR)
            ->setError($data['message'])
            ->setErrors($data['errors'] ?? null)
            ->setCode($data['code']);
        
        return $response;
    }

    private function doDispatch()
    {
        if (is_null($this->eventServerService->getEventServer())) {
            throw new NoEventServer();
        }
    }

    /**
     * @return string
     */
    private function getSocketUri(): string
    {
        return "unix:///tmp/{$this->container->getParameter('client_event.service_name')}_unix_event.sock";
    }

    /**
     * @return string
     */
    private function getTcpSocketUri(): string
    {
        return $this->container->getParameter('client_event.host') . ':' . $this->container->getParameter('client_event.tcp_socket_port');
    }

    /**
     * @param $data
     * @param $validateSchema
     *
     * @return bool
     */
    private function validate($data, $validateSchema)
    {
        return true;
    }
}
