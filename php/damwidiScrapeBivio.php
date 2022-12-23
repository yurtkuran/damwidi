<?php
// log into bivio.com
function bivioLogin($verbose){
    // options
    $login_email      = 'erol@yurtkuran.net';
    $login_pass       = BIVIOPASSWORD;
    $cookie_file_path = './cookies/cookies.txt';

    // https://www.bivio.com/pub/login?c=aMTM_%21bMTQ_%21eZjABAWYxAQFmMgEBdgEx%21zYU5nX18_&f1=Secure%20Login&tz=240&v=2&x1=erol%40yurtkuran.net&x2=XqRh863M7tBAdf

    //login form action url
    $url       = "https://www.bivio.com/pub/login";
    $postinfo  = "tz=300";
    $postinfo .= "&v=2";
    $postinfo .= "&c=aMTM_!bMTQ_!eZjABAWYxAQFmMgEBdgEx!zYU5nX18_";
    $postinfo .= "&x1=".$login_email;
    $postinfo .= "&x2=".$login_pass;
    $postinfo .= "&f1=Secure+Login";

    if ($verbose) show($url."?".$postinfo);

    $ch = curl_init();
    curl_setopt ($ch, CURLOPT_URL, $url);
    curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 0);
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

// retrieve transactions from bivio.com
function retrieveBivioTransactions($verbose){

    // open cURL session to scrape damwidi value from Bivio
    $ch = bivioLogin($verbose);

    // retrieve latest transactions
    $URL = "https://www.bivio.com/get-csv/damwidi/accounting/account/detail.csv?p=16580800007";
    curl_setopt($ch, CURLOPT_URL, $URL);
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    $response = explode( "\n", curl_exec($ch));
    unset($response[count($response)-1]);  // remove last empty line
    if ($verbose) show($response);

    // close cURL session
    curl_close($ch);

    return $response;
}

// retrieve damwidi value from bivio.com
function returnBivioValue($ch, $date, $verbose = false){
    $URL = "https://www.bivio.com/damwidi/accounting/reports/valuation?date=".$date;
    curl_setopt($ch, CURLOPT_URL, $URL);
    $response = curl_exec($ch);

    // create array
    $valuation['date'] = $date;

    // parse response for value
    $start = strpos($response, "Value of One Unit");
    $mid   = strpos($response, "<td class=\"b_align_e\" class=\"amount_cell\">", $start);
    $end   = strpos($response,"</td>", $mid);
    $data  = substr($response, $mid, $end-$mid);
    $data  = floatval(preg_replace('/[^0-9\.]/', '', $data));
    $valuation['value'] = $data;

    // parse response for units
    $start = strpos($response, "Total Number of Valuation Units to Date");
    $mid   = strpos($response, "<td class=\"b_align_e\" class=\"amount_cell\">", $start);
    $end   = strpos($response,"</td>", $mid);
    $data  = substr($response, $mid, $end-$mid);
    $data  = floatval(preg_replace('/[^0-9\.]/', '', $data));
    $valuation['units'] = $data;

    if ($verbose) show($valuation);
    return $valuation;
}

// return complete list of transactions for trade history table
function returnTransactions($verbose, $debug){
    $dbc = connect();
    $stmt = $dbc->prepare("SELECT   `transaction_date`, `symbol`, `type`, `amount`, `shares`, `description`
                           FROM     `data_transactions`
                           WHERE    (`type` = 'S' OR `type` = 'B')
                           ORDER BY `transaction_date` DESC");
    $stmt->execute();
    $result = $stmt->fetchall(PDO::FETCH_ASSOC);

    if($verbose) show($result);
    if(!$verbose) echo json_encode(array('data' => $result));
}

// retrieve recent transactions from bivio.com and add to `data_transactions` table
function updateBivioTransactions($verbose, $debug, $stdin = false){
    if ($verbose) show("--- UPDATE BIVIO TRANSACTIONS ---");

    // store start time used to determine function duration
    $start = date('Y-m-d H:i:s');

    // open cURL session to scrape damwidi value from Bivio
    $ch = bivioLogin($verbose);

    // retrieve latest transactions
    $bivioTransactions = retrieveBivioTransactions($verbose);

    // determine latest detail date
    $dbc = connect();
    $stmt = $dbc->prepare("SELECT `transaction_date` FROM `data_transactions` ORDER BY `transaction_date` DESC LIMIT 1");
    $stmt->execute();
    $startDate = $stmt->fetch(PDO::FETCH_ASSOC);
    $startDate = $startDate['transaction_date'];
    if ($verbose) show("start date: ".$startDate);

    // parse CSV file
    $firstline = true;
    for ($i=0; $i<= count($bivioTransactions)-1; $i++){
        $transaction = array();
        $data = str_getcsv($bivioTransactions[$i]);
        $date = date('Y-m-d', strtotime($data['0']));

        if(!$firstline and $date > $startDate){
            for ($j=0; $j<count($data); $j++){
                $transaction[$headers[$j]] = $data[$j];
            }
            $transaction['Date'] = date('Y-m-d', strtotime($transaction['Date']));
            $transaction = array_merge( $transaction,  parseTransaction( $transaction['Description']) );

            // revise certain symbols, e.g. BRKB -> BRK.B
            switch ($transaction['symbol']){
                case 'BRKB':
                    $transaction['symbol'] = 'BRK.B';
                    break;
                default:
                // symbol okay
            }

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
    curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, 1);
    curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt ($ch, CURLOPT_SSLVERSION, 3);
    curl_close($ch);

    // create notifications
    $end          = date('Y-m-d H:i:s');
    $duration     = strtotime($end)-strtotime($start);
    $table        = "bivio transactions";
    $log          = date('i:s', mktime(0, 0, strtotime($end)-strtotime($start)))." - ".$table;
    $notification = $end." - ".$table." - ".date('H:i:s', mktime(0, 0, strtotime($end)-strtotime($start)));

    Logs::$logger->info($log);

    if ($verbose) show($start." start");
    if (!$stdin) {
        show($notification);
    } else {
        echo $notification."\r\n";;
    }
}

function parseTransaction($transaction){
    $transaction = strtoupper($transaction);
    $parse = array();

    if (strpos($transaction, 'DIVIDEND')){
        $parse['type'] = 'D';

    } else if (strpos($transaction, 'INTEREST') !== FALSE ) {
        $parse['type'] = 'I';

    } else if (substr($transaction, 0, strlen('PAYMENT')) == 'PAYMENT') {
        $parse['type'] = 'P';

    } else if (substr($transaction, 0, strlen('PURCHASED')) == 'PURCHASED') {
        $parse['type'] = 'B';

    } else if (substr($transaction, 0, strlen('SOLD')) == 'SOLD') {
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

    $parse['symbol'] = "";
    $parse['shares'] = 0;

    // determine symbol
    if (strpos("BSD", $parse['type']) !== FALSE) {
        $start = strpos( $transaction, "(" );
        $end   = strpos( $transaction, ")" );
        if ($start !== FALSE and $end !== FALSE) {
            $parse['symbol'] =  substr( $transaction, $start+1, $end-$start-1 );
        }
    }

    // determine shares
    if (strpos("BS", $parse['type']) !== FALSE) {
        $start = strpos( $transaction, " ");
        $end   = strpos( $transaction, "SHARES");
        $parse['shares'] =  (float)substr( $transaction, ($start+1), ($end-$start-1) ) * ($parse['type'] == 'S' ? -1 : 1);
    }

    // show($parse);
    return $parse;
}

function testScrape($verbose){
    // https://gist.github.com/anchetaWern/6150297
    // https://www.the-art-of-web.com/php/html-xpath-query/
    // http://php.net/manual/en/class.domnode.php#domnode.props.nodevalue

    libxml_use_internal_errors(TRUE); // disable libxml errors

    $html = file_get_contents('./tmp/response.html');
    $doc  = new DOMDocument();
    $doc->loadHTML($html);

    libxml_clear_errors(); //remove errors for yucky html

    $xpath = new DOMXpath($doc);

    // save headers
    $headers = $xpath->query("//div[@class='main_body']/table[1]/tr[1]/th");
    $dataHeaders = array();
    foreach($headers as $header){
        $dataHeaders[] = $header->nodeValue;
    }
    $dataHeaders[0]='sector';

    $currnetRow = 1;
    $rows = $xpath->query("//div[@class='main_body']/table[1]/tr[position()>1]");
    foreach($rows as $row){
        if(strpos($row->getAttribute('class'),'b_footer_row')!==0){
            $bivioData[$currnetRow] = array();
            $cells = $xpath->query("td", $row);
            $i=0;
            foreach($cells as $cell){
                // show($cell->nodeValue);
                $bivioData[$currnetRow][$dataHeaders[$i]] = $cell->nodeValue;
                $i++;
            }
            $currnetRow++;
        } else {
            break;
        }
    }

    show($bivioData);

    // retrieve cash
    $cashRow = $xpath->query("//div[@class='main_body']/table[1]/tr[position()>".$currnetRow." and contains(@class,'b_data_row')]")[0];
    $cash = noComma($xpath->query("td[4]", $cashRow)[0]->nodeValue);
    show('cash: $'.$cash);

    // retrieve share value
    $shareValue = $xpath->query("//div[@class='main_body']/table[2]/tr[3]/td[4]")[0]->nodeValue;
    show('share value: $'.$shareValue);
}



?>