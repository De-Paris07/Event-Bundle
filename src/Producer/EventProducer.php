<?php

namespace ClientEventBundle\Producer;

use ClientEventBundle\Event;
use ClientEventBundle\Exception\ProducerException;
use ClientEventBundle\Exception\ValidateException;
use ClientEventBundle\Manager\EventManager;
use ClientEventBundle\Services\LogService;
use ClientEventBundle\Services\QueueService;
use ClientEventBundle\Services\ValidateService;
use Pheanstalk\Pheanstalk;
use Pheanstalk\PheanstalkInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class EventProducer
 *
 * @package ClientEventBundle\Producer
 */
class EventProducer implements ProducerInterface
{
    /** @var PheanstalkInterface $pheanstalk */
    private $pheanstalk;

    /** @var string */
    private $tube;

    /** @var ContainerInterface $container */
    private $container;

    /** @var QueueService $queueService */
    private $queueService;

    /** @var ValidateService $validateService */
    private $validateService;

    /** @var LogService $logService */
    private $logService;

    /** @var EventManager $eventManager */
    private $eventManager;

    /**
     * EventProducer constructor.
     *
     * @param ContainerInterface $container
     * @param QueueService $queueService
     * @param ValidateService $validateService
     * @param LogService $logService
     * @param EventManager $eventManager
     */
    public function __construct(
        ContainerInterface $container,
        QueueService $queueService,
        ValidateService $validateService,
        LogService $logService,
        EventManager $eventManager
    ) {
        $this->container = $container;
        $host = $container->getParameter('client_event.queue_host');
        $port = $container->getParameter('client_event.queue_port');
        $this->pheanstalk = new Pheanstalk($host, $port);
        $this->queueService = $queueService;
        $this->validateService = $validateService;
        $this->logService = $logService;
        $this->eventManager = $eventManager;
    }

    /**
     * @param string $tube
     *
     * @return ProducerInterface
     */
    public function setTube(string $tube): ProducerInterface
    {
        $this->tube = $tube;

        return $this;
    }

    /**
     * @param string $eventName
     * @param Event $event
     * @param bool $validate
     * 
     * @return array
     * 
     * @throws \Exception
     */
    public function produce(string $eventName, Event $event, bool $validate = true): array
    {
        $exception = null;
        $isSaveDb = false;

        if (is_null($event->getEventId())) {
            $isSaveDb = true;
            $event->setEventId($this::generateEventId($eventName));
        }

        $event->setEventName($eventName);
        $event->setSenderServiceName($this->container->getParameter('client_event.service_name'));

        if (is_null($event->getCreated())) {
            $event->setCreated((new \DateTime())->getTimestamp());
        }

        if ($validate) {
            try {
                $this->validateService->validate($event);
            } catch (ValidateException $exception) {
                $jobData = $this->queueService->getJobData($this->tube, $event, false);
                $this->logService->log($jobData, $exception->errors);
                throw $exception;
            }
        }

        $jobData = $this->queueService->getJobData($this->tube, $event, $isSaveDb);

        if (is_null($jobData)) {
            throw new \RuntimeException('Не удалось создать эвент.');
        }

        for ($i = 0; $i < QueueService::ATTEMPT_COUNT; $i++) {
            try {
                $job = $this->pheanstalk->useTube($this->tube)
                    ->put(json_encode($jobData, 128 | 256), $event->getPriority(), $event->getDelay());

                $this->logService->log($jobData);
                $this->eventManager->removeJob();
                
                return ['jobId' => $job, 'eventId' => $jobData['eventId']];
            } catch (\Exception $exceptionPut) {
                $exception = $exceptionPut;
                continue;
            }
        }

        if (!is_null($exception)) {
            $this->eventManager->changeStatus(\ClientEventBundle\Entity\Event::STATUS_DISPATH_FAIL);
            throw new ProducerException($exception->getMessage(), $jobData['eventId']);
        }
    }

    /**
     * @param string $eventName
     *
     * @return string
     * 
     * @throws \Exception
     */
    public static function generateEventId(string $eventName): string 
    {
        return md5($eventName . microtime() . bin2hex(random_bytes(5)));
    }
}
