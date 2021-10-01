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
    $server->addFunction("calculate_achievement");
    $server->addFunction("sync_status");
    $server->addFunction("set_device_user_profile");

    $server->handle();
} catch (APIException $e) {
    log_trace('UNEXPECTED API ERROR executing SOAP function: ' . $e->getMessage());
    service_log($e->getMessage());
} catch (Exception $e) {
    log_trace('UNEXPECTED ERROR executing SOAP function: ' . $e->getMessage());
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
    log_trace("UPDATE ACTIVITY. Task: $task, Date: $date");
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
    if ($errorMsg) {
        log_trace("ERROR: $errorMsg", 1);
    }
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
    log_trace("CALCULATE TARGET STATUS. Task: $task, Date: $date");
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
    if ($errorMsg) {
        log_trace("ERROR: $errorMsg", 1);
    }
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
    log_trace("INSERT NEW GOAL. Task: $task, Patient choice: $patientChoice, Date: $date");
    $errorMsg = null;

    try {
        $resp = insertNewGoal($task, $patientChoice, $date);
        $errorMsg = $resp['ErrorMsg'];
    } catch (APIException $e) {
        $errorMsg = $e->getMessage();
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
    }

    $result = $errorMsg ? 0 : 1;
    if ($errorMsg) {
        log_trace("ERROR: $errorMsg", 1);
    }
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
    log_trace("INSERT STEPS. Admission: $admission, Steps: $steps");
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
    if ($errorMsg) {
        log_trace("ERROR: $errorMsg", 1);
    }
    return ['result' => $result, 'ErrorMsg' => $errorMsg];
}

/**
 * Calculates the achievements of a patient based on the distance walked.
 * The calculation uses a pre-configured array of increasing distances that represents the possible achievements.<br>
 * The distance walked determines which item of the array has been reached.<br>
 * The parameter $mode defines what happens when the patient goes beyond the last item of the array. There are 3 options:
 * <ul>
 * <li>STAY: After reaching the last achievement, the element reported will always be the last item of the array</li>
 * <li>RESTART: (cyclic) After reaching the last achievement, the next city will be the first item of the array.</li>
 * <li>RETURN: (two way) After reaching the last achievement, the next achivements will traverse the array reversely</li>
 * </ul>
 *
 * @param string $task Reference to the TASK where the achievements will be stored
 * @param number $distance Total distance covered by the patient
 * @param string $mode Calculation mode
 */
function calculate_achievement($task, $distance, $mode = 'STAY') {
    log_trace("CALCULATE ACHIEVEMENT. Task: $task, Distance: $distance, Mode: $mode");
    $errorMsg = null;

    try {
        $resp = calculateAchievement($task, $distance, $mode);
        $errorMsg = $resp['ErrorMsg'];
    } catch (APIException $e) {
        $errorMsg = $e->getMessage();
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
    }

    $result = $errorMsg ? 0 : 1;
    if ($errorMsg) {
        log_trace("ERROR: $errorMsg", 1);
    }
    return ['result' => $result, 'ErrorMsg' => $errorMsg];
}

/**
 * Checks the status of the synchronization of a device with the Fitbit server
 *
 * @param string $task Reference to the TASK where the syncronization status will be stored
 */
function sync_status($task) {
    log_trace("CHECK SYNC STATUS. Task: $task");
    $errorMsg = null;

    try {
        $resp = checkSyncStatus($task);
        $errorMsg = $resp['ErrorMsg'];
    } catch (APIException $e) {
        $errorMsg = $e->getMessage();
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
    }

    $result = $errorMsg ? '' : $resp['result'];
    if ($errorMsg) {
        log_trace("ERROR: $errorMsg", 1);
    }
    return ['result' => $result, 'ErrorMsg' => $errorMsg];
}

/**
 * Mofifies the user profile data in the device provider (Fitbit)
 *
 * @param string $admission Reference to the ADMISSION where the credentials of the user will be searched
 */
function set_device_user_profile($admission, $fullname = null, $gender = null, $birthday = null, $height = null, $stride_length_walking = null,
        $stride_length_running = null) {
    log_trace(
            "SET DEVICE USER PROFILE. Gender: $gender, Birthday: $birthday, Fullname: $fullname, Stride length walking: $stride_length_walking, Stride length running: $stride_length_running");
    $errorMsg = null;
    $parameters = [];

    $gender = trim(strtoupper($gender));
    if (!isNullOrEmpty($gender) && in_array($gender, ['M', 'F', '?'])) {
        switch ($gender) {
            case 'M' :
                $parameters['gender'] = 'MALE';
                break;
            case 'F' :
                $parameters['gender'] = 'FEMALE';
                break;
            default :
                $parameters['gender'] = 'NA';
                break;
        }
    }
    if (!isNullOrEmpty($birthday) && strtotime($birthday)) {
        $birthday = date('Y-m-d', strtotime($birthday));
        $parameters['birthday'] = $birthday;
    }
    if (intval($height) > 0) {
        $parameters['height'] = intval($height);
    }
    if (!isNullOrEmpty($fullname)) {
        $parameters['fullname'] = $fullname;
    }
    if (intval($stride_length_walking) > 0) {
        $parameters['strideLengthWalking'] = intval($stride_length_walking);
    }
    if (intval($stride_length_running) > 0) {
        $parameters['strideLengthRunning'] = intval($stride_length_running);
    }
    try {
        $resp = setDeviceUserProfile($admission, $parameters);
        $errorMsg = $resp['ErrorMsg'];
    } catch (APIException $e) {
        $errorMsg = $e->getMessage();
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
    }

    $result = $errorMsg ? '' : $resp['result'];
    if ($errorMsg) {
        log_trace("ERROR: $errorMsg", 1);
    }
    return ['result' => $result, 'ErrorMsg' => $errorMsg];
}
