<?php
require_once 'lib/default_conf.php';

if (!isset($_GET['state'])) {
    // Missing field 'state' that will identify the TASK from which the authorization was requested
    exit('Missing "state" field.');
} else {
    $state = explode("/", $_GET['state']);
    $taskId = $state[0];
    $providerName = $state[1];
}

$scope = null;
if (!$providerName) {
    $providerName = $GLOBALS['DEFAULT_ACTIVITY_PROVIDER'];
}

$provider = ActivityProvider::getInstance($providerName);
if (isset($_GET['error']) && isset($_GET['error_description'])) {
    // An error has ocurred while getting the Fitbit permission.
    $oauthResource = new OauthResource(['errorCode' => $_GET['error'], 'errorDescription' => $_GET['error_description'], 'taskId' => $taskId], $provider);
} else {

    if (!isset($_GET['code'])) {
        // Missing field 'code' necessary for the Oauth process in order to obtain the tokens
        exit('Missing "code" field.');
    }

    try {
        $accessToken = $provider->getAccessToken('authorization_code', ['code' => $_GET['code']]);
    } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
        // Failed to get the access token.
        exit($e->getMessage());
    }

    $oauthResource = new OauthResource(['access_token' => $accessToken->getToken(), 'refresh_token' => $accessToken->getRefreshToken(),
            'expiration' => $accessToken->getExpires(), 'taskId' => $taskId], $provider);

    $additionalValues = $accessToken->getValues();
    $providerScope = is_array($additionalValues) ? $additionalValues['scope'] ?? '': '';
    $scope = $provider->normalizeScopes($providerScope);
}
$LC2redirect = storeAuthorizationUrl($oauthResource, $scope);

header('Location: ' . $LC2redirect);
exit();
?>