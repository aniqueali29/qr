/**
 * UI Helpers - Loading States, Confirmations, and Notifications
 * Centralized utilities for consistent UX across the application
 */

const UIHelpers = {
    /**
     * Show loading state on a button
     * @param {HTMLElement|string} button - Button element or selector
     * @param {string} loadingText - Text to show while loading (default: 'Loading...')
     */
    showButtonLoading: function(button, loadingText = 'Loading...') {
        const btn = typeof button === 'string' ? document.querySelector(button) : button;
        if (!btn) return;
        
        // Store original content
        btn.setAttribute('data-original-html', btn.innerHTML);
        btn.setAttribute('data-was-disabled', btn.disabled);
        
        // Set loading state
        btn.disabled = true;
        btn.innerHTML = `
            <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
            ${loadingText}
        `;
    },

    /**
     * Hide loading state on a button
     * @param {HTMLElement|string} button - Button element or selector
     */
    hideButtonLoading: function(button) {
        const btn = typeof button === 'string' ? document.querySelector(button) : button;
        if (!btn) return;
        
        // Restore original content
        const originalHtml = btn.getAttribute('data-original-html');
        const wasDisabled = btn.getAttribute('data-was-disabled') === 'true';
        
        if (originalHtml) {
            btn.innerHTML = originalHtml;
            btn.disabled = wasDisabled;
            btn.removeAttribute('data-original-html');
            btn.removeAttribute('data-was-disabled');
        }
    },

    /**
     * Show loading overlay on a container
     * @param {HTMLElement|string} container - Container element or selector
     * @param {string} message - Loading message (default: 'Loading...')
     */
    showLoadingOverlay: function(container, message = 'Loading...') {
        const elem = typeof container === 'string' ? document.querySelector(container) : container;
        if (!elem) return;
        
        // Create overlay if it doesn't exist
        let overlay = elem.querySelector('.loading-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'loading-overlay';
            overlay.innerHTML = `
                <div class="loading-overlay-content">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <div class="mt-2 loading-message">${message}</div>
                </div>
            `;
            elem.style.position = 'relative';
            elem.appendChild(overlay);
        } else {
            overlay.querySelector('.loading-message').textContent = message;
            overlay.style.display = 'flex';
        }
    },

    /**
     * Hide loading overlay on a container
     * @param {HTMLElement|string} container - Container element or selector
     */
    hideLoadingOverlay: function(container) {
        const elem = typeof container === 'string' ? document.querySelector(container) : container;
        if (!elem) return;
        
        const overlay = elem.querySelector('.loading-overlay');
        if (overlay) {
            overlay.style.display = 'none';
        }
    },

    /**
     * Show confirmation dialog
     * @param {Object} options - Configuration object
     * @param {string} options.title - Dialog title
     * @param {string} options.message - Dialog message
     * @param {string} options.confirmText - Confirm button text (default: 'Confirm')
     * @param {string} options.cancelText - Cancel button text (default: 'Cancel')
     * @param {string} options.confirmClass - Confirm button class (default: 'btn-danger')
     * @param {function} options.onConfirm - Callback when confirmed
     * @param {function} options.onCancel - Callback when cancelled
     */
    showConfirmDialog: function(options) {
        const {
            title = 'Confirm Action',
            message = 'Are you sure you want to proceed?',
            confirmText = 'Confirm',
            cancelText = 'Cancel',
            confirmClass = 'btn-danger',
            onConfirm = () => {},
            onCancel = () => {}
        } = options;
        
        // Create modal if it doesn't exist
        let modal = document.getElementById('confirmDialog');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'confirmDialog';
            modal.className = 'modal fade';
            modal.setAttribute('tabindex', '-1');
            modal.innerHTML = `
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body"></div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"></button>
                            <button type="button" class="btn"></button>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }
        
        // Update modal content
        modal.querySelector('.modal-title').textContent = title;
        modal.querySelector('.modal-body').textContent = message;
        modal.querySelector('.modal-footer .btn-secondary').textContent = cancelText;
        
        const confirmBtn = modal.querySelector('.modal-footer .btn:last-child');
        confirmBtn.textContent = confirmText;
        confirmBtn.className = `btn ${confirmClass}`;
        
        // Set up event listeners
        const bsModal = new bootstrap.Modal(modal);
        
        // Remove old listeners
        const newConfirmBtn = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
        
        newConfirmBtn.addEventListener('click', () => {
            bsModal.hide();
            onConfirm();
        });
        
        modal.addEventListener('hidden.bs.modal', onCancel, { once: true });
        
        bsModal.show();
    },

    /**
     * Show success notification
     * @param {string} message - Success message
     * @param {number} duration - Duration in ms (default: 3000)
     */
    showSuccess: function(message, duration = 3000) {
        this.showNotification(message, 'success', duration);
    },

    /**
     * Show error notification
     * @param {string} message - Error message
     * @param {number} duration - Duration in ms (default: 5000)
     */
    showError: function(message, duration = 5000) {
        this.showNotification(message, 'danger', duration);
    },

    /**
     * Show info notification
     * @param {string} message - Info message
     * @param {number} duration - Duration in ms (default: 3000)
     */
    showInfo: function(message, duration = 3000) {
        this.showNotification(message, 'info', duration);
    },

    /**
     * Show warning notification
     * @param {string} message - Warning message
     * @param {number} duration - Duration in ms (default: 4000)
     */
    showWarning: function(message, duration = 4000) {
        this.showNotification(message, 'warning', duration);
    },

    /**
     * Show toast notification
     * @param {string} message - Notification message
     * @param {string} type - Type: success, danger, warning, info
     * @param {number} duration - Duration in ms
     */
    showNotification: function(message, type = 'info', duration = 3000) {
        // Create toast container if it doesn't exist
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'toast-container position-fixed top-0 end-0 p-3';
            container.style.zIndex = '9999';
            document.body.appendChild(container);
        }
        
        // Create toast
        const toastId = 'toast-' + Date.now();
        const toast = document.createElement('div');
        toast.id = toastId;
        
        const bgMap = {
            success: 'bg-success',
            danger: 'bg-danger',
            warning: 'bg-warning',
            info: 'bg-primary'
        };
        
        const iconMap = {
            success: 'bx-check-circle',
            danger: 'bx-x-circle',
            warning: 'bx-error-circle',
            info: 'bx-info-circle'
        };
        
        const icon = iconMap[type] || iconMap.info;
        const bgClass = bgMap[type] || bgMap.info;
        
        // Use template's bs-toast class and styling
        toast.className = `bs-toast toast ${bgClass}`;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');
        
        toast.innerHTML = `
            <div class="toast-header">
                <i class="bx ${icon} me-2"></i>
                <strong class="me-auto">${type.charAt(0).toUpperCase() + type.slice(1)}</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body">
                ${message}
            </div>
        `;
        
        container.appendChild(toast);
        
        // Show toast
        const bsToast = new bootstrap.Toast(toast, { 
            autohide: true,
            delay: duration 
        });
        bsToast.show();
        
        // Remove toast after hiding
        toast.addEventListener('hidden.bs.toast', () => {
            toast.remove();
        });
    },

    /**
     * Disable form during submission
     * @param {HTMLElement|string} form - Form element or selector
     */
    disableForm: function(form) {
        const formElem = typeof form === 'string' ? document.querySelector(form) : form;
        if (!formElem) return;
        
        // Disable all inputs, selects, textareas, and buttons
        formElem.querySelectorAll('input, select, textarea, button').forEach(elem => {
            elem.setAttribute('data-was-disabled', elem.disabled);
            elem.disabled = true;
        });
        
        formElem.setAttribute('data-form-disabled', 'true');
    },

    /**
     * Enable form after submission
     * @param {HTMLElement|string} form - Form element or selector
     */
    enableForm: function(form) {
        const formElem = typeof form === 'string' ? document.querySelector(form) : form;
        if (!formElem) return;
        
        if (formElem.getAttribute('data-form-disabled') !== 'true') return;
        
        // Restore original disabled state
        formElem.querySelectorAll('input, select, textarea, button').forEach(elem => {
            const wasDisabled = elem.getAttribute('data-was-disabled') === 'true';
            elem.disabled = wasDisabled;
            elem.removeAttribute('data-was-disabled');
        });
        
        formElem.removeAttribute('data-form-disabled');
    },

    /**
     * Auto-focus first input field in a form or modal
     * @param {HTMLElement|string} container - Form, modal, or container element/selector
     * @param {number} delay - Delay in ms before focusing (default: 100)
     */
    autoFocusFirstInput: function(container, delay = 100) {
        const elem = typeof container === 'string' ? document.querySelector(container) : container;
        if (!elem) return;
        
        setTimeout(() => {
            // Find first visible, enabled input field
            const firstInput = elem.querySelector(
                'input:not([type="hidden"]):not([disabled]):not([readonly]), ' +
                'select:not([disabled]), ' +
                'textarea:not([disabled]):not([readonly])'
            );
            
            if (firstInput) {
                firstInput.focus();
                // Select text if it's an input field (not select/textarea)
                if (firstInput.tagName === 'INPUT' && firstInput.type !== 'checkbox' && firstInput.type !== 'radio') {
                    firstInput.select();
                }
            }
        }, delay);
    },

    /**
     * Optimize tab order for a form
     * Sets tabindex automatically based on visual order
     * @param {HTMLElement|string} form - Form element or selector
     */
    optimizeTabOrder: function(form) {
        const formElem = typeof form === 'string' ? document.querySelector(form) : form;
        if (!formElem) return;
        
        // Get all focusable elements
        const focusableElements = formElem.querySelectorAll(
            'input:not([type="hidden"]), select, textarea, button[type="submit"]'
        );
        
        // Set tabindex based on visual order
        let tabIndex = 1;
        focusableElements.forEach(elem => {
            // Skip disabled and readonly elements
            if (!elem.disabled && !elem.readOnly) {
                elem.setAttribute('tabindex', tabIndex++);
            }
        });
    },

    /**
     * Enable Enter key to submit form (except in textareas)
     * @param {HTMLElement|string} form - Form element or selector
     * @param {function} submitHandler - Optional custom submit handler
     */
    enableEnterKeySubmit: function(form, submitHandler = null) {
        const formElem = typeof form === 'string' ? document.querySelector(form) : form;
        if (!formElem) return;
        
        formElem.addEventListener('keydown', function(e) {
            // Check if Enter key was pressed
            if (e.key === 'Enter' || e.keyCode === 13) {
                // Don't submit if in textarea or if Shift+Enter
                if (e.target.tagName === 'TEXTAREA' || e.shiftKey) {
                    return;
                }
                
                // Prevent default form submission
                e.preventDefault();
                
                // Call custom handler or find submit button
                if (submitHandler && typeof submitHandler === 'function') {
                    submitHandler(e);
                } else {
                    const submitBtn = formElem.querySelector('button[type="submit"]');
                    if (submitBtn && !submitBtn.disabled) {
                        submitBtn.click();
                    }
                }
            }
        });
    },

    /**
     * Initialize form UX enhancements (auto-focus, tab order, enter key)
     * @param {HTMLElement|string} form - Form element or selector
     * @param {Object} options - Configuration options
     * @param {boolean} options.autoFocus - Enable auto-focus (default: true)
     * @param {boolean} options.optimizeTabOrder - Optimize tab order (default: true)
     * @param {boolean} options.enterKeySubmit - Enable Enter key submission (default: true)
     * @param {function} options.submitHandler - Custom submit handler for Enter key
     * @param {number} options.focusDelay - Delay before auto-focus (default: 100)
     */
    initFormUX: function(form, options = {}) {
        const {
            autoFocus = true,
            optimizeTabOrder = true,
            enterKeySubmit = true,
            submitHandler = null,
            focusDelay = 100
        } = options;
        
        if (autoFocus) {
            this.autoFocusFirstInput(form, focusDelay);
        }
        
        if (optimizeTabOrder) {
            this.optimizeTabOrder(form);
        }
        
        if (enterKeySubmit) {
            this.enableEnterKeySubmit(form, submitHandler);
        }
    },

    /**
     * Initialize modal UX enhancements
     * Auto-focuses first input when modal is shown
     * @param {HTMLElement|string} modal - Modal element or selector
     * @param {Object} options - Configuration options (same as initFormUX)
     */
    initModalFormUX: function(modal, options = {}) {
        const modalElem = typeof modal === 'string' ? document.querySelector(modal) : modal;
        if (!modalElem) return;
        
        const self = this;
        
        // Initialize form UX when modal is shown
        modalElem.addEventListener('shown.bs.modal', function() {
            const form = modalElem.querySelector('form');
            if (form) {
                self.initFormUX(form, options);
            }
        });
        
        // Clear form when modal is hidden (optional)
        if (options.clearOnHide !== false) {
            modalElem.addEventListener('hidden.bs.modal', function() {
                const form = modalElem.querySelector('form');
                if (form) {
                    form.reset();
                }
            });
        }
    }
};

// Make globally available
window.UIHelpers = UIHelpers;
