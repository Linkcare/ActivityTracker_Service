<?php
require_once 'lib/default_conf.php';

if (isset($_GET['task'])) {
    $providerName = $_GET['provider'];
    if (!$providerName) {
        $providerName = $GLOBALS['DEFAULT_ACTIVITY_PROVIDER'];
    }

    $provider = ActivityProvider::getInstance($providerName);

    // The prompt = 'login' parameter will force at the Fitbit side to log in
    $state = $_GET['task'] . '/' . $providerName;
    header('Location: ' . $provider->getAuthorizationUrl($state));
    exit();
}
?>
