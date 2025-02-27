#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

ini_set("log_errors", 1);
ini_set("error_log", "/var/log/rabbitmq_errors.log");

// ✅ Class to manage a single RabbitMQ connection to Database VM
class DatabaseConnection {
    private static $dbClient = null;

    public static function getClient() {
        if (self::$dbClient === null) {
            echo "[RABBITMQ VM] 🔴 Establishing persistent connection to Database VM...\n";
            error_log("[RABBITMQ VM] 🔴 Establishing persistent connection to Database VM...\n", 3, "/var/log/rabbitmq_errors.log");
            try {
                self::$dbClient = new rabbitMQClient("databaseRabbitMQ.ini", "databaseQueue");
            } catch (Exception $e) {
                error_log("[RABBITMQ VM] ❌ ERROR: Could not connect to Database VM - " . $e->getMessage(), 3, "/var/log/rabbitmq_errors.log");
                return null;
            }
        }
        return self::$dbClient;
    }

    // ✅ Close connection after response is received
    public static function closeConnection() {
        if (self::$dbClient !== null) {
            self::$dbClient = null;
            error_log("[RABBITMQ VM] 🔴 Connection to Database VM CLOSED.\n", 3, "/var/log/rabbitmq_errors.log");
        }
    }
}

// ✅ Forward request to Database VM and WAIT for a proper response
function forwardToDatabaseVM($request) {
    echo "[RABBITMQ VM] 📤 Forwarding request to Database VM: " . json_encode($request) . "\n";
    error_log("[RABBITMQ VM] 📤 Forwarding request to Database VM: " . json_encode($request) . "\n", 3, "/var/log/rabbitmq_errors.log");

    try {
        $dbClient = DatabaseConnection::getClient();
        if (!$dbClient) {
            return ["status" => "error", "message" => "Database connection unavailable"];
        }

        // ✅ Wait for the correct response from Database VM
        $response = $dbClient->send_request($request);

        echo "[RABBITMQ VM] 📬 Received response from Database VM: " . json_encode($response) . "\n";
        error_log("[RABBITMQ VM] 📬 Received response from Database VM: " . json_encode($response) . "\n", 3, "/var/log/rabbitmq_errors.log");

        // ✅ Ensure the response is properly formatted
        if (!is_array($response) || !isset($response['status'])) {
            error_log("[RABBITMQ VM] ❌ ERROR: Invalid response received from Database VM!\n", 3, "/var/log/rabbitmq_errors.log");
            return ["status" => "error", "message" => "Invalid response from Database VM"];
        }

        // ✅ Send back correct response
        DatabaseConnection::closeConnection();
        return $response;
    } catch (Exception $e) {
        echo "[RABBITMQ VM] ❌ ERROR: Failed to communicate with Database VM - " . $e->getMessage() . "\n";
        error_log("[RABBITMQ VM] ❌ ERROR: Failed to communicate with Database VM - " . $e->getMessage(), 3, "/var/log/rabbitmq_errors.log");
        return ["status" => "error", "message" => "Failed to communicate with Database VM"];
    }
}

// ✅ Process requests and ensure the correct response is sent
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

// ✅ Start RabbitMQ Server and keep it running
echo "[RABBITMQ VM] 🚀 RabbitMQ Server is waiting for messages...\n";
error_log("[RABBITMQ VM] 🚀 RabbitMQ Server is waiting for messages...\n", 3, "/var/log/rabbitmq_errors.log");

$server = new rabbitMQServer("testRabbitMQ.ini", "loginQueue");
$server->process_requests('requestProcessor');

echo "[RABBITMQ VM] 🛑 Server shutting down...\n";
error_log("[RABBITMQ VM] 🛑 Server shutting down...\n", 3, "/var/log/rabbitmq_errors.log");

exit();
?>



