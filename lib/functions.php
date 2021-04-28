<?php

/**
 * ******************************** REST FUNCTIONS *********************************
 */

/**
 * Request the activiy of a patient stored in Fitbit and updates the ADMISSION adding the necessary TASKs
 *
 * @param string $token
 * @return string
 */
function storeAuthorization($token) {
    $lc2Action = new LC2Action(LC2Action::ACTION_ERROR_MSG);
    $lc2Action->setErrorMessage('No action executed');

    try {
        $session = apiConnect($token);
    } catch (APIException $e) {
        $lc2Action = new LC2Action(LC2Action::ACTION_ERROR_MSG);
        $lc2Action->setErrorMessage($e->getMessage());
    } catch (Exception $e) {
        service_log("ERROR: " . $e->getMessage());
    }

    return $lc2Action->toString();
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
    return $GLOBALS["LC2_LINK"] . '?action=authorize';
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
