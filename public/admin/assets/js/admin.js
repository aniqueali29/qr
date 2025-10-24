/**
 * Advanced Admin Portal JavaScript
 * Handles filtering, charts, bulk import, validation, and responsive interactions
 * Version: 2.0 - Fixed API endpoints and error handling
 */

// Global variables
let currentTab = 'dashboard';
let students = [];
let programs = [];
let sections = [];
let attendance = [];
let analytics = {};
let currentPage = 1;
let totalPages = 1;
let itemsPerPage = 25;

// Chart instances
let charts = {};

// Initialize the application
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
});

// Global error handler for fetch operations
window.addEventListener('unhandledrejection', function(e) {
    // Check if it's a fetch error
    if (e.reason && e.reason.message && e.reason.message.includes('Failed to fetch')) {
        console.warn('Network error detected - this might be due to server connectivity issues');
        e.preventDefault();
        return;
    }
    
    // Check if it's a browser extension error (ignore these)
    if (e.reason && e.reason.stack && e.reason.stack.includes('chrome-extension://')) {
        console.warn('Browser extension error - ignoring');
        e.preventDefault();
        return;
    }
});

/**
 * Initialize the application
 */
function initializeApp() {
    // Set up event listeners
    setupEventListeners();
    
    // Setup roll number auto-fill
    setupRollNumberAutoFill();
    
    // Load initial data
    loadDashboard();
    
    // Initialize tooltips and other UI enhancements
    initializeUI();
}

/**
 * Set up event listeners
 */
function setupEventListeners() {
    // Tab switching
    document.querySelectorAll('.nav-tab').forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            const tabName = this.getAttribute('onclick')?.match(/switchTab\('([^']+)'\)/)?.[1];
            if (tabName) {
                switchTab(tabName);
            }
        });
    });
    
    // Modal close events
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal')) {
            closeAllModals();
        }
        if (e.target.classList.contains('close')) {
            closeAllModals();
        }
    });
    
    // Form submissions
    document.addEventListener('submit', function(e) {
        if (e.target.id === 'student-form') {
            e.preventDefault();
            handleStudentFormSubmit(e.target);
        }
        if (e.target.id === 'program-form') {
            e.preventDefault();
            handleProgramFormSubmit(e.target);
        }
        if (e.target.id === 'section-form') {
            e.preventDefault();
            handleSectionFormSubmit(e.target);
        }
    });
    
    // File upload
    const fileInput = document.getElementById('file-input');
    if (fileInput) {
        fileInput.addEventListener('change', handleFileUpload);
    }
    
    // Drag and drop
    const fileUpload = document.querySelector('.file-upload');
    if (fileUpload) {
        setupDragAndDrop(fileUpload);
    }
    
    // Search and filters
    const searchInput = document.getElementById('search-input');
    if (searchInput) {
        searchInput.addEventListener('input', debounce(handleSearch, 300));
    }
    
    // Filter toggles
    const filterToggle = document.getElementById('filter-toggle');
    if (filterToggle) {
        filterToggle.addEventListener('click', toggleFilterPanel);
    }
    
    // Pagination
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('page-btn')) {
            const page = parseInt(e.target.dataset.page);
            if (page && page !== currentPage) {
                changePage(page);
            }
        }
    });
}

/**
 * Initialize UI enhancements
 */
function initializeUI() {
    // Initialize tooltips (if using a tooltip library)
    // Initialize date pickers
    initializeDatePickers();
    
    // Initialize range sliders
    initializeRangeSliders();
    
    // Initialize dropdowns
    initializeDropdowns();
}

/**
 * Tab switching functionality
 */
function switchTab(tabName) {
    // Update tab appearance
    document.querySelectorAll('.nav-tab').forEach(tab => tab.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    
    // Activate current tab
    const activeTab = document.querySelector(`[onclick="switchTab('${tabName}')"]`);
    const activeContent = document.getElementById(tabName);
    
    if (activeTab) activeTab.classList.add('active');
    if (activeContent) activeContent.classList.add('active');
    
    currentTab = tabName;
    
    // Load tab-specific data with a small delay to ensure DOM is ready
    setTimeout(() => {
        switch(tabName) {
            case 'dashboard':
                loadDashboard();
                break;
            case 'students':
                loadStudents();
                break;
            case 'programs':
                loadPrograms();
                loadSections();
                break;
            case 'attendance':
                loadAttendance();
                break;
            case 'import':
                loadImportTab();
                break;
            case 'settings':
                loadSettings();
                break;
        }
    }, 100);
}

/**
 * Load dashboard with analytics
 */
async function loadDashboard() {
    try {
        showLoading('dashboard-content');
        
        const response = await fetch('api/admin_api.php?action=get_analytics_data');
        const data = await response.json();
        
        if (data.success) {
            analytics = data.data;
            updateDashboard(analytics);
            initializeCharts();
        } else {
            showAlert('Error loading dashboard: ' + data.error, 'error');
        }
        
        // Load year progression status
        try {
            await loadYearProgressionStatus();
        } catch (error) {
            console.error('Year progression status failed to load:', error);
            // Don't let this error stop the dashboard from loading
        }
        
    } catch (error) {
        showAlert('Error loading dashboard: ' + error.message, 'error');
    }
}

/**
 * Update dashboard with data
 */
function updateDashboard(data) {
    // Update stats cards
    updateStatsCards(data);
    
    // Update charts
    updateCharts(data);
}

/**
 * Update charts with new data
 */
function updateCharts(data) {
    // Update attendance trends chart
    if (charts.attendanceTrends && data.attendance_trends) {
        const trendData = data.attendance_trends.map(item => ({
            x: item.date,
            y: parseFloat(item.attendance_rate)
        }));
        
        charts.attendanceTrends.data.datasets[0].data = trendData;
        charts.attendanceTrends.update();
    }
    
    // Update program distribution chart
    if (charts.programDistribution && data.students_by_program) {
        const programData = data.students_by_program.map(item => ({
            label: item.program,
            value: parseInt(item.count)
        }));
        
        charts.programDistribution.data.labels = programData.map(item => item.label);
        charts.programDistribution.data.datasets[0].data = programData.map(item => item.value);
        charts.programDistribution.update();
    }
    
    // Update shift comparison chart
    if (charts.shiftComparison && data.students_by_shift) {
        const shiftData = data.students_by_shift.map(item => ({
            label: item.shift,
            value: parseInt(item.count)
        }));
        
        charts.shiftComparison.data.labels = shiftData.map(item => item.label);
        charts.shiftComparison.data.datasets[0].data = shiftData.map(item => item.value);
        charts.shiftComparison.update();
    }
    
    // Update year enrollment chart
    if (charts.yearEnrollment && data.students_by_year) {
        const yearData = data.students_by_year.map(item => ({
            label: item.year_level,
            value: parseInt(item.count)
        }));
        
        charts.yearEnrollment.data.labels = yearData.map(item => item.label);
        charts.yearEnrollment.data.datasets[0].data = yearData.map(item => item.value);
        charts.yearEnrollment.update();
    }
}

/**
 * Update stats cards
 */
function updateStatsCards(data) {
    const statsContainer = document.getElementById('stats-container');
    if (!statsContainer) return;
    
    const totalStudents = data.students_by_program?.reduce((sum, item) => sum + parseInt(item.count), 0) || 0;
    const totalPrograms = data.students_by_program?.length || 0;
    const totalSections = data.section_utilization?.length || 0;
    const avgAttendance = data.program_attendance?.reduce((sum, item) => sum + parseFloat(item.attendance_rate), 0) / (data.program_attendance?.length || 1) || 0;
    
    statsContainer.innerHTML = `
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-value">${totalStudents}</div>
                <div class="stat-label">Total Students</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div class="stat-value">${totalPrograms}</div>
                <div class="stat-label">Active Programs</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chalkboard"></i>
                </div>
                <div class="stat-value">${totalSections}</div>
                <div class="stat-label">Total Sections</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-value">${Math.round(avgAttendance)}%</div>
                <div class="stat-label">Avg Attendance</div>
            </div>
        </div>
    `;
}

/**
 * Initialize charts
 */
function initializeCharts() {
    // Attendance trends chart
    initAttendanceTrendsChart();
    
    // Program distribution chart
    initProgramDistributionChart();
    
    // Shift comparison chart
    initShiftComparisonChart();
    
    // Year-wise enrollment chart
    initYearEnrollmentChart();
}

/**
 * Initialize attendance trends chart
 */
function initAttendanceTrendsChart() {
    const ctx = document.getElementById('attendance-trends-chart');
    if (!ctx || !analytics.attendance_trends) return;
    
    const data = analytics.attendance_trends.map(item => ({
        x: item.date,
        y: parseFloat(item.attendance_rate)
    }));
    
    charts.attendanceTrends = new Chart(ctx, {
        type: 'line',
        data: {
            datasets: [{
                label: 'Attendance Rate (%)',
                data: data,
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
}

/**
 * Initialize program distribution chart
 */
function initProgramDistributionChart() {
    const ctx = document.getElementById('program-distribution-chart');
    if (!ctx || !analytics.students_by_program) return;
    
    const data = analytics.students_by_program.map(item => ({
        label: item.program,
        value: parseInt(item.count)
    }));
    
    charts.programDistribution = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: data.map(item => item.label),
            datasets: [{
                data: data.map(item => item.value),
                backgroundColor: [
                    '#3b82f6',
                    '#10b981',
                    '#f59e0b',
                    '#ef4444',
                    '#8b5cf6'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}

/**
 * Initialize shift comparison chart
 */
function initShiftComparisonChart() {
    const ctx = document.getElementById('shift-comparison-chart');
    if (!ctx || !analytics.students_by_shift) return;
    
    const data = analytics.students_by_shift.map(item => ({
        label: item.shift,
        value: parseInt(item.count)
    }));
    
    charts.shiftComparison = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.map(item => item.label),
            datasets: [{
                label: 'Students',
                data: data.map(item => item.value),
                backgroundColor: [
                    '#fb923c',
                    '#3b82f6'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

/**
 * Initialize year enrollment chart
 */
function initYearEnrollmentChart() {
    const ctx = document.getElementById('year-enrollment-chart');
    if (!ctx || !analytics.students_by_year) return;
    
    const data = analytics.students_by_year.map(item => ({
        label: item.year_level,
        value: parseInt(item.count)
    }));
    
    charts.yearEnrollment = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.map(item => item.label),
            datasets: [{
                label: 'Students',
                data: data.map(item => item.value),
                backgroundColor: '#10b981'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

/**
 * Load students with filtering
 */
async function loadStudents(page = 1) {
    try {
        showLoading('students-table');
        
        const filters = getActiveFilters();
        const params = new URLSearchParams({
            page: page,
            limit: itemsPerPage,
            ...filters
        });
        
        console.log('Loading students with params:', params.toString());
        
        // Try the basic students endpoint first if filtered fails
        let response;
        try {
            response = await fetch(`api/admin_api.php?action=get_filtered_students&${params}`);
        } catch (filterError) {
            console.log('Filtered students failed, trying basic students endpoint');
            response = await fetch(`api/admin_api.php?action=students`);
        }
        
        console.log('Students API response:', response);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        console.log('Students data:', data);
        
        if (data.success) {
            students = data.data;
            currentPage = data.pagination.current_page;
            totalPages = data.pagination.total_pages;
            updateStudentsTable();
            updatePagination();
        } else {
            console.error('Students API error:', data.error);
            showAlert('Error loading students: ' + data.error, 'error');
            // Show empty table instead of loading state
            updateStudentsTable();
        }
    } catch (error) {
        console.error('Students loading error:', error);
        showAlert('Error loading students: ' + error.message, 'error');
        // Show empty table with fallback message
        const tbody = document.querySelector('#students-table tbody');
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="10" class="text-center">Unable to load students. Please check the database connection.</td></tr>';
        }
    }
}

/**
 * Update students table
 */
function updateStudentsTable() {
    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', updateStudentsTable);
        return;
    }
    
    const tbody = document.querySelector('#students-table tbody');
    if (!tbody) {
        console.error('Students table tbody not found');
        console.log('Students table element:', document.querySelector('#students-table'));
        console.log('Students table children:', document.querySelector('#students-table')?.children);
        return;
    }
    
    if (!students || students.length === 0) {
        tbody.innerHTML = '<tr><td colspan="10" class="text-center">No students found</td></tr>';
        return;
    }
    
    tbody.innerHTML = students.map(student => `
        <tr>
            <td>
                <span class="badge primary">${student.student_id}</span>
            </td>
            <td>${student.name}</td>
            <td>
                <span class="badge ${student.program === 'SWT' ? 'success' : 'info'}">${student.program}</span>
            </td>
            <td>
                <span class="badge ${student.shift === 'Morning' ? 'morning' : 'evening'}">${student.shift}</span>
            </td>
            <td>
                <span class="badge primary">${student.year_level}</span>
            </td>
            <td>${student.section}</td>
            <td>
                <div class="progress">
                    <div class="progress-bar ${getAttendanceColor(student.attendance_percentage)}" 
                         style="width: ${student.attendance_percentage}%"></div>
                </div>
                <small>${student.attendance_percentage}%</small>
            </td>
            <td>${student.email}</td>
            <td>${student.phone || '-'}</td>
            <td>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm warning" onclick="editStudent('${student.student_id}')" title="Edit">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm danger" onclick="deleteStudent('${student.student_id}')" title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                    <button class="btn btn-sm info" onclick="viewStudentQR('${student.student_id}')" title="View QR">
                        <i class="fas fa-qrcode"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

/**
 * Get attendance color based on percentage
 */
function getAttendanceColor(percentage) {
    if (percentage >= 80) return '';
    if (percentage >= 60) return 'warning';
    return 'danger';
}

/**
 * Load programs
 */
async function loadPrograms() {
    try {
        showLoading('programs-table');
        
        console.log('Loading programs...');
        const response = await fetch('api/programs.php?action=list');
        console.log('Programs API response:', response);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        console.log('Programs data:', data);
        
        if (data.success) {
            programs = data.data;
            updateProgramsTable();
        } else {
            console.error('Programs API error:', data.error);
            showAlert('Error loading programs: ' + data.error, 'error');
            // Show empty table instead of loading state
            updateProgramsTable();
        }
    } catch (error) {
        console.error('Programs loading error:', error);
        showAlert('Error loading programs: ' + error.message, 'error');
        // Show empty table instead of loading state
        updateProgramsTable();
    }
}

/**
 * Update programs table
 */
function updateProgramsTable() {
    const tbody = document.querySelector('#programs-table tbody');
    if (!tbody) {
        console.error('Programs table tbody not found');
        return;
    }
    
    if (!programs || programs.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center">No programs found</td></tr>';
        return;
    }
    
    tbody.innerHTML = programs.map(program => `
        <tr>
            <td><span class="badge primary">${program.code}</span></td>
            <td>${program.name}</td>
            <td>${program.total_students || 0}</td>
            <td>${program.section_count || 0}</td>
            <td>
                <span class="badge ${program.is_active ? 'success' : 'danger'}">
                    ${program.is_active ? 'Active' : 'Inactive'}
                </span>
            </td>
            <td>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm warning" onclick="editProgram(${program.id})" title="Edit">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm ${program.is_active ? 'danger' : 'success'}" 
                            onclick="toggleProgramStatus(${program.id})" 
                            title="${program.is_active ? 'Deactivate' : 'Activate'}">
                        <i class="fas fa-${program.is_active ? 'times' : 'check'}"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

/**
 * Load sections
 */
async function loadSections() {
    try {
        showLoading('sections-table');
        
        const filters = getSectionFilters();
        const params = new URLSearchParams(filters);
        
        console.log('Loading sections with params:', params.toString());
        const response = await fetch(`api/programs.php?action=sections${params.toString() ? '&' + params.toString() : ''}`);
        console.log('Sections API response:', response);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const responseText = await response.text();
        console.log('Sections raw response:', responseText);
        
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            console.error('JSON parse error:', parseError);
            console.error('Response was not valid JSON:', responseText);
            throw new Error('Invalid JSON response from sections API');
        }
        
        console.log('Sections data:', data);
        
        if (data.success) {
            sections = data.data;
            updateSectionsTable();
        } else {
            console.error('Sections API error:', data.error);
            showAlert('Error loading sections: ' + data.error, 'error');
            // Show empty table instead of loading state
            updateSectionsTable();
        }
    } catch (error) {
        console.error('Sections loading error:', error);
        showAlert('Error loading sections: ' + error.message, 'error');
        // Show empty table with helpful message
        const tbody = document.querySelector('#sections-table tbody');
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center">Unable to load sections. Please check if the database migration was run.</td></tr>';
        }
    }
}

/**
 * Update sections table
 */
function updateSectionsTable() {
    const tbody = document.querySelector('#sections-table tbody');
    if (!tbody) {
        console.error('Sections table tbody not found');
        return;
    }
    
    if (!sections || sections.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center">No sections found</td></tr>';
        return;
    }
    
    tbody.innerHTML = sections.map(section => `
        <tr>
            <td>${section.section_name}</td>
            <td>${section.program_name}</td>
            <td><span class="badge primary">${section.year_level}</span></td>
            <td><span class="badge ${section.shift === 'Morning' ? 'morning' : 'evening'}">${section.shift}</span></td>
            <td>${section.current_students}/${section.capacity}</td>
            <td>
                <div class="progress">
                    <div class="progress-bar ${getCapacityColor(section.capacity_utilization)}" 
                         style="width: ${section.capacity_utilization}%"></div>
                </div>
                <small>${section.capacity_utilization}%</small>
            </td>
            <td>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm warning" onclick="editSection(${section.id})" title="Edit">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm danger" onclick="deleteSection(${section.id})" title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

/**
 * Get capacity color based on utilization
 */
function getCapacityColor(utilization) {
    if (utilization >= 90) return 'danger';
    if (utilization >= 75) return 'warning';
    return '';
}

/**
 * Handle file upload
 */
function handleFileUpload(event) {
    const file = event.target.files[0];
    if (!file) return;
    
    // Validate file type
    const allowedTypes = ['text/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
    if (!allowedTypes.includes(file.type)) {
        showAlert('Invalid file type. Please upload a CSV or Excel file.', 'error');
        return;
    }
    
    // Validate file size (5MB limit)
    if (file.size > 5 * 1024 * 1024) {
        showAlert('File too large. Maximum size is 5MB.', 'error');
        return;
    }
    
    // Show file info
    showFileInfo(file);
    
    // Validate file
    validateFile(file);
}

/**
 * Show file information
 */
function showFileInfo(file) {
    const fileInfo = document.getElementById('file-info');
    if (!fileInfo) return;
    
    fileInfo.innerHTML = `
        <div class="alert info">
            <i class="fas fa-file"></i>
            <strong>${file.name}</strong> (${formatFileSize(file.size)})
        </div>
    `;
}

/**
 * Validate uploaded file
 */
async function validateFile(file) {
    try {
        const formData = new FormData();
        formData.append('file', file);
        
        const response = await fetch('api/bulk_import_api.php?action=validate_file', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showValidationResults(data.data);
        } else {
            showAlert('File validation failed: ' + data.error, 'error');
        }
    } catch (error) {
        showAlert('Error validating file: ' + error.message, 'error');
    }
}

/**
 * Show validation results
 */
function showValidationResults(data) {
    const resultsContainer = document.getElementById('validation-results');
    if (!resultsContainer) return;
    
    resultsContainer.innerHTML = `
        <div class="alert ${data.invalid_rows > 0 ? 'warning' : 'success'}">
            <i class="fas fa-${data.invalid_rows > 0 ? 'exclamation-triangle' : 'check-circle'}"></i>
            <strong>Validation Results:</strong>
            ${data.total_rows} total rows, ${data.valid_rows} valid, ${data.invalid_rows} invalid
        </div>
        
        ${data.invalid_rows > 0 ? `
            <div class="alert error">
                <i class="fas fa-times-circle"></i>
                <strong>Errors found:</strong>
                <ul>
                    ${data.details?.map(error => `
                        <li>Row ${error.row}: ${error.student_id} - ${error.errors.join(', ')}</li>
                    `).join('') || ''}
                </ul>
            </div>
        ` : ''}
        
        <div class="d-flex gap-3 justify-center">
            <button class="btn primary" onclick="importStudents()" ${data.invalid_rows > 0 ? 'disabled' : ''}>
                <i class="fas fa-upload"></i>
                Import Students
            </button>
            <button class="btn" onclick="downloadTemplate()">
                <i class="fas fa-download"></i>
                Download Template
            </button>
        </div>
    `;
}

/**
 * Import students
 */
async function importStudents() {
    try {
        showLoading('import-results');
        
        const response = await fetch('api/bulk_import_api.php?action=import_students', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                data: window.validatedData || []
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showImportResults(data.data);
        } else {
            showAlert('Import failed: ' + data.error, 'error');
        }
    } catch (error) {
        showAlert('Error importing students: ' + error.message, 'error');
    }
}

/**
 * Show import results
 */
function showImportResults(results) {
    const resultsContainer = document.getElementById('import-results');
    if (!resultsContainer) return;
    
    resultsContainer.innerHTML = `
        <div class="alert ${results.errors.length > 0 ? 'warning' : 'success'}">
            <i class="fas fa-${results.errors.length > 0 ? 'exclamation-triangle' : 'check-circle'}"></i>
            <strong>Import Results:</strong>
            ${results.total} total, ${results.success.length} successful, ${results.errors.length} failed
        </div>
        
        ${results.errors.length > 0 ? `
            <div class="alert error">
                <i class="fas fa-times-circle"></i>
                <strong>Failed imports:</strong>
                <ul>
                    ${results.errors.map(error => `
                        <li>Row ${error.row}: ${error.student_id} - ${error.error}</li>
                    `).join('')}
                </ul>
            </div>
        ` : ''}
        
        ${results.success.length > 0 ? `
            <div class="alert success">
                <i class="fas fa-check-circle"></i>
                <strong>Successfully imported:</strong>
                <ul>
                    ${results.success.map(item => `
                        <li>${item.student_id} - ${item.name} (Password: ${item.password})</li>
                    `).join('')}
                </ul>
            </div>
        ` : ''}
    `;
}

/**
 * Download CSV template
 */
function downloadTemplate() {
    window.open('api/bulk_import_api.php?action=download_template', '_blank');
}

/**
 * Get active filters
 */
function getActiveFilters() {
    const filters = {};
    
    const programFilter = document.getElementById('program-filter');
    if (programFilter && programFilter.value) {
        filters.program = programFilter.value;
    }
    
    const shiftFilter = document.querySelector('input[name="shift-filter"]:checked');
    if (shiftFilter) {
        filters.shift = shiftFilter.value;
    }
    
    const yearFilter = document.querySelectorAll('input[name="year-filter"]:checked');
    if (yearFilter.length > 0) {
        filters.year_level = Array.from(yearFilter).map(cb => cb.value).join(',');
    }
    
    const sectionFilter = document.getElementById('section-filter');
    if (sectionFilter && sectionFilter.value) {
        filters.section = sectionFilter.value;
    }
    
    const attendanceMin = document.getElementById('attendance-min');
    if (attendanceMin && attendanceMin.value) {
        filters.attendance_min = attendanceMin.value;
    }
    
    const attendanceMax = document.getElementById('attendance-max');
    if (attendanceMax && attendanceMax.value) {
        filters.attendance_max = attendanceMax.value;
    }
    
    const searchInput = document.getElementById('search-input');
    if (searchInput && searchInput.value) {
        filters.search = searchInput.value;
    }
    
    return filters;
}

/**
 * Get section filters
 */
function getSectionFilters() {
    const filters = {};
    
    const programFilter = document.getElementById('section-program-filter');
    if (programFilter && programFilter.value) {
        filters.program_id = programFilter.value;
    }
    
    const yearFilter = document.getElementById('section-year-filter');
    if (yearFilter && yearFilter.value) {
        filters.year_level = yearFilter.value;
    }
    
    const shiftFilter = document.getElementById('section-shift-filter');
    if (shiftFilter && shiftFilter.value) {
        filters.shift = shiftFilter.value;
    }
    
    return filters;
}

/**
 * Handle search
 */
function handleSearch() {
    loadStudents(1);
}

/**
 * Toggle filter panel
 */
function toggleFilterPanel() {
    const panel = document.getElementById('filter-panel');
    if (!panel) return;
    
    panel.classList.toggle('collapsed');
}

/**
 * Change page
 */
function changePage(page) {
    if (page < 1 || page > totalPages) return;
    currentPage = page;
    loadStudents(page);
}

/**
 * Update pagination
 */
function updatePagination() {
    const pagination = document.getElementById('pagination');
    if (!pagination) return;
    
    const pages = [];
    
    // Previous button
    pages.push(`
        <button class="page-btn" data-page="${currentPage - 1}" ${currentPage <= 1 ? 'disabled' : ''}>
            <i class="fas fa-chevron-left"></i>
        </button>
    `);
    
    // Page numbers
    const startPage = Math.max(1, currentPage - 2);
    const endPage = Math.min(totalPages, currentPage + 2);
    
    if (startPage > 1) {
        pages.push(`<button class="page-btn" data-page="1">1</button>`);
        if (startPage > 2) {
            pages.push(`<span>...</span>`);
        }
    }
    
    for (let i = startPage; i <= endPage; i++) {
        pages.push(`
            <button class="page-btn ${i === currentPage ? 'active' : ''}" data-page="${i}">
                ${i}
            </button>
        `);
    }
    
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            pages.push(`<span>...</span>`);
        }
        pages.push(`<button class="page-btn" data-page="${totalPages}">${totalPages}</button>`);
    }
    
    // Next button
    pages.push(`
        <button class="page-btn" data-page="${currentPage + 1}" ${currentPage >= totalPages ? 'disabled' : ''}>
            <i class="fas fa-chevron-right"></i>
        </button>
    `);
    
    pagination.innerHTML = pages.join('');
}

/**
 * Show loading state
 */
function showLoading(containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    // For tables, only update the tbody, not the entire container
    if (containerId === 'students-table' || containerId === 'programs-table' || containerId === 'sections-table' || containerId === 'attendance-table') {
        const tbody = container.querySelector('tbody');
        if (tbody) {
            const colspan = containerId === 'students-table' ? 10 : 
                          containerId === 'programs-table' ? 6 : 
                          containerId === 'sections-table' ? 7 : 5;
            tbody.innerHTML = `
                <tr>
                    <td colspan="${colspan}" class="loading">
                        <div class="spinner"></div>
                        Loading...
                    </td>
                </tr>
            `;
        }
    } else {
        container.innerHTML = `
            <div class="loading">
                <div class="spinner"></div>
                Loading...
            </div>
        `;
    }
}


/**
 * Close all modals
 */
function closeAllModals() {
    document.querySelectorAll('.modal').forEach(modal => {
        modal.style.display = 'none';
    });
}

/**
 * Open student modal
 */
function openStudentModal(studentId = null) {
    const modal = document.getElementById('student-modal');
    if (!modal) return;
    
    if (studentId) {
        const student = students.find(s => s.student_id === studentId);
        if (student) {
            populateStudentForm(student);
        }
    } else {
        clearStudentForm();
    }
    
    modal.style.display = 'block';
}

/**
 * Close student modal
 */
function closeStudentModal() {
    const modal = document.getElementById('student-modal');
    if (modal) {
        modal.style.display = 'none';
    }
}

/**
 * Populate student form
 */
function populateStudentForm(student) {
    document.getElementById('student-id').value = student.student_id;
    document.getElementById('student-name').value = student.name;
    document.getElementById('student-email').value = student.email;
    document.getElementById('student-phone').value = student.phone || '';
    document.getElementById('student-program').value = student.program || '';
    document.getElementById('student-shift').value = student.shift || '';
    document.getElementById('student-year').value = student.year_level || '';
    document.getElementById('student-section').value = student.section || '';
}

/**
 * Clear student form
 */
function clearStudentForm() {
    document.getElementById('student-form').reset();
}

/**
 * Handle student form submission
 */
async function handleStudentFormSubmit(form) {
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    
    try {
        const response = await fetch('api/admin_api.php?action=create_student_account', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showAlert('Student created successfully!', 'success');
            closeStudentModal();
            loadStudents(currentPage);
        } else {
            showAlert('Error creating student: ' + result.error, 'error');
        }
    } catch (error) {
        showAlert('Error creating student: ' + error.message, 'error');
    }
}

/**
 * Edit student
 */
function editStudent(studentId) {
    openStudentModal(studentId);
}

/**
 * Delete student
 */
async function deleteStudent(studentId) {
    if (!confirm(`Are you sure you want to delete student ${studentId}?`)) {
        return;
    }
    
    try {
        const response = await fetch(`api/admin_api.php?action=delete_student&student_id=${studentId}`, {
            method: 'DELETE'
        });
        
        const result = await response.json();
        
        if (result.success) {
            showAlert('Student deleted successfully!', 'success');
            loadStudents(currentPage);
        } else {
            showAlert('Error deleting student: ' + result.error, 'error');
        }
    } catch (error) {
        showAlert('Error deleting student: ' + error.message, 'error');
    }
}

/**
 * View student QR
 */
function viewStudentQR(studentId) {
    // Implementation for viewing student QR code
    showAlert('QR code feature coming soon!', 'info');
}

/**
 * Setup drag and drop
 */
function setupDragAndDrop(element) {
    element.addEventListener('dragover', function(e) {
        e.preventDefault();
        this.classList.add('dragover');
    });
    
    element.addEventListener('dragleave', function(e) {
        e.preventDefault();
        this.classList.remove('dragover');
    });
    
    element.addEventListener('drop', function(e) {
        e.preventDefault();
        this.classList.remove('dragover');
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            const fileInput = document.getElementById('file-input');
            if (fileInput) {
                fileInput.files = files;
                handleFileUpload({ target: fileInput });
            }
        }
    });
}

/**
 * Initialize date pickers
 */
function initializeDatePickers() {
    // Implementation for date picker initialization
    // This would integrate with a date picker library
}

/**
 * Initialize range sliders
 */
function initializeRangeSliders() {
    // Implementation for range slider initialization
    // This would integrate with a range slider library
}

/**
 * Initialize dropdowns
 */
function initializeDropdowns() {
    // Implementation for dropdown initialization
    // This would integrate with a dropdown library
}

/**
 * Format file size
 */
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

/**
 * Debounce function
 */
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

/**
 * Load import tab
 */
function loadImportTab() {
    // Implementation for loading import tab
}

/**
 * Load settings
 */
function loadSettings() {
    // Implementation for loading settings
}

/**
 * Load attendance
 */
async function loadAttendance() {
    try {
        showLoading('attendance-table');
        
        const response = await fetch('api/admin_api.php?action=attendance');
        const data = await response.json();
        
        if (data.success) {
            attendance = data.data;
            updateAttendanceTable();
        } else {
            showAlert('Error loading attendance: ' + data.error, 'error');
        }
    } catch (error) {
        showAlert('Error loading attendance: ' + error.message, 'error');
    }
}

/**
 * Update attendance table
 */
function updateAttendanceTable() {
    const tbody = document.querySelector('#attendance-table tbody');
    if (!tbody) return;
    
    if (attendance.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center">No attendance records found</td></tr>';
        return;
    }
    
    tbody.innerHTML = attendance.map(record => `
        <tr>
            <td>${record.student_id}</td>
            <td>${record.student_name}</td>
            <td>${new Date(record.timestamp).toLocaleString()}</td>
            <td>
                <span class="badge ${record.status === 'Present' ? 'success' : 'danger'}">
                    ${record.status}
                </span>
            </td>
            <td>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm warning" onclick="editAttendance(${record.id})" title="Edit">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm danger" onclick="deleteAttendance(${record.id})" title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

/**
 * Open program modal
 */
function openProgramModal(programId = null) {
    const modal = document.getElementById('program-modal');
    if (!modal) return;
    
    if (programId) {
        const program = programs.find(p => p.id === programId);
        if (program) {
            populateProgramForm(program);
        }
    } else {
        clearProgramForm();
    }
    
    modal.style.display = 'block';
}

/**
 * Close program modal
 */
function closeProgramModal() {
    const modal = document.getElementById('program-modal');
    if (modal) {
        modal.style.display = 'none';
    }
}

/**
 * Populate program form
 */
function populateProgramForm(program) {
    document.getElementById('program-code').value = program.code;
    document.getElementById('program-name').value = program.name;
    document.getElementById('program-description').value = program.description || '';
    document.querySelector('input[name="is_active"]').checked = program.is_active;
}

/**
 * Clear program form
 */
function clearProgramForm() {
    document.getElementById('program-form').reset();
}

/**
 * Handle program form submission
 */
async function handleProgramFormSubmit(form) {
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    
    try {
        const response = await fetch('api/program_management_api.php?action=add_program', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showAlert('Program created successfully!', 'success');
            closeProgramModal();
            loadPrograms();
        } else {
            showAlert('Error creating program: ' + result.error, 'error');
        }
    } catch (error) {
        showAlert('Error creating program: ' + error.message, 'error');
    }
}

/**
 * Edit program
 */
function editProgram(programId) {
    openProgramModal(programId);
}

/**
 * Toggle program status
 */
async function toggleProgramStatus(programId) {
    try {
        const response = await fetch(`api/program_management_api.php?action=toggle_program_status&id=${programId}`, {
            method: 'POST'
        });
        
        const result = await response.json();
        
        if (result.success) {
            showAlert('Program status updated successfully!', 'success');
            loadPrograms();
        } else {
            showAlert('Error updating program status: ' + result.error, 'error');
        }
    } catch (error) {
        showAlert('Error updating program status: ' + error.message, 'error');
    }
}

/**
 * Open section modal
 */
function openSectionModal(sectionId = null) {
    const modal = document.getElementById('section-modal');
    if (!modal) return;
    
    if (sectionId) {
        const section = sections.find(s => s.id === sectionId);
        if (section) {
            populateSectionForm(section);
        }
    } else {
        clearSectionForm();
    }
    
    modal.style.display = 'block';
}

/**
 * Close section modal
 */
function closeSectionModal() {
    const modal = document.getElementById('section-modal');
    if (modal) {
        modal.style.display = 'none';
    }
}

/**
 * Populate section form
 */
function populateSectionForm(section) {
    document.getElementById('section-program').value = section.program_id;
    document.getElementById('section-year').value = section.year_level;
    document.getElementById('section-name').value = section.section_name;
    document.getElementById('section-shift').value = section.shift;
    document.getElementById('section-capacity').value = section.capacity;
}

/**
 * Clear section form
 */
function clearSectionForm() {
    document.getElementById('section-form').reset();
}

/**
 * Handle section form submission
 */
async function handleSectionFormSubmit(form) {
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    
    try {
        const response = await fetch('api/program_management_api.php?action=add_section', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showAlert('Section created successfully!', 'success');
            closeSectionModal();
            loadSections();
        } else {
            showAlert('Error creating section: ' + result.error, 'error');
        }
    } catch (error) {
        showAlert('Error creating section: ' + error.message, 'error');
    }
}

/**
 * Edit section
 */
function editSection(sectionId) {
    openSectionModal(sectionId);
}

/**
 * Delete section
 */
async function deleteSection(sectionId) {
    if (!confirm('Are you sure you want to delete this section?')) {
        return;
    }
    
    try {
        const response = await fetch(`api/program_management_api.php?action=delete_section&id=${sectionId}`, {
            method: 'DELETE'
        });
        
        const result = await response.json();
        
        if (result.success) {
            showAlert('Section deleted successfully!', 'success');
            loadSections();
        } else {
            showAlert('Error deleting section: ' + result.error, 'error');
        }
    } catch (error) {
        showAlert('Error deleting section: ' + error.message, 'error');
    }
}

/**
 * Edit attendance
 */
function editAttendance(attendanceId) {
    showAlert('Edit attendance feature coming soon!', 'info');
}

/**
 * Delete attendance
 */
async function deleteAttendance(attendanceId) {
    if (!confirm('Are you sure you want to delete this attendance record?')) {
        return;
    }
    
    try {
        const response = await fetch(`api/admin_api.php?action=delete_attendance&id=${attendanceId}`, {
            method: 'DELETE'
        });
        
        const result = await response.json();
        
        if (result.success) {
            showAlert('Attendance record deleted successfully!', 'success');
            loadAttendance();
        } else {
            showAlert('Error deleting attendance record: ' + result.error, 'error');
        }
    } catch (error) {
        showAlert('Error deleting attendance record: ' + error.message, 'error');
    }
}

/**
 * Export students
 */
async function exportStudents() {
    try {
        const filters = getActiveFilters();
        const params = new URLSearchParams(filters);
        
        const response = await fetch(`api/admin_api.php?action=export_students&${params}`);
        const blob = await response.blob();
        
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `students_export_${new Date().toISOString().split('T')[0]}.csv`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
        
        showAlert('Students exported successfully!', 'success');
    } catch (error) {
        showAlert('Error exporting students: ' + error.message, 'error');
    }
}

/**
 * Export attendance
 */
async function exportAttendance() {
    try {
        const response = await fetch('api/admin_api.php?action=export_attendance');
        const blob = await response.blob();
        
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `attendance_export_${new Date().toISOString().split('T')[0]}.csv`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
        
        showAlert('Attendance exported successfully!', 'success');
    } catch (error) {
        showAlert('Error exporting attendance: ' + error.message, 'error');
    }
}

/**
 * Export data
 */
async function exportData() {
    try {
        const program = document.getElementById('export-program').value;
        const shift = document.getElementById('export-shift').value;
        const year = document.getElementById('export-year').value;
        const format = document.getElementById('export-format').value;
        
        const params = new URLSearchParams({
            program,
            shift,
            year_level: year,
            format
        });
        
        const response = await fetch(`api/admin_api.php?action=export_students&${params}`);
        const blob = await response.blob();
        
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `students_export_${new Date().toISOString().split('T')[0]}.${format}`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
        
        showAlert('Data exported successfully!', 'success');
    } catch (error) {
        showAlert('Error exporting data: ' + error.message, 'error');
    }
}

/**
 * Save settings
 */
async function saveSettings() {
    try {
        const settings = {
            institution_name: document.getElementById('institution-name').value,
            academic_year: document.getElementById('academic-year').value,
            morning_start: document.getElementById('morning-start').value,
            morning_end: document.getElementById('morning-end').value,
            evening_start: document.getElementById('evening-start').value,
            evening_end: document.getElementById('evening-end').value
        };
        
        const response = await fetch('api/admin_api.php?action=save_settings', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(settings)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showAlert('Settings saved successfully!', 'success');
        } else {
            showAlert('Error saving settings: ' + result.error, 'error');
        }
    } catch (error) {
        showAlert('Error saving settings: ' + error.message, 'error');
    }
}

/**
 * Apply filters
 */
function applyFilters() {
    loadStudents(1);
}

/**
 * Parse roll number and auto-fill form fields
 */
async function parseRollNumber(rollNumber) {
    if (!rollNumber || rollNumber.length < 5) {
        return;
    }
    
    try {
        console.log('Parsing roll number:', rollNumber);
        
        const response = await fetch(`api/roll_parser_simple.php?action=parse_roll&roll_number=${encodeURIComponent(rollNumber)}`);
        const result = await response.json();
        
        if (result.success) {
            const data = result.data;
            console.log('Roll number parsed successfully:', data);
            
            // Auto-fill form fields
            const programSelect = document.getElementById('student-program');
            const shiftSelect = document.getElementById('student-shift');
            const yearLevelSelect = document.getElementById('student-year');
            const admissionYearInput = document.getElementById('student-admission-year');
            const sectionSelect = document.getElementById('student-section');
            
            // Fill admission year
            if (admissionYearInput) {
                admissionYearInput.value = data.admission_year;
                admissionYearInput.style.backgroundColor = '#e8f5e8';
                admissionYearInput.style.borderColor = '#4caf50';
            }
            
            // Fill program
            if (programSelect) {
                console.log('Looking for program:', data.program_code);
                console.log('Available options:', Array.from(programSelect.options).map(o => ({value: o.value, text: o.text})));
                for (let option of programSelect.options) {
                    if (option.value === data.program_code) {
                        option.selected = true;
                        programSelect.style.backgroundColor = '#e8f5e8';
                        programSelect.style.borderColor = '#4caf50';
                        console.log('Program selected:', option.text);
                        break;
                    }
                }
            } else {
                console.log('Program select not found');
            }
            
            // Fill shift
            if (shiftSelect) {
                console.log('Looking for shift:', data.shift);
                console.log('Available shift options:', Array.from(shiftSelect.options).map(o => ({value: o.value, text: o.text})));
                for (let option of shiftSelect.options) {
                    if (option.value === data.shift) {
                        option.selected = true;
                        shiftSelect.style.backgroundColor = '#e8f5e8';
                        shiftSelect.style.borderColor = '#4caf50';
                        console.log('Shift selected:', option.text);
                        break;
                    }
                }
            } else {
                console.log('Shift select not found');
            }
            
            // Fill year level
            if (yearLevelSelect) {
                const desired = data.year_level || (data.year_level_numeric === 1 ? '1st' : data.year_level_numeric === 2 ? '2nd' : data.year_level_numeric === 3 ? '3rd' : '');
                console.log('Looking for year level:', desired);
                console.log('Available year level options:', Array.from(yearLevelSelect.options).map(o => ({value: o.value, text: o.text})));
                for (let option of yearLevelSelect.options) {
                    if (option.value === desired) {
                        option.selected = true;
                        yearLevelSelect.style.backgroundColor = '#e8f5e8';
                        yearLevelSelect.style.borderColor = '#4caf50';
                        console.log('Year level selected:', option.text);
                        break;
                    }
                }
            } else {
                console.log('Year level select not found');
            }
            
            // Update sections dropdown
            if (sectionSelect && data.available_sections) {
                sectionSelect.innerHTML = '<option value="">Select Section</option>';
                data.available_sections.forEach(section => {
                    const option = document.createElement('option');
                    option.value = section.id;
                    option.textContent = `${section.section_name} (${section.current_students}/${section.capacity})`;
                    sectionSelect.appendChild(option);
                });
            }
            
            // Show success message
            showRollNumberStatus('success', `Auto-filled: ${data.program_name} - ${data.shift} - ${data.year_level}`);
            
            // Store parsed data for form submission
            window.parsedRollData = data;
            
        } else {
            console.error('Roll number parsing failed:', result.error);
            showRollNumberStatus('error', result.error);
            
            // Reset form fields
            resetAutoFilledFields();
        }
        
    } catch (error) {
        console.error('Error parsing roll number:', error);
        showRollNumberStatus('error', 'Failed to parse roll number. Please check format.');
        resetAutoFilledFields();
    }
}

/**
 * Show roll number parsing status
 */
function showRollNumberStatus(type, message) {
    const statusDiv = document.getElementById('roll-number-status');
    if (!statusDiv) return;
    
    statusDiv.className = `roll-status ${type}`;
    statusDiv.textContent = message;
    statusDiv.style.display = 'block';
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
        statusDiv.style.display = 'none';
    }, 5000);
}

/**
 * Reset auto-filled fields
 */
function resetAutoFilledFields() {
    const fields = [
        'student-program',
        'student-shift', 
        'student-year-level',
        'student-admission-year',
        'student-section'
    ];
    
    fields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            field.style.backgroundColor = '';
            field.style.borderColor = '';
            if (field.tagName === 'SELECT') {
                field.selectedIndex = 0;
            } else {
                field.value = '';
            }
        }
    });
    
    // Clear parsed data
    window.parsedRollData = null;
}

/**
 * Setup roll number auto-fill event listeners
 */
function setupRollNumberAutoFill() {
    const rollNumberInput = document.getElementById('student-roll');
    if (rollNumberInput) {
        // Add event listener for input change
        rollNumberInput.addEventListener('blur', function() {
            const rollNumber = this.value.trim();
            if (rollNumber) {
                parseRollNumber(rollNumber);
            }
        });
        
        // Add event listener for Enter key
        rollNumberInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const rollNumber = this.value.trim();
                if (rollNumber) {
                    parseRollNumber(rollNumber);
                }
            }
        });
    }
}

/**
 * Load year progression status
 * Updated: Fixed API endpoint and error handling
 */
async function loadYearProgressionStatus() {
    try {
        console.log('Loading year progression status from: api/admin_api.php?action=get_year_progression');
        const response = await fetch('api/admin_api.php?action=get_year_progression');
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const text = await response.text();
        let result;
        try {
            result = JSON.parse(text);
        } catch (e) {
            console.error('Invalid JSON response:', text);
            throw new Error('Invalid JSON response from server');
        }
        
        if (result.success) {
            updateProgressionStatus(result.data);
        } else {
            console.error('Error loading progression status:', result.error);
            updateProgressionStatus({
                last_progression: 'Error loading data',
                students_needing_update: 'Unknown',
                current_academic_year: 'Unknown'
            });
        }
    } catch (error) {
        console.error('Error loading progression status:', error);
        updateProgressionStatus({
            last_progression: 'Error loading data',
            students_needing_update: 'Unknown',
            current_academic_year: 'Unknown'
        });
        
        // Don't let this error bubble up
        return;
    }
}

/**
 * Update progression status display
 */
function updateProgressionStatus(data) {
    const lastProgression = document.getElementById('last-progression');
    const studentsNeedingUpdate = document.getElementById('students-needing-update');
    const currentAcademicYear = document.getElementById('current-academic-year');
    
    if (lastProgression) {
        lastProgression.textContent = data.last_progression || 'Never run';
        if (data.last_progression && data.last_progression !== 'Never run') {
            lastProgression.className = 'status-value success';
        } else {
            lastProgression.className = 'status-value warning';
        }
    }
    
    if (studentsNeedingUpdate) {
        const count = data.students_needing_update || 0;
        studentsNeedingUpdate.textContent = count;
        if (count > 0) {
            studentsNeedingUpdate.className = 'status-value warning';
        } else {
            studentsNeedingUpdate.className = 'status-value success';
        }
    }
    
    if (currentAcademicYear) {
        currentAcademicYear.textContent = data.current_academic_year || new Date().getFullYear();
        currentAcademicYear.className = 'status-value';
    }
}

/**
 * Run year progression manually
 */
async function runYearProgression() {
    const button = document.getElementById('run-progression-btn');
    if (button) {
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Running...';
    }
    
    try {
        const response = await fetch('api/admin_api.php?action=get_year_progression&manual=true');
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const text = await response.text();
        let result;
        try {
            result = JSON.parse(text);
        } catch (e) {
            console.error('Invalid JSON response:', text);
            throw new Error('Invalid JSON response from server');
        }
        
        if (result.success) {
            showAlert(`Year progression completed! Updated ${result.updated_students} students, ${result.graduated_students} graduated.`, 'success');
            
            // Reload the progression status
            await loadYearProgressionStatus();
            
            // Reload students data if on students tab
            if (currentTab === 'students') {
                loadStudents(1);
            }
        } else {
            showAlert(`Year progression failed: ${result.error}`, 'error');
        }
    } catch (error) {
        console.error('Error running year progression:', error);
        showAlert(`Error running year progression: ${error.message}`, 'error');
    } finally {
        if (button) {
            button.disabled = false;
            button.innerHTML = '<i class="fas fa-sync"></i> Run Now';
        }
    }
}

// Export functions for global access
window.switchTab = switchTab;
window.openStudentModal = openStudentModal;
window.closeStudentModal = closeStudentModal;
window.editStudent = editStudent;
window.deleteStudent = deleteStudent;
window.viewStudentQR = viewStudentQR;
window.openProgramModal = openProgramModal;
window.closeProgramModal = closeProgramModal;
window.editProgram = editProgram;
window.toggleProgramStatus = toggleProgramStatus;
window.openSectionModal = openSectionModal;
window.closeSectionModal = closeSectionModal;
window.editSection = editSection;
window.deleteSection = deleteSection;
window.editAttendance = editAttendance;
window.deleteAttendance = deleteAttendance;
window.downloadTemplate = downloadTemplate;
window.importStudents = importStudents;
window.exportStudents = exportStudents;
window.exportAttendance = exportAttendance;
window.exportData = exportData;
window.runYearProgression = runYearProgression;
window.saveSettings = saveSettings;
window.applyFilters = applyFilters;
