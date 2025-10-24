<?php
/**
 * Student Logout Page
 * Handles student logout and session cleanup
 */

require_once 'includes/config.php';
require_once 'includes/auth.php';

// Logout the student
logoutStudent();

// Redirect to login page
header('Location: login.php');
exit();
?>
