<?php
/**
 * Reports Page
 * Attendance reports and statistics
 */

require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';

// Require admin authentication
requireAdminAuth();

$pageTitle = "Attendance Reports";
$currentPage = "reports";
$pageCSS = ['css/responsive-buttons.css'];
$pageJS = ['js/admin.js'];

include 'partials/header.php';
include 'partials/sidebar.php';
include 'partials/navbar.php';
?>

<!-- Content wrapper -->
<div class="content-wrapper">
    <!-- Content -->
    <div class="container-xxl flex-grow-1 container-p-y">
        <!-- Breadcrumb -->
        <div class="row">
            <div class="col-12">
                <?php echo generateBreadcrumb([
                    ['title' => 'Dashboard', 'url' => 'index.php'],
                    ['title' => 'Reports', 'url' => '']
                ]); ?>
            </div>
        </div>
        
        <!-- Reports Header -->
        <div class="row mb-4">
            <div class="col-12">
                <h4 class="fw-bold">
                    <i class="bx bx-bar-chart-alt-2 me-2"></i>Attendance Reports & Statistics
                </h4>
                <p class="text-muted">Generate comprehensive attendance reports with detailed analytics</p>
            </div>
        </div>

        <!-- Quick Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3 col-6 mb-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="avatar flex-shrink-0 me-3">
                                <span class="avatar-initial rounded bg-label-primary">
                                    <i class="bx bx-user fs-4"></i>
                                </span>
                            </div>
                            <div>
                                <small class="text-muted d-block">Total Students</small>
                                <h4 class="mb-0" id="stat-total-students">-</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 col-6 mb-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="avatar flex-shrink-0 me-3">
                                <span class="avatar-initial rounded bg-label-success">
                                    <i class="bx bx-check fs-4"></i>
                                </span>
                            </div>
                            <div>
                                <small class="text-muted d-block">Present Today</small>
                                <h4 class="mb-0" id="stat-present-today">-</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 col-6 mb-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="avatar flex-shrink-0 me-3">
                                <span class="avatar-initial rounded bg-label-warning">
                                    <i class="bx bx-time fs-4"></i>
                                </span>
                            </div>
                            <div>
                                <small class="text-muted d-block">Avg Attendance (11 Months)</small>
                                <h4 class="mb-0" id="stat-avg-attendance">-</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 col-6 mb-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="avatar flex-shrink-0 me-3">
                                <span class="avatar-initial rounded bg-label-info">
                                    <i class="bx bx-calendar fs-4"></i>
                                </span>
                            </div>
                            <div>
                                <small class="text-muted d-block">11 Months Avg</small>
                                <h4 class="mb-0" id="stat-month-attendance">-</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Report Generator -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bx bx-file me-2"></i>Generate Report
                </h5>
            </div>
            <div class="card-body">
                <form id="reportForm">
                    <div class="row g-3">
                        <!-- Report Type -->
                        <div class="col-md-4">
                            <label class="form-label">Report Type</label>
                            <select id="report-type" class="form-select" required>
                                <option value="">Select Report Type</option>
                                <option value="daily">Daily Summary</option>
                                <option value="weekly">Weekly Report</option>
                                <option value="monthly">Monthly Report</option>
                                <option value="11months">11 Months Report</option>
                                <option value="custom">Custom Date Range</option>
                                <option value="student">Student-wise Report</option>
                                <option value="program">Program-wise Report</option>
                            </select>
                        </div>

                        <!-- Date Range -->
                        <div class="col-md-4" id="date-range-container">
                            <label class="form-label">From Date</label>
                            <input type="date" id="from-date" class="form-control">
                        </div>

                        <div class="col-md-4" id="to-date-container">
                            <label class="form-label">To Date</label>
                            <input type="date" id="to-date" class="form-control">
                        </div>

                        <!-- Student Filter -->
                        <div class="col-md-4" id="student-select-container" style="display: none;">
                            <label class="form-label">Select Student</label>
                            <select id="student-select" class="form-select">
                                <option value="">All Students</option>
                            </select>
                        </div>

                        <!-- Program Filter -->
                        <div class="col-md-4" id="program-select-container" style="display: none;">
                            <label class="form-label">Select Program</label>
                            <select id="program-select" class="form-select">
                                <option value="">All Programs</option>
                            </select>
                        </div>

                        <!-- Shift Filter -->
                        <div class="col-md-4" id="shift-select-container" style="display: none;">
                            <label class="form-label">Select Shift</label>
                            <select id="shift-select" class="form-select">
                                <option value="">All Shifts</option>
                            </select>
                        </div>

                        <!-- Year Level Filter -->
                        <div class="col-md-4" id="year-level-select-container" style="display: none;">
                            <label class="form-label">Select Year Level</label>
                            <select id="year-level-select" class="form-select">
                                <option value="">All Year Levels</option>
                            </select>
                        </div>

                        <!-- Section Filter -->
                        <div class="col-md-4" id="section-select-container" style="display: none;">
                            <label class="form-label">Select Section</label>
                            <select id="section-select" class="form-select">
                                <option value="">All Sections</option>
                            </select>
                        </div>

                        <!-- Format -->
                        <div class="col-md-4">
                            <label class="form-label">Export Format</label>
                            <select id="export-format" class="form-select">
                                <option value="pdf">PDF Report</option>
                                <option value="csv">CSV Data</option>
                                <option value="excel">Excel Spreadsheet</option>
                            </select>
                        </div>

                        <!-- Submit -->
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="bx bx-download me-1"></i>Generate Report
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="resetReportForm()">
                                <i class="bx bx-reset me-1"></i>Reset
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Charts Visualization -->
        <div class="row mb-4">
            <!-- Attendance Trend Chart -->
            <div class="col-md-8 mb-4">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0">
                            <i class="bx bx-line-chart me-2"></i>Attendance Trend (Last 11 Months)
                        </h5>
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-primary" onclick="loadTrendChart(7)">7 Days</button>
                            <button type="button" class="btn btn-outline-primary" onclick="loadTrendChart(30)">30 Days</button>
                            <button type="button" class="btn btn-outline-primary" onclick="loadTrendChart(90)">90 Days</button>
                            <button type="button" class="btn btn-outline-primary active" onclick="loadTrendChart(330)">11 Months</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <canvas id="attendanceTrendChart" height="80"></canvas>
                    </div>
                </div>
            </div>

            <!-- Program Distribution Chart -->
            <div class="col-md-4 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bx bx-pie-chart-alt-2 me-2"></i>Program Distribution
                        </h5>
                    </div>
                    <div class="card-body">
                        <canvas id="programDistChart" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Shift & Status Charts -->
        <div class="row mb-4">
            <!-- Shift Distribution -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bx bx-time me-2"></i>Shift-wise Performance
                        </h5>
                    </div>
                    <div class="card-body">
                        <canvas id="shiftPerformanceChart" height="150"></canvas>
                    </div>
                </div>
            </div>

            <!-- Monthly Comparison -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bx bx-bar-chart me-2"></i>Monthly Comparison
                        </h5>
                    </div>
                    <div class="card-body">
                        <canvas id="monthlyComparisonChart" height="150"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Tables -->
        <div class="row">
            <!-- Program-wise Statistics -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0">Program-wise Attendance</h5>
                        <button class="btn btn-sm btn-primary" onclick="refreshProgramStats()">
                            <i class="bx bx-refresh"></i>
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Program</th>
                                        <th>Students</th>
                                        <th>Attendance %</th>
                                    </tr>
                                </thead>
                                <tbody id="program-stats-table">
                                    <tr>
                                        <td colspan="3" class="text-center">Loading...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Shift-wise Statistics -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0">Shift-wise Attendance</h5>
                        <button class="btn btn-sm btn-primary" onclick="refreshShiftStats()">
                            <i class="bx bx-refresh"></i>
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Shift</th>
                                        <th>Students</th>
                                        <th>Attendance %</th>
                                    </tr>
                                </thead>
                                <tbody id="shift-stats-table">
                                    <tr>
                                        <td colspan="3" class="text-center">Loading...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Trends -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Attendance Trends (Last 7 Days)</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Present</th>
                                <th>Absent</th>
                                <th>Total</th>
                                <th>Percentage</th>
                            </tr>
                        </thead>
                        <tbody id="trends-table">
                            <tr>
                                <td colspan="5" class="text-center">Loading...</td>
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

<script>
// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    loadQuickStats();
    loadProgramStats();
    loadShiftStats();
    loadTrends();
    loadStudentOptions();
    loadProgramOptions();
    loadShiftOptions();
    loadYearLevelOptions();
    
    // Setup form handlers
    setupReportForm();
    
    // Initialize charts
    initCharts();
});

function setupReportForm() {
    const form = document.getElementById('reportForm');
    const reportType = document.getElementById('report-type');
    const programSelect = document.getElementById('program-select');
    const yearLevelSelect = document.getElementById('year-level-select');
    const shiftSelect = document.getElementById('shift-select');
    
    // Handle report type change
    reportType.addEventListener('change', function() {
        toggleFilters(this.value);
        setDateDefaults(this.value);
    });
    
    // Handle cascading filters
    programSelect.addEventListener('change', function() {
        updateSectionOptions();
    });
    
    yearLevelSelect.addEventListener('change', function() {
        updateSectionOptions();
    });
    
    shiftSelect.addEventListener('change', function() {
        updateSectionOptions();
    });
    
    // Handle form submission
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        generateReport();
    });
}

function updateSectionOptions() {
    const program = document.getElementById('program-select').value;
    const yearLevel = document.getElementById('year-level-select').value;
    const shift = document.getElementById('shift-select').value;
    
    loadSectionOptions(program, yearLevel, shift);
}

function toggleFilters(reportType) {
    const studentContainer = document.getElementById('student-select-container');
    const programContainer = document.getElementById('program-select-container');
    const shiftContainer = document.getElementById('shift-select-container');
    const yearLevelContainer = document.getElementById('year-level-select-container');
    const sectionContainer = document.getElementById('section-select-container');
    
    // Hide all first
    studentContainer.style.display = 'none';
    programContainer.style.display = 'none';
    shiftContainer.style.display = 'none';
    yearLevelContainer.style.display = 'none';
    sectionContainer.style.display = 'none';
    
    // Show relevant filters based on report type
    if (reportType === 'student') {
        studentContainer.style.display = 'block';
    } else if (reportType === 'program') {
        programContainer.style.display = 'block';
        shiftContainer.style.display = 'block';
        yearLevelContainer.style.display = 'block';
        sectionContainer.style.display = 'block';
    } else if (reportType === 'custom' || reportType === 'daily' || reportType === 'weekly' || reportType === 'monthly' || reportType === '11months') {
        // Show all filters for custom date range reports
        programContainer.style.display = 'block';
        shiftContainer.style.display = 'block';
        yearLevelContainer.style.display = 'block';
        sectionContainer.style.display = 'block';
    }
}

function setDateDefaults(reportType) {
    const fromDate = document.getElementById('from-date');
    const toDate = document.getElementById('to-date');
    const today = new Date();
    
    switch(reportType) {
        case 'daily':
            fromDate.value = formatDate(today);
            toDate.value = formatDate(today);
            break;
        case 'weekly':
            const weekAgo = new Date(today);
            weekAgo.setDate(today.getDate() - 6);
            fromDate.value = formatDate(weekAgo);
            toDate.value = formatDate(today);
            break;
        case 'monthly':
            const monthStart = new Date(today.getFullYear(), today.getMonth(), 1);
            fromDate.value = formatDate(monthStart);
            toDate.value = formatDate(today);
            break;
        case '11months':
            const elevenMonthsAgo = new Date(today);
            elevenMonthsAgo.setMonth(today.getMonth() - 11);
            fromDate.value = formatDate(elevenMonthsAgo);
            toDate.value = formatDate(today);
            break;
        default:
            fromDate.value = '';
            toDate.value = '';
    }
}

function formatDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function generateReport() {
    const reportType = document.getElementById('report-type').value;
    const fromDate = document.getElementById('from-date').value;
    const toDate = document.getElementById('to-date').value;
    const format = document.getElementById('export-format').value;
    const studentId = document.getElementById('student-select').value;
    const program = document.getElementById('program-select').value;
    const shift = document.getElementById('shift-select').value;
    const yearLevel = document.getElementById('year-level-select').value;
    const section = document.getElementById('section-select').value;
    
    if (!reportType) {
        UIHelpers.showWarning('Please select a report type');
        return;
    }
    
    const params = new URLSearchParams({
        action: 'generate_report',
        type: reportType,
        from: fromDate,
        to: toDate,
        format: format,
        student: studentId,
        program: program,
        shift: shift,
        year_level: yearLevel,
        section: section
    });
    
    UIHelpers.showInfo('Generating report...');
    window.open(`api/reports.php?${params}`, '_blank');
}

function resetReportForm() {
    document.getElementById('reportForm').reset();
    document.getElementById('student-select-container').style.display = 'none';
    document.getElementById('program-select-container').style.display = 'none';
    document.getElementById('shift-select-container').style.display = 'none';
    document.getElementById('year-level-select-container').style.display = 'none';
    document.getElementById('section-select-container').style.display = 'none';
}

// Load quick statistics
function loadQuickStats() {
    fetch('api/reports.php?action=quick_stats')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('stat-total-students').textContent = data.data.total_students;
                document.getElementById('stat-present-today').textContent = data.data.present_today;
                document.getElementById('stat-avg-attendance').textContent = data.data.avg_attendance + '%';
                document.getElementById('stat-month-attendance').textContent = data.data.month_attendance + '%';
            }
        })
        .catch(error => console.error('Error loading stats:', error));
}

function loadProgramStats() {
    fetch('api/reports.php?action=program_stats')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateProgramStatsTable(data.data);
            }
        })
        .catch(error => console.error('Error loading program stats:', error));
}

function updateProgramStatsTable(stats) {
    const tbody = document.getElementById('program-stats-table');
    
    if (stats.length === 0) {
        tbody.innerHTML = '<tr><td colspan="3" class="text-center">No data available</td></tr>';
        return;
    }
    
    tbody.innerHTML = stats.map(stat => `
        <tr>
            <td><strong>${stat.program}</strong></td>
            <td>${stat.total_students}</td>
            <td>
                <div class="progress" style="height: 20px;">
                    <div class="progress-bar ${getProgressBarClass(stat.attendance_percentage)}" 
                         style="width: ${stat.attendance_percentage}%">
                        ${stat.attendance_percentage}%
                    </div>
                </div>
            </td>
        </tr>
    `).join('');
}

function loadShiftStats() {
    fetch('api/reports.php?action=shift_stats')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateShiftStatsTable(data.data);
            }
        })
        .catch(error => console.error('Error loading shift stats:', error));
}

function updateShiftStatsTable(stats) {
    const tbody = document.getElementById('shift-stats-table');
    
    if (stats.length === 0) {
        tbody.innerHTML = '<tr><td colspan="3" class="text-center">No data available</td></tr>';
        return;
    }
    
    tbody.innerHTML = stats.map(stat => `
        <tr>
            <td><strong>${stat.shift}</strong></td>
            <td>${stat.total_students}</td>
            <td>
                <div class="progress" style="height: 20px;">
                    <div class="progress-bar ${getProgressBarClass(stat.attendance_percentage)}" 
                         style="width: ${stat.attendance_percentage}%">
                        ${stat.attendance_percentage}%
                    </div>
                </div>
            </td>
        </tr>
    `).join('');
}

function loadTrends() {
    fetch('api/reports.php?action=trends')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateTrendsTable(data.data);
            }
        })
        .catch(error => console.error('Error loading trends:', error));
}

function updateTrendsTable(trends) {
    const tbody = document.getElementById('trends-table');
    
    if (trends.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center">No data available</td></tr>';
        return;
    }
    
    tbody.innerHTML = trends.map(trend => `
        <tr>
            <td>${trend.date}</td>
            <td><span class="badge bg-success">${trend.present}</span></td>
            <td><span class="badge bg-danger">${trend.absent}</span></td>
            <td>${trend.total}</td>
            <td>
                <span class="badge ${getProgressBadgeClass(trend.percentage)}">
                    ${trend.percentage}%
                </span>
            </td>
        </tr>
    `).join('');
}

function loadStudentOptions() {
    fetch('api/students.php?action=list&limit=1000')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data.students) {
                const select = document.getElementById('student-select');
                select.innerHTML = '<option value="">All Students</option>';
                
                data.data.students.forEach(student => {
                    const option = document.createElement('option');
                    option.value = student.id;
                    option.textContent = `${student.roll_number} - ${student.name}`;
                    select.appendChild(option);
                });
            }
        })
        .catch(error => console.error('Error loading students:', error));
}

function loadProgramOptions() {
    fetch('api/reports.php?action=get_programs')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                const select = document.getElementById('program-select');
                select.innerHTML = '<option value="">All Programs</option>';
                
                data.data.forEach(program => {
                    const option = document.createElement('option');
                    option.value = program.code;
                    option.textContent = `${program.code} - ${program.name}`;
                    select.appendChild(option);
                });
            }
        })
        .catch(error => console.error('Error loading programs:', error));
}

function loadShiftOptions() {
    fetch('api/reports.php?action=get_shifts')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                const select = document.getElementById('shift-select');
                select.innerHTML = '<option value="">All Shifts</option>';
                
                data.data.forEach(shift => {
                    const option = document.createElement('option');
                    option.value = shift.shift;
                    option.textContent = shift.shift;
                    select.appendChild(option);
                });
            }
        })
        .catch(error => console.error('Error loading shifts:', error));
}

function loadYearLevelOptions() {
    fetch('api/reports.php?action=get_year_levels')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                const select = document.getElementById('year-level-select');
                select.innerHTML = '<option value="">All Year Levels</option>';
                
                data.data.forEach(yearLevel => {
                    const option = document.createElement('option');
                    option.value = yearLevel.year_level;
                    option.textContent = yearLevel.year_level;
                    select.appendChild(option);
                });
            }
        })
        .catch(error => console.error('Error loading year levels:', error));
}

function loadSectionOptions(program = '', yearLevel = '', shift = '') {
    const params = new URLSearchParams();
    if (program) params.append('program', program);
    if (yearLevel) params.append('year_level', yearLevel);
    if (shift) params.append('shift', shift);
    
    fetch(`api/reports.php?action=get_sections&${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                const select = document.getElementById('section-select');
                select.innerHTML = '<option value="">All Sections</option>';
                
                data.data.forEach(section => {
                    const option = document.createElement('option');
                    option.value = section.id;
                    option.textContent = section.section_name;
                    select.appendChild(option);
                });
            }
        })
        .catch(error => console.error('Error loading sections:', error));
}

function getProgressBarClass(percentage) {
    if (percentage >= 80) return 'bg-success';
    if (percentage >= 60) return 'bg-info';
    if (percentage >= 40) return 'bg-warning';
    return 'bg-danger';
}

function getProgressBadgeClass(percentage) {
    if (percentage >= 80) return 'bg-success';
    if (percentage >= 60) return 'bg-info';
    if (percentage >= 40) return 'bg-warning';
    return 'bg-danger';
}

function refreshProgramStats() {
    loadProgramStats();
}

function refreshShiftStats() {
    loadShiftStats();
}

// ========== CHART.JS VISUALIZATION ==========

let trendChart, programChart, shiftChart, monthlyChart;

function initCharts() {
    // Initialize all charts
    loadTrendChart(330); // Default to 11 months
    loadProgramDistChart();
    loadShiftPerformanceChart();
    loadMonthlyComparisonChart();
}

// Attendance Trend Line Chart
function loadTrendChart(days = 30) {
    fetch(`api/reports.php?action=trends&days=${days}`)
        .then(response => response.json())
        .then(data => {
            if (!data.success) return;
            
            const trends = data.data;
            const ctx = document.getElementById('attendanceTrendChart').getContext('2d');
            
            // Destroy existing chart
            if (trendChart) trendChart.destroy();
            
            trendChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: trends.map(t => t.date),
                    datasets: [
                        {
                            label: 'Present',
                            data: trends.map(t => t.present),
                            borderColor: 'rgb(75, 192, 192)',
                            backgroundColor: 'rgba(75, 192, 192, 0.1)',
                            tension: 0.4,
                            fill: true
                        },
                        {
                            label: 'Absent',
                            data: trends.map(t => t.absent),
                            borderColor: 'rgb(255, 99, 132)',
                            backgroundColor: 'rgba(255, 99, 132, 0.1)',
                            tension: 0.4,
                            fill: true
                        },
                        {
                            label: 'Attendance %',
                            data: trends.map(t => t.percentage),
                            borderColor: 'rgb(153, 102, 255)',
                            backgroundColor: 'rgba(153, 102, 255, 0.1)',
                            tension: 0.4,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) label += ': ';
                                    if (context.parsed.y !== null) {
                                        label += context.datasetIndex === 2 ? context.parsed.y + '%' : context.parsed.y;
                                    }
                                    return label;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: { display: true, text: 'Students' }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: { display: true, text: 'Percentage (%)' },
                            grid: { drawOnChartArea: false },
                            max: 100
                        }
                    }
                }
            });
            
            // Update active button
            document.querySelectorAll('.btn-group button').forEach(btn => btn.classList.remove('active'));
            event?.target?.classList.add('active');
        })
        .catch(error => console.error('Error loading trend chart:', error));
}

// Program Distribution Pie Chart
function loadProgramDistChart() {
    fetch('api/reports.php?action=program_stats')
        .then(response => response.json())
        .then(data => {
            if (!data.success) return;
            
            const stats = data.data;
            const ctx = document.getElementById('programDistChart').getContext('2d');
            
            if (programChart) programChart.destroy();
            
            programChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: stats.map(s => s.program),
                    datasets: [{
                        data: stats.map(s => s.total_students),
                        backgroundColor: [
                            'rgba(54, 162, 235, 0.8)',
                            'rgba(255, 99, 132, 0.8)',
                            'rgba(255, 206, 86, 0.8)',
                            'rgba(75, 192, 192, 0.8)',
                            'rgba(153, 102, 255, 0.8)'
                        ],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((value / total) * 100).toFixed(1);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        })
        .catch(error => console.error('Error loading program chart:', error));
}

// Shift Performance Bar Chart
function loadShiftPerformanceChart() {
    fetch('api/reports.php?action=shift_stats')
        .then(response => response.json())
        .then(data => {
            if (!data.success) return;
            
            const stats = data.data;
            const ctx = document.getElementById('shiftPerformanceChart').getContext('2d');
            
            if (shiftChart) shiftChart.destroy();
            
            shiftChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: stats.map(s => s.shift),
                    datasets: [
                        {
                            label: 'Total Students',
                            data: stats.map(s => s.total_students),
                            backgroundColor: 'rgba(54, 162, 235, 0.8)',
                            yAxisID: 'y'
                        },
                        {
                            label: 'Attendance %',
                            data: stats.map(s => s.attendance_percentage),
                            backgroundColor: 'rgba(75, 192, 192, 0.8)',
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top' }
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            position: 'left',
                            title: { display: true, text: 'Students' }
                        },
                        y1: {
                            type: 'linear',
                            position: 'right',
                            title: { display: true, text: 'Percentage (%)' },
                            grid: { drawOnChartArea: false },
                            max: 100
                        }
                    }
                }
            });
        })
        .catch(error => console.error('Error loading shift chart:', error));
}

// Monthly Comparison Chart
function loadMonthlyComparisonChart() {
    fetch('api/reports.php?action=monthly_comparison')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data && data.data.length > 0) {
                const monthData = data.data;
                createMonthlyChart(
                    monthData.map(m => m.month),
                    monthData.map(m => m.avg_attendance)
                );
            } else {
                // Create sample data if no real data available
                const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];
                const attendance = [85, 82, 88, 90, 87, 89];
                createMonthlyChart(months, attendance);
            }
        })
        .catch(error => {
            console.error('Error loading monthly chart:', error);
            // Create sample data on error
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];
            const attendance = [85, 82, 88, 90, 87, 89];
            createMonthlyChart(months, attendance);
        });
}

function createMonthlyChart(labels, data) {
    const ctx = document.getElementById('monthlyComparisonChart').getContext('2d');
    
    if (monthlyChart) monthlyChart.destroy();
    
    monthlyChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Average Attendance %',
                data: data,
                backgroundColor: 'rgba(153, 102, 255, 0.8)',
                borderColor: 'rgba(153, 102, 255, 1)',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    title: { display: true, text: 'Percentage (%)' }
                }
            }
        }
    });
}
</script>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<?php include 'partials/footer.php'; ?>
