<?php
require_once '../database/db.php';
header('Content-Type: application/json');

$club_id = $_GET['club_id'] ?? null;
if (!$club_id) jsonResponse(false, null, 'Club ID required');

try {
    $sql = "SELECT COUNT(DISTINCT DATE(sign_in_time)) as days 
            FROM club_attendance WHERE club_id = ? 
            AND sign_in_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $days = dbQueryOne($sql, [$club_id]);
    
    $sql = "SELECT COUNT(*) as total FROM club_attendance 
            WHERE club_id = ? AND sign_in_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $total = dbQueryOne($sql, [$club_id]);
    
    $avgAttendance = $days['days'] > 0 ? round(($total['total'] / $days['days'])) : 0;
    
    $sql = "SELECT COUNT(*) as count FROM club_attendance 
            WHERE club_id = ? AND DATE(sign_in_time) = CURDATE()";
    $today = dbQueryOne($sql, [$club_id]);
    
    jsonResponse(true, [
        'average_attendance' => $avgAttendance,
        'present_today' => $today['count'],
        'meetings_this_month' => $days['days']
    ]);
} catch (Exception $e) {
    error_log('Attendance API Error: ' . $e->getMessage());
    jsonResponse(false, null, 'Error fetching attendance');
}
