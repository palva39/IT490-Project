<?php
require_once('/home/paa39/git/IT490-Project/rabbitMQLib.inc');

header("Content-Type: application/json");

ini_set("log_errors", 1);
ini_set("error_log", "/var/log/php_errors.log");

session_start();

class RabbitMQConnection {
    private static $client = null;

    public static function getClient() {
        if (self::$client === null) {
            error_log("[RABBITMQ] 🟢 Establishing RabbitMQ connection to Broker VM (NOT DIRECTLY TO RABBITMQ)...");
            try {
                // ✅ Connect to RabbitMQ Broker, NOT directly to RabbitMQ
                self::$client = new rabbitMQClient("testRabbitMQ.ini", "loginQueue");  // 🚀 Use loginQueue instead of registerQueue
            } catch (Exception $e) {
                error_log("[RABBITMQ] ❌ ERROR: Could not connect to RabbitMQ Broker - " . $e->getMessage());
                return null;
            }
        } else {
            error_log("[RABBITMQ] 🟢 Using EXISTING RabbitMQ Broker connection...");
        }
        return self::$client;
    }

    public static function closeClient() {
        if (self::$client !== null) {
            error_log("[RABBITMQ] 🔴 Closing RabbitMQ connection...");
            self::$client = null;
        }
    }
}

// ✅ Capture user registration attempt
error_log("[REGISTER] 📩 Request received: " . json_encode($_POST));

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    exit();
}

// ✅ Extract and validate input
$firstName = trim($_POST['fname'] ?? '');
$lastName = trim($_POST['lname'] ?? '');
$dob = trim($_POST['dob'] ?? '');
$username = trim($_POST['uname'] ?? '');
$password = trim($_POST['pword'] ?? '');

// ✅ Password validation
if (!preg_match('/^(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $password)) {
    echo json_encode(["status" => "error", "message" => "Password must contain at least 8 characters, an uppercase letter, a number, and a special character."]);
    exit();
}

// ✅ Log the request before sending it to RabbitMQ
error_log("[REGISTER] 📤 Sending register request to RabbitMQ Broker: Username='" . $username . "'");

// ✅ Prepare RabbitMQ request
$request = [
    "type" => "register",
    "first_name" => $firstName,
    "last_name" => $lastName,
    "dob" => $dob,
    "username" => $username,
    "password" => password_hash($password, PASSWORD_BCRYPT)
];

try {
    // ✅ Get the persistent RabbitMQ connection to the Broker VM
    $client = RabbitMQConnection::getClient();
    if (!$client) {
        echo json_encode(["status" => "error", "message" => "Could not connect to RabbitMQ Broker"]);
        exit();
    }

    // ✅ Send the request and wait for a response
    $response = $client->send_request($request);

    error_log("[REGISTER] 📬 Received response from RabbitMQ Broker: " . json_encode($response));

    if (!isset($response['status'])) {
        echo json_encode(["status" => "error", "message" => "Unexpected response from registration server"]);
        exit();
    }

    if ($response['status'] === "success") {
        error_log("[REGISTER] ✅ User registered successfully!");
        echo json_encode(["status" => "success", "message" => "Registration successful"]);
    } else {
        error_log("[REGISTER] ❌ Registration failed: " . $response['message']);
        echo json_encode(["status" => "error", "message" => $response['message']]);

        RabbitMQConnection::closeClient();
    }

    exit();

} catch (Exception $e) {
    error_log("[REGISTER] ❌ ERROR: RabbitMQ Broker Connection Failed - " . $e->getMessage());
    echo json_encode(["status" => "error", "message" => "Error connecting to RabbitMQ Broker"]);

    RabbitMQConnection::closeClient();
    exit();
}
?>
