<?php

namespace ClientEventBundle\DependencyInjection;

use ClientEventBundle\Event\QueryEvent;
use Pheanstalk\PheanstalkInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader;

class ClientEventExtension extends Extension
{
    /**
     * @param array $configs
     * @param ContainerBuilder $container
     *
     * @throws \Exception
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration($container->getParameterBag());
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');
        $loader->load('command.yaml');

        $this->setParameters($container, $config);
    }

    /**
     * @param ContainerBuilder $container
     * @param array $config
     */
    private function setParameters(ContainerBuilder $container, array $config)
    {
        $container->setParameter('client_event.service_name', $config['service_name']);
        $container->setParameter('client_event.host', $config['host']);

        $chanks = explode(':', $config['queue_host']);
        $queueHost = $chanks[0];
        $queuePort = $chanks[1] ?? PheanstalkInterface::DEFAULT_PORT;

        $container->setParameter('client_event.queue_host', $queueHost);
        $container->setParameter('client_event.queue_port', $queuePort);
        $container->setParameter('client_event.tcp_socket_port', $config['tcp_socket_port']);
        $container->setParameter('client_event.event_server_tcp_socket_port', $config['event_server_tcp_socket_port']);
        $container->setParameter('client_event.use_query', $config['use_query']);
        $container->setParameter('client_event.job_channels_timer', $config['job_channels_timer']);
        $container->setParameter('client_event.server_health_send', $config['server_health_send']);
        $container->setParameter('client_event.server_health_send_interval', $config['server_health_send_interval']);

        $config['events_subscribe']['queue.query'] = [
            'target_object' => get_class(new QueryEvent()),
            'type_return_event_data' => 'object',
            'priority' => 1024,
            'ttr' => 60,
            'receive_historical_data' => false,
            'channel' => 'query',
            'servicePriority' => 0,
            'interval_tick' => 0.005,
            'retry' => false,
            'count_retry' => 100,
            'interval_retry' => 60,
            'priority_retry' => 1024,
        ];
        
        $container->setParameter('client_event.events_subscribe', $config['events_subscribe']);
        $container->setParameter('client_event.events_sent', $config['events_sent']);
        $container->setParameter('client_event.event_server_address', $config['event_server_address']);
        $container->setParameter('client_event.log.enabled', $config['log']['enabled']);
        $container->setParameter('client_event.max_memory_use', $config['max_memory_use']);
        $container->setParameter('client_event.receive_historical_data', $config['receive_historical_data']);
        $container->setParameter('client_event.telegram', $config['telegram']);
        $container->setParameter('client_event.retry_dispatch', $config['retry_dispatch']);
        $container->setParameter('client_event.command_polling_interval', $config['command_polling_interval']);
        $container->setParameter('client_event.socket_write_timeout', $config['socket_write_timeout']);
        
        $commands = $this->checkCommandsBlock($config);
        $this->loadTelegramConfig($config['telegram'], $container);
        
        $container->setParameter('client_event.commands', $commands);
        $container->setParameter('client_event.max_memory_use_default', $commands['default']['max_memory_use']);
        $container->setParameter('client_event.interval_tick_default', $commands['default']['interval_tick']);
        $container->setParameter('client_event.socket_name', "/tmp/{$config['service_name']}.sock");
        $container->setParameter('client_event.elastic_host', $config['elastic_host'] ?? null);
    }

    /**
     * @param $config
     * @param ContainerBuilder $container
     */
    private function loadTelegramConfig($config, ContainerBuilder $container)
    {
        $chats = $config['chats'];
        $bots = $config['bots'];

        $container->setParameter('telegram.eventDev', [
            'chat_id' => $chats['eventDev']['chat_id'] ?? null,
            'environments' => $chats['eventDev']['environments'] ?? ['dev', 'test']
        ]);

        $container->setParameter('telegram.eventProd', [
            'chat_id' => $chats['chat_id'] ?? null,
            'environments' => $chats['eventProd']['environments'] ?? ['prod']
        ]);
        
        foreach ($chats as $alias => $setting) {
            if (in_array($alias, ['eventDev', 'eventProd'])) {
                continue;
            }
            
            if (!isset($setting['chat_id'])) {
                throw new InvalidConfigurationException(sprintf('The child node "%s" at path "%s" must be configured.', 'chat_id', "client_event.telegram.chats.$alias"));
            }
            
            if (!isset($setting['environments'])) {
                throw new InvalidConfigurationException(sprintf('The child node "%s" at path "%s" must be configured.', 'environments', "client_event.telegram.chats.$alias"));
            }

            $container->setParameter("telegram.$alias", $setting);
        }

        foreach ($bots as $alias => $setting) {
            if (!isset($setting['token'])) {
                throw new InvalidConfigurationException(sprintf('The child node "%s" at path "%s" must be configured.', 'token', "client_event.telegram.bots.$alias"));
            }
            
            $container->setParameter("telegramBot.$alias", $setting['token']);
        }
    }

    /**
     * @param $commands
     *
     * @return array
     */
    private function checkCommandsBlock(array $config): array
    {
        $commands = $config['commands'];
        $channels = [];
        
        if (!isset($commands['default'])) {
            $commands['default'] = [
                'consumer' => true,
                'daemon' => true,
                'downtime_for_destruction' => $config['downtime_for_destruction'],
                'min_instance_consumer' => 1,
                'max_instance_consumer' => 4,
                'count_job' => 20,
                'timeout_create_instance' => 3,
                'interval_tick' => 0.05,
                'max_memory_use' => 50,
                'enabled' => true,
            ];
        }
        
        if (!isset($commands['retry'])) {
            $commands['retry'] = [
                'cmd' => 'event:dispatch:retry',
                'consumer' => false,
                'daemon' => true,
                'min_instance_consumer' => 1,
                'max_instance_consumer' => 1,
                'count_job' => 20,
                'timeout_create_instance' => 3,
                'interval_tick' => 10,
                'max_memory_use' => 50,
                'enabled' => true,
            ];
        }

        if (!isset($commands['socketHandler'])) {
            $commands['socketHandler'] = [
                'cmd' => 'event:socket:handler',
                'consumer' => false,
                'daemon' => true,
                'min_instance_consumer' => 1,
                'max_instance_consumer' => 1,
                'count_job' => 20,
                'timeout_create_instance' => 3,
                'interval_tick' => 1,
                'max_memory_use' => 50,
                'enabled' => true,
            ];
        }

        if (!isset($commands['healthCheck'])) {
            $commands['healthCheck'] = [
                'cmd' => 'event:server:state',
                'consumer' => false,
                'daemon' => false,
                'min_instance_consumer' => 1,
                'max_instance_consumer' => 1,
                'count_job' => 20,
                'timeout_create_instance' => 3,
                'interval_tick' => 55,
                'max_memory_use' => 50,
                'enabled' => true,
            ];
        }
        
        foreach ($config['events_subscribe'] as $subscribe) {
            if (!isset($subscribe['channel'])) {
                continue;
            }
            
            if (in_array($subscribe['channel'], $channels)) {
                continue;
            }
            
            $channels[] = $subscribe['channel'];
        }
        
        foreach ($channels as $channel) {
            if (key_exists($channel, $commands)) {
                continue;
            }

            $commands[$channel] = [
                'consumer' => true,
                'daemon' => false,
                'downtime_for_destruction' => $config['downtime_for_destruction'],
                'min_instance_consumer' => 1,
                'max_instance_consumer' => 4,
                'count_job' => 20,
                'timeout_create_instance' => 3,
                'interval_tick' => 0.05,
                'max_memory_use' => 50,
                'enabled' => true,
            ];
        }
        
        return $commands;
    }
}
