#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

// ✅ Log startup
error_log("[RABBITMQ VM] 🚀 RabbitMQ Broker is running and waiting for messages..." . PHP_EOL, 3, "/var/log/rabbitmq_errors.log");
echo "[RABBITMQ VM] 🚀 RabbitMQ Broker is running and waiting for messages..." . PHP_EOL;

function forwardToDatabaseVM($request) {
    error_log("[RABBITMQ VM] 📤 Forwarding request to Database VM Queue: " . json_encode($request) . PHP_EOL, 3, "/var/log/rabbitmq_errors.log");

    $dbClient = null;

    try {
        // ✅ Open a temporary connection per request
        error_log("[RABBITMQ VM] 🔴 Establishing temporary RabbitMQ connection to Database VM Queue..." . PHP_EOL, 3, "/var/log/rabbitmq_errors.log");
        $dbClient = new rabbitMQClient("databaseRabbitMQ.ini", "databaseQueue");

        // ✅ Send request to Database VM Queue and wait for response
        $response = $dbClient->send_request($request);

        error_log("[RABBITMQ VM] 📬 Received response from Database VM: " . json_encode($response) . PHP_EOL, 3, "/var/log/rabbitmq_errors.log");

        return $response;
    } catch (Exception $e) {
        error_log("[RABBITMQ VM] ❌ ERROR: Failed to communicate with Database VM - " . $e->getMessage() . PHP_EOL, 3, "/var/log/rabbitmq_errors.log");
        return ["status" => "error", "message" => "Failed to communicate with Database VM"];
    } finally {
        // ✅ Force Close Connection
        if ($dbClient !== null) {
            error_log("[RABBITMQ VM] 🔴 Closing RabbitMQ connection to Database VM after processing request..." . PHP_EOL, 3, "/var/log/rabbitmq_errors.log");
            $dbClient->disconnect();
            unset($dbClient);
        }
    }
}

// ✅ Process Requests
function requestProcessor($request) {
    error_log("[RABBITMQ VM] 📩 Processing request: " . json_encode($request) . PHP_EOL, 3, "/var/log/rabbitmq_errors.log");

    if (!isset($request['type'])) {
        return ["status" => "error", "message" => "Unsupported request type"];
    }

    return match ($request['type']) {
        "login" => forwardToDatabaseVM($request),
        default => ["status" => "error", "message" => "Unknown request type"]
    };
}

// ✅ Start RabbitMQ Server
$server = new rabbitMQServer("testRabbitMQ.ini", "loginQueue");

try {
    $server->process_requests('requestProcessor');
} catch (Exception $e) {
    error_log("[RABBITMQ VM] ❌ ERROR: Request Processing Failed - " . $e->getMessage() . PHP_EOL, 3, "/var/log/rabbitmq_errors.log");
} finally {
    // ✅ Force Close RabbitMQ Connection
    error_log("[RABBITMQ VM] 🔴 FORCE CLOSING RabbitMQ Broker Connection..." . PHP_EOL, 3, "/var/log/rabbitmq_errors.log");
    $server->disconnect();
    unset($server);
}
exit();
?>



