<?php

// update cash, SPY fields in `data_value` table
function updateValueTable($verbose = false, $debug = false){
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
            $valueData['market_value']  = $marketValue['close'];
            $valueData['account_value'] = $valueData['cash'] + $valueData['market_value'];
            $valueData['payments']      = returnPayments($date);
            $valueData['bivio_value']   = round($valuation['value'],6);

            if ($firstRecord) {
                $firstRecord = false;
                $totalShares = $valueData['payments']/initialShareValue;
                $valueData['total_shares'] = $totalShares;
            } else {
                $totalShares = $valuation['units'];
                $shareValue  = $valueData['account_value']/$totalShares;
                $valueData['total_shares'] = round($valuation['units'],6);
            }
            $valueData['share_value']   = round($shareValue,6);
            $valueData['open']          = ($valueData['cash'] + $marketValue['open'])  / $totalShares;
            $valueData['high']          = ($valueData['cash'] + $marketValue['high'])  / $totalShares;
            $valueData['low']           = ($valueData['cash'] + $marketValue['low'])   / $totalShares;
            $valueData['close']         = ($valueData['cash'] + $marketValue['close']) / $totalShares;

            saveValueData($valueData); // write data to database

            // determine if unstick condition exists
            if($valueData['bivio_value'] <> $valueData['share_value'] or $debug){

                // save data to array
                if (array_key_exists($date, $dataLog)){
                    // key already exists, replace data
                    $dataLog[$date] = $valueData;
                } else {
                    // key does not exist, append data
                    $dataLog = array_merge(array($date => $valueData), $dataLog);
                }
                sendSMS("damwidi unstick", $date); // send SMS via IFTTT web service
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
    curl_close($ch);

    // create notifications
    $end      = date('Y-m-d H:i:s');
    $duration = strtotime($end)-strtotime($start);
    $table    = "value";

    if ($verbose) show($start." start");
    show($end." - ".$table." - ".date('H:i:s', mktime(0, 0, strtotime($end)-strtotime($start))));
    writeAirTableRecord($table, $start, $duration);
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
    $historicalData = $historicalData['alphaVantage'];

    $marketValue = array(
        'open'    => 0,
        'high'    => 0,
        'low'     => 0,
        'close'   => 0,
    );
    foreach($openPositions as $symbol => $data){
        $marketValue['open']  += $data['shares']*$historicalData[$symbol][$date]['open'];
        $marketValue['high']  += $data['shares']*$historicalData[$symbol][$date]['high'];
        $marketValue['low']   += $data['shares']*$historicalData[$symbol][$date]['low'];
        $marketValue['close'] += $data['shares']*$historicalData[$symbol][$date]['close'];
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
                } else {
                    // new symbol
                    $openPositions[$transaction['symbol']] = array(
                        'shares'    => $transaction['shares'],
                        'purchase'  => $transaction['amount'],
                        'dividend'  => 0
                    );
                }
                break;
            case 'S':
                $openPositions[$transaction['symbol']]['shares']   += $transaction['shares'];
                $openPositions[$transaction['symbol']]['purchase'] += $transaction['amount'];
                if ($openPositions[$transaction['symbol']]['shares'] == 0) unset($openPositions[$transaction['symbol']]);
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