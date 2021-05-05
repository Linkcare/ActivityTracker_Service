<?php

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
            // Connect as service user
            apiConnect(null, $GLOBALS['SERVICE_USER'], $GLOBALS['SERVICE_PASSWORD'], 47, $GLOBALS['SERVICE_TEAM']);
            $options['errorCode'] = $_POST["error_code"];
            $options['errorDescription'] = $_POST["error"];
            $options['taskId'] = $_POST["task"];
            $options['access_token'] = $_POST["access_token"];
            $options['refresh_token'] = $_POST["refresh_token"];
            $options['expiration'] = $_POST["exp"];
            $r = new FitbitResource($options);
            $lc2Action = storeAuthorization($r);
            break;
    }
} catch (APIException $e) {
    $lc2Action = new LC2Action(LC2Action::ACTION_ERROR_MSG);
    $lc2Action->setErrorMessage($e->getMessage());
} catch (Exception $e) {
    $lc2Action = new LC2Action(LC2Action::ACTION_ERROR_MSG);
    $lc2Action->setErrorMessage($e->getMessage());
    service_log("ERROR: " . $e->getMessage());
}

header('Content-type: application/json');
echo $lc2Action->toString();

