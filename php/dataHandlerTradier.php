<?
function retrieveMarketCalendar($year, $month, $verbose = false, $debug = false){

    $url = 'https://sandbox.tradier.com/v1/markets/calendar?';
    $url .= 'year='.$year; 
    $url .= '&month='.$month;
    
    $ch = curl_init($url);

    // Headers
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        "Accept: application/json",
        "Authorization: Bearer ata5xeDkGRVybQMZbiSn0rQH8IgR",
    ));

    // Send synchronously
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    $result = curl_exec($ch);
    curl_close($ch);

    if ($result === FALSE) {
        // cURL failed
        $result = "cURL Error: " . curl_error($ch);
    } else {
        $result = json_decode($result,1)['calendar']['days']['day'];
    }

    if ($verbose) show($result);
    return $result;
}
?>