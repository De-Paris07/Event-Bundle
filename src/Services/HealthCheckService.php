<?php

declare(strict_types=1);

namespace ClientEventBundle\Services;

use ClientEventBundle\Loop\Constants;
use ClientEventBundle\Loop\SocketMessage;
use ClientEventBundle\Socket\SocketClient;
use ClientEventBundle\Socket\SocketClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class HealthCheckService
 *
 * @package ClientEventBundle\Services
 */
class HealthCheckService
{
    /** @var ContainerInterface $container */
    private $container;

    /** @var SocketClientInterface $socket */
    private $socket;
    
    /**
     * HealthCheckService constructor.
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->socket = new SocketClient();
    }

    /**
     * @return array|null
     * 
     * @throws \Exception
     */
    public function getCurrentInfo(): ?array
    {
        $response = null;
        $this->socket->setUri('unix://' . $this->container->getParameter('client_event.socket_name'));
        
        $this->socket
            ->setTimeoutConnect(1)
            ->setDebug(false)
            ->setTimeoutSocketWrite(5)
            ->writeWithoutListening(
                new SocketMessage(Constants::SOCKET_CHANNEL_HEALTH_CHECK),
                function (SocketMessage $message) use (&$response) {
                    $response = $message->getData();
                },
                function () {
                    throw new \Exception('Возникла ошибка при запросе');
                },
                false,
                ['happy_eyeballs' => false]
            );
        
        return $response;
    }

    /**
     * @param string $serviceName
     * 
     * @return array | null
     * 
     * @throws \Exception
     */
    public function getInfoByServiceName(string $serviceName): ?array
    {
        $response = null;
        $this->socket->setUri($this->container->getParameter('client_event.event_server_address') . ':'
            . $this->container->getParameter('client_event.event_server_tcp_socket_port')
        );

        $this->socket
            ->setTimeoutConnect(3)
            ->setDebug(false)
            ->setTimeoutSocketWrite(5)
            ->writeWithoutListening(
                new SocketMessage(Constants::SOCKET_CHANNEL_HEALTH_CHECK, ['serviceName' => $serviceName]),
                function (SocketMessage $message) use (&$response) {
                    $response = $message->getData();
                },
                function () {
                    throw new \Exception('Возникла ошибка при запросе');
                },
                false,
                ['happy_eyeballs' => false]
            );
        
        return $response;
    }

    public function getInfoAllServices()
    {
        $response = null;
        $this->socket->setUri($this->container->getParameter('client_event.event_server_address') . ':'
            . $this->container->getParameter('client_event.event_server_tcp_socket_port')
        );
        
        $sendServices = function (array $services) use (&$response) {
            foreach ($services as $service) {
                $countServices = count($services);
                $serviceName = $service['name'];
                $this->socket
                    ->setWaitForAnAnswer(true)
                    ->setTimeoutSocketWrite(5)
                    ->write(
                        new SocketMessage(Constants::SOCKET_CHANNEL_HEALTH_CHECK, ['serviceName' => $serviceName]),
                        function (SocketMessage $serviceResponse) use ($serviceName, &$response, &$countServices) {
                            $response[$serviceName] = $serviceResponse->getData();

                            if (count($response) === $countServices) {
                                $this->socket->close();
                                $this->socket->stop();
                                $this->socket->removeAllListeners();
                            }
                        },
                        function () use ($serviceName, &$response, &$countServices) {
                            $response[$serviceName] = '';

                            if (count($response) === $countServices) {
                                $this->socket->close();
                                $this->socket->stop();
                                $this->socket->removeAllListeners();
                            }
                        }
                    );
            }
        };

        $this->socket->on(SocketClient::SOCKET_CONNECT_CHANNEL, function () use ($sendServices) {
            $this->socket
                ->write(
                    new SocketMessage(Constants::SOCKET_CHANNEL_SERVICES_LIST, []),
                    function (SocketMessage $servicesList) use ($sendServices) {
                        $sendServices($servicesList->getField('services'));
                    },
                    function () {
                        throw new \Exception('Event server did not respond to request');
                    }
                );
        });

        $this->socket
            ->setTimeoutConnect(1)
            ->setDebug(false)
            ->connect(null, ['happy_eyeballs' => false]);
        
        $this->socket->start();
        
        return $response;
    }
}
