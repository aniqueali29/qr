/**
 * Keyboard Shortcuts
 * Global keyboard shortcuts for quick navigation
 */

const KeyboardShortcuts = {
    shortcuts: {
        // Navigation
        'g+d': { action: () => window.location.href = 'index.php', description: 'Go to Dashboard' },
        'g+s': { action: () => window.location.href = 'students.php', description: 'Go to Students' },
        'g+p': { action: () => window.location.href = 'program_sections.php', description: 'Go to Programs' },
        'g+a': { action: () => window.location.href = 'attendances.php', description: 'Go to Attendance' },
        'g+r': { action: () => window.location.href = 'reports.php', description: 'Go to Reports' },
        
        // Actions
        'n': { action: () => KeyboardShortcuts.triggerNewButton(), description: 'New/Add' },
        'e': { action: () => KeyboardShortcuts.triggerExportButton(), description: 'Export' },
        'f': { action: () => KeyboardShortcuts.triggerFilterButton(), description: 'Toggle Filters' },
        '?': { action: () => KeyboardShortcuts.showHelp(), description: 'Show shortcuts' },
        
        // Utility
        'Escape': { action: () => KeyboardShortcuts.closeModals(), description: 'Close modals' }
    },

    pressedKeys: [],
    timeout: null,

    /**
     * Initialize keyboard shortcuts
     */
    init: function() {
        document.addEventListener('keydown', (e) => this.handleKeyPress(e));
        this.createHelpModal();
        this.addShortcutIndicator();
    },

    /**
     * Handle key press
     */
    handleKeyPress: function(e) {
        // Ignore if typing in input fields
        if (e.target.tagName === 'INPUT' || 
            e.target.tagName === 'TEXTAREA' || 
            e.target.isContentEditable) {
            return;
        }

        // Handle special keys
        if (e.key === 'Escape') {
            this.shortcuts['Escape'].action();
            return;
        }

        if (e.key === '?') {
            e.preventDefault();
            this.shortcuts['?'].action();
            return;
        }

        // Build key combination
        const key = e.key.toLowerCase();
        this.pressedKeys.push(key);

        // Clear timeout
        clearTimeout(this.timeout);

        // Set timeout to reset keys
        this.timeout = setTimeout(() => {
            this.pressedKeys = [];
        }, 1000);

        // Check for shortcuts
        const combination = this.pressedKeys.join('+');
        if (this.shortcuts[combination]) {
            e.preventDefault();
            this.shortcuts[combination].action();
            this.pressedKeys = [];
        }
    },

    /**
     * Trigger new/add button
     */
    triggerNewButton: function() {
        const buttons = [
            document.querySelector('button[onclick*="openStudent"]'),
            document.querySelector('button[onclick*="openProgram"]'),
            document.querySelector('button[onclick*="openSection"]'),
            document.querySelector('button[onclick*="openAttendance"]')
        ];

        const activeButton = buttons.find(btn => btn && btn.offsetParent !== null);
        if (activeButton) {
            activeButton.click();
        }
    },

    /**
     * Trigger export button
     */
    triggerExportButton: function() {
        const exportBtn = document.querySelector('button[onclick*="export"]');
        if (exportBtn) {
            exportBtn.click();
        }
    },

    /**
     * Trigger filter button
     */
    triggerFilterButton: function() {
        const filterBtn = document.querySelector('button[onclick*="toggleFilterPanel"]') ||
                         document.querySelector('button[onclick*="Filter"]');
        if (filterBtn) {
            filterBtn.click();
        }
    },

    /**
     * Close all modals
     */
    closeModals: function() {
        const modals = document.querySelectorAll('.modal.show');
        modals.forEach(modal => {
            const bsModal = bootstrap.Modal.getInstance(modal);
            if (bsModal) {
                bsModal.hide();
            }
        });
    },

    /**
     * Show keyboard shortcuts help
     */
    showHelp: function() {
        const modal = new bootstrap.Modal(document.getElementById('keyboardShortcutsModal'));
        modal.show();
    },

    /**
     * Create help modal
     */
    createHelpModal: function() {
        if (document.getElementById('keyboardShortcutsModal')) return;

        const modal = document.createElement('div');
        modal.id = 'keyboardShortcutsModal';
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bx bx-keyboard me-2"></i>Keyboard Shortcuts
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="bx bx-info-circle me-2"></i>
                            Use these keyboard shortcuts to navigate faster and boost your productivity.
                        </div>
                        
                        <div class="row g-4">
                            <div class="col-md-6">
                                <h6 class="text-muted mb-3">
                                    <i class="bx bx-navigation me-2"></i>Navigation
                                </h6>
                                <div class="shortcut-list">
                                    <div class="shortcut-item">
                                        <span class="shortcut-keys">
                                            <kbd>G</kbd> then <kbd>D</kbd>
                                        </span>
                                        <span class="shortcut-desc">Go to Dashboard</span>
                                    </div>
                                    <div class="shortcut-item">
                                        <span class="shortcut-keys">
                                            <kbd>G</kbd> then <kbd>S</kbd>
                                        </span>
                                        <span class="shortcut-desc">Go to Students</span>
                                    </div>
                                    <div class="shortcut-item">
                                        <span class="shortcut-keys">
                                            <kbd>G</kbd> then <kbd>P</kbd>
                                        </span>
                                        <span class="shortcut-desc">Go to Programs</span>
                                    </div>
                                    <div class="shortcut-item">
                                        <span class="shortcut-keys">
                                            <kbd>G</kbd> then <kbd>A</kbd>
                                        </span>
                                        <span class="shortcut-desc">Go to Attendance</span>
                                    </div>
                                    <div class="shortcut-item">
                                        <span class="shortcut-keys">
                                            <kbd>G</kbd> then <kbd>R</kbd>
                                        </span>
                                        <span class="shortcut-desc">Go to Reports</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-muted mb-3">
                                    <i class="bx bx-command me-2"></i>Actions
                                </h6>
                                <div class="shortcut-list">
                                    <div class="shortcut-item">
                                        <span class="shortcut-keys">
                                            <kbd>Ctrl</kbd> + <kbd>K</kbd>
                                        </span>
                                        <span class="shortcut-desc">Open Search</span>
                                    </div>
                                    <div class="shortcut-item">
                                        <span class="shortcut-keys">
                                            <kbd>N</kbd>
                                        </span>
                                        <span class="shortcut-desc">New/Add Record</span>
                                    </div>
                                    <div class="shortcut-item">
                                        <span class="shortcut-keys">
                                            <kbd>E</kbd>
                                        </span>
                                        <span class="shortcut-desc">Export Data</span>
                                    </div>
                                    <div class="shortcut-item">
                                        <span class="shortcut-keys">
                                            <kbd>F</kbd>
                                        </span>
                                        <span class="shortcut-desc">Toggle Filters</span>
                                    </div>
                                    <div class="shortcut-item">
                                        <span class="shortcut-keys">
                                            <kbd>Esc</kbd>
                                        </span>
                                        <span class="shortcut-desc">Close Modals</span>
                                    </div>
                                    <div class="shortcut-item">
                                        <span class="shortcut-keys">
                                            <kbd>?</kbd>
                                        </span>
                                        <span class="shortcut-desc">Show This Help</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(modal);
    },

    /**
     * Add shortcut indicator to navbar
     */
    addShortcutIndicator: function() {
        const navbar = document.querySelector('.layout-navbar');
        if (!navbar) return;

        const indicator = document.createElement('button');
        indicator.className = 'btn btn-sm btn-outline-secondary ms-2 d-none';
        indicator.innerHTML = '<i class="bx bx-keyboard me-1"></i>Shortcuts <kbd>?</kbd>';
        indicator.onclick = () => this.showHelp();

        const navbarContent = navbar.querySelector('.navbar-nav');
        if (navbarContent) {
            const li = document.createElement('li');
            li.className = 'nav-item';
            li.appendChild(indicator);
            navbarContent.appendChild(li);
        }
    }
};

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    KeyboardShortcuts.init();
});
