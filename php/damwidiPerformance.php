<?php
// update fields in the `data_performance` table for sectors, index and cash
function updatePerformanceData($verbose, $debug){
    // load previous 2 years of data
    $startDate = date('Y-m-d', strtotime('-2 years'));

    // load all sectors, index and fund
    $sectors = loadSectors('SIF');

    // load timeframe details
    $timeFrames = json_decode(file_get_contents("./config/comparison.json"),1);

    // loop through all sectors
    foreach($sectors as $sector){
        if ($sector['sector'] <> 'DAM' ){
            $chartData     = retrievePriceDataAlpha($sector['sector'], 'daily', $startDate, true, false, false, false);  // loadNewData, saveData, verbose, debug
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
            'weight'            => $sector['weight'],
            'effectiveDate'     => $sector['effectiveDate'],
            'fetchedDate'       => $sector['effectiveDate'],
            'sectorDescription' => $sector['sectorDescription']
        );

        // loop through all timeframes
        foreach($timeFrames as $timeFrame){
            $performanceData[$sector['sector']][$timeFrame['period']] = priceGain($priceData, 0, $timeFrame['lengthDays']-1, 3)['gain'];
        }
        if($debug) break;

        // add YTD data
        $performanceData = returnYTDData($lastRefreshed, $performanceData);

        // sleep for a random amount of time to prevent rate limiting from AlphaVantage
        sleep(rand(2,5));
    }

    $performanceData = returnBasisData($lastRefreshed, $performanceData); // add basis & share data

    // $performanceData = returnYTDData($lastRefreshed, $performanceData); // add YTD data

    $performanceData = returnSectorWeights($performanceData); // add sector weights

    savePerformanceData($performanceData, $verbose); // write to MySQL database

    saveCashBalance(returnCashBalance($lastRefreshed), $lastRefreshed); // write to MySQL database

    if ($verbose) show($performanceData);

    show(date('Y-m-d H:m:s')." - Complete: Update performnace table");
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
            $performanceData[$sector]['basis']  = 0;
            $performanceData[$sector]['shares'] = 0;
        }
    }
    return $performanceData;
}

// return damwidi data properly formatted
function returnDamwidiData($startDate = null){
    $data = loadDamdidiValue(260);

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
            if ($sector <> 'DAM' and $sector <> 'SPY'){
                $performanceData[$sector]['weight']        = $sectorWeights['sectorWeights'][$data['sectorDescription']]['sectorWeight']*100;
                $performanceData[$sector]['effectiveDate'] = $sectorWeights['effectiveDate'];
                $performanceData[$sector]['fetchedDate']   = $sectorWeights['fetchedDate'];
            }
        }
    }
    return $performanceData;
}

// return YTD detail in `data_performance` table
function returnYTDData($lastRefreshed, $performanceData){

    // determine starting date
    $startDate = date('Y', strtotime($lastRefreshed)).'-01-01';

    // loop through all sectors
    foreach($performanceData as $sector => $data){
        if ($sector <> 'DAM' ){
            $priceData = retrievePriceDataAlpha($sector, 'daily', $startDate, true, false, false, false)['seriesData'];  // loadNewData, saveData, verbose, debug
        } else {
            $priceData = returnDamwidiData($startDate);
        }
        $performanceData[$sector]['YTD']  = priceGain($priceData, 0, sizeof($priceData)-1, 3)['gain'];
    }

    return $performanceData;
}

function returnSectorTimeframePerformanceData($verbose, $debug){
    $timeframe = $_GET['timeframe'];
    $sectors = loadSectors('SIF');

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

    if ($verbose)show($data);
    if(!$verbose)echo json_encode($data);
}
?>