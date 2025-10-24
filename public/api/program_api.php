<?php
/**
 * Program Management API for College Attendance System
 * Handles CRUD operations for programs and program-related functionality
 */

require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    if ($method === 'GET') {
        switch ($action) {
            case 'list':
                listPrograms($pdo);
                break;
                
            case 'get':
                getProgram($pdo);
                break;
                
            case 'get_by_code':
                getProgramByCode($pdo);
                break;
                
            case 'get_active':
                getActivePrograms($pdo);
                break;
                
            case 'get_statistics':
                getProgramStatistics($pdo);
                break;
                
            default:
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
        }
    } elseif ($method === 'POST') {
        switch ($action) {
            case 'create':
                createProgram($pdo);
                break;
                
            case 'update':
                updateProgram($pdo);
                break;
                
            case 'activate':
                activateProgram($pdo);
                break;
                
            case 'deactivate':
                deactivateProgram($pdo);
                break;
                
            case 'bulk_create':
                bulkCreatePrograms($pdo);
                break;
                
            default:
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
        }
    } elseif ($method === 'DELETE') {
        switch ($action) {
            case 'delete':
                deleteProgram($pdo);
                break;
                
            default:
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
        }
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

/**
 * List all programs with optional filtering
 */
function listPrograms($pdo) {
    $active_only = $_GET['active_only'] ?? false;
    $search = $_GET['search'] ?? '';
    $limit = intval($_GET['limit'] ?? 50);
    $offset = intval($_GET['offset'] ?? 0);
    
    try {
        $where_conditions = [];
        $params = [];
        
        if ($active_only) {
            $where_conditions[] = "is_active = 1";
        }
        
        if (!empty($search)) {
            $where_conditions[] = "(code LIKE ? OR name LIKE ? OR description LIKE ?)";
            $search_param = "%{$search}%";
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
        }
        
        $where_clause = empty($where_conditions) ? '' : 'WHERE ' . implode(' AND ', $where_conditions);
        
        $sql = "
            SELECT id, code, name, description, duration_years, is_active, created_at, updated_at
            FROM programs 
            {$where_clause}
            ORDER BY code ASC
            LIMIT ? OFFSET ?
        ";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get total count
        $count_sql = "SELECT COUNT(*) as total FROM programs {$where_clause}";
        $count_stmt = $pdo->prepare($count_sql);
        $count_params = array_slice($params, 0, -2); // Remove limit and offset
        $count_stmt->execute($count_params);
        $total = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        echo json_encode([
            'success' => true,
            'data' => $programs,
            'pagination' => [
                'total' => intval($total),
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $total
            ]
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to list programs: ' . $e->getMessage()]);
    }
}

/**
 * Get a specific program by ID
 */
function getProgram($pdo) {
    $id = $_GET['id'] ?? '';
    
    if (empty($id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Program ID is required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT id, code, name, description, duration_years, is_active, created_at, updated_at
            FROM programs 
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        $program = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$program) {
            echo json_encode(['success' => false, 'message' => 'Program not found']);
            return;
        }
        
        echo json_encode([
            'success' => true,
            'data' => $program
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to get program: ' . $e->getMessage()]);
    }
}

/**
 * Get a program by code
 */
function getProgramByCode($pdo) {
    $code = $_GET['code'] ?? '';
    
    if (empty($code)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Program code is required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT id, code, name, description, duration_years, is_active, created_at, updated_at
            FROM programs 
            WHERE code = ?
        ");
        $stmt->execute([$code]);
        $program = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$program) {
            echo json_encode(['success' => false, 'message' => 'Program not found']);
            return;
        }
        
        echo json_encode([
            'success' => true,
            'data' => $program
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to get program: ' . $e->getMessage()]);
    }
}

/**
 * Get only active programs
 */
function getActivePrograms($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, code, name, description, duration_years
            FROM programs 
            WHERE is_active = 1
            ORDER BY code ASC
        ");
        $stmt->execute();
        $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $programs
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to get active programs: ' . $e->getMessage()]);
    }
}

/**
 * Get program statistics
 */
function getProgramStatistics($pdo) {
    try {
        // Get program counts
        $stmt = $pdo->prepare("
            SELECT 
                p.code,
                p.name,
                COUNT(s.id) as student_count,
                SUM(CASE WHEN s.is_graduated = 0 THEN 1 ELSE 0 END) as active_students,
                SUM(CASE WHEN s.is_graduated = 1 THEN 1 ELSE 0 END) as graduated_students
            FROM programs p
            LEFT JOIN students s ON p.code = s.program
            GROUP BY p.id, p.code, p.name
            ORDER BY student_count DESC
        ");
        $stmt->execute();
        $program_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get total counts
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_programs,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_programs,
                SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_programs
            FROM programs
        ");
        $stmt->execute();
        $totals = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'programs' => $program_stats,
                'totals' => $totals
            ]
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to get program statistics: ' . $e->getMessage()]);
    }
}

/**
 * Create a new program
 */
function createProgram($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $code = $input['code'] ?? '';
    $name = $input['name'] ?? '';
    $description = $input['description'] ?? '';
    $duration_years = intval($input['duration_years'] ?? 4);
    
    if (empty($code) || empty($name)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Code and name are required']);
        return;
    }
    
    try {
        // Check if code already exists
        $stmt = $pdo->prepare("SELECT id FROM programs WHERE code = ?");
        $stmt->execute([$code]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Program code already exists']);
            return;
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO programs (code, name, description, duration_years, is_active)
            VALUES (?, ?, ?, ?, 1)
        ");
        $stmt->execute([$code, $name, $description, $duration_years]);
        
        $program_id = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Program created successfully',
            'data' => [
                'id' => $program_id,
                'code' => $code,
                'name' => $name,
                'description' => $description,
                'duration_years' => $duration_years
            ]
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to create program: ' . $e->getMessage()]);
    }
}

/**
 * Update an existing program
 */
function updateProgram($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $id = $input['id'] ?? '';
    $name = $input['name'] ?? '';
    $description = $input['description'] ?? '';
    $duration_years = intval($input['duration_years'] ?? 4);
    $is_active = $input['is_active'] ?? null;
    
    if (empty($id) || empty($name)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID and name are required']);
        return;
    }
    
    try {
        // Check if program exists
        $stmt = $pdo->prepare("SELECT id FROM programs WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Program not found']);
            return;
        }
        
        $update_fields = ['name = ?', 'description = ?', 'duration_years = ?'];
        $params = [$name, $description, $duration_years];
        
        if ($is_active !== null) {
            $update_fields[] = 'is_active = ?';
            $params[] = $is_active ? 1 : 0;
        }
        
        $params[] = $id;
        
        $stmt = $pdo->prepare("
            UPDATE programs 
            SET " . implode(', ', $update_fields) . "
            WHERE id = ?
        ");
        $stmt->execute($params);
        
        echo json_encode([
            'success' => true,
            'message' => 'Program updated successfully'
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to update program: ' . $e->getMessage()]);
    }
}

/**
 * Activate a program
 */
function activateProgram($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? '';
    
    if (empty($id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Program ID is required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE programs SET is_active = 1 WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'message' => 'Program not found']);
            return;
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Program activated successfully'
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to activate program: ' . $e->getMessage()]);
    }
}

/**
 * Deactivate a program
 */
function deactivateProgram($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? '';
    
    if (empty($id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Program ID is required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE programs SET is_active = 0 WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'message' => 'Program not found']);
            return;
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Program deactivated successfully'
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to deactivate program: ' . $e->getMessage()]);
    }
}

/**
 * Delete a program
 */
function deleteProgram($pdo) {
    $id = $_GET['id'] ?? '';
    
    if (empty($id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Program ID is required']);
        return;
    }
    
    try {
        // Check if program has students
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM students WHERE program = (SELECT code FROM programs WHERE id = ?)");
        $stmt->execute([$id]);
        $student_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($student_count > 0) {
            echo json_encode(['success' => false, 'message' => 'Cannot delete program with existing students']);
            return;
        }
        
        $stmt = $pdo->prepare("DELETE FROM programs WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'message' => 'Program not found']);
            return;
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Program deleted successfully'
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to delete program: ' . $e->getMessage()]);
    }
}

/**
 * Bulk create programs
 */
function bulkCreatePrograms($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    $programs = $input['programs'] ?? [];
    
    if (empty($programs) || !is_array($programs)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Programs array is required']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        $created = [];
        $errors = [];
        
        foreach ($programs as $index => $program) {
            $code = $program['code'] ?? '';
            $name = $program['name'] ?? '';
            $description = $program['description'] ?? '';
            $duration_years = intval($program['duration_years'] ?? 4);
            
            if (empty($code) || empty($name)) {
                $errors[] = "Program {$index}: Code and name are required";
                continue;
            }
            
            // Check if code already exists
            $stmt = $pdo->prepare("SELECT id FROM programs WHERE code = ?");
            $stmt->execute([$code]);
            if ($stmt->fetch()) {
                $errors[] = "Program {$index}: Code '{$code}' already exists";
                continue;
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO programs (code, name, description, duration_years, is_active)
                VALUES (?, ?, ?, ?, 1)
            ");
            $stmt->execute([$code, $name, $description, $duration_years]);
            
            $created[] = [
                'code' => $code,
                'name' => $name,
                'id' => $pdo->lastInsertId()
            ];
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Bulk creation completed',
            'data' => [
                'created' => $created,
                'errors' => $errors,
                'total_processed' => count($programs),
                'successful' => count($created),
                'failed' => count($errors)
            ]
        ]);
        
    } catch (Exception $e) {
        $pdo->rollback();
        echo json_encode(['success' => false, 'message' => 'Bulk creation failed: ' . $e->getMessage()]);
    }
}
?>
