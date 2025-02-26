<?php
require_once('/home/paa39/git/IT490-Project/rabbitMQLib.inc');

header("Content-Type: application/json");

ini_set("log_errors", 1);
ini_set("error_log", "/var/log/php_errors.log");

session_start(); // ✅ Maintain session across requests

// ✅ Class to maintain a single RabbitMQ connection across the session
class RabbitMQConnection {
    public static function getClient() {
        if (!isset($_SESSION['rabbitmq_client'])) {
            error_log("[RABBITMQ] Creating a NEW RabbitMQ connection (FIRST login attempt only)...");
            $_SESSION['rabbitmq_client'] = new rabbitMQClient("testRabbitMQ.ini", "testServer");
            $_SESSION['rabbitmq_connected'] = true;  // ✅ Mark connection as active
        } else {
            error_log("[RABBITMQ] Using EXISTING RabbitMQ connection...");
        }
        return $_SESSION['rabbitmq_client'];
    }
}

error_log("[LOGIN] Request received: " . json_encode($_POST));

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    error_log("[LOGIN] ERROR: Invalid request method.");
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    exit();
}

// ✅ Extract and validate input
$username = isset($_POST['uname']) ? trim($_POST['uname']) : null;
$password = isset($_POST['pword']) ? trim($_POST['pword']) : null;

if (empty($username) || empty($password)) {
    error_log("[LOGIN] ERROR: Missing credentials.");
    echo json_encode(["status" => "error", "message" => "Please enter both username and password"]);
    exit();
}

// ✅ Log the request before sending it to RabbitMQ
error_log("[LOGIN] Sending to RabbitMQ: Username='" . $username . "', Password='[HIDDEN]'");

// ✅ Prepare RabbitMQ request
$request = [
    "type" => "login",
    "username" => $username,
    "password" => $password
];

try {
    // ✅ Get the persistent RabbitMQ connection (only created on first login)
    $client = RabbitMQConnection::getClient();

    // ✅ Send the request and get a response
    $response = $client->send_request($request);

    error_log("[LOGIN] Received response from RabbitMQ: " . json_encode($response));

    // ✅ Ensure response is valid
    if (!isset($response['status'])) {
        echo json_encode(["status" => "error", "message" => "Unexpected response from authentication server"]);
        exit();
    }

    // ✅ Handle login responses
    if ($response['status'] === "success") {
        echo json_encode([
            "status" => "success",
            "message" => "Login successful",
            "user_id" => $response['user_id'],
            "token" => $response['token']
        ]);
        exit();
    } elseif ($response['status'] === "error") {
        if ($response['message'] === "User not found") {
            echo json_encode(["status" => "error", "message" => "Username does not exist. Please register."]);
        } elseif ($response['message'] === "Incorrect password") {
            echo json_encode(["status" => "error", "message" => "Incorrect password. Please try again."]);
        } elseif ($response['message'] === "Database connection failed") {
            echo json_encode(["status" => "error", "message" => "Database is unavailable. Please try later."]);
        } else {
            echo json_encode(["status" => "error", "message" => "Login failed: " . $response['message']]);
        }
        exit();
    } else {
        echo json_encode(["status" => "error", "message" => "Unexpected response format"]);
        exit();
    }

} catch (Exception $e) {
    error_log("[LOGIN] ERROR: RabbitMQ Connection Failed - " . $e->getMessage());
    echo json_encode(["status" => "error", "message" => "Error connecting to RabbitMQ"]);
    
    // ✅ Close connection if error occurs
    unset($_SESSION['rabbitmq_client']);
    unset($_SESSION['rabbitmq_connected']);

    exit();
}
?>
<?php
require_once('/home/paa39/git/IT490-Project/rabbitMQLib.inc');

header("Content-Type: application/json");

ini_set("log_errors", 1);
ini_set("error_log", "/var/log/php_errors.log");

session_start(); // ✅ Maintain session to persist RabbitMQ connection

// ✅ Maintain a single RabbitMQ connection across the session
class RabbitMQConnection {
    public static function getClient() {
        if (!isset($_SESSION['rabbitmq_client'])) {
            error_log("[RABBITMQ] Creating a NEW RabbitMQ connection (First login attempt only)...");
            $_SESSION['rabbitmq_client'] = new rabbitMQClient("testRabbitMQ.ini", "testServer");
        } else {
            error_log("[RABBITMQ] Using EXISTING RabbitMQ connection...");
        }
        return $_SESSION['rabbitmq_client'];
    }
}

error_log("[LOGIN] Request received: " . json_encode($_POST));

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    error_log("[LOGIN] ERROR: Invalid request method.");
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    exit();
}

// ✅ Extract and validate input
$username = isset($_POST['uname']) ? trim($_POST['uname']) : null;
$password = isset($_POST['pword']) ? trim($_POST['pword']) : null;

if (empty($username) || empty($password)) {
    error_log("[LOGIN] ERROR: Missing credentials.");
    echo json_encode(["status" => "error", "message" => "Please enter both username and password"]);
    exit();
}

// ✅ Log the request before sending it to RabbitMQ
error_log("[LOGIN] Sending to RabbitMQ: Username='" . $username . "', Password='[HIDDEN]'");

// ✅ Prepare RabbitMQ request
$request = [
    "type" => "login",
    "username" => $username,
    "password" => $password
];

try {
    // ✅ Get persistent RabbitMQ connection (only created on first login)
    $client = RabbitMQConnection::getClient();

    // ✅ Send the request and get a response
    $response = $client->send_request($request);

    error_log("[LOGIN] Received response from RabbitMQ: " . json_encode($response));

    // ✅ Ensure response is valid
    if (!isset($response['status'])) {
        echo json_encode(["status" => "error", "message" => "Unexpected response from authentication server"]);
        exit();
    }

    // ✅ Handle login responses
    if ($response['status'] === "success") {
        echo json_encode([
            "status" => "success",
            "message" => "Login successful",
            "user_id" => $response['user_id'],
            "token" => $response['token']
        ]);
        exit();
    } elseif ($response['status'] === "error") {
        echo json_encode(["status" => "error", "message" => $response['message']]);
        exit();
    } else {
        echo json_encode(["status" => "error", "message" => "Unexpected response format"]);
        exit();
    }
} catch (Exception $e) {
    error_log("[LOGIN] ERROR: RabbitMQ Connection Failed - " . $e->getMessage());
    echo json_encode(["status" => "error", "message" => "Error connecting to RabbitMQ"]);
    unset($_SESSION['rabbitmq_client']); // ✅ Close connection if an error occurs
    exit();
}
?>
