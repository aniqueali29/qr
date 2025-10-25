<?php
/**
 * Student Promotion Management Page
 */

require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';

// Require admin authentication
requireAdminAuth();

$pageTitle = "Student Promotion";
$currentPage = "settings";
$pageJS = [];

include 'partials/header.php';
include 'partials/sidebar.php';
include 'partials/navbar.php';
?>

<!-- Content wrapper -->
<div class="content-wrapper">
    <!-- Content -->
    <div class="container-xxl flex-grow-1 container-p-y">
        
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1">Student Promotion</h4>
                <p class="mb-0 text-muted">Bulk promote students to next year or mark them as graduated</p>
            </div>
            <div>
                <a href="settings.php" class="btn btn-outline-secondary">
                    <i class="bx bx-arrow-back me-1"></i> Back to Settings
                </a>
            </div>
        </div>

        <!-- Info Alert -->
        <div class="alert alert-info d-flex" role="alert">
            <span class="badge badge-center rounded-pill bg-info border-label-info p-3 me-2">
                <i class="bx bx-info-circle fs-6"></i>
            </span>
            <div class="flex-grow-1 row">
                <div class="col-12 mb-2">
                    <strong>How Student Promotion Works:</strong>
                </div>
                <div class="col-12">
                    <ul class="mb-0">
                        <li>Students in 1st, 2nd, and 3rd year will be promoted to the next year</li>
                        <li>Students in final year (3rd or 4th depending on program) will be marked as <strong>Graduated</strong></li>
                        <li>Graduated students will be marked as inactive but remain in the system</li>
                        <li>You can rollback promotions done today if needed</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Preview Card -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Current Student Distribution</h5>
                <button type="button" class="btn btn-sm btn-primary" id="refreshPreview">
                    <i class="bx bx-refresh me-1"></i> Refresh
                </button>
            </div>
            <div class="card-body">
                <div id="promotionPreview">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading preview...</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions Card -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Promotion Actions</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Promote Button -->
                    <div class="col-md-6 mb-3">
                        <div class="card border border-primary h-100">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <i class="bx bx-trending-up text-primary" style="font-size: 48px;"></i>
                                </div>
                                <h5>Promote All Students</h5>
                                <p class="text-muted mb-3">
                                    Promote all active students to the next year level. 
                                    Final year students will be graduated.
                                </p>
                                <button type="button" class="btn btn-primary" id="promoteBtn">
                                    <i class="bx bx-trending-up me-1"></i> Promote All Students
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Rollback Button -->
                    <div class="col-md-6 mb-3">
                        <div class="card border border-warning h-100">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <i class="bx bx-undo text-warning" style="font-size: 48px;"></i>
                                </div>
                                <h5>Rollback Today's Promotions</h5>
                                <p class="text-muted mb-3">
                                    Undo all promotions done today. 
                                    Use this if promotion was done by mistake.
                                </p>
                                <button type="button" class="btn btn-warning" id="rollbackBtn">
                                    <i class="bx bx-undo me-1"></i> Rollback Promotions
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Warning -->
                <div class="alert alert-warning mt-3" role="alert">
                    <i class="bx bx-error-circle me-2"></i>
                    <strong>Warning:</strong> Promotion is a bulk operation that affects all active students. 
                    Make sure to backup your database before proceeding. This action cannot be easily undone after today.
                </div>
            </div>
        </div>

    </div>
    <!-- / Content -->
</div>
<!-- Content wrapper -->

<?php include 'partials/footer.php'; ?>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Promotion JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // Load preview on page load
    loadPromotionPreview();
    
    // Refresh preview button
    document.getElementById('refreshPreview').addEventListener('click', function() {
        loadPromotionPreview();
    });
    
    // Promote button
    document.getElementById('promoteBtn').addEventListener('click', function() {
        confirmPromotion();
    });
    
    // Rollback button
    document.getElementById('rollbackBtn').addEventListener('click', function() {
        confirmRollback();
    });
    
    /**
     * Load promotion preview
     */
    function loadPromotionPreview() {
        const preview = document.getElementById('promotionPreview');
        preview.innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Loading preview...</p>
            </div>
        `;
        
        fetch('api/promote_students.php?action=preview')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayPreview(data);
                } else {
                    preview.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="bx bx-error-circle me-2"></i>
                            Failed to load preview: ${data.message}
                        </div>
                    `;
                }
            })
            .catch(error => {
                preview.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bx bx-error-circle me-2"></i>
                        Error loading preview: ${error.message}
                    </div>
                `;
            });
    }
    
    /**
     * Display preview data
     */
    function displayPreview(data) {
        const preview = document.getElementById('promotionPreview');
        const maxYears = data.max_years || 4;
        
        let html = `
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card bg-label-primary">
                        <div class="card-body text-center">
                            <i class="bx bx-user text-primary" style="font-size: 32px;"></i>
                            <h3 class="mb-0 mt-2">${data.totals.total_active}</h3>
                            <p class="mb-0 text-muted">Active Students</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-label-success">
                        <div class="card-body text-center">
                            <i class="bx bx-trending-up text-success" style="font-size: 32px;"></i>
                            <h3 class="mb-0 mt-2">${data.totals.will_promote}</h3>
                            <p class="mb-0 text-muted">Will be Promoted</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-label-warning">
                        <div class="card-body text-center">
                            <i class="bx bx-trophy text-warning" style="font-size: 32px;"></i>
                            <h3 class="mb-0 mt-2">${data.totals.will_graduate}</h3>
                            <p class="mb-0 text-muted">Will Graduate</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Current Year</th>
                            <th>Year Level</th>
                            <th>Student Count</th>
                            <th>After Promotion</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        if (data.summary && data.summary.length > 0) {
            data.summary.forEach(row => {
                const currentYear = parseInt(row.current_year);
                const nextYear = currentYear + 1;
                const willGraduate = currentYear >= maxYears;
                
                html += `
                    <tr>
                        <td><strong>Year ${row.current_year}</strong></td>
                        <td><span class="badge bg-label-primary">${row.year_level}</span></td>
                        <td><strong>${row.student_count}</strong> students</td>
                        <td>
                            ${willGraduate 
                                ? '<span class="badge bg-warning"><i class="bx bx-trophy me-1"></i>Will Graduate</span>' 
                                : '<span class="badge bg-success"><i class="bx bx-trending-up me-1"></i>Promote to Year ' + nextYear + '</span>'}
                        </td>
                    </tr>
                `;
            });
        } else {
            html += `
                <tr>
                    <td colspan="4" class="text-center">No active students found</td>
                </tr>
            `;
        }
        
        html += `
                    </tbody>
                </table>
            </div>
            
            <div class="alert alert-info mt-3">
                <i class="bx bx-info-circle me-2"></i>
                <strong>Program Duration:</strong> Currently set to ${maxYears} years. 
                You can change this in <a href="settings.php">Settings</a>.
            </div>
        `;
        
        preview.innerHTML = html;
    }
    
    /**
     * Confirm promotion
     */
    function confirmPromotion() {
        Swal.fire({
            title: 'Promote All Students?',
            html: `
                <p>This will:</p>
                <ul class="text-start">
                    <li>Promote all students to the next year</li>
                    <li>Graduate final year students</li>
                    <li>Update all student records</li>
                </ul>
                <p class="text-danger"><strong>This action affects all active students!</strong></p>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#696cff',
            cancelButtonColor: '#8592a3',
            confirmButtonText: 'Yes, Promote All',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                promoteAllStudents();
            }
        });
    }
    
    /**
     * Execute promotion
     */
    function promoteAllStudents() {
        Swal.fire({
            title: 'Processing...',
            html: 'Promoting students, please wait...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        fetch('api/promote_students.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=promote'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    html: `
                        <p>${data.message}</p>
                        <ul class="text-start">
                            <li><strong>${data.promoted}</strong> students promoted</li>
                            <li><strong>${data.graduated}</strong> students graduated</li>
                        </ul>
                    `,
                    confirmButtonColor: '#696cff'
                }).then(() => {
                    loadPromotionPreview();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Promotion Failed',
                    text: data.message,
                    confirmButtonColor: '#696cff'
                });
            }
        })
        .catch(error => {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'An error occurred: ' + error.message,
                confirmButtonColor: '#696cff'
            });
        });
    }
    
    /**
     * Confirm rollback
     */
    function confirmRollback() {
        Swal.fire({
            title: 'Rollback Promotions?',
            text: 'This will undo all promotions done today. Are you sure?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ffab00',
            cancelButtonColor: '#8592a3',
            confirmButtonText: 'Yes, Rollback',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                rollbackPromotions();
            }
        });
    }
    
    /**
     * Execute rollback
     */
    function rollbackPromotions() {
        Swal.fire({
            title: 'Processing...',
            html: 'Rolling back promotions, please wait...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        fetch('api/promote_students.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=rollback'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: data.message,
                    confirmButtonColor: '#696cff'
                }).then(() => {
                    loadPromotionPreview();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Rollback Failed',
                    text: data.message,
                    confirmButtonColor: '#696cff'
                });
            }
        })
        .catch(error => {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'An error occurred: ' + error.message,
                confirmButtonColor: '#696cff'
            });
        });
    }
});
</script>
