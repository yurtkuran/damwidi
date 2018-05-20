<?php

function calcSMA($array, $field){
    $sum = 0;
    foreach($array as $data){
        $sum += $data[$field];
    }
    return $sum/count($array);
}

function calculateGain($current, $previous, $roundDigits = 2){
    return round(100*($current - $previous)/$previous, $roundDigits);
}

function consoleLog( $data ) {
    if ( is_array( $data ) )
        $output = "<script>console.log( 'Debug Objects: " . implode( ',', $data) . "' );</script>";
    else
        $output = "<script>console.log( 'Debug Objects: " . $data . "' );</script>";
    echo $output;
}

function curl_get_contents($url){
    // explainationn of cURL errors: https://curl.haxx.se/libcurl/c/libcurl-errors.html

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    $response = curl_exec($ch);

    $data = array(
        'error'    => curl_errno($ch),
        'info'     => curl_getinfo($ch),
        'response' => $response
    );

    curl_close($ch);

    return $data;
}

#return a font awesome icon
function i($code){
    $icon = '<i class="fa fa-'.$code.'"></i>';
    return $icon;
}

function noComma($var){
    return floatval(preg_replace('/[^\d.]/', '', $var));
}

function priceGain($priceData, $index0, $index1, $roundDigits = 2){
    $priceGain = array();

    $t0 = array_slice($priceData, $index0, 1);
    $t1 = array_slice($priceData ,$index1, 1);

    $last      = $t0[key($t0)]['close'];
    $prevClose = $t1[key($t1)]['close'];
    $gain      = round(100*($last-$prevClose)/$prevClose, $roundDigits);

    $priceGain = array(
        "last"      => $last,
        "prevClose" => $prevClose,
        "gain"      => $gain
    );

    return $priceGain;
}

function save($filename, $data){
    $json = json_encode($data);
    $file = fopen($filename, "w");
    fwrite($file, $json);
    fclose($file);
}

function show($data){
    echo "<pre>";
    print_r($data);
    echo "</pre>";
}

function stDev($array) {
    $sum = 0;
    foreach($array as $data){
        $sum += $data['4. close'];
    }
    $fMean = $sum / count($array);

    $fVariance = 0.0;
    foreach ($array as $i)
    {
        $fVariance += pow($i['4. close'] - $fMean, 2);
    }

    return (float) sqrt($fVariance/count($array));
}

?>