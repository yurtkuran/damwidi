<?php

// show value DB records where the calculated share value does not equal the bivio share value
function bivioUnstick() {
    $dbc = connect();
    $stmt = $dbc->prepare("SELECT `date`, `share_value`, `bivio_value` FROM `data_value` WHERE `share_value` <> `bivio_value` ORDER BY `date` DESC");
    $stmt->execute();
    show($stmt->fetchall(PDO::FETCH_ASSOC));
}

// display contents of unstick log
function displayUnstickLog(){
    $filename = UNSTICK_LOG;

    if(file_exists($filename)){
        if(filesize($filename)){
            $file    = fopen($filename, 'r');
            $dataLog = fread($file, filesize($filename));
            $dataLog = json_decode($dataLog, true);
            show('unstick datalog as of: '.date ("Y-d-m H:i:s", filemtime($filename)).', filesize: '.filesize($filename));
            show($dataLog);
            fclose($file);
        } else {
            show('unstick datalog empty');
        }
    } else {
        show('unstick file does not exist');
    }
}

// return contents on unstick data log
function returnUnstickLogData(){
    $filename = UNSTICK_LOG;
    $dataLog  = array();

    if(file_exists($filename)){
        if(filesize($filename)){
            $file    = fopen($filename, 'r');
            $dataLog = fread($file, filesize($filename));
            $dataLog = json_decode($dataLog, true);
            fclose($file);
        }
    }
    return $dataLog;
}

// send a notification using the configured notification provider
function sendNotification($message, $type = 'normal', $title = 'Damwidi'){
    return sendPushoverNotification($message, $title, $type);
}

// send a notification using the Pushover Messages API
function sendPushoverNotification($message, $title = 'Damwidi', $type = 'normal'){
    $message = trim((string)$message);
    $title = trim((string)$title);
    $type = strtolower(trim((string)$type));
    $sounds = array(
        'normal' => 'bike',
        'warning' => 'tugboat',
        'success' => 'magic',
        'failure' => 'siren',
    );

    if ($message === '') {
        throw new InvalidArgumentException('Pushover notification message cannot be empty');
    }
    if (strlen($message) > 1024) {
        throw new InvalidArgumentException('Pushover notification message cannot exceed 1024 bytes');
    }
    if (strlen($title) > 250) {
        throw new InvalidArgumentException('Pushover notification title cannot exceed 250 bytes');
    }
    if (!isset($sounds[$type])) {
        throw new InvalidArgumentException('Unsupported notification type: '.$type);
    }

    $userKey = readSecretFromEnvironment('PUSHOVER_USER_KEY');
    $applicationToken = readSecretFromEnvironment('PUSHOVER_DAMWIDI_APP_TOKEN');
    $ch = curl_init();
    if ($ch === false) {
        throw new RuntimeException('Unable to initialize Pushover notification request');
    }

    curl_setopt_array($ch, array(
        CURLOPT_URL => 'https://api.pushover.net/1/messages.json',
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(array(
            'token' => $applicationToken,
            'user' => $userKey,
            'title' => $title,
            'message' => $message,
            'sound' => $sounds[$type],
        )),
        CURLOPT_HTTPHEADER => array('Content-Type: application/x-www-form-urlencoded'),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => false,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ));

    try {
        $responseBody = curl_exec($ch);
        $curlErrorNumber = curl_errno($ch);
        $httpStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    } finally {
        curl_close($ch);
    }

    if ($responseBody === false) {
        throw new RuntimeException('Pushover notification request failed with cURL error '.$curlErrorNumber);
    }

    $response = json_decode($responseBody, true);
    if (!is_array($response)) {
        throw new RuntimeException('Pushover notification returned an invalid JSON response');
    }

    if ($httpStatus !== 200 || !isset($response['status']) || (int)$response['status'] !== 1) {
        $errors = isset($response['errors']) && is_array($response['errors'])
            ? ': '.implode('; ', $response['errors'])
            : '';
        throw new RuntimeException('Pushover notification was rejected'.$errors);
    }

    return array(
        'status' => (int)$response['status'],
        'request' => isset($response['request']) ? $response['request'] : null,
    );
}

function readSecretFromEnvironment($variableName){
    $secret = getenv($variableName);
    if ($secret === false || trim($secret) === '') {
        throw new RuntimeException($variableName.' is not configured');
    }

    return trim($secret);
}

?>
