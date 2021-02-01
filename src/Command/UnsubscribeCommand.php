<?php

namespace ClientEventBundle\Command;

use ClientEventBundle\Exception\ValidateException;
use ClientEventBundle\Helper\DataHelper;
use ClientEventBundle\Services\SubscriptionService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class UnsubscribeCommand
 *
 * @package ClientEventBundle\Command
 */
class UnsubscribeCommand extends Command
{
    /** @var SubscriptionService $subscriptionService */
    private $subscriptionService;

    /** @var string $eventServerAddress */
    private $eventServerAddress;

    /**
     * SubscribeCommand constructor.
     *
     * @param SubscriptionService $subscriptionService
     * @param string $eventServerAddress
     */
    public function __construct(
        SubscriptionService $subscriptionService,
        string $eventServerAddress
    ) {
        $this->subscriptionService = $subscriptionService;
        $this->eventServerAddress = $eventServerAddress;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('event:unsubscribe')
            ->setDescription('Unsubscribe to receive events from server');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int | void | null
     *
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \ReflectionException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $this->subscriptionService->unsubscribe($this->eventServerAddress);

            $output->writeln('unsubscribe success');
        } catch (ValidateException $validateException) {
            $errorText = DataHelper::arrayImplode(' : ', '',  $validateException->errors);
            $output->writeln("<error>$errorText</error>");

            throw $validateException;
        }

        return 0;
    }
}
