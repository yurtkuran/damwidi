<?php
function retrievePriceDataPolygon($symbol, $interval, $startDate, $saveData = false, $verbose = false, $debug = false, $cacheAge = 15){
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

    // create URL
    $URL  = "https://api.polygon.io/v2/aggs/ticker/";
    $URL .= $symbol;
    $URL .= "/range/".$multiplier."/".$timespan."/";
    $URL .= $startDate."/".$endDate."/";
    $URL .= "?adjusted=true&sort=desc";
    $URL .= "&apiKey=".polygonKey;

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
                $json = file_get_contents($URL);  //retrieve data
            } catch(Exception $e) {
                Logs::$logger->notice($e->getMessage());
                $exceptionOccured = true;
            }

            if (!isset($http_response_header) || (isset($http_response_header) && count($http_response_header) === 0)){
                $exceptionOccured = true;
            }

            if (!$exceptionOccured) {
                $response   = array_values(array_filter($http_response_header, function($v) {return strpos($v,'HTTP/1.1 200 OK') !== false;}))[0];
                $url        = $URL;                
                $seriesData = json_decode($json,1);

                $dataArray[$symbol."-".$attempts] = $seriesData;

                if ($seriesData['status'] == 'ERROR'){
                    Logs::$logger->notice(str_pad($symbol, 6)." - failed retrieve Alpha ", [
                        "response" => $seriesData
                    ]);
                    $attempts++;
                    ratelimit(); //random backoff time
                } else {
                    $dataOK = true;
                }
            } else {
                $attempts++;
                $exceptionOccured = false;
                ratelimit(); //random backoff time
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

?>