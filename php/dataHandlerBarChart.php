<?

function retrievePriceDataBarChart($symbol, $interval, $startDate, $loadNewData = true, $saveData = false, $verbose = false, $debug = false){

    $URL  = barchartURL;
    $URL .= '?apikey='    .barchartAPIkey;
    $URL .= '&type='      .$interval;
    $URL .= '&symbol='    .$symbol;
    $URL .= '&startDate=' .$startDate;
    $URL .= '&order=desc';
    $URL .= '&volume=total';
    $URL .= '&dividends=false';

    if ($verbose) show($URL);

    $filename = "./data/data_price_barChart_".$interval."_".$symbol.".json";

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
        'lastRefreshed' => $seriesData['results'][0]['tradingDay'],
    );

    foreach($seriesData['results'] as $candle){
        $data['seriesData'][$candle['tradingDay']] = array(
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