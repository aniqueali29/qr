<?php
/**
 * CSRF Protection System
 * Provides comprehensive CSRF protection for all forms and API endpoints
 */

class CSRFProtection {
    
    private static $token_name = 'csrf_token';
    private static $token_length = 32;
    
    /**
     * Generate a new CSRF token
     */
    public static function generateToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $token = bin2hex(random_bytes(self::$token_length));
        $_SESSION[self::$token_name] = $token;
        $_SESSION[self::$token_name . '_time'] = time();
        
        return $token;
    }
    
    /**
     * Get the current CSRF token (generate if not exists)
     */
    public static function getToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION[self::$token_name])) {
            return self::generateToken();
        }
        
        // Check if token is expired (24 hours)
        if (isset($_SESSION[self::$token_name . '_time']) && 
            (time() - $_SESSION[self::$token_name . '_time']) > 86400) {
            return self::generateToken();
        }
        
        return $_SESSION[self::$token_name];
    }
    
    /**
     * Validate CSRF token
     */
    public static function validateToken($token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION[self::$token_name])) {
            return false;
        }
        
        // Check if token is expired (24 hours)
        if (isset($_SESSION[self::$token_name . '_time']) && 
            (time() - $_SESSION[self::$token_name . '_time']) > 86400) {
            unset($_SESSION[self::$token_name]);
            unset($_SESSION[self::$token_name . '_time']);
            return false;
        }
        
        return hash_equals($_SESSION[self::$token_name], $token);
    }
    
    /**
     * Validate CSRF token from various sources
     */
    public static function validateRequest() {
        $token = null;
        
        // Try to get token from various sources
        if (isset($_POST[self::$token_name])) {
            $token = $_POST[self::$token_name];
        } elseif (isset($_GET[self::$token_name])) {
            $token = $_GET[self::$token_name];
        } elseif (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'];
        } elseif (isset($_SERVER['HTTP_X_CSRFTOKEN'])) {
            $token = $_SERVER['HTTP_X_CSRFTOKEN'];
        }
        
        if ($token === null) {
            return false;
        }
        
        return self::validateToken($token);
    }
    
    /**
     * Require CSRF validation for the current request
     */
    public static function requireValidation() {
        if (!self::validateRequest()) {
            http_response_code(403);
            if (self::isAjaxRequest()) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'CSRF token validation failed',
                    'error_code' => 'CSRF_TOKEN_INVALID'
                ]);
            } else {
                echo '<h1>403 Forbidden</h1><p>CSRF token validation failed.</p>';
            }
            exit;
        }
    }
    
    /**
     * Check if the request is AJAX
     */
    private static function isAjaxRequest() {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    /**
     * Generate CSRF token HTML input
     */
    public static function generateInput() {
        $token = self::getToken();
        return '<input type="hidden" name="' . self::$token_name . '" value="' . htmlspecialchars($token) . '">';
    }
    
    /**
     * Generate CSRF token for JavaScript
     */
    public static function generateTokenForJS() {
        return self::getToken();
    }
}
?>

