<?php

/**
 * ******************************** REST FUNCTIONS *********************************
 */

/**
 * Request the activity of a patient stored in Fitbit and updates the ADMISSION adding the necessary TASKs
 *
 * @param FitbitResource $fbRes
 * @return LC2Action
 */
function storeAuthorization($fbRes) {
    $api = LinkcareSoapAPI::getInstance();

    $task = $api->task_get($fbRes->getTaskId());
    $activityList = $api->task_activity_list($task->getId());

    // Update the ITEMs of the authorization FORM with the information received
    $authForm = null;
    foreach ($activityList as $a) {
        if ($a->getFormCode() == $GLOBALS['FORM_CODES']['AUTH']) {
            $authForm = $api->form_get_summary($a->getId(), true, false);
            break;
        }
    }

    if (!$authForm) {
        $lc2Action = new LC2Action(LC2Action::ACTION_ERROR_MSG);
        $lc2Action->setErrorMessage('AUTHORIZATION FORM NOT FOUND: Cannot update authorization data');
        return $lc2Action;
    }

    $arrQuestions = [];
    if ($authForm) {
        if ($q = $authForm->findQuestion('RESPONSE')) {
            $authorized = $fbRes->getErrorCode() ? '2' : '1';
            $q->setValue($authorized);
            $arrQuestions[] = $q;
        }
        if ($q = $authForm->findQuestion('ACCESS_TOKEN')) {
            $q->setValue($fbRes->getAccessToken());
            $arrQuestions[] = $q;
        }
        if ($q = $authForm->findQuestion('REFRESH_TOKEN')) {
            $q->setValue($fbRes->getRefreshToken());
            $arrQuestions[] = $q;
        }
        if ($q = $authForm->findQuestion('EXPIRATION')) {
            $q->setValue($fbRes->getExpiration());
            $arrQuestions[] = $q;
        }
        if ($q = $authForm->findQuestion('EXPIRATION_DATE')) {
            $q->setValue($fbRes->getExpirationDate());
            $arrQuestions[] = $q;
        }
        if ($q = $authForm->findQuestion('ERROR_CODE')) {
            $q->setValue($fbRes->getErrorCode());
            $arrQuestions[] = $q;
        }
        if ($q = $authForm->findQuestion('ERROR_DESCRIPTION')) {
            $q->setValue($fbRes->getErrorDescription());
            $arrQuestions[] = $q;
        }
        $api->form_set_all_answers($authForm->getId(), $arrQuestions, false);
    }

    $lc2Action = new LC2Action(LC2Action::ACTION_REDIRECT_TO_TASK);
    $lc2Action->setAdmissionId($task->getAdmissionId());
    $lc2Action->setCaseId($task->getCaseId());
    $lc2Action->setTaskId($task->getId());

    return $lc2Action;
}

/**
 * Request the activiy of a patient stored in Fitbit and updates the ADMISSION adding the necessary TASKs
 *
 * @param string $taskId
 * @param string $toDate
 * @return string
 */
function updatePatientActivity($taskId, $toDate) {
    $api = LinkcareSoapAPI::getInstance();
    $updateActivityTask = $api->task_get($taskId);
    $admissionId = $updateActivityTask->getAdmissionId();

    // Get Fitbit OAuth credentials
    $filter = new TaskFilter();
    $filter->setObjectType('TASKS');
    $filter->setStatusIds('CLOSED');
    $admissionTasks = $api->admission_get_task_list($admissionId, 100, 0, $filter, true);
    /* @var APIForm $credentialsForm */
    $credentialsForm = null;
    foreach ($admissionTasks as $t) {
        if ($t->getTaskCode() != $GLOBALS['TASK_CODES']['AUTH']) {
            continue;
        }
        $forms = $api->task_activity_list($t->getId());
        foreach ($forms as $f) {
            if ($f->getFormCode() == $GLOBALS['FORM_CODES']['AUTH']) {
                $credentialsForm = $api->form_get_summary($f->getId());
                break;
            }
        }
    }

    if (!$credentialsForm) {
        return ['ErrorMsg' => 'Authorization missing', 'ErrorCode' => 'AUTHORIZATION_MISSING'];
    }

    $options = [];
    $fitbitCredentials = null;
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
    }
    $fitbitCredentials = new FitbitResource($options);

    if (!$fitbitCredentials || !$fitbitCredentials->isValid()) {
        return ['ErrorMsg' => 'Authorization missing', 'ErrorCode' => 'AUTHORIZATION_MISSING'];
    }

    if (!$toDate) {
        $toDate = 'today';
    }

    // Find last reported activity
    $lastReportedDate = null;
    $lastReportedTask = null;
    foreach ($admissionTasks as $t) {
        if ($t->getTaskCode() != $GLOBALS['TASK_CODES']['STEPS']) {
            continue;
        }
        $lastReportedDate = $t->getDate();
        $lastReportedTask = $t;
    }
    if (!$lastReportedDate) {
        // If there exists no previous TASK with steps, use as start date the ADMISSION enrollment date
        $admission = $api->admission_get($admissionId);
        $lastReportedDate = $admission->getEnrolDate();
    }
    if ($lastReportedDate) {
        // Use only date part
        $lastReportedDate = explode(' ', $lastReportedDate)[0];
    }
    // Request activity data to Fitbit
    $steps = getActivityData($fitbitCredentials, $lastReportedDate, $toDate);
    if ($fitbitCredentials->getErrorCode()) {
        return ['ErrorMsg' => $fitbitCredentials->getErrorDescription()];
    }
    if (empty($steps)) {
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
        if ($lastReportedTask && $lastReportedTask->getDate() == $date) {
            updateStepsTask($lastReportedTask, $value, $date);
        } else {
            createStepsTask($admissionId, $value, $date);
        }
    }

    // Update OAuth tokens if they have changed
    if ($fitbitCredentials->changed()) {
        $arrQuestions = [];
        foreach ($credentialsForm->getQuestions() as $q) {
            if ($q = $credentialsForm->findQuestion('RESPONSE')) {
                $authorized = $fitbitCredentials->getErrorCode() ? '2' : '1';
                $q->setValue($authorized);
                $arrQuestions[] = $q;
            }
            if ($q = $credentialsForm->findQuestion('ACCESS_TOKEN')) {
                $q->setValue($fitbitCredentials->getAccessToken());
                $arrQuestions[] = $q;
            }
            if ($q = $credentialsForm->findQuestion('REFRESH_TOKEN')) {
                $q->setValue($fitbitCredentials->getRefreshToken());
                $arrQuestions[] = $q;
            }
            if ($q = $credentialsForm->findQuestion('EXPIRATION')) {
                $q->setValue($fitbitCredentials->getExpiration());
                $arrQuestions[] = $q;
            }
            if ($q = $credentialsForm->findQuestion('EXPIRATION_DATE')) {
                $q->setValue($fitbitCredentials->getExpirationDate());
                $arrQuestions[] = $q;
            }
            if ($q = $credentialsForm->findQuestion('ERROR_CODE')) {
                $q->setValue($fitbitCredentials->getErrorCode());
                $arrQuestions[] = $q;
            }
            if ($q = $credentialsForm->findQuestion('ERROR_DESCRIPTION')) {
                $q->setValue($fitbitCredentials->getErrorDescription());
                $arrQuestions[] = $q;
            }
            $api->form_set_all_answers($credentialsForm->getId(), $arrQuestions, false);
        }
    }

    return ['ErrorMsg' => '', 'ErrorCode' => ''];
}

/**
 * ******************************** INTERNAL FUNCTIONS *********************************
 */
/**
 * Generates the URL to redirect to LC2 after receiving the authorization information from Fitbit.
 * This URL informs LC2 that it must invoke a service to store the authorization in the appropriate ADMISSION
 *
 * @param FitbitResource $resource Fitbit object with the data we want to store at LC2
 * @return string
 */
function storeAuthorizarionUrl(FitbitResource $resource) {
    $query = [];
    $query[] = 'task=' . urlencode($resource->getTaskId());
    if ($resource->getErrorCode()) {
        $query[] = 'error=' . urlencode($resource->getErrorDescription());
        $query[] = 'error_code=' . urlencode($resource->getErrorCode());
    } else {
        $query[] = 'access_token=' . urlencode($resource->getAccessToken());
        $query[] = 'refresh_token=' . urlencode($resource->getRefreshToken());
        $query[] = 'exp=' . urlencode($resource->getExpiration());
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
function createStepsTask($admissionId, $stepsNumber, $date) {
    $api = LinkcareSoapAPI::getInstance();
    $taskId = $api->task_insert_by_task_code($admissionId, $GLOBALS["TASK_CODES"]["STEPS"], $date);
    if (!$taskId) {
        // An unexpected error happened while creating the TASK
        throw new APIException($api->errorCode(), $api->errorMessage());
    }

    $task = $api->task_get($taskId);
    updateStepsTask($task, $stepsNumber, $date);

    return $taskId;
}

/**
 * Updates the number of steps in the TASK provided
 *
 * @param APITask $task
 * @param int $stepsNumber
 * @param string $date
 * @throws APIException
 */
function updateStepsTask($task, $stepsNumber, $date) {
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
        if ($question = $stepsForm->findQuestion('STEPS')) {
            $question->setValue($stepsNumber);
            $api->form_set_answer($stepsForm->getId(), $question->getId(), $stepsNumber);
            $task->setDate($date);
            $api->task_set($task);
        }
    }
}
