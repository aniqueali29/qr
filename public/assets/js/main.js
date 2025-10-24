/**
 * Template Name: Sneat - Bootstrap 5 HTML Admin Template
 * Author: ThemeSelection
 * Website: https://themeselection.com/
 * Version: 3.0.0
 */

(function() {
    'use strict';

    // Initialize template
    const init = () => {
        // Initialize tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Initialize popovers
        const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
        popoverTriggerList.map(function(popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl);
        });

        // Auto-hide alerts
        const alerts = document.querySelectorAll('.alert[data-auto-dismiss]');
        alerts.forEach(alert => {
            const delay = parseInt(alert.dataset.autoDismiss) || 5000;
            setTimeout(() => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, delay);
        });

        // Initialize perfect scrollbar for custom scrollbars
        if (typeof PerfectScrollbar !== 'undefined') {
            const psElements = document.querySelectorAll('.ps');
            psElements.forEach(element => {
                new PerfectScrollbar(element);
            });
        }

    // Charts are initialized per page as needed
    };

    // Charts are now initialized per page with real data

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Make functions globally available
    window.initTemplate = init;
})();