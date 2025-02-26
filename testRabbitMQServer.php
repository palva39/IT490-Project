#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('login.php.inc');

function doLogin($username, $password) {
    $login = new loginDB();
    return $login->validateLogin($username, $password);
}

function requestProcessor($request) {
    echo "Received request".PHP_EOL;
    var_dump($request);

    if (!isset($request['type'])) {
        return ["status" => "error", "message" => "Unsupported request type"];
    }

    switch ($request['type']) {
        case "login":
            return doLogin($request['username'], $request['password']);
        default:
            return ["status" => "error", "message" => "Unknown request type"];
    }
}

$server = new rabbitMQServer("testRabbitMQ.ini", "testServer");
$server->process_requests('requestProcessor');
exit();
?>
