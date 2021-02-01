<?php

namespace ClientEventBundle\Dispatcher;

use ClientEventBundle\Event;
use ClientEventBundle\Event\RetryEvent;
use ClientEventBundle\Exception\NoEventServer;
use ClientEventBundle\Producer\ProducerInterface;
use ClientEventBundle\Services\EventServerService;
use ClientEventBundle\Services\QueueService;

/**
 * Class QueueEventDispatcher
 *
 * @package ClientEventBundle\Dispatcher
 */
class QueueEventDispatcher implements QueueEventDispatcherInterface
{
    /** @var ProducerInterface $producer */
    private $producer;

    /** @var EventServerService $eventServerService */
    private $eventServerService;

    /**
     * QueueEventDispatcher constructor.
     *
     * @param ProducerInterface $producer
     * @param EventServerService $eventServerService
     */
    public function __construct(ProducerInterface $producer, EventServerService $eventServerService)
    {
        $this->producer = $producer;
        $this->eventServerService = $eventServerService;
    }

    /**
     * @param string $eventName
     * @param Event $event
     */
    public function dispatch(string $eventName, Event $event): array
    {
        $this->doDispatch();
        
        return $this->producer->setTube(QueueService::DEFAULT_TUBE)->produce($eventName, $event);
    }

    /**
     * @param RetryEvent $event
     * 
     * @return array
     */
    public function dispatchRetry(RetryEvent $event): array
    {
        $this->doDispatch();
        
        return $this->producer->setTube(QueueService::RETRY_TUBE)->produce('system.retry', $event);
    }

    /**
     * @param string $eventName
     * @param Event $event
     * 
     * @return array
     */
    public function dispatchSuccess(string $eventName, Event $event): array
    {
        return $this->producer->setTube(QueueService::SUCCESS_TUBE)->produce($eventName, $event, false);
    }

    /**
     * @param string $eventName
     * @param Event $event
     * 
     * @return array
     */
    public function dispatchFail(string $eventName, Event $event): array
    {
        return $this->producer->setTube(QueueService::FAIL_TUBE)->produce($eventName, $event, false);
    }

    private function doDispatch()
    {
        if (is_null($this->eventServerService->getEventServer())) {
            throw new NoEventServer();
        }
    }
}
