<?
// log into bivio.com
function bivioLogin($verbose){
    // options
    $login_email      = 'erol@yurtkuran.net';
    $login_pass       = '75ohms';
    $cookie_file_path = './cookies/cookies.txt';

    //login form action url
    $url       ="https://www.bivio.com/pub/login";
    $postinfo  = "x1=".$login_email;
    $postinfo .= "&x2=".$login_pass;
    $postinfo .= "&c=aMTM_!bMTQ_!eZjABAWYxAQFmMgEBdgEx!zYU5nX18_";
    $postinfo .= "&v=2";
    $postinfo .= "&f1=Secure Login";

    if ($verbose) show($url."?".$postinfo);

    $ch = curl_init();
    curl_setopt ($ch, CURLOPT_URL, $url);
    curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, 1);
    curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt ($ch, CURLOPT_FOLLOWLOCATION, TRUE);
    curl_setopt ($ch, CURLOPT_SSLVERSION, 6);
    curl_setopt ($ch, CURLOPT_POSTFIELDS, $postinfo);
    curl_setopt ($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/42.0.2311.135 Safari/537.36");
    curl_setopt ($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt ($ch, CURLOPT_COOKIEJAR, $cookie_file_path);
    curl_setopt ($ch, CURLOPT_COOKIEFILE, $cookie_file_path);
    curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt ($ch, CURLOPT_HEADER, 1);
    curl_setopt ($ch, CURLOPT_ENCODING,"gzip");
    curl_setopt ($ch, CURLOPT_POST, true);
    curl_setopt ($ch, CURLOPT_FRESH_CONNECT , 1);

    $loginp= curl_exec($ch);
    if ($loginp === FALSE) {
        die(curl_error($ch));
    } else {
        if ($verbose) show("Bivio login successful");
    }
    return $ch;
}

// retrieve damwidi value from bivio.com
function returnBivioValue($ch, $date, $verbose = false){
    $URL = "https://www.bivio.com/damwidi/accounting/reports/valuation?date=".$date;
    curl_setopt($ch, CURLOPT_URL, $URL);
    $response = curl_exec($ch);

    // parse response for value
    $start = strpos($response, "Value of One Unit");
    $mid   = strpos($response, "<td class=\"b_align_e\" class=\"amount_cell\">", $start);
    $end   = strpos($response,"</td>", $mid);
    $data  = substr($response, $mid, $end-$mid);
    $data  = floatval(preg_replace('/[^0-9\.]/', '', $data));

    if ($verbose) show($date." ".$data);
    return $data;
}

// return complete list of transactions for trade history table
function returnTransactions($verbose, $debug){
    $dbc = connect();
    $stmt = $dbc->prepare("SELECT   `transaction_date`, `ticker`, `type`, `amount`, `shares`, `description`
                           FROM     `data_transactions`
                           WHERE    (`type` = 'S' OR `type` = 'B')
                           ORDER BY `transaction_date` DESC");
    $stmt->execute();
    $result = $stmt->fetchall(PDO::FETCH_ASSOC);

    if($verbose) show($result);
    if(!$verbose) echo json_encode(array('data' => $result));
}

// retrieve recent transactions from bivio.com and add to `data_transactions` table
function updateBivioTransactions($verbose){

    // open cURL session to scrape damwidi value from Bivio
    $ch = bivioLogin($verbose);

    // retrieve latest transactions
    $URL = "https://www.bivio.com/get-csv/damwidi/accounting/account/detail.csv?p=16580800007";
    curl_setopt($ch, CURLOPT_URL, $URL);
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    $response = explode( "\n", curl_exec($ch));
    unset($response[count($response)-1]);  // remove last empty line
    if ($verbose) show($response);

    // determine latest detail date
    $dbc = connect();
    $stmt = $dbc->prepare("SELECT `transaction_date` FROM `data_transactions` ORDER BY `transaction_date` DESC LIMIT 1");
    $stmt->execute();
    $startDate = $stmt->fetch(PDO::FETCH_ASSOC);
    $startDate = $startDate['transaction_date'];
    if ($verbose) show("start date: ".$startDate);

    // parse CSV file
    $firstline = true;
    for ($i=0; $i<= count($response)-1; $i++){
        $transaction = array();
        $data = str_getcsv($response[$i]);
        $date = date('Y-m-d', strtotime($data['0']));

        if(!$firstline and $date > $startDate){
            for ($j=0; $j<count($data); $j++){
                $transaction[$headers[$j]] = $data[$j];
            }
            $transaction['Date'] = date('Y-m-d', strtotime($transaction['Date']));
            $transaction = array_merge( $transaction,  parseTransaction( $transaction['Description']) );
            if ($verbose) show($transaction);
            saveTransactionData($transaction); //update database

        } else if ($firstline){
            $headers = $data;
            $firstline = false;
        }
    }
    $stmt = $dbc->prepare("ALTER TABLE `data_transactions` ORDER BY `transaction_date` ASC");
    $stmt->execute();

    // close cURL session
    curl_close($ch);

    show(date('Y-m-d H:m:s')." - Complete: Update Bivio transactions");
}

function parseTransaction($transaction){
    $transaction = strtoupper($transaction);
    $parse = array();

    if (strpos($transaction, 'DIVIDEND')){
        $parse['type'] = 'D';

    } else if (strpos($transaction, 'INTEREST' !== FALSE )) {
        $parse['type'] = 'I';

    } else if (strpos($transaction, 'PAYMENT') !== FALSE ) {
        $parse['type'] = 'P';

    } else if (strpos($transaction, 'PURCHASED') !== FALSE ) {
        $parse['type'] = 'B';

    } else if (strpos($transaction, 'SOLD') !== FALSE ) {
        $parse['type'] = 'S';

    } else if (strpos($transaction, 'RETURN')  !== FALSE ) {
        $parse['type'] = 'R';

    } else if (strpos($transaction, 'EXPENSE') !== FALSE ) {
         $parse['type'] = 'E';

    } else if (strpos($transaction, 'SPLIT') !== FALSE ) {
         $parse['type'] = 'L';

    } else if (strpos($transaction, 'WITHDRAWL') !== FALSE ) {
         $parse['type'] = 'W';

    } else {
        $parse['type'] = 'X';
    }

    $parse['ticker'] = "";
    $parse['shares'] = 0;

    // determine ticker
    if (strpos("BSD", $parse['type']) !== FALSE) {
        $start = strpos( $transaction, "(" );
        $end   = strpos( $transaction, ")" );
        if ($start !== FALSE and $end !== FALSE) {
            $parse['ticker'] =  substr( $transaction, $start+1, $end-$start-1 );
        }
    }

    // determine shares
    if (strpos("BS", $parse['type']) !== FALSE) {
        $start = strpos( $transaction, " ");
        $end   = strpos( $transaction, "SHARES");
        $parse['shares'] =  substr( $transaction, $start+1, $end-$start-1 ) * ($parse['type'] == 'S' ? -1 : 1);
    }

    // show($parse);
    return $parse;
}

?>