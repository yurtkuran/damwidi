<?php

class Logs
{
    const DEFAULT_PLATFORM = 'local';

    public static $logger;

    private static $platform = self::DEFAULT_PLATFORM;
    private static $baseContext = array();
    private static $jobContext = array();
    private static $jobResult = array();

    public static function initialize($environment, $logtailToken)
    {
        $platformConfiguration = self::resolvePlatform();
        self::$platform = $platformConfiguration['platform'];
        self::$baseContext = array(
            'application' => 'damwidi',
            'platform' => self::$platform,
            'environment' => $environment,
            'host' => gethostname() ?: 'unknown',
        );

        self::$logger = new \Monolog\Logger('damwidi');
        $stderrLevel = empty($logtailToken) ? \Monolog\Logger::DEBUG : \Monolog\Logger::ERROR;
        $stderrHandler = new \Monolog\Handler\StreamHandler('php://stderr', $stderrLevel);
        $stderrHandler->setFormatter(new \Monolog\Formatter\JsonFormatter());
        self::$logger->pushHandler($stderrHandler);

        if (!empty($logtailToken)) {
            self::$logger->pushHandler(new \Logtail\Monolog\LogtailHandler($logtailToken));
        }

        self::$logger->pushProcessor(function ($record) {
            $record['context'] = array_merge($record['context'], self::$baseContext, self::$jobContext);
            return $record;
        });

        if (!$platformConfiguration['configured']) {
            self::$logger->warning('logging.platform.defaulted', array(
                'configuration' => 'DAMWIDI_PLATFORM',
                'default' => self::DEFAULT_PLATFORM,
            ));
        }
    }

    public static function startJob($job)
    {
        self::$jobContext = array(
            'job' => $job,
            'run_id' => bin2hex(random_bytes(8)),
        );
        self::$jobResult = array();

        $startedAt = hrtime(true);
        self::$logger->info('job.started - '.$job);

        return $startedAt;
    }

    public static function addJobResult(array $context)
    {
        self::$jobResult = array_merge(self::$jobResult, $context);
    }

    public static function completeJob($startedAt)
    {
        self::$logger->info('job.completed - '.self::$jobContext['job'], array_merge(
            array('duration_ms' => self::durationMilliseconds($startedAt)),
            self::$jobResult
        ));
        self::clearJob();
    }

    public static function failJob($startedAt, $exception)
    {
        self::$logger->error('job.failed - '.self::$jobContext['job'], array(
            'duration_ms' => self::durationMilliseconds($startedAt),
            'exception_class' => get_class($exception),
            'error_code' => $exception->getCode(),
            'error_message' => self::sanitizeMessage($exception->getMessage()),
        ));
        self::clearJob();
    }

    public static function sanitizeMessage($message)
    {
        $message = preg_replace(
            '/((?:api[_-]?key|token|password|secret|x2)=)[^&\s]+/i',
            '$1[REDACTED]',
            (string)$message
        );

        return preg_replace('/(authorization:\s*bearer\s+)\S+/i', '$1[REDACTED]', $message);
    }

    public static function verbose($enabled, $message, $context = null)
    {
        if (!$enabled) return;

        $job = array_key_exists('job', self::$jobContext) ? self::$jobContext['job'] : 'application';
        $output = date('c').' ['.self::$platform.'] ['.$job.'] '.$message;
        if ($context !== null) {
            $encodedContext = json_encode($context, JSON_UNESCAPED_SLASHES);
            if ($encodedContext !== FALSE) {
                $output .= ' '.$encodedContext;
            }
        }

        if (defined('STDOUT')) {
            fwrite(STDOUT, $output.PHP_EOL);
        } else {
            echo htmlspecialchars($output, ENT_QUOTES, 'UTF-8')."<br>\n";
        }
    }

    public static function getPlatform()
    {
        return self::$platform;
    }

    private static function resolvePlatform()
    {
        $configured = false;
        $platform = getenv('DAMWIDI_PLATFORM');
        if ($platform !== false && trim($platform) !== '') {
            $configured = true;
        } else if (defined('DAMWIDI_PLATFORM')) {
            $platform = constant('DAMWIDI_PLATFORM');
            $configured = trim((string)$platform) !== '';
        }

        if (!$configured) {
            $platform = self::DEFAULT_PLATFORM;
        }

        $platform = strtolower(trim((string)$platform));
        $supportedPlatforms = array('local', 'digitalocean', 'hostgator');
        if (!in_array($platform, $supportedPlatforms, true)) {
            throw new \UnexpectedValueException(
                'DAMWIDI_PLATFORM must be one of: '.implode(', ', $supportedPlatforms)
            );
        }

        return array(
            'platform' => $platform,
            'configured' => $configured,
        );
    }

    private static function durationMilliseconds($startedAt)
    {
        return round((hrtime(true) - $startedAt) / 1000000, 2);
    }

    private static function clearJob()
    {
        self::$jobContext = array();
        self::$jobResult = array();
    }
}

?>
