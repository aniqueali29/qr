<?php
/**
 * Student API
 * Handles student-specific API requests
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');
// SECURITY FIX: Restrict CORS to specific domains
header('Access-Control-Allow-Origin: http://localhost');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
header('Access-Control-Allow-Credentials: true');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_student_data':
            if ($method === 'GET') {
                getStudentData();
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            }
            break;
            
        case 'get_attendance_stats':
            if ($method === 'GET') {
                getAttendanceStats();
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            }
            break;
            
        case 'get_recent_attendance':
            if ($method === 'GET') {
                getRecentAttendance();
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            }
            break;
            
        case 'get_attendance_history':
            if ($method === 'GET') {
                getAttendanceHistory();
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}

/**
 * Get current student data
 */
function getStudentData() {
    if (!isStudentLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Authentication required']);
        return;
    }
    
    $student = getCurrentStudent();
    if (!$student) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Student not found']);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $student
    ]);
}

/**
 * Get student attendance statistics
 */
function getAttendanceStats() {
    if (!isStudentLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Authentication required']);
        return;
    }
    
    $student_id = $_SESSION['student_id'];
    $stats = getStudentAttendanceStats($student_id);
    
    if (!$stats) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Attendance stats not found']);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $stats
    ]);
}

/**
 * Get recent attendance records
 */
function getRecentAttendance() {
    if (!isStudentLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Authentication required']);
        return;
    }
    
    $student_id = $_SESSION['student_id'];
    // SECURITY FIX: Validate limit parameter
    require_once __DIR__ . '/../includes_ext/secure_database.php';
    $limit = InputValidator::validateInt($_GET['limit'] ?? 10, 1, 100);
    $attendance = getStudentRecentAttendance($student_id, $limit);
    
    echo json_encode([
        'success' => true,
        'data' => $attendance
    ]);
}

/**
 * Get filtered attendance history
 */
function getAttendanceHistory() {
    if (!isStudentLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Authentication required']);
        return;
    }
    
    $student_id = $_SESSION['student_id'];
    $filter_type = $_GET['filter_type'] ?? 'all';
    $days = $_GET['days'] ?? 'all';
    $date_from = $_GET['date_from'] ?? null;
    $date_to = $_GET['date_to'] ?? null;
    
    try {
        global $pdo;
        
        // Build the query based on filter
        $sql = "SELECT date, time, status, notes FROM attendance WHERE student_id = ?";
        $params = [$student_id];
        
        // Add date filtering
        if ($filter_type !== 'all' && $date_from && $date_to) {
            $sql .= " AND DATE(date) BETWEEN ? AND ?";
            $params[] = $date_from;
            $params[] = $date_to;
        }
        
        $sql .= " ORDER BY date DESC, time DESC";
        
        // Add limit based on filter type
        $limit = 50; // Default limit
        if ($filter_type === '28days') {
            $limit = 28;
        } elseif ($filter_type === 'month') {
            $limit = 30;
        } elseif ($filter_type === 'year') {
            $limit = 365;
        } elseif ($filter_type === 'all') {
            $limit = 500; // Higher limit for all records
        }
        
        $sql .= " LIMIT ?";
        $params[] = $limit;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get statistics for the filtered period
        $stats_sql = "SELECT 
            COUNT(*) as total_days,
            SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_days,
            SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_days
            FROM attendance WHERE student_id = ?";
        $stats_params = [$student_id];
        
        if ($filter_type !== 'all' && $date_from && $date_to) {
            $stats_sql .= " AND DATE(date) BETWEEN ? AND ?";
            $stats_params[] = $date_from;
            $stats_params[] = $date_to;
        }
        
        $stats_stmt = $pdo->prepare($stats_sql);
        $stats_stmt->execute($stats_params);
        $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Calculate percentage
        $percentage = 0;
        if ($stats['total_days'] > 0) {
            $percentage = round(($stats['present_days'] / $stats['total_days']) * 100, 1);
        }
        
        echo json_encode([
            'success' => true,
            'attendance' => $attendance,
            'stats' => [
                'total_days' => (int)$stats['total_days'],
                'present_days' => (int)$stats['present_days'],
                'absent_days' => (int)$stats['absent_days'],
                'percentage' => $percentage
            ]
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to get attendance history: ' . $e->getMessage()
        ]);
    }
}
?>