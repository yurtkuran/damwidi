<?php
function retrievePriceDataPolygon($symbol, $interval, $startDate, $splitAdjusted = false, $saveData = false, $verbose = false, $debug = false, $cacheAge = 15){
    $source = 'polygon';
    //
    // use https://httpstat.us/ to test the HTML response
    //
    switch(strtolower($interval)){
        case 'daily':
            $timespan   = "day";
            $multiplier = 1;
            break;
    }

    $endDate = date('Y-m-d');
    $adjusted = $splitAdjusted ? 'true' : 'false';

    // create url
    $url  = POLYGONURL."v2/aggs/ticker/";
    $url .= $symbol;
    $url .= "/range/".$multiplier."/".$timespan."/";
    $url .= $startDate."/".$endDate."/";
    $url .= "?adjusted=".$adjusted."&sort=desc";
    $url .= "&apiKey=".POLYGONKEY;

    // create filename to save data
    $filename = "./data/data_price_".$source."_".$interval."_".$symbol.".json";

    $loadNewData = true;

    // load data
    if ($loadNewData) {
        $attempts         = 1;
        $maxAttempts      = 5;
        $dataOK           = false;
        $exceptionOccured = false;
        $dataArray        = array();

        do {
            Logs::$logger->info(str_pad($symbol, 6)." - retrieve ".$source." data, attempt ".$attempts);

            try {
                $json = file_get_contents($url);  //retrieve data
            } catch(Exception $e) {
                Logs::$logger->notice($e->getMessage());
                $exceptionOccured = true;
            }

            if (!isset($http_response_header) || (isset($http_response_header) && count($http_response_header) === 0)){
                $exceptionOccured = true;
            }

            if (!$exceptionOccured) {
                $response   = array_values(array_filter($http_response_header, function($v) {return strpos($v,'HTTP/1.1 200 OK') !== false;}))[0];
                $url        = $url;                
                $seriesData = json_decode($json,1);

                $dataArray[$symbol."-".$attempts] = $seriesData;

                if ($seriesData['status'] == 'ERROR'){
                    Logs::$logger->notice(str_pad($symbol, 6)." - failed retrieve Alpha ", [
                        "response" => $seriesData
                    ]);
                    $attempts++;
                    //ratelimit(); //random backoff time
                } else {
                    $dataOK = true;
                }
            } else {
                $attempts++;
                $exceptionOccured = false;
                // ratelimit(); //random backoff time
            }

        } while (!$dataOK && $attempts <= $maxAttempts);

        if ($debug) save(  "./tmp/data_price_".$source."_".$symbol.".json", array(
            'attempts' => $attempts,
            'url'      => $url,
            'data'     => $dataArray,
            'response' => $http_response_header
        ));

        $dataSet = array(
            'symbol'    => $symbol,
            'retrieved' => date('Y-m-d H:i:s'),
            'createdAt' => time(),
            'interval'  => $timespan,
            'function'  => 'Aggregate Bars',
            'Meta Data' => array(
                'url'       => $url,
                'attempts'  => $attempts,
                'response'  => $response,
                'startTime' => $startDate,
            )
        );

        if ( (strpos($response,'200') !== false) && $dataOK ) {
            $dataSet['lastRefreshed']              = date('Y-m-d H:i:s');
            $dataSet['Meta Data']['status']        = 'success';
            $dataSet['Meta Data']['outputSize']    = $seriesData['queryCount'];
            $dataSet['Meta Data']['outputCount']   = $seriesData['resultsCount'];
            $dataSet['Meta Data']['http response'] = array_values(array_filter($http_response_header, function($v) {return strpos($v,'HTTP/1.1') !== false;}));

            // set cahced flag
            $dataSet['cached'] = false;

        } else {
            $dataSet['status'] = 'fail';
        }

        if ($debug) save(  "./tmp/data_price_".$source."_".$symbol.".json", array(
            'attempts' => $attempts,
            'url'      => $url,
            'data'     => $dataArray,
            'response' => $http_response_header
        ));

        if ($saveData) save($filename, $seriesData);
    } else {
        // use cached data
    }

    // format return data
    foreach($seriesData['results'] as $data){
        $seconds = $data['t']/1000;
        $candle  = date("Y-m-d", $seconds);

        if ($candle >= $startDate ) {
            $dataSet['seriesData'][$candle] = array(
                "open"  => $data['o'],
                "high"  => $data['h'],
                "low"   => $data['l'],
                "close" => $data['c'],
            );
        }
    }

    if ($verbose) show($dataSet['Meta Data']);
    return $dataSet;
}

function retrieveBatchDataPolygon($symbols, $saveData = false, $verbose = false, $debug = false){

    $url  = POLYGONURL.'v2/snapshot/locale/us/markets/stocks/tickers';
    $url .= '?apiKey='.POLYGONKEY;
    $url .= '&tickers='.$symbols;

    if ($verbose) show($url);

    // $json     = curl_get_contents($url);      //retrieve data
    $json     = @file_get_contents($url);  //retrieve data
    $response = $http_response_header;     //http response information

    $header = $http_response_header;
    $response = returnHttpResponseCode($header);
    $data = array();


    if ($json) {
        $seriesData = json_decode($json,1);
        foreach($seriesData['tickers'] as $candle) {
            $data[$candle['ticker']] = array(
                'quote' => array(
                    'open'  => $candle['day']['o'] != 0 ? $candle['day']['o'] : $candle['prevDay']['o'],
                    'high'  => $candle['day']['h'] != 0 ? $candle['day']['h'] : $candle['prevDay']['h'],
                    'low'   => $candle['day']['l'] != 0 ? $candle['day']['l'] : $candle['prevDay']['l'],
                    'close' => $candle['day']['c'] != 0 ? $candle['day']['c'] : $candle['prevDay']['c'],
                    'latestPrice' => $candle['day']['c'] != 0 ? $candle['day']['c'] : $candle['prevDay']['c'],
                ),
                'prevDay' => array(
                    'open'  => $candle['prevDay']['o'],
                    'high'  => $candle['prevDay']['h'],
                    'low'   => $candle['prevDay']['l'],
                    'close' => $candle['prevDay']['c'],
                ),
                'todaysChange'     => $candle["todaysChange"],
                'todaysChangePerc' => $candle["todaysChangePerc"],
                'updated'          => $candle["updated"]
            );
        }
        if ($verbose) show($data);
    } else {
        if ($verbose) show($response);
    }

    return array(
        'responseCode' => $response['responseCode'],
        'response'     => $response['response'],
        'source'       => 'polygon',
        'data'         => $data
    );
}

function retrieveBatchHistoricalDataPolygon($symbols, $saveData = false, $verbose = false, $debug = false){

    if ($verbose) show($url);
    
    $data    = array();
    $symbols = explode(',', $symbols);
    foreach($symbols as $symbol) {
        $url      = createPolygonAggUrl($symbol);
        $json     = @file_get_contents($url);  //retrieve data
        $response = $http_response_header;     //http response information
        $header   = $http_response_header;
        $response = returnHttpResponseCode($header);

        
        if ($verbose) show($url);

        if ($json) {
            $seriesData = json_decode($json,1);
            $seriesData = $seriesData['results'];
            $data[$symbol] = array(
                'quote' => array(
                    'open'  => $seriesData[0]['o'],
                    'high'  => $seriesData[0]['h'],
                    'low'   => $seriesData[0]['l'],
                    'close' => $seriesData[0]['c'],
                    'latestPrice' => $seriesData[0]['c'],
                ),
                'prevDay' => array(
                    'open'  => $seriesData[1]['o'],
                    'high'  => $seriesData[1]['h'],
                    'low'   => $seriesData[1]['l'],
                    'close' => $seriesData[1]['c'],
                ),
                // todo: update these fields
                // 'todaysChange'     => $candle["todaysChange"],
                // 'todaysChangePerc' => $candle["todaysChangePerc"],
                'updated'          => $seriesData[0]['t']
            );
        }
    }
    return array(
        'responseCode' => '200',
        'response'     => $response['response'],
        'source'       => 'polygon',
        'data'         => $data
    );
}

function retrieveCompanyDataPolygon($symbol, $saveData = false, $verbose = false, $debug = false){

    $url  = POLYGONURL.'v3/reference/tickers';
    $url .= '/'.$symbol;
    $url .= '?apiKey='.POLYGONKEY;

    if ($verbose) show($url);

    $json = curl_get_contents($url);      //retrieve data
    $data = json_decode($json,1);

    if ($verbose) show($data);
    return $data;
}

function retrieveStockSplitsPolygon($symbol, $saveData = false, $verbose = false, $debug = false){

    $url  = POLYGONURL.'v3/reference/splits';
    $url .= '?ticker='.$symbol;
    $url .= '&apiKey='.POLYGONKEY;

    if ($verbose) show($url);

    $json = curl_get_contents($url);      //retrieve data
    $data = json_decode($json,1);

    if ($verbose) show($data);
    return $data;
}

function retrieveMarketStatusPolygon($saveData = false, $verbose = false, $debug = false){

    $url  = POLYGONURL.'v1/marketstatus/now';
    $url .= '?apiKey='.POLYGONKEY;

    if ($verbose) show($url);

    $json = curl_get_contents($url);      //retrieve data
    $data = json_decode($json,1);

    if ($verbose) show($data);
    return $data;
}

function createPolygonAggUrl($symbol) {
    $startDate = date('Y-m-d', strtotime('-7 days'));
    $endDate   = date('Y-m-d');

    return POLYGONURL."v2/aggs/ticker/".$symbol."/range/1/day/".$startDate."/".$endDate."/?adjusted=true&sort=desc&limit=2&apiKey=".POLYGONKEY;
}

?>