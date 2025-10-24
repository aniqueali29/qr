<?php
/**
 * Comprehensive Admin API for QR Code Attendance System
 * Handles all admin operations including CRUD for students and attendance
 */

header('Content-Type: application/json');
// CORS: restrict to configured origin
if (!function_exists('setCorsHeaders')) {
    require_once 'config.php';
}
setCorsHeaders();

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include configuration and authentication system
require_once 'config.php';

// Start session with secure settings
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
    ini_set('session.use_strict_mode', 1);
    session_start();
}

// Bridge admin dashboard session to API session (unify auth context)
if (!isset($_SESSION['role']) && isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    $_SESSION['role'] = 'admin';
    if (!isset($_SESSION['user_id']) && isset($_SESSION['admin_user_id'])) {
        $_SESSION['user_id'] = $_SESSION['admin_user_id'];
    }
    if (!isset($_SESSION['session_id']) && isset($_SESSION['admin_session_id'])) {
        $_SESSION['session_id'] = $_SESSION['admin_session_id'];
    }
}

// Authentication check using the new auth system
function requireAuth() {
    global $pdo;
    
    // Check if user is logged in and has admin role
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Authentication required', 'message' => 'Please log in as an admin']);
        exit();
    }
    
    // Verify session is still valid (best-effort). If no DB-backed session, allow based on role.
    $sessionId = $_SESSION['session_id'] ?? '';
    if (!empty($sessionId)) {
        try {
            $stmt = $pdo->prepare("
                SELECT s.id, s.last_activity, u.is_active 
                FROM sessions s 
                JOIN users u ON s.user_id = u.id 
                WHERE s.id = ? AND s.user_id = ? AND u.is_active = 1
            ");
            $stmt->execute([$sessionId, $_SESSION['user_id']]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($session) {
                $last_activity = strtotime($session['last_activity']);
                if (time() - $last_activity > 3600) {
                    http_response_code(401);
                    echo json_encode(['success' => false, 'error' => 'Session expired', 'message' => 'Please log in again']);
                    exit();
                }
                $stmt = $pdo->prepare("UPDATE sessions SET last_activity = NOW() WHERE id = ?");
                $stmt->execute([$sessionId]);
            }
            // If no matching DB session found, proceed based on role (fallback)
        } catch (Exception $e) {
            // On DB error, proceed based on role (do not block admin)
        }
    }
}

try {
    // Database connection is already established in config.php
    
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    
    // Require authentication for all admin operations
    requireAuth();
    
    // Enforce CSRF on mutating requests
    requireCsrfForMethods(['POST','PUT','PATCH','DELETE']);
    
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
            
        case 'save_student':
            if ($method === 'POST') {
                saveStudent($pdo);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            }
            break;
            
        case 'create_student_account':
            if ($method === 'POST') {
                createStudentAccount($pdo);
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
            
        case 'attendance':
            if ($method === 'GET') {
                getAttendance($pdo);
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
            
        case 'sync_status':
            handleSyncStatus($pdo);
            break;
            
        case 'push_to_offline':
            if ($method === 'POST') {
                pushToOffline($pdo);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            }
            break;
            
        case 'pull_from_offline':
            if ($method === 'POST') {
                pullFromOffline($pdo);
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
            
        case 'get_filtered_students':
            if ($method === 'GET') {
                getFilteredStudents($pdo);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            }
            break;
            
        case 'get_analytics_data':
            if ($method === 'GET') {
                getAnalyticsData($pdo);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            }
            break;
            
        case 'export_students':
            if ($method === 'GET') {
                exportStudents($pdo);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            }
            break;
            
        case 'get_programs':
            if ($method === 'GET') {
                getPrograms($pdo);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            }
            break;
            
        case 'get_sections':
            if ($method === 'GET') {
                getSections($pdo);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            }
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
        echo json_encode(['error' => 'Dashboard error: ' . $e->getMessage()]);
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
        echo json_encode(['error' => 'Error fetching students: ' . $e->getMessage()]);
    }
}

function saveStudent($pdo) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['student_id']) || !isset($input['name'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Student ID and name are required']);
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
        
        // Legacy sync removed
        
        echo json_encode(['success' => true, 'message' => 'Student saved successfully']);
        
    } catch (Exception $e) {
        echo json_encode(['error' => 'Error saving student: ' . $e->getMessage()]);
    }
}

function deleteStudent($pdo) {
    try {
        $student_id = $_GET['student_id'] ?? '';
        
        if (empty($student_id)) {
            http_response_code(400);
            echo json_encode(['error' => 'Student ID is required']);
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
            
            // Legacy sync removed
            
            echo json_encode(['success' => true, 'message' => 'Student deleted successfully']);
            
        } catch (Exception $e) {
            $pdo->rollback();
            throw $e;
        }
        
    } catch (Exception $e) {
        echo json_encode(['error' => 'Error deleting student: ' . $e->getMessage()]);
    }
}

function createStudentAccount($pdo) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Validate required fields
        if (!isset($input['student_id']) || !isset($input['name']) || !isset($input['email'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Student ID, name, and email are required']);
            return;
        }
        
        $student_id = trim($input['student_id']);
        $name = trim($input['name']);
        $email = trim($input['email']);
        $phone = trim($input['phone'] ?? '');
        $program = trim($input['program'] ?? '');
        $shift = trim($input['shift'] ?? '');
        $year_level = trim($input['year_level'] ?? '');
        $section = trim($input['section'] ?? '');
        $admission_year = intval($input['admission_year'] ?? 0);
        
        // Auto-generate password
        $password = generatePassword();
        
        // Parse roll number using the roll parser service
        $roll_data = parseRollNumberData($student_id, $pdo);
        if (!$roll_data['success']) {
            echo json_encode(['success' => false, 'error' => $roll_data['error']]);
            return;
        }
        
        $parsed_data = $roll_data['data'];
        
        // Use parsed data to override form data if needed
        if (empty($program)) {
            $program = $parsed_data['program_code'];
        }
        if (empty($shift)) {
            $shift = $parsed_data['shift'];
        }
        if (empty($year_level)) {
            $year_level = $parsed_data['year_level'];
        }
        if ($admission_year === 0) {
            $admission_year = $parsed_data['admission_year'];
        }
        
        // Validate program exists
        if (!empty($program)) {
            $stmt = $pdo->prepare("SELECT id FROM programs WHERE code = ? AND is_active = TRUE");
            $stmt->execute([$program]);
            if (!$stmt->fetch()) {
                echo json_encode(['success' => false, 'error' => 'Invalid program. Please select a valid program.']);
                return;
            }
        }
        
        // Validate shift
        if (!empty($shift) && !in_array($shift, ['Morning', 'Evening'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid shift. Use: Morning or Evening']);
            return;
        }
        
        // Validate year level
        if (!empty($year_level) && !in_array($year_level, ['1st', '2nd', '3rd'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid year level. Use: 1st, 2nd, or 3rd']);
            return;
        }
        
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'error' => 'Invalid email format']);
            return;
        }
        
        // Check if student already exists
        $stmt = $pdo->prepare("SELECT id FROM students WHERE student_id = ?");
        $stmt->execute([$student_id]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Student with this ID already exists']);
            return;
        }
        
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Email address already in use']);
            return;
        }
        
        // Get section ID if program, year, section, and shift are provided
        $section_id = null;
        if (!empty($program) && !empty($year_level) && !empty($section) && !empty($shift)) {
            $stmt = $pdo->prepare("
                SELECT sec.id 
                FROM sections sec 
                JOIN programs p ON sec.program_id = p.id 
                WHERE p.code = ? AND sec.year_level = ? AND sec.section_name = ? AND sec.shift = ? AND sec.is_active = TRUE
            ");
            $stmt->execute([$program, $year_level, $section, $shift]);
            $section_result = $stmt->fetch(PDO::FETCH_ASSOC);
            $section_id = $section_result ? $section_result['id'] : null;
        }
        
        // Start transaction
        $pdo->beginTransaction();
        
        try {
            // Create user account
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $username = strtolower(str_replace('-', '', $student_id)); // Convert 25-SWT-01 to 25swt01
            
            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, password_hash, role, student_id, is_active, created_at) 
                VALUES (?, ?, ?, 'student', ?, 1, NOW())
            ");
            $stmt->execute([$username, $email, $password_hash, $student_id]);
            $user_id = $pdo->lastInsertId();
            
            // Create student record with new fields
            $roll_prefix = explode('-', $student_id)[1];
            
            $stmt = $pdo->prepare("
                INSERT INTO students (student_id, name, email, phone, user_id, program, shift, year_level, section, section_id, admission_year, roll_prefix, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $student_id, $name, $email, $phone, $user_id, $program, $shift, $year_level, $section, 
                $section_id, $admission_year, $roll_prefix
            ]);
            
            $pdo->commit();
            
            // Legacy sync removed; keep generated password in response
            $generated_password = $password;
            
            echo json_encode([
                'success' => true, 
                'message' => 'Student account created successfully',
                'data' => [
                    'student_id' => $student_id,
                    'name' => $name,
                    'email' => $email,
                    'username' => $username,
                    'password' => $generated_password // Include the auto-generated password in response
                ]
            ]);
            
        } catch (Exception $e) {
            $pdo->rollback();
            throw $e;
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Error creating student account: ' . $e->getMessage()]);
    }
}

function generatePassword($length = 12) {
    // Generate a secure random password
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    $password = '';
    
    // Ensure at least one character from each category
    $password .= 'abcdefghijklmnopqrstuvwxyz'[random_int(0, 25)]; // lowercase
    $password .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'[random_int(0, 25)]; // uppercase
    $password .= '0123456789'[random_int(0, 9)]; // number
    $password .= '!@#$%^&*'[random_int(0, 7)]; // special character
    
    // Fill the rest with random characters
    for ($i = 4; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    
    // Shuffle the password to randomize positions
    return str_shuffle($password);
}

// Legacy sync functions removed - data is now stored in database only

function saveToSyncData($student_id, $name, $email, $phone, $password = null) {
    try {
        $sync_file = 'sync_data.json';
        $sync_data = [];
        
        // Load existing sync data
        if (file_exists($sync_file)) {
            $content = file_get_contents($sync_file);
            if (!empty($content)) {
                $sync_data = json_decode($content, true) ?: [];
            }
        }
        
        // Initialize sync data structure
        if (!isset($sync_data['students'])) {
            $sync_data['students'] = [];
        }
        
        // Add new student to sync data including password
        $student_data = [
            'student_id' => $student_id,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => 'admin'
        ];
        
        // Include password if provided
        if ($password !== null) {
            $student_data['password'] = $password;
        }
        
        $sync_data['students'][] = $student_data;
        
        // Update timestamp
        $sync_data['timestamp'] = date('Y-m-d H:i:s');
        
        // Save sync data
        file_put_contents($sync_file, json_encode($sync_data, JSON_PRETTY_PRINT));
        error_log("Successfully saved student $student_id to sync_data.json with password");
        
    } catch (Exception $e) {
        error_log("Error saving to sync_data.json: " . $e->getMessage());
    }
}

// Legacy sync functions removed

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
        echo json_encode(['error' => 'Error fetching attendance: ' . $e->getMessage()]);
    }
}

function saveAttendance($pdo) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['student_id']) || !isset($input['timestamp']) || !isset($input['status'])) {
            http_response_code(400);
            echo json_encode(['error' => 'student_id, timestamp, and status are required']);
            return;
        }
        
        // Check if record exists
        $stmt = $pdo->prepare("SELECT id FROM attendance WHERE student_id = ? AND timestamp = ?");
        $stmt->execute([$input['student_id'], $input['timestamp']]);
        $existing = $stmt->fetch();
        
        // Fallback: look up student name if not provided
        $student_name = $input['student_name'] ?? '';
        if ($student_name === '') {
            $sn = $pdo->prepare("SELECT name FROM students WHERE student_id = ?");
            $sn->execute([$input['student_id']]);
            $row = $sn->fetch(PDO::FETCH_ASSOC);
            $student_name = $row['name'] ?? '';
        }
        
        if ($existing) {
            // Update existing record
            $stmt = $pdo->prepare("
                UPDATE attendance 
                SET student_name = ?, status = ? 
                WHERE id = ?
            ");
            $stmt->execute([
                $student_name,
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
                $student_name,
                $input['timestamp'],
                $input['status']
            ]);
        }
        
        echo json_encode(['success' => true, 'message' => 'Attendance record saved successfully']);
        
    } catch (Exception $e) {
        echo json_encode(['error' => 'Error saving attendance: ' . $e->getMessage()]);
    }
}

function deleteAttendance($pdo) {
    try {
        $id = $_GET['id'] ?? '';
        
        if (empty($id)) {
            http_response_code(400);
            echo json_encode(['error' => 'Attendance ID is required']);
            return;
        }
        
        $stmt = $pdo->prepare("DELETE FROM attendance WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true, 'message' => 'Attendance record deleted successfully']);
        
    } catch (Exception $e) {
        echo json_encode(['error' => 'Error deleting attendance: ' . $e->getMessage()]);
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
        
        // Get last sync time from sync data file
        $lastSync = 'Never';
        if (file_exists('sync_data.json')) {
            $syncData = json_decode(file_get_contents('sync_data.json'), true);
            if ($syncData && isset($syncData['timestamp'])) {
                $lastSync = $syncData['timestamp'];
            }
        }
        
        echo json_encode([
            'success' => true,
            'data' => [
                'website_records' => $studentCount,  // Show student count instead of attendance count
                'student_count' => $studentCount,
                'attendance_records' => $websiteRecords,
                'last_sync' => $lastSync,
                'database_status' => 'Connected'
            ]
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['error' => 'Error getting sync status: ' . $e->getMessage()]);
    }
}

function pushToOffline($pdo) {
    try {
        // Get all students and attendance data
        $stmt = $pdo->prepare("SELECT * FROM students");
        $stmt->execute();
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->prepare("SELECT * FROM attendance ORDER BY timestamp DESC");
        $stmt->execute();
        $attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Create sync data file for offline systems
        $syncData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'students' => $students,
            'attendance' => $attendance
        ];
        
        // Save to file that offline systems can read
        file_put_contents('sync_data.json', json_encode($syncData, JSON_PRETTY_PRINT));
        
        echo json_encode([
            'success' => true,
            'message' => 'Data pushed to offline systems successfully',
            'data' => [
                'students_count' => count($students),
                'attendance_count' => count($attendance)
            ]
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['error' => 'Error pushing to offline: ' . $e->getMessage()]);
    }
}

function pullFromOffline($pdo) {
    try {
        // This would typically read from offline system files
        // For now, we'll simulate by reading from sync_data.json if it exists
        
        if (file_exists('sync_data.json')) {
            $syncData = json_decode(file_get_contents('sync_data.json'), true);
            
            if ($syncData && isset($syncData['students']) && isset($syncData['attendance'])) {
                $pdo->beginTransaction();
                
                try {
                    // Update students
                    foreach ($syncData['students'] as $student) {
                        $stmt = $pdo->prepare("
                            INSERT INTO students (student_id, name, email, phone, created_at) 
                            VALUES (?, ?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE 
                            name = VALUES(name), 
                            email = VALUES(email), 
                            phone = VALUES(phone)
                        ");
                        $stmt->execute([
                            $student['student_id'],
                            $student['name'],
                            $student['email'] ?? null,
                            $student['phone'] ?? null,
                            $student['created_at'] ?? date('Y-m-d H:i:s')
                        ]);
                    }
                    
                    // Update attendance
                    foreach ($syncData['attendance'] as $record) {
                        $stmt = $pdo->prepare("
                            INSERT INTO attendance (student_id, student_name, timestamp, status, created_at) 
                            VALUES (?, ?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE 
                            student_name = VALUES(student_name), 
                            status = VALUES(status)
                        ");
                        $stmt->execute([
                            $record['student_id'],
                            $record['student_name'],
                            $record['timestamp'],
                            $record['status'],
                            $record['created_at'] ?? date('Y-m-d H:i:s')
                        ]);
                    }
                    
                    $pdo->commit();
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Data pulled from offline systems successfully',
                        'data' => [
                            'students_updated' => count($syncData['students']),
                            'attendance_updated' => count($syncData['attendance'])
                        ]
                    ]);
                    
                } catch (Exception $e) {
                    $pdo->rollback();
                    throw $e;
                }
            } else {
                echo json_encode(['error' => 'Invalid sync data format']);
            }
        } else {
            echo json_encode(['error' => 'No offline sync data found']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['error' => 'Error pulling from offline: ' . $e->getMessage()]);
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
            echo json_encode(['error' => 'Invalid export type']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['error' => 'Error exporting data: ' . $e->getMessage()]);
    }
}

/**
 * Get filtered students with advanced filtering
 */
function getFilteredStudents($pdo) {
    try {
        $program = $_GET['program'] ?? '';
        $shift = $_GET['shift'] ?? '';
        $year_level = $_GET['year_level'] ?? '';
        $section = $_GET['section'] ?? '';
        $attendance_min = $_GET['attendance_min'] ?? '';
        $attendance_max = $_GET['attendance_max'] ?? '';
        $search = $_GET['search'] ?? '';
        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 25);
        $offset = ($page - 1) * $limit;
        
        $sql = "
            SELECT 
                s.student_id,
                s.name,
                s.email,
                s.phone,
                s.program,
                s.shift,
                s.year_level,
                s.section,
                s.attendance_percentage,
                s.admission_year,
                s.last_year_update,
                p.name as program_name,
                sec.capacity,
                COUNT(a.id) as total_attendance,
                SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present_count
            FROM students s
            LEFT JOIN programs p ON s.program = p.code
            LEFT JOIN sections sec ON s.section_id = sec.id
            LEFT JOIN attendance a ON s.student_id = a.student_id
            WHERE 1=1
        ";
        
        $params = [];
        
        if (!empty($program)) {
            $sql .= " AND s.program = ?";
            $params[] = $program;
        }
        
        if (!empty($shift)) {
            $sql .= " AND s.shift = ?";
            $params[] = $shift;
        }
        
        if (!empty($year_level)) {
            $sql .= " AND s.year_level = ?";
            $params[] = $year_level;
        }
        
        if (!empty($section)) {
            $sql .= " AND s.section = ?";
            $params[] = $section;
        }
        
        if (!empty($attendance_min)) {
            $sql .= " AND s.attendance_percentage >= ?";
            $params[] = $attendance_min;
        }
        
        if (!empty($attendance_max)) {
            $sql .= " AND s.attendance_percentage <= ?";
            $params[] = $attendance_max;
        }
        
        if (!empty($search)) {
            $sql .= " AND (s.student_id LIKE ? OR s.name LIKE ? OR s.email LIKE ?)";
            $search_term = "%$search%";
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }
        
        $sql .= " GROUP BY s.student_id, s.name, s.email, s.phone, s.program, s.shift, s.year_level, s.section, s.attendance_percentage, s.admission_year, s.last_year_update, p.name, sec.capacity";
        $sql .= " ORDER BY s.name ASC";
        $sql .= " LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate current year level on-the-fly for each student
        $current_date = new DateTime();
        $current_academic_year = $current_date->format('Y');
        
        // Academic year starts in September
        if ($current_date->format('n') < 9) {
            $current_academic_year--;
        }
        
        foreach ($students as &$student) {
            if (!empty($student['admission_year'])) {
                $admission_year = intval($student['admission_year']);
                $years_in_program = $current_academic_year - $admission_year + 1;
                
                // Calculate correct year level
                $calculated_year_level = min(max($years_in_program, 1), 3);
                $is_completed = $years_in_program > 3;
                $calculated_status = $is_completed ? 'Completed' : $calculated_year_level . 'st';
                
                // Update the year_level to the calculated value
                $student['year_level'] = $calculated_status;
                $student['calculated_year_level'] = $calculated_status;
                $student['needs_update'] = ($student['year_level'] !== $calculated_status);
                $student['years_in_program'] = $years_in_program;
            } else {
                // If no admission year, try to parse from roll number
                $roll_data = parseRollNumberData($student['student_id'], $pdo);
                if ($roll_data['success']) {
                    $student['year_level'] = $roll_data['data']['year_level'];
                    $student['calculated_year_level'] = $roll_data['data']['year_level'];
                    $student['needs_update'] = true;
                }
            }
        }
        unset($student); // Break reference
        
        // Get total count for pagination
        $count_sql = str_replace("SELECT s.student_id, s.name, s.email, s.phone, s.program, s.shift, s.year_level, s.section, s.attendance_percentage, s.admission_year, s.last_year_update, p.name as program_name, sec.capacity, COUNT(a.id) as total_attendance, SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present_count", "SELECT COUNT(DISTINCT s.student_id)", $sql);
        $count_sql = str_replace("GROUP BY s.student_id, s.name, s.email, s.phone, s.program, s.shift, s.year_level, s.section, s.attendance_percentage, s.admission_year, s.last_year_update, p.name, sec.capacity", "", $count_sql);
        $count_sql = str_replace("ORDER BY s.name ASC", "", $count_sql);
        $count_sql = str_replace("LIMIT ? OFFSET ?", "", $count_sql);
        
        $count_params = array_slice($params, 0, -2); // Remove limit and offset
        $stmt = $pdo->prepare($count_sql);
        $stmt->execute($count_params);
        $total = $stmt->fetchColumn();
        
        echo json_encode([
            'success' => true,
            'data' => $students,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $total,
                'total_pages' => ceil($total / $limit)
            ]
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Error fetching filtered students: ' . $e->getMessage()]);
    }
}

/**
 * Get analytics data for dashboard
 */
function getAnalyticsData($pdo) {
    try {
        $date_from = $_GET['date_from'] ?? date('Y-m-01'); // First day of current month
        $date_to = $_GET['date_to'] ?? date('Y-m-d');
        $program = $_GET['program'] ?? '';
        $shift = $_GET['shift'] ?? '';
        
        // Overall statistics
        $stats = [];
        
        // Total students by program
        $sql = "SELECT program, COUNT(*) as count FROM students GROUP BY program";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $stats['students_by_program'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Students by shift
        $sql = "SELECT shift, COUNT(*) as count FROM students GROUP BY shift";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $stats['students_by_shift'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Students by year level
        $sql = "SELECT year_level, COUNT(*) as count FROM students GROUP BY year_level";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $stats['students_by_year'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Attendance trends (last 30 days)
        $sql = "
            SELECT 
                DATE(timestamp) as date,
                COUNT(*) as total_records,
                SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_count,
                ROUND((SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as attendance_rate
            FROM attendance 
            WHERE DATE(timestamp) BETWEEN ? AND ?
            GROUP BY DATE(timestamp)
            ORDER BY date DESC
            LIMIT 30
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$date_from, $date_to]);
        $stats['attendance_trends'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Program-wise attendance
        $sql = "
            SELECT 
                s.program,
                COUNT(a.id) as total_attendance,
                SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present_count,
                ROUND((SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) / COUNT(a.id)) * 100, 2) as attendance_rate
            FROM students s
            LEFT JOIN attendance a ON s.student_id = a.student_id
            WHERE DATE(a.timestamp) BETWEEN ? AND ?
            GROUP BY s.program
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$date_from, $date_to]);
        $stats['program_attendance'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Section capacity utilization
        $sql = "
            SELECT 
                sec.section_name,
                p.code as program_code,
                p.name as program_name,
                sec.year_level,
                sec.shift,
                sec.capacity,
                COUNT(s.student_id) as current_students,
                ROUND((COUNT(s.student_id) / sec.capacity) * 100, 2) as utilization_rate
            FROM sections sec
            JOIN programs p ON sec.program_id = p.id
            LEFT JOIN students s ON s.section_id = sec.id
            WHERE sec.is_active = TRUE
            GROUP BY sec.id, sec.section_name, p.code, p.name, sec.year_level, sec.shift, sec.capacity
            ORDER BY utilization_rate DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $stats['section_utilization'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $stats
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Error fetching analytics: ' . $e->getMessage()]);
    }
}

/**
 * Export students with filters
 */
function exportStudents($pdo) {
    try {
        $program = $_GET['program'] ?? '';
        $shift = $_GET['shift'] ?? '';
        $year_level = $_GET['year_level'] ?? '';
        $format = $_GET['format'] ?? 'csv';
        
        $sql = "
            SELECT 
                s.student_id,
                s.name,
                s.email,
                s.phone,
                s.program,
                s.shift,
                s.year_level,
                s.section,
                s.attendance_percentage,
                p.name as program_name
            FROM students s
            LEFT JOIN programs p ON s.program = p.code
            WHERE 1=1
        ";
        
        $params = [];
        
        if (!empty($program)) {
            $sql .= " AND s.program = ?";
            $params[] = $program;
        }
        
        if (!empty($shift)) {
            $sql .= " AND s.shift = ?";
            $params[] = $shift;
        }
        
        if (!empty($year_level)) {
            $sql .= " AND s.year_level = ?";
            $params[] = $year_level;
        }
        
        $sql .= " ORDER BY s.name ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($format === 'csv') {
            $filename = 'students_export_' . date('Y-m-d_H-i-s') . '.csv';
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            
            $output = fopen('php://output', 'w');
            fputcsv($output, array_keys($students[0] ?? []));
            
            foreach ($students as $student) {
                fputcsv($output, $student);
            }
            
            fclose($output);
        } else {
            echo json_encode([
                'success' => true,
                'data' => $students
            ]);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Error exporting students: ' . $e->getMessage()]);
    }
}

/**
 * Get programs list
 */
function getPrograms($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                p.*,
                COUNT(DISTINCT s.student_id) as total_students,
                COUNT(DISTINCT sec.id) as total_sections
            FROM programs p
            LEFT JOIN sections sec ON p.id = sec.program_id AND sec.is_active = TRUE
            LEFT JOIN students s ON s.section_id = sec.id
            GROUP BY p.id, p.code, p.name, p.description, p.is_active, p.created_at, p.updated_at
            ORDER BY p.name ASC
        ");
        $stmt->execute();
        $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $programs
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Error fetching programs: ' . $e->getMessage()]);
    }
}

/**
 * Get sections list
 */
function getSections($pdo) {
    try {
        $program_id = $_GET['program_id'] ?? '';
        $year_level = $_GET['year_level'] ?? '';
        $shift = $_GET['shift'] ?? '';
        
        $sql = "
            SELECT 
                sec.*,
                p.code as program_code,
                p.name as program_name,
                COUNT(s.student_id) as current_students,
                ROUND((COUNT(s.student_id) / sec.capacity) * 100, 2) as capacity_utilization
            FROM sections sec
            JOIN programs p ON sec.program_id = p.id
            LEFT JOIN students s ON s.section_id = sec.id
            WHERE sec.is_active = TRUE
        ";
        
        $params = [];
        
        if (!empty($program_id)) {
            $sql .= " AND sec.program_id = ?";
            $params[] = $program_id;
        }
        
        if (!empty($year_level)) {
            $sql .= " AND sec.year_level = ?";
            $params[] = $year_level;
        }
        
        if (!empty($shift)) {
            $sql .= " AND sec.shift = ?";
            $params[] = $shift;
        }
        
        $sql .= " GROUP BY sec.id, sec.program_id, sec.year_level, sec.section_name, sec.shift, sec.capacity, sec.is_active, sec.created_at, sec.updated_at, p.code, p.name";
        $sql .= " ORDER BY p.name, sec.year_level, sec.shift, sec.section_name";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $sections
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Error fetching sections: ' . $e->getMessage()]);
    }
}

/**
 * Parse roll number data using the same logic as roll_parser_service.php
 */
function parseRollNumberData($roll_number, $pdo) {
    try {
        // Parse roll number format: YY-[E]PROGRAM-NN
        $pattern = '/^(\d{2})-E?([A-Z]{2,10})-(\d{2})$/';
        if (!preg_match($pattern, $roll_number, $matches)) {
            return [
                'success' => false, 
                'error' => 'Invalid roll number format. Use: YY-PROGRAM-NN or YY-EPROGRAM-NN'
            ];
        }
        
        $year_part = $matches[1];
        $program_part = $matches[2];
        $serial_part = $matches[3];
        
        // Determine if it's evening shift (has E prefix)
        $is_evening = strpos($roll_number, '-E') !== false;
        $shift = $is_evening ? 'Evening' : 'Morning';
        
        // Convert 2-digit year to 4-digit year
        $admission_year = 2000 + intval($year_part);
        
        // Validate admission year (must be reasonable)
        $current_year = date('Y');
        if ($admission_year > $current_year || $admission_year < ($current_year - 10)) {
            return [
                'success' => false, 
                'error' => 'Invalid admission year. Must be between ' . ($current_year - 10) . ' and ' . $current_year
            ];
        }
        
        // Get program details from database
        $stmt = $pdo->prepare("SELECT id, code, name FROM programs WHERE code = ? AND is_active = TRUE");
        $stmt->execute([$program_part]);
        $program = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$program) {
            return [
                'success' => false, 
                'error' => 'Unknown program code: ' . $program_part
            ];
        }
        
        // Calculate current year level
        $current_date = new DateTime();
        $current_academic_year = $current_date->format('Y');
        
        // Academic year starts in September
        if ($current_date->format('n') < 9) {
            $current_academic_year--;
        }
        
        $years_in_program = $current_academic_year - $admission_year + 1;
        $year_level = min(max($years_in_program, 1), 3);
        
        // Determine if student has completed the program
        $is_completed = $years_in_program > 3;
        $status = $is_completed ? 'Completed' : $year_level . 'st';
        
        return [
            'success' => true,
            'data' => [
                'roll_number' => $roll_number,
                'admission_year' => $admission_year,
                'program_id' => $program['id'],
                'program_code' => $program['code'],
                'program_name' => $program['name'],
                'shift' => $shift,
                'serial_number' => $serial_part,
                'year_level' => $status,
                'is_completed' => $is_completed,
                'years_in_program' => $years_in_program
            ]
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Error parsing roll number: ' . $e->getMessage()
        ];
    }
}
?>
