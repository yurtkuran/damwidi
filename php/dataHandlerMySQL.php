<?php
// returns data from data_SPDR table based on mask
// C=cash, I=index, S=sector, F=fund (i.e. damwidi)
function loadSectors($mask = 'CISF'){
    $dbc = connect();
    $stmt = $dbc->prepare("SELECT * FROM `data_performance` WHERE INSTR('$mask', `type`) ORDER BY `sector`");
    $stmt->execute();
    $result = $stmt->fetchall(PDO::FETCH_ASSOC);

    return $result;
}

function loadDamdidiValue($limit = 1){
    $dbc = connect();
    $stmt = $dbc->prepare("SELECT * FROM `data_value` ORDER BY `date` DESC LIMIT ".$limit);
    $stmt->execute();
    $result = $stmt->fetchall(PDO::FETCH_ASSOC);

    return $result;
}

function loadDamwidiBasket(){
    $dbc = connect();
    $stmt = $dbc->prepare("SELECT * FROM `data_basket` WHERE 1");
    $stmt->execute();
    $result = $stmt->fetchall(PDO::FETCH_ASSOC);

    return $result;
}

// save damwidi basket details
function saveDamwidiBasket($symbol, $description, $exists = FALSE){
    $dbc = connect();
    if ($exists){
        $stmt = $dbc->prepare("UPDATE `data_basket` SET description = :description, dateLastVisited = :dateLastVisited, visitCount = visitCount+1 WHERE symbol = :symbol");
    } else {
        $stmt = $dbc->prepare("INSERT INTO `data_basket` (symbol, description, dateAdded, dateLastVisited, visitCount) VALUES (:symbol, :description, :dateAdded, :dateLastVisited, 1)");
        $stmt->bindValue(':dateAdded', date('Y-m-d H:i:s') );        
    }
    $stmt->bindParam(':symbol', $symbol);
    $stmt->bindParam(':description', $description); 
    $stmt->bindValue(':dateLastVisited', date('Y-m-d H:i:s') );
    $stmt->execute();
}

// saves cash balance to `data_performance`table in both the basis and previous columns
function saveCashBalance($amount, $asof){
    $dbc = connect();
    $stmt = $dbc->prepare("UPDATE `data_performance` SET basis = :basis, previous = :previous, `as-of` = :asof WHERE sector = 'CASH'");
    $stmt->bindParam(':basis',    $amount);
    $stmt->bindParam(':previous', $amount);
    $stmt->bindParam(':asof',     $asof);
    $stmt->execute();
}

// save historical data for each sector and indeo to `data_history` table
function saveHistoricalData($historicalData){
    $dbc = connect();

    // prepare sql and bind parameters
    $stmt = $dbc->prepare("INSERT INTO `data_history` (symbol, date, open, high, low, close) VALUES (:symbol, :date, :open, :high, :low, :close)");

    foreach($historicalData as $sector => $dataSet){
        foreach($dataSet as $date => $data){
            $stmt->bindParam(':symbol', $sector);
            $stmt->bindParam(':date',   $date);
            $stmt->bindParam(':open',   $data['open']);
            $stmt->bindParam(':high',   $data['high']);
            $stmt->bindParam(':low',    $data['low']);
            $stmt->bindParam(':close',  $data['close']);
            $stmt->execute();
        }
    }
}

// saves performance (return) data for each sector and index for the given timeframes
function savePerformanceData($performanceData){
    $dbc = connect();
    $dbc->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // prepare sql and bind parameters
    $stmt = $dbc->prepare("UPDATE `data_performance`
                           SET    1wk = :1wk, 2wk = :2wk, 4wk = :4wk, 8wk = :8wk, 1qtr = :1qtr, 1yr = :1yr, ytd = :ytd, previous = :previous, `as-of` = :asof,
                                  basis = :basis, shares = :shares, weight = :weight, effectiveDate = :effectiveDate, fetchedDate = :fetchedDate
                           WHERE  sector = :sector");

    foreach($performanceData as $sector => $data){
        $stmt->bindParam(':sector',        $sector);
        $stmt->bindParam(':1wk',           $data['1wk']);
        $stmt->bindParam(':2wk',           $data['2wk']);
        $stmt->bindParam(':4wk',           $data['4wk']);
        $stmt->bindParam(':8wk',           $data['8wk']);
        $stmt->bindParam(':1qtr',          $data['1qtr']);
        $stmt->bindParam(':1yr',           $data['1yr']);
        $stmt->bindParam(':ytd',           $data['YTD']);
        $stmt->bindParam(':previous',      $data['previous']);
        $stmt->bindParam(':asof',          $data['as-of']);
        $stmt->bindParam(':basis',         $data['basis']);
        $stmt->bindParam(':shares',        $data['shares']);
        $stmt->bindParam(':weight',        $data['weight']);
        $stmt->bindParam(':effectiveDate', $data['effectiveDate']);
        $stmt->bindParam(':fetchedDate',   $data['fetchedDate']);
        $stmt->execute();
    }
}

// saves transaction data scraped from bivio.com
function saveTransactionData($transaction){
    $dbc = connect();
    $stmt = $dbc->prepare("INSERT INTO `data_transactions` (`transaction_date`, `ticker`, `type`, `amount`, `shares`, `description` ) VALUES (:transaction_date, :ticker, :type, :amount, :shares, :description)");
    $stmt->bindParam(':transaction_date', $transaction['Date']);
    $stmt->bindParam(':ticker',           $transaction['ticker']);
    $stmt->bindParam(':type',             $transaction['type']);
    $stmt->bindParam(':amount',           $transaction['Amount']);
    $stmt->bindParam(':shares',           $transaction['shares']);
    $stmt->bindParam(':description',      $transaction['Description']);
    $stmt->execute();
}

// saves value detail (e.g. SPY, case, market_value, account_value, etc)
function saveValueData($valueData){
    $dbc = connect();

    //deteremine if date record exists
    $stmt = $dbc->prepare("SELECT * from `data_value` WHERE `date` = :date");
    $stmt->bindParam(':date', $valueData['date']);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if($result){
        //date found, update record
        $stmt = $dbc->prepare("UPDATE `data_value`
                               SET   `SPY` = :SPY, `cash` = :cash, `market_value` = :market_value, `account_value` = :account_value, `payments` = :payments,
                                     `total_shares` = :total_shares, `share_value` = :share_value, `bivio_value` = :bivio_value,
                                     `open` = :open, `high` = :high, `low` = :low, `close` = :close
                               WHERE `date` = :date");
    } else {
        //date not found, insert new record
        $stmt = $dbc->prepare("INSERT INTO `data_value` (`date`, `SPY`, `cash`, `market_value`, `account_value`, `payments`, `total_shares`, `share_value`, `bivio_value`, `open`, `high`, `low`, `close`)
                               VALUES (:date, :SPY, :cash, :market_value, :account_value, :payments, :total_shares, :share_value, :bivio_value, :open, :high, :low, :close)");
    }
    $stmt->bindParam(':date',          $valueData['date']);
    $stmt->bindParam(':SPY',           $valueData['SPY']);
    $stmt->bindParam(':cash',          $valueData['cash']);
    $stmt->bindValue(':market_value',  $valueData['market_value']);
    $stmt->bindValue(':account_value', $valueData['account_value']);
    $stmt->bindValue(':payments',      $valueData['payments']);
    $stmt->bindValue(':total_shares',  $valueData['total_shares']);
    $stmt->bindValue(':share_value',   $valueData['share_value']);
    $stmt->bindValue(':bivio_value',   $valueData['bivio_value']);
    $stmt->bindValue(':open',          $valueData['open']);
    $stmt->bindValue(':high',          $valueData['high']);
    $stmt->bindValue(':low',           $valueData['low']);
    $stmt->bindValue(':close',         $valueData['close']);
    $stmt->execute();
}

?>