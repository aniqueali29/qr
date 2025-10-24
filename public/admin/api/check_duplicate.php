<?php
/**
 * Roll Number Duplicate Check API
 * Checks if a roll number already exists in the database
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Authentication check
if (!isAdminLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit();
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'check_roll_number':
        $roll_number = $_GET['roll_number'] ?? '';
        echo json_encode(checkRollNumberDuplicate($roll_number, $pdo));
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}

function checkRollNumberDuplicate($roll_number, $pdo) {
    try {
        if (empty($roll_number)) {
            return [
                'success' => false,
                'error' => 'Roll number is required'
            ];
        }
        
        // Check if roll number exists in students table
        $stmt = $pdo->prepare("
            SELECT id, student_id, name, email, program, shift, year_level, created_at 
            FROM students 
            WHERE student_id = ? OR roll_number = ?
        ");
        $stmt->execute([$roll_number, $roll_number]);
        $existing_student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing_student) {
            return [
                'success' => true,
                'is_duplicate' => true,
                'data' => [
                    'roll_number' => $roll_number,
                    'existing_student' => [
                        'id' => $existing_student['id'],
                        'name' => $existing_student['name'],
                        'email' => $existing_student['email'],
                        'program' => $existing_student['program'],
                        'shift' => $existing_student['shift'],
                        'year_level' => $existing_student['year_level'],
                        'created_at' => $existing_student['created_at']
                    ]
                ],
                'message' => "Roll number '$roll_number' already exists for student: {$existing_student['name']}"
            ];
        } else {
            return [
                'success' => true,
                'is_duplicate' => false,
                'data' => [
                    'roll_number' => $roll_number,
                    'message' => "Roll number '$roll_number' is available"
                ]
            ];
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Database error: ' . $e->getMessage()
        ];
    }
}
?>
