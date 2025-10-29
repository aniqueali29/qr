<?php
/**
 * Admin Profile Page
 * Profile management for admin users including password change, profile picture, and details
 */

require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';

// Require admin authentication
requireAdminAuth();

// Get current admin user
$currentUser = getAdminUser();

// Get user's profile picture from database
$profilePicture = '../assets/img/avatars/1.png'; // Default
if ($currentUser) {
    try {
        $stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['admin_user_id']]);
        $picData = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($picData && $picData['profile_picture']) {
            // Database already contains full path like: uploads/profile_pictures/filename.jpg
            $profilePicture = '../' . $picData['profile_picture'];
        }
    } catch (Exception $e) {
        error_log("Profile picture fetch error: " . $e->getMessage());
    }
}

$pageTitle = "My Profile";
$currentPage = "profile";
$pageCSS = [];
$pageJS = ['js/admin.js'];

include 'partials/header.php';
include 'partials/sidebar.php';
?>

<style>
.profile-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 10px;
    padding: 30px;
    color: white;
    margin-bottom: 30px;
}

.profile-picture-container {
    position: relative;
    display: inline-block;
    margin-bottom: 20px;
}

.profile-picture {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    border: 5px solid white;
    object-fit: cover;
    cursor: pointer;
    transition: all 0.3s;
}

.profile-picture:hover {
    opacity: 0.8;
}

.upload-overlay {
    position: absolute;
    bottom: 0;
    right: 0;
    background: #667eea;
    color: white;
    border-radius: 50%;
    width: 45px;
    height: 45px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    border: 3px solid white;
    transition: all 0.3s;
}

.upload-overlay:hover {
    background: #764ba2;
    transform: scale(1.1);
}

.profile-details-card {
    background: white;
    border-radius: 10px;
    padding: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.detail-row {
    display: flex;
    padding: 15px 0;
    border-bottom: 1px solid #f0f0f0;
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-label {
    font-weight: 600;
    color: #666;
    width: 150px;
    flex-shrink: 0;
}

.detail-value {
    color: #333;
    flex: 1;
}

.badge-custom {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.tab-content-section {
    display: none;
}

.tab-content-section.active {
    display: block;
}
</style>

<div class="layout-page">
    <div class="content-wrapper">
        
        <!-- Content -->
        <div class="container-xxl flex-grow-1 container-p-y">
            
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mt-2 mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">My Profile</li>
                </ol>
            </nav>
            
            <!-- Profile Header -->
            <div class="profile-header">
                <div class="d-flex align-items-center">
                    <div class="profile-picture-container">
                        <img src="<?php echo htmlspecialchars($profilePicture); ?>" alt="Profile Picture" 
                             class="profile-picture" id="profilePicture" 
                             onclick="document.getElementById('profilePictureInput').click()">
                        <div class="upload-overlay" onclick="document.getElementById('profilePictureInput').click()">
                            <i class="bx bx-camera"></i>
                        </div>
                        <input type="file" id="profilePictureInput" accept="image/*" style="display: none;" 
                               onchange="uploadProfilePicture(this)">
                    </div>
                    <div class="ms-4">
                        <h2 class="text-white mb-1"><?php echo htmlspecialchars($currentUser['username']); ?></h2>
                        <p class="text-white-50 mb-0">
                            <?php 
                            $roleText = '';
                            if ($currentUser['role'] === 'superadmin') {
                                $roleText = 'Super Admin';
                                echo '<span class="badge-custom bg-danger">' . $roleText . '</span>';
                            } elseif ($currentUser['role'] === 'admin') {
                                $roleText = 'Admin';
                                echo '<span class="badge-custom bg-warning">' . $roleText . '</span>';
                            } else {
                                $roleText = 'Staff';
                                echo '<span class="badge-custom bg-info">' . $roleText . '</span>';
                            }
                            ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Profile Navigation Tabs -->
            <div class="card mb-4">
                <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#details" type="button" role="tab">
                                <i class="bx bx-user me-2"></i>Account Details
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#password" type="button" role="tab">
                                <i class="bx bx-lock me-2"></i>Change Password
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab">
                                <i class="bx bx-shield me-2"></i>Security Settings
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content">
                        
                        <!-- Account Details Tab -->
                        <div class="tab-pane fade show active" id="details" role="tabpanel">
                            <?php
                            // Get additional details from database
                            $stmt = $pdo->prepare("
                                SELECT username, email, role, is_active, last_login, created_at,
                                       (SELECT COUNT(*) FROM staff_permissions WHERE user_id = ?) as page_access_count
                                FROM users 
                                WHERE id = ?
                            ");
                            $stmt->execute([$_SESSION['admin_user_id'], $_SESSION['admin_user_id']]);
                            $userDetails = $stmt->fetch(PDO::FETCH_ASSOC);
                            ?>
                            
                            <div class="profile-details-card">
                                <div class="detail-row">
                                    <div class="detail-label">Username:</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($userDetails['username']); ?></div>
                                </div>
                                <div class="detail-row">
                                    <div class="detail-label">Email:</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($userDetails['email']); ?></div>
                                </div>
                                <div class="detail-row">
                                    <div class="detail-label">Role:</div>
                                    <div class="detail-value">
                                        <?php
                                        if ($userDetails['role'] === 'superadmin') {
                                            echo '<span class="badge bg-danger">Super Admin</span>';
                                        } elseif ($userDetails['role'] === 'admin') {
                                            echo '<span class="badge bg-warning">Admin</span>';
                                        } else {
                                            echo '<span class="badge bg-info">Staff</span>';
                                        }
                                        ?>
                                    </div>
                                </div>
                                <div class="detail-row">
                                    <div class="detail-label">Account Status:</div>
                                    <div class="detail-value">
                                        <?php if ($userDetails['is_active']): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactive</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if ($userDetails['role'] === 'staff' || $userDetails['role'] === 'admin'): ?>
                                <div class="detail-row">
                                    <div class="detail-label">Access Pages:</div>
                                    <div class="detail-value">
                                        <?php 
                                        if ($userDetails['role'] === 'superadmin' || $userDetails['page_access_count'] == 0) {
                                            echo '<span class="badge bg-success">Full Access</span>';
                                        } else {
                                            echo '<span class="badge bg-info">' . $userDetails['page_access_count'] . ' pages</span>';
                                        }
                                        ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <div class="detail-row">
                                    <div class="detail-label">Last Login:</div>
                                    <div class="detail-value">
                                        <?php 
                                        if ($userDetails['last_login']) {
                                            $lastLogin = new DateTime($userDetails['last_login']);
                                            echo $lastLogin->format('M d, Y \a\t h:i A');
                                        } else {
                                            echo '<span class="text-muted">Never</span>';
                                        }
                                        ?>
                                    </div>
                                </div>
                                <div class="detail-row">
                                    <div class="detail-label">Account Created:</div>
                                    <div class="detail-value">
                                        <?php 
                                        $createdAt = new DateTime($userDetails['created_at']);
                                        echo $createdAt->format('M d, Y \a\t h:i A');
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Change Password Tab -->
                        <div class="tab-pane fade" id="password" role="tabpanel">
                            <div class="alert alert-info">
                                <i class="bx bx-info-circle me-2"></i>
                                Reset your password using email verification. Enter your email and you'll receive a verification code.
                            </div>
                            
                            <form id="changePasswordForm">
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="forgotPassword" onchange="togglePasswordMode()">
                                        <label class="form-check-label" for="forgotPassword">
                                            I forgot my password
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="row g-3" id="currentPasswordRow">
                                    <div class="col-md-6">
                                        <label for="currentPassword" class="form-label">Current Password *</label>
                                        <input type="password" class="form-control" id="currentPassword">
                                    </div>
                                </div>
                                <div class="row g-3 mt-2">
                                    <div class="col-md-6">
                                        <label for="newPassword" class="form-label">New Password *</label>
                                        <input type="password" class="form-control" id="newPassword" required 
                                               minlength="8" placeholder="Min. 8 characters">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="confirmPassword" class="form-label">Confirm New Password *</label>
                                        <input type="password" class="form-control" id="confirmPassword" required 
                                               minlength="8" placeholder="Must match new password">
                                    </div>
                                </div>
                                <div class="row g-3 mt-3" id="otpSection">
                                    <div class="col-md-6">
                                        <label for="otpCode" class="form-label">Verification Code (OTP) *</label>
                                        <input type="text" class="form-control" id="otpCode" required 
                                               placeholder="Enter 6-digit code sent to your email">
                                        <button type="button" class="btn btn-outline-primary btn-sm mt-2" 
                                                onclick="requestOTP()">
                                            <i class="bx bx-refresh me-1"></i>Send OTP to Email
                                        </button>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bx bx-key me-1"></i>Change Password
                                    </button>
                                    <button type="button" class="btn btn-secondary" onclick="resetPasswordForm()">
                                        <i class="bx bx-x me-1"></i>Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Security Settings Tab -->
                        <div class="tab-pane fade" id="security" role="tabpanel">
                            <h5 class="mb-3">Login Activity</h5>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date & Time</th>
                                            <th>IP Address</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="loginHistoryTable">
                                        <tr>
                                            <td colspan="3" class="text-center text-muted">Loading login history...</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                    </div>
                </div>
            </div>
            
        </div>
        <!-- / Content -->
        
    </div>
</div>

<!-- Core JS -->
<script src="<?php echo getAdminAssetUrl('vendor/libs/jquery/jquery.js'); ?>"></script>
<script src="<?php echo getAdminAssetUrl('vendor/libs/popper/popper.js'); ?>"></script>
<script src="<?php echo getAdminAssetUrl('vendor/js/bootstrap.js'); ?>"></script>
<script src="<?php echo getAdminAssetUrl('vendor/libs/perfect-scrollbar/perfect-scrollbar.js'); ?>"></script>
<script src="<?php echo getAdminAssetUrl('vendor/js/menu.js'); ?>"></script>
<script src="<?php echo getAdminAssetUrl('js/main.js'); ?>"></script>

<script>
// Profile management JavaScript
let otpSent = false;
let otpExpiresAt = null;

document.addEventListener('DOMContentLoaded', function() {
    loadLoginHistory();
});

function uploadProfilePicture(input) {
    if (!input.files || !input.files[0]) return;
    
    const file = input.files[0];
    
    // Validate file type
    if (!file.type.startsWith('image/')) {
        alert('Please select an image file');
        return;
    }
    
    // Validate file size (max 5MB)
    if (file.size > 5 * 1024 * 1024) {
        alert('File size must be less than 5MB');
        return;
    }
    
    const formData = new FormData();
    formData.append('profile_picture', file);
    formData.append('action', 'upload_picture');
    
    // Show loading
    const img = document.getElementById('profilePicture');
    const originalSrc = img.src;
    img.style.opacity = '0.5';
    
    fetch('api/profile.php?action=upload_picture', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.text().then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Failed to parse JSON:', text);
                throw new Error('Invalid response from server');
            }
        });
    })
    .then(data => {
        img.style.opacity = '1';
        if (data.success) {
            // Update profile picture - use the URL from response
            if (data.profile_picture_url) {
                img.src = data.profile_picture_url + '?t=' + Date.now();
            }
            if (typeof UIHelpers !== 'undefined') {
                UIHelpers.showSuccess(data.message || 'Profile picture updated successfully!');
            } else {
                alert(data.message || 'Profile picture updated successfully!');
            }
            // Reload page after 1 second to show updated picture
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            const errorMessage = data.message || data.error || 'Unknown error';
            if (typeof UIHelpers !== 'undefined') {
                UIHelpers.showError('Failed to update picture: ' + errorMessage);
            } else {
                alert('Failed to update picture: ' + errorMessage);
            }
            img.src = originalSrc;
        }
    })
    .catch(error => {
        console.error('Upload error:', error);
        img.style.opacity = '1';
        img.src = originalSrc;
        if (typeof UIHelpers !== 'undefined') {
            UIHelpers.showError('Error uploading picture: ' + error.message);
        } else {
            alert('Error uploading picture: ' + error.message);
        }
    });
}

function requestOTP() {
    const btn = event.target;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Sending...';
    
    fetch('api/profile.php?action=send_otp', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({})
    })
    .then(response => {
        // Check if response is OK
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        // Try to parse JSON
        return response.text().then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Failed to parse JSON:', text);
                // If JSON parsing fails, check if it's the OTP message
                if (text.includes('OTP generated')) {
                    return {success: true, message: text};
                }
                throw new Error('Invalid response from server');
            }
        });
    })
    .then(data => {
        if (data.success) {
            otpSent = true;
            otpExpiresAt = new Date(Date.now() + 10 * 60 * 1000); // 10 minutes
            
            // Show the message from server (might contain the OTP)
            if (typeof UIHelpers !== 'undefined') {
                UIHelpers.showSuccess(data.message || 'Verification code sent to your email!');
            } else {
                alert(data.message || 'Verification code sent to your email!');
            }
            
            // Start timer with the button reference
            startOTPTimer(btn);
        } else {
            if (typeof UIHelpers !== 'undefined') {
                UIHelpers.showError('Failed to send OTP: ' + (data.message || 'Unknown error'));
            } else {
                alert('Failed to send OTP: ' + (data.message || 'Unknown error'));
            }
        }
        btn.disabled = false;
        btn.innerHTML = '<i class="bx bx-refresh me-1"></i>Send OTP to Email';
    })
    .catch(error => {
        console.error('OTP request error:', error);
        btn.disabled = false;
        btn.innerHTML = '<i class="bx bx-refresh me-1"></i>Send OTP to Email';
        if (typeof UIHelpers !== 'undefined') {
            UIHelpers.showError('Error requesting OTP: ' + error.message);
        } else {
            alert('Error requesting OTP: ' + error.message);
        }
    });
}

function startOTPTimer(button) {
    const btn = button;
    let seconds = 60;
    
    const timer = setInterval(() => {
        btn.innerHTML = `Resend in ${seconds}s`;
        btn.disabled = true;
        
        if (seconds <= 0) {
            clearInterval(timer);
            btn.innerHTML = '<i class="bx bx-refresh me-1"></i>Send OTP to Email';
            btn.disabled = false;
        }
        
        seconds--;
    }, 1000);
}

function resetPasswordForm() {
    document.getElementById('changePasswordForm').reset();
    document.getElementById('otpCode').value = '';
    otpSent = false;
}

function togglePasswordMode() {
    const forgotPassword = document.getElementById('forgotPassword').checked;
    const currentPasswordRow = document.getElementById('currentPasswordRow');
    const currentPasswordInput = document.getElementById('currentPassword');
    const otpSection = document.getElementById('otpSection');
    const otpCodeInput = document.getElementById('otpCode');
    
    if (forgotPassword) {
        // Forgot password mode: Hide current password, show OTP
        currentPasswordRow.style.display = 'none';
        currentPasswordInput.removeAttribute('required');
        otpSection.style.display = 'block';
        otpCodeInput.setAttribute('required', 'required');
    } else {
        // Normal password change: Show current password, hide OTP
        currentPasswordRow.style.display = 'block';
        currentPasswordInput.setAttribute('required', 'required');
        otpSection.style.display = 'none';
        otpCodeInput.removeAttribute('required');
        // Clear OTP fields
        otpCodeInput.value = '';
        otpSent = false;
    }
}

document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const forgotPassword = document.getElementById('forgotPassword').checked;
    const currentPassword = document.getElementById('currentPassword').value;
    const newPassword = document.getElementById('newPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    const otpCode = document.getElementById('otpCode').value;
    
    // Validation
    if (newPassword.length < 8) {
        if (typeof UIHelpers !== 'undefined') {
            UIHelpers.showError('New password must be at least 8 characters');
        } else {
            alert('New password must be at least 8 characters');
        }
        return;
    }
    
    if (newPassword !== confirmPassword) {
        if (typeof UIHelpers !== 'undefined') {
            UIHelpers.showError('Passwords do not match');
        } else {
            alert('Passwords do not match');
        }
        return;
    }
    
    // OTP validation only required for forgot password mode
    if (forgotPassword) {
        if (!otpSent) {
            if (typeof UIHelpers !== 'undefined') {
                UIHelpers.showError('Please request an OTP first');
            } else {
                alert('Please request an OTP first');
            }
            return;
        }
        
        if (otpCode.length !== 6) {
            if (typeof UIHelpers !== 'undefined') {
                UIHelpers.showError('Please enter a valid 6-digit verification code');
            } else {
                alert('Please enter a valid 6-digit verification code');
            }
            return;
        }
    }
    
    // If not forgot password mode, current password is required
    if (!forgotPassword && !currentPassword) {
        if (typeof UIHelpers !== 'undefined') {
            UIHelpers.showError('Current password is required');
        } else {
            alert('Current password is required');
        }
        return;
    }
    
    const btn = e.target.querySelector('button[type="submit"]');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Changing Password...';
    
    // Build request data based on mode
    const requestData = {
        forgot_password: forgotPassword,
        current_password: currentPassword,
        new_password: newPassword
    };
    
    // Only include OTP for forgot password mode
    if (forgotPassword) {
        requestData.otp = otpCode;
    }
    
    fetch('api/profile.php?action=change_password', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(requestData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (typeof UIHelpers !== 'undefined') {
                UIHelpers.showSuccess('Password changed successfully!');
            } else {
                alert('Password changed successfully!');
            }
            resetPasswordForm();
        } else {
            if (typeof UIHelpers !== 'undefined') {
                UIHelpers.showError('Failed to change password: ' + data.message);
            } else {
                alert('Failed to change password: ' + data.message);
            }
        }
        btn.disabled = false;
        btn.innerHTML = originalText;
    })
    .catch(error => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        if (typeof UIHelpers !== 'undefined') {
            UIHelpers.showError('Error changing password');
        } else {
            alert('Error changing password');
        }
    });
});

function loadLoginHistory() {
    fetch('api/profile.php?action=login_history')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const tbody = document.getElementById('loginHistoryTable');
                
                if (data.history.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">No login history available</td></tr>';
                    return;
                }
                
                let html = '';
                data.history.forEach(entry => {
                    const date = new Date(entry.login_time);
                    html += `
                        <tr>
                            <td>${date.toLocaleString()}</td>
                            <td>${entry.ip_address || 'Unknown'}</td>
                            <td><span class="badge bg-success">Successful</span></td>
                        </tr>
                    `;
                });
                
                tbody.innerHTML = html;
            }
        })
        .catch(error => {
            console.error('Error loading login history:', error);
        });
}
</script>

</body>
</html>

