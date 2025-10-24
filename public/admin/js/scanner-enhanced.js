/**
 * Enhanced Scanner Module for QR Attendance System
 * Includes: Audio feedback, visual effects, student preview, diagnostics
 */

(function() {
    'use strict';

    // ============================================================
    // Configuration Manager
    // ============================================================
    const ScannerConfig = {
        settings: {
            debounceMs: 800,
            duplicateSuppressionMs: 3000,
            autoConfirmDelayMs: 2000,
            validPattern: /^[A-Za-z0-9-_]{4,32}$/,
            soundEnabled: true,
            visualFlashEnabled: true,
            previewEnabled: true,
            useSweetAlertPreview: false
        },
        
        load() {
            try {
                const saved = localStorage.getItem('scannerConfig');
                if (saved) {
                    Object.assign(this.settings, JSON.parse(saved));
                }
            } catch (e) {
                console.error('Failed to load scanner config:', e);
            }
        },
        
        save() {
            try {
                localStorage.setItem('scannerConfig', JSON.stringify(this.settings));
            } catch (e) {
                console.error('Failed to save scanner config:', e);
            }
        },
        
        updateFromUI() {
            this.settings.debounceMs = parseInt(document.getElementById('config-debounce')?.value || 800);
            this.settings.duplicateSuppressionMs = parseInt(document.getElementById('config-duplicate')?.value || 3000);
            this.settings.autoConfirmDelayMs = parseInt(document.getElementById('config-autoconfirm')?.value || 2000);
            this.settings.soundEnabled = document.getElementById('config-sound')?.checked ?? true;
            this.settings.visualFlashEnabled = document.getElementById('config-flash')?.checked ?? true;
            this.settings.useSweetAlertPreview = document.getElementById('config-use-sweetalert-preview')?.checked ?? false;
            this.save();
        },
        
        loadToUI() {
            if (document.getElementById('config-debounce')) document.getElementById('config-debounce').value = this.settings.debounceMs;
            if (document.getElementById('config-duplicate')) document.getElementById('config-duplicate').value = this.settings.duplicateSuppressionMs;
            if (document.getElementById('config-autoconfirm')) document.getElementById('config-autoconfirm').value = this.settings.autoConfirmDelayMs;
            if (document.getElementById('config-sound')) document.getElementById('config-sound').checked = this.settings.soundEnabled;
            if (document.getElementById('config-flash')) document.getElementById('config-flash').checked = this.settings.visualFlashEnabled;
            if (document.getElementById('config-use-sweetalert-preview')) document.getElementById('config-use-sweetalert-preview').checked = this.settings.useSweetAlertPreview;
        }
    };

    // ============================================================
    // Audio Manager
    // ============================================================
    const AudioManager = {
        sounds: {},
        initialized: false,
        
        init() {
            if (this.initialized) return;
            
            try {
                this.sounds.success = new Audio('assets/sounds/success.mp3');
                this.sounds.checkout = new Audio('assets/sounds/checkout.mp3');
                this.sounds.error = new Audio('assets/sounds/error.mp3');
                this.sounds.warning = new Audio('assets/sounds/warning.mp3');
                
                // Preload all sounds
                Object.values(this.sounds).forEach(sound => {
                    sound.load();
                });
                
                this.initialized = true;
                DiagnosticMonitor.log('Audio system initialized', 'success');
            } catch (e) {
                DiagnosticMonitor.log('Audio initialization failed: ' + e.message, 'warning');
            }
        },
        
        play(type) {
            if (!ScannerConfig.settings.soundEnabled) return;
            
            const sound = this.sounds[type];
            if (sound) {
                try {
                    sound.currentTime = 0;
                    sound.play().catch(err => {
                        DiagnosticMonitor.log('Audio play failed: ' + err.message, 'warning');
                    });
                } catch (e) {
                    console.error('Sound play error:', e);
                }
            }
        }
    };

    // ============================================================
    // Visual Feedback Manager
    // ============================================================
    const VisualFeedback = {
        showFlash(type) {
            if (!ScannerConfig.settings.visualFlashEnabled) return;
            
            const overlay = document.getElementById('flash-overlay');
            if (!overlay) return;
            
            overlay.className = '';
            overlay.style.display = 'block';
            
            // Force reflow
            void overlay.offsetWidth;
            
            overlay.classList.add('flash-' + type);
            
            setTimeout(() => {
                overlay.style.display = 'none';
                overlay.className = '';
            }, 250);
        },
        
        pulseStatus() {
            const badge = document.getElementById('scanner-status');
            if (badge) {
                badge.classList.add('status-pulse');
                setTimeout(() => badge.classList.remove('status-pulse'), 1000);
            }
        },
        
        setStatus(text, type) {
            const badge = document.getElementById('scanner-status');
            if (!badge) return;
            
            badge.textContent = text;
            badge.className = 'badge ';
            
            switch(type) {
                case 'error':
                    badge.classList.add('bg-label-danger');
                    break;
                case 'success':
                    badge.classList.add('bg-label-success');
                    break;
                case 'warning':
                    badge.classList.add('bg-label-warning');
                    break;
                default:
                    badge.classList.add('bg-label-info');
            }
            
            this.pulseStatus();
        },
        
        showFeedback(message, type) {
            // Use SweetAlert2 for notifications
            const iconMap = {
                'success': 'success',
                'error': 'error',
                'warning': 'warning',
                'info': 'info'
            };
            
            Swal.fire({
                icon: iconMap[type] || 'info',
                title: message,
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                toast: true,
                position: 'top-end'
            });
        }
    };

    // ============================================================
    // Student Preview Manager
    // ============================================================
    const StudentPreview = {
        currentStudent: null,
        confirmCallback: null,
        autoConfirmTimer: null,
        
        async show(studentId, onConfirm) {
            if (!ScannerConfig.settings.previewEnabled) {
                // Skip preview, confirm immediately
                if (onConfirm) onConfirm(studentId);
                return;
            }
            
            this.confirmCallback = onConfirm;
            DiagnosticMonitor.log('Loading student preview: ' + studentId, 'info');
            
            // Check configuration to determine which modal to use
            if (ScannerConfig.settings.useSweetAlertPreview) {
                await this.showSweetAlertPreview(studentId);
            } else {
                await this.showBootstrapModal(studentId);
            }
        },

        async showBootstrapModal(studentId) {
            try {
                const response = await fetch(`api/attendance.php?action=get_student_preview&student_id=${encodeURIComponent(studentId)}`, {
                    credentials: 'same-origin'
                });
                const data = await response.json();
                
                console.log('API Response:', data);
                console.log('Response Status:', response.status);
                
                if (data.success && data.student) {
                    this.currentStudent = data.student;
                    this.renderPreview(data.student);
                    DiagnosticMonitor.log('Student preview loaded: ' + data.student.name, 'success');
                    
                    // Show Bootstrap modal
                    const modal = new bootstrap.Modal(document.getElementById('studentPreviewModal'));
                    modal.show();
                    
                    // Auto-confirm timer (only if auto-confirm is enabled)
                    if (ScannerConfig.settings.autoConfirmDelayMs > 0) {
                        this.startAutoConfirmTimer();
                    }
                } else {
                    throw new Error(data.error || 'Student not found');
                }
            } catch (error) {
                DiagnosticMonitor.log('Preview load failed: ' + error.message, 'error');
                
                // Show SweetAlert2 error popup
                Swal.fire({
                    icon: 'error',
                    title: 'Student Not Found',
                    text: `Student ID: ${studentId}`,
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#dc3545'
                });
                
                VisualFeedback.showFlash('error');
                AudioManager.play('error');
            }
        },

        async showSweetAlertPreview(studentId) {
            try {
                // Show loading state
                Swal.fire({
                    title: 'Loading...',
                    text: 'Fetching student information',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                const response = await fetch(`api/attendance.php?action=get_student_preview&student_id=${encodeURIComponent(studentId)}`, {
                    credentials: 'same-origin'
                });
                const data = await response.json();
                
                console.log('SweetAlert2 API Response:', data);
                
                if (data.success && data.student) {
                    this.currentStudent = data.student;
                    this.renderSweetAlertPreview(data.student);
                    DiagnosticMonitor.log('SweetAlert2 student preview loaded: ' + data.student.name, 'success');
                    
                    // Auto-confirm timer (only if auto-confirm is enabled)
                    if (ScannerConfig.settings.autoConfirmDelayMs > 0) {
                        this.startSweetAlertAutoConfirmTimer(data.student);
                    }
                } else {
                    throw new Error(data.error || 'Student not found');
                }
            } catch (error) {
                DiagnosticMonitor.log('SweetAlert2 preview load failed: ' + error.message, 'error');
                
                Swal.fire({
                    icon: 'error',
                    title: 'Student Not Found',
                    text: `Student ID: ${studentId}`,
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#dc3545'
                });
                
                VisualFeedback.showFlash('error');
                AudioManager.play('error');
            }
        },
        
        renderPreview(student) {
            // Update modal content instead of inline card
            const preview = document.getElementById('studentPreviewModal');
            if (!preview) return;
            
            // Set photo
            const photo = document.getElementById('preview-photo');
            if (photo) {
                photo.src = student.photo_url || 'assets/img/default-avatar.svg';
                photo.onerror = function() {
                    this.src = 'assets/img/default-avatar.svg';
                };
            }
            
            // Set student info
            if (document.getElementById('preview-name')) document.getElementById('preview-name').textContent = student.name;
            if (document.getElementById('preview-id')) document.getElementById('preview-id').textContent = student.id;
            if (document.getElementById('preview-program')) document.getElementById('preview-program').textContent = student.program || '-';
            if (document.getElementById('preview-shift')) document.getElementById('preview-shift').textContent = student.shift || '-';
            
            // Set status
            let statusHtml = '';
            if (student.check_out_time) {
                const time = new Date(student.check_out_time).toLocaleTimeString('en-US', {hour: '2-digit', minute: '2-digit'});
                statusHtml = `<span class="badge bg-success">Checked out at ${time}</span>`;
            } else if (student.check_in_time) {
                const time = new Date(student.check_in_time).toLocaleTimeString('en-US', {hour: '2-digit', minute: '2-digit'});
                statusHtml = `<span class="badge bg-info">Checked in at ${time}</span>`;
            } else {
                statusHtml = `<span class="badge bg-secondary">Not checked in today</span>`;
            }
            
            const statusEl = document.getElementById('preview-status');
            if (statusEl) statusEl.innerHTML = statusHtml;
            
            // Reset button visibility for new scan
            const confirmBtn = document.getElementById('preview-confirm');
            const cancelBtn = document.getElementById('preview-cancel');
            const btnGroup = document.querySelector('.btn-group.w-100.mb-3');
            const recordedMsg = preview.querySelector('.attendance-recorded-msg');
            
            // Show buttons and button group
            if (confirmBtn) {
                confirmBtn.style.display = 'block';
                confirmBtn.classList.remove('d-none');
            }
            if (cancelBtn) {
                cancelBtn.style.display = 'block';
                cancelBtn.classList.remove('d-none');
            }
            if (btnGroup) {
                btnGroup.style.display = 'block';
                btnGroup.classList.remove('d-none');
            }
            if (recordedMsg) recordedMsg.style.display = 'none';
            
            // Show preview with animation
            preview.style.display = 'block';
            preview.classList.add('slide-in');
        },

        renderSweetAlertPreview(student) {
            // Create HTML content for SweetAlert2 modal
            const statusHtml = student.check_out_time ? 
                `<span class="badge bg-success">Checked out at ${new Date(student.check_out_time).toLocaleTimeString('en-US', {hour: '2-digit', minute: '2-digit'})}</span>` :
                student.check_in_time ? 
                `<span class="badge bg-info">Checked in at ${new Date(student.check_in_time).toLocaleTimeString('en-US', {hour: '2-digit', minute: '2-digit'})}</span>` :
                `<span class="badge bg-secondary">Not checked in today</span>`;

            const htmlContent = `
                <div class="text-center">
                    <img src="${student.photo_url || 'assets/img/default-avatar.svg'}" 
                         class="rounded-circle mb-3" width="120" height="120" alt="Student Photo"
                         onerror="this.src='assets/img/default-avatar.svg'">
                    <h5 class="mb-1">${student.name}</h5>
                    <p class="text-muted mb-1"><strong>${student.id}</strong></p>
                    <p class="text-muted mb-2">${student.program || '-'} • ${student.shift || '-'}</p>
                    <div class="mb-3">${statusHtml}</div>
                    <div class="alert alert-light border p-2 small mb-0">
                        <i class="bx bx-time-five me-1"></i> Auto-confirms in <span id="sweetalert-timer">2</span> seconds
                    </div>
                </div>
            `;

            Swal.fire({
                title: 'Student Preview',
                html: htmlContent,
                showConfirmButton: true,
                showDenyButton: true,
                confirmButtonText: 'Confirm',
                denyButtonText: 'Cancel',
                confirmButtonColor: '#28a745',
                denyButtonColor: '#6c757d',
                allowOutsideClick: false,
                timer: ScannerConfig.settings.autoConfirmDelayMs,
                timerProgressBar: true,
                didOpen: () => {
                    // Start timer countdown
                    this.startSweetAlertTimer();
                },
                didClose: () => {
                    // Clear timer when modal closes
                    this.clearAutoConfirm();
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    this.confirm();
                } else if (result.isDenied) {
                    this.cancel();
                } else if (result.dismiss === Swal.DismissReason.timer) {
                    // Auto-confirm when timer expires
                    this.confirm();
                }
            });
        },

        startSweetAlertTimer() {
            const timerElement = document.getElementById('sweetalert-timer');
            if (!timerElement) return;

            let timeLeft = Math.ceil(ScannerConfig.settings.autoConfirmDelayMs / 1000);
            timerElement.textContent = timeLeft;

            this.timerInterval = setInterval(() => {
                timeLeft--;
                if (timerElement) {
                    timerElement.textContent = timeLeft;
                }
                if (timeLeft <= 0) {
                    clearInterval(this.timerInterval);
                    this.timerInterval = null;
                }
            }, 1000);
        },

        startSweetAlertAutoConfirmTimer(student) {
            if (ScannerConfig.settings.autoConfirmDelayMs <= 0) return;

            this.autoConfirmTimer = setTimeout(() => {
                console.log('SweetAlert2 auto-confirm timer expired');
                this.confirm();
            }, ScannerConfig.settings.autoConfirmDelayMs);
        },
        
        confirm() {
            this.clearAutoConfirm();
            
            if (this.confirmCallback && this.currentStudent) {
                this.confirmCallback(this.currentStudent.id);
            }
            
            // Hide buttons immediately after confirmation
            this.hideButtonsAfterConfirm();
            
            // Don't hide the preview - keep it visible until new scan
            // this.hide();
        },
        
        cancel() {
            this.clearAutoConfirm();
            DiagnosticMonitor.log('Student preview cancelled', 'info');
            this.hide();
        },
        
        hide() {
            // Hide Bootstrap modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('studentPreviewModal'));
            if (modal) {
                modal.hide();
            }
            
            this.currentStudent = null;
            this.confirmCallback = null;
        },
        
        clearAutoConfirm() {
            if (this.autoConfirmTimer) {
                clearTimeout(this.autoConfirmTimer);
                this.autoConfirmTimer = null;
            }
            if (this.timerInterval) {
                clearInterval(this.timerInterval);
                this.timerInterval = null;
            }
        },
        
        startAutoConfirmTimer() {
            this.clearAutoConfirm();
            
            let timeLeft = Math.ceil(ScannerConfig.settings.autoConfirmDelayMs / 1000);
            const timerElement = document.getElementById('auto-confirm-timer');
            
            // Update timer display immediately
            if (timerElement) {
                timerElement.textContent = timeLeft;
            }
            
            // Update timer every second
            this.timerInterval = setInterval(() => {
                timeLeft--;
                if (timerElement) {
                    timerElement.textContent = timeLeft;
                }
                
                if (timeLeft <= 0) {
                    this.clearAutoConfirm();
                    this.confirm();
                }
            }, 1000);
            
            // Set the main timeout
            this.autoConfirmTimer = setTimeout(() => {
                this.confirm();
            }, ScannerConfig.settings.autoConfirmDelayMs);
        },
        
        updateStudentPreviewStatus(attendanceData) {
            if (!this.currentStudent) return;
            
            console.log('Updating student preview status:', attendanceData);
            
            // Update the student's status based on the attendance data
            const statusEl = document.getElementById('preview-status');
            if (statusEl) {
                let statusHtml = '';
                if (attendanceData.mode === 'checkout') {
                    const time = new Date().toLocaleTimeString('en-US', {hour: '2-digit', minute: '2-digit'});
                    statusHtml = `<span class="badge bg-success">Checked out at ${time}</span>`;
                } else if (attendanceData.mode === 'checkin') {
                    const time = new Date().toLocaleTimeString('en-US', {hour: '2-digit', minute: '2-digit'});
                    statusHtml = `<span class="badge bg-info">Checked in at ${time}</span>`;
                }
                
                if (statusHtml) {
                    statusEl.innerHTML = statusHtml;
                }
            }
            
            // Auto-close modal after 2 seconds
            setTimeout(() => {
                const modal = bootstrap.Modal.getInstance(document.getElementById('studentPreviewModal'));
                if (modal) {
                    modal.hide();
                }
            }, 2000);
            
            // Hide the confirm/cancel buttons since attendance is recorded
            const confirmBtn = document.getElementById('preview-confirm');
            const cancelBtn = document.getElementById('preview-cancel');
            const btnGroup = document.querySelector('.btn-group.w-100.mb-3');
            
            console.log('Hiding buttons - confirmBtn:', confirmBtn, 'cancelBtn:', cancelBtn, 'btnGroup:', btnGroup);
            
            // Hide buttons and button group
            if (confirmBtn) {
                confirmBtn.style.display = 'none';
                confirmBtn.classList.add('d-none');
                console.log('Hidden confirm button');
            }
            if (cancelBtn) {
                cancelBtn.style.display = 'none';
                cancelBtn.classList.add('d-none');
                console.log('Hidden cancel button');
            }
            if (btnGroup) {
                btnGroup.style.display = 'none';
                btnGroup.classList.add('d-none');
                console.log('Hidden button group');
            }
            
            // Hide the auto-confirm timer since attendance is recorded
            const timerAlert = document.querySelector('.alert-light.border');
            if (timerAlert) {
                timerAlert.style.display = 'none';
            }
            
            // Show a "Attendance Recorded" message instead
            const preview = document.getElementById('student-preview');
            if (preview) {
                // Create or update the recorded message
                let recordedMsg = preview.querySelector('.attendance-recorded-msg');
                if (!recordedMsg) {
                    recordedMsg = document.createElement('div');
                    recordedMsg.className = 'attendance-recorded-msg alert alert-success mt-3';
                    recordedMsg.innerHTML = '<i class="bx bx-check-circle me-2"></i>Attendance recorded successfully!';
                    preview.querySelector('.card-body').appendChild(recordedMsg);
                }
                recordedMsg.style.display = 'block';
            }
        },
        
        hideButtonsAfterConfirm() {
            console.log('Hiding buttons after confirm...');
            
            // Directly target the buttons by ID
            const confirmBtn = document.getElementById('preview-confirm');
            const cancelBtn = document.getElementById('preview-cancel');
            const btnGroup = document.querySelector('.btn-group.w-100.mb-3');
            
            if (confirmBtn) {
                confirmBtn.style.display = 'none';
                confirmBtn.classList.add('d-none');
                console.log('Hidden confirm button after confirm');
            }
            
            if (cancelBtn) {
                cancelBtn.style.display = 'none';
                cancelBtn.classList.add('d-none');
                console.log('Hidden cancel button after confirm');
            }
            
            if (btnGroup) {
                btnGroup.style.display = 'none';
                btnGroup.classList.add('d-none');
                console.log('Hidden button group after confirm');
            }
            
            // Hide timer
            const timerAlert = document.querySelector('.alert-light.border');
            if (timerAlert) {
                timerAlert.style.display = 'none';
                console.log('Hidden timer after confirm');
            }
        }
    };

    // ============================================================
    // Diagnostic Monitor
    // ============================================================
    const DiagnosticMonitor = {
        logs: [],
        maxLogs: 50,
        
        log(message, type = 'info') {
            const entry = {
                timestamp: new Date().toISOString(),
                message: message,
                type: type
            };
            
            this.logs.unshift(entry);
            if (this.logs.length > this.maxLogs) {
                this.logs.pop();
            }
            
            this.updateDisplay();
            
            // Also log to console in development
            const consoleMethod = type === 'error' ? 'error' : type === 'warning' ? 'warn' : 'log';
            console[consoleMethod]('[Scanner]', message);
        },
        
        updateDisplay() {
            const container = document.getElementById('diagnostic-logs');
            if (!container) return;
            
            container.innerHTML = this.logs.map(log => {
                const time = new Date(log.timestamp).toLocaleTimeString();
                return `<div class="log-entry log-${log.type}">
                    <span class="log-time">${time}</span>
                    <span class="log-message">${this.escapeHtml(log.message)}</span>
                </div>`;
            }).join('');
        },
        
        async loadSystemStatus() {
            try {
                const response = await fetch('api/attendance.php?action=get_diagnostics', {
                    credentials: 'same-origin'
                });
                const data = await response.json();
                
                if (data.success && data.diagnostics) {
                    this.displaySystemStatus(data.diagnostics);
                }
            } catch (error) {
                this.log('Failed to load system status: ' + error.message, 'error');
            }
        },
        
        displaySystemStatus(diagnostics) {
            const container = document.getElementById('system-status');
            if (!container) return;
            
            const items = [
                {label: 'Database', value: diagnostics.database ? 'Connected' : 'Disconnected', ok: diagnostics.database},
                {label: 'Session', value: diagnostics.session_active ? 'Active' : 'Inactive', ok: diagnostics.session_active},
                {label: 'CSRF Token', value: diagnostics.csrf_token_set ? 'Set' : 'Missing', ok: diagnostics.csrf_token_set},
                {label: 'Timestamp', value: diagnostics.timestamp, ok: true},
                {label: 'Timezone', value: diagnostics.timezone, ok: true},
                {label: 'PHP Version', value: diagnostics.php_version, ok: true}
            ];
            
            container.innerHTML = items.map(item => `
                <div class="status-item">
                    <span class="status-label">${item.label}:</span>
                    <span class="status-value ${item.ok ? 'status-ok' : 'status-error'}">${this.escapeHtml(item.value)}</span>
                </div>
            `).join('');
        },
        
        escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }
    };

    // ============================================================
    // Main Scanner Controller
    // ============================================================
    const ScannerController = {
        input: null,
        lastScanValue: '',
        lastScanAt: 0,
        
        init() {
            DiagnosticMonitor.log('Initializing scanner controller', 'info');
            
            // Check if SweetAlert2 is loaded
            if (typeof Swal === 'undefined') {
                console.error('SweetAlert2 is not loaded!');
                DiagnosticMonitor.log('SweetAlert2 not available', 'error');
            } else {
                console.log('SweetAlert2 is loaded and ready');
                DiagnosticMonitor.log('SweetAlert2 loaded successfully', 'success');
            }
            
            // Load configuration
            ScannerConfig.load();
            
            // Initialize audio
            AudioManager.init();
            
            // Get DOM elements
            this.input = document.getElementById('scan-input');
            
            // Set up event listeners
            this.setupEventListeners();
            
            // Set up modal event listeners
            this.setupModalEventListeners();
            
            // Focus input
            if (this.input) this.input.focus();
            
            // Set initial status
            VisualFeedback.setStatus('Ready', 'info');
            
            // Load diagnostics on page load
            if (document.getElementById('system-status')) {
                DiagnosticMonitor.loadSystemStatus();
            }
            
            DiagnosticMonitor.log('Scanner initialized successfully', 'success');
        },
        
        setupEventListeners() {
            // Scanner input enter key
            if (this.input) {
                this.input.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        const value = this.input.value.trim();
                        this.input.value = '';
                        if (value) {
                            this.handleScan(value);
                        }
                    }
                });
                
                // Auto-refocus on blur
                this.input.addEventListener('blur', () => {
                    setTimeout(() => {
                        if (this.input) this.input.focus();
                    }, 100);
                });
            }
            
            // Global keyboard shortcut (Alt+I to refocus)
            document.addEventListener('keydown', (e) => {
                if ((e.altKey || e.metaKey) && (e.key === 'i' || e.key === 'I')) {
                    e.preventDefault();
                    if (this.input) {
                        this.input.focus();
                        VisualFeedback.setStatus('Ready', 'info');
                    }
                }
            });
            
            // Preview confirm/cancel buttons
            const confirmBtn = document.getElementById('preview-confirm');
            const cancelBtn = document.getElementById('preview-cancel');
            
            if (confirmBtn) {
                confirmBtn.addEventListener('click', () => StudentPreview.confirm());
            }
            
            if (cancelBtn) {
                cancelBtn.addEventListener('click', () => StudentPreview.cancel());
            }
            
            // Scanner config modal save button
            const saveConfigBtn = document.getElementById('save-scanner-config');
            if (saveConfigBtn) {
                saveConfigBtn.addEventListener('click', () => {
                    ScannerConfig.updateFromUI();
                    DiagnosticMonitor.log('Scanner configuration saved', 'success');
                    
                    // Close modal if using Bootstrap
                    const modal = bootstrap?.Modal?.getInstance(document.getElementById('scannerConfigModal'));
                    if (modal) modal.hide();
                });
            }
            
            // Load config to UI when modal opens
            const configModal = document.getElementById('scannerConfigModal');
            if (configModal) {
                configModal.addEventListener('show.bs.modal', () => {
                    ScannerConfig.loadToUI();
                });
            }
        },

        setupModalEventListeners() {
            // Student Preview Modal event listeners
            const studentPreviewModal = document.getElementById('studentPreviewModal');
            if (studentPreviewModal) {
                // Reset modal content when it closes
                studentPreviewModal.addEventListener('hidden.bs.modal', () => {
                    console.log('Student preview modal closed, resetting content');
                    
                    // Reset all form elements to default state
                    const photo = document.getElementById('preview-photo');
                    const name = document.getElementById('preview-name');
                    const id = document.getElementById('preview-id');
                    const program = document.getElementById('preview-program');
                    const shift = document.getElementById('preview-shift');
                    const status = document.getElementById('preview-status');
                    const timer = document.getElementById('auto-confirm-timer');
                    
                    if (photo) photo.src = 'assets/img/default-avatar.svg';
                    if (name) name.textContent = '-';
                    if (id) id.textContent = '-';
                    if (program) program.textContent = '-';
                    if (shift) shift.textContent = '-';
                    if (status) status.innerHTML = '<span class="badge bg-secondary">Not checked in today</span>';
                    if (timer) timer.textContent = '2';
                    
                    // Clear any timers
                    StudentPreview.clearAutoConfirm();
                    
                    // Reset student data
                    StudentPreview.currentStudent = null;
                    StudentPreview.confirmCallback = null;
                });
            }
        },
        
        handleScan(studentId) {
            const now = Date.now();
            
            // Debounce check
            if (now - this.lastScanAt < ScannerConfig.settings.debounceMs) {
                DiagnosticMonitor.log('Scan debounced: too fast', 'warning');
                return;
            }
            
            // Duplicate check
            if (studentId === this.lastScanValue && 
                (now - this.lastScanAt) < ScannerConfig.settings.duplicateSuppressionMs) {
                DiagnosticMonitor.log('Duplicate scan ignored: ' + studentId, 'warning');
                
                // Show SweetAlert2 warning popup
                Swal.fire({
                    icon: 'warning',
                    title: 'Duplicate Scan',
                    text: `Student ${studentId} was already scanned recently`,
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#ffc107',
                    timer: 3000,
                    timerProgressBar: true
                });
                
                VisualFeedback.setStatus('Duplicate', 'warning');
                VisualFeedback.showFlash('warning');
                AudioManager.play('warning');
                return;
            }
            
            // Validation check
            if (!ScannerConfig.settings.validPattern.test(studentId)) {
                DiagnosticMonitor.log('Invalid format: ' + studentId, 'error');
                
                // Show SweetAlert2 error popup
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Student ID',
                    text: `Format not recognized: ${studentId}`,
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#dc3545',
                    timer: 4000,
                    timerProgressBar: true
                });
                
                VisualFeedback.setStatus('Invalid', 'error');
                VisualFeedback.showFlash('error');
                AudioManager.play('error');
                return;
            }
            
            this.lastScanValue = studentId;
            this.lastScanAt = now;
            
            DiagnosticMonitor.log('Processing scan: ' + studentId, 'info');
            VisualFeedback.setStatus('Processing', 'info');
            
            // Clear any existing preview first
            StudentPreview.hide();
            
            // Show student preview
            StudentPreview.show(studentId, (confirmedId) => {
                this.submitAttendance(confirmedId);
            });
        },
        
        async submitAttendance(studentId) {
            DiagnosticMonitor.log('Submitting attendance for: ' + studentId, 'info');
            VisualFeedback.setStatus('Submitting', 'info');
            
            try {
                const csrfToken = document.getElementById('csrf-token')?.value || '';
                
                const response = await fetch('api/attendance.php?action=save_attendance', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify({
                        student_id: studentId,
                        source: 'scanner'
                    })
                });
                
                const data = await response.json();
                
                if (response.ok && data.success) {
                    const isCheckout = data.mode === 'checkout';
                    const message = isCheckout ? 'Check-out successful' : 'Check-in successful';
                    
                    DiagnosticMonitor.log(message + ': ' + studentId, 'success');
                    
                    // Show SweetAlert2 success popup
                    console.log('Showing SweetAlert2 popup for:', message);
                    try {
                        Swal.fire({
                            icon: 'success',
                            title: message,
                            text: `Student: ${studentId}`,
                            confirmButtonText: 'OK',
                            confirmButtonColor: '#28a745',
                            timer: 3000,
                            timerProgressBar: true
                        });
                        console.log('SweetAlert2 popup triggered successfully');
                    } catch (error) {
                        console.error('SweetAlert2 error:', error);
                        // Fallback to alert if SweetAlert2 fails
                        alert(message + ': ' + studentId);
                    }
                    
                    VisualFeedback.setStatus('Success', 'success');
                    VisualFeedback.showFlash('success');
                    AudioManager.play(isCheckout ? 'checkout' : 'success');
                    
                    // Update UI counters
                    this.updateCounters();
                    
                    // Update the student preview to show new status
                    this.updateStudentPreviewStatus(data);
                    
                    // Also hide buttons immediately after successful attendance
                    setTimeout(() => {
                        this.hidePreviewButtons();
                    }, 100);
                } else {
                    throw new Error(data.error || data.message || 'Attendance submission failed');
                }
            } catch (error) {
                DiagnosticMonitor.log('Submission error: ' + error.message, 'error');
                
                // Show SweetAlert2 error popup
                Swal.fire({
                    icon: 'error',
                    title: 'Attendance Error',
                    text: error.message,
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#dc3545'
                });
                
                VisualFeedback.setStatus('Error', 'error');
                VisualFeedback.showFlash('error');
                AudioManager.play('error');
            } finally {
                // Refocus input
                if (this.input) this.input.focus();
            }
        },
        
        async updateCounters() {
            // Reload recent scans
            if (typeof loadRecentScans === 'function') {
                loadRecentScans();
            }
            
            // Reload scan stats
            if (typeof updateScanCount === 'function') {
                updateScanCount();
            }
        },
        
        updateStudentPreviewStatus(attendanceData) {
            StudentPreview.updateStudentPreviewStatus(attendanceData);
        },
        
        hidePreviewButtons() {
            console.log('Hiding preview buttons...');
            
            // Try multiple selectors to find the buttons
            const selectors = [
                '#preview-confirm',
                '#preview-cancel',
                '.btn-group.w-100.mb-3',
                '.btn-group',
                'button[class*="btn-success"]',
                'button[class*="btn-outline-secondary"]'
            ];
            
            selectors.forEach(selector => {
                const elements = document.querySelectorAll(selector);
                elements.forEach(element => {
                    if (element) {
                        element.style.display = 'none';
                        element.classList.add('d-none');
                        console.log('Hidden element:', selector, element);
                    }
                });
            });
            
            // Also try to hide the entire button group container
            const buttonContainer = document.querySelector('.btn-group.w-100.mb-3');
            if (buttonContainer) {
                buttonContainer.style.display = 'none';
                buttonContainer.classList.add('d-none');
                console.log('Hidden button container');
            }
        }
    };

    // ============================================================
    // Initialize on DOM Ready
    // ============================================================
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => ScannerController.init());
    } else {
        ScannerController.init();
    }

})();

