<?php
/**
 * Simple Roll Parser Service for Admin Panel
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

require_once __DIR__ . '/../includes/config.php';

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
        // Parse D.A.E roll number format: YY-[E]PROGRAM-NN (allow 2-3 digit serial numbers)
        // Examples: 24-SWT-01, 24-ESWT-01, 24-CIT-01, 24-ECIT-01, 25-SWT-583
        $pattern = '/^(\d{2})-E?([A-Z]{2,10})-(\d{2,3})$/';
        if (!preg_match($pattern, $roll_number, $matches)) {
            return [
                'success' => false, 
                'error' => 'Invalid D.A.E roll number format. Expected: YY-PROGRAM-NN or YY-EPROGRAM-NN (e.g., 24-SWT-01, 25-SWT-583, 24-ESWT-01)'
            ];
        }
        
        $year_part = $matches[1];
        $program_part = $matches[2];
        $serial_part = $matches[3];
        
        // Determine if it's evening shift (has E prefix)
        $is_evening = strpos($roll_number, '-E') !== false;
        $shift = $is_evening ? 'Evening' : 'Morning';
        
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
        
        // Get program details from database using base program code
        $stmt = $pdo->prepare("SELECT id, code, name FROM programs WHERE code = ? AND is_active = TRUE");
        $stmt->execute([$base_program_code]);
        $program = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$program) {
            return [
                'success' => false, 
                'error' => 'Unknown D.A.E program code: ' . $base_program_code . '. Valid codes: SWT, CIT'
            ];
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
                'year_level' => $status,
                'year_level_numeric' => $year_level,
                'is_completed' => $is_completed,
                'years_difference' => $years_difference,
                'current_year' => $current_year,
                'current_month' => $current_month,
                'program_type' => 'D.A.E'
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
