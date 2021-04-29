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

    $accessToken = new AccessToken(['access_token' => $resource->getAccessToken(), 'refresh_token' => $resource->getRefreshToken(),
            'expires_in' => ($resource->getExpiration() - time())]);

    if ($accessToken->hasExpired()) {
        /* TODO: Rubén, ¿qué pasa si falla? Se deberían hacer $resource->setErrorCode() y $resource->setErrorDescription() con los datos del error */
        $accessToken = refreshToken(Fitbit::getProvider(), $resource->getRefreshToken());
        $resource->setAccessToken($accessToken->getToken());
        $resource->setRefreshToken($accessToken->getRefreshToken());
        $resource->setExpiration($accessToken->getExpires());
    }

    // Obtain the activity data from FITBIT
    $baseUrl = Fitbit::BASE_FITBIT_API_URL . '/1/user/-/activities/steps/date/' . $startDate . '/' . $endDate . '.json';
    $request = Fitbit::getProvider()->getAuthenticatedRequest(Fitbit::METHOD_GET, $baseUrl, $accessToken,
            ['headers' => [Fitbit::HEADER_ACCEPT_LOCALE => $locale]]);

    /*
     * TODO: Rubén, ¿qué pasa si falla? aunque no es probable que pase, deberíamos controlar un posible error.
     * Se podría generar a mano un $request incorrecto para ver qué pasa y asegurar que no se genere una excepción descontrolada
     * y que nos damos cuenta de que algo va mal.
     * Se deberían hacer $resource->setErrorCode() y $resource->setErrorDescription() con los datos del error
     */
    $response = Fitbit::getProvider()->getParsedResponse($request);
    if (!$response) {
        $response = [];
    }

    return $response['activities-steps'];
}
?>