<?php
require_once 'lib/default_conf.php';

use FitbitOAuth2Client\Fitbit;

if (isset($_GET['task'])) {
    $authorizationUrl = Fitbit::getProvider()->getAuthorizationUrl(['state' => $_GET['task'], 'scope' => ['activity', 'settings', 'profile']]);

    // The prompt = 'login' parameter will force at the Fitbit side to log in
    header('Location: ' . $authorizationUrl . '&prompt=login');
    exit();
}
?>
