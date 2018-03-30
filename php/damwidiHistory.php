<?

// update `data_history` table
function updateHistoryTable($verbose = false, $debug = false){

    //determine starting date
    $dbc = connect();
    $stmt = $dbc->prepare("SELECT * FROM `data_history` ORDER BY `date` DESC LIMIT 1");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!empty($result)){
        // start at the last record in the value table
        $firstRecord = false;
        $startDate   = $result['date'];
    } else {
        // start with first date in transaction table
        $stmt = $dbc->prepare("SELECT `transaction_date` FROM `data_transactions` ORDER BY `transaction_date` ASC LIMIT 1");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $startDate   = $result['transaction_date'];
    }
    $endDate = date('Y-m-d',  strtotime(date('Y-m-d') . "-1 days"));

    // temp override - remove later
    // $startDate = "2018-03-28";
    // $endDate   = "2018-03-29";

    if ($verbose) show("update data_value table \n"."start date: ".$startDate."\n"."end date:   ".$endDate);

    // get list of sectors and index
    $sectors = loadSectors('SI');

    // create array of symbols
    $symbols = array();
    foreach($sectors as $sector){
        array_push($symbols, $sector['sector']);
    }
    $historicalData = getHistory($symbols, $startDate, $endDate, false, false)['alphaVantage'];  // verbose, debug
    // show($historicalData);
    saveHistoricalData($historicalData);

    show(date('Y-m-d H:m:s')." - Complete: Update history table");
}

?>