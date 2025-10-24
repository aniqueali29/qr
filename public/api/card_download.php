<?php
/**
 * Card Download Handler for QR Code Attendance System
 * Handles secure download of generated student cards
 */

// Configure session to match admin panel settings BEFORE any other operations
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
ini_set('session.use_strict_mode', 1);
ini_set('session.gc_maxlifetime', 3600); // 1 hour
ini_set('session.name', 'QR_ATTENDANCE_SESSION'); // Match admin panel session name
ini_set('session.cookie_path', '/qr/'); // Match admin panel session path
ini_set('session.cookie_domain', '');

// Start session to access admin session data
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load config
require_once 'config.php';

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

// Check authentication - support both admin and regular session formats
$user_id = null;

// Check for admin session first (admin panel uses admin_user_id)
if (isset($_SESSION['admin_user_id']) && isset($_SESSION['admin_logged_in'])) {
    $user_id = $_SESSION['admin_user_id'];
}
// Check for regular user session
elseif (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
}

// Require authentication
if (!$user_id) {
    // Try alternative authentication method - check admin session in database
    $user_id = checkAdminSessionInDatabase();
    
    if (!$user_id) {
        http_response_code(401);
        die('Authentication required');
    }
}

// Get file parameters
$file = $_GET['file'] ?? '';
$filename = $_GET['filename'] ?? '';

if (empty($file) || empty($filename)) {
    http_response_code(400);
    die('Missing file parameters');
}

// Security check - ensure file is in temp directory
$temp_dir = sys_get_temp_dir() . '/qr_cards/';
$temp_dir_real = realpath($temp_dir);

// Check if file exists first
if (!file_exists($file)) {
    http_response_code(404);
    error_log("Card Download - File not found: " . $file);
    die('File not found');
}

$real_file_path = realpath($file);

// Validate file is in temp directory
if (!$real_file_path) {
    http_response_code(403);
    error_log("Card Download - Could not resolve file path: " . $file);
    die('Invalid file path');
}

// Check if file is in allowed directory (Windows-compatible check)
$file_dir = dirname($real_file_path);
if ($temp_dir_real && strpos($real_file_path, $temp_dir_real) !== 0) {
    http_response_code(403);
    error_log("Card Download - File not in temp directory. File: " . $real_file_path . ", Temp: " . $temp_dir_real);
    die('Invalid file path - security violation');
}

// Determine content type based on actual file extension (not the requested filename)
$actual_extension = strtolower(pathinfo($real_file_path, PATHINFO_EXTENSION));
$requested_extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

$content_types = [
    'png' => 'image/png',
    'pdf' => 'application/pdf',
    'html' => 'text/html',
    'zip' => 'application/zip'
];

// If the actual file is HTML but requested as PNG/PDF, display it inline for printing
if ($actual_extension === 'html' && in_array($requested_extension, ['png', 'pdf'])) {
    // Display HTML inline for browser printing
    header('Content-Type: text/html; charset=utf-8');
    
    // Add auto-print script for convenience
    $html_content = file_get_contents($real_file_path);
    
    // Add print instructions and button
    $print_script = '<div style="position: fixed; top: 10px; right: 10px; background: white; padding: 15px; border: 2px solid #1e3a8a; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.2); z-index: 9999; font-family: Arial, sans-serif;">
        <p style="margin: 0 0 10px 0; font-weight: bold; color: #1e3a8a;">Student Card Ready</p>
        <p style="margin: 0 0 10px 0; font-size: 14px;">Use your browser to save as ' . strtoupper($requested_extension) . ':</p>
        <button onclick="window.print()" style="background: #1e3a8a; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-size: 14px; width: 100%;">🖨️ Print / Save as ' . strtoupper($requested_extension) . '</button>
        <p style="margin: 10px 0 0 0; font-size: 11px; color: #666;">Tip: Choose "Save as PDF" or "Microsoft Print to PDF" in the print dialog</p>
    </div>
    <style>@media print { .print-controls { display: none !important; } }</style>';
    
    // Insert the print controls after the opening body tag
    $html_content = str_replace('<body>', '<body><div class="print-controls">' . $print_script . '</div>', $html_content);
    
    echo $html_content;
} else {
    // Regular file download
    $content_type = $content_types[$actual_extension] ?? 'application/octet-stream';
    
    header('Content-Type: ' . $content_type);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($real_file_path));
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
    
    readfile($real_file_path);
}

// Clean up file after download (optional - you might want to keep them for a while)
// unlink($real_file_path);

exit;
?>