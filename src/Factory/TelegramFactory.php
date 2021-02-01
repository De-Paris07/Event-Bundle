<?php

namespace ClientEventBundle\Factory;

use ClientEventBundle\Services\TelegramLogger;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;

/**
 * Class TelegramFactory
 *
 * @package ClientEventBundle\Factory
 */
class TelegramFactory
{
    /** @var ContainerInterface $container */
    private $container;

    /**
     * TelegramFactory constructor.
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @param string | null $chat
     * @param string | null $botName
     *
     * @return TelegramLogger | null
     */
    public function create(string $chat = null, string $botName = null): ?TelegramLogger
    {
        $token = null;
        
        if (is_null($chat)) {
            switch ($this->container->getParameter('kernel.environment')) {
                case 'prod':
                    $chat = 'eventProd';
                    break;
                case 'test':
                case 'dev':
                    $chat = 'eventDev';
                    break;
            }
        }

        try {
            $chatConfig = $this->container->getParameter("telegram.$chat");
        } catch (ParameterNotFoundException | \InvalidArgumentException $exception) {
            return null;
        }

        if (!is_null($botName)) {
            try {
                $token = $this->container->getParameter("telegramBot.$botName");
            } catch (ParameterNotFoundException | \InvalidArgumentException $exception) {
                return null;
            }
        }

        $instance = new TelegramLogger($this->container);
        $instance->setChatId($chatConfig['chat_id']);
        $instance->setEnvironments($chatConfig['environments']);
        
        if (!is_null($token)) {
            $instance->setTokenBot($token);
        }

        return $instance;
    }
}
