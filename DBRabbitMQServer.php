#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('mysqlconnect.php');

ini_set("log_errors", 1);
ini_set("error_log", "/var/log/database_rabbitmq.log");

// ✅ Log that the server has started
echo "[DB RABBITMQ] 🚀 Database RabbitMQ Listener is waiting for messages...\n";
error_log("[DB RABBITMQ] 🚀 Database RabbitMQ Listener is waiting for messages...\n", 3, "/var/log/database_rabbitmq.log");

// ✅ Process messages from RabbitMQ Queue
function requestProcessor($request) {
    echo "[DB RABBITMQ] 📩 Received request from queue: " . json_encode($request) . "\n";
    error_log("[DB RABBITMQ] 📩 Received request from queue: " . json_encode($request) . "\n", 3, "/var/log/database_rabbitmq.log");

    if (!isset($request['type'])) {
        return ["status" => "error", "message" => "Unsupported request type"];
    }

    switch ($request['type']) {
        case "login":
            $response = validateLogin($request['username'], $request['password']);

            // ✅ Log that response is being sent
            echo "[DB RABBITMQ] 📤 Sending response to RabbitMQ Broker: " . json_encode($response) . "\n";
            error_log("[DB RABBITMQ] 📤 Sending response to RabbitMQ Broker: " . json_encode($response) . "\n", 3, "/var/log/database_rabbitmq.log");

            return $response;
        default:
            return ["status" => "error", "message" => "Unknown request type"];
    }
}

// ✅ Validate login credentials and send response back
function validateLogin($username, $password) {
    error_log("[DB RABBITMQ] 🔍 Checking credentials for user: " . $username . "\n", 3, "/var/log/database_rabbitmq.log");

    $db = new mysqli("127.0.0.1", "testUser", "12345", "login");
    if ($db->connect_errno) {
        error_log("[DB RABBITMQ] ❌ Database connection failed: " . $db->connect_error . "\n", 3, "/var/log/database_rabbitmq.log");
        return ["status" => "error", "message" => "Database connection failed"];
    }

    // ✅ Prepare the SQL statement
    $stmt = $db->prepare("SELECT password FROM users WHERE username = ?");
    if (!$stmt) {
        error_log("[DB RABBITMQ] ❌ SQL Error: Failed to prepare statement\n", 3, "/var/log/database_rabbitmq.log");
        $db->close();
        return ["status" => "error", "message" => "Database error"];
    }

    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    // ✅ Check if user exists
    if ($stmt->num_rows === 0) {
        error_log("[DB RABBITMQ] ❌ User not found: " . $username . "\n", 3, "/var/log/database_rabbitmq.log");
        $stmt->close();
        $db->close();
        return ["status" => "error", "message" => "User not found"];
    }

    $stmt->bind_result($hashedPassword);
    $stmt->fetch();
    $stmt->close();

    // ✅ Verify password
    if (password_verify($password, $hashedPassword)) {
        error_log("[DB RABBITMQ] ✅ Login successful for user: " . $username . "\n", 3, "/var/log/database_rabbitmq.log");
        $db->close();

        // ✅ Generate session token
        $sessionToken = bin2hex(random_bytes(16));

        return [
            "status" => "success",
            "message" => "Login successful",
            "user_id" => $username,
            "token" => $sessionToken
        ];
    } else {
        error_log("[DB RABBITMQ] ❌ Incorrect password for user: " . $username . "\n", 3, "/var/log/database_rabbitmq.log");
        $db->close();
        return ["status" => "error", "message" => "Incorrect password"];
    }
}

// ✅ Start the RabbitMQ Server and process requests
$server = new rabbitMQServer("databaseRabbitMQ.ini", "databaseQueue");
$server->process_requests('requestProcessor');

echo "[DB RABBITMQ] 🛑 Server shutting down...\n";
error_log("[DB RABBITMQ] 🛑 Server shutting down...\n", 3, "/var/log/database_rabbitmq.log");

exit();
?>
