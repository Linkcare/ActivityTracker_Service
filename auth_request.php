<?php
require_once 'lib/default_conf.php';

if (isset($_GET['task'])) {
    $service = $_GET['service'];
    if (!$service) {
        $service = $GLOBALS['DEFAULT_ACTIVITY_PROVIDER'];
    }

    $provider = ActivityProvider::getInstance($service);

    // The prompt = 'login' parameter will force at the Fitbit side to log in
    $state = $_GET['task'] . '/' . $service;
    header('Location: ' . $provider->getAuthorizationUrl($state));
    exit();
}
?>
