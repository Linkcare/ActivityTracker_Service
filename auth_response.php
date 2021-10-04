<?php
require_once 'lib/default_conf.php';

use FitbitOAuth2Client\Fitbit;

if (!isset($_GET['state'])) {
    // Missing field 'state' that will identify the TASK from which the authorization was requested
    exit('Missing "state" field.');
}

if (isset($_GET['error']) && isset($_GET['error_description'])) {
    // An error has ocurred while getting the Fitbit permission.
    $fitbitResource = new FitbitResource(['errorCode' => $_GET['error'], 'errorDescription' => $_GET['error_description'], 'taskId' => $_GET['state']]);
} else {

    if (!isset($_GET['code'])) {
        // Missing field 'code' that Fitbit provides us with to obtain the tokens
        exit('Missing "code" field.');
    }

    try {
        $accessToken = Fitbit::getProvider()->getAccessToken('authorization_code', ['code' => $_GET['code']]);
    } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
        // Failed to get the access token.
        exit($e->getMessage());
    }

    $fitbitResource = new FitbitResource(['access_token' => $accessToken->getToken(), 'refresh_token' => $accessToken->getRefreshToken(),
            'expiration' => $accessToken->getExpires(), 'taskId' => $_GET['state']]);
}

if (isset($_GET['scope'])) {
    $LC2redirect = storeAuthorizarionUrl($fitbitResource, $_GET['scope']);
} else {
    $LC2redirect = storeAuthorizarionUrl($fitbitResource);
}

header('Location: ' . $LC2redirect);
exit();
?>