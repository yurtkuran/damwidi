<?php
function retrievePriceDataAlpha($symbol, $interval, $startDate, $loadNewData = true, $saveData = false, $verbose = false, $debug = false){
    //
    // use https://httpstat.us/ to test the HTML response
    //
    switch(strtolower($interval)){
        case 'daily':
            $key = "Time Series (Daily)";
            $function = "TIME_SERIES_DAILY";
            break;
        case 'weekly':
            $key = "Weekly Time Series";
            $function = "TIME_SERIES_WEEKLY";
            break;
    }

    $URL  = alphaVantageURL;
    $URL .= "?function="  .$function;
    $URL .= "&symbol="    .$symbol;
    $URL .= "&apikey="    .alphaVantageAPIkey;

    // determine output size
    if ( ((time() - strtotime($startDate)) / 86400) <=100 ){  // determine if <= 100 data points is needed
        $URL .= "&outputsize=compact";
    } else {
        $URL .= "&outputsize=full";
    }

    if ($verbose) show($URL);

    $filename = "./data/data_price_alpha_".$interval."_".$symbol.".json";

    $attempts = 1;
    $dataOK = false;
    if ($loadNewData) {
        do {
            $json     = file_get_contents($URL);  //retrieve data
            $response = $http_response_header[0]; //http response information
            $url      = $URL;                     //alphavantage URL
            $data     = json_decode($json,1);

            if (array_key_exists('Information', $data)){
                $attempts++;
                sleep(rand(2,10));
            } else {
                $dataOK = true;
            }
        } while (!$dataOK);

        if ($saveData) save($filename, $data);
        if ($debug) save(  "./tmp/data_price_alpha_".$symbol."_".date('YmdHis').".json", $data);
    } else {
        $json     = file_get_contents($filename); //load data from file, for development
        $response = "loaded from file";
        $url      = $filename;                    //filename
    }
    $seriesData = json_decode($json,1);

    // if ($verbose) show($http_response_header);

    $dataSet['Meta Data'] = array(
        'symbol'    => $symbol,
        'url'       => $url,
        'attempts'  => $attempts,
        'response'  => $response,
        'startTome' => $startDate,
    );

    if ( !(strpos($response,'200') === false) or !$loadNewData ) {
        $dataSet['lastRefreshed']       = $seriesData['Meta Data']['3. Last Refreshed'];
        $dataSet['Meta Data']['status'] = 'success';
        foreach($seriesData[$key] as $candle => $data){
            if ($candle >= $startDate ) {
                $dataSet['seriesData'][$candle] = array(
                    "open"  => $data['1. open'],
                    "high"  => $data['2. high'],
                    "low"   => $data['3. low'],
                    "close" => $data['4. close'],
                );
            }
        }
    } else {
        $dataSet['status'] = 'fail';
    }

    if ($verbose) show($dataSet['Meta Data']);
    return $dataSet;
}

function retrieveBatchDataAlpha($symbols, $loadNewData = true, $saveData = false, $verbose = false, $debug = false){

    $URL  = alphaVantageURL;
    $URL .= "?function="  .'BATCH_STOCK_QUOTES';
    $URL .= "&symbols="   .$symbols;
    $URL .= "&apikey="    .alphaVantageAPIkey;

    if ($verbose) show($URL);

    $filename = "./data/data_price_alpha_batch.json";

    if ($loadNewData) {
        $json     = file_get_contents($URL);  //retrieve data
        $response = $http_response_header[0]; //http response information
        $url      = $URL;                     //alphavantage URL
        $data     = json_decode($json,1);

        if ($saveData) save($filename, $data);
        if ($debug) {
            $data = array(
                'http_response' => $http_response_header,
                'data'          => json_decode($json,1),
            );
            save(  "./tmp/data_price_alpha_batch_".date('YmdHis').".json", $data);
        }
    } else {
        $json     = file_get_contents($filename); //load data from file, for development
        $response = "loaded from file";
        $url      = $filename;                    //filename
    }
    $seriesData = json_decode($json,1);

    if ($verbose) show($http_response_header);

    $dataSet = array(
        'symbol'   => $symbols,
        'url'      => $url,
        'response' => $response,
    );

    if ( !(strpos($response,'200') === false) or !$loadNewData ) {
        $dataSet['status'] = 'success';
        foreach($seriesData['Stock Quotes'] as $quote){
            $dataSet['seriesData'][$quote['1. symbol']] = array(
                "price"          => $quote['2. price'],
                "lastRefreshed"  => $quote['4. timestamp'],
            );
        }
    }

    if ($verbose) show($dataSet);
    return $dataSet;
}

?>