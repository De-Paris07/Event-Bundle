<?php

namespace ClientEventBundle\Manager;

use Doctrine\ORM\EntityManagerInterface;
use ClientEventBundle\Entity\Event;
use ClientEventBundle\Event as BaseEvent;

/**
 * Class EventManager
 *
 * @package ClientEventBundle\Manager
 */
class EventManager
{
    /** @var EntityManagerInterface $entityManager */
    private $entityManager;

    /** @var Event $eventDb */
    private $eventDb;

    /**
     * EventManager constructor.
     *
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @param BaseEvent $event
     *
     * @return Event
     *
     * @throws \Exception
     */
    public function createEvent(BaseEvent $event)
    {
        $eventDb = new Event();
        $eventDb->setData(serialize($event));
        $eventDb->setEventName($event->getEventName());
        $eventDb->setHash($event->getEventId());
        $eventDb->setStatus(Event::STATUS_DISPATH_SUCCESS);
        $eventDb->setCountAttempts(0);

        $this->entityManager->persist($eventDb);
        $this->entityManager->flush();
        $this->eventDb = $eventDb;

        return $eventDb;
    }

    public function successJob(BaseEvent $event)
    {

    }

    public function failJob(BaseEvent $event)
    {

    }

    /**
     * @param $status
     */
    public function changeStatus($status)
    {
        if (is_null($this->eventDb)) {
            return;
        }
        
        $this->eventDb->setStatus($status);
        $this->entityManager->flush();
    }

    public function removeJob()
    {
        if (is_null($this->eventDb)) {
            return;
        }

        $this->entityManager->remove($this->eventDb);
        $this->entityManager->flush();
    }
}
