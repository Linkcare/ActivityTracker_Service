<?php

/**
 * ******************************** FITBIT RELATED FUNCTIONS *********************************
 */
use FitbitOAuth2Client\Fitbit;
use League\OAuth2\Client\Token\AccessToken;
use function GuzzleHttp\json_decode;

/**
 * Refresh the token since it has expired.
 *
 * @param string $refresh_token
 * @return AccessToken
 */
function refreshToken($refresh_token) {
    $accessToken = Fitbit::getProvider()->getAccessToken('refresh_token', ['refresh_token' => $refresh_token]);

    return $accessToken;
}

/**
 * Obtain the Activity data from the FITBIT API using two dates.
 * The date parameters are itself included in the response, they have to be in 'yyyy-MM-dd' format.<br>
 * The return an associative array formatted as follows:
 * <ul>
 * <li>'[["dateTime" => "yyyy-MM-dd","value" => "NUMBER"],</li>
 * <li>'["dateTime" => "yyyy-MM-dd","value" => "NUMBER"],</li>
 * <li>'["dateTime" => "yyyy-MM-dd","value" => "NUMBER"]]</li>
 * </ul>
 *
 * Possible error codes returned in FitbitResource:
 * <ul>
 * <li>refresh_token_error: there was an error while refreshing the expired token.</li>
 * <li>request_error: there was an error while obtaining the activity data.</li>
 * <li>unknown_error: the obtained data has changed its format or there was an uncaught error.</li>
 * </ul>
 *
 * @param FitbitResource $resource
 * @param string $startDate date as 'yyyy-MM-dd'
 * @param string $endDate date as 'yyyy-MM-dd'
 * @param string $locale
 * @return array
 *
 */
function getActivityData(FitbitResource $resource, $startDate, $endDate, $locale = 'es_ES') {
    // ONLY FOR DEBUG
    // $steps = [['dateTime' => '2021-06-28', 'value' => '3224'], ['dateTime' => '2021-06-29', 'value' => '2864'],
    // ['dateTime' => '2021-06-30', 'value' => '9230']];
    // return $steps;
    if (!$startDate) {
        $startDate = '2021-01-01';
    }
    if (!$endDate) {
        $endDate = 'today';
    }

    $accessToken = new AccessToken(['access_token' => $resource->getAccessToken(), 'refresh_token' => $resource->getRefreshToken(),
            'expires_in' => ($resource->getExpiration() - time())]);

    if ($accessToken->hasExpired()) {
        try {
            $accessToken = refreshToken($resource->getRefreshToken());
            $resource->setAccessToken($accessToken->getToken());
            $resource->setRefreshToken($accessToken->getRefreshToken());
            $resource->setExpiration($accessToken->getExpires());
        } catch (Exception $e) {
            // The call was wrongly performed.
            $resource->setErrorCode("refresh_token_error");
            $resource->setErrorDescription($e->getMessage());
            return [];
        }
    }

    try {
        // Obtain the activity data from FITBIT
        $baseUrl = Fitbit::BASE_FITBIT_API_URL . '/1/user/-/activities/steps/date/' . $startDate . '/' . $endDate . '.json';
        $request = Fitbit::getProvider()->getAuthenticatedRequest(Fitbit::METHOD_GET, $baseUrl, $accessToken,
                ['headers' => [Fitbit::HEADER_ACCEPT_LOCALE => $locale]]);

        $response = Fitbit::getProvider()->getParsedResponse($request);
    } catch (Exception $e) {
        // Failed to perform the request.
        $resource->setErrorCode("request_error");
        $resource->setErrorDescription($e->getMessage());
    }

    if (!$response || !$response['activities-steps']) {
        if ($resource->getErrorCode() == null) {
            $resource->setErrorCode('unknown_error');
        }
        if ($resource->getErrorDescription() == null) {
            $resource->setErrorDescription('Some error happened when requesting activity information.');
        }
        return [];
    }

    return $response['activities-steps'];
}

/**
 * Obtain intraday activity data for a specific day.
 * Returned data is breakdown in the specified period series. It will have the following format:
 * <ul>
 * <li>'[{ "time": "00:00:00", "value": 0 },</li>
 * <li>'{ "time": "00:01:00", "value": 287 },</li>
 * <li>'{ "time": "00:02:00", "value": 287 }]</li>
 * </ul>
 *
 * Available breakdown periods:
 * <ul>
 * <li>15min</li>
 * <li>5min</li>
 * <li>1min (default value)</li>
 * </ul>
 *
 * Possible error codes returned in FitbitResource:
 * <ul>
 * <li>refresh_token_error: there was an error while refreshing the expired token.</li>
 * <li>request_error: there was an error while obtaining the activity data.</li>
 * <li>permissions_error: the application used to request the data doesn't have enough permissions to request intraday activity data.</li>
 * <li>unknown_error: the obtained data has changed its format or there was an uncaught error.</li>
 * </ul>
 * Access to the Intraday Time Series: https://dev.fitbit.com/build/reference/web-api/intraday-requests/
 *
 * @param FitbitResource $resource
 * @param string $date
 * @param string $breakdownPeriod
 * @param string $locale
 * @return array
 */
function getDetailedActivity(FitbitResource $resource, $date, $breakdownPeriod, $locale = 'es_ES') {
    // ONLY FOR DEBUG
    // $steps = [['time' => '09:10', 'value' => '300'], ['time' => '09:15', 'value' => '400'], ['time' => '10:22', 'value' => '500'],
    // ['time' => '11:02', 'value' => '600']];
    // return $steps;
    if (!$date) {
        $date = 'today';
    }

    $accessToken = new AccessToken(['access_token' => $resource->getAccessToken(), 'refresh_token' => $resource->getRefreshToken(),
            'expires_in' => ($resource->getExpiration() - time())]);

    if ($accessToken->hasExpired()) {
        try {
            $accessToken = refreshToken($resource->getRefreshToken());
            $resource->setAccessToken($accessToken->getToken());
            $resource->setRefreshToken($accessToken->getRefreshToken());
            $resource->setExpiration($accessToken->getExpires());
        } catch (Exception $e) {
            // The call was wrongly performed.
            $resource->setErrorCode("refresh_token_error");
            $resource->setErrorDescription($e->getMessage());
            return [];
        }
    }

    try {
        if (!$breakdownPeriod) {
            $baseUrl = Fitbit::BASE_FITBIT_API_URL . '/1/user/-/activities/steps/date/' . $date . '/1d.json';
        } else {
            $baseUrl = Fitbit::BASE_FITBIT_API_URL . '/1/user/-/activities/steps/date/' . $date . '/1d/' . $breakdownPeriod . '.json';
        }
        var_dump($baseUrl);
        // Obtain the activity data from FITBIT
        $request = Fitbit::getProvider()->getAuthenticatedRequest(Fitbit::METHOD_GET, $baseUrl, $accessToken,
                ['headers' => [Fitbit::HEADER_ACCEPT_LOCALE => $locale]]);

        $response = Fitbit::getProvider()->getParsedResponse($request);
    } catch (Exception $e) {
        // Failed to perform the request.
        $resource->setErrorCode("request_error");
        $resource->setErrorDescription($e->getMessage());
    }
    var_dump($response['activities-steps-intraday']['dataset']);
    if (!$response || !$response['activities-steps-intraday']['dataset']) {
        if ($response['activities-steps'] && !$response['activities-steps-intraday']['dataset']) {
            $resource->setErrorCode("permissions_error");
            $resource->setErrorDescription('Insufficient permissions for intraday activity data. See "Access to the Intraday Time Series".');
        } else {
            if ($resource->getErrorCode() == null) {
                $resource->setErrorCode('unknown_error');
            }
            if ($resource->getErrorDescription() == null) {
                $resource->setErrorDescription('Some error happened when requesting activity information.');
            }
        }
        return [];
    }

    return $response['activities-steps-intraday']['dataset'];
}
?>
