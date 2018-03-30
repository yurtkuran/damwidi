<?

// update cash, SPY fields in `data_value` table
function updateValueTable($verbose = false, $debug = false){

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
    $marketHolidays = getMarketCalendar($startDate, $endDate, false); // loadNewData, saveData, verbose, debug

    // get historical SPY data
    $historicalSPYData = getHistory('SPY', $startDate, $endDate)['alphaVantage']['SPY'];

    // calculate damwidi market value
    $allPositions   = returnAllPositions($startDate, $endDate); // get list of all postions (open or closed) within start/end dates
    $historicalData = getHistory($allPositions, $startDate, $endDate); // get historical prices from alphaVantage

    // open cURL session to scrape damwidi value from Bivio
    $ch = bivioLogin($verbose);

    // loop through dates
    $date = $startDate;
    while ($date <= $endDate){
        $dow = date('w', strtotime($date)); //determing day of week, do not process if Sat or Sun
        $mktClosed = array_key_exists($date, $marketHolidays);

        if ( $dow >0 and $dow < 6 and !$mktClosed) {

            $marketValue = returnMarketValue($date, $historicalData); // return market vallue H/O/L/C

            $valueData = array(); // init array to hold data for value record
            $valueData['date']          = $date;
            $valueData['SPY']           = $historicalSPYData[$date]['close'];
            $valueData['cash']          = returnCashBalance($date);
            $valueData['market_value']  = $marketValue['close'];
            $valueData['account_value'] = $valueData['cash'] + $valueData['market_value'];
            $valueData['payments']      = returnPayments($date);
            $valueData['bivio_value']   = returnBivioValue($ch, $date);
           

            if ($firstRecord) {
                $firstRecord = false;
                $totalShares = $valueData['payments']/initialShareValue;
                $valueData['total_shares'] = $totalShares;
            } else {
                $totalShares += $valueData['payments']/$shareValue;
                $shareValue   = $valueData['account_value']/$totalShares;

                $valueData['total_shares'] = $totalShares;
            }
            $valueData['share_value']   = $shareValue;
            $valueData['open']          = ($valueData['cash'] + $marketValue['open'])  / $totalShares;
            $valueData['high']          = ($valueData['cash'] + $marketValue['high'])  / $totalShares;
            $valueData['low']           = ($valueData['cash'] + $marketValue['low'])   / $totalShares;
            $valueData['close']         = ($valueData['cash'] + $marketValue['close']) / $totalShares;

            saveValueData($valueData); // write data to database
            // if ($verbose) show($valueData);
        }

        $date = date('Y-m-d',strtotime($date . "+1 days"));
        
        if ($debug){
            show($valueData);
            break;
        }
    }

    // close cURL session
    curl_close($ch);

    show(date('Y-m-d H:m:s')." - Complete: Update Damwidi value");
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
                           AND      `ticker` IS NOT NULL 
                           AND      `ticker` <> ''
                           ORDER BY `transaction_date` ASC");
    $stmt->bindParam(':date', $date);
    $stmt->execute();
    $result = $stmt->fetchall(PDO::FETCH_ASSOC);

    $openPositions = array();

    foreach($result as $transaction){
        switch($transaction['type']){
            case 'B':
                if (array_key_exists ( $transaction['ticker'] , $openPositions )){
                    // ticker in array
                    $openPositions[$transaction['ticker']]['shares']   += $transaction['shares'];
                    $openPositions[$transaction['ticker']]['purchase'] += $transaction['amount'];
                } else {
                    // new ticker
                    $openPositions[$transaction['ticker']] = array(
                        'shares'    => $transaction['shares'],
                        'purchase'  => $transaction['amount'],
                        'dividend'  => 0
                    );
                }
                break;
            case 'S':
                $openPositions[$transaction['ticker']]['shares']   += $transaction['shares'];
                $openPositions[$transaction['ticker']]['purchase'] += $transaction['amount'];
                if ($openPositions[$transaction['ticker']]['shares'] == 0) unset($openPositions[$transaction['ticker']]);   
                break;
            case 'D':
                if (array_key_exists ( $transaction['ticker'] , $openPositions )){
                    $openPositions[$transaction['ticker']]['dividend'] += $transaction['amount'];
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

?>