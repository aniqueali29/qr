<?php
/**
 * Admin Logout Handler
 * Destroys session and redirects to login
 */

require_once 'includes/config.php';
require_once 'includes/auth.php';

// Log the logout action
logAdminAction('LOGOUT', 'User logged out');

// Perform logout
adminLogout();

// Redirect to login page
header('Location: login.php?logged_out=1');
exit();
?>
