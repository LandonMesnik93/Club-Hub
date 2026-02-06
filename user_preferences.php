<?php
require_once '../database/db.php';
header('Content-Type: application/json');

if (!isLoggedIn()) jsonResponse(false, null, 'Login required');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_REQUEST['action'] ?? '';

if ($action === 'get' && $method === 'GET') {
    try {
        $sql = "SELECT * FROM user_preferences WHERE user_id = ?";
        $prefs = dbQueryOne($sql, [getCurrentUserId()]);
        if (!$prefs) {
            dbExecute("INSERT INTO user_preferences (user_id) VALUES (?)", [getCurrentUserId()]);
            $prefs = dbQueryOne($sql, [getCurrentUserId()]);
        }
        jsonResponse(true, $prefs);
    } catch (Exception $e) {
        error_log('Get preferences error: ' . $e->getMessage());
        jsonResponse(false, null, 'Error fetching preferences');
    }
}

if ($action === 'update' && $method === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $updates = [];
        $params = [];
        
        $fields = ['theme', 'notifications_enabled', 'email_notifications', 'announcement_notifications', 
                   'event_notifications', 'chat_notifications', 'language', 'timezone'];
        
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                $params[] = is_bool($data[$field]) ? ($data[$field] ? 1 : 0) : $data[$field];
            }
        }
        
        if (empty($updates)) jsonResponse(false, null, 'No updates provided');
        
        $sql = "UPDATE user_preferences SET " . implode(', ', $updates) . " WHERE user_id = ?";
        $params[] = getCurrentUserId();
        dbExecute($sql, $params);
        jsonResponse(true, null, 'Preferences updated');
    } catch (Exception $e) {
        error_log('Update preferences error: ' . $e->getMessage());
        jsonResponse(false, null, 'Error updating preferences');
    }
}

if ($action === 'update-profile' && $method === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $updates = [];
        $params = [];
        
        if (isset($data['first_name'])) {
            $updates[] = "first_name = ?";
            $params[] = sanitizeInput($data['first_name']);
        }
        if (isset($data['last_name'])) {
            $updates[] = "last_name = ?";
            $params[] = sanitizeInput($data['last_name']);
        }
        if (isset($data['email'])) {
            $existing = dbQueryOne("SELECT id FROM users WHERE email = ? AND id != ?", [$data['email'], getCurrentUserId()]);
            if ($existing) jsonResponse(false, null, 'Email already in use');
            $updates[] = "email = ?";
            $params[] = sanitizeInput($data['email']);
        }
        
        if (empty($updates)) jsonResponse(false, null, 'No updates provided');
        
        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
        $params[] = getCurrentUserId();
        dbExecute($sql, $params);
        
        if (isset($data['email'])) $_SESSION['email'] = $data['email'];
        if (isset($data['first_name'])) $_SESSION['first_name'] = $data['first_name'];
        if (isset($data['last_name'])) $_SESSION['last_name'] = $data['last_name'];
        
        jsonResponse(true, null, 'Profile updated');
    } catch (Exception $e) {
        error_log('Update profile error: ' . $e->getMessage());
        jsonResponse(false, null, 'Error updating profile');
    }
}

if ($action === 'change-password' && $method === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['current_password']) || empty($data['new_password'])) {
            jsonResponse(false, null, 'Current and new password required');
        }
        if (strlen($data['new_password']) < 8) {
            jsonResponse(false, null, 'Password must be at least 8 characters');
        }
        
        $user = dbQueryOne("SELECT password_hash FROM users WHERE id = ?", [getCurrentUserId()]);
        if (!verifyPassword($data['current_password'], $user['password_hash'])) {
            jsonResponse(false, null, 'Current password is incorrect');
        }
        
        dbExecute("UPDATE users SET password_hash = ? WHERE id = ?", [hashPassword($data['new_password']), getCurrentUserId()]);
        jsonResponse(true, null, 'Password changed successfully');
    } catch (Exception $e) {
        error_log('Change password error: ' . $e->getMessage());
        jsonResponse(false, null, 'Error changing password');
    }
}

jsonResponse(false, null, 'Invalid action');
