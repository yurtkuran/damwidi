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

// send SMS using IFTTT
function sendSMS($message, $date){
    $URL = "https://maker.ifttt.com/trigger/unstick/with/key/d6zvjEE7-153bAKOWuuJej";
    $postinfo = array(
        'value1' => $message,
        'value2' => $date
    );
    $postinfo = json_encode($postinfo);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $URL);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postinfo);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    curl_exec($ch);
}

?>