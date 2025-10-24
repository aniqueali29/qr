<?php
/**
 * Account Manager API
 * Simple interface for creating and managing admin and student accounts
 */

header('Content-Type: application/json');
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

// Simple authentication check (you can enhance this)
function isAuthorized() {
    // For simplicity, we'll allow access from localhost
    // In production, add proper authentication
    $allowed_ips = ['127.0.0.1', '::1', 'localhost'];
    $client_ip = getClientIP();
    
    return in_array($client_ip, $allowed_ips) || 
           strpos($client_ip, '192.168.') === 0 || 
           strpos($client_ip, '10.') === 0;
}

// Validate input data
function validateAccountData($data) {
    $errors = [];
    
    // Required fields
    if (empty($data['username'])) {
        $errors[] = 'Username is required';
    }
    
    if (empty($data['email'])) {
        $errors[] = 'Email is required';
    } elseif (!isValidEmail($data['email'])) {
        $errors[] = 'Invalid email format';
    }
    
    if (empty($data['password'])) {
        $errors[] = 'Password is required';
    } elseif (strlen($data['password']) < 8) {
        $errors[] = 'Password must be at least 8 characters long';
    }
    
    if (empty($data['accountType'])) {
        $errors[] = 'Account type is required';
    } elseif (!in_array($data['accountType'], ['admin', 'student'])) {
        $errors[] = 'Invalid account type';
    }
    
    // Student-specific validation
    if ($data['accountType'] === 'student') {
        if (empty($data['studentId'])) {
            $errors[] = 'Student ID is required for student accounts';
        } elseif (!isValidStudentId($data['studentId'])) {
            $errors[] = 'Invalid student ID format';
        }
        
        if (empty($data['studentName'])) {
            $errors[] = 'Student name is required for student accounts';
        }
    }
    
    return $errors;
}

// Create new account
function createAccount($data) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Check if username or email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$data['username'], $data['email']]);
        if ($stmt->fetch()) {
            throw new Exception('Username or email already exists');
        }
        
        // Hash password
        $password_hash = password_hash($data['password'], PASSWORD_DEFAULT);
        
        // Insert user account
        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password_hash, role, student_id, is_active) 
            VALUES (?, ?, ?, ?, ?, 1)
        ");
        
        $student_id = ($data['accountType'] === 'student') ? $data['studentId'] : null;
        $stmt->execute([
            $data['username'],
            $data['email'],
            $password_hash,
            $data['accountType'],
            $student_id
        ]);
        
        $user_id = $pdo->lastInsertId();
        
        // If it's a student account, also create student record
        if ($data['accountType'] === 'student') {
            $stmt = $pdo->prepare("
                INSERT INTO students (student_id, name, email, user_id, is_active) 
                VALUES (?, ?, ?, ?, 1)
            ");
            $stmt->execute([
                $data['studentId'],
                $data['studentName'],
                $data['email'],
                $user_id
            ]);
        }
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => ucfirst($data['accountType']) . ' account created successfully!',
            'user_id' => $user_id
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return [
            'success' => false,
            'message' => 'Error creating account: ' . $e->getMessage()
        ];
    }
}

// Get all accounts
function getAccounts() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT u.id, u.username, u.email, u.role, u.student_id, u.is_active, u.created_at,
                   s.name as student_name
            FROM users u
            LEFT JOIN students s ON u.id = s.user_id
            ORDER BY u.created_at DESC
        ");
        $stmt->execute();
        $accounts = $stmt->fetchAll();
        
        return [
            'success' => true,
            'accounts' => $accounts
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error fetching accounts: ' . $e->getMessage()
        ];
    }
}

// Main request handler
try {
    // Check authorization
    if (!isAuthorized()) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Access denied'
        ]);
        exit();
    }
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'POST') {
        // Create new account
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            throw new Exception('Invalid JSON data');
        }
        
        // Validate input
        $errors = validateAccountData($input);
        if (!empty($errors)) {
            echo json_encode([
                'success' => false,
                'message' => implode(', ', $errors)
            ]);
            exit();
        }
        
        // Create account
        $result = createAccount($input);
        echo json_encode($result);
        
    } elseif ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_accounts') {
        // Get all accounts
        $result = getAccounts();
        echo json_encode($result);
        
    } else {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
