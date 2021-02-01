<?php

namespace ClientEventBundle\Command;

use ClientEventBundle\Loop\SocketMessage;
use ClientEventBundle\Services\CacheService;
use ClientEventBundle\Socket\SocketClient;
use ClientEventBundle\Socket\SocketClientInterface;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class QueryListCommand
 *
 * @package ClientEventBundle\Command
 */
class QueryListCommand extends Command
{
    /** @var CacheService $cacheService */
    private $cacheService;

    /** @var ContainerInterface $container */
    private $container;

    /** @var SocketClientInterface $eventServer */
    private $eventServer;

    /**
     * QueryListCommand constructor.
     *
     * @param CacheService $cacheService
     * @param ContainerInterface $container
     */
    public function __construct(CacheService $cacheService, ContainerInterface $container)
    {
        parent::__construct();
        $this->container = $container;
        $this->cacheService = $cacheService;
        $this->eventServer = new SocketClient($this->getEventServerSocketUri());
        
    }

    protected function configure()
    {
        $this->setName('event:route:list')
            ->setDescription('Список доступных запрсов в системе');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int|void|null
     * 
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->eventServer
            ->setDebug(false)
            ->writeWithoutListening(
                new SocketMessage(SocketClient::SOCKET_LOAD_ROUTES_CHANNEL),
                function (SocketMessage $response) use ($input, $output) {
                    $data = $response->getField('data');
                    $io = new SymfonyStyle($input, $output);
                    $io->title('Список доступных роутов в системе');
                    $headers = ['Название', 'Описание', 'Сервис'];
                    $io->table($headers, $data);
                },
                function () {
                    throw new Exception('Возникла ошибка при запросе');
                },
                false,
                ['happy_eyeballs' => false]
            );

        return 0;
    }

    /**
     * @return string
     */
    private function getEventServerSocketUri(): string
    {
        return $this->container->getParameter('client_event.event_server_address') . ':' . $this->container->getParameter('client_event.event_server_tcp_socket_port');
    }
}
