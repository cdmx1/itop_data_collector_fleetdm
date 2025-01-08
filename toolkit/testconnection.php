<?php

define('APPROOT', dirname(__FILE__).'/../');
require_once APPROOT.'core/parameters.class.inc.php';
require_once APPROOT.'core/utils.class.inc.php';
require_once APPROOT.'core/restclient.class.inc.php';

echo '    curl_init exists: '.function_exists('curl_init').PHP_EOL;

try {
    Utils::InitConsoleLogLevel();

    $oRestClient = new RestClient();
    var_dump($oRestClient->ListOperations());
    echo 'Calling iTop Rest API worked!'.PHP_EOL;
    exit(0);
} catch (Exception $e) {
    echo $e->getMessage().PHP_EOL;
    exit(1);
}
