<?php

// Link the config params
require_once ("lib/default_conf.php");

setSystemTimeZone();

error_reporting(0);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $result = '';
    $action = $_POST['action'];
    switch ($action) {
        case 'autorize' :
            $result = storeAuthorization($_POST["token"]);
            break;
    }
    header('Content-type: application/json');
    echo $result;
}
