<?php
/**
 * Check-in/Check-out API for QR Code Attendance System
 * Handles check-in and check-out operations with time validation
 * Enhanced with roll number parsing and shift-based time windows
 */

require_once 'config.php';
require_once 'roll_parser.php';
// Removed deprecated time_validator.php - now using time_validator_api.php

/**
 * Helper function to validate timing using the new API
 */
function validateTimingAPI($action, $student_id, $shift = null) {
    try {
        // Use cURL to call the time_validator_api.php
        $data = [
            'action' => $action,
            'student_id' => $student_id
        ];
        
        if ($shift) {
            $data['shift'] = $shift;
        }
        
        $ch = curl_init();
        $url = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/time_validator_api.php';
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($response === false || !empty($curl_error)) {
            return [
                'success' => false,
                'valid' => false,
                'error' => 'Time validation service unavailable: ' . $curl_error
            ];
        }
        
        if ($http_code !== 200) {
            return [
                'success' => false,
                'valid' => false,
                'error' => 'Time validation service returned HTTP ' . $http_code . ': ' . $response
            ];
        }
        
        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'valid' => false,
                'error' => 'Invalid JSON response from time validator: ' . $response
            ];
        }
        
        return $result;
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'valid' => false,
            'error' => $e->getMessage()
        ];
    }
}

header('Content-Type: application/json');
// CORS from env
setCorsHeaders();

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'POST') {
        // Get action from JSON input or POST data
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? $_POST['action'] ?? $_GET['action'] ?? '';
        
        switch ($action) {
            case 'check_in':
                handleCheckIn($pdo);
                break;
                
            case 'check_out':
                handleCheckOut($pdo);
                break;
                
            case 'get_status':
                getStudentStatus($pdo);
                break;
                
            case 'bulk_checkin':
                handleBulkCheckIn($pdo);
                break;
                
            default:
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
        }
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

/**
 * Handle check-in operation with enhanced roll number parsing and time validation
 */
function handleCheckIn($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['student_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Student ID is required']);
        return;
    }
    
    $student_id = $input['student_id'];
    $current_time = new DateTime('now', new DateTimeZone('Asia/Karachi'));
    $current_time_str = $current_time->format('Y-m-d H:i:s');
    
    try {
        // Parse roll number to get student metadata
        $roll_data = RollParser::parseRollNumber($student_id);
        if (!$roll_data['valid']) {
            echo json_encode(['success' => false, 'message' => 'Invalid roll number format: ' . $roll_data['error']]);
            return;
        }
        
        // Get student information from database
        $stmt = $pdo->prepare("
            SELECT name, admission_year, current_year, shift, program, is_active, is_graduated 
            FROM students 
            WHERE student_id = ? AND is_active = 1
        ");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$student) {
            echo json_encode(['success' => false, 'message' => 'Student not found or inactive']);
            return;
        }
        
        // Check if student is graduated
        if ($student['is_graduated']) {
            echo json_encode(['success' => false, 'message' => 'Student has graduated and cannot check in']);
            return;
        }
        
        // Update student metadata if needed
        $needs_update = false;
        if ($student['admission_year'] != $roll_data['admission_year'] || 
            $student['shift'] != $roll_data['shift'] || 
            $student['program'] != $roll_data['program']) {
            $needs_update = true;
        }
        
        if ($needs_update) {
            $stmt = $pdo->prepare("
                UPDATE students 
                SET admission_year = ?, current_year = ?, shift = ?, program = ?, last_year_update = CURDATE()
                WHERE student_id = ?
            ");
            $stmt->execute([
                $roll_data['admission_year'],
                $roll_data['current_year'],
                $roll_data['shift'],
                $roll_data['program'],
                $student_id
            ]);
        }
        
        // Validate check-in time based on shift using new API
        $time_validation = validateTimingAPI('validate_checkin', $student_id, $roll_data['shift']);
        
        if (!$time_validation['success'] || !$time_validation['valid']) {
            echo json_encode([
                'success' => false, 
                'message' => $time_validation['error'] ?? 'Time validation failed',
                'timing_info' => [
                    'shift' => $roll_data['shift'],
                    'checkin_window' => ($time_validation['checkin_start'] ?? 'N/A') . ' - ' . ($time_validation['checkin_end'] ?? 'N/A'),
                    'current_time' => $time_validation['current_time'] ?? date('Y-m-d H:i:s')
                ]
            ]);
            return;
        }
        
        // Check if student already has an active session
        $stmt = $pdo->prepare("SELECT id FROM check_in_sessions WHERE student_id = ? AND is_active = 1");
        $stmt->execute([$student_id]);
        $active_session = $stmt->fetch();
        
        if ($active_session) {
            echo json_encode(['success' => false, 'message' => 'Student already checked in. Please check out first.']);
            return;
        }
        
        // Additional check: Verify no attendance record exists today (prevent duplicate attendance)
        $stmt = $pdo->prepare("
            SELECT id, status, check_out_time FROM attendance 
            WHERE student_id = ? 
            AND DATE(timestamp) = DATE(?)
        ");
        $stmt->execute([$student_id, $current_time_str]);
        $existing_attendance = $stmt->fetch();
        
        if ($existing_attendance) {
            if ($existing_attendance['status'] === 'Check-in' && $existing_attendance['check_out_time'] === null) {
                echo json_encode(['success' => false, 'message' => 'You have an incomplete check-in from today. Please check out first.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Attendance already recorded for today. Multiple check-ins per day are not allowed.']);
            }
            return;
        }
        
        // Start transaction
        $pdo->beginTransaction();
        
        try {
            // Create new check-in session (only use columns that exist in the table)
            $stmt = $pdo->prepare("
                INSERT INTO check_in_sessions (student_id, student_name, check_in_time, is_active) 
                VALUES (?, ?, ?, 1)
            ");
            $stmt->execute([
                $student_id, 
                $student['name'], 
                $current_time_str
            ]);
            
            // Record attendance with Check-in status and enhanced metadata
            $stmt = $pdo->prepare("
                INSERT INTO attendance (student_id, student_name, timestamp, status, check_in_time, shift, program, current_year, admission_year) 
                VALUES (?, ?, ?, 'Check-in', ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $student_id, 
                $student['name'], 
                $current_time_str, 
                $current_time_str,
                $roll_data['shift'],
                $roll_data['program'],
                $roll_data['current_year'],
                $roll_data['admission_year']
            ]);
            
            $pdo->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Check-in successful',
                'data' => [
                    'student_id' => $student_id,
                    'student_name' => $student['name'],
                    'check_in_time' => $current_time_str,
                    'status' => 'Check-in',
                    'shift' => $roll_data['shift'],
                    'program' => $roll_data['program'],
                    'current_year' => $roll_data['current_year'],
                    'admission_year' => $roll_data['admission_year'],
                    'timing_info' => [
                        'checkin_window' => $time_validation['checkin_start'] . ' - ' . $time_validation['checkin_end'],
                        'class_ends' => $time_validation['class_end'],
                        'time_until_close' => $time_validation['time_until_close']
                    ]
                ]
            ]);
            
        } catch (PDOException $e) {
            $pdo->rollback();
            
            // Check for duplicate entry error (unique constraint violation)
            if ($e->getCode() == 23000 || strpos($e->getMessage(), '1062') !== false || strpos($e->getMessage(), 'unique_active_session') !== false) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'You are already checked in. Please check out before checking in again.'
                ]);
                return;
            }
            
            // For other database errors
            error_log('Check-in database error: ' . $e->getMessage());
            echo json_encode([
                'success' => false, 
                'message' => 'Check-in failed due to a database error. Please try again.'
            ]);
            return;
        } catch (Exception $e) {
            $pdo->rollback();
            echo json_encode([
                'success' => false, 
                'message' => 'Check-in failed: ' . $e->getMessage()
            ]);
            return;
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Check-in failed: ' . $e->getMessage()]);
    }
}

/**
 * Handle check-out operation
 */
function handleCheckOut($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['student_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Student ID is required']);
        return;
    }
    
    $student_id = $input['student_id'];
    $current_time = date('Y-m-d H:i:s');
    
    try {
        // Check if student has an active session
        $stmt = $pdo->prepare("
            SELECT id, check_in_time, student_name 
            FROM check_in_sessions 
            WHERE student_id = ? AND is_active = 1
        ");
        $stmt->execute([$student_id]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$session) {
            echo json_encode(['success' => false, 'message' => 'No active check-in session found']);
            return;
        }
        
        // Parse roll number to get shift information for checkout validation
        $roll_data = RollParser::parseRollNumber($student_id);
        if (!$roll_data['valid']) {
            echo json_encode(['success' => false, 'message' => 'Invalid roll number format: ' . $roll_data['error']]);
            return;
        }
        
        // Validate checkout time based on shift using new API
        $checkout_validation = validateTimingAPI('validate_checkout', $student_id, $roll_data['shift']);
        
        if (!$checkout_validation['success'] || !$checkout_validation['valid']) {
            echo json_encode([
                'success' => false, 
                'message' => $checkout_validation['error'] ?? 'Checkout validation failed',
                'timing_info' => [
                    'shift' => $roll_data['shift'],
                    'checkout_window' => ($checkout_validation['checkout_start'] ?? 'N/A') . ' - ' . ($checkout_validation['checkout_end'] ?? 'N/A'),
                    'current_time' => $checkout_validation['current_time'] ?? date('Y-m-d H:i:s')
                ]
            ]);
            return;
        }
        
        // Calculate session duration for all shifts
        $check_in_time = new DateTime($session['check_in_time']);
        $current_time_obj = new DateTime($current_time);
        $time_diff = $current_time_obj->diff($check_in_time);
        $total_minutes = ($time_diff->h * 60) + $time_diff->i;
        
        // No minimum duration requirement - checkout always allowed
        
        // Start transaction
        $pdo->beginTransaction();
        
        try {
            // Update attendance record to Present (student completed check-in and check-out)
            $stmt = $pdo->prepare("
                UPDATE attendance 
                SET status = 'Present', check_out_time = ?, session_duration = ?
                WHERE student_id = ? AND DATE(timestamp) = DATE(?) AND status = 'Check-in'
            ");
            $stmt->execute([$current_time, $total_minutes, $student_id, $current_time]);
            
            // Check if any row was updated
            if ($stmt->rowCount() === 0) {
                // Log for debugging
                error_log("No attendance record found for checkout - Student: $student_id, Date: $current_time");
                
                // Try to find what records exist for debugging
                $debug_stmt = $pdo->prepare("
                    SELECT id, status, check_out_time, timestamp 
                    FROM attendance 
                    WHERE student_id = ? AND DATE(timestamp) = DATE(?)
                    ORDER BY timestamp DESC
                ");
                $debug_stmt->execute([$student_id, $current_time]);
                $debug_records = $debug_stmt->fetchAll(PDO::FETCH_ASSOC);
                error_log("Debug - Existing attendance records: " . json_encode($debug_records));
                
                throw new Exception('No check-in record found to update. Student may have already checked out or no attendance record exists.');
            }
            
            // Delete the check-in session to avoid unique constraint conflicts
            $stmt = $pdo->prepare("
                DELETE FROM check_in_sessions 
                WHERE id = ?
            ");
            $stmt->execute([$session['id']]);
            
            $pdo->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Check-out successful',
                'data' => [
                    'student_id' => $student_id,
                    'student_name' => $session['student_name'],
                    'check_in_time' => $session['check_in_time'],
                    'check_out_time' => $current_time,
                    'session_duration' => $total_minutes,
                    'status' => 'Present'
                ]
            ]);
            
        } catch (Exception $e) {
            $pdo->rollback();
            throw $e;
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Check-out failed: ' . $e->getMessage()]);
    }
}

/**
 * Get student's current status with enhanced shift and timing information
 */
function getStudentStatus($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    $student_id = $input['student_id'] ?? $_GET['student_id'] ?? '';
    
    if (empty($student_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Student ID is required']);
        return;
    }
    
    try {
        // Debug: Log the student ID being processed
        error_log("Processing student ID: " . $student_id . " (length: " . strlen($student_id) . ")");
        
        // Parse roll number to get student metadata
        $roll_data = RollParser::parseRollNumber($student_id);
        if (!$roll_data['valid']) {
            error_log("Roll number validation failed for: " . $student_id . " - Error: " . $roll_data['error']);
            echo json_encode(['success' => false, 'message' => 'Invalid roll number format: ' . $roll_data['error'] . ' (ID: ' . $student_id . ')']);
            return;
        }
        $current_time = new DateTime('now', new DateTimeZone('Asia/Karachi'));
        
        // Get student information
        $stmt = $pdo->prepare("
            SELECT name, admission_year, current_year, shift, program, is_active, is_graduated
            FROM students 
            WHERE student_id = ?
        ");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$student) {
            echo json_encode(['success' => false, 'message' => 'Student not found for ID: ' . $student_id]);
            return;
        }
        
        // Check if student has an active session (shift, program, current_year not in check_in_sessions table)
        $stmt = $pdo->prepare("
            SELECT id, check_in_time, student_name, last_activity
            FROM check_in_sessions
            WHERE student_id = ? AND is_active = 1
        ");
        $stmt->execute([$student_id]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($session) {
            // Calculate time elapsed
            $check_in_time = new DateTime($session['check_in_time']);
            $time_diff = $current_time->diff($check_in_time);
            $total_minutes = ($time_diff->h * 60) + $time_diff->i;
            
            // Get shift information from students table (not in check_in_sessions)
            $session_shift = $student['shift'];

            // Validate checkout time based on shift using new API
            $checkout_validation = validateTimingAPI('validate_checkout', $student_id, $session_shift);

            // No minimum duration requirement - checkout always allowed
            $can_checkout = $checkout_validation['success'] && $checkout_validation['valid'];

            echo json_encode([
                'success' => true,
                'data' => [
                    'student_id' => $student_id,
                    'student_name' => $session['student_name'],
                    'status' => 'Checked-in',
                    'check_in_time' => $session['check_in_time'],
                    'time_elapsed' => $total_minutes,
                    'can_checkout' => $can_checkout,
                    'remaining_minutes' => 0,
                    'shift' => $session_shift,
                    'program' => $student['program'],
                    'current_year' => $student['current_year'],
                    'admission_year' => $student['admission_year'],
                    'is_graduated' => $student['is_graduated'],
                    'checkout_validation' => [
                        'valid' => $checkout_validation['valid'],
                        'checkout_window' => $checkout_validation['checkout_start'] . ' - ' . $checkout_validation['checkout_end'],
                        'error' => $checkout_validation['error']
                    ]
                ]
            ]);
        } else {
            // Get timing information for check-in window using new API
            $time_validation = validateTimingAPI('validate_checkin', $student_id, $student['shift']);
            $next_window = null; // Next window calculation not available in new API
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'student_id' => $student_id,
                    'student_name' => $student['name'],
                    'status' => 'Not checked in',
                    'can_checkin' => $time_validation['success'] && $time_validation['valid'],
                    'shift' => $student['shift'],
                    'program' => $student['program'],
                    'current_year' => $student['current_year'],
                    'admission_year' => $student['admission_year'],
                    'is_graduated' => $student['is_graduated'],
                    'timing_info' => [
                        'checkin_window' => ($time_validation['checkin_start'] ?? 'N/A') . ' - ' . ($time_validation['checkin_end'] ?? 'N/A'),
                        'class_ends' => $time_validation['class_end'] ?? 'N/A',
                        'current_time' => $time_validation['current_time'] ?? date('Y-m-d H:i:s'),
                        'is_within_window' => $time_validation['is_within_window'] ?? false,
                        'time_until_close' => $time_validation['time_until_close'] ?? null
                    ],
                    'next_window' => $next_window
                ]
            ]);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to get status: ' . $e->getMessage()]);
    }
}

/**
 * Handle bulk check-in from external systems
 */
function handleBulkCheckIn($pdo) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Validate API key from env-driven config
        $api_key = $input['api_key'] ?? '';
        if (!hash_equals(API_KEY, $api_key)) {
            echo json_encode(['success' => false, 'message' => 'Invalid API key']);
            return;
        }
        
        $attendance_data = $input['attendance_data'] ?? [];
        
        if (empty($attendance_data)) {
            echo json_encode(['success' => false, 'message' => 'No attendance data provided']);
            return;
        }
        
        $success_count = 0;
        $error_count = 0;
        $errors = [];
        
        foreach ($attendance_data as $record) {
            try {
                $student_id = $record['ID'] ?? $record['student_id'] ?? '';
                $student_name = $record['Name'] ?? $record['student_name'] ?? 'Unknown';
                $timestamp = $record['Timestamp'] ?? $record['timestamp'] ?? date('Y-m-d H:i:s');
                $status = $record['Status'] ?? $record['status'] ?? 'present';
                $shift = $record['Shift'] ?? 'Morning';
                $program = $record['Program'] ?? '';
                $current_year = $record['Current_Year'] ?? $record['current_year'] ?? 1;
                $admission_year = $record['Admission_Year'] ?? $record['admission_year'] ?? date('Y');
                
                if (empty($student_id)) {
                    $error_count++;
                    $errors[] = "Missing student ID in record";
                    continue;
                }
                
                // Insert attendance record with all required fields
                $stmt = $pdo->prepare("
                    INSERT INTO attendance (student_id, student_name, timestamp, status, shift, program, current_year, admission_year, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $stmt->execute([$student_id, $student_name, $timestamp, $status, $shift, $program, $current_year, $admission_year]);
                $success_count++;
                
            } catch (Exception $e) {
                $error_count++;
                $errors[] = "Error processing record for {$student_id}: " . $e->getMessage();
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => "Bulk check-in completed: {$success_count} success, {$error_count} errors",
            'data' => [
                'success_count' => $success_count,
                'error_count' => $error_count,
                'errors' => $errors
            ]
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Bulk check-in failed: ' . $e->getMessage()]);
    }
}
?>
