<?php

declare(strict_types=1);

namespace ClientEventBundle\Util;

/**
 * Class HealthChecker
 *
 * @package ClientEventBundle\Util
 */
class HealthChecker
{
    /** @var array | null $memory */
    private static $memory;
    
    /** @var array | null $cpu */
    private static $cpu;

    /**
     * @return array
     */
    public static function getServerInfo(): array
    {
        if (is_null(self::$memory) || is_null(self::$cpu)) {
            self::changeServerInfo();
        }
        
        return [
            'memory' => self::$memory,
            'cpu' => self::$cpu,
        ];
    }

    public static function changeServerInfo(): void
    {
        $cores = self::getCpuCores();
        $totalLoad = (float) shell_exec("ps aux | awk '{s += $3} END {print s}'");
        $averageCore = round($totalLoad/$cores, 2) . ' %';

        self::$memory = self::getMemoryInfo();
        self::$cpu = [
            'countCore' => $cores,
            'averageProcessorLoad' => $averageCore,
            'loadAverage' => trim(shell_exec('uptime')),
        ];
    }

    /**
     * @param array $memory
     * @param array $cpu
     */
    public static function setServerInfo(array $memory, array $cpu): void
    {
        self::$memory = $memory;
        self::$cpu = $cpu;
    }

    /**
     * @return int
     */
    public static function getCpuCores()
    {
        $cmd = "uname";
        $OS = strtolower(trim(shell_exec($cmd)));

        switch($OS) {
            case('linux'):
                $cmd = "cat /proc/cpuinfo | grep processor | wc -l";
                break;
            case('darwin'):
            case('freebsd'):
                $cmd = "sysctl -a | grep 'hw.ncpu' | cut -d ':' -f2";
                break;
        }

        if ($cmd != 'uname') {
            $cpuCoreNo = intval(trim(shell_exec($cmd)));
        }

        return empty($cpuCoreNo) ? 1 : $cpuCoreNo;
    }

    /**
     * @return array
     */
    public static function getMemoryInfo(): array
    {
        $response = [
            'total' => null,
            'used' => null,
            'swapTotal' => null,
            'swapUsed' => null,
        ];
        $meminfo = shell_exec('cat /proc/meminfo');
        $meminfo = explode("\n", $meminfo);
        $memFree = null;
        $swapFree = null;

        foreach ($meminfo as $item) {
            if (strpos($item, 'MemTotal') !== false) {
                $response['total'] = (int) trim(substr($item, strlen('MemTotal:')), " \t\n\r\0\x0BkB");
            }

            if (strpos($item, 'MemAvailable') !== false) {
                $memFree = (int) trim(substr($item, strlen('MemAvailable:')), " \t\n\r\0\x0BkB");
            }

            if (strpos($item, 'SwapTotal') !== false) {
                $response['swapTotal'] = (int) trim(substr($item, strlen('SwapTotal:')), " \t\n\r\0\x0BkB");
            }

            if (strpos($item, 'SwapFree') !== false) {
                $swapFree = (int) trim(substr($item, strlen('SwapFree:')), " \t\n\r\0\x0BkB");
            }
        }

        if (!is_null($memFree) && is_integer($response['total'])) {
            $response['used'] = self::convertSize($response['total'] - $memFree);
        }

        if (!is_null($swapFree) && is_integer($response['swapTotal'])) {
            $response['swapUsed'] = self::convertSize($response['swapTotal'] - $swapFree);
        }

        $response['total'] = self::convertSize($response['total']);
        $response['swapTotal'] = self::convertSize($response['swapTotal']);

        return $response;
    }

    /**
     * @return float
     */
    public static function getAverageCore(): float
    {
        $prevStat = shell_exec('cat /proc/stat | grep cpu | (head -1)');
        $prevStat = str_replace(' ', ':', $prevStat);
        $prevStat = explode(':', $prevStat);
        unset($prevStat[0]);
        $prevStat = array_values($prevStat);

        usleep(100000);

        $stat = shell_exec('cat /proc/stat | grep cpu | (head -1)');
        $stat = str_replace(' ', ':', $stat);
        $stat = explode(':', $stat);
        unset($stat[0]);
        $stat = array_values($stat);

        $prevIdle = (int) $prevStat[4] + (int) $prevStat[5];
        $idle = (int) $stat[4] + (int) $stat[5];

        $prevNonIdle = (int) $prevStat[1] + (int) $prevStat[2] + (int) $prevStat[3] + (int) $prevStat[6] + (int) $prevStat[7] + (int) $prevStat[8];
        $nonIdle = (int) $stat[1] + (int) $stat[2] + (int) $stat[3] + (int) $stat[6] + (int) $stat[7] + (int) $stat[8];

        $prevTotal = $prevIdle + $prevNonIdle;
        $total = $idle + $nonIdle;

        $totald = $total - $prevTotal;
        $idled = $idle - $prevIdle;

        return round(($totald - $idled) / $totald * 100, 2);
    }

    /**
     * @param $size
     *
     * @return string
     */
    public static function convertSize($size)
    {
        $unit = ['KB','MB','GB','TB'];

        return @round($size/pow(1024, ($i=floor(log($size,1024)))) ,2) .' ' . $unit[$i];
    }
}
