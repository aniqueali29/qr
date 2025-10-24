<?php
require_once 'config.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'login') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Authenticate student using roll number
    $stmt = $pdo->prepare("
        SELECT id, student_id, roll_number, name, email, username, password 
        FROM students 
        WHERE username = ? AND password = ? AND is_active = 1
    ");
    $stmt->execute([$username, $password]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($student) {
        $_SESSION['user_id'] = $student['id'];
        $_SESSION['student_id'] = $student['student_id'];
        $_SESSION['role'] = 'student';
        $_SESSION['name'] = $student['name'];
        
        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'user' => [
                'student_id' => $student['student_id'],
                'name' => $student['name'],
                'role' => 'student'
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid credentials'
        ]);
    }
} elseif ($action === 'check_auth') {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'student') {
        echo json_encode([
            'success' => true,
            'user' => [
                'student_id' => $_SESSION['student_id'],
                'name' => $_SESSION['name'],
                'role' => 'student'
            ]
        ]);
    } else {
        echo json_encode(['success' => false]);
    }
} elseif ($action === 'logout') {
    session_destroy();
    echo json_encode(['success' => true]);
}
?>
