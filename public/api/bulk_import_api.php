<?php
/**
 * Bulk Import API for QR Code Attendance System
 * Handles CSV/Excel uploads for student data
 */

header('Content-Type: application/json');
require_once 'config.php';
setCorsHeaders();

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

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
    // Enforce CSRF on mutating routes
    requireCsrfForMethods(['POST']);

    switch ($action) {
        case 'download_template':
            downloadTemplate();
            break;
            
        case 'validate_file':
            if ($method === 'POST') {
                validateFile();
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            }
            break;
            
        case 'import_students':
            if ($method === 'POST') {
                importStudents();
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            }
            break;
            
        case 'get_import_history':
            getImportHistory();
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
 * Download CSV template
 */
function downloadTemplate() {
    $template_data = [
        ['student_id', 'name', 'email', 'phone', 'program', 'shift', 'year_level', 'section'],
        ['25-SWT-01', 'John Doe', 'john@example.com', '+923001234567', 'SWT', 'Morning', '1st', 'A'],
        ['25-SWT-02', 'Jane Smith', 'jane@example.com', '+923007654321', 'SWT', 'Evening', '1st', 'A'],
        ['25-CIT-01', 'Ahmed Ali', 'ahmed@example.com', '+923009876543', 'CIT', 'Morning', '1st', 'A'],
        ['25-CIT-02', 'Sara Khan', 'sara@example.com', '+923001112233', 'CIT', 'Evening', '1st', 'A']
    ];
    
    $filename = 'student_import_template_' . date('Y-m-d') . '.csv';
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    foreach ($template_data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit();
}

/**
 * Validate uploaded file
 */
function validateFile() {
    try {
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error']);
            return;
        }
        
        $file = $_FILES['file'];
        $filename = $file['name'];
        $filesize = $file['size'];
        $tmp_path = $file['tmp_name'];
        
        // Check file size
        if ($filesize > MAX_FILE_SIZE) {
            echo json_encode(['success' => false, 'error' => 'File too large. Maximum size: ' . formatFileSize(MAX_FILE_SIZE)]);
            return;
        }
        
        // Check file extension
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($extension, ALLOWED_EXTENSIONS)) {
            echo json_encode(['success' => false, 'error' => 'Invalid file type. Allowed: ' . implode(', ', ALLOWED_EXTENSIONS)]);
            return;
        }
        
        // Parse file based on extension
        $data = [];
        $errors = [];
        
        if ($extension === 'csv') {
            $data = parseCSV($tmp_path, $errors);
        } elseif ($extension === 'xlsx') {
            $data = parseExcel($tmp_path, $errors);
        }
        
        if (!empty($errors)) {
            echo json_encode([
                'success' => false,
                'error' => 'File validation failed',
                'details' => $errors
            ]);
            return;
        }
        
        // Validate data structure
        $validation_result = validateStudentData($data);
        
        if (!$validation_result['valid']) {
            echo json_encode([
                'success' => false,
                'error' => 'Data validation failed',
                'details' => $validation_result['errors']
            ]);
            return;
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'File validation successful',
            'data' => [
                'total_rows' => count($data),
                'valid_rows' => count($validation_result['valid_data']),
                'invalid_rows' => count($validation_result['errors']),
                'preview' => array_slice($validation_result['valid_data'], 0, 5)
            ]
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'File validation error: ' . $e->getMessage()]);
    }
}

/**
 * Import students from validated data
 */
function importStudents() {
    global $pdo;
    
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['data']) || !is_array($input['data'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Student data is required']);
            return;
        }
        
        $students_data = $input['data'];
        $import_results = [
            'success' => [],
            'errors' => [],
            'total' => count($students_data)
        ];
        
        $pdo->beginTransaction();
        
        try {
            foreach ($students_data as $index => $student) {
                $result = createStudentFromImport($pdo, $student, $index + 1);
                
                if ($result['success']) {
                    $import_results['success'][] = $result;
                } else {
                    $import_results['errors'][] = $result;
                }
            }
            
            $pdo->commit();
            
            // Log import
            logImport($pdo, $import_results);
            
            echo json_encode([
                'success' => true,
                'message' => 'Import completed',
                'data' => $import_results
            ]);
            
        } catch (Exception $e) {
            $pdo->rollback();
            throw $e;
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Import error: ' . $e->getMessage()]);
    }
}

/**
 * Parse CSV file
 */
function parseCSV($file_path, &$errors) {
    $data = [];
    $handle = fopen($file_path, 'r');
    
    if (!$handle) {
        $errors[] = 'Could not open CSV file';
        return $data;
    }
    
    $header = fgetcsv($handle);
    if (!$header) {
        $errors[] = 'CSV file is empty';
        fclose($handle);
        return $data;
    }
    
    // Validate header
    $required_columns = ['student_id', 'name', 'email', 'program', 'shift', 'year_level'];
    $missing_columns = array_diff($required_columns, $header);
    
    if (!empty($missing_columns)) {
        $errors[] = 'Missing required columns: ' . implode(', ', $missing_columns);
        fclose($handle);
        return $data;
    }
    
    $row_number = 1;
    while (($row = fgetcsv($handle)) !== false) {
        $row_number++;
        
        if (count($row) !== count($header)) {
            $errors[] = "Row $row_number: Column count mismatch";
            continue;
        }
        
        $data[] = array_combine($header, $row);
    }
    
    fclose($handle);
    return $data;
}

/**
 * Parse Excel file (simplified - in production use PhpSpreadsheet)
 */
function parseExcel($file_path, &$errors) {
    // For now, return error - in production implement with PhpSpreadsheet
    $errors[] = 'Excel parsing not implemented. Please use CSV format.';
    return [];
}

/**
 * Validate student data
 */
function validateStudentData($data) {
    global $pdo;
    
    $valid_data = [];
    $errors = [];
    
    // Get existing programs and sections
    $programs = [];
    $sections = [];
    
    $stmt = $pdo->prepare("SELECT code FROM programs WHERE is_active = TRUE");
    $stmt->execute();
    $programs = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'code');
    
    $stmt = $pdo->prepare("
        SELECT sec.id, p.code as program_code, sec.year_level, sec.section_name, sec.shift 
        FROM sections sec 
        JOIN programs p ON sec.program_id = p.id 
        WHERE sec.is_active = TRUE
    ");
    $stmt->execute();
    $sections_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($sections_data as $section) {
        $key = $section['program_code'] . '_' . $section['year_level'] . '_' . $section['section_name'] . '_' . $section['shift'];
        $sections[$key] = $section['id'];
    }
    
    foreach ($data as $index => $student) {
        $row_errors = [];
        $row_number = $index + 2; // +2 because of header and 0-based index
        
        // Required fields
        if (empty($student['student_id'])) {
            $row_errors[] = 'Student ID is required';
        } else {
            // Validate roll number format
            if (!preg_match('/^\d{2}-[A-Z]{2,10}-\d{2}$/', $student['student_id'])) {
                $row_errors[] = 'Invalid student ID format. Use: YY-PROGRAM-NN (e.g., 25-SWT-01)';
            }
        }
        
        if (empty($student['name'])) {
            $row_errors[] = 'Name is required';
        }
        
        if (empty($student['email'])) {
            $row_errors[] = 'Email is required';
        } elseif (!filter_var($student['email'], FILTER_VALIDATE_EMAIL)) {
            $row_errors[] = 'Invalid email format';
        }
        
        if (empty($student['program'])) {
            $row_errors[] = 'Program is required';
        } elseif (!in_array($student['program'], $programs)) {
            $row_errors[] = 'Invalid program. Available: ' . implode(', ', $programs);
        }
        
        if (empty($student['shift'])) {
            $row_errors[] = 'Shift is required';
        } elseif (!in_array($student['shift'], ['Morning', 'Evening'])) {
            $row_errors[] = 'Invalid shift. Use: Morning or Evening';
        }
        
        if (empty($student['year_level'])) {
            $row_errors[] = 'Year level is required';
        } elseif (!in_array($student['year_level'], ['1st', '2nd', '3rd'])) {
            $row_errors[] = 'Invalid year level. Use: 1st, 2nd, or 3rd';
        }
        
        if (empty($student['section'])) {
            $row_errors[] = 'Section is required';
        }
        
        // Check if student already exists
        if (!empty($student['student_id'])) {
            $stmt = $pdo->prepare("SELECT id FROM students WHERE student_id = ?");
            $stmt->execute([$student['student_id']]);
            if ($stmt->fetch()) {
                $row_errors[] = 'Student ID already exists';
            }
        }
        
        // Check if email already exists
        if (!empty($student['email'])) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$student['email']]);
            if ($stmt->fetch()) {
                $row_errors[] = 'Email already exists';
            }
        }
        
        // Check section availability
        if (!empty($student['program']) && !empty($student['year_level']) && 
            !empty($student['section']) && !empty($student['shift'])) {
            
            $section_key = $student['program'] . '_' . $student['year_level'] . '_' . $student['section'] . '_' . $student['shift'];
            
            if (!isset($sections[$section_key])) {
                $row_errors[] = 'Section not found for the given program, year, and shift';
            }
        }
        
        if (!empty($row_errors)) {
            $errors[] = [
                'row' => $row_number,
                'student_id' => $student['student_id'] ?? 'N/A',
                'errors' => $row_errors
            ];
        } else {
            $valid_data[] = $student;
        }
    }
    
    return [
        'valid' => empty($errors),
        'valid_data' => $valid_data,
        'errors' => $errors
    ];
}

/**
 * Create student from import data
 */
function createStudentFromImport($pdo, $student, $row_number) {
    try {
        $student_id = trim($student['student_id']);
        $name = trim($student['name']);
        $email = trim($student['email']);
        $phone = trim($student['phone'] ?? '');
        $program = trim($student['program']);
        $shift = trim($student['shift']);
        $year_level = trim($student['year_level']);
        $section_name = trim($student['section']);
        
        // Use roll number as username and password
        $password = $student_id; // Use roll number as password (e.g., 25-CIT-597)
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $username = $student_id; // Use roll number as username (e.g., 25-CIT-597)
        
        // Get section ID
        $stmt = $pdo->prepare("
            SELECT sec.id 
            FROM sections sec 
            JOIN programs p ON sec.program_id = p.id 
            WHERE p.code = ? AND sec.year_level = ? AND sec.section_name = ? AND sec.shift = ?
        ");
        $stmt->execute([$program, $year_level, $section_name, $shift]);
        $section = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$section) {
            return [
                'success' => false,
                'row' => $row_number,
                'student_id' => $student_id,
                'error' => 'Section not found'
            ];
        }
        
        $section_id = $section['id'];
        
        // Create user account
        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password_hash, role, student_id, is_active, created_at) 
            VALUES (?, ?, ?, 'student', ?, 1, NOW())
        ");
        $stmt->execute([$username, $email, $password_hash, $student_id]);
        $user_id = $pdo->lastInsertId();
        
        // Create student record
        $stmt = $pdo->prepare("
            INSERT INTO students (student_id, name, email, phone, user_id, program, shift, year_level, section, section_id, admission_year, roll_prefix, username, password, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $admission_year = '20' . substr($student_id, 0, 2);
        $roll_prefix = explode('-', $student_id)[1];
        
        $stmt->execute([
            $student_id, $name, $email, $phone, $user_id, $program, $shift, $year_level, $section_name, 
            $section_id, $admission_year, $roll_prefix, $username, $password
        ]);
        
        // Legacy sync removed
        
        return [
            'success' => true,
            'row' => $row_number,
            'student_id' => $student_id,
            'name' => $name,
            'email' => $email,
            'password' => $password
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'row' => $row_number,
            'student_id' => $student['student_id'] ?? 'N/A',
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Generate secure password
 */
function generatePassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    $password = '';
    
    // Ensure at least one character from each category
    $password .= 'abcdefghijklmnopqrstuvwxyz'[random_int(0, 25)];
    $password .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'[random_int(0, 25)];
    $password .= '0123456789'[random_int(0, 9)];
    $password .= '!@#$%^&*'[random_int(0, 7)];
    
    // Fill the rest with random characters
    for ($i = 4; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    
    return str_shuffle($password);
}

/**
 * Legacy sync functions removed
 */

/**
 * Log import results
 */
function logImport($pdo, $results) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO import_logs (import_type, total_records, successful_records, failed_records, error_details, created_at) 
            VALUES ('student_import', ?, ?, ?, ?, NOW())
        ");
        
        $error_details = json_encode($results['errors']);
        $stmt->execute([
            $results['total'],
            count($results['success']),
            count($results['errors']),
            $error_details
        ]);
        
    } catch (Exception $e) {
        error_log("Error logging import: " . $e->getMessage());
    }
}

/**
 * Get import history
 */
function getImportHistory() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM import_logs 
            ORDER BY created_at DESC 
            LIMIT 50
        ");
        $stmt->execute();
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $history
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Error fetching import history: ' . $e->getMessage()]);
    }
}
?>
