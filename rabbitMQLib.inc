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
    private $auto_delete = false;
    private $callback;
    private $conn_queue;
    private $connection = null; // ✅ Store connection for reuse

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
        }
        return $this->connection;
    }

    function close() {
        if ($this->connection !== null) {
            error_log("[RABBITMQ SERVER] 🔴 Closing Connection...", 3, "/var/log/rabbitmq_errors.log");
            $this->connection->disconnect();
            $this->connection = null;
        }
    }

    function process_requests($callback) {
        try {
            $this->callback = $callback;
            $conn = $this->connect();
            $channel = new AMQPChannel($conn);
            $exchange = new AMQPExchange($channel);
            $exchange->setName($this->exchange);
            $exchange->setType($this->exchange_type);

            $this->conn_queue = new AMQPQueue($channel);
            $this->conn_queue->setName($this->queue);
            $this->conn_queue->bind($exchange->getName(), $this->routing_key);
            $this->conn_queue->consume([$this, 'process_message']);

            while (count($channel->callbacks)) {
                $channel->wait();
            }
        } catch (Exception $e) {
            error_log("Failed to start request processor: " . $e->getMessage(), 3, "/var/log/rabbitmq_errors.log");
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
    private $response_queue = [];
    private $exchange_type = "topic";
    private $connection = null; // ✅ Store connection for reuse

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
        }
        return $this->connection;
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
            $conn = $this->connect();
            $channel = new AMQPChannel($conn);
            $exchange = new AMQPExchange($channel);
            $exchange->setName($this->exchange);
            $exchange->setType($this->exchange_type);

            $callback_queue = new AMQPQueue($channel);
            $callback_queue->setName($this->queue . "_response");
            $callback_queue->declare();
            $callback_queue->bind($exchange->getName(), $this->routing_key . ".response");

            $this->conn_queue = new AMQPQueue($channel);
            $this->conn_queue->setName($this->queue);
            $this->conn_queue->bind($exchange->getName(), $this->routing_key);

            $exchange->publish($json_message, $this->routing_key, AMQP_NOPARAM, [
                'reply_to' => $callback_queue->getName(),
                'correlation_id' => $uid
            ]);

            $this->response_queue[$uid] = "waiting";
            $callback_queue->consume([$this, 'process_response']);

            $response = $this->response_queue[$uid];
            unset($this->response_queue[$uid]);

            return $response;
        } catch (Exception $e) {
            die("Failed to send message to exchange: " . $e->getMessage() . "\n");
        } finally {
            $this->close();
        }
    }

    function publish($message) {
        $json_message = json_encode($message);
        try {
            $conn = $this->connect();
            $channel = new AMQPChannel($conn);
            $exchange = new AMQPExchange($channel);
            $exchange->setName($this->exchange);
            $exchange->setType($this->exchange_type);

            $this->conn_queue = new AMQPQueue($channel);
            $this->conn_queue->setName($this->queue);
            $this->conn_queue->bind($exchange->getName(), $this->routing_key);

            return $exchange->publish($json_message, $this->routing_key);
        } catch (Exception $e) {
            die("Failed to send message to exchange: " . $e->getMessage() . "\n");
        } finally {
            $this->close();
        }
    }
}
?>



