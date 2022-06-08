<?php
error_reporting(E_ERROR); // Do not report warnings to avoid undesired characters in output stream

// Link the config params
require_once ("lib/default_conf.php");

setSystemTimeZone();

error_reporting(0);

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    echo '';
    exit(0);
}

$action = $_POST['do'];

$lc2Action = new LC2Action(LC2Action::ACTION_ERROR_MSG);
$lc2Action->setErrorMessage('No action executed');

try {

    // apiConnect($_POST["token"]);
    switch ($action) {
        case 'authorize' :
            // Connect as service user, reusing existing session if possible
            apiConnect(null, $GLOBALS['SERVICE_USER'], $GLOBALS['SERVICE_PASSWORD'], 47, $GLOBALS['SERVICE_TEAM'], true);
            $options['errorCode'] = $_POST["error_code"];
            $options['errorDescription'] = $_POST["error"];
            $options['taskId'] = $_POST["task"];
            $options['access_token'] = $_POST["access_token"];
            $options['refresh_token'] = $_POST["refresh_token"];
            $options['expiration'] = $_POST["exp"];
            log_trace("AUTHORIZATION RESPONSE: " . json_encode($options));

            $scope = $_POST["scope"];

            $provider = ActivityProvider::getInstance(ActivityProvider::PROVIDER_FITBIT);
            $r = new OauthResource($options, $provider);
            $lc2Action = storeAuthorization($r, $scope);
            break;
    }
} catch (APIException $e) {
    log_trace("ERROR: " . $e->getMessage());
    $lc2Action = new LC2Action(LC2Action::ACTION_ERROR_MSG);
    $lc2Action->setErrorMessage($e->getMessage());
} catch (Exception $e) {
    log_trace("ERROR: " . $e->getMessage());
    $lc2Action = new LC2Action(LC2Action::ACTION_ERROR_MSG);
    $lc2Action->setErrorMessage($e->getMessage());
    service_log("ERROR: " . $e->getMessage());
}

header('Content-type: application/json');
echo $lc2Action->toString();

