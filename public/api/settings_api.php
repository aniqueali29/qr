<?php
/**
 * Settings API for QR Code Attendance System
 * Handles CRUD operations for system settings with validation
 */

// Suppress warnings and errors for clean JSON output
error_reporting(0);
ini_set('display_errors', 0);

require_once 'config.php';

class SettingsAPI {
    private $pdo;
    private $api_key = 'attendance_2025_secure_key_3e13bd5acfdf332ecece2d60aa29db78';
    
    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }
    
    /**
     * Check API key authentication
     */
    private function checkApiKey() {
        $headers = getallheaders();
        $api_key = $headers['X-API-Key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';
        
        if ($api_key !== $this->api_key) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Invalid API key']);
            return false;
        }
        return true;
    }
    
    /**
     * Get all settings grouped by category
     */
    public function getAllSettings() {
        // Check API key for external requests
        if (!$this->checkApiKey()) {
            return;
        }
        
        try {
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
            return [
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
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
            if (!$current['success']) {
                return $current;
            }
            
            $setting_info = $current['data'];
            
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
     * Update multiple settings
     */
    public function bulkUpdateSettings($settings, $updated_by = 'admin') {
        try {
            $this->pdo->beginTransaction();
            
            $results = [];
            $errors = [];
            
            foreach ($settings as $setting) {
                $key = $setting['key'];
                $value = $setting['value'];
                
                $result = $this->updateSetting($key, $value, $updated_by);
                $results[] = $result;
                
                if (!$result['success']) {
                    $errors[] = $result['message'];
                }
            }
            
            if (empty($errors)) {
                $this->pdo->commit();
                return [
                    'success' => true,
                    'message' => 'All settings updated successfully',
                    'updated_count' => count($settings)
                ];
            } else {
                $this->pdo->rollBack();
                return [
                    'success' => false,
                    'message' => 'Some settings failed to update',
                    'errors' => $errors
                ];
            }
            
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return [
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Reset a setting to default
     */
    public function resetSetting($key) {
        try {
            // Get default value from hardcoded defaults
            $defaults = $this->getDefaultSettings();
            
            if (!isset($defaults[$key])) {
                return [
                    'success' => false,
                    'message' => 'No default value found for this setting'
                ];
            }
            
            $default_value = $defaults[$key];
            return $this->updateSetting($key, $default_value, 'system_reset');
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error resetting setting: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Parse time string with support for both 12-hour and 24-hour formats
     */
    private function parseTime($time_str) {
        // Try 12-hour format with AM/PM first
        $time = DateTime::createFromFormat('g:i A', $time_str);
        if ($time !== false) {
            return $time;
        }
        
        // Try 12-hour format with AM/PM and seconds
        $time = DateTime::createFromFormat('g:i:s A', $time_str);
        if ($time !== false) {
            return $time;
        }
        
        // Try 24-hour format HH:MM:SS
        $time = DateTime::createFromFormat('H:i:s', $time_str);
        if ($time !== false) {
            return $time;
        }
        
        // Try 24-hour format HH:MM
        $time = DateTime::createFromFormat('H:i', $time_str);
        if ($time !== false) {
            return $time;
        }
        
        // If all formats fail, throw exception
        throw new Exception("Invalid time format: {$time_str}. Expected formats: HH:MM, HH:MM:SS, or h:mm AM/PM");
    }

    /**
     * Validate timing settings
     */
    public function validateTimings($timings) {
        $errors = [];
        $warnings = [];
        
        try {
            // Parse morning timings with flexible format support
            // All timing values are required - no defaults
            if (empty($timings['morning_checkin_start']) || empty($timings['morning_checkin_end']) || 
                empty($timings['morning_class_end']) || empty($timings['evening_checkin_start']) || 
                empty($timings['evening_checkin_end']) || empty($timings['evening_class_end'])) {
                $errors[] = "All timing settings are required. Please configure all check-in, check-out, and class end times.";
                return ['valid' => false, 'errors' => $errors, 'warnings' => $warnings];
            }
            
            $morning_start = $this->parseTime($timings['morning_checkin_start']);
            $morning_end = $this->parseTime($timings['morning_checkin_end']);
            $morning_checkout_start = $this->parseTime($timings['morning_checkout_start'] ?? $timings['morning_checkin_end']);
            $morning_checkout_end = $this->parseTime($timings['morning_checkout_end'] ?? $timings['morning_class_end']);
            $morning_class_end = $this->parseTime($timings['morning_class_end']);
            
            // Parse evening timings with flexible format support
            $evening_start = $this->parseTime($timings['evening_checkin_start']);
            $evening_end = $this->parseTime($timings['evening_checkin_end']);
            $evening_checkout_start = $this->parseTime($timings['evening_checkout_start'] ?? $timings['evening_checkin_start']);
            $evening_checkout_end = $this->parseTime($timings['evening_checkout_end'] ?? $timings['evening_class_end']);
            $evening_class_end = $this->parseTime($timings['evening_class_end']);
            
            // Validate morning shift
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
            $morning_duration = $morning_end->diff($morning_start)->h;
            if ($morning_duration < 0.5) {
                $warnings[] = "Morning check-in window is very short";
            }
            
            $evening_duration = $evening_end->diff($evening_start)->h;
            if ($evening_duration < 0.5) {
                $warnings[] = "Evening check-in window is very short";
            }
            
            return [
                'valid' => empty($errors),
                'errors' => $errors,
                'warnings' => $warnings
            ];
            
        } catch (Exception $e) {
            return [
                'valid' => false,
                'errors' => ["Invalid time format: " . $e->getMessage()],
                'warnings' => []
            ];
        }
    }
    
    /**
     * Convert value based on type
     */
    private function convertValue($value, $type) {
        switch ($type) {
            case 'integer':
                return (int) $value;
            case 'boolean':
                return in_array(strtolower($value), ['true', '1', 'yes']);
            case 'time':
                return $value;
            case 'url':
            case 'email':
            case 'string':
            default:
                return $value;
        }
    }
    
    /**
     * Convert value to string for storage
     */
    private function convertToString($value, $type) {
        switch ($type) {
            case 'boolean':
                return $value ? 'true' : 'false';
            case 'integer':
                return (string) $value;
            default:
                return (string) $value;
        }
    }
    
    /**
     * Validate a single setting
     */
    private function validateSetting($key, $value, $validation_rules) {
        $errors = [];
        
        if (empty($validation_rules)) {
            return ['valid' => true, 'errors' => []];
        }
        
        // Check required
        if (isset($validation_rules['required']) && $validation_rules['required'] && empty($value)) {
            $errors[] = "This setting is required";
        }
        
        // Check minimum length
        if (isset($validation_rules['min_length']) && strlen($value) < $validation_rules['min_length']) {
            $errors[] = "Minimum length is " . $validation_rules['min_length'];
        }
        
        // Check numeric ranges
        if (is_numeric($value)) {
            if (isset($validation_rules['min']) && $value < $validation_rules['min']) {
                $errors[] = "Minimum value is " . $validation_rules['min'];
            }
            if (isset($validation_rules['max']) && $value > $validation_rules['max']) {
                $errors[] = "Maximum value is " . $validation_rules['max'];
            }
        }
        
        // Check options
        if (isset($validation_rules['options']) && !in_array($value, $validation_rules['options'])) {
            $errors[] = "Value must be one of: " . implode(', ', $validation_rules['options']);
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
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
            $endpoints = [
                'attendance' => $this->getSetting('api_endpoint_attendance'),
                'checkin' => $this->getSetting('api_endpoint_checkin'),
                'dashboard' => $this->getSetting('api_endpoint_dashboard')
            ];
            
            foreach ($endpoints as $name => $endpoint) {
                if ($endpoint['success']) {
                    $url = $this->getSetting('website_url')['data']['value'] . $endpoint['data']['value'];
                    
                    $context = stream_context_create([
                        'http' => [
                            'timeout' => 5,
                            'method' => 'GET',
                            'header' => 'Content-Type: application/json'
                        ]
                    ]);
                    
                    $result = @file_get_contents($url, false, $context);
                    $results[$name] = [
                        'url' => $url,
                        'status' => $result !== false ? 'online' : 'offline',
                        'response_time' => microtime(true)
                    ];
                }
            }
            
            $all_online = array_reduce($results, function($carry, $result) {
                return $carry && $result['status'] === 'online';
            }, true);
            
            return [
                'success' => $all_online,
                'message' => $all_online ? 'All API endpoints are working' : 'Some API endpoints are not responding',
                'data' => $results
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'API test failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get settings by category
     */
    public function getSettingsByCategory() {
        try {
            $categories = [
                'shift_timings' => [
                    'morning_checkin_start', 'morning_checkin_end', 'morning_checkout_start',
                    'morning_checkout_end', 'morning_class_end', 'evening_checkin_start',
                    'evening_checkin_end', 'evening_checkout_start', 'evening_checkout_end',
                    'evening_class_end'
                ],
                'system_config' => [
                    'sync_interval_seconds', 'timezone',
                    'academic_year_start_month', 'auto_absent_morning_hour', 'auto_absent_evening_hour'
                ],
                'integration' => [
                    'website_url', 'api_endpoint_attendance', 'api_endpoint_checkin',
                    'api_endpoint_dashboard', 'api_key', 'api_timeout_seconds'
                ],
                'advanced' => [
                    'debug_mode', 'log_errors', 'show_errors', 'session_timeout_seconds',
                    'max_login_attempts', 'login_lockout_minutes', 'password_min_length',
                    'max_sync_records', 'api_rate_limit'
                ],
                'qr_code' => [
                    'qr_code_size', 'qr_code_margin', 'qr_code_path'
                ],
                'file_upload' => [
                    'max_file_size_mb', 'allowed_extensions'
                ],
                'email' => [
                    'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password',
                    'smtp_from_email', 'smtp_from_name'
                ]
            ];
            
            $result = [];
            foreach ($categories as $category => $keys) {
                $result[$category] = [];
                foreach ($keys as $key) {
                    $setting = $this->getSetting($key);
                    if ($setting['success']) {
                        $result[$category][] = $setting['data'];
                    }
                }
            }
            
            return [
                'success' => true,
                'data' => $result,
                'total_categories' => count($categories)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error getting settings by category: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get sync status
     */
    public function getSyncStatus() {
        try {
            $last_sync = $this->pdo->query("
                SELECT MAX(last_updated) as last_sync 
                FROM system_settings 
                WHERE updated_by = 'web_sync'
            ")->fetchColumn();
            
            $total_settings = $this->pdo->query("
                SELECT COUNT(*) FROM system_settings
            ")->fetchColumn();
            
            $web_synced = $this->pdo->query("
                SELECT COUNT(*) FROM system_settings 
                WHERE updated_by = 'web_sync'
            ")->fetchColumn();
            
            return [
                'success' => true,
                'data' => [
                    'last_sync' => $last_sync,
                    'total_settings' => $total_settings,
                    'web_synced' => $web_synced,
                    'sync_percentage' => $total_settings > 0 ? round(($web_synced / $total_settings) * 100, 2) : 0,
                    'status' => $last_sync ? 'synced' : 'never_synced'
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error getting sync status: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get default settings - NO HARDCODED TIMING VALUES
     * All timing settings must be configured by admin through settings interface
     */
    private function getDefaultSettings() {
        return [
            // System Configuration (non-timing settings only)
            'sync_interval_seconds' => 30,
            'timezone' => 'Asia/Karachi',
            'academic_year_start_month' => 9,
            
            // Integration Settings
            'website_url' => 'http://localhost/qr_attendance/public',
            'api_endpoint_attendance' => '/api/api_attendance.php',
            'api_endpoint_checkin' => '/api/checkin_api.php',
            'api_endpoint_dashboard' => '/api/dashboard_api.php',
            'api_key' => 'attendance_2025_secure_key_3e13bd5acfdf332ecece2d60aa29db78',
            'api_timeout_seconds' => 30,
            
            // Advanced Settings
            'debug_mode' => true,
            'log_errors' => true,
            'show_errors' => true,
            'session_timeout_seconds' => 3600,
            'max_login_attempts' => 5,
            'login_lockout_minutes' => 15,
            'password_min_length' => 8,
            'max_sync_records' => 1000,
            'api_rate_limit' => 100,
            
            // QR Code Settings
            'qr_code_size' => 200,
            'qr_code_margin' => 10,
            'qr_code_path' => 'assets/img/qr_codes/',
            
            // File Upload Settings
            'max_file_size_mb' => 5,
            'allowed_extensions' => 'csv,json,xlsx',
            
            // Email Settings
            'smtp_host' => 'smtp.gmail.com',
            'smtp_port' => 587,
            'smtp_username' => '',
            'smtp_password' => '',
            'smtp_from_email' => 'noreply@example.com',
            'smtp_from_name' => 'QR Attendance System'
        ];
    }
}

// Handle API requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    $api = new SettingsAPI();
    
    switch ($action) {
        case 'get_all':
            $result = $api->getAllSettings();
            break;
            
        case 'get':
            $key = $_GET['key'] ?? '';
            if (empty($key)) {
                $result = ['success' => false, 'message' => 'Key parameter required'];
            } else {
                $result = $api->getSetting($key);
            }
            break;
            
        case 'validate_timings':
            $timings = $_GET['timings'] ?? [];
            $validation = $api->validateTimings($timings);
            $result = [
                'success' => $validation['valid'],
                'message' => $validation['valid'] ? 'Timings are valid' : 'Timing validation failed',
                'data' => $validation
            ];
            break;
            
        case 'export':
            $result = $api->exportSettings();
            break;
            
        case 'test_connection':
            $result = $api->testConnection();
            break;
            
        case 'test_api':
            $result = $api->testAPI();
            break;
            
        case 'get_categories':
            $result = $api->getSettingsByCategory();
            break;
            
        case 'sync_status':
            $result = $api->getSyncStatus();
            break;
            
        default:
            $result = ['success' => false, 'message' => 'Invalid action'];
    }
    
    header('Content-Type: application/json');
    echo json_encode($result);
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    $api = new SettingsAPI();
    
    switch ($action) {
        case 'update':
            $key = $input['key'] ?? '';
            $value = $input['value'] ?? '';
            $updated_by = $input['updated_by'] ?? 'admin';
            
            if (empty($key)) {
                $result = ['success' => false, 'message' => 'Key parameter required'];
            } else {
                $result = $api->updateSetting($key, $value, $updated_by);
            }
            break;
            
        case 'bulk_update':
            $settings = $input['settings'] ?? [];
            $updated_by = $input['updated_by'] ?? 'admin';
            
            if (empty($settings)) {
                $result = ['success' => false, 'message' => 'Settings parameter required'];
            } else {
                $result = $api->bulkUpdateSettings($settings, $updated_by);
            }
            break;
            
        case 'reset':
            $key = $input['key'] ?? '';
            if (empty($key)) {
                $result = ['success' => false, 'message' => 'Key parameter required'];
            } else {
                $result = $api->resetSetting($key);
            }
            break;
            
        case 'validate_timings':
            $timings = $input['timings'] ?? [];
            $validation = $api->validateTimings($timings);
            $result = [
                'success' => $validation['valid'],
                'message' => $validation['valid'] ? 'Timings are valid' : 'Timing validation failed',
                'data' => $validation
            ];
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
