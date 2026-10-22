<?php
function bivioVerboseLog($verbose, $message, $context = null){
    Logs::verbose($verbose, $message, $context);
}

// log into bivio.com
function bivioLogin($verbose){
    if (BIVIO_USERNAME === '' || BIVIO_PASSWORD === '') {
        throw new RuntimeException('Bivio credentials are not configured');
    }

    $url = "https://www.bivio.com/pub/login";
    $ch = curl_init();
    if ($ch === FALSE) {
        throw new RuntimeException('Unable to initialize Bivio login request');
    }

    curl_setopt_array($ch, array(
        CURLOPT_URL => $url,
        CURLOPT_SSL_VERIFYPEER => TRUE,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FOLLOWLOCATION => TRUE,
        CURLOPT_USERAGENT => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/131.0 Safari/537.36",
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_COOKIEFILE => '',
        CURLOPT_COOKIESESSION => TRUE,
        CURLOPT_RETURNTRANSFER => TRUE,
        CURLOPT_HEADER => FALSE,
        CURLOPT_ENCODING => "gzip"
    ));

    try {
        bivioVerboseLog($verbose, 'Initializing Bivio login session');
        $loginPage = curl_exec($ch);
        if ($loginPage === FALSE) {
            throw new RuntimeException('Bivio login page request failed with cURL error '.curl_errno($ch));
        }
        validateBivioHttpResponse($ch, $loginPage, 'login page request');

        $loginFields = returnBivioLoginFields($loginPage);
        $loginFields['x1'] = BIVIO_USERNAME;
        $loginFields['x2'] = BIVIO_PASSWORD;

        bivioVerboseLog($verbose, 'Authenticating with Bivio');
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($loginFields));

        $loginResponse = curl_exec($ch);
        if ($loginResponse === FALSE) {
            throw new RuntimeException('Bivio login request failed with cURL error '.curl_errno($ch));
        }
        validateBivioHttpResponse($ch, $loginResponse, 'login');
        if (preg_match('/<form[^>]*action=["\']?\/pub\/login|name=["\']?x2/i', $loginResponse)) {
            throw new RuntimeException('Bivio authentication was not accepted');
        }
    } catch (Throwable $e) {
        curl_close($ch);
        throw $e;
    }

    bivioVerboseLog($verbose, 'Bivio login successful');
    return $ch;
}

function returnBivioLoginFields($response){
    if (!preg_match('/<form\b[^>]*action=["\']\/pub\/login["\'][^>]*>(.*?)<\/form>/is', $response, $formMatch)) {
        throw new RuntimeException('Bivio login form was not found');
    }

    preg_match_all('/<input\b[^>]*>/i', $formMatch[1], $inputMatches);
    $loginFields = array();
    foreach($inputMatches[0] as $input){
        if (!preg_match('/\bname=["\']([^"\']*)["\']/i', $input, $nameMatch)) continue;

        $value = '';
        if (preg_match('/\bvalue=["\']([^"\']*)["\']/i', $input, $valueMatch)) {
            $value = html_entity_decode($valueMatch[1], ENT_QUOTES, 'UTF-8');
        }
        $loginFields[$nameMatch[1]] = $value;
    }

    if (!array_key_exists('c', $loginFields) || !array_key_exists('x1', $loginFields) || !array_key_exists('x2', $loginFields)) {
        throw new RuntimeException('Bivio login form is missing required fields');
    }

    return $loginFields;
}

function validateBivioHttpResponse($ch, $response, $context){
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($httpCode < 200 || $httpCode >= 300) {
        throw new RuntimeException('Bivio '.$context.' returned HTTP status '.$httpCode);
    }

    if (!is_string($response) || trim($response) === '') {
        throw new RuntimeException('Bivio '.$context.' returned an empty response');
    }
}

// retrieve transactions from bivio.com
function retrieveBivioTransactions($ch, $verbose){
    // retrieve latest transactions
    $URL = "https://www.bivio.com/get-csv/damwidi/accounting/account/detail.csv?p=16580800007";
    curl_setopt($ch, CURLOPT_URL, $URL);
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    curl_setopt($ch, CURLOPT_HTTPGET, TRUE);
    $responseBody = curl_exec($ch);
    if ($responseBody === FALSE) {
        throw new RuntimeException('Bivio transaction download failed with cURL error '.curl_errno($ch));
    }

    validateBivioHttpResponse($ch, $responseBody, 'transaction download');

    $contentType = strtolower((string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE));
    if (strpos($contentType, 'text/html') !== FALSE || preg_match('/<html|<!doctype html/i', $responseBody)) {
        throw new RuntimeException('Bivio transaction download returned HTML instead of CSV');
    }

    $response = explode( "\n", $responseBody);
    if (trim($response[count($response)-1]) === '') {
        unset($response[count($response)-1]);  // remove last empty line
    }

    if (empty($response)) {
        throw new RuntimeException('Bivio transaction download did not contain CSV data');
    }

    foreach($response as &$responseRow){
        $responseRow = rtrim($responseRow, "\r");
    }
    unset($responseRow);

    $response[0] = preg_replace('/^\xEF\xBB\xBF/', '', $response[0]);
    $headers = str_getcsv($response[0]);
    $requiredHeaders = array('Date', 'Amount', 'Description');
    if (!empty(array_diff($requiredHeaders, $headers))) {
        throw new RuntimeException('Bivio transaction CSV is missing required columns');
    }

    if (count($response) < 2) {
        throw new RuntimeException('Bivio transaction CSV did not contain any transaction rows');
    }

    for ($i=1; $i<count($response); $i++) {
        if (count(str_getcsv($response[$i])) !== count($headers)) {
            throw new RuntimeException('Bivio transaction CSV contains an invalid row');
        }
    }

    bivioVerboseLog($verbose, 'Retrieved Bivio transaction CSV', array('rows' => count($response)-1));

    return $response;
}

// retrieve damwidi value from bivio.com
function returnBivioValue($ch, $date, $verbose = false){
    $URL = "https://www.bivio.com/damwidi/accounting/reports/valuation?date=".$date;
    curl_setopt($ch, CURLOPT_URL, $URL);
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    curl_setopt($ch, CURLOPT_HTTPGET, TRUE);
    $response = curl_exec($ch);
    if ($response === FALSE) {
        throw new RuntimeException('Bivio valuation download failed with cURL error '.curl_errno($ch));
    }

    validateBivioHttpResponse($ch, $response, 'valuation download');
    if (preg_match('/<form[^>]*action=["\']?\/pub\/login|name=["\']?x2/i', $response)) {
        throw new RuntimeException('Bivio session is not authenticated');
    }

    // create array
    $valuation['date'] = $date;

    // parse response for value
    $start = strpos($response, "Value of One Unit");
    $mid   = strpos($response, "<td class=\"b_align_e\" class=\"amount_cell\">", $start);
    $end   = strpos($response,"</td>", $mid);
    if ($start === FALSE || $mid === FALSE || $end === FALSE) {
        throw new RuntimeException('Bivio valuation response does not contain a unit value');
    }
    $data  = substr($response, $mid, $end-$mid);
    $data  = floatval(preg_replace('/[^0-9\.]/', '', $data));
    $valuation['value'] = $data;

    // parse response for units
    $start = strpos($response, "Total Number of Valuation Units to Date");
    $mid   = strpos($response, "<td class=\"b_align_e\" class=\"amount_cell\">", $start);
    $end   = strpos($response,"</td>", $mid);
    if ($start === FALSE || $mid === FALSE || $end === FALSE) {
        throw new RuntimeException('Bivio valuation response does not contain a unit count');
    }
    $data  = substr($response, $mid, $end-$mid);
    $data  = floatval(preg_replace('/[^0-9\.]/', '', $data));
    $valuation['units'] = $data;

    bivioVerboseLog($verbose, 'Retrieved Bivio valuation', $valuation);
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
    bivioVerboseLog($verbose, 'UPDATE BIVIO TRANSACTIONS');

    // store start time used to determine function duration
    $start = date('Y-m-d H:i:s');
    bivioVerboseLog($verbose, 'Bivio transaction update started', array('start' => $start));

    $ch = null;
    try {
        // open cURL session and retrieve latest transactions from Bivio
        $ch = bivioLogin($verbose);
        $bivioTransactions = retrieveBivioTransactions($ch, $verbose);

        // determine latest detail date
        $dbc = connect();
        $stmt = $dbc->prepare("SELECT `transaction_date` FROM `data_transactions` ORDER BY `transaction_date` DESC LIMIT 1");
        $stmt->execute();
        $startDate = $stmt->fetch(PDO::FETCH_ASSOC);
        $startDate = $startDate['transaction_date'];
        bivioVerboseLog($verbose, 'Latest stored transaction date', array('date' => $startDate));

        // parse CSV file
        $firstline = true;
        $recordsWritten = 0;
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

                bivioVerboseLog($verbose, 'Importing Bivio transaction', array(
                    'date' => $transaction['Date'],
                    'symbol' => $transaction['symbol'],
                    'type' => $transaction['type'],
                ));
                saveTransactionData($transaction); //update database
                $recordsWritten++;

            } else if ($firstline){
                $headers = $data;
                $firstline = false;
            }
        }
        $stmt = $dbc->prepare("ALTER TABLE `data_transactions` ORDER BY `transaction_date` ASC");
        $stmt->execute();

        $recordsRetrieved = count($bivioTransactions)-1;
        Logs::addJobResult(array(
            'records_retrieved' => $recordsRetrieved,
            'records_written' => $recordsWritten,
            'records_skipped' => $recordsRetrieved-$recordsWritten,
        ));
    } finally {
        if ($ch !== null) {
            curl_close($ch);
        }
    }

    // create notifications
    $end          = date('Y-m-d H:i:s');
    $table        = "bivio transactions";
    $notification = $end." - ".str_pad($table, 20)." - ".date('H:i:s', mktime(0, 0, strtotime($end)-strtotime($start)));

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
        $parse['type'] = 'X'; // unknown
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
