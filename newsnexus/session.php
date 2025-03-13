<?php
session_start();
header("Content-Type: application/json");

if (isset($_SESSION['username']) && $_SESSION['logged_in'] === true) {
    echo json_encode(["status" => "success", "username" => $_SESSION['username']]);
} else {
    echo json_encode(["status" => "error"]);
}
?>

