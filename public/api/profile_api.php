<?php
/**
 * Profile API
 * Handles AJAX requests for profile-related operations
 */

// Set error reporting to prevent HTML error pages
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set JSON header first - do this BEFORE any other output
header('Content-Type: application/json; charset=utf-8');
// SECURITY FIX: Restrict CORS to specific domains
header('Access-Control-Allow-Origin: http://localhost');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
header('Access-Control-Allow-Credentials: true');

// Handle preflight requests
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Validate request method
if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Validate required headers (only for web requests)
if (isset($_SERVER['CONTENT_TYPE'])) {
    $content_type = $_SERVER['CONTENT_TYPE'];
    if (strpos($content_type, 'application/x-www-form-urlencoded') === false && 
        strpos($content_type, 'multipart/form-data') === false) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid content type']);
        exit();
    }
}

try {
    require_once __DIR__ . '/../includes/config.php';
    require_once __DIR__ . '/../includes/auth.php';
    require_once __DIR__ . '/../includes/profile_helpers.php';
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server configuration error']);
    exit();
}

// Check if student is logged in
if (!isStudentLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please log in to continue']);
    exit();
}

$current_student = getCurrentStudent();
if (!$current_student) {
    echo json_encode(['success' => false, 'message' => 'Student not found or not authenticated']);
    exit();
}

// Validate action parameter
$action = $_POST['action'] ?? '';
if (empty($action)) {
    echo json_encode(['success' => false, 'message' => 'Action parameter is required']);
    exit();
}

// Validate action value
$allowed_actions = ['upload_profile_picture', 'request_password_reset', 'verify_code_and_reset_password', 'resend_verification_code'];
if (!in_array($action, $allowed_actions)) {
    echo json_encode(['success' => false, 'message' => 'Invalid action specified']);
    exit();
}

try {
    switch ($action) {
        case 'upload_profile_picture':
            handleProfilePictureUpload($current_student);
            break;
            
        case 'request_password_reset':
            handlePasswordResetRequest($current_student);
            break;
            
        case 'verify_code_and_reset_password':
            handlePasswordResetVerification($current_student);
            break;
            
        case 'resend_verification_code':
            handleResendVerificationCode($current_student);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
            break;
    }
} catch (Exception $e) {
    error_log("Profile API error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
} catch (Error $e) {
    error_log("Profile API fatal error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode(['success' => false, 'message' => 'A system error occurred. Please try again.']);
}

/**
 * Handle profile picture upload
 */
function handleProfilePictureUpload($current_student) {
    // Validate CSRF token
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (empty($csrf_token)) {
        echo json_encode(['success' => false, 'message' => 'Security token is required']);
        return;
    }
    
    if (!validateCSRFToken($csrf_token)) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token']);
        return;
    }
    
    // Check if file was uploaded
    if (!isset($_FILES['profile_picture'])) {
        echo json_encode(['success' => false, 'message' => 'No file uploaded']);
        return;
    }
    
    $file = $_FILES['profile_picture'];
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error_messages = [
            UPLOAD_ERR_INI_SIZE => 'File is too large (server limit)',
            UPLOAD_ERR_FORM_SIZE => 'File is too large (form limit)',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        
        $error_message = $error_messages[$file['error']] ?? 'Unknown upload error';
        echo json_encode(['success' => false, 'message' => $error_message]);
        return;
    }
    
    // Validate file size
    if ($file['size'] > MAX_PROFILE_PICTURE_SIZE) {
        echo json_encode(['success' => false, 'message' => 'File size must be less than ' . (MAX_PROFILE_PICTURE_SIZE / 1024 / 1024) . 'MB']);
        return;
    }
    
    // Validate file type
    $allowed_types = ALLOWED_IMAGE_TYPES;
    $file_type = mime_content_type($file['tmp_name']);
    
    if (!in_array($file_type, $allowed_types)) {
        echo json_encode(['success' => false, 'message' => 'Only JPG, PNG, and GIF images are allowed']);
        return;
    }
    
    // Validate file extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
    
    if (!in_array($extension, $allowed_extensions)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file extension. Only .jpg, .jpeg, .png, and .gif are allowed']);
        return;
    }
    
    // Validate image dimensions
    $image_info = getimagesize($file['tmp_name']);
    if ($image_info === false) {
        echo json_encode(['success' => false, 'message' => 'Invalid image file']);
        return;
    }
    
    $max_width = 2000;
    $max_height = 2000;
    
    if ($image_info[0] > $max_width || $image_info[1] > $max_height) {
        echo json_encode(['success' => false, 'message' => "Image dimensions must be less than {$max_width}x{$max_height} pixels"]);
        return;
    }
    
    $file = $_FILES['profile_picture'];
    $result = uploadProfilePicture($current_student['student_id'], $file);
    
    if ($result['success']) {
        // Log the action
        logStudentAction('PROFILE_PICTURE_UPLOAD', "Profile picture uploaded: {$result['filename']}");
        
        echo json_encode([
            'success' => true,
            'message' => 'Profile picture updated successfully!',
            'image_url' => $result['url']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => implode(', ', $result['errors'])
        ]);
    }
}

/**
 * Handle password reset request
 */
function handlePasswordResetRequest($current_student) {
    // Validate CSRF token
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (empty($csrf_token)) {
        echo json_encode(['success' => false, 'message' => 'Security token is required']);
        return;
    }
    
    if (!validateCSRFToken($csrf_token)) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token']);
        return;
    }
    
    // Validate email
    $email = $_POST['email'] ?? '';
    if (empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Email is required']);
        return;
    }
    
    $email = sanitizeInput($email);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        return;
    }
    
    // Validate email length
    if (strlen($email) > 255) {
        echo json_encode(['success' => false, 'message' => 'Email address is too long']);
        return;
    }
    
    // Validate that email matches student's email
    if ($email !== $current_student['email']) {
        echo json_encode(['success' => false, 'message' => 'Email does not match your account']);
        return;
    }
    
    // Create verification record
    $verification_result = createEmailVerification($current_student['student_id'], $email, 'password_reset');
    
    if (!$verification_result['success']) {
        echo json_encode($verification_result);
        return;
    }
    
    // Send verification email
    $email_sent = sendVerificationEmail($email, $verification_result['code'], $current_student['name'], 'password_reset');
    
    if ($email_sent) {
        // Log the action
        logStudentAction('PASSWORD_RESET_REQUEST', "Password reset requested for email: {$email}");
        
        echo json_encode([
            'success' => true,
            'message' => 'Verification code sent to your email address. Please check your inbox.',
            'expires_in' => VERIFICATION_CODE_EXPIRY
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to send verification email. Please try again later.'
        ]);
    }
}

/**
 * Handle password reset verification
 */
function handlePasswordResetVerification($current_student) {
    // Validate CSRF token
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (empty($csrf_token)) {
        echo json_encode(['success' => false, 'message' => 'Security token is required']);
        return;
    }
    
    if (!validateCSRFToken($csrf_token)) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token']);
        return;
    }
    
    // Validate verification code
    $code = $_POST['verification_code'] ?? '';
    if (empty($code)) {
        echo json_encode(['success' => false, 'message' => 'Verification code is required']);
        return;
    }
    
    $code = sanitizeInput($code);
    if (!preg_match('/^[0-9]{6}$/', $code)) {
        echo json_encode(['success' => false, 'message' => 'Verification code must be 6 digits']);
        return;
    }
    
    // Validate new password
    $new_password = $_POST['new_password'] ?? '';
    if (empty($new_password)) {
        echo json_encode(['success' => false, 'message' => 'New password is required']);
        return;
    }
    
    if (strlen($new_password) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters long']);
        return;
    }
    
    if (strlen($new_password) > 128) {
        echo json_encode(['success' => false, 'message' => 'Password is too long']);
        return;
    }
    
    // Validate confirm password
    $confirm_password = $_POST['confirm_password'] ?? '';
    if (empty($confirm_password)) {
        echo json_encode(['success' => false, 'message' => 'Password confirmation is required']);
        return;
    }
    
    if ($new_password !== $confirm_password) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
        return;
    }
    
    // Check password strength
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $new_password)) {
        echo json_encode(['success' => false, 'message' => 'Password must contain at least one uppercase letter, one lowercase letter, and one number']);
        return;
    }
    
    // Validate verification code
    $verification_result = validateVerificationCode($current_student['student_id'], $code, 'password_reset');
    
    if (!$verification_result['success']) {
        echo json_encode($verification_result);
        return;
    }
    
    // Update password
    global $pdo;
    try {
        $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("
            UPDATE students 
            SET password = ?, updated_at = NOW() 
            WHERE student_id = ?
        ");
        $stmt->execute([$new_password_hash, $current_student['student_id']]);
        
        // Mark verification code as used
        markVerificationUsed($current_student['student_id'], $code);
        
        // Log the action
        logStudentAction('PASSWORD_RESET_SUCCESS', "Password reset completed successfully");
        
        echo json_encode([
            'success' => true,
            'message' => 'Password changed successfully!'
        ]);
        
    } catch (PDOException $e) {
        error_log("Password reset error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to update password. Please try again.']);
    }
}

/**
 * Handle resend verification code
 */
function handleResendVerificationCode($current_student) {
    // Validate CSRF token
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($csrf_token)) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token']);
        return;
    }
    
    $email = sanitizeInput($_POST['email'] ?? '');
    $purpose = sanitizeInput($_POST['purpose'] ?? 'password_reset');
    
    if (empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Email is required']);
        return;
    }
    
    // Create new verification record
    $verification_result = createEmailVerification($current_student['student_id'], $email, $purpose);
    
    if (!$verification_result['success']) {
        echo json_encode($verification_result);
        return;
    }
    
    // Send verification email
    $email_sent = sendVerificationEmail($email, $verification_result['code'], $current_student['name'], $purpose);
    
    if ($email_sent) {
        // Log the action
        logStudentAction('VERIFICATION_CODE_RESEND', "Verification code resent for purpose: {$purpose}");
        
        echo json_encode([
            'success' => true,
            'message' => 'New verification code sent to your email address.',
            'expires_in' => VERIFICATION_CODE_EXPIRY
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to send verification email. Please try again later.'
        ]);
    }
}
?>
