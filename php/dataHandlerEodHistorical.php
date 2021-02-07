<?php

function retrievePriceEodHistorical($symbol, $interval, $startDate, $loadNewData = true, $saveData = false, $verbose = false, $debug = false){

    // revise certain symbols, e.g. BRKB -> BRK.B
    switch ($symbol){
        case 'BRK.B':
            $lookupSymbol = 'BRK-B';
            break;
        default:
        // symbol okay
        $lookupSymbol = $symbol;
    }


    $URL  = eodURL.$lookupSymbol.'.US';
    $URL .= '?api_token=' .eodAPIkey;
    $URL .= '&from='      .$startDate;
    $URL .= '&fmt=json';

    if ($verbose) show($URL);

    $filename = "./data/data_price_finnHub_".$interval."_".$symbol.".json";

    if ($loadNewData) {
        $json     = file_get_contents($URL);  //retrieve data
        $response = $http_response_header[0]; //http response information
        $url      = $URL;                     //alphavantage URL
        $data     = json_decode($json,1);
        if ($saveData) save($filename, $data);
    } else {
        $json     = file_get_contents($filename); //load data from file, for development
        $response = "loaded from file";
        $url      = $filename;                    //filename
    }
    $seriesData = json_decode($json,1);

    $data = array(
        'symbol'        => $symbol,
        'url'           => $url,
        'response'      => $response,
    );

    foreach($seriesData as $candle){
        $data['seriesData'][$candle['date']] = array(
            'open'  => $candle['open'],
            'high'  => $candle['high'],
            'low'   => $candle['low'],
            'close' => $candle['close'],
        );
    }

    if ($verbose) show($data);
    return $data;
}

?>