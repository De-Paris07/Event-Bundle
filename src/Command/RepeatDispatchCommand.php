<?php

declare(strict_types=1);

namespace ClientEventBundle\Command;

use ClientEventBundle\Dispatcher\QueueEventDispatcherInterface;
use ClientEventBundle\Event\RetryEvent;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class RepeatDispatchCommand
 *
 * @package ClientEventBundle\Command
 */
class RepeatDispatchCommand extends Command
{
    /** @var ContainerInterface $container */
    private $container;
    
    /** @var QueueEventDispatcherInterface $eventDispatcher */
    private $eventDispatcher;

    /**
     * RepeatDispatchCommand constructor.
     *
     * @param ContainerInterface $container
     * @param QueueEventDispatcherInterface $eventDispatcher
     */
    public function __construct(ContainerInterface $container, QueueEventDispatcherInterface $eventDispatcher)
    {
        parent::__construct();
        
        $this->container = $container;
        $this->eventDispatcher = $eventDispatcher;
    }

    protected function configure()
    {
        $this->setName('event:dispatch')
            ->setDescription('Получить событие по id')
            ->addArgument('event', InputArgument::OPTIONAL, 'event id or "last" or "error"', 'last')
            ->addOption('priority', 'p', InputOption::VALUE_OPTIONAL, 'most urgent: 0, least urgent: 4294967295', 1024);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * 
     * @return int | void | null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $eventId = $input->getArgument('event');
        $retryPriority = (int) $input->getOption('priority');
        
        $event = new RetryEvent();
        $event->setRetryEventId($eventId)
            ->setRetryPriority($retryPriority);
        
        $this->eventDispatcher->dispatchRetry($event);

        return 0;
    }
}
