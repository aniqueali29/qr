<?php
/**
 * Attendance API
 * Handles attendance records CRUD operations
 */

// Set error handling to prevent HTML output
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Start output buffering to catch any unexpected output
ob_start();

header('Content-Type: application/json');
// SECURITY FIX: Restrict CORS to specific domains
require_once __DIR__ . '/../includes/config.php';
header_remove('Access-Control-Allow-Origin');
// Set CORS using env, avoid dependency on API config helpers
$__origin = env_get('FRONTEND_ORIGIN', env_get('FRONTEND_URL', 'http://localhost'));
header('Access-Control-Allow-Origin: ' . $__origin);

/**
 * Convert 24-hour time to 12-hour format with AM/PM
 * @param string $time24 Time in 24-hour format (HH:MM:SS or HH:MM)
 * @return string Time in 12-hour format (H:MM AM/PM)
 */
function convert24to12($time24) {
    if (empty($time24)) return '';
    
    // Handle different input formats
    $timeStr = trim($time24);
    
    // If already in 12-hour format, return as is
    if (strpos($timeStr, 'AM') !== false || strpos($timeStr, 'PM') !== false) {
        return $timeStr;
    }
    
    // Extract hours and minutes
    $timeParts = explode(':', $timeStr);
    if (count($timeParts) < 2) return $time24;
    
    $hours = intval($timeParts[0]);
    $minutes = $timeParts[1];
    
    // Determine AM/PM
    $period = $hours >= 12 ? 'PM' : 'AM';
    
    // Convert to 12-hour format
    if ($hours == 0) {
        $hours = 12; // Midnight
    } elseif ($hours > 12) {
        $hours = $hours - 12;
    }
    
    // Format with leading zero for single digits
    $formattedHours = str_pad($hours, 1, '0', STR_PAD_LEFT);
    
    return $formattedHours . ':' . $minutes . ' ' . $period;
}
header('Vary: Origin');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, X-CSRF-Token');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, X-CSRF-Token');
header('Access-Control-Allow-Credentials: true');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // already loaded above
    require_once __DIR__ . '/../includes/auth.php';
    require_once __DIR__ . '/../includes/helpers.php';
} catch (Exception $e) {
    // Clear any output and return JSON error
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Configuration error: ' . $e->getMessage()]);
    exit();
}

// Authentication check - ALWAYS check first
if (!isAdminLoggedIn()) {
    // Only allow bulk_sync with valid API key
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $requestAction = $input['action'] ?? $_GET['action'] ?? 'list';
        
        if ($requestAction === 'bulk_sync') {
            $headers = getallheaders();
            $apiKey = $headers['X-API-Key'] ?? '';
            
            // Use API key from env-driven config
            $validApiKey = API_KEY;
            
            if (!hash_equals($validApiKey, $apiKey)) {
                ob_clean();
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Invalid API key']);
                exit();
            }
            // API key valid, set action for later use
            $action = 'bulk_sync';
        } else if ($requestAction !== 'test') {
            // Not bulk_sync and not authenticated
            ob_clean();
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Authentication required']);
            exit();
        }
    } else {
        // GET/PUT/DELETE requests require authentication (except test)
        $requestAction = $_GET['action'] ?? 'list';
        if ($requestAction !== 'test') {
            ob_clean();
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Authentication required']);
            exit();
        }
    }
}

// Get action from request
$action = $_GET['action'] ?? 'list';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (isset($input['action'])) {
        $action = $input['action'];
    }
}

try {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            handleGetRequest($action);
            break;
        case 'POST':
            handlePostRequest($action);
            break;
        case 'PUT':
            handlePutRequest($action);
            break;
        case 'DELETE':
            handleDeleteRequest($action);
            break;
        default:
            sendJsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
    }
} catch (Exception $e) {
    // Clear any output and return JSON error
    sendJsonResponse(['success' => false, 'error' => 'Server error: ' . $e->getMessage()], 500);
}

/**
 * Send JSON response with proper output buffer cleanup
 */
function sendJsonResponse($data, $statusCode = 200) {
    ob_clean();
    http_response_code($statusCode);
    echo json_encode($data);
    exit();
}

/**
 * Validate and sanitize status filter
 */
function validateStatusFilter($statusParam) {
    $validStatuses = ['Present', 'Absent', 'Check-in', 'Checked-out'];
    
    if (strpos($statusParam, ',') !== false) {
        $statuses = array_map('trim', explode(',', $statusParam));
        $statuses = array_filter($statuses, function($s) use ($validStatuses) {
            return in_array($s, $validStatuses);
        });
        
        if (empty($statuses)) {
            return ['valid' => false, 'error' => 'Invalid status values'];
        }
        
        return [
            'valid' => true,
            'is_array' => true,
            'values' => $statuses,
            'placeholders' => str_repeat('?,', count($statuses) - 1) . '?'
        ];
    } else {
        $status = trim($statusParam);
        if (!in_array($status, $validStatuses)) {
            return ['valid' => false, 'error' => 'Invalid status value'];
        }
        
        return [
            'valid' => true,
            'is_array' => false,
            'values' => [$status]
        ];
    }
}

/**
 * Validate date format and range
 * @param string $date - Date to validate (YYYY-MM-DD format)
 * @param string $label - Field label for error messages
 * @return array - ['valid' => bool, 'error' => string]
 */
function validateDate($date, $label = 'Date') {
    if (empty($date)) {
        return ['valid' => true]; // Optional field
    }
    
    // Check format (YYYY-MM-DD)
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return ['valid' => false, 'error' => "$label must be in YYYY-MM-DD format"];
    }
    
    // Validate actual date
    $parts = explode('-', $date);
    if (!checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0])) {
        return ['valid' => false, 'error' => "$label is not a valid date"];
    }
    
    // Check if date is not too far in the past (more than 5 years)
    $dateObj = new DateTime($date);
    $fiveYearsAgo = new DateTime('-5 years');
    if ($dateObj < $fiveYearsAgo) {
        return ['valid' => false, 'error' => "$label cannot be more than 5 years in the past"];
    }
    
    // Check if date is not in the future
    $today = new DateTime('today');
    if ($dateObj > $today) {
        return ['valid' => false, 'error' => "$label cannot be in the future"];
    }
    
    return ['valid' => true];
}

/**
 * Validate date range
 * @param string $dateFrom - Start date
 * @param string $dateTo - End date
 * @return array - ['valid' => bool, 'error' => string]
 */
function validateDateRange($dateFrom, $dateTo) {
    // Validate individual dates first
    $fromValidation = validateDate($dateFrom, 'Start date');
    if (!$fromValidation['valid']) {
        return $fromValidation;
    }
    
    $toValidation = validateDate($dateTo, 'End date');
    if (!$toValidation['valid']) {
        return $toValidation;
    }
    
    // If both dates provided, validate range
    if (!empty($dateFrom) && !empty($dateTo)) {
        $from = new DateTime($dateFrom);
        $to = new DateTime($dateTo);
        
        if ($from > $to) {
            return ['valid' => false, 'error' => 'Start date must be before or equal to end date'];
        }
        
        // Check if range is not too large (max 1 year)
        $diff = $from->diff($to);
        if ($diff->days > 365) {
            return ['valid' => false, 'error' => 'Date range cannot exceed 1 year'];
        }
    }
    
    return ['valid' => true];
}

/**
 * Validate student ID format
 * @param string $studentId - Student ID to validate
 * @return array - ['valid' => bool, 'error' => string]
 */
function validateStudentId($studentId) {
    if (empty($studentId)) {
        return ['valid' => false, 'error' => 'Student ID is required'];
    }
    
    // Check length (between 5 and 50 characters)
    if (strlen($studentId) < 5 || strlen($studentId) > 50) {
        return ['valid' => false, 'error' => 'Student ID must be between 5 and 50 characters'];
    }
    
    // Check format - alphanumeric with hyphens only
    if (!preg_match('/^[A-Za-z0-9\-]+$/', $studentId)) {
        return ['valid' => false, 'error' => 'Student ID can only contain letters, numbers, and hyphens'];
    }
    
    return ['valid' => true];
}

/**
 * Validate bulk operation IDs
 * @param array $ids - Array of IDs to validate
 * @param int $maxCount - Maximum allowed IDs
 * @return array - ['valid' => bool, 'error' => string, 'ids' => array]
 */
function validateBulkIds($ids, $maxCount = 1000) {
    if (!isset($ids) || !is_array($ids)) {
        return ['valid' => false, 'error' => 'IDs must be an array'];
    }
    
    if (empty($ids)) {
        return ['valid' => false, 'error' => 'No IDs provided'];
    }
    
    // Check count to prevent DoS
    if (count($ids) > $maxCount) {
        return ['valid' => false, 'error' => "Cannot process more than $maxCount records at once"];
    }
    
    // Filter and validate IDs
    $validIds = array_filter(array_map('intval', $ids));
    
    if (empty($validIds)) {
        return ['valid' => false, 'error' => 'No valid IDs provided'];
    }
    
    // Check for duplicates
    if (count($validIds) !== count(array_unique($validIds))) {
        return ['valid' => false, 'error' => 'Duplicate IDs detected'];
    }
    
    return ['valid' => true, 'ids' => $validIds];
}

function handleGetRequest($action) {
    switch ($action) {
        case 'list':
            sendJsonResponse(getAttendanceList());
            break;
        case 'view':
            sendJsonResponse(getAttendanceDetails());
            break;
        case 'export':
            sendJsonResponse(exportAttendance());
            break;
        case 'get_csrf_token':
            // Use unified CSRF token from CSRFProtection to align with validation
            require_once __DIR__ . '/../../includes_ext/csrf_protection.php';
            sendJsonResponse(['success' => true, 'token' => CSRFProtection::getToken()]);
            break;

        case 'get_recent_scans':
            try {
                global $pdo;
                $stmt = $pdo->prepare("
                    SELECT student_name, student_id, status, check_in_time, check_out_time, timestamp
                    FROM attendance 
                    WHERE DATE(timestamp) = CURDATE()
                    ORDER BY timestamp DESC 
                    LIMIT 10
                ");
                $stmt->execute();
                $scans = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $formattedScans = [];
                foreach ($scans as $scan) {
                    $time = $scan['check_out_time'] ?: $scan['check_in_time'] ?: $scan['timestamp'];
                    $formattedScans[] = [
                        'student_name' => $scan['student_name'],
                        'student_id' => $scan['student_id'],
                        'status' => $scan['status'],
                        'time' => convert24to12(date('H:i', strtotime($time)))
                    ];
                }
                
                sendJsonResponse(['success' => true, 'scans' => $formattedScans]);
            } catch (Exception $e) {
                error_log("Error fetching recent scans: " . $e->getMessage());
                sendJsonResponse(['success' => false, 'error' => 'Failed to load recent scans'], 500);
            }
            break;

        case 'get_scan_stats':
            try {
                global $pdo;
                // Get total scans today
                $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM attendance WHERE DATE(timestamp) = CURDATE()");
                $stmt->execute();
                $totalResult = $stmt->fetch(PDO::FETCH_ASSOC);
                $totalScans = $totalResult['total'] ?? 0;
                
                // Get last scan time
                $stmt = $pdo->prepare("
                    SELECT MAX(timestamp) as last_scan 
                    FROM attendance 
                    WHERE DATE(timestamp) = CURDATE()
                ");
                $stmt->execute();
                $lastResult = $stmt->fetch(PDO::FETCH_ASSOC);
                $lastScanTime = $lastResult['last_scan'] ? convert24to12(date('H:i', strtotime($lastResult['last_scan']))) : '-';
                
                sendJsonResponse([
                    'success' => true, 
                    'total_scans' => $totalScans,
                    'last_scan_time' => $lastScanTime
                ]);
            } catch (Exception $e) {
                error_log("Error fetching scan stats: " . $e->getMessage());
                sendJsonResponse(['success' => false, 'error' => 'Failed to load scan stats'], 500);
            }
            break;

        case 'get_student_preview':
            try {
                global $pdo;
                $studentId = $_GET['student_id'] ?? '';
                
                if (empty($studentId)) {
                    sendJsonResponse(['success' => false, 'error' => 'Student ID required'], 400);
                    break;
                }
                
                $stmt = $pdo->prepare("SELECT s.student_id, s.name, s.program, s.shift, s.profile_picture,
                    a.check_in_time, a.check_out_time, a.status
                    FROM students s
                    LEFT JOIN attendance a ON s.student_id = a.student_id 
                        AND DATE(a.timestamp) = CURDATE()
                    WHERE s.student_id = ?
                    ORDER BY a.id DESC LIMIT 1");
                $stmt->execute([$studentId]);
                $student = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($student) {
                    sendJsonResponse([
                        'success' => true,
                        'student' => [
                            'id' => $student['student_id'],
                            'name' => $student['name'],
                            'program' => $student['program'],
                            'shift' => $student['shift'],
                            'photo_url' => $student['profile_picture'] ? '../uploads/students/' . $student['profile_picture'] : null,
                            'today_status' => $student['status'],
                            'check_in_time' => $student['check_in_time'],
                            'check_out_time' => $student['check_out_time']
                        ]
                    ]);
                } else {
                    sendJsonResponse(['success' => false, 'error' => 'Student not found'], 404);
                }
            } catch (Exception $e) {
                error_log("Error fetching student preview: " . $e->getMessage());
                sendJsonResponse(['success' => false, 'error' => 'Failed to load student preview'], 500);
            }
            break;

        case 'get_diagnostics':
            try {
                global $pdo;
                sendJsonResponse([
                    'success' => true,
                    'diagnostics' => [
                        'database' => isset($pdo) && $pdo !== null,
                        'session_active' => session_status() === PHP_SESSION_ACTIVE,
                        'csrf_token_set' => isset($_SESSION['csrf_token']),
                        'timestamp' => date('Y-m-d H:i:s'),
                        'timezone' => date_default_timezone_get(),
                        'php_version' => PHP_VERSION,
                        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown'
                    ]
                ]);
            } catch (Exception $e) {
                error_log("Error fetching diagnostics: " . $e->getMessage());
                sendJsonResponse(['success' => false, 'error' => 'Failed to load diagnostics'], 500);
            }
            break;

        case 'mark_absent_students':
            try {
                // CSRF validation - temporarily disabled for debugging
                // require_once __DIR__ . '/../../includes_ext/csrf_protection.php';
                // CSRFProtection::requireValidation();
                
                global $pdo;
                
                // Load settings
                require_once __DIR__ . '/settings.php';
                $settingsApi = new AdminSettingsAPI();
                $allSettings = $settingsApi->getAllSettings();
                $timingSettings = [];
                if ($allSettings['success'] && isset($allSettings['data']['timings'])) {
                    foreach ($allSettings['data']['timings'] as $setting) {
                        $timingSettings[$setting['key']] = $setting['value'];
                    }
                }
                
                // Get current time in configured timezone
                $timezone = new DateTimeZone($timingSettings['timezone'] ?? 'Asia/Karachi');
                $currentTime = new DateTime('now', $timezone);
                $currentDate = $currentTime->format('Y-m-d');
                $currentTimeOnly = $currentTime->format('H:i:s');
                
                // Get check-in end times for both shifts
                $morningCheckinEnd = $timingSettings['morning_checkin_end'] ?? null;
                $eveningCheckinEnd = $timingSettings['evening_checkin_end'] ?? null;
                
                $markedAbsent = 0;
                $errors = [];
                
                // Check if we should mark absent (after both check-in windows close)
                if ($currentTimeOnly > $morningCheckinEnd && $currentTimeOnly > $eveningCheckinEnd) {
                    // Get all students who don't have any attendance record for today
                    $stmt = $pdo->prepare("
                        SELECT s.id, s.student_id, s.name, s.program, s.shift
                        FROM students s
                        LEFT JOIN attendance a ON s.student_id = a.student_id AND DATE(a.timestamp) = ?
                        WHERE a.student_id IS NULL
                    ");
                    $stmt->execute([$currentDate]);
                    $absentStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($absentStudents as $student) {
                        try {
                            $stmt = $pdo->prepare("
                                INSERT INTO attendance 
                                (student_id, student_name, program, shift, status, timestamp, notes, created_at) 
                                VALUES (?, ?, ?, ?, 'Absent', ?, 'Automatically marked absent after check-in window closed', NOW())
                            ");
                            $stmt->execute([
                                $student['student_id'],
                                $student['name'],
                                $student['program'],
                                $student['shift'],
                                $currentTime->format('Y-m-d H:i:s')
                            ]);
                            $markedAbsent++;
                        } catch (Exception $e) {
                            $errors[] = "Failed to mark {$student['student_id']} as absent: " . $e->getMessage();
                        }
                    }
                }
                
                sendJsonResponse([
                    'success' => true,
                    'message' => "Marked {$markedAbsent} students as absent",
                    'marked_count' => $markedAbsent,
                    'errors' => $errors,
                    'checkin_windows_closed' => $currentTimeOnly > $morningCheckinEnd && $currentTimeOnly > $eveningCheckinEnd
                ]);
                
            } catch (Exception $e) {
                error_log("Error marking absent students: " . $e->getMessage());
                sendJsonResponse(['success' => false, 'error' => 'Failed to mark absent students: ' . $e->getMessage()], 500);
            }
            break;

        case 'auto_mark_absent':
            try {
                // CSRF validation - temporarily disabled for debugging
                // require_once __DIR__ . '/../../includes_ext/csrf_protection.php';
                // CSRFProtection::requireValidation();
                
                // Use the advanced auto absent marker
                require_once __DIR__ . '/../../cron/auto_absent.php';
                
                $marker = new AutoAbsentMarker();
                $result = $marker->run();
                
                sendJsonResponse($result);
                
            } catch (Exception $e) {
                error_log("Error in auto mark absent: " . $e->getMessage());
                sendJsonResponse(['success' => false, 'error' => 'Auto absent marking failed: ' . $e->getMessage()], 500);
            }
            break;

        case 'test':
            sendJsonResponse(['success' => true, 'message' => 'API is working', 'timestamp' => date('Y-m-d H:i:s')]);
            break;

        case 'debug_absent':
            try {
                global $pdo;
                sendJsonResponse([
                    'success' => true, 
                    'message' => 'Debug endpoint working',
                    'pdo_available' => isset($pdo) && $pdo !== null,
                    'session_id' => session_id(),
                    'csrf_token' => $_SESSION['csrf_token'] ?? 'not_set',
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
            } catch (Exception $e) {
                sendJsonResponse(['success' => false, 'error' => $e->getMessage()]);
            }
            break;
        default:
            sendJsonResponse(['success' => false, 'error' => 'Invalid action'], 400);
    }
}

function handlePostRequest($action) {
    switch ($action) {
        case 'create':
            sendJsonResponse(createAttendance());
            break;
        case 'save_attendance':
            sendJsonResponse(saveAttendanceAuto());
            break;
        case 'bulk_sync':
            sendJsonResponse(bulkSyncAttendance());
            break;
        case 'bulk_delete_attendance':
            sendJsonResponse(bulkDeleteAttendance());
            break;
        case 'bulk_change_status':
            sendJsonResponse(bulkChangeStatus());
            break;
        case 'mark_absent_students':
            handleMarkAbsentStudents();
            break;
        case 'auto_mark_absent':
            handleAutoMarkAbsent();
            break;
        case 'debug_absent':
            handleDebugAbsent();
            break;
        default:
            sendJsonResponse(['success' => false, 'error' => 'Invalid action'], 400);
    }
}

function handlePutRequest($action) {
    switch ($action) {
        case 'update':
            sendJsonResponse(updateAttendance());
            break;
        default:
            sendJsonResponse(['success' => false, 'error' => 'Invalid action'], 400);
    }
}

function handleDeleteRequest($action) {
    switch ($action) {
        case 'delete':
            sendJsonResponse(deleteAttendance());
            break;
        default:
            sendJsonResponse(['success' => false, 'error' => 'Invalid action'], 400);
    }
}

/**
 * Get attendance list with pagination and filters
 */
function getAttendanceList() {
    global $pdo;
    
    try {
        // Check if PDO connection exists
        if (!isset($pdo) || $pdo === null) {
            error_log("Attendance API: Database connection not available");
            return ['success' => false, 'error' => 'Service temporarily unavailable'];
        }
        
        // Test if attendance table exists
        try {
            $testStmt = $pdo->query("SHOW TABLES LIKE 'attendance'");
            if (!$testStmt || $testStmt->rowCount() == 0) {
                error_log("Attendance API: Attendance table does not exist");
                return ['success' => false, 'error' => 'Service unavailable'];
            }
        } catch (Exception $e) {
            error_log("Attendance API Database Error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Service temporarily unavailable'];
        }
        
        // Debug: Log the request parameters
        error_log("Attendance API called with params: " . json_encode($_GET));
        $page = (int)($_GET['page'] ?? 1);
        $limit = 20;
        $offset = ($page - 1) * $limit;
        
        // Build WHERE clause
        $whereConditions = ["1=1"];
        $params = [];
        
        // Apply filters with validation
        if (!empty($_GET['date_from'])) {
            $dateValidation = validateDate($_GET['date_from'], 'Start date');
            if (!$dateValidation['valid']) {
                return ['success' => false, 'error' => $dateValidation['error']];
            }
            $whereConditions[] = "DATE(a.timestamp) >= ?";
            $params[] = $_GET['date_from'];
        }
        
        if (!empty($_GET['date_to'])) {
            $dateValidation = validateDate($_GET['date_to'], 'End date');
            if (!$dateValidation['valid']) {
                return ['success' => false, 'error' => $dateValidation['error']];
            }
            $whereConditions[] = "DATE(a.timestamp) <= ?";
            $params[] = $_GET['date_to'];
        }
        
        // Validate date range if both dates provided
        if (!empty($_GET['date_from']) && !empty($_GET['date_to'])) {
            $rangeValidation = validateDateRange($_GET['date_from'], $_GET['date_to']);
            if (!$rangeValidation['valid']) {
                return ['success' => false, 'error' => $rangeValidation['error']];
            }
        }
        
        if (!empty($_GET['student_id'])) {
            $studentIdValidation = validateStudentId($_GET['student_id']);
            if (!$studentIdValidation['valid']) {
                return ['success' => false, 'error' => $studentIdValidation['error']];
            }
            $whereConditions[] = "a.student_id = ?";
            $params[] = $_GET['student_id'];
        }
        
        if (!empty($_GET['status'])) {
            $statusValidation = validateStatusFilter($_GET['status']);
            if (!$statusValidation['valid']) {
                return ['success' => false, 'error' => $statusValidation['error']];
            }
            
            if ($statusValidation['is_array']) {
                $whereConditions[] = "a.status IN (" . $statusValidation['placeholders'] . ")";
                $params = array_merge($params, $statusValidation['values']);
            } else {
                $whereConditions[] = "a.status = ?";
                $params[] = $statusValidation['values'][0];
            }
        }
        
        if (!empty($_GET['program'])) {
            $whereConditions[] = "a.program = ?";
            $params[] = $_GET['program'];
        }
        
        if (!empty($_GET['shift'])) {
            $whereConditions[] = "a.shift = ?";
            $params[] = $_GET['shift'];
        }
        
        if (!empty($_GET['search'])) {
            $whereConditions[] = "(a.student_id LIKE ? OR a.student_name LIKE ?)";
            $searchTerm = '%' . $_GET['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        // Get total count
        $countSql = "
            SELECT COUNT(*) as total 
            FROM attendance a
            WHERE $whereClause
        ";
        $stmt = $pdo->prepare($countSql);
        if (!$stmt) {
            error_log("Failed to prepare count query: " . implode(', ', $pdo->errorInfo()));
            return ['success' => false, 'error' => 'Service error'];
        }
        $stmt->execute($params);
        $total = $stmt->fetch()['total'];
        
        // Get attendance records
        $sql = "
            SELECT 
                a.id,
                a.student_id,
                a.student_name,
                a.student_id as roll_number,
                a.student_name as student_name,
                a.program,
                a.shift,
                a.status,
                a.check_in_time as checkin_time,
                a.check_out_time as checkout_time,
                a.session_duration as duration_minutes,
                a.timestamp,
                DATE(a.timestamp) as date,
                TIME(a.timestamp) as time,
                CASE 
                    WHEN a.check_out_time IS NOT NULL 
                    THEN CONCAT(
                        FLOOR(a.session_duration / 60), 'h ',
                        MOD(a.session_duration, 60), 'm'
                    )
                    ELSE NULL 
                END as duration
            FROM attendance a
            WHERE $whereClause
            ORDER BY a.timestamp DESC
            LIMIT $limit OFFSET $offset
        ";
        
        $stmt = $pdo->prepare($sql);
        if (!$stmt) {
            error_log("Failed to prepare main query: " . implode(', ', $pdo->errorInfo()));
            return ['success' => false, 'error' => 'Service error'];
        }
        $stmt->execute($params);
        $records = $stmt->fetchAll();
        
        $totalPages = ceil($total / $limit);
        
        return [
            'success' => true,
            'data' => [
                'records' => $records,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'total_records' => $total,
                    'per_page' => $limit
                ]
            ]
        ];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Get attendance details
 */
function getAttendanceDetails() {
    global $pdo;
    
    try {
        $attendanceId = $_GET['id'] ?? 0;
        
        if (!$attendanceId) {
            return ['success' => false, 'error' => 'Attendance ID required'];
        }
        
        $stmt = $pdo->prepare("
            SELECT 
                a.id,
                a.student_id,
                a.student_name,
                a.student_id as roll_number,
                a.student_name as student_name,
                a.program,
                a.shift,
                a.status,
                a.check_in_time as checkin_time,
                a.check_out_time as checkout_time,
                a.session_duration as duration_minutes,
                a.timestamp,
                a.notes,
                DATE(a.timestamp) as date,
                TIME(a.timestamp) as time,
                CASE 
                    WHEN a.check_out_time IS NOT NULL 
                    THEN CONCAT(
                        FLOOR(a.session_duration / 60), 'h ',
                        MOD(a.session_duration, 60), 'm'
                    )
                    ELSE NULL 
                END as duration
            FROM attendance a
            WHERE a.id = ?
        ");
        $stmt->execute([$attendanceId]);
        $record = $stmt->fetch();
        
        if (!$record) {
            return ['success' => false, 'error' => 'Attendance record not found'];
        }
        
        return [
            'success' => true,
            'data' => $record
        ];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Create new attendance record
 */
function createAttendance() {
    global $pdo;
    
    try {
        // Validate CSRF token
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            return ['success' => false, 'error' => 'Invalid CSRF token'];
        }
        
        $studentId = sanitizeInput($_POST['student_id'] ?? '');
        $studentName = sanitizeInput($_POST['student_name'] ?? '');
        $status = sanitizeInput($_POST['status'] ?? '');
        $program = sanitizeInput($_POST['program'] ?? '');
        $shift = sanitizeInput($_POST['shift'] ?? '');
        $date = sanitizeInput($_POST['date'] ?? date('Y-m-d'));
        $notes = sanitizeInput($_POST['notes'] ?? '');
        
        // Validate required fields
        if (empty($studentId)) {
            return ['success' => false, 'error' => 'Student ID is required'];
        }
        
        if (empty($status)) {
            return ['success' => false, 'error' => 'Status is required'];
        }
        
        // Validate student ID format
        $studentIdValidation = validateStudentId($studentId);
        if (!$studentIdValidation['valid']) {
            return ['success' => false, 'error' => $studentIdValidation['error']];
        }
        
        // Validate status value
        $validStatuses = ['Present', 'Absent', 'Check-in', 'Checked-out'];
        if (!in_array($status, $validStatuses)) {
            return ['success' => false, 'error' => 'Invalid status value. Must be one of: ' . implode(', ', $validStatuses)];
        }
        
        // Validate date if provided
        $dateValidation = validateDate($date, 'Date');
        if (!$dateValidation['valid']) {
            return ['success' => false, 'error' => $dateValidation['error']];
        }
        
        // Validate student name length if provided
        if (!empty($studentName) && strlen($studentName) > 100) {
            return ['success' => false, 'error' => 'Student name must not exceed 100 characters'];
        }
        
        // Validate notes length if provided
        if (!empty($notes) && strlen($notes) > 500) {
            return ['success' => false, 'error' => 'Notes must not exceed 500 characters'];
        }
        
        // Check for duplicate attendance on the same date
        $stmt = $pdo->prepare("
            SELECT id FROM attendance 
            WHERE student_id = ? AND DATE(timestamp) = ?
        ");
        $stmt->execute([$studentId, $date]);
        if ($stmt->fetch()) {
            return ['success' => false, 'error' => 'Attendance record already exists for this student on this date'];
        }
        
        // Create attendance record with proper timestamp
        $timestamp = $date . ' ' . date('H:i:s');
        $stmt = $pdo->prepare("
            INSERT INTO attendance (student_id, student_name, status, timestamp, program, shift, notes, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$studentId, $studentName, $status, $timestamp, $program, $shift, $notes]);
        
        $attendanceId = $pdo->lastInsertId();
        
        // Log the action
        logAdminAction('ATTENDANCE_CREATED', "Created attendance record ID: $attendanceId for student: $studentId");
        
        return [
            'success' => true,
            'message' => 'Attendance record created successfully',
            'data' => ['id' => $attendanceId]
        ];
    } catch (Exception $e) {
        error_log("Create attendance error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Failed to create attendance record'];
    }
}

/**
 * Bulk sync attendance records from CSV
 */
function bulkSyncAttendance() {
    global $pdo;
    
    try {
        // Check API key authentication
        $headers = getallheaders();
        $apiKey = $headers['X-API-Key'] ?? '';
        
        // SECURITY FIX: Use secure configuration for API key
        require_once __DIR__ . '/../../includes_ext/secure_config.php';
        $config = SecureConfig::load();
        if (!hash_equals($config['API_KEY'], $apiKey)) {
            return ['success' => false, 'error' => 'Invalid API key'];
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $records = $input['records'] ?? [];
        
        if (empty($records)) {
            return ['success' => false, 'error' => 'No records provided'];
        }
        
        $inserted = 0;
        $updated = 0;
        
        foreach ($records as $record) {
            // Parse the timestamp and status to extract check-in/check-out times
            $timestamp = $record['timestamp'];
            $status = $record['status'];
            $checkInTime = null;
            $checkOutTime = null;
            $sessionDuration = null;
            
            // Handle different status formats from CSV
            if (strpos($status, 'Check-in') !== false && strpos($status, 'Checked out') !== false) {
                // This is a consolidated record: "Check-in (Checked out at...)"
                $finalStatus = 'Present'; // Student was present and attended
                
                // Extract check-in time from timestamp
                $checkInTime = $timestamp;
                
                // Extract check-out time from status text
                if (preg_match('/Checked out at (.+?)\)/', $status, $matches)) {
                    $checkOutTime = trim($matches[1]);
                }
                
                $status = $finalStatus;
                
                // Calculate duration if we have both times
                if ($checkInTime && $checkOutTime) {
                    try {
                        $checkIn = new DateTime($checkInTime);
                        $checkOut = new DateTime($checkOutTime);
                        $sessionDuration = $checkOut->getTimestamp() - $checkIn->getTimestamp();
                        $sessionDuration = round($sessionDuration / 60); // Convert to minutes
                    } catch (Exception $e) {
                        $sessionDuration = null;
                    }
                }
            } elseif (strpos($status, 'Check-in') !== false) {
                // This is a check-in record
                $checkInTime = $timestamp;
                $status = 'Check-in';
            } elseif (strpos($status, 'Check-out') !== false || strpos($status, 'Checked out') !== false) {
                // This is a check-out record
                $checkOutTime = $timestamp;
                $status = 'Checked-out';
            } elseif (strpos($status, 'Present') !== false) {
                // This is a consolidated present record
                $status = 'Present';
                // Try to extract check-in and check-out times from consolidated format
                if (strpos($timestamp, ' to ') !== false) {
                    $times = explode(' to ', $timestamp);
                    if (count($times) == 2) {
                        $checkInTime = trim($times[0]);
                        $checkOutTime = trim($times[1]);
                        // Calculate duration in minutes
                        $checkIn = new DateTime($checkInTime);
                        $checkOut = new DateTime($checkOutTime);
                        $sessionDuration = $checkOut->getTimestamp() - $checkIn->getTimestamp();
                        $sessionDuration = round($sessionDuration / 60); // Convert to minutes
                    }
                }
            }
            
            // Check if record exists
            $stmt = $pdo->prepare("
                SELECT id FROM attendance 
                WHERE student_id = ? AND DATE(timestamp) = DATE(?)
            ");
            $stmt->execute([$record['student_id'], $timestamp]);
            
            if ($stmt->fetch()) {
                // Update existing record
                $stmt = $pdo->prepare("
                    UPDATE attendance 
                    SET status = ?, shift = ?, program = ?, 
                        check_in_time = ?, check_out_time = ?, session_duration = ?,
                        updated_at = NOW()
                    WHERE student_id = ? AND DATE(timestamp) = DATE(?)
                ");
                $stmt->execute([
                    $status,
                    $record['shift'] ?? null,
                    $record['program'] ?? null,
                    $checkInTime,
                    $checkOutTime,
                    $sessionDuration,
                    $record['student_id'],
                    $timestamp
                ]);
                $updated++;
            } else {
                // Insert new record
                $stmt = $pdo->prepare("
                    INSERT INTO attendance (student_id, student_name, status, timestamp, shift, program, 
                                          check_in_time, check_out_time, session_duration, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $record['student_id'],
                    $record['student_name'] ?? 'Unknown',
                    $status,
                    $timestamp,
                    $record['shift'] ?? null,
                    $record['program'] ?? null,
                    $checkInTime,
                    $checkOutTime,
                    $sessionDuration
                ]);
                $inserted++;
            }
        }
        
        return [
            'success' => true,
            'message' => 'Bulk sync completed',
            'data' => [
                'inserted' => $inserted,
                'updated' => $updated,
                'total' => count($records)
            ]
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Update attendance record
 */
function updateAttendance() {
    global $pdo;
    
    try {
        $attendanceId = $_GET['id'] ?? 0;
        
        if (!$attendanceId) {
            return ['success' => false, 'error' => 'Attendance ID required'];
        }
        
        // Validate attendance ID is numeric and positive
        if (!is_numeric($attendanceId) || $attendanceId <= 0) {
            return ['success' => false, 'error' => 'Invalid attendance ID'];
        }
        
        // Validate CSRF token for PUT requests
        if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
            $input = json_decode(file_get_contents('php://input'), true);
            $csrf_token = $input['csrf_token'] ?? '';
        } else {
            $csrf_token = $_POST['csrf_token'] ?? '';
        }
        
        if (!validateCSRFToken($csrf_token)) {
            error_log("CSRF validation failed for attendance update");
            return ['success' => false, 'error' => 'Invalid security token'];
        }
        
        // Handle both POST and PUT requests
        if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
            // Parse PUT request data
            $input = json_decode(file_get_contents('php://input'), true);
            $status = sanitizeInput($input['status'] ?? '');
            $notes = sanitizeInput($input['notes'] ?? '');
        } else {
            $status = sanitizeInput($_POST['status'] ?? '');
            $notes = sanitizeInput($_POST['notes'] ?? '');
        }
        
        // Validate required fields
        if (empty($status)) {
            return ['success' => false, 'error' => 'Status is required'];
        }
        
        // Validate status value
        $validStatuses = ['Present', 'Absent', 'Check-in', 'Checked-out'];
        if (!in_array($status, $validStatuses)) {
            return ['success' => false, 'error' => 'Invalid status value. Must be one of: ' . implode(', ', $validStatuses)];
        }
        
        // Validate notes length if provided
        if (!empty($notes) && strlen($notes) > 500) {
            return ['success' => false, 'error' => 'Notes must not exceed 500 characters'];
        }
        
        // Check if record exists before updating
        $stmt = $pdo->prepare("SELECT id FROM attendance WHERE id = ?");
        $stmt->execute([$attendanceId]);
        if (!$stmt->fetch()) {
            return ['success' => false, 'error' => 'Attendance record not found'];
        }
        
        // Update attendance record
        $stmt = $pdo->prepare("
            UPDATE attendance 
            SET status = ?, notes = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$status, $notes, $attendanceId]);
        
        // Log the action
        logAdminAction('ATTENDANCE_UPDATED', "Updated attendance record ID: $attendanceId to status: $status");
        
        return [
            'success' => true,
            'message' => 'Attendance record updated successfully'
        ];
    } catch (Exception $e) {
        error_log("Update attendance error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Failed to update attendance record'];
    }
}

/**
 * Delete attendance record
 */
function deleteAttendance() {
    global $pdo;
    
    // SECURITY FIX: Add CSRF protection
    require_once __DIR__ . '/../../includes_ext/csrf_protection.php';
    CSRFProtection::requireValidation();
    
    try {
        $attendanceId = $_GET['id'] ?? 0;
        
        if (!$attendanceId) {
            return ['success' => false, 'error' => 'Attendance ID required'];
        }
        
        // Get attendance info for logging and cleanup
        $stmt = $pdo->prepare("SELECT student_id, status FROM attendance WHERE id = ?");
        $stmt->execute([$attendanceId]);
        $attendance = $stmt->fetch();
        
        if (!$attendance) {
            return ['success' => false, 'error' => 'Attendance record not found'];
        }
        
        $studentId = $attendance['student_id'];
        $status = $attendance['status'];
        
        // Start transaction
        $pdo->beginTransaction();
        
        try {
            // If deleting a Check-in status, also delete from check_in_sessions
            if ($status === 'Check-in') {
                $stmt = $pdo->prepare("DELETE FROM check_in_sessions WHERE student_id = ? AND is_active = 1");
                $stmt->execute([$studentId]);
        }
        
        // Delete attendance record
        $stmt = $pdo->prepare("DELETE FROM attendance WHERE id = ?");
        $stmt->execute([$attendanceId]);
            
            // Commit transaction
            $pdo->commit();
        
        // Log the action
        logAdminAction('ATTENDANCE_DELETED', "Deleted attendance record ID: $attendanceId");
        
        return [
            'success' => true,
            'message' => 'Attendance record deleted successfully'
        ];
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Export attendance records
 */
function exportAttendance() {
    global $pdo;
    
    try {
        // Build WHERE clause for export
        $whereConditions = ["1=1"];
        $params = [];
        
        // Apply filters
        if (!empty($_GET['date_from'])) {
            $whereConditions[] = "DATE(a.timestamp) >= ?";
            $params[] = $_GET['date_from'];
        }
        
        if (!empty($_GET['date_to'])) {
            $whereConditions[] = "DATE(a.timestamp) <= ?";
            $params[] = $_GET['date_to'];
        }
        
        if (!empty($_GET['student_id'])) {
            $whereConditions[] = "a.student_id = ?";
            $params[] = $_GET['student_id'];
        }
        
        if (!empty($_GET['status'])) {
            $statusValidation = validateStatusFilter($_GET['status']);
            if (!$statusValidation['valid']) {
                return ['success' => false, 'error' => $statusValidation['error']];
            }
            
            if ($statusValidation['is_array']) {
                $whereConditions[] = "a.status IN (" . $statusValidation['placeholders'] . ")";
                $params = array_merge($params, $statusValidation['values']);
            } else {
                $whereConditions[] = "a.status = ?";
                $params[] = $statusValidation['values'][0];
            }
        }
        
        if (!empty($_GET['program'])) {
            $whereConditions[] = "a.program = ?";
            $params[] = $_GET['program'];
        }
        
        if (!empty($_GET['shift'])) {
            $whereConditions[] = "a.shift = ?";
            $params[] = $_GET['shift'];
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        // Get attendance records for export
        $sql = "
            SELECT 
                a.student_id as roll_number,
                a.student_name as student_name,
                a.program,
                a.shift,
                a.status,
                DATE(a.timestamp) as date,
                TIME(a.timestamp) as time,
                TIME(a.check_out_time) as checkout_time,
                CASE 
                    WHEN a.check_out_time IS NOT NULL 
                    THEN CONCAT(
                        FLOOR(a.session_duration / 60), 'h ',
                        MOD(a.session_duration, 60), 'm'
                    )
                    ELSE NULL 
                END as duration
            FROM attendance a
            WHERE $whereClause
            ORDER BY a.timestamp DESC
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $records = $stmt->fetchAll();
        
        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="attendance_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($output, [
            'Roll Number', 'Student Name', 'Program', 'Shift', 'Status', 
            'Date', 'Check-in Time', 'Check-out Time', 'Duration'
        ]);
        
        // CSV data
        foreach ($records as $record) {
            fputcsv($output, [
                $record['roll_number'],
                $record['student_name'],
                $record['program'],
                $record['shift'],
                $record['status'],
                $record['date'],
                $record['time'],
                $record['checkout_time'],
                $record['duration']
            ]);
        }
        
        fclose($output);
        exit();
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Bulk delete attendance records
 */
function bulkDeleteAttendance() {
    global $pdo;
    
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Validate bulk IDs
        $validation = validateBulkIds($input['ids'] ?? null, 500);
        if (!$validation['valid']) {
            return ['success' => false, 'error' => $validation['error']];
        }
        
        $ids = $validation['ids'];
        
        // Use transaction for atomic operation
        $pdo->beginTransaction();
        
        try {
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';
            
            // Get records for logging and cleanup
            $stmt = $pdo->prepare("SELECT id, student_id, status FROM attendance WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            $records = $stmt->fetchAll();
            
            // Get unique student IDs with Check-in status for cleanup
            $checkinStudentIds = [];
            foreach ($records as $record) {
                if ($record['status'] === 'Check-in') {
                    $checkinStudentIds[] = $record['student_id'];
                }
            }
            
            // If any Check-in records exist, clean up check_in_sessions
            if (!empty($checkinStudentIds)) {
                $checkinPlaceholders = str_repeat('?,', count($checkinStudentIds) - 1) . '?';
                $stmt = $pdo->prepare("DELETE FROM check_in_sessions WHERE student_id IN ($checkinPlaceholders) AND is_active = 1");
                $stmt->execute($checkinStudentIds);
            }
            
            // Delete attendance records
            $stmt = $pdo->prepare("DELETE FROM attendance WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            
            $deletedCount = $stmt->rowCount();
            
            // Log the action
            logAdminAction('BULK_ATTENDANCE_DELETED', "Deleted $deletedCount attendance records");
            
            $pdo->commit();
            
            return [
                'success' => true, 
                'message' => "Successfully deleted $deletedCount attendance record(s)",
                'deleted_count' => $deletedCount
            ];
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Bulk delete attendance error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to delete attendance records'];
        }
    } catch (Exception $e) {
        error_log("Bulk delete attendance error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Service temporarily unavailable'];
    }
}

/**
 * Bulk change status for attendance records
 */
function bulkChangeStatus() {
    global $pdo;
    
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Validate bulk IDs
        $validation = validateBulkIds($input['ids'] ?? null, 500);
        if (!$validation['valid']) {
            return ['success' => false, 'error' => $validation['error']];
        }
        
        $ids = $validation['ids'];
        
        // Validate status
        if (!isset($input['status']) || empty($input['status'])) {
            return ['success' => false, 'error' => 'Status is required'];
        }
        
        $validStatuses = ['Present', 'Absent', 'Check-in', 'Checked-out'];
        $status = trim($input['status']);
        if (!in_array($status, $validStatuses)) {
            return ['success' => false, 'error' => 'Invalid status value. Must be one of: ' . implode(', ', $validStatuses)];
        }
        
        $notes = sanitizeInput($input['notes'] ?? '');
        
        // Validate notes length if provided
        if (!empty($notes) && strlen($notes) > 500) {
            return ['success' => false, 'error' => 'Notes must not exceed 500 characters'];
        }
        
        // Use transaction for atomic operation
        $pdo->beginTransaction();
        
        try {
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';
            
            $stmt = $pdo->prepare("UPDATE attendance SET status = ?, notes = ?, updated_at = NOW() WHERE id IN ($placeholders)");
            $params = array_merge([$status, $notes], $ids);
            $stmt->execute($params);
            
            $updatedCount = $stmt->rowCount();
            
            // Log the action
            logAdminAction('BULK_ATTENDANCE_STATUS_CHANGED', "Updated $updatedCount attendance records to $status");
            
            $pdo->commit();
            
            return [
                'success' => true, 
                'message' => "Successfully updated $updatedCount attendance record(s) to $status",
                'updated_count' => $updatedCount
            ];
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Bulk change status error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to update attendance records'];
        }
    } catch (Exception $e) {
        error_log("Bulk change status error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Service temporarily unavailable'];
    }
}

/**
 * Auto check-in/out based on settings and current state
 * Expects JSON body: { student_id, notes? }
 */
function saveAttendanceAuto() {
    global $pdo;
    
    try {
        // CSRF validation via header/body using shared protection
        require_once __DIR__ . '/../../includes_ext/csrf_protection.php';
        CSRFProtection::requireValidation();

        // Parse input
        $input = json_decode(file_get_contents('php://input'), true);
        $studentId = sanitizeInput($input['student_id'] ?? '');
        $notes = sanitizeInput($input['notes'] ?? '');

        if (empty($studentId)) {
            return ['success' => false, 'message' => 'Student ID is required'];
        }

        // Lookup student for name/program/shift
        $stmt = $pdo->prepare("SELECT student_id, name, program, shift FROM students WHERE student_id = ? LIMIT 1");
        $stmt->execute([$studentId]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$student) {
            return ['success' => false, 'message' => 'Student not found'];
        }

        $studentName = $student['name'] ?? 'Unknown';
        $program = $student['program'] ?? null;
        $shift = $student['shift'] ?? null;
        if (!$shift || !in_array($shift, ['Morning', 'Evening'])) {
            // Fallback to roll parser then Morning
            try {
                require_once __DIR__ . '/../../api/roll_parser.php';
                $shift = RollParser::getShift($studentId) ?: 'Morning';
            } catch (Exception $e) {
                $shift = 'Morning';
            }
        }

        // Load settings
        $settings = loadTimingSettings();
        $tz = new DateTimeZone($settings['timezone']);
        $now = new DateTime('now', $tz);
        $nowTime = $now->format('H:i:s');
        $todayDate = $now->format('Y-m-d');

        // Resolve shift timing window
        $timing = getShiftTimingWindow($settings, $shift);

        // Check if there is an existing attendance record today (get the most recent one)
        $stmt = $pdo->prepare("SELECT id, status, check_in_time, check_out_time FROM attendance WHERE student_id = ? AND DATE(timestamp) = ? ORDER BY timestamp DESC LIMIT 1");
        $stmt->execute([$studentId, $todayDate]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        // If student already has any attendance record for today, prevent duplicate check-in
        if ($existing) {
            if (!empty($existing['check_in_time']) && empty($existing['check_out_time'])) {
                // Student is already checked in, attempt checkout
                if (!($nowTime >= $timing['checkout_start'] && $nowTime <= $timing['checkout_end'])) {
                    return [
                        'success' => false,
                        'mode' => 'checkout',
                        'message' => "Check-out not allowed now. Window: {$timing['checkout_start']} - {$timing['checkout_end']}"
                    ];
                }

                try {
                    // Calculate session duration (no minimum duration enforcement)
                    $checkIn = new DateTime($existing['check_in_time'], $tz);
                    $duration = $now->getTimestamp() - $checkIn->getTimestamp();
                    $sessionMinutes = (int)round($duration / 60);
                    
                    // Mark as Present on successful checkout (final state)
                    $stmt = $pdo->prepare("UPDATE attendance SET status = ?, check_out_time = ?, session_duration = ?, notes = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute(['Present', $now->format('Y-m-d H:i:s'), $sessionMinutes, $notes, $existing['id']]);

                    // Check if update was successful
                    if ($stmt->rowCount() === 0) {
                        error_log("Checkout failed: No rows updated for student $studentId, record ID {$existing['id']}");
                        return [
                            'success' => false,
                            'mode' => 'checkout',
                            'message' => 'Failed to update attendance record. Please try again.'
                        ];
                    }

                    return [
                        'success' => true,
                        'mode' => 'checkout',
                        'message' => 'Checked out successfully',
                        'details' => [
                            'duration_minutes' => $sessionMinutes
                        ]
                    ];
                } catch (PDOException $e) {
                    error_log("Checkout database error for student $studentId: " . $e->getMessage());
                    return [
                        'success' => false,
                        'mode' => 'checkout',
                        'message' => 'Check-out failed due to a database error. Please try again.'
                    ];
                } catch (Exception $e) {
                    error_log("Checkout error for student $studentId: " . $e->getMessage());
                    return [
                        'success' => false,
                        'mode' => 'checkout',
                        'message' => 'Check-out failed: ' . $e->getMessage()
                    ];
                }
            } else {
                // Student already has attendance recorded for today (either checked out or other status)
                $status = $existing['status'] ?? 'Unknown';
                $checkOutTime = $existing['check_out_time'] ?? null;
                
                error_log("Duplicate check-in attempt for student $studentId. Status: $status, Check-out time: $checkOutTime");
                
                if ($checkOutTime) {
                    // Student has already completed attendance (checked out)
                    return [
                        'success' => false,
                        'mode' => 'checkin',
                        'message' => 'Attendance already completed for today. Cannot check in again.'
                    ];
                } else {
                    // Student has some other attendance status
                    return [
                        'success' => false,
                        'mode' => 'checkin',
                        'message' => 'Attendance already recorded for today (Status: ' . $status . ')'
                    ];
                }
            }
        }

        // Attempt check-in (only if no existing record)
        if (!($nowTime >= $timing['checkin_start'] && $nowTime <= $timing['checkin_end'])) {
            return [
                'success' => false,
                'mode' => 'checkin',
                'message' => "Check-in not allowed now. Window: {$timing['checkin_start']} - {$timing['checkin_end']}"
            ];
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO attendance (student_id, student_name, status, timestamp, program, shift, check_in_time, notes, created_at) VALUES (?, ?, 'Check-in', ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([
                $studentId,
                $studentName,
                $now->format('Y-m-d H:i:s'),
                $program,
                $shift,
                $now->format('Y-m-d H:i:s'),
                $notes
            ]);

            return [
                'success' => true,
                'mode' => 'checkin',
                'message' => 'Checked in successfully'
            ];
        } catch (PDOException $e) {
            error_log("Check-in database error for student $studentId: " . $e->getMessage());
            return [
                'success' => false,
                'mode' => 'checkin',
                'message' => 'Check-in failed due to a database error. Please try again.'
            ];
        } catch (Exception $e) {
            error_log("Check-in error for student $studentId: " . $e->getMessage());
            return [
                'success' => false,
                'mode' => 'checkin',
                'message' => 'Check-in failed: ' . $e->getMessage()
            ];
        }
    } catch (Exception $e) {
        error_log('saveAttendanceAuto error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to save attendance'];
    }
}

/**
 * Load timing settings with sane defaults
 */
function loadTimingSettings() {
    global $pdo;
    // NO HARDCODED DEFAULTS - All timings must come from database
    $defaults = [
        'timezone' => 'Asia/Karachi',
        // All timing settings must be configured by admin
    ];

    // Try database if available
    try {
        if ($pdo) {
            $keys = array_keys($defaults);
            $placeholders = str_repeat('?,', count($keys) - 1) . '?';
            $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ($placeholders)");
            $stmt->execute($keys);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $val = $row['setting_value'];
                // Normalize times to H:i:s
                if (strpos($row['setting_key'], 'time') === false && strpos($row['setting_key'], 'check') !== false) {
                    $val = normalizeTime($val);
                }
                $defaults[$row['setting_key']] = $val;
            }
        }
    } catch (Exception $e) {
        // ignore and use defaults
    }

    // Ensure all time values are normalized
    foreach ($defaults as $k => $v) {
        if (strpos($k, 'check') !== false) {
            $defaults[$k] = normalizeTime($v);
        }
    }
    return $defaults;
}

function normalizeTime($value) {
    $value = trim((string)$value);
    if ($value === '') return '00:00:00';
    // Accept HH:MM or HH:MM:SS
    if (preg_match('/^\d{1,2}:\d{2}$/', $value)) {
        return $value . ':00';
    }
    if (preg_match('/^\d{1,2}:\d{2}:\d{2}$/', $value)) {
        return $value;
    }
    // Try parsing human formats
    $dt = DateTime::createFromFormat('H:i', $value) ?: DateTime::createFromFormat('H:i:s', $value) ?: DateTime::createFromFormat('g:i A', $value) ?: DateTime::createFromFormat('g:i a', $value);
    if ($dt) return $dt->format('H:i:s');
    return '00:00:00';
}

function getShiftTimingWindow($settings, $shift) {
    $isMorning = strtolower($shift) === 'morning';
    if ($isMorning) {
        return [
            'checkin_start' => $settings['morning_checkin_start'],
            'checkin_end' => $settings['morning_checkin_end'],
            'checkout_start' => $settings['morning_checkout_start'],
            'checkout_end' => $settings['morning_checkout_end']
        ];
    }
    return [
        'checkin_start' => $settings['evening_checkin_start'],
        'checkin_end' => $settings['evening_checkin_end'],
        'checkout_start' => $settings['evening_checkout_start'],
        'checkout_end' => $settings['evening_checkout_end']
    ];
}

/**
 * Handle mark absent students request
 */
function handleMarkAbsentStudents() {
    try {
        // CSRF validation - temporarily disabled for debugging
        // require_once __DIR__ . '/../../includes_ext/csrf_protection.php';
        // CSRFProtection::requireValidation();
        
        global $pdo;
        
        // Load settings
        require_once __DIR__ . '/settings.php';
        $settingsApi = new AdminSettingsAPI();
        $allSettings = $settingsApi->getAllSettings();
        $timingSettings = [];
        if ($allSettings['success'] && isset($allSettings['data']['timings'])) {
            foreach ($allSettings['data']['timings'] as $setting) {
                $timingSettings[$setting['key']] = $setting['value'];
            }
        }
        
        // Get current time in configured timezone
        $timezone = new DateTimeZone($timingSettings['timezone'] ?? 'Asia/Karachi');
        $currentTime = new DateTime('now', $timezone);
        $currentDate = $currentTime->format('Y-m-d');
        $currentTimeOnly = $currentTime->format('H:i:s');
        
        // Get check-in end times for both shifts
        $morningCheckinEnd = $timingSettings['morning_checkin_end'] ?? null;
        $eveningCheckinEnd = $timingSettings['evening_checkin_end'] ?? '17:00:00';
        
        $markedAbsent = 0;
        $errors = [];
        
        // Check if we should mark absent (after both check-in windows close)
        if ($currentTimeOnly > $morningCheckinEnd && $currentTimeOnly > $eveningCheckinEnd) {
            // Get all students who don't have any attendance record for today
            $stmt = $pdo->prepare("
                SELECT s.id, s.student_id, s.name, s.program, s.shift
                FROM students s
                LEFT JOIN attendance a ON s.student_id = a.student_id AND DATE(a.timestamp) = ?
                WHERE a.student_id IS NULL
            ");
            $stmt->execute([$currentDate]);
            $absentStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($absentStudents as $student) {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO attendance 
                        (student_id, student_name, program, shift, status, timestamp, notes, created_at) 
                        VALUES (?, ?, ?, ?, 'Absent', ?, 'Automatically marked absent after check-in window closed', NOW())
                    ");
                    $stmt->execute([
                        $student['student_id'],
                        $student['name'],
                        $student['program'],
                        $student['shift'],
                        $currentTime->format('Y-m-d H:i:s')
                    ]);
                    $markedAbsent++;
                } catch (Exception $e) {
                    $errors[] = "Failed to mark {$student['student_id']} as absent: " . $e->getMessage();
                }
            }
        }
        
        sendJsonResponse([
            'success' => true,
            'message' => "Marked {$markedAbsent} students as absent",
            'marked_count' => $markedAbsent,
            'errors' => $errors,
            'checkin_windows_closed' => $currentTimeOnly > $morningCheckinEnd && $currentTimeOnly > $eveningCheckinEnd
        ]);
        
    } catch (Exception $e) {
        error_log("Error marking absent students: " . $e->getMessage());
        sendJsonResponse(['success' => false, 'error' => 'Failed to mark absent students: ' . $e->getMessage()], 500);
    }
}

/**
 * Handle auto mark absent request
 */
function handleAutoMarkAbsent() {
    try {
        // CSRF validation - temporarily disabled for debugging
        // require_once __DIR__ . '/../../includes_ext/csrf_protection.php';
        // CSRFProtection::requireValidation();
        
        global $pdo;
        
        // Check if PDO is available
        if (!$pdo) {
            throw new Exception("Database connection not available in handleAutoMarkAbsent");
        }
        
        // Load settings
        require_once __DIR__ . '/settings.php';
        $settingsApi = new AdminSettingsAPI();
        $allSettings = $settingsApi->getAllSettings();
        $timingSettings = [];
        
        if ($allSettings['success'] && isset($allSettings['data']['timings'])) {
            foreach ($allSettings['data']['timings'] as $setting) {
                $timingSettings[$setting['key']] = $setting['value'];
            }
        }
        
        // Get current time in configured timezone
        $timezone = new DateTimeZone($timingSettings['timezone'] ?? 'Asia/Karachi');
        $currentTime = new DateTime('now', $timezone);
        $currentDate = $currentTime->format('Y-m-d');
        $currentTimeOnly = $currentTime->format('H:i:s');
        
        // Get check-in end times for both shifts
        $morningCheckinEnd = $timingSettings['morning_checkin_end'] ?? null;
        $eveningCheckinEnd = $timingSettings['evening_checkin_end'] ?? '17:00:00';
        
        $results = [];
        $totalMarked = 0;
        
        // Check both shifts
        $shifts = ['Morning', 'Evening'];
        
        foreach ($shifts as $shift) {
            $checkinEnd = $timingSettings[strtolower($shift) . '_checkin_end'] ?? 
                          ($shift === 'Morning' ? ($timingSettings['morning_checkin_end'] ?? 'Not configured') : ($timingSettings['evening_checkin_end'] ?? 'Not configured'));
            
            // Only mark absent if check-in window has closed for this shift
            if ($currentTimeOnly > $checkinEnd) {
                // Get students of this shift who don't have attendance today
                $stmt = $pdo->prepare("
                    SELECT s.id, s.student_id, s.name, s.program, s.shift
                    FROM students s
                    LEFT JOIN attendance a ON s.student_id = a.student_id AND DATE(a.timestamp) = ?
                    WHERE s.shift = ? AND a.student_id IS NULL
                ");
                $stmt->execute([$currentDate, $shift]);
                $absentStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $markedAbsent = 0;
                $errors = [];
                
                foreach ($absentStudents as $student) {
                    try {
                        $stmt = $pdo->prepare("
                            INSERT INTO attendance 
                            (student_id, student_name, program, shift, status, timestamp, notes, created_at) 
                            VALUES (?, ?, ?, ?, 'Absent', ?, 'Auto-marked absent after {$shift} check-in window closed', NOW())
                        ");
                        $stmt->execute([
                            $student['student_id'],
                            $student['name'],
                            $student['program'],
                            $student['shift'],
                            $currentTime->format('Y-m-d H:i:s')
                        ]);
                        $markedAbsent++;
                    } catch (Exception $e) {
                        $errors[] = "Failed to mark {$student['student_id']} as absent: " . $e->getMessage();
                    }
                }
                
                $results[$shift] = [
                    'success' => true,
                    'message' => "Marked {$markedAbsent} {$shift} shift students as absent",
                    'marked_count' => $markedAbsent,
                    'errors' => $errors
                ];
                
                $totalMarked += $markedAbsent;
            } else {
                $results[$shift] = [
                    'success' => false,
                    'message' => "Check-in window for {$shift} shift still open (ends at {$checkinEnd})",
                    'marked_count' => 0
                ];
            }
        }
        
        sendJsonResponse([
            'success' => true,
            'message' => "Auto absent marking completed. Total marked: {$totalMarked}",
            'total_marked' => $totalMarked,
            'results' => $results,
            'timestamp' => $currentTime->format('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        error_log("Error in auto mark absent: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        sendJsonResponse(['success' => false, 'error' => 'Auto absent marking failed: ' . $e->getMessage()], 500);
    }
}

/**
 * Handle debug absent request
 */
function handleDebugAbsent() {
    try {
        global $pdo;
        sendJsonResponse([
            'success' => true, 
            'message' => 'Debug endpoint working',
            'pdo_available' => isset($pdo) && $pdo !== null,
            'session_id' => session_id(),
            'csrf_token' => $_SESSION['csrf_token'] ?? 'not_set',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } catch (Exception $e) {
        sendJsonResponse(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>
