<?php
function retrievePriceDataAlpha($symbol, $interval, $startDate, $saveData = false, $verbose = false, $debug = false, $cacheAge = 15){
    //
    // use https://httpstat.us/ to test the HTML response
    //
    switch(strtolower($interval)){
        case 'daily':
            $key = "Time Series (Daily)";
            $function = "TIME_SERIES_DAILY_ADJUSTED";
            break;
    }

    // create alphavantage URL
    $URL  = alphaVantageURL;
    $URL .= "?function="  .$function;
    $URL .= "&symbol="    .$symbol;
    $URL .= "&apikey="    .alphaVantageAPIkey;
    $URL .= "&outputsize=full";

    // determine output size
    // if ( date_diff(date_create($startDate), date_create("now"))->format('%a') <= 100 ){  // determine if <= 100 data points are needed
    //     $URL .= "&outputsize=compact";
    // } else {
    //     $URL .= "&outputsize=full";
    // }

    // create filename to save data
    $filename = "./data/data_price_alpha_".$interval."_".$symbol.".json";

    // connect to mongoDB
    // $mongoURI   = "mongodb+srv://".MONGOUSERNAME.":".MONGOPASSWORD."@cluster0.j2v6w.mongodb.net/?retryWrites=true&w=majority";
    // $client     = new MongoDB\Client($mongoURI);
    // $collection = $client->damwidi->alphavantageCache;

    // find cached data
    $loadNewData = true;
    // $doc         = $collection->findOne(['symbol' => $symbol]);
    // if (!is_null($doc)) {
    //     $alphaData = json_decode(MongoDB\BSON\toJSON(MongoDB\BSON\fromPHP($doc)),1);
    //     $id        = $alphaData['_id']['$oid'];
    //     if (time() - $alphaData['createdAt'] < ($cacheAge * 60)){
    //         $loadNewData = false;
    //         if ($verbose) show($symbol." - current - ".$id);
    //     } else {
    //         $collection->deleteOne(['_id' => new MongoDB\BSON\ObjectID($id)]);
    //         if ($verbose) show($symbol." - not current - deleted - ".$id);
    //     }
    // }

    // load data from AlphaVantage
    if ($loadNewData) {
        $attempts         = 1;
        $maxAttempts      = 5;
        $dataOK           = false;
        $exceptionOccured = false;
        $dataArray        = array();

        do {
            Logs::$logger->info(str_pad($symbol, 6)." - retrieve Alpha data, attempt ".$attempts);

            try {
                $json = file_get_contents($URL);  //retrieve data
            } catch(Exception $e) {
                Logs::$logger->notice($e->getMessage());
                $exceptionOccured = true;
            }

            if (!$exceptionOccured) {
                $response   = $http_response_header[0]; //http response information
                $url        = $URL;                     //alphavantage URL
                $seriesData = json_decode($json,1);

                $dataArray[$symbol."-".$attempts] = $seriesData;
                if (array_key_exists('Time Series (Daily)',$dataArray[$symbol."-".$attempts])) unset($dataArray[$symbol."-".$attempts]['Time Series (Daily)']);

                if (array_key_exists('Information', $seriesData)){
                    Logs::$logger->notice(str_pad($symbol, 6)." - failed retrieve Alpha ", [
                        "keys"        => array_keys($seriesData),
                        "information" => $seriesData['Information']
                    ]);
                    $attempts++;
                    ratelimit(); //random backoff time
                } else {
                    $dataOK = true;
                }
            } else {
                $attempts++;
                ratelimit(); //random backoff time
            }

        } while (!$dataOK && $attempts <= $maxAttempts);

        if ($debug) save(  "./tmp/data_price_alpha_".$symbol.".json", array(
            'attempts' => $attempts,
            'url'      => $url,
            'data'     => $dataArray,
            'response' => $http_response_header
        ));

        $dataSet = array(
            'symbol'    => $symbol,
            'retrieved' =>  date('Y-m-d H:i:s'),
            'createdAt' => time(),
            'interval'  => $interval,
            'function'  => $function,
            'Meta Data' => array(
                'url'       => $url,
                'attempts'  => $attempts,
                'response'  => $response,
                'startTime' => $startDate,
            ),
            'AlphaVantage' => $seriesData
        );

        if ( !(strpos($response,'200') === false) && $dataOK ) {
            $dataSet['lastRefreshed']              = $seriesData['Meta Data']['3. Last Refreshed'];
            $dataSet['Meta Data']['status']        = 'success';
            $dataSet['Meta Data']['outputSize']    = $seriesData['Meta Data']['4. Output Size'];
            $dataSet['Meta Data']['outputCount']   = count($seriesData[$key]);
            $dataSet['Meta Data']['information']   = $seriesData['Meta Data']['1. Information'];
            $dataSet['Meta Data']['http response'] = $http_response_header[0];

            // save raw data to MongoDB cache
            $collection->insertOne($dataSet);
            if ($verbose) show($symbol." - inserted");

            // remove raw data from returned array
            unset($dataSet['AlphaVantage']);

            // set cahced flag
            $dataSet['cached'] = false;

        } else {
            $dataSet['status'] = 'fail';
        }

        if ($debug) save(  "./tmp/data_price_alpha_".$symbol.".json", array(
            'attempts' => $attempts,
            'url'      => $url,
            'data'     => $dataArray,
            'response' => $http_response_header
        ));

        if ($saveData) save($filename, $seriesData);
    } else {
        // use cached data
        $dataSet           = $alphaData;
        $dataSet['cached'] = true;
        $seriesData = $alphaData['AlphaVantage'];

        // remove raw data from returned array
        unset($dataSet['AlphaVantage']);
    }

    // format return data
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
    if ($verbose) show($seriesData);

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

function retrieveBatchDataAlphaV2($symbols, $loadNewData = true, $saveData = false, $verbose = true, $debug = false){

    $URL  = alphaVantageURL;
    $URL .= '?function=GLOBAL_QUOTE';
    $URL .= '&apikey='.alphaVantageAPIkey;
    $URL .= '&symbol=';

    //convert symbols list to array
    $symbols = explode(",",$symbols);
    if($verbose) show($symbols);

    foreach($symbols as $symbol){
        if($verbose) show($URL.$symbol);
        if ($loadNewData) {
            $json     = file_get_contents($URL.$symbol);  //retrieve data
            $response = $http_response_header[0]; //http response information
            $url      = $URL;                     //alphavantage URL
            $data     = json_decode($json,1);
            show($data);
            show($response);
            if ($saveData) save($filename, $data);
        } else {
            $json     = file_get_contents($filename); //load data from file, for development
            $response = "loaded from file";
            $url      = $filename;                    //filename
        }
    }
    die();


    $filename = "./data/data_price_alpha_batch.json";

    $seriesData = json_decode($json,1);

    if ($verbose) show($http_response_header);
    if ($verbose) show($seriesData);

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