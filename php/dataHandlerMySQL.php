<?php

// insert stock into perforamance table
function insertPerformanceStock($symbol, $name){
    $dbc = connect();
    $stmt = $dbc->prepare("INSERT INTO `data_performance` (`sector`, `name`, `description`, `type`) VALUES (:symbol, :name , :description, 'K')");
    $stmt->bindValue(':symbol',      strtoupper($symbol));
    $stmt->bindValue(':name',        $name);
    $stmt->bindValue(':description', $name);
    $stmt->execute();
}

// returns data from data_SPDR table based on mask
// C=cash, I=index, S=sector, F=fund (i.e. damwidi), K=stock
function loadSectors($mask = 'CISFK', $rawQuery = null){
    $dbc = connect();
    if ($rawQuery == null) {
        $stmt = $dbc->prepare("SELECT * FROM `data_performance` WHERE INSTR('$mask', `type`) ORDER BY `sector`");
    } else {
        $stmt = $dbc->prepare($rawQuery);
    }
    $stmt->execute();
    $result  = $stmt->fetchall(PDO::FETCH_ASSOC); // first column (id) is used as the key
    $sectors = array();

    // add sector symbol back into the array
    foreach($result as $data){
        $sectors[$data['sector']] = $data;
    }    

    return $sectors;
}

function loadRawQuery($rawQuery){
    $dbc = connect();
    $stmt = $dbc->prepare($rawQuery);
    $stmt->execute();
    $result  = $stmt->fetchall(PDO::FETCH_ASSOC); // first column (id) is used as the key
    return $result;
}

function loadAllocations(){
    $dbc = connect();
    $stmt = $dbc->prepare("CALL sector_allocations()");
    $stmt->execute();
    $result = $stmt->fetchall(PDO::FETCH_ASSOC);

    $sectors = array();
    // add sector symbol back into the array
    foreach($result as $data){
        $sectors[$data['symbol']] = $data;
    }  

    return $sectors;
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

// save key data to database
function saveSPKeyData($symbol, $keyData){
    $dbc = connect();

    // prepare sql and bind parameters
    $stmt = $dbc->prepare("UPDATE `sp_holdings` 
                           SET     employees           = :employees,
                                   marketcap           = :marketcap,
                                   sharesOutstanding   = :sharesOutstanding,
                                   sharesFloat	       = :sharesFloat,
                                   ttmEPS	           = :ttmEPS,
                                   peRatio	           = :peRatio,
                                   beta	               = :beta,
                                   avg10Volume	       = :avg10Volume,
                                   avg30Volume	       = :avg30Volume,
                                   week52change	       = :week52change,
                                   week52high	       = :week52high,
                                   week52low	       = :week52low,
                                   day200MovingAvg	   = :day200MovingAvg,
                                   day50MovingAvg	   = :day50MovingAvg,
                                   year5ChangePercent  = :year5ChangePercent,
                                   year2ChangePercent  = :year2ChangePercent,
                                   year1ChangePercent  = :year1ChangePercent,
                                   ytdChangePercent	   = :ytdChangePercent,
                                   month6ChangePercent = :month6ChangePercent,
                                   month3ChangePercent = :month3ChangePercent,
                                   month1ChangePercent = :month1ChangePercent,
                                   day30ChangePercent  = :day30ChangePercent,
                                   day5ChangePercent   = :day5ChangePercent,
                                   ttmDividendRate     = :ttmDividendRate,
                                   dividendYield       = :dividendYield,
                                   nextDividendDate    = :nextDividendDate,
                                   exDividendDate      = :exDividendDate,
                                   nextEarningsDate    = :nextEarningsDate
                           WHERE   symbol = :symbol");

    $stmt->bindParam(':symbol', $symbol);
    $stmt->bindParam(':employees',           $keyData['employees']);
    $stmt->bindParam(':marketcap',           $keyData['marketcap']);
    $stmt->bindParam(':sharesOutstanding',   $keyData['sharesOutstanding']);
    $stmt->bindParam(':sharesFloat',         $keyData['float']);
    $stmt->bindParam(':ttmEPS',              $keyData['ttmEPS']);
    $stmt->bindParam(':peRatio',             $keyData['peRatio']);
    $stmt->bindParam(':beta',                $keyData['beta']);
    $stmt->bindParam(':avg10Volume',         $keyData['avg10Volume']);
    $stmt->bindParam(':avg30Volume',         $keyData['avg30Volume']);
    $stmt->bindParam(':week52change',        $keyData['week52change']);
    $stmt->bindParam(':week52high',          $keyData['week52high']);
    $stmt->bindParam(':week52low',           $keyData['week52low']);
    $stmt->bindParam(':day200MovingAvg',     $keyData['day200MovingAvg']);
    $stmt->bindParam(':day50MovingAvg',      $keyData['day50MovingAvg']);
    $stmt->bindParam(':year5ChangePercent',  $keyData['year5ChangePercent']);
    $stmt->bindParam(':year2ChangePercent',  $keyData['year2ChangePercent']);
    $stmt->bindParam(':year1ChangePercent',  $keyData['year1ChangePercent']);
    $stmt->bindParam(':ytdChangePercent',    $keyData['ytdChangePercent']);
    $stmt->bindParam(':month6ChangePercent', $keyData['month6ChangePercent']);
    $stmt->bindParam(':month3ChangePercent', $keyData['month3ChangePercent']);
    $stmt->bindParam(':month1ChangePercent', $keyData['month1ChangePercent']);
    $stmt->bindParam(':day30ChangePercent',  $keyData['day30ChangePercent']);
    $stmt->bindParam(':day5ChangePercent',   $keyData['day5ChangePercent']);
    $stmt->bindParam(':ttmDividendRate',     $keyData['ttmDividendRate']);
    $stmt->bindParam(':dividendYield',       $keyData['dividendYield']);
    $stmt->bindParam(':nextDividendDate',    $keyData['nextDividendDate']);
    $stmt->bindParam(':exDividendDate',      $keyData['exDividendDate']);
    $stmt->bindParam(':nextEarningsDate',    $keyData['nextEarningsDate']);
    $stmt->execute();
}


// saves transaction data scraped from bivio.com
function saveTransactionData($transaction){
    $dbc = connect();
    $stmt = $dbc->prepare("INSERT INTO `data_transactions` (`transaction_date`, `symbol`, `type`, `amount`, `shares`, `description` ) VALUES (:transaction_date, :symbol, :type, :amount, :shares, :description)");
    $stmt->bindParam(':transaction_date', $transaction['Date']);
    $stmt->bindParam(':symbol',           $transaction['symbol']);
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
                                     `open` = :open, `high` = :high, `low` = :low, `close` = :close, `source` = :source
                               WHERE `date` = :date");
    } else {
        //date not found, insert new record
        $stmt = $dbc->prepare("INSERT INTO `data_value` (`date`, `SPY`, `cash`, `market_value`, `account_value`, `payments`, `total_shares`, `share_value`, `bivio_value`, `open`, `high`, `low`, `close`, `source`)
                               VALUES (:date, :SPY, :cash, :market_value, :account_value, :payments, :total_shares, :share_value, :bivio_value, :open, :high, :low, :close, :source)");
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
    $stmt->bindValue(':source',        $valueData['source']);
    $stmt->execute();
}

function removePerformanceStock($symbol){
    $dbc = connect();
    $stmt = $dbc->prepare("DELETE FROM `data_performance` WHERE `sector` = :symbol");
    $stmt->bindValue(':symbol', strtoupper($symbol));
    $stmt->execute();
}

function alterPerformanceTable(){
    $query  = "ALTER TABLE `data_performance` CHANGE `shares`   `shares`   INT(6)       NOT NULL DEFAULT '0';";
    $query .= "ALTER TABLE `data_performance` CHANGE `weight`   `weight`   DECIMAL(4,2) NOT NULL DEFAULT '0';";
    $query .= "ALTER TABLE `data_performance` CHANGE `previous` `previous` DECIMAL(8,3) NOT NULL DEFAULT '0';";
    $query .= "ALTER TABLE `data_performance` CHANGE `1wk`      `1wk`      DECIMAL(8,3) NOT NULL DEFAULT '0';";
    $query .= "ALTER TABLE `data_performance` CHANGE `2wk`      `2wk`      DECIMAL(8,3) NOT NULL DEFAULT '0';";
    $query .= "ALTER TABLE `data_performance` CHANGE `4wk`      `4wk`      DECIMAL(8,3) NOT NULL DEFAULT '0';";
    $query .= "ALTER TABLE `data_performance` CHANGE `8wk`      `8wk`      DECIMAL(8,3) NOT NULL DEFAULT '0';";
    $query .= "ALTER TABLE `data_performance` CHANGE `1qtr`     `1qtr`     DECIMAL(8,3) NOT NULL DEFAULT '0';";
    $query .= "ALTER TABLE `data_performance` CHANGE `1yr`      `1yr`      DECIMAL(8,3) NOT NULL DEFAULT '0';";
    $query .= "ALTER TABLE `data_performance` CHANGE `ytd`      `ytd`      DECIMAL(8,3) NOT NULL DEFAULT '0';";
    $query .= "ALTER TABLE `data_performance` CHANGE `as-of`             `as-of`             DATE NULL;";
    $query .= "ALTER TABLE `data_performance` CHANGE `effectiveDate`     `effectiveDate`     DATE NULL;";
    $query .= "ALTER TABLE `data_performance` CHANGE `fetchedDate`       `fetchedDate`       DATE NULL;";
    $query .= "ALTER TABLE `data_performance` CHANGE `sector`            `sector`            CHAR(5)      CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL;";
    $query .= "ALTER TABLE `data_performance` CHANGE `Description`       `Description`       VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL;";
    $query .= "ALTER TABLE `data_performance` CHANGE `Name`              `Name`              VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL;";
    $query .= "ALTER TABLE `data_performance` CHANGE `sectorDescription` `sectorDescription` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL;";
    $query .= "ALTER TABLE `data_performance` CHANGE `type`              `type`              CHAR(1)      CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL;";
    $query .= "ALTER TABLE `data_performance` CHANGE `Name` `name` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL;";
    $query .= "ALTER TABLE `data_performance` CHANGE `Description` `description` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL;";

    $dbc = connect();
    $stmt = $dbc->prepare($query);
    $stmt->execute();

}

?>