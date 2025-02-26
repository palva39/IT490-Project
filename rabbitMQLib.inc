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
            error_log("[RABBITMQ SERVER] 🔴 Establishing NEW Connection...", 3, "/var/log/rabbitmq_errors.log");
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
            error_log("[RABBITMQ SERVER] 🔴 Closing Connection...", 3, "/var/log/rabbitmq_errors.log");
            $this->connection->disconnect();
            $this->connection = null;
        }
    }

    function process_message($msg) {
        error_log("[RABBITMQ SERVER] 📩 Received message: " . $msg->getBody(), 3, "/var/log/rabbitmq_errors.log");

        $this->queueObj->ack($msg->getDeliveryTag());

        try {
            $payload = json_decode($msg->getBody(), true);
            $response = isset($this->callback) ? call_user_func($this->callback, $payload) : ["status" => "error", "message" => "No callback defined"];

            if ($msg->getReplyTo()) {
                $reply_key = $this->routing_key . ".response";
                $this->queueObj->setName($msg->getReplyTo());
                $this->queueObj->bind($this->exchangeObj->getName(), $reply_key);
                $this->exchangeObj->publish(json_encode($response), $reply_key, AMQP_NOPARAM, ['correlation_id' => $msg->getCorrelationId()]);
            }
        } catch (Exception $e) {
            error_log("[RABBITMQ SERVER] ❌ ERROR processing message: " . $e->getMessage(), 3, "/var/log/rabbitmq_errors.log");
        }
    }

    function process_requests($callback) {
        try {
            $this->callback = $callback;
            $this->connect();
            $this->queueObj->consume([$this, 'process_message']);
        } catch (Exception $e) {
            error_log("[RABBITMQ SERVER] ❌ ERROR: Request Processing Failed - " . $e->getMessage(), 3, "/var/log/rabbitmq_errors.log");
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
            error_log("[RABBITMQ CLIENT] 🔴 Establishing NEW Connection...", 3, "/var/log/rabbitmq_errors.log");
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
            error_log("[RABBITMQ CLIENT] 🔴 Closing Connection...", 3, "/var/log/rabbitmq_errors.log");
            $this->connection->disconnect();
            $this->connection = null;
        }
    }

    function send_request($message) {
        $uid = uniqid();
        $json_message = json_encode($message);

        try {
            $this->connect();
            $reply_queue = $this->queue . "_response";
            $this->queueObj->bind($this->exchangeObj->getName(), $this->routing_key);
            $this->exchangeObj->publish($json_message, $this->routing_key, AMQP_NOPARAM, ['reply_to' => $reply_queue, 'correlation_id' => $uid]);

            return "Message Sent";
        } catch (Exception $e) {
            return "Failed to send message: " . $e->getMessage();
        } finally {
            $this->close();
        }
    }
}

?>

