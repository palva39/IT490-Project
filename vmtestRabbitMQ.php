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

// ✅ Forward requests from RabbitMQ Broker to Database VM
function forwardToDatabaseVM($request) {
    echo "[RABBITMQ VM] 📤 Forwarding request to Database VM: " . json_encode($request) . "\n";
    error_log("[RABBITMQ VM] 📤 Forwarding request to Database VM: " . json_encode($request) . "\n", 3, "/var/log/rabbitmq_errors.log");

    try {
        $dbClient = DatabaseConnection::getClient();
        if (!$dbClient) {
            return ["status" => "error", "message" => "Database connection unavailable"];
        }

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

// ✅ Process login and registration requests
function requestProcessor($request) {
    echo "[RABBITMQ VM] 📩 Processing request: " . json_encode($request) . "\n";
    error_log("[RABBITMQ VM] 📩 Processing request: " . json_encode($request) . "\n", 3, "/var/log/rabbitmq_errors.log");

    if (!isset($request['type'])) {
        return ["status" => "error", "message" => "Unsupported request type"];
    }

    return match ($request['type']) {
        "login" => forwardToDatabaseVM($request),
        "register" => forwardToDatabaseVM($request),
        default => ["status" => "error", "message" => "Unknown request type"]
    };
}

// ✅ Start RabbitMQ Servers for both `loginQueue` and `registerQueue`
echo "[RABBITMQ VM] 🚀 RabbitMQ Server is waiting for messages...\n";
error_log("[RABBITMQ VM] 🚀 RabbitMQ Server is waiting for messages...\n", 3, "/var/log/rabbitmq_errors.log");

$loginServer = new rabbitMQServer("testRabbitMQ.ini", "loginQueue");
$registerServer = new rabbitMQServer("testRabbitMQ.ini", "registerQueue");

// ✅ Process requests for both queues
$pid1 = pcntl_fork();
if ($pid1 == 0) {
    $loginServer->process_requests("requestProcessor");
    exit();
}

$pid2 = pcntl_fork();
if ($pid2 == 0) {
    $registerServer->process_requests("requestProcessor");
    exit();
}

// ✅ Parent process waits for child processes
pcntl_wait($status);
pcntl_wait($status);

exit();
?>
