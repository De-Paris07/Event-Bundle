<?php

namespace ClientEventBundle\Dispatcher;

use ClientEventBundle\Event;
use ClientEventBundle\Event\RetryEvent;

/**
 * Interface QueueEventDispatcherInterface
 *
 * @package ClientEventBundle\Dispatcher
 */
interface QueueEventDispatcherInterface
{
    /**
     * @param string $eventName
     * @param Event $event
     * 
     * @return array
     */
    public function dispatch(string $eventName, Event $event): array;

    /**
     * @param RetryEvent $event
     *
     * @return array
     */
    public function dispatchRetry(RetryEvent $event): array;

    /**
     * @param string $eventName
     * @param Event $event
     * 
     * @return array
     */
    public function dispatchSuccess(string $eventName, Event $event);

    /**
     * @param string $eventName
     * @param Event $event
     * 
     * @return array
     */
    public function dispatchFail(string $eventName, Event $event);
}
