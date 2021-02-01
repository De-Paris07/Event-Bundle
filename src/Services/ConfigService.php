<?php

namespace ClientEventBundle\Services;

use ClientEventBundle\Loop\CommandLoop;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Kernel;

/**
 * Class ConfigService
 *
 * @package ClientEventBundle\Services
 */
class ConfigService
{
    const DEFAULT_CONSUMER_KEY = 'default';
    const DEFAULT_COMMAND = 'exec php bin/console ';

    /** @var ContainerInterface $container */
    private $container;

    /**
     * ConfigService constructor.
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @return CommandLoop[]
     */
    public function getCommands(): array
    {
        $commands = [];
        $configCommands = $this->container->getParameter('client_event.commands');
        $channels = $this->getChannels();
        $commandPollingInterval = $this->container->getParameter('client_event.command_polling_interval');
        $timeoutSocketWrite = $this->container->getParameter('client_event.socket_write_timeout');

        if (!$this->container->hasParameter('client_event.routes') && key_exists('query', $configCommands)) {
            unset($configCommands['query']);
        }
        
        if (empty($subs = $this->container->getParameter('client_event.events_subscribe')) || (count($subs) === 1 && isset($subs['queue.query']))) {
            unset($configCommands['default']);
        }

        if (empty($this->container->getParameter('client_event.events_sent'))) {
            unset($configCommands['retry']);
        }
        
        foreach ($configCommands as $key => $configCommand) {
            $cmd = null;
            
            if (self::DEFAULT_CONSUMER_KEY === $key) {
                $cmd = self::DEFAULT_COMMAND . "event:queue:start";
            }

            if (in_array($key, $channels) && $configCommand['consumer']) {
                $cmd = self::DEFAULT_COMMAND . "event:queue:start --channel $key";
            }
            
            if (!$configCommand['consumer'] && isset($configCommand['cmd'])) {
                $cmd = self::DEFAULT_COMMAND . $configCommand['cmd'];
            }

            if (Kernel::VERSION_ID < 40000) {
                $cmd = "$cmd --env={$this->container->getParameter('kernel.environment')}";
            }

            if (!is_null($cmd) && $configCommand['enabled']) {
                $command = $this->createCommand($configCommand);
                $command->setName($key);
                $command->setCommand($cmd);
                $command->setPingPollingInterval($commandPollingInterval);
                $command->setTimeoutSocketWrite($timeoutSocketWrite);
                
                if ($command->isConsumer()) {
                    $tube = $this->container->getParameter('client_event.service_name');
                    $tube = in_array($key, $channels) ? "$tube.$key" : $tube;
                    $command->setTube($tube);
                    $command->setDowntimeForDestruction($configCommand['downtime_for_destruction']);
                }
                
                $commands[] = $command;
            }
        }

        return $commands;
    }

    /**
     * @return array
     */
    public function getChannels(): array
    {
        $channels = [];
        $subscribers = $this->container->getParameter('client_event.events_subscribe');

        foreach ($subscribers as $subscriber) {
            if (isset($subscriber['channel']) && !in_array($subscriber['channel'], $channels)) {
                $channels[] = $subscriber['channel'];
            }
        }

        return $channels;
    }

    /**
     * @param array $config
     *
     * @return CommandLoop
     */
    private function createCommand(array $config): CommandLoop
    {
        $command = new CommandLoop();
        $command->setConsumer($config['consumer']);
        $command->setDaemon($config['daemon']);
        $command->setCountJob($config['count_job']);
        $command->setIntervalTick($config['interval_tick']);
        $command->setMinInstance($config['min_instance_consumer']);
        $command->setMaxInstance($config['max_instance_consumer']);
        $command->setTimeoutCreate($config['timeout_create_instance']);
        $command->setUseMaxMemory($config['max_memory_use']);
        
        if (isset($config['schedule'])) {
            $command->setIntervalTick(1);
            $command->setSchedule($config['schedule']);
            $command->setStartSecond($config['start_second'] ?? 0);
        }
        
        return $command;
    }
}
