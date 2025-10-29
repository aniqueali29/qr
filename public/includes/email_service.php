<?php
/**
 * Email Service
 * Handles email sending using PHPMailer with SMTP configuration
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailService {
    
    private $mailer;
    private $from_email;
    private $from_name;
    
    public function __construct() {
        // Try to load PHPMailer
        $autoloadPath = __DIR__ . '/../vendor/autoload.php';
        if (file_exists($autoloadPath)) {
            require_once $autoloadPath;
        } else {
            // Fallback: try from public directory
            $autoloadPath = __DIR__ . '/../../vendor/autoload.php';
            if (file_exists($autoloadPath)) {
                require_once $autoloadPath;
            }
        }
        
        try {
            // Check if PHPMailer is available
            if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                throw new Exception("PHPMailer class not available");
            }
            
            $this->mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
            $this->configureSMTP();
        } catch (Exception $e) {
            error_log("EmailService initialization error: " . $e->getMessage());
            // Don't throw - let the constructor complete so we can handle gracefully
        }
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
    
    /**
     * Send staff account credentials email
     */
    public function sendStaffAccountCredentials($to_email, $staff_name, $username, $password, $login_url) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($to_email, $staff_name);
            
            $this->mailer->Subject = 'Your Staff Account Credentials - QR Attendance System';
            
            // Email body with credentials
            $body = $this->getStaffAccountEmailTemplate($staff_name, $username, $password, $login_url);
            $this->mailer->Body = $body;
            
            // Plain text version
            $this->mailer->AltBody = $this->getStaffAccountTextTemplate($staff_name, $username, $password, $login_url);
            
            $result = $this->mailer->send();
            
            if ($result) {
                error_log("Staff account credentials sent to: {$to_email}");
                return ['success' => true, 'message' => 'Credentials sent successfully'];
            } else {
                return ['success' => false, 'message' => 'Failed to send credentials'];
            }
            
        } catch (Exception $e) {
            error_log("Email sending error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Email sending failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Staff account email template (HTML)
     */
    private function getStaffAccountEmailTemplate($name, $username, $password, $login_url) {
        $site_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['PHP_SELF']));
        return "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Welcome to QR Attendance System</title>
</head>
<body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;'>
    <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
        <h1 style='color: white; margin: 0;'>Welcome to QR Attendance System</h1>
        <p style='color: #f0f0f0; margin: 10px 0 0 0;'>Your Staff Account Has Been Created</p>
    </div>
    
    <div style='background: #ffffff; padding: 30px; border: 1px solid #e0e0e0; border-radius: 0 0 10px 10px;'>
        <p>Dear <strong>{$name}</strong>,</p>
        
        <p>Your staff account has been successfully created for the QR Attendance System. Below are your login credentials:</p>
        
        <div style='background: #f5f5f5; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #667eea;'>
            <h3 style='margin-top: 0; color: #667eea;'>Login Credentials</h3>
            <table style='width: 100%; border-collapse: collapse;'>
                <tr style='border-bottom: 1px solid #ddd;'>
                    <td style='padding: 8px; font-weight: bold;'>Username:</td>
                    <td style='padding: 8px;'><strong style='color: #333; font-size: 16px;'>{$username}</strong></td>
                </tr>
                <tr>
                    <td style='padding: 8px; font-weight: bold;'>Password:</td>
                    <td style='padding: 8px;'><strong style='color: #333; font-size: 16px;'>{$password}</strong></td>
                </tr>
            </table>
        </div>
        
        <div style='text-align: center; margin: 30px 0;'>
            <a href='{$login_url}' style='display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Login Now</a>
        </div>
        
        <div style='background: #fff3cd; padding: 15px; border-radius: 5px; border-left: 4px solid #ffc107; margin: 20px 0;'>
            <p style='margin: 0;'><strong>⚠️ Security Note:</strong></p>
            <ul style='margin: 10px 0; padding-left: 20px;'>
                <li>Please change your password immediately after first login</li>
                <li>Keep your credentials secure and do not share them</li>
                <li>If you did not expect this email, please contact your administrator</li>
            </ul>
        </div>
        
        <p style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #e0e0e0; color: #777; font-size: 14px;'>
            <strong>Next Steps:</strong><br>
            1. Click the \"Login Now\" button above<br>
            2. Enter your credentials<br>
            3. Change your password in Settings<br>
            4. Start using the system
        </p>
        
        <hr style='border: none; border-top: 1px solid #e0e0e0; margin: 30px 0;'>
        
        <p style='margin: 0; color: #777; font-size: 12px; text-align: center;'>
            This is an automated email from QR Attendance System.<br>
            Please do not reply to this message.
        </p>
    </div>
</body>
</html>";
    }
    
    /**
     * Staff account email template (Plain Text)
     */
    private function getStaffAccountTextTemplate($name, $username, $password, $login_url) {
        return "Welcome to QR Attendance System

Dear {$name},

Your staff account has been successfully created for the QR Attendance System.

LOGIN CREDENTIALS:
Username: {$username}
Password: {$password}

Login URL: {$login_url}

SECURITY NOTE:
- Please change your password immediately after first login
- Keep your credentials secure and do not share them
- If you did not expect this email, please contact your administrator

Next Steps:
1. Visit the login URL above
2. Enter your credentials
3. Change your password in Settings
4. Start using the system

This is an automated email from QR Attendance System.
Please do not reply to this message.";
    }
}
?>
