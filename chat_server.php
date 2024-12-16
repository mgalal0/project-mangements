<?php
require 'vendor/autoload.php';
require 'config.php'; // Add this line to include your database configuration

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

class ChatServer implements \Ratchet\MessageComponentInterface {
    protected $clients;
    protected $userConnections = [];
    protected $conn; // Database connection

    public function __construct($dbConnection) {
        $this->clients = new \SplObjectStorage;
        $this->conn = $dbConnection;
        echo "Chat Server Started!\n";
    }

    public function onOpen(\Ratchet\ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(\Ratchet\ConnectionInterface $from, $msg) {
        $data = json_decode($msg);
        
        if (isset($data->type) && $data->type === 'init') {
            $this->userConnections[$data->userId] = $from;
            return;
        }
    
        // Save message to database
        try {
            $stmt = $this->conn->prepare("INSERT INTO messages (from_user_id, to_user_id, message, file_name, file_type) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iisss", $data->from_user_id, $data->to_user, $data->message, $data->file_name, $data->file_type);
            $stmt->execute();
            $messageId = $stmt->insert_id;
            $stmt->close();
    
            // Get the complete message data
            $stmt = $this->conn->prepare("SELECT m.*, u.username, u.department 
                                        FROM messages m 
                                        JOIN users u ON m.from_user_id = u.id 
                                        WHERE m.id = ?");
            $stmt->bind_param("i", $messageId);
            $stmt->execute();
            $result = $stmt->get_result();
            $messageData = $result->fetch_assoc();
            $stmt->close();
    
            // Send to sender
            $from->send(json_encode($messageData));
    
            // Send to recipient if online
            if (isset($this->userConnections[$data->to_user])) {
                $recipientConn = $this->userConnections[$data->to_user];
                if ($recipientConn !== $from) {
                    $recipientConn->send(json_encode($messageData));
                }
            }
    
        } catch (\Exception $e) {
            echo "Error saving message: " . $e->getMessage() . "\n";
            $from->send(json_encode(['error' => 'Failed to save message']));
        }
    }

    public function onClose(\Ratchet\ConnectionInterface $conn) {
        $this->clients->detach($conn);
        foreach ($this->userConnections as $userId => $connection) {
            if ($connection === $conn) {
                unset($this->userConnections[$userId]);
                break;
            }
        }
    }

    public function onError(\Ratchet\ConnectionInterface $conn, \Exception $e) {
        echo "Error: {$e->getMessage()}\n";
        $conn->close();
    }
}

// Create database connection
$dbConnection = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new ChatServer($dbConnection)
        )
    ),
    8080
);

echo "Chat Server running on port 8080...\n";
$server->run();
?>