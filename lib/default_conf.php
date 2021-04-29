<?php
session_start();
require_once __DIR__ . '/../vendor/autoload.php';
require_once ("utils.php");
require_once ("WSAPI/WSAPI.php");
require_once ("FitbitAPI/FitbitAPI.php");
require_once "classes/LC2Action.php";
require_once "classes/FitbitResource.php";
require_once "functions.php";
require_once "fitbit_functions.php";

$GLOBALS["LANG"] = "EN";
$GLOBALS["DEFAULT_TIMEZONE"] = "Europe/Madrid";

// Url of the WS-API and LC2
$GLOBALS["WS_LINK"] = "https://dev-api.linkcareapp.com/ServerWSDL.php";
$GLOBALS["LC2_LINK"] = "https://dev.linkcareapp.com/apiservice/activity_tracker";

// Fitbit credentials
$GLOBALS['FITBIT_CLIENT_ID'] = 'client_key';
$GLOBALS['FITBIT_CLIENT_SECRET'] = 'client_secret';
$GLOBALS['FITBIT_REDIRECT_URI'] = 'redirect_uri';

// Load particular configuration
if (file_exists(__DIR__ . '/../conf/configuration.php')) {
    include_once __DIR__ . '/../conf/configuration.php';
}

date_default_timezone_set($GLOBALS["DEFAULT_TIMEZONE"]);

// Constants
$GLOBALS['TASK_CODES']['AUTH'] = 'PAC_START';
$GLOBALS['TASK_CODES']['STEPS'] = 'PAC_STEPS';

$GLOBALS['FORM_CODES']['AUTH'] = 'PAC_START_FORM';
$GLOBALS['FORM_CODES']['STEPS'] = 'PAC_STEPS_FORM';
