<?php
// previous cron job: wget -q http://www.damwidi.com/damwidiMain.php?mode=updateDatabases  (as of 23-Nov-18)

include_once 'php-includes/init.php';

// setup environment defaults
$verbose = FALSE;
$debug   = FALSE;
$stdin   = FALSE;

if (defined('STDIN')) {
    // command line
    $mode    = isset($argv[1]) ? $argv[1] : NULL;
    $verbose = (isset($argv[2]) and $argv[2]) == 1 ? TRUE : FALSE;
    $debug   = (isset($argv[3]) and $argv[3]) == 1 ? TRUE : FALSE;
    $stdin   = TRUE;
} else {
    // web call
    $mode    = isset($_GET['mode']) ? $_GET['mode'] : NULL;
    $verbose = (isset($_GET['verbose']) and $_GET['verbose']) == 1 ? TRUE : FALSE;
    $debug   = (isset($_GET['debug']) and $_GET['debug']) == 1 ? TRUE : FALSE;
}

Logs::initialize(ENV, defined('LOGTAIL_TOKEN') ? LOGTAIL_TOKEN : '');

function runScheduledJob($job, $callback, $stdin)
{
    $startedAt = Logs::startJob($job);

    try {
        call_user_func($callback);
        Logs::completeJob($startedAt);
        return true;
    } catch (Throwable $e) {
        Logs::failJob($startedAt, $e);
        $message = $job.' failed: '.Logs::sanitizeMessage($e->getMessage());

        if ($stdin && defined('STDERR')) {
            fwrite(STDERR, $message.PHP_EOL);
        } else {
            http_response_code(500);
            echo 'Scheduled update failed';
        }

        return false;
    }
}

switch($mode){
    //
    // correct order of operation:
    // 1. updateBivioTransactions
    // 2. updatePerformanceData
    // 3. updateValueTable
    // 4. updateHistoryTable
    //
    // the following four functions run daily to update mySQL databases
    // 1
    case 'updateBivioTransactions':
        if (!$stdin) {
            http_response_code(403);
            echo 'updateBivioTransactions is available from the command line only';
            break;
        }

        $success = runScheduledJob('updateBivioTransactions', function () use ($verbose, $debug, $stdin) {
            updateBivioTransactions($verbose, $debug, $stdin);
        }, $stdin);
        if (!$success) exit(1);
        break;

    // 2
    case 'updatePerformanceData':
        $success = runScheduledJob('updatePerformanceData', function () use ($verbose, $debug, $stdin) {
            updatePerformanceData($verbose, $debug, $stdin);
        }, $stdin);
        if (!$success && $stdin) exit(1);
        break;

    // 3
    case 'updateValueTable':
        $success = runScheduledJob('updateValueTable', function () use ($verbose, $debug, $stdin) {
            updateValueTable($verbose, $debug, $stdin);
        }, $stdin);
        if (!$success && $stdin) exit(1);
        break;

    // 4
    case 'updateHistoryTable':
        $success = runScheduledJob('updateHistoryTable', function () use ($verbose, $debug, $stdin) {
            updateHistoryTable($verbose, $debug, $stdin);
        }, $stdin);
        if (!$success && $stdin) exit(1);
        break;

    case 'logDatabaseUpdateSummary':
        if (!$stdin) {
            http_response_code(403);
            echo 'logDatabaseUpdateSummary is available from the command line only';
            break;
        }

        $status = isset($argv[2]) ? $argv[2] : '';
        $failedJobs = isset($argv[3]) && $argv[3] !== '' ? explode(',', $argv[3]) : array();
        $durationSeconds = isset($argv[4]) ? filter_var($argv[4], FILTER_VALIDATE_INT) : false;
        $scheduledJobs = array(
            'updateBivioTransactions',
            'updatePerformanceData',
            'updateValueTable',
            'updateHistoryTable',
        );

        $validStatus = $status === 'successful' || $status === 'fail';
        $validFailedJobs = empty(array_diff($failedJobs, $scheduledJobs));
        $validOutcome = ($status === 'successful' && empty($failedJobs))
            || ($status === 'fail' && !empty($failedJobs));

        if (!$validStatus || !$validFailedJobs || !$validOutcome || $durationSeconds === false || $durationSeconds < 0) {
            fwrite(STDERR, 'Invalid database update summary arguments'.PHP_EOL);
            exit(1);
        }

        $logContext = array(
            'event' => 'database_updates.summary',
            'status' => $status,
            'jobs' => $scheduledJobs,
            'failed_jobs' => $failedJobs,
            'duration_seconds' => $durationSeconds,
        );

        if ($status === 'successful') {
            Logs::$logger->info('Database updates successful', $logContext);
        } else {
            Logs::$logger->error('Database updates fail: '.implode(', ', $failedJobs), $logContext);
        }
        break;

    // the following are required API endpoints

    case 'returnAboveBelow':
        returnAboveBelow($verbose, $debug);
        break;

    // case 'returnDamwidiOHLC':
    //     returnDamwidiOHLC($verbose, $debug);
    //     break;

    case 'returnIntraDayData':
        returnIntraDayData($verbose, $debug, false);
        break;

    // case 'returnSectorTimeframePerformanceData':
    //     returnSectorTimeframePerformanceData($verbose, $debug, isset($_GET['version']) ? $_GET['version'] : 'v1' );
    //     break;

    // case 'returnTransactions':
    //     returnTransactions($verbose, $debug);
    //     break;

    case 'returnUnstickLog':
        echo json_encode(returnUnstickLogData());
        break;

    case 'environment':
        show(ENV);
        show(date('Y-m-d H:i:s'));
        show(php_uname());
        phpinfo();
        break;

    // show value DB records where the calculated share value does not equal the bivio share value
    case 'bivioUnstick':
        bivioUnstick();
        break;

    // test functions - used only in DEV
    case 'test':
        retrieveYahooQuote("AAPL", $verbose);
        break;

    default:
    // no valid mode supplied
}
?>
