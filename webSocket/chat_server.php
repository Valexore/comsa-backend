<?php
require __DIR__ . '/../vendor/autoload.php';
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\WebSocket\WsServer;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;

class ChatWebSocket implements MessageComponentInterface {
    protected $clients;
    protected $userConnections;
    protected $db;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->userConnections = [];
        
        // Database connection
        $this->connectToDatabase();
    }
    
    private function connectToDatabase() {
        try {
            $this->db = new PDO('mysql:host=localhost;dbname=your_database_name', 'username', 'password');
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            echo "Database connected successfully\n";
        } catch (PDOException $e) {
            echo "Database connection failed: " . $e->getMessage() . "\n";
            exit(1);
        }
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New connection: ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        try {
            $data = json_decode($msg, true);
            
            if (!$data || !isset($data['type'])) {
                echo "Invalid message format\n";
                return;
            }
            
            switch ($data['type']) {
                case 'auth':
                    $this->handleAuthentication($from, $data);
                    break;
                case 'message':
                    $this->handleMessage($from, $data);
                    break;
                case 'get_history':
                    $this->handleGetHistory($from, $data);
                    break;
                case 'typing':
                    $this->handleTyping($from, $data);
                    break;
                case 'read_receipt':
                    $this->handleReadReceipt($from, $data);
                    break;
                default:
                    echo "Unknown message type: {$data['type']}\n";
            }
        } catch (Exception $e) {
            echo "Error processing message: " . $e->getMessage() . "\n";
            $from->send(json_encode([
                'type' => 'error',
                'message' => 'Error processing your request'
            ]));
        }
    }
    
    private function handleAuthentication($conn, $data) {
        if (!isset($data['userId']) || !isset($data['userName'])) {
            $conn->send(json_encode([
                'type' => 'error',
                'message' => 'Authentication requires userId and userName'
            ]));
            return;
        }
        
        $userId = $data['userId'];
        $userName = $data['userName'];
        
        // Store the connection with user ID
        $this->userConnections[$userId] = $conn;
        $conn->userId = $userId;
        
        // Update online status in database
        $this->updateUserOnlineStatus($userId, 'online');
        
        // Send list of online users
        $this->sendOnlineUsersList();
        
        echo "User {$userName} ({$userId}) authenticated\n";
        
        $conn->send(json_encode([
            'type' => 'auth_success',
            'message' => 'Authentication successful'
        ]));
    }
    
    private function handleMessage($conn, $data) {
        if (!isset($conn->userId)) {
            $conn->send(json_encode([
                'type' => 'error',
                'message' => 'You must authenticate first'
            ]));
            return;
        }
        
        if (!isset($data['receiver_id']) || !isset($data['content'])) {
            $conn->send(json_encode([
                'type' => 'error',
                'message' => 'Message requires receiver_id and content'
            ]));
            return;
        }
        
        $senderId = $conn->userId;
        $receiverId = $data['receiver_id'];
        $content = $data['content'];
        $timestamp = isset($data['timestamp']) ? $data['timestamp'] : date('Y-m-d H:i:s');
        
        // Get or create conversation
        $conversationId = $this->getConversationId($senderId, $receiverId);
        
        // Save message to database
        $messageId = $this->saveMessage($conversationId, $senderId, $content);
        
        // Prepare message data to send
        $messageData = [
            'type' => 'message',
            'id' => $messageId,
            'conversation_id' => $conversationId,
            'sender_id' => $senderId,
            'receiver_id' => $receiverId,
            'content' => $content,
            'timestamp' => $timestamp,
            'is_read' => false
        ];
        
        // Send to sender (for confirmation)
        $conn->send(json_encode($messageData));
        
        // Send to receiver if online
        if (isset($this->userConnections[$receiverId])) {
            $this->userConnections[$receiverId]->send(json_encode($messageData));
        }
    }
    
    private function handleGetHistory($conn, $data) {
        if (!isset($conn->userId)) {
            $conn->send(json_encode([
                'type' => 'error',
                'message' => 'You must authenticate first'
            ]));
            return;
        }
        
        if (!isset($data['targetUserId'])) {
            $conn->send(json_encode([
                'type' => 'error',
                'message' => 'Target user ID is required'
            ]));
            return;
        }
        
        $userId = $conn->userId;
        $targetUserId = $data['targetUserId'];
        
        // Get conversation ID
        $conversationId = $this->getConversationId($userId, $targetUserId);
        
        // Get message history
        $messages = $this->getMessageHistory($conversationId, $userId);
        
        $conn->send(json_encode([
            'type' => 'message_history',
            'messages' => $messages
        ]));
    }
    
    private function handleTyping($conn, $data) {
        if (!isset($conn->userId) || !isset($data['receiver_id'])) {
            return;
        }
        
        $typingData = [
            'type' => 'typing',
            'sender_id' => $conn->userId,
            'receiver_id' => $data['receiver_id'],
            'is_typing' => isset($data['is_typing']) ? $data['is_typing'] : true,
            'userName' => isset($data['userName']) ? $data['userName'] : ''
        ];
        
        // Send typing indicator to receiver if online
        if (isset($this->userConnections[$data['receiver_id']])) {
            $this->userConnections[$data['receiver_id']]->send(json_encode($typingData));
        }
    }
    
    private function handleReadReceipt($conn, $data) {
        // Implement read receipt functionality
        // This would update the message as read in the database
        // and notify the sender that their message was read
    }
    
    private function getConversationId($userId1, $userId2) {
        // Sort user IDs to ensure consistent conversation ID
        $userIds = [$userId1, $userId2];
        sort($userIds);
        $user1 = $userIds[0];
        $user2 = $userIds[1];
        
        // Check if conversation already exists
        $stmt = $this->db->prepare("
            SELECT cp.conversation_id 
            FROM conversation_participants cp
            INNER JOIN conversation_participants cp2 ON cp.conversation_id = cp2.conversation_id
            WHERE cp.student_id = ? AND cp2.student_id = ?
            GROUP BY cp.conversation_id
            HAVING COUNT(*) = 2
        ");
        
        $stmt->execute([$user1, $user2]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return $result['conversation_id'];
        }
        
        // Create new conversation
        $this->db->beginTransaction();
        
        // Create conversation
        $stmt = $this->db->prepare("INSERT INTO conversations () VALUES ()");
        $stmt->execute();
        $conversationId = $this->db->lastInsertId();
        
        // Add participants
        $stmt = $this->db->prepare("INSERT INTO conversation_participants (conversation_id, student_id) VALUES (?, ?)");
        $stmt->execute([$conversationId, $user1]);
        $stmt->execute([$conversationId, $user2]);
        
        $this->db->commit();
        
        return $conversationId;
    }
    
    private function saveMessage($conversationId, $senderId, $content) {
        $stmt = $this->db->prepare("
            INSERT INTO messages (conversation_id, sender_id, message_text) 
            VALUES (?, ?, ?)
        ");
        
        $stmt->execute([$conversationId, $senderId, $content]);
        return $this->db->lastInsertId();
    }
    
    private function getMessageHistory($conversationId, $userId) {
        $stmt = $this->db->prepare("
            SELECT m.id, m.conversation_id, m.sender_id, m.message_text as content, 
                   m.created_at as timestamp, m.is_read
            FROM messages m
            WHERE m.conversation_id = ?
            ORDER BY m.created_at ASC
        ");
        
        $stmt->execute([$conversationId]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Update last read time for this user
        $this->updateLastReadTime($conversationId, $userId);
        
        return $messages;
    }
    
    private function updateLastReadTime($conversationId, $userId) {
        $stmt = $this->db->prepare("
            UPDATE conversation_participants 
            SET last_read_at = NOW() 
            WHERE conversation_id = ? AND student_id = ?
        ");
        
        $stmt->execute([$conversationId, $userId]);
    }
    
    private function updateUserOnlineStatus($userId, $status) {
        $stmt = $this->db->prepare("
            INSERT INTO user_online_status (student_id, status, last_seen) 
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE status = ?, last_seen = NOW()
        ");
        
        $stmt->execute([$userId, $status, $status]);
    }
    
    private function sendOnlineUsersList() {
        // Get all online users
        $stmt = $this->db->prepare("
            SELECT s.id, s.full_name as name, uos.status, s.avatar
            FROM user_online_status uos
            INNER JOIN students s ON uos.student_id = s.id
            WHERE uos.status = 'online'
        ");
        
        $stmt->execute();
        $onlineUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Send to all connected clients
        $userListMessage = json_encode([
            'type' => 'user_list',
            'users' => $onlineUsers
        ]);
        
        foreach ($this->clients as $client) {
            if (isset($client->userId)) {
                $client->send($userListMessage);
            }
        }
    }

    public function onClose(ConnectionInterface $conn) {
        if (isset($conn->userId)) {
            // Update user status to offline
            $this->updateUserOnlineStatus($conn->userId, 'offline');
            
            // Remove from user connections
            unset($this->userConnections[$conn->userId]);
            
            // Send updated online users list
            $this->sendOnlineUsersList();
            
            echo "User {$conn->userId} disconnected\n";
        }
        
        $this->clients->detach($conn);
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "Error: {$e->getMessage()}\n";
        $conn->close();
    }
}

// Start server
$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new ChatWebSocket()
        )
    ),
    8080
);

echo "Chat WebSocket server running at ws://localhost:8080\n";
$server->run();