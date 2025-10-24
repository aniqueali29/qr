<?php
/**
 * Dashboard API for QR Code Attendance System
 * Provides data for public dashboard and statistics
 */

require_once 'config.php';

// Handle API requests
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    $response = ['success' => false, 'message' => 'Invalid action'];
    
    switch ($action) {
        case 'public_stats':
            $response = getPublicStats();
            break;
            
        case 'recent_activity':
            $response = getRecentActivity();
            break;
            
        case 'chart_data':
            $response = getChartData();
            break;
            
        case 'weekly_trend':
            $response = getWeeklyTrend();
            break;
            
        case 'monthly_stats':
            $response = getMonthlyStats();
            break;
            
        case 'attendance-trends':
            $response = getAttendanceTrends();
            break;
    }
    
    echo json_encode($response);
    exit;
}

// Default response
echo json_encode(['success' => false, 'message' => 'Invalid request method']);

/**
 * Get public statistics
 */
function getPublicStats() {
    global $pdo;
    
    try {
        $today = date('Y-m-d');
        
        // Get total students
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM students WHERE is_active = 1");
        $stmt->execute();
        $total_students = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Get today's attendance
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent
            FROM attendance 
            WHERE DATE(timestamp) = ?
        ");
        $stmt->execute([$today]);
        $today_stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Calculate attendance rate
        $rate = 0;
        if ($today_stats['total'] > 0) {
            $rate = round(($today_stats['present'] / $today_stats['total']) * 100, 1);
        }
        
        // Get yesterday's stats for comparison
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present
            FROM attendance 
            WHERE DATE(timestamp) = ?
        ");
        $stmt->execute([$yesterday]);
        $yesterday_stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Calculate changes
        $present_change = ($today_stats['present'] || 0) - ($yesterday_stats['present'] || 0);
        $absent_change = ($today_stats['absent'] || 0) - (($yesterday_stats['total'] || 0) - ($yesterday_stats['present'] || 0));
        
        return [
            'success' => true,
            'data' => [
                'students' => (int)$total_students,
                'today' => [
                    'total' => (int)$today_stats['total'],
                    'present' => (int)$today_stats['present'],
                    'absent' => (int)$today_stats['absent'],
                    'rate' => $rate
                ],
                'students_change' => 0, // Students don't change daily
                'present_change' => $present_change,
                'absent_change' => $absent_change,
                'rate_change' => $rate - (($yesterday_stats['present'] || 0) / max($yesterday_stats['total'] || 1, 1) * 100)
            ]
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Failed to get public stats: ' . $e->getMessage()];
    }
}

/**
 * Get recent activity
 */
function getRecentActivity($limit = 20) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT student_id, student_name, timestamp, status 
            FROM attendance 
            ORDER BY timestamp DESC 
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'data' => $activities
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Failed to get recent activity: ' . $e->getMessage()];
    }
}

/**
 * Get chart data
 */
function getChartData() {
    global $pdo;
    
    try {
        $today = date('Y-m-d');
        
        // Get today's attendance overview
        $stmt = $pdo->prepare("
            SELECT 
                SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent
            FROM attendance 
            WHERE DATE(timestamp) = ?
        ");
        $stmt->execute([$today]);
        $overview = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get weekly trend (last 7 days)
        $weekly_data = getWeeklyTrendData();
        
        // Get monthly stats
        $monthly_data = getMonthlyStatsData();
        
        return [
            'success' => true,
            'data' => [
                'attendance_overview' => $overview,
                'weekly_trend' => $weekly_data,
                'monthly_stats' => $monthly_data
            ]
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Failed to get chart data: ' . $e->getMessage()];
    }
}

/**
 * Get weekly trend data
 */
function getWeeklyTrend() {
    global $pdo;
    
    try {
        $weekly_data = getWeeklyTrendData();
        
        return [
            'success' => true,
            'data' => $weekly_data
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Failed to get weekly trend: ' . $e->getMessage()];
    }
}

/**
 * Get weekly trend data (helper function)
 */
function getWeeklyTrendData() {
    global $pdo;
    
    $labels = [];
    $rates = [];
    
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $labels[] = date('M j', strtotime($date));
        
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present
            FROM attendance 
            WHERE DATE(timestamp) = ?
        ");
        $stmt->execute([$date]);
        $day_stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $rate = 0;
        if ($day_stats['total'] > 0) {
            $rate = round(($day_stats['present'] / $day_stats['total']) * 100, 1);
        }
        
        $rates[] = $rate;
    }
    
    return [
        'labels' => $labels,
        'rates' => $rates
    ];
}

/**
 * Get monthly stats
 */
function getMonthlyStats() {
    global $pdo;
    
    try {
        $monthly_data = getMonthlyStatsData();
        
        return [
            'success' => true,
            'data' => $monthly_data
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Failed to get monthly stats: ' . $e->getMessage()];
    }
}

/**
 * Get monthly stats data (helper function)
 */
function getMonthlyStatsData() {
    global $pdo;
    
    $labels = [];
    $present_data = [];
    $absent_data = [];
    
    // Get last 12 months
    for ($i = 11; $i >= 0; $i--) {
        $date = date('Y-m', strtotime("-$i months"));
        $labels[] = date('M Y', strtotime($date . '-01'));
        
        $stmt = $pdo->prepare("
            SELECT 
                SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent
            FROM attendance 
            WHERE DATE_FORMAT(timestamp, '%Y-%m') = ?
        ");
        $stmt->execute([$date]);
        $month_stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $present_data[] = (int)($month_stats['present'] || 0);
        $absent_data[] = (int)($month_stats['absent'] || 0);
    }
    
    return [
        'labels' => $labels,
        'present' => $present_data,
        'absent' => $absent_data
    ];
}

/**
 * Get attendance summary by date range
 */
function getAttendanceSummary($start_date, $end_date) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                DATE(timestamp) as date,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent
            FROM attendance 
            WHERE DATE(timestamp) BETWEEN ? AND ?
            GROUP BY DATE(timestamp)
            ORDER BY date ASC
        ");
        $stmt->execute([$start_date, $end_date]);
        $summary = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'data' => $summary
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Failed to get attendance summary: ' . $e->getMessage()];
    }
}

/**
 * Get top performing students
 */
function getTopStudents($limit = 10) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                student_id,
                student_name,
                COUNT(*) as total_days,
                SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_days,
                ROUND((SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as attendance_rate
            FROM attendance 
            GROUP BY student_id, student_name
            HAVING total_days >= 5
            ORDER BY attendance_rate DESC, present_days DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $top_students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'data' => $top_students
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Failed to get top students: ' . $e->getMessage()];
    }
}

/**
 * Get attendance patterns
 */
function getAttendancePatterns() {
    global $pdo;
    
    try {
        // Get attendance by day of week
        $stmt = $pdo->prepare("
            SELECT 
                DAYNAME(timestamp) as day_name,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present
            FROM attendance 
            WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 11 MONTH)
            GROUP BY DAYOFWEEK(timestamp), DAYNAME(timestamp)
            ORDER BY DAYOFWEEK(timestamp)
        ");
        $stmt->execute();
        $day_patterns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get attendance by hour
        $stmt = $pdo->prepare("
            SELECT 
                HOUR(timestamp) as hour,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present
            FROM attendance 
            WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY HOUR(timestamp)
            ORDER BY hour
        ");
        $stmt->execute();
        $hour_patterns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'data' => [
                'day_patterns' => $day_patterns,
                'hour_patterns' => $hour_patterns
            ]
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Failed to get attendance patterns: ' . $e->getMessage()];
    }
}

/**
 * Get attendance trends data
 */
function getAttendanceTrends() {
    global $pdo;
    
    try {
        // Get attendance trends for the last 7 days
        $stmt = $pdo->prepare("
            SELECT 
                DATE(check_in_time) as date,
                COUNT(DISTINCT student_id) as attendance_count
            FROM attendance 
            WHERE check_in_time >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY DATE(check_in_time)
            ORDER BY date ASC
        ");
        $stmt->execute();
        $trends = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format data for charts
        $formatted_data = [];
        foreach ($trends as $trend) {
            $formatted_data[] = [
                'date' => $trend['date'],
                'attendance' => (int)$trend['attendance_count']
            ];
        }
        
        return [
            'success' => true,
            'data' => $formatted_data
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Failed to get attendance trends: ' . $e->getMessage()];
    }
}
?>