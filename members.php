<?php
/**
 * api/members.php
 * API endpoint for member management including role updates
 */

session_start();
require_once __DIR__ . '/../database/db.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$action = $_GET['action'] ?? '';
$pdo = getDBConnection();

try {
    // ============================================
    // UPDATE MEMBER ROLE
    // ============================================
    if ($action === 'update-role') {
        $input = json_decode(file_get_contents('php://input'), true);
        $clubId = $input['club_id'] ?? null;
        $userId = $input['user_id'] ?? null;
        $roleId = $input['role_id'] ?? null;
        
        if (!$clubId || !$userId || !$roleId) {
            throw new Exception('Missing required parameters');
        }
        
        // Verify requester has permission to edit member roles
        $stmt = $pdo->prepare("
            SELECT rp.permission_value 
            FROM club_members cm
            JOIN role_permissions rp ON cm.role_id = rp.role_id
            WHERE cm.club_id = ? AND cm.user_id = ? AND cm.status = 'active'
            AND rp.permission_key = 'edit_member_roles'
        ");
        $stmt->execute([$clubId, $_SESSION['user_id']]);
        $hasPermission = $stmt->fetchColumn();
        
        if (!$hasPermission) {
            throw new Exception('You do not have permission to edit member roles');
        }
        
        // Verify target user is actually a member of this club
        $stmt = $pdo->prepare("
            SELECT id FROM club_members 
            WHERE club_id = ? AND user_id = ? AND status = 'active'
        ");
        $stmt->execute([$clubId, $userId]);
        if (!$stmt->fetch()) {
            throw new Exception('User is not a member of this club');
        }
        
        // Verify target role exists and belongs to this club
        $stmt = $pdo->prepare("SELECT is_president FROM club_roles WHERE id = ? AND club_id = ?");
        $stmt->execute([$roleId, $clubId]);
        $role = $stmt->fetch();
        
        if (!$role) {
            throw new Exception('Invalid role');
        }
        
        // Cannot assign president role through this method
        if ($role['is_president']) {
            throw new Exception('Cannot assign president role through this method');
        }
        
        // Update member role
        $stmt = $pdo->prepare("
            UPDATE club_members 
            SET role_id = ?, is_president = 0
            WHERE club_id = ? AND user_id = ? AND status = 'active'
        ");
        $stmt->execute([$roleId, $clubId, $userId]);
        
        // Log the activity
        logActivity($_SESSION['user_id'], 'update_member_role', [
            'club_id' => $clubId,
            'target_user_id' => $userId,
            'new_role_id' => $roleId
        ]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Role updated successfully'
        ]);
    }
    
    // ============================================
    // LIST MEMBERS
    // ============================================
    elseif ($action === 'list') {
        $clubId = $_GET['club_id'] ?? null;
        
        if (!$clubId) {
            throw new Exception('Club ID is required');
        }
        
        // Verify user is a member of this club
        $stmt = $pdo->prepare("
            SELECT id FROM club_members 
            WHERE club_id = ? AND user_id = ? AND status = 'active'
        ");
        $stmt->execute([$clubId, $_SESSION['user_id']]);
        if (!$stmt->fetch()) {
            throw new Exception('You are not a member of this club');
        }
        
        // Get all active members
        $stmt = $pdo->prepare("
            SELECT 
                cm.id,
                cm.user_id,
                cm.is_president,
                cm.joined_at,
                u.first_name,
                u.last_name,
                u.email,
                cr.id as role_id,
                cr.role_name,
                cr.role_description
            FROM club_members cm
            JOIN users u ON cm.user_id = u.id
            JOIN club_roles cr ON cm.role_id = cr.id
            WHERE cm.club_id = ? AND cm.status = 'active'
            ORDER BY cm.is_president DESC, u.last_name ASC, u.first_name ASC
        ");
        $stmt->execute([$clubId]);
        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $members,
            'count' => count($members)
        ]);
    }
    
    // ============================================
    // REMOVE MEMBER
    // ============================================
    elseif ($action === 'remove') {
        $input = json_decode(file_get_contents('php://input'), true);
        $clubId = $input['club_id'] ?? null;
        $userId = $input['user_id'] ?? null;
        
        if (!$clubId || !$userId) {
            throw new Exception('Missing required parameters');
        }
        
        // Verify requester has permission to manage members
        $stmt = $pdo->prepare("
            SELECT rp.permission_value 
            FROM club_members cm
            JOIN role_permissions rp ON cm.role_id = rp.role_id
            WHERE cm.club_id = ? AND cm.user_id = ? AND cm.status = 'active'
            AND rp.permission_key = 'manage_members'
        ");
        $stmt->execute([$clubId, $_SESSION['user_id']]);
        $hasPermission = $stmt->fetchColumn();
        
        if (!$hasPermission) {
            throw new Exception('You do not have permission to remove members');
        }
        
        // Cannot remove yourself
        if ($userId == $_SESSION['user_id']) {
            throw new Exception('You cannot remove yourself from the club');
        }
        
        // Check if target user is president
        $stmt = $pdo->prepare("
            SELECT is_president FROM club_members 
            WHERE club_id = ? AND user_id = ? AND status = 'active'
        ");
        $stmt->execute([$clubId, $userId]);
        $member = $stmt->fetch();
        
        if ($member && $member['is_president']) {
            throw new Exception('Cannot remove the club president');
        }
        
        // Remove member (set status to removed)
        $stmt = $pdo->prepare("
            UPDATE club_members 
            SET status = 'removed'
            WHERE club_id = ? AND user_id = ? AND status = 'active'
        ");
        $stmt->execute([$clubId, $userId]);
        
        // Log the activity
        logActivity($_SESSION['user_id'], 'remove_member', [
            'club_id' => $clubId,
            'removed_user_id' => $userId
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Member removed successfully'
        ]);
    }
    
    // ============================================
    // INVALID ACTION
    // ============================================
    else {
        throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>