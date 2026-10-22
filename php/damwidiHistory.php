<?php

// update `data_history` table
function updateHistoryTable($verbose = false, $debug = false, $stdin = false){
    if ($verbose) show("--- UPDATE HISTORY TABLE ---");

    // store start time used to determine function duration
    $start = date('Y-m-d H:i:s');


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
    $symbolsProcessed = 0;
    $recordsWritten = 0;
    foreach($sectors as $sector){
        $symbol = $sector['sector'];
        $startDate = determineStartDate($sector['sector'], $firstDate);

        // retrieve and save historical data
        $count = 0;
        if ($endDate >= $startDate) {
            $historicalData = getHistory($sector['sector'], $startDate, $endDate, false, false);  // verbose, debug
            if (!empty($historicalData)) {
                $historicalData = $historicalData['polygon'];
                saveHistoricalData($historicalData);
                $count = count($historicalData[$symbol]);
            }
        }

        if ($verbose) show($sector['sector'].": update data_history table \n"."start date: ".$startDate."\n"."end date:   ".$endDate."\n"."added ".$count." record".($count <> 1 ?'s':''));

        $symbolsProcessed++;
        $recordsWritten += $count;

        // sleep for a random amount of time to prevent rate limiting from AlphaVantage
        // rateLimit();
    }

    Logs::addJobResult(array(
        'symbols_processed' => $symbolsProcessed,
        'records_written' => $recordsWritten,
        'end_date' => $endDate,
    ));

    // create notifications
    $end          = date('Y-m-d H:i:s');
    $table        = "history";
    $notification = $end." - ".str_pad($table, 20)." - ".date('H:i:s', mktime(0, 0, strtotime($end)-strtotime($start)));

    if ($verbose) show($start." start");
    if (!$stdin) {
        show($notification);
    } else {
        echo $notification."\r\n";;
    }
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
