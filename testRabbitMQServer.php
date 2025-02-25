#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('mysqlconnect.php');

ini_set("log_errors", 1);
ini_set("error_log", "/var/log/database_rabbitmq.log");

// ✅ Process messages from RabbitMQ
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

// ✅ Validate login credentials
function validateLogin($username, $password) {
    error_log("Checking login for username: " . $username . PHP_EOL);

    $db = new mysqli("127.0.0.1", "testUser", "12345", "login");
    if ($db->connect_errno) {
        error_log("Database connection failed: " . $db->connect_error);
        return ["status" => "error", "message" => "Database connection failed"];
    }

    // ✅ Normalize username (convert to lowercase and trim spaces)
    $username = trim(strtolower($username));

    $stmt = $db->prepare("SELECT password FROM users WHERE LOWER(username) = LOWER(?)");
    if (!$stmt) {
        error_log("Database query failed.");
        $db->close();
        return ["status" => "error", "message" => "Database error"];
    }

    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        error_log("User not found in database.");
        $stmt->close();
        $db->close();
        return ["status" => "error", "message" => "User not found"];
    }

    $stmt->bind_result($hashedPassword);
    $stmt->fetch();
    $stmt->close();

    if (password_verify($password, $hashedPassword)) {
        $db->close();
        error_log("User authenticated successfully.");
        return [
            "status" => "success",
            "message" => "Login successful",
            "user_id" => $username,
            "token" => bin2hex(random_bytes(16))
        ];
    } else {
        error_log("Incorrect password for user: " . $username);
        $db->close();
        return ["status" => "error", "message" => "Incorrect password"];
    }
}

// ✅ Start the RabbitMQ Server
echo "Database VM RabbitMQ is waiting for messages...\n";
$server = new rabbitMQServer("databaseRabbitMQ.ini", "databaseServer");
$server->process_requests('requestProcessor');
exit();
?>
