<?php
/**
 * Profile Helper Functions
 * Functions for profile picture management and email verification
 */

require_once 'config.php';

/**
 * Validate profile picture file
 */
function validateProfilePicture($file) {
    // SECURITY FIX: Use secure upload validation
    require_once __DIR__ . '/../includes_ext/secure_upload.php';
    $validation = SecureUpload::validateImage($file);
    
    if (!$validation['valid']) {
        return $validation['errors'];
    }
    
    return []; // No errors
}

/**
 * Upload profile picture
 */
function uploadProfilePicture($student_id, $file) {
    global $pdo;
    
    // SECURITY FIX: Use secure upload system
    require_once __DIR__ . '/../includes_ext/secure_upload.php';
    
    // Validate file
    $errors = validateProfilePicture($file);
    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }
    
    try {
        // Create upload directory if it doesn't exist
        $upload_dir = PROFILE_PICTURE_PATH;
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Use secure upload
        $upload_result = SecureUpload::uploadFile($file, $upload_dir, $student_id . '_' . time());
        
        if (!$upload_result['success']) {
            return $upload_result;
        }
        
        // Update database
        $stmt = $pdo->prepare("
            UPDATE students 
            SET profile_picture = ?, updated_at = NOW() 
            WHERE student_id = ?
        ");
        $stmt->execute([$upload_result['filename'], $student_id]);
        
        // Clean up old profile pictures (keep only the latest)
        deleteOldProfilePicture($student_id, true);
        
        return [
            'success' => true, 
            'filename' => $upload_result['filename'],
            'url' => getStudentProfilePicture($student_id, $upload_result['filename'])
        ];
        
    } catch (Exception $e) {
        error_log("Profile picture upload error: " . $e->getMessage());
        return ['success' => false, 'errors' => ['Upload failed. Please try again.']];
    }
}

/**
 * Get student profile picture URL
 */
function getStudentProfilePicture($student_id, $filename = null) {
    if ($filename && file_exists(PROFILE_PICTURE_PATH . $filename)) {
        return PROFILE_PICTURE_URL . $filename;
    }
    
    // Return default avatar
    return getStudentAssetUrl('img/avatars/1.png');
}

/**
 * Delete old profile pictures
 */
function deleteOldProfilePicture($student_id, $keep_latest = true) {
    $upload_dir = PROFILE_PICTURE_PATH;
    $pattern = $upload_dir . $student_id . '_*';
    $files = glob($pattern);
    
    if (count($files) <= 1) {
        return; // Keep the only file
    }
    
    // Sort by modification time (newest first)
    usort($files, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    
    // Delete all but the latest file
    $files_to_delete = $keep_latest ? array_slice($files, 1) : $files;
    
    foreach ($files_to_delete as $file) {
        if (file_exists($file)) {
            unlink($file);
        }
    }
}

/**
 * Generate verification code
 */
function generateVerificationCode() {
    return str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * Format verification email
 */
function formatVerificationEmail($code, $student_name, $purpose) {
    $site_name = STUDENT_SITE_NAME;
    $expiry_minutes = VERIFICATION_CODE_EXPIRY / 60;
    
    $subject = $purpose === 'password_reset' ? 'Password Reset Verification' : 'Email Verification';
    $purpose_text = $purpose === 'password_reset' ? 'reset your password' : 'verify your email address';
    
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>{$subject}</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #696cff; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px; }
            .code { background: #fff; border: 2px solid #696cff; border-radius: 8px; padding: 20px; text-align: center; margin: 20px 0; }
            .code-number { font-size: 32px; font-weight: bold; color: #696cff; letter-spacing: 5px; }
            .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 5px; margin: 20px 0; }
            .footer { text-align: center; margin-top: 30px; color: #666; font-size: 14px; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h1>{$site_name}</h1>
        </div>
        <div class='content'>
            <h2>Hello {$student_name}!</h2>
            <p>You have requested to {$purpose_text}. Please use the verification code below:</p>
            
            <div class='code'>
                <div class='code-number'>{$code}</div>
            </div>
            
            <div class='warning'>
                <strong>Important:</strong> This code will expire in {$expiry_minutes} minutes. Do not share this code with anyone.
            </div>
            
            <p>If you did not request this verification, please ignore this email or contact support if you have concerns.</p>
        </div>
        <div class='footer'>
            <p>This is an automated message from {$site_name}. Please do not reply to this email.</p>
        </div>
    </body>
    </html>
    ";
    
    return ['subject' => $subject, 'html' => $html];
}

/**
 * Send verification email
 */
function sendVerificationEmail($email, $code, $student_name, $purpose) {
    $email_data = formatVerificationEmail($code, $student_name, $purpose);
    
    
    try {
        // Use PHPMailer for SMTP authentication
        require_once __DIR__ . '/../vendor/autoload.php';
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS; // SSL
        $mail->Port = SMTP_PORT;
        
        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($email, $student_name);
        $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $email_data['subject'];
        $mail->Body = $email_data['html'];
        $mail->AltBody = strip_tags($email_data['html']);
        
        $mail->send();
        error_log("Verification email sent successfully to: {$email}");
        return true;
        
    } catch (Exception $e) {
        error_log("Failed to send verification email to {$email}: " . $e->getMessage());
        
        
        return false;
    }
}

/**
 * Create email verification record
 */
function createEmailVerification($student_id, $email, $purpose) {
    global $pdo;
    
    try {
        // Check rate limiting (max 3 codes per hour)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM email_verifications 
            WHERE student_id = ? AND purpose = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute([$student_id, $purpose]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] >= 3) {
            return ['success' => false, 'message' => 'Too many verification requests. Please wait before requesting another code.'];
        }
        
        // Generate code
        $code = generateVerificationCode();
        $expires_at = date('Y-m-d H:i:s', time() + VERIFICATION_CODE_EXPIRY);
        
        // Insert verification record
        $stmt = $pdo->prepare("
            INSERT INTO email_verifications (student_id, email, verification_code, purpose, expires_at) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$student_id, $email, $code, $purpose, $expires_at]);
        
        return ['success' => true, 'code' => $code];
        
    } catch (PDOException $e) {
        error_log("Email verification creation error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to create verification record.'];
    }
}

/**
 * Validate verification code
 */
function validateVerificationCode($student_id, $code, $purpose) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT id, email, expires_at, is_used 
            FROM email_verifications 
            WHERE student_id = ? AND verification_code = ? AND purpose = ? 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$student_id, $code, $purpose]);
        $verification = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$verification) {
            return ['success' => false, 'message' => 'Invalid verification code.'];
        }
        
        if ($verification['is_used']) {
            return ['success' => false, 'message' => 'Verification code has already been used.'];
        }
        
        if (strtotime($verification['expires_at']) < time()) {
            return ['success' => false, 'message' => 'Verification code has expired.'];
        }
        
        return ['success' => true, 'email' => $verification['email']];
        
    } catch (PDOException $e) {
        error_log("Verification code validation error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to validate verification code.'];
    }
}

/**
 * Mark verification code as used
 */
function markVerificationUsed($student_id, $code) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE email_verifications 
            SET is_used = 1 
            WHERE student_id = ? AND verification_code = ?
        ");
        $stmt->execute([$student_id, $code]);
        
        return true;
        
    } catch (PDOException $e) {
        error_log("Mark verification used error: " . $e->getMessage());
        return false;
    }
}

/**
 * Clean up expired verification codes
 */
function cleanupExpiredVerifications() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            DELETE FROM email_verifications 
            WHERE expires_at < NOW() OR (is_used = 1 AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR))
        ");
        $stmt->execute();
        
        return true;
        
    } catch (PDOException $e) {
        error_log("Cleanup expired verifications error: " . $e->getMessage());
        return false;
    }
}
?>
