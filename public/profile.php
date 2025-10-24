<?php
/**
 * Student Profile Page
 * Dynamic profile management with picture upload, password reset, and comprehensive info display
 */

require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/profile_helpers.php';

// Require student authentication
requireStudentAuth();

// Get current student data
$current_student = getCurrentStudent();
if (!$current_student) {
    logoutStudent();
    header('Location: login.php');
    exit();
}

$error_message = '';
$success_message = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    // Validate CSRF token
    if (!validateCSRFToken($csrf_token)) {
        $error_message = 'Invalid security token. Please try again.';
    } else {
        if ($action === 'update_profile') {
            $phone = sanitizeInput($_POST['phone'] ?? '');
            
                try {
                // Update only phone number (name and email are read-only)
                    $stmt = $pdo->prepare("
                        UPDATE students 
                    SET phone = ?, updated_at = NOW() 
                        WHERE student_id = ?
                    ");
                $stmt->execute([$phone, $current_student['student_id']]);
                
                $success_message = 'Phone number updated successfully!';
                    
                    // Refresh student data
                    $current_student = getCurrentStudent();
                    
                // Log the action
                logStudentAction('PROFILE_UPDATE', "Phone number updated");
                    
                } catch (PDOException $e) {
                $error_message = 'Failed to update phone number. Please try again.';
                    error_log("Profile update error: " . $e->getMessage());
            }
        }
    }
}

$pageTitle = "Profile - " . $current_student['name'];
$currentPage = "profile";
$pageJS = ['js/profile.js'];

// Include header
include 'includes/partials/header.php';

// Include sidebar
include 'includes/partials/sidebar.php';

// Include navbar
include 'includes/partials/navbar.php';
?>

                <!-- Content wrapper -->
                <div class="content-wrapper">
                    <!-- Content -->
                    <div class="container-xxl flex-grow-1 container-p-y">
                        <h4 class="fw-bold py-3 mb-4">
                            <span class="text-muted fw-light">Student /</span> Profile
                        </h4>

                        <?php if ($error_message): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="bx bx-error me-2"></i>
                                <?php echo sanitizeOutput($error_message); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($success_message): ?>
                            <div class="alert alert-success" role="alert">
                                <i class="bx bx-check me-2"></i>
                                <?php echo sanitizeOutput($success_message); ?>
                            </div>
                        <?php endif; ?>

                        <div class="row">
                            <!-- Account Settings Card -->
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <h5 class="card-header">Account Settings</h5>
                                    <div class="card-body">
                                        <form id="formAccountSettings" method="POST">
                                            <input type="hidden" name="action" value="update_profile">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                            
                                            <div class="row">
                                                <div class="mb-3 col-md-6">
                                                    <label for="name" class="form-label">Full Name</label>
                                                    <input class="form-control" type="text" id="name" name="name" value="<?php echo sanitizeOutput($current_student['name']); ?>" readonly />
                                                    <div class="form-text">Name cannot be changed. Contact administrator if needed.</div>
                                                </div>
                                                <div class="mb-3 col-md-6">
                                                    <label for="email" class="form-label">E-mail</label>
                                                    <input class="form-control" type="email" id="email" name="email" value="<?php echo sanitizeOutput($current_student['email']); ?>" readonly />
                                                    <div class="form-text">Email cannot be changed. Contact administrator if needed.</div>
                                                </div>
                                                <div class="mb-3 col-md-6">
                                                    <label for="phone" class="form-label">Phone Number</label>
                                                    <input type="text" class="form-control" id="phone" name="phone" value="<?php echo sanitizeOutput($current_student['phone']); ?>" />
                                                </div>
                                                <div class="mb-3 col-md-6">
                                                    <label for="created_at" class="form-label">Account Created</label>
                                                    <input class="form-control" type="text" id="created_at" value="<?php echo date('M d, Y', strtotime($current_student['created_at'])); ?>" readonly />
                                                </div>
                                            </div>
                                            <div class="mt-2">
                                                <button type="submit" class="btn btn-primary me-2">Save changes</button>
                                                <button type="reset" class="btn btn-outline-secondary">Cancel</button>
                                            </div>
                                        </form>
                                    </div>
                                    </div>
                                </div>

                            <!-- Profile Picture Card -->
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <h5 class="card-header">Profile Picture</h5>
                                    <div class="card-body">
                                        <div class="d-flex align-items-start align-items-sm-center gap-4">
                                            <img src="<?php echo getStudentProfilePicture($current_student['student_id'], $current_student['profile_picture'] ?? null); ?>" 
                                                 alt="user-avatar" class="d-block rounded" height="100" width="100" id="uploadedAvatar" />
                                            <div class="button-wrapper">
                                                <input type="file" id="profilePictureInput" class="account-file-input" hidden accept="image/png, image/jpeg, image/gif" />
                                                <button type="button" id="uploadProfilePictureBtn" class="btn btn-primary me-2 mb-4">
                                                    <i class="bx bx-upload me-1"></i>
                                                    Select Picture
                                                </button>
                                                <button type="button" id="resetProfilePicture" class="btn btn-outline-secondary account-image-reset mb-4">
                                                    <i class="bx bx-reset d-block d-sm-none"></i>
                                                    <span class="d-none d-sm-block">Reset</span>
                                                </button>
                                                <p class="text-muted mb-0">Allowed JPG, PNG or GIF. Max size of 2MB</p>
                                                
                                                <!-- Upload Progress -->
                                                <div id="uploadProgress" class="mt-2" style="display: none;">
                                                    <div class="progress">
                                                        <div id="uploadProgressBar" class="progress-bar" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                                                    </div>
                                                    <small class="text-muted">Uploading...</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                                            
                                            <div class="row">
                            <!-- Personal Information Card -->
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <h5 class="card-header">Personal Information</h5>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="mb-3 col-md-6">
                                                <label for="student_id" class="form-label">Student ID</label>
                                                <input class="form-control" type="text" id="student_id" value="<?php echo sanitizeOutput($current_student['student_id']); ?>" readonly />
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="username" class="form-label">Username</label>
                                                <input class="form-control" type="text" id="username" value="<?php echo sanitizeOutput($current_student['username']); ?>" readonly />
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="program" class="form-label">Program</label>
                                                <input class="form-control" type="text" id="program" value="<?php echo sanitizeOutput($current_student['program']); ?>" readonly />
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="shift" class="form-label">Shift</label>
                                                <input class="form-control" type="text" id="shift" value="<?php echo sanitizeOutput($current_student['shift']); ?>" readonly />
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="year_level" class="form-label">Year Level</label>
                                                <input class="form-control" type="text" id="year_level" value="<?php echo sanitizeOutput($current_student['year_level']); ?>" readonly />
                                            </div>
                                                <div class="mb-3 col-md-6">
                                                <label for="section" class="form-label">Section</label>
                                                <input class="form-control" type="text" id="section" value="<?php echo sanitizeOutput($current_student['section']); ?>" readonly />
                                                </div>
                                                <div class="mb-3 col-md-6">
                                                <label for="admission_year" class="form-label">Admission Year</label>
                                                <input class="form-control" type="text" id="admission_year" value="<?php echo sanitizeOutput($current_student['admission_year']); ?>" readonly />
                                                </div>
                                                <div class="mb-3 col-md-6">
                                                <label for="last_login" class="form-label">Last Login</label>
                                                <input class="form-control" type="text" id="last_login" value="<?php echo $current_student['last_login'] ? date('M d, Y H:i', strtotime($current_student['last_login'])) : 'Never'; ?>" readonly />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Password Change Card -->
                            <div class="col-md-6">
                                <div class="card">
                                    <h5 class="card-header">Change Password</h5>
                                    <div class="card-body">
                                        <!-- Step 1: Request Verification -->
                                        <div id="passwordResetStep1">
                                            <form id="passwordResetRequestForm">
                                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                
                                                <div class="mb-3">
                                                    <label for="resetEmail" class="form-label">Email Address</label>
                                                    <input class="form-control" type="email" id="resetEmail" name="email" value="<?php echo sanitizeOutput($current_student['email']); ?>" readonly />
                                                    <div class="form-text">We'll send a verification code to this email address.</div>
                                                </div>
                                                
                                                <div class="mt-2">
                                                    <button type="button" id="requestPasswordResetBtn" class="btn btn-primary me-2">
                                                        <i class="bx bx-envelope me-1"></i>
                                                        Send Verification Code
                                                    </button>
                                            </div>
                                        </form>
                                </div>

                                        <!-- Step 2: Verify and Reset -->
                                        <div id="passwordResetStep2" style="display: none;">
                                            <form id="passwordResetVerifyForm">
                                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                
                                                <div class="mb-3">
                                                    <label for="verificationCode" class="form-label">Verification Code</label>
                                                    <input class="form-control text-center" type="text" id="verificationCode" name="verification_code" 
                                                           placeholder="000000" maxlength="6" style="font-size: 1.5rem; letter-spacing: 0.5rem;" />
                                                    <div class="form-text">
                                                        Enter the 6-digit code sent to your email.
                                                        <span id="countdown" class="text-warning ms-2"></span>
                                                </div>
                                            </div>
                                                
                                                <div class="mb-3">
                                                    <label for="newPassword" class="form-label">New Password</label>
                                                    <input class="form-control" type="password" id="newPassword" name="new_password" required />
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="confirmPassword" class="form-label">Confirm New Password</label>
                                                    <input class="form-control" type="password" id="confirmPassword" name="confirm_password" required />
                                                </div>
                                                
                                            <div class="mt-2">
                                                    <button type="button" id="verifyPasswordResetBtn" class="btn btn-primary me-2">
                                                        <i class="bx bx-check me-1"></i>
                                                        Change Password
                                                    </button>
                                                    <button type="button" id="resendVerificationBtn" class="btn btn-outline-secondary">
                                                        <i class="bx bx-refresh me-1"></i>
                                                        Resend Code
                                                    </button>
                                            </div>
                                        </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- / Content -->

<?php
// Include footer
include 'includes/partials/footer.php';
?>

<!-- Profile Page Styles -->
<style>
/* Profile Picture Upload Styles */
.account-file-input {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}

/* Button hover effects */
.btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    transition: all 0.2s ease;
}

/* Progress bar styling */
#uploadProgress {
    margin-top: 10px;
}

.progress {
    height: 8px;
    border-radius: 4px;
    background-color: #e9ecef;
}

.progress-bar {
    background: linear-gradient(45deg, #696cff, #8592a3);
    border-radius: 4px;
    transition: width 0.3s ease;
}

/* Avatar styling */
#uploadedAvatar {
    transition: all 0.3s ease;
    border: 3px solid #fff;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

#uploadedAvatar:hover {
    transform: scale(1.05);
    box-shadow: 0 6px 20px rgba(0,0,0,0.2);
}

/* Upload area styling */
.button-wrapper {
    position: relative;
}

.button-wrapper .btn {
    margin-right: 8px;
    margin-bottom: 8px;
}

/* Alert styling */
.alert {
    border-radius: 8px;
    border: none;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.alert-success {
    background: linear-gradient(135deg, #d4edda, #c3e6cb);
    color: #155724;
}

.alert-danger {
    background: linear-gradient(135deg, #f8d7da, #f5c6cb);
    color: #721c24;
}

/* Loading state */
.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none !important;
}

/* Mobile responsive */
@media (max-width: 576px) {
    .button-wrapper {
        text-align: center;
    }
    
    .button-wrapper .btn {
        width: 100%;
        margin-right: 0;
        margin-bottom: 10px;
    }
    
    #uploadedAvatar {
        width: 80px;
        height: 80px;
    }
}

/* File input label styling */
label[for="profilePictureInput"] {
    cursor: pointer;
    position: relative;
    overflow: hidden;
}

label[for="profilePictureInput"]:hover {
    background-color: #5a5fcf !important;
    border-color: #5a5fcf !important;
}

/* Reset button styling */
#resetProfilePicture {
    border-color: #6c757d;
    color: #6c757d;
}

#resetProfilePicture:hover {
    background-color: #6c757d;
    border-color: #6c757d;
    color: white;
}

/* Preview state */
#uploadedAvatar[style*="opacity: 0.7"] {
    border: 3px dashed #696cff;
    background-color: rgba(105, 108, 255, 0.1);
}

/* Animation for success */
@keyframes successPulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

.alert-success {
    animation: successPulse 0.6s ease-in-out;
}

/* Password Reset Styles */
#verificationCode {
    font-family: 'Courier New', monospace;
    font-weight: bold;
}

#countdown {
    font-weight: bold;
}

/* Form validation styles */
.is-invalid {
    border-color: #dc3545;
}

.invalid-feedback {
    display: block;
    width: 100%;
    margin-top: 0.25rem;
    font-size: 0.875em;
    color: #dc3545;
}

/* Read-only input styling */
input[readonly] {
    background-color: #f8f9fa;
    border-color: #e9ecef;
    color: #6c757d;
    cursor: not-allowed;
}

/* Form text styling for read-only fields */
.form-text {
    font-size: 0.875rem;
    color: #6c757d;
    margin-top: 0.25rem;
}

/* Card hover effects */
.card {
    transition: box-shadow 0.3s ease;
}

.card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

/* Step indicator */
.step-indicator {
    display: flex;
    justify-content: center;
    margin-bottom: 2rem;
}

.step {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background-color: #e9ecef;
    color: #6c757d;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 10px;
    font-weight: bold;
}

.step.active {
    background-color: #696cff;
    color: white;
}

.step.completed {
    background-color: #28a745;
    color: white;
}
</style>