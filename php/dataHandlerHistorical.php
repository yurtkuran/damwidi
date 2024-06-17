<?php
// function will accept a single symbol as a string or multiple symbols as an array
function getHistory($symbols, $startDate, $endDate, $verbose = false, $debug = false){
    if ($verbose) show("get historical data \n"."start date: ".$startDate."\n"."end date:   ".$endDate);

    // list of data providers
    $dataProviders = array('polygon');

    // convert to array if single symbol
    $symbols = (is_array($symbols) ? $symbols : array($symbols));

    $dataset = array();
    foreach($symbols as $symbol){
        // load and merge ASMG data, this stock was delisted and not included in alphavantage
        if ($symbol == 'AMSG'){
            $json = file_get_contents('./data/data_price_daily_AMSG.json');
            $historicalData = json_decode($json,1);
            foreach($historicalData as $candle => $data){
                if ( $candle >= $startDate and $candle <= $endDate  ) {
                    $dataset['alphaVantage'][$symbol][$candle] = $data;
                }
            }
        } else {
            foreach($dataProviders as $provider){
                if ($verbose) show($provider." - ".$symbol);
                switch($provider){
                    case 'alphaVantage':
                        $historicalData  = retrievePriceDataAlpha($symbol, 'daily', $startDate, false, false, false, 30);  // saveData, verbose, debug, cacheAge

                        // if not cached, sleep for a random amount of time to prevent rate limiting from AlphaVantage
                        if(!$historicalData['cached']) rateLimit();

                        break;
                    case 'barChart':
                        $historicalData  = retrievePriceDataBarChart($symbol, 'daily', $startDate, true, false, false, false);  // loadNewData, saveData, verbose, debug
                        break;
                    case 'finnHub':
                        $historicalData  = retrievePriceFinnhub($symbol, 'D', $startDate, true, false, false, false);  // loadNewData, saveData, verbose, debug
                        break;
                    case 'eod':
                        $historicalData  = retrievePriceEodHistorical($symbol, 'D', $startDate, true, false, false, false);  // loadNewData, saveData, verbose, debug
                        break;
                    case 'polygon':
                        $historicalData  = retrievePriceDataPolygon($symbol, 'daily', $startDate, false, false, false, 30);  // saveData, verbose, debug, cacheAge

                        // if not cached, sleep for a random amount of time to prevent rate limiting from AlphaVantage
                        // if(!$historicalData['cached']) rateLimit();

                        break;
                }
                if (array_key_exists('seriesData', $historicalData)) { // determine if seriesData exists
                    foreach($historicalData['seriesData'] as $candle => $data){
                        if ( $candle >= $startDate and $candle <= $endDate  ) {
                            $dataset[$provider][$symbol][$candle] = $data;
                        }
                    }
                }
            }
        }

    }

    if ($verbose) show($dataset);
    return $dataset;
}

// return market holidays
function getMarketCalendar($startDate, $endDate, $loadNewData = true, $saveData = true, $verbose = false, $debug = false){
    if ($verbose) show("get market calendar \n"."start date: ".$startDate."\n"."end date:   ".$endDate);

    $startDate      = strtotime($startDate);
    $endDate        = strtotime($endDate);
    $marketHolidays = array();
    $filename       = './data/marketHolidays.json';

    if ($loadNewData) {
        // loop year
        for ($year = date("Y", $startDate); $year <= date("Y", $endDate); $year++) {

            // loop month
            for ($month = 1; $month <= 12; $month++) {
                $marketCalendar = retrieveMarketCalendar($year, $month);

                foreach($marketCalendar as $day){
                    $dow = date('w', strtotime($day['date'])); //determing day of week, do not process if Sat or Sun
                    if ($day['status']== 'closed' and $dow >0 and $dow < 6){
                        $marketHolidays[$day['date']] = $day['description'];
                    }
                }
            }
        }

        // save data if requested
        if ($saveData) save($filename, $marketHolidays);

    } else {
        $marketHolidays = file_get_contents($filename);
        $marketHolidays = json_decode($marketHolidays, true); //load data from file, for development
    }

    if ($verbose) show($marketHolidays);
    return $marketHolidays;
}

?>