<?php
/**
 * Time Validator API for QR Attendance System
 * Provides time validation endpoints for attendance operations
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once 'config.php';

/**
 * Convert 24-hour time to 12-hour format with AM/PM
 * @param string $time24 Time in 24-hour format (HH:MM:SS or HH:MM)
 * @return string Time in 12-hour format (H:MM AM/PM)
 */
function convert24to12($time24) {
    if (empty($time24)) return '';
    
    // Handle different input formats
    $timeStr = trim($time24);
    
    // If already in 12-hour format, return as is
    if (strpos($timeStr, 'AM') !== false || strpos($timeStr, 'PM') !== false) {
        return $timeStr;
    }
    
    // Extract hours and minutes
    $timeParts = explode(':', $timeStr);
    if (count($timeParts) < 2) return $time24;
    
    $hours = intval($timeParts[0]);
    $minutes = $timeParts[1];
    
    // Determine AM/PM
    $period = $hours >= 12 ? 'PM' : 'AM';
    
    // Convert to 12-hour format
    if ($hours == 0) {
        $hours = 12; // Midnight
    } elseif ($hours > 12) {
        $hours = $hours - 12;
    }
    
    // Format with leading zero for single digits
    $formattedHours = str_pad($hours, 1, '0', STR_PAD_LEFT);
    
    return $formattedHours . ':' . $minutes . ' ' . $period;
}

header('Content-Type: application/json');

// Simple CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit();
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input: ' . json_last_error_msg());
    }
    
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'validate_checkin':
            validateCheckinTime($input);
            break;
            
        case 'validate_checkout':
            validateCheckoutTime($input);
            break;
            
        case 'get_shift_timings':
            getShiftTimingsAPI($input);
            break;
            
        case 'debug_settings':
            debugSettings();
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
    }
    
} catch (Exception $e) {
    error_log('Time validator API error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

/**
 * Validate check-in time
 */
function validateCheckinTime($input) {
    if (!isset($input['student_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Student ID is required']);
        return;
    }
    
    $student_id = $input['student_id'];
    $shift = $input['shift'] ?? 'Morning';
    $current_time = new DateTime('now', new DateTimeZone('Asia/Karachi'));
    $current_time_str = $current_time->format('H:i:s');
    
    // Debug logging
    error_log("Time validation - Student ID: " . $student_id . ", Shift: " . $shift . ", Current time: " . $current_time_str);
    
    try {
        // Simple time validation without complex dependencies
        $timings = getShiftTimings($shift);
        
        $is_within_window = false;
        $error = null;
        
        if ($current_time_str >= $timings['checkin_start'] && $current_time_str <= $timings['checkin_end']) {
            $is_within_window = true;
        } else {
            $error = "Check-in not allowed. Window: " . convert24to12($timings['checkin_start']) . " - " . convert24to12($timings['checkin_end']);
        }
        
        echo json_encode([
            'success' => true,
            'valid' => $is_within_window,
            'error' => $error,
            'student_id' => $student_id,
            'shift' => $shift,
            'current_time' => $current_time->format('Y-m-d H:i:s'),
            'checkin_start' => convert24to12($timings['checkin_start']),
            'checkin_end' => convert24to12($timings['checkin_end']),
            'class_end' => convert24to12($timings['class_end']),
            'is_within_window' => $is_within_window,
            'time_until_close' => null
        ]);
        
    } catch (Exception $e) {
        error_log('Time validation error: ' . $e->getMessage());
        echo json_encode([
            'success' => false,
            'valid' => false,
            'error' => 'Validation error: ' . $e->getMessage()
        ]);
    }
}

/**
 * Validate check-out time
 */
function validateCheckoutTime($input) {
    global $pdo;
    
    if (!isset($input['student_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Student ID is required']);
        return;
    }
    
    $student_id = $input['student_id'];
    $shift = $input['shift'] ?? 'Morning';
    $current_time = new DateTime('now', new DateTimeZone('Asia/Karachi'));
    
    try {
        // Simple time validation without complex dependencies
        $timings = getShiftTimings($shift);
        $current_time_str = $current_time->format('H:i:s');
        
        $is_within_window = false;
        $error = null;
        
        // For checkout, use specific checkout time window
        $checkout_start = $timings['checkout_start'] ?? $timings['checkin_start'];
        $checkout_end = $timings['checkout_end'] ?? $timings['class_end'];
        
        // Debug logging
        error_log("Checkout validation - Current time: $current_time_str, Start: $checkout_start, End: $checkout_end");
        error_log("Comparison result: " . ($current_time_str >= $checkout_start ? 'true' : 'false') . " && " . ($current_time_str <= $checkout_end ? 'true' : 'false'));
        
        // Check if within checkout time window
        if ($current_time_str >= $checkout_start && $current_time_str <= $checkout_end) {
            // Additional check: ensure student has been checked in for a reasonable time
            // Get the check-in time for this student
            try {
                $stmt = $pdo->prepare("
                    SELECT check_in_time 
                    FROM check_in_sessions 
                    WHERE student_id = ? AND is_active = 1 
                    ORDER BY check_in_time DESC 
                    LIMIT 1
                ");
                $stmt->execute([$student_id]);
                $checkin_record = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($checkin_record) {
                    $checkin_time = new DateTime($checkin_record['check_in_time'], new DateTimeZone('Asia/Karachi'));
                    $time_diff = $current_time->getTimestamp() - $checkin_time->getTimestamp();
                    $minutes_checked_in = $time_diff / 60;
                    
                // Require at least 30 seconds between check-in and check-out (to prevent accidental double-scans)
                if ($minutes_checked_in < 0.5) {
                    $is_within_window = false;
                    $error = "Please wait 30 seconds after check-in before checking out. Time since check-in: " . round($minutes_checked_in * 60, 0) . " seconds";
                    } else {
                        $is_within_window = true;
                    }
                } else {
                    $is_within_window = true; // If no check-in record found, allow (fallback)
                }
            } catch (Exception $db_error) {
                // If database check fails, just validate based on time window
                $is_within_window = true;
            }
        } else {
            $is_within_window = false;
            $error = "Check-out not allowed. Allowed: " . convert24to12($checkout_start) . " - " . convert24to12($checkout_end);
        }
        
        echo json_encode([
            'success' => true,
            'valid' => $is_within_window,
            'error' => $error,
            'student_id' => $student_id,
            'shift' => $shift,
            'current_time' => $current_time->format('Y-m-d H:i:s'),
            'checkout_start' => convert24to12($checkout_start),
            'checkout_end' => convert24to12($checkout_end),
            'class_end' => convert24to12($timings['class_end']),
            'is_within_window' => $is_within_window,
            'time_until_close' => null,
            'debug' => [
                'current_time_24h' => $current_time_str,
                'checkout_start_24h' => $checkout_start,
                'checkout_end_24h' => $checkout_end,
                'comparison_start' => $current_time_str >= $checkout_start,
                'comparison_end' => $current_time_str <= $checkout_end
            ]
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'valid' => false,
            'error' => 'Validation error: ' . $e->getMessage()
        ]);
    }
}

/**
 * Get shift timings (API endpoint)
 */
function getShiftTimingsAPI($input) {
    $shift = $input['shift'] ?? 'Morning';
    
    try {
        $timings = getShiftTimings($shift);
        
        echo json_encode([
            'success' => true,
            'timings' => $timings
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Error getting shift timings: ' . $e->getMessage()
        ]);
    }
}

/**
 * Get shift timings from database settings
 */
function getShiftTimings($shift) {
    global $pdo;
    
        // No hardcoded defaults - all timings must come from database
    
    try {
        // Get timing settings from database - get all timing settings at once
        $stmt = $pdo->prepare("
            SELECT setting_key, setting_value 
            FROM system_settings 
            WHERE setting_key LIKE '%check%' OR setting_key LIKE '%class%'
            ORDER BY setting_key
        ");
        $stmt->execute();
        $all_settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // Filter settings for the specific shift
        $shift_lower = strtolower($shift);
        $settings = [];
        foreach ($all_settings as $key => $value) {
            if (strpos($key, $shift_lower . '_') === 0) {
                $settings[$key] = $value;
            }
        }
        
        // Debug logging
        error_log("Shift: " . $shift . " (lowercase: " . $shift_lower . ")");
        error_log("Settings found: " . print_r($settings, true));
        
        // If no settings found, return error - all timings must be configured
        if (empty($settings)) {
            error_log("No timing settings found for shift: " . $shift);
            throw new Exception("Timing settings not configured for " . $shift . " shift. Please configure timings in admin settings.");
        }
        
        // Build timing array from database settings
        $timings = [];
        
                    if ($shift_lower === 'morning') {
                        // All settings must exist - no fallbacks
                        if (!isset($settings['morning_checkin_start']) || !isset($settings['morning_checkin_end']) || 
                            !isset($settings['morning_class_end'])) {
                            throw new Exception("Incomplete morning shift timing configuration. Please configure all timing settings.");
                        }
                        
                        $timings['checkin_start'] = $settings['morning_checkin_start'];
                        $timings['checkin_end'] = $settings['morning_checkin_end'];
                        $timings['class_end'] = $settings['morning_class_end'];
                        
                        // Use checkout settings if available, otherwise use checkin_end as checkout_start and class_end as checkout_end
                        $timings['checkout_start'] = $settings['morning_checkout_start'] ?? $settings['morning_checkin_end'];
                        $timings['checkout_end'] = $settings['morning_checkout_end'] ?? $settings['morning_class_end'];
                        
                    } elseif ($shift_lower === 'evening') {
                        // All settings must exist - no fallbacks
                        if (!isset($settings['evening_checkin_start']) || !isset($settings['evening_checkin_end']) || 
                            !isset($settings['evening_class_end'])) {
                            throw new Exception("Incomplete evening shift timing configuration. Please configure all timing settings.");
                        }
                        
                        $timings['checkin_start'] = $settings['evening_checkin_start'];
                        $timings['checkin_end'] = $settings['evening_checkin_end'];
                        $timings['class_end'] = $settings['evening_class_end'];
                        
                        // Use checkout settings if available, otherwise use checkin_start as checkout_start and class_end as checkout_end
                        $timings['checkout_start'] = $settings['evening_checkout_start'] ?? $settings['evening_checkin_start'];
                        $timings['checkout_end'] = $settings['evening_checkout_end'] ?? $settings['evening_class_end'];
                        
                    } else {
                        throw new Exception("Invalid shift: " . $shift . ". Must be 'morning' or 'evening'.");
                    }
        
        // Ensure times are in HH:MM:SS format
        foreach ($timings as $key => $time) {
            if (strlen($time) === 5) { // HH:MM format
                $timings[$key] = $time . ':00';
            }
        }
        
        // Debug logging
        error_log("Final timings for " . $shift_lower . ": " . print_r($timings, true));
        
        return $timings;
        
    } catch (Exception $e) {
        error_log('Error loading shift timings from database: ' . $e->getMessage());
        // NO FALLBACK TO DEFAULTS - All timings must be configured by admin
        throw new Exception("Timing settings not properly configured. Please configure all timing settings in admin panel before using attendance features.");
    }
}

/**
 * Debug settings function
 */
function debugSettings() {
    global $pdo;
    
    try {
        // Get all timing settings
        $stmt = $pdo->prepare("
            SELECT setting_key, setting_value 
            FROM system_settings 
            WHERE setting_key LIKE '%checkin%' OR setting_key LIKE '%class%'
            ORDER BY setting_key
        ");
        $stmt->execute();
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        echo json_encode([
            'success' => true,
            'settings' => $settings,
            'morning_timings' => getShiftTimings('morning'),
            'evening_timings' => getShiftTimings('evening')
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Debug error: ' . $e->getMessage()
        ]);
    }
}
?>
