<?php
require_once 'lib/default_conf.php';

use FitbitOAuth2Client\Fitbit;

$provider = new Fitbit(['clientId' => $GLOBALS['FITBIT_CLIENT_ID'], 'clientSecret' => $GLOBALS['FITBIT_CLIENT_SECRET'],
        'redirectUri' => $GLOBALS['FITBIT_REDIRECT_URI']]);
if (isset($_GET['task'])) {
    $authorizationUrl = $provider->getAuthorizationUrl(['state' => $_GET['task'], 'scope' => ['activity']]);

    header('Location: ' . $authorizationUrl);
    exit();
}
?>
