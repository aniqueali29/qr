<?php
/**
 * Admin Authentication API
 * Handles admin login, logout, and session management
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

// Set JSON header
header('Content-Type: application/json');

// Handle CORS if needed
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');
}

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    }
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    }
    exit(0);
}

// Only allow POST requests for authentication
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Get the action from POST data
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'login':
            // Log login attempt
            error_log("🔐 Admin login attempt - Username: " . ($_POST['username'] ?? 'empty') . " - IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            
            // Validate CSRF token
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                error_log("❌ CSRF token validation failed");
                echo json_encode(['success' => false, 'message' => 'Invalid security token. Please try again.']);
                exit();
            }
            
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            $remember = isset($_POST['remember']);
            
            error_log("📝 Login data - Username: $username - Password length: " . strlen($password) . " - Remember: " . ($remember ? 'yes' : 'no'));
            
            if (empty($username) || empty($password)) {
                error_log("❌ Empty username or password");
                echo json_encode(['success' => false, 'message' => 'Username and password are required']);
                exit();
            }
            
            error_log("🚀 Calling adminAuth->login()");
            $result = $adminAuth->login($username, $password, $remember);
            error_log("📊 Login result: " . json_encode($result));
            echo json_encode($result);
            break;
            
        case 'logout':
            $result = $adminAuth->logout();
            echo json_encode(['success' => $result, 'message' => 'Logged out successfully']);
            break;
            
        case 'check':
            echo json_encode(['authenticated' => $adminAuth->isLoggedIn()]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error', 'message' => $e->getMessage()]);
}
?>
