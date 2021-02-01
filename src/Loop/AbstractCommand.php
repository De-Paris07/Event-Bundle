<?php

namespace ClientEventBundle\Loop;

use ClientEventBundle\Socket\SocketServerInterface;
use Evenement\EventEmitter;
use React\ChildProcess\Process;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use React\Socket\ConnectionInterface;

/**
 * Class AbstractCommand
 * 
 * @package ClientEventBundle\Loop
 */
abstract class AbstractCommand extends EventEmitter implements CommandInterface
{
    /** @var string $name */
    protected $name;

    /** @var string | null $command */
    protected $command;

    /** @var integer $minInstance */
    protected $minInstance;

    /** @var integer $maxInstance */
    protected $maxInstance;

    /** @var integer $timeoutCreate */
    protected $timeoutCreate;

    /** @var float $intervalTick */
    protected $intervalTick;

    /** @var Process[] $procesess */
    protected $processes = [];

    /** @var LoopInterface $loop */
    protected $loop;

    /** @var int $useMaxMemory */
    protected $useMaxMemory = 0;

    /** @var int $pingPollingInterval */
    protected $pingPollingInterval;
    
    /** @var TimerInterface $pingPollingTimer */
    protected $pingPollingTimer;

    /** @var SocketServerInterface $socket */
    protected $socket;

    /** @var ConnectionInterface[] $socketPid */
    protected $socketPid = [];

    /** @var int $timeoutSocketWrite */
    protected $timeoutSocketWrite = 30;
    
    /** @var TimerInterface $daemonTimer */
    protected $daemonTimer;
    
    /** @var TimerInterface $intervalTickTimer */
    protected $intervalTickTimer;

    /** @var \DateTime | null $startTime */
    protected $startTime;

    /**
     * @return array
     */
    public abstract function getSettings(): array;

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return string|null
     */
    public function getCommand(): string
    {
        return $this->command;
    }

    /**
     * @param string|null $command
     */
    public function setCommand(?string $command): void
    {
        $this->command = $command;
    }

    /**
     * @return int
     */
    public function getMinInstance(): int
    {
        return $this->minInstance;
    }

    /**
     * @param int $minInstance
     */
    public function setMinInstance(int $minInstance): void
    {
        $this->minInstance = $minInstance;
    }

    /**
     * @return int
     */
    public function getMaxInstance(): int
    {
        return $this->maxInstance;
    }

    /**
     * @param int $maxInstance
     */
    public function setMaxInstance(int $maxInstance): void
    {
        $this->maxInstance = $maxInstance;
    }

    /**
     * @return int
     */
    public function getTimeoutCreate(): int
    {
        return $this->timeoutCreate;
    }

    /**
     * @param int $timeoutCreate
     */
    public function setTimeoutCreate(int $timeoutCreate): void
    {
        $this->timeoutCreate = $timeoutCreate;
    }

    /**
     * @return float
     */
    public function getIntervalTick(): float
    {
        return $this->intervalTick;
    }

    /**
     * @param float $intervalTick
     */
    public function setIntervalTick(float $intervalTick): void
    {
        $this->intervalTick = $intervalTick;
    }

    /**
     * @return LoopInterface
     */
    public function getLoop(): LoopInterface
    {
        return $this->loop;
    }

    /**
     * @param LoopInterface $loop
     */
    public function setLoop(LoopInterface $loop): void
    {
        $this->loop = $loop;
    }

    /**
     * @return int
     */
    public function getUseMaxMemory(): int
    {
        return $this->useMaxMemory;
    }

    /**
     * @param int $useMaxMemory
     */
    public function setUseMaxMemory(int $useMaxMemory): void
    {
        $this->useMaxMemory = $useMaxMemory;
    }

    /**
     * @return int
     */
    public function getCountProcesses(): int
    {
        return count($this->processes);
    }

    /**
     * @return bool
     */
    public function isMaximumProcesses(): bool
    {
        return $this->getCountProcesses() >= $this->getMaxInstance();
    }

    /**
     * @param $pid
     *
     * @return Process | null
     */
    public function getProcess(?int $pid): ?Process
    {
        if (!isset($this->processes[$pid]) || is_null($pid)) {
            return null;
        }

        return $this->processes[$pid];
    }

    /**
     * @return int
     */
    public function getPingPollingInterval(): int
    {
        return $this->pingPollingInterval;
    }

    /**
     * @param int $pingPollingInterval
     */
    public function setPingPollingInterval(int $pingPollingInterval): void
    {
        $this->pingPollingInterval = $pingPollingInterval;
    }

    /**
     * @param SocketServerInterface $socket
     */
    public function setSocket(SocketServerInterface &$socket): void
    {
        $this->socket = $socket;
    }

    /**
     * @param int $pid
     * @param ConnectionInterface $socket
     */
    public function setSocketPid(int $pid, ConnectionInterface $socket): void 
    {
        if (array_key_exists($pid, $this->socketPid)) {
            return;
        }

        $this->socketPid[$pid] = $socket;
    }

    /**
     * @return ConnectionInterface[]
     */
    public function getSocketPid(): array
    {
        return $this->socketPid;
    }

    /**
     * @param int $pid
     *
     * @return ConnectionInterface | null
     */
    public function getSocketByPid(int $pid): ?ConnectionInterface
    {
        if (!array_key_exists($pid, $this->socketPid)) {
            return null;
        }

        return $this->socketPid[$pid];
    }

    /**
     * @return int
     */
    public function getTimeoutSocketWrite(): int
    {
        return $this->timeoutSocketWrite;
    }

    /**
     * @param int $timeoutSocketWrite
     */
    public function setTimeoutSocketWrite(int $timeoutSocketWrite): void
    {
        $this->timeoutSocketWrite = $timeoutSocketWrite;
    }

    /**
     * @return \DateTime|null
     */
    public function getStartTime(): ?\DateTime
    {
        return $this->startTime;
    }
}
