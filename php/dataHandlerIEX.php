<?php

function retrieveIEXBatchData($symbol, $saveData = false, $verbose = false, $debug = false){

    $URL  = iexURL;
    $URL .= 'stock/market/batch?symbols='.$symbol.'&types=quote,news&last=2';
    if ($verbose) show($URL);

    $json = file_get_contents($URL);      //retrieve data
    $data = json_decode($json,1);

    if ($verbose) show($data);
    return $data;
}

function retrieveIEXCompanyData($symbol, $saveData = false, $verbose = false, $debug = false){

    $URL  = iexURL;
    $URL .= 'stock/'.$symbol.'/company';
    if ($verbose) show($URL);

    $json = file_get_contents($URL);      //retrieve data
    $data = json_decode($json,1);

    if ($verbose) show($data);
    return $data;
}
?>

