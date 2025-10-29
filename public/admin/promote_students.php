<?php
/**
 * Student Promotion Management Page
 */

require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';

// Require admin authentication
requireAdminAuth();

// Check if module is enabled
requireModuleEnabled('module_promote_students');

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
                        <li><strong>Semester-based Promotion:</strong> Students advance by semester (1→2→3...→8)</li>
                        <li><strong>Time-based:</strong> Uses 6-month cycles from admission year to determine correct semester</li>
                        <li><strong>Enrollment Session Unchanged:</strong> Each student's enrollment session (Fall 2025, Summer 2025, etc.) stays the same - it only tracks when they enrolled</li>
                        <li><strong>Fields Updated:</strong> `current_semester`, `year_level`, `current_year`, `last_year_update`</li>
                        <li><strong>Final Semester:</strong> Students in semester 8 will be marked as <strong>Graduated</strong></li>
                        <li><strong>Rollback Available:</strong> You can undo promotions done today if needed</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Filters Card -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bx bx-filter me-2"></i>Promotion Filters
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="filter-session" class="form-label">Enrollment Session</label>
                        <select id="filter-session" class="form-select">
                            <option value="">All Sessions</option>
                            <!-- Will be populated by JavaScript -->
                        </select>
                        <small class="text-muted">Promote only students from specific enrollment batch</small>
                    </div>
                    <div class="col-md-4">
                        <label for="filter-current-semester" class="form-label">Current Semester</label>
                        <select id="filter-current-semester" class="form-select">
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
                        <small class="text-muted">Promote only students in specific semester</small>
                    </div>
                    <div class="col-md-4">
                        <label for="filter-program" class="form-label">Program</label>
                        <select id="filter-program" class="form-select">
                            <option value="">All Programs</option>
                            <!-- Will be populated by JavaScript -->
                        </select>
                        <small class="text-muted">Promote only students in specific program</small>
                    </div>
                    <div class="col-12">
                        <button type="button" class="btn btn-sm btn-secondary" onclick="clearFilters()">
                            <i class="bx bx-x me-1"></i>Clear Filters
                        </button>
                        <small class="text-muted ms-3">Filters apply to both promotion methods</small>
                    </div>
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
                <h5 class=\"mb-0\">Promotion Actions</h5><small class=\"text-muted ms-2\">Console will show detailed logs</small>
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
                                <h5>Promote by Date (Smart Promotion)</h5>
                                <p class="text-muted mb-3">
                                    Updates each student to their <strong>correct semester</strong> based on time elapsed since admission (≈6 months per semester). Students who completed 8 semesters will be graduated. Updates `current_semester` and `year_level`.
                                </p>
                                <button type="button" class="btn btn-primary" id="promoteBtn">
                                    <i class="bx bx-time-five me-1"></i> Promote by Date
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Force Next Semester -->
                    <div class="col-md-6 mb-3">
                        <div class="card border border-success h-100">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <i class="bx bx-fast-forward text-success" style="font-size: 48px;"></i>
                                </div>
                                <h5>Force Next Semester (Manual Advance)</h5>
                                <p class="text-muted mb-3">
                                    Manually advance every active student by exactly <strong>one semester</strong> (1→2, 2→3, etc.). Ignores elapsed time - use this for mid-semester promotions. Students in semester 8 will be graduated.
                                </p>
                                <button type="button" class="btn btn-success" id="forceNextBtn">
                                    <i class="bx bx-fast-forward me-1"></i> Force Next Semester
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
                    <strong>Important Notes:</strong>
                    <ul class="mb-0 mt-2">
                        <li><strong>Enrollment Session Unchanged:</strong> Each student's `enrollment_session_id` (Fall 2025, Summer 2025, etc.) will NOT change - it represents their original enrollment batch</li>
                        <li><strong>Automatic Updates:</strong> System updates `current_semester`, `year_level`, `current_year`, and `last_year_update` fields</li>
                        <li><strong>Database Backup:</strong> Make sure to backup your database before proceeding</li>
                        <li><strong>Rollback Available:</strong> You can undo today's promotions using the Rollback button</li>
                    </ul>
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
/**
 * Load filter options (sessions and programs)
 */
async function loadFilterOptions() {
    try {
        // Load sessions
        const sessionRes = await fetch('api/sessions.php?action=list');
        const sessionData = await sessionRes.json();
        
        if (sessionData.success && sessionData.data) {
            const sessionSelect = document.getElementById('filter-session');
            sessionData.data.forEach(session => {
                const option = document.createElement('option');
                option.value = session.code;
                option.textContent = session.label;
                sessionSelect.appendChild(option);
            });
        }
        
        // Load programs
        const programRes = await fetch('api/programs.php?action=programs');
        const programData = await programRes.json();
        
        if (programData.success && programData.data) {
            const programSelect = document.getElementById('filter-program');
            programData.data.forEach(program => {
                const option = document.createElement('option');
                option.value = program.code;
                option.textContent = `${program.code} - ${program.name}`;
                programSelect.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error loading filter options:', error);
    }
}

/**
 * Clear all filter selections
 */
function clearFilters() {
    document.getElementById('filter-session').value = '';
    document.getElementById('filter-current-semester').value = '';
    document.getElementById('filter-program').value = '';
    loadPromotionPreview();
}

document.addEventListener('DOMContentLoaded', function() {
    
    // Load filter options
    loadFilterOptions();
    
    // Load preview on page load
    loadPromotionPreview();
    
    // Refresh preview button
    document.getElementById('refreshPreview').addEventListener('click', function() {
        loadPromotionPreview();
    });
    
    // Auto-refresh preview when filters change
    document.getElementById('filter-session').addEventListener('change', loadPromotionPreview);
    document.getElementById('filter-current-semester').addEventListener('change', loadPromotionPreview);
    document.getElementById('filter-program').addEventListener('change', loadPromotionPreview);
    
    // Promote by date button
    document.getElementById('promoteBtn').addEventListener('click', function() {
        confirmPromotion('by_date');
    });

    // Force next button
    document.getElementById('forceNextBtn').addEventListener('click', function() {
        confirmPromotion('force_next');
    });
    
    // Rollback button
    document.getElementById('rollbackBtn').addEventListener('click', function() {
        confirmRollback();
    });
    
    /**
     * Load promotion preview
     */
    function loadPromotionPreview() {
        console.group('Promotion Preview');
        console.time('preview_fetch');
        const preview = document.getElementById('promotionPreview');
        preview.innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Loading preview...</p>
            </div>
        `;
        
        // Build URL with filter parameters
        const params = new URLSearchParams({ action: 'preview' });
        const session = document.getElementById('filter-session').value;
        const semester = document.getElementById('filter-current-semester').value;
        const program = document.getElementById('filter-program').value;
        
        if (session) params.append('session', session);
        if (semester) params.append('current_semester', semester);
        if (program) params.append('program', program);
        
        console.log('Preview filters:', { session, semester, program });
        
        fetch('api/promote_students.php?' + params.toString())
            .then(async response => {
                const text = await response.text();
                console.debug('Preview raw:', text);
                try { return JSON.parse(text); } catch (e) {
                    throw new Error('Invalid JSON from preview: ' + e.message);
                }
            })
            .then(data => {
                console.timeEnd('preview_fetch');
                console.log('Preview payload:', data);
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
                console.error('Preview error:', error);
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
        const isSemester = (data.mode === 'semester');
        
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
                            ${isSemester ? '' : '<th>Current Year</th>'}
                            <th>${isSemester ? 'Semester' : 'Year Level'}</th>
                            <th>Student Count</th>
                            <th>After Promotion</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        if (data.summary && data.summary.length > 0) {
            data.summary.forEach(row => {
                const after = row.after_label || '';
                const willGraduate = !!row.will_graduate;
                const yearCell = isSemester ? '' : `<td><strong>Year ${row.current_year}</strong></td>`;
                
                html += `
                    <tr>
                        ${yearCell}
                        <td><span class="badge bg-label-primary">${row.year_level}</span></td>
                        <td><strong>${row.student_count}</strong> students</td>
                        <td>
                            ${willGraduate 
                                ? '<span class="badge bg-warning"><i class="bx bx-trophy me-1"></i>Will Graduate</span>' 
                                : '<span class="badge bg-success"><i class="bx bx-trending-up me-1"></i>' + after + '</span>'}
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
                ${(() => { if (isSemester) { const spY = Number(data.semesters_per_year || 2); const total = maxYears * spY; return `<strong>Academic Structure:</strong> Semester mode — ${total} semester(s) total (${spY} per year).`; } else { return `<strong>Academic Structure:</strong> Year mode — Program length: ${maxYears} year(s).`; } })()}
                You can change this in <a href="settings.php">Settings</a>.
            </div>
        `;
        
        preview.innerHTML = html;
    }
    
    /**
     * Confirm promotion
     */
    function confirmPromotion(mode) {
        const isDate = mode === 'by_date';
        
        // Get current filters
        const session = document.getElementById('filter-session').value;
        const semester = document.getElementById('filter-current-semester').value;
        const program = document.getElementById('filter-program').value;
        
        let filterNote = '';
        const filters = [];
        if (session) filters.push(`Enrollment: ${session}`);
        if (semester) filters.push(`Semester: ${semester}`);
        if (program) filters.push(`Program: ${program}`);
        
        if (filters.length > 0) {
            filterNote = `<p class="text-info"><strong>Filters applied:</strong> ${filters.join(', ')}</p>`;
        } else {
            filterNote = '<p class="text-danger"><strong>No filters - affects ALL active students!</strong></p>';
        }
        
        Swal.fire({
            title: isDate ? 'Promote by Date?' : 'Force Next Semester?',
            html: `
                <p>This will:</p>
                <ul class="text-start">
                    <li>${isDate ? 'Set students to the correct semester by date' : 'Advance all students by one semester now'}</li>
                    <li>Graduate final-semester students</li>
                    <li>Update student records based on filters</li>
                </ul>
                ${filterNote}
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#696cff',
            cancelButtonColor: '#8592a3',
            confirmButtonText: isDate ? 'Yes, Promote by Date' : 'Yes, Force Next',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                promoteAllStudents(mode, {session, semester, program});
            }
        });
    }
    
    /**
     * Execute promotion
     */
    function promoteAllStudents(mode, filters = {}) {
        console.group('Promote All Students');
        console.time('promote_request');
        
        console.log('Filters:', filters);
        
        Swal.fire({
            title: 'Processing...',
            html: 'Promoting students, please wait...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        // Choose action based on mode
        const action = (mode === 'by_date') ? 'promote_by_date' : (mode === 'force_next' ? 'promote' : 'promote_by_elapsed');
        const body = new URLSearchParams({ action });
        
        // Add filters to request
        if (filters.session) body.append('session', filters.session);
        if (filters.semester) body.append('current_semester', filters.semester);
        if (filters.program) body.append('program', filters.program);
        
        console.log('Request body:', body.toString());
        
        fetch('api/promote_students.php', {
            method: 'POST',
            headers: {
'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            },
            body: body.toString()
        })
.then(async response => { const text = await response.text(); console.debug('Promote raw:', text); try { return JSON.parse(text); } catch(e){ throw new Error('Invalid JSON promote: ' + e.message); } })
        .then(data => {
            console.timeEnd('promote_request');
            console.log('Promote result:', data);
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
                const errCount = data.errors ?? 0;
                const extra = Array.isArray(data.details) && data.details.length > 0
                  ? `\nErrors: ${errCount}. See console for details.`
                  : '';
                console.error('Promotion details:', data);
                Swal.fire({
                    icon: 'warning',
                    title: 'No Changes Applied',
                    text: (data.message || 'No students were updated.') + extra,
                    confirmButtonColor: '#696cff'
                });
            }
        })
        .catch(error => {
            console.error('Promote request error:', error);
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
        console.group('Rollback Promotions');
        console.time('rollback_request');
        Swal.fire({
            title: 'Processing...',
            html: 'Rolling back promotions, please wait...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        const body = new URLSearchParams({ action: 'rollback' });
        fetch('api/promote_students.php', {
            method: 'POST',
            headers: {
'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            },
            body: body.toString()
        })
.then(async response => { const text = await response.text(); console.debug('Rollback raw:', text); try { return JSON.parse(text); } catch(e){ throw new Error('Invalid JSON rollback: ' + e.message); } })
        .then(data => {
            console.timeEnd('rollback_request');
            console.log('Rollback result:', data);
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
            console.error('Rollback request error:', error);
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
