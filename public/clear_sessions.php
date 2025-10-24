<?php
/**
 * Clear Session Script
 * This script clears all existing session data to fix the authentication issue
 * after changing the folder name from qr_attendance to qr
 */

// Clear all session cookies
if (isset($_COOKIE)) {
    foreach ($_COOKIE as $name => $value) {
        if (strpos($name, 'QR_ATTENDANCE') !== false || 
            strpos($name, 'SECURE_SESSION') !== false || 
            strpos($name, 'admin_csrf') !== false) {
            setcookie($name, '', time() - 3600, '/qr/');
            setcookie($name, '', time() - 3600, '/qr_attendance/');
            setcookie($name, '', time() - 3600, '/');
        }
    }
}

// Clear session data
session_start();
session_destroy();

// Clear any session files in the sessions directory
$session_path = __DIR__ . '/sessions/';
if (is_dir($session_path)) {
    $files = glob($session_path . 'sess_*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
}

echo "Session data cleared successfully!<br>";
echo "You can now try logging in again.<br>";
echo "<a href='admin/login.php'>Go to Admin Login</a><br>";
echo "<a href='login.php'>Go to Student Login</a>";
?>
