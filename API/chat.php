<?php
require_once '../database/db.php';
header('Content-Type: application/json');

if (!isLoggedIn()) jsonResponse(false, null, 'Login required');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_REQUEST['action'] ?? '';

// Get messages
if ($action === 'messages' && $method === 'GET') {
    try {
        $roomId = $_GET['room_id'] ?? null;
        if (!$roomId) jsonResponse(false, null, 'Room ID required');
        
        checkRateLimit('chat_get_messages', getCurrentUserId(), 50);
        
        $since = $_GET['since'] ?? null;
        $limit = $_GET['limit'] ?? 50;
        
        $sql = "SELECT * FROM chat_messages WHERE room_id = ?";
        $params = [$roomId];
        
        if ($since) {
            $sql .= " AND id > ?";
            $params[] = (int)$since;
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ?";
        $params[] = (int)$limit;
        
        $messages = dbQuery($sql, $params);
        jsonResponse(true, array_reverse($messages));
    } catch (Exception $e) {
        error_log('Get messages error: ' . $e->getMessage());
        jsonResponse(false, null, 'Error fetching messages');
    }
}

// Send message
if ($action === 'send' && $method === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $roomId = $data['room_id'] ?? null;
        $message = $data['message'] ?? '';
        
        if (!$roomId || empty($message)) jsonResponse(false, null, 'Room ID and message required');
        
        checkRateLimit('chat_send_message', getCurrentUserId(), 30);
        
        $user = getCurrentUser();
        $username = $user['first_name'] . ' ' . $user['last_name'];
        
        $sql = "INSERT INTO chat_messages (room_id, user_id, username, message) VALUES (?, ?, ?, ?)";
        $messageId = dbExecute($sql, [$roomId, getCurrentUserId(), $username, sanitizeInput($message)]);
        
        dbExecute("UPDATE chat_room_members SET last_seen = NOW() WHERE room_id = ? AND user_id = ?", 
            [$roomId, getCurrentUserId()]);
        
        jsonResponse(true, ['message_id' => $messageId], 'Message sent');
    } catch (Exception $e) {
        error_log('Send message error: ' . $e->getMessage());
        jsonResponse(false, null, 'Error sending message');
    }
}

// Get rooms
if ($action === 'rooms' && $method === 'GET') {
    try {
        $clubId = $_GET['club_id'] ?? null;
        if (!$clubId) jsonResponse(false, null, 'Club ID required');
        
        $sql = "SELECT cr.* FROM chat_rooms cr
                WHERE cr.club_id = ? AND cr.is_active = TRUE
                ORDER BY cr.is_general DESC, cr.created_at ASC";
        
        $rooms = dbQuery($sql, [$clubId]);
        jsonResponse(true, $rooms);
    } catch (Exception $e) {
        error_log('Get rooms error: ' . $e->getMessage());
        jsonResponse(false, null, 'Error fetching rooms');
    }
}

jsonResponse(false, null, 'Invalid action');
