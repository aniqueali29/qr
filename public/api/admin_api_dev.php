<?php
/**
 * Development Admin API for QR Code Attendance System
 * No authentication required for development/testing
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

// No authentication required for development
function requireAuth() {
    return true;
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    
    // No authentication check for development
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
            
        case 'save_student':
            if ($method === 'POST') {
                saveStudent($pdo);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            }
            break;
            
        case 'delete_student':
            if ($method === 'DELETE') {
                deleteStudent($pdo);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            }
            break;
            
        case 'save_attendance':
            if ($method === 'POST') {
                saveAttendance($pdo);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            }
            break;
            
        case 'delete_attendance':
            if ($method === 'DELETE') {
                deleteAttendance($pdo);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            }
            break;
            
        case 'export_data':
            if ($method === 'GET') {
                exportData($pdo);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action: ' . $action]);
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

function saveStudent($pdo) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['student_id']) || !isset($input['name'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Student ID and name are required']);
            return;
        }
        
        // Check if student exists
        $stmt = $pdo->prepare("SELECT id FROM students WHERE student_id = ?");
        $stmt->execute([$input['student_id']]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Update existing student
            $stmt = $pdo->prepare("
                UPDATE students 
                SET name = ?, email = ?, phone = ? 
                WHERE student_id = ?
            ");
            $stmt->execute([
                $input['name'],
                $input['email'] ?? null,
                $input['phone'] ?? null,
                $input['student_id']
            ]);
        } else {
            // Insert new student
            $stmt = $pdo->prepare("
                INSERT INTO students (student_id, name, email, phone, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $input['student_id'],
                $input['name'],
                $input['email'] ?? null,
                $input['phone'] ?? null
            ]);
        }
        
        echo json_encode(['success' => true, 'message' => 'Student saved successfully']);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Error saving student: ' . $e->getMessage()]);
    }
}

function deleteStudent($pdo) {
    try {
        $student_id = $_GET['student_id'] ?? '';
        
        if (empty($student_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Student ID is required']);
            return;
        }
        
        // Delete student and related attendance records
        $pdo->beginTransaction();
        
        try {
            // Delete attendance records first
            $stmt = $pdo->prepare("DELETE FROM attendance WHERE student_id = ?");
            $stmt->execute([$student_id]);
            
            // Delete student
            $stmt = $pdo->prepare("DELETE FROM students WHERE student_id = ?");
            $stmt->execute([$student_id]);
            
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Student deleted successfully']);
            
        } catch (Exception $e) {
            $pdo->rollback();
            throw $e;
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Error deleting student: ' . $e->getMessage()]);
    }
}

function saveAttendance($pdo) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['student_id']) || !isset($input['student_name']) || 
            !isset($input['timestamp']) || !isset($input['status'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'All attendance fields are required']);
            return;
        }
        
        // Check if record exists
        $stmt = $pdo->prepare("SELECT id FROM attendance WHERE student_id = ? AND timestamp = ?");
        $stmt->execute([$input['student_id'], $input['timestamp']]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Update existing record
            $stmt = $pdo->prepare("
                UPDATE attendance 
                SET student_name = ?, status = ? 
                WHERE id = ?
            ");
            $stmt->execute([
                $input['student_name'],
                $input['status'],
                $existing['id']
            ]);
        } else {
            // Insert new record
            $stmt = $pdo->prepare("
                INSERT INTO attendance (student_id, student_name, timestamp, status, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $input['student_id'],
                $input['student_name'],
                $input['timestamp'],
                $input['status']
            ]);
        }
        
        echo json_encode(['success' => true, 'message' => 'Attendance record saved successfully']);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Error saving attendance: ' . $e->getMessage()]);
    }
}

function deleteAttendance($pdo) {
    try {
        $id = $_GET['id'] ?? '';
        
        if (empty($id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Attendance ID is required']);
            return;
        }
        
        $stmt = $pdo->prepare("DELETE FROM attendance WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true, 'message' => 'Attendance record deleted successfully']);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Error deleting attendance: ' . $e->getMessage()]);
    }
}

function exportData($pdo) {
    try {
        $format = $_GET['format'] ?? 'csv';
        $type = $_GET['type'] ?? 'attendance';
        
        if ($type === 'attendance') {
            $stmt = $pdo->prepare("
                SELECT student_id, student_name, timestamp, status 
                FROM attendance 
                ORDER BY timestamp DESC
            ");
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if ($format === 'csv') {
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="attendance_export_' . date('Y-m-d') . '.csv"');
                
                $output = fopen('php://output', 'w');
                fputcsv($output, ['Student ID', 'Name', 'Timestamp', 'Status']);
                
                foreach ($data as $row) {
                    fputcsv($output, $row);
                }
                
                fclose($output);
            } else {
                echo json_encode([
                    'success' => true,
                    'data' => $data
                ]);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid export type']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Error exporting data: ' . $e->getMessage()]);
    }
}
?>
