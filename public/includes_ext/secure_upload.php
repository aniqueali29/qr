<?php
/**
 * Secure File Upload System
 * Provides secure file upload handling with comprehensive validation
 */

class SecureUpload {
    
    private static $allowed_types = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/gif' => ['gif'],
        'image/webp' => ['webp'],
        'application/pdf' => ['pdf'],
        'application/msword' => ['doc'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx']
    ];
    
    private static $max_file_size = 5 * 1024 * 1024; // 5MB
    private static $upload_path = 'uploads/';
    private static $quarantine_path = 'quarantine/';
    
    /**
     * Validate uploaded file
     */
    public static function validateFile($file, $allowed_extensions = null) {
        $errors = [];
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = self::getUploadErrorMessage($file['error']);
            return ['valid' => false, 'errors' => $errors];
        }
        
        // Check file size
        if ($file['size'] > self::$max_file_size) {
            $errors[] = "File size exceeds maximum allowed size of " . self::formatBytes(self::$max_file_size);
        }
        
        // Check if file is actually uploaded
        if (!is_uploaded_file($file['tmp_name'])) {
            $errors[] = "File was not properly uploaded";
        }
        
        // Get file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Check file extension
        if ($allowed_extensions && !in_array($extension, $allowed_extensions)) {
            $errors[] = "File extension '{$extension}' is not allowed";
        }
        
        // Validate MIME type
        $mime_type = self::getMimeType($file['tmp_name']);
        if (!$mime_type) {
            $errors[] = "Unable to determine file type";
        } elseif (!isset(self::$allowed_types[$mime_type])) {
            $errors[] = "File type '{$mime_type}' is not allowed";
        } elseif (!in_array($extension, self::$allowed_types[$mime_type])) {
            $errors[] = "File extension does not match file type";
        }
        
        // Additional security checks
        $security_check = self::performSecurityChecks($file);
        if (!$security_check['safe']) {
            $errors = array_merge($errors, $security_check['errors']);
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'mime_type' => $mime_type,
            'extension' => $extension
        ];
    }
    
    /**
     * Upload file securely
     */
    public static function uploadFile($file, $destination_dir = null, $custom_name = null) {
        // Validate file first
        $validation = self::validateFile($file);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors']
            ];
        }
        
        // Set destination directory
        $upload_dir = $destination_dir ?: self::$upload_path;
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                return [
                    'success' => false,
                    'errors' => ['Failed to create upload directory']
                ];
            }
        }
        
        // Generate secure filename
        $filename = $custom_name ?: self::generateSecureFilename($file['name'], $validation['extension']);
        $filepath = $upload_dir . '/' . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            return [
                'success' => false,
                'errors' => ['Failed to move uploaded file']
            ];
        }
        
        // Set proper permissions
        chmod($filepath, 0644);
        
        // Additional security scan
        $security_scan = self::scanUploadedFile($filepath);
        if (!$security_scan['safe']) {
            // Move to quarantine
            $quarantine_path = self::$quarantine_path . '/' . $filename;
            if (!is_dir(self::$quarantine_path)) {
                mkdir(self::$quarantine_path, 0755, true);
            }
            rename($filepath, $quarantine_path);
            
            return [
                'success' => false,
                'errors' => ['File failed security scan: ' . implode(', ', $security_scan['errors'])]
            ];
        }
        
        return [
            'success' => true,
            'filename' => $filename,
            'filepath' => $filepath,
            'size' => $file['size'],
            'mime_type' => $validation['mime_type']
        ];
    }
    
    /**
     * Get MIME type of file
     */
    private static function getMimeType($filepath) {
        // Use finfo if available
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $filepath);
            finfo_close($finfo);
            return $mime_type;
        }
        
        // Fallback to mime_content_type
        if (function_exists('mime_content_type')) {
            return mime_content_type($filepath);
        }
        
        return false;
    }
    
    /**
     * Perform security checks on uploaded file
     */
    private static function performSecurityChecks($file) {
        $errors = [];
        
        // Check for PHP code in file content
        $content = file_get_contents($file['tmp_name']);
        
        // Check for PHP tags
        if (strpos($content, '<?php') !== false || strpos($content, '<?=') !== false) {
            $errors[] = "File contains PHP code";
        }
        
        // Check for script tags
        if (strpos($content, '<script') !== false) {
            $errors[] = "File contains script tags";
        }
        
        // Check for executable signatures
        $executable_signatures = [
            "\x4D\x5A", // PE/COFF executable
            "\x7F\x45\x4C\x46", // ELF executable
            "\xFE\xED\xFA", // Mach-O executable
        ];
        
        foreach ($executable_signatures as $signature) {
            if (strpos($content, $signature) === 0) {
                $errors[] = "File appears to be an executable";
                break;
            }
        }
        
        // Check file size vs content size
        if (strlen($content) !== $file['size']) {
            $errors[] = "File size mismatch";
        }
        
        return [
            'safe' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Scan uploaded file for malware
     */
    private static function scanUploadedFile($filepath) {
        $errors = [];
        
        // Check file permissions
        $perms = fileperms($filepath);
        if ($perms & 0x0001) { // World executable
            $errors[] = "File has world executable permissions";
        }
        
        // Additional content checks
        $content = file_get_contents($filepath);
        
        // Check for suspicious patterns
        $suspicious_patterns = [
            '/eval\s*\(/i',
            '/base64_decode\s*\(/i',
            '/system\s*\(/i',
            '/exec\s*\(/i',
            '/shell_exec\s*\(/i',
            '/passthru\s*\(/i',
            '/file_get_contents\s*\(\s*["\']http/i',
            '/curl_exec\s*\(/i',
            '/fopen\s*\(\s*["\']http/i'
        ];
        
        foreach ($suspicious_patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                $errors[] = "File contains suspicious code patterns";
                break;
            }
        }
        
        return [
            'safe' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Generate secure filename
     */
    private static function generateSecureFilename($original_name, $extension) {
        // Remove any path information
        $filename = basename($original_name);
        
        // Generate random prefix
        $prefix = bin2hex(random_bytes(16));
        
        // Sanitize filename
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        
        // Create secure filename
        return $prefix . '_' . $filename;
    }
    
    /**
     * Get upload error message
     */
    private static function getUploadErrorMessage($error_code) {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        
        return $messages[$error_code] ?? 'Unknown upload error';
    }
    
    /**
     * Format bytes to human readable format
     */
    private static function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Delete uploaded file
     */
    public static function deleteFile($filepath) {
        if (file_exists($filepath) && is_file($filepath)) {
            return unlink($filepath);
        }
        return false;
    }
    
    /**
     * Clean up old files
     */
    public static function cleanupOldFiles($directory, $max_age_days = 30) {
        if (!is_dir($directory)) {
            return false;
        }
        
        $files = glob($directory . '/*');
        $cutoff_time = time() - ($max_age_days * 24 * 60 * 60);
        $deleted_count = 0;
        
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoff_time) {
                if (unlink($file)) {
                    $deleted_count++;
                }
            }
        }
        
        return $deleted_count;
    }
    
    /**
     * Validate image file specifically
     */
    public static function validateImage($file) {
        $validation = self::validateFile($file, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
        
        if (!$validation['valid']) {
            return $validation;
        }
        
        // Additional image-specific validation
        $image_info = getimagesize($file['tmp_name']);
        if ($image_info === false) {
            return [
                'valid' => false,
                'errors' => ['File is not a valid image']
            ];
        }
        
        // Check image dimensions
        $max_width = 4000;
        $max_height = 4000;
        
        if ($image_info[0] > $max_width || $image_info[1] > $max_height) {
            return [
                'valid' => false,
                'errors' => ["Image dimensions exceed maximum allowed size ({$max_width}x{$max_height})"]
            ];
        }
        
        return $validation;
    }
}
?>

