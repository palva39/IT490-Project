<?php
require_once('rabbitMQLib.inc');

$client = new rabbitMQClient("testRabbitMQ.ini", "registerQueue");

$request = [
    "type" => "register",
    "username" => "testuser",
    "email" => "test@example.com",
    "password" => password_hash("password123", PASSWORD_DEFAULT)
];

// Send test registration request(hopefully this works)
$response = $client->send_request($request);

echo "Test Registration Response: ";
print_r($response);
?>
