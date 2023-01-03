<?php

// update cash, SPY fields in `data_value` table
function updateValueTable($verbose = false, $debug = false, $stdin = false){
    if ($verbose) show("--- UPDATE VALUE TABLE ---");

    // store start time used to determine function duration
    $start = date('Y-m-d H:i:s');

    // open data log file
    $dataLog = returnUnstickLogData();

    //determine starting date
    $dbc = connect();
    $stmt = $dbc->prepare("SELECT * FROM `data_value` ORDER BY `date` DESC LIMIT 1");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!empty($result)){
        // start at the last record in the value table
        $firstRecord = false;
        $shareValue  = $result['share_value'];
        $totalShares = $result['total_shares'];
        $startDate   = $result['date'];
    } else {
        // start with first date in transaction table
        $stmt = $dbc->prepare("SELECT `transaction_date` FROM `data_transactions` ORDER BY `transaction_date` ASC LIMIT 1");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $startDate   = $result['transaction_date'];
        $firstRecord = true;
        $shareValue  = initialShareValue;
    }
    $endDate = date('Y-m-d',  strtotime(date('Y-m-d') . "-1 days"));

    // temp override - remove later
    // $startDate = "2014-07-01";
    // $endDate   = "2014-12-18";

    if ($verbose) show("update data_value table \n"."start date: ".$startDate."\n"."end date:   ".$endDate);

    // get market holidays
    $marketHolidays = getMarketCalendar($startDate, $endDate, true, false, $verbose, $debug ); // loadNewData, saveData, verbose, debug

    // get historical SPY data
    $historicalSPYData = getHistory('SPY', $startDate, $endDate, false, false)['alphaVantage']['SPY']; //verbose, debug

    // calculate damwidi market value
    $allPositions   = returnAllPositions($startDate, $endDate); // get list of all postions (open or closed) within start/end dates
    $historicalData = getHistory($allPositions, $startDate, $endDate, false, false); // get historical prices from alphaVantage and barChart -- verbose, debug

    // open cURL session to scrape damwidi value from Bivio
    $ch = bivioLogin($verbose);

    // loop through dates
    $date = $startDate;
    while ($date <= $endDate){
        $dow = date('w', strtotime($date)); //determing day of week, do not process if Sat or Sun
        $mktClosed = array_key_exists($date, $marketHolidays);

        if ( $dow >0 and $dow < 6 and !$mktClosed) {
            $valuation = returnBivioValue($ch, $date);

            $marketValue = returnMarketValue($date, $historicalData); // return market vallue H/O/L/C

            $valueData = array(); // init array to hold data for value record
            $valueData['date']          = $date;
            $valueData['SPY']           = $historicalSPYData[$date]['close'];
            $valueData['cash']          = returnCashBalance($date);
            $valueData['payments']      = returnPayments($date);
            $valueData['bivio_value']   = round($valuation['value'],6);

            // determine share count
            if ($firstRecord) {
                $firstRecord = false;
                $totalShares = $valueData['payments']/initialShareValue;
                $valueData['total_shares'] = $totalShares;
            } else {
                $totalShares = $valuation['units'];
                $valueData['total_shares'] = round($valuation['units'],6);
            }

            // determine which datasource to use
            $unstickError = null;
            foreach(array_keys($marketValue) as $source){
                $shareValue  = round(($valueData['cash'] + $marketValue[$source]['close'])/$totalShares,6);

                if (abs($shareValue - $valueData['bivio_value']) < $unstickError || $unstickError == null) {
                    $unstickError = abs($shareValue - $valueData['bivio_value'])*$valueData['total_shares'] ;
                    $provider     = $source;
                }

                if ($verbose) show($date.' - '.$provider.' - '.$unstickError);

                if ($unstickError == 0) break;
            }

            $valueData['market_value']  = $marketValue[$provider]['close'];
            $valueData['account_value'] = $marketValue[$provider]['close'] + $valueData['cash'];
            $valueData['share_value']   = round(($valueData['cash'] + $marketValue[$source]['close'])/$totalShares,8);
            $valueData['share_value']   = round($valueData['share_value'],7);
            $valueData['share_value']   = round($valueData['share_value'],6);
            // $valueData['share_value']   = round(($valueData['cash'] + $marketValue[$source]['close'])/$totalShares,6);

            $valueData['open']          = ($valueData['cash'] + $marketValue[$provider]['open'])  / $totalShares;
            $valueData['high']          = ($valueData['cash'] + $marketValue[$provider]['high'])  / $totalShares;
            $valueData['low']           = ($valueData['cash'] + $marketValue[$provider]['low'])   / $totalShares;
            $valueData['close']         = ($valueData['cash'] + $marketValue[$provider]['close']) / $totalShares;

            $valueData['source']        = $provider;

            if ($verbose) show($date.' - '.$provider);

            saveValueData($valueData); // write data to database

            // determine if unstick condition exists - in other words, when the bivio account value does not equal the calculated account value
            if($valueData['bivio_value'] <> $valueData['share_value'] or $debug){
                // save data to array
                if (array_key_exists($date, $dataLog)){
                    // key already exists, replace data
                    $dataLog[$date] = $valueData;
                } else {
                    // key does not exist, append data
                    $dataLog = array_merge(array($date => $valueData), $dataLog);
                }
                $dataLog[$date]['unstickDelta'] = round($result['total_shares']*($valueData['bivio_value'] - $valueData['share_value']),2);  //positive: bivio NAV is higher than calculated NAV
                $dataLog[$date]['bivio_account_value'] = round($result['total_shares']*$valueData['bivio_value'],2);

                // add close price share qty to log file
                $openPositions = returnOpenPositions($date);
                foreach($openPositions as $symbol => $data){
                    $dataLog[$date]['positions'][$symbol]['shares'] = $data['shares'];
                    $dataLog[$date]['positions'][$symbol]['close']  = $historicalData[$provider][$symbol][$date]['close'];
                }

                $unstickDeltaMsg = ($dataLog[$date]['unstickDelta'] < 0 ? '-$' : '$').abs($dataLog[$date]['unstickDelta']);

                if(ENV == 'PROD') sendSMS('damwidi unstick: '.$unstickDeltaMsg, $date); // send SMS via IFTTT web service, only for production

            } else {
                if (array_key_exists($date, $dataLog)){
                    // key already exists, remove data
                    unset($dataLog[$date]);
                }
            }
        }

        $date = date('Y-m-d',strtotime($date . "+1 days"));

        if ($debug){
            show($valueData);
            break;
        }
    }

    // save unstick log data
    if(!empty($dataLog)) save(UNSTICK_LOG, $dataLog);

    // close cURL session
    curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, 1);
    curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt ($ch, CURLOPT_SSLVERSION, 3);
    curl_close($ch);

    // create notifications
    $end          = date('Y-m-d H:i:s');
    $duration     = strtotime($end)-strtotime($start);
    $table        = "value";
    $log          = date('i:s', mktime(0, 0, strtotime($end)-strtotime($start)))." - ".$table;
    $notification = $end." - ".$table." - ".date('H:i:s', mktime(0, 0, strtotime($end)-strtotime($start)));

    Logs::$logger->info($log);

    if ($verbose) show($start." start");
    if (!$stdin) {
        show($notification);
    } else {
        echo $notification."\r\n";;
    }
}

// query `data_transaction` table to return a list of all positions (open or closed) between two dates
function returnAllPositions($startDate, $endDate, $verbose = false, $debug = false){
    $symbols = array();

    // loop through dates
    $date = $startDate;
    while ($date <= $endDate){
        $openPositions = returnOpenPositions($date);
        foreach ($openPositions as $symbol => $position){
            if (array_search($symbol, $symbols) === false) array_push($symbols, $symbol);
        }
        $date = date('Y-m-d',strtotime($date . "+1 days"));
    }
    if ($verbose) show($symbols);
    return $symbols;
}

// query `data_transaction` table and update cash balance in `data_value`
function returnCashBalance($date, $verbose = false, $debug = false){
    $dbc = connect();
    $stmt = $dbc->prepare("SELECT SUM(`amount`) AS balance FROM `data_transactions`
                           WHERE  `transaction_date` <= :transaction_date");
    $stmt->bindParam(':transaction_date', $date);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($verbose) show('cash balance as of '.$date.' is '. $result['balance']);
    return $result['balance'];
}

// return market vallue H/O/L/C
function returnMarketValue($date, $historicalData){
    $openPositions = returnOpenPositions($date);

    // list of data providers
    $dataProviders = array_keys($historicalData);

    // $historicalData = $historicalData['alphaVantage'];

    foreach($dataProviders as $provider){

        $marketValue[$provider] = array(
            'open'    => 0,
            'high'    => 0,
            'low'     => 0,
            'close'   => 0,
        );
        foreach($openPositions as $symbol => $data){
            if (array_key_exists($symbol, $historicalData[$provider])){
                $marketValue[$provider]['open']  += $data['shares']*round($historicalData[$provider][$symbol][$date]['open']  ,3);
                $marketValue[$provider]['high']  += $data['shares']*round($historicalData[$provider][$symbol][$date]['high']  ,3);
                $marketValue[$provider]['low']   += $data['shares']*round($historicalData[$provider][$symbol][$date]['low']   ,3);
                $marketValue[$provider]['close'] += $data['shares']*round($historicalData[$provider][$symbol][$date]['close'] ,3);
            }
        }
    }

    return $marketValue;
}

// query `data_transaction` table to return open positions (symbol and share count) on a specific date
function returnOpenPositions($date, $verbose = false, $debug = false){
    $dbc = connect();
    $stmt = $dbc->prepare("SELECT * FROM `data_transactions`
                           WHERE    `transaction_date` <= :date
                           AND      `symbol` IS NOT NULL
                           AND      `symbol` <> ''
                           ORDER BY `transaction_date` ASC");
    $stmt->bindParam(':date', $date);
    $stmt->execute();
    $result = $stmt->fetchall(PDO::FETCH_ASSOC);

    $openPositions = array();

    foreach($result as $transaction){
        switch($transaction['type']){
            case 'B':
                if (array_key_exists ( $transaction['symbol'] , $openPositions )){
                    // symbol in array
                    $openPositions[$transaction['symbol']]['shares']   += $transaction['shares'];
                    $openPositions[$transaction['symbol']]['purchase'] += $transaction['amount'];
                    array_push($openPositions[$transaction['symbol']]['purchases'], $transaction['transaction_date'] );
                } else {
                    // new symbol
                    $openPositions[$transaction['symbol']] = array(
                        'shares'    => $transaction['shares'],
                        'purchase'  => $transaction['amount'],
                        'purchases' => array($transaction['transaction_date']),
                        'dividend'  => 0
                    );
                }
                break;
            case 'S':
                $openPositions[$transaction['symbol']]['shares']   += $transaction['shares'];
                $openPositions[$transaction['symbol']]['purchase'] += $transaction['amount'];
                if (round($openPositions[$transaction['symbol']]['shares'],5) == 0) unset($openPositions[$transaction['symbol']]);
                break;
            case 'D':
                if (array_key_exists ( $transaction['symbol'] , $openPositions )){
                    $openPositions[$transaction['symbol']]['dividend'] += $transaction['amount'];
                }
                break;
            default:
                break;
        }
    }

    //add basis data
    foreach($openPositions as &$position){
        $position['basis'] = round(-1*($position['purchase']+$position['dividend'])/$position['shares'],3);
    }

    if ($verbose) show($openPositions);
    return $openPositions;
}

// query `data_transaction` table to return payments on a specific date
function returnPayments($date){
    $dbc = connect();
    $stmt = $dbc->prepare("SELECT SUM(`amount`) AS payments FROM `data_transactions`
                           WHERE  `transaction_date` = :transaction_date AND `type` = 'P'");
    $stmt->bindParam(':transaction_date', $date);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    return  (!is_null($result['payments']) ? $result['payments'] : 0); //return 0 if no payments were made that day
}

// return damwidi OHLC data in alphavantage format
function returnDamwidiOHLC($verbose, $debug){
    $dbc = connect();
    $stmt = $dbc->prepare("SELECT `date`, `open`, `high`, `low`, `close` FROM `data_value` ORDER BY `date` DESC");
    $stmt->execute();
    $result = $stmt->fetchall(PDO::FETCH_ASSOC);

    $damwidiOHLC = array();
    foreach($result as $candle){
        $damwidiOHLC['Time Series (Daily)'][$candle['date']] = array(
            "1. open"  => round($candle['open'],2),
            "2. high"  => round($candle['high'],2),
            "3. low"   => round($candle['low'],2),
            "4. close" => round($candle['close'],2),
        );
    }

    if ($verbose) show($damwidiOHLC);
    if (!$verbose) echo json_encode($damwidiOHLC);
}

?>