<?php

namespace ClientEventBundle\Loop;

use Cron\CronExpression;
use React\ChildProcess\Process;
use React\EventLoop\TimerInterface;

/**
 * Class CommandLoop
 * 
 * @package ClientEventBundle\Loop
 */
class CommandLoop extends AbstractCommand
{
    /** @var bool $consumer */
    private $consumer;
    
    /** @var bool $daemon */
    private $daemon;

    /** @var integer | null $countJob */
    private $countJob;
    
    /** @var int $counJobReady */
    private $countJobReady = 0;

    /** @var TimerInterface $timers */
    private $timerNewInstance;

    /** @var TimerInterface $timers */
    private $timerCloseInstance;
    
    /** @var TimerInterface $timerStopConsumer */
    private $timerStopConsumer;
    
    /** @var string | null $schedule */
    private $schedule;
    
    /** @var \DateTime | null $lastScheduledStart */
    private $lastScheduledStart;
    
    /** @var int $startSecond */
    private $startSecond = 0;
    
    /** @var string | null $tube */
    private $tube;

    /** @var integer $downtimeForDestruction */
    private $downtimeForDestruction = 0;

    /**
     * @return array
     */
    public function getSettings(): array
    {
        return [
            'interval' => $this->getIntervalTick(),
            'maxMemory' => $this->getUseMaxMemory(),
            'timeoutSocketWrite' => $this->getTimeoutSocketWrite(),
            'daemon' => $this->isDaemon(),
        ];
    }

    /**
     * @return bool
     */
    public function isConsumer(): bool
    {
        return $this->consumer;
    }

    /**
     * @param bool $consumer
     */
    public function setConsumer(bool $consumer): void
    {
        $this->consumer = $consumer;
    }

    /**
     * @return bool
     */
    public function isDaemon(): bool
    {
        return $this->daemon;
    }

    /**
     * @param bool $daemon
     */
    public function setDaemon(bool $daemon): void
    {
        $this->daemon = $daemon;
    }

    /**
     * @return int|null
     */
    public function getCountJob(): ?int
    {
        return $this->countJob;
    }

    /**
     * @param int|null $countJob
     */
    public function setCountJob(?int $countJob): void
    {
        $this->countJob = $countJob;
    }

    /**
     * @return int
     */
    public function getCountJobReady(): int
    {
        return $this->countJobReady;
    }

    /**
     * @return string | null
     */
    public function getSchedule(): ?string
    {
        return $this->schedule;
    }

    /**
     * @param string|null $schedule
     */
    public function setSchedule(?string $schedule): void
    {
        $this->schedule = $schedule;
    }

    /**
     * @return int
     */
    public function getStartSecond(): int
    {
        return $this->startSecond;
    }

    /**
     * @param int $startSecond
     */
    public function setStartSecond(int $startSecond): void
    {
        $this->startSecond = $startSecond;
    }

    /**
     * @return string|null
     */
    public function getTube(): ?string
    {
        return $this->tube;
    }

    /**
     * @param string|null $tube
     * 
     * @return $this
     */
    public function setTube(?string $tube): self
    {
        $this->tube = $tube;
        
        return $this;
    }

    /**
     * @return int
     */
    public function getDowntimeForDestruction(): int
    {
        return $this->downtimeForDestruction;
    }

    /**
     * @param int $downtimeForDestruction
     * 
     * @return $this
     */
    public function setDowntimeForDestruction(int $downtimeForDestruction): self
    {
        $this->downtimeForDestruction = $downtimeForDestruction;
        
        return $this;
    }

    /**
     * @return \DateTime|null
     */
    public function getLastScheduledStart(): ?\DateTime
    {
        return $this->lastScheduledStart;
    }

    /**
     * @return \DateTime|null
     */
    public function getStartTime(): ?\DateTime
    {
        return $this->startTime;
    }
    
    public function start()
    {
        $timer = null;
        
        if ($this->getCountProcesses() === $this->getMinInstance()) {
            return;
        }
        
        if ($this->getMinInstance() < 1) {
            return;
        }

        if (!is_null($this->getSchedule()) && !$this->isConsumer()) {
            $this->startScheduleDaemon();

            return;
        }

        if (!$this->isDaemon() && !$this->isConsumer()) {
            $this->intervalTickTimer = $this->loop->addPeriodicTimer($this->getIntervalTick(), function (TimerInterface $timer) {
                if (0 !== $this->getCountProcesses()) {
                    return;
                }

                $this->startProcess();
            });
        }
        
        $this->startProcess($this->getMinInstance());
        
        if ($this->isDaemon()) {
            $this->daemonTimer = $this->loop->addPeriodicTimer(1, function (TimerInterface $timer) {
                if ($this->getCountProcesses() < $this->getMinInstance()) {
                    $this->startProcess($this->getMinInstance() - $this->getCountProcesses());
                }
            });
        }

        $this->pingPollingTimer = $this->loop->addPeriodicTimer($this->pingPollingInterval, function (TimerInterface $timer) {
            foreach ($this->socketPid as $pid => $socket) {
                $this->socket
                    ->setConnection($socket)
                    ->write(new SocketMessage(Constants::SOCKET_CHANNEL_PING, []), null, function () use ($pid) {
                        if (!is_null($process = $this->getProcess($pid))) {
                            $this->restartProcess($process);
                        }
                });
            }
        });
        
        $this->startTime = new \DateTime();
    }

    /**
     * @param null $signal
     */
    public function stop($signal = null): void
    {
        foreach ($this->processes as $process) {
            $this->removeProcess($process, $signal);
        }

        if (!is_null($this->pingPollingTimer)) {
            $this->loop->cancelTimer($this->pingPollingTimer);
            $this->pingPollingTimer = null;
        }

        if (!is_null($this->daemonTimer)) {
            $this->loop->cancelTimer($this->daemonTimer);
            $this->daemonTimer = null;
        }

        if (!is_null($this->intervalTickTimer)) {
            $this->loop->cancelTimer($this->intervalTickTimer);
            $this->intervalTickTimer = null;
        }
        
        $this->startTime = null;
        $this->lastScheduledStart = null;
    }

    /**
     * @param int $countJobReady
     * 
     * @return $this
     */
    public function setCountJobReady(int $countJobReady): CommandInterface
    {
        $this->countJobReady = $countJobReady;
        
        // если воркер не запущен то просто выходим
        if ($this->getCountProcesses() <= 0) {
            return $this;
        }
        
        $handleStop = function (TimerInterface $stopTimer) {
            if (0 === $this->countJobReady) {
                $this->stop();
            }

            $this->loop->cancelTimer($this->timerStopConsumer);
            $this->timerStopConsumer = null;
        };
        
        if ($this->isConsumer() && !$this->isDaemon() && 0 === $countJobReady && is_null($this->timerStopConsumer)) {
            $this->timerStopConsumer = $this->loop->addTimer($this->getDowntimeForDestruction(), $handleStop);
        }
        
        if ($this->isConsumer() && !$this->isDaemon() && $countJobReady > 0 && !is_null($this->timerStopConsumer)) {
            $this->loop->cancelTimer($this->timerStopConsumer);
            $this->timerStopConsumer = null;
            $this->timerStopConsumer = $this->loop->addTimer($this->getDowntimeForDestruction(), $handleStop);
        }

        if ($this->getCountJobReady() > $this->getCountJob() && !$this->isMaximumProcesses() && is_null($this->timerNewInstance)) {
            $this->timerNewInstance = $this->loop->addPeriodicTimer($this->getTimeoutCreate(), function ($timer) {
                if ($this->getCountJobReady() > $this->getCountJob() && !$this->isMaximumProcesses()) {
                    $this->startProcess();
                    
                    return;
                }

                $this->loop->cancelTimer($timer);
                $this->timerNewInstance = null;
            });
        }

        if ($this->getCountJobReady() < $this->getCountJob() && is_null($this->timerCloseInstance)) {
            $this->timerCloseInstance = $this->loop->addPeriodicTimer($this->getTimeoutCreate(), function ($timer) use (&$counJobReady) {
                if ($this->getCountProcesses() > $this->getMinInstance() && $this->getCountJobReady() < $this->getCountJob()) {
                    $this->removeProcess(current($this->processes));

                    return;
                }
                
                $this->loop->cancelTimer($timer);
                $this->timerCloseInstance = null;
            });
        }
        
        return $this;
    }

    /**
     * @param Process $process
     */
    private function addProcess(Process $process)
    {
        $this->processes[$process->getPid()] = $process;
    }

    /**
     * @param int $countProcesess
     */
    private function startProcess($countProcesess = 1)
    {
        if ($this->getCountProcesses() >= $this->maxInstance) {
            return;
        }
        
        do {
            $process = new Process($this->getCommand());
            $process->start($this->loop);
            $this->subscribeToProcessOutput($process, $this->loop);
            $this->addProcess($process);
            $countProcesess --;
        } while($countProcesess > 0);
    }

    /**
     * @param Process $process
     */
    private function restartProcess(Process $process)
    {
        $this->emit(Constants::RESTART_PROCESS_EVENT, ["Process '{$this->getName()}' - {$process->getPid()}: restart."]);

        $this->removeProcess($process);
        $this->startProcess();
    }

    /**
     * @param Process $process
     * @param null $signal
     */
    private function removeProcess(Process $process, $signal = null)
    {
        $this->emit(Constants::STOP_PROCESS_EVENT, ["Process '{$this->getName()}' - {$process->getPid()}: stop."]);
        $process->removeAllListeners();
        $process->terminate($signal);
        unset($this->processes[$process->getPid()]);
        unset($this->socketPid[$process->getPid()]);
    }

    /**
     * @param Process $process
     * @param $loop
     */
    private function subscribeToProcessOutput(Process $process, $loop): void
    {
        $that = $this;
        
        $this->emit(Constants::START_PROCESS_EVENT, ["Process '{$this->getName()}' - {$process->getPid()}: start."]);
        
        $process->stdout->on('data', function ($data) use ($process) {
            $data = str_replace("\n", '', $data);

            if (stristr($data, '[ERROR]')) {
                $data = str_replace('[ERROR]', '', $data);
                $this->emit(Constants::ERROR_PROCESS_EVENT, ["Process '{$this->getName()}' - {$process->getPid()}:\n $data"]);
                
                return;
            }
            
            if (empty(str_replace("\n", '', $data))) {
                return;
            }
            
            $this->emit(Constants::CHANNEL_CLIENT_CONSOLE, ["Process '{$this->getName()}' - {$process->getPid()}: $data."]);
        });

        $process->stderr->on('data', function ($data) use ($process, $loop, $that) {
            if (empty(str_replace("\n", '', $data))) {
                return;
            }
            
            $this->emit(Constants::ERROR_PROCESS_EVENT, ["Process '{$this->getName()}' - {$process->getPid()}:\n $data"]);

            if (!is_null($this->getSchedule()) && !$this->isConsumer()) {
                $this->emit(Constants::ERROR_CRON_PROCESS_EVENT, [$this->getName(), $process->getPid(), $data]);
            }
        });

        $process->on('exit', function($exitCode) use ($process, $loop, $that) {
            if ($exitCode === 0) {
                $this->emit(Constants::EXIT_PROCESS_EVENT, ["Process '{$this->getName()}' - {$process->getPid()}: Остановка по команде СТОП."]);
                $that->removeProcess($process);
            } else {
                if (!$this->isDaemon()) {
                    $that->removeProcess($process);
                    return;
                }
                
                $that->restartProcess($process);
            }
        });

        // логируем что отправили по сокету клиенты
        $this->on(Constants::CHANNEL_CLIENT_WRITE, function ($message) use ($process) {
            if ($message['pid'] !== $process->getPid()) {
                return;
            }

            $message = json_encode($message);
            $this->emit(Constants::CHANNEL_CLIENT_CONSOLE, ["Process '{$this->getName()}' - {$process->getPid()} отправил данные: $message."]);
        });
    }

    private function startScheduleDaemon()
    {
        $scheduler = new CronExpression($this->getSchedule());
        
        $offsetFunction = function ($scheduler, $offsetFunction) {
            $this->loop->addPeriodicTimer(0.1, function (TimerInterface $offsetTimer) use ($scheduler, $offsetFunction) {
                if ((int) date('s') !== $this->startSecond) {
                    return;
                }

                $this->loop->addPeriodicTimer(60, function (TimerInterface $scheduleTimer) use ($scheduler, $offsetFunction) {
                    if (!$scheduler->isDue()) {
                        return;
                    }

                    if (0 !== $this->getCountProcesses()) {
                        return;
                    }
                    
                    $this->startProcess();
                    $this->lastScheduledStart = new \DateTime();

                    if ((int) date('s') !== $this->startSecond) {
                        $this->loop->cancelTimer($scheduleTimer);
                        $offsetFunction($scheduler, $offsetFunction);
                    }
                });

                if ($scheduler->isDue()) {
                    $this->startProcess();
                    $this->lastScheduledStart = new \DateTime();
                }

                $this->loop->cancelTimer($offsetTimer);
            });
        };

        $offsetFunction($scheduler, $offsetFunction);
    }
}
