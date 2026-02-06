<?php
require_once __DIR__ . '/../database/db.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_REQUEST['action'] ?? null;

// Read JSON body
$input = json_decode(file_get_contents('php://input'), true);

// ---------------------------
// HELPER FUNCTION
// ---------------------------
function requireCsrf($token) {
    if (!verifyCSRFToken($token)) {
        jsonResponse(false, null, 'Invalid CSRF token');
    }
}

// ---------------------------
// REGISTER
// ---------------------------
if ($action === 'register' && $method === 'POST') {
    try {
        checkRateLimit('register', null, 10);
        $data = $input;

        if (empty($data['email']) || empty($data['password']) || empty($data['first_name']) || empty($data['last_name'])) {
            jsonResponse(false, null, 'All fields are required');
        }

        if (!isValidEmail($data['email'])) {
            jsonResponse(false, null, 'Invalid email address');
        }

        if (strlen($data['password']) < 8) {
            jsonResponse(false, null, 'Password must be at least 8 characters');
        }

        if (dbQueryOne("SELECT id FROM users WHERE email = ?", [$data['email']])) {
            jsonResponse(false, null, 'Email already registered');
        }

        $userId = dbExecute(
            "INSERT INTO users (email, password_hash, first_name, last_name, email_verified) VALUES (?, ?, ?, ?, TRUE)",
            [
                sanitizeInput($data['email']),
                hashPassword($data['password']),
                sanitizeInput($data['first_name']),
                sanitizeInput($data['last_name'])
            ]
        );

        dbExecute("INSERT INTO user_preferences (user_id) VALUES (?)", [$userId]);

        // SESSION
        $_SESSION['user_id'] = $userId;
        $_SESSION['email'] = $data['email'];
        $_SESSION['first_name'] = $data['first_name'];
        $_SESSION['last_name'] = $data['last_name'];
        $_SESSION['is_system_owner'] = false;

        dbExecute(
            "INSERT INTO sessions (id, user_id, ip_address, user_agent) VALUES (?, ?, ?, ?)",
            [session_id(), $userId, getUserIP(), $_SERVER['HTTP_USER_AGENT'] ?? '']
        );

        dbExecute("UPDATE users SET last_login = NOW() WHERE id = ?", [$userId]);

        createNotification($userId, 'Welcome to Club Hub!', 'Your account has been created successfully.', 'success');
        logActivity($userId, 'register', ['email' => $data['email']]);

        jsonResponse(true, [
            'user_id' => $userId,
            'email' => $data['email'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'is_system_owner' => false
        ], 'Registration successful');

    } catch (Exception $e) {
        error_log('Registration error: ' . $e->getMessage());
        jsonResponse(false, null, 'Registration failed');
    }
}

// ---------------------------
// LOGIN
// ---------------------------
if ($action === 'login' && $method === 'POST') {
    try {
        checkRateLimit('login', null, MAX_LOGIN_ATTEMPTS);
        $data = $input;

        if (empty($data['email']) || empty($data['password'])) {
            jsonResponse(false, null, 'Email and password are required');
        }

        $user = dbQueryOne("SELECT id, email, password_hash, first_name, last_name, is_system_owner, is_active FROM users WHERE email = ?", [$data['email']]);

        if (!$user || !verifyPassword($data['password'], $user['password_hash'])) {
            jsonResponse(false, null, 'Invalid email or password');
        }

        if (!$user['is_active']) {
            jsonResponse(false, null, 'Account is deactivated');
        }

        // SESSION
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name'] = $user['last_name'];
        $_SESSION['is_system_owner'] = (bool)$user['is_system_owner'];

        dbExecute(
            "REPLACE INTO sessions (id, user_id, ip_address, user_agent) VALUES (?, ?, ?, ?)",
            [session_id(), $user['id'], getUserIP(), $_SERVER['HTTP_USER_AGENT'] ?? '']
        );

        dbExecute("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);
        logActivity($user['id'], 'login', ['ip' => getUserIP()]);

        jsonResponse(true, [
            'user_id' => $user['id'],
            'email' => $user['email'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'is_system_owner' => (bool)$user['is_system_owner']
        ], 'Login successful');

    } catch (Exception $e) {
        error_log('Login error: ' . $e->getMessage());
        jsonResponse(false, null, 'Login failed');
    }
}

// ---------------------------
// LOGOUT
// ---------------------------
if ($action === 'logout' && $method === 'POST') {
    try {
        if (isLoggedIn()) {
            $userId = getCurrentUserId();
            dbExecute("DELETE FROM sessions WHERE id = ?", [session_id()]);
            logActivity($userId, 'logout');
        }
        session_destroy();
        jsonResponse(true, null, 'Logged out successfully');

    } catch (Exception $e) {
        error_log('Logout error: ' . $e->getMessage());
        jsonResponse(false, null, 'Logout failed');
    }
}

// ---------------------------
// CHECK SESSION
// ---------------------------
if ($action === 'check' && $method === 'GET') {
    if (isLoggedIn()) {
        $user = getCurrentUser();
        if ($user) {
            dbExecute("UPDATE sessions SET last_activity = NOW() WHERE id = ?", [session_id()]);
            jsonResponse(true, [
                'user_id' => $user['id'],
                'email' => $user['email'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'is_system_owner' => $_SESSION['is_system_owner'] ?? false
            ]);
        }
    }
    jsonResponse(false, null, 'Not logged in');
}

// ---------------------------
// GET MY CLUBS (MISSING ACTION - NOW FIXED)
// ---------------------------
if ($action === 'my-clubs' && $method === 'GET') {
    if (!isLoggedIn()) {
        jsonResponse(false, null, 'Login required');
    }
    
    try {
        $sql = "SELECT 
                    c.id,
                    c.name,
                    c.description,
                    c.access_code,
                    cm.is_president,
                    cm.status,
                    cr.role_name,
                    cr.id as role_id
                FROM club_members cm
                JOIN clubs c ON c.id = cm.club_id
                JOIN club_roles cr ON cr.id = cm.role_id
                WHERE cm.user_id = ? AND cm.status = 'active' AND c.is_active = TRUE
                ORDER BY c.name ASC";
        
        $clubs = dbQuery($sql, [getCurrentUserId()]);
        jsonResponse(true, $clubs);
        
    } catch (Exception $e) {
        error_log('Get my clubs error: ' . $e->getMessage());
        jsonResponse(false, null, 'Error fetching clubs');
    }
}

// ---------------------------
// INVALID ACTION
// ---------------------------
jsonResponse(false, null, 'Invalid action');
