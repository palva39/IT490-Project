#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('mysqlconnect.php');

ini_set("log_errors", 1);
ini_set("error_log", "/var/log/rabbitmq_errors.log");

function requestProcessor($request) {
    echo "[DB RABBITMQ] 📩 Received request from queue: " . json_encode($request) . "\n";
    error_log("[DB RABBITMQ] 📩 Received request from queue: " . json_encode($request) . "\n", 3, "/var/log/database_rabbitmq.log");

    if (!isset($request['type'])) {
        return ["status" => "error", "message" => "Unsupported request type"];
    }

    switch ($request['type']) {
        case "login":
            return validateLogin($request['username'], $request['password']);  // ✅ Ensure function exists
        case "register":
            return registerUser($request);
        case "logout":
       	    return logoutUser($request);
        default:
            return ["status" => "error", "message" => "Unknown request type"];
    }
}

function validateLogin($username, $password) {
    echo "[DB RABBITMQ] 🔍 Checking credentials for user: " . $username . "\n";
    error_log("[DB RABBITMQ] 🔍 Checking credentials for user: " . $username . "\n", 3, "/var/log/database_rabbitmq.log");

    $db = new mysqli("127.0.0.1", "testUser", "12345", "login");

    if ($db->connect_errno) {
        $errorMsg = "[DB RABBITMQ] ❌ Database connection failed: " . $db->connect_error;
        echo $errorMsg . "\n";
        error_log($errorMsg . "\n", 3, "/var/log/database_rabbitmq.log");
        return ["status" => "error", "message" => "Database connection failed"];
    }

    // ✅ Fetch hashed password from the database
    $stmt = $db->prepare("SELECT password FROM users WHERE username = ?");
    if (!$stmt) {
        error_log("[DB RABBITMQ] ❌ SQL error preparing statement.\n", 3, "/var/log/database_rabbitmq.log");
        return ["status" => "error", "message" => "Database error"];
    }

    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        $stmt->close();
        $db->close();
        return ["status" => "error", "message" => "User not found"];
    }

    $stmt->bind_result($hashedPassword);
    $stmt->fetch();
    $stmt->close();

    // ✅ Check if the password is correct
    if (!password_verify($password, $hashedPassword)) {
        $db->close();
        return ["status" => "error", "message" => "Incorrect password"];
    }

    // ✅ Generate a session key and set expiration time (1 hour)
    $sessionKey = bin2hex(random_bytes(32));  // 64-character session key
    $sessionExpiration = date("Y-m-d H:i:s", strtotime("+1 hour"));  // Expire in 1 hour

    // ✅ Store session key in the database
    $stmt = $db->prepare("UPDATE users SET session_key = ?, session_expires = ? WHERE username = ?");
    if (!$stmt) {
        $db->close();
        return ["status" => "error", "message" => "Failed to create session"];
    }
    $stmt->bind_param("sss", $sessionKey, $sessionExpiration, $username);
    $stmt->execute();
    $stmt->close();
    $db->close();

    error_log("[DB RABBITMQ] ✅ Login successful! Session key stored.\n", 3, "/var/log/database_rabbitmq.log");

    return [
        "status" => "success",
        "message" => "Login successful",
        "user_id" => $username,
        "session_key" => $sessionKey,
        "expires_at" => $sessionExpiration
    ];
}

// ✅ Register New User
function registerUser($data) {
    $username = $data['username'];
    $password = $data['password']; // Already hashed before sending
    $firstName = $data['first_name'];
    $lastName = $data['last_name'];
    $dob = $data['dob'];

    $db = new mysqli("127.0.0.1", "testUser", "12345", "login");

    if ($db->connect_errno) {
        return ["status" => "error", "message" => "Database connection failed"];
    }

    // ✅ Check if username already exists
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        $stmt->close();
        $db->close();
        return ["status" => "error", "message" => "Username already exists"];
    }
    $stmt->close();

    // ✅ Insert new user
    $stmt = $db->prepare("INSERT INTO users (username, password, first_name, last_name, dob, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    if (!$stmt) {
        return ["status" => "error", "message" => "Database error"];
    }

    $stmt->bind_param("sssss", $username, $password, $firstName, $lastName, $dob);
    if ($stmt->execute()) {
        $stmt->close();
        $db->close();
        return ["status" => "success", "message" => "User registered successfully"];
    } else {
        $stmt->close();
        $db->close();
        return ["status" => "error", "message" => "User registration failed"];
    }
}

function logoutUser($data) {
    if (!isset($data['username'])) {
        return ["status" => "error", "message" => "Invalid logout request"];
    }

    $username = $data['username'];

    echo "[DB RABBITMQ] 🔴 Logging out user: " . $username . "\n";
    error_log("[DB RABBITMQ] 🔴 Logging out user: " . $username . "\n", 3, "/var/log/database_rabbitmq.log");

    $db = new mysqli("127.0.0.1", "testUser", "12345", "login");

    if ($db->connect_errno) {
        return ["status" => "error", "message" => "Database connection failed"];
    }

    // ✅ Clear session key and expiration from database
    $stmt = $db->prepare("UPDATE users SET session_key = NULL, session_expires = NULL WHERE username = ?");
    if (!$stmt) {
        $db->close();
        return ["status" => "error", "message" => "Database error"];
    }

    $stmt->bind_param("s", $username);
    if ($stmt->execute()) {
        $stmt->close();
        $db->close();
        return ["status" => "success", "message" => "User logged out successfully"];
    } else {
        $stmt->close();
        $db->close();
        return ["status" => "error", "message" => "Logout failed"];
    }
}

// ✅ Start RabbitMQ Database Listener
echo "[DB RABBITMQ] 🚀 Database RabbitMQ Listener is waiting for messages...\n";
error_log("[DB RABBITMQ] 🚀 Database RabbitMQ Listener is waiting for messages...\n", 3, "/var/log/database_rabbitmq.log");

$server = new rabbitMQServer("databaseRabbitMQ.ini", "databaseQueue");
$server->process_requests('requestProcessor');
exit();
?>
