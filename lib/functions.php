<?php

/**
 * ******************************** REST FUNCTIONS *********************************
 */

/**
 * Request the activity of a patient stored in the Activity provider platform and updates the ADMISSION adding the necessary TASKs
 *
 * @param OauthResource $oauthResource
 * @param string $grantedScopes A space separated list of scopes for which authorization has been granted
 * @return LC2Action
 */
function storeAuthorization($oauthResource, $grantedScopes = null) {
    $api = LinkcareSoapAPI::getInstance();

    $deniedScopes = [];
    if ($grantedScopes) {
        $grantedScopes = explode(' ', $grantedScopes);
        // Check if authorization was granted for all requested scopes
        foreach ($GLOBALS['PERMISSIONS_REQUESTED'] as $scope) {
            if (!in_array($scope, $grantedScopes)) {
                $deniedScopes[] = $scope;
            }
        }
    }

    $task = $api->task_get($oauthResource->getTaskId());
    $activityList = $api->task_activity_list($task->getId());

    // Update the ITEMs of the authorization FORM with the information received
    $authForm = null;
    $patientDataForm = null;
    foreach ($activityList as $a) {
        if ($a->getFormCode() == $GLOBALS['FORM_CODES']['AUTH']) {
            $authForm = $api->form_get_summary($a->getId(), true, false);
        }
        if ($a->getFormCode() == $GLOBALS['FORM_CODES']['PATIENT_DATA']) {
            $patientDataForm = $api->form_get_summary($a->getId(), true, false);
        }
    }

    if (!$authForm) {
        log_trace("ERROR: AUTHORIZATION FORM NOT FOUND: Cannot update authorization data", 1);
        $lc2Action = new LC2Action(LC2Action::ACTION_ERROR_MSG);
        $lc2Action->setErrorMessage('AUTHORIZATION FORM NOT FOUND: Cannot update authorization data');
        return $lc2Action;
    }

    $arrQuestions = [];
    if ($authForm) {
        if ($q = $authForm->findQuestion('RESPONSE')) {
            $authorized = $oauthResource->getErrorCode() ? '2' : '1';
            $q->setValue($authorized);
            $arrQuestions[] = $q;
        }
        if ($q = $authForm->findQuestion('ACCESS_TOKEN')) {
            $q->setValue($oauthResource->getToken());
            $arrQuestions[] = $q;
        }
        if ($q = $authForm->findQuestion('REFRESH_TOKEN')) {
            $q->setValue($oauthResource->getRefreshToken());
            $arrQuestions[] = $q;
        }
        if ($q = $authForm->findQuestion('EXPIRATION')) {
            $q->setValue($oauthResource->getExpiration());
            $arrQuestions[] = $q;
        }
        if ($q = $authForm->findQuestion('EXPIRATION_DATE')) {
            $q->setValue($oauthResource->getExpirationDate());
            $arrQuestions[] = $q;
        }
        if ($q = $authForm->findQuestion('ERROR_CODE')) {
            $q->setValue($oauthResource->getErrorCode());
            $arrQuestions[] = $q;
        }
        if ($q = $authForm->findQuestion('ERROR_DESCRIPTION')) {
            $q->setValue($oauthResource->getErrorDescription());
            $arrQuestions[] = $q;
        }
        if ($q = $authForm->findQuestion('GRANTED_SCOPES')) {
            // Store granted scope as a list of comma separated scopes
            $q->setValue(is_array($grantedScopes) ? implode(',', $grantedScopes) : '');
            $arrQuestions[] = $q;
        }
        if ($q = $authForm->findQuestion('DENIED_SCOPES')) {
            // Store granted scope as a list of comma separated scopes
            $q->setValue(implode(',', $deniedScopes));
            $arrQuestions[] = $q;
        }
        if ($q = $authForm->findQuestion('PROVIDER')) {
            // Store granted scope as a list of comma separated scopes
            $q->setValue($oauthResource->getProviderName());
            $arrQuestions[] = $q;
        }
        $api->form_set_all_answers($authForm->getId(), $arrQuestions, false);
    }

    // Update user profile information in the Activity provider platform
    if (!$oauthResource->getErrorCode()) {
        if ($GLOBALS['UPDATE_PERSONAL_DATA']) {
            $patient = $api->case_get_contact($task->getCaseId());
            $profileInfo = [];
            if ($patient) {
                switch ($patient->getGender()) {
                    case 'M' :
                        $profileInfo['gender'] = 'MALE';
                        break;
                    case 'F' :
                        $profileInfo['gender'] = 'FEMALE';
                        break;
                }
                $profileInfo['birthday'] = $patient->getBirthdate();
                $participantRef = $patient->findIdentifier('PARTICIPANT_REF');
                if ($participantRef) {
                    /*
                     * The name assigned to the patient will be composed by a generic name (e.g. "Mafipar") and the PARTICIPANT_REF IDENTIFIER
                     */
                    $profileInfo['fullname'] = $GLOBALS['DEFAULT_PROFILE_NAME'] . ' ' . explode('@', $participantRef->getValue())[0];
                } else {
                    $profileInfo['fullname'] = $GLOBALS['DEFAULT_PROFILE_NAME'];
                }
            }
        }

        if ($patientDataForm) {
            if ($q = $patientDataForm->findQuestion('STEP_LENGTH')) {
                $profileInfo['strideLengthWalking'] = $q->getValue();
            }
            if ($q = $patientDataForm->findQuestion('HEIGHT')) {
                $profileInfo['height'] = $q->getValue();
            }
        }

        $activityProvider = $oauthResource->getProvider();
        $activityProvider->updateProfile($oauthResource, $profileInfo);
    }

    $lc2Action = new LC2Action(LC2Action::ACTION_REDIRECT_TO_TASK);
    $lc2Action->setAdmissionId($task->getAdmissionId());
    $lc2Action->setCaseId($task->getCaseId());
    $lc2Action->setTaskId($task->getId());

    return $lc2Action;
}

/**
 * Request the activiy of a patient stored in tha Activity Provider platform and updates the ADMISSION adding the necessary TASKs
 *
 * @param string $taskId
 * @param string $toDate
 * @return string[]
 */
function updatePatientActivity($taskId, $toDate) {
    if (!$toDate) {
        $toDate = 'today';
    }

    $api = LinkcareSoapAPI::getInstance();
    $updateActivityTask = $api->task_get($taskId);
    $admissionId = $updateActivityTask->getAdmissionId();
    $patient = $api->case_get($updateActivityTask->getCaseId());
    $timeZone = $patient->getTimezone();

    log_trace("UPDATE PATIENT ACTIVITY. Date: $toDate,  Admission: $admissionId");

    $oauthResource = loadOauthCredentials($admissionId);

    if (!$oauthResource->isValid()) {
        log_trace('ERROR! Invalid OAuth credentials: ' . $oauthResource->getErrorDescription(), 1);
        return ['ErrorMsg' => $oauthResource->getErrorDescription(), 'ErrorCode' => $oauthResource->getErrorCode()];
    }

    // Find last reported activity
    $lastReportedTask = findLastReportedActivity($admissionId);
    $lastReportedDate = null;
    if ($lastReportedTask) {
        $lastReportedDate = $lastReportedTask->getDate();
    } else {
        // If there exists no previous TASK with steps, use as start date the ADMISSION enrollment date
        $admission = $api->admission_get($admissionId);
        $lastReportedDate = $admission->getEnrolDate();
    }
    if ($lastReportedDate) {
        // Use only date part
        $lastReportedDate = explode(' ', $lastReportedDate)[0];
    }
    // Request activity data to the Activity provider
    $activityProvider = $oauthResource->getProvider();
    $steps = $activityProvider->getActivityData($oauthResource, $lastReportedDate, $toDate, $timeZone);
    if ($oauthResource->getErrorCode()) {
        log_trace("ERROR! Activity provider returned: " . $oauthResource->getErrorDescription(), 1);
        return ['ErrorMsg' => $oauthResource->getErrorDescription()];
    }
    if (empty($steps)) {
        log_trace("No new activity detected", 1);
        return ['ErrorMsg' => '', 'ErrorCode' => ''];
    }

    // $steps[] = ['dateTime' => '2021-04-27', 'value' => '3224'];
    foreach ($steps as $daySteps) {
        $date = $daySteps['dateTime'];
        if ($date < $lastReportedDate) {
            // Theoretically this could not happen because we have requested steps from the last reporte TASK
            continue;
        }
        $value = $daySteps['value'];
        if ($value <= 0) {
            continue;
        }
        log_trace("Steps in $date: $value", 2);
        $partialSteps = $activityProvider->getDetailedActivity($oauthResource, $date, '15min', $timeZone);
        if ($lastReportedTask && $lastReportedTask->getDate() == $date) {
            updateStepsTask($lastReportedTask, $value, $date, $partialSteps);
        } else {
            createStepsTask($admissionId, $value, $date, $partialSteps);
        }
    }

    return ['ErrorMsg' => '', 'ErrorCode' => ''];
}

/**
 * Checks the status of the synchronization of a device with the Activity provider server
 *
 * @param string $taskId Reference to the TASK where the sync status will be stored
 */
function checkSyncStatus($taskId) {
    $api = LinkcareSoapAPI::getInstance();
    $task = $api->task_get($taskId);

    log_trace("Checking sync status. Task: " . $task->getId() . ", Date:" . $task->getDate() . ", Patient: " . $task->getCaseId());

    $oauthResource = loadOauthCredentials($task->getAdmissionId());
    if (!$oauthResource->isValid()) {
        log_trace('ERROR! Invalid OAuth credentials: ' . $oauthResource->getErrorDescription(), 1);
        return ['ErrorMsg' => $oauthResource->getErrorDescription(), 'ErrorCode' => $oauthResource->getErrorCode()];
    }

    $syncStatusForm = $task->findForm($GLOBALS['FORM_CODES']['SYNC_STATUS']);
    if (!$syncStatusForm) {
        $errorMsg = "FORM NOT FOUND: " . $GLOBALS['FORM_CODES']['SYNC_STATUS'];
        log_trace("ERROR! " . $errorMsg, 1);
        throw new APIException("FORM.NOT_FOUND", $errorMsg);
    }

    $activityProvider = $oauthResource->getProvider();
    $devData = $activityProvider->getDeviceData($oauthResource);
    if ($oauthResource->getErrorCode()) {
        log_trace("ERROR! Activity provider returned: " . $oauthResource->getErrorDescription(), 1);
        return ['ErrorMsg' => $oauthResource->getErrorDescription()];
    }

    $lastSyncTime = '';
    $batteryLevel = null;
    foreach ($devData as $device) {
        if ($device['type'] != 'TRACKER') {
            continue;
        }
        if ($lastSyncTime < $device['lastSyncTime']) {
            $lastSyncTime = $device['lastSyncTime'];
            $batteryLevel = $device['batteryLevel'];
        }
    }

    if (isNullOrEmpty($lastSyncTime)) {
        /* We have no information abount the last synchronization. Use as last sync date the last date with steps */
        log_trace("WARNING: We have no information abount the last synchronization date. Use as last sync date the last date with steps", 1);
        $lastReportedTask = findLastReportedActivity($task->getAdmissionId());
        $lastSyncTime = null;
        if ($lastReportedTask) {
            $lastSyncTime = $lastReportedTask->getDateTime();
        } else {
            // If there exists no previous TASK with steps, use as start date the ADMISSION enrollment date
            $admission = $api->admission_get($task->getAdmissionId());
            $lastSyncTime = $admission->getEnrolDate();
        }
    }

    $arrQuestions = [];

    if ($q = $syncStatusForm->findQuestion($GLOBALS['ITEM_CODES']['LAST_SYNC_TIME'])) {
        $q->setValue($lastSyncTime);
        $arrQuestions[] = $q;
    }
    if ($q = $syncStatusForm->findQuestion($GLOBALS['ITEM_CODES']['BATTERY_LEVEL'])) {
        $q->setValue($batteryLevel);
        $arrQuestions[] = $q;
    }
    $api->form_set_all_answers($syncStatusForm->getId(), $arrQuestions, true);

    return ['result' => $lastSyncTime, 'ErrorMsg' => '', 'ErrorCode' => ''];
}

/**
 * Checks the status of the synchronization of a device with the Activity provider server
 *
 * @param string $admissionId Reference to the ADMISSION of the patient
 */
function setDeviceUserProfile($admissionId, $profileData) {
    log_trace("Set device user profile. Admission: " . $admissionId . ", ProfileData: " . json_encode($profileData));

    $profileData = array_filter($profileData);
    if (empty($profileData)) {
        return ['result' => 1, 'ErrorMsg' => '', 'ErrorCode' => ''];
    }
    $oauthResource = loadOauthCredentials($admissionId);
    if (!$oauthResource->isValid()) {
        log_trace('ERROR! Invalid OAuth credentials: ' . $oauthResource->getErrorDescription(), 1);
        return ['ErrorMsg' => $oauthResource->getErrorDescription(), 'ErrorCode' => $oauthResource->getErrorCode()];
    }

    $activityProvider = $oauthResource->getProvider();
    $activityProvider->updateProfile($oauthResource, $profileData);
    if ($oauthResource->getErrorCode()) {
        log_trace("ERROR! Activity provider returned: " . $oauthResource->getErrorDescription(), 1);
        return ['ErrorMsg' => $oauthResource->getErrorDescription()];
    }

    return ['result' => 1, 'ErrorMsg' => '', 'ErrorCode' => ''];
}

/**
 * Insert "STEPS" TASKS with data provided manually
 *
 * @param string $admissionId
 * @param Array $steps [['dateTime' => '2021-04-27', 'value' => '3224'], ...];
 *       
 * @return string
 */
function insertCustomSteps($admissionId, $steps) {
    if (empty($steps)) {
        return ['ErrorMsg' => '', 'ErrorCode' => ''];
    }

    log_trace("INSERT CUSTOM STEPS. Admission: $admissionId");

    $api = LinkcareSoapAPI::getInstance();

    $minDate = null;
    $maxDate = null;
    foreach ($steps as $daySteps) {
        $date = $daySteps['dateTime'];
        if (!$minDate || $date < $minDate) {
            $minDate = $date;
        }
        if (!$maxDate || $date > $maxDate) {
            $maxDate = $date;
        }
    }

    $filter = new TaskFilter();
    $filter->setObjectType('TASKS');
    $filter->setStatusIds('CLOSED');
    $filter->setFromDate($minDate);
    $filter->setToDate($maxDate);
    $filter->setTaskCodes($GLOBALS['TASK_CODES']['STEPS']);

    $tasks = $api->admission_get_task_list($admissionId, 100, 0, $filter, true);
    foreach ($tasks as $t) {
        $existingSteps[$t->getDate()] = $t;
    }

    $admission = $api->admission_get($admissionId);
    $limitDate = $admission->getEnrolDate();

    if ($limitDate) {
        // Use only date part
        $limitDate = explode(' ', $limitDate)[0];
    }

    foreach ($steps as $daySteps) {
        $date = $daySteps['dateTime'];
        if ($date < $limitDate) {
            // Do not add steps previous to the ADMISSION enroll
            continue;
        }
        $value = $daySteps['value'];
        if ($value <= 0) {
            continue;
        }
        log_trace("Steps in $date: $value", 1);
        $partialSteps = [['time' => '09:00', 'value' => $value]];
        if (array_key_exists($date, $existingSteps)) {
            updateStepsTask($existingSteps[$date], $value, $date, $partialSteps);
        } else {
            createStepsTask($admissionId, $value, $date, $partialSteps);
        }
    }

    return ['ErrorMsg' => '', 'ErrorCode' => ''];
}

/**
 * ******************************** INTERNAL FUNCTIONS *********************************
 */
/**
 * Generates the URL to redirect to LC2 after receiving the authorization information from the Activity provider's authorization server.
 * This URL informs LC2 that it must invoke a service to store the authorization in the appropriate ADMISSION
 *
 * @param OauthResource $resource OAuthResource object with the data we want to store at LC2
 * @return string
 */
function storeAuthorizationUrl(OauthResource $resource, $scope = null) {
    /*
     * The name of the scopes returned depend on the activity provider (Fitbit, Huawei...), but we must use standard names, so we will map the
     * proprietary names to our own names
     */
    $query = [];
    $query[] = 'task=' . urlencode($resource->getTaskId());
    if ($resource->getErrorCode()) {
        $query[] = 'error=' . urlencode($resource->getErrorDescription());
        $query[] = 'error_code=' . urlencode($resource->getErrorCode());
    } else {
        $query[] = 'access_token=' . urlencode($resource->getToken());
        $query[] = 'refresh_token=' . urlencode($resource->getRefreshToken());
        $query[] = 'exp=' . urlencode($resource->getExpiration());
        if (!empty($scope)) {
            $query[] = 'scope=' . urlencode($scope);
        }
        $query[] = 'provider=' . urlencode($resource->getProviderName());
    }

    $strQuery = implode('&', $query);
    return $GLOBALS["LC2_LINK"] . '?do=authorize&' . $strQuery;
}

/**
 * Connects to the WS-API using the session $token passed as parameter
 *
 * @param string $token
 * @param string $user
 * @param string $password
 * @param int $role
 * @param string $team
 *
 * @throws APIException
 * @throws Exception
 * @return LinkcareSoapAPI
 */
function apiConnect($token, $user = null, $password = null, $role = null, $team = null, $reuseExistingSession = false) {
    $timezone = "0";
    $session = null;

    try {
        LinkcareSoapAPI::setEndpoint($GLOBALS["WS_LINK"]);
        if ($token) {
            LinkcareSoapAPI::session_join($token, $timezone);
        } else {
            LinkcareSoapAPI::session_init($user, $password, 0, $reuseExistingSession);
        }

        $session = LinkcareSoapAPI::getInstance()->getSession();
        // Ensure to set the correct active ROLE and TEAM
        if ($team && $team != $session->getTeamCode() && $team != $session->getTeamId()) {
            LinkcareSoapAPI::getInstance()->session_set_team($team);
        }
        if ($role && $session->getRoleId() != $role) {
            LinkcareSoapAPI::getInstance()->session_role($role);
        }
    } catch (APIException $e) {
        throw $e;
    } catch (Exception $e) {
        throw $e;
    }

    return $session;
}

/**
 * Inserts a new "STEPS" TASK in the ADMISSION
 *
 * @param string $admissionId
 * @throws APIException
 * @return string
 */
function createStepsTask($admissionId, $stepsNumber, $date, $partialSteps = null) {
    $api = LinkcareSoapAPI::getInstance();
    $taskId = $api->task_insert_by_task_code($admissionId, $GLOBALS["TASK_CODES"]["STEPS"], $date);
    if (!$taskId) {
        // An unexpected error happened while creating the TASK
        throw new APIException($api->errorCode(), $api->errorMessage());
    }

    $task = $api->task_get($taskId);
    updateStepsTask($task, $stepsNumber, $date, $partialSteps);

    return $taskId;
}

/**
 * Updates the number of steps in the TASK provided.
 * The parameter $partialSteps can be used to store also a breakdown of the steps in different intervals of the day. It must be an array where each
 * item is an associative array with 2 values:
 * <ul>
 * <li>'[{ "time": "00:00:00", "value": 0 },</li>
 * <li>'{ "time": "00:01:00", "value": 287 },</li>
 * <li>'{ "time": "00:02:00", "value": 287 }]</li>
 * </ul>
 *
 * @param APITask $task
 * @param int $stepsNumber
 * @param string $date
 * @param array $partialSteps
 * @throws APIException
 */
function updateStepsTask($task, $stepsNumber, $date, $partialSteps = null) {
    if (!$task) {
        return;
    }

    $api = LinkcareSoapAPI::getInstance();
    // TASK inserted. Now update the questions with the number of steps
    $forms = $api->task_activity_list($task->getId());
    $stepsForm = null;
    foreach ($forms as $f) {
        if ($f->getFormCode() == $GLOBALS['FORM_CODES']['STEPS']) {
            $stepsForm = $api->form_get_summary($f->getId());
            break;
        }
    }

    if ($stepsForm) {
        if ($question = $stepsForm->findQuestion($GLOBALS['ITEM_CODES']['STEPS'])) {
            $question->setValue($stepsNumber);
            $arrQuestions[] = $question;
        }
        /* If we have partial steps, then we must fill the array of questions with the details */
        if (!empty($partialSteps) && ($arrayHeader = $stepsForm->findQuestion($GLOBALS['ITEM_CODES']['PARTIAL_STEPS_TIME'])) &&
                $arrayHeader->getArrayRef()) {

            $row = 1;
            foreach ($partialSteps as $partialInfo) {
                $partialValue = $partialInfo['value'];
                $partialTime = $partialInfo['time'];

                if (!$partialValue) {
                    continue;
                }
                if ($question = $stepsForm->findArrayQuestion($arrayHeader->getArrayRef(), $row, $GLOBALS['ITEM_CODES']['PARTIAL_STEPS_TIME'])) {
                    $question->setValue($partialTime);
                    $arrQuestions[] = $question;
                }
                if ($question = $stepsForm->findArrayQuestion($arrayHeader->getArrayRef(), $row, $GLOBALS['ITEM_CODES']['PARTIAL_STEPS'])) {
                    $question->setValue($partialValue);
                    $arrQuestions[] = $question;
                }

                $row++;
            }
        }

        if (!empty($arrQuestions)) {
            $api->form_set_all_answers($stepsForm->getId(), $arrQuestions, true);
        }
        // $api->form_set_answer($stepsForm->getId(), $question->getId(), $stepsNumber);
        $task->setDate($date);
        $api->task_set($task);
    }
}

/**
 *
 * @param string $admissionId
 * @return OauthResource
 */
function loadOauthCredentials($admissionId) {
    $api = LinkcareSoapAPI::getInstance();

    // Get OAuth credentials from the most recent autentication TASK
    $filter = new TaskFilter();
    $filter->setObjectType('TASKS');
    $filter->setStatusIds('CLOSED');
    $filter->setTaskCodes([$GLOBALS['TASK_CODES']['AUTH'], $GLOBALS['TASK_CODES']['RENEW_AUTH']]);
    $authTasks = $api->admission_get_task_list($admissionId, 100, 0, $filter, false);
    /* @var APIForm $credentialsForm */
    $credentialsForm = null;
    if (!empty($authTasks)) {
        /* @var APITask $t */
        $t = reset($authTasks);
        $forms = $api->task_activity_list($t->getId());
        foreach ($forms as $f) {
            if ($f->getFormCode() == $GLOBALS['FORM_CODES']['AUTH']) {
                $credentialsForm = $api->form_get_summary($f->getId());
                break;
            }
        }
    }

    if (!$credentialsForm) {
        $options['errorCode'] = 'AUTHORIZATION_MISSING';
        $options['errorDescription'] = 'OAuth credentials not found in this ADMISSION';
        return new OauthResource($options, null);
    }

    $options = [];
    foreach ($credentialsForm->getQuestions() as $q) {
        if ($q->getItemCode() == 'ACCESS_TOKEN') {
            $options['access_token'] = $q->getValue();
        }
        if ($q->getItemCode() == 'REFRESH_TOKEN') {
            $options['refresh_token'] = $q->getValue();
        }
        if ($q->getItemCode() == 'EXPIRATION') {
            $options['expiration'] = $q->getValue();
        }
        if ($q->getItemCode() == 'PROVIDER') {
            $activityProviderName = $q->getValue();
        }
    }

    if (!$activityProviderName) {
        // If an specific activity provider has not been defined, use the default provider
        $activityProviderName = $GLOBALS['DEFAULT_ACTIVITY_PROVIDER'];
    }

    $provider = ActivityProvider::getInstance($activityProviderName);
    $resource = new OauthResource($options, $provider);
    if (!$resource->isValid()) {
        return $resource;
    }

    // Update OAuth tokens if they have changed
    if ($resource->changed()) {
        $arrQuestions = [];
        foreach ($credentialsForm->getQuestions() as $q) {
            if ($q = $credentialsForm->findQuestion('RESPONSE')) {
                $authorized = $resource->getErrorCode() ? '2' : '1';
                $q->setValue($authorized);
                $arrQuestions[] = $q;
            }
            if ($q = $credentialsForm->findQuestion('ACCESS_TOKEN')) {
                $q->setValue($resource->getToken());
                $arrQuestions[] = $q;
            }
            if ($q = $credentialsForm->findQuestion('REFRESH_TOKEN')) {
                $q->setValue($resource->getRefreshToken());
                $arrQuestions[] = $q;
            }
            if ($q = $credentialsForm->findQuestion('EXPIRATION')) {
                $q->setValue($resource->getExpiration());
                $arrQuestions[] = $q;
            }
            if ($q = $credentialsForm->findQuestion('EXPIRATION_DATE')) {
                $q->setValue($resource->getExpirationDate());
                $arrQuestions[] = $q;
            }
            if ($q = $credentialsForm->findQuestion('ERROR_CODE')) {
                $q->setValue($resource->getErrorCode());
                $arrQuestions[] = $q;
            }
            if ($q = $credentialsForm->findQuestion('ERROR_DESCRIPTION')) {
                $q->setValue($resource->getErrorDescription());
                $arrQuestions[] = $q;
            }
        }
        $api->form_set_all_answers($credentialsForm->getId(), $arrQuestions, false);
    }

    return $resource;
}

/**
 * Returns the last TASK with registered activity
 *
 * @param string $admissionId
 * @return APITask
 */
function findLastReportedActivity($admissionId) {
    $api = LinkcareSoapAPI::getInstance();

    $lastReportedTask = null;
    $filter = new TaskFilter();
    $filter->setObjectType('TASKS');
    $filter->setStatusIds('CLOSED');
    $filter->setTaskCodes($GLOBALS['TASK_CODES']['STEPS']);
    $stepTasks = $api->admission_get_task_list($admissionId, 1, 0, $filter, false);

    if (!empty($stepTasks)) {
        /* @var APITask $t */
        $t = reset($stepTasks);
        $lastReportedTask = $t;
    }

    return $lastReportedTask;
}



