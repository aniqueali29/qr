<?php
/**
 * Development Authentication Helper
 * This file provides easy authentication for development/testing
 * DO NOT USE IN PRODUCTION!
 */

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include configuration
require_once 'config.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Development authentication - bypasses normal login
function devLogin($username = 'admin') {
    try {
        // Find user in database
        $stmt = $pdo->prepare("
            SELECT id, username, email, role, student_id, is_active 
            FROM users 
            WHERE username = ? AND is_active = 1
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }
        
        // Create session
        $session_id = bin2hex(random_bytes(32));
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $stmt = $pdo->prepare("
            INSERT INTO sessions (id, user_id, ip_address, user_agent, last_activity) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$session_id, $user['id'], $ip_address, $user_agent]);
        
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['student_id'] = $user['student_id'];
        $_SESSION['session_id'] = $session_id;
        
        return [
            'success' => true,
            'message' => 'Development login successful',
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role'],
                'student_id' => $user['student_id']
            ]
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Login failed: ' . $e->getMessage()];
    }
}

// Handle requests
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'login_admin':
            $result = devLogin('admin');
            echo json_encode($result);
            break;
            
        case 'login_student':
            $result = devLogin('john.doe');
            echo json_encode($result);
            break;
            
        case 'check':
            if (isset($_SESSION['user_id'])) {
                echo json_encode([
                    'success' => true,
                    'user' => [
                        'id' => $_SESSION['user_id'],
                        'username' => $_SESSION['username'],
                        'role' => $_SESSION['role']
                    ]
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Not logged in']);
            }
            break;
            
        case 'logout':
            session_destroy();
            echo json_encode(['success' => true, 'message' => 'Logged out']);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>
