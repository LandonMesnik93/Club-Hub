<?php
require_once '../database/db.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$club_id = $_REQUEST['club_id'] ?? null;

if (!$club_id) jsonResponse(false, null, 'Club ID required');

try {
    if ($method === 'GET') {
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
        
        $sql = "SELECT a.*, u.first_name, u.last_name 
                FROM announcements a 
                LEFT JOIN users u ON a.user_id = u.id 
                WHERE a.club_id = ? 
                ORDER BY a.is_pinned DESC, a.created_at DESC 
                LIMIT ?";
        
        $announcements = dbQuery($sql, [$club_id, $limit]);
        jsonResponse(true, $announcements);
    }
    
    if ($method === 'POST') {
        if (!isLoggedIn()) jsonResponse(false, null, 'Login required');
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['title']) || empty($data['content'])) {
            jsonResponse(false, null, 'Title and content are required');
        }
        
        $sql = "INSERT INTO announcements (club_id, user_id, title, content, priority) 
                VALUES (?, ?, ?, ?, ?)";
        
        $id = dbExecute($sql, [
            $club_id,
            getCurrentUserId(),
            sanitizeInput($data['title']),
            sanitizeInput($data['content']),
            sanitizeInput($data['priority'] ?? 'normal')
        ]);
        
        jsonResponse(!!$id, ['id' => $id], $id ? 'Announcement created' : 'Failed');
    }
    
    if ($method === 'PUT') {
        if (!isLoggedIn()) jsonResponse(false, null, 'Login required');
        
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? null;
        
        if (!$id) jsonResponse(false, null, 'Announcement ID required');
        
        $updates = [];
        $params = [];
        
        if (isset($data['title'])) {
            $updates[] = "title = ?";
            $params[] = sanitizeInput($data['title']);
        }
        if (isset($data['content'])) {
            $updates[] = "content = ?";
            $params[] = sanitizeInput($data['content']);
        }
        if (isset($data['priority'])) {
            $updates[] = "priority = ?";
            $params[] = sanitizeInput($data['priority']);
        }
        
        if (empty($updates)) jsonResponse(false, null, 'No updates provided');
        
        $sql = "UPDATE announcements SET " . implode(', ', $updates) . " WHERE id = ? AND club_id = ?";
        $params[] = $id;
        $params[] = $club_id;
        
        $result = dbExecute($sql, $params);
        jsonResponse(!!$result, null, $result ? 'Announcement updated' : 'Failed');
    }
    
    if ($method === 'DELETE') {
        if (!isLoggedIn()) jsonResponse(false, null, 'Login required');
        
        $id = $_GET['id'] ?? null;
        if (!$id) jsonResponse(false, null, 'Announcement ID required');
        
        $result = dbExecute("DELETE FROM announcements WHERE id = ? AND club_id = ?", [$id, $club_id]);
        jsonResponse(!!$result, null, $result ? 'Announcement deleted' : 'Failed');
    }
    
} catch (Exception $e) {
    error_log('Announcements API Error: ' . $e->getMessage());
    jsonResponse(false, null, 'Error processing request');
}
