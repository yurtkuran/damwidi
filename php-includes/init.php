<?php
date_default_timezone_set('America/New_York');

// error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);
// ini_set('display_errors', false); // Error display
// ini_set('log_errors', TRUE); // Error logging
// ini_set("error_log", "./tmp/php-error.log");

include_once('./php-includes/functions.php');
include_once('./php-includes/functions_db.php');
include_once('./php-includes/definedConstants.php');

include_once('./php/damwidiHistory.php');
include_once('./php/damwidiIntraDay.php');
include_once('./php/damwidiPerformance.php');
include_once('./php/damwidiScrapeBivio.php');
include_once('./php/damwidiSectorWeights.php');
include_once('./php/damwidiUpdateValue.php');
include_once('./php/damwidiUtilities.php');

include_once('./php/dataHandlerAlphaVantage.php');
include_once('./php/dataHandlerBarChart.php');
include_once('./php/dataHandlerHistorical.php');
include_once('./php/dataHandlerMySQL.php');
include_once('./php/dataHandlerTradier.php');
?>