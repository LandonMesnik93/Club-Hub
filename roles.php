<?php
/**
 * api/roles.php
 * API endpoint for role management
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
    // LIST ROLES FOR A CLUB
    // ============================================
    if ($action === 'list') {
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
        
        // Get all roles for this club
        $stmt = $pdo->prepare("
            SELECT 
                id,
                role_name,
                role_description as description,
                is_system_role,
                created_at
            FROM club_roles
            WHERE club_id = ?
            ORDER BY 
                CASE 
                    WHEN role_name = 'President' THEN 0
                    WHEN role_name LIKE '%Vice%' OR role_name LIKE '%VP%' THEN 1
                    ELSE 2
                END,
                role_name ASC
        ");
        $stmt->execute([$clubId]);
        $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Add is_president flag based on role name
        foreach ($roles as &$role) {
            $role['is_president'] = (stripos($role['role_name'], 'president') !== false && 
                                     stripos($role['role_name'], 'vice') === false);
        }
        
        echo json_encode([
            'success' => true,
            'data' => $roles,
            'count' => count($roles)
        ]);
    }
    
    // ============================================
    // GET ROLE DETAILS
    // ============================================
    elseif ($action === 'get') {
        $roleId = $_GET['role_id'] ?? null;
        
        if (!$roleId) {
            throw new Exception('Role ID is required');
        }
        
        // Get role details with permissions
        $stmt = $pdo->prepare("
            SELECT 
                cr.id,
                cr.club_id,
                cr.role_name,
                cr.role_description as description,
                cr.is_system_role,
                cr.created_at
            FROM club_roles cr
            WHERE cr.id = ?
        ");
        $stmt->execute([$roleId]);
        $role = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$role) {
            throw new Exception('Role not found');
        }
        
        // Verify user has access to this club
        $stmt = $pdo->prepare("
            SELECT id FROM club_members 
            WHERE club_id = ? AND user_id = ? AND status = 'active'
        ");
        $stmt->execute([$role['club_id'], $_SESSION['user_id']]);
        if (!$stmt->fetch()) {
            throw new Exception('You do not have access to this role');
        }
        
        // Get permissions for this role
        $stmt = $pdo->prepare("
            SELECT permission_key, permission_value
            FROM role_permissions
            WHERE role_id = ?
        ");
        $stmt->execute([$roleId]);
        $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $role['permissions'] = $permissions;
        $role['is_president'] = (stripos($role['role_name'], 'president') !== false && 
                                 stripos($role['role_name'], 'vice') === false);
        
        echo json_encode([
            'success' => true,
            'data' => $role
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