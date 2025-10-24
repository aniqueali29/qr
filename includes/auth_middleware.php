<?php
/**
 * Authentication Middleware System
 * Provides comprehensive authentication and authorization checks
 */

class AuthMiddleware {
    
    private static $rate_limit_attempts = [];
    private static $max_attempts = 5;
    private static $lockout_time = 900; // 15 minutes
    
    /**
     * Require authentication for the current request
     */
    public static function requireAuth($redirect_url = null) {
        if (!self::isAuthenticated()) {
            if (self::isAjaxRequest()) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Authentication required',
                    'redirect' => $redirect_url ?: 'login.php'
                ]);
                exit;
            } else {
                $redirect = $redirect_url ?: 'login.php';
                header("Location: {$redirect}");
                exit;
            }
        }
    }
    
    /**
     * Require specific role
     */
    public static function requireRole($role, $redirect_url = null) {
        self::requireAuth($redirect_url);
        
        if (!self::hasRole($role)) {
            if (self::isAjaxRequest()) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Insufficient permissions',
                    'required_role' => $role
                ]);
                exit;
            } else {
                http_response_code(403);
                echo '<h1>403 Forbidden</h1><p>You do not have permission to access this resource.</p>';
                exit;
            }
        }
    }
    
    /**
     * Check if user is authenticated
     */
    public static function isAuthenticated() {
        require_once __DIR__ . '/secure_session.php';
        
        // Check if session is valid
        if (!SecureSession::validate()) {
            return false;
        }
        
        // Check if user is locked out
        if (SecureSession::isLocked()) {
            return false;
        }
        
        // Check for required session variables
        $required_vars = ['student_id', 'student_username'];
        foreach ($required_vars as $var) {
            if (!SecureSession::has($var)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Check if user has specific role
     */
    public static function hasRole($role) {
        if (!self::isAuthenticated()) {
            return false;
        }
        
        require_once __DIR__ . '/secure_session.php';
        $user_role = SecureSession::get('user_role', 'student');
        
        return $user_role === $role;
    }
    
    /**
     * Check if user is admin
     */
    public static function isAdmin() {
        return self::hasRole('admin');
    }
    
    /**
     * Check if user is student
     */
    public static function isStudent() {
        return self::hasRole('student');
    }
    
    /**
     * Rate limiting for login attempts
     */
    public static function checkRateLimit($identifier = null) {
        $identifier = $identifier ?: self::getClientIP();
        $current_time = time();
        
        // Clean old attempts
        if (isset(self::$rate_limit_attempts[$identifier])) {
            self::$rate_limit_attempts[$identifier] = array_filter(
                self::$rate_limit_attempts[$identifier],
                function($timestamp) use ($current_time) {
                    return ($current_time - $timestamp) < self::$lockout_time;
                }
            );
        } else {
            self::$rate_limit_attempts[$identifier] = [];
        }
        
        // Check if limit exceeded
        if (count(self::$rate_limit_attempts[$identifier]) >= self::$max_attempts) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Record failed login attempt
     */
    public static function recordFailedAttempt($identifier = null) {
        $identifier = $identifier ?: self::getClientIP();
        
        if (!isset(self::$rate_limit_attempts[$identifier])) {
            self::$rate_limit_attempts[$identifier] = [];
        }
        
        self::$rate_limit_attempts[$identifier][] = time();
        
        // Also record in secure session
        require_once __DIR__ . '/secure_session.php';
        SecureSession::recordLoginAttempt(false);
    }
    
    /**
     * Record successful login attempt
     */
    public static function recordSuccessfulAttempt($identifier = null) {
        $identifier = $identifier ?: self::getClientIP();
        
        // Clear failed attempts
        if (isset(self::$rate_limit_attempts[$identifier])) {
            unset(self::$rate_limit_attempts[$identifier]);
        }
        
        // Record in secure session
        require_once __DIR__ . '/secure_session.php';
        SecureSession::recordLoginAttempt(true);
    }
    
    /**
     * Get remaining lockout time
     */
    public static function getLockoutTime($identifier = null) {
        $identifier = $identifier ?: self::getClientIP();
        
        if (!isset(self::$rate_limit_attempts[$identifier])) {
            return 0;
        }
        
        $attempts = self::$rate_limit_attempts[$identifier];
        if (count($attempts) < self::$max_attempts) {
            return 0;
        }
        
        $oldest_attempt = min($attempts);
        $unlock_time = $oldest_attempt + self::$lockout_time;
        
        return max(0, $unlock_time - time());
    }
    
    /**
     * Validate CSRF token
     */
    public static function validateCSRF($token = null) {
        require_once __DIR__ . '/csrf_protection.php';
        
        if ($token === null) {
            return CSRFProtection::validateRequest();
        }
        
        return CSRFProtection::validateToken($token);
    }
    
    /**
     * Require CSRF validation
     */
    public static function requireCSRF() {
        if (!self::validateCSRF()) {
            if (self::isAjaxRequest()) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'CSRF token validation failed'
                ]);
                exit;
            } else {
                http_response_code(403);
                echo '<h1>403 Forbidden</h1><p>CSRF token validation failed.</p>';
                exit;
            }
        }
    }
    
    /**
     * Check if request is AJAX
     */
    private static function isAjaxRequest() {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
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
     * Log security event
     */
    public static function logSecurityEvent($event, $details = '') {
        $log_file = __DIR__ . '/../logs/security_' . date('Y-m-d') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        $ip = self::getClientIP();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $log_entry = "[{$timestamp}] [{$ip}] [{$user_agent}] {$event}: {$details}" . PHP_EOL;
        
        file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Check for suspicious activity
     */
    public static function checkSuspiciousActivity() {
        $suspicious_patterns = [
            '/\.\.\//', // Directory traversal
            '/<script/i', // XSS attempts
            '/union\s+select/i', // SQL injection
            '/eval\s*\(/i', // Code injection
            '/base64_decode/i', // Obfuscated code
        ];
        
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        $query_string = $_SERVER['QUERY_STRING'] ?? '';
        $post_data = file_get_contents('php://input');
        
        $all_data = $request_uri . ' ' . $query_string . ' ' . $post_data;
        
        foreach ($suspicious_patterns as $pattern) {
            if (preg_match($pattern, $all_data)) {
                self::logSecurityEvent('SUSPICIOUS_ACTIVITY', "Pattern matched: {$pattern}");
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Block suspicious requests
     */
    public static function blockSuspiciousRequests() {
        if (self::checkSuspiciousActivity()) {
            http_response_code(403);
            echo '<h1>403 Forbidden</h1><p>Request blocked due to suspicious activity.</p>';
            exit;
        }
    }
    
    /**
     * Get current user information
     */
    public static function getCurrentUser() {
        if (!self::isAuthenticated()) {
            return null;
        }
        
        require_once __DIR__ . '/secure_session.php';
        
        return [
            'id' => SecureSession::get('student_id'),
            'username' => SecureSession::get('student_username'),
            'name' => SecureSession::get('student_name'),
            'email' => SecureSession::get('student_email'),
            'role' => SecureSession::get('user_role', 'student'),
            'login_time' => SecureSession::get('login_time')
        ];
    }
    
    /**
     * Logout user
     */
    public static function logout() {
        require_once __DIR__ . '/secure_session.php';
        
        $user = self::getCurrentUser();
        if ($user) {
            self::logSecurityEvent('LOGOUT', "User logged out: {$user['username']}");
        }
        
        SecureSession::destroy();
    }
}
?>
