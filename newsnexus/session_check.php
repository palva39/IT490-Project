<?php
session_start();

$response = [];

if (isset($_SESSION['username'])) {
    $response['loggedIn'] = true;
    $response['username'] = $_SESSION['username'];
} else {
    $response['loggedIn'] = false;
}

header("Content-Type: application/json");
echo json_encode($response);
exit();
?>
