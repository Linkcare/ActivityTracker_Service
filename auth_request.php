<?php
require_once 'lib/default_conf.php';

use FitbitOAuth2Client\Fitbit;

if (isset($_GET['task'])) {
    $authorizationUrl = Fitbit::getProvider()->getAuthorizationUrl(['state' => $_GET['task'], 'scope' => ['activity', 'settings']]);

    header('Location: ' . $authorizationUrl);
    exit();
}
?>
