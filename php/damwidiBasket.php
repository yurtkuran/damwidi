<?php
// save damwidi basket details
function updateDamwidiBasket($symbol, $description){
    saveDamwidiBasket($symbol, $description, doesSymbolExist($symbol));
}

function doesSymbolExist($symbol){
    $dbc = connect();
    $stmt = $dbc->prepare("SELECT EXISTS(SELECT 1 FROM `data_basket` WHERE `symbol` = :symbol) AS 'exists'");
    $stmt->bindParam(':symbol', $symbol);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['exists'];
}

function returnBasket($verbose, $debug){
    $dbc = connect();
    $stmt = $dbc->prepare("SELECT * FROM `data_basket` ORDER BY `dateLastVisited`");
    $stmt->execute();
    $result = $stmt->fetchall(PDO::FETCH_ASSOC);

    if($verbose) show($result);
    if(!$verbose) echo json_encode(array('data' => $result));
}

function updateBasketDescriptions($verbose, $debug) {
    $symbols = loadDamwidiBasket();
    foreach($symbols as $symbol){
        $companyData = retrieveIEXCompanyData($symbol['symbol']);
        saveDamwidiBasket($symbol['symbol'], $companyData['companyName'], TRUE);
        if($verbose) show($symbol['symbol'].' '.$companyData['companyName']);
    }
}

?>