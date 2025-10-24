<?php
/**
 * Simple Authentication System for Admin Panel
 * In production, use proper authentication with sessions, JWT, or OAuth
 */

session_start();



function authenticate($username, $password) {
    global $ADMIN_CREDENTIALS;
    
    if (isset($ADMIN_CREDENTIALS[$username]) && $ADMIN_CREDENTIALS[$username] === $password) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        return true;
    }
    
    return false;
}

function isAuthenticated() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function logout() {
    session_destroy();
    return true;
}

function requireAuth() {
    if (!isAuthenticated()) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        exit();
    }
}

// Handle login requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'login':
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            
            if (authenticate($username, $password)) {
                echo json_encode(['success' => true, 'message' => 'Login successful']);
            } else {
                http_response_code(401);
                echo json_encode(['error' => 'Invalid credentials']);
            }
            break;
            
        case 'logout':
            logout();
            echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
            break;
            
        case 'check':
            echo json_encode(['authenticated' => isAuthenticated()]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} else {
    // Return login form if not authenticated
    if (!isAuthenticated()) {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Admin Login - QR Attendance System</title>
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                
                body {
                    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: #1e293b;
                }
                
                .login-container {
                    background: rgba(255, 255, 255, 0.95);
                    backdrop-filter: blur(10px);
                    border-radius: 16px;
                    padding: 40px;
                    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
                    border: 1px solid rgba(255, 255, 255, 0.2);
                    width: 100%;
                    max-width: 400px;
                }
                
                .login-header {
                    text-align: center;
                    margin-bottom: 30px;
                }
                
                .login-header h1 {
                    font-size: 1.75rem;
                    font-weight: 700;
                    color: #1e293b;
                    margin-bottom: 8px;
                }
                
                .login-header p {
                    color: #64748b;
                    font-size: 0.9rem;
                }
                
                .form-group {
                    margin-bottom: 20px;
                }
                
                .form-group label {
                    display: block;
                    margin-bottom: 8px;
                    font-weight: 600;
                    color: #1e293b;
                }
                
                .form-control {
                    width: 100%;
                    padding: 12px 16px;
                    border: 1px solid #e2e8f0;
                    border-radius: 10px;
                    font-size: 0.9rem;
                    transition: all 0.2s ease;
                }
                
                .form-control:focus {
                    outline: none;
                    border-color: #3b82f6;
                    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
                }
                
                .btn {
                    width: 100%;
                    background: #1e3a8a;
                    color: white;
                    border: none;
                    padding: 12px 20px;
                    border-radius: 10px;
                    cursor: pointer;
                    font-size: 0.9rem;
                    font-weight: 600;
                    transition: all 0.2s ease;
                }
                
                .btn:hover {
                    background: #1e40af;
                    transform: translateY(-1px);
                }
                
                .alert {
                    padding: 12px 16px;
                    border-radius: 8px;
                    margin-bottom: 20px;
                    display: none;
                }
                
                .alert.error {
                    background: #ffeaea;
                    color: #dc2626;
                    border: 1px solid #ef4444;
                }
                
                .alert.success {
                    background: #e8f5e8;
                    color: #059669;
                    border: 1px solid #10b981;
                }
                
                .credentials {
                    background: #f8fafc;
                    border-radius: 8px;
                    padding: 16px;
                    margin-top: 20px;
                    font-size: 0.8rem;
                    color: #64748b;
                }
                
                .credentials h3 {
                    color: #1e293b;
                    margin-bottom: 8px;
                }
            </style>
        </head>
        <body>
            <div class="login-container">
                <div class="login-header">
                    <h1>Admin Login</h1>
                    <p>QR Code Attendance System</p>
                </div>
                
                <div id="alert" class="alert"></div>
                
                <form id="loginForm">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" class="form-control" required>
                    </div>
                    
                    <button type="submit" class="btn">Login</button>
                </form>
                
                <div class="credentials">
                    <h3>Demo Credentials:</h3>
                    <p><strong>Username:</strong> admin</p>
                    <p><strong>Password:</strong> admin123</p>
                </div>
            </div>
            
            <script>
                document.getElementById('loginForm').addEventListener('submit', async function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(this);
                    formData.append('action', 'login');
                    
                    try {
                        const response = await fetch('admin_auth.php', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            showAlert('Login successful! Redirecting...', 'success');
                            setTimeout(() => {
                                window.location.href = 'admin_panel.html';
                            }, 1000);
                        } else {
                            showAlert(data.error || 'Login failed', 'error');
                        }
                    } catch (error) {
                        showAlert('Login error: ' + error.message, 'error');
                    }
                });
                
                function showAlert(message, type) {
                    const alert = document.getElementById('alert');
                    alert.textContent = message;
                    alert.className = `alert ${type}`;
                    alert.style.display = 'block';
                    
                    setTimeout(() => {
                        alert.style.display = 'none';
                    }, 5000);
                }
            </script>
        </body>
        </html>
        <?php
    } else {
        // Redirect to admin panel if already authenticated
        header('Location: admin_panel.html');
        exit();
    }
}
?>

