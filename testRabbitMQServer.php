#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

// Enable logging
ini_set("log_errors", 1);
ini_set("error_log", "/var/log/database_rabbitmq.log");

// Persistent MySQL connection
function getDatabaseConnection() {
    static $db = null;
    if ($db === null) {
        $db = new mysqli("127.0.0.1", "testUser", "12345", "login");
        if ($db->connect_errno) {
            error_log("Database connection failed: " . $db->connect_error);
            return ["status" => "error", "message" => "Database connection failed: " . $db->connect_error];
        }
    }
    return $db;
}

function validateLogin($username, $password) {
    $db = getDatabaseConnection(); // Reuse the connection
    if (is_array($db)) {
        return $db; // If connection failed, return the error
    }

    $stmt = $db->prepare("SELECT password FROM users WHERE username = ?");
    if (!$stmt) {
        return ["status" => "error", "message" => "Database error"];
    }

    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        return ["status" => "error", "message" => "User not found"];
    }

    $stmt->bind_result($hashedPassword);
    $stmt->fetch();
    $stmt->close();

    if (password_verify($password, $hashedPassword)) {
        return [
            "status" => "success",
            "message" => "Login successful",
            "user_id" => $username,
            "token" => bin2hex(random_bytes(16))
        ];
    } else {
        return ["status" => "error", "message" => "Incorrect password"];
    }
}

function requestProcessor($request) {
    error_log("Received request in Database VM: " . json_encode($request) . PHP_EOL);
    echo "Processing request in Database VM\n";
    print_r($request);

    if (!isset($request['type'])) {
        return ["status" => "error", "message" => "Unsupported request type"];
    }

    switch ($request['type']) {
        case "login":
            return validateLogin($request['username'], $request['password']);
        default:
            return ["status" => "error", "message" => "Unknown request type"];
    }
}

// Start RabbitMQ Server
echo "Database VM RabbitMQ is waiting for messages...\n";
$server = new rabbitMQServer("databaseRabbitMQ.ini", "databaseServer");
$server->process_requests('requestProcessor');
exit();
?>
