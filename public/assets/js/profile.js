/**
 * Profile Page JavaScript
 * Handles profile picture upload, password reset, and form interactions
 */

document.addEventListener('DOMContentLoaded', function() {
    initializeProfilePage();
});

function initializeProfilePage() {
    // Initialize profile picture upload
    initializeProfilePictureUpload();
    
    // Initialize password reset flow
    initializePasswordReset();
    
    // Initialize form validation
    initializeFormValidation();
    
    // Initialize tooltips
    initializeTooltips();
}

/**
 * Profile Picture Upload
 */
function initializeProfilePictureUpload() {
    const fileInput = document.getElementById('profilePictureInput');
    const uploadButton = document.getElementById('uploadProfilePictureBtn');
    const previewImage = document.getElementById('uploadedAvatar');
    const progressContainer = document.getElementById('uploadProgress');
    const progressBar = document.getElementById('uploadProgressBar');
    
    if (!fileInput || !uploadButton) return;
    
    
    // Upload button click handler - trigger file input
    uploadButton.addEventListener('click', function() {
        fileInput.click();
    });
    
    // File input change handler - handle upload after selection
    fileInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            // Validate file
            const validation = validateImageFile(file);
            if (!validation.valid) {
                showAlert('error', validation.message);
                fileInput.value = '';
                return;
            }
            
            // Show preview
            showImagePreview(file, previewImage);
            
            // Auto-upload the file
            uploadProfilePicture(file);
        }
    });
    
    // Reset button handler
    const resetButton = document.getElementById('resetProfilePicture');
    if (resetButton) {
        resetButton.addEventListener('click', function() {
            fileInput.value = '';
            uploadButton.disabled = false;
            uploadButton.textContent = 'Select Picture';
            hideImagePreview(previewImage);
            hideProgress();
        });
    }
}

function validateImageFile(file) {
    const maxSize = 2 * 1024 * 1024; // 2MB
    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    
    if (file.size > maxSize) {
        return { valid: false, message: 'File size must be less than 2MB' };
    }
    
    if (!allowedTypes.includes(file.type)) {
        return { valid: false, message: 'Only JPG, PNG, and GIF images are allowed' };
    }
    
    return { valid: true };
}

function showImagePreview(file, previewElement) {
    const reader = new FileReader();
    reader.onload = function(e) {
        previewElement.src = e.target.result;
        previewElement.style.opacity = '0.7';
    };
    reader.readAsDataURL(file);
}

function hideImagePreview(previewElement) {
    previewElement.style.opacity = '1';
}

function uploadProfilePicture(file) {
    const formData = new FormData();
    formData.append('action', 'upload_profile_picture');
    formData.append('profile_picture', file);
    formData.append('csrf_token', getCSRFToken());
    
    const uploadButton = document.getElementById('uploadProfilePictureBtn');
    const progressContainer = document.getElementById('uploadProgress');
    const progressBar = document.getElementById('uploadProgressBar');
    
    // Show progress
    showProgress();
    uploadButton.disabled = true;
    uploadButton.textContent = 'Uploading...';
    
    // Create XMLHttpRequest for progress tracking
    const xhr = new XMLHttpRequest();
    
    xhr.upload.addEventListener('progress', function(e) {
        if (e.lengthComputable) {
            const percentComplete = (e.loaded / e.total) * 100;
            updateProgress(percentComplete);
        }
    });
    
    xhr.addEventListener('load', function() {
        hideProgress();
        uploadButton.disabled = false;
        uploadButton.textContent = 'Select Picture';
        
        try {
            const response = JSON.parse(xhr.responseText);
            if (response.success) {
                showAlert('success', response.message);
                // Update the image source
                const previewImage = document.getElementById('uploadedAvatar');
                if (previewImage) {
                    previewImage.src = response.image_url;
                    previewImage.style.opacity = '1';
                }
                // Clear file input
                document.getElementById('profilePictureInput').value = '';
            } else {
                showAlert('error', response.message);
            }
        } catch (e) {
            showAlert('error', 'Upload failed. Please try again.');
        }
    });
    
    xhr.addEventListener('error', function() {
        hideProgress();
        uploadButton.disabled = false;
        uploadButton.textContent = 'Select Picture';
        showAlert('error', 'Upload failed. Please check your connection and try again.');
    });
    
    xhr.open('POST', 'api/profile_api.php');
    xhr.send(formData);
}

function showProgress() {
    const progressContainer = document.getElementById('uploadProgress');
    if (progressContainer) {
        progressContainer.style.display = 'block';
    }
}

function hideProgress() {
    const progressContainer = document.getElementById('uploadProgress');
    if (progressContainer) {
        progressContainer.style.display = 'none';
    }
}

function updateProgress(percent) {
    const progressBar = document.getElementById('uploadProgressBar');
    if (progressBar) {
        progressBar.style.width = percent + '%';
        progressBar.setAttribute('aria-valuenow', percent);
    }
}

/**
 * Password Reset Flow
 */
function initializePasswordReset() {
    const requestForm = document.getElementById('passwordResetRequestForm');
    const verifyForm = document.getElementById('passwordResetVerifyForm');
    const requestButton = document.getElementById('requestPasswordResetBtn');
    const verifyButton = document.getElementById('verifyPasswordResetBtn');
    const resendButton = document.getElementById('resendVerificationBtn');
    
    if (!requestForm || !verifyForm) return;
    
    // Request form submission
    if (requestButton) {
        requestButton.addEventListener('click', function() {
            requestPasswordReset();
        });
    }
    
    // Verify form submission
    if (verifyButton) {
        verifyButton.addEventListener('click', function() {
            verifyPasswordReset();
        });
    }
    
    // Resend code button
    if (resendButton) {
        resendButton.addEventListener('click', function() {
            resendVerificationCode();
        });
    }
    
    // Verification code input formatting
    const codeInput = document.getElementById('verificationCode');
    if (codeInput) {
        codeInput.addEventListener('input', function() {
            // Format as 6-digit code
            let value = this.value.replace(/\D/g, '');
            if (value.length > 6) {
                value = value.substring(0, 6);
            }
            this.value = value;
        });
    }
}

function requestPasswordReset() {
    const email = document.getElementById('resetEmail').value.trim();
    
    // Validate email
    if (!email) {
        showAlert('error', 'Email is required');
        return;
    }
    
    if (!isValidEmail(email)) {
        showAlert('error', 'Please enter a valid email address');
        return;
    }
    
    if (email.length > 255) {
        showAlert('error', 'Email address is too long');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'request_password_reset');
    formData.append('email', email);
    formData.append('csrf_token', getCSRFToken());
    
    const button = document.getElementById('requestPasswordResetBtn');
    button.disabled = true;
    button.textContent = 'Sending...';
    
    
    fetch('api/profile_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        return response.json();
    })
    .then(data => {
        button.disabled = false;
        button.textContent = 'Send Verification Code';
        
        if (data.success) {
            showAlert('success', data.message);
            showPasswordResetStep(2);
            startCountdown(data.expires_in);
        } else {
            showAlert('error', data.message);
        }
    })
    .catch(error => {
        button.disabled = false;
        button.textContent = 'Send Verification Code';
        
        showAlert('error', 'Request failed: ' + error.message);
    });
}

function verifyPasswordReset() {
    const code = document.getElementById('verificationCode').value.trim();
    const newPassword = document.getElementById('newPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    
    // Validate verification code
    if (!code) {
        showAlert('error', 'Verification code is required');
        return;
    }
    
    if (!/^[0-9]{6}$/.test(code)) {
        showAlert('error', 'Verification code must be 6 digits');
        return;
    }
    
    // Validate new password
    if (!newPassword) {
        showAlert('error', 'New password is required');
        return;
    }
    
    if (newPassword.length < 6) {
        showAlert('error', 'Password must be at least 6 characters long');
        return;
    }
    
    if (newPassword.length > 128) {
        showAlert('error', 'Password is too long');
        return;
    }
    
    // Check password strength
    if (!/(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/.test(newPassword)) {
        showAlert('error', 'Password must contain at least one uppercase letter, one lowercase letter, and one number');
        return;
    }
    
    // Validate confirm password
    if (!confirmPassword) {
        showAlert('error', 'Password confirmation is required');
        return;
    }
    
    if (newPassword !== confirmPassword) {
        showAlert('error', 'Passwords do not match');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'verify_code_and_reset_password');
    formData.append('verification_code', code);
    formData.append('new_password', newPassword);
    formData.append('confirm_password', confirmPassword);
    formData.append('csrf_token', getCSRFToken());
    
    const button = document.getElementById('verifyPasswordResetBtn');
    button.disabled = true;
    button.textContent = 'Updating...';
    
    fetch('api/profile_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        button.disabled = false;
        button.textContent = 'Change Password';
        
        if (data.success) {
            showAlert('success', data.message);
            showPasswordResetStep(1);
            clearPasswordResetForm();
        } else {
            showAlert('error', data.message);
        }
    })
    .catch(error => {
        button.disabled = false;
        button.textContent = 'Change Password';
        showAlert('error', 'Password reset failed. Please try again.');
    });
}

function resendVerificationCode() {
    const email = document.getElementById('resetEmail').value.trim();
    
    if (!email) {
        showAlert('error', 'Email is required');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'resend_verification_code');
    formData.append('email', email);
    formData.append('purpose', 'password_reset');
    formData.append('csrf_token', getCSRFToken());
    
    const button = document.getElementById('resendVerificationBtn');
    button.disabled = true;
    button.textContent = 'Sending...';
    
    fetch('api/profile_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        button.disabled = false;
        button.textContent = 'Resend Code';
        
        if (data.success) {
            showAlert('success', data.message);
            startCountdown(data.expires_in);
        } else {
            showAlert('error', data.message);
        }
    })
    .catch(error => {
        button.disabled = false;
        button.textContent = 'Resend Code';
        showAlert('error', 'Failed to resend code. Please try again.');
    });
}

function showPasswordResetStep(step) {
    const step1 = document.getElementById('passwordResetStep1');
    const step2 = document.getElementById('passwordResetStep2');
    
    if (step === 1) {
        if (step1) step1.style.display = 'block';
        if (step2) step2.style.display = 'none';
    } else if (step === 2) {
        if (step1) step1.style.display = 'none';
        if (step2) step2.style.display = 'block';
    }
}

function clearPasswordResetForm() {
    document.getElementById('verificationCode').value = '';
    document.getElementById('newPassword').value = '';
    document.getElementById('confirmPassword').value = '';
}

/**
 * Form Validation
 */
function initializeFormValidation() {
    // Email validation
    const emailInputs = document.querySelectorAll('input[type="email"], input[name="email"]');
    emailInputs.forEach(input => {
        input.addEventListener('blur', function() {
            validateEmail(this);
        });
    });
    
    // Password validation
    const passwordInputs = document.querySelectorAll('input[type="password"]');
    passwordInputs.forEach(input => {
        input.addEventListener('input', function() {
            if (this.name === 'new_password' || this.name === 'confirm_password') {
                validatePassword(this);
            }
        });
    });
}

function validateEmail(input) {
    const email = input.value.trim();
    const isValid = isValidEmail(email);
    
    if (email && !isValid) {
        showFieldError(input, 'Please enter a valid email address');
    } else {
        clearFieldError(input);
    }
    
    return isValid;
}

function validatePassword(input) {
    const password = input.value;
    const confirmInput = document.getElementById('confirmPassword');
    
    if (input.name === 'new_password') {
        if (password.length > 0 && password.length < 6) {
            showFieldError(input, 'Password must be at least 6 characters long');
        } else {
            clearFieldError(input);
        }
    }
    
    if (input.name === 'confirm_password' && confirmInput) {
        const newPassword = document.getElementById('newPassword').value;
        if (password && password !== newPassword) {
            showFieldError(input, 'Passwords do not match');
        } else {
            clearFieldError(input);
        }
    }
}

function showFieldError(input, message) {
    clearFieldError(input);
    input.classList.add('is-invalid');
    
    const errorDiv = document.createElement('div');
    errorDiv.className = 'invalid-feedback';
    errorDiv.textContent = message;
    input.parentNode.appendChild(errorDiv);
}

function clearFieldError(input) {
    input.classList.remove('is-invalid');
    const errorDiv = input.parentNode.querySelector('.invalid-feedback');
    if (errorDiv) {
        errorDiv.remove();
    }
}

/**
 * Utility Functions
 */
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

function getCSRFToken() {
    const tokenInput = document.querySelector('input[name="csrf_token"]');
    return tokenInput ? tokenInput.value : '';
}

function showAlert(type, message) {
    // Remove existing alerts
    const existingAlerts = document.querySelectorAll('.alert');
    existingAlerts.forEach(alert => alert.remove());
    
    // Create new alert
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type === 'error' ? 'danger' : 'success'} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        <i class="bx bx-${type === 'error' ? 'error' : 'check'} me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    // Insert at the top of the content
    const content = document.querySelector('.container-xxl.flex-grow-1.container-p-y');
    if (content) {
        content.insertBefore(alertDiv, content.firstChild);
    }
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}

function startCountdown(seconds) {
    const countdownElement = document.getElementById('countdown');
    if (!countdownElement) return;
    
    let timeLeft = seconds;
    
    const timer = setInterval(() => {
        const minutes = Math.floor(timeLeft / 60);
        const secs = timeLeft % 60;
        
        countdownElement.textContent = `${minutes}:${secs.toString().padStart(2, '0')}`;
        
        if (timeLeft <= 0) {
            clearInterval(timer);
            countdownElement.textContent = 'Expired';
            countdownElement.classList.add('text-danger');
        }
        
        timeLeft--;
    }, 1000);
}

function initializeTooltips() {
    // Initialize Bootstrap tooltips if available
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
}
