<?php
function retrieveIEXBatchData($symbol, $saveData = false, $verbose = false, $debug = false){

    $URL  = iexURL;
    $URL .= 'stock/market/batch?symbols='.$symbol.'&types=quote';
    $URL .= '&token='.iexPK;

    if ($verbose) show($URL);

    $json = curl_get_contents($URL);      //retrieve data

    $data = json_decode($json,1);

    if ($verbose) show($data);
    return $data;
}

function retrieveIEXCompanyData($symbol, $saveData = false, $verbose = false, $debug = false){

    $URL  = iexURL;
    $URL .= 'stock/'.$symbol.'/company';
    $URL .= '?token='.iexPK;

    if ($verbose) show($URL);

    $json = curl_get_contents($URL);      //retrieve data
    $data = json_decode($json,1);

    if ($verbose) show($data);
    return $data;
}
?>

