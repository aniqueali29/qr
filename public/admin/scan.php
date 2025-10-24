<?php
// Admin QR Scanner Check-in Page - Modern Redesign
// Protect page with admin auth and render modern UI

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
// Ensure API CSRF token exists in session
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }

requireAdminAuth();

$pageTitle = 'QR Scanner';
$pageCSS = ['css/scanner.css'];
$pageJS = ['js/scanner.js'];

include 'partials/header.php';
include 'partials/sidebar.php';
include 'partials/navbar.php';
?>


<!-- Custom Styles -->
<style>
.scanner-container {
    background: #f8f9fa;
    min-height: 100vh;
    padding: 2rem 0;
}

.scanner-card {
    background: #ffffff;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
    border: 1px solid #e9ecef;
}

.scanner-input {
    border: 2px dashed var(--bs-secondary);
    border-radius: 8px;
    transition: all 0.3s ease;
    background: #ffffff;
}

.scanner-input:focus {
    border-color: var(--bs-primary);
    box-shadow: 0 0 0 0.2rem rgba(var(--bs-primary-rgb), 0.25);
    transform: none;
}

.scan-animation {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.02); }
    100% { transform: scale(1); }
}

.stats-card {
    background: #ffffff;
    color: var(--bs-body-color);
    border-radius: 8px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    border: 1px solid var(--bs-border-color);
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
}

.recent-scans {
    max-height: 400px;
    overflow-y: auto;
}

.scan-item {
    border-left: 4px solid var(--bs-secondary);
    transition: all 0.3s ease;
    background: #ffffff;
    border: 1px solid var(--bs-border-color);
}

.scan-item:hover {
    background: var(--bs-light);
    border-left-color: var(--bs-primary);
}

.status-badge {
    font-size: 0.75rem;
    padding: 0.5rem 1rem;
    border-radius: 4px;
}

.btn-scan {
    background: var(--bs-primary);
    border: 1px solid var(--bs-primary);
    border-radius: 6px;
    padding: 0.75rem 2rem;
    color: white;
    font-weight: 500;
    transition: all 0.3s ease;
}

.btn-scan:hover {
    background: var(--bs-primary);
    border-color: var(--bs-primary);
    color: white;
    transform: none;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
    opacity: 0.9;
}

.flash-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(255, 255, 255, 0.9);
    z-index: 9999;
    display: none;
    animation: flash 0.3s ease;
}

@keyframes flash {
    0% { opacity: 0; }
    50% { opacity: 1; }
    100% { opacity: 0; }
}

.qr-icon {
    font-size: 3rem;
    color: var(--bs-secondary);
    animation: none;
}

.mode-toggle {
    position: relative;
    width: 60px;
    height: 30px;
    background: var(--bs-light);
    border-radius: 15px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.mode-toggle.active {
    background: var(--bs-primary);
}

.mode-toggle::after {
    content: '';
    position: absolute;
    top: 3px;
    left: 3px;
    width: 24px;
    height: 24px;
    background: white;
    border-radius: 50%;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.mode-toggle.active::after {
    transform: translateX(30px);
}

.header-section {
    background: #ffffff;
    border-radius: 8px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
    border: 1px solid var(--bs-border-color);
}

.header-section h1 {
    color: var(--bs-body-color);
    font-weight: 600;
}

.header-section p {
    color: var(--bs-secondary);
}

.badge-success {
    background-color: #28a745;
}

.badge-warning {
    background-color: #ffc107;
    color: #212529;
}

.badge-secondary {
    background-color: #6c757d;
}

.btn-outline-primary {
    color: var(--bs-primary);
    border-color: var(--bs-primary);
}

.btn-outline-primary:hover {
    background-color: var(--bs-primary);
    border-color: var(--bs-primary);
    color: white;
}

.btn-outline-warning {
    color: #ffc107;
    border-color: #ffc107;
}

.btn-outline-warning:hover {
    background-color: #ffc107;
    border-color: #ffc107;
    color: #212529;
}

.btn-outline-danger {
    color: #dc3545;
    border-color: #dc3545;
}

.btn-outline-danger:hover {
    background-color: #dc3545;
    border-color: #dc3545;
    color: white;
}

.btn-outline-info {
    color: #17a2b8;
    border-color: #17a2b8;
}

.btn-outline-info:hover {
    background-color: #17a2b8;
    border-color: #17a2b8;
    color: white;
}

.modal-header.bg-primary {
    background-color: var(--bs-primary) !important;
}

.input-group-text.bg-primary {
    background-color: var(--bs-primary) !important;
    border-color: var(--bs-primary);
}

.border-primary {
    border-color: var(--bs-primary) !important;
}

.badge.bg-success {
    background-color: #28a745 !important;
}
</style>

<!-- Flash Overlay for Visual Feedback -->
<div id="flash-overlay" class="flash-overlay"></div>

<!-- Toast Container -->
<div id="toast-container" class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999;"></div>

<!-- Main Content -->
<div class="scanner-container">
    <div class="container-xxl">
        <!-- Header Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="header-section text-center">
                    <h1 class="display-5 fw-bold mb-3">
                        <i class="bx bx-qr-scan qr-icon me-3"></i>
                        QR Attendance Scanner
                    </h1>
                    <p class="lead mb-0">Scan student QR codes or enter manually to mark attendance</p>
                </div>
            </div>
        </div>

    <div class="row">
            <!-- Scanner Section -->
            <div class="col-12 col-lg-8">
                <div class="scanner-card p-4 mb-4">
                    <!-- Mode Toggle -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="d-flex align-items-center gap-3">
                            <div class="mode-toggle" id="mode-toggle">
                                <input type="checkbox" id="mode-checkbox" class="d-none">
                        </div>
                            <span class="fw-bold" id="mode-label">Scanner Mode</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-success px-3 py-2" id="scanner-status">
                                <i class="bx bx-check-circle me-1"></i>Ready
                            </span>
                            <button type="button" class="btn btn-outline-primary btn-sm" 
                                    data-bs-toggle="modal" data-bs-target="#scannerConfigModal">
                                <i class="bx bx-cog me-1"></i>Settings
                        </button>
                    </div>
                    </div>

                    <!-- Scanner Input Area -->
                    <div id="scanner-section">
                        <input type="hidden" id="csrf-token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        
                        <div class="text-center mb-4">
                            <div class="scanner-input p-4 mb-3">
                                <i class="bx bx-qr-scan qr-icon mb-3 d-block"></i>
                                <h5 class="mb-3 text-dark">Ready to Scan</h5>
                                <p class="text-muted mb-4">Position the QR code in front of the scanner or focus the input field below</p>
                                
                            <div class="input-group input-group-lg">
                                    <span class="input-group-text bg-primary text-white">
                                    <i class="bx bx-qr-scan"></i>
                                </span>
                                    <input type="text" id="scan-input" class="form-control form-control-lg text-center" 
                                           placeholder="Scan QR code or type Student ID..." 
                                       autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false">
                            </div>
                                <small class="text-muted mt-2 d-block">
                                    <i class="bx bx-info-circle me-1"></i>
                                    Press <strong> Alt+I</strong> to focus input field
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- Manual Input Section -->
                    <div id="manual-section" class="d-none">
                        <div class="row g-3">
                            <div class="col-12">
                                <h5 class="mb-3">
                                    <i class="bx bx-keyboard me-2"></i>Manual Entry
                                </h5>
                            </div>
                            <div class="col-md-8">
                                <label for="manual-student-id" class="form-label fw-bold">Student ID</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bx bx-id-card"></i>
                                    </span>
                                    <input type="text" id="manual-student-id" class="form-control" 
                                           placeholder="e.g., 25-SWT-01">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label for="manual-notes" class="form-label fw-bold">Notes</label>
                                <input type="text" id="manual-notes" class="form-control" 
                                       placeholder="Optional...">
                            </div>
                            <div class="col-12">
                                <button type="button" id="manual-submit" class="btn btn-scan w-100">
                                    <i class="bx bx-save me-2"></i>Save Attendance
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="d-flex gap-2 flex-wrap">
                                <button type="button" id="auto-absent-btn" class="btn btn-outline-warning">
                                    <i class="bx bx-time me-1"></i>Auto Check Absent
                                </button>
                                <button type="button" id="mark-absent-btn" class="btn btn-outline-danger">
                                    <i class="bx bx-user-x me-1"></i>Mark All Absent
                                </button>
                                <button type="button" class="btn btn-outline-info" onclick="refreshData()">
                                    <i class="bx bx-refresh me-1"></i>Refresh
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats & Recent Scans -->
            <div class="col-12 col-lg-4">
                <!-- Statistics Card -->
                <div class="stats-card mb-4">
                    <h5 class="mb-3">
                        <i class="bx bx-bar-chart me-2"></i>Today's Statistics
                    </h5>
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="fs-2 fw-bold" id="scan-count">0</div>
                            <div class="small">Total Scans</div>
                </div>
                        <div class="col-6">
                            <div class="fs-2 fw-bold" id="present-count">0</div>
                            <div class="small">Present</div>
                </div>
                    </div>
                    <div class="mt-3">
                        <small class="opacity-75">
                            <i class="bx bx-time me-1"></i>
                            Last scan: <span id="last-scan-time">-</span>
                        </small>
                </div>
        </div>

                <!-- Recent Scans -->
                <div class="scanner-card p-4">
                    <h6 class="mb-3">
                        <i class="bx bx-history me-2"></i>Recent Scans
                    </h6>
                    <div class="recent-scans" id="recent-scans">
                        <div class="text-center text-muted py-4">
                            <i class="bx bx-history bx-lg mb-2 d-block"></i>
                            <div>No scans yet</div>
                </div>
                </div>
                </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Student Preview Modal -->
<div class="modal fade" id="studentPreviewModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-labelledby="studentPreviewModalLabel" role="dialog">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                <h5 class="modal-title" id="studentPreviewModalLabel">
                    <i class="bx bx-user-check me-2"></i>Student Verification
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
            <div class="modal-body text-center p-4">
                    <div class="preview-loading d-none">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    <div class="mt-3">Loading student information...</div>
                    </div>
                
                <div class="student-preview">
                    <div class="position-relative d-inline-block mb-4">
                        <div class="rounded-circle border border-3 border-secondary d-flex align-items-center justify-content-center bg-light" 
                             style="width: 120px; height: 120px;">
                            <i class="bx bx-user text-muted" style="font-size: 3rem;"></i>
                    </div>
                        <div class="position-absolute top-0 end-0">
                            <span class="badge bg-success rounded-circle p-2">
                                <i class="bx bx-check"></i>
                            </span>
                    </div>
                    </div>
                    
                    <h4 id="preview-name" class="mb-2 fw-bold">Student Name</h4>
                    <p class="text-muted mb-1">
                        <strong id="preview-id">Student ID</strong>
                    </p>
                    <p class="text-muted mb-3">
                        <span id="preview-program">Software Technology</span> • <span id="preview-shift">Morning</span>
                    </p>
                    
                    <div id="preview-status" class="mb-4">
                        <span class="badge bg-secondary fs-6 px-3 py-2">Not checked in today</span>
    </div>

                    <div class="row g-2 mb-4">
                        <div class="col-6">
                            <button class="btn btn-success btn-lg w-100" id="preview-confirm" disabled>
                                <i class="bx bx-check me-2"></i>Confirm Attendance
                        </button>
                                        </div>
                        <div class="col-6">
                            <button class="btn btn-outline-secondary btn-lg w-100" id="preview-cancel">
                                <i class="bx bx-x me-2"></i>Cancel
                            </button>
                                    </div>
                                </div>
                    
                    <!-- Time Validation Errors -->
                    <div id="time-validation-errors" class="d-none mx-auto">
                        <div class="alert alert-danger border-0 mb-3">
                            <div class="d-flex align-items-start">
                                <i class="bx bx-error-circle me-2 mt-1"></i>
                                <div>
                                    <h6 class="alert-heading mb-2">Attendance Not Allowed</h6>
                                    <div id="validation-error-message" class="mb-2"></div>
                                    <div id="timing-info" class="small text-muted"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                    
                    <!-- Auto-confirm Timer (only shown when validation passes) -->
                    <div id="auto-confirm-timer-container" class="alert alert-light border mb-0">
                        <i class="bx bx-time-five me-2"></i>
                        Auto-confirms in <span id="auto-confirm-timer" class="fw-bold">2</span> seconds
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<!-- Scanner Configuration Modal -->
<div class="modal fade" id="scannerConfigModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bx bx-cog me-2"></i>Scanner Configuration
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Scan Debounce (ms)</label>
                    <input type="number" class="form-control" id="config-debounce" value="800" min="0" max="5000" step="100">
                        <div class="form-text">Prevents double-scanning</div>
                </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Auto-confirm Delay (ms)</label>
                        <input type="number" class="form-control" id="config-autoconfirm" value="3000" min="0" max="10000" step="500">
                        <div class="form-text">Time before auto-confirming (0 = manual only)</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Duplicate Suppression (ms)</label>
                    <input type="number" class="form-control" id="config-duplicate" value="3000" min="0" max="10000" step="500">
                        <div class="form-text">Ignore same student within time window</div>
                </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Sound Volume</label>
                        <input type="range" class="form-range" id="config-volume" value="75" min="0" max="100">
                        <div class="form-text">Adjust notification sound volume</div>
                </div>
                </div>
                
                <hr class="my-4">
                
                <div class="row g-3">
                    <div class="col-12">
                        <h6 class="mb-3 fw-semibold">Features</h6>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="config-sound" checked>
                            <label class="form-check-label fw-medium" for="config-sound">
                                <i class="bx bx-volume-full me-2"></i>Sound Effects
                    </label>
                </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="config-flash" checked>
                            <label class="form-check-label fw-medium" for="config-flash">
                                <i class="bx bx-brightness me-2"></i>Visual Flash
                    </label>
                </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="config-vibration">
                            <label class="form-check-label fw-medium" for="config-vibration">
                                <i class="bx bx-vibration me-2"></i>Vibration
                    </label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="bx bx-x me-1"></i>Cancel
                </button>
                <button type="button" class="btn btn-primary" id="save-scanner-config">
                    <i class="bx bx-save me-1"></i>Save Settings
                </button>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>

<script>
// Modern QR Scanner Application
class QRScanner {
    constructor() {
        this.scanInput = document.getElementById('scan-input');
        this.modeToggle = document.getElementById('mode-toggle');
        this.modeCheckbox = document.getElementById('mode-checkbox');
        this.modeLabel = document.getElementById('mode-label');
        this.scannerSection = document.getElementById('scanner-section');
        this.manualSection = document.getElementById('manual-section');
        this.scannerStatus = document.getElementById('scanner-status');
        this.csrfToken = document.getElementById('csrf-token').value;
        
        this.lastScanTime = 0;
        this.scanDebounce = 800;
        this.duplicateSuppression = 3000;
        this.autoConfirmDelay = 3000;
        this.soundEnabled = true;
        this.flashEnabled = true;
        this.vibrationEnabled = false;
        
        this.init();
    }
    
    init() {
        this.setupEventListeners();
        this.loadSettings();
        this.checkTimingSettings();
        this.loadData();
        this.focusInput();
    }
    
    setupEventListeners() {
        // Mode toggle
        this.modeToggle.addEventListener('click', () => this.toggleMode());
        
        // Scanner input
        this.scanInput.addEventListener('input', (e) => this.handleScan(e));
        this.scanInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                this.processScan(e.target.value);
            }
        });
        
        // Manual submit
        document.getElementById('manual-submit').addEventListener('click', () => this.handleManualSubmit());
        
        // Action buttons
        document.getElementById('auto-absent-btn').addEventListener('click', () => this.autoMarkAbsent());
        document.getElementById('mark-absent-btn').addEventListener('click', () => this.markAllAbsent());
        
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.altKey && e.key === 'i') {
                e.preventDefault();
                this.focusInput();
            }
        });
        
        // Settings modal
        document.getElementById('save-scanner-config').addEventListener('click', () => this.saveSettings());
    }
    
    toggleMode() {
        const isManual = this.modeCheckbox.checked;
        this.modeCheckbox.checked = !isManual;
        
        this.modeLabel.textContent = isManual ? 'Scanner Mode' : 'Manual Mode';
        this.modeToggle.classList.toggle('active', !isManual);
        this.scannerSection.classList.toggle('d-none', !isManual);
        this.manualSection.classList.toggle('d-none', isManual);
        
        if (!isManual) {
            this.focusInput();
        }
    }
    
    handleScan(event) {
        const value = event.target.value.trim();
        if (value.length > 0) {
            this.processScan(value);
        }
    }
    
    processScan(qrData) {
        const now = Date.now();
        
        // Check debounce
        if (now - this.lastScanTime < this.scanDebounce) {
            return;
        }
        
        // Try to extract a valid student ID from the QR data
        const studentId = this.extractStudentIdFromQR(qrData);

            if (!studentId) {
            this.showMessage('Invalid QR code format. Expected student ID format: YY-PROGRAM-NNN (e.g., 25-SWT-595)', 'error');
            console.error('Invalid QR data:', qrData);
            this.scanInput.value = '';
                return;
            }

        // Check duplicate suppression
        if (this.isDuplicateScan(studentId, now)) {
            this.showMessage('Duplicate scan ignored', 'warning');
            return;
        }
        
        this.lastScanTime = now;
        this.showStudentPreview(studentId);
        this.scanInput.value = '';
    }
    
    isDuplicateScan(studentId, timestamp) {
        // Simple duplicate check - in real app, you'd store this in localStorage or session
        const lastScans = JSON.parse(localStorage.getItem('lastScans') || '[]');
        const recentScans = lastScans.filter(scan => timestamp - scan.time < this.duplicateSuppression);
        
        if (recentScans.some(scan => scan.studentId === studentId)) {
            return true;
        }
        
        // Store this scan
        recentScans.push({ studentId, time: timestamp });
        localStorage.setItem('lastScans', JSON.stringify(recentScans.slice(-10))); // Keep last 10
        
        return false;
    }
    
    async showStudentPreview(studentId) {
        try {
            // Show loading
            const modalElement = document.getElementById('studentPreviewModal');
            
            // Configure modal with proper accessibility settings
            const modal = new bootstrap.Modal(modalElement, {
                backdrop: 'static',
                keyboard: false,
                focus: true
            });
            
            modal.show();
            
            // Fetch student data and validate time
            const studentData = await this.fetchStudentData(studentId);
            
            // Update modal content
            document.getElementById('preview-name').textContent = studentData.name || 'Unknown Student';
            document.getElementById('preview-id').textContent = studentId;
            document.getElementById('preview-program').textContent = studentData.program || 'N/A';
            document.getElementById('preview-shift').textContent = studentData.shift || 'N/A';
            
            // Update status badge based on current status
            this.updateStatusBadge(studentData.current_status);
            
            // Validate time and show appropriate UI
            await this.validateAttendanceTime(studentId, studentData);
            
        } catch (error) {
            this.showMessage('Student not found', 'error');
        }
    }
    
    async fetchStudentData(studentId) {
        try {
            console.log('Fetching data for student:', studentId);
            console.log('Student ID type:', typeof studentId);
            console.log('Student ID length:', studentId ? studentId.length : 'null');
            console.log('Student ID raw value:', JSON.stringify(studentId));
            
            // Validate student ID format (should be like 25-SWT-01)
            if (!this.isValidStudentId(studentId)) {
                console.error('Invalid student ID format:', studentId);
                this.showMessage('Invalid student ID format. Expected format: YY-PROGRAM-NNN (e.g., 25-SWT-595)', 'error');
                return {
                    name: 'Invalid ID Format',
                    program: 'N/A',
                    shift: 'Morning',
                    is_active: false,
                    is_graduated: false,
                    current_status: 'Invalid'
                };
            }
            
            const response = await fetch('../api/checkin_api.php?action=get_status', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                },
                body: JSON.stringify({ student_id: studentId })
            });
            
            console.log('Response status:', response.status);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            console.log('API response:', data);
            
            if (data.success && data.data) {
                return {
                    name: data.data.student_name,
                    program: data.data.program,
                    shift: data.data.shift,
                    is_active: data.data.is_active !== false, // Default to true if not specified
                    is_graduated: data.data.is_graduated || false,
                    current_status: data.data.status === 'Checked-in' ? 'checked_in' : 'not_checked_in'
                };
            } else {
                console.error('API returned error:', data.message);
                // Show error message to user
                this.showMessage('Failed to fetch student data: ' + (data.message || 'Unknown error'), 'error');
                // Fallback to mock data if API fails
                return {
                    name: 'Unknown Student',
                    program: 'N/A',
                    shift: 'Morning',
                    is_active: true,
                    is_graduated: false,
                    current_status: 'Unknown'
                };
            }
        } catch (error) {
            console.error('Error fetching student data:', error);
            // Fallback to mock data
            return {
                name: 'Unknown Student',
                program: 'N/A',
                shift: 'Morning',
                is_active: true,
                is_graduated: false,
                current_status: 'Unknown'
            };
        }
    }
    
    isValidStudentId(studentId) {
        // Check if student ID matches the expected format: YY-[E]PROGRAM-NN
        // Examples: 25-SWT-01, 24-ECSE-02, 25-ESWT-01, 25-SWT-595
        
        // If the ID is too long, it's likely a malformed QR code
        if (studentId.length > 20) {
            console.error('Student ID too long:', studentId.length, 'characters');
            return false;
        }
        
        // Updated pattern to allow 2-3 digit sequence numbers
        const pattern = /^\d{2}-[E]?[A-Z]{2,4}-\d{2,3}$/i;
        return pattern.test(studentId);
    }
    
    extractStudentIdFromQR(qrData) {
        // Try to extract a valid student ID from QR data
        // Sometimes QR codes contain extra data or are malformed
        
        // If it's already a valid ID, return it
        if (this.isValidStudentId(qrData)) {
            return qrData;
        }
        
        // Try to find a pattern that looks like a student ID
        const patterns = [
            /(\d{2}-[E]?[A-Z]{2,4}-\d{2,3})/i,  // Standard format (2-3 digits)
            /(\d{2}[E]?[A-Z]{2,4}\d{2,3})/i,    // Without dashes (2-3 digits)
            /(\d{2}\s+[E]?[A-Z]{2,4}\s+\d{2,3})/i  // With spaces (2-3 digits)
        ];
        
        for (const pattern of patterns) {
            const match = qrData.match(pattern);
            if (match) {
                let extracted = match[1];
                // Normalize format (add dashes if missing)
                if (!extracted.includes('-')) {
                    extracted = extracted.replace(/(\d{2})([E]?)([A-Z]{2,4})(\d{2,3})/i, '$1-$2$3-$4');
                }
                if (this.isValidStudentId(extracted)) {
                    console.log('Extracted student ID from QR:', extracted);
                    return extracted;
                }
            }
        }
        
        console.error('Could not extract valid student ID from QR data:', qrData);
        return null;
    }
    
    async validateCheckoutTime(studentId, studentData) {
        try {
            console.log('Validating checkout time for:', studentId, 'Shift:', studentData.shift);
            // Validate checkout time window
            const response = await fetch('../api/time_validator_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ 
                    action: 'validate_checkout',
                    student_id: studentId,
                    shift: studentData.shift
                })
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const validationResult = await response.json();
            console.log('Checkout validation result:', validationResult);
            
            if (!validationResult.valid) {
                console.log('Checkout validation failed:', validationResult.error);
                this.showValidationError(
                    validationResult.error || 'Check-out not allowed',
                    `Current time: ${this.formatTime12Hour(validationResult.current_time)}<br>
                     Check-out window: ${this.formatTime12Hour(validationResult.checkout_start)} - ${this.formatTime12Hour(validationResult.checkout_end)}<br>
                     Shift: ${validationResult.shift}`
                );
                // Update status to show they're checked in but can't checkout yet
                this.updateStatusBadge('checked_in');
                return;
            }
            
            // If validation passes, show checkout confirmation
            this.showCheckoutConfirmation(studentId, studentData);
            
        } catch (error) {
            console.error('Checkout validation failed:', error);
            // If checkout validation fails due to missing settings, show error
            if (error.message && error.message.includes('not properly configured')) {
                this.showValidationError(
                    'Timing Settings Not Configured',
                    'Please configure all timing settings in the admin panel before using attendance features.<br><br>' +
                    '<a href="settings.php" class="btn btn-primary btn-sm mt-2">Go to Settings</a>'
                );
                return;
            }
            // For other validation errors, proceed without validation
            this.showCheckoutConfirmation(studentId, studentData);
        }
    }
    
    showCheckoutConfirmation(studentId, studentData) {
        // Update modal content for checkout
        document.getElementById('preview-name').textContent = studentData.name || 'Unknown Student';
        document.getElementById('preview-id').textContent = studentId;
        document.getElementById('preview-program').textContent = studentData.program || 'N/A';
        document.getElementById('preview-shift').textContent = studentData.shift || 'N/A';
        
        // Update status to show checkout
        const statusElement = document.querySelector('.status-badge');
        if (statusElement) {
            statusElement.textContent = 'Check-out';
            statusElement.className = 'status-badge bg-warning text-white';
        }
        
        // Update confirm button
        const confirmBtn = document.getElementById('preview-confirm');
        if (confirmBtn) {
            confirmBtn.innerHTML = '<i class="bx bx-log-out me-2"></i>Confirm Check-out';
            confirmBtn.className = 'btn btn-warning btn-lg w-100';
        }
        
        // Show auto-confirm timer for checkout
        this.showAutoConfirmTimer();
        this.setupAutoConfirm(studentId);
    }
    
    async validateAttendanceTime(studentId, studentData) {
        try {
            // Check if student is active and not graduated
            if (!studentData.is_active) {
                this.showValidationError('Student account is inactive', 'This student cannot check in/out at this time.');
                return;
            }

            if (studentData.is_graduated) {
                this.showValidationError('Student has graduated', 'Graduated students cannot check in/out.');
                return;
            }
            
            // Check if student is already checked in - allow checkout
            if (studentData.current_status === 'checked_in') {
                console.log('Student is checked in, validating checkout time...');
                // Validate checkout time instead of showing error
                await this.validateCheckoutTime(studentId, studentData);
                return;
            }
            
            // Debug: Check what settings are in database
            try {
                const debugResponse = await fetch('../api/time_validator_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'debug_settings'
                    })
                });
                const debugData = await debugResponse.json();
                console.log('Debug settings:', debugData);
            } catch (e) {
                console.log('Debug failed:', e);
            }
            
            // Validate time window
            try {
                const response = await fetch('../api/time_validator_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ 
                        action: 'validate_checkin',
                        student_id: studentId,
                        shift: studentData.shift
                    })
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const validationResult = await response.json();
                
                if (!validationResult.valid) {
                    this.showValidationError(
                        validationResult.error || 'Check-in not allowed',
                        `Current time: ${this.formatTime12Hour(validationResult.current_time)}<br>
                         Check-in window: ${this.formatTime12Hour(validationResult.checkin_start)} - ${this.formatTime12Hour(validationResult.checkin_end)}<br>
                         Shift: ${validationResult.shift}`
                    );
                    return;
                }
                
            } catch (error) {
                console.error('Time validation failed:', error);
                // If time validation fails due to missing settings, show error
                if (error.message && error.message.includes('not properly configured')) {
                    this.showValidationError(
                        'Timing Settings Not Configured',
                        'Please configure all timing settings in the admin panel before using attendance features.<br><br>' +
                        '<a href="settings.php" class="btn btn-primary btn-sm mt-2">Go to Settings</a>'
                    );
                    return;
                }
                // For other validation errors, proceed without validation
                this.showAutoConfirmTimer();
                this.setupAutoConfirm(studentId);
                return;
            }
            
            // If validation passes, show auto-confirm timer
            this.showAutoConfirmTimer();
            this.setupAutoConfirm(studentId);
            
        } catch (error) {
            console.error('Time validation error:', error);
            this.showValidationError('Validation error', 'Unable to validate attendance time. Please try again.');
        }
    }
    
    showValidationError(title, message) {
        // Hide auto-confirm timer
        document.getElementById('auto-confirm-timer-container').classList.add('d-none');
        
        // Show validation errors
        document.getElementById('time-validation-errors').classList.remove('d-none');
        document.getElementById('validation-error-message').innerHTML = message;
        
        // Disable confirm button
        const confirmBtn = document.getElementById('preview-confirm');
        if (confirmBtn) {
            confirmBtn.disabled = true;
            confirmBtn.classList.add('disabled');
        }
    }
    
    showAutoConfirmTimer() {
        // Hide validation errors
        document.getElementById('time-validation-errors').classList.add('d-none');
        
        // Show auto-confirm timer
        document.getElementById('auto-confirm-timer-container').classList.remove('d-none');
        
        // Enable confirm button
        const confirmBtn = document.getElementById('preview-confirm');
        if (confirmBtn) {
            confirmBtn.disabled = false;
            confirmBtn.classList.remove('disabled');
        }
    }
    
    updateStatusBadge(status) {
        const statusElement = document.querySelector('#preview-status .badge');
        if (!statusElement) return;
        
        // Remove all status classes
        statusElement.className = 'badge fs-6 px-3 py-2';
        
        switch (status) {
            case 'checked_in':
            case 'Check-in':
                statusElement.textContent = 'Checked In';
                statusElement.classList.add('bg-success');
                break;
            case 'Present':
            case 'checked_out':
                statusElement.textContent = 'Present (Checked Out)';
                statusElement.classList.add('bg-info');
                break;
            case 'Absent':
                statusElement.textContent = 'Absent';
                statusElement.classList.add('bg-danger');
                break;
            case 'Invalid':
                statusElement.textContent = 'Invalid ID';
                statusElement.classList.add('bg-danger');
                break;
            default:
                statusElement.textContent = 'Not checked in today';
                statusElement.classList.add('bg-secondary');
        }
    }
    
    setupAutoConfirm(studentId) {
        let timeLeft = 2; // Set to 2 seconds
        const timerElement = document.getElementById('auto-confirm-timer');
        
        const timer = setInterval(() => {
            timeLeft--;
            timerElement.textContent = timeLeft;
            
            if (timeLeft <= 0) {
                clearInterval(timer);
                this.confirmAttendance(studentId);
            }
        }, 1000);
        
        // Store timer for manual confirm/cancel
        this.autoConfirmTimer = timer;
    }
    
    async confirmAttendance(studentId) {
        try {
            // Determine if this is check-in or check-out based on current status
            const statusResponse = await fetch('../api/checkin_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'get_status',
                    student_id: studentId
                })
            });
            const statusData = await statusResponse.json();
            
            let action = 'checkin';
            let successMessage = 'Check-in recorded successfully!';
            
            if (statusData.success && statusData.data && statusData.data.status === 'Checked-in') {
                action = 'checkout';
                successMessage = 'Check-out recorded successfully!';
            }
            
            const requestBody = { 
                student_id: studentId,
                action: action
            };
            
            console.log('Sending attendance request:', requestBody);
            
            const response = await fetch('../api/attendance.php?action=save_attendance', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    'X-CSRF-Token': this.csrfToken
                    },
                body: JSON.stringify(requestBody)
                });
            
                const data = await response.json();
                
                console.log('Attendance API response:', data);
                console.log('Response status:', response.status);
                
                if (data.success) {
                this.showMessage(successMessage, 'success');
                this.playSuccessSound();
                this.showFlashEffect();
                this.loadData();
                } else {
                this.showMessage(data.message || data.error || 'Failed to record attendance', 'error');
            }
            
            } catch (error) {
            this.showMessage('Network error occurred', 'error');
        }
        
        // Close modal
        const modalElement = document.getElementById('studentPreviewModal');
        const modal = bootstrap.Modal.getInstance(modalElement);
        modal.hide();
        
        // Return focus to scanner input after modal closes
        modalElement.addEventListener('hidden.bs.modal', () => {
            this.focusInput();
        }, { once: true });
    }
    
    async handleManualSubmit() {
            const studentId = document.getElementById('manual-student-id').value.trim();
            const notes = document.getElementById('manual-notes').value.trim();

            if (!studentId) {
            this.showMessage('Please enter Student ID', 'warning');
                return;
            }

            try {
            const response = await fetch('../api/attendance.php?action=save_attendance', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    'X-CSRF-Token': this.csrfToken
                    },
                body: JSON.stringify({ student_id: studentId, notes })
                });
            
                const data = await response.json();
                
                if (data.success) {
                this.showMessage('Attendance recorded successfully!', 'success');
                    document.getElementById('manual-student-id').value = '';
                    document.getElementById('manual-notes').value = '';
                this.loadData();
                } else {
                this.showMessage(data.error || 'Failed to record attendance', 'error');
            }
            
        } catch (error) {
            this.showMessage('Network error occurred', 'error');
        }
    }
    
    async loadData() {
        await Promise.all([
            this.loadRecentScans(),
            this.updateStats()
        ]);
    }
    
    async loadRecentScans() {
        try {
            const response = await fetch('../api/attendance.php?action=get_recent_scans');
            const data = await response.json();
            
            const container = document.getElementById('recent-scans');
            
            if (data.success && data.data && data.data.length > 0) {
                container.innerHTML = data.data.map(scan => `
                    <div class="scan-item p-3 mb-2 rounded">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-bold">${scan.student_name || 'Unknown Student'}</div>
                                <small class="text-muted">${scan.student_id || 'Unknown ID'}</small>
                            </div>
                            <div class="text-end">
                                <span class="status-badge bg-${scan.status === 'Present' ? 'success' : 'warning'} text-white">
                                    ${scan.status || 'Unknown'}
                                </span>
                                <div class="small text-muted mt-1">${scan.timestamp ? new Date(scan.timestamp).toLocaleTimeString() : 'Unknown time'}</div>
                            </div>
                        </div>
                    </div>
                `).join('');
                } else {
                container.innerHTML = `
                    <div class="text-center text-muted py-4">
                        <i class="bx bx-history bx-lg mb-2 d-block"></i>
                        <div>No scans yet</div>
                    </div>
                `;
            }
        } catch (error) {
            console.error('Error loading recent scans:', error);
        }
    }
    
    async updateStats() {
        try {
            const response = await fetch('../api/attendance.php?action=get_scan_stats');
            const data = await response.json();
            
            if (data.success && data.data) {
                document.getElementById('scan-count').textContent = data.data.today.total_scans || 0;
                document.getElementById('present-count').textContent = data.data.today.present || 0;
                document.getElementById('last-scan-time').textContent = data.data.today.last_scan_time || '-';
            }
        } catch (error) {
            console.error('Error loading stats:', error);
        }
    }
    
    async autoMarkAbsent() {
        const confirmed = confirm('Mark all students who haven\'t checked in as absent?');
        if (!confirmed) return;

            try {
            const response = await fetch('../api/attendance.php?action=auto_mark_absent', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    'X-CSRF-Token': this.csrfToken
                }
                });
            
                const data = await response.json();
                
                if (data.success) {
                this.showMessage(data.message, 'success');
                this.loadData();
                } else {
                this.showMessage(data.error || 'Failed to mark absent', 'error');
                }
            } catch (error) {
            this.showMessage('Network error occurred', 'error');
        }
    }
    
    async markAllAbsent() {
        const confirmed = confirm('This will mark ALL students as absent. Are you sure?');
        if (!confirmed) return;
        
        try {
            const response = await fetch('../api/attendance.php?action=mark_absent_students', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    'X-CSRF-Token': this.csrfToken
                }
                });
            
                const data = await response.json();
                
                if (data.success) {
                this.showMessage(data.message, 'success');
                this.loadData();
                } else {
                this.showMessage(data.error || 'Failed to mark absent', 'error');
                }
            } catch (error) {
            this.showMessage('Network error occurred', 'error');
        }
    }
    
    showMessage(message, type = 'info') {
        showAlert(message, type);
    }
    
    playSuccessSound() {
        if (this.soundEnabled) {
            // Create audio context for success sound
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            
            oscillator.frequency.setValueAtTime(800, audioContext.currentTime);
            oscillator.frequency.setValueAtTime(1000, audioContext.currentTime + 0.1);
            
            gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.3);
            
            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + 0.3);
        }
    }
    
    showFlashEffect() {
        if (this.flashEnabled) {
            const flash = document.getElementById('flash-overlay');
            flash.style.display = 'block';
            setTimeout(() => {
                flash.style.display = 'none';
            }, 300);
        }
    }
    
    focusInput() {
        this.scanInput.focus();
    }
    
    formatTime12Hour(time24) {
        if (!time24) return 'N/A';
        
        // Convert 24-hour format to 12-hour format
        const timeStr = time24.toString().trim();
        
        // If already in 12-hour format, return as is
        if (timeStr.includes('AM') || timeStr.includes('PM')) {
            return timeStr;
        }
        
        // Extract hours and minutes
        const timeParts = timeStr.split(' ');
        const timeOnly = timeParts[0]; // Get just the time part
        
        const timeComponents = timeOnly.split(':');
        if (timeComponents.length < 2) return time24;
        
        let hours = parseInt(timeComponents[0], 10);
        const minutes = timeComponents[1];
        
        // Determine AM/PM
        const period = hours >= 12 ? 'PM' : 'AM';
        
        // Convert to 12-hour format
        if (hours === 0) {
            hours = 12; // Midnight
        } else if (hours > 12) {
            hours = hours - 12;
        }
        
        // Format with leading zero for single digits
        const formattedHours = hours.toString().padStart(1, '0');
        
        return `${formattedHours}:${minutes} ${period}`;
    }
    
    async checkTimingSettings() {
        try {
            // Check if timing settings are properly configured
            const response = await fetch('../api/time_validator_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'debug_settings'
                })
            });
            
            if (!response.ok) {
                throw new Error('Failed to check timing settings');
            }
            
            const data = await response.json();
            
            if (!data.success) {
                this.showMessage('Timing settings not configured. Please configure all timing settings in admin panel.', 'warning');
                this.scannerStatus.innerHTML = '<i class="bx bx-error-circle me-1"></i>Settings Required';
                this.scannerStatus.className = 'badge bg-warning px-3 py-2';
            } else {
                this.scannerStatus.innerHTML = '<i class="bx bx-check-circle me-1"></i>Ready';
                this.scannerStatus.className = 'badge bg-success px-3 py-2';
            }
            
        } catch (error) {
            console.error('Error checking timing settings:', error);
            this.showMessage('Unable to verify timing settings. Please check admin configuration.', 'warning');
            this.scannerStatus.innerHTML = '<i class="bx bx-error-circle me-1"></i>Config Error';
            this.scannerStatus.className = 'badge bg-danger px-3 py-2';
        }
    }
    
    loadSettings() {
        // Load settings from localStorage
        const settings = JSON.parse(localStorage.getItem('scannerSettings') || '{}');
        
        this.scanDebounce = settings.debounce || 800;
        this.duplicateSuppression = settings.duplicate || 3000;
        this.autoConfirmDelay = settings.autoConfirm || 3000;
        this.soundEnabled = settings.sound !== false;
        this.flashEnabled = settings.flash !== false;
        this.vibrationEnabled = settings.vibration || false;
        
        // Update UI
        document.getElementById('config-debounce').value = this.scanDebounce;
        document.getElementById('config-duplicate').value = this.duplicateSuppression;
        document.getElementById('config-autoconfirm').value = this.autoConfirmDelay;
        document.getElementById('config-sound').checked = this.soundEnabled;
        document.getElementById('config-flash').checked = this.flashEnabled;
        document.getElementById('config-vibration').checked = this.vibrationEnabled;
    }
    
    saveSettings() {
        const settings = {
            debounce: parseInt(document.getElementById('config-debounce').value),
            duplicate: parseInt(document.getElementById('config-duplicate').value),
            autoConfirm: parseInt(document.getElementById('config-autoconfirm').value),
            sound: document.getElementById('config-sound').checked,
            flash: document.getElementById('config-flash').checked,
            vibration: document.getElementById('config-vibration').checked
        };
        
        localStorage.setItem('scannerSettings', JSON.stringify(settings));
        
        // Update current settings
        this.scanDebounce = settings.debounce;
        this.duplicateSuppression = settings.duplicate;
        this.autoConfirmDelay = settings.autoConfirm;
        this.soundEnabled = settings.sound;
        this.flashEnabled = settings.flash;
        this.vibrationEnabled = settings.vibration;
        
        this.showMessage('Settings saved successfully!', 'success');
        
        // Close modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('scannerConfigModal'));
        modal.hide();
    }
}

// Global functions for compatibility
function loadRecentScans() {
    if (window.scanner) {
        window.scanner.loadRecentScans();
    }
}

function updateScanCount() {
    if (window.scanner) {
        window.scanner.updateStats();
    }
}

function refreshData() {
    if (window.scanner) {
        window.scanner.loadData();
    }
}

// Initialize scanner when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.scanner = new QRScanner();
    
    // Set up modal event listeners
    document.getElementById('preview-confirm').addEventListener('click', () => {
        if (window.scanner.autoConfirmTimer) {
            clearInterval(window.scanner.autoConfirmTimer);
        }
        // Get student ID from modal and confirm
        const studentId = document.getElementById('preview-id').textContent;
        window.scanner.confirmAttendance(studentId);
    });
    
    document.getElementById('preview-cancel').addEventListener('click', () => {
        if (window.scanner.autoConfirmTimer) {
            clearInterval(window.scanner.autoConfirmTimer);
        }
        const modal = bootstrap.Modal.getInstance(document.getElementById('studentPreviewModal'));
        modal.hide();
    });
});
</script>