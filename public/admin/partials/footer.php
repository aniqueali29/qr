<?php
/**
 * Admin Footer Partial
 * Common footer with JavaScript includes
 */

$pageJS = $pageJS ?? [];
?>

<!-- Content wrapper -->
<div class="content-wrapper">
    <!-- Content -->
    <div class="container-xxl flex-grow-1 container-p-y">
        <!-- Page content will be inserted here -->
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

<!-- Page-specific JS -->
<?php foreach ($pageJS as $js): ?>
    <script src="<?php echo getAdminAssetUrl($js); ?>?v=<?php echo time(); ?>"></script>
<?php endforeach; ?>

<!-- Main JS -->
<script src="<?php echo getAdminAssetUrl('js/main.js?v=' . time()); ?>"></script>

<!-- Admin-specific JS -->
<script>
// Admin dashboard specific JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initialize popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
    
    
    // Confirm delete actions
    document.querySelectorAll('[data-confirm]').forEach(function(element) {
        element.addEventListener('click', function(e) {
            var message = this.getAttribute('data-confirm') || 'Are you sure?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });
    
    // Form validation
    var forms = document.querySelectorAll('.needs-validation');
    Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
});

// Global admin functions
window.adminUtils = {
    
    showLoading: function(show) {
        var loadingElements = document.querySelectorAll('.loading');
        loadingElements.forEach(function(element) {
            element.style.display = show ? 'block' : 'none';
        });
    },
    
    formatDate: function(dateString) {
        var date = new Date(dateString);
        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
    }
};
</script>

</body>
</html>
