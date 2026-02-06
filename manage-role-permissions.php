<?php
/**
 * manage-role-permissions.php
 * Complete role and permission management system with LIVE PREVIEW
 * Accessible ONLY to Club Presidents and Vice Presidents
 */

session_start();
require_once __DIR__ . '/database/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get user and club data
try {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("SELECT id, first_name, last_name FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        session_destroy();
        header('Location: login.php');
        exit;
    }
    
    // Get active club
    $clubId = $_SESSION['active_club_id'] ?? null;
    if (!$clubId) {
        header('Location: index.php');
        exit;
    }
    
    // Check if user is President or Vice President
    $stmt = $pdo->prepare("
        SELECT cm.is_president, cr.role_name 
        FROM club_members cm 
        JOIN club_roles cr ON cm.role_id = cr.id
        WHERE cm.club_id = ? AND cm.user_id = ? AND cm.status = 'active'
    ");
    $stmt->execute([$clubId, $user['id']]);
    $userRole = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Only presidents and vice presidents can access this page
    $isPresident = $userRole['is_president'] == 1;
    $isVicePresident = stripos($userRole['role_name'], 'vice') !== false || stripos($userRole['role_name'], 'vp') !== false;
    
    if (!$isPresident && !$isVicePresident) {
        die('Access Denied: Only Club Presidents and Vice Presidents can manage role permissions.');
    }
    
    // Get club info
    $stmt = $pdo->prepare("SELECT name FROM clubs WHERE id = ?");
    $stmt->execute([$clubId]);
    $club = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Generate CSRF token
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    die("An error occurred. Please try again later.");
}

$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid request');
    }
    
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'update_permission') {
            $roleId = (int)$_POST['role_id'];
            $permissionKey = $_POST['permission_key'];
            $permissionValue = (int)$_POST['permission_value'];
            
            // Verify role belongs to this club
            $stmt = $pdo->prepare("SELECT id FROM club_roles WHERE id = ? AND club_id = ?");
            $stmt->execute([$roleId, $clubId]);
            if (!$stmt->fetch()) {
                throw new Exception("Invalid role");
            }
            
            // Update or insert permission
            $stmt = $pdo->prepare("
                INSERT INTO role_permissions (role_id, permission_key, permission_value)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE permission_value = ?
            ");
            $stmt->execute([$roleId, $permissionKey, $permissionValue, $permissionValue]);
            
            $message = "Permission updated successfully!";
            $messageType = 'success';
            
            // Log activity
            logActivity($user['id'], 'update_role_permission', [
                'club_id' => $clubId,
                'role_id' => $roleId,
                'permission_key' => $permissionKey,
                'permission_value' => $permissionValue
            ]);
            
        } elseif ($action === 'create_role') {
            $roleName = trim($_POST['role_name']);
            $description = trim($_POST['description']);
            
            if (empty($roleName)) {
                throw new Exception("Role name is required");
            }
            
            // Create role
            $stmt = $pdo->prepare("
                INSERT INTO club_roles (club_id, role_name, description, is_president)
                VALUES (?, ?, ?, 0)
            ");
            $stmt->execute([$clubId, $roleName, $description]);
            
            $message = "Role created successfully!";
            $messageType = 'success';
            
            logActivity($user['id'], 'create_role', [
                'club_id' => $clubId,
                'role_name' => $roleName
            ]);
            
        } elseif ($action === 'delete_role') {
            $roleId = (int)$_POST['role_id'];
            
            // Can't delete president role
            $stmt = $pdo->prepare("SELECT is_president FROM club_roles WHERE id = ? AND club_id = ?");
            $stmt->execute([$roleId, $clubId]);
            $role = $stmt->fetch();
            
            if ($role && $role['is_president']) {
                throw new Exception("Cannot delete president role");
            }
            
            // Check if role has members
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM club_members WHERE role_id = ? AND status = 'active'");
            $stmt->execute([$roleId]);
            $memberCount = $stmt->fetchColumn();
            
            if ($memberCount > 0) {
                throw new Exception("Cannot delete role with active members. Reassign members first.");
            }
            
            // Delete role and permissions
            dbBeginTransaction();
            try {
                $stmt = $pdo->prepare("DELETE FROM role_permissions WHERE role_id = ?");
                $stmt->execute([$roleId]);
                
                $stmt = $pdo->prepare("DELETE FROM club_roles WHERE id = ? AND club_id = ?");
                $stmt->execute([$roleId, $clubId]);
                
                dbCommit();
                
                $message = "Role deleted successfully!";
                $messageType = 'success';
                
                logActivity($user['id'], 'delete_role', [
                    'club_id' => $clubId,
                    'role_id' => $roleId
                ]);
            } catch (Exception $e) {
                dbRollback();
                throw $e;
            }
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = 'error';
    }
}

// Get all roles for this club
$stmt = $pdo->prepare("
    SELECT id, role_name, description, is_president, created_at
    FROM club_roles
    WHERE club_id = ?
    ORDER BY is_president DESC, role_name ASC
");
$stmt->execute([$clubId]);
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Selected role
$selectedRoleId = (int)($_GET['role_id'] ?? ($roles[0]['id'] ?? 0));
$selectedRole = null;
$rolePermissions = [];

if ($selectedRoleId > 0) {
    foreach ($roles as $role) {
        if ($role['id'] == $selectedRoleId) {
            $selectedRole = $role;
            break;
        }
    }
    
    if ($selectedRole) {
        $stmt = $pdo->prepare("SELECT permission_key, permission_value FROM role_permissions WHERE role_id = ?");
        $stmt->execute([$selectedRoleId]);
        $perms = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($perms as $perm) {
            $rolePermissions[$perm['permission_key']] = (bool)$perm['permission_value'];
        }
    }
}

// Define all available permissions with categories
$permissionCategories = [
    'Core Access' => [
        ['key' => 'view_announcements', 'name' => 'View Announcements', 'desc' => 'Can see club announcements'],
        ['key' => 'create_announcements', 'name' => 'Create Announcements', 'desc' => 'Can post new announcements'],
        ['key' => 'edit_announcements', 'name' => 'Edit Announcements', 'desc' => 'Can modify existing announcements'],
        ['key' => 'delete_announcements', 'name' => 'Delete Announcements', 'desc' => 'Can remove announcements'],
    ],
    'Events' => [
        ['key' => 'view_events', 'name' => 'View Events', 'desc' => 'Can see club events'],
        ['key' => 'create_events', 'name' => 'Create Events', 'desc' => 'Can create new events'],
        ['key' => 'edit_events', 'name' => 'Edit Events', 'desc' => 'Can modify events'],
        ['key' => 'delete_events', 'name' => 'Delete Events', 'desc' => 'Can remove events'],
    ],
    'Members' => [
        ['key' => 'view_members', 'name' => 'View Members', 'desc' => 'Can see member list'],
        ['key' => 'manage_members', 'name' => 'Manage Members', 'desc' => 'Can add/remove members and approve join requests'],
        ['key' => 'edit_member_roles', 'name' => 'Edit Member Roles', 'desc' => 'Can change member roles'],
    ],
    'Attendance' => [
        ['key' => 'view_attendance', 'name' => 'View Attendance', 'desc' => 'Can see attendance records'],
        ['key' => 'take_attendance', 'name' => 'Take Attendance', 'desc' => 'Can mark attendance'],
        ['key' => 'edit_attendance', 'name' => 'Edit Attendance', 'desc' => 'Can modify attendance records'],
    ],
    'Communication' => [
        ['key' => 'access_chat', 'name' => 'Access Chat', 'desc' => 'Can use club chat'],
        ['key' => 'create_chat_rooms', 'name' => 'Create Chat Rooms', 'desc' => 'Can create new chat rooms'],
        ['key' => 'manage_chat_rooms', 'name' => 'Manage Chat Rooms', 'desc' => 'Can edit/delete chat rooms'],
    ],
    'Administration' => [
        ['key' => 'modify_club_settings', 'name' => 'Modify Club Settings', 'desc' => 'Can change club information'],
        ['key' => 'manage_roles', 'name' => 'Manage Roles', 'desc' => 'Can create and manage roles'],
        ['key' => 'view_analytics', 'name' => 'View Analytics', 'desc' => 'Can see club statistics and reports'],
    ],
];

// Get statistics for preview
$stats = [
    'total_members' => $pdo->prepare("SELECT COUNT(*) FROM club_members WHERE club_id = ? AND status = 'active'"),
    'upcoming_events' => $pdo->prepare("SELECT COUNT(*) FROM club_events WHERE club_id = ? AND event_date >= CURDATE()"),
    'attendance_rate' => 85, // Placeholder
    'messages_today' => 24, // Placeholder
];

foreach ($stats as $key => $stmt) {
    if ($stmt instanceof PDOStatement) {
        $stmt->execute([$clubId]);
        $stats[$key] = $stmt->fetchColumn();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Roles - <?php echo htmlspecialchars($club['name']); ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    
    <style>
        .permissions-container {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 2rem;
            align-items: start;
        }
        
        @media (max-width: 1400px) {
            .permissions-container {
                grid-template-columns: 1fr;
            }
            
            .preview-sidebar {
                position: relative !important;
                top: auto !important;
                max-height: none !important;
            }
        }
        
        .page-header {
            margin-bottom: 2rem;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .page-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .page-subtitle {
            color: var(--text-secondary);
        }
        
        .alert {
            padding: 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid var(--success);
            color: var(--success);
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid var(--danger);
            color: var(--danger);
        }
        
        .role-selector-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        
        .role-selector-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .role-selector-header h3 {
            font-size: 1.125rem;
            font-weight: 600;
        }
        
        .role-tabs {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .role-tab {
            padding: 0.75rem 1.5rem;
            background: var(--bg-tertiary);
            border: 2px solid transparent;
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: all var(--transition);
            font-weight: 600;
            font-size: 0.875rem;
        }
        
        .role-tab:hover {
            border-color: var(--primary);
            background: var(--bg-card);
        }
        
        .role-tab.active {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border-color: var(--primary);
        }
        
        .role-tab.president {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }
        
        .role-tab.president.active {
            background: var(--danger);
            color: white;
        }
        
        .role-info-banner {
            background: var(--bg-tertiary);
            padding: 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.5rem;
        }
        
        .role-info-banner h4 {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .role-info-banner p {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }
        
        .permissions-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        
        .category-section {
            margin-bottom: 2rem;
        }
        
        .category-header {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--border);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .permission-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            border-bottom: 1px solid var(--border);
            transition: background var(--transition);
        }
        
        .permission-row:last-child {
            border-bottom: none;
        }
        
        .permission-row:hover {
            background: var(--bg-tertiary);
        }
        
        .permission-info {
            flex: 1;
        }
        
        .permission-name {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }
        
        .permission-desc {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }
        
        .permission-toggle {
            position: relative;
            width: 52px;
            height: 28px;
        }
        
        .permission-toggle input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 28px;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .toggle-slider {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
        }
        
        input:checked + .toggle-slider:before {
            transform: translateX(24px);
        }
        
        input:disabled + .toggle-slider {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        /* PREVIEW SIDEBAR */
        .preview-sidebar {
            position: sticky;
            top: 20px;
            max-height: calc(100vh - 40px);
            overflow-y: auto;
        }
        
        .preview-container {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        
        .preview-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border);
        }
        
        .preview-header h3 {
            font-size: 1.125rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .preview-badge {
            background: var(--success);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 700;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        .preview-dashboard {
            background: var(--bg-tertiary);
            border-radius: var(--radius-md);
            padding: 1rem;
        }
        
        .preview-section-title {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--text-secondary);
            text-transform: uppercase;
            margin-bottom: 0.75rem;
            letter-spacing: 0.5px;
        }
        
        .preview-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }
        
        .preview-stat {
            background: var(--bg-card);
            padding: 1rem;
            border-radius: var(--radius-md);
            text-align: center;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
        }
        
        .preview-stat.hidden {
            opacity: 0.2;
            filter: blur(2px);
            transform: scale(0.95);
        }
        
        .preview-stat-icon {
            font-size: 1.75rem;
            margin-bottom: 0.5rem;
        }
        
        .preview-stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }
        
        .preview-stat-label {
            font-size: 0.7rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            font-weight: 600;
        }
        
        .preview-nav {
            display: grid;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }
        
        .preview-nav-item {
            background: var(--bg-card);
            padding: 0.875rem;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
        }
        
        .preview-nav-item.hidden {
            opacity: 0.2;
            filter: blur(2px);
            transform: translateX(-10px);
        }
        
        .preview-nav-item i {
            font-size: 1.25rem;
            width: 24px;
            text-align: center;
        }
        
        .preview-nav-item span {
            font-size: 0.875rem;
            font-weight: 600;
        }
        
        .preview-modules {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
        }
        
        .preview-module {
            background: var(--bg-card);
            padding: 1rem;
            border-radius: var(--radius-md);
            text-align: center;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
        }
        
        .preview-module.hidden {
            opacity: 0.2;
            filter: blur(2px);
            transform: scale(0.9);
        }
        
        .preview-module-icon {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .preview-module-name {
            font-size: 0.75rem;
            font-weight: 600;
            line-height: 1.3;
        }
        
        .preview-note {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid var(--primary);
            color: var(--primary);
            padding: 0.75rem;
            border-radius: var(--radius-md);
            font-size: 0.75rem;
            text-align: center;
            margin-top: 1.5rem;
        }
        
        .btn-create-role {
            background: var(--success);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: var(--radius-md);
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition);
        }
        
        .btn-create-role:hover {
            background: #059669;
            transform: translateY(-2px);
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.show {
            display: flex;
        }
        
        .modal-content {
            background: var(--bg-card);
            border-radius: var(--radius-xl);
            padding: 2rem;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .form-input, .form-textarea {
            width: 100%;
            padding: 0.875rem;
            border: 2px solid var(--border);
            border-radius: var(--radius-md);
            font-family: inherit;
        }
        
        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .modal-actions {
            display: flex;
            gap: 0.75rem;
            justify-content: flex-end;
        }
        
        .btn {
            padding: 0.875rem 1.5rem;
            border-radius: var(--radius-md);
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition);
        }
        
        .btn-secondary {
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Include your sidebar here -->
        <main class="main-content" style="margin-left: 0; width: 100%;">
            <div style="max-width: 1400px; margin: 0 auto; padding: 2rem;">
                <div class="page-header">
                    <a href="index.php" class="back-link">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                    <h1 class="page-title">🔐 Manage Roles & Permissions</h1>
                    <p class="page-subtitle">Configure what each role can do in your club</p>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?>">
                        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                
                <div class="permissions-container">
                    <!-- Left: Main Content -->
                    <div>
                        <!-- Role Selector -->
                        <div class="role-selector-card">
                            <div class="role-selector-header">
                                <h3>Select Role to Manage</h3>
                                <button class="btn-create-role" onclick="openCreateRoleModal()">
                                    <i class="fas fa-plus"></i> Create New Role
                                </button>
                            </div>
                            <div class="role-tabs">
                                <?php foreach ($roles as $role): ?>
                                    <div class="role-tab <?php echo $role['id'] == $selectedRoleId ? 'active' : ''; ?> <?php echo $role['is_president'] ? 'president' : ''; ?>"
                                         onclick="window.location.href='?role_id=<?php echo $role['id']; ?>'">
                                        <?php if ($role['is_president']): ?>
                                            <i class="fas fa-crown"></i>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($role['role_name']); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <?php if ($selectedRole): ?>
                            <!-- Role Info -->
                            <div class="role-info-banner">
                                <h4>
                                    <?php if ($selectedRole['is_president']): ?>
                                        <i class="fas fa-crown" style="color: var(--danger);"></i>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($selectedRole['role_name']); ?>
                                </h4>
                                <p><?php echo htmlspecialchars($selectedRole['description'] ?: 'No description provided'); ?></p>
                                <?php if ($selectedRole['is_president']): ?>
                                    <p style="color: var(--danger); margin-top: 0.5rem; font-weight: 600;">
                                        <i class="fas fa-lock"></i> President role cannot be modified or deleted
                                    </p>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Permissions Matrix -->
                            <div class="permissions-card">
                                <form method="POST" id="permissionsForm">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="action" value="update_permission">
                                    <input type="hidden" name="role_id" value="<?php echo $selectedRoleId; ?>">
                                    
                                    <?php foreach ($permissionCategories as $category => $permissions): ?>
                                        <div class="category-section">
                                            <div class="category-header">
                                                <i class="fas fa-folder"></i>
                                                <?php echo htmlspecialchars($category); ?>
                                            </div>
                                            
                                            <?php foreach ($permissions as $perm): ?>
                                                <div class="permission-row">
                                                    <div class="permission-info">
                                                        <div class="permission-name"><?php echo htmlspecialchars($perm['name']); ?></div>
                                                        <div class="permission-desc"><?php echo htmlspecialchars($perm['desc']); ?></div>
                                                    </div>
                                                    <label class="permission-toggle">
                                                        <input type="checkbox" 
                                                               data-permission="<?php echo $perm['key']; ?>"
                                                               <?php echo isset($rolePermissions[$perm['key']]) && $rolePermissions[$perm['key']] ? 'checked' : ''; ?>
                                                               <?php echo $selectedRole['is_president'] ? 'disabled' : ''; ?>
                                                               onchange="togglePermission('<?php echo $perm['key']; ?>', this.checked)">
                                                        <span class="toggle-slider"></span>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Right: Live Preview -->
                    <?php if ($selectedRole): ?>
                    <div class="preview-sidebar">
                        <div class="preview-container">
                            <div class="preview-header">
                                <h3>
                                    <i class="fas fa-eye"></i>
                                    Live Preview
                                </h3>
                                <span class="preview-badge">LIVE</span>
                            </div>
                            
                            <div class="preview-dashboard">
                                <!-- Stats Preview -->
                                <div class="preview-section-title">Dashboard Statistics</div>
                                <div class="preview-stats">
                                    <div class="preview-stat" id="preview-stat-members">
                                        <div class="preview-stat-icon">👥</div>
                                        <div class="preview-stat-value"><?php echo $stats['total_members']; ?></div>
                                        <div class="preview-stat-label">Members</div>
                                    </div>
                                    
                                    <div class="preview-stat" id="preview-stat-events">
                                        <div class="preview-stat-icon">📅</div>
                                        <div class="preview-stat-value"><?php echo $stats['upcoming_events']; ?></div>
                                        <div class="preview-stat-label">Events</div>
                                    </div>
                                    
                                    <div class="preview-stat" id="preview-stat-attendance">
                                        <div class="preview-stat-icon">📊</div>
                                        <div class="preview-stat-value"><?php echo $stats['attendance_rate']; ?>%</div>
                                        <div class="preview-stat-label">Attendance</div>
                                    </div>
                                    
                                    <div class="preview-stat" id="preview-stat-messages">
                                        <div class="preview-stat-icon">💬</div>
                                        <div class="preview-stat-value"><?php echo $stats['messages_today']; ?></div>
                                        <div class="preview-stat-label">Messages</div>
                                    </div>
                                </div>
                                
                                <!-- Navigation Preview -->
                                <div class="preview-section-title">Sidebar Navigation</div>
                                <div class="preview-nav">
                                    <div class="preview-nav-item" id="preview-nav-dashboard">
                                        <i class="fas fa-chart-line"></i>
                                        <span>Dashboard</span>
                                    </div>
                                    <div class="preview-nav-item" id="preview-nav-announcements">
                                        <i class="fas fa-bullhorn"></i>
                                        <span>Announcements</span>
                                    </div>
                                    <div class="preview-nav-item" id="preview-nav-events">
                                        <i class="fas fa-calendar-alt"></i>
                                        <span>Events</span>
                                    </div>
                                    <div class="preview-nav-item" id="preview-nav-members">
                                        <i class="fas fa-user-friends"></i>
                                        <span>Members</span>
                                    </div>
                                    <div class="preview-nav-item" id="preview-nav-signin">
                                        <i class="fas fa-id-card"></i>
                                        <span>Sign-In</span>
                                    </div>
                                    <div class="preview-nav-item" id="preview-nav-attendance">
                                        <i class="fas fa-check-circle"></i>
                                        <span>Attendance</span>
                                    </div>
                                    <div class="preview-nav-item" id="preview-nav-chat">
                                        <i class="fas fa-comments"></i>
                                        <span>Chat</span>
                                    </div>
                                </div>
                                
                                <!-- Modules Preview -->
                                <div class="preview-section-title">Admin Tools</div>
                                <div class="preview-modules">
                                    <div class="preview-module" id="preview-module-settings">
                                        <div class="preview-module-icon">⚙️</div>
                                        <div class="preview-module-name">Club Settings</div>
                                    </div>
                                    <div class="preview-module" id="preview-module-roles">
                                        <div class="preview-module-icon">🔐</div>
                                        <div class="preview-module-name">Roles</div>
                                    </div>
                                    <div class="preview-module" id="preview-module-analytics">
                                        <div class="preview-module-icon">📈</div>
                                        <div class="preview-module-name">Analytics</div>
                                    </div>
                                    <div class="preview-module" id="preview-module-chat-manage">
                                        <div class="preview-module-icon">💬</div>
                                        <div class="preview-module-name">Chat Rooms</div>
                                    </div>
                                </div>
                                
                                <div class="preview-note">
                                    💡 Preview updates in real-time as you toggle permissions
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Create Role Modal -->
    <div id="createRoleModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">Create New Role</div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="create_role">
                
                <div class="form-group">
                    <label class="form-label">Role Name *</label>
                    <input type="text" name="role_name" class="form-input" placeholder="e.g., Treasurer" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-textarea" placeholder="What does this role do?"></textarea>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeCreateRoleModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Role</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Permission to preview element mapping
        const previewMap = {
            // Stats
            'view_members': ['preview-stat-members'],
            'view_events': ['preview-stat-events'],
            'view_attendance': ['preview-stat-attendance'],
            'access_chat': ['preview-stat-messages'],
            
            // Navigation
            'view_announcements': ['preview-nav-announcements'],
            'view_events': ['preview-nav-events'],
            'view_members': ['preview-nav-members'],
            'view_attendance': ['preview-nav-attendance'],
            'access_chat': ['preview-nav-chat'],
            
            // Modules
            'modify_club_settings': ['preview-module-settings'],
            'manage_roles': ['preview-module-roles'],
            'view_analytics': ['preview-module-analytics'],
            'manage_chat_rooms': ['preview-module-chat-manage'],
        };
        
        // Initialize preview based on current permissions
        function initializePreview() {
            const checkboxes = document.querySelectorAll('input[type="checkbox"][data-permission]');
            checkboxes.forEach(checkbox => {
                updatePreviewElement(checkbox.dataset.permission, checkbox.checked);
            });
        }
        
        // Toggle permission with AJAX
        function togglePermission(permissionKey, isEnabled) {
            const formData = new FormData();
            formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
            formData.append('action', 'update_permission');
            formData.append('role_id', '<?php echo $selectedRoleId; ?>');
            formData.append('permission_key', permissionKey);
            formData.append('permission_value', isEnabled ? '1' : '0');
            
            fetch('', {
                method: 'POST',
                body: formData
            }).then(response => response.text())
              .then(data => {
                  // Update preview
                  updatePreviewElement(permissionKey, isEnabled);
              })
              .catch(error => console.error('Error:', error));
        }
        
        // Update preview elements
        function updatePreviewElement(permissionKey, isEnabled) {
            if (previewMap[permissionKey]) {
                previewMap[permissionKey].forEach(elementId => {
                    const element = document.getElementById(elementId);
                    if (element) {
                        if (isEnabled) {
                            element.classList.remove('hidden');
                            element.style.animation = 'fadeIn 0.3s ease-in';
                        } else {
                            element.classList.add('hidden');
                        }
                    }
                });
            }
        }
        
        // Modal functions
        function openCreateRoleModal() {
            document.getElementById('createRoleModal').classList.add('show');
        }
        
        function closeCreateRoleModal() {
            document.getElementById('createRoleModal').classList.remove('show');
        }
        
        // Close modal on outside click
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
            }
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', initializePreview);
        
        // Add animation styles
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeIn {
                from { opacity: 0; transform: scale(0.95); }
                to { opacity: 1; transform: scale(1); }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>