<?php
/**
 * Secure Session Management System
 * Provides secure session handling with proper security measures
 */

class SecureSession {
    
    private static $session_name = 'SECURE_SESSION';
    private static $session_timeout = 3600; // 1 hour
    private static $regenerate_interval = 300; // 5 minutes
    private static $max_login_attempts = 5;
    private static $lockout_time = 900; // 15 minutes
    
    /**
     * Initialize secure session
     */
    public static function start() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        
        // Check if headers have already been sent
        if (headers_sent()) {
            // If headers are sent, just start session without configuration
            session_start();
            return;
        }
        
        // Set secure session configuration
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.gc_maxlifetime', self::$session_timeout);
        ini_set('session.name', self::$session_name);
        
        // Set session save path to a secure location under public/sessions
        $session_path = __DIR__ . '/../sessions';
        if (!is_dir($session_path)) {
            mkdir($session_path, 0700, true);
        }
        ini_set('session.save_path', $session_path);
        
        // Start session
        session_start();
        
        // Initialize session security
        self::initializeSecurity();
        
        // Check for session timeout
        self::checkTimeout();
        
        // Regenerate session ID periodically
        self::regenerateIfNeeded();
    }
    
    /**
     * Initialize session security variables
     */
    private static function initializeSecurity() {
        if (!isset($_SESSION['_security'])) {
            $_SESSION['_security'] = [
                'created' => time(),
                'last_activity' => time(),
                'ip_address' => self::getClientIP(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'login_attempts' => 0,
                'locked_until' => 0
            ];
        }
    }
    
    /**
     * Check session timeout
     */
    private static function checkTimeout() {
        if (!isset($_SESSION['_security']['last_activity'])) {
            self::destroy();
            return;
        }
        
        $last_activity = $_SESSION['_security']['last_activity'];
        if (time() - $last_activity > self::$session_timeout) {
            self::destroy();
            return;
        }
        
        // Update last activity
        $_SESSION['_security']['last_activity'] = time();
    }
    
    /**
     * Regenerate session ID if needed
     */
    private static function regenerateIfNeeded() {
        if (!isset($_SESSION['_security']['last_regeneration'])) {
            $_SESSION['_security']['last_regeneration'] = time();
            return;
        }
        
        if (time() - $_SESSION['_security']['last_regeneration'] > self::$regenerate_interval) {
            session_regenerate_id(true);
            $_SESSION['_security']['last_regeneration'] = time();
        }
    }
    
    /**
     * Validate session security
     */
    public static function validate() {
        if (!isset($_SESSION['_security'])) {
            return false;
        }
        
        $security = $_SESSION['_security'];
        
        // Check IP address
        if ($security['ip_address'] !== self::getClientIP()) {
            self::destroy();
            return false;
        }
        
        // Check user agent
        if ($security['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
            self::destroy();
            return false;
        }
        
        // Check if session is locked
        if ($security['locked_until'] > time()) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Record login attempt
     */
    public static function recordLoginAttempt($success = false) {
        if (!isset($_SESSION['_security'])) {
            self::initializeSecurity();
        }
        
        if ($success) {
            $_SESSION['_security']['login_attempts'] = 0;
            $_SESSION['_security']['locked_until'] = 0;
        } else {
            $_SESSION['_security']['login_attempts']++;
            
            if ($_SESSION['_security']['login_attempts'] >= self::$max_login_attempts) {
                $_SESSION['_security']['locked_until'] = time() + self::$lockout_time;
            }
        }
    }
    
    /**
     * Check if account is locked
     */
    public static function isLocked() {
        if (!isset($_SESSION['_security'])) {
            return false;
        }
        
        return $_SESSION['_security']['locked_until'] > time();
    }
    
    /**
     * Get remaining lockout time
     */
    public static function getLockoutTime() {
        if (!isset($_SESSION['_security'])) {
            return 0;
        }
        
        $locked_until = $_SESSION['_security']['locked_until'];
        return max(0, $locked_until - time());
    }
    
    /**
     * Set session data securely
     */
    public static function set($key, $value) {
        // Ensure session is started
        self::start();
        
        // Initialize security if not already done
        if (!isset($_SESSION['_security'])) {
            self::initializeSecurity();
        }
        
        $_SESSION[$key] = $value;
        return true;
    }
    
    /**
     * Get session data securely
     */
    public static function get($key, $default = null) {
        // Ensure session is started
        self::start();
        
        // If session not initialized yet, return default
        if (!isset($_SESSION['_security'])) {
            return $default;
        }
        
        // Validate only if security is initialized
        if (!self::validate()) {
            return $default;
        }
        
        return $_SESSION[$key] ?? $default;
    }
    
    /**
     * Check if session key exists
     */
    public static function has($key) {
        // Ensure session is started
        self::start();
        
        // If session not initialized yet, return false
        if (!isset($_SESSION['_security'])) {
            return false;
        }
        
        // Validate only if security is initialized
        if (!self::validate()) {
            return false;
        }
        
        return isset($_SESSION[$key]);
    }
    
    /**
     * Remove session data
     */
    public static function remove($key) {
        if (!self::validate()) {
            return false;
        }
        
        unset($_SESSION[$key]);
        return true;
    }
    
    /**
     * Destroy session completely
     */
    public static function destroy() {
        // Clear session data
        $_SESSION = [];
        
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
    }
    
    /**
     * Get client IP address
     */
    private static function getClientIP() {
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
    
    /**
     * Clean up old sessions
     */
    public static function cleanup() {
        $session_path = ini_get('session.save_path');
        if (!$session_path || !is_dir($session_path)) {
            return;
        }
        
        $files = glob($session_path . '/sess_*');
        $now = time();
        
        foreach ($files as $file) {
            if (is_file($file) && ($now - filemtime($file)) > self::$session_timeout) {
                unlink($file);
            }
        }
    }
    
    /**
     * Get session information for debugging
     */
    public static function getInfo() {
        if (!isset($_SESSION['_security'])) {
            return null;
        }
        
        return [
            'session_id' => session_id(),
            'created' => date('Y-m-d H:i:s', $_SESSION['_security']['created']),
            'last_activity' => date('Y-m-d H:i:s', $_SESSION['_security']['last_activity']),
            'ip_address' => $_SESSION['_security']['ip_address'],
            'login_attempts' => $_SESSION['_security']['login_attempts'],
            'is_locked' => self::isLocked(),
            'lockout_time' => self::getLockoutTime()
        ];
    }
}

// Auto-start secure session
SecureSession::start();
?>

