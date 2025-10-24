<?php
/**
 * Student Authentication System
 * Handles student login, session management, and authorization
 */

require_once 'config.php';

// Authentication Functions
function isStudentLoggedIn() {
    require_once __DIR__ . '/../includes_ext/secure_session.php';
    return SecureSession::has('student_id') && SecureSession::has('student_username') && SecureSession::validate();
}

function requireStudentAuth() {
    if (!isStudentLoggedIn()) {
        // Store the current page to redirect after login
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: login.php');
        exit();
    }
}

function getCurrentStudent() {
    global $pdo;
    
    if (!isStudentLoggedIn()) {
        return null;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT s.*, u.username, u.email as user_email, u.last_login
            FROM students s 
            LEFT JOIN users u ON s.user_id = u.id 
            WHERE s.student_id = ? AND s.is_active = 1
        ");
        require_once __DIR__ . '/../includes_ext/secure_session.php';
        $stmt->execute([SecureSession::get('student_id')]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching current student: " . $e->getMessage());
        return null;
    }
}

function authenticateStudent($username, $password) {
    global $pdo;
    
    try {
        // Check if student exists and is active
        $stmt = $pdo->prepare("
            SELECT s.*, u.username, u.email, u.password_hash, u.last_login
            FROM students s 
            LEFT JOIN users u ON s.user_id = u.id 
            WHERE (s.username = ? OR s.student_id = ? OR u.username = ?) 
            AND s.is_active = 1
        ");
        $stmt->execute([$username, $username, $username]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        
        if (!$student) {
            return ['success' => false, 'message' => 'Student not found or inactive'];
        }
        
        // SECURITY FIX: Use secure password verification
        require_once __DIR__ . '/../includes_ext/password_manager.php';
        
        $password_hash = $student['password_hash'] ?? null;
        $student_password = $student['password'] ?? null;
        
        $password_valid = false;
        
        // First try hashed password from users table (newer records)
        if ($password_hash) {
            $password_valid = PasswordManager::verifyPassword($password, $password_hash);
            
            // Check if rehashing is needed for security updates
            if ($password_valid && PasswordManager::needsRehash($password_hash)) {
                $new_hash = PasswordManager::hashPassword($password);
                $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $stmt->execute([$new_hash, $student['user_id']]);
            }
        }
        
        // If no user record or hashed password fails, try student password field
        if (!$password_valid && $student_password) {
            // Check if student password is hashed (60+ characters) or plain text
            if (strlen($student_password) > 20) {
                // It's a hash, verify it
                $password_valid = PasswordManager::verifyPassword($password, $student_password);
                
                // If valid but needs rehashing, update it
                if ($password_valid && PasswordManager::needsRehash($student_password)) {
                    $new_hash = PasswordManager::hashPassword($password);
                    $stmt = $pdo->prepare("UPDATE students SET password = ? WHERE student_id = ?");
                    $stmt->execute([$new_hash, $username]);
                }
            } else {
                // CRITICAL SECURITY FIX: Plain text passwords are not allowed
                // Hash the plain text password and update the database
                $new_hash = PasswordManager::hashPassword($password);
                $stmt = $pdo->prepare("UPDATE students SET password = ? WHERE student_id = ?");
                $stmt->execute([$new_hash, $username]);
                
                // Now verify the newly hashed password
                $password_valid = PasswordManager::verifyPassword($password, $new_hash);
            }
        }
        
        if (!$password_valid) {
            return ['success' => false, 'message' => 'Invalid password'];
        }
        
        // Update last login (only if user_id exists)
        if ($student['user_id']) {
            $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$student['user_id']]);
        }
        
        // SECURITY FIX: Use secure session management
        require_once __DIR__ . '/../includes_ext/secure_session.php';
        
        // Set session variables securely
        SecureSession::set('student_id', $student['student_id']);
        SecureSession::set('student_username', $student['username'] ?: $student['student_id']);
        SecureSession::set('student_name', $student['name']);
        SecureSession::set('student_email', $student['email']);
        SecureSession::set('student_program', $student['program']);
        SecureSession::set('student_shift', $student['shift']);
        SecureSession::set('student_year_level', $student['year_level']);
        SecureSession::set('student_section', $student['section']);
        SecureSession::set('login_time', time());
        
        // Record successful login
        SecureSession::recordLoginAttempt(true);
        
        // Log successful login
        logStudentAction('LOGIN_SUCCESS', "Student logged in: {$student['student_id']}");
        
        return ['success' => true, 'student' => $student];
        
    } catch (PDOException $e) {
        error_log("Authentication error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Authentication failed. Please try again.'];
    }
}

function logoutStudent() {
    require_once __DIR__ . '/../includes_ext/secure_session.php';
    
    if (isStudentLoggedIn()) {
        $student_id = SecureSession::get('student_id');
        logStudentAction('LOGOUT', "Student logged out: {$student_id}");
    }
    
    // Destroy secure session
    SecureSession::destroy();
}

function checkSessionTimeout() {
    if (isStudentLoggedIn() && isset($_SESSION['login_time'])) {
        if (time() - $_SESSION['login_time'] > STUDENT_SESSION_TIMEOUT) {
            logoutStudent();
            return false;
        }
        return true;
    }
    return false;
}

function hasRole($role) {
    // For now, all students have the same role
    // This can be extended for different student types
    return isStudentLoggedIn();
}

function getStudentAttendanceStats($student_id) {
    global $pdo;
    
    try {
        // Get current month attendance
        $current_month = date('Y-m');
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_days,
                SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_days,
                SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_days,
                SUM(CASE WHEN status = 'Check-in' THEN 1 ELSE 0 END) as checkin_days
            FROM attendance 
            WHERE student_id = ? 
            AND DATE_FORMAT(timestamp, '%Y-%m') = ?
        ");
        $stmt->execute([$student_id, $current_month]);
        $monthly_stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get overall attendance percentage
        $stmt = $pdo->prepare("
            SELECT attendance_percentage 
            FROM students 
            WHERE student_id = ?
        ");
        $stmt->execute([$student_id]);
        $overall_stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Ensure proper array structure with default values
        return [
            'monthly' => [
                'total_days' => (int)($monthly_stats['total_days'] ?? 0),
                'present_days' => (int)($monthly_stats['present_days'] ?? 0),
                'absent_days' => (int)($monthly_stats['absent_days'] ?? 0),
                'checkin_days' => (int)($monthly_stats['checkin_days'] ?? 0),
                'percentage' => 0
            ],
            'overall' => [
                'total_days' => 0,
                'present_days' => 0,
                'absent_days' => 0,
                'checkin_days' => 0,
                'percentage' => 0
            ],
            'overall_percentage' => (float)($overall_stats['attendance_percentage'] ?? 0)
        ];
        
    } catch (PDOException $e) {
        error_log("Error fetching attendance stats: " . $e->getMessage());
        // Return proper array structure even on error
        return [
            'monthly' => [
                'total_days' => 0,
                'present_days' => 0,
                'absent_days' => 0,
                'checkin_days' => 0,
                'percentage' => 0
            ],
            'overall' => [
                'total_days' => 0,
                'present_days' => 0,
                'absent_days' => 0,
                'checkin_days' => 0,
                'percentage' => 0
            ],
            'overall_percentage' => 0
        ];
    }
}

function getStudentRecentAttendance($student_id, $limit = 10) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                DATE(timestamp) as date,
                TIME(timestamp) as time,
                status, 
                notes
            FROM attendance 
            WHERE student_id = ? 
            ORDER BY timestamp DESC 
            LIMIT ?
        ");
        $stmt->execute([$student_id, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error fetching recent attendance: " . $e->getMessage());
        return [];
    }
}

function getStudentUpcomingEvents($student_id) {
    global $pdo;
    
    try {
        // Get upcoming assignments (if assignments table exists)
        $events = [];
        
        // For now, return empty array - can be extended when assignments/quizzes are implemented
        return $events;
        
    } catch (PDOException $e) {
        error_log("Error fetching upcoming events: " . $e->getMessage());
        return [];
    }
}

// Auto-check session timeout on every request
checkSessionTimeout();
?>
