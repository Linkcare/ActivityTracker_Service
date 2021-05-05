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
    // $steps = [['dateTime' => '2021-05-03', 'value' => '3224'], ['dateTime' => '2021-05-04', 'value' => '2864'],
    // ['dateTime' => '2021-05-05', 'value' => '9230']];
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

    // Perform the request to the Fitbit API only if we have a valid token to do so
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
?>