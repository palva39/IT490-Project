<?php
require_once('rabbitMQLib.inc');

$client = new rabbitMQClient("emailRabbitMQ.ini", "emailQueue");

$request = [
    "type" => "send_email",
    "to" => "test@example.com",
    "subject" => "Welcome!",
    "message" => "Welcome, testuser! Thank you for registering."
];

$response = $client->send_request($request);

echo "Email Test Response: ";
print_r($response);
?>
