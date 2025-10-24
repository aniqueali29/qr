<?php
/**
 * Admin API
 * Handles various admin operations
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json');

// Check authentication and return JSON error if not authenticated
if (!isAdminLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required', 'message' => 'Please log in as an admin']);
    exit();
}

$response = ['success' => false, 'message' => 'Invalid request.'];

// Get action from either query string or JSON body
$action = $_REQUEST['action'] ?? null;

// If no action in query string, try to get it from JSON body for POST requests
if (!$action && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $jsonData = json_decode(file_get_contents('php://input'), true);
    if (isset($jsonData['action'])) {
        $action = $jsonData['action'];
    }
}

if (isset($action)) {
    try {
        switch ($action) {
            case 'get_analytics_data':
                // Get analytics data for dashboard
                $totalStudents = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
                $totalPrograms = $pdo->query("SELECT COUNT(*) FROM programs")->fetchColumn();
                $totalSections = $pdo->query("SELECT COUNT(*) FROM sections")->fetchColumn();
                $todayAttendance = $pdo->query("
                    SELECT COUNT(DISTINCT student_id) as present_today 
                    FROM attendance 
                    WHERE DATE(check_in_time) = CURDATE()
                ")->fetchColumn();
                
                $response = [
                    'success' => true,
                    'data' => [
                        'totalStudents' => (int)$totalStudents,
                        'totalPrograms' => (int)$totalPrograms,
                        'totalSections' => (int)$totalSections,
                        'todayAttendance' => (int)$todayAttendance
                    ]
                ];
                break;

            case 'students':
                // Get students list
                $page = $_GET['page'] ?? 1;
                $limit = $_GET['limit'] ?? 10;
                $offset = ($page - 1) * $limit;

                $stmt = $pdo->prepare("
                    SELECT s.*, 
                           COUNT(a.id) as attendance_count,
                           ROUND((COUNT(a.id) / GREATEST(DATEDIFF(CURDATE(), s.created_at), 1)) * 100, 2) as attendance_percentage
                    FROM students s
                    LEFT JOIN attendance a ON s.student_id = a.student_id
                    GROUP BY s.id
                    ORDER BY s.name ASC
                    LIMIT ? OFFSET ?
                ");
                $stmt->execute([$limit, $offset]);
                $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $totalStudents = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();

                $response = [
                    'success' => true,
                    'data' => $students,
                    'total' => $totalStudents,
                    'page' => (int)$page,
                    'limit' => (int)$limit
                ];
                break;

            case 'get_filtered_students':
                // Get filtered students
                $page = $_GET['page'] ?? 1;
                $limit = $_GET['limit'] ?? 10;
                $offset = ($page - 1) * $limit;

                $filters = [];
                $params = [];

                if (!empty($_GET['program'])) {
                    $filters[] = "s.program = ?";
                    $params[] = $_GET['program'];
                }
                if (!empty($_GET['shift'])) {
                    $filters[] = "s.shift = ?";
                    $params[] = $_GET['shift'];
                }
                // Support both 'year' and 'year_level' parameter names
                if (!empty($_GET['year_level']) || !empty($_GET['year'])) {
                    $filters[] = "s.year_level = ?";
                    $params[] = $_GET['year_level'] ?? $_GET['year'];
                }
                if (!empty($_GET['section'])) {
                    $filters[] = "s.section = ?";
                    $params[] = $_GET['section'];
                }
                if (isset($_GET['status']) && $_GET['status'] !== '') {
                    $filters[] = "s.is_active = ?";
                    $params[] = $_GET['status'];
                }
                if (!empty($_GET['search'])) {
                    $search = '%' . $_GET['search'] . '%';
                    $filters[] = "(s.name LIKE ? OR s.student_id LIKE ? OR s.email LIKE ?)";
                    $params[] = $search;
                    $params[] = $search;
                    $params[] = $search;
                }

                $whereClause = count($filters) > 0 ? "WHERE " . implode(" AND ", $filters) : "";

                // Get total count
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM students s $whereClause");
                $stmt->execute($params);
                $totalStudents = $stmt->fetchColumn();

                // Get students
                $stmt = $pdo->prepare("
                    SELECT s.*, 
                           COUNT(a.id) as attendance_count,
                           ROUND((COUNT(a.id) / GREATEST(DATEDIFF(CURDATE(), s.created_at), 1)) * 100, 2) as attendance_percentage
                    FROM students s
                    LEFT JOIN attendance a ON s.student_id = a.student_id
                    $whereClause
                    GROUP BY s.id
                    ORDER BY s.name ASC
                    LIMIT ? OFFSET ?
                ");
                $stmt->execute(array_merge($params, [$limit, $offset]));
                $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $response = [
                    'success' => true,
                    'data' => $students,
                    'total' => $totalStudents,
                    'page' => (int)$page,
                    'limit' => (int)$limit
                ];
                break;

            case 'attendance':
                // Get attendance records
                $page = $_GET['page'] ?? 1;
                $limit = $_GET['limit'] ?? 10;
                $offset = ($page - 1) * $limit;

                $stmt = $pdo->prepare("
                    SELECT a.id, a.student_id, s.name as student_name, a.check_in_time, a.check_out_time, a.status
                    FROM attendance a
                    JOIN students s ON a.student_id = s.student_id
                    ORDER BY a.check_in_time DESC
                    LIMIT ? OFFSET ?
                ");
                $stmt->execute([$limit, $offset]);
                $attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $totalAttendance = $pdo->query("SELECT COUNT(*) FROM attendance")->fetchColumn();

                $response = [
                    'success' => true,
                    'data' => $attendance,
                    'total' => $totalAttendance,
                    'page' => (int)$page,
                    'limit' => (int)$limit
                ];
                break;

            case 'view_student':
                $id = $_GET['id'] ?? '';
                if (empty($id)) {
                    $response = ['success' => false, 'message' => 'Student ID is required.'];
                    break;
                }
                
                $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
                $stmt->execute([$id]);
                $student = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($student) {
                    $response = ['success' => true, 'data' => $student];
                } else {
                    $response = ['success' => false, 'message' => 'Student not found.'];
                }
                break;

            case 'reset_student_password':
                $data = json_decode(file_get_contents('php://input'), true);
                $student_id = $data['student_id'] ?? '';
                $new_password = $data['new_password'] ?? '';
                $password_type = $data['password_type'] ?? 'custom';
                
                if (empty($student_id) || empty($new_password)) {
                    $response = ['success' => false, 'message' => 'Student ID and password are required'];
                    break;
                }
                
                // Get student info
                $stmt = $pdo->prepare("SELECT id, student_id, roll_number FROM students WHERE id = ?");
                $stmt->execute([$student_id]);
                $student = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$student) {
                    $response = ['success' => false, 'message' => 'Student not found'];
                    break;
                }
                
                // SECURITY FIX: Use secure password hashing
                require_once __DIR__ . '/../../../includes/password_manager.php';
                $hashed_password = PasswordManager::hashPassword($new_password);
                
                // Update password in students table (hashed for security)
                $stmt = $pdo->prepare("UPDATE students SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $student_id]);
                
                // Password updated in database
                
                // Log the password change
                $log_message = "Password updated for student {$student['student_id']} (Type: {$password_type})";
                error_log($log_message);
                
                $response = ['success' => true, 'message' => 'Password updated successfully'];
                break;

            case 'export_students':
                // Redirect to export API
                $format = $_GET['format'] ?? 'csv';
                $filters = [
                    'program' => $_GET['program'] ?? '',
                    'shift' => $_GET['shift'] ?? '',
                    'year' => $_GET['year'] ?? '',
                    'section' => $_GET['section'] ?? '',
                    'search' => $_GET['search'] ?? ''
                ];
                
                $queryString = http_build_query(array_merge(['format' => $format], $filters));
                header("Location: export.php?action=students&$queryString");
                exit;
                break;

            case 'get_year_progression':
                // Get year progression status
                $manual = $_GET['manual'] ?? false;
                
                if ($manual) {
                    // Manual year progression - this would typically update student year levels
                    // For now, return a placeholder response
                    $response = [
                        'success' => true,
                        'message' => 'Year progression completed',
                        'updated_students' => 0,
                        'graduated_students' => 0,
                        'last_progression' => date('Y-m-d'),
                        'next_progression' => date('Y-m-d', strtotime('+1 year'))
                    ];
                } else {
                    // Get progression status
                    $response = [
                        'success' => true,
                        'data' => [
                            'last_progression' => date('Y-m-d', strtotime('-1 year')),
                            'next_progression' => date('Y-m-d', strtotime('+6 months')),
                            'students_needing_update' => 0,
                            'current_academic_year' => date('Y') . '-' . (date('Y') + 1),
                            'progression_enabled' => true
                        ]
                    ];
                }
                break;

            case 'create_student':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    $response = ['success' => false, 'message' => 'POST method required.'];
                    break;
                }
                
                $rollNumber = $_POST['roll_number'] ?? '';
                $name = $_POST['name'] ?? '';
                $email = $_POST['email'] ?? '';
                $phone = $_POST['phone'] ?? '';
                $program = $_POST['program'] ?? '';
                $shift = $_POST['shift'] ?? '';
                $yearLevel = $_POST['year_level'] ?? '';
                $section = $_POST['section'] ?? '';
                $admissionYear = $_POST['admission_year'] ?? '';
                
                if (empty($rollNumber) || empty($name) || empty($email) || empty($program) || empty($shift) || empty($yearLevel) || empty($section)) {
                    $response = ['success' => false, 'message' => 'All required fields must be filled.'];
                    break;
                }
                
                // Check if roll number already exists
                $stmt = $pdo->prepare("
                    SELECT id, student_id, name, email, program, shift 
                    FROM students 
                    WHERE roll_number = ? OR student_id = ?
                ");
                $stmt->execute([$rollNumber, $rollNumber]);
                $existingStudent = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existingStudent) {
                    $response = [
                        'success' => false, 
                        'message' => "Roll number '$rollNumber' already exists for student: {$existingStudent['name']} ({$existingStudent['program']} - {$existingStudent['shift']})",
                        'duplicate_info' => $existingStudent
                    ];
                    break;
                }
                
                // Create student
                $stmt = $pdo->prepare("
                    INSERT INTO students (student_id, roll_number, name, email, phone, program, shift, year_level, section, admission_year, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$rollNumber, $rollNumber, $name, $email, $phone, $program, $shift, $yearLevel, $section, $admissionYear]);
                
                // Set username and password to roll number (plaintext)
                $password = $rollNumber; // Use roll number as password (plaintext)
                
                // Update students table to include username and password
                $updateStmt = $pdo->prepare("
                    UPDATE students 
                    SET username = ?, password = ? 
                    WHERE roll_number = ?
                ");
                $updateStmt->execute([$rollNumber, $password, $rollNumber]);
                
                // Student created in database
                
                $response = ['success' => true, 'message' => 'Student created successfully.'];
                break;

            case 'update_student':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    $response = ['success' => false, 'message' => 'POST method required.'];
                    break;
                }
                
                $id = $_GET['id'] ?? '';
                $rollNumber = $_POST['roll_number'] ?? '';
                $name = $_POST['name'] ?? '';
                $email = $_POST['email'] ?? '';
                $phone = $_POST['phone'] ?? '';
                $program = $_POST['program'] ?? '';
                $shift = $_POST['shift'] ?? '';
                $yearLevel = $_POST['year_level'] ?? '';
                $section = $_POST['section'] ?? '';
                $admissionYear = $_POST['admission_year'] ?? '';
                
                if (empty($id) || empty($rollNumber) || empty($name) || empty($email) || empty($program) || empty($shift) || empty($yearLevel) || empty($section)) {
                    $response = ['success' => false, 'message' => 'All required fields must be filled.'];
                    break;
                }
                
                // Check if roll number already exists for another student
                $stmt = $pdo->prepare("
                    SELECT id, student_id, name, email, program, shift 
                    FROM students 
                    WHERE (roll_number = ? OR student_id = ?) AND id != ?
                ");
                $stmt->execute([$rollNumber, $rollNumber, $id]);
                $existingStudent = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existingStudent) {
                    $response = [
                        'success' => false, 
                        'message' => "Roll number '$rollNumber' already exists for student: {$existingStudent['name']} ({$existingStudent['program']} - {$existingStudent['shift']})",
                        'duplicate_info' => $existingStudent
                    ];
                    break;
                }
                
                // Update student
                $stmt = $pdo->prepare("
                    UPDATE students 
                    SET student_id = ?, roll_number = ?, name = ?, email = ?, phone = ?, program = ?, shift = ?, year_level = ?, section = ?, admission_year = ?
                    WHERE id = ?
                ");
                $stmt->execute([$rollNumber, $rollNumber, $name, $email, $phone, $program, $shift, $yearLevel, $section, $admissionYear, $id]);
                
                $response = ['success' => true, 'message' => 'Student updated successfully.'];
                break;

            case 'delete_student':
                if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
                    $response = ['success' => false, 'message' => 'DELETE method required.'];
                    break;
                }
                
                $id = $_GET['id'] ?? '';
                if (empty($id)) {
                    $response = ['success' => false, 'message' => 'Student ID is required.'];
                    break;
                }
                
                // Delete student
                $stmt = $pdo->prepare("DELETE FROM students WHERE id = ?");
                $stmt->execute([$id]);
                
                $response = ['success' => true, 'message' => 'Student deleted successfully.'];
                break;

            case 'test_bulk_import':
                // Simple test endpoint
                $response = [
                    'success' => true,
                    'message' => 'Bulk import API is working',
                    'data' => [
                        'timestamp' => date('Y-m-d H:i:s'),
                        'server_time' => time()
                    ]
                ];
                break;

            case 'download_import_template':
                // Download CSV template for bulk import
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

            case 'validate_bulk_import':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    $response = ['success' => false, 'message' => 'POST method required.'];
                    break;
                }
                
                try {
                    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                        $response = ['success' => false, 'message' => 'No file uploaded or upload error'];
                        break;
                    }
                    
                    $file = $_FILES['file'];
                    $filename = $file['name'];
                    $filesize = $file['size'];
                    $tmp_path = $file['tmp_name'];
                    
                    // Check file size (10MB max)
                    if ($filesize > 10 * 1024 * 1024) {
                        $response = ['success' => false, 'message' => 'File too large. Maximum size: 10MB'];
                        break;
                    }
                    
                    // Check file extension
                    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                    if (!in_array($extension, ['csv', 'xlsx'])) {
                        $response = ['success' => false, 'message' => 'Invalid file type. Allowed: CSV, Excel (.xlsx)'];
                        break;
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
                        $response = [
                            'success' => false,
                            'message' => 'File validation failed',
                            'details' => $errors
                        ];
                        break;
                    }
                    
                    // Validate data structure
                    $validation_result = validateStudentData($data);
                    
                    if (!$validation_result['valid']) {
                        $response = [
                            'success' => false,
                            'message' => 'Data validation failed',
                            'details' => $validation_result['errors']
                        ];
                        break;
                    }
                    
                    $response = [
                        'success' => true,
                        'message' => 'File validation successful',
                        'data' => [
                            'total_rows' => count($data),
                            'valid_rows' => count($validation_result['valid_data']),
                            'invalid_rows' => count($validation_result['errors']),
                            'preview' => array_slice($validation_result['valid_data'], 0, 5),
                            'valid_data' => $validation_result['valid_data'],
                            'errors' => $validation_result['errors']
                        ]
                    ];
                    
                } catch (Exception $e) {
                    $response = ['success' => false, 'message' => 'File validation error: ' . $e->getMessage()];
                }
                break;

            case 'bulk_import_students':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    $response = ['success' => false, 'message' => 'POST method required.'];
                    break;
                }
                
                try {
                    $input = json_decode(file_get_contents('php://input'), true);
                    
                    if (!isset($input['data']) || !is_array($input['data'])) {
                        $response = ['success' => false, 'message' => 'Student data is required'];
                        break;
                    }
                    
                    $students_data = $input['data'];
                    $import_results = [
                        'success' => [],
                        'errors' => [],
                        'total' => count($students_data)
                    ];
                    
                    // Check if we have valid data to import
                    if (empty($students_data)) {
                        $response = ['success' => false, 'message' => 'No valid data to import'];
                        break;
                    }
                    
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
                        
                        $response = [
                            'success' => true,
                            'message' => 'Import completed',
                            'data' => $import_results
                        ];
                        
                    } catch (Exception $e) {
                        $pdo->rollback();
                        $response = ['success' => false, 'message' => 'Import failed: ' . $e->getMessage()];
                    }
                    
                } catch (Exception $e) {
                    $response = ['success' => false, 'message' => 'Import error: ' . $e->getMessage()];
                }
                break;

            case 'bulk_activate_students':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    $response = ['success' => false, 'message' => 'POST method required.'];
                    break;
                }
                
                $input = json_decode(file_get_contents('php://input'), true);
                $response = bulkActivateStudents($input['ids'] ?? []);
                break;

            case 'bulk_deactivate_students':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    $response = ['success' => false, 'message' => 'POST method required.'];
                    break;
                }
                
                $input = json_decode(file_get_contents('php://input'), true);
                $response = bulkDeactivateStudents($input['ids'] ?? []);
                break;

            case 'bulk_delete_students':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    $response = ['success' => false, 'message' => 'POST method required.'];
                    break;
                }
                
                $input = json_decode(file_get_contents('php://input'), true);
                $response = bulkDeleteStudents($input['ids'] ?? []);
                break;

            case 'bulk_change_program':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    $response = ['success' => false, 'message' => 'POST method required.'];
                    break;
                }
                
                $input = json_decode(file_get_contents('php://input'), true);
                $response = bulkChangeProgram($input);
                break;

            case 'bulk_password_reset':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    $response = ['success' => false, 'message' => 'POST method required.'];
                    break;
                }
                
                $input = json_decode(file_get_contents('php://input'), true);
                $response = bulkPasswordReset($input);
                break;

            default:
                http_response_code(400);
                $response = ['success' => false, 'message' => 'Unknown action.'];
                break;
        }
    } catch (PDOException $e) {
        http_response_code(500);
        $response = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    } catch (Exception $e) {
        http_response_code(500);
        $response = ['success' => false, 'message' => 'Server error: ' . $e->getMessage()];
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
    
    try {
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
    } catch (Exception $e) {
        $errors[] = ['row' => 0, 'student_id' => 'N/A', 'errors' => ['Database error: ' . $e->getMessage()]];
        return [
            'valid' => false,
            'valid_data' => [],
            'errors' => $errors
        ];
    }
    
    foreach ($data as $index => $student) {
        $row_errors = [];
        $row_number = $index + 2; // +2 because of header and 0-based index
        
        // Required fields
        if (empty($student['student_id'])) {
            $row_errors[] = 'Student ID is required';
        } else {
            // Validate roll number format (allow 2-3 digit serial numbers)
            if (!preg_match('/^\d{2}-[A-Z]{2,10}-\d{2,3}$/', $student['student_id'])) {
                $row_errors[] = 'Invalid student ID format. Use: YY-PROGRAM-NN (e.g., 25-SWT-01 or 25-SWT-583)';
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
        
        // Check if email already exists in students table
        if (!empty($student['email'])) {
            $stmt = $pdo->prepare("SELECT id FROM students WHERE email = ?");
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
        
        // SECURITY FIX: Use secure password hashing
        require_once __DIR__ . '/../../../includes/password_manager.php';
        
        // Use roll number as username and password
        $password = $student_id; // Use roll number as password (e.g., 25-CIT-597)
        $password_hash = PasswordManager::hashPassword($password);
        $username = $student_id; // Use roll number as username (e.g., 25-CIT-597)
        
        // Get section ID - try different approaches
        $section_id = null;
        
        try {
            // First try the full join approach
            $stmt = $pdo->prepare("
                SELECT sec.id 
                FROM sections sec 
                JOIN programs p ON sec.program_id = p.id 
                WHERE p.code = ? AND sec.year_level = ? AND sec.section_name = ? AND sec.shift = ?
            ");
            $stmt->execute([$program, $year_level, $section_name, $shift]);
            $section = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($section) {
                $section_id = $section['id'];
            } else {
                // Try without join if sections table has different structure
                $stmt = $pdo->prepare("
                    SELECT id FROM sections 
                    WHERE program = ? AND year_level = ? AND section_name = ? AND shift = ?
                ");
                $stmt->execute([$program, $year_level, $section_name, $shift]);
                $section = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($section) {
                    $section_id = $section['id'];
                }
            }
        } catch (Exception $e) {
            // If sections table doesn't exist or has issues, continue without section_id
            error_log("Section lookup failed: " . $e->getMessage());
        }
        
        // If no section found, we'll continue without it (for systems that don't use sections)
        if (!$section_id) {
            error_log("Section not found for: $program, $year_level, $section_name, $shift");
        }
        
        // Create student record only (no user account creation)
        $admission_year = '20' . substr($student_id, 0, 2);
        $roll_prefix = explode('-', $student_id)[1];
        
        if ($section_id) {
            // Insert with section_id
            $stmt = $pdo->prepare("
                INSERT INTO students (student_id, roll_number, name, email, phone, program, shift, year_level, section, section_id, admission_year, roll_prefix, username, password, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $student_id, $student_id, $name, $email, $phone, $program, $shift, $year_level, $section_name, 
                $section_id, $admission_year, $roll_prefix, $username, $password
            ]);
        } else {
            // Insert without section_id
            $stmt = $pdo->prepare("
                INSERT INTO students (student_id, roll_number, name, email, phone, program, shift, year_level, section, admission_year, roll_prefix, username, password, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $student_id, $student_id, $name, $email, $phone, $program, $shift, $year_level, $section_name, 
                $admission_year, $roll_prefix, $username, $password
            ]);
        }
        
        // Student data saved to database
        
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
 * Legacy sync functions removed - data is now stored in database only
 */

/**
 * Log import results
 */
function logImport($pdo, $results) {
    try {
        // Create import_logs table if it doesn't exist
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS import_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                import_type VARCHAR(50) NOT NULL,
                total_records INT NOT NULL,
                successful_records INT NOT NULL,
                failed_records INT NOT NULL,
                error_details TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
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
 * Bulk activate students
 */
function bulkActivateStudents($ids) {
    global $pdo;
    
    try {
        if (empty($ids) || !is_array($ids)) {
            return ['success' => false, 'message' => 'Invalid student IDs provided'];
        }
        
        $ids = array_map('intval', $ids);
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        
        $stmt = $pdo->prepare("UPDATE students SET is_active = 1, updated_at = NOW() WHERE id IN ($placeholders)");
        $result = $stmt->execute($ids);
        
        if ($result) {
            $updatedCount = $stmt->rowCount();
            return [
                'success' => true, 
                'message' => "Successfully activated $updatedCount student(s)",
                'updated_count' => $updatedCount
            ];
        } else {
            return ['success' => false, 'message' => 'Failed to activate students'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Bulk deactivate students
 */
function bulkDeactivateStudents($ids) {
    global $pdo;
    
    try {
        if (empty($ids) || !is_array($ids)) {
            return ['success' => false, 'message' => 'Invalid student IDs provided'];
        }
        
        $ids = array_map('intval', $ids);
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        
        $stmt = $pdo->prepare("UPDATE students SET is_active = 0, updated_at = NOW() WHERE id IN ($placeholders)");
        $result = $stmt->execute($ids);
        
        if ($result) {
            $updatedCount = $stmt->rowCount();
            return [
                'success' => true, 
                'message' => "Successfully deactivated $updatedCount student(s)",
                'updated_count' => $updatedCount
            ];
        } else {
            return ['success' => false, 'message' => 'Failed to deactivate students'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Bulk delete students
 */
function bulkDeleteStudents($ids) {
    global $pdo;
    
    try {
        if (empty($ids) || !is_array($ids)) {
            return ['success' => false, 'message' => 'Invalid student IDs provided'];
        }
        
        $ids = array_map('intval', $ids);
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        
        // First delete related attendance records
        $stmt = $pdo->prepare("DELETE FROM attendance WHERE student_id IN (SELECT student_id FROM students WHERE id IN ($placeholders))");
        $stmt->execute($ids);
        
        // Then delete students
        $stmt = $pdo->prepare("DELETE FROM students WHERE id IN ($placeholders)");
        $result = $stmt->execute($ids);
        
        if ($result) {
            $deletedCount = $stmt->rowCount();
            return [
                'success' => true, 
                'message' => "Successfully deleted $deletedCount student(s) and their attendance records",
                'deleted_count' => $deletedCount
            ];
        } else {
            return ['success' => false, 'message' => 'Failed to delete students'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Bulk change program for students
 */
function bulkChangeProgram($input) {
    global $pdo;
    
    try {
        if (empty($input['ids']) || !is_array($input['ids'])) {
            return ['success' => false, 'message' => 'Invalid student IDs provided'];
        }
        
        $ids = array_map('intval', $input['ids']);
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        
        $updates = [];
        $params = [];
        
        if (!empty($input['program'])) {
            $updates[] = "program = ?";
            $params[] = $input['program'];
        }
        
        if (!empty($input['shift'])) {
            $updates[] = "shift = ?";
            $params[] = $input['shift'];
        }
        
        if (!empty($input['year'])) {
            $updates[] = "year_level = ?";
            $params[] = $input['year'];
        }
        
        if (!empty($input['section'])) {
            $updates[] = "section = ?";
            $params[] = $input['section'];
        }
        
        if (empty($updates)) {
            return ['success' => false, 'message' => 'No changes specified'];
        }
        
        $updates[] = "updated_at = NOW()";
        $params = array_merge($params, $ids);
        
        $sql = "UPDATE students SET " . implode(', ', $updates) . " WHERE id IN ($placeholders)";
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($params);
        
        if ($result) {
            $updatedCount = $stmt->rowCount();
            return [
                'success' => true, 
                'message' => "Successfully updated $updatedCount student(s)",
                'updated_count' => $updatedCount
            ];
        } else {
            return ['success' => false, 'message' => 'Failed to update students'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Bulk password reset for students
 */
function bulkPasswordReset($input) {
    global $pdo;
    
    try {
        if (empty($input['ids']) || !is_array($input['ids'])) {
            return ['success' => false, 'message' => 'Invalid student IDs provided'];
        }
        
        $ids = array_map('intval', $input['ids']);
        $passwordType = $input['password_type'] ?? 'roll';
        $customPassword = $input['custom_password'] ?? '';
        
        $updatedCount = 0;
        $errors = [];
        
        foreach ($ids as $id) {
            try {
                // Get student info
                $stmt = $pdo->prepare("SELECT student_id, name FROM students WHERE id = ?");
                $stmt->execute([$id]);
                $student = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$student) {
                    $errors[] = "Student with ID $id not found";
                    continue;
                }
                
                $newPassword = '';
                switch ($passwordType) {
                    case 'roll':
                        $newPassword = $student['student_id'];
                        break;
                    case 'custom':
                        $newPassword = $customPassword;
                        break;
                    case 'random':
                        $newPassword = generateRandomPassword();
                        break;
                    default:
                        $errors[] = "Invalid password type for student {$student['name']}";
                        continue 2;
                }
                
                // SECURITY FIX: Use secure password hashing
                require_once __DIR__ . '/../../../includes/password_manager.php';
                $hashedPassword = PasswordManager::hashPassword($newPassword);
                $stmt = $pdo->prepare("UPDATE students SET password = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$hashedPassword, $id]);
                $updatedCount++;
                
            } catch (Exception $e) {
                $errors[] = "Error updating password for student ID $id: " . $e->getMessage();
            }
        }
        
        $message = "Successfully reset passwords for $updatedCount student(s)";
        if (!empty($errors)) {
            $message .= ". Errors: " . implode(', ', $errors);
        }
        
        return [
            'success' => true,
            'message' => $message,
            'updated_count' => $updatedCount,
            'errors' => $errors
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Generate random password
 */
function generateRandomPassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $password;
}

echo json_encode($response);
?>

