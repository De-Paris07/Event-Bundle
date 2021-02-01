<?php

namespace ClientEventBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Class Configuration
 *
 * @package ClientEventBundle\DependencyInjection
 */
class Configuration implements ConfigurationInterface
{
    /**
     * @var ConfigurationInterface
     */
    private $parameterBag;

    /**
     * Configuration constructor.
     *
     * @param ParameterBagInterface $parameterBag
     */
    public function __construct(ParameterBagInterface $parameterBag)
    {
        $this->parameterBag = $parameterBag;
    }
    
    /**
     * @return TreeBuilder
     * @SuppressWarnings(PHPMD.ElseExpression)
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('client_event');

        if (method_exists($treeBuilder, 'root')) {
            $rootNode = $treeBuilder->root('client_event');
        } else {
            $rootNode = $treeBuilder->getRootNode();
        }

        $rootNode
            ->children()
                ->scalarNode('service_name')->isRequired()->end()
                ->scalarNode('host')->isRequired()->end()
                ->scalarNode('queue_host')->isRequired()->end()
                ->scalarNode('event_server_address')->isRequired()->end()
                ->scalarNode('elastic_host')->end()
                ->booleanNode('receive_historical_data')->defaultTrue()->end()
                ->integerNode('command_polling_interval')->min(3)->max(3600)->defaultValue(300)->end()
                ->integerNode('socket_write_timeout')->min(1)->max(120)->defaultValue(60)->end()
                ->integerNode('tcp_socket_port')->min(8040)->max(8070)->isRequired()->end()
                ->integerNode('event_server_tcp_socket_port')->min(8040)->max(8070)->defaultValue(8041)->end()
                ->booleanNode('use_query')->defaultFalse()->end()
                ->integerNode('downtime_for_destruction')->defaultValue(5)->end()
                ->floatNode('job_channels_timer')->defaultValue(1)->end()
                ->integerNode('max_memory_use')
                    ->min(50)
                    ->max(1000)
                    ->defaultValue(50)
                    ->end()
                ->arrayNode('events_sent')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('name')->isRequired()->end()
                            ->scalarNode('target_object')->isRequired()->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('events_subscribe')
                    ->arrayPrototype()
                        ->children()
                            ->enumNode('type_return_event_data')->values(['object', 'array'])->defaultValue('object')->end()
                            ->scalarNode('target_object')->end()
                            ->integerNode('priority')->min(0)->max(4294967295)->defaultValue(1024)->end()
                            ->scalarNode('ttr')->defaultValue(60)->end()
                            ->booleanNode('receive_historical_data')->defaultTrue()->end()
                            ->scalarNode('channel')->end()
                            ->scalarNode('servicePriority')->defaultValue(0)->end()
                            ->booleanNode('retry')->defaultFalse()->end()
                            ->integerNode('count_retry')->min(1)->max(10000)->defaultValue(100)->end()
                            ->integerNode('interval_retry')->min(1)->max(604800)->defaultValue(60)->end()
                            ->integerNode('priority_retry')->min(0)->max(4294967295)->defaultValue(1024)->info('наиболее срочно: 0, наименее срочно: 4294967295')->end()
                            ->scalarNode('callback_after_max_retry')->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('telegram')
                    ->canBeDisabled()
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                        ->booleanNode('use_proxy')->defaultFalse()->end()
                        ->arrayNode('environments')->scalarPrototype()->end()->defaultValue(['prod'])->end()
                        ->scalarNode('socks5')->end()
                        ->scalarNode('token')->end()
                        ->arrayNode('bots')
                            ->arrayPrototype()
                                ->children()
                                    ->scalarNode('token')->end()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('chats')
                            ->arrayPrototype()
                                ->children()
                                    ->scalarNode('chat_id')->end()
                                    ->arrayNode('environments')->scalarPrototype()->end()->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('commands')
                    ->arrayPrototype()
                        ->children()
                            ->booleanNode('consumer')->defaultTrue()->end()
                            ->booleanNode('daemon')
                                ->defaultTrue()
                                ->beforeNormalization()
                                    ->ifString()
                                    ->then($this->getNormalaizer())
                                ->end()
                            ->end()
                            ->integerNode('downtime_for_destruction')->defaultValue(5)->end()
                            ->scalarNode('cmd')->end()            
                            ->integerNode('min_instance_consumer')->defaultValue(1)->end()
                            ->integerNode('max_instance_consumer')->defaultValue(1)->end()
                            ->integerNode('count_job')->defaultValue(20)->end()
                            ->integerNode('timeout_create_instance')->defaultValue(3)->end()
                            ->floatNode('interval_tick')->defaultValue(0.05)->end()
                            ->integerNode('max_memory_use')->min(50)->max(1000)->defaultValue(50)->end()
                            ->scalarNode('schedule')->end()
                            ->integerNode('start_second')->min(0)->max(59)->defaultValue(0)->end()
                            ->booleanNode('enabled')
                                ->defaultTrue()
                                ->beforeNormalization()
                                    ->ifString()
                                    ->then($this->getNormalaizer())
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('retry_dispatch')
                    ->canBeDisabled()
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('count_retry')->defaultValue(10)->end()
                        ->integerNode('count_retry_for_notification')->defaultValue(3)->end()
                        ->integerNode('timeout_before_notification')->defaultValue(10)->end()
                        ->integerNode('timeout_after_notification')->defaultValue(60)->end()
                    ->end()
                ->end()
                ->arrayNode('log')
                    ->canBeDisabled()
                    ->addDefaultsIfNotSet()
                ->end()
            ->end();

        return $treeBuilder;
    }

    /**
     * @return \Closure
     */
    private function getNormalaizer()
    {
        return function ($value) {
            $values = [];
            preg_match_all('/\{.*?}/', $value,$tokens);
            
            if (!isset($tokens[0]) || !is_array($tokens[0])) {
                return $value;
            }
            
            $exp = '$result = ' . $value;

            foreach ($tokens[0] as $key => $token) {
                if (key_exists("$token", $values)) {
                    continue;
                }

                $name = trim($token, '{}');

                if ('%' === $name{0}) {
                    $resolveValue = $this->parameterBag->get(substr($name, 1));
                } elseif (0 === strpos($name, 'env(') && ')' === substr($name, -1) && 'env()' !== $name) {
                    $resolveValue = getenv(substr($name, 4, -1));
                }

                if (!isset($resolveValue)) {
                    return $value;
                }

                $values[$token] = $resolveValue;
                ${"v" . $key} = $resolveValue;
                $exp = str_replace($token, '$v' . $key, $exp);
            }

            $exp .= ";\n";

            try {
                eval($exp);
            } catch (\Throwable $exception) {
                return null;
            }

            return $result;
        };
    }
}
