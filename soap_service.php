<?php
ini_set("soap.wsdl_cache_enabled", 0);

// Link the config params
require_once ("lib/default_conf.php");

setSystemTimeZone();

error_reporting(0);
try {
    // Init connection with WS-API
    LinkcareSoapAPI::setEndpoint($GLOBALS["WS_LINK"]);
    LinkcareSoapAPI::session_init($GLOBALS['SERVICE_USER'], $GLOBALS['SERVICE_PASSWORD']);

    // Initialize SOAP Server
    $server = new SoapServer("soap_service.wsdl");
    $server->addFunction("update_activity");
    $server->handle();
} catch (APIException $e) {
    service_log($e->getMessage());
} catch (Exception $e) {
    service_log($e->getMessage());
}

/**
 * ******************************** SOAP FUNCTIONS *********************************
 */
function update_activity($task, $date_to) {
    try {
        updatePatientActivity($task, $date_to);
    } catch (APIException $e) {
        return ['result' => '', 'ErrorMsg' => $e->getMessage()];
    } catch (Exception $e) {
        return ['result' => '', 'ErrorMsg' => $e->getMessage()];
    }
}

