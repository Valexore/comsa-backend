<?php
require_once "../../config/db.php";
require_once "../../config/session.php";

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$student_id = $_SESSION['user_id'];
$request_method = $_SERVER['REQUEST_METHOD'];

// Get database connection
function getDBConnection() {
    static $conn = null;
    if ($conn === null) {
        try {
            $conn = new PDO('mysql:host=' . 'localhost' . ';dbname=' . 'db_dsn', 'root', '');
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
            exit;
        }
    }
    return $conn;
}

// Get user conversations
if ($request_method === 'GET' && isset($_GET['action'])) {
    $action = $_GET['action'];
    $db = getDBConnection();
    
    try {
        switch ($action) {
            case 'get_conversations':
                // Get all conversations for the current user
                $stmt = $db->prepare("
                    SELECT c.id, c.created_at, c.updated_at,
                           s2.id as other_user_id, s2.full_name as other_user_name,
                           s2.avatar as other_user_avatar,
                           (SELECT message_text FROM messages 
                            WHERE conversation_id = c.id 
                            ORDER BY created_at DESC LIMIT 1) as last_message,
                           (SELECT created_at FROM messages 
                            WHERE conversation_id = c.id 
                            ORDER BY created_at DESC LIMIT 1) as last_message_time,
                           (SELECT COUNT(*) FROM messages 
                            WHERE conversation_id = c.id 
                            AND sender_id != :student_id 
                            AND is_read = FALSE) as unread_count
                    FROM conversations c
                    INNER JOIN conversation_participants cp1 ON c.id = cp1.conversation_id
                    INNER JOIN conversation_participants cp2 ON c.id = cp2.conversation_id
                    INNER JOIN students s1 ON cp1.student_id = s1.id
                    INNER JOIN students s2 ON cp2.student_id = s2.id
                    WHERE cp1.student_id = :student_id AND cp2.student_id != :student_id
                    ORDER BY c.updated_at DESC
                ");
                $stmt->bindParam(':student_id', $student_id, PDO::PARAM_INT);
                $stmt->execute();
                $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['conversations' => $conversations]);
                break;
                
            case 'get_messages':
                // Get messages for a specific conversation
                if (!isset($_GET['conversation_id'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'conversation_id parameter is required']);
                    exit;
                }
                
                $conversation_id = $_GET['conversation_id'];
                
                // Verify user is part of this conversation
                $stmt = $db->prepare("
                    SELECT COUNT(*) as count 
                    FROM conversation_participants 
                    WHERE conversation_id = :conversation_id AND student_id = :student_id
                ");
                $stmt->bindParam(':conversation_id', $conversation_id, PDO::PARAM_INT);
                $stmt->bindParam(':student_id', $student_id, PDO::PARAM_INT);
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result['count'] == 0) {
                    http_response_code(403);
                    echo json_encode(['error' => 'You are not part of this conversation']);
                    exit;
                }
                
                // Get messages
                $stmt = $db->prepare("
                    SELECT m.*, s.full_name as sender_name, s.avatar as sender_avatar
                    FROM messages m
                    INNER JOIN students s ON m.sender_id = s.id
                    WHERE m.conversation_id = :conversation_id
                    ORDER BY m.created_at ASC
                ");
                $stmt->bindParam(':conversation_id', $conversation_id, PDO::PARAM_INT);
                $stmt->execute();
                $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Mark messages as read
                $stmt = $db->prepare("
                    UPDATE messages 
                    SET is_read = TRUE 
                    WHERE conversation_id = :conversation_id 
                    AND sender_id != :student_id
                    AND is_read = FALSE
                ");
                $stmt->bindParam(':conversation_id', $conversation_id, PDO::PARAM_INT);
                $stmt->bindParam(':student_id', $student_id, PDO::PARAM_INT);
                $stmt->execute();
                
                echo json_encode(['messages' => $messages]);
                break;
                
            case 'get_online_users':
                // Get online users
                $stmt = $db->prepare("
                    SELECT s.id, s.full_name, s.avatar, uos.status, uos.last_seen
                    FROM user_online_status uos
                    INNER JOIN students s ON uos.student_id = s.id
                    WHERE uos.status = 'online' AND uos.student_id != :student_id
                    ORDER BY s.full_name
                ");
                $stmt->bindParam(':student_id', $student_id, PDO::PARAM_INT);
                $stmt->execute();
                $online_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['online_users' => $online_users]);
                break;
                
            default:
                http_response_code(400);
                echo json_encode(['error' => 'Invalid action']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

// Handle POST requests
if ($request_method === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $db = getDBConnection();
    
    try {
        switch ($action) {
            case 'send_message':
                // Send a new message
                if (!isset($_POST['receiver_id']) || !isset($_POST['message'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'receiver_id and message parameters are required']);
                    exit;
                }
                
                $receiver_id = $_POST['receiver_id'];
                $message_text = $_POST['message'];
                $message_type = isset($_POST['message_type']) ? $_POST['message_type'] : 'text';
                
                // Get or create conversation
                $conversation_id = getConversationId($student_id, $receiver_id, $db);
                
                // Insert message
                $stmt = $db->prepare("
                    INSERT INTO messages (conversation_id, sender_id, message_text, message_type)
                    VALUES (:conversation_id, :sender_id, :message_text, :message_type)
                ");
                $stmt->bindParam(':conversation_id', $conversation_id, PDO::PARAM_INT);
                $stmt->bindParam(':sender_id', $student_id, PDO::PARAM_INT);
                $stmt->bindParam(':message_text', $message_text, PDO::PARAM_STR);
                $stmt->bindParam(':message_type', $message_type, PDO::PARAM_STR);
                $stmt->execute();
                
                $message_id = $db->lastInsertId();
                
                // Update conversation updated_at
                $stmt = $db->prepare("
                    UPDATE conversations 
                    SET updated_at = NOW() 
                    WHERE id = :conversation_id
                ");
                $stmt->bindParam(':conversation_id', $conversation_id, PDO::PARAM_INT);
                $stmt->execute();
                
                // Get the complete message data
                $stmt = $db->prepare("
                    SELECT m.*, s.full_name as sender_name, s.avatar as sender_avatar
                    FROM messages m
                    INNER JOIN students s ON m.sender_id = s.id
                    WHERE m.id = :message_id
                ");
                $stmt->bindParam(':message_id', $message_id, PDO::PARAM_INT);
                $stmt->execute();
                $message = $stmt->fetch(PDO::FETCH_ASSOC);
                
                echo json_encode(['message' => $message, 'conversation_id' => $conversation_id]);
                break;
                
            case 'start_conversation':
                // Start a new conversation
                if (!isset($_POST['receiver_id'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'receiver_id parameter is required']);
                    exit;
                }
                
                $receiver_id = $_POST['receiver_id'];
                $conversation_id = getConversationId($student_id, $receiver_id, $db);
                
                echo json_encode(['conversation_id' => $conversation_id]);
                break;
                
            case 'update_online_status':
                // Update user online status
                if (!isset($_POST['status'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'status parameter is required']);
                    exit;
                }
                
                $status = $_POST['status'];
                $socket_id = isset($_POST['socket_id']) ? $_POST['socket_id'] : null;
                
                $stmt = $db->prepare("
                    INSERT INTO user_online_status (student_id, status, socket_id, last_seen)
                    VALUES (:student_id, :status, :socket_id, NOW())
                    ON DUPLICATE KEY UPDATE 
                    status = :status, socket_id = :socket_id, last_seen = NOW()
                ");
                $stmt->bindParam(':student_id', $student_id, PDO::PARAM_INT);
                $stmt->bindParam(':status', $status, PDO::PARAM_STR);
                $stmt->bindParam(':socket_id', $socket_id, PDO::PARAM_STR);
                $stmt->execute();
                
                echo json_encode(['status' => 'success']);
                break;
                
            default:
                http_response_code(400);
                echo json_encode(['error' => 'Invalid action']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

// Helper function to get or create conversation
function getConversationId($user1_id, $user2_id, $db) {
    // Sort IDs to ensure consistency
    $user_ids = [$user1_id, $user2_id];
    sort($user_ids);
    $user1 = $user_ids[0];
    $user2 = $user_ids[1];
    
    // Check if conversation already exists
    $stmt = $db->prepare("
        SELECT cp.conversation_id 
        FROM conversation_participants cp
        INNER JOIN conversation_participants cp2 ON cp.conversation_id = cp2.conversation_id
        WHERE cp.student_id = :user1 AND cp2.student_id = :user2
        GROUP BY cp.conversation_id
        HAVING COUNT(*) = 2
    ");
    $stmt->bindParam(':user1', $user1, PDO::PARAM_INT);
    $stmt->bindParam(':user2', $user2, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        return $result['conversation_id'];
    }
    
    // Create new conversation
    $db->beginTransaction();
    
    // Create conversation
    $stmt = $db->prepare("INSERT INTO conversations () VALUES ()");
    $stmt->execute();
    $conversation_id = $db->lastInsertId();
    
    // Add participants
    $stmt = $db->prepare("INSERT INTO conversation_participants (conversation_id, student_id) VALUES (?, ?)");
    $stmt->execute([$conversation_id, $user1]);
    $stmt->execute([$conversation_id, $user2]);
    
    $db->commit();
    
    return $conversation_id;
}