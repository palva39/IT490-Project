#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

// Enable logging for debugging
ini_set("log_errors", 1);
ini_set("error_log", "/var/log/rabbitmq_errors.log");

function forwardToDatabaseVM($request) {
    error_log("Forwarding request to Database VM: " . json_encode($request) . PHP_EOL);

    // Connect to Database VM's RabbitMQ
    $dbClient = new rabbitMQClient("databaseRabbitMQ.ini", "databaseServer");

    // Send login request to the Database VM
    $response = $dbClient->send_request($request);

    error_log("Received response from Database VM: " . json_encode($response) . PHP_EOL);
    
    return $response;
}

function requestProcessor($request) {
    error_log("Received request in RabbitMQ Server: " . json_encode($request) . PHP_EOL);
    echo "Received request from Web Server\n";
    print_r($request);

    if (!isset($request['type'])) {
        return ["status" => "error", "message" => "Unsupported request type"];
    }

    switch ($request['type']) {
        case "login":
            return forwardToDatabaseVM($request);
        default:
            return ["status" => "error", "message" => "Unknown request type"];
    }
}

// Start RabbitMQ Server
echo "RabbitMQ Server is waiting for messages...\n";
$server = new rabbitMQServer("testRabbitMQ.ini", "testServer");
$server->process_requests('requestProcessor');
exit();
?>



