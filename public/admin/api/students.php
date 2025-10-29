<?php
/**
 * Students API
 * Handles student CRUD operations + semester/session filters
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

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

// Require admin authentication
if (!isAdminLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit();
}

$action = $_GET['action'] ?? 'list';

try {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            handleGetRequest($action);
            break;
        case 'POST':
            handlePostRequest($action);
            break;
        case 'PUT':
            handlePutRequest($action);
            break;
        case 'DELETE':
            handleDeleteRequest($action);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}

function handleGetRequest($action) {
    switch ($action) {
        case 'list':
            echo json_encode(getStudentsList());
            break;
        case 'view':
            echo json_encode(getStudentDetails());
            break;
        case 'export':
            echo json_encode(exportStudents());
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
}

function handlePostRequest($action) {
    switch ($action) {
        case 'create':
            echo json_encode(createStudent());
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
}

function handlePutRequest($action) {
    switch ($action) {
        case 'update':
            echo json_encode(updateStudent());
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
}

function handleDeleteRequest($action) {
    switch ($action) {
        case 'delete':
            echo json_encode(deleteStudent());
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
}

/**
 * Get students list with pagination and filters (program, shift, year_level, current_semester, session)
 */
function getStudentsList() {
    global $pdo;
    
    try {
        $page = (int)($_GET['page'] ?? 1);
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 25;
        $limit = min(max($limit, 1), 1000);
        $offset = ($page - 1) * $limit;
        
        $where = ["s.is_active = 1"]; $params = [];
        $joinSession = false;
        
        // Filters
        if (!empty($_GET['program'])) { $where[] = "s.program = ?"; $params[] = $_GET['program']; }
        if (!empty($_GET['shift'])) { $where[] = "s.shift = ?"; $params[] = $_GET['shift']; }
        // Accept both 'year' and 'year_level' string labels (e.g., 'Semester 1')
        if (!empty($_GET['year'])) { $where[] = "s.year_level = ?"; $params[] = $_GET['year']; }
        if (!empty($_GET['year_level'])) { $where[] = "s.year_level = ?"; $params[] = $_GET['year_level']; }
        // Numeric semester filter
        if (!empty($_GET['semester']) || !empty($_GET['current_semester'])) {
            $sem = (int)($_GET['current_semester'] ?? $_GET['semester']);
            if ($sem > 0) { $where[] = "s.current_semester = ?"; $params[] = $sem; }
        }
        // Enrollment session filter: accept id or code
        if (!empty($_GET['session'])) {
            $sessionParam = trim($_GET['session']);
            if (ctype_digit($sessionParam)) {
                $where[] = "s.enrollment_session_id = ?"; $params[] = (int)$sessionParam;
            } else {
                $joinSession = true;
                $where[] = "sess.code = ?"; $params[] = $sessionParam;
            }
        }
        if (!empty($_GET['section'])) { $where[] = "s.section = ?"; $params[] = $_GET['section']; }
        if (!empty($_GET['search'])) {
            $search = filter_var($_GET['search'], FILTER_SANITIZE_SPECIAL_CHARS);
            if (strlen($search) > 100) { return ['success' => false, 'error' => 'Search term too long']; }
            $search = str_replace(['%','_'], ['\\%','\\_'], $search);
            $where[] = "(s.student_id LIKE ? OR s.name LIKE ? OR s.email LIKE ?)";
            $like = "%$search%"; array_push($params, $like, $like, $like);
        }
        $whereClause = implode(' AND ', $where);
        
        // Count
        $countSql = "SELECT COUNT(*) AS total FROM students s" . ($joinSession ? " LEFT JOIN enrollment_sessions sess ON sess.id = s.enrollment_session_id" : "") . " WHERE $whereClause";
        $stmt = $pdo->prepare($countSql); $stmt->execute($params); $total = (int)$stmt->fetch()['total'];
        
        // Data
        $sql = "
            SELECT 
              s.id,
              s.student_id AS roll_number,
              s.name,
              s.email,
              s.phone,
              s.program,
              s.shift,
              s.year_level,
              s.current_semester,
              s.section,
              s.enrollment_session_id,
              sess.code AS session_code,
              sess.label AS session_label,
              COALESCE(s.attendance_percentage, 0) AS attendance_percentage
            FROM students s
            LEFT JOIN enrollment_sessions sess ON sess.id = s.enrollment_session_id
            WHERE $whereClause
            ORDER BY s.student_id
            LIMIT $limit OFFSET $offset
        ";
        $stmt = $pdo->prepare($sql); $stmt->execute($params); $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'data' => $rows,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => (int)ceil($total / $limit),
                'total_records' => $total,
                'per_page' => $limit
            ]
        ];
    } catch (Throwable $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/** Get student details */
function getStudentDetails() {
    global $pdo;
    try {
        $studentId = (int)($_GET['id'] ?? 0);
        if ($studentId <= 0) return ['success' => false, 'error' => 'Student ID required'];
        $stmt = $pdo->prepare("SELECT s.*, sess.code AS session_code, sess.label AS session_label FROM students s LEFT JOIN enrollment_sessions sess ON sess.id = s.enrollment_session_id WHERE s.id = ? AND s.is_active = 1");
        $stmt->execute([$studentId]); $student = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$student) return ['success' => false, 'error' => 'Student not found'];
        return ['success' => true, 'data' => $student];
    } catch (Throwable $e) { return ['success' => false, 'error' => $e->getMessage()]; }
}

/** Create new student */
function createStudent() {
    global $pdo;
    try {
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) { return ['success' => false, 'error' => 'Invalid CSRF token']; }
        $rollNumber = sanitizeInput($_POST['roll_number'] ?? '');
        $name = sanitizeInput($_POST['name'] ?? '');
        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $phone = sanitizeInput($_POST['phone'] ?? '');
        $program = sanitizeInput($_POST['program'] ?? '');
        $shift = sanitizeInput($_POST['shift'] ?? '');
        $yearLevel = sanitizeInput($_POST['year_level'] ?? '');
        $section = sanitizeInput($_POST['section'] ?? '');
        $currentSemester = isset($_POST['current_semester']) ? (int)$_POST['current_semester'] : null;
        $sessionCode = trim($_POST['enrollment_session_code'] ?? '');
        $sessionId = isset($_POST['enrollment_session_id']) && ctype_digit((string)$_POST['enrollment_session_id']) ? (int)$_POST['enrollment_session_id'] : null;
        
        if (!$rollNumber || !$name || !$program || !$shift || !$yearLevel || !$section) {
            return ['success' => false, 'error' => 'All required fields must be filled'];
        }
        if (!preg_match("/^[a-zA-Z\s\-'.]+$/u", $name)) return ['success' => false, 'error' => 'Invalid name'];
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) return ['success' => false, 'error' => 'Invalid email'];
        if ($currentSemester !== null && ($currentSemester < 1 || $currentSemester > 12)) return ['success' => false, 'error' => 'Invalid semester'];
        if (!preg_match('/^\\d{2}-[A-Z]{3,4}-\\d{4,6}$/', $rollNumber)) return ['success' => false, 'error' => 'Invalid roll number format'];
        
        // Resolve session by code if provided
        if (!$sessionId && $sessionCode) {
            $sess = get_session_by_code($sessionCode);
            if (!$sess) return ['success' => false, 'error' => 'Unknown session code'];
            $sessionId = (int)$sess['id'];
        }
        
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT id FROM students WHERE student_id = ? OR roll_number = ? FOR UPDATE");
            $stmt->execute([$rollNumber, $rollNumber]); if ($stmt->fetch()) { $pdo->rollBack(); return ['success' => false, 'error' => 'Roll number already exists']; }
            if ($email) { $stmt = $pdo->prepare("SELECT id FROM students WHERE email = ? FOR UPDATE"); $stmt->execute([$email]); if ($stmt->fetch()) { $pdo->rollBack(); return ['success' => false, 'error' => 'Email already exists']; } }
            
            $password = $rollNumber; $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $admissionYear = (int)('20' . substr($rollNumber, 0, 2)); $rollPrefix = explode('-', $rollNumber)[1];
            $stmt = $pdo->prepare("INSERT INTO students (student_id, roll_number, name, email, phone, program, shift, year_level, current_semester, section, admission_year, enrollment_session_id, roll_prefix, password, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?, NOW())");
            $stmt->execute([$rollNumber, $rollNumber, $name, $email, $phone, $program, $shift, $yearLevel, $currentSemester, $section, $admissionYear, $sessionId, $rollPrefix, $hashedPassword]);
            $studentId = $pdo->lastInsertId();
            $pdo->commit();
            logAdminAction('STUDENT_CREATED', "Created student: $rollNumber ($name)");
            return ['success' => true, 'message' => 'Student created successfully', 'data' => ['id' => $studentId, 'roll_number' => $rollNumber, 'password' => $password]];
        } catch (Throwable $e) { $pdo->rollBack(); return ['success' => false, 'error' => 'Failed to create student']; }
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); return ['success' => false, 'error' => $e->getMessage()]; }
}

/** Update student */
function updateStudent() {
    global $pdo;
    try {
        $studentId = (int)($_GET['id'] ?? 0); if ($studentId <= 0) return ['success' => false, 'error' => 'Student ID required'];
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) return ['success' => false, 'error' => 'Invalid CSRF token'];
        $name = sanitizeInput($_POST['name'] ?? '');
        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $phone = sanitizeInput($_POST['phone'] ?? '');
        $program = sanitizeInput($_POST['program'] ?? '');
        $shift = sanitizeInput($_POST['shift'] ?? '');
        $yearLevel = sanitizeInput($_POST['year_level'] ?? '');
        $section = sanitizeInput($_POST['section'] ?? '');
        $currentSemester = isset($_POST['current_semester']) ? (int)$_POST['current_semester'] : null;
        $sessionCode = trim($_POST['enrollment_session_code'] ?? '');
        $sessionId = isset($_POST['enrollment_session_id']) && ctype_digit((string)$_POST['enrollment_session_id']) ? (int)$_POST['enrollment_session_id'] : null;
        if (!$name || !$program || !$shift || !$yearLevel || !$section) return ['success' => false, 'error' => 'All required fields must be filled'];
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) return ['success' => false, 'error' => 'Invalid email'];
        if ($currentSemester !== null && ($currentSemester < 1 || $currentSemester > 12)) return ['success' => false, 'error' => 'Invalid semester'];
        if (!$sessionId && $sessionCode) { $sess = get_session_by_code($sessionCode); if ($sess) $sessionId = (int)$sess['id']; else return ['success' => false, 'error' => 'Unknown session code']; }
        if ($email) { $stmt = $pdo->prepare("SELECT id FROM students WHERE email = ? AND id != ?"); $stmt->execute([$email, $studentId]); if ($stmt->fetch()) return ['success' => false, 'error' => 'Email already exists']; }
        
        $stmt = $pdo->prepare("UPDATE students SET name=?, email=?, phone=?, program=?, shift=?, year_level=?, current_semester = COALESCE(?, current_semester), section=?, enrollment_session_id = COALESCE(?, enrollment_session_id), updated_at = NOW() WHERE id = ? AND is_active = 1");
        $stmt->execute([$name, $email, $phone, $program, $shift, $yearLevel, $currentSemester, $section, $sessionId, $studentId]);
        if ($stmt->rowCount() === 0) return ['success' => false, 'error' => 'Student not found'];
        logAdminAction('STUDENT_UPDATED', "Updated student ID: $studentId");
        return ['success' => true, 'message' => 'Student updated successfully'];
    } catch (Throwable $e) { return ['success' => false, 'error' => $e->getMessage()]; }
}

/** Delete student */
function deleteStudent() {
    global $pdo;
    try {
        $studentId = (int)($_GET['id'] ?? 0); if ($studentId <= 0) return ['success' => false, 'error' => 'Student ID required'];
        // If you keep users table, adapt accordingly; keeping previous behavior
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("DELETE FROM attendance WHERE user_id = ?"); $stmt->execute([$studentId]);
            $stmt = $pdo->prepare("DELETE FROM students WHERE id = ?"); $stmt->execute([$studentId]);
            $pdo->commit();
            logAdminAction('STUDENT_DELETED', "Deleted student ID: {$studentId}");
            return ['success' => true, 'message' => 'Student deleted successfully'];
        } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
    } catch (Throwable $e) { return ['success' => false, 'error' => $e->getMessage()]; }
}

/** Export students */
function exportStudents() {
    global $pdo;
    try {
        $sql = "SELECT s.student_id AS roll_number, s.name, s.email, s.phone, s.program, s.shift, s.year_level, s.current_semester, s.section, sess.label AS session_label, COALESCE(s.attendance_percentage,0) AS attendance_percentage, s.created_at FROM students s LEFT JOIN enrollment_sessions sess ON sess.id = s.enrollment_session_id WHERE s.is_active = 1 ORDER BY s.student_id";
        $stmt = $pdo->query($sql); $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="students_' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Roll Number','Name','Email','Phone','Program','Shift','Year Level','Current Semester','Session','Attendance %','Created At']);
        foreach ($students as $s) { fputcsv($out, [$s['roll_number'],$s['name'],$s['email'],$s['phone'],$s['program'],$s['shift'],$s['year_level'],$s['current_semester'],$s['session_label'],$s['attendance_percentage'],$s['created_at']]); }
        fclose($out); exit();
    } catch (Throwable $e) { return ['success' => false, 'error' => $e->getMessage()]; }
}
?>
