<?php
/**
 * Admin Dashboard Configuration
 * Database connection and admin-specific constants
 */

// Prevent direct access
if (!defined('ADMIN_ACCESS')) {
    define('ADMIN_ACCESS', true);
}

// Load env configuration (public/config.env)
require_once __DIR__ . '/../../includes/env.php';
$config = [
    'DB_HOST' => env_get('DB_HOST', 'localhost'),
    'DB_NAME' => env_get('DB_NAME', 'qr_attendance'),
    'DB_USER' => env_get('DB_USER', 'root'),
    'DB_PASS' => env_get('DB_PASS', ''),
    'DEBUG_MODE' => env_bool('DEBUG_MODE', false),
];

// Database Configuration (same as main system)
define('DB_HOST', $config['DB_HOST']);
define('DB_NAME', $config['DB_NAME']);
define('DB_USER', $config['DB_USER']);
define('DB_PASS', $config['DB_PASS']);
define('DB_CHARSET', 'utf8');

// Admin-specific Configuration
define('ADMIN_SITE_NAME', 'QR Attendance Admin');
define('ADMIN_SITE_URL', env_get('APP_URL', 'http://localhost/qr') . '/public/admin');
define('ADMIN_SESSION_TIMEOUT', 3600); // 1 hour
define('ADMIN_MAX_LOGIN_ATTEMPTS', 5);
define('ADMIN_LOGIN_LOCKOUT_TIME', 900); // 15 minutes

// Asset Paths (relative to /public/admin/)
define('ADMIN_ASSETS_URL', ADMIN_SITE_URL . '/assets');
define('ADMIN_CSS_URL', ADMIN_ASSETS_URL . '/css');
define('ADMIN_JS_URL', ADMIN_ASSETS_URL . '/js');
define('ADMIN_IMG_URL', ADMIN_ASSETS_URL . '/img');
define('ADMIN_VENDOR_URL', ADMIN_ASSETS_URL . '/vendor');

// API Paths
define('ADMIN_API_URL', ADMIN_SITE_URL . '/api');
define('MAIN_API_URL', env_get('APP_URL', 'http://localhost/qr') . '/public/api');

// Security Configuration
define('ADMIN_CSRF_TOKEN_NAME', 'admin_csrf_token');
define('ADMIN_SESSION_NAME', env_get('SESSION_NAME', 'QR_ATTENDANCE_SESSION'));

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

// Session Configuration (only if unified system is not being used)
if (!defined('UNIFIED_SESSION_ACCESS')) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
    ini_set('session.use_strict_mode', 1);
    ini_set('session.gc_maxlifetime', ADMIN_SESSION_TIMEOUT);
    ini_set('session.name', ADMIN_SESSION_NAME);
    ini_set('session.cookie_path', '/qr/');
    ini_set('session.cookie_domain', '');
    ini_set('session.cookie_samesite', 'Lax');
}

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
    if (DEBUG_MODE) {
        die("Database connection failed: " . $e->getMessage());
    } else {
        die("Database connection failed. Please contact administrator.");
    }
}

// Helper Functions
function getAdminAssetUrl($path) {
    return ADMIN_ASSETS_URL . '/' . ltrim($path, '/');
}

function getAdminApiUrl($endpoint) {
    return ADMIN_API_URL . '/' . ltrim($endpoint, '/');
}

function getMainApiUrl($endpoint) {
    return MAIN_API_URL . '/' . ltrim($endpoint, '/');
}

function getAdminBaseUrl() {
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

function logAdminAction($action, $details = '') {
    if (!defined('LOG_ADMIN_ACTIONS') || !LOG_ADMIN_ACTIONS) return;
    
    $logFile = '../logs/admin_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $user = $_SESSION['admin_username'] ?? 'unknown';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $logEntry = "[$timestamp] [$user] [$ip] $action: $details" . PHP_EOL;
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// Initialize session only if not already started by unified config
if (session_status() === PHP_SESSION_NONE && !defined('SESSION_ALREADY_STARTED')) {
    session_start();
}

// CSRF Protection
function generateCSRFToken() {
    if (!isset($_SESSION['admin_csrf_token'])) {
        $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['admin_csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['admin_csrf_token']) && hash_equals($_SESSION['admin_csrf_token'], $token);
}

// Get session by code
function get_session_by_code($code) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM enrollment_sessions WHERE code = ?");
        $stmt->execute([$code]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting session by code: " . $e->getMessage());
        return null;
    }
}

// Rate Limiting for login attempts
function checkLoginRateLimit($ip) {
    $key = "login_attempts_$ip";
    $current_time = time();
    $window_start = $current_time - ADMIN_LOGIN_LOCKOUT_TIME;
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [];
    }
    
    // Clean old attempts
    $_SESSION[$key] = array_filter($_SESSION[$key], function($timestamp) use ($window_start) {
        return $timestamp > $window_start;
    });
    
    // Check if limit exceeded
    if (count($_SESSION[$key]) >= ADMIN_MAX_LOGIN_ATTEMPTS) {
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

// Set CSRF token only if unified system is not being used
if (!defined('UNIFIED_SESSION_ACCESS')) {
    generateCSRFToken();
}
?>
