<?php
session_start();
header('Content-Type: application/json');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get raw JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Check CSRF token
if (!isset($_SERVER['HTTP_CSRF_TOKEN']) || $_SERVER['HTTP_CSRF_TOKEN'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

// Handle logout action
if (isset($input['action']) && $input['action'] === 'logout') {
    // Destroy session safely
    if (isset($_SESSION['user_id'])) {
        session_unset();
        session_destroy();

        // Clear session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
    }

    echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
    exit;
}

// Default response
echo json_encode(['success' => false, 'message' => 'Invalid action']);
exit;
