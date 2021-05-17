<?php
error_reporting(E_ERROR); // Do not report warnings to avoid undesired characters in output stream
ini_set("soap.wsdl_cache_enabled", 0);

// Link the config params
require_once ("lib/default_conf.php");

setSystemTimeZone();

error_reporting(0);
try {
    // Init connection with WS-API
    apiConnect(null, $GLOBALS['SERVICE_USER'], $GLOBALS['SERVICE_PASSWORD'], 47, $GLOBALS['SERVICE_TEAM'], true);

    // Initialize SOAP Server
    $server = new SoapServer("soap_service.wsdl");
    $server->addFunction("update_activity");
    $server->addFunction("calculate_target_status");
    $server->addFunction("insert_new_goal");
    $server->addFunction("insert_steps");
    $server->handle();
} catch (APIException $e) {
    service_log($e->getMessage());
} catch (Exception $e) {
    service_log($e->getMessage());
}

/**
 * ******************************** SOAP FUNCTIONS *********************************
 */
/**
 * Request the activiy of a patient stored in Fitbit and updates the ADMISSION adding the necessary TASKs
 *
 * @param string $task
 * @param string $date
 * @return string
 */
function update_activity($task, $date = null) {
    $errorMsg = null;
    try {
        $resp = updatePatientActivity($task, $date);
        $errorMsg = $resp['ErrorMsg'];
    } catch (APIException $e) {
        $errorMsg = $e->getMessage();
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
    }

    $result = $errorMsg ? 0 : 1;
    return ['result' => $result, 'ErrorMsg' => $errorMsg];
}

/**
 * Calculates the target activity for this week based on the past week activity.
 * The function creates a TASK 'TARGET_STATUS' with the information about the performance of the patient and the calculation of the next week's
 * goal<br>
 * The $date provided is the date in which the TARGET_STATUS TASK will be inserted. The calculation of the activity is done using the information of
 * the previous week. If no $date is provided, then the date of the TASK will be used.<br>
 * This function should be invoked by a TASK inserted the first day of the new week.
 *
 * @param string $task TASK that invokes the service function
 * @param string $date
 */
function calculate_target_status($task, $date = null) {
    $errorMsg = null;
    try {
        $resp = calculateTargetStatus('STEP', $task, $date);
        $errorMsg = $resp['ErrorMsg'];
    } catch (APIException $e) {
        $errorMsg = $e->getMessage();
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
    }

    $result = $errorMsg ? 0 : 1;
    return ['result' => $result, 'ErrorMsg' => $errorMsg];
}

/**
 * Inserts the GOAL for the next week.
 * This function should be invoked by a TASK inserted the first day of the new week, and shoud be called after 'calculate_target_status()' because it
 * needs the TASK 'TARGET STATUS'.<br>
 * The $date provided is the date in which the TARGET_STATUS TASK will be inserted. The calculation of the activity is done using the information of
 * the previous week. If no $date is provided, then the date of the TASK will be used.<br>
 * It is necessary to indicate the choice of the patient about how to increase the GOAL:
 * <ul>
 * <li>NULL: no choice</li>
 * <li>1: Keep goal</li>
 * <li>2: Increase 5m</li>
 * <li>3: Increase 10m</li>
 * </ul>
 *
 *
 * @param string $task TASK that invokes the service function
 * @param int $patientChoice
 * @param string $date
 */
function insert_new_goal($task, $patientChoice = null, $date = null) {
    try {
        $resp = insertNewGoal($task, $patientChoice, $date);
        $errorMsg = $resp['ErrorMsg'];
    } catch (APIException $e) {
        $errorMsg = $e->getMessage();
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
    }

    $result = $errorMsg ? 0 : 1;
    return ['result' => $result, 'ErrorMsg' => $errorMsg];
}

/**
 * ONLY FOR DEBUG PURPOSES:
 * Update the activiy of a patient adding the necessary "STEPS" TASKs with the data provided.
 * The data mus be passed as a JSON string representing an array with the information with the following format:
 * $steps = '[{"dateTime": "2021-04-27", "value": "3224"}, {"dateTime": "2021-04-28", "value": "5423"}...]'
 *
 * @param string $admission
 * @param string $steps
 * @return string
 */
function insert_steps($admission, $steps = null) {
    $errorMsg = null;

    $steps = json_decode($steps, true); // Convert string to an associative array

    try {
        $resp = insertCustomSteps($admission, $steps);
        $errorMsg = $resp['ErrorMsg'];
    } catch (APIException $e) {
        $errorMsg = $e->getMessage();
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
    }

    $result = $errorMsg ? 0 : 1;
    return ['result' => $result, 'ErrorMsg' => $errorMsg];
}
