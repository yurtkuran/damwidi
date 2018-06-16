<?php

function returnAboveBelow($verbose = false, $debug = false){

    // $timeframe = $_GET['timeframe'];

    // load all sectors, index and fund
    $sectors = loadSectors('SI');

    // load timeframe details
    $timeFrames = json_decode(file_get_contents("./config/comparison.json"),1);
    $timeFrame  = $timeFrames['2wk'];

    // if ($verbose)show($data);
    // if(!$verbose)echo json_encode($data);
}

?>