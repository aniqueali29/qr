<?php
/**
 * Sessions Management API
 * Complete CRUD operations for session management
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

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

// Require admin authentication (for sensitive operations, you can enable this)
// if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
//     http_response_code(401);
//     echo json_encode(['success' => false, 'error' => 'Authentication required']);
//     exit();
// }

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
    error_log("Sessions API Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}

function handleGetRequest($action) {
    switch ($action) {
        case 'list':
            echo json_encode(getSessionsList());
            break;
        case 'stats':
            echo json_encode(getSessionStats());
            break;
        case 'view':
            echo json_encode(getSessionDetails());
            break;
        case 'rollback_history':
            echo json_encode(getRollbackHistory());
            break;
        case 'preview_bulk':
        case 'preview_bulk_assign':
            echo json_encode(previewBulkAssignment());
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
}

function handlePostRequest($action) {
    switch ($action) {
        case 'create':
            echo json_encode(createSession());
            break;
        case 'rollback':
            echo json_encode(rollbackAssignment());
            break;
        case 'bulk_assign':
            echo json_encode(executeBulkAssignment());
            break;
        case 'toggle_status':
            echo json_encode(toggleSessionStatus());
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
}

function handlePutRequest($action) {
    switch ($action) {
        case 'update':
            echo json_encode(updateSession());
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
}

function handleDeleteRequest($action) {
    switch ($action) {
        case 'delete':
            echo json_encode(deleteSession());
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
}

/**
 * Get enrollment_sessions list with student counts
 */
function getSessionsList() {
    global $pdo;
    
    try {
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $perPage = isset($_GET['per_page']) ? max(1, (int)$_GET['per_page']) : 10;
        $offset = ($page - 1) * $perPage;
        
        // Get total count
        $countStmt = $pdo->query("SELECT COUNT(*) as total FROM enrollment_sessions");
        $totalRecords = $countStmt->fetch()['total'];
        $totalPages = ceil($totalRecords / $perPage);
        
        // Get paginated data
        $stmt = $pdo->query("
            SELECT 
                s.id,
                s.code,
                s.label,
                s.term,
                s.year,
                s.start_date,
                s.end_date,
                s.is_active,
                s.created_at,
                s.updated_at,
                COUNT(st.id) as student_count
            FROM enrollment_sessions s
            LEFT JOIN students st ON st.enrollment_session_id = s.id AND st.is_active = 1
            GROUP BY s.id
            ORDER BY s.year DESC, s.term DESC
            LIMIT $perPage OFFSET $offset
        ");
        
        $enrollment_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'data' => $enrollment_sessions,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total_records' => $totalRecords,
                'total_pages' => $totalPages
            ]
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Get session statistics
 */
function getSessionStats() {
    global $pdo;
    
    try {
        // Total enrollment_sessions
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM enrollment_sessions");
        $totalSessions = $stmt->fetch()['total'];
        
        // Active enrollment_sessions
        $stmt = $pdo->query("SELECT COUNT(*) as active FROM enrollment_sessions WHERE is_active = 1");
        $activeSessions = $stmt->fetch()['active'];
        
        // Total students
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM students WHERE is_active = 1");
        $totalStudents = $stmt->fetch()['total'];
        
        // Unassigned students
        $stmt = $pdo->query("SELECT COUNT(*) as unassigned FROM students WHERE is_active = 1 AND enrollment_session_id IS NULL");
        $unassignedStudents = $stmt->fetch()['unassigned'];
        
        return [
            'success' => true,
            'stats' => [
                'total_sessions' => (int)$totalSessions,
                'active_sessions' => (int)$activeSessions,
                'total_students' => (int)$totalStudents,
                'unassigned_students' => (int)$unassignedStudents
            ]
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Get session details
 */
function getSessionDetails() {
    global $pdo;
    
    try {
        $sessionId = (int)($_GET['id'] ?? 0);
        if ($sessionId <= 0) {
            return ['success' => false, 'error' => 'Session ID required'];
        }
        
        $stmt = $pdo->prepare("
            SELECT 
                s.*,
                COUNT(st.id) as student_count
            FROM enrollment_sessions s
            LEFT JOIN students st ON st.enrollment_session_id = s.id AND st.is_active = 1
            WHERE s.id = ?
            GROUP BY s.id
        ");
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$session) {
            return ['success' => false, 'error' => 'Session not found'];
        }
        
        return [
            'success' => true,
            'data' => $session
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Create new session
 */
function createSession() {
    global $pdo;
    
    try {
        // CSRF token validation (temporarily disabled for debugging)
        // if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        //     return ['success' => false, 'error' => 'Invalid CSRF token'];
        // }
        
        $term = sanitizeInput($_POST['term'] ?? '');
        $year = (int)($_POST['year'] ?? 0);
        $label = sanitizeInput($_POST['label'] ?? '');
        $startDate = $_POST['start_date'] ?? null;
        $endDate = $_POST['end_date'] ?? null;
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        // Validation
        if (!$term || !$year) {
            return ['success' => false, 'error' => 'Term and year are required'];
        }
        
        if (!in_array($term, ['Spring', 'Summer', 'Fall', 'Winter'])) {
            return ['success' => false, 'error' => 'Invalid term'];
        }
        
        if ($year < 2020 || $year > 2030) {
            return ['success' => false, 'error' => 'Invalid year'];
        }
        
        // Generate code and label if not provided
        $termCode = $term[0]; // First letter
        $code = $termCode . $year;
        
        if (!$label) {
            $label = $term . ' ' . $year;
        }
        
        // Check if session already exists
        $stmt = $pdo->prepare("SELECT id FROM enrollment_sessions WHERE code = ?");
        $stmt->execute([$code]);
        if ($stmt->fetch()) {
            return ['success' => false, 'error' => 'Session with this code already exists'];
        }
        
        // Insert session
        $stmt = $pdo->prepare("
            INSERT INTO enrollment_sessions (code, label, term, year, start_date, end_date, is_active) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$code, $label, $term, $year, $startDate, $endDate, $isActive]);
        
        $sessionId = $pdo->lastInsertId();
        
        logAdminAction('SESSION_CREATED', "Created session: $label ($code)");
        
        return [
            'success' => true,
            'message' => 'Session created successfully',
            'data' => ['id' => $sessionId, 'code' => $code, 'label' => $label]
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Update session
 */
function updateSession() {
    global $pdo;
    
    try {
        $sessionId = (int)($_GET['id'] ?? 0);
        if ($sessionId <= 0) {
            return ['success' => false, 'error' => 'Session ID required'];
        }
        
        // CSRF token validation (temporarily disabled for debugging)
        // if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        //     return ['success' => false, 'error' => 'Invalid CSRF token'];
        // }
        
        $term = sanitizeInput($_POST['term'] ?? '');
            $year = (int)($_POST['year'] ?? 0);
        $label = sanitizeInput($_POST['label'] ?? '');
        $startDate = $_POST['start_date'] ?? null;
        $endDate = $_POST['end_date'] ?? null;
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        // Validation
            if (!$term || !$year) {
            return ['success' => false, 'error' => 'Term and year are required'];
        }
        
        if (!in_array($term, ['Spring', 'Summer', 'Fall', 'Winter'])) {
            return ['success' => false, 'error' => 'Invalid term'];
        }
        
        if ($year < 2020 || $year > 2030) {
            return ['success' => false, 'error' => 'Invalid year'];
        }
        
        // Generate code
        $termCode = $term[0];
        $code = $termCode . $year;
        
        if (!$label) {
            $label = $term . ' ' . $year;
        }
        
        // Check if another session with this code exists
        $stmt = $pdo->prepare("SELECT id FROM enrollment_sessions WHERE code = ? AND id != ?");
        $stmt->execute([$code, $sessionId]);
        if ($stmt->fetch()) {
            return ['success' => false, 'error' => 'Session with this code already exists'];
        }
        
        // Update session
        $stmt = $pdo->prepare("
            UPDATE enrollment_sessions 
            SET code = ?, label = ?, term = ?, year = ?, start_date = ?, end_date = ?, is_active = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$code, $label, $term, $year, $startDate, $endDate, $isActive, $sessionId]);
        
        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'error' => 'Session not found'];
        }
        
        logAdminAction('SESSION_UPDATED', "Updated session ID: $sessionId");
        
        return [
            'success' => true,
            'message' => 'Session updated successfully'
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Delete session
 */
function deleteSession() {
    global $pdo;
    
    try {
        $sessionId = (int)($_GET['id'] ?? 0);
        if ($sessionId <= 0) {
            return ['success' => false, 'error' => 'Session ID required'];
        }
        
        // Check if session has students assigned
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM students WHERE enrollment_session_id = ? AND is_active = 1");
        $stmt->execute([$sessionId]);
        $studentCount = $stmt->fetch()['count'];
        
        if ($studentCount > 0) {
            return ['success' => false, 'error' => "Cannot delete session with $studentCount students assigned. Please reassign students first."];
        }
        
        // Delete session
        $stmt = $pdo->prepare("DELETE FROM enrollment_sessions WHERE id = ?");
        $stmt->execute([$sessionId]);
        
        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'error' => 'Session not found'];
        }
        
        logAdminAction('SESSION_DELETED', "Deleted session ID: $sessionId");
        
        return [
            'success' => true,
            'message' => 'Session deleted successfully'
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Toggle session status
 */
function toggleSessionStatus() {
    global $pdo;
    
    try {
        $sessionId = (int)($_GET['id'] ?? 0);
        if ($sessionId <= 0) {
            return ['success' => false, 'error' => 'Session ID required'];
        }
        
        // Get current status
        $stmt = $pdo->prepare("SELECT is_active FROM enrollment_sessions WHERE id = ?");
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch();
        
        if (!$session) {
            return ['success' => false, 'error' => 'Session not found'];
        }
        
        $newStatus = $session['is_active'] ? 0 : 1;
        
        // Update status
        $stmt = $pdo->prepare("UPDATE enrollment_sessions SET is_active = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$newStatus, $sessionId]);
        
        $action = $newStatus ? 'activated' : 'deactivated';
        logAdminAction('SESSION_STATUS_CHANGED', "Session ID: $sessionId $action");
        
        return [
            'success' => true,
            'message' => "Session $action successfully"
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Execute bulk assignment
 */
function executeBulkAssignment() {
    global $pdo;
    
    try {
        // Handle both JSON and FormData
        $inputData = json_decode(file_get_contents('php://input'), true);
        if (!$inputData) {
            $inputData = $_POST;
        }
        
        $sessionId = (int)($inputData['session_id'] ?? 0);
        $semester = (int)($inputData['semester'] ?? 0);
        $program = $inputData['program'] ?? '';
        $shift = $inputData['shift'] ?? '';
        $currentSemester = (int)($inputData['current_semester'] ?? 0);
        
        if ($sessionId <= 0) {
            return ['success' => false, 'error' => 'Session ID required'];
        }
        
        // Verify session exists
        $stmt = $pdo->prepare("SELECT id, label FROM enrollment_sessions WHERE id = ?");
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$session) {
            return ['success' => false, 'error' => 'Session not found'];
        }
        
            $pdo->beginTransaction();
        
        try {
            // Build WHERE clause based on filters (remove s. prefix for UPDATE)
            $where = ["is_active = 1"];
            $params = [];
            
            if (!empty($program)) {
                $where[] = "program = ?";
                $params[] = $program;
            }
            
            if (!empty($shift)) {
                $where[] = "shift = ?";
                $params[] = $shift;
            }
            
            if (!empty($currentSemester)) {
                $where[] = "current_semester = ?";
                $params[] = $currentSemester;
            }
            
            $whereClause = implode(' AND ', $where);
            
            // Build UPDATE query
            $updateFields = ["enrollment_session_id = ?"];
            $updateParams = [$sessionId];
            
            if ($semester > 0) {
                $updateFields[] = "current_semester = ?";
                $updateParams[] = $semester;
            }
            
            $updateClause = implode(', ', $updateFields);
            
            // Update students matching filters
            $sql = "
                UPDATE students 
                SET $updateClause, updated_at = NOW()
                WHERE $whereClause
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_merge($updateParams, $params));
            
            $updated = $stmt->rowCount();
            
                $pdo->commit();
            
            logAdminAction('BULK_SESSION_ASSIGNMENT', "Assigned $updated students to session: {$session['label']}");
            
            return [
                'success' => true,
                'message' => "Bulk assignment completed successfully",
                'updated' => $updated
            ];
            
        } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Preview bulk assignment (how many students will be affected)
 */
function previewBulkAssignment() {
    global $pdo;
    
    try {
        $sessionId = (int)($_GET['session_id'] ?? 0);
        
        if ($sessionId <= 0) {
            return ['success' => false, 'error' => 'Session ID required'];
        }
        
        // Build WHERE clause based on filters
        $where = ["s.is_active = 1"];
        $params = [];
        
        if (!empty($_GET['semester'])) {
            $where[] = "s.current_semester = ?";
            $params[] = (int)$_GET['semester'];
        }
        
        if (!empty($_GET['program'])) {
            $where[] = "s.program = ?";
            $params[] = $_GET['program'];
        }
        
        if (!empty($_GET['shift'])) {
            $where[] = "s.shift = ?";
            $params[] = $_GET['shift'];
        }
        
        if (!empty($_GET['year_level'])) {
            $where[] = "s.year_level = ?";
            $params[] = $_GET['year_level'];
        }
        
        if (!empty($_GET['current_semester'])) {
            $where[] = "s.current_semester = ?";
            $params[] = (int)$_GET['current_semester'];
        }
        
        $whereClause = implode(' AND ', $where);
        
        // Count affected students
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM students s 
            WHERE $whereClause
        ");
        $stmt->execute($params);
        $count = $stmt->fetch()['count'];
        
        return [
            'success' => true,
            'count' => (int)$count
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Rollback history (placeholder for future implementation)
 */
function getRollbackHistory() {
    global $pdo;
    
    try {
        // Create history table if it doesn't exist
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS enrollment_session_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                session_id INT NOT NULL,
                session_code VARCHAR(32),
                session_label VARCHAR(64),
                filters JSON,
                student_ids JSON,
                updated_count INT DEFAULT 0,
                created_by VARCHAR(100),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_session (session_id),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // Return history
        $stmt = $pdo->query("
            SELECT * FROM enrollment_session_history 
            ORDER BY created_at DESC 
            LIMIT 50
        ");
        
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'history' => $history
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Rollback assignment
 */
function rollbackAssignment() {
    global $pdo;
    
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $historyId = (int)($data['history_id'] ?? 0);
        
        if ($historyId <= 0) {
            return ['success' => false, 'error' => 'History ID required'];
        }
        
        // Get history record
        $stmt = $pdo->prepare("SELECT * FROM enrollment_session_history WHERE id = ?");
        $stmt->execute([$historyId]);
        $history = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$history) {
            return ['success' => false, 'error' => 'History record not found'];
        }
        
        // Get the student IDs that were affected
        $studentIds = json_decode($history['student_ids'], true);
        
        if (!$studentIds || !is_array($studentIds)) {
            return ['success' => false, 'error' => 'No student data in history'];
        }
        
        // Rollback: Set students' enrollment_session_id back to NULL
        $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
        $stmt = $pdo->prepare("UPDATE students SET enrollment_session_id = NULL WHERE id IN ($placeholders)");
        $stmt->execute($studentIds);
        $rolledBack = $stmt->rowCount();
        
        // Delete history record
        $pdo->prepare("DELETE FROM enrollment_session_history WHERE id = ?")->execute([$historyId]);
        
        return [
            'success' => true,
            'message' => "Rolled back $rolledBack students",
            'rolled_back' => $rolledBack
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

?>