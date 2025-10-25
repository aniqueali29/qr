<?php
/**
 * Email Service
 * Handles email sending using PHPMailer with SMTP configuration
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load PHPMailer
require_once __DIR__ . '/../vendor/autoload.php';

class EmailService {
    
    private $mailer;
    private $from_email;
    private $from_name;
    
    public function __construct() {
        $this->mailer = new PHPMailer(true);
        $this->configureSMTP();
    }
    
    /**
     * Configure SMTP settings from environment
     */
    private function configureSMTP() {
        // Load config
        require_once __DIR__ . '/env.php';
        
        try {
            // Server settings
            $this->mailer->isSMTP();
            $this->mailer->Host = env_get('SMTP_HOST', 'smtp.gmail.com');
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = env_get('SMTP_USERNAME', '');
            $this->mailer->Password = env_get('SMTP_PASSWORD', '');
            
            // Port configuration
            $smtp_port = env_int('SMTP_PORT', 587);
            $this->mailer->Port = $smtp_port;
            
            // Encryption
            if ($smtp_port == 465) {
                $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }
            
            // From address
            $this->from_email = env_get('SMTP_USERNAME', 'noreply@example.com');
            $this->from_name = 'QR Attendance System';
            
            $this->mailer->setFrom($this->from_email, $this->from_name);
            
            // Settings
            $this->mailer->isHTML(true);
            $this->mailer->CharSet = 'UTF-8';
            
        } catch (Exception $e) {
            error_log("Email configuration error: " . $e->getMessage());
            throw new Exception("Email system configuration failed");
        }
    }
    
    /**
     * Send OTP email for password reset
     */
    public function sendPasswordResetOTP($to_email, $to_name, $otp, $expires_in_minutes = 10) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($to_email, $to_name);
            
            $this->mailer->Subject = 'Password Reset OTP - QR Attendance System';
            
            // Email body with OTP
            $body = $this->getPasswordResetEmailTemplate($to_name, $otp, $expires_in_minutes);
            $this->mailer->Body = $body;
            
            // Plain text version
            $this->mailer->AltBody = strip_tags($body);
            
            $result = $this->mailer->send();
            
            if ($result) {
                error_log("Password reset OTP sent to: {$to_email}");
                return ['success' => true, 'message' => 'OTP sent successfully'];
            } else {
                return ['success' => false, 'message' => 'Failed to send OTP'];
            }
            
        } catch (Exception $e) {
            error_log("Email sending error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Email sending failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Send password reset link (alternative to OTP)
     */
    public function sendPasswordResetLink($to_email, $to_name, $reset_link) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($to_email, $to_name);
            
            $this->mailer->Subject = 'Password Reset Request - QR Attendance System';
            
            // Email body with reset link
            $body = $this->getPasswordResetLinkTemplate($to_name, $reset_link);
            $this->mailer->Body = $body;
            
            // Plain text version
            $this->mailer->AltBody = strip_tags($body);
            
            $result = $this->mailer->send();
            
            if ($result) {
                error_log("Password reset link sent to: {$to_email}");
                return ['success' => true, 'message' => 'Reset link sent successfully'];
            } else {
                return ['success' => false, 'message' => 'Failed to send reset link'];
            }
            
        } catch (Exception $e) {
            error_log("Email sending error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Email sending failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Email template for OTP
     */
    private function getPasswordResetEmailTemplate($name, $otp, $expires_in_minutes) {
        return "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; background-color: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 30px auto; background: #ffffff; border-radius: 10px; overflow: hidden; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #ffffff; padding: 30px; text-align: center; }
        .header h1 { margin: 0; font-size: 28px; }
        .content { padding: 40px 30px; }
        .otp-box { background: #f8f9fa; border: 2px dashed #667eea; border-radius: 8px; padding: 20px; text-align: center; margin: 30px 0; }
        .otp-code { font-size: 36px; font-weight: bold; color: #667eea; letter-spacing: 8px; margin: 10px 0; }
        .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666; }
        .button { display: inline-block; padding: 12px 30px; background: #667eea; color: #ffffff; text-decoration: none; border-radius: 5px; margin: 20px 0; }
        ul { padding-left: 20px; }
        li { margin: 10px 0; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>🔒 Password Reset Request</h1>
        </div>
        <div class='content'>
            <p>Hi <strong>{$name}</strong>,</p>
            
            <p>We received a request to reset your password for your QR Attendance System account.</p>
            
            <div class='otp-box'>
                <p style='margin: 0; font-size: 14px; color: #666;'>Your One-Time Password (OTP)</p>
                <div class='otp-code'>{$otp}</div>
                <p style='margin: 0; font-size: 12px; color: #999;'>Valid for {$expires_in_minutes} minutes</p>
            </div>
            
            <p>Enter this OTP on the password reset page to proceed with resetting your password.</p>
            
            <div class='warning'>
                <strong>⚠️ Security Notice:</strong>
                <ul>
                    <li>This OTP will expire in {$expires_in_minutes} minutes</li>
                    <li>Never share your OTP with anyone</li>
                    <li>If you didn't request this reset, please ignore this email</li>
                    <li>Your password will remain unchanged until you complete the reset process</li>
                </ul>
            </div>
            
            <p>If you have any questions or need assistance, please contact your system administrator.</p>
            
            <p>Best regards,<br><strong>QR Attendance System Team</strong></p>
        </div>
        <div class='footer'>
            <p>This is an automated email. Please do not reply to this message.</p>
            <p>&copy; " . date('Y') . " QR Attendance System. All rights reserved.</p>
        </div>
    </div>
</body>
</html>";
    }
    
    /**
     * Email template for reset link (alternative)
     */
    private function getPasswordResetLinkTemplate($name, $reset_link) {
        return "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .button { display: inline-block; padding: 12px 30px; background: #667eea; color: #ffffff; text-decoration: none; border-radius: 5px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class='container'>
        <h2>Password Reset Request</h2>
        <p>Hi {$name},</p>
        <p>Click the button below to reset your password:</p>
        <a href='{$reset_link}' class='button'>Reset Password</a>
        <p>If the button doesn't work, copy and paste this link into your browser:</p>
        <p>{$reset_link}</p>
        <p>This link will expire in 1 hour.</p>
        <p>If you didn't request this, please ignore this email.</p>
    </div>
</body>
</html>";
    }
}
?>
