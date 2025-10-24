<?php
/**
 * Admin Dashboard
 * Main dashboard with stats, charts, and overview
 */

require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';

// Require admin authentication
requireAdminAuth();

// Load dashboard data server-side
try {
    // Get basic statistics
    $totalStudents = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
    $totalPrograms = $pdo->query("SELECT COUNT(*) FROM programs")->fetchColumn();
    $totalSections = $pdo->query("SELECT COUNT(*) FROM sections")->fetchColumn();
    
    // Today's attendance
    $todayAttendance = $pdo->query("
        SELECT COUNT(DISTINCT student_id) as present_today 
        FROM attendance 
        WHERE DATE(check_in_time) = CURDATE()
    ")->fetchColumn();
    
    // Total attendance records
    $totalAttendance = $pdo->query("SELECT COUNT(*) FROM attendance")->fetchColumn();
    
    // Active programs (programs with students)
    $activePrograms = $pdo->query("
        SELECT COUNT(DISTINCT p.id) 
        FROM programs p 
        INNER JOIN students s ON p.code = s.program
    ")->fetchColumn();
    
    // Get programs for distribution chart
    $programDistribution = $pdo->query("
        SELECT 
            p.name as program_name,
            COUNT(s.id) as student_count
        FROM programs p
        LEFT JOIN students s ON p.code = s.program
        GROUP BY p.id, p.name
        ORDER BY student_count DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent activity
    $recentActivity = $pdo->query("
        SELECT 
            a.id,
            s.name as student_name,
            s.student_id,
            a.check_in_time,
            a.status
        FROM attendance a
        JOIN students s ON a.student_id = s.student_id
        ORDER BY a.check_in_time DESC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Get attendance trends (last 7 days)
    $attendanceTrends = $pdo->query("
        SELECT 
            DATE(check_in_time) as date,
            COUNT(DISTINCT student_id) as present_students
        FROM attendance 
        WHERE check_in_time >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(check_in_time)
        ORDER BY date ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Get shift-specific attendance for today - BUG FIX #10: Add NULL checks
    $morningAttendance = $pdo->query("
        SELECT COUNT(DISTINCT a.student_id) as morning_count
        FROM attendance a
        JOIN students s ON a.student_id = s.student_id
        WHERE DATE(a.check_in_time) = CURDATE() AND s.shift = 'Morning'
    ")->fetchColumn() ?? 0;
    
    $eveningAttendance = $pdo->query("
        SELECT COUNT(DISTINCT a.student_id) as evening_count
        FROM attendance a
        JOIN students s ON a.student_id = s.student_id
        WHERE DATE(a.check_in_time) = CURDATE() AND s.shift = 'Evening'
    ")->fetchColumn() ?? 0;
    
    // Get total students by shift - BUG FIX #10: Add NULL checks
    $totalMorningStudents = $pdo->query("
        SELECT COUNT(*) FROM students WHERE shift = 'Morning'
    ")->fetchColumn() ?? 0;
    
    $totalEveningStudents = $pdo->query("
        SELECT COUNT(*) FROM students WHERE shift = 'Evening'
    ")->fetchColumn() ?? 0;
    
    // Calculate absent students
    $absentToday = $totalStudents - $todayAttendance;
    $morningAbsent = $totalMorningStudents - $morningAttendance;
    $eveningAbsent = $totalEveningStudents - $eveningAttendance;
    
    // Calculate attendance rate
    $attendanceRate = $totalStudents > 0 ? round(($todayAttendance / $totalStudents) * 100, 1) : 0;
    
} catch (Exception $e) {
    // Set default values if database query fails
    $totalStudents = 0;
    $totalPrograms = 0;
    $totalSections = 0;
    $todayAttendance = 0;
    $totalAttendance = 0;
    $activePrograms = 0;
    $programDistribution = [];
    $recentActivity = [];
    $attendanceTrends = [];
    $morningAttendance = 0;
    $eveningAttendance = 0;
    $totalMorningStudents = 0;
    $totalEveningStudents = 0;
    $absentToday = 0;
    $morningAbsent = 0;
    $eveningAbsent = 0;
    $attendanceRate = 0;
}

$pageTitle = "Dashboard";
$currentPage = "index";
$pageCSS = ['vendor/libs/apex-charts/apex-charts.css'];
$pageJS = ['vendor/libs/apex-charts/apexcharts.js', 'js/dashboards-analytics.js'];

include 'partials/header.php';
include 'partials/sidebar.php';
include 'partials/navbar.php';
?>

<!-- Content wrapper -->
<div class="content-wrapper">
    <!-- Content -->
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="row">
            <div class="col-xxl-8 mb-6 order-0">
                <div class="card">
                    <div class="d-flex align-items-start row">
                        <div class="col-sm-7">
                            <div class="card-body">
                                <h5 class="card-title text-primary mb-3">Welcome to QR Attendance System! 🎉</h5>
                                <p class="mb-6">
                                    Monitor student attendance, manage programs, and track academic progress with our comprehensive admin dashboard.
                                </p>
                                <a href="students.php" class="btn btn-sm btn-outline-primary">Manage Students</a>
                            </div>
                        </div>
                        <div class="col-sm-5 text-center text-sm-left">
                            <div class="card-body pb-0 px-0 px-md-6">
                                <img src="<?php echo getAdminAssetUrl('img/illustrations/man-with-laptop.png'); ?>" height="175" alt="Admin Dashboard" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xxl-4 col-lg-12 col-md-4 order-1">
                <div class="row">
                    <div class="col-lg-6 col-md-12 col-6 mb-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="card-title d-flex align-items-start justify-content-between mb-4">
                                    <div class="avatar flex-shrink-0">
                                        <img src="<?php echo getAdminAssetUrl('img/icons/unicons/chart-success.png'); ?>" alt="chart success" class="rounded" />
                                    </div>
                                    <div class="dropdown">
                                        <button class="btn p-0" type="button" id="cardOpt3" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            <i class="icon-base bx bx-dots-vertical-rounded text-body-secondary"></i>
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-end" aria-labelledby="cardOpt3">
                                            <a class="dropdown-item" href="students.php">View Students</a>
                                            <a class="dropdown-item" href="importexport.php">Import Data</a>
                                        </div>
                                    </div>
                                </div>
                                <p class="mb-1">Total Students</p>
                                <h4 class="card-title mb-3" id="total-students"><?php echo number_format($totalStudents); ?></h4>
                                <small class="text-success fw-medium" id="students-change">
                                    <i class="icon-base bx bx-up-arrow-alt"></i> Active Students
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 col-md-12 col-6 mb-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="card-title d-flex align-items-start justify-content-between mb-4">
                                    <div class="avatar flex-shrink-0">
                                        <img src="<?php echo getAdminAssetUrl('img/icons/unicons/wallet-info.png'); ?>" alt="wallet info" class="rounded" />
                                    </div>
                                    <div class="dropdown">
                                        <button class="btn p-0" type="button" id="cardOpt6" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            <i class="icon-base bx bx-dots-vertical-rounded text-body-secondary"></i>
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-end" aria-labelledby="cardOpt6">
                                            <a class="dropdown-item" href="attendances.php">View Attendance</a>
                                            <a class="dropdown-item" href="importexport.php">Export Data</a>
                                        </div>
                                    </div>
                                </div>
                                <p class="mb-1">Today's Attendance</p>
                                <h4 class="card-title mb-3" id="today-attendance"><?php echo number_format($todayAttendance); ?></h4>
                                <small class="text-success fw-medium" id="attendance-change">
                                    <i class="icon-base bx bx-up-arrow-alt"></i> Present Today
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Total Revenue -->
            <div class="col-12 col-xxl-8 order-2 order-md-3 order-xxl-2 mb-6 total-revenue">
                <div class="card">
                    <div class="row row-bordered g-0">
                        <div class="col-lg-8">
                            <div class="card-header d-flex align-items-center justify-content-between">
                                <div class="card-title mb-0">
                                    <h5 class="m-0 me-2">Attendance Trends</h5>
                                </div>
                                <div class="dropdown">
                                    <button class="btn p-0" type="button" id="totalRevenue" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <i class="icon-base bx bx-dots-vertical-rounded icon-lg text-body-secondary"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end" aria-labelledby="totalRevenue">
                                        <a class="dropdown-item" href="attendances.php">View All Records</a>
                                        <a class="dropdown-item" href="javascript:void(0);" onclick="refreshCharts()">Refresh</a>
                                    </div>
                                </div>
                            </div>
                            <div id="totalRevenueChart" class="px-3"></div>
                        </div>
                        <div class="col-lg-4">
                            <div class="card-body px-xl-9 py-12 d-flex align-items-center flex-column">
                                <div class="text-center mb-6">
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-outline-primary" id="trend-period-7">7 Days</button>
                                        <button type="button" class="btn btn-outline-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                                            <span class="visually-hidden">Toggle Dropdown</span>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="javascript:void(0);" onclick="setTrendPeriod(7)">7 Days</a></li>
                                            <li><a class="dropdown-item" href="javascript:void(0);" onclick="setTrendPeriod(30)">30 Days</a></li>
                                            <li><a class="dropdown-item" href="javascript:void(0);" onclick="setTrendPeriod(90)">90 Days</a></li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <div id="growthChart"></div>
                                <div class="text-center fw-medium my-6" id="attendance-rate"><?php echo $attendanceRate; ?>%</div>
                                
                                <div class="d-flex gap-11 justify-content-between">
                                    <div class="d-flex">
                                        <div class="avatar me-2">
                                            <span class="avatar-initial rounded-2 bg-label-primary">
                                                <i class="icon-base bx bx-user icon-lg text-primary"></i>
                                            </span>
                                        </div>
                                        <div class="d-flex flex-column">
                                            <small>Present Today</small>
                                            <h6 class="mb-0" id="present-today"><?php echo number_format($todayAttendance); ?></h6>
                                        </div>
                                    </div>
                                    <div class="d-flex">
                                        <div class="avatar me-2">
                                            <span class="avatar-initial rounded-2 bg-label-info">
                                                <i class="icon-base bx bx-user-x icon-lg text-info"></i>
                                            </span>
                                        </div>
                                        <div class="d-flex flex-column">
                                            <small>Absent Today</small>
                                            <h6 class="mb-0" id="absent-today"><?php echo number_format($absentToday); ?></h6>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!--/ Total Revenue -->
            
            <div class="col-12 col-md-8 col-lg-12 col-xxl-4 order-3 order-md-2 profile-report">
                <div class="row">
                    <div class="col-6 mb-6 programs">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="card-title d-flex align-items-start justify-content-between mb-4">
                                    <div class="avatar flex-shrink-0">
                                        <img src="<?php echo getAdminAssetUrl('img/icons/unicons/paypal.png'); ?>" alt="programs" class="rounded" />
                                    </div>
                                    <div class="dropdown">
                                        <button class="btn p-0" type="button" id="cardOpt4" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            <i class="icon-base bx bx-dots-vertical-rounded text-body-secondary"></i>
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-end" aria-labelledby="cardOpt4">
                                            <a class="dropdown-item" href="program_sections.php">Manage Programs</a>
                                            <a class="dropdown-item" href="importexport.php">Import Programs</a>
                                        </div>
                                    </div>
                                </div>
                                <p class="mb-1">Active Programs</p>
                                <h4 class="card-title mb-3" id="total-programs"><?php echo number_format($activePrograms); ?></h4>
                                <small class="text-success fw-medium" id="programs-change">
                                    <i class="icon-base bx bx-up-arrow-alt"></i> Active Programs
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 mb-6 sections">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="card-title d-flex align-items-start justify-content-between mb-4">
                                    <div class="avatar flex-shrink-0">
                                        <img src="<?php echo getAdminAssetUrl('img/icons/unicons/cc-primary.png'); ?>" alt="sections" class="rounded" />
                                    </div>
                                    <div class="dropdown">
                                        <button class="btn p-0" type="button" id="cardOpt1" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            <i class="icon-base bx bx-dots-vertical-rounded text-body-secondary"></i>
                                        </button>
                                        <div class="dropdown-menu" aria-labelledby="cardOpt1">
                                            <a class="dropdown-item" href="program_sections.php">Manage Sections</a>
                                            <a class="dropdown-item" href="importexport.php">Import Sections</a>
                                        </div>
                                    </div>
                                </div>
                                <p class="mb-1">Total Sections</p>
                                <h4 class="card-title mb-3" id="total-sections"><?php echo number_format($totalSections); ?></h4>
                                <small class="text-success fw-medium" id="sections-change">
                                    <i class="icon-base bx bx-up-arrow-alt"></i> Total Sections
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Order Statistics -->
            <div class="col-md-6 col-lg-4 col-xl-4 order-0 mb-6">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between">
                        <div class="card-title mb-0">
                            <h5 class="mb-1 me-2">Program Distribution</h5>
                            <p class="card-subtitle">Students by Program</p>
                        </div>
                        <div class="dropdown">
                            <button class="btn text-body-secondary p-0" type="button" id="orederStatistics" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="icon-base bx bx-dots-vertical-rounded icon-lg"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="orederStatistics">
                                <a class="dropdown-item" href="program_sections.php">Manage Programs</a>
                                <a class="dropdown-item" href="javascript:void(0);" onclick="refreshCharts()">Refresh</a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-6">
                            <div class="d-flex flex-column align-items-center gap-1">
                                <h3 class="mb-1" id="total-enrolled"><?php echo number_format($totalStudents); ?></h3>
                                <small>Total Enrolled</small>
                            </div>
                            <div id="orderStatisticsChart"></div>
                        </div>
                        <ul class="p-0 m-0" id="program-distribution-list">
                            <?php if (!empty($programDistribution)): ?>
                                <?php foreach ($programDistribution as $program): ?>
                                    <li class="d-flex align-items-center mb-5">
                                        <div class="d-flex w-100 align-items-center">
                                            <div class="d-flex justify-content-between w-100">
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar avatar-sm me-3">
                                                        <span class="avatar-initial rounded bg-label-primary">
                                                            <i class="icon-base bx bx-book"></i>
                                                        </span>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-0"><?php echo htmlspecialchars($program['program_name']); ?></h6>
                                                        <small class="text-muted"><?php echo $program['student_count']; ?> students</small>
                                                    </div>
                                                </div>
                                                <div class="user-progress">
                                                    <small class="fw-medium"><?php echo $program['student_count']; ?></small>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li class="d-flex align-items-center mb-5">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar avatar-sm me-3">
                                            <span class="avatar-initial rounded bg-label-secondary">
                                                <i class="icon-base bx bx-info-circle"></i>
                                            </span>
                                        </div>
                                        <div>
                                            <h6 class="mb-0">No programs found</h6>
                                            <small class="text-muted">Add programs to see distribution</small>
                                        </div>
                                    </div>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
            <!--/ Order Statistics -->
            
            <!-- Attendance Overview -->
            <div class="col-md-6 col-lg-4 order-1 mb-6">
                <div class="card h-100">
                    <div class="card-header nav-align-top">
                        <ul class="nav nav-pills flex-wrap row-gap-2" role="tablist">
                            <li class="nav-item">
                                <button type="button" class="nav-link active" role="tab" data-bs-toggle="tab" data-bs-target="#navs-tabs-line-card-morning" aria-controls="navs-tabs-line-card-morning" aria-selected="true">
                                    Morning
                                </button>
                            </li>
                            <li class="nav-item">
                                <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#navs-tabs-line-card-evening" aria-controls="navs-tabs-line-card-evening" aria-selected="false">
                                    Evening
                                </button>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content p-0">
                            <div class="tab-pane fade show active" id="navs-tabs-line-card-morning" role="tabpanel">
                                <div class="d-flex mb-6">
                                    <div class="avatar flex-shrink-0 me-3">
                                        <img src="<?php echo getAdminAssetUrl('img/icons/unicons/wallet.png'); ?>" alt="Morning Shift" />
                                    </div>
                                    <div>
                                        <p class="mb-0">Morning Shift Attendance</p>
                                        <div class="d-flex align-items-center">
                                            <h6 class="mb-0 me-1" id="morning-attendance"><?php echo number_format($morningAttendance); ?></h6>
                                            <small class="text-success fw-medium" id="morning-change">
                                                <i class="icon-base bx bx-chevron-up icon-lg"></i>
                                                <?php echo $totalMorningStudents > 0 ? round(($morningAttendance / $totalMorningStudents) * 100, 1) : 0; ?>%
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                <div id="morningChart"></div>
                            </div>
                            <div class="tab-pane fade" id="navs-tabs-line-card-evening" role="tabpanel">
                                <div class="d-flex mb-6">
                                    <div class="avatar flex-shrink-0 me-3">
                                        <img src="<?php echo getAdminAssetUrl('img/icons/unicons/wallet.png'); ?>" alt="Evening Shift" />
                                    </div>
                                    <div>
                                        <p class="mb-0">Evening Shift Attendance</p>
                                        <div class="d-flex align-items-center">
                                            <h6 class="mb-0 me-1" id="evening-attendance"><?php echo number_format($eveningAttendance); ?></h6>
                                            <small class="text-success fw-medium" id="evening-change">
                                                <i class="icon-base bx bx-chevron-up icon-lg"></i>
                                                <?php echo $totalEveningStudents > 0 ? round(($eveningAttendance / $totalEveningStudents) * 100, 1) : 0; ?>%
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                <div id="eveningChart"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!--/ Attendance Overview -->
            
            <!-- Recent Activity -->
            <div class="col-md-6 col-lg-4 order-2 mb-6">
                <div class="card h-100">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="card-title m-0 me-2">Recent Activity</h5>
                        <div class="dropdown">
                            <button class="btn text-body-secondary p-0" type="button" id="recentActivity" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="icon-base bx bx-dots-vertical-rounded icon-lg"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="recentActivity">
                                <a class="dropdown-item" href="attendances.php">View All Activity</a>
                                <a class="dropdown-item" href="javascript:void(0);" onclick="refreshActivity()">Refresh</a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body pt-4">
                        <ul class="p-0 m-0" id="recent-activity-list">
                            <?php if (!empty($recentActivity)): ?>
                                <?php foreach ($recentActivity as $activity): ?>
                                    <li class="d-flex align-items-center mb-6">
                                        <div class="d-flex w-100 align-items-center">
                                            <div class="d-flex justify-content-between w-100">
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar avatar-sm me-3">
                                                        <span class="avatar-initial rounded bg-label-primary">
                                                            <i class="icon-base bx bx-user"></i>
                                                        </span>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-0"><?php echo htmlspecialchars($activity['student_name']); ?></h6>
                                                        <small class="text-muted"><?php echo date('M j, Y g:i A', strtotime($activity['check_in_time'])); ?></small>
                                                    </div>
                                                </div>
                                                <div class="user-progress">
                                                    <span class="badge bg-label-<?php echo $activity['status'] === 'present' ? 'success' : 'warning'; ?>">
                                                        <?php echo ucfirst($activity['status']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li class="d-flex align-items-center mb-6">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar avatar-sm me-3">
                                            <span class="avatar-initial rounded bg-label-secondary">
                                                <i class="icon-base bx bx-info-circle"></i>
                                            </span>
                                        </div>
                                        <div>
                                            <h6 class="mb-0">No recent activity</h6>
                                            <small class="text-muted">Attendance records will appear here</small>
                                        </div>
                                    </div>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
            <!--/ Recent Activity -->
        </div>
    </div>
    <!-- / Content -->
    
    <!-- Footer -->
    <footer class="content-footer footer bg-footer-theme">
        <div class="container-xxl">
            <div class="footer-container d-flex align-items-center justify-content-between py-4 flex-md-row flex-column">
                <div class="mb-2 mb-md-0">
                    &#169;
                    <script>
                        document.write(new Date().getFullYear());
                    </script>
                    , made with ❤️ by
                    <a href="#" target="_blank" class="footer-link">QR Attendance System</a>
                </div>
                <div class="d-none d-lg-inline-block">
                    <a href="#" class="footer-link me-4">Documentation</a>
                    <a href="#" class="footer-link me-4">Support</a>
                </div>
            </div>
        </div>
    </footer>
    <!-- / Footer -->
    
    <div class="content-backdrop fade"></div>
</div>
<!-- Content wrapper -->
</div>
<!-- / Layout page -->
</div>
<!-- / Layout container -->
</div>
<!-- / Layout wrapper -->

<!-- Core JS -->
<script src="<?php echo getAdminAssetUrl('vendor/libs/jquery/jquery.js'); ?>"></script>
<script src="<?php echo getAdminAssetUrl('vendor/libs/popper/popper.js'); ?>"></script>
<script src="<?php echo getAdminAssetUrl('vendor/js/bootstrap.js'); ?>"></script>
<script src="<?php echo getAdminAssetUrl('vendor/libs/perfect-scrollbar/perfect-scrollbar.js'); ?>"></script>
<script src="<?php echo getAdminAssetUrl('vendor/js/menu.js'); ?>"></script>

<!-- Vendors JS -->
<script src="<?php echo getAdminAssetUrl('vendor/libs/apex-charts/apexcharts.js'); ?>"></script>

<!-- Main JS -->
<script src="<?php echo getAdminAssetUrl('js/main.js'); ?>"></script>

<!-- Page JS -->
<script src="<?php echo getAdminAssetUrl('js/dashboards-analytics.js?v=' . time()); ?>"></script>

<script>
// BUG FIX #14: Proper logging system instead of console.log
const DEBUG_MODE = false; // Set to true for development, false for production

const logger = {
    log: function(...args) {
        if (DEBUG_MODE) console.log(...args);
    },
    info: function(...args) {
        if (DEBUG_MODE) console.info(...args);
    },
    warn: function(...args) {
        console.warn(...args); // Always show warnings
    },
    error: function(...args) {
        console.error(...args); // Always show errors
    }
};

// BUG FIX #15: Extract magic numbers to constants
const DASHBOARD_CONFIG = {
    REFRESH_INTERVAL: 5 * 60 * 1000,  // 5 minutes
    API_TIMEOUT: 10000,                // 10 seconds
    CHART_FALLBACK_DELAY: 15000,       // 15 seconds
    BASE_API_PATH: 'api/dashboard.php'
};

// Dashboard specific JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Test API connectivity first
    testAPIConnectivity();
    
    // Initialize charts with default data first
    initializeDefaultCharts();
    
    // Initialize order statistics chart
    if (typeof initializeOrderStatisticsChart === 'function') {
        initializeOrderStatisticsChart();
    }
    
    // Load dashboard data
    loadDashboardData();
    
    // Set up auto-refresh every 5 minutes - BUG FIX #19: Only refresh when tab is visible
    let refreshInterval;
    
    function startAutoRefresh() {
        refreshInterval = setInterval(() => {
            if (document.visibilityState === 'visible') {
                logger.log('Auto-refreshing dashboard data...');
                loadDashboardData();
            } else {
                logger.log('Tab is hidden, skipping refresh');
            }
        }, DASHBOARD_CONFIG.REFRESH_INTERVAL);
    }
    
    // Start auto-refresh
    startAutoRefresh();
    
    // Pause when tab is hidden, resume when visible
    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'visible') {
            logger.log('Tab visible, resuming auto-refresh');
            if (!refreshInterval) startAutoRefresh();
        }
    });
});

function testAPIConnectivity() {
    logger.log('Testing API connectivity...');
    fetch(`${DASHBOARD_CONFIG.BASE_API_PATH}?action=test`)
        .then(response => {
            logger.log('API test response status:', response.status);
            if (!response.ok) {
                logger.error('API test failed with status:', response.status);
                return response.text().then(text => {
                    logger.error('Response body:', text);
                    throw new Error(`HTTP ${response.status}: ${text}`);
                });
            }
            return response.json();
        })
        .then(data => {
            logger.log('✅ API test response:', data);
            if (data.success) {
                logger.log('✅ Dashboard API is working correctly');
            } else {
                logger.error('❌ API returned success=false:', data.message);
            }
        })
        .catch(error => {
            logger.error('❌ API connectivity test failed:', error);
            showAlert('API Connection Failed: ' + error.message, 'danger');
        });
}

function initializeDefaultCharts() {
    // Initialize attendance trends chart with empty data
    if (typeof ApexCharts !== 'undefined') {
        const chartElement = document.querySelector("#totalRevenueChart");
        if (chartElement) {
            const options = {
                series: [{
                    name: 'Attendance',
                    data: []
                }],
                chart: {
                    type: 'line',
                    height: 300,
                    toolbar: {
                        show: false
                    }
                },
                xaxis: {
                    categories: []
                },
                yaxis: {
                    title: {
                        text: 'Students'
                    }
                },
                stroke: {
                    curve: 'smooth'
                },
                noData: {
                    text: 'Loading attendance data...',
                    align: 'center',
                    verticalAlign: 'middle',
                    style: {
                        color: '#999',
                        fontSize: '14px'
                    }
                }
            };
            
            const chart = new ApexCharts(chartElement, options);
            chart.render();
            chartElement._apexChart = chart;
            
            // Set a timeout to show fallback chart if data doesn't load within 15 seconds
            setTimeout(() => {
                if (chartElement._apexChart && chartElement._apexChart.series && chartElement._apexChart.series[0] && chartElement._apexChart.series[0].data.length === 0) {
                    console.log('Chart still loading after 15 seconds, showing fallback');
                    showFallbackChart();
                }
            }, 15000);
        }
    }
}

function loadDashboardData() {
    // BUG FIX #4: Add timeout to prevent browser hanging
    const statsController = new AbortController();
    const statsTimeoutId = setTimeout(() => {
        logger.warn('Stats request timed out');
        statsController.abort();
    }, DASHBOARD_CONFIG.API_TIMEOUT);
    
    // Load stats
    fetch(`${DASHBOARD_CONFIG.BASE_API_PATH}?action=stats`, {
        signal: statsController.signal
    })
        .then(response => {
            clearTimeout(statsTimeoutId);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                updateStatsCards(data.data);
            } else {
                logger.error('API error:', data.message);
            }
        })
        .catch(error => {
            clearTimeout(statsTimeoutId);
            if (error.name === 'AbortError') {
                logger.error('Stats request was aborted due to timeout');
            } else {
                logger.error('Error loading stats:', error);
            }
            // Set default values on error
            updateStatsCards({
                totalStudents: 0,
                presentToday: 0,
                activePrograms: 0,
                totalSections: 0
            });
        });
    
    // Load charts
    loadAttendanceTrends();
    loadProgramDistribution();
    loadRecentActivity();
}

function updateStatsCards(data) {
    document.getElementById('total-students').textContent = data.totalStudents || 0;
    document.getElementById('today-attendance').textContent = data.presentToday || 0;
    document.getElementById('total-programs').textContent = data.activePrograms || 0;
    document.getElementById('total-sections').textContent = data.totalSections || 0;
    
    // Update change indicators
    updateChangeIndicator('students-change', data.students_change);
    updateChangeIndicator('attendance-change', data.attendance_change);
    updateChangeIndicator('programs-change', data.programs_change);
    updateChangeIndicator('sections-change', data.sections_change);
}

function updateChangeIndicator(elementId, change) {
    const element = document.getElementById(elementId);
    if (element && change !== undefined) {
        const isPositive = change >= 0;
        element.innerHTML = `<i class="icon-base bx bx-${isPositive ? 'up' : 'down'}-arrow-alt"></i> ${Math.abs(change)}%`;
        element.className = `text-${isPositive ? 'success' : 'danger'} fw-medium`;
    }
}

function loadAttendanceTrends() {
    logger.log('Loading attendance trends...');
    
    // Add timeout to the fetch request
    const controller = new AbortController();
    const timeoutId = setTimeout(() => {
        logger.warn('Attendance trends request timed out after', DASHBOARD_CONFIG.API_TIMEOUT, 'ms');
        controller.abort();
    }, DASHBOARD_CONFIG.API_TIMEOUT);
    
    fetch(`${DASHBOARD_CONFIG.BASE_API_PATH}?action=attendance-trends`, {
        signal: controller.signal
    })
        .then(response => {
            clearTimeout(timeoutId);
            logger.log('Attendance trends response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            logger.log('✅ Attendance trends API response:', data);
            if (data.success && data.data) {
                logger.log(`✅ Received ${data.data.length} data points`);
                // Update attendance trends chart
                if (typeof ApexCharts !== 'undefined') {
                    // Prepare chart data with proper validation
                    const chartData = Array.isArray(data.data) ? data.data : [];
                    logger.log('Chart data:', chartData);
                    
                    // Ensure we have valid data structure
                    if (chartData.length === 0) {
                        logger.warn('⚠️ No attendance data available, showing fallback chart');
                        showFallbackChart();
                        return;
                    }
                    
                    logger.log(`✅ Processing ${chartData.length} chart data points...`);
                    
                    // Safely extract categories and data
                    let categories = [];
                    let seriesData = [];
                    
                    try {
                        categories = chartData.map(item => {
                            if (item && typeof item === 'object' && item.date) {
                                return item.date;
                            }
                            return '';
                        }).filter(date => date !== '');
                        
                        seriesData = chartData.map(item => {
                            if (item && typeof item === 'object' && item.present_students !== undefined) {
                                return parseInt(item.present_students) || 0;
                            }
                            return 0;
                        });
                    } catch (error) {
                        logger.error('Error processing chart data:', error);
                        // Use default data if processing fails
                        categories = ['No Data'];
                        seriesData = [0];
                    }
                    
                    // Ensure we have at least one data point
                    if (categories.length === 0) {
                        categories = ['No Data'];
                        seriesData = [0];
                    }
                    
                    const series = [{
                        name: 'Attendance',
                        data: seriesData
                    }];
                    
                    // Debug logging
                    logger.log('Series data for chart:', series);
                    logger.log('Categories:', categories);
                    logger.log('Series data array:', seriesData);
                    
                    const chartElement = document.querySelector("#totalRevenueChart");
                    if (chartElement) {
                        // BUG FIX #5: Proper chart cleanup to prevent race conditions
                        if (chartElement._apexChart) {
                            try {
                                chartElement._apexChart.destroy();
                                chartElement._apexChart = null;
                            } catch (e) {
                                console.error('Chart destroy error:', e);
                            }
                        }
                        
                        // Create new chart with updated data
                        const options = {
                            series: series,
                            chart: {
                                type: 'line',
                                height: 300,
                                toolbar: {
                                    show: false
                                }
                            },
                            xaxis: {
                                categories: categories
                            },
                            yaxis: {
                                title: {
                                    text: 'Students'
                                }
                            },
                            stroke: {
                                curve: 'smooth'
                            },
                            dataLabels: {
                                enabled: false
                            }
                        };
                        
                        const chart = new ApexCharts(chartElement, options);
                        chart.render();
                        chartElement._apexChart = chart;
                        logger.log('✅ Chart rendered successfully with', seriesData.length, 'data points');
                    }
                }
            } else {
                logger.error('❌ API returned success=false:', data.message || 'Unknown error');
                showFallbackChart();
            }
        })
        .catch(error => {
            clearTimeout(timeoutId);
            logger.error('Error loading attendance trends:', error);
            if (error.name === 'AbortError') {
                logger.error('Request timed out');
            }
            // Show fallback chart with sample data
            showFallbackChart();
        });
}

function loadProgramDistribution() {
    // BUG FIX #4: Add timeout to prevent browser hanging
    const controller = new AbortController();
    const timeoutId = setTimeout(() => {
        logger.warn('Program distribution request timed out');
        controller.abort();
    }, DASHBOARD_CONFIG.API_TIMEOUT);
    
    fetch(`${DASHBOARD_CONFIG.BASE_API_PATH}?action=program-distribution`, {
        signal: controller.signal
    })
        .then(response => {
            clearTimeout(timeoutId);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data && data.success && data.data) {
                // Additional validation for the API response
                if (Array.isArray(data.data)) {
                    updateProgramDistribution(data.data);
                } else {
                    logger.warn('API returned non-array data for program distribution');
                    updateProgramDistribution([]);
                }
            } else {
                logger.error('API error:', data?.message || 'Unknown error');
                updateProgramDistribution([]);
            }
        })
        .catch(error => {
            clearTimeout(timeoutId);
            if (error.name === 'AbortError') {
                logger.error('Program distribution request was aborted due to timeout');
            } else {
                logger.error('Error loading program distribution:', error);
            }
            updateProgramDistribution([]);
        });
}

function updateProgramDistribution(data) {
    const listElement = document.getElementById('program-distribution-list');
    if (listElement) {
        listElement.innerHTML = '';
        
        // Enhanced data validation
        let safeData = [];
        if (Array.isArray(data)) {
            safeData = data.filter(program => {
                return program && 
                       typeof program === 'object' && 
                       program.program_name && 
                       program.student_count !== undefined;
            });
        }
        
        if (safeData.length > 0) {
            safeData.forEach(program => {
                const listItem = document.createElement('li');
                listItem.className = 'd-flex align-items-center mb-5';
                listItem.innerHTML = `
                    <div class="d-flex w-100 align-items-center">
                        <div class="d-flex justify-content-between w-100">
                            <div class="d-flex align-items-center">
                                <div class="avatar avatar-sm me-3">
                                    <span class="avatar-initial rounded bg-label-primary">
                                        <i class="icon-base bx bx-book"></i>
                                    </span>
                                </div>
                                <div>
                                    <h6 class="mb-0">${program.program_name || 'Unknown Program'}</h6>
                                    <small class="text-muted">${Number(program.student_count || 0)} students</small>
                                </div>
                            </div>
                            <div class="user-progress">
                                <small class="fw-medium">${Number(program.student_count || 0)}</small>
                            </div>
                        </div>
                    </div>
                `;
                listElement.appendChild(listItem);
            });
        } else {
            listElement.innerHTML = `
                <li class="d-flex align-items-center mb-5">
                    <div class="d-flex align-items-center">
                        <div class="avatar avatar-sm me-3">
                            <span class="avatar-initial rounded bg-label-secondary">
                                <i class="icon-base bx bx-info-circle"></i>
                            </span>
                        </div>
                        <div>
                            <h6 class="mb-0">No programs found</h6>
                            <small class="text-muted">Add programs to see distribution</small>
                        </div>
                    </div>
                </li>
            `;
        }
    }
    
    // Update the donut chart with new data - with enhanced validation
    if (typeof updateOrderStatisticsChart === 'function') {
        try {
            // Use the same validation logic as above
            let safeData = [];
            if (Array.isArray(data)) {
                safeData = data.filter(program => {
                    return program && 
                           typeof program === 'object' && 
                           program.program_name && 
                           program.student_count !== undefined;
                });
            }
            updateOrderStatisticsChart(safeData);
        } catch (error) {
            console.error('Error updating order statistics chart:', error);
        }
    }
}

function loadRecentActivity() {
    // BUG FIX #4: Add timeout to prevent browser hanging
    const controller = new AbortController();
    const timeoutId = setTimeout(() => {
        logger.warn('Recent activity request timed out');
        controller.abort();
    }, DASHBOARD_CONFIG.API_TIMEOUT);
    
    fetch(`${DASHBOARD_CONFIG.BASE_API_PATH}?action=recent-activity`, {
        signal: controller.signal
    })
        .then(response => {
            clearTimeout(timeoutId);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                updateRecentActivity(data.data);
            } else {
                logger.error('API error:', data.message);
                updateRecentActivity([]);
            }
        })
        .catch(error => {
            clearTimeout(timeoutId);
            if (error.name === 'AbortError') {
                logger.error('Recent activity request was aborted due to timeout');
            } else {
                logger.error('Error loading recent activity:', error);
            }
            updateRecentActivity([]);
        });
}

function updateRecentActivity(activities) {
    const listElement = document.getElementById('recent-activity-list');
    if (listElement) {
        listElement.innerHTML = '';
        if (activities && activities.length > 0) {
            activities.forEach(activity => {
                const listItem = document.createElement('li');
                listItem.className = 'd-flex align-items-center mb-6';
                listItem.innerHTML = `
                    <div class="d-flex w-100 align-items-center">
                        <div class="d-flex justify-content-between w-100">
                            <div class="d-flex align-items-center">
                                <div class="avatar avatar-sm me-3">
                                    <span class="avatar-initial rounded bg-label-primary">
                                        <i class="icon-base bx bx-user"></i>
                                    </span>
                                </div>
                                <div>
                                    <h6 class="mb-0">${activity.student_name || 'Unknown Student'}</h6>
                                    <small class="text-muted">${new Date(activity.check_in_time).toLocaleString()}</small>
                                </div>
                            </div>
                            <div class="user-progress">
                                <span class="badge bg-label-${activity.status === 'present' ? 'success' : 'warning'}">
                                    ${activity.status ? activity.status.charAt(0).toUpperCase() + activity.status.slice(1) : 'Unknown'}
                                </span>
                            </div>
                        </div>
                    </div>
                `;
                listElement.appendChild(listItem);
            });
        } else {
            listElement.innerHTML = `
                <li class="d-flex align-items-center mb-6">
                    <div class="d-flex align-items-center">
                        <div class="avatar avatar-sm me-3">
                            <span class="avatar-initial rounded bg-label-secondary">
                                <i class="icon-base bx bx-info-circle"></i>
                            </span>
                        </div>
                        <div>
                            <h6 class="mb-0">No recent activity</h6>
                            <small class="text-muted">Attendance records will appear here</small>
                        </div>
                    </div>
                </li>
            `;
        }
    }
}

function refreshCharts() {
    loadDashboardData();
}

function refreshActivity() {
    loadRecentActivity();
}

// Fallback showAlert function if adminUtils is not available

function setTrendPeriod(days) {
    // Update trend period and reload data
    loadAttendanceTrends();
}

function showFallbackChart() {
    logger.log('Showing fallback chart with sample data');
    if (typeof ApexCharts !== 'undefined') {
        const chartElement = document.querySelector("#totalRevenueChart");
        if (chartElement) {
            // BUG FIX #5: Proper chart instance cleanup to prevent race conditions
            if (chartElement._apexChart) {
                try {
                    chartElement._apexChart.destroy();
                    chartElement._apexChart = null;
                } catch (e) {
                    logger.error('Chart destroy error:', e);
                }
            }
            // Create sample data for the last 7 days
            const sampleData = [];
            const categories = [];
            for (let i = 6; i >= 0; i--) {
                const date = new Date();
                date.setDate(date.getDate() - i);
                categories.push(date.toISOString().split('T')[0]);
                sampleData.push(Math.floor(Math.random() * 20) + 5); // Random data between 5-25
            }
            
            const options = {
                series: [{
                    name: 'Attendance',
                    data: sampleData
                }],
                chart: {
                    type: 'line',
                    height: 300,
                    toolbar: {
                        show: false
                    }
                },
                xaxis: {
                    categories: categories
                },
                yaxis: {
                    title: {
                        text: 'Students'
                    }
                },
                stroke: {
                    curve: 'smooth'
                },
                dataLabels: {
                    enabled: false
                },
                title: {
                    text: 'Sample Data (API Unavailable)',
                    align: 'center',
                    style: {
                        fontSize: '14px',
                        color: '#666'
                    }
                }
            };
            
            const chart = new ApexCharts(chartElement, options);
            chart.render();
            chartElement._apexChart = chart;
        }
    }
}
</script>

</body>
</html>
