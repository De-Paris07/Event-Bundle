<?php

declare(strict_types=1);

namespace ClientEventBundle\Command;

use ClientEventBundle\Loop\ClientTrait;
use ClientEventBundle\Loop\Constants;
use ClientEventBundle\Loop\SocketMessage;
use ClientEventBundle\Util\HealthChecker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class CollectingServerStateCommand
 *
 * @package ClientEventBundle\Command
 */
class CollectingServerStateCommand extends Command
{
    use ClientTrait;

    protected static $defaultName = 'event:server:state';

    /**
     * CollectingServerStateCommand constructor.
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        parent::__construct();

        $this->container = $container;
    }

    protected function configure()
    {
        $this->setDescription('Процесс собирающий метрики состояния сервера');
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
        
        if (is_null($this->clientSocket->getConnection())) {
            echo 'Нет подключения к сокету';
            
            return 0;
        }
        
        $stat = HealthChecker::getServerInfo();
        
        $this->clientSocket
            ->setTimeoutConnect(1)
            ->setWaitForAnAnswer(false)
            ->write(new SocketMessage(Constants::SOCKET_CHANNEL_HEALTH_CHECK_DATA, $stat));

        return 0;
    }
}
