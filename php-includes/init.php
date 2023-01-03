<?php
include_once 'globals.php';

ini_set('max_execution_time', 1200); // increase execution time
date_default_timezone_set('America/New_York');

ini_set('display_errors', 1);
error_reporting(E_ALL);

// error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);
// ini_set('display_errors', false); // Error display
// ini_set('log_errors', TRUE); // Error logging
// ini_set("error_log", "./tmp/php-error.log");

include_once 'php-includes/functions.php';
include_once 'php-includes/functions_db.php';
include_once 'php-includes/definedConstants.php';

include_once 'php/damwidiAboveBelow.php';
include_once 'php/damwidiBasket.php';
include_once 'php/damwidiHistory.php';
include_once 'php/damwidiIntraDay.php';
include_once 'php/damwidiPerformance.php';
include_once 'php/damwidiScrapeBivio.php';
include_once 'php/damwidiSectorWeights.php';
include_once 'php/damwidiSPanalytics.php';
include_once 'php/damwidiUpdateValue.php';
include_once 'php/damwidiUtilities.php';

include_once 'php/dataHandlerAlphaVantage.php';
include_once 'php/dataHandlerBarChart.php';
include_once 'php/dataHandlerEodHistorical.php';
include_once 'php/dataHandlerFinnhub.php';
include_once 'php/dataHandlerHistorical.php';
include_once 'php/dataHandlerIEX.php';
include_once 'php/dataHandlerMySQL.php';
include_once 'php/dataHandlerTradier.php';
include_once 'php/dataHandlerAWS.php';

// load AirTable API wrapper
// https://github.com/sleiman/airtable-php
include_once 'php-includes/Airtable.php';
include_once 'php-includes/Request.php';
include_once 'php-includes/Response.php';

require "vendor/autoload.php";
// ?>