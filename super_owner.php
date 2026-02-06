<?php
require_once '../database/db.php';
header('Content-Type: application/json');

if (!isLoggedIn() || !isset($_SESSION['is_system_owner']) || !$_SESSION['is_system_owner']) {
    jsonResponse(false, null, 'Access denied - System owner only');
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_REQUEST['action'] ?? '';

// System statistics
if ($action === 'stats' && $method === 'GET') {
    try {
        $totalUsers = dbQueryOne("SELECT COUNT(*) as count FROM users WHERE is_active = TRUE");
        $totalClubs = dbQueryOne("SELECT COUNT(*) as count FROM clubs WHERE is_active = TRUE");
        $pendingRequests = dbQueryOne("SELECT COUNT(*) as count FROM club_creation_requests WHERE status = 'pending'");
        $totalAnnouncements = dbQueryOne("SELECT COUNT(*) as count FROM announcements");
        $totalEvents = dbQueryOne("SELECT COUNT(*) as count FROM events WHERE is_cancelled = FALSE");
        $usersToday = dbQueryOne("SELECT COUNT(*) as count FROM users WHERE DATE(created_at) = CURDATE()");
        $activeClubs = dbQueryOne("SELECT COUNT(DISTINCT club_id) as count FROM club_members WHERE last_activity >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        
        jsonResponse(true, [
            'total_users' => $totalUsers['count'],
            'total_clubs' => $totalClubs['count'],
            'pending_requests' => $pendingRequests['count'],
            'total_announcements' => $totalAnnouncements['count'],
            'total_events' => $totalEvents['count'],
            'users_today' => $usersToday['count'],
            'active_clubs' => $activeClubs['count']
        ]);
    } catch (Exception $e) {
        error_log('System stats error: ' . $e->getMessage());
        jsonResponse(false, null, 'Error fetching statistics');
    }
}

// Get all clubs
if ($action === 'clubs' && $method === 'GET') {
    try {
        $sql = "SELECT c.*, u.first_name as president_first_name, u.last_name as president_last_name,
                (SELECT COUNT(*) FROM club_members WHERE club_id = c.id AND status = 'active') as member_count
                FROM clubs c LEFT JOIN users u ON c.current_president_id = u.id ORDER BY c.created_at DESC";
        jsonResponse(true, dbQuery($sql));
    } catch (Exception $e) {
        error_log('Get clubs error: ' . $e->getMessage());
        jsonResponse(false, null, 'Error fetching clubs');
    }
}

// Get all users  
if ($action === 'users' && $method === 'GET') {
    try {
        $sql = "SELECT u.id, u.email, u.first_name, u.last_name, u.is_active, u.created_at, u.last_login,
                (SELECT COUNT(*) FROM club_members WHERE user_id = u.id AND status = 'active') as club_count
                FROM users u WHERE u.is_system_owner = FALSE ORDER BY u.created_at DESC";
        jsonResponse(true, dbQuery($sql));
    } catch (Exception $e) {
        error_log('Get users error: ' . $e->getMessage());
        jsonResponse(false, null, 'Error fetching users');
    }
}

// Get pending requests (FIXED: using created_at instead of requested_at)
if ($action === 'pending-requests' && $method === 'GET') {
    try {
        $sql = "SELECT cr.*, u.email, u.first_name as requester_first_name, u.last_name as requester_last_name, 
                cr.created_at as requested_at
                FROM club_creation_requests cr JOIN users u ON cr.requested_by = u.id
                WHERE cr.status = 'pending' ORDER BY cr.created_at ASC";
        jsonResponse(true, dbQuery($sql));
    } catch (Exception $e) {
        error_log('Get requests error: ' . $e->getMessage());
        jsonResponse(false, null, 'Error fetching requests');
    }
}

// Approve club
if ($action === 'approve-club' && $method === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $requestId = $data['request_id'] ?? null;
        if (!$requestId) jsonResponse(false, null, 'Request ID required');
        
        $request = dbQueryOne("SELECT * FROM club_creation_requests WHERE id = ? AND status = 'pending'", [$requestId]);
        if (!$request) jsonResponse(false, null, 'Request not found or already processed');
        
        dbBeginTransaction();
        try {
            $accessCode = generateAccessCode($request['club_name']);
            
            $sql = "INSERT INTO clubs (name, description, staff_advisor, access_code, current_president_id, created_from_request_id) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $clubId = dbExecute($sql, [$request['club_name'], $request['description'], $request['staff_advisor'], 
                $accessCode, $request['requested_by'], $requestId]);
            if (!$clubId) throw new Exception('Failed to create club');
            
            // Create default roles
            $presidentRoleId = dbExecute("INSERT INTO club_roles (club_id, role_name, role_description, is_system_role) 
                VALUES (?, 'President', 'Club president with full permissions', TRUE)", [$clubId]);
            $vpRoleId = dbExecute("INSERT INTO club_roles (club_id, role_name, role_description, is_system_role) 
                VALUES (?, 'Vice President', 'Assists president and manages operations', TRUE)", [$clubId]);
            $memberRoleId = dbExecute("INSERT INTO club_roles (club_id, role_name, role_description, is_system_role) 
                VALUES (?, 'Member', 'Regular club member', TRUE)", [$clubId]);
            
            // Set permissions for President (all permissions)
            $permissions = ['view_announcements', 'create_announcements', 'edit_announcements', 'delete_announcements',
                'view_events', 'create_events', 'edit_events', 'delete_events', 'view_members', 'manage_members', 'remove_members',
                'view_attendance', 'export_attendance', 'view_stats', 'modify_club_settings', 'create_roles', 'assign_roles', 
                'manage_roles', 'access_chat'];
            
            foreach ($permissions as $perm) {
                dbExecute("INSERT INTO role_permissions (role_id, permission_key, permission_value) VALUES (?, ?, TRUE)", 
                    [$presidentRoleId, $perm]);
            }
            
            // Limited permissions for VP
            $vpPermissions = ['view_announcements', 'create_announcements', 'edit_announcements', 'view_events', 
                'create_events', 'edit_events', 'view_members', 'manage_members', 'view_attendance', 'export_attendance', 
                'view_stats', 'assign_roles', 'access_chat'];
            
            foreach ($permissions as $perm) {
                $value = in_array($perm, $vpPermissions) ? 'TRUE' : 'FALSE';
                dbExecute("INSERT INTO role_permissions (role_id, permission_key, permission_value) VALUES (?, ?, $value)", 
                    [$vpRoleId, $perm]);
            }
            
            // Basic permissions for Member
            $memberPermissions = ['view_announcements', 'view_events', 'view_members', 'access_chat'];
            foreach ($permissions as $perm) {
                $value = in_array($perm, $memberPermissions) ? 'TRUE' : 'FALSE';
                dbExecute("INSERT INTO role_permissions (role_id, permission_key, permission_value) VALUES (?, ?, $value)", 
                    [$memberRoleId, $perm]);
            }
            
            // Add requester as president
            dbExecute("INSERT INTO club_members (club_id, user_id, role_id, is_president, status) 
                VALUES (?, ?, ?, TRUE, 'active')", [$clubId, $request['requested_by'], $presidentRoleId]);
            
            // Create general chat room
            $roomId = dbExecute("INSERT INTO chat_rooms (club_id, room_name, description, created_by, is_general) 
                VALUES (?, 'General', 'Main chat room for all members', ?, TRUE)", [$clubId, $request['requested_by']]);
            dbExecute("INSERT INTO chat_room_members (room_id, user_id) VALUES (?, ?)", [$roomId, $request['requested_by']]);
            
            // Update request status
            dbExecute("UPDATE club_creation_requests SET status = 'approved', reviewed_at = NOW() WHERE id = ?", [$requestId]);
            
            createNotification(
                $request['requested_by'],
                'Club Approved!',
                'Your club "' . $request['club_name'] . '" has been approved! Access code: ' . $accessCode,
                'success'
            );
            
            dbCommit();
            jsonResponse(true, ['club_id' => $clubId, 'access_code' => $accessCode], 'Club created successfully');
        } catch (Exception $e) {
            dbRollback();
            throw $e;
        }
    } catch (Exception $e) {
        error_log('Approve club error: ' . $e->getMessage());
        jsonResponse(false, null, 'Error approving club request');
    }
}

// Reject club
if ($action === 'reject-club' && $method === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $requestId = $data['request_id'] ?? null;
        if (!$requestId) jsonResponse(false, null, 'Request ID required');
        
        $request = dbQueryOne("SELECT requested_by FROM club_creation_requests WHERE id = ? AND status = 'pending'", [$requestId]);
        if (!$request) jsonResponse(false, null, 'Request not found or already processed');
        
        $sql = "UPDATE club_creation_requests SET status = 'rejected', reviewed_at = NOW(), rejection_reason = ? 
                WHERE id = ? AND status = 'pending'";
        $result = dbExecute($sql, [$data['reason'] ?? '', $requestId]);
        
        if ($result) {
            createNotification(
                $request['requested_by'],
                'Club Request Declined',
                'Your club creation request has been declined.',
                'warning'
            );
        }
        
        jsonResponse(!!$result, null, $result ? 'Request rejected' : 'Request not found or already processed');
    } catch (Exception $e) {
        error_log('Reject club error: ' . $e->getMessage());
        jsonResponse(false, null, 'Error rejecting request');
    }
}

// Deactivate user
if ($action === 'deactivate-user' && $method === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $userId = $data['user_id'] ?? null;
        if (!$userId) jsonResponse(false, null, 'User ID required');
        
        $user = dbQueryOne("SELECT is_system_owner FROM users WHERE id = ?", [$userId]);
        if ($user && $user['is_system_owner']) jsonResponse(false, null, 'Cannot deactivate system owner');
        
        dbExecute("UPDATE users SET is_active = FALSE WHERE id = ?", [$userId]);
        jsonResponse(true, null, 'User deactivated');
    } catch (Exception $e) {
        error_log('Deactivate user error: ' . $e->getMessage());
        jsonResponse(false, null, 'Error deactivating user');
    }
}

// Activate user
if ($action === 'activate-user' && $method === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $userId = $data['user_id'] ?? null;
        if (!$userId) jsonResponse(false, null, 'User ID required');
        
        dbExecute("UPDATE users SET is_active = TRUE WHERE id = ?", [$userId]);
        jsonResponse(true, null, 'User activated');
    } catch (Exception $e) {
        error_log('Activate user error: ' . $e->getMessage());
        jsonResponse(false, null, 'Error activating user');
    }
}

// Delete club
if ($action === 'delete-club' && $method === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $clubId = $data['club_id'] ?? null;
        if (!$clubId) jsonResponse(false, null, 'Club ID required');
        
        dbExecute("UPDATE clubs SET is_active = FALSE WHERE id = ?", [$clubId]);
        jsonResponse(true, null, 'Club deactivated');
    } catch (Exception $e) {
        error_log('Delete club error: ' . $e->getMessage());
        jsonResponse(false, null, 'Error deleting club');
    }
}

jsonResponse(false, null, 'Invalid action');