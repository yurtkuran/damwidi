<?php
// previous cron job: wget -q http://www.damwidi.com/damwidiMain.php?mode=updateDatabases  (as of 23-Nov-18)

include_once 'php-includes/init.php';

// setup environment defaults
$verbose = FALSE; 
$debug   = FALSE;

if (defined('STDIN')) {
    // command line
    $mode    = isset($argv[1]) ? $argv[1] : NULL;
    $verbose = (isset($argv[2]) and $argv[2]) == 1 ? TRUE : FALSE;
    $debug   = (isset($argv[3]) and $argv[3]) == 1 ? TRUE : FALSE;
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
        // 2. updateValueTable
        // 3. updatePerformanceData
        // 4. updateHistoryTable
        $start = date('Y-m-d H:i:s');               // store start time used to determine function duration
        updateBivioTransactions($verbose);
        updateValueTable($verbose, $debug);
        sleep(10);
        updatePerformanceData($verbose, $debug);
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

    case 'updateBivioTransactions':
        updateBivioTransactions($verbose);
        break;

    case 'updateHistoryTable':
        updateHistoryTable($verbose, $debug);
        break;

    case 'updatePerformanceData':
        updatePerformanceData($verbose, $debug);
        break;

    case 'updateValueTable':
        updateValueTable($verbose, $debug);
        break;

    case 'returnAboveBelow':
        returnAboveBelow($verbose, $debug);
        break;

    case 'returnDamwidiOHLC':
        returnDamwidiOHLC($verbose, $debug);
        break;

    case 'returnDetails':
        returnDetails($verbose, $debug);
        break;

    case 'returnIntraDayData':
        returnIntraDayData($verbose, $debug);
        break;

    case 'returnSectorTimeframePerformanceData':
        returnSectorTimeframePerformanceData($verbose, $debug);
        break;

    case 'returnTransactions':
        returnTransactions($verbose, $debug);
        break;

    case 'retrieveBatchDataAlpha':
        $symbols='SPY,XLB,XLE,XLF,XLI,XLK,XLP,XLU,XLV,XLY';
        retrieveBatchDataAlpha($symbols, $loadNewData = true, $saveData = true, $verbose, $debug);
        break;

    case 'retrieveBivioTransactions':
        retrieveBivioTransactions($verbose);
        break;

    case 'retrievePriceDataAlpha':
        $startDate = date('Y-m-d', strtotime('-1 years'));
        $startDate = date('Y-m-d', strtotime('1/1/2018'));
        retrievePriceDataAlpha('SPY', 'daily', $startDate, $loadNewData = true, $saveData = false, $verbose, $debug);
        break;

    case 'retrievePriceDataBarChart':
        $startDate = date('Y-m-d', strtotime('-2 years'));
        // $startDate = date('Y-m-d', strtotime('1/1/2018'));
        retrievePriceDataBarChart('SPY', 'daily', $startDate, $loadNewData = true, $saveData = false, $verbose, $debug);
        break;

    case 'retrieveSectorWeights':
        retrieveSectorWeights($verbose, $debug);
        break;

    case 'buildPortfolioTable':
        buildPortfolioTable();
        break;

    case 'buildAllocationTable':
        buildAllocationTable();
        break;

    case 'updateDamwidiBasket':
        updateDamwidiBasket($_GET['symbol'], $_GET['description'] );
        break;

    // admin & report functions
    case 'returnBasket':
        returnBasket($verbose, $debug);
        break;

    case 'displayUnstickLog':
        displayUnstickLog();
        break;

    case 'bivioUnstick':                // show value DB records where the calculated share value does not equal the bivio share value
        bivioUnstick();
        break;

    case 'returnPerformanceData':
        returnPerformanceData($verbose, $debug);
        break;

    case 'updateBasketDescriptions':
        updateBasketDescriptions($verbose, $debug);
        break;

    // test functions
    case 'test':
        returnOpenPositions('2019/01/21',true, false);
        break;

    case 'test2':
        retrieveIEXBatchData('amzn', false, $verbose, $debug);
        break;

    case 'test3':
        show(php_uname());
        show(stristr(php_uname(),'ubuntu') ? 'local' : 'host' );
        writeAirTableRecord('test', 1, 2);
        phpinfo(INFO_GENERAL);
        break;
        
    case 'test4':
        bivioLogin($verbose);
        break;
    
    default:
        // no valid mode supplied
} 
?>