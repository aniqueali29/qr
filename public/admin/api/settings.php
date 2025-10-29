<?php
/**
 * Admin Settings API
 * Enhanced settings management for admin panel
 */

// Enable error reporting for development (disable suppression)
// Errors will be logged to PHP error log, not displayed to client
error_reporting(E_ALL);
ini_set('display_errors', 0); // Keep this 0 to prevent HTML output in JSON
ini_set('log_errors', 1);     // Enable error logging

// Set JSON content type early
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

class AdminSettingsAPI {
    private $pdo;
    
    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        
        if (!$this->pdo) {
            error_log("Database connection failed in AdminSettingsAPI");
        }
    }
    
    private function isAcademicStructureKey($key) {
        $keys = [
            'academic_structure_mode',
            'semesters_per_year',
            'semester_names',
            'semester_start_months',
            'max_program_years',
            'academic_year_start_month'
        ];
        return in_array($key, $keys, true);
    }

    private function hasRegisteredStudents() {
        try {
            $stmt = $this->pdo->query("SELECT COUNT(*) AS c FROM students");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)($row['c'] ?? 0) > 0;
        } catch (Exception $e) {
            // Be conservative: if we cannot verify, assume students exist to protect integrity
            error_log('hasRegisteredStudents check failed: ' . $e->getMessage());
            return true;
        }
    }
    
    /**
     * Check if system_settings table exists
     * DO NOT create tables at runtime - they should be created during deployment
     */
    private function ensureTableExists() {
        try {
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'system_settings'");
            if ($stmt->rowCount() == 0) {
                error_log("CRITICAL: system_settings table missing! Run database migrations.");
                throw new Exception("Database schema not initialized. Please contact administrator.");
            }
            return true;
        } catch (PDOException $e) {
            error_log("Database check error: " . $e->getMessage());
            throw new Exception("Database connection error");
        }
    }
    
    /**
     * Get all settings with enhanced categorization
     */
    public function getAllSettings() {
        try {
            if (!$this->pdo) {
                return [
                    'success' => false,
                    'message' => 'Database connection not available'
                ];
            }
            
            // Check if table exists (don't create it)
            $this->ensureTableExists();
            
            // First, ensure all default settings exist
            $this->ensureDefaultSettings();
            
            $stmt = $this->pdo->query("
                SELECT setting_key, setting_value, setting_type, category, description, 
                       validation_rules, last_updated, updated_by
                FROM system_settings 
                ORDER BY category, setting_key
            ");
            
            $settings = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $category = $row['category'];
                if (!isset($settings[$category])) {
                    $settings[$category] = [];
                }
                
                // Convert value based on type
                $value = $this->convertValue($row['setting_value'], $row['setting_type']);
                
                $settings[$category][] = [
                    'key' => $row['setting_key'],
                    'value' => $value,
                    'type' => $row['setting_type'],
                    'description' => $row['description'],
                    'validation_rules' => json_decode($row['validation_rules'], true),
                    'last_updated' => $row['last_updated'],
                    'updated_by' => $row['updated_by']
                ];
            }
            
            return [
                'success' => true,
                'data' => $settings,
                'total_categories' => count($settings)
            ];
            
        } catch (PDOException $e) {
            error_log("Settings API Database Error: " . $e->getMessage());
            // Return default settings as fallback
            return [
                'success' => true,
                'data' => $this->getDefaultSettings(),
                'message' => 'Using default settings (database unavailable)'
            ];
        } catch (Exception $e) {
            error_log("Settings API General Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error loading settings: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get default settings as fallback
     */
    private function getDefaultSettings() {
        return [
            'timings' => [
                // No default timing values - all must be configured by admin
                // Timing settings are required and must be set in admin panel
            ],
            'system' => [
                ['key' => 'sync_interval_seconds', 'value' => '30', 'type' => 'integer'],
                ['key' => 'timezone', 'value' => 'Asia/Karachi', 'type' => 'string'],
                ['key' => 'academic_year_start_month', 'value' => '9', 'type' => 'integer'],
                ['key' => 'academic_structure_mode', 'value' => 'year', 'type' => 'string'],
                ['key' => 'semesters_per_year', 'value' => '2', 'type' => 'integer'],
                ['key' => 'semester_names', 'value' => '["Semester 1","Semester 2"]', 'type' => 'json'],
                ['key' => 'semester_start_months', 'value' => '[9,2]', 'type' => 'json'],
                ['key' => 'max_program_years', 'value' => '4', 'type' => 'integer'],
                ['key' => 'auto_absent_morning_hour', 'value' => '11', 'type' => 'integer'],
                ['key' => 'auto_absent_evening_hour', 'value' => '17', 'type' => 'integer']
            ],
            'advanced' => [
                ['key' => 'debug_mode', 'value' => true, 'type' => 'boolean'],
                ['key' => 'log_errors', 'value' => true, 'type' => 'boolean'],
                ['key' => 'show_errors', 'value' => true, 'type' => 'boolean'],
                ['key' => 'session_timeout_seconds', 'value' => '3600', 'type' => 'integer'],
                ['key' => 'max_login_attempts', 'value' => '5', 'type' => 'integer'],
                ['key' => 'login_lockout_minutes', 'value' => '15', 'type' => 'integer'],
                ['key' => 'password_min_length', 'value' => '8', 'type' => 'integer']
            ],
            'backup' => [
                ['key' => 'backup_frequency', 'value' => 'weekly', 'type' => 'string'],
                ['key' => 'backup_retention_days', 'value' => '30', 'type' => 'integer'],
                ['key' => 'last_backup_time', 'value' => null, 'type' => 'string']
            ]
        ];
    }
    
    /**
     * Ensure all default settings exist in database
     */
    private function ensureDefaultSettings() {
        $defaults = $this->getDefaultSettings();
        
        foreach ($defaults as $category => $settings) {
            foreach ($settings as $setting) {
                $key = $setting['key'];
                $value = $setting['value'];
                $type = $setting['type'];
                
                // Check if setting exists
                $stmt = $this->pdo->prepare("SELECT id FROM system_settings WHERE setting_key = ?");
                $stmt->execute([$key]);
                
                if ($stmt->fetch()) {
                    // Setting exists, update validation rules
                    $this->updateValidationRules($key);
                } else {
                    // Setting doesn't exist, create it
                    $stmt = $this->pdo->prepare("
                        INSERT INTO system_settings 
                        (setting_key, setting_value, setting_type, category, description, validation_rules)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    
                    $description = $this->getDescriptionForKey($key);
                    $validation = $this->getValidationRulesForKey($key);
                    
                    $stmt->execute([
                        $key,
                        (string) $value,
                        $type,
                        $category,
                        $description,
                        json_encode($validation)
                    ]);
                }
            }
        }
    }
    
    private function updateValidationRules($key) {
        try {
            $validation = $this->getValidationRulesForKey($key);
            $stmt = $this->pdo->prepare("UPDATE system_settings SET validation_rules = ? WHERE setting_key = ?");
            $stmt->execute([json_encode($validation), $key]);
            error_log("Updated validation rules for {$key}: " . json_encode($validation));
        } catch (Exception $e) {
            error_log("Error updating validation rules for {$key}: " . $e->getMessage());
        }
    }
    
    /**
     * Get category for a setting key
     */
    private function getCategoryForKey($key) {
        $categories = [
            'shift_timings' => ['morning_', 'evening_'],
'system_config' => ['sync_interval', 'timezone', 'academic_', 'academic_year', 'auto_absent'],
            'integration' => ['website_url', 'api_endpoint', 'api_key', 'api_timeout'],
            'advanced' => ['debug_mode', 'log_errors', 'show_errors', 'session_timeout', 'max_login', 'login_lockout', 'password_min', 'max_sync', 'api_rate'],
            'qr_code' => ['qr_code_'],
            'file_upload' => ['max_file_size', 'allowed_extensions'],
            'email' => ['smtp_']
        ];
        
        foreach ($categories as $category => $prefixes) {
            foreach ($prefixes as $prefix) {
                if (strpos($key, $prefix) === 0) {
                    return $category;
                }
            }
        }
        
        return 'general';
    }
    
    /**
     * Get type for a value
     */
    private function getTypeForValue($value) {
        if (is_bool($value)) {
            return 'boolean';
        } elseif (is_int($value)) {
            return 'integer';
        } elseif (is_float($value)) {
            return 'float';
        } elseif (is_array($value)) {
            return 'json';
        } elseif (is_string($value) && @json_decode($value) !== null) {
            return 'json';
        } elseif (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return 'email';
        } elseif (filter_var($value, FILTER_VALIDATE_URL)) {
            return 'url';
        } elseif (is_string($value) && preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $value)) {
            return 'time';
        } else {
            return 'string';
        }
    }
    
    /**
     * Get description for a setting key
     */
    private function getDescriptionForKey($key) {
        $descriptions = [
            'morning_checkin_start' => 'When morning shift students can start checking in',
            'morning_checkin_end' => 'Last time morning shift students can check in',
            'morning_checkout_start' => 'When morning shift students can start checking out',
            'morning_checkout_end' => 'Last time morning shift students can check out',
            'morning_class_end' => 'When morning shift class ends',
            'evening_checkin_start' => 'When evening shift students can start checking in',
            'evening_checkin_end' => 'Last time evening shift students can check in',
            'evening_checkout_start' => 'When evening shift students can start checking out',
            'evening_checkout_end' => 'Last time evening shift students can check out',
            'evening_class_end' => 'When evening shift class ends',
            'sync_interval_seconds' => 'How often to sync with web server',
            'timezone' => 'System timezone',
'academic_year_start_month' => 'When the academic year starts',
            'academic_structure_mode' => 'Academic structure mode (year-wise or semester-wise)',
            'semesters_per_year' => 'Number of semesters per academic year',
            'semester_names' => 'JSON array of semester display names',
            'semester_start_months' => 'JSON array of semester start months (1-12) in order',
            'max_program_years' => 'Maximum years for a program before graduation',
            'auto_absent_morning_hour' => 'Hour to mark morning shift absent',
            'auto_absent_evening_hour' => 'Hour to mark evening shift absent',
            'website_url' => 'Base URL of the web application',
            'api_key' => 'API authentication key',
            'debug_mode' => 'Show detailed debug information',
            'log_errors' => 'Log errors to system log',
            'show_errors' => 'Display errors in development mode',
            'session_timeout_seconds' => 'How long sessions remain active',
            'max_login_attempts' => 'Maximum failed login attempts before lockout',
            'login_lockout_minutes' => 'How long to lockout after failed attempts',
            'password_min_length' => 'Minimum required password length',
            'max_sync_records' => 'Maximum records per sync operation',
            'api_rate_limit' => 'Rate limit for API requests',
            'qr_code_size' => 'Size of generated QR codes',
            'qr_code_margin' => 'Margin around QR codes',
            'qr_code_path' => 'Directory to store QR code images',
            'max_file_size_mb' => 'Maximum file size for uploads',
            'allowed_extensions' => 'Comma-separated list of allowed file extensions',
            'smtp_host' => 'SMTP server hostname',
            'smtp_port' => 'SMTP server port',
            'smtp_username' => 'SMTP authentication username',
            'smtp_password' => 'SMTP authentication password',
            'smtp_from_email' => 'Default sender email address',
            'smtp_from_name' => 'Default sender name'
        ];
        
        return $descriptions[$key] ?? 'System setting';
    }
    
    /**
     * Get validation rules for a setting key
     */
    private function valuesEqual($a, $b, $type) {
        try {
            switch ($type) {
                case 'integer':
                case 'number':
                    return (int)$a === (int)$b;
                case 'boolean':
                    $normalize = function($v){ return in_array($v, [true, 'true', 1, '1', 'yes'], true); };
                    return $normalize($a) === $normalize($b);
                case 'json':
                    if (is_string($a)) { $a = json_decode($a, true); }
                    if (is_string($b)) { $b = json_decode($b, true); }
                    $normalizeArray = function($v) use (&$normalizeArray) {
                        if (is_array($v)) {
                            // Sort associative arrays by key for stable compare; leave indexed arrays order as-is
                            if (array_keys($v) !== range(0, count($v)-1)) { ksort($v); }
                            foreach ($v as $k => $vv) { $v[$k] = $normalizeArray($vv); }
                        }
                        return $v;
                    };
                    return json_encode($normalizeArray($a)) === json_encode($normalizeArray($b));
                default:
                    return trim((string)$a) === trim((string)$b);
            }
        } catch (Throwable $e) {
            return false;
        }
    }

    private function getValidationRulesForKey($key) {
        $rules = [
            'morning_checkin_start' => ['required' => true, 'type' => 'time'],
            'morning_checkin_end' => ['required' => true, 'type' => 'time'],
            'morning_checkout_start' => ['required' => true, 'type' => 'time'],
            'morning_checkout_end' => ['required' => true, 'type' => 'time'],
            'morning_class_end' => ['required' => true, 'type' => 'time'],
            'evening_checkin_start' => ['required' => true, 'type' => 'time'],
            'evening_checkin_end' => ['required' => true, 'type' => 'time'],
            'evening_checkout_start' => ['required' => true, 'type' => 'time'],
            'evening_checkout_end' => ['required' => true, 'type' => 'time'],
            'evening_class_end' => ['required' => true, 'type' => 'time'],
            // minimum_duration_minutes removed: no validation/enforcement
            'sync_interval_seconds' => ['required' => true, 'min' => 10, 'max' => 300],
            'timezone' => ['required' => true, 'options' => ['Asia/Karachi', 'UTC', 'America/New_York', 'Europe/London']],
'academic_year_start_month' => ['required' => true, 'min' => 1, 'max' => 12],
            'academic_structure_mode' => ['required' => true, 'options' => ['year','semester']],
            'semesters_per_year' => ['required' => true, 'min' => 1, 'max' => 4, 'type' => 'integer'],
            'semester_names' => ['required' => true, 'type' => 'json'],
            'semester_start_months' => ['required' => true, 'type' => 'json'],
            'max_program_years' => ['required' => true, 'min' => 1, 'max' => 6, 'type' => 'integer'],
            'auto_absent_morning_hour' => ['required' => true, 'min' => 8, 'max' => 16],
            'auto_absent_evening_hour' => ['required' => true, 'min' => 14, 'max' => 20],
            'website_url' => ['required' => true, 'type' => 'url'],
            'api_key' => ['required' => true, 'min_length' => 10],
            'api_timeout_seconds' => ['required' => true, 'min' => 5, 'max' => 120],
            'session_timeout_seconds' => ['required' => true, 'min' => 300, 'max' => 86400],
            'max_login_attempts' => ['required' => true, 'min' => 3, 'max' => 10],
            'login_lockout_minutes' => ['required' => true, 'min' => 5, 'max' => 60],
            'password_min_length' => ['required' => true, 'min' => 6, 'max' => 32],
            'max_sync_records' => ['required' => true, 'min' => 100, 'max' => 10000],
            'api_rate_limit' => ['required' => true, 'min' => 10, 'max' => 1000],
            'qr_code_size' => ['required' => true, 'min' => 100, 'max' => 500],
            'qr_code_margin' => ['required' => true, 'min' => 0, 'max' => 50],
            'max_file_size_mb' => ['required' => true, 'min' => 1, 'max' => 100],
            'smtp_port' => ['required' => true, 'min' => 1, 'max' => 65535],
            'smtp_from_email' => ['required' => true, 'type' => 'email']
        ];
        
        return $rules[$key] ?? [];
    }
    
    /**
     * Convert value based on type
     */
    private function convertValue($value, $type) {
        switch ($type) {
            case 'integer':
                return (int) $value;
            case 'float':
                return (float) $value;
            case 'boolean':
                // Handle string values 'enabled'/'disabled' directly
                if (in_array(strtolower($value), ['enabled', 'disabled'])) {
                    return $value;
                }
                // Handle boolean values
                $boolValue = in_array(strtolower($value), ['true', '1', 'yes']);
                return $boolValue ? 'enabled' : 'disabled';
            case 'time':
            case 'url':
            case 'email':
            case 'json':
                $decoded = json_decode($value, true);
                return $decoded !== null ? $decoded : $value;
            case 'string':
            default:
                return $value;
        }
    }
    
    /**
     * Test website connection
     */
    public function testConnection() {
        try {
            $website_url = $this->getSetting('website_url');
            if (!$website_url['success']) {
                return [
                    'success' => false,
                    'message' => 'Website URL not configured'
                ];
            }
            
            $url = $website_url['data']['value'];
            
            // Test connection with timeout
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'method' => 'HEAD'
                ]
            ]);
            
            $result = @file_get_contents($url, false, $context);
            
            if ($result !== false) {
                return [
                    'success' => true,
                    'message' => 'Connection successful',
                    'data' => [
                        'url' => $url,
                        'response_time' => microtime(true),
                        'status' => 'online'
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Connection failed',
                    'data' => [
                        'url' => $url,
                        'status' => 'offline'
                    ]
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Test API endpoints
     */
    public function testAPI() {
        try {
            $results = [];
            
            // Default endpoints to test if settings are not configured
            $default_endpoints = [
                'attendance' => '/api/api_attendance.php',
                'checkin' => '/api/checkin_api.php',
                'dashboard' => '/api/dashboard_api.php'
            ];
            
            $endpoints = [
                'attendance' => $this->getSetting('api_endpoint_attendance'),
                'checkin' => $this->getSetting('api_endpoint_checkin'),
                'dashboard' => $this->getSetting('api_endpoint_dashboard')
            ];
            
            $base_url = $this->getSetting('website_url');
            $base_url_value = $base_url['success'] ? $base_url['data']['value'] : 'http://localhost/qr_attendance/public';
            
            foreach ($endpoints as $name => $endpoint) {
                // Use configured endpoint or fall back to default
                if ($endpoint['success']) {
                    $endpoint_path = $endpoint['data']['value'];
                } else {
                    $endpoint_path = $default_endpoints[$name];
                }
                
                $url = rtrim($base_url_value, '/') . '/' . ltrim($endpoint_path, '/');
                
                $start_time = microtime(true);
                
                // Test if the endpoint file exists
                $file_path = $_SERVER['DOCUMENT_ROOT'] . '/qr_attendance/public' . $endpoint_path;
                $file_exists = file_exists($file_path);
                
                // Test HTTP response
                $http_works = false;
                if ($file_exists) {
                    $context = stream_context_create([
                        'http' => [
                            'timeout' => 3,
                            'method' => 'GET',
                            'header' => 'Content-Type: application/json',
                            'ignore_errors' => true
                        ]
                    ]);
                    
                    $result = @file_get_contents($url, false, $context);
                    $http_works = $result !== false;
                }
                
                $response_time = round((microtime(true) - $start_time) * 1000, 2);
                
                $results[$name] = [
                    'url' => $url,
                    'file_exists' => $file_exists,
                    'http_works' => $http_works,
                    'status' => $file_exists && $http_works ? 'online' : 'offline',
                    'response_time' => $response_time . 'ms'
                ];
            }
            
            $online_count = 0;
            $total_count = count($results);
            foreach ($results as $result) {
                if ($result['status'] === 'online') {
                    $online_count++;
                }
            }
            
            $all_online = $online_count === $total_count;
            
            return [
                'success' => $all_online,
                'message' => $all_online ? 'All API endpoints are working' : "Only {$online_count}/{$total_count} API endpoints are responding",
                'data' => $results,
                'summary' => [
                    'total' => $total_count,
                    'online' => $online_count,
                    'offline' => $total_count - $online_count
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'API test failed: ' . $e->getMessage(),
                'data' => []
            ];
        }
    }
    
    /**
     * Get a specific setting
     */
    public function getSetting($key) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT setting_key, setting_value, setting_type, category, description, 
                       validation_rules, last_updated, updated_by
                FROM system_settings 
                WHERE setting_key = ?
            ");
            $stmt->execute([$key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$row) {
                return [
                    'success' => false,
                    'message' => 'Setting not found'
                ];
            }
            
            $value = $this->convertValue($row['setting_value'], $row['setting_type']);
            
            return [
                'success' => true,
                'data' => [
                    'key' => $row['setting_key'],
                    'value' => $value,
                    'type' => $row['setting_type'],
                    'category' => $row['category'],
                    'description' => $row['description'],
                    'validation_rules' => json_decode($row['validation_rules'], true),
                    'last_updated' => $row['last_updated'],
                    'updated_by' => $row['updated_by']
                ]
            ];
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Update a single setting
     */
    public function updateSetting($key, $value, $updated_by = 'admin') {
        try {
            // Get current setting info
            $current = $this->getSetting($key);

            // Guard: prevent academic structure edits if students exist (only if value actually changes)
            if ($this->isAcademicStructureKey($key) && $this->hasRegisteredStudents()) {
                $setting_info = $current['success'] ? $current['data'] : null;
                $current_value = $setting_info ? $setting_info['value'] : null;
                $current_type = $setting_info ? $setting_info['type'] : $this->getTypeForValue($value);
                if ($this->valuesEqual($current_value, $value, $current_type)) {
                    return [
                        'success' => true,
                        'message' => 'No change to academic structure (updates skipped due to existing students)',
                        'data' => ['key' => $key, 'value' => $current_value]
                    ];
                }
                return [
                    'success' => false,
                    'message' => 'Academic structure cannot be modified because students already exist. Remove all students first to change structure.'
                ];
            }
            
            if (!$current['success']) {
                // Setting doesn't exist, create it
                return $this->createSetting($key, $value, $updated_by);
            }
            
            $setting_info = $current['data'];

            // Normalize JSON inputs for academic fields
            if (in_array($key, ['semester_names', 'semester_start_months'], true)) {
                if (is_string($value)) {
                    $trim = trim($value);
                    // Allow simple CSV as fallback (e.g., Fall,Spring or 9,2)
                    if ($trim !== '' && $trim[0] !== '[' && $trim[strlen($trim)-1] !== ']') {
                        $parts = array_map('trim', explode(',', $trim));
                        $value = ($key === 'semester_start_months') ? array_map('intval', $parts) : $parts;
                    } else {
                        $decoded = json_decode($trim, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $value = $decoded;
                        }
                    }
                }
                // Basic sanitize
                if ($key === 'semester_start_months' && is_array($value)) {
                    $value = array_values(array_filter(array_map(function($m){
                        $m = (int)$m; return ($m>=1 && $m<=12)?$m:null;
                    }, $value), function($v){ return $v !== null; }));
                }
            }
            
            // Validate the value
            $validation = $this->validateSetting($key, $value, $setting_info['validation_rules']);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validation['errors']
                ];
            }
            
            // Convert value to string for storage
            $string_value = $this->convertToString($value, $setting_info['type']);
            
            // Update the setting
            $stmt = $this->pdo->prepare("
                UPDATE system_settings 
                SET setting_value = ?, last_updated = CURRENT_TIMESTAMP, updated_by = ?
                WHERE setting_key = ?
            ");
            $result = $stmt->execute([$string_value, $updated_by, $key]);
            
            if ($result) {
                // Settings updated in database
                
                return [
                    'success' => true,
                    'message' => 'Setting updated successfully',
                    'data' => [
                        'key' => $key,
                        'value' => $value,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to update setting'
                ];
            }
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Create a new setting
     */
    private function createSetting($key, $value, $updated_by = 'admin') {
        try {
            $category = $this->getCategoryForKey($key);
            $type = $this->getTypeForValue($value);
            $description = $this->getDescriptionForKey($key);
            $validation = $this->getValidationRulesForKey($key);
            
            $stmt = $this->pdo->prepare("
                INSERT INTO system_settings 
                (setting_key, setting_value, setting_type, category, description, validation_rules, updated_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $key,
                (string) $value,
                $type,
                $category,
                $description,
                json_encode($validation),
                $updated_by
            ]);
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Setting created successfully',
                    'data' => [
                        'key' => $key,
                        'value' => $value,
                        'created_at' => date('Y-m-d H:i:s')
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to create setting'
                ];
            }
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Update multiple settings
     */
    public function bulkUpdateSettings($settings, $updated_by = 'admin') {
        try {
            error_log("Bulk update started with " . count($settings) . " settings");

            $studentsExist = $this->hasRegisteredStudents();
            $allowed = [];
            $skipped = [];
            foreach ($settings as $s) {
                $key = $s['key'] ?? '';
                $val = $s['value'] ?? null;
                if ($studentsExist && $this->isAcademicStructureKey($key)) {
                    $curr = $this->getSetting($key);
                    if ($curr['success']) {
                        $info = $curr['data'];
                        if ($this->valuesEqual($info['value'], $val, $info['type'])) {
                            // No change; skip silently but record
                            $skipped[] = [ 'key' => $key, 'reason' => 'no-change (students exist)' ];
                            continue; // nothing to update
                        } else {
                            // Change requested but students exist -> block this key
                            $skipped[] = [ 'key' => $key, 'reason' => 'blocked (students exist)' ];
                            continue;
                        }
                    } else {
                        // If cannot fetch current, be conservative and block
                        $skipped[] = [ 'key' => $key, 'reason' => 'blocked (cannot read current value)' ];
                        continue;
                    }
                }
                $allowed[] = $s;
            }
            error_log("Settings data: " . print_r($settings, true));
            
            $this->pdo->beginTransaction();
            
            $results = [];
            $errors = [];
            
            foreach ($allowed as $setting) {
                $key = $setting['key'];
                $value = $setting['value'];
                
                error_log("Processing setting: {$key} = " . print_r($value, true));
                
                // Special handling for AM/PM period settings
                if (strpos($key, '_period') !== false) {
                    error_log("Processing AM/PM period setting: {$key} = {$value}");
                }
                
                $result = $this->updateSetting($key, $value, $updated_by);
                $results[] = $result;
                
                if (!$result['success']) {
                    $errors[] = "Setting '{$key}': " . $result['message'];
                    error_log("Failed to update setting '{$key}': " . $result['message']);
                } else {
                    error_log("Successfully updated setting '{$key}'");
                }
            }
            
            if (empty($errors)) {
                $this->pdo->commit();
                
                $msg = 'Settings updated successfully';
                if (!empty($skipped)) {
                    $msg .= ' (some academic structure changes were skipped)';
                }
                return [
                    'success' => true,
                    'message' => $msg,
                    'updated_count' => count($allowed),
                    'skipped' => $skipped
                ];
            } else {
                $this->pdo->rollBack();
                return [
                    'success' => false,
                    'message' => 'Some settings failed to update',
                    'errors' => $errors,
                    'failed_count' => count($errors),
                    'total_count' => count($settings)
                ];
            }
            
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Bulk update database error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Validate timing settings
     */
    public function validateTimings($timings) {
        $errors = [];
        $warnings = [];
        
        // Debug: Log the received timings
        error_log("Received timings: " . print_r($timings, true));
        
        try {
            // Helper function to parse time with flexible format and proper validation
            $parseTime = function($time, $fieldName) {
                if (empty($time)) {
                    throw new Exception("Time value is required for {$fieldName}");
                }
                
                // Try different time formats
                $formats = ['H:i:s', 'H:i', 'g:i A', 'g:i a'];
                foreach ($formats as $format) {
                    $dt = DateTime::createFromFormat($format, $time);
                    if ($dt !== false) {
                        // Validate that the parsing was successful without errors
                        $errors = DateTime::getLastErrors();
                        if ($errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
                            continue; // Try next format
                        }
                        return $dt;
                    }
                }
                
                // If no format worked, throw exception
                throw new Exception("Invalid time format for {$fieldName}: {$time}. Expected formats: HH:MM, HH:MM:SS, or h:mm AM/PM");
            };
            
            // Parse morning timings with validation
            // All timing values are required - no defaults
            if (empty($timings['morning_checkin_start']) || empty($timings['morning_checkin_end']) || 
                empty($timings['morning_class_end']) || empty($timings['evening_checkin_start']) || 
                empty($timings['evening_checkin_end']) || empty($timings['evening_class_end'])) {
                $errors[] = "All timing settings are required. Please configure all check-in, check-out, and class end times.";
                return ['valid' => false, 'errors' => $errors, 'warnings' => $warnings];
            }
            
            $morning_start = $parseTime($timings['morning_checkin_start'], 'morning_checkin_start');
            $morning_end = $parseTime($timings['morning_checkin_end'], 'morning_checkin_end');
            $morning_checkout_start = $parseTime($timings['morning_checkout_start'] ?? $timings['morning_checkin_end'], 'morning_checkout_start');
            $morning_checkout_end = $parseTime($timings['morning_checkout_end'] ?? $timings['morning_class_end'], 'morning_checkout_end');
            $morning_class_end = $parseTime($timings['morning_class_end'], 'morning_class_end');
            
            // Parse evening timings with validation
            $evening_start = $parseTime($timings['evening_checkin_start'], 'evening_checkin_start');
            $evening_end = $parseTime($timings['evening_checkin_end'], 'evening_checkin_end');
            $evening_checkout_start = $parseTime($timings['evening_checkout_start'] ?? $timings['evening_checkin_start'], 'evening_checkout_start');
            $evening_checkout_end = $parseTime($timings['evening_checkout_end'] ?? $timings['evening_class_end'], 'evening_checkout_end');
            $evening_class_end = $parseTime($timings['evening_class_end'], 'evening_class_end');
            
            // Validate morning shift logic
            if ($morning_end <= $morning_start) {
                $errors[] = "Morning check-in end must be after check-in start";
            }
            
            if ($morning_checkout_start < $morning_start) {
                $errors[] = "Morning check-out start must be during or after check-in start";
            }
            
            if ($morning_checkout_end <= $morning_checkout_start) {
                $errors[] = "Morning check-out end must be after check-out start";
            }
            
            if ($morning_class_end <= $morning_start) {
                $errors[] = "Morning class end must be after check-in start";
            }
            
            // Validate evening shift
            if ($evening_end <= $evening_start) {
                $errors[] = "Evening check-in end must be after check-in start";
            }
            
            if ($evening_checkout_start < $evening_start) {
                $errors[] = "Evening check-out start must be during or after check-in start";
            }
            
            if ($evening_checkout_end <= $evening_checkout_start) {
                $errors[] = "Evening check-out end must be after check-out start";
            }
            
            if ($evening_class_end <= $evening_start) {
                $errors[] = "Evening class end must be after check-in start";
            }
            
            // Check for overlapping shifts
            if ($morning_class_end > $evening_start) {
                $warnings[] = "Morning class end overlaps with evening shift start";
            }
            
            // Check for reasonable time windows
            try {
                $morning_duration = $morning_end->diff($morning_start)->h;
                if ($morning_duration < 0.5) {
                    $warnings[] = "Morning check-in window is very short";
                }
            } catch (Exception $e) {
                $warnings[] = "Could not calculate morning duration";
            }
            
            try {
                $evening_duration = $evening_end->diff($evening_start)->h;
                if ($evening_duration < 0.5) {
                    $warnings[] = "Evening check-in window is very short";
                }
            } catch (Exception $e) {
                $warnings[] = "Could not calculate evening duration";
            }
            
            return [
                'success' => empty($errors),
                'valid' => empty($errors),
                'errors' => $errors,
                'warnings' => $warnings,
                'message' => empty($errors) ? 'Timing configuration is valid' : 'Validation failed'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'valid' => false,
                'errors' => ["Invalid time format: " . $e->getMessage()],
                'warnings' => [],
                'message' => 'Validation error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Import settings from JSON
     */
    public function importSettings($data) {
        try {
            if (!isset($data['data']) || !is_array($data['data'])) {
                return [
                    'success' => false,
                    'message' => 'Invalid import data format'
                ];
            }
            
            $imported = 0;
            $errors = [];
            
            foreach ($data['data'] as $category => $settings) {
                if (!is_array($settings)) continue;
                
                foreach ($settings as $setting) {
                    if (!isset($setting['key']) || !isset($setting['value'])) continue;
                    
                    $result = $this->updateSetting($setting['key'], $setting['value'], 'admin');
                    if ($result['success']) {
                        $imported++;
                    } else {
                        $errors[] = "Failed to import {$setting['key']}: " . $result['message'];
                    }
                }
            }
            
            return [
                'success' => true,
                'message' => "Imported {$imported} settings successfully",
                'imported_count' => $imported,
                'errors' => $errors
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Export all settings as JSON
     */
    public function exportSettings() {
        try {
            $settings = $this->getAllSettings();
            if ($settings['success']) {
                return [
                    'success' => true,
                    'message' => 'Settings exported successfully',
                    'data' => $settings['data'],
                    'exported_at' => date('Y-m-d H:i:s'),
                    'total_settings' => array_sum(array_map('count', $settings['data']))
                ];
            } else {
                return $settings;
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Export failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Convert value to string for storage
     */
    private function convertToString($value, $type) {
        switch ($type) {
            case 'boolean':
                // For module settings, convert to 'enabled'/'disabled'
                if ($value === 'enabled' || $value === 'disabled') {
                    return $value;
                }
                return $value ? 'enabled' : 'disabled';
            case 'integer':
                return (string) $value;
            case 'json':
                if (is_string($value)) {
                    // Validate JSON string before storing
                    json_decode($value, true);
                    if (json_last_error() === JSON_ERROR_NONE) return $value;
                    // If invalid, try to coerce
                    $value = @json_decode($value, true);
                }
                return json_encode($value, JSON_UNESCAPED_UNICODE);
            default:
                return (string) $value;
        }
    }
    
    /**
     * Validate a single setting
     */
    private function validateSetting($key, $value, $validation_rules) {
        $errors = [];
        
        error_log("Validating setting '{$key}' with value: " . print_r($value, true) . " and rules: " . print_r($validation_rules, true));
        
        if (empty($validation_rules)) {
            return ['valid' => true, 'errors' => []];
        }
        
        // Type validation first
        if (isset($validation_rules['type'])) {
            switch ($validation_rules['type']) {
                case 'integer':
                case 'number':
                    if (!is_numeric($value)) {
                        $errors[] = "Value must be numeric (got: " . gettype($value) . ")";
                        // If not numeric, skip other validations
                        return ['valid' => false, 'errors' => $errors];
                    }
                    break;
                case 'boolean':
                    if (!in_array($value, [true, false, 'true', 'false', '1', '0', 1, 0], true)) {
                        $errors[] = "Value must be boolean (true/false)";
                        return ['valid' => false, 'errors' => $errors];
                    }
                    break;
                case 'email':
                    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $errors[] = "Invalid email format";
                        return ['valid' => false, 'errors' => $errors];
                    }
                    break;
                case 'url':
                    if (!filter_var($value, FILTER_VALIDATE_URL)) {
                        $errors[] = "Invalid URL format";
                        return ['valid' => false, 'errors' => $errors];
                    }
                    break;
                case 'json':
                    if (is_string($value)) {
                        $decoded = json_decode($value, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $value = $decoded; // proceed with decoded value
                        } else {
                            $errors[] = 'Invalid JSON';
                            return ['valid' => false, 'errors' => $errors];
                        }
                    }
                    if (!is_array($value)) {
                        $errors[] = 'Value must be JSON array/object';
                        return ['valid' => false, 'errors' => $errors];
                    }
                    // Extra semantic checks for our fields
                    if ($key === 'semester_names') {
                        // Ensure array of strings
                        foreach ($value as $n) {
                            if (!is_string($n) || $n === '') { $errors[] = 'Semester names must be non-empty strings'; break; }
                        }
                    }
                    if ($key === 'semester_start_months') {
                        foreach ($value as $m) {
                            if (!is_numeric($m) || $m < 1 || $m > 12) { $errors[] = 'Semester months must be integers 1-12'; break; }
                        }
                    }
                    if (!empty($errors)) { return ['valid' => false, 'errors' => $errors]; }
                    break;
                case 'time':
                    // Validate time format (HH:MM or HH:MM:SS)
                    if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $value)) {
                        $errors[] = "Invalid time format. Expected HH:MM or HH:MM:SS";
                        return ['valid' => false, 'errors' => $errors];
                    }
                    break;
            }
        }
        
        // Check required
        if (isset($validation_rules['required']) && $validation_rules['required']) {
            if ($value === '' || $value === null) {
                $errors[] = "This setting is required";
            }
        }
        
        // Check minimum length (for strings)
        if (isset($validation_rules['min_length']) && is_string($value)) {
            if (strlen($value) < $validation_rules['min_length']) {
                $errors[] = "Minimum length is " . $validation_rules['min_length'] . " characters";
            }
        }
        
        // Check maximum length (for strings)
        if (isset($validation_rules['max_length']) && is_string($value)) {
            if (strlen($value) > $validation_rules['max_length']) {
                $errors[] = "Maximum length is " . $validation_rules['max_length'] . " characters";
            }
        }
        
        // Check numeric ranges
        $is_numeric = is_numeric($value) || (is_string($value) && is_numeric(trim($value)));
        error_log("Value '{$value}' is numeric: " . ($is_numeric ? 'yes' : 'no'));
        
        if ($is_numeric) {
            $numeric_value = (float) $value;
            error_log("Numeric value: {$numeric_value}");
            
            if (isset($validation_rules['min'])) {
                error_log("Checking min: {$numeric_value} < {$validation_rules['min']} = " . ($numeric_value < $validation_rules['min'] ? 'true' : 'false'));
                if ($numeric_value < $validation_rules['min']) {
                    $errors[] = "Minimum value is " . $validation_rules['min'] . " (got " . $numeric_value . ")";
                }
            }
            if (isset($validation_rules['max'])) {
                error_log("Checking max: {$numeric_value} > {$validation_rules['max']} = " . ($numeric_value > $validation_rules['max'] ? 'true' : 'false'));
                if ($numeric_value > $validation_rules['max']) {
                    $errors[] = "Maximum value is " . $validation_rules['max'] . " (got " . $numeric_value . ")";
                }
            }
        } else {
            // If it's not numeric but has min/max rules, it's an error
            if (isset($validation_rules['min']) || isset($validation_rules['max'])) {
                $errors[] = "Value must be numeric for min/max validation (got: " . var_export($value, true) . ")";
            }
        }
        
        // Check options (whitelist)
        if (isset($validation_rules['options']) && is_array($validation_rules['options'])) {
            if (!in_array($value, $validation_rules['options'], true)) {
                $errors[] = "Value must be one of: " . implode(', ', $validation_rules['options']);
            }
        }
        
        // Check regex pattern
        if (isset($validation_rules['pattern']) && is_string($value)) {
            if (!preg_match($validation_rules['pattern'], $value)) {
                $errors[] = "Value does not match required pattern";
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Legacy sync functions removed - settings are now stored in database only
     */
    
    /**
     * Upload logo and save to settings
     */
    public function uploadLogo($file) {
        try {
            if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
                return ['success' => false, 'message' => 'No file uploaded'];
            }
            
            // Validate file type
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            if (!in_array($file['type'], $allowedTypes)) {
                return ['success' => false, 'message' => 'Invalid file type. Only JPEG, PNG, and GIF are allowed.'];
            }
            
            // Validate file size (2MB max)
            if ($file['size'] > 2 * 1024 * 1024) {
                return ['success' => false, 'message' => 'File size must be less than 2MB'];
            }
            
            // Create upload directory if it doesn't exist
            // From public/admin/api/, go up two levels to public/
            $uploadDir = '../../uploads/logos/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'logo_' . time() . '.' . $extension;
            $filepath = $uploadDir . $filename;
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                return ['success' => false, 'message' => 'Failed to save file'];
            }
            
            // Verify file was saved
            if (!file_exists($filepath)) {
                error_log("File was not saved to: {$filepath}");
                return ['success' => false, 'message' => 'File was not saved successfully'];
            }
            
            // Save to settings
            $logoPath = 'uploads/logos/' . $filename;
            $stmt = $this->pdo->prepare("
                INSERT INTO system_settings (setting_key, setting_value, setting_type) 
                VALUES ('sidebar_logo', ?, 'string')
                ON DUPLICATE KEY UPDATE setting_value = ?
            ");
            $stmt->execute([$logoPath, $logoPath]);
            
            // Generate favicons from the uploaded logo
            if (function_exists('generateFaviconFromLogo') && extension_loaded('gd')) {
                $fullLogoPath = '../../uploads/logos/' . $filename;
                generateFaviconFromLogo($fullLogoPath);
            }
            
            return [
                'success' => true,
                'logo_url' => '../uploads/logos/' . $filename,
                'message' => 'Logo uploaded successfully'
            ];
            
        } catch (Exception $e) {
            error_log("Logo upload error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Update general settings (project name, tagline, etc.)
     */
    public function updateGeneralSettings($input) {
        try {
            $settings = [
                'project_name' => $input['project_name'] ?? '',
                'project_short_name' => $input['project_short_name'] ?? '',
                'project_tagline' => $input['project_tagline'] ?? ''
            ];
            
            $stmt = $this->pdo->prepare("
                INSERT INTO system_settings (setting_key, setting_value, setting_type) 
                VALUES (?, ?, 'string')
                ON DUPLICATE KEY UPDATE setting_value = ?
            ");
            
            foreach ($settings as $key => $value) {
                $stmt->execute([$key, $value, $value]);
            }
            
            return [
                'success' => true,
                'message' => 'General settings updated successfully'
            ];
            
        } catch (Exception $e) {
            error_log("General settings update error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}

// Handle API requests
if (!isAdminLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    $api = new AdminSettingsAPI();
    
    // Debug logging
    error_log("Settings API called with action: " . $action);
    
    switch ($action) {
        case 'get_all':
            $result = $api->getAllSettings();
            error_log("Settings API result: " . json_encode($result));
            break;
            
        case 'test_db':
            // Test database connection
            try {
                if (!$api->pdo) {
                    $result = [
                        'success' => false,
                        'message' => 'Database connection not available'
                    ];
                } else {
                    $stmt = $api->pdo->query("SHOW TABLES LIKE 'system_settings'");
                    $tableExists = $stmt->fetch();
                    $result = [
                        'success' => true,
                        'message' => $tableExists ? 'Table exists' : 'Table does not exist',
                        'data' => ['table_exists' => (bool)$tableExists]
                    ];
                }
            } catch (Exception $e) {
                $result = [
                    'success' => false,
                    'message' => 'Database test failed: ' . $e->getMessage()
                ];
            }
            break;
            
        case 'test_connection':
            $result = $api->testConnection();
            break;
            
        case 'test_api':
            $result = $api->testAPI();
            break;
            
        case 'export':
            $result = $api->exportSettings();
            break;
        
        case 'get':
            $key = $_GET['key'] ?? '';
            if (!$key) {
                $result = ['success' => false, 'message' => 'Key required'];
            } else {
                $res = $api->getSetting($key);
                if ($res['success']) {
                    $result = ['success' => true, 'key' => $key, 'value' => $res['data']['value']];
                } else {
                    $result = $res;
                }
            }
            break;
            
        default:
            $result = ['success' => false, 'message' => 'Invalid action'];
    }
    
    header('Content-Type: application/json');
    echo json_encode($result);
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if this is a file upload
    if (isset($_FILES['logo']) && isset($_POST['action']) && $_POST['action'] === 'upload_logo') {
        $api = new AdminSettingsAPI();
        $result = $api->uploadLogo($_FILES['logo']);
        header('Content-Type: application/json');
        echo json_encode($result);
        exit();
    }
    
    // Handle JSON requests
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    $api = new AdminSettingsAPI();
    
    switch ($action) {
        case 'bulk_update':
            $result = $api->bulkUpdateSettings($input['settings'] ?? [], $input['updated_by'] ?? 'admin');
            break;
            
        case 'update':
            $result = $api->updateSetting($input['key'] ?? '', $input['value'] ?? '', $input['updated_by'] ?? 'admin');
            break;
            
        case 'import':
            $result = $api->importSettings($input);
            break;
            
        case 'validate_timings':
            $result = $api->validateTimings($input['timings'] ?? []);
            break;
            
        case 'update_general_settings':
            $result = $api->updateGeneralSettings($input);
            break;
            
        default:
            $result = ['success' => false, 'message' => 'Invalid action'];
    }
    
    header('Content-Type: application/json');
    echo json_encode($result);
    
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>
