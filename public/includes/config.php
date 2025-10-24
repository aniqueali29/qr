<?php
/**
 * Student Portal Configuration
 * Database connection and student-specific constants
 */

// Prevent direct access
if (!defined('STUDENT_ACCESS')) {
    define('STUDENT_ACCESS', true);
}

// Load env from public/config.env
require_once __DIR__ . '/../includes/env.php';
// Shim array to replace prior $config usage
$config = [
    'DB_HOST' => env_get('DB_HOST', 'localhost'),
    'DB_NAME' => env_get('DB_NAME', 'qr_attendance'),
    'DB_USER' => env_get('DB_USER', 'root'),
    'DB_PASS' => env_get('DB_PASS', ''),
    'DEBUG_MODE' => env_bool('DEBUG_MODE', false),
    'SMTP_HOST' => env_get('SMTP_HOST', ''),
    'SMTP_PORT' => (int)env_get('SMTP_PORT', 587),
    'SMTP_USERNAME' => env_get('SMTP_USERNAME', ''),
    'SMTP_PASSWORD' => env_get('SMTP_PASSWORD', ''),
];

// Database Configuration (same as admin system)
define('DB_HOST', $config['DB_HOST']);
define('DB_NAME', $config['DB_NAME']);
define('DB_USER', $config['DB_USER']);
define('DB_PASS', $config['DB_PASS']);
define('DB_CHARSET', 'utf8');

// Student Portal Configuration
define('STUDENT_SITE_NAME', 'QR Attendance Student Portal');
define('STUDENT_SITE_URL', env_get('APP_URL', 'http://localhost/qr') . '/public');
define('STUDENT_SESSION_TIMEOUT', 7200); // 2 hours
define('STUDENT_MAX_LOGIN_ATTEMPTS', 5);
define('STUDENT_LOGIN_LOCKOUT_TIME', 900); // 15 minutes

// Asset Paths (relative to /public/)
define('STUDENT_ASSETS_URL', STUDENT_SITE_URL . '/assets');
define('STUDENT_CSS_URL', STUDENT_ASSETS_URL . '/css');
define('STUDENT_JS_URL', STUDENT_ASSETS_URL . '/js');
define('STUDENT_IMG_URL', STUDENT_ASSETS_URL . '/img');
define('STUDENT_VENDOR_URL', STUDENT_ASSETS_URL . '/vendor');

// API Paths
define('STUDENT_API_URL', STUDENT_SITE_URL . '/api');
define('ADMIN_API_URL', 'http://localhost/qr/public/admin/api');

// Security Configuration
define('STUDENT_CSRF_TOKEN_NAME', 'student_csrf_token');
define('STUDENT_SESSION_NAME', 'student_session');

// Error Reporting - SECURITY FIX: Use secure config
if (!defined('DEBUG_MODE')) {
    define('DEBUG_MODE', $config['DEBUG_MODE']);
}

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Timezone
date_default_timezone_set('Asia/Karachi');

// Session Configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
ini_set('session.use_strict_mode', 1);
ini_set('session.gc_maxlifetime', STUDENT_SESSION_TIMEOUT);
ini_set('session.name', STUDENT_SESSION_NAME);
ini_set('session.cookie_path', '/qr/');
ini_set('session.cookie_domain', '');
ini_set('session.cookie_samesite', 'Lax');

// Database Connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed. Please contact administrator.");
}

// Helper Functions
function getStudentAssetUrl($path) {
    return STUDENT_ASSETS_URL . '/' . ltrim($path, '/');
}

function getStudentApiUrl($endpoint) {
    return STUDENT_API_URL . '/' . ltrim($endpoint, '/');
}

function getAdminApiUrl($endpoint) {
    return ADMIN_API_URL . '/' . ltrim($endpoint, '/');
}

function getStudentBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['SCRIPT_NAME']);
    return $protocol . '://' . $host . $path;
}

function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function sanitizeOutput($data) {
    if (is_array($data)) {
        return array_map('sanitizeOutput', $data);
    }
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

function logStudentAction($action, $details = '') {
    if (!defined('LOG_STUDENT_ACTIONS') || !LOG_STUDENT_ACTIONS) return;
    
    $logFile = 'logs/student_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $user = $_SESSION['student_username'] ?? 'unknown';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $logEntry = "[$timestamp] [$user] [$ip] $action: $details" . PHP_EOL;
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// Initialize session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CSRF Protection
function generateCSRFToken() {
    if (!isset($_SESSION['student_csrf_token'])) {
        $_SESSION['student_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['student_csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['student_csrf_token']) && hash_equals($_SESSION['student_csrf_token'], $token);
}

// Rate Limiting for login attempts
function checkLoginRateLimit($ip) {
    $key = "student_login_attempts_$ip";
    $current_time = time();
    $window_start = $current_time - STUDENT_LOGIN_LOCKOUT_TIME;
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [];
    }
    
    // Clean old attempts
    $_SESSION[$key] = array_filter($_SESSION[$key], function($timestamp) use ($window_start) {
        return $timestamp > $window_start;
    });
    
    // Check if limit exceeded
    if (count($_SESSION[$key]) >= STUDENT_MAX_LOGIN_ATTEMPTS) {
        return false;
    }
    
    // Add current attempt
    $_SESSION[$key][] = $current_time;
    return true;
}

// Get client IP address
function getClientIP() {
    $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

// Profile Picture Configuration
define('PROFILE_PICTURE_PATH', __DIR__ . '/../uploads/profile_pictures/');
define('PROFILE_PICTURE_URL', STUDENT_SITE_URL . '/uploads/profile_pictures/');
define('MAX_PROFILE_PICTURE_SIZE', 2 * 1024 * 1024); // 2MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif']);
define('VERIFICATION_CODE_EXPIRY', 900); // 15 minutes in seconds

// Email Configuration - SECURITY FIX: Use secure config
define('SMTP_HOST', $config['SMTP_HOST']);
define('SMTP_PORT', $config['SMTP_PORT']);
define('SMTP_USERNAME', $config['SMTP_USERNAME']);
define('SMTP_PASSWORD', $config['SMTP_PASSWORD']);
define('SMTP_FROM_EMAIL', $config['SMTP_USERNAME']);
define('SMTP_FROM_NAME', 'QR Attendance System');
define('EMAIL_DEBUG_MODE', false); // Set to false for production email sending

// Set CSRF token
generateCSRFToken();
?>