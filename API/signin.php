<?php
require_once '../database/db.php';
header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? 'lookup';
$club_id = $_REQUEST['club_id'] ?? null;

if (!$club_id) jsonResponse(false, null, 'Club ID required');

try {
    if ($action === 'lookup') {
        $student_id = $_GET['student_id'] ?? '';
        
        $sql = "SELECT id FROM club_attendance 
                WHERE club_id = ? AND student_id = ? AND DATE(sign_in_time) = CURDATE()";
        $exists = dbQueryOne($sql, [$club_id, $student_id]);
        
        if ($exists) {
            jsonResponse(true, ['already_signed_in' => true, 'found' => false]);
        }
        
        $sql = "SELECT DISTINCT first_name, last_name, grade_level, student_id 
                FROM club_attendance 
                WHERE club_id = ? AND student_id = ? 
                ORDER BY sign_in_time DESC LIMIT 1";
        $student = dbQueryOne($sql, [$club_id, $student_id]);
        
        jsonResponse(true, [
            'found' => !!$student,
            'student' => $student,
            'already_signed_in' => false
        ]);
    }
    
    if ($action === 'signin') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $sql = "INSERT INTO club_attendance (club_id, student_id, first_name, last_name, grade_level) 
                VALUES (?, ?, ?, ?, ?)";
        $id = dbExecute($sql, [
            $club_id,
            $data['student_id'],
            sanitizeInput($data['first_name']),
            sanitizeInput($data['last_name']),
            $data['grade_level'] ?? null
        ]);
        
        jsonResponse(!!$id, ['id' => $id], $id ? 'Signed in successfully' : 'Failed');
    }
    
    if ($action === 'today') {
        $sql = "SELECT COUNT(*) as count FROM club_attendance 
                WHERE club_id = ? AND DATE(sign_in_time) = CURDATE()";
        $count = dbQueryOne($sql, [$club_id]);
        
        $sql = "SELECT * FROM club_attendance 
                WHERE club_id = ? AND DATE(sign_in_time) = CURDATE()
                ORDER BY sign_in_time DESC LIMIT 10";
        $recent = dbQuery($sql, [$club_id]);
        
        jsonResponse(true, ['count' => $count['count'], 'recent' => $recent]);
    }
} catch (Exception $e) {
    error_log('Sign-in API Error: ' . $e->getMessage());
    jsonResponse(false, null, 'Error processing request');
}
