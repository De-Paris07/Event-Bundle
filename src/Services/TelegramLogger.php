<?php

namespace ClientEventBundle\Services;

use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use GuzzleHttp\Client;

/**
 * Class TelegramLogger
 *
 * @package ClientEventBundle\Services
 */
class TelegramLogger
{
    /** @var ContainerInterface $container */
    private $container;
    
    /** @var Client $guzzleClient */
    private $guzzleClient;

    /** @var string $eventId */
    private $eventId = null;

    /** @var string $eventName */
    private $eventName = null;
    
    /** @var string $tokenBot */
    private $tokenBot;

    /** @var string $chatId */
    private $chatId;

    /** @var string[] $environments */
    private $environments;
    
    /** @var ?string $proxy */
    private $proxy;
    
    /** @var boolean $useProxy */
    private $useProxy;
    
    /** @var bool $enabled */
    private $enabled;

    /**
     * TelegramLogger constructor.
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $telegramConfig = $container->getParameter('client_event.telegram');
        $this->enabled = $telegramConfig['enabled'];
        $this->useProxy = $telegramConfig['use_proxy'];
        $this->tokenBot = $telegramConfig['token'];
        $this->proxy = $telegramConfig['socks5'];
        $this->guzzleClient = new Client();
        $this->chatId = 'prod' === $this->container->getParameter('kernel.environment') ?
            $container->getParameter('telegram.eventProd')['chat_id'] :
            $container->getParameter('telegram.eventDev')['chat_id'];
        $this->environments = 'prod' === $this->container->getParameter('kernel.environment') ?
            $container->getParameter('telegram.eventProd')['environments'] :
            $container->getParameter('telegram.eventDev')['environments'];
    }

    /**
     * @param bool $useProxy
     *
     * @return TelegramLogger
     */
    public function setUseProxy(bool $useProxy): TelegramLogger
    {
        $this->useProxy = $useProxy;

        return $this;
    }

    /**
     * @param string $chatId
     *
     * @return $this
     */
    public function setChatId(string $chatId): TelegramLogger
    {
        $this->chatId = $chatId;

        return $this;
    }

    /**
     * @param array $environments
     *
     * @return $this
     */
    public function setEnvironments(array $environments): TelegramLogger
    {
        $this->environments = $environments;

        return $this;
    }

    /**
     * @param string $eventId
     * @param string $eventName
     *
     * @return $this
     */
    public function setCurrentEvent(string $eventId, string $eventName): TelegramLogger
    {
        $this->eventId = $eventId;
        $this->eventName = $eventName;

        return $this;
    }

    /**
     * @param string $tokenBot
     */
    public function setTokenBot(string $tokenBot)
    {
        $this->tokenBot = $tokenBot;
    }

    /**
     * @param $exception
     * @param string|null $clientMessage
     */
    public function setFail($exception, string $clientMessage = null)
    {
        $serviceName = $this->container->getParameter('client_event.service_name');
        $currentEnvironment = $this->container->getParameter('kernel.environment');
        $message = '';

        if (!in_array($currentEnvironment, $this->environments)) {
            return;
        }

        if ('prod' === $currentEnvironment) {
            $message = "‼️‼️ALERT‼️‼️\n";
        }
        
        $message = $message . "Environment $currentEnvironment \n" . (!is_null($this->eventId) ? "EventId: {$this->eventId} \n" : '') . (!is_null($this->eventName) ? "EventName: {$this->eventName} \n" : '') . "Service: $serviceName \n";

        if (!is_null($clientMessage)) {
            $message = $message . $clientMessage;
        }

        $message = $message . "Message: {$exception->getMessage()} \n \n";
        $trace = stristr($exception->getTraceAsString(), '#3', true);
        
        $message = $message . $trace;
        
        $this->write($message);
    }

    /**
     * @param string | array $message
     */
    public function log($message)
    {
        $serviceName = $this->container->getParameter('client_event.service_name');
        $currentEnvironment = $this->container->getParameter('kernel.environment');
        $info = "Service: $serviceName \n";

        if ('prod' === $currentEnvironment) {
            return;
        }

        if (!is_null($this->eventName) && !is_null($this->eventId)) {
            $info .= "EventId: {$this->eventId} \nEventName: {$this->eventName} \n";
        }
        
        if (is_array($message)) {
            $message = json_encode($message, 128 | 256);
        }

        if (is_object($message)) {
            throw new RuntimeException('To write a message to the log, a string or array is expected, an object is transferred.');
        }

        $this->write("{$info}MessageLog: \n $message");
    }

    /**
     * @param string | array $message
     */
    public function info($message)
    {
        $currentEnvironment = $this->container->getParameter('kernel.environment');

        if (!in_array($currentEnvironment, $this->environments)) {
            return;
        }

        if (is_array($message)) {
            $message = json_encode($message, 128 | 256);
        }

        if (is_object($message)) {
            throw new RuntimeException('To write a message a string or array is expected, an object is transferred.');
        }

        $this->write("$message");
    }

    /**
     * @param string $message
     */
    private function write(string $message)
    {
        if (!$this->enabled) {
            return;
        }

        if ($this->useProxy && (is_null($this->proxy) || '' === $this->proxy)) {
            return;
        }
        
        if (is_null($this->chatId) || is_null($this->tokenBot) || '' === $this->tokenBot || '' === $this->chatId) {
            return;
        }

        $options = [
            'body' => json_encode(['chat_id' => $this->chatId, 'text' => $message]),
            'headers' => ['Content-Type' => 'application/json']
        ];

        if ($this->useProxy) {
            $options['proxy'] = 'socks5://' . $this->proxy;
        }

        try {
            $response = $this->guzzleClient->request('POST', "https://api.telegram.org/{$this->tokenBot}/sendMessage", $options);

            if (!is_null($response)) {
                echo "Message sent to telegram chat '$this->chatId'" . PHP_EOL;
            }
        } catch (\Throwable $exception){
            echo "Error message sent to telegram chat '$this->chatId'" . $exception->getMessage();
        }
    }
}
