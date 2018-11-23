<?php
// save damwidi basket details
function updateDamwidiBasket($symbol){
    saveDamwidiBasket($symbol, doesSymbolExist($symbol));
}

function doesSymbolExist($symbol){
    $dbc = connect();
    $stmt = $dbc->prepare("SELECT EXISTS(SELECT 1 FROM `data_basket` WHERE `symbol` = :symbol) AS 'exists'");
    $stmt->bindParam(':symbol', $symbol);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['exists'];
}

function viewBasketData(){
    $dbc = connect();
    $stmt = $dbc->prepare("SELECT * FROM `data_basket` ORDER BY `dateLastVisited`");
    $stmt->execute();
    $result = $stmt->fetchall(PDO::FETCH_ASSOC);
    show(json_encode($result));
}

?>