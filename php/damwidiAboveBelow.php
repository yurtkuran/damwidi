<?php

function returnAboveBelow($verbose = false, $debug = false){

    // $timeframe = $_GET['timeframe'];

    // load all sectors, index and fund
    $sectors = loadSectors('SI');

    // load timeframe details
    $timeFrame = $_GET['timeframe'];
    if ($timeFrame != 'ytd') {
        $timeFrames = json_decode(file_get_contents("./config/comparison.json"),1);
        $length = $timeFrames[$timeFrame]['lengthDays'];
    } else {
        $length = determineYTDlength();
    }

    $data = array();

    // calculate gain
    foreach($sectors as $sector){
        $symbol = $sector['sector'];
        $historicalData = returnHistoricalData($symbol, $length);

        if (count($historicalData) >= $length){
            for ($i=0; $i < $length; $i++){
                if ($i==0) {
                    $data[$symbol]['gain'][$i] = 1;
                } else {
                    $gain = $data[$symbol]['gain'][$i-1] * calculateGain($historicalData[$i]['close'], $historicalData[$i-1]['close'], 6, false);
                    $data[$symbol]['gain'][$i] = round($gain, 4);
                }
            }
        }
    }

    //calculate RS (relative strength)
    foreach($sectors as $sector){
        $symbol = $sector['sector'];

        if(array_key_exists($symbol, $data)){
            for ($i=0; $i < $length; $i++ ){
                $data[$symbol]['rs'][$i] = round(100*($data[$symbol]['gain'][$i] / $data['SPY']['gain'][$i]),4);
            }
        }
    }

    // create label array
    $labels = array();
    for ($i=0; $i < $length; $i++ ){
        $labels[$i] = $historicalData[$i]['date'];
    }

    //create dataSummary array to sort data
    $i = 0;
    $dataSummary = array();
    foreach($sectors as $sector){
        $symbol = $sector['sector'];

        if(array_key_exists($symbol, $data)){
            $dataSummary[$i]['sector']   = $symbol;
            $dataSummary[$i]['name']     = $sector['Name'];
            $dataSummary[$i]['type']     = $sector['type'];
            $dataSummary[$i]['rs']       = $data[$symbol]['rs'][$length-1];
            $dataSummary[$i++]['gain']   = $data[$symbol]['gain'][$length-1];
        }
    }

    // build 'above the line' dataset
    usort($dataSummary,function($a,$b) {return ($a['gain'] < $b['gain']) ; }); //sort desending
    $aboveDataSet = buildDataSet($data, $dataSummary, 'above');

    // build 'below the line' dataset
    usort($dataSummary,function($a,$b) {return ($a['gain'] > $b['gain']) ; }); //sort ascending
    $belowDataSet = buildDataSet($data, $dataSummary, 'below');

    // build relative strength 'rs' dataset
    usort($dataSummary,function($a,$b) {return ($a['rs'] < $b['rs']) ; }); //sort desending
    $rsDataSet = buildDataSet($data, $dataSummary, 'rs');

    $data = array(
        'labels' => $labels,
        'above'  => $aboveDataSet,
        'below'  => $belowDataSet,
        'rs'     => $rsDataSet
    );

    if ($verbose) show($data);
    if (!$verbose) echo json_encode($data);
}

function returnHistoricalData($symbol, $length){

    $dbc = connect();
    $stmt = $dbc->prepare("SELECT * FROM `data_history` WHERE `symbol` = :symbol ORDER BY `date` DESC LIMIT :length");
    $stmt->bindParam(':symbol', $symbol);
    $stmt->bindParam(':length', $length, PDO::PARAM_INT);
    $stmt->execute();

    $result = $stmt->fetchall(PDO::FETCH_ASSOC);
    return  array_reverse($result);

}

function determineYTDlength(){
    $startDate = date('Y').'-01-01';
    $dbc = connect();
    $stmt = $dbc->prepare("SELECT COUNT(*) FROM `data_history` WHERE `symbol` = 'SPY' AND `date` >= :date");
    $stmt->bindParam(':date', $startDate);
    $stmt->execute();

    $result = $stmt->fetch(PDO::FETCH_NUM);
    return (int)$result[0]+1;
}

function buildDataSet($data, $dataSummary, $type){
    $formats = returnFormatDetails($type);
    $formatCount = count($formats)-1;

    $datasetOptions = array(
        'fill' => false,
        'lineTension' => 0.4,
        'borderWidth' => 3,
        'borderCapStyle' => 'butt',
        // 'borderDash' => [],
        'borderDashOffset' => 0.0,
        'borderJoinStyle' => 'miter',
        'pointBorderColor' => "rgba(0,0,0,1)",
        'pointBackgroundColor' => "#fff",
        'pointBorderWidth' => 0,
        'pointHoverRadius' => 0,
        'pointHoverBackgroundColor' => "rgba(75,192,192,1)",
        'pointHoverBorderColor' => "rgba(220,220,220,1)",
        'pointHoverBorderWidth' => 2,
        'pointRadius' => 0,
        'pointHitRadius' => 10,
        'spanGaps' => false,
    );

    $i=0;
    $dataset = array();
    foreach ($dataSummary as $sector){
        $formatIndex = ($i <= $formatCount) ? $i : $formatCount;

        $dataset[$i] = $datasetOptions;
        $dataset[$i]['borderDash']      = explode(",", $formats[$formatIndex]['style']);
        $dataset[$i]['borderWidth']     = $formats[$formatIndex]['weight'];
        $dataset[$i]['borderColor']     = "rgba(".$formats[$formatIndex][$type].")";
        $dataset[$i]['backgroundColor'] = "rgba(".substr($formats[$formatIndex][$type],0,11).",0.1)";
        $dataset[$i]['label']           = $sector['name'];

        // add y-axis data
        $dataset[$i]['data'] = $data[$sector['sector']][($type != 'rs' ? 'gain' : 'rs')];

        // format SPY
        if ($sector['type'] == 'I'){
            $dataset[$i]['borderDash']      = [];
            $dataset[$i]['borderWidth']     = 3;
            $dataset[$i]['borderColor']     = "rgba(0,0,0,1)";
            $dataset[$i]['backgroundColor'] = "rgba(255,255,255,0.1)";

            if($type <> 'rs') break; // truncate loop at the index
        }

        ++$i;
    }

    return $dataset;
}

function returnFormatDetails($type){
    $dbc = connect();
    if($type=='rs'){
        $table = 'format_rs';
    } else {
        $table = 'format_above_below';
    }

    $stmt = $dbc->prepare("SELECT * FROM ".$table);
    $stmt->execute();
    return  $stmt->fetchall(PDO::FETCH_ASSOC);
}

?>