#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

function forwardToDatabaseVM($request) {
    error_log("Forwarding request to Database VM: " . json_encode($request) . PHP_EOL);

    try {
        // Always create a new connection to avoid stuck connections
        $dbClient = new rabbitMQClient("databaseRabbitMQ.ini", "databaseServer");

        $response = $dbClient->send_request($request);

        // Ensure connection is properly closed after request
        unset($dbClient);
        error_log("Received response from Database VM: " . json_encode($response) . PHP_EOL);

        return $response;
    } catch (Exception $e) {
        error_log("Error forwarding request to Database VM: " . $e->getMessage() . PHP_EOL);
        return ["status" => "error", "message" => "Failed to communicate with Database VM"];
    }
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


