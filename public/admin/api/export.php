<?php
/**
 * Export API for Admin Panel
 * Handles data export in various formats (CSV, Excel, PDF)
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

// Require admin authentication
requireAdminAuth();

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$format = $_GET['format'] ?? 'csv';

switch ($action) {
    case 'students':
        exportStudents($format, $pdo);
        break;
    case 'attendance':
        exportAttendance($format, $pdo);
        break;
    case 'programs':
        exportPrograms($format, $pdo);
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid export action']);
        break;
}

function exportStudents($format, $pdo) {
    try {
        // Get filter parameters
        $filters = [
            'program' => $_GET['program'] ?? '',
            'shift' => $_GET['shift'] ?? '',
            'year' => $_GET['year'] ?? '',
            'section' => $_GET['section'] ?? '',
            'search' => $_GET['search'] ?? ''
        ];
        
        // Build query with filters
        $whereConditions = [];
        $params = [];
        
        if (!empty($filters['program'])) {
            $whereConditions[] = "program = ?";
            $params[] = $filters['program'];
        }
        
        if (!empty($filters['shift'])) {
            $whereConditions[] = "shift = ?";
            $params[] = $filters['shift'];
        }
        
        if (!empty($filters['year'])) {
            $whereConditions[] = "year_level = ?";
            $params[] = $filters['year'];
        }
        
        if (!empty($filters['section'])) {
            $whereConditions[] = "section = ?";
            $params[] = $filters['section'];
        }
        
        if (!empty($filters['search'])) {
            $whereConditions[] = "(name LIKE ? OR student_id LIKE ? OR email LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $query = "
            SELECT 
                student_id as 'Roll Number',
                name as 'Full Name',
                email as 'Email',
                phone as 'Phone',
                program as 'Program',
                shift as 'Shift',
                year_level as 'Year Level',
                section as 'Section',
                admission_year as 'Admission Year',
                attendance_percentage as 'Attendance %',
                created_at as 'Created Date'
            FROM students 
            $whereClause
            ORDER BY created_at DESC
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($format === 'csv') {
            exportCSV($students, 'students_export_' . date('Y-m-d_H-i-s') . '.csv');
        } elseif ($format === 'excel') {
            exportExcel($students, 'students_export_' . date('Y-m-d_H-i-s') . '.xlsx');
        } elseif ($format === 'pdf') {
            exportPDF($students, 'students_export_' . date('Y-m-d_H-i-s') . '.pdf');
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid export format']);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Export failed: ' . $e->getMessage()]);
    }
}

function exportCSV($data, $filename) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8 support in Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    if (!empty($data)) {
        // Write headers
        fputcsv($output, array_keys($data[0]));
        
        // Write data rows
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
    exit;
}

function exportExcel($data, $filename) {
    // Excel export using CSV format with proper Excel headers
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8 support in Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    if (!empty($data)) {
        // Write headers
        fputcsv($output, array_keys($data[0]));
        
        // Write data rows
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
    exit;
}

function exportPDF($data, $filename) {
    // Since we don't have a PDF library, export as HTML that can be printed to PDF
    // This prevents the "Failed to load PDF document" error
    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Disposition: inline; filename="' . str_replace('.pdf', '.html', $filename) . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Create HTML content that can be printed to PDF
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Students Export Report</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px; 
            background: white;
        }
        h1 { 
            color: #333; 
            text-align: center; 
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        .header { 
            text-align: center; 
            margin-bottom: 30px; 
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 20px; 
            font-size: 12px;
        }
        th, td { 
            border: 1px solid #333; 
            padding: 8px; 
            text-align: left; 
        }
        th { 
            background-color: #f8f9fa; 
            font-weight: bold; 
            color: #333;
        }
        tr:nth-child(even) { 
            background-color: #f9f9f9; 
        }
        .footer { 
            text-align: center; 
            margin-top: 30px; 
            font-size: 10px; 
            color: #666; 
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        .print-button {
            position: fixed;
            top: 10px;
            right: 10px;
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        .print-button:hover {
            background: #0056b3;
        }
        @media print {
            .print-button { display: none; }
            body { margin: 0; }
        }
    </style>
</head>
<body>
    <button class="print-button" onclick="window.print()">🖨️ Print to PDF</button>
    
    <div class="header">
        <h1>Students Export Report</h1>
        <p><strong>Generated on:</strong> ' . date('Y-m-d H:i:s') . '</p>
        <p><strong>Total Records:</strong> ' . count($data) . '</p>
    </div>';
    
    if (!empty($data)) {
        $html .= '<table>
            <thead>
                <tr>';
        foreach (array_keys($data[0]) as $header) {
            $html .= '<th>' . htmlspecialchars($header) . '</th>';
        }
        $html .= '</tr>
            </thead>
            <tbody>';
        
        foreach ($data as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= '<td>' . htmlspecialchars($cell) . '</td>';
            }
            $html .= '</tr>';
        }
        
        $html .= '</tbody>
        </table>';
    } else {
        $html .= '<p>No data found.</p>';
    }
    
    $html .= '<div class="footer">
        <p>Generated by QR Attendance System | ' . date('Y-m-d H:i:s') . '</p>
    </div>
</body>
</html>';
    
    echo $html;
    exit;
}

function exportAttendance($format, $pdo) {
    // Implementation for attendance export
    echo json_encode(['success' => false, 'message' => 'Attendance export not implemented yet']);
}

function exportPrograms($format, $pdo) {
    try {
        // Get programs data
        $programsQuery = "
            SELECT 
                p.id as 'Program ID',
                p.code as 'Program Code',
                p.name as 'Program Name',
                p.description as 'Description',
                p.is_active as 'Active',
                p.created_at as 'Created Date',
                COUNT(s.id) as 'Total Sections'
            FROM programs p
            LEFT JOIN sections s ON p.id = s.program_id
            GROUP BY p.id, p.code, p.name, p.description, p.is_active, p.created_at
            ORDER BY p.created_at DESC
        ";
        
        $stmt = $pdo->prepare($programsQuery);
        $stmt->execute();
        $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get sections data
        $sectionsQuery = "
            SELECT 
                s.id as 'Section ID',
                s.section_name as 'Section Name',
                s.year_level as 'Year Level',
                s.shift as 'Shift',
                s.capacity as 'Capacity',
                s.is_active as 'Active',
                p.code as 'Program Code',
                p.name as 'Program Name',
                s.created_at as 'Created Date'
            FROM sections s
            JOIN programs p ON s.program_id = p.id
            ORDER BY p.name, s.year_level, s.section_name
        ";
        
        $stmt = $pdo->prepare($sectionsQuery);
        $stmt->execute();
        $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($format === 'csv') {
            exportProgramsCSV($programs, $sections, 'programs_sections_export_' . date('Y-m-d_H-i-s') . '.csv');
        } elseif ($format === 'excel') {
            exportProgramsExcel($programs, $sections, 'programs_sections_export_' . date('Y-m-d_H-i-s') . '.xlsx');
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid export format']);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Export failed: ' . $e->getMessage()]);
    }
}

function exportProgramsCSV($programs, $sections, $filename) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Write Programs sheet
    fputcsv($output, ['=== PROGRAMS DATA ===']);
    if (!empty($programs)) {
        fputcsv($output, array_keys($programs[0]));
        foreach ($programs as $row) {
            fputcsv($output, $row);
        }
    }
    
    // Add separator
    fputcsv($output, []);
    fputcsv($output, ['=== SECTIONS DATA ===']);
    
    // Write Sections sheet
    if (!empty($sections)) {
        fputcsv($output, array_keys($sections[0]));
        foreach ($sections as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
    exit;
}

function exportProgramsExcel($programs, $sections, $filename) {
    // Simple Excel export using CSV with .xlsx extension
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Write Programs sheet
    fputcsv($output, ['=== PROGRAMS DATA ===']);
    if (!empty($programs)) {
        fputcsv($output, array_keys($programs[0]));
        foreach ($programs as $row) {
            fputcsv($output, $row);
        }
    }
    
    // Add separator
    fputcsv($output, []);
    fputcsv($output, ['=== SECTIONS DATA ===']);
    
    // Write Sections sheet
    if (!empty($sections)) {
        fputcsv($output, array_keys($sections[0]));
        foreach ($sections as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
    exit;
}
?>
