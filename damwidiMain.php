<?php
// previous cron job: wget -q http://www.damwidi.com/damwidiMain.php?mode=updateDatabases  (as of 23-Nov-18)

include_once 'php-includes/init.php';

use Monolog\Logger;
use Logtail\Monolog\LogtailHandler;
Logs::$logger = new Logger(ENV);
Logs::$logger->pushHandler(new LogtailHandler(LOGTAIL_TOKEN));

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

switch($mode){
    case 'updateDatabases':
        // correct order of operation:
        // 1. updateBivioTransactions
        // 2. updatePerformanceData
        // 3. updateValueTable
        // 4. updateHistoryTable

        $start = date('Y-m-d H:i:s');               // store start time used to determine function duration
        updateBivioTransactions($verbose);
        updatePerformanceData($verbose, $debug);
        sleep(10);
        updateValueTable($verbose, $debug);
        sleep(10);
        updateHistoryTable($verbose, $debug);

        // create notifications
        $end      = date('Y-m-d H:i:s');
        $duration = strtotime($end)-strtotime($start);
        $table    = "complete update";

        if ($verbose) show($start." start");
        show($end." - ".$table." - ".date('H:i:s', mktime(0, 0, $duration)));
        writeAirTableRecord($table, $start, $duration);
        break;

    // the following four functions run daily to update mySQL databases
    // 1
    case 'updateBivioTransactions':
        updateBivioTransactions($verbose, $debug, $stdin);
        break;

    // 2
    case 'updatePerformanceData':
        updatePerformanceData($verbose, $debug, $stdin);
        break;

    // 3
    case 'updateValueTable':
        updateValueTable($verbose, $debug, $stdin);
        break;

    // 4
    case 'updateHistoryTable':
        updateHistoryTable($verbose, $debug, $stdin);
        break;

    // the following are required API endpoints

    case 'returnAboveBelow':
        returnAboveBelow($verbose, $debug);
        break;

    case 'returnDamwidiOHLC':
        returnDamwidiOHLC($verbose, $debug);
        break;

    case 'returnIntraDayData':
        returnIntraDayData($verbose, $debug, false);
        break;

    case 'returnSectorTimeframePerformanceData':
        returnSectorTimeframePerformanceData($verbose, $debug, isset($_GET['version']) ? $_GET['version'] : 'v1' );
        break;

    case 'returnTransactions':
        returnTransactions($verbose, $debug);
        break;

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
    case 'test1':
        if(ENV == 'DEV') {
        }
        break;

    case 'test2':
        if(ENV == 'DEV') {
            $startDate = date('Y-m-d', strtotime('-1 years'));
            $alphaData = retrievePriceDataAlpha('AMZN', 'daily', $startDate, $saveData = false, $verbose = true, $debug);
        }
        break;

    default:
        // no valid mode supplied
}
?>