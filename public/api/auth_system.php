<?php
/**
 * Authentication System for QR Code Attendance System
 * Handles secure login, logout, session management, and role-based access
 */

// Set content type to JSON
header('Content-Type: application/json');
// SECURITY FIX: Restrict CORS to specific domains
header('Access-Control-Allow-Origin: http://localhost');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
header('Access-Control-Allow-Credentials: true');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include configuration
require_once 'config.php';

// Start session with secure settings
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
    ini_set('session.use_strict_mode', 1);
    session_start();
}

// Database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

class AuthSystem {
    private $pdo;
    private $session_timeout = 3600; // 1 hour
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Authenticate user with username/email and password
     */
    public function login($username, $password, $remember = false) {
        try {
            // Find user by username or email
            $stmt = $this->pdo->prepare("
                SELECT id, username, email, password_hash, role, student_id, is_active, last_login 
                FROM users 
                WHERE (username = ? OR email = ?) AND is_active = 1
            ");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                return ['success' => false, 'message' => 'Invalid credentials'];
            }
            
            // Verify password
            if (!password_verify($password, $user['password_hash'])) {
                return ['success' => false, 'message' => 'Invalid credentials'];
            }
            
            // Update last login
            $stmt = $this->pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);
            
            // Create session
            $session_id = $this->createSession($user['id']);
            
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['student_id'] = $user['student_id'];
            $_SESSION['session_id'] = $session_id;
            
            // Set remember me cookie if requested
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', isset($_SERVER['HTTPS']), true);
                // Store token in database (implement if needed)
            }
            
            return [
                'success' => true, 
                'message' => 'Login successful',
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
    
    /**
     * Create a new session
     */
    private function createSession($user_id) {
        $session_id = bin2hex(random_bytes(32));
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $stmt = $this->pdo->prepare("
            INSERT INTO sessions (id, user_id, ip_address, user_agent, last_activity) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$session_id, $user_id, $ip_address, $user_agent]);
        
        return $session_id;
    }
    
    /**
     * Check if user is logged in and session is valid
     */
    public function isLoggedIn() {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_id'])) {
            return false;
        }
        
        try {
            // Check session in database
            $stmt = $this->pdo->prepare("
                SELECT s.id, s.last_activity, u.is_active 
                FROM sessions s 
                JOIN users u ON s.user_id = u.id 
                WHERE s.id = ? AND s.user_id = ? AND u.is_active = 1
            ");
            $stmt->execute([$_SESSION['session_id'], $_SESSION['user_id']]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$session) {
                $this->logout();
                return false;
            }
            
            // Check session timeout
            $last_activity = strtotime($session['last_activity']);
            if (time() - $last_activity > $this->session_timeout) {
                $this->logout();
                return false;
            }
            
            // Update last activity
            $stmt = $this->pdo->prepare("UPDATE sessions SET last_activity = NOW() WHERE id = ?");
            $stmt->execute([$_SESSION['session_id']]);
            
            return true;
            
        } catch (Exception $e) {
            $this->logout();
            return false;
        }
    }
    
    /**
     * Check if user has specific role
     */
    public function hasRole($role) {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        return $_SESSION['role'] === $role;
    }
    
    /**
     * Get current user data
     */
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'email' => $_SESSION['email'],
            'role' => $_SESSION['role'],
            'student_id' => $_SESSION['student_id']
        ];
    }
    
    /**
     * Logout user and destroy session
     */
    public function logout() {
        if (isset($_SESSION['session_id'])) {
            try {
                // Remove session from database
                $stmt = $this->pdo->prepare("DELETE FROM sessions WHERE id = ?");
                $stmt->execute([$_SESSION['session_id']]);
            } catch (Exception $e) {
                // Ignore errors during logout
            }
        }
        
        // Clear session data
        $_SESSION = array();
        
        // Destroy session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // Destroy session
        session_destroy();
        
        // Clear remember me cookie
        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
        }
    }
    
    
    /**
     * Change user password
     */
    public function changePassword($user_id, $current_password, $new_password) {
        try {
            // Verify current password
            $stmt = $this->pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user || !password_verify($current_password, $user['password_hash'])) {
                return ['success' => false, 'message' => 'Current password is incorrect'];
            }
            
            // Update password
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $this->pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$new_password_hash, $user_id]);
            
            return ['success' => true, 'message' => 'Password changed successfully'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Password change failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Clean up expired sessions
     */
    public function cleanupExpiredSessions() {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM sessions 
                WHERE last_activity < DATE_SUB(NOW(), INTERVAL ? SECOND)
            ");
            $stmt->execute([$this->session_timeout]);
            
            return $stmt->rowCount();
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Register a new student
     */
    public function registerStudent($username, $email, $password, $student_id, $name, $phone) {
        try {
            // SECURITY FIX: Use secure password hashing
            require_once __DIR__ . '/../includes_ext/password_manager.php';
            
            // Validate input
            if (empty($username) || empty($email) || empty($password) || empty($student_id) || empty($name)) {
                return ['success' => false, 'message' => 'All required fields must be filled'];
            }
            
            // Validate email format
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'Invalid email format'];
            }
            
            // Validate password strength
            $passwordValidation = PasswordManager::validatePassword($password);
            if (!$passwordValidation['valid']) {
                return ['success' => false, 'message' => 'Password does not meet requirements: ' . implode(', ', $passwordValidation['errors'])];
            }
            
            // Check if username or email already exists
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Username or email already exists'];
            }
            
            // Check if student_id already exists
            $stmt = $this->pdo->prepare("SELECT id FROM students WHERE student_id = ?");
            $stmt->execute([$student_id]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Student ID already exists'];
            }
            
            // Hash password
            $hashed_password = PasswordManager::hashPassword($password);
            
            // Start transaction
            $this->pdo->beginTransaction();
            
            try {
                // Insert into users table
                $stmt = $this->pdo->prepare("
                    INSERT INTO users (username, email, password_hash, role, is_active, created_at) 
                    VALUES (?, ?, ?, 'student', 1, NOW())
                ");
                $stmt->execute([$username, $email, $hashed_password]);
                $user_id = $this->pdo->lastInsertId();
                
                // Insert into students table
                $stmt = $this->pdo->prepare("
                    INSERT INTO students (user_id, student_id, name, email, phone, password, is_active, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, 1, NOW())
                ");
                $stmt->execute([$user_id, $student_id, $name, $email, $phone, $hashed_password]);
                
                // Commit transaction
                $this->pdo->commit();
                
                return [
                    'success' => true, 
                    'message' => 'Student registered successfully',
                    'user_id' => $user_id
                ];
                
            } catch (Exception $e) {
                $this->pdo->rollBack();
                throw $e;
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()];
        }
    }
}

// Initialize authentication system
$auth = new AuthSystem($pdo);

// Handle API requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => 'Invalid action'];
    
    switch ($action) {
        case 'login':
            try {
                $username = $_POST['username'] ?? '';
                $password = $_POST['password'] ?? '';
                $remember = isset($_POST['remember']);
                $response = $auth->login($username, $password, $remember);
            } catch (Exception $e) {
                $response = ['success' => false, 'message' => 'Login error: ' . $e->getMessage()];
            }
            break;
            
        case 'register':
            $username = $_POST['username'] ?? '';
            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';
            $student_id = $_POST['student_id'] ?? '';
            $name = $_POST['name'] ?? '';
            $phone = $_POST['phone'] ?? '';
            $response = $auth->registerStudent($username, $email, $password, $student_id, $name, $phone);
            break;
            
        case 'logout':
            $auth->logout();
            $response = ['success' => true, 'message' => 'Logged out successfully'];
            break;
            
        case 'change_password':
            if ($auth->isLoggedIn()) {
                $user_id = $_SESSION['user_id'];
                $current_password = $_POST['current_password'] ?? '';
                $new_password = $_POST['new_password'] ?? '';
                $response = $auth->changePassword($user_id, $current_password, $new_password);
            } else {
                $response = ['success' => false, 'message' => 'Not logged in'];
            }
            break;
    }
    
    echo json_encode($response);
    exit(); // ADD THIS!
} else {
    // Handle GET requests (like check_auth)
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'check_auth':
            if ($auth->isLoggedIn()) {
                $user = $auth->getCurrentUser();
                echo json_encode(['success' => true, 'user' => $user]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Not logged in']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
    exit;
}

// Helper functions for use in other files
function requireLogin() {
    global $auth;
    if (!$auth->isLoggedIn()) {
        header('Location: login.html');
        exit;
    }
}

function requireRole($role) {
    global $auth;
    if (!$auth->hasRole($role)) {
        http_response_code(403);
        die('Access denied');
    }
}

function getCurrentUser() {
    global $auth;
    return $auth->getCurrentUser();
}

function isLoggedIn() {
    global $auth;
    return $auth->isLoggedIn();
}

function hasRole($role) {
    global $auth;
    return $auth->hasRole($role);
}
?>
