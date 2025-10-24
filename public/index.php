<?php
/**
 * Student Portal Index
 * Default landing page - redirects to dashboard or login
 */

require_once 'includes/config.php';
require_once 'includes/auth.php';

// Check if student is already logged in
if (isStudentLoggedIn()) {
    // Redirect to dashboard if already logged in
    header('Location: dashboard.php');
    exit();
} else {
    // Redirect to login page if not logged in
    header('Location: login.php');
    exit();
}
?>
