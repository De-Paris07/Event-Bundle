<?php

namespace ClientEventBundle\Producer;

use ClientEventBundle\Event;

/**
 * Interface ProducerInterface
 *
 * @package ClientEventBundle\Producer
 */
interface ProducerInterface
{
    /**
     * @param string $tube
     * 
     * @return ProducerInterface
     */
    public function setTube(string $tube): ProducerInterface;

    /**
     * @param string $eventName
     * @param Event $event
     * @param bool $validate
     *
     * @return array
     */
    public function produce(string $eventName, Event $event, bool $validate = true): array ;
}
