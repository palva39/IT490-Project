<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

error_log("[EMAIL📧] Starting Email Consumer...\n", 3, "/var/log/mail.log");

try {
    $server = new rabbitMQServer("emailRabbitMQ.ini", "emailQueue");
    error_log("[EMAIL📧] Connected to RabbitMQ successfully!\n", 3, "/var/log/mail.log");
    echo("[EMAIL📩] Connected to RabbitMQ successfully! \n");
} catch (Exception $e) {
    error_log("[EMAIL❌] ERROR: Failed to connect to RabbitMQ - " . $e->getMessage() . "\n", 3, "/var/log/mail.log");
    echo("[EMAIL❌] ERROR: Failed to connect to RabbitMQ \n");
    exit("[EMAIL❌] ERROR: Failed to connect to RabbitMQ\n");

}

// ✅ Function to send email
function sendEmail($to, $subject, $message) {
    $headers = "From: no-reply@yourdomain.com\r\n";
    $headers .= "Reply-To: support@yourdomain.com\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

    if (mail($to, $subject, $message, $headers)) {
        error_log("[EMAIL✅] Email sent to: $to\n", 3, "/var/log/mail.log");
        echo("[EMAIL📩] Email successfully sent to: $to:\n");
        return ["status" => "success", "message" => "Email sent"];
    } else {
        error_log("[EMAIL❌] Email failed for: $to\n", 3, "/var/log/mail.log");
        echo("[EMAIL❌] Email sending failed for: $to\n");
        return ["status" => "error", "message" => "Failed to send email"];
    }
}

// ✅ Process RabbitMQ Messages
function processEmailRequest($request) {
    error_log("[EMAIL📩] Received email request: " . json_encode($request) . "\n", 3, "/var/log/mail.log");
    echo("[EMAIL📩] Received email request: $request\n");

    if (!isset($request['to']) || !isset($request['subject']) || !isset($request['message'])) {
        error_log("[EMAIL⚠️] Invalid email request format\n", 3, "/var/log/mail.log");
        echo("[EMAIL⚠️] Invalid email request format\n");
        return ["status" => "error", "message" => "Invalid email request"];
    }

    // ✅ Send email and return response to RabbitMQ
    return sendEmail($request['to'], $request['subject'], $request['message']);
}

// ✅ Start RabbitMQ Consumer
error_log("[EMAIL📧] Waiting for email messages...\n", 3, "/var/log/mail.log");
echo("[EMAIL📧] Waiting for email messages...\n");
$server->process_requests('processEmailRequest');
