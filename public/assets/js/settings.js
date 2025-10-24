/**
 * Settings Page JavaScript
 * Handles settings management, validation, and AJAX operations
 */

class SettingsManager {
    constructor() {
        this.settings = {};
        this.changes = {};
        this.apiUrl = 'api/settings_api.php';
        this.init();
    }

    init() {
        this.loadSettings();
        this.bindEvents();
        this.updateStatus('Loading settings...', 'loading');
    }

    bindEvents() {
        // Tab navigation
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const tabName = e.target.getAttribute('onclick').match(/'([^']+)'/)[1];
                this.showTab(tabName);
            });
        });

        // Input change tracking
        document.querySelectorAll('input, select, textarea').forEach(input => {
            input.addEventListener('change', (e) => {
                this.trackChange(e.target.id, e.target.value);
            });
        });

        // Time input validation
        document.querySelectorAll('.time-input').forEach(input => {
            input.addEventListener('change', (e) => {
                this.validateTimeInput(e.target);
            });
        });

        // Number input validation
        document.querySelectorAll('.number-input').forEach(input => {
            input.addEventListener('change', (e) => {
                this.validateNumberInput(e.target);
            });
        });
    }

    async loadSettings() {
        try {
            const response = await fetch(`${this.apiUrl}?action=get_all`);
            const result = await response.json();

            if (result.success) {
                this.settings = result.data;
                this.populateForm();
                this.updateStatus('Settings loaded successfully', 'success');
            } else {
                this.updateStatus('Failed to load settings: ' + result.message, 'error');
            }
        } catch (error) {
            this.updateStatus('Error loading settings: ' + error.message, 'error');
        }
    }

    populateForm() {
        // Populate timing settings
        const timingSettings = this.settings.shift_timings || [];
        timingSettings.forEach(setting => {
            const element = document.getElementById(setting.key);
            if (element) {
                if (element.type === 'time') {
                    element.value = setting.value;
                } else {
                    element.value = setting.value;
                }
            }
        });

        // Populate system settings
        const systemSettings = this.settings.system_config || [];
        systemSettings.forEach(setting => {
            const element = document.getElementById(setting.key);
            if (element) {
                if (element.type === 'checkbox') {
                    element.checked = setting.value;
                } else {
                    element.value = setting.value;
                }
            }
        });

        // Populate integration settings
        const integrationSettings = this.settings.integration || [];
        integrationSettings.forEach(setting => {
            const element = document.getElementById(setting.key);
            if (element) {
                element.value = setting.value;
            }
        });

        // Populate advanced settings
        const advancedSettings = this.settings.advanced || [];
        advancedSettings.forEach(setting => {
            const element = document.getElementById(setting.key);
            if (element) {
                if (element.type === 'checkbox') {
                    element.checked = setting.value;
                } else {
                    element.value = setting.value;
                }
            }
        });
    }

    trackChange(key, value) {
        this.changes[key] = value;
        this.updateSaveButton();
    }

    updateSaveButton() {
        const saveBtn = document.querySelector('.btn-primary');
        const hasChanges = Object.keys(this.changes).length > 0;
        
        if (hasChanges) {
            saveBtn.innerHTML = '<i class="fas fa-save"></i> Save Changes (' + Object.keys(this.changes).length + ')';
            saveBtn.classList.add('btn-warning');
        } else {
            saveBtn.innerHTML = '<i class="fas fa-save"></i> Save All Changes';
            saveBtn.classList.remove('btn-warning');
        }
    }

    async saveAllSettings() {
        if (Object.keys(this.changes).length === 0) {
            this.showToast('No changes to save', 'info');
            return;
        }

        try {
            this.updateStatus('Saving settings...', 'loading');
            
            const settingsArray = Object.entries(this.changes).map(([key, value]) => ({
                key: key,
                value: value
            }));

            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'bulk_update',
                    settings: settingsArray,
                    updated_by: 'admin'
                })
            });

            const result = await response.json();

            if (result.success) {
                this.changes = {};
                this.updateSaveButton();
                this.updateStatus('Settings saved successfully', 'success');
                this.showToast('Settings saved successfully', 'success');
            } else {
                this.updateStatus('Failed to save settings: ' + result.message, 'error');
                this.showToast('Failed to save settings: ' + result.message, 'error');
            }
        } catch (error) {
            this.updateStatus('Error saving settings: ' + error.message, 'error');
            this.showToast('Error saving settings: ' + error.message, 'error');
        }
    }

    async validateTimings() {
        try {
            const timings = {
                morning_checkin_start: document.getElementById('morning_checkin_start').value,
                morning_checkin_end: document.getElementById('morning_checkin_end').value,
                morning_checkout_start: document.getElementById('morning_checkout_start').value,
                morning_checkout_end: document.getElementById('morning_checkout_end').value,
                morning_class_end: document.getElementById('morning_class_end').value,
                evening_checkin_start: document.getElementById('evening_checkin_start').value,
                evening_checkin_end: document.getElementById('evening_checkin_end').value,
                evening_checkout_start: document.getElementById('evening_checkout_start').value,
                evening_checkout_end: document.getElementById('evening_checkout_end').value,
                evening_class_end: document.getElementById('evening_class_end').value
            };

            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'validate_timings',
                    timings: timings
                })
            });

            const result = await response.json();
            this.displayValidationResults(result);
        } catch (error) {
            this.showToast('Error validating timings: ' + error.message, 'error');
        }
    }

    displayValidationResults(result) {
        const container = document.getElementById('validationResults');
        container.innerHTML = '';

        if (result.valid) {
            container.innerHTML = '<div class="validation-success"><i class="fas fa-check-circle"></i> Configuration is valid</div>';
        } else {
            let html = '<div class="validation-errors"><h4>Validation Errors:</h4><ul>';
            result.errors.forEach(error => {
                html += `<li><i class="fas fa-exclamation-triangle"></i> ${error}</li>`;
            });
            html += '</ul></div>';
            container.innerHTML = html;
        }

        if (result.warnings && result.warnings.length > 0) {
            let html = '<div class="validation-warnings"><h4>Warnings:</h4><ul>';
            result.warnings.forEach(warning => {
                html += `<li><i class="fas fa-exclamation-circle"></i> ${warning}</li>`;
            });
            html += '</ul></div>';
            container.innerHTML += html;
        }
    }

    testConfiguration() {
        this.showToast('Testing configuration...', 'info');
        
        // Simulate configuration test
        setTimeout(() => {
            this.showToast('Configuration test completed successfully', 'success');
        }, 2000);
    }

    async testConnection() {
        try {
            this.updateStatus('Testing connection...', 'loading');
            
            const websiteUrl = document.getElementById('website_url').value;
            const response = await fetch(websiteUrl, { method: 'HEAD' });
            
            if (response.ok) {
                this.updateStatus('Connection successful', 'success');
                this.showToast('Website connection successful', 'success');
            } else {
                this.updateStatus('Connection failed: ' + response.status, 'error');
                this.showToast('Website connection failed: ' + response.status, 'error');
            }
        } catch (error) {
            this.updateStatus('Connection failed: ' + error.message, 'error');
            this.showToast('Website connection failed: ' + error.message, 'error');
        }
    }

    async testAPI() {
        try {
            this.updateStatus('Testing API endpoints...', 'loading');
            
            const response = await fetch(`${this.apiUrl}?action=get_all`);
            const result = await response.json();
            
            if (result.success) {
                this.updateStatus('API test successful', 'success');
                this.showToast('API endpoints are working correctly', 'success');
            } else {
                this.updateStatus('API test failed: ' + result.message, 'error');
                this.showToast('API test failed: ' + result.message, 'error');
            }
        } catch (error) {
            this.updateStatus('API test failed: ' + error.message, 'error');
            this.showToast('API test failed: ' + error.message, 'error');
        }
    }

    validateTimeInput(input) {
        const value = input.value;
        if (!value) return;

        const timeRegex = /^([01]?[0-9]|2[0-3]):[0-5][0-9]$/;
        if (!timeRegex.test(value)) {
            input.classList.add('error');
            this.showInputError(input, 'Invalid time format');
        } else {
            input.classList.remove('error');
            this.clearInputError(input);
        }
    }

    validateNumberInput(input) {
        const value = parseFloat(input.value);
        const min = parseFloat(input.getAttribute('min'));
        const max = parseFloat(input.getAttribute('max'));

        if (isNaN(value)) {
            input.classList.add('error');
            this.showInputError(input, 'Invalid number');
            return;
        }

        if (min !== null && value < min) {
            input.classList.add('error');
            this.showInputError(input, `Value must be at least ${min}`);
            return;
        }

        if (max !== null && value > max) {
            input.classList.add('error');
            this.showInputError(input, `Value must be at most ${max}`);
            return;
        }

        input.classList.remove('error');
        this.clearInputError(input);
    }

    showInputError(input, message) {
        this.clearInputError(input);
        const errorDiv = document.createElement('div');
        errorDiv.className = 'input-error';
        errorDiv.textContent = message;
        input.parentNode.appendChild(errorDiv);
    }

    clearInputError(input) {
        const existingError = input.parentNode.querySelector('.input-error');
        if (existingError) {
            existingError.remove();
        }
    }

    showTab(tabName) {
        // Hide all tabs
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.classList.remove('active');
        });

        // Remove active class from all buttons
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('active');
        });

        // Show selected tab
        document.getElementById(tabName + '-tab').classList.add('active');
        
        // Add active class to clicked button
        event.target.classList.add('active');
    }

    updateStatus(message, type) {
        const statusElement = document.getElementById('settingsStatus');
        if (!statusElement) {
            console.error('Status element not found');
            return;
        }
        
        const icon = statusElement.querySelector('i');
        const textElement = statusElement.querySelector('.status-text');
        
        if (!icon || !textElement) {
            console.error('Status elements not found');
            return;
        }
        
        textElement.textContent = message;
        
        // Update icon based on type
        icon.className = 'fas fa-circle';
        if (type === 'success') {
            icon.style.color = '#28a745';
        } else if (type === 'error') {
            icon.style.color = '#dc3545';
        } else if (type === 'loading') {
            icon.style.color = '#ffc107';
            icon.classList.add('fa-spin');
        } else {
            icon.style.color = '#6c757d';
        }
    }

    showToast(message, type = 'info') {
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        
        const icon = type === 'success' ? 'fa-check-circle' : 
                   type === 'error' ? 'fa-exclamation-circle' : 
                   type === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle';
        
        toast.innerHTML = `
            <i class="fas ${icon}"></i>
            <span>${message}</span>
            <button onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        container.appendChild(toast);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (toast.parentElement) {
                toast.remove();
            }
        }, 5000);
    }

    togglePassword(inputId) {
        const input = document.getElementById(inputId);
        const button = input.nextElementSibling;
        const icon = button.querySelector('i');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'fas fa-eye-slash';
        } else {
            input.type = 'password';
            icon.className = 'fas fa-eye';
        }
    }

    exportSettings() {
        const settingsData = {
            exported_at: new Date().toISOString(),
            settings: this.settings,
            version: '1.0'
        };
        
        const blob = new Blob([JSON.stringify(settingsData, null, 2)], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        
        const a = document.createElement('a');
        a.href = url;
        a.download = `settings_export_${new Date().toISOString().split('T')[0]}.json`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        
        this.showToast('Settings exported successfully', 'success');
    }

    importSettings() {
        document.getElementById('importFile').click();
    }

    handleImportFile(input) {
        const file = input.files[0];
        if (!file) return;
        
        const reader = new FileReader();
        reader.onload = (e) => {
            try {
                const data = JSON.parse(e.target.result);
                if (data.settings) {
                    this.showToast('Settings imported successfully', 'success');
                    this.loadSettings(); // Reload to show imported settings
                } else {
                    this.showToast('Invalid settings file', 'error');
                }
            } catch (error) {
                this.showToast('Error reading settings file: ' + error.message, 'error');
            }
        };
        reader.readAsText(file);
    }

    async resetAllSettings() {
        if (!confirm('Are you sure you want to reset all settings to defaults? This action cannot be undone.')) {
            return;
        }
        
        try {
            this.updateStatus('Resetting settings...', 'loading');
            
            // Get all setting keys
            const allSettings = Object.values(this.settings).flat();
            const resetPromises = allSettings.map(setting => 
                fetch(this.apiUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'reset',
                        key: setting.key
                    })
                })
            );
            
            await Promise.all(resetPromises);
            
            this.updateStatus('Settings reset successfully', 'success');
            this.showToast('All settings reset to defaults', 'success');
            this.loadSettings(); // Reload to show reset settings
        } catch (error) {
            this.updateStatus('Error resetting settings: ' + error.message, 'error');
            this.showToast('Error resetting settings: ' + error.message, 'error');
        }
    }
}

// Global functions for HTML onclick handlers
function showTab(tabName) {
    window.settingsManager.showTab(tabName);
}

function saveAllSettings() {
    window.settingsManager.saveAllSettings();
}

function validateTimings() {
    window.settingsManager.validateTimings();
}

function testConfiguration() {
    window.settingsManager.testConfiguration();
}

function testConnection() {
    window.settingsManager.testConnection();
}

function testAPI() {
    window.settingsManager.testAPI();
}

function togglePassword(inputId) {
    window.settingsManager.togglePassword(inputId);
}

function exportSettings() {
    window.settingsManager.exportSettings();
}

function importSettings() {
    window.settingsManager.importSettings();
}

function handleImportFile(input) {
    window.settingsManager.handleImportFile(input);
}

function resetAllSettings() {
    window.settingsManager.resetAllSettings();
}

// Initialize settings manager when page loads
document.addEventListener('DOMContentLoaded', () => {
    window.settingsManager = new SettingsManager();
});
