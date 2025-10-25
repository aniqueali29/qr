<?php
/**
 * Verify OTP Page
 * Verifies the OTP sent to user's email for password reset
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/config.php';
require_once 'includes/auth.php';

// Redirect if already logged in
if (isStudentLoggedIn()) {
    header('Location: ./dashboard.php');
    exit();
}

// Get email from query parameter
$email = $_GET['email'] ?? '';

if (empty($email)) {
    header('Location: forgot-password.php');
    exit();
}

$error_message = '';
$success_message = '';

// Handle OTP verification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $submitted_otp = sanitizeInput($_POST['otp'] ?? '');
        $csrf_token = $_POST['csrf_token'] ?? '';
        
        // Validate CSRF token
        if (!validateCSRFToken($csrf_token)) {
            $error_message = 'Invalid security token. Please try again.';
        } elseif (empty($submitted_otp)) {
            $error_message = 'Please enter the OTP.';
        } elseif (!preg_match('/^\d{6}$/', $submitted_otp)) {
            $error_message = 'OTP must be 6 digits.';
        } else {
            // Verify OTP from database
            $stmt = $pdo->prepare("
                SELECT token, expires_at 
                FROM password_resets 
                WHERE email = ? 
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$email]);
            $reset_record = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$reset_record) {
                $error_message = 'No OTP found for this email. Please request a new one.';
            } elseif (strtotime($reset_record['expires_at']) < time()) {
                $error_message = 'OTP has expired. Please request a new one.';
                // Delete expired OTP
                $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
                $stmt->execute([$email]);
            } elseif ($reset_record['token'] !== $submitted_otp) {
                $error_message = 'Invalid OTP. Please check and try again.';
            } else {
                // OTP is valid - redirect to reset password page
                header('Location: reset-password.php?email=' . urlencode($email) . '&token=' . urlencode($submitted_otp));
                exit();
            }
        }
    } catch (Exception $e) {
        error_log("OTP verification error: " . $e->getMessage());
        $error_message = 'An error occurred. Please try again later.';
    }
}

// Generate CSRF token
$csrf_token = generateCSRFToken();
?>

<!doctype html>
<html lang="en" class="layout-wide customizer-hide" data-assets-path="./assets/" data-template="vertical-menu-template-free">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>Verify OTP - QR Attendance System</title>
    <meta name="description" content="Verify your OTP for password reset" />
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="./assets/img/favicon/favicon.ico" />
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet" />
    
    <link rel="stylesheet" href="./assets/vendor/fonts/iconify-icons.css" />
    
    <!-- Core CSS -->
    <link rel="stylesheet" href="./assets/vendor/css/core.css" />
    <link rel="stylesheet" href="./assets/css/demo.css" />
    
    <!-- Vendors CSS -->
    <link rel="stylesheet" href="./assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    
    <!-- Page CSS -->
    <link rel="stylesheet" href="./assets/vendor/css/pages/page-auth.css" />
    
    <!-- Helpers -->
    <script src="./assets/vendor/js/helpers.js"></script>
    <script src="./assets/js/config.js"></script>
    
    <style>
        .otp-input {
            width: 50px;
            height: 50px;
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            margin: 0 5px;
            border: 2px solid #d9dee3;
            border-radius: 8px;
        }
        .otp-input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .otp-container {
            display: flex;
            justify-content: center;
            margin: 20px 0;
        }
        .countdown {
            font-size: 14px;
            color: #666;
            margin-top: 10px;
        }
    </style>
</head>

<body>
    <!-- Content -->
    <div class="container-xxl">
        <div class="authentication-wrapper authentication-basic container-p-y">
            <div class="authentication-inner">
                <!-- Verify OTP -->
                <div class="card px-sm-6 px-0">
                    <div class="card-body">
                        <!-- Logo -->
                        <div class="app-brand justify-content-center mb-6">
                            <a href="index.php" class="app-brand-link gap-2">
                                <span class="app-brand-logo demo">
                                    <span class="text-primary">
                                        <svg width="25" viewBox="0 0 25 42" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
                                            <defs>
                                                <path d="M13.7918663,0.358365126 L3.39788168,7.44174259 C0.566865006,9.69408886 -0.379795268,12.4788597 0.557900856,15.7960551 C0.68998853,16.2305145 1.09562888,17.7872135 3.12357076,19.2293357 C3.8146334,19.7207684 5.32369333,20.3834223 7.65075054,21.2172976 L7.59773219,21.2525164 L2.63468769,24.5493413 C0.445452254,26.3002124 0.0884951797,28.5083815 1.56381646,31.1738486 C2.83770406,32.8170431 5.20850219,33.2640127 7.09180128,32.5391577 C8.347334,32.0559211 11.4559176,30.0011079 16.4175519,26.3747182 C18.0338572,24.4997857 18.6973423,22.4544883 18.4080071,20.2388261 C17.963753,17.5346866 16.1776345,15.5799961 13.0496516,14.3747546 L10.9194936,13.4715819 L18.6192054,7.984237 L13.7918663,0.358365126 Z" id="path-1"></path>
                                                <path d="M5.47320593,6.00457225 C4.05321814,8.216144 4.36334763,10.0722806 6.40359441,11.5729822 C8.61520715,12.571656 10.0999176,13.2171421 10.8577257,13.5094407 L15.5088241,14.433041 L18.6192054,7.984237 C15.5364148,3.11535317 13.9273018,0.573395879 13.7918663,0.358365126 C13.5790555,0.511491653 10.8061687,2.3935607 5.47320593,6.00457225 Z" id="path-3"></path>
                                                <path d="M7.50063644,21.2294429 L12.3234468,23.3159332 C14.1688022,24.7579751 14.397098,26.4880487 13.008334,28.506154 C11.6195701,30.5242593 10.3099883,31.790241 9.07958868,32.3040991 C5.78142938,33.4346997 4.13234973,34 4.13234973,34 C4.13234973,34 2.75489982,33.0538207 2.37032616e-14,31.1614621 C-0.55822714,27.8186216 -0.55822714,26.0572515 -4.05231404e-15,25.8773518 C0.83734071,25.6075023 2.77988457,22.8248993 3.3049379,22.52991 C3.65497346,22.3332504 5.05353963,21.8997614 7.50063644,21.2294429 Z" id="path-4"></path>
                                                <path d="M20.6,7.13333333 L25.6,13.8 C26.2627417,14.6836556 26.0836556,15.9372583 25.2,16.6 C24.8538077,16.8596443 24.4327404,17 24,17 L14,17 C12.8954305,17 12,16.1045695 12,15 C12,14.5672596 12.1403557,14.1461923 12.4,13.8 L17.4,7.13333333 C18.0627417,6.24967773 19.3163444,6.07059163 20.2,6.73333333 C20.3516113,6.84704183 20.4862915,6.981722 20.6,7.13333333 Z" id="path-5"></path>
                                            </defs>
                                            <g id="g-app-brand" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                                <g id="Brand-Logo" transform="translate(-27.000000, -15.000000)">
                                                    <g id="Icon" transform="translate(27.000000, 15.000000)">
                                                        <g id="Mask" transform="translate(0.000000, 8.000000)">
                                                            <mask id="mask-2" fill="white">
                                                                <use xlink:href="#path-1"></use>
                                                            </mask>
                                                            <use fill="currentColor" xlink:href="#path-1"></use>
                                                            <g id="Path-3" mask="url(#mask-2)">
                                                                <use fill="currentColor" xlink:href="#path-3"></use>
                                                                <use fill-opacity="0.2" fill="#FFFFFF" xlink:href="#path-3"></use>
                                                            </g>
                                                            <g id="Path-4" mask="url(#mask-2)">
                                                                <use fill="currentColor" xlink:href="#path-4"></use>
                                                                <use fill-opacity="0.2" fill="#FFFFFF" xlink:href="#path-4"></use>
                                                            </g>
                                                        </g>
                                                        <g id="Triangle" transform="translate(19.000000, 11.000000) rotate(-300.000000) translate(-19.000000, -11.000000)">
                                                            <use fill="currentColor" xlink:href="#path-5"></use>
                                                            <use fill-opacity="0.2" fill="#FFFFFF" xlink:href="#path-5"></use>
                                                        </g>
                                                    </g>
                                                </g>
                                            </g>
                                        </svg>
                                    </span>
                                </span>
                                <span class="app-brand-text demo text-heading fw-bold">QR Attendance</span>
                            </a>
                        </div>
                        <!-- /Logo -->
                        
                        <h4 class="mb-1">Verify OTP 🔐</h4>
                        <p class="mb-4">Enter the 6-digit code sent to <strong><?php echo htmlspecialchars($email); ?></strong></p>
                        
                        <!-- Error/Success Messages -->
                        <?php if (!empty($error_message)): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="bx bx-error-circle me-2"></i>
                                <?php echo htmlspecialchars($error_message); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($success_message)): ?>
                            <div class="alert alert-success" role="alert">
                                <i class="bx bx-check-circle me-2"></i>
                                <?php echo htmlspecialchars($success_message); ?>
                            </div>
                        <?php endif; ?>
                        
                        <form id="otpForm" class="mb-6" method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <input type="hidden" name="otp" id="otpValue">
                            
                            <div class="mb-4">
                                <label class="form-label">Enter OTP</label>
                                <div class="otp-container">
                                    <input type="text" class="form-control otp-input" maxlength="1" data-index="0" autofocus>
                                    <input type="text" class="form-control otp-input" maxlength="1" data-index="1">
                                    <input type="text" class="form-control otp-input" maxlength="1" data-index="2">
                                    <input type="text" class="form-control otp-input" maxlength="1" data-index="3">
                                    <input type="text" class="form-control otp-input" maxlength="1" data-index="4">
                                    <input type="text" class="form-control otp-input" maxlength="1" data-index="5">
                                </div>
                                <div class="text-center countdown" id="countdown">OTP expires in: <span id="timer">10:00</span></div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary d-grid w-100 mb-4">Verify OTP</button>
                        </form>
                        
                        <div class="text-center">
                            <a href="forgot-password.php" class="d-flex align-items-center justify-content-center">
                                <i class="bx bx-chevron-left scaleX-n1-rtl me-1"></i>
                                Request new OTP
                            </a>
                        </div>
                    </div>
                </div>
                <!-- /Verify OTP -->
            </div>
        </div>
    </div>

    <!-- Core JS -->
    <script src="./assets/vendor/libs/jquery/jquery.js"></script>
    <script src="./assets/vendor/js/bootstrap.js"></script>
    <script src="./assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="./assets/vendor/js/menu.js"></script>
    
    <!-- Main JS -->
    <script src="./assets/js/main.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('.otp-input');
            const otpValue = document.getElementById('otpValue');
            const form = document.getElementById('otpForm');
            
            // Auto-focus and auto-submit logic
            inputs.forEach((input, index) => {
                input.addEventListener('input', function(e) {
                    const value = e.target.value;
                    
                    // Only allow digits
                    if (!/^\d$/.test(value)) {
                        e.target.value = '';
                        return;
                    }
                    
                    // Move to next input
                    if (value && index < inputs.length - 1) {
                        inputs[index + 1].focus();
                    }
                    
                    // Check if all filled
                    const otp = Array.from(inputs).map(i => i.value).join('');
                    if (otp.length === 6) {
                        otpValue.value = otp;
                        form.submit();
                    }
                });
                
                // Handle backspace
                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Backspace' && !e.target.value && index > 0) {
                        inputs[index - 1].focus();
                    }
                });
                
                // Handle paste
                input.addEventListener('paste', function(e) {
                    e.preventDefault();
                    const pastedData = e.clipboardData.getData('text').trim();
                    
                    if (/^\d{6}$/.test(pastedData)) {
                        pastedData.split('').forEach((char, i) => {
                            if (inputs[i]) {
                                inputs[i].value = char;
                            }
                        });
                        otpValue.value = pastedData;
                        form.submit();
                    }
                });
            });
            
            // Countdown timer (10 minutes = 600 seconds)
            let timeLeft = 600;
            const timerElement = document.getElementById('timer');
            
            const countdown = setInterval(function() {
                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;
                timerElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
                
                if (timeLeft <= 0) {
                    clearInterval(countdown);
                    timerElement.textContent = 'Expired';
                    inputs.forEach(input => input.disabled = true);
                    alert('OTP has expired. Please request a new one.');
                    window.location.href = 'forgot-password.php';
                }
                
                timeLeft--;
            }, 1000);
        });
    </script>
</body>
</html>
