<?php

// update `data_history` table
function updateHistoryTable($verbose = false, $debug = false){

    // determine opening date of fund
    $dbc = connect();
    $stmt = $dbc->prepare("SELECT `transaction_date` FROM `data_transactions` ORDER BY `transaction_date` ASC LIMIT 1");
    $stmt->execute();
    $result    = $stmt->fetch(PDO::FETCH_ASSOC);
    $firstDate = $result['transaction_date'];

    // load all sectors, index and fund
    $sectors = loadSectors('SI');

    $endDate = date('Y-m-d',  strtotime(date('Y-m-d') . "-1 days"));

    // loop through sectors
    foreach($sectors as $sector){
        $symbol = $sector['sector'];
        $startDate = determineStartDate($sector['sector'], $firstDate);

        // retrieve and save historical data
        $count = 0;
        if ($endDate >= $startDate) {
            $historicalData = getHistory($sector['sector'], $startDate, $endDate, false, false);  // verbose, debug
            if (!empty($historicalData)) {
                $historicalData = $historicalData['alphaVantage'];
                saveHistoricalData($historicalData);
                $count = count($historicalData[$symbol]);
            }
        }

        if ($verbose) show($sector['sector'].": update data_history table \n"."start date: ".$startDate."\n"."end date:   ".$endDate."\n"."added ".$count." record".($count <> 1 ?'s':''));

        // sleep for a random amount of time to prevent rate limiting from AlphaVantage
        sleep(rand(12,15));
    }

    // create notifications
    $message = date('Y-m-d H:i:s')." - Complete: Update history table";
    show($message);
    writeAirTableRecord($message);
}

function determineStartDate($sector, $firstDate){
    $dbc = connect();
    $stmt = $dbc->prepare("SELECT * FROM `data_history` WHERE `symbol` = :symbol ORDER BY `date` DESC LIMIT 1");
    $stmt->bindParam(':symbol', $sector);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!empty($result)){
        $startDate = $result['date'];
        $startDate = date('Y-m-d',  strtotime($result['date'] . "+1 days"));
    } else {
        $startDate = $firstDate;
    }

    return $startDate;
}

?>