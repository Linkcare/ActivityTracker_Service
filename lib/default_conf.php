<?php
session_start();
require_once __DIR__ . '/../vendor/autoload.php';
require_once ("utils.php");
require_once ("WSAPI/WSAPI.php");
require_once ("FitbitAPI/FitbitAPI.php");
require_once "classes/LC2Action.php";
require_once "classes/FitbitResource.php";
require_once "functions.php";
require_once "goal_functions.php";
require_once "fitbit_functions.php";

$GLOBALS["LANG"] = "EN";
$GLOBALS["DEFAULT_TIMEZONE"] = "Europe/Madrid";
$GLOBALS["DEBUG_LOG"] = false; // Set to true to activate logs (on STDERR)

// Url of the WS-API and LC2
$GLOBALS["WS_LINK"] = "https://dev-api.linkcareapp.com/ServerWSDL.php";
$GLOBALS["LC2_LINK"] = "https://dev.linkcareapp.com/apiservice/activity_tracker";

// Credentials of the SERVICE USER
$GLOBALS['SERVICE_USER'] = 'service';
$GLOBALS['SERVICE_PASSWORD'] = 'password';
$GLOBALS['SERVICE_TEAM'] = 'LINKCARE';

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
$GLOBALS['TASK_CODES']['GOAL'] = 'PAC_GOALS';
$GLOBALS['TASK_CODES']['AGREEMENT'] = 'PAC_AGREEMENT_PERIOD';
$GLOBALS['TASK_CODES']['MAXGOAL'] = 'PAC_MAXGOAL';
$GLOBALS['TASK_CODES']['TARGET_STATUS'] = 'PAC_TARGET_STATUS';

$GLOBALS['FORM_CODES']['AUTH'] = 'PAC_START_FORM';
$GLOBALS['FORM_CODES']['STEPS'] = 'PAC_STEPS_FORM';
$GLOBALS['FORM_CODES']['GOAL'] = 'PAC_GOALS_FORM';
$GLOBALS['FORM_CODES']['AGREEMENT'] = 'PAC_AGREEMENT_PERIOD_FORM';
$GLOBALS['FORM_CODES']['MAXGOAL'] = 'PAC_MAXGOAL_FORM';
$GLOBALS['FORM_CODES']['TARGET_STATUS'] = 'PAC_TARGET_STATUS_FORM';

$GLOBALS['ITEM_CODES']['GOAL'] = 'GOAL';
$GLOBALS['ITEM_CODES']['THEOR_GOAL'] = 'THEOR_GOAL';

$GLOBALS['ITEM_CODES']['STEPS'] = 'STEPS';

$GLOBALS['ITEM_CODES']['AGREEMENT_START'] = 'AGREE_START_DATE';
$GLOBALS['ITEM_CODES']['AGREEMENT_END'] = 'AGREE_END_DATE';

$GLOBALS['ITEM_CODES']['MAXGOAL'] = 'MAXGOAL';

$GLOBALS['ITEM_CODES']['TARGET_STATUS'] = 'STATUS';
$GLOBALS['ITEM_CODES']['TARGET_GLOBAL_PERFORMANCE'] = 'GLOBAL_PERFORMANCE';
$GLOBALS['ITEM_CODES']['TARGET_NUM_DAYS_ACCOM'] = 'NUM_DAYS_ACCOM';
$GLOBALS['ITEM_CODES']['TARGET_MEDIAN6'] = 'MEDIAN6';
$GLOBALS['ITEM_CODES']['TARGET_AVG4'] = 'AVG4';
$GLOBALS['ITEM_CODES']['TARGET_WEEK_STEPS'] = 'WEEK_STEPS';
$GLOBALS['ITEM_CODES']['TARGET_GOAL_BASE'] = 'GOAL_BASE';
$GLOBALS['ITEM_CODES']['TARGET_GOAL_5M'] = 'GOAL_5M';
$GLOBALS['ITEM_CODES']['TARGET_GOAL_10M'] = 'GOAL_10M';
$GLOBALS['ITEM_CODES']['TARGET_IN_AGREEMENT'] = 'IN_AGREEMENT_PERIOD';
