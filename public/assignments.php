<?php
/**
 * Student Assignments Page
 * Assignment list, submissions, and deadlines
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

// Mock assignments data (in a real system, this would come from the database)
$assignments = [
    [
        'id' => 1,
        'title' => 'Web Development Project',
        'description' => 'Create a responsive website using HTML, CSS, and JavaScript',
        'due_date' => '2024-01-15',
        'status' => 'pending',
        'submitted_date' => null,
        'grade' => null,
        'max_grade' => 100,
        'subject' => 'Web Development',
        'instructions' => 'Submit a complete website with at least 5 pages, responsive design, and interactive features.'
    ],
    [
        'id' => 2,
        'title' => 'Database Design Assignment',
        'description' => 'Design a database schema for a library management system',
        'due_date' => '2024-01-20',
        'status' => 'submitted',
        'submitted_date' => '2024-01-18',
        'grade' => 85,
        'max_grade' => 100,
        'subject' => 'Database Systems',
        'instructions' => 'Create ER diagrams and normalized database schema with proper relationships.'
    ],
    [
        'id' => 3,
        'title' => 'Programming Exercise',
        'description' => 'Implement sorting algorithms in Python',
        'due_date' => '2024-01-25',
        'status' => 'overdue',
        'submitted_date' => null,
        'grade' => null,
        'max_grade' => 50,
        'subject' => 'Programming',
        'instructions' => 'Implement bubble sort, quick sort, and merge sort with time complexity analysis.'
    ],
    [
        'id' => 4,
        'title' => 'Research Paper',
        'description' => 'Write a research paper on Artificial Intelligence trends',
        'due_date' => '2024-02-01',
        'status' => 'pending',
        'submitted_date' => null,
        'grade' => null,
        'max_grade' => 100,
        'subject' => 'Research Methods',
        'instructions' => 'Minimum 2000 words with proper citations and references.'
    ]
];

$pageTitle = "Assignments - " . $current_student['name'];
$currentPage = "assignments";

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
                        <h4 class="fw-bold py-3 mb-4">
                            <span class="text-muted fw-light">Student /</span> Assignments
                        </h4>

                        <!-- Assignment Statistics -->
                        <div class="row mb-4">
                            <div class="col-lg-3 col-md-6 col-6 mb-4">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="card-title d-flex align-items-start justify-content-between">
                                            <div class="avatar flex-shrink-0">
                                                <i class="bx bx-book text-primary"></i>
                                            </div>
                                        </div>
                                        <span class="fw-semibold d-block mb-1">Total Assignments</span>
                                        <h3 class="card-title mb-2"><?php echo count($assignments); ?></h3>
                                        <small class="text-muted fw-semibold">
                                            <i class="bx bx-info-circle"></i> All time
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6 col-6 mb-4">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="card-title d-flex align-items-start justify-content-between">
                                            <div class="avatar flex-shrink-0">
                                                <i class="bx bx-check-circle text-success"></i>
                                            </div>
                                        </div>
                                        <span class="fw-semibold d-block mb-1">Submitted</span>
                                        <h3 class="card-title mb-2"><?php echo count(array_filter($assignments, function($a) { return $a['status'] === 'submitted'; })); ?></h3>
                                        <small class="text-success fw-semibold">
                                            <i class="bx bx-up-arrow-alt"></i> Completed
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6 col-6 mb-4">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="card-title d-flex align-items-start justify-content-between">
                                            <div class="avatar flex-shrink-0">
                                                <i class="bx bx-time text-warning"></i>
                                            </div>
                                        </div>
                                        <span class="fw-semibold d-block mb-1">Pending</span>
                                        <h3 class="card-title mb-2"><?php echo count(array_filter($assignments, function($a) { return $a['status'] === 'pending'; })); ?></h3>
                                        <small class="text-warning fw-semibold">
                                            <i class="bx bx-clock"></i> In progress
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6 col-6 mb-4">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="card-title d-flex align-items-start justify-content-between">
                                            <div class="avatar flex-shrink-0">
                                                <i class="bx bx-x-circle text-danger"></i>
                                            </div>
                                        </div>
                                        <span class="fw-semibold d-block mb-1">Overdue</span>
                                        <h3 class="card-title mb-2"><?php echo count(array_filter($assignments, function($a) { return $a['status'] === 'overdue'; })); ?></h3>
                                        <small class="text-danger fw-semibold">
                                            <i class="bx bx-down-arrow-alt"></i> Late
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Assignments List -->
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title m-0 me-2">Assignment List</h5>
                                <div class="dropdown">
                                    <button class="btn p-0" type="button" id="assignmentFilter" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <i class="bx bx-dots-vertical-rounded"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end" aria-labelledby="assignmentFilter">
                                        <a class="dropdown-item" href="javascript:void(0);">All Assignments</a>
                                        <a class="dropdown-item" href="javascript:void(0);">Pending</a>
                                        <a class="dropdown-item" href="javascript:void(0);">Submitted</a>
                                        <a class="dropdown-item" href="javascript:void(0);">Overdue</a>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php foreach ($assignments as $assignment): ?>
                                        <div class="col-md-6 col-lg-4 mb-4">
                                            <div class="card h-100">
                                                <div class="card-header d-flex justify-content-between align-items-center">
                                                    <h6 class="card-title mb-0"><?php echo sanitizeOutput($assignment['title']); ?></h6>
                                                    <span class="badge bg-label-<?php echo $assignment['status'] === 'submitted' ? 'success' : ($assignment['status'] === 'overdue' ? 'danger' : 'warning'); ?>">
                                                        <?php echo ucfirst($assignment['status']); ?>
                                                    </span>
                                                </div>
                                                <div class="card-body">
                                                    <p class="card-text"><?php echo sanitizeOutput($assignment['description']); ?></p>
                                                    <div class="mb-3">
                                                        <small class="text-muted">
                                                            <i class="bx bx-book me-1"></i>
                                                            <?php echo sanitizeOutput($assignment['subject']); ?>
                                                        </small>
                                                    </div>
                                                    <div class="mb-3">
                                                        <small class="text-muted">
                                                            <i class="bx bx-calendar me-1"></i>
                                                            Due: <?php echo date('M d, Y', strtotime($assignment['due_date'])); ?>
                                                        </small>
                                                    </div>
                                                    <?php if ($assignment['submitted_date']): ?>
                                                        <div class="mb-3">
                                                            <small class="text-success">
                                                                <i class="bx bx-check me-1"></i>
                                                                Submitted: <?php echo date('M d, Y', strtotime($assignment['submitted_date'])); ?>
                                                            </small>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($assignment['grade'] !== null): ?>
                                                        <div class="mb-3">
                                                            <small class="text-info">
                                                                <i class="bx bx-star me-1"></i>
                                                                Grade: <?php echo $assignment['grade']; ?>/<?php echo $assignment['max_grade']; ?>
                                                            </small>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="card-footer">
                                                    <div class="d-flex gap-2">
                                                        <button class="btn btn-outline-primary btn-sm flex-fill" data-bs-toggle="modal" data-bs-target="#assignmentModal<?php echo $assignment['id']; ?>">
                                                            <i class="bx bx-show me-1"></i>View Details
                                                        </button>
                                                        <?php if ($assignment['status'] !== 'submitted'): ?>
                                                            <button class="btn btn-primary btn-sm flex-fill">
                                                                <i class="bx bx-upload me-1"></i>Submit
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Assignment Details Modal -->
                                        <div class="modal fade" id="assignmentModal<?php echo $assignment['id']; ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title"><?php echo sanitizeOutput($assignment['title']); ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <h6>Subject</h6>
                                                                <p><?php echo sanitizeOutput($assignment['subject']); ?></p>
                                                                
                                                                <h6>Due Date</h6>
                                                                <p><?php echo date('M d, Y', strtotime($assignment['due_date'])); ?></p>
                                                                
                                                                <h6>Max Grade</h6>
                                                                <p><?php echo $assignment['max_grade']; ?> points</p>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <h6>Status</h6>
                                                                <p>
                                                                    <span class="badge bg-label-<?php echo $assignment['status'] === 'submitted' ? 'success' : ($assignment['status'] === 'overdue' ? 'danger' : 'warning'); ?>">
                                                                        <?php echo ucfirst($assignment['status']); ?>
                                                                    </span>
                                                                </p>
                                                                
                                                                <?php if ($assignment['submitted_date']): ?>
                                                                    <h6>Submitted Date</h6>
                                                                    <p><?php echo date('M d, Y', strtotime($assignment['submitted_date'])); ?></p>
                                                                <?php endif; ?>
                                                                
                                                                <?php if ($assignment['grade'] !== null): ?>
                                                                    <h6>Grade</h6>
                                                                    <p><?php echo $assignment['grade']; ?>/<?php echo $assignment['max_grade']; ?></p>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        
                                                        <h6>Description</h6>
                                                        <p><?php echo sanitizeOutput($assignment['description']); ?></p>
                                                        
                                                        <h6>Instructions</h6>
                                                        <p><?php echo sanitizeOutput($assignment['instructions']); ?></p>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                                                        <?php if ($assignment['status'] !== 'submitted'): ?>
                                                            <button type="button" class="btn btn-primary">Submit Assignment</button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- / Content -->

<?php
// Include footer
include 'includes/partials/footer.php';
?>
