<?php
require_once '../database/db.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    jsonResponse(false, null, 'Login required');
}

$club_id = $_REQUEST['club_id'] ?? null;

if (!$club_id) {
    jsonResponse(false, null, 'Club ID required');
}

try {
    $totalMembers = dbQueryOne(
        "SELECT COUNT(*) as count FROM club_members WHERE club_id = ? AND status = 'active'",
        [$club_id]
    );
    
    $upcomingEvents = dbQueryOne(
        "SELECT COUNT(*) as count FROM events WHERE club_id = ? AND event_date >= NOW() AND is_cancelled = 0",
        [$club_id]
    );
    
    $avgAttendance = dbQueryOne(
        "SELECT COUNT(DISTINCT DATE(sign_in_time)) as days 
         FROM club_attendance WHERE club_id = ? 
         AND sign_in_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
        [$club_id]
    );
    
    $totalAttendance = dbQueryOne(
        "SELECT COUNT(*) as total FROM club_attendance 
         WHERE club_id = ? AND sign_in_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
        [$club_id]
    );
    
    $attendanceRate = 0;
    if ($avgAttendance['days'] > 0 && $totalMembers['count'] > 0) {
        $avgPerDay = $totalAttendance['total'] / $avgAttendance['days'];
        $attendanceRate = round(($avgPerDay / $totalMembers['count']) * 100);
    }
    
    $messagesQuery = dbQueryOne(
        "SELECT COUNT(*) as count FROM chat_messages cm
         JOIN chat_rooms cr ON cm.room_id = cr.id
         WHERE cr.club_id = ? AND DATE(cm.created_at) = CURDATE()",
        [$club_id]
    );
    
    jsonResponse(true, [
        'total_members' => $totalMembers['count'],
        'upcoming_events' => $upcomingEvents['count'],
        'attendance_rate' => $attendanceRate,
        'messages_today' => $messagesQuery['count'] ?? 0
    ]);
    
} catch (Exception $e) {
    error_log('Stats API Error: ' . $e->getMessage());
    jsonResponse(false, null, 'Error fetching statistics');
}
