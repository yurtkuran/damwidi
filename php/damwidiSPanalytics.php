<?php   

// alter TABLE sp_holdings ADD COLUMN id int not null AUTO_INCREMENT PRIMARY KEY FIRST

function updateSPKeyData($verbose, $debug){
    $holdings = loadRawQuery('SELECT symbol FROM `sp_holdings` WHERE 1');   // load S&P 500 holdings

    // loop through all holdings in S&P 500
    foreach($holdings as $holding){
        if ($verbose) show($holding['symbol']);

        // create URL to retrieve key data from IEX
        $symbol = $holding['symbol'];
        $URL  = iexURL;
        $URL .= 'stock/'.$symbol.'/stats';
        $URL .= '?token='.iexPK;
        if ($verbose) show($URL);

        //retrieve data
        $json = file_get_contents($URL);      
        $keyData = json_decode($json,1);

        // save to database
        saveSPKeyData($symbol, $keyData);

        if($debug) break;
    }
}

?>