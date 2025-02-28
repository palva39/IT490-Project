<?php
require_once('get_host_info.inc');

class rabbitMQServer {
    private $machine = "";
    public $BROKER_HOST;
    private $BROKER_PORT;
    private $USER;
    private $PASSWORD;
    private $VHOST;
    private $exchange;
    private $queue;
    private $routing_key = '*';
    private $exchange_type = "topic";
    private $connection = null;
    private $channel = null;
    private $exchangeObj = null;
    private $queueObj = null;

    function __construct($machine, $server = "rabbitMQ") {
        $this->machine = getHostInfo([$machine]);
        $this->BROKER_HOST = $this->machine[$server]["BROKER_HOST"];
        $this->BROKER_PORT = $this->machine[$server]["BROKER_PORT"];
        $this->USER = $this->machine[$server]["USER"];
        $this->PASSWORD = $this->machine[$server]["PASSWORD"];
        $this->VHOST = $this->machine[$server]["VHOST"];
        $this->exchange = $this->machine[$server]["EXCHANGE"];
        $this->queue = $this->machine[$server]["QUEUE"];
    }

    function connect() {
        if ($this->connection === null) {
            error_log("[RABBITMQ SERVER] 🔴 Establishing NEW Connection...\n", 3, "/var/log/rabbitmq_errors.log");
            $params = [
                'host' => $this->BROKER_HOST,
                'port' => $this->BROKER_PORT,
                'login' => $this->USER,
                'password' => $this->PASSWORD,
                'vhost' => $this->VHOST
            ];
            $this->connection = new AMQPConnection($params);
            $this->connection->connect();
            $this->channel = new AMQPChannel($this->connection);
            $this->exchangeObj = new AMQPExchange($this->channel);
            $this->exchangeObj->setName($this->exchange);
            $this->queueObj = new AMQPQueue($this->channel);
            $this->queueObj->setName($this->queue);
        }
    }

    function close() {
        if ($this->connection !== null) {
            error_log("[RABBITMQ SERVER] 🔴 Closing Connection...\n", 3, "/var/log/rabbitmq_errors.log");
            $this->connection->disconnect();
            $this->connection = null;
        }
    }

    function process_message($msg) {
        error_log("[RABBITMQ SERVER] 📩 Received message: " . $msg->getBody() . "\n", 3, "/var/log/rabbitmq_errors.log");

        $this->queueObj->ack($msg->getDeliveryTag());

        try {
            $payload = json_decode($msg->getBody(), true);
            $response = isset($this->callback) ? call_user_func($this->callback, $payload) : ["status" => "error", "message" => "No callback defined"];

            if ($msg->getReplyTo()) {
                // ✅ Correctly bind the response queue
                $reply_queue_name = $msg->getReplyTo();
                $reply_queue = new AMQPQueue($this->channel);
                $reply_queue->setName($reply_queue_name);
                $reply_queue->declare();
                $reply_queue->bind($this->exchangeObj->getName(), $reply_queue_name);

                // ✅ Publish the response message
                $this->exchangeObj->publish(
                    json_encode($response),
                    $reply_queue_name,
                    AMQP_NOPARAM,
                    ['correlation_id' => $msg->getCorrelationId()]
                );

                error_log("[RABBITMQ SERVER] 📬 Sent response to queue: {$reply_queue_name}\n", 3, "/var/log/rabbitmq_errors.log");
            }

        } catch (Exception $e) {
            error_log("[RABBITMQ SERVER] ❌ ERROR processing message: " . $e->getMessage() . "\n", 3, "/var/log/rabbitmq_errors.log");
        }
    }

    function process_requests($callback) {
        try {
            $this->callback = $callback;
            $this->connect();
            $this->queueObj->consume([$this, 'process_message']);
        } catch (Exception $e) {
            error_log("[RABBITMQ SERVER] ❌ ERROR: Request Processing Failed - " . $e->getMessage() . "\n", 3, "/var/log/rabbitmq_errors.log");
        } finally {
            $this->close();
        }
    }
}

class rabbitMQClient {
    private $machine = "";
    public $BROKER_HOST;
    private $BROKER_PORT;
    private $USER;
    private $PASSWORD;
    private $VHOST;
    private $exchange;
    private $queue;
    private $routing_key = '*';
    private $exchange_type = "topic";
    private $connection = null;
    private $channel = null;
    private $exchangeObj = null;
    private $queueObj = null;

    function __construct($machine, $server = "rabbitMQ") {
        $this->machine = getHostInfo([$machine]);
        $this->BROKER_HOST = $this->machine[$server]["BROKER_HOST"];
        $this->BROKER_PORT = $this->machine[$server]["BROKER_PORT"];
        $this->USER = $this->machine[$server]["USER"];
        $this->PASSWORD = $this->machine[$server]["PASSWORD"];
        $this->VHOST = $this->machine[$server]["VHOST"];
        $this->exchange = $this->machine[$server]["EXCHANGE"];
        $this->queue = $this->machine[$server]["QUEUE"];
    }

    function connect() {
        if ($this->connection === null) {
            error_log("[RABBITMQ CLIENT] 🔴 Establishing NEW Connection...\n", 3, "/var/log/rabbitmq_errors.log");
            $params = [
                'host' => $this->BROKER_HOST,
                'port' => $this->BROKER_PORT,
                'login' => $this->USER,
                'password' => $this->PASSWORD,
                'vhost' => $this->VHOST
            ];
            $this->connection = new AMQPConnection($params);
            $this->connection->connect();
            $this->channel = new AMQPChannel($this->connection);
            $this->exchangeObj = new AMQPExchange($this->channel);
            $this->exchangeObj->setName($this->exchange);
            $this->queueObj = new AMQPQueue($this->channel);
            $this->queueObj->setName($this->queue);
        }
    }

    function close() {
        if ($this->connection !== null) {
            error_log("[RABBITMQ CLIENT] 🔴 Closing Connection...\n", 3, "/var/log/rabbitmq_errors.log");
            $this->connection->disconnect();
            $this->connection = null;
        }
    }
   
    function send_request($message) {
    $uid = uniqid(); // Unique correlation ID for response tracking
    $json_message = json_encode($message);
    
    try {
        $this->connect();

        // ✅ Declare a response queue for receiving messages
        $reply_queue_name = $this->queue . "_response";  // Example: loginQueue_response
        $reply_queue = new AMQPQueue($this->channel);
        $reply_queue->setName($reply_queue_name);
        $reply_queue->declare();
        $reply_queue->bind($this->exchangeObj->getName(), $reply_queue_name);

        // ✅ Publish the message with `reply_to` set to the response queue
        $this->exchangeObj->publish(
            $json_message,
            $this->routing_key,
            AMQP_NOPARAM,
            ['reply_to' => $reply_queue_name, 'correlation_id' => $uid]
        );

        error_log("[RABBITMQ CLIENT] 📤 Message sent to queue: {$this->queue} with reply queue: {$reply_queue_name}\n", 3, "/var/log/rabbitmq_errors.log");

        // ✅ Wait for and consume response from the response queue
        $response = null;
        $start_time = time();  // Start timeout tracking
        $timeout = 5;  // Timeout in seconds

        // ✅ Consume with a proper consumer tag (string, not array)
        $reply_queue->consume(function ($msg) use (&$response, $uid, $start_time, $timeout) {
            $payload = json_decode($msg->getBody(), true);

            if ($msg->getCorrelationId() === $uid) { // Ensure correct response
                $response = $payload;
                return false; // Stop consuming
            }

            // ✅ Implement manual timeout to avoid infinite waiting
            if (time() - $start_time > $timeout) {
                error_log("[RABBITMQ CLIENT] ❌ ERROR: Timeout waiting for response\n", 3, "/var/log/rabbitmq_errors.log");
                $response = ["status" => "error", "message" => "Timeout waiting for response"];
                return false; // Stop consuming
            }

        }, AMQP_AUTOACK, "response_consumer_tag");  // ✅ Corrected consumer tag

        if (!$response) {
            error_log("[RABBITMQ CLIENT] ❌ ERROR: No response received from Database VM.\n", 3, "/var/log/rabbitmq_errors.log");
            return ["status" => "error", "message" => "No response received from Database VM"];
        }

        return $response;

    } catch (Exception $e) {
        error_log("[RABBITMQ CLIENT] ❌ ERROR sending request: " . $e->getMessage() . "\n", 3, "/var/log/rabbitmq_errors.log");
        return ["status" => "error", "message" => "Failed to send request"];
    } finally {
        $this->close();
    }
}

   


}
?>

