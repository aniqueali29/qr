<?php
/**
 * Secure Configuration Loader
 * Loads configuration from environment variables and provides secure defaults
 */

class SecureConfig {
    private static $config = [];
    private static $loaded = false;
    
    /**
     * Load configuration from environment variables
     */
    public static function load() {
        if (self::$loaded) {
            return self::$config;
        }
        
        // Load from config.env file if it exists
        $envFile = __DIR__ . '/../config.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    
                    // Remove quotes if present
                    if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                        (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                        $value = substr($value, 1, -1);
                    }
                    
                    $_ENV[$key] = $value;
                    putenv("$key=$value");
                }
            }
        }
        
        // Load configuration with secure defaults
        self::$config = [
            // Database Configuration
            'DB_HOST' => self::getEnv('DB_HOST', 'localhost'),
            'DB_NAME' => self::getEnv('DB_NAME', 'qr_attendance'),
            'DB_USER' => self::getEnv('DB_USER', ''),
            'DB_PASS' => self::getEnv('DB_PASS', ''),
            
            // API Configuration
            'API_KEY' => self::getEnv('API_KEY', self::generateSecureKey()),
            'API_SECRET' => self::getEnv('API_SECRET', self::generateSecureKey()),
            
            // SMTP Configuration
            'SMTP_HOST' => self::getEnv('SMTP_HOST', 'localhost'),
            'SMTP_PORT' => (int)self::getEnv('SMTP_PORT', '587'),
            'SMTP_USERNAME' => self::getEnv('SMTP_USERNAME', ''),
            'SMTP_PASSWORD' => self::getEnv('SMTP_PASSWORD', ''),
            
            // Security Configuration
            'JWT_SECRET' => self::getEnv('JWT_SECRET', self::generateSecureKey()),
            'ENCRYPTION_KEY' => self::getEnv('ENCRYPTION_KEY', self::generateSecureKey()),
            
            // System Configuration
            'DEBUG_MODE' => self::getEnv('DEBUG_MODE', 'false') === 'true',
            'LOG_LEVEL' => self::getEnv('LOG_LEVEL', 'error'),
            'TIMEZONE' => self::getEnv('TIMEZONE', 'Asia/Karachi'),
            
            // File Upload Configuration
            'MAX_FILE_SIZE' => (int)self::getEnv('MAX_FILE_SIZE', '5242880'),
            'UPLOAD_PATH' => self::getEnv('UPLOAD_PATH', 'uploads/'),
            'QR_CODE_PATH' => self::getEnv('QR_CODE_PATH', 'qr_codes/'),
            
            // Session Configuration
            'SESSION_TIMEOUT' => (int)self::getEnv('SESSION_TIMEOUT', '3600'),
            'SESSION_NAME' => self::getEnv('SESSION_NAME', 'QR_ATTENDANCE_SESSION'),
        ];
        
        self::$loaded = true;
        return self::$config;
    }
    
    /**
     * Get configuration value
     */
    public static function get($key, $default = null) {
        if (!self::$loaded) {
            self::load();
        }
        
        return isset(self::$config[$key]) ? self::$config[$key] : $default;
    }
    
    /**
     * Get environment variable with fallback
     */
    private static function getEnv($key, $default = null) {
        $value = getenv($key);
        if ($value === false) {
            $value = isset($_ENV[$key]) ? $_ENV[$key] : $default;
        }
        return $value;
    }
    
    /**
     * Generate secure random key
     */
    private static function generateSecureKey($length = 32) {
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes($length));
        } elseif (function_exists('openssl_random_pseudo_bytes')) {
            return bin2hex(openssl_random_pseudo_bytes($length));
        } else {
            // Fallback for older PHP versions
            $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
            $key = '';
            for ($i = 0; $i < $length * 2; $i++) {
                $key .= $chars[mt_rand(0, strlen($chars) - 1)];
            }
            return $key;
        }
    }
    
    /**
     * Validate configuration
     */
    public static function validate() {
        $required = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'];
        $missing = [];
        
        foreach ($required as $key) {
            if (empty(self::get($key))) {
                $missing[] = $key;
            }
        }
        
        if (!empty($missing)) {
            throw new Exception('Missing required configuration: ' . implode(', ', $missing));
        }
        
        return true;
    }
}

// Auto-load configuration
SecureConfig::load();
?>
