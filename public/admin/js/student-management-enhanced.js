/**
 * Enhanced Student Management JavaScript
 * Includes semester/session filtering and management
 */

let students = [];
let sessions = [];
let currentFilters = {};
let currentPage = 1;
let totalPages = 1;

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    loadSessions();
    loadStudents();
    setupEventListeners();
});

function setupEventListeners() {
    // Filter change events
    document.getElementById('program-filter')?.addEventListener('change', applyFilters);
    document.getElementById('shift-filter')?.addEventListener('change', applyFilters);
    document.getElementById('year-filter')?.addEventListener('change', applyFilters);
    document.getElementById('semester-filter')?.addEventListener('change', applyFilters);
    document.getElementById('session-filter')?.addEventListener('change', applyFilters);
    document.getElementById('section-filter')?.addEventListener('change', applyFilters);
    document.getElementById('status-filter')?.addEventListener('change', applyFilters);
    document.getElementById('search-input')?.addEventListener('input', debounce(applyFilters, 300));
}

function loadSessions() {
    fetch('api/sessions.php?action=list')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                sessions = data.data;
                populateSessionFilter();
            } else {
                console.error('Failed to load sessions:', data.error);
            }
        })
        .catch(error => {
            console.error('Error loading sessions:', error);
        });
}

function populateSessionFilter() {
    const sessionFilter = document.getElementById('session-filter');
    if (!sessionFilter) return;
    
    sessionFilter.innerHTML = '<option value="">All Sessions</option>';
    sessions.forEach(session => {
        const option = document.createElement('option');
        option.value = session.id;
        option.textContent = `${session.label} (${session.code})`;
        sessionFilter.appendChild(option);
    });
}

function loadStudents(page = 1) {
    const params = new URLSearchParams({
        page: page,
        limit: 25,
        ...currentFilters
    });
    
    fetch(`api/students.php?action=list&${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                students = data.data;
                currentPage = data.pagination.current_page;
                totalPages = data.pagination.total_pages;
                renderStudentsTable();
                updatePagination();
            } else {
                showAlert('error', 'Failed to load students: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('error', 'Failed to load students');
        });
}

function renderStudentsTable() {
    const tbody = document.querySelector('#students-table tbody');
    if (!tbody) return;
    
    if (students.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="10" class="text-center py-4">
                    <i class="bx bx-user-x" style="font-size: 3rem; color: #ccc;"></i>
                    <h5 class="mt-3 text-muted">No Students Found</h5>
                    <p class="text-muted">No students match your current filters.</p>
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = students.map(student => `
        <tr>
            <td class="bulk-checkbox-column">
                <input type="checkbox" data-student-id="${student.id}" data-roll-number="${escapeHtml(student.roll_number)}" onchange="updateSelectedStudentsCount()">
            </td>
            <td><strong>${escapeHtml(student.roll_number)}</strong></td>
            <td>${escapeHtml(student.name)}</td>
            <td><span class="badge bg-primary">${escapeHtml(student.program)}</span></td>
            <td><span class="badge bg-${student.shift === 'Morning' ? 'success' : 'info'}">${escapeHtml(student.shift)}</span></td>
            <td><span class="badge bg-secondary">${escapeHtml(student.year_level)}</span></td>
            <td>
                ${student.current_semester ? 
                    `<span class="badge bg-warning">Semester ${student.current_semester}</span>` : 
                    '<span class="text-muted">Not Set</span>'
                }
            </td>
            <td>
                ${student.session_label ? 
                    `<span class="badge bg-info">${escapeHtml(student.session_label)}</span>` : 
                    '<span class="text-muted">Unassigned</span>'
                }
            </td>
            <td>Section ${escapeHtml(student.section)}</td>
            <td>
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bx bx-dots-vertical-rounded"></i>
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" onclick="editStudent(${student.id})">
                            <i class="bx bx-edit me-2"></i>Edit
                        </a></li>
                        <li><a class="dropdown-item" href="#" onclick="assignSession(${student.id})">
                            <i class="bx bx-calendar me-2"></i>Assign Session
                        </a></li>
                        <li><a class="dropdown-item" href="#" onclick="updateSemester(${student.id})">
                            <i class="bx bx-graduation me-2"></i>Update Semester
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="#" onclick="deleteStudent(${student.id})">
                            <i class="bx bx-trash me-2"></i>Delete
                        </a></li>
                    </ul>
                </div>
            </td>
        </tr>
    `).join('');
}

function applyFilters() {
    currentFilters = {
        program: document.getElementById('program-filter')?.value || '',
        shift: document.getElementById('shift-filter')?.value || '',
        year_level: document.getElementById('year-filter')?.value || '',
        current_semester: document.getElementById('semester-filter')?.value || '',
        session: document.getElementById('session-filter')?.value || '',
        section: document.getElementById('section-filter')?.value || '',
        status: document.getElementById('status-filter')?.value || '',
        search: document.getElementById('search-input')?.value || ''
    };
    
    // Remove empty filters
    Object.keys(currentFilters).forEach(key => {
        if (!currentFilters[key]) {
            delete currentFilters[key];
        }
    });
    
    currentPage = 1;
    loadStudents(currentPage);
}

function clearFilters() {
    document.getElementById('program-filter').value = '';
    document.getElementById('shift-filter').value = '';
    document.getElementById('year-filter').value = '';
    document.getElementById('semester-filter').value = '';
    document.getElementById('session-filter').value = '';
    document.getElementById('section-filter').value = '';
    document.getElementById('status-filter').value = '';
    document.getElementById('search-input').value = '';
    
    currentFilters = {};
    currentPage = 1;
    loadStudents(currentPage);
}

function updatePagination() {
    const paginationDiv = document.getElementById('pagination');
    if (!paginationDiv) return;
    
    if (totalPages <= 1) {
        paginationDiv.innerHTML = '';
        return;
    }
    
    let paginationHTML = '<nav><ul class="pagination justify-content-center">';
    
    // Previous button
    if (currentPage > 1) {
        paginationHTML += `<li class="page-item"><a class="page-link" href="#" onclick="loadStudents(${currentPage - 1})">Previous</a></li>`;
    }
    
    // Page numbers
    const startPage = Math.max(1, currentPage - 2);
    const endPage = Math.min(totalPages, currentPage + 2);
    
    for (let i = startPage; i <= endPage; i++) {
        const activeClass = i === currentPage ? 'active' : '';
        paginationHTML += `<li class="page-item ${activeClass}"><a class="page-link" href="#" onclick="loadStudents(${i})">${i}</a></li>`;
    }
    
    // Next button
    if (currentPage < totalPages) {
        paginationHTML += `<li class="page-item"><a class="page-link" href="#" onclick="loadStudents(${currentPage + 1})">Next</a></li>`;
    }
    
    paginationHTML += '</ul></nav>';
    paginationDiv.innerHTML = paginationHTML;
}

function assignSession(studentId) {
    const student = students.find(s => s.id === studentId);
    if (!student) return;
    
    const modal = new bootstrap.Modal(document.getElementById('sessionAssignmentModal'));
    
    // Populate session dropdown
    const sessionSelect = document.getElementById('assignment-session');
    sessionSelect.innerHTML = '<option value="">Select Session</option>';
    sessions.filter(s => s.is_active).forEach(session => {
        const option = document.createElement('option');
        option.value = session.id;
        option.textContent = `${session.label} (${session.code})`;
        if (student.enrollment_session_id == session.id) {
            option.selected = true;
        }
        sessionSelect.appendChild(option);
    });
    
    // Set current semester
    document.getElementById('assignment-semester').value = student.current_semester || '';
    document.getElementById('assignment-student-id').value = studentId;
    
    modal.show();
}

function updateSemester(studentId) {
    const student = students.find(s => s.id === studentId);
    if (!student) return;
    
    const newSemester = prompt(`Enter new semester for ${student.name} (1-8):`, student.current_semester || '');
    
    if (newSemester === null) return; // User cancelled
    
    const semester = parseInt(newSemester);
    if (isNaN(semester) || semester < 1 || semester > 8) {
        showAlert('error', 'Please enter a valid semester (1-8)');
        return;
    }
    
    updateStudentSemester(studentId, semester);
}

function updateStudentSemester(studentId, semester) {
    const formData = new FormData();
    formData.append('current_semester', semester);
    formData.append('csrf_token', document.getElementById('csrfToken')?.value || '');
    
    fetch(`api/students.php?action=update&id=${studentId}`, {
        method: 'PUT',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', 'Semester updated successfully');
            loadStudents(currentPage);
        } else {
            showAlert('error', data.error || 'Failed to update semester');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('error', 'Failed to update semester');
    });
}

function saveSessionAssignment() {
    const studentId = document.getElementById('assignment-student-id').value;
    const sessionId = document.getElementById('assignment-session').value;
    const semester = document.getElementById('assignment-semester').value;
    
    if (!studentId) {
        showAlert('error', 'Student ID is required');
        return;
    }
    
    const formData = new FormData();
    formData.append('enrollment_session_id', sessionId);
    formData.append('current_semester', semester);
    formData.append('csrf_token', document.getElementById('csrfToken')?.value || '');
    
    fetch(`api/students.php?action=update&id=${studentId}`, {
        method: 'PUT',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', 'Session assignment updated successfully');
            bootstrap.Modal.getInstance(document.getElementById('sessionAssignmentModal')).hide();
            loadStudents(currentPage);
        } else {
            showAlert('error', data.error || 'Failed to update session assignment');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('error', 'Failed to update session assignment');
    });
}

function bulkAssignSessions() {
    const selectedStudents = getSelectedStudents();
    if (selectedStudents.length === 0) {
        showAlert('warning', 'Please select students to assign sessions');
        return;
    }
    
    const modal = new bootstrap.Modal(document.getElementById('bulkSessionModal'));
    
    // Populate session dropdown
    const sessionSelect = document.getElementById('bulk-session-select');
    sessionSelect.innerHTML = '<option value="">Select Session</option>';
    sessions.filter(s => s.is_active).forEach(session => {
        const option = document.createElement('option');
        option.value = session.id;
        option.textContent = `${session.label} (${session.code})`;
        sessionSelect.appendChild(option);
    });
    
    document.getElementById('bulk-student-count').textContent = selectedStudents.length;
    modal.show();
}

function executeBulkSessionAssignment() {
    const selectedStudents = getSelectedStudents();
    const sessionId = document.getElementById('bulk-session-select').value;
    const semester = document.getElementById('bulk-semester-select').value;
    
    if (selectedStudents.length === 0) {
        showAlert('warning', 'Please select students');
        return;
    }
    
    if (!sessionId) {
        showAlert('warning', 'Please select a session');
        return;
    }
    
    if (confirm(`Are you sure you want to assign ${selectedStudents.length} students to the selected session?`)) {
        const promises = selectedStudents.map(studentId => {
            const formData = new FormData();
            formData.append('enrollment_session_id', sessionId);
            if (semester) formData.append('current_semester', semester);
            formData.append('csrf_token', document.getElementById('csrfToken')?.value || '');
            
            return fetch(`api/students.php?action=update&id=${studentId}`, {
                method: 'PUT',
                body: formData
            });
        });
        
        Promise.all(promises)
            .then(responses => Promise.all(responses.map(r => r.json())))
            .then(results => {
                const successCount = results.filter(r => r.success).length;
                showAlert('success', `Successfully assigned ${successCount} out of ${selectedStudents.length} students`);
                bootstrap.Modal.getInstance(document.getElementById('bulkSessionModal')).hide();
                loadStudents(currentPage);
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('error', 'Failed to execute bulk assignment');
            });
    }
}

function getSelectedStudents() {
    const checkboxes = document.querySelectorAll('input[data-student-id]:checked');
    return Array.from(checkboxes).map(cb => parseInt(cb.dataset.studentId));
}

function updateSelectedStudentsCount() {
    const selectedCount = getSelectedStudents().length;
    const countElement = document.getElementById('selected-students-count');
    if (countElement) {
        countElement.textContent = selectedCount;
    }
    
    // Show/hide bulk actions
    const bulkActions = document.getElementById('bulk-students-actions');
    if (bulkActions) {
        bulkActions.style.display = selectedCount > 0 ? 'block' : 'none';
    }
}

function toggleSelectAllStudents() {
    const selectAll = document.getElementById('select-all-students');
    const checkboxes = document.querySelectorAll('input[data-student-id]');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
    });
    
    updateSelectedStudentsCount();
}

function exportFilteredStudents() {
    const params = new URLSearchParams({
        action: 'export',
        ...currentFilters
    });
    
    window.open(`api/students.php?${params}`, '_blank');
}

// Utility functions
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

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

function showAlert(type, message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    const container = document.querySelector('.container-xxl');
    if (container) {
        container.insertBefore(alertDiv, container.firstChild);
        
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);
    }
}
