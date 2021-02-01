<?php

namespace ClientEventBundle\Command;

use DateTime;
use ClientEventBundle\Loop\CommandInterface;
use ClientEventBundle\Loop\CommandLoop;
use ClientEventBundle\Loop\LoopTrait;
use ClientEventBundle\Loop\ServerTrait;
use ClientEventBundle\Services\ConfigService;
use ClientEventBundle\Services\TelegramLogger;
use ClientEventBundle\Util\HealthChecker;
use Pheanstalk\Pheanstalk;
use React\EventLoop\TimerInterface;
use React\Socket\ConnectionInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Throwable;

/**
 * Class MasterLoopCommand
 * 
 * @package ClientEventBundle\Command
 */
class MasterLoopCommand extends Command
{
    use ServerTrait;
    
    /** @var ConfigService $configService */
    private $configService;
    
    /** @var ConnectionInterface[] $connections */
    private $connections;

    /** @var TelegramLogger $telegramLogger */
    private $telegramLogger;
    
    /** @var ContainerInterface $container */
    private $container;

    /**
     * MasterLoopCommand constructor.
     *
     * @param ContainerInterface $container
     * @param ConfigService $configService
     * @param TelegramLogger $telegramLogger
     * @param string|null $name
     */
    public function __construct(ContainerInterface $container, ConfigService $configService, TelegramLogger $telegramLogger, string $name = null)
    {
        parent::__construct($name);
        $this->configService = $configService;
        $this->socketUri = $socket = $container->getParameter('client_event.socket_name');
        $this->telegramLogger = $telegramLogger;
        $this->container = $container;
    }

    protected function configure()
    {
        $this->setName('event:loop')
            ->setDescription('Мастер процесс управления всеми демонами обработки событий')
            ->addOption(
                'output',
                null,
                InputOption::VALUE_REQUIRED,
                'output',
                true
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int|void|null
     *
     * @throws Throwable
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        ini_set('memory_limit', '200M');
        $this->initServer($input, $output);
        
        $commands = $this->configService->getCommands();
        $startCommands = $this->checkQueues($commands);
        
        $this->addCommands($startCommands);
        $this->startCommands();
        
        $this->loop->addPeriodicTimer(600, function (TimerInterface $timer) {
           echo (new DateTime())->format('d-m-Y H:i:s.u') . " Process 'master' - " . getmypid() . ": memory -> " . memory_get_peak_usage(true) / 1024 / 1024 . PHP_EOL;
        });
        
        $this->loop->addPeriodicTimer(60, function (TimerInterface $serverInfoTimer) {
            HealthChecker::changeServerInfo();
        });

        $checkCommands = array_filter($commands, function (CommandInterface $command) {
            return $command->isConsumer() && !$command->isDaemon();
        });

        /** @var CommandLoop $command */
        foreach ($checkCommands as $command) {
            $this->addCommand($command);
        }

        try {
            $this->loop->run();
        } catch (Throwable $exception) {
            $this->stopCommands();
            throw $exception;
        }

        return 0;
    }

    /**
     * @param array $commands
     *
     * @return array
     */
    private function checkQueues(array $commands): array
    {
        $filter = array_filter($commands, function (CommandInterface $command) {
            return !$command->isConsumer() || ($command->isConsumer() && $command->isDaemon());
        });

        $checkCommands = array_filter($commands, function (CommandInterface $command) {
           return $command->isConsumer() && !$command->isDaemon();
        });
        $pheanstalk = new Pheanstalk($this->container->getParameter('client_event.queue_host'));
        $checkTimer = $this->container->getParameter('client_event.job_channels_timer');
        
        $this->loop->addPeriodicTimer($checkTimer, function (TimerInterface $timer) use ($checkCommands, $pheanstalk) {
            /** @var CommandLoop $command */
            foreach ($checkCommands as $command) {
                if ($command->getCountProcesses() > 0 || is_null($command->getTube())) {
                    continue;
                }
                
                $pheanstalk->useTube('default');
                $pheanstalk->useTube($command->getTube());
                
                if ((int) $pheanstalk->statsTube($command->getTube())['current-jobs-ready'] > 0) {
                    $command->start();
                }
            }
            
            if ($pheanstalk->getConnection()->hasSocket()) {
                $pheanstalk->getConnection()->disconnect();
            }
        });
        
        return $filter;
    }
}
