<?php
/**
 * Simplified Admin API for QR Code Attendance System
 * Development version with minimal authentication
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

// Include configuration
require_once 'config.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Simple authentication bypass for development
function requireAuth() {
    // For development, we'll allow access without strict authentication
    // In production, implement proper authentication
    return true;
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    
    // Require authentication for all admin operations
    requireAuth();
    
    switch ($action) {
        case 'dashboard':
            handleDashboard($pdo);
            break;
            
        case 'students':
            if ($method === 'GET') {
                getStudents($pdo);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            }
            break;
            
        case 'attendance':
            if ($method === 'GET') {
                getAttendance($pdo);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            }
            break;
            
        case 'sync_status':
            handleSyncStatus($pdo);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}

function handleDashboard($pdo) {
    try {
        $today = date('Y-m-d');
        
        // Today's attendance stats
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent
            FROM attendance 
            WHERE DATE(timestamp) = ?
        ");
        $stmt->execute([$today]);
        $todayStats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Total students count
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM students");
        $stmt->execute();
        $studentCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Recent activity (last 10 records)
        $stmt = $pdo->prepare("
            SELECT student_id, student_name, timestamp, status 
            FROM attendance 
            ORDER BY timestamp DESC 
            LIMIT 10
        ");
        $stmt->execute();
        $recent = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'today' => $todayStats,
                'students' => $studentCount,
                'recent' => $recent
            ]
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Dashboard error: ' . $e->getMessage()]);
    }
}

function getStudents($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT student_id, name, email, phone, created_at 
            FROM students 
            ORDER BY name ASC
        ");
        $stmt->execute();
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $students
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Error fetching students: ' . $e->getMessage()]);
    }
}

function getAttendance($pdo) {
    try {
        $limit = $_GET['limit'] ?? 100;
        $offset = $_GET['offset'] ?? 0;
        
        $stmt = $pdo->prepare("
            SELECT id, student_id, student_name, timestamp, status, created_at 
            FROM attendance 
            ORDER BY timestamp DESC 
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$limit, $offset]);
        $attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $attendance
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Error fetching attendance: ' . $e->getMessage()]);
    }
}

function handleSyncStatus($pdo) {
    try {
        // Get website stats
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM attendance");
        $stmt->execute();
        $websiteRecords = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM students");
        $stmt->execute();
        $studentCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        echo json_encode([
            'success' => true,
            'data' => [
                'website_records' => $websiteRecords,
                'student_count' => $studentCount,
                'last_sync' => date('Y-m-d H:i:s'),
                'database_status' => 'Connected'
            ]
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Error getting sync status: ' . $e->getMessage()]);
    }
}
?>
