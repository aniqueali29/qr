<?php
/**
 * Attendance Records Page
 * View and manage attendance records
 */

require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';

// Require admin authentication
requireAdminAuth();

$pageTitle = "Today's Active Students";
$currentPage = "attendances";
$pageCSS = ['css/responsive-buttons.css'];
$pageJS = ['js/admin.js'];

include 'partials/header.php';
include 'partials/sidebar.php';
include 'partials/navbar.php';
?>

<style>
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

/* Bulk Actions Toolkit - Professional Design */
.bulk-actions {
    background: rgba(255, 255, 255, 0.95) !important;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(0, 0, 0, 0.08);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08) !important;
    border-radius: 12px !important;
}

.bulk-actions .bulk-btn-text {
    display: none !important;
}

.bulk-actions .btn {
    padding: 0.55rem;
    font-size: 0.875rem;
    border: 1px solid rgba(0, 0, 0, 0.1);
    border-radius: 8px;
    transition: all 0.2s ease;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 48px;
    min-height: 48px;
}

.bulk-actions .btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.bulk-actions .btn i {
    margin: 0 !important;
    font-size: 1.25rem;
    line-height: 1;
    display: inline-block;
    vertical-align: middle;
}

/* Professional color scheme */
.bulk-actions .btn-success {
    background: #10b981;
    border-color: #10b981;
    color: white;
}

.bulk-actions .btn-warning {
    background: #f59e0b;
    border-color: #f59e0b;
    color: white;
}

.bulk-actions .btn-danger {
    background: #ef4444;
    border-color: #ef4444;
    color: white;
}

.bulk-actions .btn-info {
    background: #06b6d4;
    border-color: #06b6d4;
    color: white;
}

.bulk-actions .btn-primary {
    background: #3b82f6;
    border-color: #3b82f6;
    color: white;
}

.bulk-actions #selected-attendance-count {
    color: #374151;
    font-weight: 600;
    font-size: 0.9rem;
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
                    ['title' => 'Attendance Records', 'url' => '']
                ]); ?>
            </div>
        </div>
        
        <!-- Attendance Records Card -->
        <div class="card">
            <div class="card-header">
                <div class="row align-items-center">
                    <div class="col-12 col-md-6">
                        <h5 class="card-title mb-0">
                            <i class="bx bx-clipboard me-2"></i>Attendance Records
                            <span id="student-filter-indicator" class="badge bg-info ms-2" style="display: none;">
                                <i class="bx bx-user me-1"></i>Filtered by Student
                            </span>
                        </h5>
                    </div>
                    <div class="col-12 col-md-6 mt-2 mt-md-0">
                        <div class="d-flex flex-wrap gap-2 justify-content-md-end">
                            <button class="btn btn-success" onclick="exportAttendance()">
                                <i class="bx bx-download me-1"></i>
                                <span class="btn-text">Export</span>
                            </button>
                            <button class="btn btn-primary" onclick="loadAttendance()">
                                <i class="bx bx-refresh me-1"></i>
                                <span class="btn-text">Refresh</span>
                            </button>
                            <button class="btn btn-info" onclick="toggleFilterPanel()">
                                <i class="bx bx-filter me-1"></i>
                                <span class="btn-text">Filters</span>
                            </button>

                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filter Panel -->
            <div id="filter-panel" class="card-body border-top" style="display: none;">
                <div class="row g-3 mt-3">
                    <!-- Date Range Presets -->
                    <div class="col-12">
                        <label class="form-label fw-semibold">Quick Date Ranges</label>
                        <div class="btn-group d-flex flex-wrap gap-2" role="group">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="applyDatePreset('today')">Today</button>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="applyDatePreset('yesterday')">Yesterday</button>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="applyDatePreset('last7days')">Last 7 Days</button>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="applyDatePreset('last30days')">Last 30 Days</button>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="applyDatePreset('thisweek')">This Week</button>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="applyDatePreset('lastweek')">Last Week</button>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="applyDatePreset('thismonth')">This Month</button>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="applyDatePreset('lastmonth')">Last Month</button>
                        </div>
                    </div>
                    <div class="col-12"><hr class="my-2"></div>
                    <div class="col-md-3">
                        <label for="date-from" class="form-label">From Date</label>
                        <input type="date" id="date-from" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label for="date-to" class="form-label">To Date</label>
                        <input type="date" id="date-to" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label for="student-filter" class="form-label">Student</label>
                        <select id="student-filter" class="form-select">
                            <option value="">All Students</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="status-filter" class="form-label">Status</label>
                        <select id="status-filter" class="form-select">
                            <option value="">All Status</option>
                            <option value="Check-in">Check-in</option>
                            <option value="Checked-out">Checked-out</option>
                            <option value="Present">Present</option>
                            <option value="Absent">Absent</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="program-filter" class="form-label">Program</label>
                        <select id="program-filter" class="form-select">
                            <option value="">All Programs</option>
                            <option value="SWT">SWT - Software Technology (Morning)</option>
                            <option value="ESWT">ESWT - Software Technology (Evening)</option>
                            <option value="CIT">CIT - Computer Information Technology (Morning)</option>
                            <option value="ECIT">ECIT - Computer Information Technology (Evening)</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="shift-filter" class="form-label">Shift</label>
                        <select id="shift-filter" class="form-select">
                            <option value="">All Shifts</option>
                            <option value="Morning">Morning</option>
                            <option value="Evening">Evening</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="search-input" class="form-label">Search</label>
                        <input type="text" id="search-input" class="form-control" placeholder="Search by student name or ID...">
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary" onclick="applyFilters()">
                            <i class="bx bx-search me-1"></i>Apply Filters
                        </button>
                        <button class="btn btn-info" onclick="clearFilters()">
                            <i class="bx bx-home me-1"></i>Reset to Default
                        </button>
                        <button class="btn btn-secondary" onclick="clearAllFilters()">
                            <i class="bx bx-x me-1"></i>Clear All
                        </button>
                        <button class="btn btn-warning" onclick="toggleBulkModeAttendance()">
                                <i class="bx bx-checkbox-square me-1"></i>
                                <span class="btn-text">Bulk Actions</span>
                            </button>
                    </div>
                </div>
            </div>
            
            <!-- Attendance Table -->
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="attendance-table">
                        <thead>
                            <tr>
                                <th width="50" class="bulk-checkbox-column">
                                    <input type="checkbox" id="select-all-attendance" onchange="toggleSelectAllAttendance()">
                                </th>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Program</th>
                                <th>Shift</th>
                                <th>Date & Time</th>
                                <th>Status</th>
                                <th>Duration</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="9" class="text-center py-4">
                                    <div class="spinner-border" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <div class="mt-2">Loading attendance records...</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div id="pagination" class="d-flex justify-content-center mt-4">
                    <!-- Pagination will be loaded here -->
                </div>
                
                <!-- Bulk Actions Panel for Attendance -->
                <div id="bulk-attendance-actions" class="bulk-actions">
                    <div class="d-flex align-items-center gap-3">
                        <span class="text-muted" id="selected-attendance-count">0 selected</span>
                        <div class="ms-auto d-flex flex-wrap gap-2">
                             <button class="btn btn-sm btn-danger" onclick="bulkDeleteAttendance()">
                                 <i class="bx bx-trash me-1"></i>
                                 <span class="bulk-btn-text">Delete</span>
                             </button>
                             <button class="btn btn-sm btn-primary" onclick="bulkChangeStatus()">
                                 <i class="bx bx-edit me-1"></i>
                                 <span class="bulk-btn-text">Change Status</span>
                             </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- / Content -->
</div>
<!-- Content wrapper -->

<!-- Attendance Details Modal -->
<div class="modal fade" id="attendanceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="attendanceModalTitle">Attendance Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="attendanceModalBody">
                <!-- Content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="editAttendanceBtn" style="display: none;">
                    <i class="bx bx-edit me-1"></i>Edit
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Attendance Modal -->
<div class="modal fade" id="editAttendanceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Attendance Record</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editAttendanceForm">
                <div class="modal-body">
                    <input type="hidden" id="edit-attendance-id" name="id">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="mb-3">
                        <label for="edit-status" class="form-label">Status *</label>
                        <select id="edit-status" name="status" class="form-select" required>
                            <option value="Check-in">Check-in</option>
                            <option value="Absent">Absent</option>
                            <option value="Present">Present</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit-notes" class="form-label">Notes</label>
                        <textarea id="edit-notes" name="notes" class="form-control" rows="3" placeholder="Additional notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bx bx-save me-1"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this attendance record? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDelete">
                    <i class="bx bx-trash me-1"></i>Delete Record
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Alert Container -->

<!-- Core JS -->
<script src="<?php echo getAdminAssetUrl('vendor/libs/jquery/jquery.js'); ?>"></script>
<script src="<?php echo getAdminAssetUrl('vendor/libs/popper/popper.js'); ?>"></script>
<script src="<?php echo getAdminAssetUrl('vendor/js/bootstrap.js'); ?>"></script>
<script src="<?php echo getAdminAssetUrl('vendor/libs/perfect-scrollbar/perfect-scrollbar.js'); ?>"></script>
<script src="<?php echo getAdminAssetUrl('vendor/js/menu.js'); ?>"></script>

<!-- Main JS -->
<script src="<?php echo getAdminAssetUrl('js/main.js'); ?>"></script>

<!-- Page JS -->
<script src="<?php echo getAdminAssetUrl('js/admin.js?v=' . time()); ?>"></script>

<script>
// Attendance page specific JavaScript
let attendanceCurrentPage = 1;
let currentFilters = {};
let deleteAttendanceId = null;

document.addEventListener('DOMContentLoaded', function() {
    // Check for URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    const studentIdParam = urlParams.get('student_id');
    
    // Set default filter values in the UI
    const today = new Date();
    const todayString = today.toISOString().split('T')[0];
    
    // Set filter values in the UI
    document.getElementById('date-from').value = todayString;
    document.getElementById('date-to').value = todayString;
    document.getElementById('status-filter').value = '';
    
    // Setup filter handlers
    setupFilterHandlers();
    
    // Load student options first, then apply student filter if needed
    loadStudentOptions().then(() => {
        // If student_id parameter exists, set it in the filter after options are loaded
        if (studentIdParam) {
            const studentSelect = document.getElementById('student-filter');
            
            // Try to set the value in the dropdown
            studentSelect.value = studentIdParam;
            
            // If the option wasn't found, try to find it manually
            if (studentSelect.value !== studentIdParam) {
                for (let i = 0; i < studentSelect.options.length; i++) {
                    if (studentSelect.options[i].value === studentIdParam) {
                        studentSelect.selectedIndex = i;
                        break;
                    }
                }
            }
            
            // Apply the student filter immediately
            currentFilters.student_id = studentIdParam;
            
            // Show student filter indicator
            const indicator = document.getElementById('student-filter-indicator');
            if (indicator) {
                indicator.style.display = 'inline-block';
            }
        }
        
        // Then load attendance with filters
    loadAttendance();
    });
    
    // Setup form handlers
    setupFormHandlers();
    
    // Initialize modal form UX enhancements
    if (typeof UIHelpers !== 'undefined') {
        UIHelpers.initModalFormUX('#editAttendanceModal', {
            autoFocus: true,
            optimizeTabOrder: true,
            enterKeySubmit: true
        });
    }
});

function setupFormHandlers() {
    // Edit attendance form submission
    const editForm = document.getElementById('editAttendanceForm');
    if (editForm) {
        console.log('Setting up edit form handler');
        editForm.addEventListener('submit', function(e) {
            console.log('Form submit event triggered');
            e.preventDefault();
            saveAttendanceEdit();
        });
    } else {
        console.error('Edit form not found!');
    }
    
    // Delete confirmation
    document.getElementById('confirmDelete').addEventListener('click', function() {
        if (deleteAttendanceId) {
            deleteAttendance(deleteAttendanceId);
        }
    });
    
    // Edit button
    document.getElementById('editAttendanceBtn').addEventListener('click', function() {
        const attendanceId = this.getAttribute('data-attendance-id');
        openEditModal(attendanceId);
    });
    
    // Save button click handler (backup)
    const saveButton = document.querySelector('#editAttendanceModal button[type="submit"]');
    if (saveButton) {
        saveButton.addEventListener('click', function(e) {
            console.log('Save button clicked directly');
            e.preventDefault();
            saveAttendanceEdit();
        });
    }
}

function setupFilterHandlers() {
    // Set default filters: Today's Check-in and Present students
    const today = new Date();
    const todayString = today.toISOString().split('T')[0];
    
    // Use setTimeout to ensure DOM elements are ready
    setTimeout(() => {
        const dateFromEl = document.getElementById('date-from');
        const dateToEl = document.getElementById('date-to');
        const statusEl = document.getElementById('status-filter');
        
        if (dateFromEl) dateFromEl.value = todayString;
        if (dateToEl) dateToEl.value = todayString;
        if (statusEl) statusEl.value = ''; // Leave status filter empty to show all
        
        // Apply the default filters immediately
        currentFilters = {
            date_from: todayString,
            date_to: todayString,
            status: 'Check-in,Checked-out,Present'
        };
    }, 100);
}

function loadAttendance(page = 1) {
    attendanceCurrentPage = page;
    
    // If no filters are set, apply default filters (today's check-in and present students)
    if (Object.keys(currentFilters).length === 0) {
        const today = new Date();
        const todayString = today.toISOString().split('T')[0];
        currentFilters = {
            date_from: todayString,
            date_to: todayString,
            status: 'Check-in,Checked-out,Present'  // Multiple statuses separated by comma
        };
    }
    
    // Always check if student filter is set in the UI and apply it
    const studentFilter = document.getElementById('student-filter').value;
    if (studentFilter) {
        currentFilters.student_id = studentFilter;
    } else if (currentFilters.student_id) {
        // Keep existing student filter if UI doesn't have one set
    }
    
    const params = new URLSearchParams({
        action: 'list',
        page: page,
        limit: 20,
        ...currentFilters
    });
    
    console.log('API URL:', `api/attendance.php?${params}`);
    
    fetch(`api/attendance.php?${params}`)
        .then(response => {
            // Check if response is ok
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            // Check content type
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                // If not JSON, try to get text to see what the error is
                return response.text().then(text => {
                    console.error('Non-JSON response from attendance API:', text.substring(0, 500));
                    throw new Error('Server returned non-JSON response. Check console for details.');
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                updateAttendanceTable(data.data.records);
                updatePagination(data.data.pagination);
            } else {
                showAlert('Error loading attendance: ' + data.error, 'danger');
            }
        })
        .catch(error => {
            console.error('Error loading attendance:', error);
            showAlert('Error loading attendance', 'danger');
        });
}

function loadStudentOptions() {
    return fetch('api/students.php?action=list&limit=1000')
        .then(response => {
            // Check if response is ok
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            // Check content type
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                // If not JSON, try to get text to see what the error is
                return response.text().then(text => {
                    console.error('Non-JSON response:', text.substring(0, 500));
                    throw new Error('Server returned non-JSON response. Check console for details.');
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                const select = document.getElementById('student-filter');
                select.innerHTML = '<option value="">All Students</option>';
                
                data.data.students.forEach(student => {
                    const option = document.createElement('option');
                    option.value = student.student_id || student.roll_number; // Use actual student ID string
                    option.textContent = `${student.roll_number} - ${student.name}`;
                    select.appendChild(option);
                });
            } else {
                console.error('API returned error:', data.error);
                showAlert('Error loading students: ' + (data.error || 'Unknown error'), 'warning');
            }
            return data;
        })
        .catch(error => {
            console.error('Error loading students:', error);
            // Show a user-friendly error message
            showAlert('Could not load student list. Please refresh the page.', 'danger');
            throw error;
        });
}

function updateAttendanceTable(records) {
    const tbody = document.querySelector('#attendance-table tbody');
    
    if (records.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4">No attendance records found</td></tr>';
        return;
    }
    
    tbody.innerHTML = records.map(record => {
        // Compute display program code based on shift
        const displayProgramCode = record.shift === 'Evening' ? 'E' + record.program : record.program;
        
        return `
        <tr>
            <td class="bulk-checkbox-column">
                <input type="checkbox" data-attendance-id="${record.id}" onchange="updateSelectedAttendanceCount()">
            </td>
            <td><strong>${record.roll_number}</strong></td>
            <td>${record.student_name}</td>
            <td><span class="badge bg-primary">${displayProgramCode}</span></td>
            <td><span class="badge bg-${record.shift === 'Morning' ? 'success' : 'info'}">${record.shift}</span></td>
            <td>
                <div>${record.date}</div>
                <small class="text-muted">${record.time}</small>
            </td>
            <td>
                <span class="badge bg-${getStatusColor(record.status)}">${record.status.toUpperCase()}</span>
            </td>
            <td>${record.duration || '-'}</td>
            <td>
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        Actions
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" onclick="viewAttendance(${record.id})">
                            <i class="bx bx-show me-2"></i>View Details
                        </a></li>
                        <li><a class="dropdown-item" href="#" onclick="editAttendanceRecord(${record.id})">
                            <i class="bx bx-edit me-2"></i>Edit
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="#" onclick="confirmDelete(${record.id})">
                            <i class="bx bx-trash me-2"></i>Delete
                        </a></li>
                    </ul>
                </div>
            </td>
        </tr>
        `;
    }).join('');
}

function updatePagination(pagination) {
    const container = document.getElementById('pagination');
    
    if (pagination.total_pages <= 1) {
        container.innerHTML = '';
        return;
    }
    
    let html = '<nav aria-label="Page navigation"><ul class="pagination">';
    
    // Previous button
    if (pagination.current_page > 1) {
        html += `<li class="page-item"><a class="page-link" href="#" onclick="loadAttendance(${pagination.current_page - 1})">Previous</a></li>`;
    } else {
        html += '<li class="page-item disabled"><span class="page-link">Previous</span></li>';
    }
    
    // Page numbers
    for (let i = 1; i <= pagination.total_pages; i++) {
        if (i === pagination.current_page) {
            html += `<li class="page-item active"><span class="page-link">${i}</span></li>`;
        } else {
            html += `<li class="page-item"><a class="page-link" href="#" onclick="loadAttendance(${i})">${i}</a></li>`;
        }
    }
    
    // Next button
    if (pagination.current_page < pagination.total_pages) {
        html += `<li class="page-item"><a class="page-link" href="#" onclick="loadAttendance(${pagination.current_page + 1})">Next</a></li>`;
    } else {
        html += '<li class="page-item disabled"><span class="page-link">Next</span></li>';
    }
    
    html += '</ul></nav>';
    container.innerHTML = html;
}

function getStatusColor(status) {
    switch (status) {
        case 'Present': return 'success';
        case 'Absent': return 'danger';
        case 'Check-in': return 'info';
        case 'Checked-out': return 'warning';
        default: return 'secondary';
    }
}

function viewAttendance(attendanceId) {
    fetch(`api/attendance.php?action=view&id=${attendanceId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const record = data.data;
                const modalBody = document.getElementById('attendanceModalBody');
                const editBtn = document.getElementById('editAttendanceBtn');
                
                modalBody.innerHTML = `
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Student Information</h6>
                            <p><strong>Name:</strong> ${record.student_name}</p>
                            <p><strong>Roll Number:</strong> ${record.roll_number}</p>
                            <p><strong>Program:</strong> ${record.program}</p>
                            <p><strong>Shift:</strong> ${record.shift}</p>
                        </div>
                        <div class="col-md-6">
                            <h6>Attendance Details</h6>
                            <p><strong>Date:</strong> ${record.date}</p>
                            <p><strong>Time:</strong> ${record.time}</p>
                            <p><strong>Status:</strong> <span class="badge bg-${getStatusColor(record.status)}">${record.status.toUpperCase()}</span></p>
                            <p><strong>Duration:</strong> ${record.duration || 'N/A'}</p>
                        </div>
                    </div>
                    ${record.notes ? `<div class="mt-3"><h6>Notes</h6><p>${record.notes}</p></div>` : ''}
                `;
                
                editBtn.setAttribute('data-attendance-id', attendanceId);
                editBtn.style.display = 'inline-block';
                
                const modal = new bootstrap.Modal(document.getElementById('attendanceModal'));
                modal.show();
            } else {
                showAlert('Error loading attendance details', 'danger');
            }
        })
        .catch(error => {
            console.error('Error loading attendance:', error);
            showAlert('Error loading attendance details', 'danger');
        });
}

function editAttendanceRecord(attendanceId) {
    openEditModal(attendanceId);
}

function openEditModal(attendanceId) {
    fetch(`api/attendance.php?action=view&id=${attendanceId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const record = data.data;
                
                document.getElementById('edit-attendance-id').value = record.id;
                document.getElementById('edit-status').value = record.status;
                document.getElementById('edit-notes').value = record.notes || '';
                
                const modal = new bootstrap.Modal(document.getElementById('editAttendanceModal'));
                modal.show();
            } else {
                showAlert('Error loading attendance data: ' + data.error, 'danger');
            }
        })
        .catch(error => {
            console.error('Error loading attendance:', error);
            showAlert('Error loading attendance data', 'danger');
        });
}

function saveAttendanceEdit() {
    console.log('saveAttendanceEdit called');
    
    const form = document.getElementById('editAttendanceForm');
    const formData = new FormData(form);
    const attendanceId = document.getElementById('edit-attendance-id').value;
    const submitBtn = form.querySelector('button[type="submit"]') || document.querySelector('#editAttendanceModal .btn-primary');
    
    // Convert FormData to JSON
    const jsonData = {
        status: formData.get('status'),
        notes: formData.get('notes'),
        csrf_token: formData.get('csrf_token')
    };
    
    console.log('JSON data:', jsonData);
    console.log('Attendance ID:', attendanceId);
    
    // Show loading state
    if (submitBtn) {
        UIHelpers.showButtonLoading(submitBtn, 'Updating...');
    }
    UIHelpers.disableForm(form);
    
    fetch(`api/attendance.php?action=update&id=${attendanceId}`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(jsonData)
    })
    .then(response => {
        console.log('Response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('Response data:', data);
        if (submitBtn) {
            UIHelpers.hideButtonLoading(submitBtn);
        }
        UIHelpers.enableForm(form);
        
        if (data.success) {
            UIHelpers.showSuccess(data.message || 'Attendance updated successfully!');
            bootstrap.Modal.getInstance(document.getElementById('editAttendanceModal')).hide();
            loadAttendance(attendanceCurrentPage);
        } else {
            UIHelpers.showError(data.error || 'Error updating attendance');
        }
    })
    .catch(error => {
        console.error('Error saving attendance:', error);
        if (submitBtn) {
            UIHelpers.hideButtonLoading(submitBtn);
        }
        UIHelpers.enableForm(form);
        UIHelpers.showError('Error saving attendance: ' + error.message);
    });
}

function confirmDelete(attendanceId) {
    UIHelpers.showConfirmDialog({
        title: 'Delete Attendance Record',
        message: 'Are you sure you want to delete this attendance record? This action cannot be undone.',
        confirmText: 'Yes, Delete',
        cancelText: 'Cancel',
        confirmClass: 'btn-danger',
        onConfirm: () => {
            deleteAttendance(attendanceId);
        }
    });
}

function deleteAttendance(attendanceId) {
    UIHelpers.showLoadingOverlay('.card-body', 'Deleting attendance record...');
    
    // Fetch CSRF token first
    fetch('api/attendance.php?action=get_csrf_token')
        .then(r => r.json())
        .then(tok => {
            const token = (tok && tok.token) ? tok.token : '';
            return fetch(`api/attendance.php?action=delete&id=${attendanceId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-Token': token
                }
            });
        })
        .then(response => {
            const ct = response.headers.get('content-type') || '';
            if (ct.includes('application/json')) return response.json();
            return response.text().then(text => ({ success: false, error: text.substring(0, 200) }));
        })
        .then(data => {
            UIHelpers.hideLoadingOverlay('.card-body');
            
            if (data && data.success) {
                UIHelpers.showSuccess((data.message) || 'Attendance deleted successfully!');
                loadAttendance(attendanceCurrentPage);
            } else {
                UIHelpers.showError((data && data.error) || 'Error deleting attendance');
            }
        })
        .catch(error => {
            console.error('Error deleting attendance:', error);
            UIHelpers.hideLoadingOverlay('.card-body');
            UIHelpers.showError('Error deleting attendance');
        });
}

function toggleFilterPanel() {
    const panel = document.getElementById('filter-panel');
    panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
}

function applyFilters() {
    currentFilters = {
        date_from: document.getElementById('date-from').value,
        date_to: document.getElementById('date-to').value,
        student_id: document.getElementById('student-filter').value,
        status: document.getElementById('status-filter').value,
        program: document.getElementById('program-filter').value,
        shift: document.getElementById('shift-filter').value,
        search: document.getElementById('search-input').value
    };
    
    loadAttendance(1);
}

function clearFilters() {
    // Reset to default view: Today's Check-in and Present students
    const today = new Date();
    const todayString = today.toISOString().split('T')[0];
    
    document.getElementById('date-from').value = todayString;
    document.getElementById('date-to').value = todayString;
    document.getElementById('student-filter').value = '';
    document.getElementById('status-filter').value = '';
    document.getElementById('program-filter').value = '';
    document.getElementById('shift-filter').value = '';
    document.getElementById('search-input').value = '';
    
    // Hide student filter indicator
    const indicator = document.getElementById('student-filter-indicator');
    if (indicator) {
        indicator.style.display = 'none';
    }
    
    currentFilters = {
        date_from: todayString,
        date_to: todayString,
        status: 'Check-in,Checked-out,Present'
    };
    loadAttendance(1);
}

function clearAllFilters() {
    // Clear all filters to show all records
    document.getElementById('date-from').value = '';
    document.getElementById('date-to').value = '';
    document.getElementById('student-filter').value = '';
    document.getElementById('status-filter').value = '';
    document.getElementById('program-filter').value = '';
    document.getElementById('shift-filter').value = '';
    document.getElementById('search-input').value = '';
    
    // Hide student filter indicator
    const indicator = document.getElementById('student-filter-indicator');
    if (indicator) {
        indicator.style.display = 'none';
    }
    
    currentFilters = {};
    loadAttendance(1);
}

/**
 * Apply date range preset
 * @param {string} preset - Preset name (today, yesterday, last7days, etc.)
 */
function applyDatePreset(preset) {
    const today = new Date();
    let fromDate, toDate;
    
    switch(preset) {
        case 'today':
            fromDate = toDate = new Date(today);
            break;
            
        case 'yesterday':
            fromDate = toDate = new Date(today);
            fromDate.setDate(today.getDate() - 1);
            toDate.setDate(today.getDate() - 1);
            break;
            
        case 'last7days':
            fromDate = new Date(today);
            fromDate.setDate(today.getDate() - 6);
            toDate = new Date(today);
            break;
            
        case 'last30days':
            fromDate = new Date(today);
            fromDate.setDate(today.getDate() - 29);
            toDate = new Date(today);
            break;
            
        case 'thisweek':
            // Get start of week (Sunday)
            fromDate = new Date(today);
            fromDate.setDate(today.getDate() - today.getDay());
            toDate = new Date(today);
            break;
            
        case 'lastweek':
            // Get start of last week (Sunday)
            fromDate = new Date(today);
            fromDate.setDate(today.getDate() - today.getDay() - 7);
            // Get end of last week (Saturday)
            toDate = new Date(fromDate);
            toDate.setDate(fromDate.getDate() + 6);
            break;
            
        case 'thismonth':
            fromDate = new Date(today.getFullYear(), today.getMonth(), 1);
            toDate = new Date(today);
            break;
            
        case 'lastmonth':
            fromDate = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            toDate = new Date(today.getFullYear(), today.getMonth(), 0);
            break;
            
        default:
            UIHelpers.showWarning('Invalid date preset');
            return;
    }
    
    // Format dates as YYYY-MM-DD
    const formatDate = (date) => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };
    
    // Set date inputs
    document.getElementById('date-from').value = formatDate(fromDate);
    document.getElementById('date-to').value = formatDate(toDate);
    
    // Show success message with readable date range
    const presetNames = {
        'today': 'Today',
        'yesterday': 'Yesterday',
        'last7days': 'Last 7 Days',
        'last30days': 'Last 30 Days',
        'thisweek': 'This Week',
        'lastweek': 'Last Week',
        'thismonth': 'This Month',
        'lastmonth': 'Last Month'
    };
    
    UIHelpers.showInfo(`Date range set to: ${presetNames[preset]}`);
    
    // Automatically apply filters
    applyFilters();
}

function exportAttendance() {
    const params = new URLSearchParams(currentFilters);
    window.open(`api/attendance.php?action=export&${params}`, '_blank');
}


// Bulk Operations Functions for Attendance
function toggleBulkModeAttendance() {
    const table = document.getElementById('attendance-table');
    const bulkPanel = document.getElementById('bulk-attendance-actions');
    
    if (table.classList.contains('bulk-mode')) {
        // Exit bulk mode
        table.classList.remove('bulk-mode');
        bulkPanel.classList.remove('show');
        clearAllAttendanceSelections();
    } else {
        // Enter bulk mode
        table.classList.add('bulk-mode');
    }
}

function toggleSelectAllAttendance() {
    const selectAllCheckbox = document.getElementById('select-all-attendance');
    const checkboxes = document.querySelectorAll('input[type="checkbox"][data-attendance-id]');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAllCheckbox.checked;
    });
    
    updateSelectedAttendanceCount();
}

function updateSelectedAttendanceCount() {
    const checkboxes = document.querySelectorAll('input[type="checkbox"][data-attendance-id]:checked');
    const countElement = document.getElementById('selected-attendance-count');
    const bulkPanel = document.getElementById('bulk-attendance-actions');
    
    countElement.textContent = `${checkboxes.length} selected`;
    
    if (checkboxes.length > 0) {
        bulkPanel.classList.add('show');
    } else {
        bulkPanel.classList.remove('show');
    }
}

function getSelectedAttendance() {
    const checkboxes = document.querySelectorAll('input[type="checkbox"][data-attendance-id]:checked');
    return Array.from(checkboxes).map(checkbox => checkbox.dataset.attendanceId);
}

function bulkDeleteAttendance() {
    const selectedIds = getSelectedAttendance();
    if (selectedIds.length === 0) {
        UIHelpers.showWarning('Please select attendance records to delete');
        return;
    }
    
    UIHelpers.showConfirmDialog({
        title: 'Delete Attendance Records',
        message: `Are you sure you want to delete ${selectedIds.length} attendance record(s)? This action cannot be undone.`,
        confirmText: 'Yes, Delete',
        cancelText: 'Cancel',
        confirmClass: 'btn-danger',
        onConfirm: () => {
            performBulkActionAttendance('delete', selectedIds);
        }
    });
}

function bulkChangeStatus() {
    const selectedIds = getSelectedAttendance();
    if (selectedIds.length === 0) {
        UIHelpers.showWarning('Please select attendance records to change status');
        return;
    }
    
    // Show bulk status change modal
    showBulkStatusChangeModal(selectedIds);
}

function performBulkActionAttendance(action, ids) {
    const data = {
        action: `bulk_${action}_attendance`,
        ids: ids
    };
    
    console.log('Bulk action data:', data);
    console.log('API URL:', 'api/attendance.php');
    
    UIHelpers.showLoadingOverlay('.card-body', `${action.charAt(0).toUpperCase() + action.slice(1)}ing ${ids.length} record(s)...`);
    
    fetch('api/attendance.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        UIHelpers.hideLoadingOverlay('.card-body');
        
        if (result.success) {
            UIHelpers.showSuccess(result.message || `Successfully ${action}d ${ids.length} record(s)`);
            clearAllAttendanceSelections();
            loadAttendance(attendanceCurrentPage);
        } else {
            UIHelpers.showError(result.error || 'Operation failed');
        }
    })
    .catch(error => {
        console.error('Bulk action error:', error);
        UIHelpers.hideLoadingOverlay('.card-body');
        UIHelpers.showError('Error performing bulk action');
    });
}

function clearAllAttendanceSelections() {
    const selectAllCheckbox = document.getElementById('select-all-attendance');
    const checkboxes = document.querySelectorAll('input[type="checkbox"][data-attendance-id]');
    const bulkPanel = document.getElementById('bulk-attendance-actions');
    
    selectAllCheckbox.checked = false;
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
    
    bulkPanel.classList.remove('show');
    updateSelectedAttendanceCount();
}

// Bulk Status Change Modal
function showBulkStatusChangeModal(selectedIds) {
    // Create modal if it doesn't exist
    let modal = document.getElementById('bulkStatusChangeModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'bulkStatusChangeModal';
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Bulk Change Status</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">New Status</label>
                            <select id="bulk-status-select" class="form-select">
                                <option value="">Select Status</option>
                                <option value="Check-in">Check-in</option>
                                <option value="Absent">Absent</option>
                                <option value="Present">Present</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes (Optional)</label>
                            <textarea id="bulk-status-notes" class="form-control" rows="3" placeholder="Add notes for the status change..."></textarea>
                        </div>
                        <div class="alert alert-info">
                            <i class="bx bx-info-circle me-2"></i>
                            This will change the status for <span id="bulk-status-selected-count">0</span> selected attendance records.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" onclick="confirmBulkStatusChange()">
                            <i class="bx bx-check me-1"></i>Apply Changes
                        </button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }
    
    // Update selected count
    document.getElementById('bulk-status-selected-count').textContent = selectedIds.length;
    
    // Store selected IDs for later use
    modal.dataset.selectedIds = selectedIds.join(',');
    
    // Show modal
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
}

function confirmBulkStatusChange() {
    const modal = document.getElementById('bulkStatusChangeModal');
    if (!modal) {
        console.error('Bulk status change modal not found');
        return;
    }
    
    const selectedIds = modal.dataset.selectedIds ? modal.dataset.selectedIds.split(',') : [];
    const statusSelect = document.getElementById('bulk-status-select');
    const notesTextarea = document.getElementById('bulk-status-notes');
    
    if (!statusSelect || !notesTextarea) {
        console.error('Bulk status change form elements not found');
        return;
    }
    
    const status = statusSelect.value;
    const notes = notesTextarea.value;
    const submitBtn = modal.querySelector('.btn-primary');
    
    if (!status) {
        UIHelpers.showWarning('Please select a status');
        return;
    }
    
    const data = {
        action: 'bulk_change_status',
        ids: selectedIds,
        status: status,
        notes: notes
    };
    
    UIHelpers.showButtonLoading(submitBtn, 'Updating...');
    
    fetch('api/attendance.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        UIHelpers.hideButtonLoading(submitBtn);
        
        if (result.success) {
            UIHelpers.showSuccess(result.message || `Status updated for ${selectedIds.length} record(s)`);
            bootstrap.Modal.getInstance(modal).hide();
            clearAllAttendanceSelections();
            loadAttendance(attendanceCurrentPage);
        } else {
            UIHelpers.showError(result.error || 'Error changing status');
        }
    })
    .catch(error => {
        console.error('Bulk status change error:', error);
        UIHelpers.hideButtonLoading(submitBtn);
        UIHelpers.showError('Error changing status');
    });
}
</script>

<style>
/* Bulk Actions Panel */
.bulk-actions {
    position: fixed;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 1rem 1.5rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    z-index: 1000;
    display: none;
}

.bulk-actions.show {
    display: block;
}

/* Bulk Mode - Hide checkboxes by default */
.bulk-checkbox-column {
    display: none;
}

.bulk-mode .bulk-checkbox-column {
    display: table-cell;
}

/* Responsive button text */
@media (max-width: 768px) {
    .btn-text {
        display: none;
    }
    
    .d-flex.flex-wrap.gap-2 .btn {
        padding: 0.5rem 0.75rem;
    }
    
    .bulk-actions {
        bottom: 10px;
        left: 10px;
        right: 10px;
        transform: none;
        padding: 0.75rem 1rem;
    }
    
    .bulk-actions .d-flex {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .bulk-actions .ms-auto {
        margin-left: 0 !important;
        margin-top: 0.5rem;
    }
    
    .bulk-actions .btn-sm {
        font-size: 0.75rem;
        padding: 0.375rem 0.5rem;
    }
}

@media (min-width: 769px) {
    .btn i.me-1 {
        margin-right: 0.5rem !important;
    }
}

/* Ensure attendance table has no height restrictions */
.table-responsive {
    max-height: none !important;
    overflow-y: visible !important;
}
</style>

</body>
</html>
