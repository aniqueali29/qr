<?php
/**
 * Student Attendance Page
 * Attendance tracking and history view
 */

require_once 'includes/config.php';
require_once 'includes/auth.php';

// Require student authentication
requireStudentAuth();

// Get current student data
$current_student = getCurrentStudent();
if (!$current_student) {
    logoutStudent();
    header('Location: login.php');
    exit();
}

// Get attendance data
$attendance_stats = getStudentAttendanceStats($current_student['student_id']);
$recent_attendance = getStudentRecentAttendance($current_student['student_id'], 50);

$pageTitle = "Attendance - " . $current_student['name'];
$currentPage = "attendance";
$pageCSS = ['vendor/libs/apex-charts/apex-charts.css'];

// Include header
include 'includes/partials/header.php';

// Include sidebar
include 'includes/partials/sidebar.php';

// Include navbar
include 'includes/partials/navbar.php';
?>

<!-- Responsive Styles -->
<style>
    /* Mobile-first responsive improvements */
    @media (max-width: 576px) {
        .card-body {
            padding: 1rem;
        }
        
        .card-title {
            font-size: 1.1rem;
        }
        
        .avatar {
            width: 2rem;
            height: 2rem;
        }
        
        .avatar i {
            font-size: 1.2rem;
        }
        
        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
        }
        
        .table-responsive {
            border-radius: 0.375rem;
        }
    }
    
    @media (max-width: 768px) {
        .container-xxl {
            padding-left: 1rem;
            padding-right: 1rem;
        }
        
        .card {
            margin-bottom: 1rem;
        }
        
        .navbar-nav .dropdown-menu {
            min-width: 200px;
        }
    }
    
    @media (max-width: 992px) {
        .layout-navbar {
            padding: 0.5rem 1rem;
        }
        
        .card-header {
            padding: 1rem;
        }
    }
    
    /* Ensure proper text wrapping and truncation */
    .text-truncate {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    
    /* Improve card heights on mobile */
    @media (max-width: 576px) {
        .card.h-100 {
            min-height: auto;
        }
    }
    
    /* Better spacing for mobile cards */
    .d-lg-none .card {
        border: 1px solid rgba(0,0,0,0.125);
        box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
    }
    
    /* Responsive badge sizing */
    @media (max-width: 576px) {
        .badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
    }
    
    /* Filter dropdown styles */
    .filter-option.active {
        background-color: var(--bs-primary);
        color: white;
    }
    
    .filter-option.active i {
        color: white;
    }
    
    .filter-option:hover {
        background-color: var(--bs-light);
    }
    
    .filter-option.active:hover {
        background-color: var(--bs-primary);
        color: white;
    }
    
    /* Loading state for filter button */
    .btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
    
    /* Spinner animation */
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    .bx-spin {
        animation: spin 1s linear infinite;
    }
</style>

                <!-- Content wrapper -->
                <div class="content-wrapper">
                    <!-- Content -->
                    <div class="container-xxl flex-grow-1 container-p-y">
                        <div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center justify-content-between mb-4">
                            <div class="flex-grow-1">
                                <h4 class="fw-bold py-3 mb-2 mb-sm-0">
                                    <span class="text-muted fw-light d-block d-sm-inline">Student /</span> 
                                    <span class="d-block d-sm-inline">Attendance</span>
                                </h4>
                            </div>
                            <!-- Quick Actions for Mobile -->
                            <div class="d-flex gap-2 d-sm-none w-100">
                                <button class="btn btn-outline-primary btn-sm flex-fill" type="button">
                                    <i class="bx bx-refresh me-1"></i>
                                    Refresh
                                </button>
                                <button class="btn btn-primary btn-sm flex-fill" type="button">
                                    <i class="bx bx-download me-1"></i>
                                    Export
                                </button>
                            </div>
                        </div>

                        <!-- Statistics Cards -->
                        <div class="row g-3 mb-4">
                            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6 col-12">
                                <div class="card h-100">
                                    <div class="card-body d-flex flex-column">
                                        <div class="card-title d-flex align-items-start justify-content-between">
                                            <div class="avatar flex-shrink-0">
                                                <i class="bx bx-calendar-check text-primary fs-4"></i>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1">
                                            <span class="fw-semibold d-block mb-1 text-truncate">Present Days</span>
                                            <h3 class="card-title mb-2 text-nowrap"><?php echo $attendance_stats['monthly']['present_days'] ?? 0; ?></h3>
                                            <small class="text-success fw-semibold d-flex align-items-center">
                                                <i class="bx bx-up-arrow-alt me-1"></i> 
                                                <span class="text-truncate">This month</span>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6 col-12">
                                <div class="card h-100">
                                    <div class="card-body d-flex flex-column">
                                        <div class="card-title d-flex align-items-start justify-content-between">
                                            <div class="avatar flex-shrink-0">
                                                <i class="bx bx-x-circle text-danger fs-4"></i>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1">
                                            <span class="fw-semibold d-block mb-1 text-truncate">Absent Days</span>
                                            <h3 class="card-title mb-2 text-nowrap"><?php echo $attendance_stats['monthly']['absent_days'] ?? 0; ?></h3>
                                            <small class="text-danger fw-semibold d-flex align-items-center">
                                                <i class="bx bx-down-arrow-alt me-1"></i> 
                                                <span class="text-truncate">This month</span>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6 col-12">
                                <div class="card h-100">
                                    <div class="card-body d-flex flex-column">
                                        <div class="card-title d-flex align-items-start justify-content-between">
                                            <div class="avatar flex-shrink-0">
                                                <i class="bx bx-trending-up text-info fs-4"></i>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1">
                                            <span class="fw-semibold d-block mb-1 text-truncate">Attendance %</span>
                                            <h3 class="card-title mb-2 text-nowrap"><?php echo number_format($attendance_stats['overall_percentage'] ?? 0, 1); ?>%</h3>
                                            <small class="text-success fw-semibold d-flex align-items-center">
                                                <i class="bx bx-up-arrow-alt me-1"></i> 
                                                <span class="text-truncate">Overall</span>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6 col-12">
                                <div class="card h-100">
                                    <div class="card-body d-flex flex-column">
                                        <div class="card-title d-flex align-items-start justify-content-between">
                                            <div class="avatar flex-shrink-0">
                                                <i class="bx bx-calendar text-warning fs-4"></i>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1">
                                            <span class="fw-semibold d-block mb-1 text-truncate">Total Days</span>
                                            <h3 class="card-title mb-2 text-nowrap"><?php echo $attendance_stats['monthly']['total_days'] ?? 0; ?></h3>
                                            <small class="text-muted fw-semibold d-flex align-items-center">
                                                <i class="bx bx-calendar me-1"></i> 
                                                <span class="text-truncate">This month</span>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Attendance History -->
                        <div class="card">
                            <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
                                <h5 class="card-title m-0">Attendance History</h5>
                                <div class="dropdown position-relative">
                                    <button class="btn btn-sm btn-outline-secondary" type="button" id="transactionID">
                                        <i class="bx bx-filter-alt me-1"></i>
                                        <span class="d-none d-sm-inline" id="filter-text">Filter</span>
                                        <i class="bx bx-chevron-down ms-1"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end position-absolute" id="filter-dropdown" style="display: none; top: 100%; right: 0; z-index: 1000; min-width: 200px;">
                                        <a class="dropdown-item filter-option active" href="javascript:void(0);" data-filter="all" data-days="all">
                                            <i class="bx bx-calendar-check me-2"></i>All Records
                                        </a>
                                        <a class="dropdown-item filter-option" href="javascript:void(0);" data-filter="28days" data-days="28">
                                            <i class="bx bx-calendar me-2"></i>Last 28 Days
                                        </a>
                                        <a class="dropdown-item filter-option" href="javascript:void(0);" data-filter="month" data-days="30">
                                            <i class="bx bx-calendar me-2"></i>Last Month
                                        </a>
                                        <a class="dropdown-item filter-option" href="javascript:void(0);" data-filter="year" data-days="365">
                                            <i class="bx bx-calendar me-2"></i>Last Year
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item" href="javascript:void(0);" onclick="refreshAttendanceData()">
                                            <i class="bx bx-refresh me-2"></i>Refresh Data
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <!-- Desktop Table -->
                                <div class="table-responsive d-none d-lg-block">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="px-3 py-3">Date</th>
                                                <th class="px-3 py-3">Time</th>
                                                <th class="px-3 py-3">Status</th>
                                                <th class="px-3 py-3">Notes</th>
                                            </tr>
                                        </thead>
                                        <tbody class="table-border-bottom-0">
                                            <?php if (empty($recent_attendance)): ?>
                                                <tr>
                                                    <td colspan="4" class="text-center py-5">
                                                        <div class="d-flex flex-column align-items-center">
                                                            <i class="bx bx-calendar-x text-muted" style="font-size: 3rem;"></i>
                                                            <p class="text-muted mt-2 mb-0">No attendance records found</p>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($recent_attendance as $record): ?>
                                                    <tr>
                                                        <td class="px-3 py-3">
                                                            <div class="d-flex flex-column">
                                                                <span class="fw-semibold"><?php echo date('M d, Y', strtotime($record['date'])); ?></span>
                                                                <small class="text-muted"><?php echo date('l', strtotime($record['date'])); ?></small>
                                                            </div>
                                                        </td>
                                                        <td class="px-3 py-3">
                                                            <span class="badge bg-label-info"><?php echo $record['time']; ?></span>
                                                        </td>
                                                        <td class="px-3 py-3">
                                                            <span class="badge bg-label-<?php echo $record['status'] === 'Present' ? 'success' : ($record['status'] === 'Absent' ? 'danger' : 'info'); ?>">
                                                                <?php echo $record['status']; ?>
                                                            </span>
                                                        </td>
                                                        <td class="px-3 py-3">
                                                            <span class="text-muted small"><?php echo $record['notes'] ? sanitizeOutput($record['notes']) : '-'; ?></span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Mobile Cards -->
                                <div class="d-lg-none">
                                    <?php if (empty($recent_attendance)): ?>
                                        <div class="text-center py-5">
                                            <div class="d-flex flex-column align-items-center">
                                                <i class="bx bx-calendar-x text-muted" style="font-size: 3rem;"></i>
                                                <p class="text-muted mt-2 mb-0">No attendance records found</p>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="p-3">
                                            <?php foreach ($recent_attendance as $index => $record): ?>
                                                <div class="card mb-3 <?php echo $index === 0 ? '' : ''; ?>">
                                                    <div class="card-body p-3">
                                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                                            <div class="flex-grow-1">
                                                                <h6 class="card-title mb-1">
                                                                    <?php echo date('M d, Y', strtotime($record['date'])); ?>
                                                                </h6>
                                                                <small class="text-muted">
                                                                    <?php echo date('l', strtotime($record['date'])); ?>
                                                                </small>
                                                            </div>
                                                            <span class="badge bg-label-<?php echo $record['status'] === 'Present' ? 'success' : ($record['status'] === 'Absent' ? 'danger' : 'info'); ?>">
                                                                <?php echo $record['status']; ?>
                                                            </span>
                                                        </div>
                                                        <div class="row g-2">
                                                            <div class="col-6">
                                                                <small class="text-muted d-block">Time</small>
                                                                <span class="badge bg-label-info"><?php echo $record['time']; ?></span>
                                                            </div>
                                                            <div class="col-6">
                                                                <small class="text-muted d-block">Notes</small>
                                                                <span class="small"><?php echo $record['notes'] ? sanitizeOutput($record['notes']) : '-'; ?></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- / Content -->

<?php
// Include footer
include 'includes/partials/footer.php';
?>

<!-- Attendance Filter JavaScript -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM loaded, initializing attendance filters...');
        // Initialize filter functionality
        initializeAttendanceFilters();
    });

    function initializeAttendanceFilters() {
        console.log('Initializing attendance filters...');
        
        // Check if the filter button exists
        const filterButton = document.getElementById('transactionID');
        const filterDropdown = document.getElementById('filter-dropdown');
        console.log('Filter button found:', !!filterButton);
        console.log('Filter dropdown found:', !!filterDropdown);
        
        if (!filterButton || !filterDropdown) {
            console.error('Filter elements not found!');
            return;
        }
        
        // Check if filter options exist
        const filterOptions = document.querySelectorAll('.filter-option');
        console.log('Found filter options:', filterOptions.length);
        
        if (filterOptions.length === 0) {
            console.error('No filter options found!');
            return;
        }
        
        // Setup manual dropdown toggle
        filterButton.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            console.log('Filter button clicked!');
            
            // Toggle dropdown
            const isVisible = filterDropdown.style.display === 'block';
            filterDropdown.style.display = isVisible ? 'none' : 'block';
            
            console.log('Dropdown visibility:', !isVisible ? 'shown' : 'hidden');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!filterButton.contains(e.target) && !filterDropdown.contains(e.target)) {
                filterDropdown.style.display = 'none';
            }
        });
        
        // Add click handlers to filter options
        filterOptions.forEach((option, index) => {
            console.log(`Adding click handler to option ${index}:`, option.textContent.trim());
            
            option.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                console.log('Filter option clicked:', this.textContent.trim());
                
                // Remove active class from all options
                filterOptions.forEach(opt => opt.classList.remove('active'));
                
                // Add active class to clicked option
                this.classList.add('active');
                
                // Get filter data
                const filterType = this.getAttribute('data-filter');
                const days = this.getAttribute('data-days');
                
                console.log('Filter type:', filterType, 'Days:', days);
                
                // Apply filter
                applyAttendanceFilter(filterType, days);
                
                // Update button text
                updateFilterButtonText(this.textContent.trim());
                
                // Close dropdown
                filterDropdown.style.display = 'none';
            });
        });
        
        console.log('Filter initialization complete');
    }

    function setupManualDropdown() {
        console.log('Setting up manual dropdown fallback...');
        
        const filterButton = document.getElementById('transactionID');
        const dropdownMenu = filterButton.nextElementSibling;
        
        if (!dropdownMenu || !dropdownMenu.classList.contains('dropdown-menu')) {
            console.error('Dropdown menu not found for manual setup');
            return;
        }
        
        // Hide dropdown initially
        dropdownMenu.style.display = 'none';
        
        // Add click handler to button
        filterButton.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Toggle dropdown
            const isVisible = dropdownMenu.style.display === 'block';
            dropdownMenu.style.display = isVisible ? 'none' : 'block';
            
            if (!isVisible) {
                // Position dropdown
                dropdownMenu.style.position = 'absolute';
                dropdownMenu.style.top = '100%';
                dropdownMenu.style.right = '0';
                dropdownMenu.style.zIndex = '1000';
            }
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!filterButton.contains(e.target) && !dropdownMenu.contains(e.target)) {
                dropdownMenu.style.display = 'none';
            }
        });
        
        console.log('Manual dropdown setup complete');
    }

    function applyAttendanceFilter(filterType, days) {
        // Show loading state
        showLoadingState();
        
        // Calculate date range if needed
        let dateFrom = null;
        let dateTo = null;
        
        if (days !== 'all') {
            const today = new Date();
            dateTo = today.toISOString().split('T')[0];
            
            const fromDate = new Date();
            fromDate.setDate(fromDate.getDate() - parseInt(days));
            dateFrom = fromDate.toISOString().split('T')[0];
        }
        
        // Make AJAX request to get filtered data
        const params = new URLSearchParams({
            action: 'get_attendance_history',
            student_id: '<?php echo $current_student['student_id']; ?>',
            filter_type: filterType,
            days: days
        });
        
        if (dateFrom) params.append('date_from', dateFrom);
        if (dateTo) params.append('date_to', dateTo);
        
        fetch(`api/student_api.php?${params}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateAttendanceDisplay(data.attendance);
                    updateStatisticsDisplay(data.stats);
                } else {
                    console.error('Filter error:', data.message);
                    showErrorMessage('Failed to load filtered data');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showErrorMessage('Network error occurred');
            })
            .finally(() => {
                hideLoadingState();
            });
    }

    function updateAttendanceDisplay(attendanceData) {
        // Update desktop table
        const desktopTable = document.querySelector('.table-responsive.d-none.d-lg-block tbody');
        const mobileCards = document.querySelector('.d-lg-none .p-3');
        
        if (attendanceData.length === 0) {
            // Show no records message
            desktopTable.innerHTML = `
                <tr>
                    <td colspan="4" class="text-center py-5">
                        <div class="d-flex flex-column align-items-center">
                            <i class="bx bx-calendar-x text-muted" style="font-size: 3rem;"></i>
                            <p class="text-muted mt-2 mb-0">No attendance records found</p>
                        </div>
                    </td>
                </tr>
            `;
            
            mobileCards.innerHTML = `
                <div class="text-center py-5">
                    <div class="d-flex flex-column align-items-center">
                        <i class="bx bx-calendar-x text-muted" style="font-size: 3rem;"></i>
                        <p class="text-muted mt-2 mb-0">No attendance records found</p>
                    </div>
                </div>
            `;
            return;
        }
        
        // Update desktop table
        desktopTable.innerHTML = attendanceData.map(record => `
            <tr>
                <td class="px-3 py-3">
                    <div class="d-flex flex-column">
                        <span class="fw-semibold">${formatDate(record.date)}</span>
                        <small class="text-muted">${formatDayOfWeek(record.date)}</small>
                    </div>
                </td>
                <td class="px-3 py-3">
                    <span class="badge bg-label-info">${record.time}</span>
                </td>
                <td class="px-3 py-3">
                    <span class="badge bg-label-${getStatusColor(record.status)}">
                        ${record.status}
                    </span>
                </td>
                <td class="px-3 py-3">
                    <span class="text-muted small">${record.notes || '-'}</span>
                </td>
            </tr>
        `).join('');
        
        // Update mobile cards
        mobileCards.innerHTML = attendanceData.map((record, index) => `
            <div class="card mb-3">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="flex-grow-1">
                            <h6 class="card-title mb-1">${formatDate(record.date)}</h6>
                            <small class="text-muted">${formatDayOfWeek(record.date)}</small>
                        </div>
                        <span class="badge bg-label-${getStatusColor(record.status)}">
                            ${record.status}
                        </span>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <small class="text-muted d-block">Time</small>
                            <span class="badge bg-label-info">${record.time}</span>
                        </div>
                        <div class="col-6">
                            <small class="text-muted d-block">Notes</small>
                            <span class="small">${record.notes || '-'}</span>
                        </div>
                    </div>
                </div>
            </div>
        `).join('');
    }

    function updateStatisticsDisplay(stats) {
        // Update statistics cards if stats are provided
        if (stats) {
            const presentDaysElement = document.querySelector('.col-xl-3:first-child .card-title');
            const absentDaysElement = document.querySelector('.col-xl-3:nth-child(2) .card-title');
            const attendancePercentElement = document.querySelector('.col-xl-3:nth-child(3) .card-title');
            const totalDaysElement = document.querySelector('.col-xl-3:last-child .card-title');
            
            if (presentDaysElement) presentDaysElement.textContent = stats.present_days || 0;
            if (absentDaysElement) absentDaysElement.textContent = stats.absent_days || 0;
            if (attendancePercentElement) attendancePercentElement.textContent = (stats.percentage || 0).toFixed(1) + '%';
            if (totalDaysElement) totalDaysElement.textContent = stats.total_days || 0;
        }
    }

    function updateFilterButtonText(text) {
        const filterText = document.getElementById('filter-text');
        if (filterText) {
            filterText.textContent = text.split(' ')[0]; // Show first word only
        }
    }

    function refreshAttendanceData() {
        // Get currently active filter
        const activeFilter = document.querySelector('.filter-option.active');
        if (activeFilter) {
            const filterType = activeFilter.getAttribute('data-filter');
            const days = activeFilter.getAttribute('data-days');
            applyAttendanceFilter(filterType, days);
        }
    }

    function showLoadingState() {
        // Add loading spinner or disable interactions
        const button = document.getElementById('transactionID');
        const filterText = document.getElementById('filter-text');
        
        if (button) {
            button.disabled = true;
            if (filterText) {
                filterText.textContent = 'Loading...';
            }
            button.innerHTML = '<i class="bx bx-loader-alt bx-spin me-1"></i><span class="d-none d-sm-inline" id="filter-text">Loading...</span><i class="bx bx-chevron-down ms-1"></i>';
        }
    }

    function hideLoadingState() {
        const button = document.getElementById('transactionID');
        const filterText = document.getElementById('filter-text');
        
        if (button) {
            button.disabled = false;
            button.innerHTML = '<i class="bx bx-filter-alt me-1"></i><span class="d-none d-sm-inline" id="filter-text">Filter</span><i class="bx bx-chevron-down ms-1"></i>';
        }
    }

    function showErrorMessage(message) {
        // You can implement a toast notification here
        console.error(message);
        alert(message); // Simple alert for now
    }

    // Utility functions
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { 
            month: 'short', 
            day: 'numeric', 
            year: 'numeric' 
        });
    }

    function formatDayOfWeek(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { weekday: 'long' });
    }

    function getStatusColor(status) {
        switch(status.toLowerCase()) {
            case 'present': return 'success';
            case 'absent': return 'danger';
            default: return 'info';
        }
    }
</script>
