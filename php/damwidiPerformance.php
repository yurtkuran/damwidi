<?php
// update fields in the `data_performance` table for sectors, index and cash
function updatePerformanceData($verbose, $debug, $stdin = false){
    if ($verbose) show("--- UPDATE PERFORMANCE DATA ---");
    Logs::$logger->info("start update performance");


    // store start time used to determine function duration
    $start = date('Y-m-d H:i:s');

    // load previous 2 years of data
    $startDate = date('Y-m-d', strtotime('-2 years'));

    // add and remove stock positions from performance MySQL table
    refreshPerformanceTable($verbose);

    // load all sectors, stocks, index and fund
    $sectors = loadSectors('SKIF');

    // if symbol is passed as query parameter, only update that single sector/symbol, used to update single row in performance table
    if (isset($_GET['symbol'])) {
        $symbol  = strtoupper($_GET['symbol']);

        // verify symbol is in sectors array
        if (array_key_exists($symbol, $sectors)) {
            // $sectors = $sectors[$symbol];

            $sectors = array_intersect_key($sectors, array_flip(array($symbol)));
        } else {
            show($symbol.' is not in performance table, execution terminated');
            die();
        }
    }

    // load timeframe details
    $timeFrames = json_decode(file_get_contents("./config/comparison.json"),1);

    // loop through all sectors
    foreach($sectors as $sector){
        if ($sector['sector'] <> 'DAM' ){
            $chartData     = retrievePriceDataAlpha($sector['sector'], 'daily', $startDate, true, false, $verbose, true);  // loadNewData, saveData, verbose, debug
            $priceData     = $chartData['seriesData'];
            $lastRefreshed = $chartData['lastRefreshed'];


        } else {
            $priceData     = returnDamwidiData();
            $lastRefreshed = array_keys($priceData)[0];
        }

        // init array
        $previous = array_slice($priceData, 0, 1);
        $performanceData[$sector['sector']] = array(
            'as-of'         => $lastRefreshed,
            'previous'      => current($previous)['close'],

            // sector weights data
            // 'weight'            => $sector['weight'],
            // 'effectiveDate'     => $sector['effectiveDate'],
            // 'fetchedDate'       => $sector['effectiveDate'],
            // 'sectorDescription' => $sector['sectorDescription']
        );

        // loop through all timeframes, add gain data
        $priceGain = array();
        foreach($timeFrames as $timeFrame){

            $performanceData[$sector['sector']][$timeFrame['period']] = priceGain($priceData, 0, $timeFrame['lengthDays'], 3)['gain'];

            $priceGain[$timeFrame['period']] = array_merge(array(
                'startDate' => (count($priceData) >= $timeFrame['lengthDays'] ? array_keys($priceData)[$timeFrame['lengthDays']] : '0'),
                'endDate'   => array_keys($priceData)[0],
            ), priceGain($priceData, 0, $timeFrame['lengthDays'], 3));
        }

        // add placeholder for YTD data
        $performanceData[$sector['sector']]['YTD'] = 0;

        // insert priceGain data
        $performanceData[$sector['sector']]['priceGain'] = $priceGain;

        // add YTD data
        $performanceData = returnYTDData($sector['sector'], $lastRefreshed, $performanceData, $priceData, $verbose);

        // sleep for a random amount of time to prevent rate limiting from AlphaVantage
        rateLimit();

        if($debug) break;
    }

    $performanceData = returnBasisData($lastRefreshed, $performanceData); // add basis & share data

    // $performanceData = returnSectorWeights($performanceData); // add sector weights
    savePerformanceData($performanceData, $verbose); // write to MySQL database

    saveCashBalance(returnCashBalance($lastRefreshed), $lastRefreshed); // write to MySQL database

    if ($verbose) show($performanceData);

    save("./data/performanceData.json", array(
        'lastRefreshed'  => $lastRefreshed,
        'performanceData' => $performanceData,
    ));

    // create notifications
    $end          = date('Y-m-d H:i:s');
    $duration     = strtotime($end)-strtotime($start);
    $table        = "performance";
    $log          = date('i:s', mktime(0, 0, strtotime($end)-strtotime($start)))." - ".$table;
    $notification = $end." - ".$table." - ".date('H:i:s', mktime(0, 0, strtotime($end)-strtotime($start)));
    Logs::$logger->info($log);

    if ($verbose) show($start." start");
    if (!$stdin) {
        show($notification);
    } else {
        echo $notification."\r\n";;
    }
    // writeAirTableRecord($table, $start, $duration);
}

// add/remove individual stocks from preformance table
function refreshPerformanceTable($verbose){

    // load open positions, needed to add individual stock positions to the 'data_performance' table
    $openPositions = returnOpenPositions(date("Y-m-d"));

    // load all sectors, stocks and index
    $sectors = loadSectors('SIK');

    // add new stock positions to performance table
    foreach ($openPositions as $symbol => $position){
        if(!array_key_exists($symbol,$sectors)){
            $companyData = retrieveIEXCompanyData($symbol);
            if ($verbose) show($symbol.', '.$companyData['companyName']);
            insertPerformanceStock($symbol, $companyData['companyName']);
        }
    }

    // load all stocks
    $stocks = loadSectors('K');

    // remove any stocks from performance table that are not in open positions
    foreach ($stocks as $stock => $position){
        if(!array_key_exists($stock,$openPositions)){
            if ($verbose) show($stock.' removed from Performance table');
            removePerformanceStock($stock);
        }
    }
}

// return shares and basis detail in `data_performance` table
function returnBasisData($lastRefreshed, $performanceData){

    // load open position share, amount and dividend detail
    $openPositions = returnOpenPositions($lastRefreshed);

    // loop through all sectors
    foreach($performanceData as $sector => $data){
        if (array_key_exists($sector, $openPositions)) {
            $performanceData[$sector]['basis']  = $openPositions[$sector]['basis'];
            $performanceData[$sector]['shares'] = $openPositions[$sector]['shares'];
        } else {
            Logs::$logger->notice(str_pad($sector, 6)." - setting shares and basis to 0");
            $performanceData[$sector]['basis']  = 0;
            $performanceData[$sector]['shares'] = 0;
        }
    }
    return $performanceData;
}

// return damwidi data properly formatted
function returnDamwidiData($startDate = null){
    $data = loadDamdidiValue(265);

    foreach($data as $candle){
        if ($startDate == null or $candle['date'] >= $startDate ) {
            $seriesData[$candle['date']] = array(
                'open'  => $candle['open'],
                'high'  => $candle['high'],
                'low'   => $candle['low'],
                'close' => $candle['close'],
            );
        }
    }
    return $seriesData;
}

// return sector weights from https://us.spindices.com/indices/equity/sp-500
function returnSectorWeights($performanceData){

    // determine starting date
    $sectorWeights = retrieveSectorWeights();

    // loop through all sectors
    if ($sectorWeights['status']) {
        foreach($performanceData as $sector => $data){
            if ($sector <> 'DAM' and $sector <> 'SPY' and array_key_exists($data['sectorDescription'],$sectorWeights['sectorWeights'])){
                $performanceData[$sector]['weight']        = $sectorWeights['sectorWeights'][$data['sectorDescription']]['sectorWeight']*100;
                $performanceData[$sector]['effectiveDate'] = $sectorWeights['effectiveDate'];
                $performanceData[$sector]['fetchedDate']   = $sectorWeights['fetchedDate'];
            }
        }
    }
    return $performanceData;
}

// return YTD detail in `data_performance` table
function returnYTDData($sector, $lastRefreshed, $performanceData, $priceData, $verbose){

    // determine starting date
    $startDate = date('Y', strtotime($lastRefreshed)).'-01-01';

    // determine previous close index in array
    $i = 0;
    while(array_keys($priceData)[$i] >= $startDate){
        $i++;
    }

    // calculate YTD price gain
    $priceGain = array_merge(array(
        'startDate' => array_keys($priceData)[$i],
        'endDate'   => array_keys($priceData)[0],
    ), priceGain($priceData, 0, $i, 3));

    // add data to array
    $performanceData[$sector]['YTD'] = $priceGain['gain'];
    $performanceData[$sector]['priceGain']['YTD'] = $priceGain;

    return $performanceData;
}

function returnSectorTimeframePerformanceData($verbose, $debug){

    // set version for API, v2 is for damwidi_v2, v4 is for damwidi_v4
    $version = isset($_GET['version']) ? $_GET['version'] : 'v2';

    // MySQL query to retrieve performance data
    $query = 'SELECT * FROM `data_performance` WHERE INSTR(\'SIFK\', `type`) ORDER BY FIELD(`type`, "F", "I", "S", "K"), `sector`';

    if ($version==='v2') {
        $timeframe = $_GET['timeframe'];
        $sectors = loadSectors(null, $query);

        // add sector timeframe data for chart.js
        $i = 0;
        $labels  = array();
        $dataset = array(
            'data'            => [],
            'backgroundColor' => [],
            'borderColor'     => [],
            'borderWidth'     => 1,
        );
        foreach($sectors as $sector){
            $labels[$i]          = $sector['sector'];
            $dataset['data'][$i] = $sector[$timeframe];
            if($sector[$timeframe] < 0) {
                $dataset['backgroundColor'][$i] = 'rgba(196, 33, 27, 0.2)' ;
                $dataset['borderColor'][$i] = 'rgba(196, 33, 27, 1)' ;
            } else {
                $dataset['backgroundColor'][$i] = 'rgba(29, 170, 90, 0.2)' ;
                $dataset['borderColor'][$i] = 'rgba(29, 170, 90, 1)' ;
            }
            if($sector['sector']=='SPY') $SPY = $sector[$timeframe];
            $i++;
        }

        $data = array(
            'SPY'      => $SPY,
            'labels'   => $labels,
            'datasets' => [$dataset]
        );
    } else if ($version === 'v4'){
        // $data = loadRawQuery($query);
        $data = loadSectors(null, $query);
    }

    if ($verbose)show($data);
    if(!$verbose)echo json_encode($data);
}

function returnPerformanceData($verbose, $debug){
    $json = file_get_contents("./data/performanceData.json"); //load data from file

    if($verbose) show($json);
    if(!$verbose) echo $json;
}
?>