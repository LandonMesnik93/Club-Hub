<?php
require_once '../database/db.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_REQUEST['action'] ?? '';

// CREATE CLUB REQUEST
if ($action === 'create' && $method === 'POST') {
    if (!isLoggedIn()) {
        jsonResponse(false, null, 'Login required');
    }
    
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['club_name']) || empty($data['president_name'])) {
            jsonResponse(false, null, 'Club name and president name are required');
        }
        
        $sql = "SELECT id FROM club_creation_requests 
                WHERE requested_by = ? AND status = 'pending'";
        $existing = dbQueryOne($sql, [getCurrentUserId()]);
        
        if ($existing) {
            jsonResponse(false, null, 'You already have a pending club creation request');
        }
        
        $sql = "INSERT INTO club_creation_requests 
                (requested_by, club_name, description, staff_advisor, president_name, requester_comment) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $requestId = dbExecute($sql, [
            getCurrentUserId(),
            sanitizeInput($data['club_name']),
            sanitizeInput($data['description'] ?? ''),
            sanitizeInput($data['staff_advisor'] ?? ''),
            sanitizeInput($data['president_name']),
            sanitizeInput($data['requester_comment'] ?? '')
        ]);
        
        if (!$requestId) {
            jsonResponse(false, null, 'Failed to create request');
        }
        
        createNotification(
            getCurrentUserId(),
            'Club Request Submitted',
            'Your club creation request for "' . $data['club_name'] . '" has been submitted and is pending approval.',
            'info'
        );
        
        jsonResponse(true, ['request_id' => $requestId], 'Club creation request submitted successfully');
        
    } catch (Exception $e) {
        error_log('Club request error: ' . $e->getMessage());
        jsonResponse(false, null, 'Error creating request');
    }
}

// GET MY REQUESTS
if ($action === 'my-requests' && $method === 'GET') {
    if (!isLoggedIn()) {
        jsonResponse(false, null, 'Login required');
    }
    
    try {
        $sql = "SELECT * FROM club_creation_requests 
                WHERE requested_by = ? 
                ORDER BY created_at DESC";
        
        $requests = dbQuery($sql, [getCurrentUserId()]);
        jsonResponse(true, $requests);
        
    } catch (Exception $e) {
        error_log('Get requests error: ' . $e->getMessage());
        jsonResponse(false, null, 'Error fetching requests');
    }
}

jsonResponse(false, null, 'Invalid action');
