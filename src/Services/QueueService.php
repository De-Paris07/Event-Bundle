<?php

namespace ClientEventBundle\Services;

use ClientEventBundle\Event;
use ClientEventBundle\Manager\EventManager;

/**
 * Class QueueService
 *
 * @package ClientEventBundle\Services
 */
class QueueService
{
    const FAIL_TUBE = 'event.fail';
    const SUCCESS_TUBE = 'event.success';
    const DEFAULT_TUBE = 'system.event';
    const QUERY_TUBE = 'event.query';
    const RETRY_TUBE = 'system.retry';
    const ATTEMPT_COUNT = 3;

    /** @var EventManager $eventManager */
    private $eventManager;

    /** @var EventServerService $eventServerService */
    private $eventServerService;

    /** @var FieldsService $fieldsService */
    private $fieldsService;

    /** @var VirtualPropertyService $virtualPropertyService */
    private $virtualPropertyService;

    /**
     * QueueService constructor.
     *
     * @param EventManager $eventManager
     * @param EventServerService $eventServerService
     * @param FieldsService $fieldsService
     * @param VirtualPropertyService $virtualPropertyService
     */
    public function __construct(
        EventManager $eventManager,
        EventServerService $eventServerService,
        FieldsService $fieldsService,
        VirtualPropertyService $virtualPropertyService
    ) {
        $this->eventManager = $eventManager;
        $this->eventServerService = $eventServerService;
        $this->fieldsService = $fieldsService;
        $this->virtualPropertyService = $virtualPropertyService;
    }

    /**
     * @param string $tube
     * @param Event $event
     * @param bool $isSave
     *
     * @return array | null
     *
     * @throws \Exception
     */
    public function getJobData(string $tube, Event $event, $isSave = true)
    {
        $data = null;

        switch ($tube) {
            case self::DEFAULT_TUBE:
                if ($isSave) {
                    $this->eventManager->createEvent($event);
                }

                $data = [
                    'serviceName' => $event->getSenderServiceName(),
                    'serverToken' => $this->eventServerService->getServerToken(),
                    'eventId' => $event->getEventId(),
                    'eventName' => $event->getEventName(),
                    'event' => serialize($event),
                    'history' => $event->isHistory(),
                ];
                break;
            case self::RETRY_TUBE:
                if ($isSave) {
                    $this->eventManager->createEvent($event);
                }

                $data = [
                    'serviceName' => $event->getSenderServiceName(),
                    'serverToken' => $this->eventServerService->getServerToken(),
                    'eventId' => $event->getEventId(),
                    'eventName' => $event->getEventName(),
                    'event' => serialize($event),
                    'retryPriority' => $event->getRetryPriority(),
                    'retryEventId' => $event->getRetryEventId(),
                ];
                break;
            case self::QUERY_TUBE:
                $data = [
                    'serviceName' => $event->getSenderServiceName(),
                    'serverToken' => $this->eventServerService->getServerToken(),
                    'eventId' => $event->getEventId(),
                    'eventName' => $event->getEventName(),
                    'event' => serialize($event),
                    'route' => $event->getRoute(),
                ];
                break;
            case self::SUCCESS_TUBE:
                $data = [
                    'serverToken' => $this->eventServerService->getServerToken(),
                    'eventId' => $event->getEventId(),
                ];
                break;
            case self::FAIL_TUBE:
                $data = [
                    'serverToken' => $this->eventServerService->getServerToken(),
                    'eventId' => $event->getEventId(),
                    'error' => $event->getError(),
                ];
                break;
        }

        if (is_null($data)) {
            return null;
        }

        $fields = $this->fieldsService->getFields($event);
        $virtualProperties = $this->virtualPropertyService->getProperties();
        $this->virtualPropertyService->clearProperties();

        if (count($fields)) {
            $data = $data + $fields;
        }

        if (count($virtualProperties)) {
            $serviceName = str_replace('.', '-', $event->getSenderServiceName());

            foreach ($virtualProperties as $key => $virtualProperty) {
                $value = null;

                switch (true) {
                    case is_object($virtualProperty):
                        $value = (array) $virtualProperty;
                        break;
                    case is_array($virtualProperty):
                        $value = json_encode($virtualProperty, 128 | 256);
                        break;
                    case is_numeric($virtualProperty) || is_float($virtualProperty):
                        $value = (string) $virtualProperty;
                        break;
                    default:
                        $value = $virtualProperty;
                }

                $data[$serviceName][str_replace('.', '-', $key)] = $value;
            }
        }

        return $data;
    }
}
