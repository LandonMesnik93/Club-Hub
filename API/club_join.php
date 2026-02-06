<?php

// club_join.php - FIXED VERSION

require_once '../database/db.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_REQUEST['action'] ?? '';

function checkPermission($clubId, $userId, $permissionKey) {
    $sql = "SELECT rp.permission_value FROM club_members cm
            JOIN role_permissions rp ON cm.role_id = rp.role_id
            WHERE cm.club_id = ? AND cm.user_id = ? AND cm.status = 'active' AND rp.permission_key = ?";
    $result = dbQueryOne($sql, [$clubId, $userId, $permissionKey]);
    return $result && $result['permission_value'];
}

// REQUEST TO JOIN CLUB (FIXED WITH NOTIFICATIONS TO CLUB OFFICERS)
if ($action === 'request-join' && $method === 'POST') {
    if (!isLoggedIn()) jsonResponse(false, null, 'Login required');
    
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['access_code'])) jsonResponse(false, null, 'Access code is required');
        
        $club = dbQueryOne("SELECT id, name FROM clubs WHERE access_code = ? AND is_active = TRUE", 
            [strtoupper($data['access_code'])]);
        if (!$club) jsonResponse(false, null, 'Invalid access code');
        
        $existing = dbQueryOne("SELECT id, status FROM club_members WHERE club_id = ? AND user_id = ?", 
            [$club['id'], getCurrentUserId()]);
        if ($existing) jsonResponse(false, null, $existing['status'] === 'active' ? 'Already a member' : 'Removed from club');
        
        $pending = dbQueryOne("SELECT id FROM club_join_requests WHERE club_id = ? AND user_id = ? AND status = 'pending'", 
            [$club['id'], getCurrentUserId()]);
        if ($pending) jsonResponse(false, null, 'Already have pending request');
        
        $sql = "INSERT INTO club_join_requests (club_id, user_id, access_code_used, message) VALUES (?, ?, ?, ?)";
        $requestId = dbExecute($sql, [$club['id'], getCurrentUserId(), strtoupper($data['access_code']), sanitizeInput($data['message'] ?? '')]);
        
        // Notify the requesting user
        createNotification(
            getCurrentUserId(),
            'Join Request Submitted',
            'Your request to join "' . $club['name'] . '" has been submitted.',
            'info'
        );
        
        // **FIX: Notify all club officers who can manage members**
        $currentUser = getCurrentUser();
        $requesterName = $currentUser['first_name'] . ' ' . $currentUser['last_name'];
        
        // Get all users with manage_members permission for this club
        $sql = "SELECT DISTINCT cm.user_id 
                FROM club_members cm
                JOIN role_permissions rp ON cm.role_id = rp.role_id
                WHERE cm.club_id = ? 
                AND cm.status = 'active' 
                AND rp.permission_key = 'manage_members' 
                AND rp.permission_value = 1";
        $officers = dbQuery($sql, [$club['id']]);
        
        // Send notification to each officer
        foreach ($officers as $officer) {
            createNotification(
                $officer['user_id'],
                'New Join Request',
                $requesterName . ' has requested to join "' . $club['name'] . '". Please review the request in the Members section.',
                'info'
            );
        }
        
        jsonResponse(true, ['request_id' => $requestId, 'club_name' => $club['name']], 'Join request submitted');
    } catch (Exception $e) {
        error_log('Join request error: ' . $e->getMessage());
        jsonResponse(false, null, 'Error creating join request');
    }
}

// GET PENDING JOIN REQUESTS
if ($action === 'pending' && $method === 'GET') {
    if (!isLoggedIn()) jsonResponse(false, null, 'Login required');
    $clubId = $_GET['club_id'] ?? null;
    if (!$clubId) jsonResponse(false, null, 'Club ID required');
    
    try {
        if (!checkPermission($clubId, getCurrentUserId(), 'manage_members')) {
            jsonResponse(false, null, 'Permission denied');
        }
        $sql = "SELECT jr.*, u.email, u.first_name, u.last_name FROM club_join_requests jr
                JOIN users u ON jr.user_id = u.id WHERE jr.club_id = ? AND jr.status = 'pending'
                ORDER BY jr.created_at ASC";
        jsonResponse(true, dbQuery($sql, [$clubId]));
    } catch (Exception $e) {
        error_log('Get join requests error: ' . $e->getMessage());
        jsonResponse(false, null, 'Error fetching join requests');
    }
}

// APPROVE JOIN REQUEST
if ($action === 'approve' && $method === 'POST') {
    if (!isLoggedIn()) jsonResponse(false, null, 'Login required');
    
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $requestId = $data['request_id'] ?? null;
        $roleId = $data['role_id'] ?? null;
        if (!$requestId || !$roleId) jsonResponse(false, null, 'Request ID and Role ID required');
        
        $request = dbQueryOne("SELECT * FROM club_join_requests WHERE id = ? AND status = 'pending'", [$requestId]);
        if (!$request) jsonResponse(false, null, 'Request not found or already processed');
        
        if (!checkPermission($request['club_id'], getCurrentUserId(), 'manage_members')) {
            jsonResponse(false, null, 'Permission denied');
        }
        
        $role = dbQueryOne("SELECT id FROM club_roles WHERE id = ? AND club_id = ?", [$roleId, $request['club_id']]);
        if (!$role) jsonResponse(false, null, 'Invalid role for this club');
        
        dbBeginTransaction();
        try {
            dbExecute("INSERT INTO club_members (club_id, user_id, role_id, status) VALUES (?, ?, ?, 'active')", 
                [$request['club_id'], $request['user_id'], $roleId]);
            dbExecute("UPDATE club_join_requests SET status = 'approved', reviewed_by = ?, reviewed_at = NOW(), assigned_role_id = ? WHERE id = ?", 
                [getCurrentUserId(), $roleId, $requestId]);
            
            $generalRoom = dbQueryOne("SELECT id FROM chat_rooms WHERE club_id = ? AND is_general = TRUE", [$request['club_id']]);
            if ($generalRoom) {
                dbExecute("INSERT INTO chat_room_members (room_id, user_id) VALUES (?, ?)", [$generalRoom['id'], $request['user_id']]);
            }
            
            createNotification(
                $request['user_id'],
                'Join Request Approved!',
                'Your request to join the club has been approved.',
                'success'
            );
            
            dbCommit();
            jsonResponse(true, null, 'Member approved and added to club');
        } catch (Exception $e) {
            dbRollback();
            throw $e;
        }
    } catch (Exception $e) {
        error_log('Approve join error: ' . $e->getMessage());
        jsonResponse(false, null, 'Error approving join request');
    }
}

// REJECT JOIN REQUEST
if ($action === 'reject' && $method === 'POST') {
    if (!isLoggedIn()) jsonResponse(false, null, 'Login required');
    
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $requestId = $data['request_id'] ?? null;
        if (!$requestId) jsonResponse(false, null, 'Request ID required');
        
        $request = dbQueryOne("SELECT * FROM club_join_requests WHERE id = ? AND status = 'pending'", [$requestId]);
        if (!$request) jsonResponse(false, null, 'Request not found or already processed');
        
        if (!checkPermission($request['club_id'], getCurrentUserId(), 'manage_members')) {
            jsonResponse(false, null, 'Permission denied');
        }
        
        dbExecute("UPDATE club_join_requests SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW(), rejection_reason = ? WHERE id = ?", 
            [getCurrentUserId(), $data['reason'] ?? '', $requestId]);
        
        createNotification(
            $request['user_id'],
            'Join Request Declined',
            'Your request to join the club has been declined.',
            'warning'
        );
        
        jsonResponse(true, null, 'Request rejected');
    } catch (Exception $e) {
        error_log('Reject join error: ' . $e->getMessage());
        jsonResponse(false, null, 'Error rejecting request');
    }
}

jsonResponse(false, null, 'Invalid action');
