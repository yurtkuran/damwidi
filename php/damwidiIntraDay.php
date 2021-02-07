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
function returnIntraDayData($verbose, $debug, $api = false){
    $sectors = loadSectors('SIK');
    if($verbose) show('Complete Sector List:');
    if($verbose) show($sectors);

    $openPositions = returnOpenPositions(date("Y-m-d"));
    if($verbose) show('Open Positions:');
    if($verbose) show($openPositions);

    // create symbol list
    $symbols = '';
    foreach($sectors as $sector){
        $symbols .= $sector['sector'].',';
    }
    $symbols = rtrim($symbols, ','); // remove final comma

    // retrieve realtime batch quotes
    $priceData = retrieveIEXBatchData($symbols);  //saveData, verbose, debug 
    
    // create heatmap data
    $heatMapData = array();
    foreach($sectors as $sector){
        $symbol = $sector['sector'];

        $heatMapData[$sector['sector']]=array(
            "sector"        => $sector['sector'],
            "openPosition"  => $sector['shares']>0 ? true : false,
            "shares"        => $sector['shares'],
            "basis"         => $sector['basis'],
            "last"          => $priceData[$symbol]['quote']['latestPrice'],
            "currentValue"  => $sector['shares'] * $priceData[$symbol]['quote']['latestPrice'],
            "prevClose"     => $sector['previous'],
            "gain"          => calculateGain($priceData[$symbol]['quote']['latestPrice'], $priceData[$symbol]['quote']['previousClose']),
            "lastRefreshed" => date('Y-m-d h:i:s', $priceData[$symbol]['quote']['latestUpdate']/1000),
            "description"   => $sector['description']
        );
    }

    // add damwidi data
    $heatMapData = damwidiGain($heatMapData, $verbose); // calculate current & previous damwidi value

    // sort data by gain high to low
    uasort($heatMapData, function($a,$b) {return ($a['gain'] < $b['gain']) ; }); //sort desending
    if($verbose) show($heatMapData);

    $intraDayData = (object) array(
        'time'             => $heatMapData['DAM']['lastRefreshed'],
        'graphHeatMap'     => createHeatMapData($heatMapData, $verbose),
        'portfolioTable'   => createPortfolioData($heatMapData, $verbose),
        'allocationTable'  => createAllocationData($heatMapData, $verbose),
        'performanceData'  => createPerformacneData($heatMapData, $openPositions, $verbose),
        'heatMapData'      => createPortfolioData_v4($heatMapData, $verbose),
        'intraDay'         => $heatMapData
    );

    if($verbose) show($intraDayData);
    
    if($api){
        return $intraDayData;
    } else {
        if(!$verbose)echo json_encode($intraDayData);
    }
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

        $dataset['data'][$i]        = round($sectorData['gain'],2);
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
    
    // load data
    $query      = 'SELECT * FROM `data_performance` WHERE INSTR(\'CIS\', `type`) ORDER BY FIELD(`type`, "C", "I", "S"), `weight` DESC';
    $sectors    = loadSectors(null, $query);                    // load data for SPY, cash, sectors and stocks
    $stocks     = loadRawQuery('CALL stock_allocation()');      // load stock-to-sector data
    $stocksData = loadSectors('K');                             // load data for stocks
    
    // init data variables & array
    $allocationData      = array();
    $damwidiBasis        = 0;
    $summaryCurrentValue = 0;  

    // loop through sectors
    foreach($sectors as $sector => $sectorData){
        $sectorSummary     = false;
        insertIntoAllocationData($sector, $sector, $sectorData, $heatMapData, $allocationData, $damwidiBasis);

        // summary data
        $sectorSummaryData = array(
            'type'         => 'Y',
            'description'  => $sectorData['name'],
            'currentValue' => $allocationData[$sector]['currentValue'],
            'change'       => $allocationData[$sector]['change'],
            'weight'       => $sectorData['weight'],
        );
        
        // loop through stocks
        foreach($stocks as $stock){
            if($stock['sector'] == $sector) {
                $sectorSummary = true;
                $symbol        = $stock['symbol'];
                insertIntoAllocationData($symbol, $sector, $stocksData[$symbol], $heatMapData, $allocationData, $damwidiBasis);

                // update summary data
                $sectorSummaryData['currentValue'] += $allocationData[$symbol]['currentValue'];
                $sectorSummaryData['change']       += $allocationData[$symbol]['change'];
            }
        }
        
        if ($sectorSummary) insertIntoAllocationData($sector.'_Total', $sector, $sectorSummaryData, $heatMapData, $allocationData, $damwidiBasis);

        if (!$sectorSummary and $sectorData['type'] == 'S') $allocationData[$sector]['type'] = 'Y'; // convert setor only row to summary
    }

    // add DAM data
    $symbol = 'DAM';
    $allocationData[$symbol]['sector']       = $symbol;
    $allocationData[$symbol]['symbol']       = $symbol;
    $allocationData[$symbol]['description']  = "Damwidi";
    $allocationData[$symbol]['type']         = 'F';
    $allocationData[$symbol]['shares']       = damwidiShareCount;
    $allocationData[$symbol]['basis']        = $damwidiBasis;
    $allocationData[$symbol]['currentValue'] = $heatMapData[$symbol]['last'];
    $allocationData[$symbol]['change']       = $heatMapData[$symbol]['last'] - $damwidiBasis;
    $allocationData[$symbol]['allocation']   = 0;

    // format numbers
    foreach($allocationData as &$sector){
        $sector['currentValue']  = number_format($sector['currentValue'],2);
        $sector['change']        = number_format($sector['change'],2);
        $sector['allocation']    = number_format($sector['allocation'],1).'%';

        if (strpos('SKY', $sector['type'])) $sector['changePercent'] = number_format($sector['changePercent'],1).'%';

        if ($sector['type']=='S' or $sector['type']=='Y'){
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

// build data object for performance graph
function createPerformacneData($heatMapData, $positions, $verbose){
    $data        = array();
    $categories  = array();
    $seriesPrice = array();
    $seriesSPY   = array();
    $seriesDate  = array();

    // sort openpositions alphabetically
    ksort($positions, SORT_STRING);

    // loop through open positions
    foreach($positions as $position => $positionData){
        $positionBasis = loadPositionBasis($position, end($positionData['purchases']));
        $spyBasis = loadHistoricalBasis('SPY',$positionBasis['date'])['close'];

        $data[$position]['symbol']     = $position;
        $data[$position]['dateBasis']  = $positionBasis['date'];
        $data[$position]['priceBasis'] = floatval(number_format($positionBasis['price'],2,'.',''));
        $data[$position]['priceLast']  = $heatMapData[$position]['last'];
        $data[$position]['priceGain']  = round(100*($heatMapData[$position]['last']-$positionBasis['price'])/$positionBasis['price'], 2);
        $data[$position]['spyBasis']   = floatval(number_format($spyBasis,2));        
        $data[$position]['spyLast']    = $heatMapData['SPY']['last'];
        $data[$position]['spyGain']    = round(100*($heatMapData['SPY']['last']-$spyBasis)/$spyBasis, 2);

        // create array of all open purchases
        for ($i=0; $i < count($positionData['purchases']); $i++) {
            $positionBasis = loadPositionBasis($position, $positionData['purchases'][$i]);
            $spyBasis      = loadHistoricalBasis('SPY',$positionData['purchases'][$i])['close'];

            $data[$position]['purchases'][$i]['dateBasis']  = $positionData['purchases'][$i];
            $data[$position]['purchases'][$i]['priceBasis'] = floatval(number_format($positionBasis['price'],2,'.',''));
            $data[$position]['purchases'][$i]['priceGain']  = round(100*($heatMapData[$position]['last']-$positionBasis['price'])/$positionBasis['price'], 2);
            $data[$position]['purchases'][$i]['spyBasis']   = floatval(number_format($spyBasis,2));    
            $data[$position]['purchases'][$i]['spyGain']    = round(100*($heatMapData['SPY']['last']-$spyBasis)/$spyBasis, 2);
        }

        // create categories array
        array_push($categories, $position);

        // create price gain array
        array_push($seriesPrice, $data[$position]['priceGain']);

        // create spy gain array
        array_push($seriesSPY, $data[$position]['spyGain']);

        // create date array
        array_push($seriesDate, $data[$position]['dateBasis']);
    }
    // show('test');
    return array(
        'data'        => $data, 
        'categories'  => $categories, 
        'seriesPrice' => $seriesPrice, 
        'seriesSPY'   => $seriesSPY,
        'seriesDate'  => $seriesDate
    );
    
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

// create data used in the intraday portfilio table for v4 of site
function createPortfolioData_v4($heatMapData, $verbose){
    $portfolioData = array();
    foreach($heatMapData as $symbol => $data){
        if($data['shares']>0) array_push($portfolioData, $data);
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
        "sector"         => 'DAM',
        "openPosition"   => true,
        "shares"         => (float)$damwidiValue[0]['total_shares'],
        "last"           => $last,
        "currentValue"   => $last,
        "prevClose"      => (float)$damwidiValue[0]['bivio_value']*$damwidiValue[0]['total_shares'],
        "gain"           => (float)calculateGain($last, $damwidiValue[0]['bivio_value']*$damwidiValue[0]['total_shares']),
        "currShareValue" => (float)$last/$damwidiValue[0]['total_shares'],
        "prevShareValue" => $damwidiValue[0]['share_value'],
        "lastRefreshed"  => $lastRefreshed
    );

    return $heatMapData;
}

// complete the open positions portfolio table
function buildPortfolioTable(){
    $damwidiPrevious = 0;

    $query = 'SELECT * FROM `data_performance` WHERE INSTR(\'SIFK\', `type`) ORDER BY FIELD(`type`, "F", "I", "S", "K"), `sector`';
    $sectors = loadSectors(null, $query);

    foreach($sectors as $sector){
        if($sector['shares']>0){
            ?>
            <tr class=<?=($sector['sector']=='SPY' ? "rowSPY" : "")?>>
                <td class="text-center" ><?=$sector['sector']?></td>
                <td class="text-left"   ><?=trim($sector['description'])?></td>
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
    $sectors         = returnIntraDayData(false, false, true)['allocationTable'];  //verbose, debug, api
    // $sectors         = loadSectors('CIS'); // lodad cash, sectors and index (SPY) data

    foreach($sectors as $sector){

        if($sector['type'] != 'F' and ($sector['type'] != 'S' or ($sector['type'] = 'S' and $sector['shares'] >0 )) ){
            $class  = ($sector['sector']=='SPY' ? "rowSPY" : "");
            $class .= ' ' . ($sector['type']=='Y' ? "rowSummary" : "");

            ?>
            <tr class=<?= $class ?>>
                <td class="text-left" ><?=$sector['symbol']?></td>
                <td class="text-left"   ><?=trim($sector['description'])?></td>
                <td class="text-right"  id="shares<?=$sector['symbol']?>"> <?= (!strpos($sector['symbol'],'Total') ? number_format($sector['shares'],0) : '')?></td>
                <td class="text-right"  id="value<?=$sector['symbol']?>" > <?=($sector['type']=='C' ? number_format($sector['basis'],2) :'')?></td>
                <td class="text-right"  id="change<?=$sector['symbol']?>"> </td>
                <td class="text-right"  id="allocation<?=$sector['symbol']?>"></td>
                <td class="text-right"  id="weight<?= ($sector['type'] != 'S' ? $sector['symbol'] : '') ?>"> </td>
                <td class="text-right"  id="implied<?= ($sector['type'] != 'S' ? $sector['symbol'] : '') ?>"></td>
                <td class="text-right"  id="impliedOverUnder<?= ($sector['type'] != 'S' ? $sector['symbol'] : '') ?>"></td>
            </tr>
            <?php
        }

    }

    ?>
    <tr class="rowDAM">
        <td class="text-left" >DAM</td>
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

// add data to allocationData array
function insertIntoAllocationData($symbol, $sector, $data, $heatMapData, & $allocationData, & $damwidiBasis){
    $allocationData[$symbol]['symbol']        = $symbol;
    $allocationData[$symbol]['sector']        = $sector;
    $allocationData[$symbol]['description']   = $data['description'];
    $allocationData[$symbol]['type']          = $data['type'];
    
    if ($data['type'] == 'C') { //cash
        $allocationData[$symbol]['last']          = $data['basis'];
        $allocationData[$symbol]['currentValue']  = $data['basis'];
        $allocationData[$symbol]['basis']         = $data['basis'];
        $allocationData[$symbol]['allocation']    = ($allocationData[$symbol]['currentValue'] / $heatMapData['DAM']['last'])*100;
        $allocationData[$symbol]['shares']        = 1;
        $allocationData[$symbol]['change']        = 0;
        $damwidiBasis                            += $data['shares'] * $data['basis'];
    } else if ($data['type'] == 'S' or $data['type'] == 'K' or $data['type'] == 'I') {
        $allocationData[$symbol]['last']          = $heatMapData[$symbol]['last'];
        $allocationData[$symbol]['currentValue']  = $data['shares'] * $heatMapData[$symbol]['last'];
        $allocationData[$symbol]['allocation']    = (($data['shares'] * $heatMapData[$symbol]['last']) / $heatMapData['DAM']['last'])*100;
        $allocationData[$symbol]['shares']        = (int)   $data['shares'];
        $allocationData[$symbol]['basis']         = (float) $data['basis'];
        $allocationData[$symbol]['change']        = $data['shares'] * ($heatMapData[$symbol]['last'] - $data['basis']);
        $allocationData[$symbol]['changePercent'] = ($data['shares'] ? ($heatMapData[$symbol]['last'] / $allocationData[$symbol]['basis'] - 1)*100 : 0);
        $damwidiBasis                            += $data['shares'] * $data['basis'];
    } else if ($data['type'] == 'Y') {
        $allocationData[$symbol]['currentValue']  = $data['currentValue'];
        $allocationData[$symbol]['allocation']    = ($data['currentValue'] / $heatMapData['DAM']['last'])*100;
        $allocationData[$symbol]['shares']        = null;
        $allocationData[$symbol]['change']        = $data['change'];
        $allocationData[$symbol]['changePercent'] = ($data['change']/($data['currentValue']  - $data['change']))*100;
    } 

    if ($data['type'] == 'S' or $data['type'] == 'Y') {
        $allocationData[$symbol]['weight']                  = $data['weight']/100 * $heatMapData['DAM']['last'];
        $allocationData[$symbol]['weightPercent']           = (float) $data['weight'];
        $allocationData[$symbol]['actualOverUnderPercent']  = $allocationData[$symbol]['allocation'] - $data['weight'];
        $allocationData[$symbol]['implied']                 = ($heatMapData['SPY']['currentValue'] * ($data['weight']/100) + $allocationData[$symbol]['currentValue']);
        $allocationData[$symbol]['impliedPercent']          = (($heatMapData['SPY']['currentValue'] * ($data['weight']/100) + $allocationData[$symbol]['currentValue'])/$heatMapData['DAM']['last'])*100;
        $allocationData[$symbol]['impliedOverUnder']        = $allocationData[$symbol]['implied'] - $data['weight']/100 * $heatMapData['DAM']['last'];
        $allocationData[$symbol]['impliedOverUnderPercent'] = $allocationData[$symbol]['impliedPercent'] - $data['weight'];
    }
}

?>