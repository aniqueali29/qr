<?php
/**
 * Student Dashboard
 * Main dashboard with overview widgets and analytics
 */

require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';

// Require student authentication
requireStudentAuth();

if (DEBUG_MODE) {
    error_log("Dashboard accessed - Session ID: " . session_id());
    error_log("Dashboard - isStudentLoggedIn(): " . (isStudentLoggedIn() ? 'TRUE' : 'FALSE'));
    error_log("Dashboard - Session variables: " . json_encode($_SESSION));
}

// Get current student data
$current_student = getCurrentStudent();
if (!$current_student) {
    logoutStudent();
    header('Location: login.php');
    exit();
}

// Get student statistics
$attendance_stats = getStudentAttendanceStats($current_student['student_id']);
$recent_attendance = getStudentRecentAttendance($current_student['student_id'], 5);
$upcoming_events = getStudentUpcomingEvents($current_student['student_id']);

$pageTitle = "Dashboard - " . $current_student['name'] . " (" . $current_student['student_id'] . ")";
$currentPage = "dashboard";
$pageCSS = ['vendor/libs/apex-charts/apex-charts.css'];
$pageJS = ['js/dashboards-analytics.js'];

// Include header
include 'includes/partials/header.php';

// Include sidebar
include 'includes/partials/sidebar.php';

// Include navbar
include 'includes/partials/navbar.php';
?>

                <!-- Content wrapper -->
                <div class="content-wrapper">
                    <!-- Content -->
                    <div class="container-xxl flex-grow-1 container-p-y">
                        <div class="row">
                            <div class="col-lg-8 mb-4 order-0">
                                <div class="card">
                                    <div class="d-flex align-items-end row">
                                        <div class="col-sm-7">
                                            <div class="card-body">
                                                <h5 class="card-title text-primary">Welcome back, <?php echo sanitizeOutput($current_student['name']); ?>! 🎉</h5>
                                                <p class="mb-4">
                                                    You have <span class="fw-bold"><?php echo $attendance_stats['monthly']['present_days'] ?? 0; ?></span> present days this month. 
                                                    Your overall attendance is <span class="fw-bold"><?php echo number_format($attendance_stats['overall_percentage'] ?? 0, 1); ?>%</span>.
                                                </p>
                                                <a href="attendance.php" class="btn btn-sm btn-outline-primary">View Attendance</a>
                                            </div>
                                        </div>
                                        <div class="col-sm-5 text-center text-sm-left">
                                            <div class="card-body pb-0 px-0 px-md-4">
                                                <img src="<?php echo getStudentAssetUrl('img/illustrations/man-with-laptop.png'); ?>" height="140" alt="View Badge User" data-app-dark-img="illustrations/man-with-laptop-dark.png" data-app-light-img="illustrations/man-with-laptop-light.png" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-4 col-md-4 order-1">
                                <div class="row">
                                    <div class="col-lg-6 col-md-12 col-6 mb-4">
                                        <div class="card">
                                            <div class="card-body">
                                                <div class="card-title d-flex align-items-start justify-content-between">
                                                    <div class="avatar flex-shrink-0">
                                                        <i class="bx bx-calendar-check text-primary"></i>
                                                    </div>
                                                </div>
                                                <span class="fw-semibold d-block mb-1">Present Days</span>
                                                <h3 class="card-title mb-2"><?php echo $attendance_stats['monthly']['present_days'] ?? 0; ?></h3>
                                                <small class="text-success fw-semibold">
                                                    <i class="bx bx-up-arrow-alt"></i> This month
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-6 col-md-12 col-6 mb-4">
                                        <div class="card">
                                            <div class="card-body">
                                                <div class="card-title d-flex align-items-start justify-content-between">
                                                    <div class="avatar flex-shrink-0">
                                                        <i class="bx bx-trending-up text-info"></i>
                                                    </div>
                                                </div>
                                                <span class="fw-semibold d-block mb-1">Attendance %</span>
                                                <h3 class="card-title mb-2"><?php echo number_format($attendance_stats['overall_percentage'] ?? 0, 1); ?>%</h3>
                                                <small class="text-success fw-semibold">
                                                    <i class="bx bx-up-arrow-alt"></i> Overall
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Recent Attendance -->
                            <div class="col-md-6 col-lg-4 col-xl-4 order-0 mb-4">
                                <div class="card h-100">
                                    <div class="card-header d-flex align-items-center justify-content-between pb-0">
                                        <div class="card-title mb-0">
                                            <h5 class="m-0 me-2">Recent Attendance</h5>
                                        </div>
                                        <div class="dropdown">
                                            <button class="btn p-0" type="button" id="attendanceDropdown" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                <i class="bx bx-dots-vertical-rounded"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="attendanceDropdown">
                                                <a class="dropdown-item" href="attendance.php">View All</a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <div class="d-flex flex-column align-items-center gap-1">
                                                <h2 class="mb-2"><?php echo count($recent_attendance); ?></h2>
                                                <span>Recent Records</span>
                                            </div>
                                            <?php if (count($recent_attendance) > 0 && ($attendance_stats['monthly']['total_days'] > 0)): ?>
                                                <div id="orderStatisticsChart"></div>
                                            <?php else: ?>
                                                <div class="d-flex flex-column align-items-center gap-1">
                                                    <div class="avatar">
                                                        <span class="avatar-initial rounded bg-label-secondary">
                                                            <i class="bx bx-calendar"></i>
                                                        </span>
                                                    </div>
                                                    <small class="text-muted">No data</small>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <ul class="p-0 m-0">
                                            <?php if (empty($recent_attendance)): ?>
                                                <li class="d-flex mb-4 pb-1">
                                                    <div class="empty-state d-flex flex-column align-items-center justify-content-center text-center" style="min-height: 120px; padding: 1rem;">
                                                        <div class="avatar mb-3" style="width: 3rem; height: 3rem;">
                                                            <span class="avatar-initial rounded bg-label-secondary">
                                                                <i class="bx bx-time"></i>
                                                            </span>
                                                        </div>
                                                        <h6 class="text-muted mb-2">No attendance records found</h6>
                                                        <small class="text-muted">Your attendance will appear here</small>
                                                    </div>
                                                </li>
                                            <?php else: ?>
                                                <?php foreach ($recent_attendance as $record): ?>
                                                    <li class="d-flex mb-4 pb-1">
                                                        <div class="avatar flex-shrink-0 me-3">
                                                            <span class="avatar-initial rounded bg-label-<?php echo getStatusBadgeClass($record['status']); ?>">
                                                                <i class="bx bx-<?php echo getStatusIcon($record['status']); ?>"></i>
                                                            </span>
                                                        </div>
                                                        <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                                            <div class="me-2">
                                                                <h6 class="mb-0"><?php echo formatDate($record['date']); ?></h6>
                                                                <small class="text-muted"><?php echo formatTime($record['time']); ?></small>
                                                            </div>
                                                            <div class="user-progress d-flex align-items-center gap-1">
                                                                <span class="badge bg-label-<?php echo getStatusBadgeClass($record['status']); ?>">
                                                                    <?php echo $record['status']; ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </li>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <!--/ Recent Attendance -->

                            <!-- Student Info -->
                            <div class="col-md-6 col-lg-4 order-1 mb-4">
                                <div class="card h-100">
                                    <div class="card-header">
                                        <h5 class="card-title m-0 me-2">Student Information</h5>
                                    </div>
                                    <div class="card-body">
                                        <ul class="p-0 m-0">
                                            <li class="d-flex mb-4 pb-1">
                                                <div class="avatar flex-shrink-0 me-3">
                                                    <span class="avatar-initial rounded bg-label-primary">
                                                        <i class="bx bx-user"></i>
                                                    </span>
                                                </div>
                                                <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                                    <div class="me-2">
                                                        <h6 class="mb-0">Student ID</h6>
                                                        <small class="text-muted"><?php echo sanitizeOutput($current_student['student_id']); ?></small>
                                                    </div>
                                                </div>
                                            </li>
                                            <li class="d-flex mb-4 pb-1">
                                                <div class="avatar flex-shrink-0 me-3">
                                                    <span class="avatar-initial rounded bg-label-success">
                                                        <i class="bx bx-book"></i>
                                                    </span>
                                                </div>
                                                <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                                    <div class="me-2">
                                                        <h6 class="mb-0">Program</h6>
                                                        <small class="text-muted"><?php echo sanitizeOutput($current_student['program']); ?></small>
                                                    </div>
                                                </div>
                                            </li>
                                            <li class="d-flex mb-4 pb-1">
                                                <div class="avatar flex-shrink-0 me-3">
                                                    <span class="avatar-initial rounded bg-label-info">
                                                        <i class="bx bx-time"></i>
                                                    </span>
                                                </div>
                                                <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                                    <div class="me-2">
                                                        <h6 class="mb-0">Shift</h6>
                                                        <small class="text-muted"><?php echo sanitizeOutput($current_student['shift']); ?></small>
                                                    </div>
                                                </div>
                                            </li>
                                            <li class="d-flex mb-4 pb-1">
                                                <div class="avatar flex-shrink-0 me-3">
                                                    <span class="avatar-initial rounded bg-label-warning">
                                                        <i class="bx bx-layer"></i>
                                                    </span>
                                                </div>
                                                <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                                    <div class="me-2">
                                                        <h6 class="mb-0">Year Level</h6>
                                                        <small class="text-muted"><?php echo sanitizeOutput($current_student['year_level']); ?></small>
                                                    </div>
                                                </div>
                                            </li>
                                            <li class="d-flex mb-4 pb-1">
                                                <div class="avatar flex-shrink-0 me-3">
                                                    <span class="avatar-initial rounded bg-label-secondary">
                                                        <i class="bx bx-group"></i>
                                                    </span>
                                                </div>
                                                <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                                    <div class="me-2">
                                                        <h6 class="mb-0">Section</h6>
                                                        <small class="text-muted"><?php echo sanitizeOutput($current_student['section']); ?></small>
                                                    </div>
                                                </div>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <!--/ Student Info -->

                            <!-- Upcoming Events -->
                            <div class="col-md-6 col-lg-4 order-2 mb-4">
                                <div class="card h-100">
                                    <div class="card-header d-flex align-items-center justify-content-between pb-0">
                                        <div class="card-title mb-0">
                                            <h5 class="m-0 me-2">Upcoming Events</h5>
                                        </div>
                                        <div class="dropdown">
                                            <button class="btn p-0" type="button" id="eventsDropdown" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                <i class="bx bx-dots-vertical-rounded"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="eventsDropdown">
                                                <a class="dropdown-item" href="assignments.php">View Assignments</a>
                                                <a class="dropdown-item" href="quizzes.php">View Quizzes</a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <?php if (empty($upcoming_events)): ?>
                                            <div class="empty-state">
                                                <div class="avatar">
                                                    <span class="avatar-initial rounded bg-label-secondary">
                                                        <i class="bx bx-calendar-event"></i>
                                                    </span>
                                                </div>
                                                <h6 class="text-muted">No upcoming events</h6>
                                                <small class="text-muted">Your assignments and quizzes will appear here</small>
                                            </div>
                                        <?php else: ?>
                                            <ul class="p-0 m-0">
                                                <?php foreach ($upcoming_events as $event): ?>
                                                    <li class="d-flex mb-4 pb-1">
                                                        <div class="avatar flex-shrink-0 me-3">
                                                            <span class="avatar-initial rounded bg-label-<?php echo $event['type'] === 'assignment' ? 'primary' : 'warning'; ?>">
                                                                <i class="bx bx-<?php echo $event['type'] === 'assignment' ? 'book' : 'edit'; ?>"></i>
                                                            </span>
                                                        </div>
                                                        <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                                            <div class="me-2">
                                                                <h6 class="mb-0"><?php echo sanitizeOutput($event['name']); ?></h6>
                                                                <small class="text-muted"><?php echo ucfirst($event['type']); ?></small>
                                                            </div>
                                                            <div class="user-progress d-flex align-items-center gap-1">
                                                                <span class="badge bg-label-<?php echo getUrgencyClass($event['date']); ?>">
                                                                    <?php 
                                                                    $days = getDaysUntilDue($event['date']);
                                                                    if ($days < 0) {
                                                                        echo 'Overdue';
                                                                    } elseif ($days == 0) {
                                                                        echo 'Due Today';
                                                                    } elseif ($days == 1) {
                                                                        echo 'Due Tomorrow';
                                                                    } else {
                                                                        echo $days . ' days';
                                                                    }
                                                                    ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <!--/ Upcoming Events -->
                        </div>

                        <!-- Attendance Chart Section -->
                        <div class="row">
                            <div class="col-12 mb-4">
                                <div class="card">
                                    <div class="card-header d-flex align-items-center justify-content-between">
                                        <h5 class="card-title m-0 me-2">Attendance Trend (Last 6 Months)</h5>
                                        <div class="dropdown">
                                            <button class="btn p-0" type="button" id="chartDropdown" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                <i class="bx bx-dots-vertical-rounded"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="chartDropdown">
                                                <a class="dropdown-item" href="attendance.php">View Detailed Report</a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div id="attendanceChart" style="height: 300px;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!--/ Attendance Chart Section -->

                        <!-- Performance Summary -->
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title m-0 me-2">Academic Performance</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php 
                                        $performance = getStudentPerformanceSummary($current_student['student_id']);
                                        ?>
                                        <div class="row">
                                            <div class="col-6">
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar flex-shrink-0 me-3">
                                                        <span class="avatar-initial rounded bg-label-primary">
                                                            <i class="bx bx-book"></i>
                                                        </span>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-0">Assignments</h6>
                                                        <small class="text-muted"><?php echo $performance['assignments']['total']; ?> completed</small>
                                                    </div>
                                                </div>
                                                <div class="mt-2">
                                                    <span class="fw-semibold">Average Grade: </span>
                                                    <span class="text-primary"><?php echo $performance['assignments']['average_grade']; ?>%</span>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar flex-shrink-0 me-3">
                                                        <span class="avatar-initial rounded bg-label-warning">
                                                            <i class="bx bx-edit"></i>
                                                        </span>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-0">Quizzes</h6>
                                                        <small class="text-muted"><?php echo $performance['quizzes']['total']; ?> taken</small>
                                                    </div>
                                                </div>
                                                <div class="mt-2">
                                                    <span class="fw-semibold">Best Score: </span>
                                                    <span class="text-warning"><?php echo $performance['quizzes']['best_score']; ?>%</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title m-0 me-2">Monthly Summary</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-6">
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar flex-shrink-0 me-3">
                                                        <span class="avatar-initial rounded bg-label-success">
                                                            <i class="bx bx-check-circle"></i>
                                                        </span>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-0">Present Days</h6>
                                                        <small class="text-muted">This month</small>
                                                    </div>
                                                </div>
                                                <div class="mt-2">
                                                    <span class="fw-semibold"><?php echo $attendance_stats['monthly']['present_days'] ?? 0; ?></span>
                                                    <span class="text-muted">/ <?php echo $attendance_stats['monthly']['total_days'] ?? 0; ?> days</span>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar flex-shrink-0 me-3">
                                                        <span class="avatar-initial rounded bg-label-danger">
                                                            <i class="bx bx-x-circle"></i>
                                                        </span>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-0">Absent Days</h6>
                                                        <small class="text-muted">This month</small>
                                                    </div>
                                                </div>
                                                <div class="mt-2">
                                                    <span class="fw-semibold"><?php echo $attendance_stats['monthly']['absent_days'] ?? 0; ?></span>
                                                    <span class="text-muted">days</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!--/ Performance Summary -->
                    </div>
                    <!-- / Content -->

<?php
// Include footer
include 'includes/partials/footer.php';
?>
    
    <!-- Custom Styles for Empty States -->
    <style>
        .empty-state {
            min-height: 200px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 2rem 1rem;
        }
        
        .empty-state .avatar {
            width: 4rem;
            height: 4rem;
            margin-bottom: 1rem;
        }
        
        .empty-state .avatar-initial {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .empty-state h6 {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .empty-state small {
            opacity: 0.8;
            line-height: 1.4;
        }
    </style>

<!-- Vendors JS -->
<script src="<?php echo getStudentAssetUrl('vendor/libs/apex-charts/apexcharts.js'); ?>"></script>

    <!-- Attendance Chart -->
    <script>
        // Get attendance chart data from PHP
        <?php 
        $chart_data = getStudentAttendanceChartData($current_student['student_id']);
        ?>
        const attendanceChartData = {
            months: <?php echo json_encode($chart_data['months']); ?>,
            percentages: <?php echo json_encode($chart_data['percentages']); ?>
        };

        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof ApexCharts !== 'undefined') {
                // Order Statistics Chart (Recent Attendance widget)
                const orderStatisticsChartEl = document.querySelector('#orderStatisticsChart');
                if (orderStatisticsChartEl && <?php echo count($recent_attendance); ?> > 0 && <?php echo $attendance_stats['monthly']['total_days'] ?? 0; ?> > 0) {
                    // Use overall attendance percentage instead of just recent records
                    const overallPercentage = <?php echo $attendance_stats['overall_percentage'] ?? 0; ?>;
                    const monthlyTotal = <?php echo $attendance_stats['monthly']['total_days'] ?? 0; ?>;
                    const monthlyPresent = <?php echo $attendance_stats['monthly']['present_days'] ?? 0; ?>;
                    const monthlyAbsent = <?php echo $attendance_stats['monthly']['absent_days'] ?? 0; ?>;
                    const monthlyCheckin = <?php echo $attendance_stats['monthly']['checkin_days'] ?? 0; ?>;
                    
                    // For chart display, use overall percentage if available, otherwise calculate from monthly data
                    const displayPercentage = overallPercentage > 0 ? overallPercentage : 
                        (monthlyTotal > 0 ? Math.round((monthlyPresent / monthlyTotal) * 100) : 0);
                    
                    // Chart data calculated from real attendance statistics
                    
                    // Clear any existing chart
                    orderStatisticsChartEl.innerHTML = '';
                    
                    const orderStatisticsChart = new ApexCharts(orderStatisticsChartEl, {
                        chart: {
                            type: 'donut',
                            height: 85,
                            width: 85,
                            sparkline: {
                                enabled: true
                            }
                        },
                        labels: ['Present', 'Absent', 'Check-in'],
                        series: [monthlyPresent, monthlyAbsent, monthlyCheckin],
                        colors: ['#71dd37', '#ff3e1d', '#03c3ec'],
                        stroke: {
                            width: 0
                        },
                        dataLabels: {
                            enabled: false
                        },
                        legend: {
                            show: false
                        },
                        plotOptions: {
                            pie: {
                                donut: {
                                    size: '70%',
                                    labels: {
                                        show: true,
                                        name: {
                                            show: false
                                        },
                                        value: {
                                            show: true,
                                            fontSize: '12px',
                                            fontWeight: 600,
                                            color: '#8592a3',
                                            formatter: function(val) {
                                                return displayPercentage + '%';
                                            }
                                        },
                                        total: {
                                            show: false
                                        }
                                    }
                                }
                            }
                        },
                        tooltip: {
                            enabled: false
                        }
                    });
                    orderStatisticsChart.render();
                }

                // Main Attendance Trend Chart
                const attendanceChartEl = document.querySelector("#attendanceChart");
                if (attendanceChartEl) {
                    const attendanceChartOptions = {
                        series: [{
                            name: 'Attendance %',
                            data: attendanceChartData.percentages
                        }],
                        chart: {
                            type: 'line',
                            height: 300,
                            toolbar: {
                                show: false
                            }
                        },
                        colors: ['#696cff'],
                        stroke: {
                            curve: 'smooth',
                            width: 3
                        },
                        xaxis: {
                            categories: attendanceChartData.months,
                            labels: {
                                style: {
                                    colors: '#8592a3'
                                }
                            }
                        },
                        yaxis: {
                            min: 0,
                            max: 100,
                            labels: {
                                style: {
                                    colors: '#8592a3'
                                },
                                formatter: function(value) {
                                    return value + '%';
                                }
                            }
                        },
                        grid: {
                            borderColor: '#e7eef7',
                            strokeDashArray: 5
                        },
                        tooltip: {
                            y: {
                                formatter: function(value) {
                                    return value + '%';
                                }
                            }
                        },
                        markers: {
                            size: 5,
                            colors: ['#696cff'],
                            strokeColors: '#fff',
                            strokeWidth: 2,
                            hover: {
                                size: 7
                            }
                        }
                    };

                    const attendanceChart = new ApexCharts(attendanceChartEl, attendanceChartOptions);
                    attendanceChart.render();
                }
            }
        });
    </script>
