<?php
ini_set("soap.wsdl_cache_enabled", 0);

// Link the config params
require_once ("lib/default_conf.php");

setSystemTimeZone();

error_reporting(0);
try {
    $server = new SoapServer("soap_service.wsdl");
    $server->addFunction("update_activity");
    $server->handle();
} catch (Exception $e) {
    service_log($e->getMessage());
}
