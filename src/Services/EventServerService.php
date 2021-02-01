<?php

namespace ClientEventBundle\Services;

use Doctrine\ORM\EntityManagerInterface;
use ClientEventBundle\Entity\EventServer;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class EventServerService
 *
 * @package ClientEventBundle\Services
 */
class EventServerService
{
    /** @var EntityManagerInterface $entityManager */
    private $entityManager;
    
    /** @var EventServer $evetServer */
    private $eventServer;

    /**
     * SubscriptionService constructor.
     *
     * @param EntityManagerInterface $entityManager
     * @param ContainerInterface $container
     */
    public function __construct(EntityManagerInterface $entityManager, ContainerInterface $container)
    {
        $this->entityManager = $entityManager;

        try {
            $this->eventServer = $entityManager->getRepository(EventServer::class)
                ->findOneBy(['currentHost' => $container->getParameter('client_event.host')], ['id' => 'DESC']);
        } catch (\Exception $exception) {
            return;
        }
    }

    /**
     * @return EventServer | null
     */
    public function getEventServer(): ?EventServer
    {
        return $this->eventServer;
    }

    /**
     * @return string | null
     */
    public function getClientHeader(): ?string
    {
        if (is_null($server = $this->getEventServer())) {
            return null;
        }
        
        return $server->getClientHeader();
    }

    /**
     * @return string | null
     */
    public function getClientToken(): ?string
    {
        if (is_null($server = $this->getEventServer())) {
            return null;
        }

        return $server->getClientToken();
    }

    /**
     * @return string | null
     */
    public function getServerHeader(): ?string
    {
        if (is_null($server = $this->getEventServer())) {
            return null;
        }

        return $server->getServerHeader();
    }

    /**
     * @return string | null
     */
    public function getServerToken(): ?string
    {
        if (is_null($server = $this->getEventServer())) {
            return null;
        }

        return $server->getServerToken();
    }
}
