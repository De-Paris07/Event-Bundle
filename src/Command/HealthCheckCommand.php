<?php

declare(strict_types=1);

namespace ClientEventBundle\Command;

use ClientEventBundle\Exception\ConnectTimeoutException;
use ClientEventBundle\Services\HealthCheckService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Class HealthCheckCommand
 *
 * @package ClientEventBundle\Command
 */
class HealthCheckCommand extends Command
{
    /** @var HealthCheckService $healthCheckService */
    private $healthCheckService;

    /**
     * HealthCheckCommand constructor.
     *
     * @param HealthCheckService $healthCheckService
     */
    public function __construct(HealthCheckService $healthCheckService)
    {
        parent::__construct();
        $this->healthCheckService = $healthCheckService;
    }

    protected function configure()
    {
        $this->setName('event:health:check')
            ->setDescription('Информация о сервере и текущих воркерах')
            ->addOption(
                'service',
                null,
                InputOption::VALUE_REQUIRED,
                'service name',
                null
            );
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
        $output->writeln(sprintf('<info>%s</info>', 'Получение данных сервера'));
        $response = null;

        try {
            if (!is_null($serviceName = $input->getOption('service'))) {
                $response = $this->healthCheckService->getInfoByServiceName($serviceName);
            } else {
                $response = $this->healthCheckService->getCurrentInfo();   
            }
        } catch (ConnectTimeoutException $timeoutException) {
            $output->writeln("<error>Сервис не запущен</error>");
        }

        if (!is_null($response) && isset($response['error'])) {
            $output->writeln("<error>{$response['error']}</error>");
            return 0;
        }

        if (is_null($response)) {
            return 0;
        }

        $ioMemory = new SymfonyStyle($input, $output);
        $ioMemory->title('Память');
        $headers = ['Всего', 'Использовано', 'Свап', 'Использовано свапа'];
        $ioMemory->table($headers, [$response['memory']]);

        $ioCpu = new SymfonyStyle($input, $output);
        $ioCpu->title('Процессор');
        $headers = ['Количество ядер', 'Средняя загрузка процессора', 'uptime'];
        $ioCpu->table($headers, [$response['cpu']]);
        
        $process = new Table($output);
        $process->setHeaderTitle('Отслеживаемые процессы');
        $process->setStyle('box-double');
        $process->setHeaders(['Команда', 'Тип', 'Запущен', 'Дата старта', 'Количество процессов', 'Задач в очереди', 'Очередь', 'Демон', 'Расписание']);
        $commands = $response['workers'];
        $processData = [];
        
        foreach ($commands as $name => $command) {
            $type = '';
            
            switch ($command['type']) {
                case 'consumer':
                    $type = 'Консьюмер';
                    break;
                case 'cron':
                    $type = 'Крон';
                    break;
                default:
                    $type = 'Прочее';
            }
            
            $processData[] = [
                $name,
                $type,
                $command['countInstanse'] > 0 ? 'Да' : 'Нет',
                !is_null($command['timeLastStart']) ? $command['timeLastStart'] : $command['dateStart'],
                $command['countInstanse'],
                $command['countJob'],
                $command['channel'] ?? null,
                $command['isDaemon'] ? 'Да' : 'Нет',
                $command['schedule'] ?? null,
            ];
            $processData[] = new TableSeparator();
        }
        
        array_pop($processData);
        
        $process->setRows($processData);
        $process->render();
        
        return 0;
    }
}
