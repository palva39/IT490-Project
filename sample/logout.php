<?php

session_start();

// Unset all of the session variables
$_SESSION = array();

// process to delete session data and cookies 
// removes session data on the server side.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"], $params["secure"], $params["httponly"]
    );
}

// calls function to delete session
session_destroy();

// Redirect to the login page or homepage
header("Location: login.php");
exit;
?>