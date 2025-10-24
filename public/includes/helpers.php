<?php
/**
 * Student Portal Helper Functions
 * Common utility functions for the student portal
 */

require_once 'config.php';

// getCurrentStudent() function is already defined in auth.php

// getStudentAttendanceStats() function is already defined in auth.php

// getStudentRecentAttendance() function is already defined in auth.php

// getStudentUpcomingEvents() function is already defined in auth.php

/**
 * Get student attendance chart data for the last 6 months
 */
function getStudentAttendanceChartData($student_id) {
    global $pdo;
    
    try {
        $chart_data = [];
        $months = [];
        
        // Get last 6 months
        for ($i = 5; $i >= 0; $i--) {
            $month = date('Y-m', strtotime("-$i months"));
            $months[] = date('M Y', strtotime("-$i months"));
            
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_days,
                    SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_days
                FROM attendance 
                WHERE student_id = ? AND DATE_FORMAT(timestamp, '%Y-%m') = ?
            ");
            $stmt->execute([$student_id, $month]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $percentage = $stats['total_days'] > 0 ? 
                ($stats['present_days'] / $stats['total_days']) * 100 : 0;
            
            $chart_data[] = round($percentage, 1);
        }
        
        return [
            'months' => $months,
            'percentages' => $chart_data
        ];
    } catch (PDOException $e) {
        error_log("Error fetching chart data: " . $e->getMessage());
        return [
            'months' => [],
            'percentages' => []
        ];
    }
}

/**
 * Get student performance summary
 */
function getStudentPerformanceSummary($student_id) {
    global $pdo;
    
    try {
        // For now, return mock data since assignment/quiz tables don't exist yet
        // This can be updated when those features are implemented
        
        return [
            'assignments' => [
                'total' => 0,
                'average_grade' => 0,
                'excellent_count' => 0
            ],
            'quizzes' => [
                'total' => 0,
                'average_score' => 0,
                'best_score' => 0
            ]
        ];
    } catch (PDOException $e) {
        error_log("Error fetching performance summary: " . $e->getMessage());
        return [
            'assignments' => ['total' => 0, 'average_grade' => 0, 'excellent_count' => 0],
            'quizzes' => ['total' => 0, 'average_score' => 0, 'best_score' => 0]
        ];
    }
}

// logStudentAction() function is already defined in config.php

/**
 * Format date for display
 */
function formatDate($date, $format = 'M d, Y') {
    return date($format, strtotime($date));
}

/**
 * Format time for display
 */
function formatTime($time, $format = 'H:i') {
    return $time ? date($format, strtotime($time)) : 'N/A';
}

/**
 * Get status badge class
 */
function getStatusBadgeClass($status) {
    switch (strtolower($status)) {
        case 'present':
            return 'success';
        case 'absent':
            return 'danger';
        case 'late':
            return 'warning';
        case 'excused':
            return 'info';
        default:
            return 'secondary';
    }
}

/**
 * Get status icon
 */
function getStatusIcon($status) {
    switch (strtolower($status)) {
        case 'present':
            return 'check';
        case 'absent':
            return 'x';
        case 'late':
            return 'time';
        case 'excused':
            return 'calendar';
        default:
            return 'help-circle';
    }
}

/**
 * Calculate days until due date
 */
function getDaysUntilDue($due_date) {
    $today = new DateTime();
    $due = new DateTime($due_date);
    $diff = $today->diff($due);
    
    if ($due < $today) {
        return -$diff->days; // Overdue
    }
    
    return $diff->days;
}

/**
 * Get urgency class for due dates
 */
function getUrgencyClass($due_date) {
    $days = getDaysUntilDue($due_date);
    
    if ($days < 0) {
        return 'danger'; // Overdue
    } elseif ($days <= 1) {
        return 'warning'; // Due today or tomorrow
    } elseif ($days <= 3) {
        return 'info'; // Due soon
    }
    
    return 'success'; // Not urgent
}
?>
