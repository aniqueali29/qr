<?php
/**
 * Simple Roll Parser Service for Admin Panel
 * No authentication required - for auto-fill functionality
 */

// Strict JSON output for admin simple parser
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();
set_error_handler(function($errno,$errstr,$errfile,$errline){
    error_log("roll_parser_simple PHP[$errno] $errstr in $errfile:$errline");
    return true;
});
register_shutdown_function(function(){
    http_response_code(200);
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR,E_USER_ERROR])) {
        if (ob_get_length()) { ob_clean(); }
        header('Content-Type: application/json');
        echo json_encode(['success'=>false,'error'=>'Parser fatal: '.$e['message']]);
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
require_once __DIR__ . '/../../includes/academic.php';

$action = $_GET['action'] ?? '';

if (ob_get_length()) { ob_clean(); }

switch ($action) {
    case 'parse_roll':
        $roll_number = $_GET['roll_number'] ?? '';
        // Normalize: uppercase and normalize unicode dashes to ASCII '-'
        $roll_number = strtoupper(preg_replace('/[\x{2012}-\x{2015}]/u', '-', $roll_number));
        echo json_encode(parseRollNumberData($roll_number, $pdo));
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}

function parseRollNumberData($roll_number, $pdo) {
    try {
        // Parse roll number format: XX-XXX-XXXXX or XX-XXXX-XXXXXX
        // Examples: 24-SWT-00001, 24-CIVL-000001
        $pattern = '/^(\d{2})-([A-Za-z]{3,4})-([0-9]{2,6})$/';
        if (!preg_match($pattern, $roll_number, $matches)) {
            return [
                'success' => false, 
                'error' => 'Invalid roll number format. Expected: XX-XXX-XX to XX-XXXX-XXXXXX (2–6 digit serial)'
            ];
        }
        
        $year_part = $matches[1];
        $program_part = $matches[2];
        $serial_part = $matches[3];
        
        // Shift is not encoded in the new format; leave empty and let user select
        $shift = '';
        
        // For evening programs, the regex already captures the program without E
        // So ESWT becomes SWT, ECIT becomes CIT
        $base_program_code = $program_part;
        
        // Convert 2-digit year to 4-digit year
        $admission_year = 2000 + intval($year_part);
        
        // Validate admission year (must be reasonable)
        $current_year = date('Y');
        if ($admission_year > $current_year || $admission_year < ($current_year - 10)) {
            return [
                'success' => false, 
                'error' => 'Invalid admission year: ' . $admission_year
            ];
        }
        
        // Get program details from database trying multiple code candidates
        $candidates = [];
        $candidates[] = $program_part; // as-is (base)
        if (stripos($program_part, 'E') !== 0) { $candidates[] = 'E' . $program_part; } // E-prefixed variant
        if (stripos($program_part, 'E') === 0) { $candidates[] = substr($program_part, 1); } // de-prefixed variant
        $program = null;
        foreach ($candidates as $pc) {
            $stmt = $pdo->prepare("SELECT id, code, name FROM programs WHERE code = ? AND is_active = TRUE");
            $stmt->execute([$pc]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) { $program = $row; break; }
        }
        
        if (!$program) {
            // Build dynamic list of available codes for a helpful error
            try {
                $listStmt = $pdo->query("SELECT code FROM programs WHERE is_active = TRUE ORDER BY code");
                $codes = $listStmt ? array_column($listStmt->fetchAll(PDO::FETCH_ASSOC), 'code') : [];
            } catch (Throwable $e2) { $codes = []; }
            return [
                'success' => false, 
                'error' => 'Unknown program code: ' . $base_program_code . (empty($codes) ? '' : '. Available: ' . implode(', ', $codes))
            ];
        }
        
        // Calculate current academic info using centralized helpers
        $current_date = new DateTime();
        $current_year = (int)$current_date->format('Y');
        $current_month = (int)$current_date->format('n');
        $year_level_num = compute_year_of_study($admission_year, $current_date);
        $max_years = (int) academic_get_setting('max_program_years', 4);
        $is_completed = $year_level_num >= $max_years;

        $mode = academic_get_setting('academic_structure_mode', 'year');
        if ($is_completed) {
            $status = 'Completed';
        } else if ($mode === 'semester') {
            $ctx = determine_academic_context($current_date);
            $status = $ctx['current_semester_name'] ?: 'Semester';
        } else {
            if ($year_level_num === 1) {
                $status = '1st';
            } elseif ($year_level_num === 2) {
                $status = '2nd';
            } elseif ($year_level_num === 3) {
                $status = '3rd';
            } else {
                $status = $year_level_num . 'th';
            }
        }
        
        if (ob_get_length()) { ob_clean(); }
        return [
            'success' => true,
            'data' => [
                'roll_number' => $roll_number,
                'admission_year' => $admission_year,
                'program_id' => $program['id'],
                'program_code' => $program['code'],
                'program_name' => $program['name'],
            'shift' => $shift, // empty string; user must select
                'serial_number' => $serial_part,
'year_level' => $status,
                'academic_structure' => get_academic_config(),
                'academic_context' => determine_academic_context($current_date),
'year_level_numeric' => $year_level_num,
                'is_completed' => $is_completed,
                'current_year' => $current_year,
                'current_month' => $current_month,
                'program_type' => 'D.A.E'
            ]
        ];
        
    } catch (Throwable $e) {
        return [
            'success' => false,
            'error' => 'Error parsing roll number: ' . $e->getMessage()
        ];
    }
}
?>
