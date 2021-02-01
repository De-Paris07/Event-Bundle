<?php

namespace ClientEventBundle\Subscribers;

use ClientEventBundle\Event\QueryEvent;
use ClientEventBundle\Exception\ConnectTimeoutException;
use ClientEventBundle\Loop\SocketMessage;
use ClientEventBundle\Query\QueryClient;
use ClientEventBundle\Query\QueryRequest;
use ClientEventBundle\Query\QueryResponse;
use ClientEventBundle\Services\CacheService;
use ClientEventBundle\Services\LogService;
use ClientEventBundle\Services\TelegramLogger;
use ClientEventBundle\Socket\SocketClient;
use React\Socket\ConnectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class QuerySubscriber
 *
 * @package ClientEventBundle\Subscribers
 */
class QuerySubscriber implements EventSubscriberInterface
{
    private const CACHE_METHOD = 'redis';
    private const SOCKET_METHOD = 'socket';
    
    /** @var ContainerInterface $container */
    private $container;

    /** @var SocketClient $socketService */
    private $socketService;
    
    /** @var LogService $logService */
    private $logService;

    /** @var TelegramLogger $telegramLogger */
    private $telegramLogger;
    
    /** @var CacheService $cacheService */
    private $cacheService;
    
    /** @var SocketMessage $message */
    private $message;

    /** @var array $configRoutes */
    private $configRoutes = [];

    /** @var array<ConnectionInterface> $connections */
    private $connections = [];

    /**
     * QuerySubscriber constructor.
     *
     * @param ContainerInterface $container
     * @param LogService $logService
     * @param TelegramLogger $telegramLogger
     * @param CacheService $cacheService
     */
    public function __construct(
        ContainerInterface $container,
        LogService $logService,
        TelegramLogger $telegramLogger,
        CacheService $cacheService
    ) {
        $this->container = $container;
        $this->logService = $logService;
        $this->telegramLogger = $telegramLogger;
        $this->cacheService = $cacheService;
        $this->socketService = new SocketClient();
        $this->configRoutes = $this->container->getParameter('client_event.routes');

        $this->socketService->on(SocketClient::SOCKET_TIMEOUT_CONNECT_CHANNEL, function ($message) {
            $this->telegramLogger->setFail(new \Exception('Ошибка соединения: ' . $message));
        });
        
        $this->socketService->on(SocketClient::SOCKET_DISCONNECT_CHANNEL, function (ConnectionInterface $connection) {
            $address = str_replace('tcp://', '', $connection->getRemoteAddress());
            
            if (!isset($this->connections[$address])) {
                return;
            }
            
            unset($this->connections[$address]);
        });
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents(): array 
    {
        return [
            QueryClient::QUERY_EVENT_NAME => ['onHandle'],
        ];
    }

    /**
     * @param QueryEvent $event
     *
     * @throws \Exception
     */
    public function onHandle(QueryEvent $event)
    {
        $exception = null;
        $response = null;
        $request = null;
        $data = null;
        
        if (time() >= $event->getExpires() || ($event->getExpires() - time()) < 1) {
            return;
        }
        
        if (!isset($this->configRoutes[$event->getRoute()])) {
            return;
        }

        $isCache = $event->isResponseToCache();
        $request = new QueryRequest($event->getQueryData());
        echo 'пришел запрос: метод - ' . $event->getRoute() . ' ' . (string) $request . PHP_EOL;
        $config = $this->configRoutes[$event->getRoute()];
        
        try {
            $handler = $this->container->get($config['class']);
            /** @var QueryResponse $response */
            $response = $handler->{$config['method']}($request);
            $responseData = [
                'status' => QueryResponse::STATUS_OK,
                'code' => 200,
                'data' => $response->getData()
            ];
            $this->message = $message = new SocketMessage(
                SocketClient::SOCKET_QUERY_RESPONSE_CHANNEL,
                $responseData,
                $event->getEventId()
            );
        } catch (\Throwable $except) {
            $exception = $except;
            $statusCode = 500;

            if (method_exists($exception, 'getStatusCode')) {
                $statusCode = $exception->getStatusCode();
            }
            
            $data = ['status' => QueryResponse::STATUS_ERROR, 'message' => $exception->getMessage(), 'code' => $statusCode];
            
            if (property_exists($exception, 'errors') && !empty($exception->errors)) {
                $data['errors'] = $exception->errors;
            }
            
            $this->message = $message = new SocketMessage(
                SocketClient::SOCKET_QUERY_RESPONSE_CHANNEL,
                $data,
                $event->getEventId()
            );

            if (500 === $statusCode) {
                $this->telegramLogger->setFail(
                    new \Exception($exception->getMessage()),
                    "RouteName: {$event->getRoute()}\n"
                    . (isset($data['errors']) && is_array($data['errors']) ? "Errors: " . json_encode($data['errors']) : '')
                );
            }
        }

        if (!$isCache) {
            $this->sendMessage($event);
        }

        $this->cacheService->setQueryResponse($event->getEventId(), !is_null($response) ? $responseData : $data, $event->getTimeout());
        echo 'отправил данные в редис: ' . $event->getEventId() . PHP_EOL;
        
        $this->logService->responseQueryLog([
            'request' => (string) $request,
            'response' => !is_null($response) ? (string) $response : null,
            'error' => !is_null($exception) ? $exception->getMessage() : null,
            'validateErrors' => isset($data['errors']) && is_array($data['errors']) ? json_encode($data['errors'], 128 | 256) : null,
            'eventId' => $event->getEventId(),
        ]);
        
        $this->message = null;
        unset($exception, $response, $request, $config, $configRoutes, $data);
        
        gc_collect_cycles();
    }

    /**
     * @param QueryEvent $event
     */
    private function sendMessage(QueryEvent $event)
    {
        $message = $this->message;
        
        if (isset($this->connections[$event->getAdress()])) {
            try {
                $sent = \fwrite($this->connections[$event->getAdress()]->stream, (string) $message);
                echo "Отправил данные в открытый сокет $sent байт : " . $event->getEventId() . PHP_EOL;
            } catch (\Throwable $throwable) { }
            
            return;
        }

        try {
            $this->socketService->on(SocketClient::SOCKET_CONNECT_CHANNEL, function () use ($message, $event) {
                try {
                    $sent = \fwrite($this->socketService->getConnection()->stream, (string) $message);
                    echo "Отправил данные в сокет $sent байт : " . $event->getEventId() . PHP_EOL;
                } catch (\Throwable $throwable) { }
                
                $this->connections[$event->getAdress()] = $this->socketService->getConnection();
                $this->socketService->removeAllListeners(SocketClient::SOCKET_CONNECT_CHANNEL);
            });

            $this->socketService
                ->setIsReconnect(false)
                ->setTimeoutConnect($event->getTimeout())
                ->setTimeoutSocketWrite($event->getTimeout())
                ->setUri($event->getAdress())
                ->setWaitForAnAnswer(true)
                ->connect(null, ['happy_eyeballs' => false]);
        } catch (ConnectTimeoutException $except) {
        }
    }
}
