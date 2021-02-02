<?php

namespace ClientEventBundle\Command;

use __PHP_Incomplete_Class;
use DateTime;
use Doctrine\DBAL\DBALException;
use ClientEventBundle\Dispatcher\QueueEventDispatcherInterface;
use ClientEventBundle\Entity\ProcessingJob;
use ClientEventBundle\Event;
use ClientEventBundle\Exception\ExtractFieldException;
use ClientEventBundle\Exception\MemoryLimitException;
use ClientEventBundle\IncompleteClassAccessor;
use ClientEventBundle\Loop\ClientTrait;
use ClientEventBundle\Services\FieldsService;
use ClientEventBundle\Services\SubscriptionService;
use ClientEventBundle\Services\TelegramLogger;
use ClientEventBundle\Services\ValidateService;
use Exception;
use PDOException;
use Pheanstalk\Exception\ServerException;
use Pheanstalk\Exception\SocketException;
use Pheanstalk\Job;
use Pheanstalk\Pheanstalk;
use Pheanstalk\PheanstalkInterface;
use RuntimeException;
use stdClass;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Doctrine\ORM\ORMException;
use Symfony\Component\HttpKernel\Kernel;
use Throwable;

/**
 * Class StartConsumerCommand
 *
 * @package ClientEventBundle\Command
 */
class StartConsumerCommand extends Command
{
    use ClientTrait;

    /** @var ContainerInterface $container */
    private $container;
    
    /** @var PheanstalkInterface $pheanstalk */
    private $pheanstalk;

    /** @var string $tube */
    private $tube;

    /** @var array $eventsSubscribe */
    private $eventsSubscribe;

    /** @var QueueEventDispatcherInterface $queueEventDispatcher */
    private $queueEventDispatcher;

    /** @var EventDispatcherInterface $eventDispatcher */
    private $eventDispatcher;

    /** @var ValidateService $validateService */
    private $validateService;
    
    /** @var int $settingMemory */
    private $settingMemory;

    /** @var FieldsService $fieldsService */
    private $fieldsService;

    /** @var TelegramLogger $telegramLogger */
    private $telegramLogger;

    /**
     * TestCommand constructor.
     *
     * @param EventDispatcherInterface $eventDispatcher
     * @param QueueEventDispatcherInterface $queueEventDispatcher
     * @param ValidateService $validateService
     * @param FieldsService $fieldsService
     * @param TelegramLogger $telegramLogger
     * @param ContainerInterface $container
     */
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        QueueEventDispatcherInterface $queueEventDispatcher,
        ValidateService $validateService,
        FieldsService $fieldsService,
        TelegramLogger $telegramLogger,
        ContainerInterface $container
    ) {
        parent::__construct();

        $this->eventDispatcher = $eventDispatcher;
        $this->container = $container;
        $host = $container->getParameter('client_event.queue_host');
        $port = $container->getParameter('client_event.queue_port');
        $this->tube = $container->getParameter('client_event.service_name');
        $this->pheanstalk = new Pheanstalk($host, $port);
        $this->eventsSubscribe = $container->getParameter('client_event.events_subscribe');
        $this->settingMemory = $container->getParameter('client_event.max_memory_use');
        $this->queueEventDispatcher = $queueEventDispatcher;
        $this->validateService = $validateService;
        $this->fieldsService = $fieldsService;
        $this->telegramLogger = $telegramLogger;
    }

    protected function configure()
    {
        $this->setName('event:queue:start')
            ->setDescription('Обработчик заданий из очереди.')
            ->addOption(
                'channel',
                null,
                InputOption::VALUE_REQUIRED,
                'channel',
                null
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int | void | null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $counter = 0;
        $countJobReady = null;
        $this->initClient();
        
        if (!is_null($tube = $input->getOption('channel'))) {
            $this->tube .= ".$tube";
        }
        
        if (is_null($tube)) {
            $tube = 'default';
        }

        $commandConfig = $this->container->getParameter('client_event.commands');
        $this->settingMemory = isset($commandConfig[$tube]) && isset($commandConfig[$tube]['max_memory_use'])
            ? $commandConfig[$tube]['max_memory_use']
            : $this->settingMemory;
        
        $memory = ini_get_all()['memory_limit']['local_value'];
        
        if ($memory !== '-1' && (int) $memory < $this->settingMemory) {
            ini_set('memory_limit', "{$this->settingMemory}M");
        }

        $this->pheanstalk->useTube($this->tube);

        $handler = function ($timer) use (&$countJobReady, &$counter, $io, $output) {
            try {
                if ($countJobReady !== $stat = $this->pheanstalk->statsTube($this->tube)['current-jobs-ready']) {
                    if ($this->clientSocket) {
                        $this->setCountJobReadyClient($stat);
                    }
                    
                    $io->success($stat = $this->pheanstalk->statsTube($this->tube)['current-jobs-ready']);
                    $countJobReady = $stat;
                }

                $this->checkConnection();

                /** @var Job $job */
                $job = $this->pheanstalk->watch($this->tube)->ignore('default')->reserve(1);

                if (!($job instanceof Job)) {
                    return;
                }

                $data = json_decode($job->getData(), true);
                $this->telegramLogger->setCurrentEvent($data['eventId'], $data['eventName']);
                $subscribeConfig = $this->getConfigSubscribe($data['eventName']);
                $systemEvent = $this->createSystemEvent($data);
                
                if (is_null($systemEvent)) {
                    return;
                }
                
                $systemEvent->setPriority($data['priority'] ?? 1024);

                if (!$this->validateService->canDispatchByFilter($systemEvent)
                    || (!$subscribeConfig['receive_historical_data'] && $systemEvent->isHistory())
                ) {
                    $this->pheanstalk->delete($job);
                    $systemEvent->setDelay(PheanstalkInterface::DEFAULT_DELAY);
                    $this->queueEventDispatcher->dispatchSuccess($data['eventName'], $systemEvent);
                    
                    return;
                }

                if (Kernel::VERSION_ID < 40000) {
                    $this->eventDispatcher->dispatch($data['eventName'], $systemEvent);
                } else {
                    $this->eventDispatcher->dispatch($systemEvent, $data['eventName']);
                }

                $systemEvent->setDelay(PheanstalkInterface::DEFAULT_DELAY);
                $this->queueEventDispatcher->dispatchSuccess($data['eventName'], $systemEvent);
                $this->pheanstalk->delete($job);

                ++$counter;
                $this->container->get('doctrine')->getManager()->clear();
                gc_collect_cycles();
                $memoryUsage = memory_get_peak_usage(true) / 1024 / 1024;
                $output->writeln(sprintf('Job "%d" of tube "%s" completed. Processing tube exhausted memory with "%sMB" of usage.', $counter, $this->tube, $memoryUsage));
                
                if ($memoryUsage > $this->settingMemory) {
                    throw new MemoryLimitException();
                }
                
                unset($job, $memoryUsage, $systemEvent, $data, $subscribeConfig);
            } catch (MemoryLimitException $memoryLimitException) {
                throw $memoryLimitException;
            } catch (SocketException $socketException) {
                $io->error($socketException->getMessage());
                exit(50);
            } catch (ServerException $exception) {
                $io->error($exception->getMessage());
                exit(50);
            } catch (PDOException $exception)  {
                $io->error($exception->getMessage());
                $this->telegramLogger->setFail($exception);
                throw $exception;
            } catch (ORMException | DBALException $exception) {
                if (!isset($job)) {
                    throw $exception;
                }

                try {
                    $this->pheanstalk->bury($job);
                } catch (Exception $e) {
                    throw $exception;
                }

                if (isset($systemEvent)) {
                    $systemEvent->setError($exception->getMessage());
                    $systemEvent->setDelay(PheanstalkInterface::DEFAULT_DELAY);
                    $this->queueEventDispatcher->dispatchFail($data['eventName'], $systemEvent);
                    $this->telegramLogger->setFail($exception);

                    throw $exception;
                }

                $event = new Event();
                $event->setEventId($data['eventId']);
                $event->setError($exception->getMessage());

                $this->queueEventDispatcher->dispatchFail($data['eventName'], $event);
                $this->telegramLogger->setFail($exception);

                throw $exception;
            } catch (Exception | Throwable $exception) {
                if (!isset($job)) {
                    $io->error($exception->getMessage());
                    return;
                }
                
                try {
                    $this->pheanstalk->bury($job);
                } catch (Exception $e) {
                    $io->error($e->getMessage());
                    gc_collect_cycles();
                    
                    return;
                }

                $io->error($exception->getMessage());

                if (isset($systemEvent)) {
                    $systemEvent->setError($exception->getMessage());
                    $systemEvent->setDelay(PheanstalkInterface::DEFAULT_DELAY);
                    $this->queueEventDispatcher->dispatchFail($data['eventName'], $systemEvent);
                    $this->telegramLogger->setFail($exception);
                    gc_collect_cycles();
                    
                    return;
                }

                $event = new Event();
                $event->setEventId($data['eventId']);
                $event->setError($exception->getMessage());

                $this->queueEventDispatcher->dispatchFail($data['eventName'], $event);
                $this->telegramLogger->setFail($exception);
                gc_collect_cycles();
            }
        };

        $this->addJob($handler);
        $this->start();

        return 0;
    }

    /**
     * @param array $data
     *
     * @return Event | null
     */
    private function createSystemEvent(array $data): ?Event
    {
        $eventName = $data['eventName'];
        $subscribeConfig = $this->getConfigSubscribe($eventName);

        if (is_null($subscribeConfig)) {
            return null;
        }

        $event = unserialize($data['event']);

        if (!($event instanceof Event)) {
            $event = $this->buildEvent($event, $subscribeConfig);
        }

        if (!($event instanceof Event)) {
            throw new RuntimeException(sprintf('Failed to create an event object "%s"', Event::class));
        }

        return $event;
    }

    /**
     * @param $rawData
     * @param array $config
     *
     * @return Event
     */
    private function buildEvent($rawData, array $config): Event
    {
        $object = Event::class;

        if ($this->isObjectReturnType($config)) {
            $object = $config['target_object'];
        }

        return $this->convertDataToUserObject($rawData, $object);
    }

    /**
     * @param $data
     * @param $object
     *
     * @return Event
     */
    private function convertDataToUserObject($data, $object): Event
    {
        $arrayProperties = [];
        $event = null;

        if ($data instanceof __PHP_Incomplete_Class) {
            $accessor = new IncompleteClassAccessor($data);
            $arrayProperties = $accessor->getProperties();
        }

        if (is_array($data)) {
            $arrayProperties = $data;
        }

        if (class_exists($object)) {
            $event = new $object;
        }

        if (!($event instanceof Event)) {
            throw new RuntimeException(sprintf('Class must "%s" extend "%s"', Event::class, $object));
        }

        unset($arrayProperties['dataArray']);

        $propertyPathFields = $this->fieldsService->getPropertyPathFields($event);
        $compositeProperties = $this->getCompositeProperties($arrayProperties, $this->fieldsService->getTargetObjectFields($event));
//        $compositeProperties = array_merge($arrayProperties, $compositeProperties);
        $arrayProperties = array_merge($arrayProperties, $this->getDinamicProperties($propertyPathFields, $arrayProperties, $compositeProperties), $compositeProperties);

        foreach ($arrayProperties as $key => $property) {
            if (SubscriptionService::isJson($property)) {
                $property = json_decode($property);
            }

            if (is_array($property)) {
                $property = json_decode(json_encode($property));
            }

            if ($property instanceof IncompleteClassAccessor) {
                $property = $this->getRecursiveValueIncompleteClass($property);
            }

            if (property_exists($event, $key) && method_exists($event, $method = 'set' . ucfirst($key))) {
                $event->$method($property);
                unset($arrayProperties[$key]);
                continue;
            }

            $event->setDataArray($event->getDataArray() + [$key => $property]);
        }

        if (Event::class === $object) {
            $this->setDataArray($event, $arrayProperties);
        }

        return $event;
    }

    /**
     * @param array $arrayProperties
     * @param array $targetObjectProperties
     * @param string $parent
     *
     * @return array
     */
    private function getCompositeProperties(array $arrayProperties, array $targetObjectProperties, string $parent = ''): array
    {
        $fields = [];
        
        foreach ($targetObjectProperties as $key => $property) {
            if (!is_string($key)) {
                continue;
            }

            foreach ($property as $_key => $value) {
                if (isset($value['name'])) {
                    if (!key_exists($key, $fields)) {
                        $fields[$key] = new $value['class'];
                    }
                    $value['path'] = '' === $parent ? "$key.{$value['name']}" : "$parent.{$value['name']}";
                    $propertyValue = $this->getDinamicValue($value, $arrayProperties);

                    if (is_null($propertyValue)) {
                        continue;
                    }

                    $method = 'set'.ucfirst($value['name']);

                    if (property_exists($fields[$key] , $value['name']) && method_exists($fields[$key], $method)) {
                        $fields[$key]->$method($propertyValue);
                    }
                    continue;
                }

                if (is_string($_key)) {
                    $data = $this->getCompositeProperties($arrayProperties, $property, '' === $parent ? $key : "$parent.$key");
                    $method = 'set'.ucfirst($_key);
                    $object = $fields[$key];

                    if (property_exists($object, $_key) && method_exists($object, $method)) {
                        $fields[$key]->$method($data[$_key]);
                    }
                }
            }
        }
        
        return $fields;
    }

    /**
     * @param Event $event
     *
     * @param $data
     */
    private function setDataArray(Event $event, $data)
    {
        $event->setDataArray(array_map(function ($property) {
            if (SubscriptionService::isJson($property)) {
                $property = json_decode($property);
            }

            if (is_array($property)) {
                $property = json_decode(json_encode($property));
            }

            if ($property instanceof IncompleteClassAccessor) {
                $property = $this->getRecursiveValueIncompleteClass($property);
            }

            return $property;
        }, $data));
    }

    /**
     * @param string $eventName
     *
     * @return array | null
     */
    private function getConfigSubscribe(string $eventName): ?array
    {
        foreach ($this->eventsSubscribe as $key => $item) {
            if ($key === $eventName) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @param array $config
     *
     * @return bool
     */
    private function isObjectReturnType(array $config)
    {
        return 'object' === $config['type_return_event_data'];
    }

    private function checkConnection()
    {
        $em = $this->container->get('doctrine')->getManager();

        if ($em->getConnection()->ping() === false) {
            $em->getConnection()->close();
            $em->getConnection()->connect();
        }
    }

    /**
     * @param array $propertyPathFields
     * @param array $properties
     * @param array $compositeProperties
     *
     * @return array
     */
    private function getDinamicProperties(array $propertyPathFields, array $properties, array $compositeProperties): array
    {
        $fields = [];

        foreach ($propertyPathFields as $propertyPath) {
            if (is_array($propertyPath['path'])) {
                $childValue = $this->getDinamicProperties($propertyPath['path'], $properties, $compositeProperties);

                if (isset($propertyPath['parent']) && !is_null($propertyPath['parent'])) {
                    if (!isset($propertyPath['class'])) {
                        $parents = explode('.', $propertyPath['parent']);

                        foreach ($parents as $key => $parent) {
                            if (0 === $key) {
                                if (isset($fields[$parent])) {
                                    $class = $fields[$parent];
                                } else {
                                    $class = $this->getDinamicValue($propertyPath, $properties, 'parent');
                                    $class = ($class instanceof IncompleteClassAccessor) || is_null($class) ? $this->getDinamicValue($propertyPath, $compositeProperties, 'parent') : $class;
                                }
                                continue;
                            }

                            if (is_null($class)) {
                                break;
                            }

                            $parentMethod = $this->getMethod($parent, $class);
                            $class = $class->$parentMethod();
                        }

                        if (is_null($class)) {
                            continue;
                        }

                        $method = 'set'.ucfirst($propertyPath['name']);

                        if (property_exists($class, $propertyPath['name']) && method_exists($class, $method)) {
                            $class->$method(is_array($childValue) ? json_decode(json_encode($childValue)) : $childValue);
                        }

                        continue;
                    }

                    $fields[$propertyPath['parent']] = is_array($childValue) ? json_decode(json_encode($childValue)) : $childValue;

                    continue;
                }
                
                $fields[$propertyPath['name']] = is_array($childValue) ? json_decode(json_encode($childValue)) : $childValue;
                
                continue;
            }
            
            if (!is_null($propertyPath['parent'])) {
                $value = $this->getDinamicValue($propertyPath, $properties, isset($propertyPath['class']) ? 'parent' : 'path');
                $value = is_null($value) ? $this->getDinamicValue($propertyPath, $compositeProperties, isset($propertyPath['class']) ? 'parent' : 'path') : $value;

                if (!isset($propertyPath['class'])) {
                    $parents = explode('.', $propertyPath['parent']);
                    $class = null;

                    foreach ($parents as $key => $parent) {
                        if (0 === $key) {
                            if (isset($fields[$parent])) {
                                $class = $fields[$parent];   
                            } else {
                                $class = $this->getDinamicValue($propertyPath, $properties, 'parent');
                                $class = ($class instanceof IncompleteClassAccessor) || is_null($class) ? $this->getDinamicValue($propertyPath, $compositeProperties, 'parent') : $class;
                            }
                            continue;
                        }
                        
                        if (is_null($class)) {
                            break;
                        }

                        $parentMethod = $this->getMethod($parent, $class);
                        $class = $class->$parentMethod();
                    }

                    if (is_null($class)) {
                        continue;
                    }

                    $method = 'set'.ucfirst($propertyPath['name']);

                    if (property_exists($class, $propertyPath['name']) && method_exists($class, $method)) {
                        $class->$method($value);
                    }
                    
                    continue;
                }
                
                $fields[$propertyPath['parent']] = $value;

                continue;
            }
            
            $value = $this->getDinamicValue($propertyPath, $properties);
            $fields[$propertyPath['name']] = $value;
        }

        return $fields;
    }

    /**
     * @param array $propertyPath
     * @param array $properties
     * @param string $propertyKey
     *
     * @return mixed | null
     */
    private function getDinamicValue(array $propertyPath, array $properties, string $propertyKey = 'path')
    {
        if (count(explode(' ', $propertyPath[$propertyKey])) > 1) {
            return $this->parseAndExpressionExecution($propertyPath, $properties, $propertyKey);
        }
        
        $paths = explode('.', $propertyPath[$propertyKey]);
        $value = null;

        foreach ($paths as $key => $path) {
            $value = is_null($value) ? $properties : $value;

            if ($key > 0) {
                if (SubscriptionService::isJson($value)) {
                    $value = json_decode($value);
                }

                if (is_array($value)) {
                    $value = json_decode(json_encode($value));
                }

                if ($value instanceof IncompleteClassAccessor) {
                    $value = $this->getRecursiveValueIncompleteClass($value);
                }
            }

            if (is_array($value) && key_exists($path, $value) && 0 === $key) {
                $value = $value[$path];
            } elseif (is_object($value) && property_exists($value, $path)) {
                if ($value instanceof stdClass) {
                    $value = $value->$path;
                } else {
                    $method = $this->getMethod($path, $value);
                    $value = $value->$method();
                }
            } else {
                return null;
            }
        }

        return $value;
    }

    /**
     * @param array $propertyPath
     * @param array $properties
     * @param string $propertyKey
     *
     * @return mixed | null
     */
    private function parseAndExpressionExecution(array $propertyPath, array $properties, string $propertyKey = 'path')
    {
        $values = [];
        preg_match_all('/\{.*?}/',$propertyPath[$propertyKey],$tokens);
        $exp = '$result = ' . $propertyPath[$propertyKey];
        
        foreach ($tokens[0] as $key => $token) {
            if (key_exists("$token", $values)) {
                continue;
            }

            $propertyPath['path'] = trim($token, '{}');
            $value = $this->getDinamicValue($propertyPath, $properties, $propertyKey);
            $values[$token] = $value;
            ${"v" . $key} = $value;
            $exp = str_replace($token, '$v' . $key, $exp);
        }

        $exp .= ";\n";

        try {
            eval($exp);
        } catch (Throwable $exception) {
            return null;
        }
        
        return $result;
    }

    /**
     * @param IncompleteClassAccessor $class
     *
     * @return stdClass
     */
    private function getRecursiveValueIncompleteClass(IncompleteClassAccessor $class)
    {
        $properties = new stdClass();
        
        foreach ($class->getProperties() as $key => $property) {
            if (!$property instanceof IncompleteClassAccessor) {
                $properties->$key = $property;
                continue;
            }
            
            $properties->$key = $this->getRecursiveValueIncompleteClass($property);
        }
        
        return $properties;
    }

    /**
     * @param array $property
     *
     * @return mixed
     */
    private function getCompositeValue(array $property)
    {
        $class = new $property['class'];

        if (!is_null($property['parent'])) {
            $parents = explode('.', $property['parent']);

            foreach ($parents as $paren) {
                $parentMethod = $this->getMethod($paren, $class);
                $class = $class->$parentMethod();
            }
        }

        $method = $this->getMethod($property['name'], $class);
        return $class->$method();
    }

    /**
     * @param string $fieldName
     * @param $class
     *
     * @return string
     *
     * @throws ExtractFieldException
     */
    private function getMethod(string $fieldName, $class): string
    {
        $method = null;
        $className = get_class($class);
        $getMethod = 'get'.ucfirst($fieldName);
        $isMethod = 'is'.ucfirst($fieldName);
        $hasMethod = 'has'.ucfirst($fieldName);

        if (method_exists($class, $getMethod)) {
            $method = $getMethod;
        } elseif (method_exists($class, $isMethod)) {
            $method = $isMethod;
        } elseif (method_exists($class, $hasMethod)) {
            $method = $hasMethod;
        } else {
            throw new ExtractFieldException(sprintf('Neither of these methods exist in class %s: %s, %s, %s', $className, $getMethod, $isMethod, $hasMethod));
        }

        return $method;
    }
}
