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
    case 'test':
        $symbol = isset($_GET['symbol']) ? $_GET['symbol'] : null;
        if (!is_null($symbol)) {
            $quote = retrieveYahooQuote($symbol, $verbose); //['regularMarketPrice'];

            if (isset($quote['regularMarketPrice'])) {
                show($quote);

            } else {
                show("not set");
            }
        } else {
            echo "no symbol";
        }
        break;

    default:
    // no valid mode supplied
}
?>