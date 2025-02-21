#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

function forwardToDatabaseVM($request) {
    // Connect to Database VM's RabbitMQ Server
    $dbClient = new rabbitMQClient("databaseRabbitMQ.ini", "databaseServer");

    // Send login request to the Database VM
    $response = $dbClient->send_request($request);

    return $response;
}

function requestProcessor($request) {
    echo "Received request from Web Server" . PHP_EOL;
    var_dump($request);

    if (!isset($request['type'])) {
        return ["status" => "error", "message" => "Unsupported request type"];
    }

    switch ($request['type']) {
        case "login":
            echo "Forwarding request to Database VM: " . json_encode($request) . PHP_EOL;
            return forwardToDatabaseVM($request);
        default:
            return ["status" => "error", "message" => "Unknown request type"];
    }
}

// Create a RabbitMQ Server
$server = new rabbitMQServer("testRabbitMQ.ini", "testServer");

// **Fix Freezing Issue: Process Requests One by One**
while (true) {
    try {
        echo "Waiting for messages..." . PHP_EOL;
        $server->process_requests('requestProcessor');
    } catch (Exception $e) {
        echo "Error processing request: " . $e->getMessage() . PHP_EOL;
        sleep(2); // **Prevent CPU overload if RabbitMQ crashes**
    }
}

exit();
?>



