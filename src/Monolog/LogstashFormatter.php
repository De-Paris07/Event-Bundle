<?php

declare(strict_types=1);

namespace ClientEventBundle\Monolog;

use Monolog\Formatter\NormalizerFormatter;

/**
 * Class LogstashFormatter
 *
 * @package ClientEventBundle\Monolog
 */
class LogstashFormatter extends NormalizerFormatter
{
    /**
     * @var string the name of the system for the Logstash log message, used to fill the @source field
     */
    protected $systemName;

    /**
     * @var string an application name for the Logstash log message, used to fill the @type field
     */
    protected $applicationName;

    /**
     * @param string $applicationName the application that sends the data, used as the "type" field of logstash
     * @param string | null $systemName      the system/machine name, used as the "source" field of logstash, defaults to the hostname of the machine
     */
    public function __construct(string $applicationName, $systemName = null)
    {
        // logstash requires a ISO 8601 format date with optional millisecond precision.
        parent::__construct('Y-m-d\TH:i:s.uP');

        $this->systemName = $systemName ?: gethostname();
        $this->applicationName = $applicationName;
    }

    /**
     * {@inheritdoc}
     */
    public function format(array $record)
    {
        $record = parent::format($record);

        if (empty($record['datetime'])) {
            $record['datetime'] = gmdate('c');
        }
        $message = array(
            '@timestamp' => $record['datetime'],
            '@version' => 1,
            'host' => $this->systemName,
        );
        if (isset($record['message'])) {
            $message['message'] = $record['message'];
        }
        if (isset($record['channel'])) {
            $message['type'] = $record['channel'];
            $message['channel'] = $record['channel'];
        }
        if (isset($record['level_name'])) {
            $message['level'] = $record['level_name'];
        }
        if ($this->applicationName) {
            $message['type'] = $this->applicationName;
        }
        if (!empty($record['extra'])) {
            foreach ($record['extra'] as $key => $val) {
                $message[$key] = $val;
            }
        }
        if (!empty($record['context'])) {
            foreach ($record['context'] as $key => $val) {
                $message[$key] = $val;
            }
        }

        return $this->toJson($message) . "\n";
    }
}
