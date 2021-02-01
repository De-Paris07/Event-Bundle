<?php

namespace ClientEventBundle\Loop;

use ClientEventBundle\Socket\SocketMessageInterface;
use ClientEventBundle\Socket\SocketServer;
use ClientEventBundle\Socket\SocketServerInterface;
use ClientEventBundle\Util\HealthChecker;
use Evenement\EventEmitterTrait;
use React\EventLoop\LoopInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

trait ServerTrait
{
    use EventEmitterTrait;
    
    /** @var LoopInterface $lopp */
    protected $loop;

    /** @var CommandInterface[] $commands */
    private $commands = [];

    /** @var Screen $screen */
    private $screen;
    
    /** @var string $socketUri */
    private $socketUri;

    /** @var SocketServerInterface  $server */
    protected $server;

    /**
     * @param CommandInterface $command
     *
     * @return CommandInterface
     */
    public function addCommand(CommandInterface $command): CommandInterface
    {
        if (key_exists($command->getCommand(), $this->commands)) {
            return $command;
        }
        
        $this->commands[$command->getCommand()] = $command;
        $command->setLoop($this->loop);

        $command->on(Constants::CHANNEL_CLIENT_CONSOLE, function ($payload) {
            $this->screen->info($payload);
        });

        $command->on(Constants::START_PROCESS_EVENT, function ($payload) {
            $this->screen->comment($payload);
        });

        $command->on(Constants::STOP_PROCESS_EVENT, function ($payload) {
            $this->screen->comment($payload);
        });

        $command->on(Constants::RESTART_PROCESS_EVENT, function ($payload) {
            $this->screen->comment($payload);
        });

        $command->on(Constants::ERROR_PROCESS_EVENT, function ($payload) {
            $this->screen->warning($payload);
        });

        $command->on(Constants::ERROR_CRON_PROCESS_EVENT, function (string $cronName, int $pid, string $error) {
            $this->telegramLogger->setFail(new \Exception($error), "Cron: {$cronName}[$pid]\n");
        });

        $command->on(Constants::EXIT_PROCESS_EVENT, function ($payload) {
            $this->screen->info($payload);
        });

        return $command;
    }

    /**
     * @param CommandInterface[] $commands
     */
    public function addCommands(array $commands)
    {
        foreach ($commands as $command) {
            $this->addCommand($command);
        }
    }

    public function startCommands()
    {
        foreach ($this->commands as $command) {
            $command->start();
        }
    }

    public function stopCommands()
    {
        foreach ($this->commands as $command) {
            $command->stop();
        }
    }

    /**
     * @param int | null $pid
     *
     * @return CommandInterface | null
     */
    public function getCommandByPid(?int $pid): ?CommandInterface
    {
        if (is_null($pid)) {
            return null;
        }

        foreach ($this->commands as $command) {
            if ($command->getProcess($pid)) {
                return $command;
            }
        }

        return null;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    private function initServer(InputInterface $input, OutputInterface $output)
    {
        $this->loop = LoopFactory::getLoop();
        $this->screen = new Screen(new SymfonyStyle($input, $output));
        $this->initSocketServer();

        $stopHandler = function (int $signal) {
            $this->server->close();
            exit($signal);
        };

        $this->loop->addSignal(SIGINT, $stopHandler);
        $this->loop->addSignal(SIGTERM, $stopHandler);
        $this->loop->addSignal(SIGHUP, $stopHandler);
        $this->loop->addSignal(SIGBUS, $stopHandler);
        $this->loop->addSignal(SIGSYS, $stopHandler);
        $this->loop->addSignal(SIGTRAP, $stopHandler);
        $this->loop->addSignal(SIGTSTP, $stopHandler);

//        $this->loop->addPeriodicTimer(1, function ($timer) {
//            if ((memory_get_peak_usage(true) / 1024 / 1024) > 100) {
//                $this->stopCommands();
//                $this->server->close();
//                $this->loop->cancelTimer($timer);
//                exit();
//            }
//        });
    }
    
    private function initSocketServer()
    {
        $this->server = new SocketServer();
        $this->server
            ->setUri($this->socketUri)
            ->setLoop($this->loop)
            ->setMode(SocketServer::MODE_UNIX)
            ->connect();

        // канал что подключился новый клиент
        $this->server->on(Constants::SOCKET_CHANNEL_CLIENT_CONNECT, function (SocketMessageInterface $message) {
            if (is_null($command = $this->getCommandByPid($message->getPid()))) {
                return;
            }
            
            $command->setSocket($this->server);
            $command->setSocketPid($message->getPid(), $message->getConnection());
            
            $this->server
                ->setConnection($message->getConnection())
                ->setWaitForAnAnswer(false)
                ->write(new SocketMessage(Constants::SOCKET_CHANNEL_CLIENT_SETTINGS, $command->getSettings(), $message->getXid()));
        });

        // канал для получения количества задач для обработки от процесса
        $this->server->on(Constants::SOCKET_CHANNEL_CLIENT_JOB_READY, function (SocketMessageInterface $message) {
            if (is_null($count = $message->getField('countJobReady'))) {
                return;
            }

            if (is_null($command = $this->getCommandByPid($message->getPid()))) {
                return;
            }
            
            $command->setCountJobReady($count);
        });
        
        // канал получения количества задач у демона
        $this->server->on('count.task.queue.worker', function (SocketMessageInterface $message) {
            $processName = $message->getField('processName');
            $response = [
                'error' => null,
                'count' => 0,
            ];
            
            if (is_null($command = $this->getCommandByName($processName))) {
                $response['error'] = 'Not found command';
            }

            if (!is_null($command)) {
                $response['count'] = $command->getCountJobReady();   
            }
            
            $this->server
                ->setConnection($message->getConnection())
                ->setWaitForAnAnswer(false)
                ->write(new SocketMessage($message->getChannel(), $response, $message->getXid()));
        });

        // отдача состояния сервера и всех воркеров
        $this->server->on(Constants::SOCKET_CHANNEL_HEALTH_CHECK, function (SocketMessageInterface $message) {
            $info = $this->getServerInfo();
            
            $this->server
                ->setConnection($message->getConnection())
                ->setWaitForAnAnswer(false)
                ->write(new SocketMessage($message->getChannel(), $info, $message->getXid()));
        });

        // логируем что отправили по сокету клиенты
        $this->server->on(SocketServer::SOCKET_RAW_MESSAGE, function ($message) {
            if (Constants::SOCKET_CHANNEL_CLIENT_JOB_READY === $message['channel'] ) {
                return;
            }
            
            if (is_null($command = $this->getCommandByPid($message['pid']))) {
                return;
            }

            $command->emit(Constants::CHANNEL_CLIENT_WRITE, [$message]);
        });
    }

    /**
     * @return array
     */
    private function getServerInfo(): array
    {
        $data = HealthChecker::getServerInfo();

        foreach ($this->commands as $command) {
            $worker = [];

            if (!is_null($command->getSchedule())) {
                $worker['type'] = 'cron';
                $worker['cmd'] = $command->getCommand();
                $worker['schedule'] = $command->getSchedule();
            }
            
            if ($command->isConsumer()) {
                $worker['type'] = 'consumer';
                $worker['channel'] = $command->getTube();
                
            }
            
            if (is_null($command->getSchedule()) && !$command->isConsumer()) {
                $worker['type'] = 'cmd';
                $worker['cmd'] = $command->getCommand();
            }

            $worker['timeLastStart'] = !is_null($command->getLastScheduledStart())
                ? $command->getLastScheduledStart()->format('Y-m-d H:i:s')
                : null;
            $worker['dateStart'] = !is_null($command->getStartTime())
                ? $command->getStartTime()->format('Y-m-d H:i:s')
                : null;
            $worker['countInstanse'] = $command->getCountProcesses();
            $worker['countJob'] = $command->getCountJobReady();
            $worker['isDaemon'] = $command->isDaemon();
            
            $data['workers'][$command->getName()] = $worker;
        }
        
        return $data;
    }

    /**
     * @param $name
     * 
     * @return CommandInterface|null
     */
    private function getCommandByName($name): ?CommandInterface
    {
        foreach ($this->commands as $command) {
            if ($name === $command->getName()) {
                return $command;
            }
        }

        return null;
    }
}
