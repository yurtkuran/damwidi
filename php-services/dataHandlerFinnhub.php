<?php

function retrievePriceFinnhub($symbol, $interval, $startDate, $loadNewData = true, $saveData = false, $verbose = false, $debug = false){

    $URL  = finnhubURL;
    $URL .= '?token='     .finnhubAPIkey;
    $URL .= '&resolution='.$interval;
    $URL .= '&symbol='    .$symbol;
    $URL .= '&from='      .strtotime($startDate);
    $URL .= '&to='        .time();


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

    for ($i=0; $i < count($seriesData['t']); $i++) {

        $data['seriesData'][date('Y-m-d', $seriesData['t'][$i] )] = array(

            'open'  => $seriesData['o'][$i],
            'high'  => $seriesData['h'][$i],
            'low'   => $seriesData['l'][$i],
            'close' => $seriesData['c'][$i],
        );
    }

    if ($verbose) show($data);
    return $data;
}

?>