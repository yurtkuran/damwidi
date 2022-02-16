<?php
function retrieveYahooQuote($symbol, $verbose = false){

    $url = awsURL.$symbol;

    $ch = curl_init($url);

    // Headers
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        "Accept: application/json",
        "x-api-key: ".awsKey,
    ));

    // Send synchronously
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    $result = curl_exec($ch);
    curl_close($ch);

    if ($result === FALSE) {
        // cURL failed
        $result = "cURL Error: " . curl_error($ch);
    } 

    $data = json_decode($result,1);

    if ($verbose) show($data);
    return $data;
}
?>