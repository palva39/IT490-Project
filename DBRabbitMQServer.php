#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('mysqlconnect.php');

echo "[DB RABBITMQ] 🚀 Database RabbitMQ Server is starting...\n";
error_log("[DB RABBITMQ] 🚀 Database RabbitMQ Server is starting...\n", 3, "/var/log/database_rabbitmq.log");

function requestProcessor($request) {
    echo "[DB RABBITMQ] 📩 Received request: " . json_encode($request) . "\n";
    error_log("[DB RABBITMQ] 📩 Received request: " . json_encode($request) . "\n", 3, "/var/log/database_rabbitmq.log");

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

function validateLogin($username, $password) {
    echo "[DB RABBITMQ] 🔍 Checking credentials for user: " . $username . "\n";
    error_log("[DB RABBITMQ] 🔍 Checking credentials for user: " . $username . "\n", 3, "/var/log/database_rabbitmq.log");

    $db = new mysqli("127.0.0.1", "testUser", "12345", "login");
    if ($db->connect_errno) {
        echo "[DB RABBITMQ] ❌ Database connection failed: " . $db->connect_error . "\n";
        error_log("[DB RABBITMQ] ❌ Database connection failed: " . $db->connect_error . "\n", 3, "/var/log/database_rabbitmq.log");
        return ["status" => "error", "message" => "Database connection failed"];
    }

    $stmt = $db->prepare("SELECT password FROM users WHERE username = ?");
    if (!$stmt) {
        echo "[DB RABBITMQ] ❌ SQL error preparing statement.\n";
        error_log("[DB RABBITMQ] ❌ SQL error preparing statement.\n", 3, "/var/log/database_rabbitmq.log");
        $db->close();
        return ["status" => "error", "message" => "Database error"];
    }

    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        echo "[DB RABBITMQ] ❌ User not found: " . $username . "\n";
        error_log("[DB RABBITMQ] ❌ User not found: " . $username . "\n", 3, "/var/log/database_rabbitmq.log");
        $stmt->close();
        $db->close();
        return ["status" => "error", "message" => "User not found"];
    }

    $stmt->bind_result($hashedPassword);
    $stmt->fetch();
    $stmt->close();

    if (password_verify($password, $hashedPassword)) {
        echo "[DB RABBITMQ] ✅ Login successful for user: " . $username . "\n";
        error_log("[DB RABBITMQ] ✅ Login successful for user: " . $username . "\n", 3, "/var/log/database_rabbitmq.log");
        $db->close();
        return [
            "status" => "success",
            "message" => "Login successful",
            "user_id" => $username,
            "token" => bin2hex(random_bytes(16))
        ];
    } else {
        error_log("[DB RABBITMQ] ❌ Incorrect password for user: " . $username . "\n", 3, "/var/log/database_rabbitmq.log");
        $db->close();
        return ["status" => "error", "message" => "Incorrect password"];
    }
}

// ✅ Start the Database VM RabbitMQ Listener
echo "[DB RABBITMQ] 🚀 Database RabbitMQ Listener is waiting for messages...\n";
error_log("[DB RABBITMQ] 🚀 Database RabbitMQ Listener is waiting for messages...\n", 3, "/var/log/database_rabbitmq.log");

$server = new rabbitMQServer("databaseRabbitMQ.ini", "databaseQueue");
$server->process_requests('requestProcessor');

exit();
?>
