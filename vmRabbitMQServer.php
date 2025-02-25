#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

ini_set("log_errors", 1);
ini_set("error_log", "/var/log/rabbitmq_server.log");

// ✅ Maintain a single connection to the Database VM
class DatabaseConnection {
    private static $dbClient = null;

    public static function getClient() {
        if (self::$dbClient === null) {
            error_log("[RABBITMQ SERVER] Creating a single persistent connection to Database VM...");
            self::$dbClient = new rabbitMQClient("databaseRabbitMQ.ini", "databaseServer");
        }
        return self::$dbClient;
    }
}

function forwardToDatabaseVM($request) {
    error_log("[RABBITMQ SERVER] Forwarding request to Database VM: " . json_encode($request));

    try {
        $dbClient = DatabaseConnection::getClient(); // ✅ Use single connection
        $response = $dbClient->send_request($request);
        error_log("[RABBITMQ SERVER] Received response from Database VM: " . json_encode($response));
        return $response;
    } catch (Exception $e) {
        error_log("[RABBITMQ SERVER] ERROR: Failed to communicate with Database VM - " . $e->getMessage());
        return ["status" => "error", "message" => "Failed to communicate with Database VM"];
    }
}

function requestProcessor($request) {
    error_log("[RABBITMQ SERVER] Processing request: " . json_encode($request));
    return forwardToDatabaseVM($request);
}

// ✅ Start RabbitMQ Server
error_log("[RABBITMQ SERVER] Server is running and waiting for messages...");
$server = new rabbitMQServer("testRabbitMQ.ini", "testServer");
$server->process_requests('requestProcessor');
exit();
?>

