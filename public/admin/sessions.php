<?php
/**
 * Session Management Page
 * Manage enrollment sessions (Fall 2024, Spring 2025, etc.)
 */

require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';

// Require admin authentication
requireAdminAuth();

// Check if module is enabled
requireModuleEnabled('module_sessions');

$pageTitle = "Session Management";
$currentPage = "sessions";
$pageCSS = [];
$pageJS = ['js/session-management.js'];

include 'partials/header.php';
include 'partials/sidebar.php';
include 'partials/navbar.php';
?>

<style>
/* Session Management Styles */
/* Responsive button styling */
@media (max-width: 768px) {
    .btn-text { 
        display: none; 
    }
    .d-flex.flex-wrap.gap-2 .btn {
        padding: 0.375rem 0.5rem;
        font-size: 0.875rem;
    }
    .d-flex.flex-wrap.gap-2 .btn i {
        font-size: 1rem;
        margin: 0 !important;
    }
}

@media (min-width: 769px) {
    .d-flex.flex-wrap.gap-2 .btn i.me-1 {
        margin-right: 0.5rem !important;
    }
}
</style>

<!-- Content wrapper -->
<div class="content-wrapper">
    <!-- Content -->
    <div class="container-xxl flex-grow-1 container-p-y">
        <!-- Breadcrumb -->
        <div class="row">
            <div class="col-12">
                <?php echo generateBreadcrumb([
                    ['title' => 'Dashboard', 'url' => 'index.php'],
                    ['title' => 'Session Management', 'url' => '']
                ]); ?>
            </div>
        </div>
        
        <!-- Session Management Card -->
        <div class="card">
            <div class="card-header">
                <div class="row align-items-center">
                    <div class="col-12 col-md-6">
                        <h5 class="card-title mb-0">
                            <i class="bx bx-calendar me-2"></i>Session Management
                        </h5>
                    </div>
                    <div class="col-12 col-md-6 mt-2 mt-md-0">
                        <div class="d-flex flex-wrap gap-2 justify-content-md-end">
                            <button class="btn btn-primary" onclick="openSessionModal()">
                                <i class="bx bx-plus me-1"></i>
                                <span class="btn-text">New Session</span>
                            </button>
                            <button class="btn btn-success" onclick="bulkAssignSessions()">
                                <i class="bx bx-user-check me-1"></i>
                                <span class="btn-text">Bulk Assign</span>
                            </button>
                            <button class="btn btn-info" onclick="window.refreshSessions()">
                                <i class="bx bx-refresh me-1"></i>
                                <span class="btn-text">Refresh</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Stats -->
            <div class="card-body border-bottom">
                <div class="row">
                    <div class="col-md-3 col-6 mb-3">
                        <div class="d-flex align-items-center">
                            <div class="avatar flex-shrink-0 me-3">
                                <span class="avatar-initial rounded bg-label-primary">
                                    <i class="bx bx-calendar fs-4"></i>
                                </span>
                            </div>
                            <div>
                                <small class="text-muted d-block">Total Sessions</small>
                                <h4 class="mb-0" id="total-sessions">-</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6 mb-3">
                        <div class="d-flex align-items-center">
                            <div class="avatar flex-shrink-0 me-3">
                                <span class="avatar-initial rounded bg-label-success">
                                    <i class="bx bx-check fs-4"></i>
                                </span>
                            </div>
                            <div>
                                <small class="text-muted d-block">Active Sessions</small>
                                <h4 class="mb-0" id="active-sessions">-</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6 mb-3">
                        <div class="d-flex align-items-center">
                            <div class="avatar flex-shrink-0 me-3">
                                <span class="avatar-initial rounded bg-label-warning">
                                    <i class="bx bx-user fs-4"></i>
                                </span>
                            </div>
                            <div>
                                <small class="text-muted d-block">Total Students</small>
                                <h4 class="mb-0" id="total-students">-</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6 mb-3">
                        <div class="d-flex align-items-center">
                            <div class="avatar flex-shrink-0 me-3">
                                <span class="avatar-initial rounded bg-label-danger">
                                    <i class="bx bx-user-x fs-4"></i>
                                </span>
                            </div>
                            <div>
                                <small class="text-muted d-block">Unassigned</small>
                                <h4 class="mb-0" id="unassigned-students">-</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Sessions Table -->
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="sessions-table">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Label</th>
                                <th>Term</th>
                                <th>Year</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Students</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="sessions-tbody">
                            <tr>
                                <td colspan="9" class="text-center py-4">
                                    <div class="spinner-border" role="status">
                                        <span class="visually-hidden">Loading sessions...</span>
                                    </div>
                                    <div class="mt-2">Loading sessions...</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div id="sessions-pagination" class="d-flex justify-content-center mt-4">
                    <!-- Pagination will be loaded here -->
                </div>
            </div>
        </div>
    </div>
    <!-- / Content -->
</div>
<!-- Content wrapper -->

<!-- Session Modal -->
<div class="modal fade" id="sessionModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="sessionModalTitle">Create New Session</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="sessionForm">
                    <input type="hidden" id="sessionId" name="id">
                    <input type="hidden" id="csrfToken" name="csrf_token">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="term" class="form-label">Term <span class="text-danger">*</span></label>
                            <select class="form-select" id="term" name="term" required>
                                <option value="">Select Term</option>
                                <option value="Spring">Spring</option>
                                <option value="Summer">Summer</option>
                                <option value="Fall">Fall</option>
                                <option value="Winter">Winter</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="year" class="form-label">Year <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="year" name="year" 
                                   min="2020" max="2030" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="label" class="form-label">Display Label</label>
                            <input type="text" class="form-control" id="label" name="label" 
                                   placeholder="e.g., Fall 2024">
                            <div class="form-text">Auto-generated if left empty</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="code" class="form-label">Session Code</label>
                            <input type="text" class="form-control" id="code" name="code" 
                                   placeholder="e.g., F2024" readonly>
                            <div class="form-text">Auto-generated based on term and year</div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="startDate" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="startDate" name="start_date">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="endDate" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="endDate" name="end_date">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="isActive" name="is_active" checked>
                            <label class="form-check-label" for="isActive">
                                Active Session
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveSessionBtn">
                    <i class="bx bx-save me-1"></i>Save Session
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Assignment Modal -->
<div class="modal fade" id="bulkAssignModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bulk Student Session Assignment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="bx bx-info-circle me-2"></i>
                    Assign students to sessions in bulk. Changes can be rolled back using the history.
                </div>
                
                <!-- Session Selection -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="bulkSession" class="form-label">Target Session <span class="text-danger">*</span></label>
                        <select class="form-select" id="bulkSession" name="session_id" required>
                            <option value="">Select Session</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="bulkSemester" class="form-label">Target Semester</label>
                        <select class="form-select" id="bulkSemester" name="semester">
                            <option value="">All Semesters</option>
                            <option value="1">Semester 1</option>
                            <option value="2">Semester 2</option>
                            <option value="3">Semester 3</option>
                            <option value="4">Semester 4</option>
                            <option value="5">Semester 5</option>
                            <option value="6">Semester 6</option>
                            <option value="7">Semester 7</option>
                            <option value="8">Semester 8</option>
                        </select>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="card mb-3">
                    <div class="card-header">
                        <strong>Filters</strong>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="bulkProgram" class="form-label">Program</label>
                                <select class="form-select" id="bulkProgram">
                                    <option value="">All Programs</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="bulkShift" class="form-label">Shift</label>
                                <select class="form-select" id="bulkShift">
                                    <option value="">All Shifts</option>
                                    <option value="Morning">Morning</option>
                                    <option value="Evening">Evening</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="bulkSemesterNum" class="form-label">Current Semester</label>
                                <select class="form-select" id="bulkSemesterNum">
                                    <option value="">All Semesters</option>
                                    <option value="1">Semester 1</option>
                                    <option value="2">Semester 2</option>
                                    <option value="3">Semester 3</option>
                                    <option value="4">Semester 4</option>
                                    <option value="5">Semester 5</option>
                                    <option value="6">Semester 6</option>
                                    <option value="7">Semester 7</option>
                                    <option value="8">Semester 8</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Preview Section -->
                <div id="bulkPreview" class="alert alert-info" style="display: none;">
                    <i class="bx bx-info-circle me-2"></i>
                    <span id="previewCount">0</span> students will be assigned
                </div>
                
                <!-- Rollback Section -->
                <div id="rollbackSection" class="mt-3" style="display: none;">
                    <div class="alert alert-warning">
                        <i class="bx bx-history me-2"></i>
                        <strong>Rollback Available</strong>
                        <button class="btn btn-sm btn-warning float-end" onclick="showRollbackHistory()">
                            <i class="bx bx-time-five me-1"></i>View History
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-info" onclick="previewBulkAssignment()">
                    <i class="bx bx-search me-1"></i>Preview
                </button>
                <button type="button" class="btn btn-success" onclick="executeBulkAssignment()">
                    <i class="bx bx-check me-1"></i>Execute Assignment
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Rollback History Modal -->
<div class="modal fade" id="rollbackHistoryModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assignment History</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="rollbackHistoryContent">
                    <div class="text-center py-4">
                        <div class="spinner-border" role="status"></div>
                        <div class="mt-2">Loading history...</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// Enhanced Session Management JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Auto-generate code and label
    const termSelect = document.getElementById('term');
    const yearInput = document.getElementById('year');
    
    if (termSelect && yearInput) {
        termSelect.addEventListener('change', generateSessionCode);
        yearInput.addEventListener('input', generateSessionCode);
    }
    
    // Bind form save button
    const saveBtn = document.getElementById('saveSessionBtn');
    if (saveBtn) {
        saveBtn.addEventListener('click', saveSession);
    }
});

function generateSessionCode() {
    const term = document.getElementById('term').value;
    const year = document.getElementById('year').value;
    
    if (term && year) {
        const termCode = term.charAt(0).toUpperCase();
        const code = termCode + year;
        document.getElementById('code').value = code;
        
        if (!document.getElementById('label').value) {
            document.getElementById('label').value = term + ' ' + year;
        }
    }
}

function saveSession() {
    const form = document.getElementById('sessionForm');
    const formData = new FormData(form);
    
    // Get session ID
    const sessionId = document.getElementById('sessionId').value;
    
    const url = sessionId ? 
        `api/sessions.php?action=update&id=${sessionId}` : 
        'api/sessions.php?action=create';
    
    const saveBtn = document.getElementById('saveSessionBtn');
    const originalText = saveBtn.innerHTML;
    
    // Show loading state
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
    
    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalText;
        
        if (data.success) {
            UIHelpers.showSuccess(data.message || 'Session saved successfully');
            bootstrap.Modal.getInstance(document.getElementById('sessionModal')).hide();
            if (window.refreshSessions) {
                window.refreshSessions();
            }
        } else {
            UIHelpers.showError(data.error || 'Failed to save session');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalText;
        UIHelpers.showError('Failed to save session');
    });
}

function openSessionModal(sessionId = null) {
    window.openSessionModal(sessionId);
}

function previewBulkAssignment() {
    const sessionId = document.getElementById('bulkSession').value;
    const semester = document.getElementById('bulkSemester').value;
    
    if (!sessionId) {
        UIHelpers.showWarning('Please select a session');
        return;
    }
    
    // Show loading state
    UIHelpers.showInfo('Loading preview...');
    
    fetch(`api/sessions.php?action=preview_bulk_assign&session_id=${sessionId}&semester=${semester}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('previewCount').textContent = data.count;
                document.getElementById('bulkPreview').style.display = 'block';
                UIHelpers.showSuccess(`Preview ready. ${data.count} students will be assigned.`);
            } else {
                UIHelpers.showError(data.error || 'Failed to preview assignment');
                document.getElementById('bulkPreview').style.display = 'none';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            UIHelpers.showError('Failed to preview assignment. Please check your connection.');
            document.getElementById('bulkPreview').style.display = 'none';
        });
}

function executeBulkAssignment() {
    window.executeBulkAssignment();
}

function bulkAssignSessions() {
    window.bulkAssignSessions();
}
</script>

<?php include 'partials/footer.php'; ?>