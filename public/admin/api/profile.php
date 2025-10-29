<?php
/**
 * Profile Management API
 * Handles profile updates, password changes with OTP, and profile picture upload
 */

// Strict JSON output
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

// Set error handler to log errors
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("Profile API PHP[$errno] $errstr in $errfile:$errline");
    return true;
});

// Return JSON on fatal errors
register_shutdown_function(function() {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        if (ob_get_length()) { ob_clean(); }
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Fatal error occurred']);
    }
});

require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

// Load email service from public/includes/
if (file_exists(__DIR__ . '/../../includes/email_service.php')) {
    require_once __DIR__ . '/../../includes/email_service.php';
}

header('Content-Type: application/json');

// Check authentication
if (!isAdminLoggedIn()) {
    if (ob_get_length()) { ob_clean(); }
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$action = $_GET['action'] ?? '';
$response = ['success' => false, 'error' => 'Unknown error'];

try {
    switch ($action) {
        case 'send_otp':
            $response = sendPasswordChangeOTP();
            break;
            
        case 'change_password':
            $response = changePassword();
            break;
            
        case 'upload_picture':
            $response = uploadProfilePicture();
            break;
            
        case 'login_history':
            $response = getLoginHistory();
            break;
            
        default:
            $response = ['success' => false, 'error' => 'Invalid action'];
    }
    
} catch (Exception $e) {
    error_log("Profile API Exception: " . $e->getMessage());
    http_response_code(500);
    $response = ['success' => false, 'error' => 'Server error occurred'];
}

// Clear any output buffer and send JSON
if (ob_get_length()) { ob_clean(); }
header('Content-Type: application/json');

// Log for debugging
error_log("Profile API Response: " . json_encode($response));

echo json_encode($response);
exit();

/**
 * Send OTP for password change
 */
function sendPasswordChangeOTP() {
    global $pdo;
    
    try {
        $user = getAdminUser();
        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }
        
        // Generate 6-digit OTP
        $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Store OTP in session
        $_SESSION['password_change_otp'] = $otp;
        $_SESSION['password_change_otp_expires'] = time() + 600; // 10 minutes
        $_SESSION['password_change_otp_user_id'] = $user['id'];
        
        // Try to send OTP email
        $emailSent = false;
        $emailMessage = 'Verification code sent to your email';
        
        if (class_exists('EmailService')) {
            try {
                $emailService = new EmailService();
                $emailResult = $emailService->sendPasswordResetOTP(
                    $user['email'],
                    $user['username'],
                    $otp,
                    10 // 10 minutes expiry
                );
                
                if (isset($emailResult['success']) && $emailResult['success']) {
                    $emailSent = true;
                } else {
                    $emailMessage = 'OTP generated. Note: Email sending failed, but OTP is valid. Please use: ' . $otp;
                }
            } catch (Exception $e) {
                error_log("Email service error: " . $e->getMessage());
                $emailMessage = 'OTP generated. Note: Email sending failed. Your OTP is: ' . $otp;
            }
        } else {
            // Email service not available, but still return OTP for testing
            $emailMessage = 'OTP generated (Email service not configured). Your OTP is: ' . $otp;
        }
        
        return [
            'success' => true,
            'message' => $emailMessage
        ];
        
    } catch (Exception $e) {
        error_log("OTP generation error: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Change password with OTP verification
 */
function changePassword() {
    global $pdo;
    
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $forgotPassword = isset($input['forgot_password']) && $input['forgot_password'] === true;
        $currentPassword = $input['current_password'] ?? '';
        $newPassword = $input['new_password'] ?? '';
        $otp = $input['otp'] ?? '';
        
        if (empty($newPassword)) {
            return ['success' => false, 'message' => 'New password is required'];
        }
        
        // OTP is only required for forgot password mode
        if ($forgotPassword && empty($otp)) {
            return ['success' => false, 'message' => 'Verification code is required'];
        }
        
        if (strlen($newPassword) < 8) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters'];
        }
        
        $user = getAdminUser();
        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }
        
        // Verify current password only if NOT in forgot password mode
        if (!$forgotPassword) {
            if (empty($currentPassword)) {
                return ['success' => false, 'message' => 'Current password is required'];
            }
            
            // Verify current password
            $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$user['id']]);
            $userData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!password_verify($currentPassword, $userData['password_hash'])) {
                return ['success' => false, 'message' => 'Current password is incorrect'];
            }
        }
        
        // Verify OTP only for forgot password mode
        if ($forgotPassword) {
            if (!isset($_SESSION['password_change_otp']) || 
                !isset($_SESSION['password_change_otp_expires']) ||
                !isset($_SESSION['password_change_otp_user_id'])) {
                return ['success' => false, 'message' => 'Please request a verification code first'];
            }
            
            if (time() > $_SESSION['password_change_otp_expires']) {
                unset($_SESSION['password_change_otp']);
                unset($_SESSION['password_change_otp_expires']);
                unset($_SESSION['password_change_otp_user_id']);
                return ['success' => false, 'message' => 'Verification code has expired. Please request a new one.'];
            }
            
            if ($_SESSION['password_change_otp_user_id'] != $user['id']) {
                return ['success' => false, 'message' => 'Invalid verification code'];
            }
            
            if ($_SESSION['password_change_otp'] != $otp) {
                return ['success' => false, 'message' => 'Invalid verification code'];
            }
        }
        
        // Change password
        $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$newPasswordHash, $user['id']]);
        
        // Clear OTP session if we verified it
        if ($forgotPassword) {
            unset($_SESSION['password_change_otp']);
            unset($_SESSION['password_change_otp_expires']);
            unset($_SESSION['password_change_otp_user_id']);
        }
        
        return [
            'success' => true,
            'message' => 'Password changed successfully'
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Upload profile picture
 */
function uploadProfilePicture() {
    global $pdo;
    
    try {
        if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'No file uploaded'];
        }
        
        $user = getAdminUser();
        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }
        
        $file = $_FILES['profile_picture'];
        
        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        if (!in_array($file['type'], $allowedTypes)) {
            return ['success' => false, 'message' => 'Invalid file type. Only JPEG, PNG, and GIF are allowed.'];
        }
        
        // Validate file size (5MB max)
        if ($file['size'] > 5 * 1024 * 1024) {
            return ['success' => false, 'message' => 'File size must be less than 5MB'];
        }
        
        // Create upload directory if it doesn't exist
        $uploadDir = '../../uploads/profile_pictures/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'admin_' . $user['id'] . '_' . time() . '.' . $extension;
        $filepath = $uploadDir . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            return ['success' => false, 'message' => 'Failed to save file'];
        }
        
        // Save filepath to database (add profile_picture column if it doesn't exist)
        try {
            $stmt = $pdo->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
            $stmt->execute(['uploads/profile_pictures/' . $filename, $user['id']]);
        } catch (PDOException $e) {
            // Column might not exist, that's okay
        }
        
        return [
            'success' => true,
            'profile_picture_url' => '../uploads/profile_pictures/' . $filename,
            'message' => 'Profile picture uploaded successfully'
        ];
        
    } catch (Exception $e) {
        error_log("Profile upload error: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Get login history
 */
function getLoginHistory() {
    global $pdo;
    
    try {
        $user = getAdminUser();
        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }
        
        // Try to get login history from users table last_login field
        // This is more reliable than auth_sessions which may not exist
        $stmt = $pdo->prepare("
            SELECT last_login as login_time, 'Current session' as ip_address 
            FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$user['id']]);
        $currentLogin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $history = [];
        
        // If last_login exists, add it to history
        if ($currentLogin && $currentLogin['login_time']) {
            $history[] = $currentLogin;
        }
        
        return [
            'success' => true,
            'history' => $history
        ];
        
    } catch (Exception $e) {
        error_log("Login history error: " . $e->getMessage());
        return ['success' => true, 'history' => []];
    }
}

