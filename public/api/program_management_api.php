<?php
/**
 * Program Management API for QR Code Attendance System
 * Handles CRUD operations for programs and sections
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

require_once 'config.php';
require_once 'auth_system.php';

// Authentication check
if (!isLoggedIn() || !hasRole('admin')) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required', 'message' => 'Please log in as an admin']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_programs':
            getPrograms($pdo);
            break;
            
        case 'get_program':
            getProgram($pdo);
            break;
            
        case 'add_program':
            if ($method === 'POST') {
                addProgram($pdo);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            }
            break;
            
        case 'update_program':
            if ($method === 'POST') {
                updateProgram($pdo);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            }
            break;
            
        case 'toggle_program_status':
            if ($method === 'POST') {
                toggleProgramStatus($pdo);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            }
            break;
            
        case 'delete_program':
            if ($method === 'DELETE') {
                deleteProgram($pdo);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            }
            break;
            
        case 'get_sections':
            getSections($pdo);
            break;
            
        case 'get_section':
            getSection($pdo);
            break;
            
        case 'add_section':
            if ($method === 'POST') {
                addSection($pdo);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            }
            break;
            
        case 'update_section':
            if ($method === 'POST') {
                updateSection($pdo);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            }
            break;
            
        case 'delete_section':
            if ($method === 'DELETE') {
                deleteSection($pdo);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            }
            break;
            
        case 'get_section_students':
            getSectionStudents($pdo);
            break;
            
        case 'get_program_stats':
            getProgramStats($pdo);
            break;
            
        case 'get_section_stats':
            getSectionStats($pdo);
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

/**
 * Get all programs with statistics
 */
function getPrograms($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                p.*,
                COUNT(DISTINCT s.student_id) as total_students,
                COUNT(DISTINCT sec.id) as total_sections,
                SUM(sec.capacity) as total_capacity
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
 * Get single program by ID
 */
function getProgram($pdo) {
    try {
        $program_id = $_GET['id'] ?? '';
        
        if (empty($program_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Program ID is required']);
            return;
        }
        
        $stmt = $pdo->prepare("
            SELECT 
                p.*,
                COUNT(DISTINCT s.student_id) as total_students,
                COUNT(DISTINCT sec.id) as total_sections,
                SUM(sec.capacity) as total_capacity
            FROM programs p
            LEFT JOIN sections sec ON p.id = sec.program_id AND sec.is_active = TRUE
            LEFT JOIN students s ON s.section_id = sec.id
            WHERE p.id = ?
            GROUP BY p.id, p.code, p.name, p.description, p.is_active, p.created_at, p.updated_at
        ");
        $stmt->execute([$program_id]);
        $program = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$program) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Program not found']);
            return;
        }
        
        echo json_encode([
            'success' => true,
            'data' => $program
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Error fetching program: ' . $e->getMessage()]);
    }
}

/**
 * Add new program
 */
function addProgram($pdo) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['code']) || !isset($input['name'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Program code and name are required']);
            return;
        }
        
        $code = strtoupper(trim($input['code']));
        $name = trim($input['name']);
        $description = trim($input['description'] ?? '');
        $is_active = isset($input['is_active']) ? (bool)$input['is_active'] : true;
        
        // Validate code format (2-10 uppercase letters)
        if (!preg_match('/^[A-Z]{2,10}$/', $code)) {
            echo json_encode(['success' => false, 'error' => 'Program code must be 2-10 uppercase letters']);
            return;
        }
        
        // Check if code already exists
        $stmt = $pdo->prepare("SELECT id FROM programs WHERE code = ?");
        $stmt->execute([$code]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Program code already exists']);
            return;
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO programs (code, name, description, is_active) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$code, $name, $description, $is_active]);
        
        $program_id = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Program created successfully',
            'data' => [
                'id' => $program_id,
                'code' => $code,
                'name' => $name,
                'description' => $description,
                'is_active' => $is_active
            ]
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Error creating program: ' . $e->getMessage()]);
    }
}

/**
 * Update program
 */
function updateProgram($pdo) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Program ID is required']);
            return;
        }
        
        $program_id = $input['id'];
        $name = trim($input['name'] ?? '');
        $description = trim($input['description'] ?? '');
        $is_active = isset($input['is_active']) ? (bool)$input['is_active'] : true;
        
        if (empty($name)) {
            echo json_encode(['success' => false, 'error' => 'Program name is required']);
            return;
        }
        
        // Check if program exists
        $stmt = $pdo->prepare("SELECT id FROM programs WHERE id = ?");
        $stmt->execute([$program_id]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Program not found']);
            return;
        }
        
        $stmt = $pdo->prepare("
            UPDATE programs 
            SET name = ?, description = ?, is_active = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$name, $description, $is_active, $program_id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Program updated successfully'
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Error updating program: ' . $e->getMessage()]);
    }
}

/**
 * Toggle program status
 */
function toggleProgramStatus($pdo) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Program ID is required']);
            return;
        }
        
        $program_id = $input['id'];
        
        $stmt = $pdo->prepare("
            UPDATE programs 
            SET is_active = NOT is_active, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$program_id]);
        
        if ($stmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'error' => 'Program not found']);
            return;
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Program status updated successfully'
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Error updating program status: ' . $e->getMessage()]);
    }
}

/**
 * Delete program
 */
function deleteProgram($pdo) {
    try {
        $program_id = $_GET['id'] ?? '';
        
        if (empty($program_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Program ID is required']);
            return;
        }
        
        // Check if program has students
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM students s 
            JOIN sections sec ON s.section_id = sec.id 
            WHERE sec.program_id = ?
        ");
        $stmt->execute([$program_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] > 0) {
            echo json_encode(['success' => false, 'error' => 'Cannot delete program with existing students']);
            return;
        }
        
        $pdo->beginTransaction();
        
        try {
            // Delete sections first
            $stmt = $pdo->prepare("DELETE FROM sections WHERE program_id = ?");
            $stmt->execute([$program_id]);
            
            // Delete program
            $stmt = $pdo->prepare("DELETE FROM programs WHERE id = ?");
            $stmt->execute([$program_id]);
            
            $pdo->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Program deleted successfully'
            ]);
            
        } catch (Exception $e) {
            $pdo->rollback();
            throw $e;
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Error deleting program: ' . $e->getMessage()]);
    }
}

/**
 * Get sections with filters
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
            WHERE 1=1
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
 * Get single section
 */
function getSection($pdo) {
    try {
        $section_id = $_GET['id'] ?? '';
        
        if (empty($section_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Section ID is required']);
            return;
        }
        
        $stmt = $pdo->prepare("
            SELECT 
                sec.*,
                p.code as program_code,
                p.name as program_name,
                COUNT(s.student_id) as current_students,
                ROUND((COUNT(s.student_id) / sec.capacity) * 100, 2) as capacity_utilization
            FROM sections sec
            JOIN programs p ON sec.program_id = p.id
            LEFT JOIN students s ON s.section_id = sec.id
            WHERE sec.id = ?
            GROUP BY sec.id, sec.program_id, sec.year_level, sec.section_name, sec.shift, sec.capacity, sec.is_active, sec.created_at, sec.updated_at, p.code, p.name
        ");
        $stmt->execute([$section_id]);
        $section = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$section) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Section not found']);
            return;
        }
        
        echo json_encode([
            'success' => true,
            'data' => $section
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Error fetching section: ' . $e->getMessage()]);
    }
}

/**
 * Add new section
 */
function addSection($pdo) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['program_id']) || !isset($input['year_level']) || 
            !isset($input['section_name']) || !isset($input['shift'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'All section fields are required']);
            return;
        }
        
        $program_id = $input['program_id'];
        $year_level = $input['year_level'];
        $section_name = strtoupper(trim($input['section_name']));
        $shift = $input['shift'];
        $capacity = (int)($input['capacity'] ?? 40);
        
        // Validate year level
        if (!in_array($year_level, ['1st', '2nd', '3rd'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid year level']);
            return;
        }
        
        // Validate shift
        if (!in_array($shift, ['Morning', 'Evening'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid shift']);
            return;
        }
        
        // Check if program exists
        $stmt = $pdo->prepare("SELECT id FROM programs WHERE id = ? AND is_active = TRUE");
        $stmt->execute([$program_id]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Program not found or inactive']);
            return;
        }
        
        // Check if section already exists
        $stmt = $pdo->prepare("
            SELECT id FROM sections 
            WHERE program_id = ? AND year_level = ? AND section_name = ? AND shift = ?
        ");
        $stmt->execute([$program_id, $year_level, $section_name, $shift]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Section already exists']);
            return;
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO sections (program_id, year_level, section_name, shift, capacity) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$program_id, $year_level, $section_name, $shift, $capacity]);
        
        $section_id = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Section created successfully',
            'data' => [
                'id' => $section_id,
                'program_id' => $program_id,
                'year_level' => $year_level,
                'section_name' => $section_name,
                'shift' => $shift,
                'capacity' => $capacity
            ]
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Error creating section: ' . $e->getMessage()]);
    }
}

/**
 * Update section
 */
function updateSection($pdo) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Section ID is required']);
            return;
        }
        
        $section_id = $input['id'];
        $capacity = (int)($input['capacity'] ?? 40);
        $is_active = isset($input['is_active']) ? (bool)$input['is_active'] : true;
        
        // Check if section exists
        $stmt = $pdo->prepare("SELECT id FROM sections WHERE id = ?");
        $stmt->execute([$section_id]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Section not found']);
            return;
        }
        
        $stmt = $pdo->prepare("
            UPDATE sections 
            SET capacity = ?, is_active = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$capacity, $is_active, $section_id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Section updated successfully'
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Error updating section: ' . $e->getMessage()]);
    }
}

/**
 * Delete section
 */
function deleteSection($pdo) {
    try {
        $section_id = $_GET['id'] ?? '';
        
        if (empty($section_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Section ID is required']);
            return;
        }
        
        // Check if section has students
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM students WHERE section_id = ?");
        $stmt->execute([$section_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] > 0) {
            echo json_encode(['success' => false, 'error' => 'Cannot delete section with existing students']);
            return;
        }
        
        $stmt = $pdo->prepare("DELETE FROM sections WHERE id = ?");
        $stmt->execute([$section_id]);
        
        if ($stmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'error' => 'Section not found']);
            return;
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Section deleted successfully'
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Error deleting section: ' . $e->getMessage()]);
    }
}

/**
 * Get students in a section
 */
function getSectionStudents($pdo) {
    try {
        $section_id = $_GET['section_id'] ?? '';
        
        if (empty($section_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Section ID is required']);
            return;
        }
        
        $stmt = $pdo->prepare("
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
                COUNT(a.id) as total_attendance,
                SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present_count
            FROM students s
            LEFT JOIN attendance a ON s.student_id = a.student_id
            WHERE s.section_id = ?
            GROUP BY s.student_id, s.name, s.email, s.phone, s.program, s.shift, s.year_level, s.section, s.attendance_percentage
            ORDER BY s.name ASC
        ");
        $stmt->execute([$section_id]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $students
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Error fetching section students: ' . $e->getMessage()]);
    }
}

/**
 * Get program statistics
 */
function getProgramStats($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                p.code,
                p.name,
                COUNT(DISTINCT s.student_id) as total_students,
                COUNT(DISTINCT sec.id) as total_sections,
                SUM(sec.capacity) as total_capacity,
                AVG(s.attendance_percentage) as avg_attendance,
                COUNT(DISTINCT CASE WHEN s.shift = 'Morning' THEN s.student_id END) as morning_students,
                COUNT(DISTINCT CASE WHEN s.shift = 'Evening' THEN s.student_id END) as evening_students
            FROM programs p
            LEFT JOIN sections sec ON p.id = sec.program_id AND sec.is_active = TRUE
            LEFT JOIN students s ON s.section_id = sec.id
            GROUP BY p.id, p.code, p.name
            ORDER BY p.name
        ");
        $stmt->execute();
        $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $stats
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Error fetching program stats: ' . $e->getMessage()]);
    }
}

/**
 * Get section statistics
 */
function getSectionStats($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                sec.id,
                sec.section_name,
                p.code as program_code,
                p.name as program_name,
                sec.year_level,
                sec.shift,
                sec.capacity,
                COUNT(s.student_id) as current_students,
                ROUND((COUNT(s.student_id) / sec.capacity) * 100, 2) as capacity_utilization,
                AVG(s.attendance_percentage) as avg_attendance
            FROM sections sec
            JOIN programs p ON sec.program_id = p.id
            LEFT JOIN students s ON s.section_id = sec.id
            WHERE sec.is_active = TRUE
            GROUP BY sec.id, sec.section_name, p.code, p.name, sec.year_level, sec.shift, sec.capacity
            ORDER BY p.name, sec.year_level, sec.shift, sec.section_name
        ");
        $stmt->execute();
        $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $stats
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Error fetching section stats: ' . $e->getMessage()]);
    }
}
?>
