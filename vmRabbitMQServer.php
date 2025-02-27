#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

// ✅ Persistent RabbitMQ Connection to Database VM
class DatabaseConnection {
    private static $dbClient = null;

    public static function getClient() {
        if (self::$dbClient === null) {
            echo "[RABBITMQ VM] 🔴 Establishing persistent connection to Database VM...\n";
            error_log("[RABBITMQ VM] 🔴 Establishing persistent connection to Database VM...\n", 3, "/var/log/rabbitmq_errors.log");
            try {
                self::$dbClient = new rabbitMQClient("databaseRabbitMQ.ini", "databaseQueue");
            } catch (Exception $e) {
                error_log("[RABBITMQ VM] ❌ ERROR: Could not connect to Database VM - " . $e->getMessage() . "\n", 3, "/var/log/rabbitmq_errors.log");
                return null;
            }
        }
        return self::$dbClient;
    }
}

function forwardToDatabaseVM($request) {
    echo "[RABBITMQ VM] 📤 Forwarding request to Database VM: " . json_encode($request) . "\n";
    error_log("[RABBITMQ VM] 📤 Forwarding request to Database VM: " . json_encode($request) . "\n", 3, "/var/log/rabbitmq_errors.log");

    try {
        $dbClient = DatabaseConnection::getClient();
        if (!$dbClient) {
            return ["status" => "error", "message" => "Database connection unavailable"];
        }

        // ✅ Send request and retrieve response from Database VM
        $response = $dbClient->send_request($request);

        echo "[RABBITMQ VM] 📬 Received response from Database VM: " . json_encode($response) . "\n";
        error_log("[RABBITMQ VM] 📬 Received response from Database VM: " . json_encode($response) . "\n", 3, "/var/log/rabbitmq_errors.log");

        if (!is_array($response)) {
            echo "[RABBITMQ VM] ❌ ERROR: Invalid response format from Database VM\n";
            error_log("[RABBITMQ VM] ❌ ERROR: Invalid response format from Database VM\n", 3, "/var/log/rabbitmq_errors.log");
            return ["status" => "error", "message" => "Invalid response format from Database VM"];
        }

        return $response;
    } catch (Exception $e) {
        echo "[RABBITMQ VM] ❌ ERROR: Failed to communicate with Database VM - " . $e->getMessage() . "\n";
        error_log("[RABBITMQ VM] ❌ ERROR: Failed to communicate with Database VM - " . $e->getMessage() . "\n", 3, "/var/log/rabbitmq_errors.log");
        return ["status" => "error", "message" => "Failed to communicate with Database VM"];
    }
}

function requestProcessor($request) {
    echo "[RABBITMQ VM] 📩 Processing request: " . json_encode($request) . "\n";
    error_log("[RABBITMQ VM] 📩 Processing request: " . json_encode($request) . "\n", 3, "/var/log/rabbitmq_errors.log");

    if (!isset($request['type'])) {
        return ["status" => "error", "message" => "Unsupported request type"];
    }

    return match ($request['type']) {
        "login" => forwardToDatabaseVM($request),
        default => ["status" => "error", "message" => "Unknown request type"]
    };
}

// ✅ Start RabbitMQ Server
echo "[RABBITMQ VM] 🚀 RabbitMQ Server is waiting for messages...\n";
error_log("[RABBITMQ VM] 🚀 RabbitMQ Server is waiting for messages...\n", 3, "/var/log/rabbitmq_errors.log");

$server = new rabbitMQServer("testRabbitMQ.ini", "loginQueue");

// ✅ Correctly send the response back to `loginQueue_response`
$server->process_requests(function($request) {
    $response = requestProcessor($request);

    // ✅ Send response to `loginQueue_response`
    echo "[RABBITMQ SERVER] 📬 Sending response to `loginQueue_response`: " . json_encode($response) . "\n";
    error_log("[RABBITMQ SERVER] 📬 Sending response to `loginQueue_response`: " . json_encode($response) . "\n", 3, "/var/log/rabbitmq_errors.log");

    return $response;
});

exit();
?>



