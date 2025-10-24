<?php
/**
 * Security Configuration
 * Centralized security settings and configurations
 */

// Security Headers
class SecurityHeaders {
    
    /**
     * Set comprehensive security headers
     */
    public static function setHeaders() {
        // Prevent clickjacking
        header('X-Frame-Options: DENY');
        
        // Prevent MIME type sniffing
        header('X-Content-Type-Options: nosniff');
        
        // Enable XSS protection
        header('X-XSS-Protection: 1; mode=block');
        
        // Strict Transport Security (HTTPS only)
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }
        
        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Content Security Policy
        $csp = "default-src 'self'; " .
               "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; " .
               "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; " .
               "img-src 'self' data: https:; " .
               "font-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; " .
               "connect-src 'self'; " .
               "frame-ancestors 'none'; " .
               "base-uri 'self'; " .
               "form-action 'self'";
        
        header("Content-Security-Policy: {$csp}");
        
        // Permissions Policy
        $permissions = "geolocation=(), microphone=(), camera=(), payment=(), usb=(), magnetometer=(), gyroscope=(), speaker=()";
        header("Permissions-Policy: {$permissions}");
    }
}

// Input Sanitization
class InputSanitizer {
    
    /**
     * Sanitize string input
     */
    public static function sanitizeString($input, $max_length = null) {
        if (is_array($input)) {
            return array_map([self::class, 'sanitizeString'], $input);
        }
        
        $sanitized = trim($input);
        $sanitized = htmlspecialchars($sanitized, ENT_QUOTES, 'UTF-8');
        
        if ($max_length && strlen($sanitized) > $max_length) {
            $sanitized = substr($sanitized, 0, $max_length);
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize integer input
     */
    public static function sanitizeInt($input, $min = null, $max = null) {
        $int = filter_var($input, FILTER_VALIDATE_INT);
        
        if ($int === false) {
            return null;
        }
        
        if ($min !== null && $int < $min) {
            return $min;
        }
        
        if ($max !== null && $int > $max) {
            return $max;
        }
        
        return $int;
    }
    
    /**
     * Sanitize email input
     */
    public static function sanitizeEmail($input) {
        return filter_var(trim($input), FILTER_SANITIZE_EMAIL);
    }
    
    /**
     * Sanitize URL input
     */
    public static function sanitizeUrl($input) {
        return filter_var(trim($input), FILTER_SANITIZE_URL);
    }
    
    /**
     * Sanitize filename
     */
    public static function sanitizeFilename($filename) {
        // Remove path information
        $filename = basename($filename);
        
        // Remove dangerous characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        
        // Remove multiple dots
        $filename = preg_replace('/\.{2,}/', '.', $filename);
        
        // Limit length
        if (strlen($filename) > 255) {
            $filename = substr($filename, 0, 255);
        }
        
        return $filename;
    }
}

// Security Utilities
class SecurityUtils {
    
    /**
     * Generate secure random token
     */
    public static function generateToken($length = 32) {
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes($length));
        } elseif (function_exists('openssl_random_pseudo_bytes')) {
            return bin2hex(openssl_random_pseudo_bytes($length));
        } else {
            // Fallback for older PHP versions
            $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            $token = '';
            for ($i = 0; $i < $length * 2; $i++) {
                $token .= $chars[mt_rand(0, strlen($chars) - 1)];
            }
            return $token;
        }
    }
    
    /**
     * Hash data securely
     */
    public static function hashData($data, $algorithm = 'sha256') {
        return hash($algorithm, $data);
    }
    
    /**
     * Compare hashes securely
     */
    public static function compareHashes($hash1, $hash2) {
        return hash_equals($hash1, $hash2);
    }
    
    /**
     * Encrypt data
     */
    public static function encrypt($data, $key) {
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt data
     */
    public static function decrypt($encrypted_data, $key) {
        $data = base64_decode($encrypted_data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }
    
    /**
     * Validate file type by content
     */
    public static function validateFileType($filepath, $allowed_types) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $filepath);
        finfo_close($finfo);
        
        return in_array($mime_type, $allowed_types);
    }
    
    /**
     * Check if IP is in whitelist
     */
    public static function isIPWhitelisted($ip, $whitelist) {
        foreach ($whitelist as $allowed_ip) {
            if (self::ipInRange($ip, $allowed_ip)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Check if IP is in range
     */
    private static function ipInRange($ip, $range) {
        if (strpos($range, '/') !== false) {
            // CIDR notation
            list($subnet, $mask) = explode('/', $range);
            $ip_long = ip2long($ip);
            $subnet_long = ip2long($subnet);
            $mask_long = -1 << (32 - $mask);
            return ($ip_long & $mask_long) === ($subnet_long & $mask_long);
        } else {
            // Single IP
            return $ip === $range;
        }
    }
    
    /**
     * Log security event
     */
    public static function logSecurityEvent($event, $details = '', $level = 'INFO') {
        $log_file = __DIR__ . '/../logs/security_' . date('Y-m-d') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $log_entry = "[{$timestamp}] [{$level}] [{$ip}] [{$user_agent}] {$event}: {$details}" . PHP_EOL;
        
        file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Clean up old log files
     */
    public static function cleanupLogs($days = 30) {
        $log_dir = __DIR__ . '/../logs/';
        if (!is_dir($log_dir)) {
            return;
        }
        
        $files = glob($log_dir . '*.log');
        $cutoff_time = time() - ($days * 24 * 60 * 60);
        
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoff_time) {
                unlink($file);
            }
        }
    }
}

// Auto-set security headers
SecurityHeaders::setHeaders();

// Auto-cleanup old logs (run once per day)
$cleanup_file = __DIR__ . '/../logs/.last_cleanup';
if (!file_exists($cleanup_file) || (time() - filemtime($cleanup_file)) > 86400) {
    SecurityUtils::cleanupLogs();
    touch($cleanup_file);
}
?>
