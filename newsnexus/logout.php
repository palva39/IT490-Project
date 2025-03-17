<?php
session_start();

// Unset all session variables
$_SESSION = [];

// Destroy the session
session_destroy();

// Remove session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Force browser cache to clear
header("Cache-Control: no-cache, no-store, must-revalidate"); 
header("Expires: 0");
header("Pragma: no-cache");

// Send JSON response
header("Content-Type: application/json");
echo json_encode(["status" => "success", "message" => "Logged out successfully"]);
exit();
?>
