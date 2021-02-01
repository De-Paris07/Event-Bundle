<?php

namespace ClientEventBundle\Loop;

use ClientEventBundle\Socket\SocketServerInterface;
use Evenement\EventEmitterInterface;
use React\ChildProcess\Process;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionInterface;

/**
 * Interface CommandInterface
 * 
 * @package ClientEventBundle\Loop
 */
interface CommandInterface extends EventEmitterInterface
{
    public function start();

    /**
     * @param null $signal
     */
    public function stop($signal = null): void;
    
    /**
     * @return string
     */
    public function getName(): string;

    /**
     * @param string $name
     */
    public function setName(string $name): void;
    
    /**
     * @return string
     */
    public function getCommand(): string;

    /**
     * @param string $command
     */
    public function setCommand(string $command): void;

    /**
     * @return int
     */
    public function getMinInstance(): int;

    /**
     * @param int $minInstance
     */
    public function setMinInstance(int $minInstance): void;

    /**
     * @return int
     */
    public function getMaxInstance(): int;

    /**
     * @param int $maxInstance
     */
    public function setMaxInstance(int $maxInstance): void;

    /**
     * @return int
     */
    public function getTimeoutCreate(): int;

    /**
     * @param int $timeoutCreate
     */
    public function setTimeoutCreate(int $timeoutCreate): void;

    /**
     * @return float
     */
    public function getIntervalTick(): float;

    /**
     * @param float $intervalTick
     */
    public function setIntervalTick(float $intervalTick): void;

    /**
     * @return LoopInterface
     */
    public function getLoop(): LoopInterface;

    /**
     * @param LoopInterface $loop
     */
    public function setLoop(LoopInterface $loop): void;

    /**
     * @return int
     */
    public function getUseMaxMemory(): int;

    /**
     * @param int $useMaxMemory
     */
    public function setUseMaxMemory(int $useMaxMemory): void;

    /**
     * @param $pid
     *
     * @return Process | null
     */
    public function getProcess(?int $pid): ?Process;

    /**
     * @return int
     */
    public function getCountProcesses(): int;

    /**
     * @return bool
     */
    public function isMaximumProcesses(): bool;

    /**
     * @return int
     */
    public function getPingPollingInterval(): int;

    /**
     * @param int $pingPollingInterval
     */
    public function setPingPollingInterval(int $pingPollingInterval): void;

    /**
     * @param SocketServerInterface $socket
     */
    public function setSocket(SocketServerInterface &$socket): void;

    /**
     * @param int $pid
     * @param ConnectionInterface $socket
     */
    public function setSocketPid(int $pid, ConnectionInterface $socket);

    /**
     * @return ConnectionInterface[]
     */
    public function getSocketPid(): array;

    /**
     * @param int $pid
     *
     * @return ConnectionInterface | null
     */
    public function getSocketByPid(int $pid): ?ConnectionInterface;

    /**
     * @return int
     */
    public function getTimeoutSocketWrite(): int;

    /**
     * @param int $timeoutSocketWrite
     */
    public function setTimeoutSocketWrite(int $timeoutSocketWrite): void;

    /**
     * @return array
     */
    public function getSettings(): array;

    /**
     * @return int
     */
    public function getCountJobReady(): int;

    /**
     * @param int $countJobReady
     * 
     * @return CommandInterface|null
     */
    public function setCountJobReady(int $countJobReady): CommandInterface;
}
