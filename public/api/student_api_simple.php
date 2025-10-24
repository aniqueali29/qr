<?php
/**
 * Simplified Student API for QR Code Attendance System
 * Development version with minimal authentication
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include configuration
require_once 'config.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Simple authentication bypass for development
function requireStudentAuth() {
    // For development, we'll allow access without strict authentication
    // In production, implement proper authentication
    return true;
}

// Set a default student for development
$default_student_id = '24-SWT-01'; // Use the first student from the database

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    // Require authentication for all student operations
    requireStudentAuth();
    
    if ($method === 'POST') {
        switch ($action) {
            case 'generate_qr':
                $student_id = $_POST['student_id'] ?? $default_student_id;
                $response = generateStudentQR($student_id);
                break;
                
            case 'update_profile':
                $response = ['success' => false, 'message' => 'Profile update not implemented in simple mode'];
                break;
                
            case 'change_password':
                $response = ['success' => false, 'message' => 'Password change not implemented in simple mode'];
                break;
                
            default:
                $response = ['success' => false, 'message' => 'Invalid action: ' . $action];
        }
        
        echo json_encode($response);
        exit;
    }
    
    if ($method === 'GET') {
        switch ($action) {
            case 'get_qr':
                $student_id = $_GET['student_id'] ?? $default_student_id;
                $response = getStudentQR($student_id);
                break;
                
            case 'get_stats':
                $student_id = $_GET['student_id'] ?? $default_student_id;
                $response = getStudentStats($student_id);
                break;
                
            case 'get_history':
                $student_id = $_GET['student_id'] ?? $default_student_id;
                $response = getStudentHistory($student_id);
                break;
                
            case 'get_profile':
                $response = getStudentProfile($default_student_id);
                break;
                
            case 'get_all':
                $response = getAllStudents();
                break;
                
            default:
                $response = ['success' => false, 'message' => 'Invalid action: ' . $action];
        }
        
        echo json_encode($response);
        exit;
    }
    
    // Default response
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

/**
 * Generate QR code for student
 */
function generateStudentQR($student_id) {
    global $pdo;
    
    try {
        // Check if student exists and is active
        $stmt = $pdo->prepare("
            SELECT s.student_id, s.name, s.email 
            FROM students s 
            WHERE s.student_id = ? AND s.is_active = 1
        ");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$student) {
            return ['success' => false, 'message' => 'Student not found'];
        }
        
        // Create QR data - only student ID for scanning
        $qr_data = $student['student_id'];
        
        // Generate QR code (simplified)
        $filename = 'qr_' . $student_id . '_' . time() . '.png';
        $qr_path = dirname(__DIR__) . '/assets/img/qr_codes/'; // Absolute path
        $filepath = $qr_path . $filename;
        
        // Create directory if it doesn't exist
        if (!is_dir($qr_path)) {
            mkdir($qr_path, 0755, true);
        }
        
        // Create a QR code using qr-server.com API (more reliable)
        $qr_data_encoded = urlencode($qr_data);
        $qr_size = 200;
        $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size={$qr_size}x{$qr_size}&data=" . $qr_data_encoded;
        
        $qr_html = '
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { 
                    margin: 0; 
                    padding: 10px; 
                    font-family: Arial, sans-serif; 
                    background: white; 
                    width: 240px; 
                    height: 240px; 
                    overflow: hidden;
                }
                .qr-container { 
                    width: 220px; 
                    height: 220px; 
                    border: 2px solid #000; 
                    display: flex; 
                    align-items: center; 
                    justify-content: center; 
                    background: white;
                    margin: 0 auto;
                }
                .qr-image { 
                    width: 200px; 
                    height: 200px; 
                    border: 1px solid #ccc;
                    display: block;
                }
            </style>
        </head>
        <body>
            <div class="qr-container">
                <img src="' . $qr_url . '" alt="QR Code" class="qr-image" />
            </div>
        </body>
        </html>';
        
        // Save as HTML file instead of PNG
        $html_filepath = str_replace('.png', '.html', $filepath);
        file_put_contents($html_filepath, $qr_html);
        
        // Create a simple text-based QR representation
        $qr_text = "QR Code for Student: " . $student_id . "\nGenerated: " . date('Y-m-d H:i:s');
        file_put_contents($filepath, $qr_text);
        
        // Deactivate old QR codes
        $stmt = $pdo->prepare("UPDATE qr_codes SET is_active = 0 WHERE student_id = ?");
        $stmt->execute([$student_id]);
        
        // Get the first user ID for the foreign key constraint
        $stmt = $pdo->prepare("SELECT id FROM users LIMIT 1");
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $created_by = $user ? $user['id'] : NULL;
        
        // Store QR code in database
        $stmt = $pdo->prepare("
            INSERT INTO qr_codes (student_id, qr_data, qr_image_path, created_by) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$student_id, $qr_data, $filepath, $created_by]);
        
        return [
            'success' => true,
            'message' => 'QR code generated successfully',
            'data' => [
                'student_id' => $student_id,
                'qr_data' => $qr_data,
                'image_path' => $filepath,
                'image_url' => 'assets/img/qr_codes/' . str_replace('.png', '.html', $filename),
                'generated_at' => date('Y-m-d H:i:s')
            ]
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'QR generation failed: ' . $e->getMessage()];
    }
}

/**
 * Get student QR code
 */
function getStudentQR($student_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT qr_data, qr_image_path, generated_at 
            FROM qr_codes 
            WHERE student_id = ? AND is_active = 1 
            ORDER BY generated_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$student_id]);
        $qr = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$qr) {
            return ['success' => false, 'message' => 'No active QR code found'];
        }
        
        // Convert PNG path to HTML path
        $html_filename = str_replace('.png', '.html', basename($qr['qr_image_path']));
        
        return [
            'success' => true,
            'data' => [
                'student_id' => $student_id,
                'qr_data' => $qr['qr_data'],
                'image_path' => $qr['qr_image_path'],
                'image_url' => 'assets/img/qr_codes/' . $html_filename,
                'generated_at' => $qr['generated_at']
            ]
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Failed to get QR code: ' . $e->getMessage()];
    }
}

/**
 * Get student attendance statistics
 */
function getStudentStats($student_id) {
    global $pdo;
    
    try {
        // Get total attendance records
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_days,
                SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_days,
                SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_days
            FROM attendance 
            WHERE student_id = ?
        ");
        $stmt->execute([$student_id]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Calculate percentage
        $percentage = 0;
        if ($stats['total_days'] > 0) {
            $percentage = round(($stats['present_days'] / $stats['total_days']) * 100, 1);
        }
        
        return [
            'success' => true,
            'data' => [
                'total_days' => (int)$stats['total_days'],
                'present_days' => (int)$stats['present_days'],
                'absent_days' => (int)$stats['absent_days'],
                'percentage' => $percentage
            ]
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Failed to get statistics: ' . $e->getMessage()];
    }
}

/**
 * Get student attendance history
 */
function getStudentHistory($student_id, $limit = 50) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT student_id, student_name, timestamp, status 
            FROM attendance 
            WHERE student_id = ? 
            ORDER BY timestamp DESC 
            LIMIT ?
        ");
        $stmt->execute([$student_id, $limit]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'data' => $history
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Failed to get attendance history: ' . $e->getMessage()];
    }
}

/**
 * Get student profile
 */
function getStudentProfile($student_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT s.student_id, s.name, s.email, s.phone, s.created_at
            FROM students s
            WHERE s.student_id = ?
        ");
        $stmt->execute([$student_id]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$profile) {
            return ['success' => false, 'message' => 'Profile not found'];
        }
        
        return [
            'success' => true,
            'data' => $profile
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Failed to get profile: ' . $e->getMessage()];
    }
}

/**
 * Get all students
 */
function getAllStudents() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("
            SELECT s.id, s.student_id, s.name, s.email, s.phone, s.is_active, 
                   s.created_at, s.updated_at, s.admission_year, s.current_year, 
                   s.shift, s.program, s.is_graduated, s.last_year_update
            FROM students s
            ORDER BY s.student_id
        ");
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'students' => $students,
            'count' => count($students)
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Failed to get students: ' . $e->getMessage()];
    }
}
?>
