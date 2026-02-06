<?php
require_once '../database/db.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    jsonResponse(false, null, 'Login required');
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $clubId = $data['club_id'] ?? null;
        
        if (!$clubId) {
            jsonResponse(false, null, 'Club ID required');
        }
        
        // Verify user is a member of this club
        $sql = "SELECT cm.id FROM club_members cm 
                JOIN clubs c ON c.id = cm.club_id
                WHERE cm.club_id = ? AND cm.user_id = ? AND cm.status = 'active' AND c.is_active = TRUE";
        
        $member = dbQueryOne($sql, [$clubId, getCurrentUserId()]);
        
        if (!$member) {
            jsonResponse(false, null, 'You are not a member of this club');
        }
        
        // Update session
        $_SESSION['active_club_id'] = $clubId;
        
        jsonResponse(true, ['club_id' => $clubId], 'Club switched successfully');
        
    } catch (Exception $e) {
        error_log('Switch club error: ' . $e->getMessage());
        jsonResponse(false, null, 'Error switching club');
    }
}

jsonResponse(false, null, 'Invalid request method');