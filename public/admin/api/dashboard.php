<?php
/**
 * Dashboard API
 * Provides data for the admin dashboard
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json');

// Add debugging
error_log("Dashboard API called with action: " . ($_GET['action'] ?? 'none'));

// Require admin authentication
requireAdminAuth();

$response = ['success' => false, 'message' => 'Invalid request.'];

// BUG FIX #3: Validate HTTP method
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only GET requests are allowed']);
    exit;
}

if (isset($_GET['action'])) {
    try {
        switch ($_GET['action']) {
            case 'test':
                $response = [
                    'success' => true,
                    'message' => 'Dashboard API is working',
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                break;
            case 'stats':
                // Get basic statistics
                $totalStudents = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
                $totalPrograms = $pdo->query("SELECT COUNT(*) FROM programs")->fetchColumn();
                $totalSections = $pdo->query("SELECT COUNT(*) FROM sections")->fetchColumn();
                
                // Today's attendance - BUG FIX #2: Optimized date query for index usage
                $todayAttendance = $pdo->query("
                    SELECT COUNT(DISTINCT student_id) as present_today 
                    FROM attendance 
                    WHERE check_in_time >= CURDATE() 
                    AND check_in_time < CURDATE() + INTERVAL 1 DAY
                ")->fetchColumn();
                
                // Total attendance records
                $totalAttendance = $pdo->query("SELECT COUNT(*) FROM attendance")->fetchColumn();
                
                // Active programs (assuming programs with students)
                $activePrograms = $pdo->query("
                    SELECT COUNT(DISTINCT p.id) 
                    FROM programs p 
                    INNER JOIN students s ON p.code = s.program
                ")->fetchColumn();
                
                $response = [
                    'success' => true,
                    'data' => [
                        'totalStudents' => (int)$totalStudents,
                        'totalPrograms' => (int)$totalPrograms,
                        'totalSections' => (int)$totalSections,
                        'presentToday' => (int)$todayAttendance,
                        'totalAttendance' => (int)$totalAttendance,
                        'activePrograms' => (int)$activePrograms,
                        'systemStatus' => 'ONLINE'
                    ]
                ];
                break;

            case 'attendance-trends':
                // Get attendance trends for the last 7 days
                try {
                    // BUG FIX #2: Optimized date query
                    $stmt = $pdo->query("
                        SELECT 
                            DATE(check_in_time) as date,
                            COUNT(DISTINCT student_id) as present_students
                        FROM attendance 
                        WHERE check_in_time >= CURDATE() - INTERVAL 7 DAY
                        AND check_in_time < CURDATE() + INTERVAL 1 DAY
                        GROUP BY DATE(check_in_time)
                        ORDER BY date ASC
                    ");
                    $trends = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // If no data, create some sample data for the last 7 days
                    if (empty($trends)) {
                        $trends = [];
                        for ($i = 6; $i >= 0; $i--) {
                            $date = date('Y-m-d', strtotime("-{$i} days"));
                            $trends[] = [
                                'date' => $date,
                                'present_students' => 0
                            ];
                        }
                    }
                    
                    $response = [
                        'success' => true,
                        'data' => $trends
                    ];
                } catch (Exception $e) {
                    error_log("Dashboard API attendance-trends error: " . $e->getMessage());
                    $response = [
                        'success' => true,
                        'data' => []
                    ];
                }
                break;

            case 'program-distribution':
                // BUG FIX #7: Sanitize data to prevent XSS
                // Get student count by program and shift to separate morning and evening programs
                // Only show programs that actually have students
                $stmt = $pdo->query("
                    SELECT 
                        CONCAT(p.name, ' (', s.shift, ')') as program_name,
                        COUNT(s.id) as student_count,
                        s.shift,
                        p.name as base_program_name
                    FROM students s
                    INNER JOIN programs p ON s.program = p.code
                    WHERE s.is_active = 1 
                    AND s.shift IS NOT NULL 
                    AND s.shift != ''
                    GROUP BY p.id, p.name, s.shift
                    HAVING student_count > 0
                    ORDER BY p.name, s.shift
                ");
                $distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Sanitize output data
                $sanitized = array_map(function($item) {
                    return [
                        'program_name' => htmlspecialchars($item['program_name'] ?? '', ENT_QUOTES, 'UTF-8'),
                        'student_count' => (int)($item['student_count'] ?? 0),
                        'shift' => htmlspecialchars($item['shift'] ?? '', ENT_QUOTES, 'UTF-8'),
                        'base_program_name' => htmlspecialchars($item['base_program_name'] ?? '', ENT_QUOTES, 'UTF-8')
                    ];
                }, $distribution);
                
                $response = [
                    'success' => true,
                    'data' => $sanitized
                ];
                break;

            case 'recent-activity':
                // BUG FIX #33: Add date filter - only last 7 days
                // Get recent attendance records
                $stmt = $pdo->query("
                    SELECT 
                        a.id,
                        s.name as student_name,
                        s.student_id,
                        a.check_in_time,
                        a.status
                    FROM attendance a
                    JOIN students s ON a.student_id = s.student_id
                    WHERE a.check_in_time >= CURDATE() - INTERVAL 7 DAY
                    ORDER BY a.check_in_time DESC
                    LIMIT 10
                ");
                $activity = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // BUG FIX #7: Sanitize output
                $sanitized = array_map(function($item) {
                    return [
                        'id' => (int)($item['id'] ?? 0),
                        'student_name' => htmlspecialchars($item['student_name'] ?? '', ENT_QUOTES, 'UTF-8'),
                        'student_id' => htmlspecialchars($item['student_id'] ?? '', ENT_QUOTES, 'UTF-8'),
                        'check_in_time' => $item['check_in_time'] ?? '',
                        'status' => htmlspecialchars($item['status'] ?? '', ENT_QUOTES, 'UTF-8')
                    ];
                }, $activity);
                
                $response = [
                    'success' => true,
                    'data' => $sanitized
                ];
                break;

            case 'shift-comparison':
                // Get student count by shift
                $stmt = $pdo->query("
                    SELECT 
                        shift,
                        COUNT(id) as student_count
                    FROM students
                    GROUP BY shift
                ");
                $shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $response = [
                    'success' => true,
                    'data' => $shifts
                ];
                break;

            case 'year-enrollment':
                // Get student count by year level
                $stmt = $pdo->query("
                    SELECT 
                        year_level,
                        COUNT(id) as student_count
                    FROM students
                    GROUP BY year_level
                    ORDER BY year_level ASC
                ");
                $enrollment = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $response = [
                    'success' => true,
                    'data' => $enrollment
                ];
                break;

            case 'growth-metrics':
                // Get growth metrics for the radial chart
                try {
                    // Calculate attendance growth rate
                    $today = date('Y-m-d');
                    $yesterday = date('Y-m-d', strtotime('-1 day'));
                    $lastWeek = date('Y-m-d', strtotime('-7 days'));
                    
                    // Today's attendance - BUG FIX #2: Optimized date query
                    $stmt = $pdo->prepare("
                        SELECT COUNT(DISTINCT student_id) as today_count
                        FROM attendance 
                        WHERE check_in_time >= ? 
                        AND check_in_time < ? + INTERVAL 1 DAY
                    ");
                    $stmt->execute([$today, $today]);
                    $todayAttendance = $stmt->fetchColumn();
                    
                    // Yesterday's attendance - BUG FIX #2: Optimized date query
                    $stmt = $pdo->prepare("
                        SELECT COUNT(DISTINCT student_id) as yesterday_count
                        FROM attendance 
                        WHERE check_in_time >= ? 
                        AND check_in_time < ? + INTERVAL 1 DAY
                    ");
                    $stmt->execute([$yesterday, $yesterday]);
                    $yesterdayAttendance = $stmt->fetchColumn();
                    
                    // Last week's attendance - BUG FIX #2: Optimized date query
                    $stmt = $pdo->prepare("
                        SELECT COUNT(DISTINCT student_id) as last_week_count
                        FROM attendance 
                        WHERE check_in_time >= ? 
                        AND check_in_time < ? + INTERVAL 1 DAY
                    ");
                    $stmt->execute([$lastWeek, $lastWeek]);
                    $lastWeekAttendance = $stmt->fetchColumn();
                    
                    // Calculate growth percentage
                    $growthRate = 0;
                    if ($yesterdayAttendance > 0) {
                        $growthRate = round((($todayAttendance - $yesterdayAttendance) / $yesterdayAttendance) * 100);
                    } elseif ($lastWeekAttendance > 0) {
                        $growthRate = round((($todayAttendance - $lastWeekAttendance) / $lastWeekAttendance) * 100);
                    }
                    
                    // Ensure growth rate is between 0 and 100 for the chart
                    $growthRate = max(0, min(100, $growthRate));
                    
                    $response = [
                        'success' => true,
                        'data' => [
                            'growth_rate' => $growthRate,
                            'today_attendance' => $todayAttendance,
                            'yesterday_attendance' => $yesterdayAttendance,
                            'last_week_attendance' => $lastWeekAttendance,
                            'label' => 'Growth'
                        ]
                    ];
                } catch (Exception $e) {
                    $response = [
                        'success' => true,
                        'data' => [
                            'growth_rate' => 0,
                            'today_attendance' => 0,
                            'yesterday_attendance' => 0,
                            'last_week_attendance' => 0,
                            'label' => 'Growth'
                        ]
                    ];
                }
                break;

            default:
                http_response_code(400);
                $response = ['success' => false, 'message' => 'Unknown action.'];
                break;
        }
    } catch (PDOException $e) {
        // BUG FIX #1: Don't expose database details to client
        error_log('Dashboard API Database Error: ' . $e->getMessage());
        http_response_code(500);
        $response = ['success' => false, 'message' => 'A database error occurred. Please try again later.'];
    } catch (Exception $e) {
        // BUG FIX #1: Don't expose server details to client
        error_log('Dashboard API Server Error: ' . $e->getMessage());
        http_response_code(500);
        $response = ['success' => false, 'message' => 'A server error occurred. Please try again later.'];
    }
}

echo json_encode($response);
?>