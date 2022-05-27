<?php
require_once 'lib/default_conf.php';

if (isset($_GET['task'])) {
    if (isset($_GET['service'])) {
        $service = $_GET['service'];

        $provider = ActivityProvider::getInstance($service);

        // The prompt = 'login' parameter will force at the Fitbit side to log in
        $state = $_GET['task'] . '/' . $service;
        header('Location: ' . $provider->getAuthorizationUrl($state));
    }
    exit();
}
?>
