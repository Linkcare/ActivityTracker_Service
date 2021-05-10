<?php
error_reporting(E_ERROR); // Do not report warnings to avoid undesired characters in output stream
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
    $server->addFunction("calculate_target_status");
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
    try {
        updatePatientActivity($task, $date);
    } catch (APIException $e) {
        return ['result' => '', 'ErrorMsg' => $e->getMessage()];
    } catch (Exception $e) {
        return ['result' => '', 'ErrorMsg' => $e->getMessage()];
    }
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
    error_log("TASK: $task, DATE: $date");
    try {
        calculateTargetStatus('STEP', $task, $date);
    } catch (APIException $e) {
        return ['result' => '', 'ErrorMsg' => $e->getMessage()];
    } catch (Exception $e) {
        return ['result' => '', 'ErrorMsg' => $e->getMessage()];
    }
}
