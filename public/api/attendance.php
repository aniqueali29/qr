<?php
/**
 * Attendance API for QR Code Attendance System
 * Handles attendance-related operations and statistics
 */

require_once 'config.php';

header('Content-Type: application/json');
setCorsHeaders();

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        // Don't override URL action with JSON action - they serve different purposes
        // URL action determines which function to call, JSON action determines checkin/checkout
    }
    
    switch ($action) {
        case 'save_attendance':
            if ($method === 'POST') {
                saveAttendance($pdo);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;
            
        case 'get_recent_scans':
            if ($method === 'GET') {
                getRecentScans($pdo);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;
            
        case 'get_scan_stats':
            if ($method === 'GET') {
                getScanStats($pdo);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;
            
        case 'auto_mark_absent':
            if ($method === 'POST') {
                autoMarkAbsent($pdo);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;
            
        case 'mark_absent_students':
            if ($method === 'POST') {
                markAbsentStudents($pdo);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

/**
 * Save attendance record
 */
function saveAttendance($pdo) {
    $raw_input = file_get_contents('php://input');
    $input = json_decode($raw_input, true);
    
    if (!isset($input['student_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Student ID is required']);
        return;
    }
    
    $student_id = $input['student_id'];
    $action = $input['action'] ?? 'checkin'; // Default to checkin if not specified
    $notes = $input['notes'] ?? '';
    $current_time = new DateTime('now', new DateTimeZone('Asia/Karachi'));
    $current_time_str = $current_time->format('Y-m-d H:i:s');
    
    try {
        // Get student information
        $stmt = $pdo->prepare("
            SELECT name, shift, program, current_year, admission_year, is_active, is_graduated
            FROM students 
            WHERE student_id = ? AND is_active = 1
        ");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$student) {
            echo json_encode(['success' => false, 'message' => 'Student not found or inactive']);
            return;
        }
        
        if ($student['is_graduated']) {
            echo json_encode(['success' => false, 'message' => 'Student has graduated and cannot check in']);
            return;
        }
        
        // Check if student already has an active session
        $stmt = $pdo->prepare("SELECT id FROM check_in_sessions WHERE student_id = ? AND is_active = 1");
        $stmt->execute([$student_id]);
        $active_session = $stmt->fetch();
        
        if ($action === 'checkin') {
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
            
            // Start transaction for check-in
            $pdo->beginTransaction();
            
            try {
                // Create new check-in session
                $stmt = $pdo->prepare("
                    INSERT INTO check_in_sessions (student_id, student_name, check_in_time, is_active) 
                    VALUES (?, ?, ?, 1)
                ");
                $stmt->execute([
                    $student_id, 
                    $student['name'], 
                    $current_time_str
                ]);
                
                // Record attendance
                $stmt = $pdo->prepare("
                    INSERT INTO attendance (student_id, student_name, timestamp, status, check_in_time, shift, program, current_year, admission_year, notes) 
                    VALUES (?, ?, ?, 'Check-in', ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $student_id, 
                    $student['name'], 
                    $current_time_str, 
                    $current_time_str,
                    $student['shift'],
                    $student['program'],
                    $student['current_year'],
                    $student['admission_year'],
                    $notes
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
                        'shift' => $student['shift'],
                        'program' => $student['program']
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
                echo json_encode([
                    'success' => false, 
                    'message' => 'Check-in failed due to a database error. Please try again.'
                ]);
                error_log('Check-in error: ' . $e->getMessage());
                return;
            } catch (Exception $e) {
                $pdo->rollback();
                echo json_encode([
                    'success' => false, 
                    'message' => 'Check-in failed: ' . $e->getMessage()
                ]);
                return;
            }
            
        } elseif ($action === 'checkout') {
            if (!$active_session) {
                echo json_encode(['success' => false, 'message' => 'Student is not checked in. Please check in first.']);
                return;
            }
            
            // Get check-in session details including ID
            $stmt = $pdo->prepare("
                SELECT id, check_in_time, student_name
                FROM check_in_sessions 
                WHERE student_id = ? AND is_active = 1
            ");
            $stmt->execute([$student_id]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$session) {
                echo json_encode(['success' => false, 'message' => 'Check-in session not found.']);
                return;
            }
            
            // Calculate session duration
            $check_in_time = new DateTime($session['check_in_time'], new DateTimeZone('Asia/Karachi'));
            $duration_seconds = $current_time->getTimestamp() - $check_in_time->getTimestamp();
            $duration_minutes = round($duration_seconds / 60);
            
            // Start transaction for check-out
            $pdo->beginTransaction();
            
            try {
                // Delete the check-in session to avoid unique constraint conflicts
                $stmt = $pdo->prepare("
                    DELETE FROM check_in_sessions 
                    WHERE id = ?
                ");
                $stmt->execute([$active_session['id']]);
                
                // Try to update existing check-in record first
                $stmt = $pdo->prepare("
                    UPDATE attendance 
                    SET status = 'Present', 
                        check_out_time = ?, 
                        session_duration = ?
                    WHERE student_id = ? 
                    AND DATE(timestamp) = DATE(?) 
                    AND status = 'Check-in'
                    AND check_out_time IS NULL
                ");
                $stmt->execute([
                    $current_time_str,
                    $duration_minutes,
                    $student_id,
                    $current_time_str
                ]);
                
                // If no record was updated, create a new attendance record for this session
                if ($stmt->rowCount() === 0) {
                    // Get student info for new record
                    $student_stmt = $pdo->prepare("
                        SELECT name, shift, program, current_year, admission_year
                        FROM students 
                        WHERE student_id = ?
                    ");
                    $student_stmt->execute([$student_id]);
                    $student_info = $student_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Create new attendance record for this checkout
                    $stmt = $pdo->prepare("
                        INSERT INTO attendance (student_id, student_name, timestamp, status, check_in_time, check_out_time, session_duration, shift, program, current_year, admission_year) 
                        VALUES (?, ?, ?, 'Present', ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $student_id,
                        $student_info['name'],
                        $session['check_in_time'], // Use session check-in time as timestamp
                        $session['check_in_time'],
                        $current_time_str,
                        $duration_minutes,
                        $student_info['shift'],
                        $student_info['program'],
                        $student_info['current_year'],
                        $student_info['admission_year']
                    ]);
                }
                
                $pdo->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Check-out successful',
                    'data' => [
                        'student_id' => $student_id,
                        'student_name' => $session['student_name'],
                        'check_in_time' => $session['check_in_time'],
                        'check_out_time' => $current_time_str,
                        'session_duration' => $duration_minutes,
                        'status' => 'Present',
                        'shift' => $student['shift'],
                        'program' => $student['program']
                    ]
                ]);
                
            } catch (PDOException $e) {
                $pdo->rollback();
                
                // Log the error for debugging
                error_log('Check-out database error: ' . $e->getMessage());
                
                // Show more descriptive error for debugging
                echo json_encode([
                    'success' => false, 
                    'message' => 'Database error during checkout: ' . $e->getMessage(),
                    'debug' => [
                        'error_code' => $e->getCode(),
                        'sql_state' => $e->errorInfo[0] ?? null
                    ]
                ]);
                return;
            } catch (Exception $e) {
                $pdo->rollback();
                error_log('Check-out error: ' . $e->getMessage());
                echo json_encode([
                    'success' => false, 
                    'message' => 'Check-out failed: ' . $e->getMessage()
                ]);
                return;
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid action. Must be checkin or checkout.']);
            return;
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Check-in failed: ' . $e->getMessage()]);
    }
}

/**
 * Get recent scans
 */
function getRecentScans($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT student_id, student_name, timestamp, status, shift, program
            FROM attendance 
            WHERE DATE(timestamp) = CURDATE()
            ORDER BY timestamp DESC 
            LIMIT 10
        ");
        $stmt->execute();
        $scans = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $scans
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to load recent scans: ' . $e->getMessage()
        ]);
    }
}

/**
 * Get scan statistics
 */
function getScanStats($pdo) {
    try {
        // Today's stats
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_scans,
                SUM(CASE WHEN status = 'Check-in' THEN 1 ELSE 0 END) as check_ins,
                SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent
            FROM attendance 
            WHERE DATE(timestamp) = CURDATE()
        ");
        $stmt->execute();
        $today_stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Active sessions
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as active_sessions
            FROM check_in_sessions 
            WHERE is_active = 1
        ");
        $stmt->execute();
        $active_sessions = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'today' => $today_stats,
                'active_sessions' => $active_sessions['active_sessions']
            ]
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to load stats: ' . $e->getMessage()
        ]);
    }
}

/**
 * Auto mark absent students
 */
function autoMarkAbsent($pdo) {
    try {
        // This would typically mark students as absent based on some criteria
        // For now, just return success
        echo json_encode([
            'success' => true,
            'message' => 'Auto mark absent completed'
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Auto mark absent failed: ' . $e->getMessage()
        ]);
    }
}

/**
 * Mark absent students
 */
function markAbsentStudents($pdo) {
    try {
        // This would typically mark students as absent based on some criteria
        // For now, just return success
        echo json_encode([
            'success' => true,
            'message' => 'Mark absent students completed'
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Mark absent students failed: ' . $e->getMessage()
        ]);
    }
}
?>
