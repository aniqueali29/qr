<?php
/**
 * Admin Settings Page
 * System configuration and settings management
 */

require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';

// Require admin authentication
requireAdminAuth();

// Check if user has access (unless superadmin)
if (!isSuperAdmin() && !staffHasAccess('settings.php')) {
    header('Location: index.php?error=access_denied');
    exit();
}

$pageTitle = "System Settings";
$currentPage = "settings";
$pageCSS = ['css/settings.css'];
$pageJS = ['js/settings.js'];

// Load general settings for branding
$generalSettings = [];
try {
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('sidebar_logo', 'project_name', 'project_short_name', 'project_tagline')");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $generalSettings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    error_log("General settings load error: " . $e->getMessage());
}

include 'partials/header.php';
include 'partials/sidebar.php';
include 'partials/navbar.php';
?>

<!-- Content wrapper -->
<div class="content-wrapper">
    <!-- Content -->
    <div class="container-xxl flex-grow-1 container-p-y">
        <!-- Header -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <div class="row align-items-center">
                            <div class="col-12 col-md-6">
                                <h4 class="card-title mb-0">
                                    <i class="bx bx-cog me-2"></i>System Settings
                                </h4>
                                <p class="card-subtitle mb-0">Configure system parameters and timing settings</p>
                            </div>
                            <div class="col-12 col-md-6 mt-2 mt-md-0">
                                <div class="d-flex flex-wrap align-items-center gap-2 justify-content-md-end">
                                    <div class="settings-status">
                                        <span class="badge bg-label-info" id="settingsStatus">
                                            <i class="bx bx-circle me-1"></i> <span class="status-text">Loading...</span>
                                        </span>
                                    </div>
                                    <button class="btn btn-primary" onclick="saveAllSettings()">
                                        <i class="bx bx-save me-1"></i>
                                        <span class="btn-text">Save All</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="row mt-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?php echo !isSuperAdmin() ? 'active' : ''; ?>" id="timings-tab" data-bs-toggle="tab" data-bs-target="#timings" type="button" role="tab">
                                    <i class="bx bx-time me-1"></i>Shift Timings
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="system-tab" data-bs-toggle="tab" data-bs-target="#system" type="button" role="tab">
                                    <i class="bx bx-slider me-1"></i>System Config
                                </button>
                            </li>
                            <?php if (isSuperAdmin()): ?>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="modules-tab" data-bs-toggle="tab" data-bs-target="#modules" type="button" role="tab">
                                    <i class="bx bx-grid me-1"></i>Modules
                                </button>
                            </li>
                            <?php endif; ?>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="staff-tab" data-bs-toggle="tab" data-bs-target="#staff" type="button" role="tab">
                                    <i class="bx bx-group me-1"></i>Staff
                                </button>
                            </li>
                            <?php if (isSuperAdmin()): ?>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="advanced-tab" data-bs-toggle="tab" data-bs-target="#advanced" type="button" role="tab">
                                    <i class="bx bx-cog me-1"></i>Advanced
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab">
                                    <i class="bx bx-palette me-1"></i>General
                                </button>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab Content -->
        <div class="tab-content" id="settingsTabContent">
            <!-- Shift Timings Tab -->
            <div class="tab-pane fade <?php echo !isSuperAdmin() ? 'show active' : ''; ?>" id="timings" role="tabpanel">
                <div class="row">
                    <!-- Morning Shift Settings -->
                    <div class="col-lg-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bx bx-sun me-2"></i>Morning Shift Settings
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                     <div class="col-12">
                                         <label for="morning_checkin_start" class="form-label">Check-in Start Time</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control time-input" id="morning_checkin_start" 
                                                   placeholder="09:00" pattern="[0-9]{1,2}:[0-9]{2}" maxlength="5" required autocomplete="off">
                                            <select class="form-select time-period" id="morning_checkin_start_period">
                                                <option value="AM">AM</option>
                                                <option value="PM">PM</option>
                                            </select>
                                        </div>
                                        <div class="form-text">When students can start checking in (12-hour format)</div>
                                     </div>
                                     <div class="col-12">
                                         <label for="morning_checkin_end" class="form-label">Check-in End Time</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control time-input" id="morning_checkin_end" 
                                                   placeholder="11:00" pattern="[0-9]{1,2}:[0-9]{2}" maxlength="5" required>
                                            <select class="form-select time-period" id="morning_checkin_end_period">
                                                <option value="AM">AM</option>
                                                <option value="PM">PM</option>
                                            </select>
                                        </div>
                                        <div class="form-text">Last time students can check in (12-hour format)</div>
                                     </div>
                                     <div class="col-12">
                                         <label for="morning_checkout_start" class="form-label">Check-out Start Time</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control time-input" id="morning_checkout_start" 
                                                   placeholder="12:00" pattern="[0-9]{1,2}:[0-9]{2}" maxlength="5">
                                            <select class="form-select time-period" id="morning_checkout_start_period">
                                                <option value="AM">AM</option>
                                                <option value="PM" selected>PM</option>
                                            </select>
                                        </div>
                                        <div class="form-text">When students can start checking out (optional - defaults to check-in end)</div>
                                     </div>
                                     <div class="col-12">
                                         <label for="morning_checkout_end" class="form-label">Check-out End Time</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control time-input" id="morning_checkout_end" 
                                                   placeholder="01:40" pattern="[0-9]{1,2}:[0-9]{2}" maxlength="5">
                                            <select class="form-select time-period" id="morning_checkout_end_period">
                                                <option value="AM">AM</option>
                                                <option value="PM" selected>PM</option>
                                            </select>
                                        </div>
                                        <div class="form-text">Last time students can check out (optional - defaults to class end)</div>
                                     </div>
                                     <div class="col-12">
                                         <label for="morning_class_end" class="form-label">Class End Time</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control time-input" id="morning_class_end" 
                                                   placeholder="01:40" pattern="[0-9]{1,2}:[0-9]{2}" maxlength="5" required>
                                            <select class="form-select time-period" id="morning_class_end_period">
                                                <option value="AM">AM</option>
                                                <option value="PM" selected>PM</option>
                                            </select>
                                        </div>
                                        <div class="form-text">When the class session ends (12-hour format)</div>
                                     </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Evening Shift Settings -->
                    <div class="col-lg-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bx bx-moon me-2"></i>Evening Shift Settings
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                     <div class="col-12">
                                         <label for="evening_checkin_start" class="form-label">Check-in Start Time</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control time-input" id="evening_checkin_start" 
                                                   placeholder="03:00" pattern="[0-9]{1,2}:[0-9]{2}" maxlength="5" required>
                                            <select class="form-select time-period" id="evening_checkin_start_period">
                                                <option value="AM">AM</option>
                                                <option value="PM" selected>PM</option>
                                            </select>
                                        </div>
                                        <div class="form-text">When students can start checking in (12-hour format)</div>
                                     </div>
                                     <div class="col-12">
                                         <label for="evening_checkin_end" class="form-label">Check-in End Time</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control time-input" id="evening_checkin_end" 
                                                   placeholder="06:00" pattern="[0-9]{1,2}:[0-9]{2}" maxlength="5" required>
                                            <select class="form-select time-period" id="evening_checkin_end_period">
                                                <option value="AM">AM</option>
                                                <option value="PM" selected>PM</option>
                                            </select>
                                        </div>
                                        <div class="form-text">Last time students can check in (12-hour format)</div>
                                     </div>
                                     <div class="col-12">
                                         <label for="evening_checkout_start" class="form-label">Check-out Start Time</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control time-input" id="evening_checkout_start" 
                                                   placeholder="03:00" pattern="[0-9]{1,2}:[0-9]{2}" maxlength="5">
                                            <select class="form-select time-period" id="evening_checkout_start_period">
                                                <option value="AM">AM</option>
                                                <option value="PM" selected>PM</option>
                                            </select>
                                        </div>
                                        <div class="form-text">When students can start checking out (optional - defaults to check-in start)</div>
                                     </div>
                                     <div class="col-12">
                                         <label for="evening_checkout_end" class="form-label">Check-out End Time</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control time-input" id="evening_checkout_end" 
                                                   placeholder="06:00" pattern="[0-9]{1,2}:[0-9]{2}" maxlength="5">
                                            <select class="form-select time-period" id="evening_checkout_end_period">
                                                <option value="AM">AM</option>
                                                <option value="PM" selected>PM</option>
                                            </select>
                                        </div>
                                        <div class="form-text">Last time students can check out (optional - defaults to class end)</div>
                                     </div>
                                     <div class="col-12">
                                         <label for="evening_class_end" class="form-label">Class End Time</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control time-input" id="evening_class_end" 
                                                   placeholder="06:00" pattern="[0-9]{1,2}:[0-9]{2}" maxlength="5" required>
                                            <select class="form-select time-period" id="evening_class_end_period">
                                                <option value="AM">AM</option>
                                                <option value="PM" selected>PM</option>
                                            </select>
                                        </div>
                                        <div class="form-text">When the class session ends (12-hour format)</div>
                                     </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Timing Validation -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bx bx-check-circle me-2"></i>Timing Validation
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex flex-wrap gap-2 mb-3">
                                    <button class="btn btn-outline-primary" onclick="validateTimings()">
                                        <i class="bx bx-check me-1"></i>
                                        <span class="btn-text">Validate</span>
                                    </button>
                                    <button class="btn btn-outline-info" onclick="testConfiguration()">
                                        <i class="bx bx-play me-1"></i>
                                        <span class="btn-text">Test</span>
                                    </button>
                                </div>
                                <div id="validationResults" class="alert alert-info d-none">
                                    <i class="bx bx-info-circle me-1"></i>
                                    <span class="validation-message">Click "Validate Configuration" to check your settings</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- System Configuration Tab -->
            <div class="tab-pane fade" id="system" role="tabpanel">
                <div class="row">
                    <!-- General Settings -->
                    <div class="col-lg-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bx bx-cog me-2"></i>General Settings
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    
                                    <div class="col-12">
                                        <label for="timezone" class="form-label">Timezone</label>
                                        <select class="form-select" id="timezone">
                                            <option value="Asia/Karachi">Asia/Karachi</option>
                                            <option value="UTC">UTC</option>
                                            <option value="America/New_York">America/New_York</option>
                                            <option value="Europe/London">Europe/London</option>
                                        </select>
                                        <div class="form-text">System timezone</div>
                                    </div>
                                    
                                    <div class="col-12">
                                        <label for="max_program_years" class="form-label">Program Duration</label>
                                        <select class="form-select" id="max_program_years">
                                            <option value="3">3</option>
                                            <option value="4" selected>4</option>
                                            <option value="5">5</option>
                                            <option value="6">6</option>
                                        </select>
                                        <div class="form-text">Maximum duration before graduation</div>
                                    </div>


                                    <div class="col-12">
                                        <label for="semesters_per_year" class="form-label">Semesters Per Year</label>
                                        <select class="form-select" id="semesters_per_year">
                                            <option value="1">1</option>
                                            <option value="2" selected>2</option>
                                            <option value="3">3</option>
                                            <option value="4">4</option>
                                        </select>
                                        <div class="form-text">Number of semesters in the academic cycle</div>
                                    </div>

                                    <div class="col-12">
                                        <label for="semester_names" class="form-label">Semester Names (JSON)</label>
                                        <textarea class="form-control" id="semester_names" rows="2">["Semester 1","Semester 2"]</textarea>
                                        <div class="form-text">JSON array, e.g., ["Fall","Spring"]</div>
                                    </div>

                                    <div class="col-12">
                                        <label for="semester_start_months" class="form-label">Semester Start Months (JSON)</label>
                                        <input type="text" class="form-control" id="semester_start_months" value="[9,2]">
                                        <div class="form-text">JSON array of months (1-12) corresponding to semester starts</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Auto-Absent Settings -->
                    <div class="col-lg-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bx bx-user-x me-2"></i>Auto-Absent Settings
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label for="auto_morning_checkin_end" class="form-label">Morning Check-in Deadline</label>
                                        <input type="text" class="form-control" id="auto_morning_checkin_end" placeholder="11:00:00">
                                        <div class="form-text">Time to mark morning shift absent if no check-in (HH:MM:SS format)</div>
                                    </div>
                                    <div class="col-12">
                                        <label for="auto_evening_checkin_end" class="form-label">Evening Check-in Deadline</label>
                                        <input type="text" class="form-control" id="auto_evening_checkin_end" placeholder="17:00:00">
                                        <div class="form-text">Time to mark evening shift absent if no check-in (HH:MM:SS format)</div>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="enable_auto_absent" checked>
                                            <label class="form-check-label" for="enable_auto_absent">
                                                Enable Auto-Absent Feature
                                            </label>
                                        </div>
                                        <div class="form-text">
                                            Automatically mark students as absent at specified times (runs via cron at 9:00 PM daily)
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="alert alert-warning">
                                            <i class="bx bx-info-circle me-2"></i>
                                            <strong>Note:</strong> Ensure the Windows Task Scheduler is set up using <code>setup_cron.bat</code> for this feature to work.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Student Promotion -->
                <div class="row">
                    <div class="col-12">
                        <div class="card border-primary">
                            <div class="card-header bg-label-primary">
                                <h5 class="card-title mb-0">
                                    <i class="bx bx-trending-up me-2"></i>Student Promotion Management
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info mb-3 mt-3">
                                    <i class="bx bx-info-circle me-2"></i>
                                    <strong>Semester-based Promotion:</strong> The system uses semester-only progression. Use the tool below to advance students and graduate final-semester students.
                                </div>
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <h6 class="mb-1">Bulk Student Promotion</h6>
                                        <p class="text-muted mb-0 small">Promote students to the next semester or mark final-semester students as graduated</p>
                                    </div>
                                    <a href="promote_students.php" class="btn btn-primary">
                                        <i class="bx bx-trending-up me-1"></i> Manage Promotions
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Modules Tab -->
            <div class="tab-pane fade" id="modules" role="tabpanel">
                <div class="row">
                    <div class="col-12 mb-4">
                        <div class="card border-info">
                            <div class="card-header bg-label-info">
                                <h5 class="card-title mb-0">
                                    <i class="bx bx-info-circle me-2"></i>Module Management
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info mb-4 mt-3">
                                    <i class="bx bx-info-circle me-2"></i>
                                    <strong>Control Module Visibility:</strong> Toggle modules on or off to control which pages are accessible in the admin panel. Disabled modules will be hidden from the sidebar.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

                <div class="row">
                    <!-- Core Modules -->
                    <div class="col-lg-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bx bx-layer me-2"></i>Core Modules
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <div class="d-flex justify-content-between align-items-center p-3 border rounded">
                                            <div>
                                                <h6 class="mb-1">
                                                    <i class="bx bx-clipboard me-2 text-primary"></i>Attendance Module
                                                </h6>
                                                <p class="text-muted mb-0 small">View and manage attendance records</p>
                                            </div>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="module_attendance" checked>
                                                <label class="form-check-label" for="module_attendance"></label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="d-flex justify-content-between align-items-center p-3 border rounded">
                                            <div>
                                                <h6 class="mb-1">
                                                    <i class="bx bx-calendar me-2 text-info"></i>Sessions Module
                                                </h6>
                                                <p class="text-muted mb-0 small">Manage enrollment sessions</p>
                                            </div>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="module_sessions" checked>
                                                <label class="form-check-label" for="module_sessions"></label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="d-flex justify-content-between align-items-center p-3 border rounded">
                                            <div>
                                                <h6 class="mb-1">
                                                    <i class="bx bx-bar-chart-alt-2 me-2 text-success"></i>Reports Module
                                                </h6>
                                                <p class="text-muted mb-0 small">Generate attendance reports and analytics</p>
                                            </div>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="module_reports" checked>
                                                <label class="form-check-label" for="module_reports"></label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="d-flex justify-content-between align-items-center p-3 border rounded">
                                            <div>
                                                <h6 class="mb-1">
                                                    <i class="bx bx-laptop me-2 text-warning"></i>Programs & Sections Module
                                                </h6>
                                                <p class="text-muted mb-0 small">Manage academic programs and sections</p>
                                            </div>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="module_program_sections" checked>
                                                <label class="form-check-label" for="module_program_sections"></label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="d-flex justify-content-between align-items-center p-3 border rounded">
                                            <div>
                                                <h6 class="mb-1">
                                                    <i class="bx bx-trending-up me-2 text-secondary"></i>Student Promotion Module
                                                </h6>
                                                <p class="text-muted mb-0 small">Promote students and manage graduations</p>
                                            </div>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="module_promote_students" checked>
                                                <label class="form-check-label" for="module_promote_students"></label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Modules -->
                    <div class="col-lg-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bx bx-extension me-2"></i>Additional Modules
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <div class="d-flex justify-content-between align-items-center p-3 border rounded">
                                            <div>
                                                <h6 class="mb-1">
                                                    <i class="bx bx-qr-scan me-2 text-danger"></i>Scan Module
                                                </h6>
                                                <p class="text-muted mb-0 small">QR code scanning for check-in/out</p>
                                            </div>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="module_scan" checked>
                                                <label class="form-check-label" for="module_scan"></label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="d-flex justify-content-between align-items-center p-3 border rounded">
                                            <div>
                                                <h6 class="mb-1">
                                                    <i class="bx bx-user me-2 text-primary"></i>Students Module
                                                </h6>
                                                <p class="text-muted mb-0 small">Manage student records and information</p>
                                            </div>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="module_students" checked>
                                                <label class="form-check-label" for="module_students"></label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="d-flex justify-content-between align-items-center p-3 border rounded bg-light">
                                            <div>
                                                <h6 class="mb-1">
                                                    <i class="bx bx-home-smile me-2 text-info"></i>Dashboard
                                                </h6>
                                                <p class="text-muted mb-0 small">Main dashboard overview (cannot be disabled)</p>
                                            </div>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="module_dashboard" checked disabled>
                                                <label class="form-check-label" for="module_dashboard"></label>
                                            </div>
                                        </div>
                                        <div class="text-muted text-center mt-2">
                                            <small><i class="bx bx-info-circle me-1"></i>Core module - always enabled</small>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="d-flex justify-content-between align-items-center p-3 border rounded bg-light">
                                            <div>
                                                <h6 class="mb-1">
                                                    <i class="bx bx-cog me-2 text-secondary"></i>Settings
                                                </h6>
                                                <p class="text-muted mb-0 small">System configuration (cannot be disabled)</p>
                                            </div>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="module_settings" checked disabled>
                                                <label class="form-check-label" for="module_settings"></label>
                                            </div>
                                        </div>
                                        <div class="text-muted text-center mt-2">
                                            <small><i class="bx bx-info-circle me-1"></i>Core module - always enabled</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card border-warning">
                            <div class="card-body">
                                <div class="alert alert-warning mb-0">
                                    <i class="bx bx-error me-2"></i>
                                    <strong>Note:</strong> Changes will take effect after saving and refreshing the page. Disabled modules will be hidden from the sidebar navigation.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Staff Management Tab -->
            <div class="tab-pane fade" id="staff" role="tabpanel">
                <div class="row">
                    <div class="col-12 mb-4">
                        <div class="card border-primary">
                            <div class="card-header bg-label-primary">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">
                                        <i class="bx bx-group me-2"></i>Staff Management
                                    </h5>
                                    <button class="btn btn-primary btn-sm" onclick="openAddStaffModal()">
                                        <i class="bx bx-plus me-1"></i>Add Staff
                                    </button>
                                </div>
                            </div>
                            <div class="card-body mt-3">
                                <div class="alert alert-info mb-3 mt-3">
                                    <i class="bx bx-info-circle me-2"></i>
                                    <strong>Manage Staff Accounts:</strong> Create and manage staff accounts with granular page access control. Only Super Admin can access this feature.
                                </div>
                                <div id="staff-list-container">
                                    <div class="text-center py-5">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Loading staff...</span>
                                        </div>
                                        <p class="mt-2">Loading staff accounts...</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Advanced Tab -->
            <div class="tab-pane fade" id="advanced" role="tabpanel">
                <div class="row">
                    <!-- Debug Settings -->
                    <div class="col-lg-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bx bx-bug me-2"></i>Debug Settings
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="debug_mode" checked>
                                            <label class="form-check-label" for="debug_mode">
                                                Enable Debug Mode
                                            </label>
                                        </div>
                                        <div class="form-text">Show detailed debug information</div>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="log_errors" checked>
                                            <label class="form-check-label" for="log_errors">
                                                Enable Error Logging
                                            </label>
                                        </div>
                                        <div class="form-text">Log errors to system log</div>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="show_errors" checked>
                                            <label class="form-check-label" for="show_errors">
                                                Show Errors in Development
                                            </label>
                                        </div>
                                        <div class="form-text">Display errors in development mode</div>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="enable_audit_log" checked>
                                            <label class="form-check-label" for="enable_audit_log">
                                                Enable Audit Logging
                                            </label>
                                        </div>
                                        <div class="form-text">Log all user actions and system events</div>
                                    </div>
                                    <div class="col-12">
                                        <label for="log_retention_days" class="form-label">Log Retention (days)</label>
                                        <input type="number" class="form-control" id="log_retention_days" value="30" min="7" max="365">
                                        <div class="form-text">How long to keep log files</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Security Settings -->
                    <div class="col-lg-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bx bx-shield me-2"></i>Security Settings
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label for="session_timeout_seconds" class="form-label">Session Timeout (seconds)</label>
                                        <input type="number" class="form-control" id="session_timeout_seconds" value="3600" min="300" max="86400">
                                        <div class="form-text">How long sessions remain active</div>
                                    </div>
                                    <div class="col-12">
                                        <label for="max_login_attempts" class="form-label">Max Login Attempts</label>
                                        <input type="number" class="form-control" id="max_login_attempts" value="5" min="3" max="10">
                                        <div class="form-text">Maximum failed login attempts before lockout</div>
                                    </div>
                                    <div class="col-12">
                                        <label for="login_lockout_minutes" class="form-label">Login Lockout (minutes)</label>
                                        <input type="number" class="form-control" id="login_lockout_minutes" value="15" min="5" max="60">
                                        <div class="form-text">How long to lockout after failed attempts</div>
                                    </div>
                                    <div class="col-12">
                                        <label for="password_min_length" class="form-label">Minimum Password Length</label>
                                        <input type="number" class="form-control" id="password_min_length" value="8" min="6" max="32">
                                        <div class="form-text">Minimum required password length</div>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="require_password_change" checked>
                                            <label class="form-check-label" for="require_password_change">
                                                Require Password Change on First Login
                                            </label>
                                        </div>
                                        <div class="form-text">Force users to change password on first login</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Backup & Restore -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bx bx-download me-2"></i>Backup & Restore
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex flex-wrap gap-2 mb-3">
                                    <button class="btn btn-success" onclick="exportSettings()">
                                        <i class="bx bx-download me-1"></i>
                                        <span class="btn-text">Export</span>
                                    </button>
                                    <button class="btn btn-warning" onclick="importSettings()">
                                        <i class="bx bx-upload me-1"></i>
                                        <span class="btn-text">Import</span>
                                    </button>
                                    <button class="btn btn-danger" onclick="resetAllSettings()">
                                        <i class="bx bx-undo me-1"></i>
                                        <span class="btn-text">Reset</span>
                                    </button>
                                </div>
                                <input type="file" id="importFile" accept=".json" style="display: none;" onchange="handleImportFile(this)">
                                <div class="alert alert-info">
                                    <i class="bx bx-info-circle me-1"></i>
                                    <strong>Backup & Restore:</strong> Export your settings to a JSON file for backup, or import previously exported settings. Use "Reset to Defaults" to restore all settings to their original values.
                                </div>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label for="backup_frequency" class="form-label">Automatic Backup Frequency</label>
                                        <select class="form-select" id="backup_frequency">
                                            <option value="daily">Daily</option>
                                            <option value="weekly" selected>Weekly</option>
                                            <option value="monthly">Monthly</option>
                                            <option value="disabled">Disabled</option>
                                        </select>
                                        <div class="form-text">How often to create automatic backups</div>
                                    </div>
                                    <div class="col-12">
                                        <label for="backup_retention_days" class="form-label">Backup Retention (days)</label>
                                        <input type="number" class="form-control" id="backup_retention_days" value="30" min="7" max="365">
                                        <div class="form-text">How long to keep backup files</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- General Settings Tab -->
            <?php if (isSuperAdmin()): ?>
            <div class="tab-pane fade show active" id="general" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12 mb-4">
                                <h5 class="mb-3">
                                    <i class="bx bx-palette me-2"></i>Branding & General Settings
                                </h5>
                                <p class="text-muted">Manage your system's logo and project name</p>
                            </div>
                        </div>
                        
                        <form id="generalSettingsForm">
                            <div class="row">
                                <!-- Logo Upload Section -->
                                <div class="col-12 col-lg-6 mb-4">
                                    <div class="card">
                                        <div class="card-header">
                                            <h6 class="mb-0"><i class="bx bx-image me-2"></i>System Logo</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="text-center mb-3">
                                                <img src="<?php 
                                                    $logo = $generalSettings['sidebar_logo'] ?? '';
                                                    if ($logo) {
                                                        echo htmlspecialchars('../' . $logo);
                                                    } else {
                                                        // Show placeholder or default
                                                        echo 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjUwIiBoZWlnaHQ9IjEwMCIgdmlld0JveD0iMCAwIDI1MCAxMDAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHRleHQgeD0iNTAlIiB5PSI1MCIgZm9udC1mYW1pbHk9IkFyaWFsIiBmb250LXNpemU9IjIwIiBmaWxsPSIjNjY2IiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBkeT0iLjNlbSI+TG9nbyBQcmV2aWV3PC90ZXh0Pjwvc3ZnPg==';
                                                    }
                                                ?>" 
                                                     alt="System Logo" 
                                                     id="logoPreview" 
                                                     class="img-thumbnail" 
                                                     style="max-width: 250px; max-height: 100px; object-fit: contain;">
                                            </div>
                                            <div class="mb-3">
                                                <label for="logoUpload" class="form-label">Upload New Logo</label>
                                                <input type="file" class="form-control" id="logoUpload" accept="image/*" onchange="previewLogo(this)">
                                                <div class="form-text">Supported formats: JPG, PNG, GIF. Max size: 2MB</div>
                                            </div>
                                            <button type="button" class="btn btn-primary btn-sm" onclick="uploadLogo()">
                                                <i class="bx bx-upload me-1"></i>Upload Logo
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Project Name Section -->
                                <div class="col-12 col-lg-6 mb-4">
                                    <div class="card">
                                        <div class="card-header">
                                            <h6 class="mb-0"><i class="bx bx-edit me-2"></i>Project Name</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label for="projectName" class="form-label">Project Name</label>
                                                <input type="text" class="form-control" id="projectName" 
                                                       name="project_name" 
                                                       value="<?php echo htmlspecialchars($generalSettings['project_name'] ?? 'QR Attendance System'); ?>" 
                                                       placeholder="Enter project name">
                                                <div class="form-text">This name will appear in emails, PDFs, and student cards</div>
                                            </div>
                                            <div class="mb-3">
                                                <label for="projectShortName" class="form-label">Project Short Name</label>
                                                <input type="text" class="form-control" id="projectShortName" 
                                                       name="project_short_name" 
                                                       value="<?php echo htmlspecialchars($generalSettings['project_short_name'] ?? 'QAS'); ?>" 
                                                       placeholder="Enter short name">
                                                <div class="form-text">Used in document headers and quick references</div>
                                            </div>
                                            <div class="mb-3">
                                                <label for="projectTagline" class="form-label">Tagline (Optional)</label>
                                                <input type="text" class="form-control" id="projectTagline" 
                                                       name="project_tagline" 
                                                       value="<?php echo htmlspecialchars($generalSettings['project_tagline'] ?? ''); ?>" 
                                                       placeholder="Enter tagline">
                                                <div class="form-text">A short descriptive phrase for your system</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mt-3">
                                <div class="col-12">
                                    <div class="d-flex justify-content-end gap-2">
                                        <button type="button" class="btn btn-secondary" onclick="resetGeneralSettings()">
                                            <i class="bx bx-refresh me-1"></i>Reset
                                        </button>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bx bx-save me-1"></i>Save General Settings
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <!-- / Content -->
    
    <!-- Footer -->
    <footer class="content-footer footer bg-footer-theme">
        <div class="container-xxl">
            <div class="footer-container d-flex align-items-center justify-content-between py-4 flex-md-row flex-column">
                <div class="mb-2 mb-md-0">
                    &#169;
                    <script>
                        document.write(new Date().getFullYear());
                    </script>
                    , made with ❤️ by
                    <a href="https://sharelimitless.com/" target="_blank" class="footer-link">Sharelimitless.com</a>
                </div>
                <div class="d-none d-lg-inline-block">
                    <a href="#" class="footer-link me-4">Documentation</a>
                    <a href="#" class="footer-link me-4">Support</a>
                </div>
            </div>
        </div>
    </footer>
    <!-- / Footer -->
    
    <div class="content-backdrop fade"></div>
</div>
<!-- Content wrapper -->

<!-- Alert Container -->
</div>
<!-- / Layout page -->
</div>
<!-- / Layout container -->
</div>
<!-- / Layout wrapper -->

<!-- Core JS -->
<script src="<?php echo getAdminAssetUrl('vendor/libs/jquery/jquery.js'); ?>"></script>
<script src="<?php echo getAdminAssetUrl('vendor/libs/popper/popper.js'); ?>"></script>
<script src="<?php echo getAdminAssetUrl('vendor/js/bootstrap.js'); ?>"></script>
<script src="<?php echo getAdminAssetUrl('vendor/libs/perfect-scrollbar/perfect-scrollbar.js'); ?>"></script>
<script src="<?php echo getAdminAssetUrl('vendor/js/menu.js'); ?>"></script>

<!-- Main JS -->
<script src="<?php echo getAdminAssetUrl('js/main.js'); ?>"></script>

<!-- Time Utils -->
<script src="<?php echo getAdminAssetUrl('../../assets/js/time-utils.js'); ?>"></script>

<!-- Staff Management Modals -->
<!-- Add/Edit Staff Modal -->
<div class="modal fade" id="addStaffModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addStaffModalTitle">Add Staff Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addStaffForm">
                <div class="modal-body">
                    <input type="hidden" id="staff-user-id" name="user_id">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="staff-username" class="form-label">Username *</label>
                            <input type="text" class="form-control" id="staff-username" name="username" required>
                        </div>
                        <div class="col-md-6">
                            <label for="staff-email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="staff-email" name="email" required>
                        </div>
                        <div class="col-md-6">
                            <label for="staff-password" class="form-label">Password *</label>
                            <input type="password" class="form-control" id="staff-password" name="password" required>
                            <div class="form-text" id="password-hint">Min. 8 characters</div>
                        </div>
                        <div class="col-md-6">
                            <label for="staff-status" class="form-label">Status *</label>
                            <select class="form-select" id="staff-status" name="status" required>
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="staff-role" class="form-label">Role *</label>
                            <select class="form-select" id="staff-role" name="role" required>
                                <option value="staff">Staff - Limited Access</option>
                                <option value="admin">Admin - Full Access</option>
                                <option value="superadmin">Super Admin - Complete Control</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <hr>
                            <h6 class="mb-3">Page Access Permissions</h6>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="perm-dashboard" value="index.php" name="permissions[]">
                                        <label class="form-check-label" for="perm-dashboard">Dashboard</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="perm-students" value="students.php" name="permissions[]">
                                        <label class="form-check-label" for="perm-students">Students</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="perm-attendance" value="attendances.php" name="permissions[]">
                                        <label class="form-check-label" for="perm-attendance">Attendance</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="perm-sessions" value="sessions.php" name="permissions[]">
                                        <label class="form-check-label" for="perm-sessions">Sessions</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="perm-scan" value="scan.php" name="permissions[]">
                                        <label class="form-check-label" for="perm-scan">Scan</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="perm-programs" value="program_sections.php" name="permissions[]">
                                        <label class="form-check-label" for="perm-programs">Programs & Sections</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="perm-reports" value="reports.php" name="permissions[]">
                                        <label class="form-check-label" for="perm-reports">Reports</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="perm-settings" value="settings.php" name="permissions[]">
                                        <label class="form-check-label" for="perm-settings">Settings</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bx bx-save me-1"></i>Save Staff
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Page JS -->
<script src="<?php echo getAdminAssetUrl('js/settings.js'); ?>"></script>

<script>
// Staff Management JavaScript
document.addEventListener('DOMContentLoaded', function() {
    loadStaffList();
});

function loadStaffList() {
    fetch('api/staff.php?action=list')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderStaffList(data.data);
            } else {
                document.getElementById('staff-list-container').innerHTML = '<div class="alert alert-danger">' + data.error + '</div>';
            }
        })
        .catch(error => {
            console.error('Error loading staff:', error);
            document.getElementById('staff-list-container').innerHTML = '<div class="alert alert-danger">Error loading staff list</div>';
        });
}

function renderStaffList(staff) {
    const container = document.getElementById('staff-list-container');
    
    if (staff.length === 0) {
        container.innerHTML = '<div class="alert alert-info">No staff accounts found. Click "Add Staff" to create one.</div>';
        return;
    }
    
    let html = '<div class="table-responsive"><table class="table table-hover"><thead><tr>';
    html += '<th>Username</th><th>Email</th><th>Role</th><th>Pages Allowed</th><th>Status</th><th>Last Login</th><th>Actions</th>';
    html += '</tr></thead><tbody>';
    
    // Get current user ID and role from the page
    const currentUserId = <?php echo isset($_SESSION['admin_user_id']) ? $_SESSION['admin_user_id'] : 0; ?>;
    const currentUserRole = '<?php echo isset($_SESSION['admin_role']) ? $_SESSION['admin_role'] : ''; ?>';
    
    staff.forEach(member => {
        let roleBadge;
        if (member.role === 'superadmin') {
            roleBadge = '<span class="badge bg-danger">Super Admin</span>';
        } else if (member.role === 'admin') {
            roleBadge = '<span class="badge bg-warning">Admin</span>';
        } else {
            roleBadge = '<span class="badge bg-info">Staff</span>';
        }
        
        // Determine action buttons based on role and current user
        let actionButtons;
        if (member.role === 'superadmin') {
            actionButtons = '<button class="btn btn-sm btn-outline-primary" disabled><i class="bx bx-lock"></i></button>';
        } else if (member.id == currentUserId) {
            // Current user cannot edit their own account
            actionButtons = '<button class="btn btn-sm btn-outline-secondary" disabled><i class="bx bx-info-circle" title="You cannot edit your own account"></i></button>';
        } else if (currentUserRole === 'staff' && member.role === 'admin') {
            // Staff cannot edit Admin accounts
            actionButtons = '<button class="btn btn-sm btn-outline-secondary" disabled><i class="bx bx-lock" title="Staff cannot edit Admin accounts"></i></button>';
        } else {
            actionButtons = `<button class="btn btn-sm btn-outline-primary" onclick="editStaff(${member.id})">
                    <i class="bx bx-edit"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger" onclick="deleteStaff(${member.id})">
                    <i class="bx bx-trash"></i>
                </button>`;
        }
        
        // Display pages allowed based on role
        let pagesAllowed;
        if (member.role === 'superadmin') {
            pagesAllowed = '<span class="badge bg-success"><i class="bx bx-shield-quarter me-1"></i>Full Access</span>';
        } else if (member.role === 'admin') {
            const count = member.page_access_count || 0;
            pagesAllowed = count > 0 ? `<small>${count} pages</small>` : '<span class="badge bg-warning">Limited</span>';
        } else {
            const count = member.page_access_count || 0;
            pagesAllowed = `<small>${count} pages</small>`;
        }
        
        html += `<tr>
            <td><strong>${member.username}</strong></td>
            <td>${member.email}</td>
            <td>${roleBadge}</td>
            <td>${pagesAllowed}</td>
            <td><span class="badge bg-${member.is_active ? 'success' : 'danger'}">${member.is_active ? 'Active' : 'Inactive'}</span></td>
            <td><small class="text-muted">${member.last_login || 'Never'}</small></td>
            <td>${actionButtons}</td>
        </tr>`;
    });
    
    html += '</tbody></table></div>';
    container.innerHTML = html;
}

function openAddStaffModal() {
    document.getElementById('addStaffForm').reset();
    document.getElementById('staff-user-id').value = '';
    document.getElementById('staff-role').value = 'staff'; // Default to staff
    document.getElementById('addStaffModalTitle').textContent = 'Add Staff Account';
    document.getElementById('password-hint').textContent = 'Min. 8 characters';
    document.getElementById('staff-password').required = true;
    
    // Clear permissions
    document.querySelectorAll('#addStaffForm input[type="checkbox"]').forEach(cb => cb.checked = false);
    
    const modal = new bootstrap.Modal(document.getElementById('addStaffModal'));
    modal.show();
}

function editStaff(userId) {
    fetch(`api/staff.php?action=get&id=${userId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const staff = data.data;
                document.getElementById('staff-user-id').value = staff.id;
                document.getElementById('staff-username').value = staff.username;
                document.getElementById('staff-email').value = staff.email;
                document.getElementById('staff-status').value = staff.is_active ? '1' : '0';
                document.getElementById('staff-role').value = staff.role || 'staff';
                document.getElementById('staff-password').value = ''; // Clear password field
                document.getElementById('addStaffModalTitle').textContent = 'Edit Staff Account';
                document.getElementById('password-hint').textContent = 'Leave blank to keep current password';
                document.getElementById('staff-password').required = false;
                
                // Set permissions
                document.querySelectorAll('#addStaffForm input[type="checkbox"]').forEach(cb => {
                    cb.checked = staff.permissions && staff.permissions.includes(cb.value);
                });
                
                const modal = new bootstrap.Modal(document.getElementById('addStaffModal'));
                modal.show();
            } else {
                alert('Error loading staff data: ' + data.error);
            }
        });
}

function deleteStaff(userId) {
    if (confirm('Are you sure you want to delete this staff account?')) {
        fetch(`api/staff.php?action=delete&id=${userId}`, { method: 'DELETE' })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadStaffList();
                } else {
                    alert('Error deleting staff: ' + data.error);
                }
            });
    }
}

document.getElementById('addStaffForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const data = {};
    formData.forEach((value, key) => {
        if (key === 'permissions[]') {
            if (!data.permissions) data.permissions = [];
            data.permissions.push(value);
        } else if (key === 'password' && value === '') {
            // Skip empty passwords in edit mode
            return;
        } else {
            data[key] = value;
        }
    });
    
    const userId = document.getElementById('staff-user-id').value;
    const isEdit = userId !== '';
    
    // Don't include password field if it's empty in edit mode
    if (isEdit && !data.password) {
        delete data.password;
    }
    
    // Debug: Check what we're sending
    console.log('Staff update data:', data);
    
    // Get submit button and disable it with loader
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalBtnHTML = submitBtn.innerHTML;
    
    // Disable button and show loader
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Saving...';
    
    fetch(`api/staff.php?action=${isEdit ? 'update' : 'create'}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            const modal = bootstrap.Modal.getInstance(document.getElementById('addStaffModal'));
            modal.hide();
            
            // Show success message
            if (typeof UIHelpers !== 'undefined') {
                UIHelpers.showSuccess(result.message || 'Staff account created successfully!');
            } else {
                alert(result.message || 'Staff account created successfully!');
            }
            
            loadStaffList();
        } else {
            if (typeof UIHelpers !== 'undefined') {
                UIHelpers.showError('Error saving staff: ' + (result.error || 'Unknown error'));
            } else {
                alert('Error saving staff: ' + result.error);
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        if (typeof UIHelpers !== 'undefined') {
            UIHelpers.showError('Error creating staff account');
        } else {
            alert('Error creating staff account');
        }
    })
    .finally(() => {
        // Re-enable button and restore original text
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnHTML;
        }
    });
});
</script>

<style>
/* Responsive button text */
@media (max-width: 768px) {
    .btn-text {
        display: none;
    }
    
    .d-flex.flex-wrap.gap-2 .btn {
        padding: 0.5rem 0.75rem;
    }
    
    .settings-status {
        width: 100%;
        text-align: center;
        margin-bottom: 0.5rem;
    }
}

@media (min-width: 769px) {
    .btn i.me-1 {
        margin-right: 0.5rem !important;
    }
}
</style>

</body>
</html>