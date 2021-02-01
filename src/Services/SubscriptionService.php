<?php

namespace ClientEventBundle\Services;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use ClientEventBundle\Entity\EventServer;
use ClientEventBundle\Exception\ValidateException;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Mapping\Factory\LazyLoadingMetadataFactory;
use Symfony\Component\Validator\Mapping\Loader\AnnotationLoader;

/**
 * Class SubscriptionService
 *
 * @package ClientEventBundle\Services
 */
class SubscriptionService
{
    /** @var ContainerInterface $container */
    private $container;

    /** @var EntityManagerInterface $entityManager */
    private $entityManager;

    /** @var EventServer $eventServerService */
    private $eventServerService;

    /** @var ClientInterface $client */
    private $guzzleClient;

    /** @var AnnotationReader $annotationReader */
    private $annotationReader;

    /**
     * SubscriptionService constructor.
     *
     * @param ContainerInterface $container
     * @param EventServerService $eventServerService
     * @param AnnotationReader $annotationReader
     */
    public function __construct(
        ContainerInterface $container,
        EventServerService $eventServerService,
        AnnotationReader $annotationReader
    ) {
        $this->container = $container;
        $this->entityManager = $container->get('doctrine.orm.entity_manager');
        $this->eventServerService = $eventServerService;
        $this->guzzleClient = new Client();
        $this->annotationReader = $annotationReader;
    }

    /**
     * @param string $serverAddress
     *
     * @return EventServer
     *
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws GuzzleException
     * @throws \ReflectionException
     */
    public function subscribe(string $serverAddress): EventServer
    {
        if (!$this->eventServerService->getClientToken()) {
            return $this->newSubscription($serverAddress);
        }

        return $this->updateSubscription($serverAddress);
    }

    /**
     * @param string $serverAddress
     *
     * @throws GuzzleException
     * @throws \ReflectionException
     */
    public function unsubscribe(string $serverAddress)
    {
        if (is_null($this->eventServerService->getEventServer())) {
            return;
        }
        
        $body = $this->getBody();
        $headers = [
            $this->eventServerService->getServerHeader() => $this->eventServerService->getServerToken(),
        ];

        $response = $this->request(
            "$serverAddress/unsubscribe",
            ['body' => json_encode($body), 'headers' => $headers]
        );

        $this->entityManager->remove($this->eventServerService->getEventServer());
        $this->entityManager->flush();
    }

    /**
     * @param string $serverAddress
     * @return EventServer
     *
     * @throws GuzzleException
     * @throws \ReflectionException
     */
    private function newSubscription(string $serverAddress): EventServer
    {
        $clientAuthToken = bin2hex(random_bytes(EventServer::TOKEN_LENGTH));

        //Создание и отправка запроса
        $body = $this->getBody();
        $headers = [
            EventServer::CLIENT_AUTH_HEADER => $clientAuthToken,
        ];

        $response = $this->request(
            "$serverAddress/subscribe",
            ['body' => json_encode($body), 'headers' => $headers]
        );

        //Получение авторизационного токена в ответе от сервера
        $serverAuthToken = current($response->getHeader(EventServer::SERVER_AUTH_HEADER));

        if (!$serverAuthToken) {
            throw new BadRequestHttpException('The application has not received an authentication token');
        }

        return $this->createToken($serverAuthToken, $clientAuthToken, $serverAddress);
    }

    /**
     * @param string $serverAddress
     *
     * @return EventServer
     *
     * @throws GuzzleException
     * @throws \ReflectionException
     */
    private function updateSubscription(string $serverAddress): EventServer
    {
        $body = $this->getBody();
        $headers = [
            $this->eventServerService->getServerHeader() => $this->eventServerService->getServerToken(),
        ];

        $response = $this->request(
            "$serverAddress/subscribe",
            ['body' => json_encode($body), 'headers' => $headers]
        );

        $token = $this->eventServerService->getEventServer();
        $token->setCurrentHost($this->container->getParameter('client_event.host'));
        $this->entityManager->flush();

        return $token;
    }

    /**
     * @param string $serverAuthToken
     * @param string $clientAuthToken
     * @param string $serverHost
     *
     * @return EventServer
     */
    private function createToken(string $serverAuthToken, string $clientAuthToken, string $serverHost): EventServer
    {
        if (null === $token = $this->entityManager->getRepository(EventServer::class)
                ->findOneBy(['clientToken' => $clientAuthToken])
        ) {
            $token = new EventServer();
        }

        $token->setClientHeader(EventServer::CLIENT_AUTH_HEADER);
        $token->setClientToken($clientAuthToken);
        $token->setServerHeader(EventServer::SERVER_AUTH_HEADER);
        $token->setServerToken($serverAuthToken);
        $token->setServerHost($serverHost);
        $token->setCurrentHost($this->container->getParameter('client_event.host'));

        $this->entityManager->persist($token);
        $this->entityManager->flush();

        return $token;
    }

    /**
     * @return array
     *
     * @throws \ReflectionException
     */
    private function getBody(): array
    {
        $host = $this->container->getParameter('client_event.host');
        $serviceName = $this->container->getParameter('client_event.service_name');
        $eventsSubscribe = $this->container->getParameter('client_event.events_subscribe');
        $receiveHistoricalData = $this->container->getParameter('client_event.receive_historical_data');
        $socketPort = $this->container->getParameter('client_event.tcp_socket_port');
        $routes = null;
        
        if ($this->container->hasParameter('client_event.routes')) {
            $routes = $this->container->getParameter('client_event.routes');
        }

        return [
            'name' => $serviceName,
            'receiveHistoricalData' => $receiveHistoricalData,
            'delivery' => [
                [
                    'type' => 'queue',
                    'address' => $serviceName,
                    'default' => true,
                ],
                [
                    'type' => 'rest',
                    'address' => "$host/event",
                    'default' => false,
                ],
                [
                    'type' => 'socket',
                    'address' => "$host:$socketPort",
                    'default' => false,
                ]
            ],
            'eventsSubscribe' => array_map(
                function ($key, $item) use ($serviceName) {
                    return [
                        'name' => $key,
                        'priority' => $item['priority'],
                        'channel' => isset($item['channel']) ? "$serviceName.{$item['channel']}" : null,
                        'servicePriority' => $item['servicePriority'],
                        'isRetry' => $item['retry'],
                        'countRetry' => $item['count_retry'],
                        'intervalRetry' => $item['interval_retry'],
                        'priorityRetry' => $item['priority_retry'],
                    ];
                },
                array_keys($eventsSubscribe),
                $eventsSubscribe
            ),
            'eventsSent' => $this->parseEventConstrains(),
            'callbackFailUrl' => "$host/event-process/fail",
            'callbackSuccessUrl' => "$host/event-process/success",
            'routes' => !is_null($routes) ?
                array_map(function ($item) {
                    return [
                        'name' => $item['name'],
                        'description' => $item['description'],
                        'validationScheme' => null,
                    ];
                }, $routes) :
                null,
        ];
    }

    /**
     * @param $url
     * @param $options
     *
     * @return ResponseInterface
     *
     * @throws GuzzleException
     */
    private function request($url, $options): ResponseInterface
    {
        try {
            return $this->guzzleClient->request(
                'POST',
                $url,
                $options
            );
        } catch (ClientException $exception) {
            $content = $exception->getResponse()->getBody()->getContents();
            $exceptionBody = $this->isJson($content) ? \GuzzleHttp\json_decode($content, true) : null;

            if (array_key_exists('errors', $exceptionBody)) {
                throw new ValidateException($exceptionBody['errors'], $exceptionBody['message']);
            }

            throw new HttpException($exception->getCode(), $exceptionBody['message']);
        } catch (ServerException $exception) {
            $content = $exception->getResponse()->getBody()->getContents();
            $exceptionBody = $this->isJson($content) ? \GuzzleHttp\json_decode($content, true) : null;

            throw new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR, $exceptionBody['message'] ? $exceptionBody['message'] : 'Internal Server Error');
        }
    }

    /**
     * @param $string
     *
     * @return bool
     */
    public static function isJson($string): bool
    {
        return !is_null($string) && is_string($string) && is_array(json_decode($string, true)) && json_last_error() == JSON_ERROR_NONE;
    }

    /**
     * @return array
     *
     * @throws \ReflectionException
     */
    private function parseEventConstrains(): array
    {
        $events = [];
        $loader = new AnnotationLoader($this->annotationReader);
        $lazyLoading = new LazyLoadingMetadataFactory($loader);
        $eventsSent = $this->container->getParameter('client_event.events_sent');

        foreach ($eventsSent as $event) {
            $child = [];
            $child['name'] = $event['name'];
            $metadata = $lazyLoading->getMetadataFor($event['target_object']);

            foreach ($metadata->getConstrainedProperties() as $propertyName) {
                $property = ['name' => $propertyName];
                foreach ($metadata->getPropertyMetadata($propertyName) as $propertyMetadata) {
                    foreach ($propertyMetadata->getConstraints() as $key => $cons) {
                        $property['constraints'][] = $cons->validatedBy();
                    }
                }

                $child['dataStructure'][] = $property;
            }

            $properties = $this->getNotConstrainProperties($event['target_object']);

            foreach ($properties as $item) {
                $child['dataStructure'][] = ['name' => $item, 'constraints' => null];
            }

            array_push($events, $child);
        }

        return $events;
    }

    /**
     * @param string $className
     *
     * @return array
     *
     * @throws \ReflectionException
     */
    private function getNotConstrainProperties(string $className): array
    {
        $reflClass = new \ReflectionClass($className);
        $properties = [];

        foreach ($reflClass->getProperties() as $property) {
            foreach ($this->annotationReader->getPropertyAnnotations($property) as $constraint) {
                if ($constraint instanceof Constraint) {
                    continue 2;
                }
            }

            array_push($properties, $property->name);
        }

        return $properties;
    }
}
