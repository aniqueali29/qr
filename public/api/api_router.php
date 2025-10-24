<?php
/**
 * API Router - Automatically routes to development APIs when authentication fails
 * This provides a seamless fallback for development
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Get the requested API endpoint
$request_uri = $_SERVER['REQUEST_URI'];
$path_info = parse_url($request_uri, PHP_URL_PATH);
$path_parts = explode('/', trim($path_info, '/'));

// Determine which API to route to
$api_type = $path_parts[2] ?? 'admin'; // admin, student, etc.
$action = $_GET['action'] ?? '';

// Route to appropriate development API
switch ($api_type) {
    case 'admin':
        // Route to development admin API
        $_SERVER['REQUEST_URI'] = '/qr_attendance/public/api/admin_api_dev.php';
        include 'admin_api_dev.php';
        break;
        
    case 'student':
        // Route to development student API
        $_SERVER['REQUEST_URI'] = '/qr_attendance/public/api/student_api_simple.php';
        include 'student_api_simple.php';
        break;
        
    default:
        // Default to admin API
        $_SERVER['REQUEST_URI'] = '/qr_attendance/public/api/admin_api_dev.php';
        include 'admin_api_dev.php';
        break;
}
?>
