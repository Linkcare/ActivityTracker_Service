<?php
require_once 'lib/default_conf.php';

$provider = new Fitbit(['clientId' => $GLOBALS['FITBIT_CLIENT_ID'], 'clientSecret' => $GLOBALS['FITBIT_CLIENT_SECRET'],
        'redirectUri' => $GLOBALS['FITBIT_REDIRECT_URI']]);
if (isset($_GET['admission'])) {
    $authorizationUrl = $provider->getAuthorizationUrl(['state' => $_GET['admission'], 'scope' => ['activity']]);

    header('Location: ' . $authorizationUrl);
    exit();
}
?>
