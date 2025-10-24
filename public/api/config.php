<?php
/**
 * Configuration file for QR Code Attendance System
 * Contains all system settings and database configuration
 */

require_once __DIR__ . '/../includes/env.php';

// Database Configuration (env-driven)
define('DB_HOST', env_get('DB_HOST', 'localhost'));
define('DB_NAME', env_get('DB_NAME', 'qr_attendance'));
define('DB_USER', env_get('DB_USER', 'root'));
define('DB_PASS', env_get('DB_PASS', ''));
define('DB_CHARSET', env_get('DB_CHARSET', 'utf8'));

// System Configuration
define('SITE_NAME', 'QR Code Attendance System');
define('SITE_URL', env_get('APP_URL', 'http://localhost/qr'));
define('ADMIN_EMAIL', env_get('ADMIN_EMAIL', 'admin@example.com'));

// Security Configuration
define('SESSION_TIMEOUT', env_int('SESSION_TIMEOUT', 3600)); // 1 hour in seconds
define('MAX_LOGIN_ATTEMPTS', env_int('MAX_LOGIN_ATTEMPTS', 5));
define('LOGIN_LOCKOUT_TIME', env_int('LOGIN_LOCKOUT_TIME', 900)); // 15 minutes
define('PASSWORD_MIN_LENGTH', env_int('PASSWORD_MIN_LENGTH', 8));

// QR Code Configuration
define('QR_CODE_SIZE', 200);
define('QR_CODE_MARGIN', 10);
define('QR_CODE_PATH', 'assets/img/qr_codes/');
define('QR_CODE_URL', SITE_URL . '/' . QR_CODE_PATH);

// Sync Configuration
define('SYNC_INTERVAL', 30); // seconds
define('SYNC_TIMEOUT', 30); // seconds
define('MAX_SYNC_RECORDS', 1000);

// File Upload Configuration
define('MAX_FILE_SIZE', env_int('MAX_FILE_SIZE', 5 * 1024 * 1024)); // 5MB
define('ALLOWED_EXTENSIONS', ['csv', 'json', 'xlsx']);

// Email Configuration (for notifications)
define('SMTP_HOST', env_get('SMTP_HOST', 'smtp.gmail.com'));
define('SMTP_PORT', env_int('SMTP_PORT', 587));
define('SMTP_USERNAME', env_get('SMTP_USERNAME', ''));
define('SMTP_PASSWORD', env_get('SMTP_PASSWORD', ''));
define('SMTP_FROM_EMAIL', env_get('SMTP_FROM_EMAIL', 'noreply@example.com'));
define('SMTP_FROM_NAME', env_get('SMTP_FROM_NAME', 'QR Attendance System'));

// API Configuration
define('API_KEY', env_get('API_KEY', 'changeme_api_key'));
define('API_RATE_LIMIT', 100); // requests per hour
define('API_TIMEOUT', 30); // seconds

// Shift Timing Configuration - Now loaded dynamically from database
// All timing constants removed - use database settings instead
define('ACADEMIC_YEAR_START_MONTH', 9);

// Development/Production Settings
define('DEBUG_MODE', env_bool('DEBUG_MODE', false)); // Set via env
define('LOG_ERRORS', true);
define('SHOW_ERRORS', DEBUG_MODE);

// Error Reporting
error_reporting(DEBUG_MODE ? E_ALL : 0);
ini_set('display_errors', DEBUG_MODE ? '1' : '0');

// Timezone
$__tz = env_get('TIMEZONE', 'Asia/Karachi');
date_default_timezone_set($__tz);

// Session Configuration (only if session not yet started)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
    ini_set('session.use_strict_mode', 1);
    ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);
    // Align session name with admin to share login
    ini_set('session.name', env_get('SESSION_NAME', 'QR_ATTENDANCE_SESSION'));
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
function getConfig($key, $default = null) {
    return defined($key) ? constant($key) : $default;
}

function isProduction() {
    return !DEBUG_MODE;
}

function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['SCRIPT_NAME']);
    return $protocol . '://' . $host . $path;
}

function getAssetUrl($path) {
    return getBaseUrl() . '/assets/' . ltrim($path, '/');
}

function getApiUrl($endpoint) {
    return getBaseUrl() . '/api/' . ltrim($endpoint, '/');
}

// Create necessary directories
$directories = [
    QR_CODE_PATH,
    'logs/',
    'uploads/',
    'backups/',
    'temp/'
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Logging function
function logMessage($message, $level = 'INFO') {
    if (!LOG_ERRORS) return;
    $logDir = storage_path('logs');
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    $logFile = $logDir . DIRECTORY_SEPARATOR . 'system_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$level] $message" . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// Error handler
if (LOG_ERRORS) {
    set_error_handler(function($severity, $message, $file, $line) {
        logMessage("PHP Error: $message in $file on line $line", 'ERROR');
    });
    
    set_exception_handler(function($exception) {
        logMessage("Uncaught Exception: " . $exception->getMessage(), 'ERROR');
    });
}

// CSRF Protection
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function getRequestCsrfToken() {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    if (!empty($headers['X-CSRF-Token'])) return $headers['X-CSRF-Token'];
    if (!empty($headers['x-csrf-token'])) return $headers['x-csrf-token'];
    return $_POST['csrf_token'] ?? null;
}

function requireCsrfForMethods($methods = ['POST','PUT','PATCH','DELETE']) {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (in_array($method, $methods, true)) {
        $token = getRequestCsrfToken();
        if (!$token || !validateCSRFToken($token)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'CSRF validation failed']);
            exit();
        }
    }
}

// CORS helper
function setCorsHeaders() {
    $origin = env_get('FRONTEND_ORIGIN', env_get('FRONTEND_URL', 'http://localhost'));
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
    header('Access-Control-Allow-Credentials: true');
}

// Rate Limiting
function checkRateLimit($identifier, $limit = API_RATE_LIMIT, $window = 3600) {
    $key = "rate_limit_$identifier";
    $current_time = time();
    $window_start = $current_time - $window;
    
    // This is a simple in-memory rate limiter
    // In production, use Redis or database
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [];
    }
    
    // Clean old entries
    $_SESSION[$key] = array_filter($_SESSION[$key], function($timestamp) use ($window_start) {
        return $timestamp > $window_start;
    });
    
    // Check if limit exceeded
    if (count($_SESSION[$key]) >= $limit) {
        return false;
    }
    
    // Add current request
    $_SESSION[$key][] = $current_time;
    return true;
}

// Input sanitization
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Validate email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Validate student ID format
function isValidStudentId($student_id) {
    return preg_match('/^[A-Z0-9-]+$/', $student_id) && strlen($student_id) >= 5;
}

// Generate secure random string
function generateSecureToken($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

// Format file size
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
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

// Initialize session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set CSRF token
generateCSRFToken();
?>
