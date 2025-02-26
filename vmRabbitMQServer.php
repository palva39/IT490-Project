#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

function forwardToDatabaseVM($request) {
    echo "[RABBITMQ VM] 📤 Forwarding request to Database VM: " . json_encode($request) . PHP_EOL;
    error_log("[RABBITMQ VM] 📤 Forwarding request to Database VM: " . json_encode($request), 3, "/var/log/rabbitmq_errors.log");

    try {
        // ✅ Create a new RabbitMQ Client for every request
        echo "[RABBITMQ VM] 🔴 Creating NEW connection to Database VM..." . PHP_EOL;
        error_log("[RABBITMQ VM] 🔴 Creating NEW connection to Database VM...", 3, "/var/log/rabbitmq_errors.log");

        $dbClient = new rabbitMQClient("databaseRabbitMQ.ini", "databaseQueue");
        
        // ✅ Send request to Database VM
        $response = $dbClient->send_request($request);
        
        echo "[RABBITMQ VM] 📬 Received response from Database VM: " . json_encode($response) . PHP_EOL;
        error_log("[RABBITMQ VM] 📬 Received response from Database VM: " . json_encode($response), 3, "/var/log/rabbitmq_errors.log");

        // ✅ Close connection after request is completed
        unset($dbClient);
        echo "[RABBITMQ VM] 🔴 Connection to Database VM CLOSED." . PHP_EOL;
        error_log("[RABBITMQ VM] 🔴 Connection to Database VM CLOSED.", 3, "/var/log/rabbitmq_errors.log");

        return $response;
    } catch (Exception $e) {
        echo "[RABBITMQ VM] ❌ ERROR: Failed to communicate with Database VM - " . $e->getMessage() . PHP_EOL;
        error_log("[RABBITMQ VM] ❌ ERROR: Failed to communicate with Database VM - " . $e->getMessage(), 3, "/var/log/rabbitmq_errors.log");
        return ["status" => "error", "message" => "Failed to communicate with Database VM"];
    }
}

function requestProcessor($request) {
    echo "[RABBITMQ VM] 📩 Processing request: " . json_encode($request) . PHP_EOL;
    error_log("[RABBITMQ VM] 📩 Processing request: " . json_encode($request), 3, "/var/log/rabbitmq_errors.log");

    if (!isset($request['type'])) {
        return ["status" => "error", "message" => "Unsupported request type"];
    }

    return match ($request['type']) {
        "login" => forwardToDatabaseVM($request),
        default => ["status" => "error", "message" => "Unknown request type"]
    };
}

// ✅ Start RabbitMQ Server
echo "[RABBITMQ VM] 🚀 RabbitMQ Server is waiting for messages..." . PHP_EOL;
error_log("[RABBITMQ VM] 🚀 RabbitMQ Server is waiting for messages...", 3, "/var/log/rabbitmq_errors.log");

$server = new rabbitMQServer("testRabbitMQ.ini", "testServer");
$server->process_requests('requestProcessor');
exit();
?>



