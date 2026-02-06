<?php
/**
 * db.php - Database Connection & Utility Functions
 * Club Hub Management System - Production Ready with All Security Functions
 */

session_start();

// ---------------------------
// DATABASE CONFIGURATION
// ---------------------------
define('DB_HOST', 'localhost');
define('DB_NAME', 'dbztof0dny7rla');
define('DB_USER', 'upxmhqodbwb3x');
define('DB_PASS', '@b34c$b_b{R3');
define('DB_CHARSET', 'utf8mb4');

// SECURITY SETTINGS
define('SESSION_LIFETIME', 86400); // 24 hours
define('MAX_LOGIN_ATTEMPTS', 5);
define('RATE_LIMIT_WINDOW', 3600); // 1 hour

// Global PDO instance
global $pdo;
$pdo = null;

// ---------------------------
// DATABASE CONNECTION
// ---------------------------
function getDBConnection() {
    global $pdo;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            http_response_code(500);
            die(json_encode(['success' => false, 'message' => 'Database connection failed']));
        }
    }
    return $pdo;
}

// Initialize connection
$pdo = getDBConnection();

// ---------------------------
// QUERY FUNCTIONS
// ---------------------------
function dbQuery($sql, $params = []) {
    try {
        $stmt = getDBConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Query error: " . $e->getMessage() . " | SQL: " . $sql);
        throw $e;
    }
}

function dbQueryOne($sql, $params = []) {
    try {
        $stmt = getDBConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Query error: " . $e->getMessage() . " | SQL: " . $sql);
        throw $e;
    }
}

function dbExecute($sql, $params = []) {
    try {
        $stmt = getDBConnection()->prepare($sql);
        $stmt->execute($params);
        
        // Return last insert ID for INSERT statements, otherwise return affected rows
        if (stripos(trim($sql), 'INSERT') === 0) {
            return getDBConnection()->lastInsertId();
        }
        return $stmt->rowCount();
    } catch (PDOException $e) {
        error_log("Execute error: " . $e->getMessage() . " | SQL: " . $sql);
        throw $e;
    }
}

// ---------------------------
// TRANSACTION FUNCTIONS
// ---------------------------
function dbBeginTransaction() {
    return getDBConnection()->beginTransaction();
}

function dbCommit() {
    return getDBConnection()->commit();
}

function dbRollback() {
    return getDBConnection()->rollBack();
}

// ---------------------------
// SECURITY FUNCTIONS
// ---------------------------
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function getUserIP() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    
    // Check for proxy headers
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    }
    
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

function generateAccessCode($clubName) {
    // Generate a unique 6-character access code
    $prefix = strtoupper(substr(preg_replace('/[^A-Z]/', '', strtoupper($clubName)), 0, 3));
    if (strlen($prefix) < 3) {
        $prefix = str_pad($prefix, 3, 'X');
    }
    
    $code = $prefix . rand(100, 999);
    
    // Ensure uniqueness
    $existing = dbQueryOne("SELECT id FROM clubs WHERE access_code = ?", [$code]);
    if ($existing) {
        return generateAccessCode($clubName . rand(1, 999));
    }
    
    return $code;
}

// ---------------------------
// CSRF FUNCTIONS
// ---------------------------
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// ---------------------------
// RATE LIMITING
// ---------------------------
function checkRateLimit($action, $userId = null, $maxAttempts = 10) {
    $identifier = $userId ?? getUserIP();
    
    try {
        // Clean old entries
        dbExecute(
            "DELETE FROM chat_rate_limits WHERE action_type = ? AND window_start < DATE_SUB(NOW(), INTERVAL ? SECOND)",
            [$action, RATE_LIMIT_WINDOW]
        );
        
        // Check current attempts
        $sql = "SELECT action_count FROM chat_rate_limits WHERE action_type = ? AND " .
               ($userId ? "user_id = ?" : "ip_address = ?") . " AND window_start >= DATE_SUB(NOW(), INTERVAL ? SECOND)";
        $params = [$action, $identifier, RATE_LIMIT_WINDOW];
        
        $record = dbQueryOne($sql, $params);
        
        if ($record && $record['action_count'] >= $maxAttempts) {
            jsonResponse(false, null, 'Rate limit exceeded. Please try again later.');
        }
        
        // Update or insert rate limit record
        if ($record) {
            $sql = "UPDATE chat_rate_limits SET action_count = action_count + 1, last_action = NOW() WHERE action_type = ? AND " .
                   ($userId ? "user_id = ?" : "ip_address = ?");
            dbExecute($sql, [$action, $identifier]);
        } else {
            $sql = "INSERT INTO chat_rate_limits (action_type, " . ($userId ? "user_id" : "ip_address") . ", action_count) VALUES (?, ?, 1)";
            dbExecute($sql, [$action, $identifier]);
        }
        
    } catch (Exception $e) {
        error_log("Rate limit error: " . $e->getMessage());
        // Don't block on rate limit errors
    }
}

// ---------------------------
// SESSION & LOGIN FUNCTIONS
// ---------------------------
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin($redirect = 'login.php') {
    if (!isLoggedIn()) {
        header("Location: $redirect");
        exit;
    }
}

function getCurrentUser() {
    if (!isLoggedIn()) return false;
    try {
        $sql = "SELECT * FROM users WHERE id = ? AND is_active = 1";
        return dbQueryOne($sql, [$_SESSION['user_id']]);
    } catch (Exception $e) {
        error_log("Get current user error: " . $e->getMessage());
        return false;
    }
}

function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

// ---------------------------
// NOTIFICATION FUNCTIONS
// ---------------------------
function createNotification($userId, $title, $message, $type = 'info', $link = null) {
    try {
        $sql = "INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, ?, ?)";
        return dbExecute($sql, [$userId, $title, $message, $type, $link]);
    } catch (Exception $e) {
        error_log("Create notification error: " . $e->getMessage());
        return false;
    }
}

// ---------------------------
// ACTIVITY LOGGING
// ---------------------------
function logActivity($userId, $action, $metadata = []) {
    try {
        error_log("User Activity - User ID: $userId, Action: $action, Data: " . json_encode($metadata));
    } catch (Exception $e) {
        error_log("Log activity error: " . $e->getMessage());
    }
}

// ---------------------------
// JSON RESPONSE HELPER
// ---------------------------
function jsonResponse($success, $data = null, $message = '') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message,
        'csrf_token' => generateCSRFToken()
    ]);
    exit;
}

// ---------------------------
// CLEANUP OLD SESSIONS
// ---------------------------
function cleanOldSessions() {
    try {
        dbExecute("DELETE FROM sessions WHERE last_activity < DATE_SUB(NOW(), INTERVAL ? SECOND)", [SESSION_LIFETIME]);
    } catch (Exception $e) {
        error_log("Clean sessions error: " . $e->getMessage());
    }
}

// Regenerate session periodically
if (!isset($_SESSION['created'])) {
    $_SESSION['created'] = time();
} elseif (time() - $_SESSION['created'] > 1800) {
    session_regenerate_id(true);
    $_SESSION['created'] = time();
    if (rand(1, 100) === 1) cleanOldSessions();
}