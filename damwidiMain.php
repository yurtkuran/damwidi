<?php
include_once 'php-includes/init.php';

if (isset($_GET['verbose'])){
    $verbose = $_GET['verbose']==1 ? TRUE : FALSE;
} else {
    $verbose = FALSE;
}

if (isset($_GET['debug'])){
    $debug = $_GET['debug']==1 ? TRUE : FALSE;
} else {
    $debug = FALSE;
}

if (isset($_GET['mode'])){
    switch($_GET['mode']){
        case 'updateDatabases':
            // correct order of operation:
            // 1. updateBivioTransactions
            // 2. updateValueTable
            // 3. updatePerformanceData
            // 4. updateHistoryTable
            updateBivioTransactions($verbose);
            updateValueTable($verbose, $debug);
            sleep(10);
            updatePerformanceData($verbose, $debug);
            sleep(10);
            updateHistoryTable($verbose, $debug);
            writeAirTableRecord(date('Y-m-d H:i:s')." - Complete: Update Damwidi");
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

        case 'viewPerformanceData':
            viewPerformanceData();
            break;

        case 'bivioUnstick':
            bivioUnstick($verbose);
            break;

        case 'displayUnstickLog':
            displayUnstickLog();
            break;

        case 'buildPortfolioTable':
            buildPortfolioTable();
            break;

        case 'buildAllocationTable':
            buildAllocationTable();
            break;

        case 'test':
            $dbc = connect();
            // $message = date('Y-m-d H:i:s').' - test';
            // show($message);
            // writeAirTableRecord($message);
            break;
    } 
}
?>