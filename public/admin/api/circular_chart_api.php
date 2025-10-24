<?php
/**
 * API endpoint for dynamic circular chart data
 * Provides real-time data for the circular chart component
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/helpers.php';

try {
    $action = $_GET['action'] ?? 'default';
    
    switch ($action) {
        case 'circular-chart-data':
            $data = getCircularChartData();
            break;
        case 'attendance-stats':
            $data = getAttendanceStats();
            break;
        case 'program-distribution':
            $data = getProgramDistribution();
            break;
        default:
            $data = getDefaultChartData();
    }
    
    echo json_encode([
        'success' => true,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Get circular chart data with 4 segments
 */
function getCircularChartData() {
    global $pdo;
    
    try {
        // Get attendance statistics
        $attendanceStats = getAttendanceStatistics();
        
        // Get program distribution
        $programStats = getProgramStatistics();
        
        // Calculate segments based on real data
        $segments = [
            [
                'label' => 'Present',
                'value' => $attendanceStats['present_count'],
                'color' => '#32cd32' // lime green
            ],
            [
                'label' => 'Absent',
                'value' => $attendanceStats['absent_count'],
                'color' => '#ef4444' // red
            ],
            [
                'label' => 'Late',
                'value' => $attendanceStats['late_count'],
                'color' => '#f59e0b' // yellow
            ],
            [
                'label' => 'Excused',
                'value' => $attendanceStats['excused_count'],
                'color' => '#9c27b0' // purple
            ]
        ];
        
        // Calculate total percentage (attendance rate)
        $totalStudents = $attendanceStats['total_students'];
        $presentStudents = $attendanceStats['present_count'];
        $attendanceRate = $totalStudents > 0 ? round(($presentStudents / $totalStudents) * 100) : 0;
        
        return [
            'segments' => $segments,
            'totalPercentage' => $attendanceRate,
            'totalLabel' => 'Attendance',
            'lastUpdated' => date('Y-m-d H:i:s')
        ];
        
    } catch (Exception $e) {
        // Return default data if database error
        return getDefaultChartData();
    }
}

/**
 * Get attendance statistics
 */
function getAttendanceStatistics() {
    global $pdo;
    
    $today = date('Y-m-d');
    
    // Get total students
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM students WHERE status = 'active'");
    $stmt->execute();
    $totalStudents = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get today's attendance
    $stmt = $pdo->prepare("
        SELECT 
            status,
            COUNT(*) as count
        FROM attendance 
        WHERE DATE(check_in_time) = ? 
        GROUP BY status
    ");
    $stmt->execute([$today]);
    $attendanceData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stats = [
        'total_students' => $totalStudents,
        'present_count' => 0,
        'absent_count' => 0,
        'late_count' => 0,
        'excused_count' => 0
    ];
    
    foreach ($attendanceData as $row) {
        switch ($row['status']) {
            case 'present':
                $stats['present_count'] = $row['count'];
                break;
            case 'absent':
                $stats['absent_count'] = $row['count'];
                break;
            case 'late':
                $stats['late_count'] = $row['count'];
                break;
            case 'excused':
                $stats['excused_count'] = $row['count'];
                break;
        }
    }
    
    return $stats;
}

/**
 * Get program distribution statistics
 */
function getProgramStatistics() {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            program,
            COUNT(*) as count
        FROM students 
        WHERE status = 'active'
        GROUP BY program
        ORDER BY count DESC
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get default chart data (fallback)
 */
function getDefaultChartData() {
    return [
        'segments' => [
            [
                'label' => 'Sports',
                'value' => 85,
                'color' => '#9c27b0'
            ],
            [
                'label' => 'Education',
                'value' => 45,
                'color' => '#32cd32'
            ],
            [
                'label' => 'Health',
                'value' => 30,
                'color' => '#00bcd4'
            ],
            [
                'label' => 'Other',
                'value' => 15,
                'color' => '#9e9e9e'
            ]
        ],
        'totalPercentage' => 38,
        'totalLabel' => 'World',
        'lastUpdated' => date('Y-m-d H:i:s')
    ];
}

/**
 * Get attendance stats for dashboard
 */
function getAttendanceStats() {
    global $pdo;
    
    $today = date('Y-m-d');
    $weekStart = date('Y-m-d', strtotime('monday this week'));
    
    // Today's stats
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
            SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late
        FROM attendance 
        WHERE DATE(check_in_time) = ?
    ");
    $stmt->execute([$today]);
    $todayStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Weekly stats
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent
        FROM attendance 
        WHERE DATE(check_in_time) >= ?
    ");
    $stmt->execute([$weekStart]);
    $weekStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return [
        'today' => $todayStats,
        'week' => $weekStats,
        'attendance_rate' => $todayStats['total'] > 0 ? 
            round(($todayStats['present'] / $todayStats['total']) * 100) : 0
    ];
}

/**
 * Get program distribution for dashboard
 */
function getProgramDistribution() {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            program,
            COUNT(*) as student_count
        FROM students 
        WHERE status = 'active'
        GROUP BY program
        ORDER BY student_count DESC
        LIMIT 5
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
