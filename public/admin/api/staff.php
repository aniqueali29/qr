<?php
/**
 * Staff Management API
 * Handles CRUD operations for staff accounts with page access control
 */

require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

header('Content-Type: application/json');

// Check authentication
if (!isAdminLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Check if user is super admin or admin (for staff management)  
// Allow both admin and superadmin roles to access staff management
$currentUser = getAdminUser();
if (!$currentUser) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden: Unauthorized access']);
    exit();
}

// Get the action parameter
$action = $_GET['action'] ?? 'list';

// Only superadmin and admin can modify staff (create, update, delete)
$modifyActions = ['create', 'update', 'delete'];
if (in_array($action, $modifyActions) && !in_array($currentUser['role'], ['superadmin', 'admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden: Only Admin/Super Admin can modify staff']);
    exit();
}

try {
    switch ($action) {
        case 'list':
            echo json_encode(getStaffList());
            break;
        case 'get':
            echo json_encode(getStaff($_GET['id'] ?? 0));
            break;
        case 'create':
            echo json_encode(createStaff());
            break;
        case 'update':
            echo json_encode(updateStaff());
            break;
        case 'delete':
            echo json_encode(deleteStaff($_GET['id'] ?? 0));
            break;
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    error_log("Staff API Error: " . $e->getMessage() . " - Trace: " . $e->getTraceAsString());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function getStaffList() {
    global $pdo, $currentUser;
    
    try {
        // Check if staff_permissions table exists
        $tableExists = false;
        try {
            $testStmt = $pdo->query("SHOW TABLES LIKE 'staff_permissions'");
            $tableExists = $testStmt->rowCount() > 0;
        } catch (Exception $e) {
            $tableExists = false;
        }
        
        $pageCountColumn = $tableExists 
            ? "(SELECT COUNT(*) FROM staff_permissions WHERE user_id = users.id) as page_access_count"
            : "0 as page_access_count";
        
        // Build the WHERE clause based on user role
        $whereClause = "WHERE role IN ('staff', 'admin', 'superadmin')";
        
        // If current user is NOT superadmin, hide superadmin accounts
        if ($currentUser['role'] !== 'superadmin') {
            $whereClause = "WHERE role IN ('staff', 'admin')";
        }
            
        $stmt = $pdo->query("
            SELECT id, username, email, is_active, last_login, role, $pageCountColumn
            FROM users 
            $whereClause
            ORDER BY 
                CASE role 
                    WHEN 'superadmin' THEN 1 
                    WHEN 'admin' THEN 2 
                    ELSE 3 
                END, created_at DESC
        ");
        
        $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'data' => $staff
        ];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function getStaff($id) {
    global $pdo;
    
    try {
        // Get user (staff or admin)
        $stmt = $pdo->prepare("SELECT id, username, email, is_active, role FROM users WHERE id = ? AND role IN ('staff', 'admin', 'superadmin')");
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return ['success' => false, 'error' => 'Staff not found'];
        }
        
        // Get permissions if table exists
        $permissions = [];
        try {
            $testStmt = $pdo->query("SHOW TABLES LIKE 'staff_permissions'");
            if ($testStmt->rowCount() > 0) {
                $stmt = $pdo->prepare("SELECT page_url FROM staff_permissions WHERE user_id = ?");
                $stmt->execute([$id]);
                $permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
            }
        } catch (Exception $e) {
            $permissions = [];
        }
        
        $user['permissions'] = $permissions;
        
        return [
            'success' => true,
            'data' => $user
        ];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function createStaff() {
    global $pdo;
    
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $username = $input['username'] ?? '';
        $email = $input['email'] ?? '';
        $password = $input['password'] ?? '';
        $role = $input['role'] ?? 'staff';
        $status = $input['status'] ?? 1;
        $permissions = $input['permissions'] ?? [];
        
        // Validation
        if (empty($username) || empty($email) || empty($password)) {
            return ['success' => false, 'error' => 'Username, email, and password are required'];
        }
        
        if (strlen($password) < 8) {
            return ['success' => false, 'error' => 'Password must be at least 8 characters'];
        }
        
        // Check if username exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            return ['success' => false, 'error' => 'Username already exists'];
        }
        
        // Check if email exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            return ['success' => false, 'error' => 'Email already exists'];
        }
        
        $pdo->beginTransaction();
        
        try {
            // Create user
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, password_hash, role, is_active, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$username, $email, $passwordHash, $role, $status]);
            
            $userId = $pdo->lastInsertId();
            
            // Add permissions if table exists
            if (!empty($permissions)) {
                try {
                    $testStmt = $pdo->query("SHOW TABLES LIKE 'staff_permissions'");
                    if ($testStmt->rowCount() > 0) {
                        $stmt = $pdo->prepare("INSERT INTO staff_permissions (user_id, page_url) VALUES (?, ?)");
                        foreach ($permissions as $page) {
                            $stmt->execute([$userId, $page]);
                        }
                    }
                } catch (Exception $e) {
                    // Table doesn't exist, skip permissions
                }
            }
            
            $pdo->commit();
            
            // Try to send email with credentials (non-blocking)
            $emailSent = false;
            try {
                $emailServicePath = __DIR__ . '/../../includes/email_service.php';
                if (file_exists($emailServicePath)) {
                    require_once $emailServicePath;
                    
                    if (class_exists('EmailService')) {
                        $emailService = new EmailService();
                        $loginUrl = "http://localhost" . dirname(dirname($_SERVER['PHP_SELF'])) . '/public/admin/login.php';
                        $emailResult = $emailService->sendStaffAccountCredentials($email, $username, $username, $password, $loginUrl);
                        $emailSent = $emailResult['success'] ?? false;
                    }
                }
            } catch (Exception $e) {
                // Email sending failed, but don't fail account creation
                error_log("Email sending error: " . $e->getMessage());
            }
            
            return [
                'success' => true,
                'message' => 'Staff account created successfully',
                'data' => ['id' => $userId, 'email_sent' => $emailSent]
            ];
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function updateStaff() {
    global $pdo, $currentUser;
    
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $userId = $input['user_id'] ?? 0;
        $username = $input['username'] ?? '';
        $email = $input['email'] ?? '';
        $password = $input['password'] ?? '';
        $role = $input['role'] ?? 'staff';
        $status = $input['status'] ?? 1;
        $permissions = $input['permissions'] ?? [];
        
        if (!$userId) {
            return ['success' => false, 'error' => 'User ID is required'];
        }
        
        // Prevent Staff from editing Admin or Super Admin accounts
        if ($currentUser['role'] === 'staff') {
            $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($targetUser && in_array($targetUser['role'], ['admin', 'superadmin'])) {
                return ['success' => false, 'error' => 'Staff accounts cannot edit Admin or Super Admin accounts'];
            }
        }
        
        // Prevent Normal Admin from editing their own account
        if ($currentUser['role'] === 'admin' && $userId == $currentUser['id']) {
            return ['success' => false, 'error' => 'You cannot edit your own account'];
        }
        
        // Prevent Normal Admin from editing Super Admin accounts
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($targetUser && $targetUser['role'] === 'superadmin' && $currentUser['role'] !== 'superadmin') {
            return ['success' => false, 'error' => 'You cannot edit Super Admin accounts'];
        }
        
        // Remove empty passwords to prevent accidental updates
        if ($password === '') {
            unset($password);
        }
        
        $pdo->beginTransaction();
        
        try {
            // Update user
            if (!empty($password) && isset($password)) {
                if (strlen($password) < 8) {
                    throw new Exception('Password must be at least 8 characters');
                }
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, password_hash = ?, role = ?, is_active = ? WHERE id = ? AND role IN ('staff', 'admin', 'superadmin')");
                $stmt->execute([$username, $email, $passwordHash, $role, $status, $userId]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, role = ?, is_active = ? WHERE id = ? AND role IN ('staff', 'admin', 'superadmin')");
                $stmt->execute([$username, $email, $role, $status, $userId]);
            }
            
            // Update permissions if table exists
            try {
                $testStmt = $pdo->query("SHOW TABLES LIKE 'staff_permissions'");
                if ($testStmt->rowCount() > 0) {
                    $stmt = $pdo->prepare("DELETE FROM staff_permissions WHERE user_id = ?");
                    $stmt->execute([$userId]);
                    
                    if (!empty($permissions)) {
                        $stmt = $pdo->prepare("INSERT INTO staff_permissions (user_id, page_url) VALUES (?, ?)");
                        foreach ($permissions as $page) {
                            $stmt->execute([$userId, $page]);
                        }
                    }
                }
            } catch (Exception $e) {
                // Table doesn't exist, skip permissions
            }
            
            $pdo->commit();
            
            return [
                'success' => true,
                'message' => 'Staff account updated successfully'
            ];
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function deleteStaff($id) {
    global $pdo;
    
    try {
        if (!$id) {
            return ['success' => false, 'error' => 'User ID is required'];
        }
        
        $pdo->beginTransaction();
        
        try {
            // Check if user exists and get their role
            $stmt = $pdo->prepare("SELECT id, username, role FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                throw new Exception('User not found');
            }
            
            // Prevent deletion of superadmin accounts only
            if ($user['role'] === 'superadmin') {
                throw new Exception('Cannot delete superadmin accounts');
            }
            
            // Delete permissions if table exists
            try {
                $testStmt = $pdo->query("SHOW TABLES LIKE 'staff_permissions'");
                if ($testStmt->rowCount() > 0) {
                    $stmt = $pdo->prepare("DELETE FROM staff_permissions WHERE user_id = ?");
                    $stmt->execute([$id]);
                }
            } catch (Exception $e) {
                // Table doesn't exist, skip
            }
            
            // Delete user (only staff)
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'staff'");
            $stmt->execute([$id]);
            
            $pdo->commit();
            
            return [
                'success' => true,
                'message' => 'Staff account deleted successfully'
            ];
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

