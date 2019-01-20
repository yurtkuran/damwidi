<?php
function retrieveSectorWeights($verbose = false, $debug = false, $loadNewData = true, $saveData = true){

    $filename = './tmp/spindices.txt';
    if($loadNewData){
        $data = curl_get_contents(SPINDICES_URL);
    } else {
        $data = file_get_contents($filename);
        $data = json_decode($data, 1);
    }

    if($saveData) {
        $file = fopen($filename, "w");
        fwrite($file, json_encode($data));
    }

    if($verbose){
        show('Error: '.$data['error']);
        show($data['info']);
    }

    // parse response to extract sector data array
    $sectorData = $data['response'];
    $start      = strpos($sectorData, "indexData");
    $mid        = strpos($sectorData,"{", $start);
    $end        = strpos($sectorData,";", $start);
    $sectorData = json_decode(substr($sectorData, $mid, $end-$mid),1);

    if($verbose) show($sectorData['indexSectorBreakdownHolder']);

    $sectorWeights = array();
    foreach($sectorData['indexSectorBreakdownHolder']['indexSectorBreakdown'] as $sector){
        $sectorWeights[$sector['sectorDescription']]['sectorDescription'] = $sector['sectorDescription'];
        $sectorWeights[$sector['sectorDescription']]['sectorWeight']      = $sector['marketCapitalPercentage'];
        $sectorWeights[$sector['sectorDescription']]['marketCapital']     = $sector['marketCapital'];
    }

    if (!$data['error']){
        $data = array(
            'status'             => 1,
            'effectiveDate'      => date('Y-m-d', $sectorData['indexSectorBreakdownHolder']['effectiveDate']/1000),
            'fetchedDate'        => date('Y-m-d'),
            'indexMarketCapital' => $sector['indexMarketCapital'],
            'sectorWeights'      => $sectorWeights
        );
    } else {
        $data = array(
            'status'             => 0,
            'fetchedDate'        => date('Y-m-d'),
        );
    }

    if($verbose) show($data);
    return $data;

}
?>