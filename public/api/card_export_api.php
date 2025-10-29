<?php
/**
 * Card Export API for QR Code Attendance System
 * Handles individual and bulk student card exports in PNG and PDF formats
 */

// Configure session to match admin panel settings BEFORE any other operations
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
ini_set('session.use_strict_mode', 1);
ini_set('session.gc_maxlifetime', 3600); // 1 hour
ini_set('session.name', 'QR_ATTENDANCE_SESSION'); // Match admin panel session name
ini_set('session.cookie_path', '/qr/'); // Match admin panel session path
ini_set('session.cookie_domain', '');

// Start output buffering to catch any stray output
ob_start();

// Error handling - prevent HTML output
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to browser
ini_set('log_errors', 1); // Log errors instead

// Start session to access admin session data
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load config
require_once 'config.php';

// Load simplified QR code manager (no auth_system.php, no GD library)
require_once 'qr_code_manager_simple.php';


// Check if database connection exists
if (!isset($pdo) || !$pdo) {
    error_log("Card Export API - PDO not available");
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection not available']);
    exit;
}

// Check authentication - support both admin and regular session formats
$user_id = null;
$user_role = null;


// Check for admin session first (admin panel uses admin_user_id)
if (isset($_SESSION['admin_user_id']) && isset($_SESSION['admin_logged_in'])) {
    $user_id = $_SESSION['admin_user_id'];
    $user_role = $_SESSION['admin_role'] ?? 'admin';
}
// Check for regular user session
elseif (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['role'] ?? 'user';
}

// Require authentication
if (!$user_id) {
    // Try alternative authentication method - check admin session in database
    $user_id = checkAdminSessionInDatabase();
    
    if (!$user_id) {
        ob_clean();
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentication required. Please login.']);
        exit;
    }
}

// Store normalized user_id for use in the API
$_SESSION['export_user_id'] = $user_id;
$_SESSION['export_user_role'] = $user_role;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => 'Invalid action'];


    try {
        switch ($action) {
            case 'export_card':
                $response = handleCardExport();
                break;
            case 'check_qr_status':
                $response = checkQRStatus();
                break;
            default:
                $response = ['success' => false, 'message' => 'Invalid action: "' . $action . '" (received: ' . json_encode($_POST) . ')'];
        }
    } catch (Exception $e) {
        $response = ['success' => false, 'message' => 'Server error: ' . $e->getMessage()];
    } catch (Error $e) {
        $response = ['success' => false, 'message' => 'Fatal error: ' . $e->getMessage()];
    }

    // Clear any buffered output and send only JSON
    ob_clean();
    echo json_encode($response);
    exit;
}

// Default response
ob_clean();
echo json_encode(['success' => false, 'message' => 'Invalid request method']);

/**
 * Get branding information from settings
 */
function getBrandingInfo() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT setting_key, setting_value 
            FROM system_settings 
            WHERE setting_key IN ('sidebar_logo', 'project_name', 'project_short_name', 'project_tagline')
        ");
        $stmt->execute();
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // Debug: Log what we fetched
        error_log("Branding settings fetched: " . json_encode($settings));
        
        // Get logo - check if it exists
        $logo_path = $settings['sidebar_logo'] ?? '';
        $logo_url = '';
        
        if ($logo_path) {
            // $logo_path from database: "uploads/logos/filename.jpg"
            // We're in: public/api/
            // We need: ../uploads/logos/filename.jpg
            $logo_url = '../' . ltrim($logo_path, '/');
            
            // Verify file exists - check from public/uploads/
            $full_path = realpath(__DIR__ . '/../' . $logo_path);
            if (!$full_path || !file_exists($full_path)) {
                error_log("Logo file not found at: " . ($full_path ?? $logo_path));
                $logo_url = '';
            }
        }
        
        // Fallback to default logo if custom logo not found
        if (empty($logo_url)) {
            $logo_url = '../assets/img/logo.jpeg';
        }
        
        // Get project name - if empty, check if we need to use it at all
        $institution_name = !empty($settings['project_name']) ? $settings['project_name'] : 'JINNAH POLYTECHNIC INSTITUTE';
        
        $result = [
            'logo_url' => $logo_url,
            'institution_name' => $institution_name,
            'short_name' => $settings['project_short_name'] ?? 'JPI',
            'tagline' => $settings['project_tagline'] ?? ''
        ];
        
        // Debug: Log the final result
        error_log("Branding result: " . json_encode($result));
        error_log("Institution name being used: " . $institution_name);
        
        return $result;
    } catch (Exception $e) {
        error_log("Error getting branding info: " . $e->getMessage());
        // Return defaults on error
        return [
            'logo_url' => '../assets/img/logo.jpeg',
            'institution_name' => 'JINNAH POLYTECHNIC INSTITUTE',
            'short_name' => 'JPI',
            'tagline' => ''
        ];
    }
}

/**
 * Check admin session in database as fallback authentication
 */
function checkAdminSessionInDatabase() {
    global $pdo;
    
    try {
        // Check if we have any session cookies
        $session_id = null;
        $possible_session_names = ['QR_ATTENDANCE_SESSION', 'PHPSESSID', 'admin_session'];
        
        foreach ($possible_session_names as $session_name) {
            if (isset($_COOKIE[$session_name])) {
                $session_id = $_COOKIE[$session_name];
                break;
            }
        }
        
        if (!$session_id) {
            return null;
        }
        
        // Check if session exists in database and is valid
        $stmt = $pdo->prepare("
            SELECT s.user_id, u.role 
            FROM sessions s 
            JOIN users u ON s.user_id = u.id 
            WHERE s.id = ? AND u.role = 'admin' AND u.is_active = 1 
            AND s.last_activity > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute([$session_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return $result['user_id'];
        }
        
        return null;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Handle card export requests
 */
function handleCardExport() {
    global $pdo;
    
    try {
        
        $student_ids_raw = $_POST['student_ids'] ?? [];
        $format = $_POST['format'] ?? 'png';
        $type = $_POST['type'] ?? 'individual';
        
        
        // Handle JSON string input
        if (is_string($student_ids_raw)) {
            $student_ids = json_decode($student_ids_raw, true);
        } else {
            $student_ids = $student_ids_raw;
        }
        
        // Validate inputs
        if (empty($student_ids) || !is_array($student_ids)) {
            return ['success' => false, 'message' => 'Student IDs required'];
        }
        
        if ($format !== 'pdf') {
            return ['success' => false, 'message' => 'Only PDF format is supported'];
        }
        
        if (!in_array($type, ['individual', 'bulk'])) {
            return ['success' => false, 'message' => 'Invalid type. Use individual or bulk'];
        }
        
        // Validate student IDs exist
        
        $placeholders = str_repeat('?,', count($student_ids) - 1) . '?';
        $sql = "SELECT student_id, name, email, phone FROM students WHERE student_id IN ($placeholders) AND is_active = 1";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($student_ids);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        
        if (count($students) !== count($student_ids)) {
            return ['success' => false, 'message' => 'One or more students not found'];
        }
        
        // Ensure QR codes exist for all students
        $qr_manager = new QRCodeManager($pdo);
        $missing_qr_students = [];
        
        foreach ($students as $student) {
            $qr_result = $qr_manager->getStudentQR($student['student_id']);
            if (!$qr_result['success']) {
                // Generate QR code if missing
                $generate_result = $qr_manager->generateStudentQR($student['student_id'], $_SESSION['export_user_id'] ?? null);
                if (!$generate_result['success']) {
                    $missing_qr_students[] = $student['student_id'];
                }
            }
        }
        
        if (!empty($missing_qr_students)) {
            return [
                'success' => false, 
                'message' => 'Failed to generate QR codes for: ' . implode(', ', $missing_qr_students)
            ];
        }
        
        // Generate export files (PDF only)
        if ($type === 'individual' && count($student_ids) === 1) {
            return generateIndividualCard($students[0], 'pdf');
        } else {
            return generateBulkCards($students, 'pdf');
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Export failed: ' . $e->getMessage()];
    }
}

/**
 * Generate individual card (PDF only)
 */
function generateIndividualCard($student, $format) {
    $student_id = $student['student_id'];
    
    // Generate PDF card
    $filename = "card_{$student_id}_" . date('Y-m-d_H-i-s') . '.pdf';
    $filepath = generateCardPDF($student);
    
    if ($filepath) {
        return [
            'success' => true,
            'message' => 'Card generated successfully',
            'data' => [
                'download_url' => 'api/card_download.php?file=' . urlencode($filepath) . '&filename=' . urlencode($filename),
                'filename' => $filename
            ]
        ];
    } else {
        return ['success' => false, 'message' => 'Failed to generate card PDF'];
    }
}

/**
 * Generate bulk cards (PDF only)
 */
function generateBulkCards($students, $format) {
    $timestamp = date('Y-m-d_H-i-s');
    
    // Generate single PDF with all cards
    $pdf_filename = "student_cards_bulk_{$timestamp}.pdf";
    $pdf_filepath = generateBulkPDF($students, $pdf_filename);
    
    if ($pdf_filepath) {
        return [
            'success' => true,
            'message' => 'Bulk cards generated successfully',
            'data' => [
                'download_url' => 'api/card_download.php?file=' . urlencode($pdf_filepath) . '&filename=' . urlencode($pdf_filename),
                'filename' => $pdf_filename,
                'count' => count($students)
            ]
        ];
    } else {
        return ['success' => false, 'message' => 'Failed to generate bulk PDF'];
    }
}


/**
 * Generate card PDF
 */
function generateCardPDF($student) {
    $student_id = $student['student_id'];
    
    // Create temporary HTML file for PDF generation
    $temp_dir = sys_get_temp_dir() . '/qr_cards/';
    if (!is_dir($temp_dir)) {
        mkdir($temp_dir, 0755, true);
    }
    
    $temp_html = $temp_dir . "card_{$student_id}_" . time() . '.html';
    $temp_pdf = $temp_dir . "card_{$student_id}_" . time() . '.pdf';
    
    // Generate card HTML with print styles
    $card_html = generateCardHTML($student, true);
    file_put_contents($temp_html, $card_html);
    
    // For now, return the HTML file path - the frontend will handle PDF generation
    return $temp_html;
}


/**
 * Generate bulk PDF
 */
function generateBulkPDF($students, $pdf_filename) {
    $temp_dir = sys_get_temp_dir() . '/qr_cards/';
    if (!is_dir($temp_dir)) {
        mkdir($temp_dir, 0755, true);
    }
    
    $temp_html = $temp_dir . $pdf_filename . '.html';
    
    // Generate HTML with multiple cards in grid layout
    $html = generateBulkCardHTML($students);
    file_put_contents($temp_html, $html);
    
    return $temp_html;
}

/**
 * Generate card HTML (PDF only)
 */
function generateCardHTML($student, $for_pdf = false) {
    $student_id = $student['student_id'];
    $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($student_id) . "&margin=10";
    $current_year = date('Y');
    $card_id = 'AC' . strtoupper(substr(md5($student_id), 0, 8));
    
    // Get branding information
    $branding = getBrandingInfo();
    
    // Debug: Ensure branding has values
    error_log("generateCardHTML - branding data: " . json_encode($branding));
    
    $print_styles = '';
    if ($for_pdf) {
        $print_styles = '
            @media print {
                body { 
                    background: white; 
                    padding: 0; 
                    margin: 0; 
                }
                .print-controls { display: none !important; }
                .attendance-card { 
                    box-shadow: none; 
                    border: 1px solid #ddd; 
                    page-break-inside: avoid;
                }
            }
            @page { 
                size: A4; 
                margin: 15mm; 
            }
        ';
    }
    
    return '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Student Attendance Card - ' . htmlspecialchars($student['name']) . '</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
                background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%);
                min-height: 100vh;
                padding: 20px;
                display: flex;
                justify-content: center;
                align-items: center;
            }
            .attendance-card {
                width: 85.6mm;
                height: 55mm;
                background: white;
                border-radius: 12px;
                box-shadow: 0 10px 40px rgba(0,0,0,0.3);
                overflow: hidden;
                position: relative;
                display: flex;
                flex-direction: column;
            }
            .card-header {
                background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
                padding: 6px 8px;
                display: flex;
                align-items: center;
                gap: 6px;
                color: white;
            }
            .logo-section {
                width: 25px;
                height: 25px;
                background: white;
                border-radius: 4px;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
                overflow: hidden;
            }
            .logo-section img {
                width: 100%;
                height: 100%;
                object-fit: contain;
            }
            .header-text {
                flex: 1;
                text-align: center;
            }
            .institution-name {
                font-size: 9.5px;
                font-weight: 700;
                letter-spacing: 0.5px;
                margin-bottom: 1px;
                text-shadow: 0 1px 2px rgba(0,0,0,0.2);
            }
            .card-type {
                font-size: 7px;
                font-weight: 500;
                opacity: 0.95;
                text-transform: uppercase;
                letter-spacing: 0.3px;
            }
            .card-body {
                padding: 6px 10px;
                display: flex;
                gap: 8px;
                flex: 1;
            }
            .qr-section {
                width: 38mm;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
            }
            .qr-code-container {
                background: white;
                padding: 3px;
                border-radius: 6px;
                border: 2px solid #1e3a8a;
            }
            .qr-code-container img {
                display: block;
                width: 32mm;
                height: 32mm;
            }
            .student-info {
                flex: 1;
                display: flex;
                flex-direction: column;
                justify-content: center;
                gap: 3px;
            }
            .info-item {
                font-size: 7px;
                line-height: 1.3;
            }
            .info-label {
                color: #666;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.3px;
            }
            .info-value {
                color: #333;
                font-weight: 700;
                font-size: 8px;
                margin-top: 1px;
                word-wrap: break-word;
            }
            .student-name {
                font-size: 9.5px !important;
                color: #1e3a8a !important;
                font-weight: 800 !important;
                margin-bottom: 3px;
                line-height: 1.2;
            }
            .card-footer {
                background: #f8f9fa;
                padding: 3px 8px;
                font-size: 6px;
                color: #666;
                text-align: center;
                border-top: 1px solid #e0e0e0;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .scan-text {
                font-size: 5.5px;
                color: #1e3a8a;
                font-weight: 600;
                text-align: center;
                margin-top: 2px;
            }
            ' . $print_styles . '
        </style>
    </head>
    <body>
        <div class="attendance-card">
            <div class="card-header">
                <div class="logo-section">
                    <img src="' . htmlspecialchars($branding['logo_url']) . '" alt="' . htmlspecialchars($branding['short_name']) . ' Logo" />
                </div>
                <div class="header-text">
                    <div class="institution-name">' . htmlspecialchars($branding['institution_name']) . '</div>
                    <div class="card-type">Student Attendance Card</div>
                </div>
            </div>
            
            <div class="card-body">
                <div class="qr-section">
                    <div>
                        <div class="qr-code-container">
                            <img src="' . $qr_url . '" alt="QR Code" />
                        </div>
                        <div class="scan-text">SCAN FOR ATTENDANCE</div>
                    </div>
                </div>
                
                <div class="student-info">
                    <div class="info-item">
                        <div class="info-value student-name">' . htmlspecialchars($student['name']) . '</div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Student ID</div>
                        <div class="info-value">' . htmlspecialchars($student_id) . '</div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Email</div>
                        <div class="info-value">' . htmlspecialchars($student['email']) . '</div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Phone</div>
                        <div class="info-value">' . htmlspecialchars($student['phone'] ?? '') . '</div>
                    </div>
                </div>
            </div>
            
            <div class="card-footer">
                <span>Valid: ' . $current_year . '-' . ($current_year + 1) . '</span>
                <span>Card ID: ' . $card_id . '</span>
            </div>
        </div>
    </body>
    </html>';
}

/**
 * Generate bulk card HTML for PDF
 */
function generateBulkCardHTML($students, $filename = null) {
    // Get branding information
    $branding = getBrandingInfo();
    
    $html = '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Student Cards - Bulk Export</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { 
                font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; 
                background: white; 
                margin: 0;
                padding: 0;
            }
            .page {
                width: 210mm;
                min-height: 297mm;
                padding: 8mm 10mm;
                margin: 0 auto;
                background: white;
                page-break-after: always;
            }
            .page:last-child {
                page-break-after: avoid;
            }
            .cards-grid {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 6mm 8mm;
                width: 100%;
            }
            .attendance-card {
                width: 85.6mm;
                height: 55mm;
                background: white;
                border-radius: 8px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                overflow: hidden;
                position: relative;
                display: flex;
                flex-direction: column;
                border: 1px solid #ddd;
                page-break-inside: avoid;
            }
            .card-header {
                background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
                padding: 6px 8px;
                display: flex;
                align-items: center;
                gap: 6px;
                color: white;
            }
            .logo-section {
                width: 25px;
                height: 25px;
                background: white;
                border-radius: 4px;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
                overflow: hidden;
            }
            .logo-section img {
                width: 100%;
                height: 100%;
                object-fit: contain;
            }
            .header-text {
                flex: 1;
                text-align: center;
            }
            .institution-name {
                font-size: 9.5px;
                font-weight: 700;
                letter-spacing: 0.5px;
                margin-bottom: 1px;
                text-shadow: 0 1px 2px rgba(0,0,0,0.2);
            }
            .card-type {
                font-size: 7px;
                font-weight: 500;
                opacity: 0.95;
                text-transform: uppercase;
                letter-spacing: 0.3px;
            }
            .card-body {
                padding: 6px 10px;
                display: flex;
                gap: 8px;
                flex: 1;
            }
            .qr-section {
                width: 38mm;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
            }
            .qr-code-container {
                background: white;
                padding: 3px;
                border-radius: 6px;
                border: 2px solid #1e3a8a;
            }
            .qr-code-container img {
                display: block;
                width: 32mm;
                height: 32mm;
            }
            .student-info {
                flex: 1;
                display: flex;
                flex-direction: column;
                justify-content: center;
                gap: 3px;
            }
            .info-item {
                font-size: 7px;
                line-height: 1.3;
            }
            .info-label {
                color: #666;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.3px;
            }
            .info-value {
                color: #333;
                font-weight: 700;
                font-size: 8px;
                margin-top: 1px;
                word-wrap: break-word;
            }
            .student-name {
                font-size: 9.5px !important;
                color: #1e3a8a !important;
                font-weight: 800 !important;
                margin-bottom: 3px;
                line-height: 1.2;
            }
            .card-footer {
                background: #f8f9fa;
                padding: 3px 8px;
                font-size: 6px;
                color: #666;
                text-align: center;
                border-top: 1px solid #e0e0e0;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .scan-text {
                font-size: 5.5px;
                color: #1e3a8a;
                font-weight: 600;
                text-align: center;
                margin-top: 2px;
            }
            @page { 
                size: A4 portrait;
                margin: 8mm 10mm;
            }
            @media print {
                body { 
                    margin: 0;
                    padding: 0;
                }
                .page {
                    margin: 0;
                    padding: 8mm 10mm;
                }
                .attendance-card { 
                    box-shadow: none; 
                    border: 1px solid #ddd;
                    page-break-inside: avoid;
                }
                .print-controls {
                    display: none !important;
                }
            }
            @media screen {
                .page {
                    margin: 20px auto;
                    box-shadow: 0 0 10px rgba(0,0,0,0.1);
                }
            }
        </style>
    </head>
    <body>';
    
    $cards_per_page = 10; // 2 columns x 4 rows per A4 page
    $current_page = 0;
    $card_count = 0;
    
    foreach ($students as $index => $student) {
        if ($card_count % $cards_per_page === 0) {
            if ($current_page > 0) {
                $html .= '</div></div>'; // Close grid and page
            }
            $html .= '<div class="page"><div class="cards-grid">';
            $current_page++;
        }
        
        $student_id = $student['student_id'];
        $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($student_id) . "&margin=10";
        $current_year = date('Y');
        $card_id = 'AC' . strtoupper(substr(md5($student_id), 0, 8));
        
        $html .= '
        <div class="attendance-card">
            <div class="card-header">
                <div class="logo-section">
                    <img src="' . htmlspecialchars($branding['logo_url']) . '" alt="' . htmlspecialchars($branding['short_name']) . ' Logo" />
                </div>
                <div class="header-text">
                    <div class="institution-name">' . htmlspecialchars($branding['institution_name']) . '</div>
                    <div class="card-type">Student Attendance Card</div>
                </div>
            </div>
            
            <div class="card-body">
                <div class="qr-section">
                    <div>
                        <div class="qr-code-container">
                            <img src="' . $qr_url . '" alt="QR Code" />
                        </div>
                        <div class="scan-text">SCAN FOR ATTENDANCE</div>
                    </div>
                </div>
                
                <div class="student-info">
                    <div class="info-item">
                        <div class="info-value student-name">' . htmlspecialchars($student['name']) . '</div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Student ID</div>
                        <div class="info-value">' . htmlspecialchars($student_id) . '</div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Email</div>
                        <div class="info-value">' . htmlspecialchars($student['email']) . '</div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Phone</div>
                        <div class="info-value">' . htmlspecialchars($student['phone'] ?? '') . '</div>
                    </div>
                </div>
            </div>
            
            <div class="card-footer">
                <span>Valid: ' . $current_year . '-' . ($current_year + 1) . '</span>
                <span>Card ID: ' . $card_id . '</span>
            </div>
        </div>';
        
        $card_count++;
    }
    
    // Close the last page and grid
    $html .= '</div></div></body></html>';
    
    return $html;
}

/**
 * Check QR status for students
 */
function checkQRStatus() {
    global $pdo;
    
    try {
        $student_ids_raw = $_POST['student_ids'] ?? [];
        
        // Handle JSON string input
        if (is_string($student_ids_raw)) {
            $student_ids = json_decode($student_ids_raw, true);
        } else {
            $student_ids = $student_ids_raw;
        }
        
        if (empty($student_ids) || !is_array($student_ids)) {
            return ['success' => false, 'message' => 'Student IDs required'];
        }
        
        // Check if qr_codes table exists
        $stmt = $pdo->prepare("SHOW TABLES LIKE 'qr_codes'");
        $stmt->execute();
        $table_exists = $stmt->fetch();
        
        if (!$table_exists) {
            // If table doesn't exist, return all students as not having QR
            $placeholders = str_repeat('?,', count($student_ids) - 1) . '?';
            $stmt = $pdo->prepare("
                SELECT 
                    s.student_id,
                    0 as has_qr
                FROM students s
                WHERE s.student_id IN ($placeholders) AND s.is_active = 1
            ");
            $stmt->execute($student_ids);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $placeholders = str_repeat('?,', count($student_ids) - 1) . '?';
            $stmt = $pdo->prepare("
                SELECT 
                    s.student_id,
                    CASE WHEN q.id IS NOT NULL THEN 1 ELSE 0 END as has_qr
                FROM students s
                LEFT JOIN qr_codes q ON s.student_id = q.student_id AND q.is_active = 1
                WHERE s.student_id IN ($placeholders) AND s.is_active = 1
            ");
            $stmt->execute($student_ids);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        return [
            'success' => true,
            'data' => $results
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Failed to check QR status: ' . $e->getMessage()];
    }
}

?>
