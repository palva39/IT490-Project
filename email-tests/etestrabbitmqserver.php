#!/usr/bin/php 
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('mysqlconnect.php');

ini_set("log_errors", 1);
ini_set("error_log", "/var/log/rabbitmq_errors.log");

// ✅ Process login, registration, and logout
function requestProcessor($request) {
    echo "[RABBITMQ VM] 📩 Processing request: " . json_encode($request) . "\n";
    error_log("[RABBITMQ VM] 📩 Processing request: " . json_encode($request) . "\n", 3, "/var/log/rabbitmq_errors.log");

    if (!isset($request['type'])) {
        return ["status" => "error", "message" => "Unsupported request type"];
    }

    return match ($request['type']) {
        "login" => validateLogin($request['username'], $request['password']),
        "register" => registerUser($request),
        "logout" => logoutUser($request),
        default => ["status" => "error", "message" => "Unknown request type"]
    };
}

// ✅ Validate user login credentials
function validateLogin($username, $password) {
    $db = new mysqli("127.0.0.1", "testUser", "12345", "login");

    if ($db->connect_errno) {
        return ["status" => "error", "message" => "Database connection failed"];
    }

    // ✅ Fetch password hash
    $stmt = $db->prepare("SELECT password FROM users WHERE username = ?");
    if (!$stmt) return ["status" => "error", "message" => "Database error"];

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

    if (!password_verify($password, $hashedPassword)) {
        $db->close();
        return ["status" => "error", "message" => "Incorrect password"];
    }

    // ✅ Generate and store session key
    $sessionKey = bin2hex(random_bytes(32));
    $sessionExpiration = date("Y-m-d H:i:s", strtotime("+1 hour"));

    $stmt = $db->prepare("UPDATE users SET session_key = ?, session_expires = ? WHERE username = ?");
    if (!$stmt) return ["status" => "error", "message" => "Failed to create session"];

    $stmt->bind_param("sss", $sessionKey, $sessionExpiration, $username);
    $stmt->execute();
    $stmt->close();
    $db->close();

    return [
        "status" => "success",
        "message" => "Login successful",
        "user_id" => $username,
        "session_key" => $sessionKey,
        "expires_at" => $sessionExpiration
    ];
}

// ✅ Register new user
function registerUser($data) {
    $db = new mysqli("127.0.0.1", "testUser", "12345", "login");

    if ($db->connect_errno) return ["status" => "error", "message" => "Database connection failed"];

    // ✅ Check if username already exists
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $data['username']);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        $stmt->close();
        $db->close();
        return ["status" => "error", "message" => "Username already exists"];
    }

    $stmt->close();

    // ✅ Insert new user
    $stmt = $db->prepare("INSERT INTO users (username, password, first_name, last_name, dob, email, created_at) 
                          VALUES (?, ?, ?, ?, ?, ?, NOW())");
    if (!$stmt) return ["status" => "error", "message" => "Database error"];

    $stmt->bind_param("ssssss", $data['username'], $data['password'], $data['first_name'], 
                      $data['last_name'], $data['dob'], $data['email']);
    
    if ($stmt->execute()) {
        $stmt->close();
        $db->close();

        // ✅ Send Welcome Email via RabbitMQ
        return sendWelcomeEmail($data['email'], $data['first_name']);
    } else {
        $stmt->close();
        $db->close();
        return ["status" => "error", "message" => "User registration failed"];
    }
}

// ✅ Send Welcome Email via RabbitMQ
function sendWelcomeEmail($email, $first_name) {
    require_once('rabbitMQLib.inc');

    try {
        $client = new rabbitMQClient("emailRabbitMQ.ini", "emailQueue");
        $request = [
            "type" => "send_email",
            "to" => $email,
            "subject" => "Welcome to IT490 Project!",
            "message" => "Hello $first_name, <br><br> 
                          Thank you for registering! We’re excited to have you on board. <br><br> 
                          Regards, <br> 
                          IT490 Team"
        ];
        
        $response = $client->send_request($request);

        if ($response && $response['status'] === "success") {
            return ["status" => "success", "message" => "User registered and email sent"];
        } else {
            return ["status" => "error", "message" => "User registered, but email failed"];
        }
    } catch (Exception $e) {
        return ["status" => "error", "message" => "User registered, but email request failed: " . $e->getMessage()];
    }
}

// ✅ Logout user (clear session key)
function logoutUser($data) {
    $db = new mysqli("127.0.0.1", "testUser", "12345", "login");

    if ($db->connect_errno) return ["status" => "error", "message" => "Database connection failed"];

    $stmt = $db->prepare("UPDATE users SET session_key = NULL, session_expires = NULL WHERE username = ?");
    if (!$stmt) return ["status" => "error", "message" => "Database error"];

    $stmt->bind_param("s", $data['username']);
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

// ✅ Start RabbitMQ Servers for `loginQueue` and `registerQueue`
echo "[RABBITMQ VM] 🚀 RabbitMQ Server is waiting for messages...\n";
error_log("[RABBITMQ VM] 🚀 RabbitMQ Server is waiting for messages...\n", 3, "/var/log/rabbitmq_errors.log");

$loginServer = new rabbitMQServer("testRabbitMQ.ini", "loginQueue");
$registerServer = new rabbitMQServer("testRabbitMQ.ini", "registerQueue");

// ✅ Process requests for both queues in child processes
$pid1 = pcntl_fork();
if ($pid1 == 0) {
    $loginServer->process_requests("requestProcessor");
    exit(0);  // ✅ Child exits after processing
}

$pid2 = pcntl_fork();
if ($pid2 == 0) {
    $registerServer->process_requests("requestProcessor");
    exit(0);  // ✅ Child exits after processing
}

// ✅ Parent process keeps running indefinitely
while (true) {
    pcntl_wait($status);
    sleep(1); // ✅ Prevent high CPU usage
}
