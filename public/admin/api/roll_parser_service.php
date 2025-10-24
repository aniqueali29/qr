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

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

// Authentication check
if (!isAdminLoggedIn()) {
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
    
    // Parse roll number format: YY-[E]PROGRAM-NN (allow 2-3 digit serial numbers)
    $pattern = '/^(\d{2})-E?([A-Z]{2,10})-(\d{2,3})$/';
    if (!preg_match($pattern, $roll_number, $matches)) {
        echo json_encode([
            'success' => false, 
            'error' => 'Invalid roll number format. Use: YY-PROGRAM-NN or YY-EPROGRAM-NN',
            'expected_format' => 'YY-PROGRAM-NN (e.g., 24-SWT-01, 25-SWT-583) or YY-EPROGRAM-NN (e.g., 24-ESWT-01)'
        ]);
        return;
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
        echo json_encode([
            'success' => false, 
            'error' => 'Invalid admission year. Must be between ' . ($current_year - 10) . ' and ' . $current_year
        ]);
        return;
    }
    
    // Get program details from database
    $stmt = $pdo->prepare("SELECT id, code, name FROM programs WHERE code = ? AND is_active = TRUE");
    $stmt->execute([$program_part]);
    $program = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$program) {
        // Get available programs for error message
        $stmt = $pdo->prepare("SELECT code, name FROM programs WHERE is_active = TRUE ORDER BY code");
        $stmt->execute();
        $available_programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => false, 
            'error' => 'Unknown program code: ' . $program_part,
            'available_programs' => $available_programs
        ]);
        return;
    }
    
    // Calculate current year level based on admission year and current date
    $current_date = new DateTime();
    $current_year = (int)$current_date->format('Y');
    $current_month = (int)$current_date->format('n');
    
    // Calculate years difference from admission year
    $years_difference = $current_year - $admission_year;
    
    // Academic year progression logic:
    // - If current month is September or later, students progress to next year
    // - If current month is before September, they're still in the same academic year
    
    if ($current_month >= 9) {
        // After September: students have progressed to the next academic year
        $year_level = $years_difference + 1;
    } else {
        // Before September: students are still in the same academic year
        $year_level = $years_difference;
    }
    
    // Ensure year level is within valid range (1-3)
    $year_level = max(1, min($year_level, 3));
    
    // Determine if student has completed the program
    $is_completed = $years_difference >= 3;
    
    // Format the status
    if ($is_completed) {
        $status = 'Completed';
    } else {
        // Convert numeric year level to ordinal format
        switch ($year_level) {
            case 1:
                $status = '1st';
                break;
            case 2:
                $status = '2nd';
                break;
            case 3:
                $status = '3rd';
                break;
            default:
                $status = '1st';
        }
    }
    
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
            'year_level_numeric' => $year_level,
            'is_completed' => $is_completed,
            'years_difference' => $years_difference,
            'current_year' => $current_year,
            'current_month' => $current_month,
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
    
    // Check if program exists
    $stmt = $pdo->prepare("SELECT code, name FROM programs WHERE code = ? AND is_active = TRUE");
    $stmt->execute([$program_part]);
    $program = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$program) {
        echo json_encode(['success' => false, 'error' => 'Unknown program code: ' . $program_part]);
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
