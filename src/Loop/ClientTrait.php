<?php

namespace ClientEventBundle\Loop;

use ClientEventBundle\Socket\SocketClient;
use ClientEventBundle\Socket\SocketClientInterface;
use ClientEventBundle\Socket\SocketMessageInterface;
use Evenement\EventEmitterTrait;
use React\EventLoop\LoopInterface;

trait ClientTrait
{
    use EventEmitterTrait;
    
    /** @var LoopInterface $lopp */
    protected $loop;

    /** @var bool $stop */
    private $stop = false;
    
    /** @var array | null $clientSettings */
    private $clientSettings;

    /** @var \Closure[] $clientJobs */
    private $clientJobs = [];

    /** @var string $socketUri */
    private $socketUri;
    
    /** @var SocketClientInterface $clientSocket */
    private $clientSocket;

    /**
     * @param \Closure $closure
     * @param null $interval
     */
    public function addJob(\Closure $closure, $interval = null)
    {
        $this->clientJobs[] = ['callable' => $closure, 'interval' => $interval];
    }

    public function start()
    {
        $this->loop->run();
    }

    private function initClient()
    {
        $this->loop = LoopFactory::getLoop();
        $this->initSocketClient();

        $stopHandler = function (int $signal) {
            $this->stop = true;
        };

        $this->loop->addSignal(SIGINT, $stopHandler);
        $this->loop->addSignal(SIGTERM, $stopHandler);
        $this->loop->addSignal(SIGHUP, $stopHandler);
        $this->loop->addSignal(SIGBUS, $stopHandler);
        $this->loop->addSignal(SIGSYS, $stopHandler);
        $this->loop->addSignal(SIGTRAP, $stopHandler);
        $this->loop->addSignal(SIGTSTP, $stopHandler);

        // таймер на ожидание от сервера настроек команды.
        $settingTimer = $this->loop->addPeriodicTimer(0.01, function ($timer) {
            if (is_null($this->clientSettings)) {
                return;
            }

            $intervalTick = $this->clientSettings['interval'];
            $memorySetting = $this->clientSettings['maxMemory'];

            $memory = ini_get('memory_limit');

            if ($memory !== '-1' && (int) $memory < $memorySetting) {
                ini_set('memory_limit', "{$memorySetting}M");
            }

            if (empty($this->clientJobs)) {
                $this->loop->addPeriodicTimer($intervalTick, function ($timer) use ($memorySetting) {
                    $this->safeExit();

                    if (($memory = memory_get_peak_usage(true) / 1024 / 1024) > $memorySetting) {
                        echo "Процесс превысил выделенное количество памяти в $memorySetting Mb " . PHP_EOL;
                        exit(50);
                    }
                });

                return;
            }

            foreach ($this->clientJobs as $job) {
                $this->loop->addPeriodicTimer(is_null($job['interval']) ? $intervalTick : $job['interval'], function ($timer) use ($job, $memorySetting) {
                    $this->safeExit();

                    call_user_func($job['callable'], $timer);

                    $this->safeExit();

                    if (($memory = memory_get_peak_usage(true) / 1024 / 1024) > $memorySetting) {
                        echo "Процесс превысил выделенное количество памяти в $memorySetting Mb " . PHP_EOL;
                        exit(50);
                    }
                });

                call_user_func($job['callable'], $timer);
            }

            $this->loop->cancelTimer($timer);
        });

        // если через секунду соединение с сервером не установилось, то сбрасываем таймер на ожидание настроек, и задаем дефолтные.
        $this->loop->addTimer(3, function ($timer) use ($settingTimer) {
            if (!$this->clientSocket->getConnection()) {
                echo 'Нет подключения к сокету, запускаемся с дефолтными конфигами ' . PHP_EOL;
                $this->clientSocket->close();
                
                $intervalTick = $this->container->getParameter('client_event.interval_tick_default');
                $memoryUse = $this->container->getParameter('client_event.max_memory_use_default');

                if (empty($this->clientJobs)) {
                    $this->loop->addPeriodicTimer($intervalTick, function ($timer) use ($memoryUse) {
                        $this->safeExit();

                        if (($memory = memory_get_peak_usage(true) / 1024 / 1024) > $memoryUse) {
                            echo "Процесс превысил выделенное количество памяти в $memoryUse Mb " . PHP_EOL;
                            exit(50);
                        }
                    });

                    return;
                }

                foreach ($this->clientJobs as $job) {
                    $this->loop->addPeriodicTimer(is_null($job['interval']) ? $intervalTick : $job['interval'], function ($timer) use ($job, $memoryUse) {
                        $this->safeExit();

                        call_user_func($job['callable'], $timer);

                        $this->safeExit();

                        if (($memory = memory_get_peak_usage(true) / 1024 / 1024) > $memoryUse) {
                            echo "Процесс превысил выделенное количество памяти в $memoryUse Mb " . PHP_EOL;
                            exit(50);
                        }
                    });
                }
            }
            
            // если через 3 секунды подключились к сокету, но мастер не отдал настройки запуска, пробуем перезапуститься
            if ($this->clientSocket->getConnection() && is_null($this->clientSettings)) {
                echo 'Мастер за 3 секунды не отдал настройки, перезапускаемся ' . PHP_EOL;
                exit(50);
            }

            $this->loop->cancelTimer($settingTimer);
        });
    }

    private function safeExit()
    {
        if ($this->stop) {
            $this->emit('exit');
            echo 'Завершение процесса по команде "СТОП"' . PHP_EOL;
            exit();
        }
    }
    
    private function initSocketClient()
    {
        if (is_null($this->socketUri)) {
            $this->socketUri = $this->container->getParameter('client_event.socket_name');
        }
        
        $this->clientSocket = new SocketClient("unix://$this->socketUri");
        $this->clientSocket->setLoop($this->loop);

        $this->clientSocket->on(Constants::SOCKET_CHANNEL_PING, function (SocketMessageInterface $message) {
            $this->clientSocket
                ->setWaitForAnAnswer(false)
                ->write(new SocketMessage(Constants::SOCKET_CHANNEL_PONG, [
                    'status' => 'ok',
                    'memory' => memory_get_peak_usage(true) / 1024 / 1024
                ], $message->getXid()));
        });

        $this->clientSocket->on(SocketClient::SOCKET_CONNECT_CHANNEL, function () {
            echo 'Подключились к сокету ' . PHP_EOL;
            $this->clientSocket
                ->setWaitForAnAnswer(true)
                ->write(new SocketMessage(Constants::SOCKET_CHANNEL_CLIENT_CONNECT),
                    function (SocketMessageInterface $message) {
                        $this->clientSettings = $message->getData();

                        if (!is_null($timeout = $message->getField('timeoutSocketWrite')) && $timeout > 0) {
                            $this->clientSocket->setTimeoutSocketWrite($timeout);
                        }
                    });
        });
        
        $this->clientSocket->on(SocketClient::SOCKET_RAW_MESSAGE, function ($message) {
            echo json_encode($message) . PHP_EOL;
        });
        
        $this->clientSocket->connect();
    }

    /**
     * Метод для записи клиентом о количестве задач на обработку.
     *
     * @param int $count
     *
     * @return bool
     */
    private function setCountJobReadyClient(int $count): bool
    {
        return $this->clientSocket
            ->setWaitForAnAnswer(false)
            ->write(new SocketMessage(Constants::SOCKET_CHANNEL_CLIENT_JOB_READY, [
                'countJobReady' => $count,
            ]));
    }
}
