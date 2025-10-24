<?php
/**
 * Secure Password Management System
 * Handles password hashing, verification, and validation
 */

class PasswordManager {
    
    /**
     * Hash a password using Argon2ID (most secure algorithm)
     */
    public static function hashPassword($password) {
        // Use Argon2ID if available, otherwise fall back to bcrypt
        if (defined('PASSWORD_ARGON2ID')) {
            return password_hash($password, PASSWORD_ARGON2ID, [
                'memory_cost' => 65536, // 64 MB
                'time_cost' => 4,       // 4 iterations
                'threads' => 3,         // 3 threads
            ]);
        } else {
            return password_hash($password, PASSWORD_BCRYPT, [
                'cost' => 12
            ]);
        }
    }
    
    /**
     * Verify a password against its hash
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Check if a password needs to be rehashed (for algorithm upgrades)
     */
    public static function needsRehash($hash) {
        if (defined('PASSWORD_ARGON2ID')) {
            return password_needs_rehash($hash, PASSWORD_ARGON2ID, [
                'memory_cost' => 65536,
                'time_cost' => 4,
                'threads' => 3,
            ]);
        } else {
            return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 12]);
        }
    }
    
    /**
     * Validate password strength
     */
    public static function validatePassword($password) {
        $errors = [];
        
        // Minimum length
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long';
        }
        
        // Maximum length
        if (strlen($password) > 128) {
            $errors[] = 'Password must be less than 128 characters';
        }
        
        // Check for at least one uppercase letter
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        }
        
        // Check for at least one lowercase letter
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter';
        }
        
        // Check for at least one number
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number';
        }
        
        // Check for at least one special character
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'Password must contain at least one special character';
        }
        
        // Check for common weak passwords
        $weakPasswords = [
            'password', '123456', '123456789', 'qwerty', 'abc123',
            'password123', 'admin', 'letmein', 'welcome', 'monkey'
        ];
        
        if (in_array(strtolower($password), $weakPasswords)) {
            $errors[] = 'Password is too common and easily guessable';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Generate a secure random password
     */
    public static function generateSecurePassword($length = 12) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        
        // Ensure at least one character from each required type
        $password .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'[random_int(0, 25)]; // Uppercase
        $password .= 'abcdefghijklmnopqrstuvwxyz'[random_int(0, 25)]; // Lowercase
        $password .= '0123456789'[random_int(0, 9)]; // Number
        $password .= '!@#$%^&*'[random_int(0, 7)]; // Special character
        
        // Fill the rest with random characters
        for ($i = 4; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        
        // Shuffle the password
        return str_shuffle($password);
    }
    
    /**
     * Check if password is in common password list
     */
    public static function isCommonPassword($password) {
        // This would typically check against a database of common passwords
        // For now, we'll use a basic list
        $commonPasswords = [
            'password', '123456', '123456789', 'qwerty', 'abc123',
            'password123', 'admin', 'letmein', 'welcome', 'monkey',
            'dragon', 'master', 'hello', 'freedom', 'whatever',
            'qazwsx', 'trustno1', '654321', 'jordan23', 'harley',
            'password1', '1234', 'robert', 'matthew', 'jordan'
        ];
        
        return in_array(strtolower($password), $commonPasswords);
    }
    
    /**
     * Secure password comparison to prevent timing attacks
     */
    public static function secureCompare($a, $b) {
        return hash_equals($a, $b);
    }
    
    /**
     * Get password strength score (0-100)
     */
    public static function getPasswordStrength($password) {
        $score = 0;
        
        // Length bonus
        $length = strlen($password);
        if ($length >= 8) $score += 10;
        if ($length >= 12) $score += 10;
        if ($length >= 16) $score += 10;
        
        // Character variety bonus
        if (preg_match('/[a-z]/', $password)) $score += 10;
        if (preg_match('/[A-Z]/', $password)) $score += 10;
        if (preg_match('/[0-9]/', $password)) $score += 10;
        if (preg_match('/[^A-Za-z0-9]/', $password)) $score += 10;
        
        // Pattern penalties
        if (preg_match('/(.)\1{2,}/', $password)) $score -= 10; // Repeated characters
        if (preg_match('/123|abc|qwe/i', $password)) $score -= 10; // Sequential patterns
        
        // Common password penalty
        if (self::isCommonPassword($password)) $score -= 20;
        
        return max(0, min(100, $score));
    }
}
?>
