<?php

namespace ClientEventBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMException;
use ClientEventBundle\Dispatcher\QueueEventDispatcherInterface;
use ClientEventBundle\Entity\Event;
use ClientEventBundle\Exception\ProducerException;
use ClientEventBundle\Loop\ClientTrait;
use ClientEventBundle\Services\TelegramLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class RetryDispatchCommand
 *
 * @package ClientEventBundle\Command
 */
class RetryDispatchCommand extends Command
{
    use ClientTrait;

    /** @var ContainerInterface $container */
    private $container;
    
    /** @var EntityManagerInterface $entityManager */
    private $entityManager;
    
    /** @var QueueEventDispatcherInterface $queueEventDispatcher */
    private $queueEventDispatcher;

    /** @var array $retryConfig */
    private $retryConfig;

    /** @var TelegramLogger $telegramLogger */
    private $telegramLogger;

    /** @var int $settingMemory */
    private $settingMemory;

    /**
     * RetryDispatchCommand constructor.
     *
     * @param ContainerInterface $container
     * @param EntityManagerInterface $entityManager
     * @param QueueEventDispatcherInterface $queueEventDispatcher
     * @param TelegramLogger $telegramLogger
     */
    public function __construct(
        ContainerInterface $container,
        EntityManagerInterface $entityManager,
        QueueEventDispatcherInterface $queueEventDispatcher,
        TelegramLogger $telegramLogger
    ) {
        parent::__construct();

        $this->container = $container;
        $this->queueEventDispatcher = $queueEventDispatcher;
        $this->entityManager = $entityManager;
        $this->retryConfig = $container->getParameter('client_event.retry_dispatch');
        $this->telegramLogger = $telegramLogger;
        $this->settingMemory = $container->getParameter('client_event.max_memory_use');
    }

    protected function configure()
    {
        $this->setName('event:dispatch:retry')
            ->setDescription('Команда для повторной отправки событий в очередь');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int|void|null
     *
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initClient();
        
        $handler = function () use ($input, $output) {
            /** @var Event[] $events */
            $events = $this->entityManager->getRepository(Event::class)
                ->createQueryBuilder('event')
                ->andWhere('event.status = :status')
                ->andWhere('event.countAttempts < :count')
                ->setParameters(['status' => Event::STATUS_DISPATH_FAIL, 'count' => $this->retryConfig['count_retry']])
                ->getQuery()
                ->getResult();

            $this->setCountJobReadyClient(count($events));

            foreach ($events as $eventDb) {
                try {
                    $count = $eventDb->getCountAttempts();
                    $currentCount = $count + 1;
                    $time = (new \DateTime())->getTimestamp();

                    // если первая попытка и текущее время меньше времени создания + время ожидания до первой нотификации
                    if (0 === $count && (new \DateTime())->getTimestamp() < ($eventDb->getCreated()->getTimestamp() + $this->retryConfig['timeout_before_notification'])) {
                        continue;
                    }

                    if ($currentCount !== 0 && $currentCount <= $this->retryConfig['count_retry_for_notification'] && $time < ($eventDb->getRetryDate() + $this->retryConfig['timeout_before_notification'])) {
                        continue;
                    }

                    if ($currentCount !== 0 && $currentCount > $this->retryConfig['count_retry_for_notification'] && $time < ($eventDb->getRetryDate() + $this->retryConfig['timeout_after_notification'])) {
                        continue;
                    }

                    if ($currentCount !== 0 && $currentCount === $this->retryConfig['count_retry_for_notification'] && $time < ($eventDb->getRetryDate() + $this->retryConfig['timeout_before_notification'])) {
                        continue;
                    }

                    /** @var \ClientEventBundle\Event $event */
                    $event = unserialize($eventDb->getData());
                    $this->queueEventDispatcher->dispatch($event->getEventName(), $event);
                    $this->entityManager->remove($eventDb);
                    $this->entityManager->flush();
                } catch (ProducerException $producerException) {
                    $eventDb->setCountAttempts($currentCount);
                    $eventDb->setRetryDate((new \DateTime())->getTimestamp());
                    $this->entityManager->flush();

                    if ($currentCount === $this->retryConfig['count_retry_for_notification'] || $currentCount === $this->retryConfig['count_retry']) {
                        $this->telegramLogger->setCurrentEvent($eventDb->getHash(), $eventDb->getEventName());
                        $this->telegramLogger->setFail($producerException, " Не удалось поставить событие в очередь!!! \nКоличество попыток: $currentCount из {$this->retryConfig['count_retry']} \n");
                    }
                } catch (ORMException $exception) {
                    throw $exception;
                } catch (\Exception | \Throwable $exception) {
                    $this->telegramLogger->setFail($exception);
                }
            }

            $this->entityManager->clear(Event::class);
        };
        
        $this->addJob($handler);
        $this->start();

        return 0;
    }
}
