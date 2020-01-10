<?php

// return details 1) sector weights fetch and and effective dates 2) position data as-of date
function returnDetails($verbose, $debug){
    $dbc = connect();
    $stmt = $dbc->prepare("SELECT `transaction_date` FROM `data_transactions` ORDER BY `transaction_date` DESC LIMIT 1");
    $stmt->execute();
    $openPositionData = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $dbc->prepare("SELECT `effectiveDate`, `fetchedDate` FROM `data_performance` WHERE TYPE = 'S' LIMIT 1");
    $stmt->execute();
    $weightData = $stmt->fetch(PDO::FETCH_ASSOC);

    $data = array(
        'openPositionData' => $openPositionData['transaction_date'],
        'effectiveDate'    => $weightData['effectiveDate'],
        'fetchedDate'      => $weightData['fetchedDate'],
    );
    echo json_encode($data);
}

// return IntraDay data
function returnIntraDayData($verbose, $debug){
    $sectors = loadSectors('SI');
    if($verbose) show($sectors);

    $openPositions = returnOpenPositions(date("Y-m-d"));
    if($verbose) show($openPositions);

    // create symbol list
    $symbols = '';
    foreach($sectors as $sector){
        $symbols .= $sector['sector'].',';
    }
    $symbols = rtrim($symbols, ','); // remove final comma

    // retrieve realtime batch quotes
    $aplhaVantageData = retrieveBatchDataAlpha($symbols, true); // loadNewData, saveData, verbose, debug
    $priceData        = $aplhaVantageData['seriesData'];

    //add data for SPY and sectors
    $heatMapData = array();
    foreach($sectors as $sector){
        $heatMapData[$sector['sector']]=array(
            "sector"        => $sector['sector'],
            "openPosition"  => $sector['shares']>0 ? true : false,
            "shares"        => $sector['shares'],
            "basis"         => $sector['basis'],
            "last"          => $priceData[$sector['sector']]['price'],
            "currentValue"  => $sector['shares'] * $priceData[$sector['sector']]['price'],
            "prevClose"     => $sector['previous'],
            "gain"          => calculateGain($priceData[$sector['sector']]['price'], $sector['previous']),
            "lastRefreshed" => $priceData[$sector['sector']]['lastRefreshed'],
        );
    }

    // add data for individual stocks
    foreach($openPositions as $symbol => $data){
        if(!array_key_exists($symbol,$heatMapData)){
            $iexData = retrieveIEXBatchData($symbol);
            $heatMapData[$symbol]=array(
                "sector"        => $symbol,
                "openPosition"  => true,
                "shares"        => $data['shares'],
                "basis"         => $data['basis'],
                "last"          => $iexData[$symbol]['quote']['latestPrice'],
                "currentValue"  => $data['shares'] * $iexData[$symbol]['quote']['latestPrice'],
                "prevClose"     => $iexData[$symbol]['quote']['previousClose'],
                "gain"          => calculateGain($iexData[$symbol]['quote']['latestPrice'], $iexData[$symbol]['quote']['previousClose']),
                "lastRefreshed" => date('Y-m-d h:i:s', $iexData[$symbol]['quote']['latestUpdate']/1000),
            );
        }
    }


    // add damwidi data
    $heatMapData = damwidiGain($heatMapData, $verbose); // calculate current & previous damwidi value

    // sort data by gain high to low
    uasort($heatMapData, function($a,$b) {return ($a['gain'] < $b['gain']) ; }); //sort desending
    if($verbose) show($heatMapData);

    $intraDayData = array(
        'time'            => $heatMapData['DAM']['lastRefreshed'],
        'graphHeatMap'    => createHeatMapData($heatMapData, $verbose),
        'portfolioTable'  => createPortfolioData($heatMapData, $verbose),
        'allocationTable' => createAllocationData($heatMapData, $verbose),
        'intraDay'        => $heatMapData
    );

    if($verbose) show($intraDayData);
    if(!$verbose)echo json_encode($intraDayData);

}

// create chart.js labels and data
function createHeatMapData($heatMapData, $verbose){
    $i = 0;

    $labels  = array();
    $dataset = array(
        'data'            => [],
        'backgroundColor' => [],
        'borderColor'     => [],
        'borderWidth'     => [],
    );

    foreach($heatMapData as $sector => $sectorData){
        $labels[$i] = $sector;

        $dataset['data'][$i]        = $sectorData['gain'];
        $dataset['borderWidth'][$i] = 1;

        if($sectorData['gain'] < 0) {
            $dataset['backgroundColor'][$i] = 'rgba(196, 33, 27, '. ($sectorData['openPosition'] ? 1 : 0.2 ) .')' ;
            $dataset['borderColor'][$i]     = 'rgba(196, 33, 27, 1)' ;
        } else {
            $dataset['backgroundColor'][$i] = 'rgba(29, 170, 90, '. ($sectorData['openPosition'] ? 1 : 0.2 ) .')' ;
            $dataset['borderColor'][$i]     = 'rgba(29, 170, 90, 1)' ;
        }

        if($sector == 'DAM') {
            $dataset['borderColor'][$i]     = 'rgba(00, 00, 00, 1)';
            $dataset['borderWidth'][$i]     = 2;
        }

        $i++;
    }

    return array(
        'labels'   => $labels,
        'datasets' => [$dataset]
    );
}

function createAllocationData($heatMapData, $verbose){

    $sectors        = loadSectors('SIC'); // load data for SPY, Cash and sectors
    $allocationData = array();
    $damwidiBasis   = 0;

    foreach($sectors as $sectorData){
        $sector = $sectorData['sector'];

        $allocationData[$sector]['sector']       = $sector;
        $allocationData[$sector]['description']  = $sectorData['Description'];
        $allocationData[$sector]['type']         = $sectorData['type'];
        $allocationData[$sector]['shares']       = $sectorData['shares'];
        $allocationData[$sector]['basis']        = $sectorData['basis'];
        $damwidiBasis                           += $sectorData['shares'] * $sectorData['basis'];

        if ($sectorData['type'] == 'C') {
            $allocationData[$sector]['last']          = $sectorData['basis'];
            $allocationData[$sector]['currentValue']  = $sectorData['basis'];
            $allocationData[$sector]['allocation']    = ($allocationData[$sector]['currentValue'] / $heatMapData['DAM']['last'])*100;
        } else {
            $allocationData[$sector]['last']          = $heatMapData[$sector]['last'];
            $allocationData[$sector]['currentValue']  = $sectorData['shares'] * $heatMapData[$sector]['last'];
            $allocationData[$sector]['allocation']    = (($sectorData['shares'] * $heatMapData[$sector]['last']) / $heatMapData['DAM']['last'])*100;
        }

        $allocationData[$sector]['change']          = $sectorData['shares'] * ($allocationData[$sector]['last'] - $sectorData['basis']);
        $allocationData[$sector]['changePercent']   = ($sectorData['shares'] ? ($allocationData[$sector]['last'] / $allocationData[$sector]['basis'] - 1)*100 : 0);

        if ($sectorData['type']=='S') {
            $allocationData[$sector]['weight']                  = $sectorData['weight']/100 * $heatMapData['DAM']['last'];
            $allocationData[$sector]['weightPercent']           = $sectorData['weight'];
            $allocationData[$sector]['actualOverUnderPercent']  = $allocationData[$sector]['allocation'] - $sectorData['weight'];
            $allocationData[$sector]['implied']                 = ($heatMapData['SPY']['currentValue'] * ($sectorData['weight']/100) + $allocationData[$sector]['currentValue']);
            $allocationData[$sector]['impliedPercent']          = (($heatMapData['SPY']['currentValue'] * ($sectorData['weight']/100) + $allocationData[$sector]['currentValue'])/$heatMapData['DAM']['last'])*100;
            $allocationData[$sector]['impliedOverUnder']        = $allocationData[$sector]['implied'] - $sectorData['weight']/100 * $heatMapData['DAM']['last'];
            $allocationData[$sector]['impliedOverUnderPercent'] = $allocationData[$sector]['impliedPercent'] - $sectorData['weight'];
        }
    }
    // add DAM data
    $sector = 'DAM';
    $allocationData[$sector]['sector']       = $sector;
    $allocationData[$sector]['description']  = "Damwidi";
    $allocationData[$sector]['type']         = 'F';
    $allocationData[$sector]['shares']       = damwidiShareCount;
    $allocationData[$sector]['basis']        = $damwidiBasis;
    $allocationData[$sector]['currentValue'] = $heatMapData[$sector]['last'];
    $allocationData[$sector]['change']       = $heatMapData[$sector]['last'] - $damwidiBasis;
    $allocationData[$sector]['allocation']   = 0;

    // format numbers
    foreach($allocationData as &$sector){
        $sector['currentValue'] = number_format($sector['currentValue'],2);
        $sector['change']       = number_format($sector['change'],2);
        $sector['allocation']   = number_format($sector['allocation'],1).'%';

        if ($sector['type']=='S'){
            $sector['weight']                  = number_format($sector['weight'],0);
            $sector['weightPercent']           = number_format($sector['weightPercent'],1).'%';
            $sector['actualOverUnderPercent']  = number_format($sector['actualOverUnderPercent'],1).'%';
            $sector['implied']                 = number_format($sector['implied'],0);
            $sector['impliedPercent']          = number_format($sector['impliedPercent'],1).'%';
            $sector['impliedOverUnder']        = number_format($sector['impliedOverUnder'],0);
            $sector['impliedOverUnderPercent'] = number_format($sector['impliedOverUnderPercent'],1).'%';
        }
    }

    return $allocationData;
}

// create data used in the intraday portfilio table
function createPortfolioData($heatMapData, $verbose){
    $portfolioData = array();
    foreach($heatMapData as $sector => $sectorData){
        if($sectorData['shares']>0 ){
            $portfolioData[$sector]['sector']        = $sector;
            $portfolioData[$sector]['last']          = number_format($sectorData['last'],2);
            $portfolioData[$sector]['change']        = number_format(abs($sectorData['last']-$sectorData['prevClose']),2);
            $portfolioData[$sector]['changePercent'] = number_format(abs(100*($sectorData['last']-$sectorData['prevClose'])/$sectorData['prevClose']),2);

            if ($sector <> 'DAM') {
                $portfolioData[$sector]['value']         = number_format($sectorData['last']*$sectorData['shares'],2);
                $portfolioData[$sector]['valueChange']   = number_format($portfolioData[$sector]['change']*$sectorData['shares'],2);
            } else {
                $portfolioData[$sector]['value']         = number_format($sectorData['last'],2);
                $portfolioData[$sector]['valueChange']   = $portfolioData[$sector]['change'];
            }

            // used to set the css styling in the portfolion table
            switch (true) {
                case ($sectorData['gain'] > 0):
                    $portfolioData[$sector]['tick'] = 'UP';
                    break;
                case ($sectorData['gain'] < 0):
                    $portfolioData[$sector]['tick'] = 'DOWN';
                    break;
                default:
                    $portfolioData[$sector]['tick'] = 'ZERO';
            }
        }
    }
    return $portfolioData;
}

// calculate and return DAM gain
function damwidiGain($heatMapData, $verbose){

    $lastRefreshed = '';
    $last = loadSectors('C')['CASH']['basis'];  //load cash
    foreach ($heatMapData as $sector){          //loop through sectors, add open positions (shares*lastQuote)
        if ($sector['shares']){
            $last += $sector['shares'] * $sector['last'];
        }
        $lastRefreshed = ( $lastRefreshed < $sector['lastRefreshed'] ? $sector['lastRefreshed'] : $lastRefreshed );
    }

    $damwidiValue = loadDamdidiValue(1);

    $heatMapData['DAM'] = array(
        "sector"        => 'DAM',
        "openPosition"  => true,
        "shares"        => damwidiShareCount,
        "last"          => $last,
        "currentValue"  => $last,
        "prevClose"     => $damwidiValue[0]['bivio_value']*damwidiShareCount,
        "gain"          => calculateGain($last, $damwidiValue[0]['bivio_value']*damwidiShareCount),
        "lastRefreshed" => $lastRefreshed
    );

    return $heatMapData;
}

// complete the open positions portfolio table
function buildPortfolioTable(){
    $damwidiPrevious = 0;
    $sectors = loadSectors('CIS'); // lodad cash, sectors and index (SPY) data
    foreach($sectors as $sector){
        if($sector['shares']>0){
            ?>
            <tr class=<?=($sector['sector']=='SPY' ? "rowSPY" : "")?>>
                <td class="text-center" ><?=$sector['sector']?></td>
                <td class="text-left"   ><?=trim($sector['Description'])?></td>
                <td class="text-right"  ><?=number_format($sector['basis'],2)?> </td>
                <td class="text-right"  ><?=$sector['shares']?> </td>
                <td class="text-right"  ><?=number_format($sector['previous'],2)?> </td>
                <td class="text-right"  id="last<?=$sector['sector']?>"></td>
                <td class="text-center" id="change<?=$sector['sector']?>"></td>
                <td class="text-right"  id="value<?=$sector['sector']?>"> <?=($sector['sector']=='CASH' ? number_format($sector['previous'],2) : "")?></td>
                <td class="text-right"  id="valueChange<?=$sector['sector']?>"></td>
            </tr>
            <?php
            $damwidiPrevious += $sector['shares'] * $sector['previous'];
        }
    }

    $openPositions = returnOpenPositions(date("Y-m-d"));
    foreach($openPositions as $symbol => $data){
        if(array_search($symbol, array_column(loadSectors('CIS'), 'sector')) === FALSE){
            $iexData = retrieveIEXBatchData($symbol);
            ?>
            <tr class=<?=($sector['sector']=='SPY' ? "rowSPY" : "")?>>
                <td class="text-center" ><?=$symbol?></td>
                <td class="text-left"   ><?=$iexData[$symbol]['quote']['companyName']?></td>
                <td class="text-right"  ><?=number_format($data['basis'],2)?> </td>
                <td class="text-right"  ><?=$data['shares']?> </td>
                <td class="text-right"  ><?=number_format($iexData[$symbol]['quote']['previousClose'],2)?> </td>
                <td class="text-right"  id="last<?=$symbol?>"></td>
                <td class="text-center" id="change<?=$symbol?>"></td>
                <td class="text-right"  id="value<?=$symbol?>"> <?=($symbol=='CASH' ? number_format($sector['previous'],2) : "")?></td>
                <td class="text-right"  id="valueChange<?=$symbol?>"></td>
            </tr>
            <?php
            $damwidiPrevious += $data['shares'] * $iexData[$symbol]['quote']['previousClose'];
        }
    }

    ?>
    <tr class="rowDAM">
        <td class="text-center" >DAM</td>
        <td>Total</td>
        <td></td>
        <td></td>
        <td class="text-right" > <?=number_format($damwidiPrevious,2,'.',',')?> </td>
        <td></td>
        <td class="text-center" id="changeDAM"></td>
        <td class="text-right"  id="valueDAM"></td>
        <td class="text-right"  id="valueChangeDAM"></td>
    </tr>
    <?php
}

// complete the allocation table
function buildAllocationTable(){
    $damwidiPrevious = 0;
    $damwidiBasis    = 0;
    $sectors         = loadSectors('CIS'); // lodad cash, sectors and index (SPY) data
    $openPositions   = returnOpenPositions(date("Y-m-d")); // retrieve all open positions 

    foreach($sectors as $sector){
        ?>
        <tr class=<?=($sector['sector']=='SPY' ? "rowSPY" : "")?>>
            <td class="text-center" ><?=$sector['sector']?></td>
            <td class="text-left"   ><?=trim($sector['Description'])?></td>
            <td class="text-right"  id="shares<?=$sector['sector']?>"> <?= number_format($sector['shares'],0)?></td>
            <td class="text-right"  id="value<?=$sector['sector']?>" > <?=($sector['type']=='C' ? number_format($sector['basis'],2) :'')?></td>
            <td class="text-right"  id="change<?=$sector['sector']?>"> </td>
            <td class="text-right"  id="allocation<?=$sector['sector']?>"></td>
            <td class="text-right"  id="weight<?=$sector['sector']?>"> </td>
            <td class="text-right"  id="implied<?=$sector['sector']?>"></td>
            <td class="text-right"  id="impliedOverUnder<?=$sector['sector']?>"></td>
        </tr>
        <?php
        $damwidiPrevious += $sector['shares'] * $sector['previous'];
        $damwidiBasis    += $sector['shares'] * $sector['basis'];
    }
    
    foreach($openPositions as $symbol => $data){
        if(!array_key_exists($symbol, $sectors)){
            ?>
            <tr class=''>
                <td class="text-center" ><?=$symbol?></td>
            </tr>
            <?php
        }
    }
    die();


    ?>
    <tr class="rowDAM">
        <td class="text-center" >DAM</td>
        <td class="text-left"   >Total</td>
        <td class="text-right"  > </td>
        <td class="text-right" id="valueDAM"> </td>
        <td class="text-right"  > </td>
        <td class="text-center" > </td>
        <td class="text-center" > </td>
        <td class="text-center" > </td>
        <td class="text-center" > </td>
    </tr>
    <?php
}
?>