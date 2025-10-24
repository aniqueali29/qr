<?php
/**
 * Roll Number Parser Service for D.A.E Students
 * Parses roll numbers in format: YY-[E]PROGRAM-NN
 * Examples: 24-SWT-01, 24-ESWT-01, 24-CIT-05, 24-ECIT-03
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit();
}

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'parse_roll':
            parseRollNumber($pdo);
            break;
            
        case 'validate_roll':
            validateRollNumber($pdo);
            break;
            
        case 'get_program_codes':
            getProgramCodes($pdo);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}

/**
 * Parse a roll number and extract structured data
 */
function parseRollNumber($pdo) {
    $roll_number = trim($_GET['roll_number'] ?? '');
    
    if (empty($roll_number)) {
        echo json_encode(['success' => false, 'error' => 'Roll number is required']);
        return;
    }
    
    // Parse roll number format: YY-[E]PROGRAM-NN
    $pattern = '/^(\d{2})-E?([A-Z]{2,10})-(\d{2})$/';
    if (!preg_match($pattern, $roll_number, $matches)) {
        echo json_encode([
            'success' => false, 
            'error' => 'Invalid roll number format. Use: YY-PROGRAM-NN or YY-EPROGRAM-NN',
            'expected_format' => 'YY-PROGRAM-NN (e.g., 24-SWT-01) or YY-EPROGRAM-NN (e.g., 24-ESWT-01)'
        ]);
        return;
    }
    
    $year_part = $matches[1];
    $program_part = $matches[2];
    $serial_part = $matches[3];
    
    // Determine if it's evening shift (has E prefix)
    $is_evening = strpos($roll_number, '-E') !== false;
    $shift = $is_evening ? 'Evening' : 'Morning';
    
    // For evening shifts, the program_part already includes the E prefix
    // We need to extract the base program code for database lookup
    $base_program_code = $program_part;
    
    // Convert 2-digit year to 4-digit year
    $admission_year = 2000 + intval($year_part);
    
    // Validate admission year (must be reasonable)
    $current_year = date('Y');
    if ($admission_year > $current_year || $admission_year < ($current_year - 10)) {
        echo json_encode([
            'success' => false, 
            'error' => 'Invalid admission year. Must be between ' . ($current_year - 10) . ' and ' . $current_year
        ]);
        return;
    }
    
    // Get program details from database using base program code
    $stmt = $pdo->prepare("SELECT id, code, name FROM programs WHERE code = ? AND is_active = TRUE");
    $stmt->execute([$base_program_code]);
    $program = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$program) {
        // Get available programs for error message
        $stmt = $pdo->prepare("SELECT code, name FROM programs WHERE is_active = TRUE ORDER BY code");
        $stmt->execute();
        $available_programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => false, 
            'error' => 'Unknown program code: ' . $base_program_code,
            'available_programs' => $available_programs
        ]);
        return;
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
    
    // Get available sections for this program, year, and shift
    $stmt = $pdo->prepare("
        SELECT id, section_name, capacity, current_students 
        FROM sections 
        WHERE program_id = ? AND year_level = ? AND shift = ? AND is_active = TRUE
        ORDER BY section_name
    ");
    $stmt->execute([$program['id'], $status, $shift]);
    $available_sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Compute display program code based on shift
    $display_program_code = $shift === 'Evening' ? 'E' . $program['code'] : $program['code'];
    
    echo json_encode([
        'success' => true,
        'data' => [
            'roll_number' => $roll_number,
            'admission_year' => $admission_year,
            'program_id' => $program['id'],
            'program_code' => $program['code'], // Backward compatibility
            'base_program_code' => $program['code'], // Store base code for database
            'display_program_code' => $display_program_code, // Computed code for display
            'program_name' => $program['name'],
            'shift' => $shift,
            'serial_number' => $serial_part,
            'year_level' => $status,
            'is_completed' => $is_completed,
            'years_in_program' => $years_in_program,
            'available_sections' => $available_sections,
            'parsed_at' => date('Y-m-d H:i:s')
        ]
    ]);
}

/**
 * Validate a roll number format without full parsing
 */
function validateRollNumber($pdo) {
    $roll_number = trim($_GET['roll_number'] ?? '');
    
    if (empty($roll_number)) {
        echo json_encode(['success' => false, 'error' => 'Roll number is required']);
        return;
    }
    
    // Check format
    $pattern = '/^(\d{2})-E?([A-Z]{2,10})-(\d{2})$/';
    if (!preg_match($pattern, $roll_number, $matches)) {
        echo json_encode([
            'success' => false, 
            'error' => 'Invalid format',
            'valid_format' => 'YY-PROGRAM-NN or YY-EPROGRAM-NN'
        ]);
        return;
    }
    
    $program_part = $matches[2];
    
    // Extract base program code for database lookup
    $base_program_code = $program_part;
    
    // Check if program exists using base code
    $stmt = $pdo->prepare("SELECT code, name FROM programs WHERE code = ? AND is_active = TRUE");
    $stmt->execute([$base_program_code]);
    $program = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$program) {
        echo json_encode(['success' => false, 'error' => 'Unknown program code: ' . $base_program_code]);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Valid roll number format',
        'program' => $program
    ]);
}

/**
 * Get all available program codes
 */
function getProgramCodes($pdo) {
    $stmt = $pdo->prepare("SELECT id, code, name FROM programs WHERE is_active = TRUE ORDER BY code");
    $stmt->execute();
    $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'programs' => $programs
    ]);
}
?>
