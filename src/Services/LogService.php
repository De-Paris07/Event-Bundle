<?php

namespace ClientEventBundle\Services;

use ClientEventBundle\Monolog\LogstashFormatter;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class LogService
 *
 * @package ClientEventBundle\Services
 */
class LogService
{
    /** @var LoggerInterface $logger */
    private $logger;
    
    /** @var ContainerInterface $container */
    private $container;

    /**
     * EventDistributeConsumer constructor.
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container) 
    {
        $this->container = $container;
        $this->logger = $container->get('monolog.logger.customEvent');
        $handler = $this->logger->getHandlers()[0];
        $handler->setFormatter(new LogstashFormatter($this->container->getParameter('client_event.service_name')));
    }

    /**
     * @param array $data
     * @param null $dispatchError
     */
    public function log(array $data, $dispatchError = null)
    {
        if (!$this->container->getParameter('client_event.log.enabled')) {
            return;
        }

        $serviceName = str_replace('.', '-', $this->container->getParameter('client_event.service_name'));
        
        if (isset($data['error'])) {
            $data['fail'][$serviceName] = [(new \DateTime())->format('d-m-Y H:i:s.u'), $data['error']];
            unset($data['error']);
        } elseif (isset($data['serviceName'])) {
            $data['createdSender'] = new \DateTime();
            $data['eventData'] = $data['event'];
            unset($data['event']);
            
            if (!is_null($dispatchError)) {
                $data['dispatchError'] = is_array($dispatchError) ? json_encode($dispatchError, 128 | 256) : $dispatchError;
            }
        } else {
            $data['success'][$serviceName] = [(new \DateTime())->format('d-m-Y H:i:s.u'), 'success'];
        }
        unset($data['serverToken']);
        
        $this->logger->info('', $data);
    }

    /**
     * @param array $data
     */
    public function responseQueryLog(array $data)
    {
        $this->logger->info('', $data);
    }
}
