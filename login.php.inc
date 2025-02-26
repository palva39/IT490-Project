<?php
require_once('mysqlconnect.php');

class loginDB {
    private $logindb;

    public function __construct() {
        $this->logindb = new mysqli("127.0.0.1", "testUser", "12345", "login");

        if ($this->logindb->connect_errno) {
            echo "Error connecting to database: " . $this->logindb->connect_error . PHP_EOL;
            exit(1);
        }
    }

    public function validateLogin($username, $password) {
        $stmt = $this->logindb->prepare("SELECT password FROM users WHERE username = ?");
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
                "token" => bin2hex(random_bytes(16)) // Generate session token
            ];
        } else {
            return ["status" => "error", "message" => "Incorrect password"];
        }
    }
}
?>
