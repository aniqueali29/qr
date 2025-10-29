<?php
/**
 * Roll Number Parser Service for D.A.E Students
 * Parses roll numbers in format: YY-[E]PROGRAM-NN
 * Examples: 24-SWT-01, 24-ESWT-01, 24-CIT-05, 24-ECIT-03
 */

// Strict JSON output
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

// Swallow warnings/notices into log; never echo HTML
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("roll_parser_service PHP[$errno] $errstr in $errfile:$errline");
    return true; // handled, prevent default output
});

// Return JSON on fatal errors
register_shutdown_function(function() {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        if (ob_get_length()) { ob_clean(); }
        header('Content-Type: application/json');
        http_response_code(200);
        echo json_encode(['success' => false, 'error' => 'Parser fatal: ' . $e['message']]);
    }
});

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
require_once __DIR__ . '/../../includes/academic.php';

// Authentication check
if (!isAdminLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit();
}

// Clear any buffered output from includes/notices before responding
if (ob_get_length()) { ob_clean(); }

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
    try {
        $roll_number = trim($_GET['roll_number'] ?? '');
        // Normalize: uppercase and normalize unicode dashes to ASCII '-'
        $roll_number = strtoupper(preg_replace('/[\x{2012}-\x{2015}]/u', '-', $roll_number));
        
        if (empty($roll_number)) {
            echo json_encode(['success' => false, 'error' => 'Roll number is required']);
            return;
        }
        
        // Accept formats: XX-XXX-XXXX / XX-XXX-XXXXX / XX-XXXX-XXXXXX
        $pattern = '/^(\d{2})-([A-Za-z]{3,4})-([0-9]{4,6})$/';
        if (!preg_match($pattern, $roll_number, $matches)) {
            echo json_encode([
                'success' => false,
                'error' => 'Invalid roll number format. Use: XX-XXX-XXXX / XX-XXX-XXXXX / XX-XXXX-XXXXXX',
                'expected_format' => 'e.g., 24-SWT-0001 / 24-SWT-00001 / 24-CIVL-000001'
            ]);
            return;
        }
        
        $year_part = $matches[1];
        $program_part = $matches[2];
        $serial_part = $matches[3];
        $shift = '';
        
        $admission_year = 2000 + intval($year_part);
        $cy = (int)date('Y');
        if ($admission_year > $cy || $admission_year < ($cy - 10)) {
            echo json_encode(['success' => false, 'error' => 'Invalid admission year']);
            return;
        }
        
        // Program lookup
        $stmt = $pdo->prepare("SELECT id, code, name FROM programs WHERE code = ? AND is_active = TRUE");
        $stmt->execute([$program_part]);
        $program = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$program) {
            echo json_encode(['success' => false, 'error' => 'Unknown program code: ' . $program_part]);
            return;
        }
        
        $current_date = new DateTime();
        $current_year = (int)$current_date->format('Y');
        $current_month = (int)$current_date->format('n');
        
        // Academic computations
        $year_level = compute_year_of_study($admission_year, $current_date);
        $max_years = (int) academic_get_setting('max_program_years', 4);
        $is_completed = $year_level >= $max_years;
        
        $mode = academic_get_setting('academic_structure_mode', 'year');
        if ($is_completed) {
            $status = 'Completed';
        } else if ($mode === 'semester') {
            $ctx = determine_academic_context($current_date);
            $status = $ctx['current_semester_name'] ?: 'Semester';
        } else {
            $status = ($year_level===1?'1st':$year_level===2?'2nd':$year_level===3?'3rd':$year_level.'th');
        }
        
        $display_program_code = $program['code'];
        
if (ob_get_length()) { ob_clean(); }
        echo json_encode([
            'success' => true,
            'data' => [
                'roll_number' => $roll_number,
                'admission_year' => $admission_year,
                'program_id' => $program['id'],
                'program_code' => $program['code'],
                'base_program_code' => $program['code'],
                'display_program_code' => $display_program_code,
                'program_name' => $program['name'],
                'shift' => $shift,
                'serial_number' => $serial_part,
                'year_level' => $status,
                'year_level_numeric' => $year_level,
                'is_completed' => $is_completed,
                'current_year' => $current_year,
                'current_month' => $current_month,
                'academic_structure' => get_academic_config(),
                'academic_context' => (function($d){ $ctx = determine_academic_context($d); return [
                    'academic_year_label' => $ctx['academic_year_label'],
                    'current_semester_name' => $ctx['current_semester_name'],
                    'config' => $ctx['config']
                ]; })($current_date),
                'available_sections' => [],
                'parsed_at' => date('Y-m-d H:i:s')
            ]
        ]);
    } catch (Throwable $e) {
        http_response_code(200);
if (ob_get_length()) { ob_clean(); }
        echo json_encode(['success' => false, 'error' => 'Parser error: ' . $e->getMessage()]);
    }
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
$pattern = '/^(\d{2})-([A-Za-z]{3,4})-([0-9]{4,6})$/';
    if (!preg_match($pattern, $roll_number, $matches)) {
        echo json_encode([
            'success' => false, 
            'error' => 'Invalid format',
'valid_format' => 'XX-XXX-XXXXX or XX-XXXX-XXXXXX'
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
