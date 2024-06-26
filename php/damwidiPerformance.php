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
        switch ($sector['sector']) {
            case 'OBDC':
                $chartData     = retrievePriceDataAlpha($sector['sector'], 'daily', $startDate, false, $verbose, true, 30);  // saveData, verbose, debug, cacheAge
                $priceData     = $chartData['seriesData'];
                $lastRefreshed = $chartData['lastRefreshed'];

                // sleep for a random amount of time to prevent rate limiting from AlphaVantage
                if(!$chartData['cached']) //rateLimit();

                break;
            case 'DAM':
                $priceData     = returnDamwidiData();
                $lastRefreshed = array_keys($priceData)[0];
                $chartData['cached'] = true;
                break;
            default: 
                $chartData     = retrievePriceDataPolygon($sector['sector'], 'daily', $startDate, true, false, $verbose, true, 30);  // splitAdjusted, saveData, verbose, debug, cacheAge
                $priceData     = $chartData['seriesData'];
                $lastRefreshed = $chartData['lastRefreshed'];
                break;
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

            if ($timeFrame['relative']) {
                $performanceData[$sector['sector']][$timeFrame['period']] = priceGain($priceData, 0, $timeFrame['lengthDays'], 3)['gain'];
            } else {
                $candle = array_slice($priceData, $timeFrame['lengthDays']-1, 1); 
                $closePrice = $candle[key($candle)]['close'];
                $performanceData[$sector['sector']][$timeFrame['period']] = $closePrice;
            }

            $priceGain[$timeFrame['period']] = array_merge(array(
                'startDate' => (count($priceData) >= $timeFrame['lengthDays'] ? array_keys($priceData)[$timeFrame['lengthDays']] : '0'),
                'endDate'   => array_keys($priceData)[0],
            ), priceGain($priceData, 0, $timeFrame['lengthDays'], 3));
        }
        $performanceData[$sector['sector']]['previousDate'] = array_key_first($priceData);

        // add placeholder for YTD data
        $performanceData[$sector['sector']]['YTD'] = 0;

        // insert priceGain data
        $performanceData[$sector['sector']]['priceGain'] = $priceGain;

        // add YTD data
        $performanceData = returnYTDData($sector['sector'], $lastRefreshed, $performanceData, $priceData, $verbose);

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
    $notification = $end." - ".str_pad($table, 20)." - ".date('H:i:s', mktime(0, 0, strtotime($end)-strtotime($start)));


    Logs::$logger->info($log);

    if ($verbose) show($start." start");
    if (!$stdin) {
        show($notification);
    } else {
        echo $notification."\r\n";;
    }

    return true;
}

// add/remove individual stocks from preformance table
function refreshPerformanceTable($verbose){

    // load open positions, needed to add individual stock positions to the 'data_performance' table
    $openPositions = returnOpenPositions(date("Y-m-d"));

    // load all sectors, stocks and index
    $sectors = loadSectors('SIK');

    // add new stock positions to performance table
    foreach ($openPositions as $symbol => $position){
        if(!array_key_exists($symbol, $sectors)){
            $companyData = retrieveCompanyDataPolygon($symbol);
            $companyName = $companyData['status'] == 'OK' ? $companyData['results']['name'] : $symbol;

            if ($verbose) show($symbol.', '.$companyName);
            insertPerformanceStock($symbol, $companyName);

            $log = $symbol.' added to Performance table';
            Logs::$logger->info($log);
            if ($verbose) show($log);
        }
    }

    // load all stocks
    $stocks = loadSectors('K');

    // remove any stocks from performance table that are not in open positions
    foreach ($stocks as $stock => $position){
        if(!array_key_exists($stock,$openPositions)){
            removePerformanceStock($stock);

            $log = $stock.' removed from Performance table';
            Logs::$logger->info($log);
            if ($verbose) show($log);
        }
    }

    return true;
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
            Logs::$logger->info(str_pad($sector, 6)." - setting shares and basis to 0");
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
    $dataSize = count($priceData);

    while(array_keys($priceData)[$i] >= $startDate){
        $i++;
        if ($i >= $dataSize) {
            $i--;
            if ($verbose) show($sector.' - incomplete history data');
            break;
        }
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