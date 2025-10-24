<?php
/**
 * Programs & Sections Management Page
 * Manage programs and sections with CRUD operations
 */

require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';

// Require admin authentication
requireAdminAuth();

$pageTitle = "Programs & Sections";
$currentPage = "program_sections";
$pageCSS = ['css/responsive-buttons.css'];
$pageJS = ['js/admin.js'];

include 'partials/header.php';
include 'partials/sidebar.php';
include 'partials/navbar.php';
?>

<style>
/* Custom layout styling for buttons and filters */
.btn-container {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.75rem;
}

.btn-container .btn {
    border-radius: 0.375rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    transition: all 0.2s ease;
}

.btn-container .btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

.btn-container .form-select {
    border-radius: 0.375rem;
    border: 1px solid #d1d5db;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    transition: all 0.2s ease;
}

.btn-container .form-select:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

/* Responsive button text */
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
                    ['title' => 'Programs & Sections', 'url' => '']
                ]); ?>
            </div>
        </div>
        
        <!-- Programs Management Card -->
        <div class="card mb-4">
            <div class="card-header">
                <div class="row align-items-center">
                    <div class="col-12 col-md-6">
                        <h5 class="card-title mb-0">
                            <i class="bx bx-graduation-cap me-2"></i>Programs Management
                        </h5>
                    </div>
                    <div class="col-12 col-md-6 mt-2 mt-md-0">
                        <div class="d-flex flex-wrap gap-2 justify-content-md-end">
                            <!-- Add Program Button -->
                            <button class="btn btn-primary" onclick="openProgramModal()">
                                <i class="bx bx-plus me-1"></i>
                                <span class="btn-text">Add</span>
                            </button>
                            
                            <!-- Export Button -->
                            <button class="btn btn-success" onclick="exportPrograms()">
                                <i class="bx bx-download me-1"></i>
                                <span class="btn-text">Export</span>
                            </button>
                            
                            <!-- Filter Toggle Button -->
                            <button class="btn btn-info" onclick="toggleProgramFilterPanel()">
                                <i class="bx bx-filter me-1"></i>
                                <span class="btn-text">Filters</span>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Advanced Filter Panel for Programs -->
                <div id="program-filter-panel" class="card-body border-top" style="display: none;">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label for="program-status-filter" class="form-label">Status</label>
                            <select id="program-status-filter" class="form-select">
                                <option value="">All Status</option>
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="program-search-filter" class="form-label">Search</label>
                            <input type="text" id="program-search-filter" class="form-control" placeholder="Search by name or code...">
                        </div>
                        <div class="col-md-3">
                            <label for="program-student-count-filter" class="form-label">Student Count</label>
                            <select id="program-student-count-filter" class="form-select">
                                <option value="">All Programs</option>
                                <option value="0">No Students</option>
                                <option value="1-10">1-10 Students</option>
                                <option value="11-50">11-50 Students</option>
                                <option value="51+">51+ Students</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="program-section-count-filter" class="form-label">Section Count</label>
                            <select id="program-section-count-filter" class="form-select">
                                <option value="">All Programs</option>
                                <option value="0">No Sections</option>
                                <option value="1-3">1-3 Sections</option>
                                <option value="4-10">4-10 Sections</option>
                                <option value="11+">11+ Sections</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <button class="btn btn-primary" onclick="applyProgramFilters()">
                                <i class="bx bx-search me-1"></i>Apply Filters
                            </button>
                            <button class="btn btn-secondary" onclick="clearProgramFilters()">
                                <i class="bx bx-x me-1"></i>Clear Filters
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Programs Table -->
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="programs-table">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Students</th>
                                <th>Sections</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="6" class="text-center py-4">
                                    <div class="spinner-border" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <div class="mt-2">Loading programs...</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Sections Management Card -->
        <div class="card">
            <div class="card-header">
                <div class="row align-items-center">
                    <div class="col-12 col-md-6">
                        <h5 class="card-title mb-0">
                            <i class="bx bx-chalkboard me-2"></i>Sections Management
                        </h5>
                    </div>
                    <div class="col-12 col-md-6 mt-2 mt-md-0">
                        <div class="d-flex flex-wrap gap-2 justify-content-md-end">
                            <!-- Add Section Button -->
                            <button class="btn btn-primary" onclick="openSectionModal()">
                                <i class="bx bx-plus me-1"></i>
                                <span class="btn-text">Add</span>
                            </button>
                            
                            <!-- Export Button -->
                            <button class="btn btn-success" onclick="exportSections()">
                                <i class="bx bx-download me-1"></i>
                                <span class="btn-text">Export</span>
                            </button>
                            
                            <!-- Filter Toggle Button -->
                            <button class="btn btn-info" onclick="toggleSectionFilterPanel()">
                                <i class="bx bx-filter me-1"></i>
                                <span class="btn-text">Filters</span>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Advanced Filter Panel for Sections -->
                <div id="section-filter-panel" class="card-body border-top" style="display: none;">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label for="section-program-filter" class="form-label">Program</label>
                            <select id="section-program-filter" class="form-select">
                                <option value="">All Programs</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="section-year-filter" class="form-label">Year Level</label>
                            <select id="section-year-filter" class="form-select">
                                <option value="">All Years</option>
                                <option value="1st">1st Year</option>
                                <option value="2nd">2nd Year</option>
                                <option value="3rd">3rd Year</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="section-shift-filter" class="form-label">Shift</label>
                            <select id="section-shift-filter" class="form-select">
                                <option value="">All Shifts</option>
                                <option value="Morning">Morning</option>
                                <option value="Evening">Evening</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="section-search-filter" class="form-label">Search</label>
                            <input type="text" id="section-search-filter" class="form-control" placeholder="Search by section name...">
                        </div>
                        <div class="col-md-3">
                            <label for="section-capacity-filter" class="form-label">Capacity</label>
                            <select id="section-capacity-filter" class="form-select">
                                <option value="">All Capacities</option>
                                <option value="0-20">Small (0-20)</option>
                                <option value="21-40">Medium (21-40)</option>
                                <option value="41-60">Large (41-60)</option>
                                <option value="61+">Extra Large (61+)</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="section-utilization-filter" class="form-label">Utilization</label>
                            <select id="section-utilization-filter" class="form-select">
                                <option value="">All Utilization</option>
                                <option value="0-25">Low (0-25%)</option>
                                <option value="26-50">Medium (26-50%)</option>
                                <option value="51-75">High (51-75%)</option>
                                <option value="76-100">Full (76-100%)</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="section-status-filter" class="form-label">Status</label>
                            <select id="section-status-filter" class="form-select">
                                <option value="">All Status</option>
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <button class="btn btn-primary" onclick="applySectionFilters()">
                                <i class="bx bx-search me-1"></i>Apply Filters
                            </button>
                            <button class="btn btn-secondary" onclick="clearSectionFilters()">
                                <i class="bx bx-x me-1"></i>Clear Filters
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Sections Table -->
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="sections-table">
                        <thead>
                            <tr>
                                <th>Section</th>
                                <th>Program</th>
                                <th>Year</th>
                                <th>Shift</th>
                                <th>Capacity</th>
                                <th>Utilization</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <div class="spinner-border" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <div class="mt-2">Loading sections...</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <!-- / Content -->
</div>
<!-- Content wrapper -->

<!-- Program Modal -->
<div class="modal fade" id="programModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="programModalTitle">Add Program</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="programForm">
                <div class="modal-body">
                    <input type="hidden" id="program-id" name="id">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="mb-3">
                        <label for="program-code" class="form-label">Program Code *</label>
                        <input type="text" id="program-code" name="code" class="form-control" placeholder="e.g., SWT, CIT" required>
                        <div class="form-text">2-10 uppercase letters. Evening programs automatically get "E" prefix (e.g., SWT becomes ESWT for evening)</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="program-name" class="form-label">Program Name *</label>
                        <input type="text" id="program-name" name="name" class="form-control" placeholder="e.g., Software Technology" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="program-description" class="form-label">Description</label>
                        <textarea id="program-description" name="description" class="form-control" rows="3" placeholder="Program description..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="program-active" name="is_active" checked>
                            <label class="form-check-label" for="program-active">
                                Active Program
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bx bx-plus me-1"></i>Create Program
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Section Modal -->
<div class="modal fade" id="sectionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="sectionModalTitle">Add Section</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="sectionForm">
                <div class="modal-body">
                    <input type="hidden" id="section-id" name="id">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="section-program" class="form-label">Program *</label>
                            <select id="section-program" name="program_id" class="form-select" required>
                                <option value="">Select Program</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="section-year" class="form-label">Year Level *</label>
                            <select id="section-year" name="year_level" class="form-select" required>
                                <option value="">Select Year</option>
                                <option value="1st">1st Year</option>
                                <option value="2nd">2nd Year</option>
                                <option value="3rd">3rd Year</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="section-name" class="form-label">Section Name *</label>
                            <input type="text" id="section-name" name="section_name" class="form-control" placeholder="e.g., A, B, C" required>
                        </div>
                        <div class="col-md-6">
                            <label for="section-shift" class="form-label">Shift *</label>
                            <select id="section-shift" name="shift" class="form-select" required>
                                <option value="">Select Shift</option>
                                <option value="Morning">Morning</option>
                                <option value="Evening">Evening</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="section-capacity" class="form-label">Capacity</label>
                            <input type="number" id="section-capacity" name="capacity" class="form-control" value="40" min="1" max="100">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bx bx-plus me-1"></i>Create Section
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
                <p id="delete-message">Are you sure you want to delete this item? This action cannot be undone.</p>
                <div class="alert alert-warning">
                    <i class="bx bx-warning me-2"></i>
                    This will also affect all related students and attendance records.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDelete">
                    <i class="bx bx-trash me-1"></i>Delete
                </button>
            </div>
        </div>
    </div>
</div>

<!-- View Section Modal -->
<div class="modal fade" id="viewSectionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Section Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewSectionModalBody">
                <!-- Content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="editSectionFromViewBtn" style="display: none;">
                    <i class="bx bx-edit me-1"></i>Edit Section
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
// Programs & Sections page specific JavaScript
let deleteItemId = null;
let deleteItemType = null;

document.addEventListener('DOMContentLoaded', function() {
    loadPrograms();
    loadSections();
    loadProgramOptions();
    
    // Setup form handlers
    setupFormHandlers();
    setupFilterHandlers();
    
    // Initialize modal form UX enhancements
    if (typeof UIHelpers !== 'undefined') {
        UIHelpers.initModalFormUX('#programModal', {
            autoFocus: true,
            optimizeTabOrder: true,
            enterKeySubmit: true
        });
        
        UIHelpers.initModalFormUX('#sectionModal', {
            autoFocus: true,
            optimizeTabOrder: true,
            enterKeySubmit: true
        });
    }
});

function setupFormHandlers() {
    // Program form submission
    document.getElementById('programForm').addEventListener('submit', function(e) {
        e.preventDefault();
        saveProgram();
    });
    
    // Section form submission
    document.getElementById('sectionForm').addEventListener('submit', function(e) {
        e.preventDefault();
        saveSection();
    });
    
    // Delete confirmation
    document.getElementById('confirmDelete').addEventListener('click', function() {
        if (deleteItemId && deleteItemType) {
            if (deleteItemType === 'program') {
                deleteProgram(deleteItemId);
            } else if (deleteItemType === 'section') {
                deleteSection(deleteItemId);
            }
        }
    });
}

function setupFilterHandlers() {
    // Section filters
    document.getElementById('section-program-filter').addEventListener('change', loadSections);
    document.getElementById('section-year-filter').addEventListener('change', loadSections);
    document.getElementById('section-shift-filter').addEventListener('change', loadSections);
    
    // Program filter handlers
    document.getElementById('program-status-filter').addEventListener('change', loadPrograms);
    document.getElementById('program-search-filter').addEventListener('input', debounce(loadPrograms, 500));
    document.getElementById('program-student-count-filter').addEventListener('change', loadPrograms);
    document.getElementById('program-section-count-filter').addEventListener('change', loadPrograms);
    
    // Section filter handlers
    document.getElementById('section-search-filter').addEventListener('input', debounce(loadSections, 500));
    document.getElementById('section-capacity-filter').addEventListener('change', loadSections);
    document.getElementById('section-utilization-filter').addEventListener('change', loadSections);
    document.getElementById('section-status-filter').addEventListener('change', loadSections);
}

function loadPrograms() {
    const params = new URLSearchParams({
        action: 'list',
        status: document.getElementById('program-status-filter').value,
        search: document.getElementById('program-search-filter').value,
        student_count: document.getElementById('program-student-count-filter').value,
        section_count: document.getElementById('program-section-count-filter').value
    });
    
    fetch(`api/programs.php?${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateProgramsTable(data.data);
            } else {
                showAlert('Error loading programs: ' + data.error, 'danger');
            }
        })
        .catch(error => {
            console.error('Error loading programs:', error);
            showAlert('Error loading programs', 'danger');
        });
}

function loadSections() {
    const params = new URLSearchParams({
        action: 'sections',
        program_id: document.getElementById('section-program-filter').value,
        year: document.getElementById('section-year-filter').value,
        shift: document.getElementById('section-shift-filter').value,
        search: document.getElementById('section-search-filter').value,
        capacity: document.getElementById('section-capacity-filter').value,
        utilization: document.getElementById('section-utilization-filter').value,
        status: document.getElementById('section-status-filter').value
    });
    
    fetch(`api/programs.php?${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateSectionsTable(data.data);
            } else {
                showAlert('Error loading sections: ' + data.error, 'danger');
            }
        })
        .catch(error => {
            console.error('Error loading sections:', error);
            showAlert('Error loading sections', 'danger');
        });
}

function loadProgramOptions() {
    fetch('api/programs.php?action=programs')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById('section-program');
                const filterSelect = document.getElementById('section-program-filter');
                
                select.innerHTML = '<option value="">Select Program</option>';
                filterSelect.innerHTML = '<option value="">All Programs</option>';
                
                data.data.forEach(program => {
                    const option = document.createElement('option');
                    option.value = program.id;
                    option.textContent = `${program.code} - ${program.name}`;
                    select.appendChild(option.cloneNode(true));
                    filterSelect.appendChild(option);
                });
            }
        })
        .catch(error => {
            console.error('Error loading program options:', error);
        });
}

function updateProgramsTable(programs) {
    const tbody = document.querySelector('#programs-table tbody');
    
    if (programs.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4">No programs found</td></tr>';
        return;
    }
    
    // HTML escape function to prevent XSS
    function escapeHtml(unsafe) {
        if (!unsafe) return '-';
        return String(unsafe)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
    
    tbody.innerHTML = programs.map(program => `
        <tr>
            <td>
                <div>
                    <strong>${escapeHtml(program.code)}</strong>
                    <br>
                    <small class="text-muted">
                        Morning: ${escapeHtml(program.code.startsWith('E') ? program.code.substring(1) : program.code)} | Evening: ${escapeHtml(program.code.startsWith('E') ? program.code : 'E' + program.code)}
                    </small>
                </div>
            </td>
            <td>${escapeHtml(program.name)}</td>
            <td><span class="badge bg-primary">${escapeHtml(program.total_students || 0)}</span></td>
            <td><span class="badge bg-info">${escapeHtml(program.section_count)}</span></td>
            <td>
                <span class="badge bg-${program.is_active ? 'success' : 'secondary'}">
                    ${program.is_active ? 'Active' : 'Inactive'}
                </span>
            </td>
            <td>
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        Actions
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" onclick="editProgram(${program.id})">
                            <i class="bx bx-edit me-2"></i>Edit
                        </a></li>
                        <li><a class="dropdown-item" href="#" onclick="viewProgram(${program.id})">
                            <i class="bx bx-show me-2"></i>View
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#" onclick="toggleProgramStatus(${program.id}, ${program.is_active ? 0 : 1})">
                            <i class="bx bx-${program.is_active ? 'x' : 'check'} me-2"></i>${program.is_active ? 'Deactivate' : 'Activate'}
                        </a></li>
                        <li><a class="dropdown-item text-danger" href="#" onclick="confirmDelete(${program.id}, 'program')">
                            <i class="bx bx-trash me-2"></i>Delete
                        </a></li>
                    </ul>
                </div>
            </td>
        </tr>
    `).join('');
}

function updateSectionsTable(sections) {
    const tbody = document.querySelector('#sections-table tbody');
    
    if (sections.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4">No sections found</td></tr>';
        return;
    }
    
    // HTML escape function to prevent XSS
    function escapeHtml(unsafe) {
        if (!unsafe) return '-';
        return String(unsafe)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
    
    tbody.innerHTML = sections.map(section => `
        <tr>
            <td><strong>Section ${escapeHtml(section.section_name)}</strong></td>
            <td><span class="badge bg-primary">${escapeHtml(section.program_name)}</span></td>
            <td>${escapeHtml(section.year_level)}</td>
            <td><span class="badge bg-${section.shift === 'Morning' ? 'success' : 'info'}">${escapeHtml(section.shift)}</span></td>
            <td>${escapeHtml(section.capacity)}</td>
            <td>
                <div class="progress" style="height: 20px;">
                    <div class="progress-bar" role="progressbar" style="width: ${escapeHtml(section.capacity_utilization)}%">
                        ${escapeHtml(section.capacity_utilization)}%
                    </div>
                </div>
                <small class="text-muted">${escapeHtml(section.student_count)}/${escapeHtml(section.capacity)} students</small>
            </td>
            <td>
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        Actions
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" onclick="editSection(${section.id})">
                            <i class="bx bx-edit me-2"></i>Edit
                        </a></li>
                        <li><a class="dropdown-item" href="#" onclick="viewSection(${section.id})">
                            <i class="bx bx-show me-2"></i>View
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#" onclick="toggleSectionStatus(${section.id}, ${section.is_active ? 0 : 1})">
                            <i class="bx bx-${section.is_active ? 'x' : 'check'} me-2"></i>${section.is_active ? 'Deactivate' : 'Activate'}
                        </a></li>
                        <li><a class="dropdown-item text-danger" href="#" onclick="confirmDelete(${section.id}, 'section')">
                            <i class="bx bx-trash me-2"></i>Delete
                        </a></li>
                    </ul>
                </div>
            </td>
        </tr>
    `).join('');
}

function openProgramModal(programId = null) {
    const modal = new bootstrap.Modal(document.getElementById('programModal'));
    const form = document.getElementById('programForm');
    const title = document.getElementById('programModalTitle');
    
    if (programId) {
        title.textContent = 'Edit Program';
        loadProgramData(programId);
    } else {
        title.textContent = 'Add Program';
        form.reset();
        document.getElementById('program-id').value = '';
    }
    
    modal.show();
}

function openSectionModal(sectionId = null) {
    const modal = new bootstrap.Modal(document.getElementById('sectionModal'));
    const form = document.getElementById('sectionForm');
    const title = document.getElementById('sectionModalTitle');
    
    if (sectionId) {
        title.textContent = 'Edit Section';
        loadSectionData(sectionId);
    } else {
        title.textContent = 'Add Section';
        form.reset();
        document.getElementById('section-id').value = '';
    }
    
    modal.show();
}

function loadProgramData(programId) {
    fetch(`api/programs.php?action=view&id=${programId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const program = data.data;
                document.getElementById('program-id').value = program.id;
                document.getElementById('program-code').value = program.code;
                document.getElementById('program-name').value = program.name;
                document.getElementById('program-description').value = program.description || '';
                document.getElementById('program-active').checked = program.is_active;
            } else {
                showAlert('Error loading program data', 'danger');
            }
        })
        .catch(error => {
            console.error('Error loading program:', error);
            showAlert('Error loading program data', 'danger');
        });
}

function loadSectionData(sectionId) {
    fetch(`api/programs.php?action=view-section&id=${sectionId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const section = data.data;
                document.getElementById('section-id').value = section.id;
                document.getElementById('section-program').value = section.program_id;
                document.getElementById('section-year').value = section.year_level;
                document.getElementById('section-name').value = section.section_name;
                document.getElementById('section-shift').value = section.shift;
                document.getElementById('section-capacity').value = section.capacity;
            } else {
                showAlert('Error loading section data', 'danger');
            }
        })
        .catch(error => {
            console.error('Error loading section:', error);
            showAlert('Error loading section data', 'danger');
        });
}

function saveProgram() {
    const form = document.getElementById('programForm');
    const formData = new FormData(form);
    const submitBtn = form.querySelector('button[type="submit"]');
    
    // Handle checkbox - ensure is_active is always sent
    const isActiveCheckbox = document.getElementById('program-active');
    if (isActiveCheckbox) {
        formData.set('is_active', isActiveCheckbox.checked ? '1' : '0');
    }
    
    const programId = document.getElementById('program-id').value;
    const url = programId ? `api/programs.php?action=update&id=${programId}` : 'api/programs.php?action=add';
    const method = 'POST';
    
    // Show loading state
    UIHelpers.showButtonLoading(submitBtn, programId ? 'Updating...' : 'Creating...');
    UIHelpers.disableForm(form);
    
    fetch(url, {
        method: method,
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        UIHelpers.hideButtonLoading(submitBtn);
        UIHelpers.enableForm(form);
        
        if (data.success) {
            UIHelpers.showSuccess(data.message || (programId ? 'Program updated successfully!' : 'Program created successfully!'));
            bootstrap.Modal.getInstance(document.getElementById('programModal')).hide();
            loadPrograms();
        } else {
            UIHelpers.showError(data.message || data.error || 'Error saving program');
        }
    })
    .catch(error => {
        console.error('Error saving program:', error);
        UIHelpers.hideButtonLoading(submitBtn);
        UIHelpers.enableForm(form);
        UIHelpers.showError('Error saving program');
    });
}

function saveSection() {
    const form = document.getElementById('sectionForm');
    const formData = new FormData(form);
    const submitBtn = form.querySelector('button[type="submit"]');
    
    const sectionId = document.getElementById('section-id').value;
    const url = sectionId ? `api/programs.php?action=update-section&id=${sectionId}` : 'api/programs.php?action=add-section';
    const method = 'POST';
    
    // Show loading state
    UIHelpers.showButtonLoading(submitBtn, sectionId ? 'Updating...' : 'Creating...');
    UIHelpers.disableForm(form);
    
    fetch(url, {
        method: method,
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        UIHelpers.hideButtonLoading(submitBtn);
        UIHelpers.enableForm(form);
        
        if (data.success) {
            UIHelpers.showSuccess(data.message || (sectionId ? 'Section updated successfully!' : 'Section created successfully!'));
            bootstrap.Modal.getInstance(document.getElementById('sectionModal')).hide();
            loadSections();
        } else {
            UIHelpers.showError(data.message || data.error || 'Error saving section');
        }
    })
    .catch(error => {
        console.error('Error saving section:', error);
        UIHelpers.hideButtonLoading(submitBtn);
        UIHelpers.enableForm(form);
        UIHelpers.showError('Error saving section');
    });
}

function editProgram(programId) {
    openProgramModal(programId);
}

function editSection(sectionId) {
    openSectionModal(sectionId);
}

function viewProgram(programId) {
    fetch(`api/programs.php?action=view&id=${programId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const program = data.data;
                const modalBody = document.getElementById('viewSectionModalBody'); // Reuse the same modal
                const editBtn = document.getElementById('editSectionFromViewBtn');
                
                modalBody.innerHTML = `
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Program Information</h6>
                            <p><strong>Code:</strong> <span class="badge bg-primary">${program.code}</span></p>
                            <p><strong>Name:</strong> ${program.name}</p>
                            <p><strong>Description:</strong> ${program.description || 'No description available'}</p>
                        </div>
                        <div class="col-md-6">
                            <h6>Statistics</h6>
                            <p><strong>Total Students:</strong> ${program.total_students || 0} students</p>
                            <p><strong>Total Sections:</strong> ${program.section_count || 0} sections</p>
                            <p><strong>Status:</strong> 
                                <span class="badge bg-${program.is_active ? 'success' : 'secondary'}">
                                    ${program.is_active ? 'Active' : 'Inactive'}
                                </span>
                            </p>
                        </div>
                    </div>
                `;
                
                editBtn.setAttribute('data-program-id', programId);
                editBtn.innerHTML = '<i class="bx bx-edit me-1"></i>Edit Program';
                editBtn.style.display = 'inline-block';
                
                // Add click handler for edit button
                editBtn.onclick = function() {
                    bootstrap.Modal.getInstance(document.getElementById('viewSectionModal')).hide();
                    editProgram(programId);
                };
                
                // Update modal title
                document.querySelector('#viewSectionModal .modal-title').textContent = 'Program Details';
                
                const modal = new bootstrap.Modal(document.getElementById('viewSectionModal'));
                modal.show();
            } else {
                showAlert('Error loading program details: ' + data.error, 'danger');
            }
        })
        .catch(error => {
            console.error('Error loading program:', error);
            showAlert('Error loading program details', 'danger');
        });
}

function viewSection(sectionId) {
    fetch(`api/programs.php?action=view-section&id=${sectionId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const section = data.data;
                const modalBody = document.getElementById('viewSectionModalBody');
                const editBtn = document.getElementById('editSectionFromViewBtn');
                
                modalBody.innerHTML = `
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Section Information</h6>
                            <p><strong>Section Name:</strong> ${section.section_name}</p>
                            <p><strong>Program:</strong> ${section.program_name}</p>
                            <p><strong>Year Level:</strong> <span class="badge bg-primary">${section.year_level}</span></p>
                            <p><strong>Shift:</strong> <span class="badge bg-${section.shift === 'Morning' ? 'success' : 'info'}">${section.shift}</span></p>
                        </div>
                        <div class="col-md-6">
                            <h6>Capacity & Enrollment</h6>
                            <p><strong>Capacity:</strong> ${section.capacity} students</p>
                            <p><strong>Current Students:</strong> ${section.student_count || 0} students</p>
                            <p><strong>Utilization:</strong> ${section.capacity_utilization || 0}%</p>
                            <div class="progress mt-2" style="height: 20px;">
                                <div class="progress-bar ${getCapacityColor(section.capacity_utilization || 0)}" 
                                     role="progressbar" 
                                     style="width: ${section.capacity_utilization || 0}%">
                                    ${section.capacity_utilization || 0}%
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12">
                            <h6>Status</h6>
                            <p>
                                <span class="badge bg-${section.is_active ? 'success' : 'secondary'}">
                                    ${section.is_active ? 'Active' : 'Inactive'}
                                </span>
                            </p>
                        </div>
                    </div>
                `;
                
                editBtn.setAttribute('data-section-id', sectionId);
                editBtn.innerHTML = '<i class="bx bx-edit me-1"></i>Edit Section';
                editBtn.style.display = 'inline-block';
                
                // Reset modal title
                document.querySelector('#viewSectionModal .modal-title').textContent = 'Section Details';
                
                // Add click handler for edit button
                editBtn.onclick = function() {
                    bootstrap.Modal.getInstance(document.getElementById('viewSectionModal')).hide();
                    editSection(sectionId);
                };
                
                const modal = new bootstrap.Modal(document.getElementById('viewSectionModal'));
                modal.show();
            } else {
                showAlert('Error loading section details: ' + data.error, 'danger');
            }
        })
        .catch(error => {
            console.error('Error loading section:', error);
            showAlert('Error loading section details', 'danger');
        });
}

function getCapacityColor(utilization) {
    if (utilization >= 90) return 'danger';
    if (utilization >= 70) return 'warning';
    return 'success';
}

function confirmDelete(itemId, itemType) {
    const message = itemType === 'program' 
        ? 'Are you sure you want to delete this program? This will also delete all associated sections and affect students.'
        : 'Are you sure you want to delete this section? This will affect all students in this section.';
    
    UIHelpers.showConfirmDialog({
        title: `Delete ${itemType === 'program' ? 'Program' : 'Section'}`,
        message: message,
        confirmText: 'Yes, Delete',
        cancelText: 'Cancel',
        confirmClass: 'btn-danger',
        onConfirm: () => {
            if (itemType === 'program') {
                deleteProgram(itemId);
            } else {
                deleteSection(itemId);
            }
        }
    });
}

function deleteProgram(programId) {
    const formData = new FormData();
    formData.append('csrf_token', '<?php echo generateCSRFToken(); ?>');
    formData.append('id', programId);
    
    UIHelpers.showLoadingOverlay('.card-body', 'Deleting program...');
    
    fetch(`api/programs.php?action=delete`, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        UIHelpers.hideLoadingOverlay('.card-body');
        
        if (data.success) {
            UIHelpers.showSuccess(data.message || 'Program deleted successfully!');
            loadPrograms();
            loadSections();
        } else {
            UIHelpers.showError(data.message || data.error || 'Error deleting program');
        }
    })
    .catch(error => {
        console.error('Error deleting program:', error);
        UIHelpers.hideLoadingOverlay('.card-body');
        UIHelpers.showError('Error deleting program');
    });
}

function deleteSection(sectionId) {
    const formData = new FormData();
    formData.append('csrf_token', '<?php echo generateCSRFToken(); ?>');
    formData.append('id', sectionId);
    
    UIHelpers.showLoadingOverlay('.card-body', 'Deleting section...');
    
    fetch(`api/programs.php?action=delete-section`, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        UIHelpers.hideLoadingOverlay('.card-body');
        
        if (data.success) {
            UIHelpers.showSuccess(data.message || 'Section deleted successfully!');
            loadSections();
        } else {
            UIHelpers.showError(data.message || data.error || 'Error deleting section');
        }
    })
    .catch(error => {
        console.error('Error deleting section:', error);
        UIHelpers.hideLoadingOverlay('.card-body');
        UIHelpers.showError('Error deleting section');
    });
}

function toggleProgramStatus(programId, newStatus) {
    const statusText = newStatus ? 'activate' : 'deactivate';
    
    UIHelpers.showConfirmDialog({
        title: `${statusText.charAt(0).toUpperCase() + statusText.slice(1)} Program`,
        message: `Are you sure you want to ${statusText} this program?`,
        confirmText: `Yes, ${statusText.charAt(0).toUpperCase() + statusText.slice(1)}`,
        confirmClass: newStatus ? 'btn-success' : 'btn-warning',
        onConfirm: () => {
            const formData = new FormData();
            formData.append('csrf_token', '<?php echo generateCSRFToken(); ?>');
            formData.append('is_active', newStatus);
            
            UIHelpers.showLoadingOverlay('.card-body', `${statusText.charAt(0).toUpperCase() + statusText.slice(1)}ing program...`);
            
            fetch(`api/programs.php?action=toggle-status&id=${programId}`, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                UIHelpers.hideLoadingOverlay('.card-body');
                
                if (data.success) {
                    UIHelpers.showSuccess(data.message || `Program ${statusText}d successfully`);
                    loadPrograms();
                } else {
                    UIHelpers.showError(data.message || data.error || `Error ${statusText}ing program`);
                }
            })
            .catch(error => {
                console.error('Error toggling program status:', error);
                UIHelpers.hideLoadingOverlay('.card-body');
                UIHelpers.showError(`Error ${statusText}ing program`);
            });
        }
    });
}

function toggleSectionStatus(sectionId, newStatus) {
    const statusText = newStatus ? 'activate' : 'deactivate';
    
    UIHelpers.showConfirmDialog({
        title: `${statusText.charAt(0).toUpperCase() + statusText.slice(1)} Section`,
        message: `Are you sure you want to ${statusText} this section?`,
        confirmText: `Yes, ${statusText.charAt(0).toUpperCase() + statusText.slice(1)}`,
        confirmClass: newStatus ? 'btn-success' : 'btn-warning',
        onConfirm: () => {
            const formData = new FormData();
            formData.append('csrf_token', '<?php echo generateCSRFToken(); ?>');
            formData.append('is_active', newStatus);
            
            UIHelpers.showLoadingOverlay('.card-body', `${statusText.charAt(0).toUpperCase() + statusText.slice(1)}ing section...`);
            
            fetch(`api/programs.php?action=toggle-section-status&id=${sectionId}`, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                UIHelpers.hideLoadingOverlay('.card-body');
                
                if (data.success) {
                    UIHelpers.showSuccess(data.message || `Section ${statusText}d successfully`);
                    loadSections();
                } else {
                    UIHelpers.showError(data.message || data.error || `Error ${statusText}ing section`);
                }
            })
            .catch(error => {
                console.error('Error toggling section status:', error);
                UIHelpers.hideLoadingOverlay('.card-body');
                UIHelpers.showError(`Error ${statusText}ing section`);
            });
        }
    });
}

function exportSections() {
    // Show export options modal
    showExportModal();
}

function exportPrograms() {
    // Show export options modal for programs
    showExportModal('programs');
}

function showExportModal() {
    // Create export modal if it doesn't exist
    let modal = document.getElementById('exportModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'exportModal';
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Export Sections</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Export Format</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="exportFormat" id="formatCsv" value="csv" checked>
                                <label class="form-check-label" for="formatCsv">
                                    <i class="bx bx-file me-2"></i>CSV (Comma Separated Values)
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="exportFormat" id="formatExcel" value="excel">
                                <label class="form-check-label" for="formatExcel">
                                    <i class="bx bx-table me-2"></i>Excel (.xlsx)
                                </label>
                            </div>
                        </div>
                        <div class="alert alert-info">
                            <i class="bx bx-info-circle me-2"></i>
                            <strong>Note:</strong> The export will include all sections and programs data.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-success" onclick="performExport()">
                            <i class="bx bx-download me-1"></i>Export
                        </button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }
    
    // Show the modal
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
}

function performExport() {
    const format = document.querySelector('input[name="exportFormat"]:checked').value;
    
    // Close the modal
    const modal = bootstrap.Modal.getInstance(document.getElementById('exportModal'));
    modal.hide();
    
    // Show loading message
    showAlert('Preparing export...', 'info');
    
    // Open export URL
    window.open(`api/export.php?action=programs&format=${format}`, '_blank');
}

// Filter Panel Toggle Functions
function toggleProgramFilterPanel() {
    const panel = document.getElementById('program-filter-panel');
    panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
}

function toggleSectionFilterPanel() {
    const panel = document.getElementById('section-filter-panel');
    panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
}

// Filter Application Functions
function applyProgramFilters() {
    loadPrograms();
}

function applySectionFilters() {
    loadSections();
}

// Filter Clear Functions
function clearProgramFilters() {
    document.getElementById('program-status-filter').value = '';
    document.getElementById('program-search-filter').value = '';
    document.getElementById('program-student-count-filter').value = '';
    document.getElementById('program-section-count-filter').value = '';
    loadPrograms();
}

function clearSectionFilters() {
    document.getElementById('section-program-filter').value = '';
    document.getElementById('section-year-filter').value = '';
    document.getElementById('section-shift-filter').value = '';
    document.getElementById('section-search-filter').value = '';
    document.getElementById('section-capacity-filter').value = '';
    document.getElementById('section-utilization-filter').value = '';
    document.getElementById('section-status-filter').value = '';
    loadSections();
}

// Debounce utility function for search inputs
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Alert system (if not already defined)
</script>

</body>
</html>
