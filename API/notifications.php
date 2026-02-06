<?php
require_once '../database/db.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    jsonResponse(false, null, 'Login required');
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_REQUEST['action'] ?? '';

// Get notifications
if ($action === 'get' && $method === 'GET') {
    try {
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
        $unreadOnly = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true';
        
        $sql = "SELECT * FROM notifications WHERE user_id = ?";
        $params = [getCurrentUserId()];
        
        if ($unreadOnly) {
            $sql .= " AND is_read = FALSE";
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ?";
        $params[] = $limit;
        
        $notifications = dbQuery($sql, $params);
        jsonResponse(true, $notifications);
        
    } catch (Exception $e) {
        error_log('Get notifications error: ' . $e->getMessage());
        jsonResponse(false, null, 'Error fetching notifications');
    }
}

// Mark notification as read
if ($action === 'mark-read' && $method === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $notificationId = $data['notification_id'] ?? null;
        
        if (!$notificationId) {
            jsonResponse(false, null, 'Notification ID required');
        }
        
        $sql = "UPDATE notifications SET is_read = TRUE WHERE id = ? AND user_id = ?";
        $result = dbExecute($sql, [$notificationId, getCurrentUserId()]);
        
        jsonResponse(!!$result, null, $result ? 'Notification marked as read' : 'Failed');
        
    } catch (Exception $e) {
        error_log('Mark notification read error: ' . $e->getMessage());
        jsonResponse(false, null, 'Error marking notification as read');
    }
}

// Mark all notifications as read
if ($action === 'mark-all-read' && $method === 'POST') {
    try {
        $sql = "UPDATE notifications SET is_read = TRUE WHERE user_id = ? AND is_read = FALSE";
        $result = dbExecute($sql, [getCurrentUserId()]);
        
        jsonResponse(true, ['count' => $result], 'All notifications marked as read');
        
    } catch (Exception $e) {
        error_log('Mark all notifications read error: ' . $e->getMessage());
        jsonResponse(false, null, 'Error marking notifications as read');
    }
}

// Delete notification
if ($action === 'delete' && $method === 'DELETE') {
    try {
        $notificationId = $_GET['notification_id'] ?? null;
        
        if (!$notificationId) {
            jsonResponse(false, null, 'Notification ID required');
        }
        
        $sql = "DELETE FROM notifications WHERE id = ? AND user_id = ?";
        $result = dbExecute($sql, [$notificationId, getCurrentUserId()]);
        
        jsonResponse(!!$result, null, $result ? 'Notification deleted' : 'Failed');
        
    } catch (Exception $e) {
        error_log('Delete notification error: ' . $e->getMessage());
        jsonResponse(false, null, 'Error deleting notification');
    }
}

// Get unread count
if ($action === 'unread-count' && $method === 'GET') {
    try {
        $sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = FALSE";
        $result = dbQueryOne($sql, [getCurrentUserId()]);
        
        jsonResponse(true, ['count' => $result['count'] ?? 0]);
        
    } catch (Exception $e) {
        error_log('Get unread count error: ' . $e->getMessage());
        jsonResponse(false, null, 'Error fetching unread count');
    }
}

jsonResponse(false, null, 'Invalid action');
