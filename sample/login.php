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
            error_log("[RABBITMQ] 🔴 Establishing NEW RabbitMQ connection to Broker VM...");
            try {
                self::$client = new rabbitMQClient("testRabbitMQ.ini", "loginQueue");
            } catch (Exception $e) {
                error_log("[RABBITMQ] ❌ ERROR: Could not connect to RabbitMQ Broker - " . $e->getMessage());
                return null;
            }
        } else {
            error_log("[RABBITMQ] 🟢 Using EXISTING RabbitMQ connection...");
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

// ✅ Log session details
error_log("[SESSION] 🔍 Session Started - Session ID: " . session_id());

// ✅ Capture user login attempt
error_log("[LOGIN] 📩 Request received: " . json_encode($_POST));

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    exit();
}

// ✅ Extract and validate input
$username = isset($_POST['uname']) ? trim($_POST['uname']) : null;
$password = isset($_POST['pword']) ? trim($_POST['pword']) : null;

if (empty($username) || empty($password)) {
    echo json_encode(["status" => "error", "message" => "Please enter both username and password"]);
    exit();
}

// ✅ Log the request before sending it to RabbitMQ
error_log("[LOGIN] 📤 Sending login request to RabbitMQ Queue: Username='" . $username . "', Password='[HIDDEN]'");

// ✅ Prepare RabbitMQ request
$request = [
    "type" => "login",
    "username" => $username,
    "password" => $password
];

try {
    // ✅ Get the persistent RabbitMQ connection to the Broker VM
    $client = RabbitMQConnection::getClient();
    if (!$client) {
        echo json_encode(["status" => "error", "message" => "Could not connect to RabbitMQ"]);
        exit();
    }

    // ✅ Send the request and wait for a response
    $response = $client->send_request($request);

    error_log("[LOGIN] 📬 Received response from RabbitMQ Broker: " . json_encode($response));

    // ✅ Ensure response is valid
    if (!isset($response['status'])) {
        echo json_encode(["status" => "error", "message" => "Unexpected response from authentication server"]);
        exit();
    }

    // ✅ Handle login responses
    if ($response['status'] === "success") {
        $_SESSION['username'] = $username;  // ✅ Store in session
        $_SESSION['logged_in'] = true;

        error_log("[LOGIN] ✅ User authenticated successfully! Redirecting...");

        echo json_encode([
            "status" => "success",
            "message" => "Login successful",
            "user_id" => $response['user_id'],
            "token" => $response['token']
        ]);
    } else {
        error_log("[LOGIN] ❌ Login failed: " . $response['message']);
        echo json_encode(["status" => "error", "message" => $response['message']]);

        RabbitMQConnection::closeClient();
    }

    exit();

} catch (Exception $e) {
    error_log("[LOGIN] ❌ ERROR: RabbitMQ Connection Failed - " . $e->getMessage());
    echo json_encode(["status" => "error", "message" => "Error connecting to RabbitMQ"]);

    RabbitMQConnection::closeClient();
    exit();
}
?>
