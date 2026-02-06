<?php
require_once '../database/db.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$club_id = $_REQUEST['club_id'] ?? null;

if (!$club_id) jsonResponse(false, null, 'Club ID required');

try {
    if ($method === 'GET') {
        $upcoming = isset($_GET['upcoming']);
        $limit = $_GET['limit'] ?? 50;
        
        $sql = "SELECT e.*, u.first_name, u.last_name 
                FROM events e 
                LEFT JOIN users u ON e.created_by = u.id 
                WHERE e.club_id = ? " . 
                ($upcoming ? "AND e.event_date >= NOW() AND e.is_cancelled = 0" : "") .
                " ORDER BY e.event_date ASC LIMIT ?";
        
        $events = dbQuery($sql, [$club_id, (int)$limit]);
        jsonResponse(true, $events);
    }
    
    if ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isLoggedIn()) jsonResponse(false, null, 'Login required');
        
        $sql = "INSERT INTO events (club_id, created_by, title, description, event_date, location) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $id = dbExecute($sql, [
            $club_id, getCurrentUserId(), 
            sanitizeInput($data['title']), 
            sanitizeInput($data['description'] ?? ''),
            $data['event_date'], 
            sanitizeInput($data['location'] ?? '')
        ]);
        
        jsonResponse(!!$id, ['id' => $id], $id ? 'Event created' : 'Failed');
    }
} catch (Exception $e) {
    error_log('Events API Error: ' . $e->getMessage());
    jsonResponse(false, null, 'Error processing request');
}
