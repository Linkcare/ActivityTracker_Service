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
 * @param FitbitResource $resource
 * @param string $startDate date as 'yyyy-MM-dd'
 * @param string $endDate date as 'yyyy-MM-dd'
 * @param string $locale
 * @return array
 * @throws \League\OAuth2\Client\Provider\Exception\IdentityProviderException When the API request has failed
 */
function getActivityData(FitbitResource $resource, $startDate, $endDate, $locale = 'es_ES') {
    if (!$startDate) {
        $startDate = '2021-01-01';
    }
    if (!$endDate) {
        $endDate = 'today';
    }

    $accessToken = new AccessToken(['access_token' => $resource->getAccesToken(), 'refresh_token' => $resource->getRefreshToken(),
            'expires_in' => ($resource->getExpiration() - time())]);

    if ($accessToken->hasExpired()) {
        $accessToken = refreshToken(Fitbit::getProvider(), $resource->getRefreshToken());
        $resource->setAccessToken($accessToken->getToken());
        $resource->setRefreshToken($accessToken->getRefreshToken());
        $resource->setExpiration($accessToken->getExpires());
    }

    // Obtain the activity data from FITBIT
    $baseUrl = Fitbit::BASE_FITBIT_API_URL . '/1/user/-/activities/steps/date/' . $startDate . '/' . $endDate . '.json';
    $request = Fitbit::getProvider()->getAuthenticatedRequest(Fitbit::METHOD_GET, $baseUrl, $accessToken,
            ['headers' => [Fitbit::HEADER_ACCEPT_LOCALE => $locale]]);

    $response = Fitbit::getProvider()->getParsedResponse($request);

    return $response['activities-steps'];
}
?>