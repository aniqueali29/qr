<?php
/**
 * Simple Roll Parser Service
 * No authentication required - for auto-fill functionality
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

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'parse_roll':
        $roll_number = $_GET['roll_number'] ?? '';
        echo json_encode(parseRollNumberData($roll_number, $pdo));
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}

function parseRollNumberData($roll_number, $pdo) {
    try {
        // Parse roll number format: YY-[E]PROGRAM-NN
        $pattern = '/^(\d{2})-E?([A-Z]{2,10})-(\d{2})$/';
        if (!preg_match($pattern, $roll_number, $matches)) {
            return [
                'success' => false, 
                'error' => 'Invalid roll number format. Expected: YY-PROGRAM-NN or YY-EPROGRAM-NN'
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
                'error' => 'Invalid admission year: ' . $admission_year
            ];
        }
        
        // Get program details from database
        $stmt = $pdo->prepare("SELECT id, code, name FROM programs WHERE code = ? AND is_active = TRUE");
        $stmt->execute([$program_part]);
        $program = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$program) {
            return [
                'success' => false, 
                'error' => 'Unknown program code: ' . $program_part . '. Valid codes: SWT, CIT'
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
        // Clamp to 1-3 for D.A.E programs (3 years)
        $clamped_year = min(max($years_in_program, 1), 3);
        
        // Determine if student has completed the program
        $is_completed = $years_in_program > 3;
        
        // Map to proper suffix values that match UI options: 1st, 2nd, 3rd
        $suffixMap = [
            1 => '1st',
            2 => '2nd',
            3 => '3rd'
        ];
        $year_level_str = $suffixMap[$clamped_year];
        
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
                'year_level' => $year_level_str,
                'year_level_numeric' => $clamped_year,
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
