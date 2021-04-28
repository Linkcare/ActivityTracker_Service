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
        if ($a->getFormCode() == $GLOBALS['FORM_CODES']['AUTH_FORM']) {
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
            $authorized = $fbRes->getErrorCode() ? '1' : '2';
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
 * ******************************** SOAP FUNCTIONS *********************************
 */
/**
 * Request the activiy of a patient stored in Fitbit and updates the ADMISSION adding the necessary TASKs
 *
 * @param string $token
 * @return string
 */
function update_activity() {
    return ['result' => 'OK', 'ErrorMsg' => ''];
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
        $query[] = 'token=' . urlencode($resource->getAccessToken());
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
 *
 * @throws APIException
 * @throws Exception
 * @return LinkcareSoapAPI
 */
function apiConnect($token = null) {
    $timezone = "0";
    $session = null;

    try {
        LinkcareSoapAPI::init($GLOBALS["WS_LINK"], $timezone, $token);
        $session = LinkcareSoapAPI::getInstance()->getSession();
    } catch (APIException $e) {
        throw $e;
    } catch (Exception $e) {
        throw $e;
    }

    return $session;
}
