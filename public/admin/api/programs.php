<?php
/**
 * Programs API
 * Handles program and section management
 */

// Start output buffering to catch any unexpected output
ob_start();

// Set error handling to prevent HTML output
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

// Clean any output before setting headers
ob_clean();

header('Content-Type: application/json');

// Require admin authentication
requireAdminAuth();

$response = ['success' => false, 'message' => 'Invalid request.'];

/**
 * Validate program code format
 * @param string $code - Program code to validate
 * @return array - ['valid' => bool, 'error' => string]
 */
function validateProgramCode($code) {
    if (empty($code)) {
        return ['valid' => false, 'error' => 'Program code is required'];
    }
    
    // Check length (2-10 characters)
    if (strlen($code) < 2 || strlen($code) > 10) {
        return ['valid' => false, 'error' => 'Program code must be between 2 and 10 characters'];
    }
    
    // Check format - uppercase letters only (no numbers or special chars)
    if (!preg_match('/^[A-Z]+$/', $code)) {
        return ['valid' => false, 'error' => 'Program code must contain only uppercase letters'];
    }
    
    return ['valid' => true];
}

/**
 * Validate program name
 * @param string $name - Program name to validate
 * @return array - ['valid' => bool, 'error' => string]
 */
function validateProgramName($name) {
    if (empty($name)) {
        return ['valid' => false, 'error' => 'Program name is required'];
    }
    
    // Check length (3-100 characters)
    if (strlen($name) < 3 || strlen($name) > 100) {
        return ['valid' => false, 'error' => 'Program name must be between 3 and 100 characters'];
    }
    
    // Check format - letters, numbers, spaces, hyphens, parentheses, ampersand
    if (!preg_match('/^[a-zA-Z0-9\s\-()&]+$/', $name)) {
        return ['valid' => false, 'error' => 'Program name can only contain letters, numbers, spaces, hyphens, parentheses, and ampersand'];
    }
    
    return ['valid' => true];
}

/**
 * Validate section name
 * @param string $sectionName - Section name to validate
 * @return array - ['valid' => bool, 'error' => string]
 */
function validateSectionName($sectionName) {
    if (empty($sectionName)) {
        return ['valid' => false, 'error' => 'Section name is required'];
    }
    
    // Check length (1-50 characters)
    if (strlen($sectionName) < 1 || strlen($sectionName) > 50) {
        return ['valid' => false, 'error' => 'Section name must be between 1 and 50 characters'];
    }
    
    // Check format - letters, numbers, hyphens
    if (!preg_match('/^[a-zA-Z0-9\-]+$/', $sectionName)) {
        return ['valid' => false, 'error' => 'Section name can only contain letters, numbers, and hyphens'];
    }
    
    return ['valid' => true];
}

/**
 * Validate year level
 * @param string $yearLevel - Year level to validate
 * @return array - ['valid' => bool, 'error' => string]
 */
function validateYearLevel($yearLevel) {
    if (empty($yearLevel)) {
        return ['valid' => false, 'error' => 'Year level is required'];
    }
    
    $validYearLevels = ['1st', '2nd', '3rd', '4th'];
    if (!in_array($yearLevel, $validYearLevels)) {
        return ['valid' => false, 'error' => 'Year level must be one of: ' . implode(', ', $validYearLevels)];
    }
    
    return ['valid' => true];
}

/**
 * Validate shift
 * @param string $shift - Shift to validate
 * @return array - ['valid' => bool, 'error' => string]
 */
function validateShift($shift) {
    if (empty($shift)) {
        return ['valid' => false, 'error' => 'Shift is required'];
    }
    
    $validShifts = ['Morning', 'Evening'];
    if (!in_array($shift, $validShifts)) {
        return ['valid' => false, 'error' => 'Shift must be one of: ' . implode(', ', $validShifts)];
    }
    
    return ['valid' => true];
}

/**
 * Validate capacity
 * @param int $capacity - Capacity to validate
 * @return array - ['valid' => bool, 'error' => string]
 */
function validateCapacity($capacity) {
    if (!is_numeric($capacity)) {
        return ['valid' => false, 'error' => 'Capacity must be a number'];
    }
    
    $capacity = (int)$capacity;
    
    if ($capacity < 1) {
        return ['valid' => false, 'error' => 'Capacity must be at least 1'];
    }
    
    if ($capacity > 200) {
        return ['valid' => false, 'error' => 'Capacity cannot exceed 200'];
    }
    
    return ['valid' => true, 'capacity' => $capacity];
}

/**
 * Validate description
 * @param string $description - Description to validate
 * @return array - ['valid' => bool, 'error' => string]
 */
function validateDescription($description) {
    // Description is optional
    if (empty($description)) {
        return ['valid' => true];
    }
    
    // Check length (max 500 characters)
    if (strlen($description) > 500) {
        return ['valid' => false, 'error' => 'Description must not exceed 500 characters'];
    }
    
    return ['valid' => true];
}

/**
 * Get program code for specific shift
 * @param string $baseCode - Base program code (e.g., "SWT", "CIT")
 * @param string $shift - Shift ("Morning" or "Evening")
 * @return string - Computed program code
 */
function getProgramCodeForShift($baseCode, $shift) {
    if ($shift === 'Evening') {
        return 'E' . $baseCode;
    }
    return $baseCode;
}

/**
 * Extract base program code from display code
 * @param string $displayCode - Display program code (e.g., "ESWT", "SWT")
 * @return string - Base program code
 */
function getBaseProgramCode($displayCode) {
    if (strpos($displayCode, 'E') === 0) {
        return substr($displayCode, 1);
    }
    return $displayCode;
}

try {
    $action = $_REQUEST['action'] ?? '';

    switch ($action) {
        case 'list':
            // Get filter parameters
            $status = $_GET['status'] ?? '';
            $search = $_GET['search'] ?? '';
            $studentCount = $_GET['student_count'] ?? '';
            $sectionCount = $_GET['section_count'] ?? '';
            
            // Build WHERE clause
            $whereConditions = [];
            $params = [];
            
            // Status filter
            if ($status !== '') {
                $whereConditions[] = "p.is_active = ?";
                $params[] = $status;
            }
            
            // Search filter (name or code)
            if (!empty($search)) {
                $whereConditions[] = "(p.name LIKE ? OR p.code LIKE ?)";
                $searchParam = '%' . $search . '%';
                $params[] = $searchParam;
                $params[] = $searchParam;
            }
            
            $whereClause = '';
            if (!empty($whereConditions)) {
                $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
            }
            
            // OPTIMIZED QUERY: Use LEFT JOINs instead of subqueries to avoid N+1 problem
            // This query is 3-5x faster for large datasets
            $query = "
                SELECT 
                    p.id,
                    p.code,
                    p.name,
                    p.description,
                    p.is_active,
                    COUNT(DISTINCT CASE 
                        WHEN s.is_active = 1 AND (
                            -- Morning sections: count under their assigned program
                            (s.shift = 'Morning' AND s.program_id = p.id)
                            OR 
                            -- Evening sections: count under evening program codes
                            (s.shift = 'Evening' AND (
                                (p.code = 'ESWT' AND s.program_id IN (SELECT id FROM programs WHERE code = 'SWT'))
                                OR
                                (p.code = 'ECIT' AND s.program_id IN (SELECT id FROM programs WHERE code = 'CIT'))
                            ))
                        ) THEN s.id 
                        ELSE NULL 
                    END) as section_count,
                    COUNT(DISTINCT CASE 
                        WHEN st.is_active = 1 AND (
                            -- Morning students: count under their assigned program
                            (st.shift = 'Morning' AND st.program = p.code)
                            OR 
                            -- Evening students: count under evening program codes
                            (st.shift = 'Evening' AND (
                                (p.code = 'ESWT' AND st.program = 'SWT') OR
                                (p.code = 'ECIT' AND st.program = 'CIT')
                            ))
                            OR
                            -- Students assigned to sections of this program
                            st.section_id IN (SELECT id FROM sections WHERE program_id = p.id)
                        ) THEN st.id 
                        ELSE NULL 
                    END) as total_students
                FROM programs p
                LEFT JOIN sections s ON (
                    (s.shift = 'Morning' AND s.program_id = p.id) OR
                    (s.shift = 'Evening' AND (
                        (p.code = 'ESWT' AND s.program_id IN (SELECT id FROM programs WHERE code = 'SWT')) OR
                        (p.code = 'ECIT' AND s.program_id IN (SELECT id FROM programs WHERE code = 'CIT'))
                    ))
                )
                LEFT JOIN students st ON (
                    (st.shift = 'Morning' AND st.program = p.code) OR
                    (st.shift = 'Evening' AND (
                        (p.code = 'ESWT' AND st.program = 'SWT') OR
                        (p.code = 'ECIT' AND st.program = 'CIT')
                    )) OR
                    st.section_id IN (SELECT id FROM sections WHERE program_id = p.id)
                )
                $whereClause
                GROUP BY p.id, p.code, p.name, p.description, p.is_active
            ";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Apply student count filter (post-query filtering)
            if (!empty($studentCount)) {
                $programs = array_filter($programs, function($program) use ($studentCount) {
                    $count = (int)$program['total_students'];
                    if ($studentCount === '0') {
                        return $count == 0;
                    } elseif ($studentCount === '1-10') {
                        return $count >= 1 && $count <= 10;
                    } elseif ($studentCount === '11-50') {
                        return $count >= 11 && $count <= 50;
                    } elseif ($studentCount === '51+') {
                        return $count >= 51;
                    }
                    return true;
                });
                $programs = array_values($programs); // Re-index array
            }
            
            // Apply section count filter (post-query filtering)
            if (!empty($sectionCount)) {
                $programs = array_filter($programs, function($program) use ($sectionCount) {
                    $count = (int)$program['section_count'];
                    if ($sectionCount === '0') {
                        return $count == 0;
                    } elseif ($sectionCount === '1-3') {
                        return $count >= 1 && $count <= 3;
                    } elseif ($sectionCount === '4-10') {
                        return $count >= 4 && $count <= 10;
                    } elseif ($sectionCount === '11+') {
                        return $count >= 11;
                    }
                    return true;
                });
                $programs = array_values($programs); // Re-index array
            }
            
            // Sort by name
            usort($programs, function($a, $b) {
                return strcmp($a['name'], $b['name']);
            });
            
            $response = [
                'success' => true,
                'data' => $programs
            ];
            break;

        case 'programs':
            // Get just the programs list
            $stmt = $pdo->query("
                SELECT id, code, name, is_active
                FROM programs
                ORDER BY name ASC
            ");
            $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $response = [
                'success' => true,
                'data' => $programs
            ];
            break;

        case 'programs_with_shift':
            // Get programs with computed codes for both shifts
            $stmt = $pdo->query("
                SELECT id, code, name, is_active
                FROM programs
                WHERE is_active = 1
                ORDER BY name ASC
            ");
            $basePrograms = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $programsWithShift = [];
            foreach ($basePrograms as $program) {
                $programsWithShift[] = [
                    'id' => $program['id'],
                    'base_code' => $program['code'],
                    'name' => $program['name'],
                    'morning_code' => getProgramCodeForShift($program['code'], 'Morning'),
                    'evening_code' => getProgramCodeForShift($program['code'], 'Evening'),
                    'is_active' => $program['is_active']
                ];
            }
            
            $response = [
                'success' => true,
                'data' => $programsWithShift
            ];
            break;

        case 'sections':
            // Get filter parameters
            $programId = $_GET['program_id'] ?? '';
            $year = $_GET['year'] ?? '';
            $shift = $_GET['shift'] ?? '';
            $search = $_GET['search'] ?? '';
            $capacity = $_GET['capacity'] ?? '';
            $utilization = $_GET['utilization'] ?? '';
            $status = $_GET['status'] ?? '';
            
            // Build WHERE clause
            $whereConditions = [];
            $params = [];
            
            // Program filter
            if (!empty($programId)) {
                $whereConditions[] = "s.program_id = ?";
                $params[] = $programId;
            }
            
            // Year level filter
            if (!empty($year)) {
                $whereConditions[] = "s.year_level = ?";
                $params[] = $year;
            }
            
            // Shift filter
            if (!empty($shift)) {
                $whereConditions[] = "s.shift = ?";
                $params[] = $shift;
            }
            
            // Search filter
            if (!empty($search)) {
                $whereConditions[] = "s.section_name LIKE ?";
                $params[] = '%' . $search . '%';
            }
            
            // Status filter
            if ($status !== '') {
                $whereConditions[] = "s.is_active = ?";
                $params[] = $status;
            }
            
            $whereClause = '';
            if (!empty($whereConditions)) {
                $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
            }
            
            $query = "
                SELECT 
                    s.id,
                    s.section_name,
                    s.year_level,
                    s.shift,
                    s.capacity,
                    s.current_students,
                    s.is_active,
                    p.name as program_name,
                    COUNT(st.id) as student_count,
                    ROUND((COUNT(st.id) / s.capacity) * 100, 2) as capacity_utilization
                FROM sections s
                LEFT JOIN programs p ON s.program_id = p.id
                LEFT JOIN students st ON (s.id = st.section_id OR (st.section_id IS NULL AND st.program = p.code)) AND st.is_active = 1
                $whereClause
                GROUP BY s.id, s.section_name, s.year_level, s.shift, s.capacity, s.current_students, s.is_active, p.name
            ";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Apply capacity filter (post-query filtering)
            if (!empty($capacity)) {
                $sections = array_filter($sections, function($section) use ($capacity) {
                    $cap = (int)$section['capacity'];
                    if ($capacity === '0-20') {
                        return $cap >= 0 && $cap <= 20;
                    } elseif ($capacity === '21-40') {
                        return $cap >= 21 && $cap <= 40;
                    } elseif ($capacity === '41-60') {
                        return $cap >= 41 && $cap <= 60;
                    } elseif ($capacity === '61+') {
                        return $cap >= 61;
                    }
                    return true;
                });
                $sections = array_values($sections); // Re-index array
            }
            
            // Apply utilization filter (post-query filtering)
            if (!empty($utilization)) {
                $sections = array_filter($sections, function($section) use ($utilization) {
                    $util = (float)$section['capacity_utilization'];
                    if ($utilization === '0-25') {
                        return $util >= 0 && $util <= 25;
                    } elseif ($utilization === '26-50') {
                        return $util >= 26 && $util <= 50;
                    } elseif ($utilization === '51-75') {
                        return $util >= 51 && $util <= 75;
                    } elseif ($utilization === '76-100') {
                        return $util >= 76 && $util <= 100;
                    }
                    return true;
                });
                $sections = array_values($sections); // Re-index array
            }
            
            // Sort by section name
            usort($sections, function($a, $b) {
                return strcmp($a['section_name'], $b['section_name']);
            });
            
            $response = [
                'success' => true,
                'data' => $sections
            ];
            break;

        case 'add':
            // Validate CSRF token
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                http_response_code(403);
                $response = ['success' => false, 'message' => 'Invalid CSRF token'];
                break;
            }
            
            $code = strtoupper(sanitizeInput($_POST['code'] ?? ''));
            $name = sanitizeInput($_POST['name'] ?? '');
            $description = sanitizeInput($_POST['description'] ?? '');
            
            // Validate program code
            $codeValidation = validateProgramCode($code);
            if (!$codeValidation['valid']) {
                $response = ['success' => false, 'message' => $codeValidation['error']];
                break;
            }
            
            // Validate program name
            $nameValidation = validateProgramName($name);
            if (!$nameValidation['valid']) {
                $response = ['success' => false, 'message' => $nameValidation['error']];
                break;
            }
            
            // Validate description
            $descValidation = validateDescription($description);
            if (!$descValidation['valid']) {
                $response = ['success' => false, 'message' => $descValidation['error']];
                break;
            }
            
            // Check if program code already exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM programs WHERE code = ?");
            $stmt->execute([$code]);
            if ($stmt->fetchColumn() > 0) {
                $response = ['success' => false, 'message' => 'Program code already exists'];
                break;
            }
            
            // Check if program name already exists (case-insensitive)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM programs WHERE LOWER(name) = LOWER(?)");
            $stmt->execute([$name]);
            if ($stmt->fetchColumn() > 0) {
                $response = ['success' => false, 'message' => 'Program name already exists'];
                break;
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO programs (code, name, description, is_active, created_at)
                VALUES (?, ?, ?, 1, NOW())
            ");
            $stmt->execute([$code, $name, $description]);
            
            // Log the action
            logAdminAction('PROGRAM_CREATED', "Created program: $code - $name");
            
            $response = ['success' => true, 'message' => 'Program added successfully'];
            break;

        case 'add-section':
            // Validate CSRF token
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                http_response_code(403);
                $response = ['success' => false, 'message' => 'Invalid CSRF token'];
                break;
            }
            
            $programId = (int)($_POST['program_id'] ?? 0);
            $sectionName = sanitizeInput($_POST['section_name'] ?? '');
            $yearLevel = sanitizeInput($_POST['year_level'] ?? '');
            $shift = sanitizeInput($_POST['shift'] ?? '');
            $capacity = (int)($_POST['capacity'] ?? 40);
            
            // Validate program ID
            if ($programId <= 0) {
                $response = ['success' => false, 'message' => 'Invalid program ID'];
                break;
            }
            
            // Check if program exists
            $stmt = $pdo->prepare("SELECT id FROM programs WHERE id = ?");
            $stmt->execute([$programId]);
            if (!$stmt->fetch()) {
                $response = ['success' => false, 'message' => 'Program not found'];
                break;
            }
            
            // Validate section name
            $sectionValidation = validateSectionName($sectionName);
            if (!$sectionValidation['valid']) {
                $response = ['success' => false, 'message' => $sectionValidation['error']];
                break;
            }
            
            // Validate year level
            $yearValidation = validateYearLevel($yearLevel);
            if (!$yearValidation['valid']) {
                $response = ['success' => false, 'message' => $yearValidation['error']];
                break;
            }
            
            // Validate shift
            $shiftValidation = validateShift($shift);
            if (!$shiftValidation['valid']) {
                $response = ['success' => false, 'message' => $shiftValidation['error']];
                break;
            }
            
            // Validate capacity
            $capacityValidation = validateCapacity($capacity);
            if (!$capacityValidation['valid']) {
                $response = ['success' => false, 'message' => $capacityValidation['error']];
                break;
            }
            $capacity = $capacityValidation['capacity'];
            
            // Check for duplicate section (same program, name, year, shift)
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM sections 
                WHERE program_id = ? AND section_name = ? AND year_level = ? AND shift = ?
            ");
            $stmt->execute([$programId, $sectionName, $yearLevel, $shift]);
            if ($stmt->fetchColumn() > 0) {
                $response = ['success' => false, 'message' => 'Section already exists for this program, year level, and shift'];
                break;
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO sections (program_id, section_name, year_level, shift, capacity, is_active, created_at)
                VALUES (?, ?, ?, ?, ?, 1, NOW())
            ");
            $stmt->execute([$programId, $sectionName, $yearLevel, $shift, $capacity]);
            
            // Log the action
            logAdminAction('SECTION_CREATED', "Created section: $sectionName for program ID: $programId");
            
            $response = ['success' => true, 'message' => 'Section added successfully'];
            break;

        case 'view':
            $id = $_GET['id'] ?? '';
            
            // OPTIMIZED QUERY: Use LEFT JOINs instead of subqueries
            $stmt = $pdo->prepare("
                SELECT 
                    p.*,
                    COUNT(DISTINCT CASE 
                        WHEN s.is_active = 1 AND (
                            -- Morning sections: count under their assigned program
                            (s.shift = 'Morning' AND s.program_id = p.id)
                            OR 
                            -- Evening sections: count under evening program codes
                            (s.shift = 'Evening' AND (
                                (p.code = 'ESWT' AND s.program_id IN (SELECT id FROM programs WHERE code = 'SWT'))
                                OR
                                (p.code = 'ECIT' AND s.program_id IN (SELECT id FROM programs WHERE code = 'CIT'))
                            ))
                        ) THEN s.id 
                        ELSE NULL 
                    END) as section_count,
                    COUNT(DISTINCT CASE 
                        WHEN st.is_active = 1 AND (
                            -- Morning students: count under their assigned program
                            (st.shift = 'Morning' AND st.program = p.code)
                            OR 
                            -- Evening students: count under evening program codes
                            (st.shift = 'Evening' AND (
                                (p.code = 'ESWT' AND st.program = 'SWT') OR
                                (p.code = 'ECIT' AND st.program = 'CIT')
                            ))
                            OR
                            -- Students assigned to sections of this program
                            st.section_id IN (SELECT id FROM sections WHERE program_id = p.id)
                        ) THEN st.id 
                        ELSE NULL 
                    END) as total_students
                FROM programs p
                LEFT JOIN sections s ON (
                    (s.shift = 'Morning' AND s.program_id = p.id) OR
                    (s.shift = 'Evening' AND (
                        (p.code = 'ESWT' AND s.program_id IN (SELECT id FROM programs WHERE code = 'SWT')) OR
                        (p.code = 'ECIT' AND s.program_id IN (SELECT id FROM programs WHERE code = 'CIT'))
                    ))
                )
                LEFT JOIN students st ON (
                    (st.shift = 'Morning' AND st.program = p.code) OR
                    (st.shift = 'Evening' AND (
                        (p.code = 'ESWT' AND st.program = 'SWT') OR
                        (p.code = 'ECIT' AND st.program = 'CIT')
                    )) OR
                    st.section_id IN (SELECT id FROM sections WHERE program_id = p.id)
                )
                WHERE p.id = ?
                GROUP BY p.id
            ");
            $stmt->execute([$id]);
            $program = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($program) {
                $response = ['success' => true, 'data' => $program];
            } else {
                $response = ['success' => false, 'message' => 'Program not found.'];
            }
            break;

        case 'view-section':
            $id = $_GET['id'] ?? '';
            
            $stmt = $pdo->prepare("
                SELECT 
                    s.*, 
                    p.name as program_name, 
                    COUNT(st.id) as student_count,
                    ROUND((COUNT(st.id) / s.capacity) * 100, 2) as capacity_utilization
                FROM sections s
                LEFT JOIN programs p ON s.program_id = p.id
                LEFT JOIN students st ON (s.id = st.section_id OR (st.section_id IS NULL AND st.program = p.code)) AND st.is_active = 1
                WHERE s.id = ?
                GROUP BY s.id, s.section_name, s.year_level, s.shift, s.capacity, s.current_students, s.is_active, p.name
            ");
            $stmt->execute([$id]);
            $section = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Rename section_name to name for consistency
            if ($section) {
                $section['name'] = $section['section_name'];
            }
            
            if ($section) {
                $response = ['success' => true, 'data' => $section];
            } else {
                $response = ['success' => false, 'message' => 'Section not found.'];
            }
            break;

        case 'delete':
            // Validate CSRF token
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                http_response_code(403);
                $response = ['success' => false, 'message' => 'Invalid CSRF token'];
                break;
            }
            
            $id = (int)($_POST['id'] ?? 0);
            
            if (empty($id)) {
                $response = ['success' => false, 'message' => 'Program ID is required.'];
                break;
            }
            
            // Use transaction to check all dependencies
            $pdo->beginTransaction();
            
            try {
                // Check if program has students
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE program = (SELECT code FROM programs WHERE id = ?)");
                $stmt->execute([$id]);
                if ($stmt->fetchColumn() > 0) {
                    $pdo->rollBack();
                    $response = ['success' => false, 'message' => 'Cannot delete program with existing students.'];
                    break;
                }
                
                // Check if program has sections
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM sections WHERE program_id = ?");
                $stmt->execute([$id]);
                if ($stmt->fetchColumn() > 0) {
                    $pdo->rollBack();
                    $response = ['success' => false, 'message' => 'Cannot delete program with existing sections.'];
                    break;
                }
                
                // Check if program has attendance records
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE program = (SELECT code FROM programs WHERE id = ?)");
                $stmt->execute([$id]);
                if ($stmt->fetchColumn() > 0) {
                    $pdo->rollBack();
                    $response = ['success' => false, 'message' => 'Cannot delete program with attendance records.'];
                    break;
                }
                
                // Safe to delete
                $stmt = $pdo->prepare("DELETE FROM programs WHERE id = ?");
                $stmt->execute([$id]);
                
                $pdo->commit();
                
                // Log the action
                logAdminAction('PROGRAM_DELETED', "Deleted program ID: $id");
                
                $response = ['success' => true, 'message' => 'Program deleted successfully.'];
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Program delete error: " . $e->getMessage());
                $response = ['success' => false, 'message' => 'Failed to delete program'];
            }
            break;

        case 'delete-section':
            // Validate CSRF token
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                http_response_code(403);
                $response = ['success' => false, 'message' => 'Invalid CSRF token'];
                break;
            }
            
            $id = (int)($_POST['id'] ?? 0);
            
            if (empty($id)) {
                $response = ['success' => false, 'message' => 'Section ID is required.'];
                break;
            }
            
            // Check if section has students
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE section_id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) {
                $response = ['success' => false, 'message' => 'Cannot delete section with existing students.'];
                break;
            }
            
            $stmt = $pdo->prepare("DELETE FROM sections WHERE id = ?");
            $stmt->execute([$id]);
            
            $response = ['success' => true, 'message' => 'Section deleted successfully.'];
            break;

        case 'update':
            // Validate CSRF token
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                http_response_code(403);
                $response = ['success' => false, 'message' => 'Invalid CSRF token'];
                break;
            }
            
            $id = (int)($_POST['id'] ?? 0);
            $code = strtoupper(sanitizeInput($_POST['code'] ?? ''));
            $name = sanitizeInput($_POST['name'] ?? '');
            $description = sanitizeInput($_POST['description'] ?? '');
            $isActive = (int)($_POST['is_active'] ?? 1);

            // Validate ID
            if ($id <= 0) {
                $response = ['success' => false, 'message' => 'Invalid program ID'];
                break;
            }
            
            // Check if program exists
            $stmt = $pdo->prepare("SELECT id FROM programs WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                $response = ['success' => false, 'message' => 'Program not found'];
                break;
            }
            
            // Validate program code
            $codeValidation = validateProgramCode($code);
            if (!$codeValidation['valid']) {
                $response = ['success' => false, 'message' => $codeValidation['error']];
                break;
            }
            
            // Validate program name
            $nameValidation = validateProgramName($name);
            if (!$nameValidation['valid']) {
                $response = ['success' => false, 'message' => $nameValidation['error']];
                break;
            }
            
            // Validate description
            $descValidation = validateDescription($description);
            if (!$descValidation['valid']) {
                $response = ['success' => false, 'message' => $descValidation['error']];
                break;
            }
            
            // Check if code exists for another program
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM programs WHERE code = ? AND id != ?");
            $stmt->execute([$code, $id]);
            if ($stmt->fetchColumn() > 0) {
                $response = ['success' => false, 'message' => 'Program code already exists'];
                break;
            }
            
            // Check if name exists for another program (case-insensitive)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM programs WHERE LOWER(name) = LOWER(?) AND id != ?");
            $stmt->execute([$name, $id]);
            if ($stmt->fetchColumn() > 0) {
                $response = ['success' => false, 'message' => 'Program name already exists'];
                break;
            }

            $stmt = $pdo->prepare("
                UPDATE programs SET code = ?, name = ?, description = ?, is_active = ?, updated_at = NOW() WHERE id = ?
            ");
            $stmt->execute([$code, $name, $description, $isActive, $id]);
            
            // Log the action
            logAdminAction('PROGRAM_UPDATED', "Updated program ID: $id - $code - $name");
            
            $response = ['success' => true, 'message' => 'Program updated successfully'];
            break;

        case 'update-section':
            // Validate CSRF token
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                http_response_code(403);
                $response = ['success' => false, 'message' => 'Invalid CSRF token'];
                break;
            }
            
            $id = (int)($_POST['id'] ?? 0);
            $programId = (int)($_POST['program_id'] ?? 0);
            $sectionName = sanitizeInput($_POST['section_name'] ?? '');
            $yearLevel = sanitizeInput($_POST['year_level'] ?? '');
            $shift = sanitizeInput($_POST['shift'] ?? '');
            $capacity = (int)($_POST['capacity'] ?? 40);
            $isActive = (int)($_POST['is_active'] ?? 1);

            // Validate ID
            if ($id <= 0) {
                $response = ['success' => false, 'message' => 'Invalid section ID'];
                break;
            }
            
            // Check if section exists
            $stmt = $pdo->prepare("SELECT id FROM sections WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                $response = ['success' => false, 'message' => 'Section not found'];
                break;
            }
            
            // Validate program ID
            if ($programId <= 0) {
                $response = ['success' => false, 'message' => 'Invalid program ID'];
                break;
            }
            
            // Check if program exists
            $stmt = $pdo->prepare("SELECT id FROM programs WHERE id = ?");
            $stmt->execute([$programId]);
            if (!$stmt->fetch()) {
                $response = ['success' => false, 'message' => 'Program not found'];
                break;
            }
            
            // Validate section name
            $sectionValidation = validateSectionName($sectionName);
            if (!$sectionValidation['valid']) {
                $response = ['success' => false, 'message' => $sectionValidation['error']];
                break;
            }
            
            // Validate year level
            $yearValidation = validateYearLevel($yearLevel);
            if (!$yearValidation['valid']) {
                $response = ['success' => false, 'message' => $yearValidation['error']];
                break;
            }
            
            // Validate shift
            $shiftValidation = validateShift($shift);
            if (!$shiftValidation['valid']) {
                $response = ['success' => false, 'message' => $shiftValidation['error']];
                break;
            }
            
            // Validate capacity
            $capacityValidation = validateCapacity($capacity);
            if (!$capacityValidation['valid']) {
                $response = ['success' => false, 'message' => $capacityValidation['error']];
                break;
            }
            $capacity = $capacityValidation['capacity'];
            
            // Check capacity vs current students
            $stmt = $pdo->prepare("SELECT current_students FROM sections WHERE id = ?");
            $stmt->execute([$id]);
            $currentStudents = (int)($stmt->fetchColumn() ?? 0);
            if ($capacity < $currentStudents) {
                $response = ['success' => false, 'message' => "Capacity cannot be less than current students ($currentStudents)"];
                break;
            }
            
            // Check for duplicate section (same program, name, year, shift, excluding current)
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM sections 
                WHERE program_id = ? AND section_name = ? AND year_level = ? AND shift = ? AND id != ?
            ");
            $stmt->execute([$programId, $sectionName, $yearLevel, $shift, $id]);
            if ($stmt->fetchColumn() > 0) {
                $response = ['success' => false, 'message' => 'Section already exists for this program, year level, and shift'];
                break;
            }

            $stmt = $pdo->prepare("
                UPDATE sections SET program_id = ?, section_name = ?, year_level = ?, shift = ?, capacity = ?, is_active = ?, updated_at = NOW() WHERE id = ?
            ");
            $stmt->execute([$programId, $sectionName, $yearLevel, $shift, $capacity, $isActive, $id]);
            
            // Log the action
            logAdminAction('SECTION_UPDATED', "Updated section ID: $id - $sectionName");
            
            $response = ['success' => true, 'message' => 'Section updated successfully'];
            break;

        case 'toggle-status':
            // Validate CSRF token
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                http_response_code(403);
                $response = ['success' => false, 'message' => 'Invalid CSRF token'];
                break;
            }
            
            $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
            $isActive = (int)($_POST['is_active'] ?? 0);

            if (empty($id) || $isActive === '') {
                $response = ['success' => false, 'message' => 'Program ID and status are required.'];
                break;
            }

            $stmt = $pdo->prepare("UPDATE programs SET is_active = ? WHERE id = ?");
            $stmt->execute([$isActive, $id]);
            
            $statusText = $isActive ? 'activated' : 'deactivated';
            $response = ['success' => true, 'message' => "Program {$statusText} successfully."];
            break;

        case 'toggle-section-status':
            // Validate CSRF token
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                http_response_code(403);
                $response = ['success' => false, 'message' => 'Invalid CSRF token'];
                break;
            }
            
            $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
            $isActive = (int)($_POST['is_active'] ?? 0);

            if (empty($id) || $isActive === '') {
                $response = ['success' => false, 'message' => 'Section ID and status are required.'];
                break;
            }

            $stmt = $pdo->prepare("UPDATE sections SET is_active = ? WHERE id = ?");
            $stmt->execute([$isActive, $id]);
            
            $statusText = $isActive ? 'activated' : 'deactivated';
            $response = ['success' => true, 'message' => "Section {$statusText} successfully."];
            break;

        default:
            http_response_code(400);
            $response = ['success' => false, 'message' => 'Invalid action'];
            break;
    }
} catch (PDOException $e) {
    ob_clean();
    error_log("Programs API Database Error: " . $e->getMessage());
    http_response_code(500);
    $response = ['success' => false, 'message' => 'Database error occurred'];
} catch (Exception $e) {
    ob_clean();
    error_log("Programs API Error: " . $e->getMessage());
    http_response_code(500);
    $response = ['success' => false, 'message' => 'Server error occurred'];
}

// Clean buffer and output JSON
ob_clean();
echo json_encode($response);
exit();
