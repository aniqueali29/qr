<?php
/**
 * Reports API
 * Handles attendance reports generation and statistics
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

// Require admin authentication
requireAdminAuth();

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'quick_stats':
            $stats = getQuickStats($pdo);
            echo json_encode(['success' => true, 'data' => $stats]);
            break;
            
        case 'program_stats':
            $stats = getProgramStats($pdo);
            echo json_encode(['success' => true, 'data' => $stats]);
            break;
            
        case 'shift_stats':
            $stats = getShiftStats($pdo);
            echo json_encode(['success' => true, 'data' => $stats]);
            break;
            
        case 'trends':
            $days = $_GET['days'] ?? 7;
            $useSession = isset($_GET['session']) && $_GET['session'] === 'true';
            
            if ($useSession) {
                $trends = getAttendanceTrendsForSession($pdo);
            } else {
            $trends = getAttendanceTrends($pdo, $days);
            }
            
            echo json_encode(['success' => true, 'data' => $trends]);
            break;
            
        case 'monthly_comparison':
            $comparison = getMonthlyComparison($pdo);
            echo json_encode(['success' => true, 'data' => $comparison]);
            break;
            
        case 'get_programs':
            $programs = getPrograms($pdo);
            echo json_encode(['success' => true, 'data' => $programs]);
            break;
            
        case 'get_shifts':
            $shifts = getShifts($pdo);
            echo json_encode(['success' => true, 'data' => $shifts]);
            break;
            
        case 'get_year_levels':
            $yearLevels = getYearLevels($pdo);
            echo json_encode(['success' => true, 'data' => $yearLevels]);
            break;
            
        case 'get_sections':
            $program = $_GET['program'] ?? '';
            $yearLevel = $_GET['year_level'] ?? '';
            $shift = $_GET['shift'] ?? '';
            $sections = getSections($pdo, $program, $yearLevel, $shift);
            echo json_encode(['success' => true, 'data' => $sections]);
            break;
            
        case 'get_session_dates':
            $sessionRange = getActiveSessionRange($pdo);
            echo json_encode([
                'success' => true, 
                'start_date' => $sessionRange['start'], 
                'end_date' => $sessionRange['end'],
                'label' => $sessionRange['label']
            ]);
            break;
            
        case 'generate_report':
            generateReport($pdo);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * Get active session date range
 */
function getActiveSessionRange($pdo) {
    // Get the most recent active session
    $stmt = $pdo->query("
        SELECT start_date, end_date 
        FROM enrollment_sessions 
        WHERE is_active = 1 
        ORDER BY start_date DESC 
        LIMIT 1
    ");
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($session && $session['start_date'] && $session['end_date']) {
        return [
            'start' => $session['start_date'],
            'end' => $session['end_date'],
            'label' => 'Current Session'
        ];
    }
    
    // Fallback: use last 12 months if no session found
    return [
        'start' => date('Y-m-d', strtotime('-12 months')),
        'end' => date('Y-m-d'),
        'label' => 'Last 12 Months'
    ];
}

/**
 * Get quick statistics
 */
function getQuickStats($pdo) {
    // Total students
    $stmt = $pdo->query("SELECT COUNT(*) FROM students WHERE is_active = 1");
    $totalStudents = $stmt->fetchColumn();
    
    // Present today
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT student_id) 
        FROM attendance 
        WHERE DATE(timestamp) = CURDATE() 
        AND status IN ('Present', 'Check-in')
    ");
    $presentToday = $stmt->fetchColumn();
    
    // Get session date range
    $sessionRange = getActiveSessionRange($pdo);
    
    // Average attendance for current session
    $stmt = $pdo->prepare("
        SELECT ROUND(
            (COUNT(DISTINCT a.student_id) * 100.0 / 
            NULLIF((SELECT COUNT(*) FROM students WHERE is_active = 1), 0)), 2
        ) as avg_attendance
        FROM attendance a
        WHERE a.timestamp >= ? AND a.timestamp <= ?
    ");
    $stmt->execute([$sessionRange['start'], $sessionRange['end']]);
    $avgAttendance = $stmt->fetchColumn() ?? 0;
    
    // Session attendance (same calculation)
    $monthAttendance = $avgAttendance;
    
    return [
        'total_students' => (int)$totalStudents,
        'present_today' => (int)$presentToday,
        'avg_attendance' => (float)$avgAttendance,
        'month_attendance' => (float)$monthAttendance,
        'session_label' => $sessionRange['label']
    ];
}

/**
 * Get program-wise statistics
 */
function getProgramStats($pdo) {
    // Get session date range
    $sessionRange = getActiveSessionRange($pdo);
    
    // Get program-wise attendance statistics
    // Calculate: (Average attendance percentage per student) 
    $stmt = $pdo->prepare("
        SELECT 
            s.program,
            COUNT(DISTINCT s.id) as total_students,
            COALESCE(AVG(student_attendance.attendance_pct), 0) as attendance_percentage
        FROM students s
        LEFT JOIN (
            SELECT 
                student_id,
            ROUND(
                    (COUNT(CASE WHEN status IN ('Present', 'Check-in') THEN 1 END) * 100.0 / 
                    NULLIF(COUNT(*), 0)), 2
                ) as attendance_pct
            FROM attendance
            WHERE timestamp >= ? AND timestamp <= ?
            GROUP BY student_id
        ) student_attendance ON s.id = student_attendance.student_id
        WHERE s.is_active = 1
        GROUP BY s.program
        ORDER BY s.program
    ");
    
    $stmt->execute([$sessionRange['start'], $sessionRange['end']]);
    $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format percentages
    foreach ($stats as &$stat) {
        $stat['attendance_percentage'] = (float)$stat['attendance_percentage'];
        $stat['total_students'] = (int)$stat['total_students'];
    }
    
    return $stats;
}

/**
 * Get shift-wise statistics
 */
function getShiftStats($pdo) {
    // Get session date range
    $sessionRange = getActiveSessionRange($pdo);
    
    // Get shift-wise attendance statistics
    // Calculate: (Average attendance percentage per student) 
    $stmt = $pdo->prepare("
        SELECT 
            s.shift,
            COUNT(DISTINCT s.id) as total_students,
            COALESCE(AVG(student_attendance.attendance_pct), 0) as attendance_percentage
        FROM students s
        LEFT JOIN (
            SELECT 
                student_id,
            ROUND(
                    (COUNT(CASE WHEN status IN ('Present', 'Check-in') THEN 1 END) * 100.0 / 
                    NULLIF(COUNT(*), 0)), 2
                ) as attendance_pct
            FROM attendance
            WHERE timestamp >= ? AND timestamp <= ?
            GROUP BY student_id
        ) student_attendance ON s.id = student_attendance.student_id
        WHERE s.is_active = 1
        GROUP BY s.shift
        ORDER BY s.shift
    ");
    
    $stmt->execute([$sessionRange['start'], $sessionRange['end']]);
    $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format percentages
    foreach ($stats as &$stat) {
        $stat['attendance_percentage'] = (float)$stat['attendance_percentage'];
        $stat['total_students'] = (int)$stat['total_students'];
    }
    
    return $stats;
}

/**
 * Get attendance trends (last N days)
 */
function getAttendanceTrends($pdo, $days = 7) {
    $days = max(1, min(365, (int)$days)); // Limit between 1 and 365 days
    
    // Get total active students count
    $totalStudents = $pdo->query("SELECT COUNT(*) FROM students WHERE is_active = 1")->fetchColumn();
    
    $stmt = $pdo->prepare("
        SELECT 
            DATE(timestamp) as date,
            COUNT(DISTINCT CASE WHEN status IN ('Present', 'Check-in') THEN student_id END) as present,
            COUNT(DISTINCT CASE WHEN status = 'Absent' THEN student_id END) as absent,
            (SELECT COUNT(*) FROM students WHERE is_active = 1) as total,
            ROUND(
                (COUNT(DISTINCT CASE WHEN status IN ('Present', 'Check-in') THEN student_id END) * 100.0 / 
                NULLIF((SELECT COUNT(*) FROM students WHERE is_active = 1), 0)), 2
            ) as percentage
        FROM attendance
        WHERE timestamp >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
        GROUP BY DATE(timestamp)
        ORDER BY DATE(timestamp) DESC
    ");
    
    $stmt->execute([$days - 1]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get attendance trends for current session
 */
function getAttendanceTrendsForSession($pdo) {
    $sessionRange = getActiveSessionRange($pdo);
    
    $stmt = $pdo->prepare("
        SELECT 
            DATE(timestamp) as date,
            COUNT(DISTINCT CASE WHEN status IN ('Present', 'Check-in') THEN student_id END) as present,
            COUNT(DISTINCT CASE WHEN status = 'Absent' THEN student_id END) as absent,
            (SELECT COUNT(*) FROM students WHERE is_active = 1) as total,
            ROUND(
                (COUNT(DISTINCT CASE WHEN status IN ('Present', 'Check-in') THEN student_id END) * 100.0 / 
                NULLIF((SELECT COUNT(*) FROM students WHERE is_active = 1), 0)), 2
            ) as percentage
        FROM attendance
        WHERE timestamp >= ? AND timestamp <= ?
        GROUP BY DATE(timestamp)
        ORDER BY DATE(timestamp) ASC
    ");
    
    $stmt->execute([$sessionRange['start'], $sessionRange['end']]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Generate attendance report
 */
function generateReport($pdo) {
    $type = $_GET['type'] ?? '';
    $fromDate = $_GET['from'] ?? '';
    $toDate = $_GET['to'] ?? '';
    $format = $_GET['format'] ?? 'pdf';
    $studentId = $_GET['student'] ?? '';
    $program = $_GET['program'] ?? '';
    $shift = $_GET['shift'] ?? '';
    $yearLevel = $_GET['year_level'] ?? '';
    $sectionId = $_GET['section'] ?? '';
    
    // Build query based on filters
    $whereConditions = [];
    $params = [];
    
    if ($fromDate) {
        $whereConditions[] = "DATE(a.timestamp) >= ?";
        $params[] = $fromDate;
    }
    
    if ($toDate) {
        $whereConditions[] = "DATE(a.timestamp) <= ?";
        $params[] = $toDate;
    }
    
    if ($studentId) {
        $whereConditions[] = "s.id = ?";
        $params[] = $studentId;
    }
    
    if ($program) {
        $whereConditions[] = "s.program = ?";
        $params[] = $program;
    }
    
    if ($shift) {
        $whereConditions[] = "s.shift = ?";
        $params[] = $shift;
    }
    
    if ($yearLevel) {
        $whereConditions[] = "s.year_level = ?";
        $params[] = $yearLevel;
    }
    
    if ($sectionId) {
        $whereConditions[] = "s.section_id = ?";
        $params[] = $sectionId;
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Get report data
    $query = "
        SELECT 
            s.student_id as 'Roll Number',
            s.name as 'Student Name',
            s.program as 'Program',
            s.shift as 'Shift',
            s.year_level as 'Year Level',
            sec.section_name as 'Section',
            COUNT(a.id) as 'Total Records',
            COUNT(CASE WHEN a.status IN ('Present', 'Check-in') THEN 1 END) as 'Present',
            COUNT(CASE WHEN a.status = 'Absent' THEN 1 END) as 'Absent',
            ROUND(
                (COUNT(CASE WHEN a.status IN ('Present', 'Check-in') THEN 1 END) * 100.0 / 
                NULLIF(COUNT(a.id), 0)), 2
            ) as 'Attendance %'
        FROM students s
        LEFT JOIN attendance a ON s.student_id = a.student_id
        LEFT JOIN sections sec ON s.section_id = sec.id
        $whereClause
        GROUP BY s.id, s.student_id, s.name, s.program, s.shift, s.year_level, sec.section_name
        ORDER BY s.name
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Export based on format
    $filters = [
        'type' => $type,
        'from' => $fromDate,
        'to' => $toDate,
        'program' => $program,
        'shift' => $shift,
        'year_level' => $yearLevel,
        'section' => $sectionId
    ];
    
    if ($format === 'csv') {
        exportCSV($data, 'attendance_report_' . date('Y-m-d') . '.csv', $filters);
    } elseif ($format === 'excel') {
        exportExcel($data, 'attendance_report_' . date('Y-m-d') . '.xlsx', $filters);
    } else {
        exportPDF($data, 'attendance_report_' . date('Y-m-d') . '.pdf', $filters);
    }
}

function exportCSV($data, $filename, $filters = []) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
    
    // Add filter information as comments
    if (!empty($filters)) {
        fputcsv($output, ['# Report Filters']);
        foreach ($filters as $key => $value) {
            if (!empty($value)) {
                fputcsv($output, ['# ' . ucfirst(str_replace('_', ' ', $key)) . ': ' . $value]);
            }
        }
        fputcsv($output, ['# Generated: ' . date('Y-m-d H:i:s')]);
        fputcsv($output, []); // Empty row
    }
    
    if (!empty($data)) {
        fputcsv($output, array_keys($data[0]));
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
    exit;
}

function exportExcel($data, $filename, $filters = []) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
    
    // Add filter information
    if (!empty($filters)) {
        fputcsv($output, ['Report Filters']);
        foreach ($filters as $key => $value) {
            if (!empty($value)) {
                fputcsv($output, [ucfirst(str_replace('_', ' ', $key)) . ': ' . $value]);
            }
        }
        fputcsv($output, ['Generated: ' . date('Y-m-d H:i:s')]);
        fputcsv($output, []); // Empty row
    }
    
    if (!empty($data)) {
        fputcsv($output, array_keys($data[0]));
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
    exit;
}

function exportPDF($data, $filename, $filters = []) {
    header('Content-Type: text/html; charset=UTF-8');
    
    $reportTitle = ucfirst($filters['type'] ?? 'Attendance') . ' Attendance Report';
    $dateRange = '';
    if (!empty($filters['from']) && !empty($filters['to'])) {
        $dateRange = "From: {$filters['from']} To: {$filters['to']}";
    } elseif (!empty($filters['from'])) {
        $dateRange = "From: {$filters['from']}";
    } elseif (!empty($filters['to'])) {
        $dateRange = "To: {$filters['to']}";
    }
    
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>' . $reportTitle . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1 { color: #333; text-align: center; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        .header { text-align: center; margin-bottom: 30px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 12px; }
        th, td { border: 1px solid #333; padding: 8px; text-align: left; }
        th { background-color: #f8f9fa; font-weight: bold; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .print-button { position: fixed; top: 10px; right: 10px; background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; }
        .print-button:hover { background: #0056b3; }
        @media print { .print-button { display: none; } }
    </style>
</head>
<body>
    <button class="print-button" onclick="window.print()">🖨️ Print to PDF</button>
    
    <div class="header">
        <h1>' . $reportTitle . '</h1>
        <p><strong>Generated on:</strong> ' . date('Y-m-d H:i:s') . '</p>';
    
    if ($dateRange) {
        $html .= '<p><strong>' . $dateRange . '</strong></p>';
    }
    
    // Add other filter information
    if (!empty($filters['program'])) {
        $html .= '<p><strong>Program:</strong> ' . htmlspecialchars($filters['program'] ?? '') . '</p>';
    }
    if (!empty($filters['shift'])) {
        $html .= '<p><strong>Shift:</strong> ' . htmlspecialchars($filters['shift'] ?? '') . '</p>';
    }
    if (!empty($filters['year_level'])) {
        $html .= '<p><strong>Year Level:</strong> ' . htmlspecialchars($filters['year_level'] ?? '') . '</p>';
    }
    if (!empty($filters['section'])) {
        $html .= '<p><strong>Section:</strong> ' . htmlspecialchars($filters['section'] ?? '') . '</p>';
    }
    
    $html .= '<p><strong>Total Records:</strong> ' . count($data) . '</p>
    </div>';
    
    if (!empty($data)) {
        $html .= '<table><thead><tr>';
        foreach (array_keys($data[0]) as $header) {
            $html .= '<th>' . htmlspecialchars($header ?? '') . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        
        foreach ($data as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= '<td>' . htmlspecialchars($cell ?? '') . '</td>';
            }
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
    }
    
    $html .= '</body></html>';
    
    echo $html;
    exit;
}

/**
 * Get monthly comparison data (last 6 months)
 */
function getMonthlyComparison($pdo) {
    $stmt = $pdo->query("
        SELECT 
            DATE_FORMAT(timestamp, '%b') as month,
            DATE_FORMAT(timestamp, '%Y-%m') as month_key,
            ROUND(
                (COUNT(CASE WHEN status IN ('Present', 'Check-in') THEN 1 END) * 100.0 / 
                NULLIF(COUNT(*), 0)), 2
            ) as avg_attendance
        FROM attendance
        WHERE timestamp >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
        GROUP BY DATE_FORMAT(timestamp, '%Y-%m'), DATE_FORMAT(timestamp, '%b')
        ORDER BY month_key ASC
    ");
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get active programs from database
 */
function getPrograms($pdo) {
    $stmt = $pdo->query("
        SELECT code, name 
        FROM programs 
        WHERE is_active = 1 
        ORDER BY code
    ");
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get available shifts
 */
function getShifts($pdo) {
    $stmt = $pdo->query("
        SELECT DISTINCT shift 
        FROM students 
        WHERE is_active = 1 AND shift IS NOT NULL
        ORDER BY shift
    ");
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get available year levels
 */
function getYearLevels($pdo) {
    $stmt = $pdo->query("
        SELECT DISTINCT year_level 
        FROM students 
        WHERE is_active = 1 AND year_level IS NOT NULL
        ORDER BY year_level
    ");
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get sections filtered by program, year level, and shift
 */
function getSections($pdo, $program = '', $yearLevel = '', $shift = '') {
    $whereConditions = ['s.is_active = 1'];
    $params = [];
    
    if ($program) {
        $whereConditions[] = "p.code = ?";
        $params[] = $program;
    }
    
    if ($yearLevel) {
        $whereConditions[] = "s.year_level = ?";
        $params[] = $yearLevel;
    }
    
    if ($shift) {
        $whereConditions[] = "s.shift = ?";
        $params[] = $shift;
    }
    
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    
    $query = "
        SELECT DISTINCT s.id, s.section_name, s.year_level, s.shift, p.code as program_code
        FROM sections s
        LEFT JOIN programs p ON s.program_id = p.id
        $whereClause
        ORDER BY s.section_name
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
