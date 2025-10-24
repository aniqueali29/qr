<?php
/**
 * Student Quizzes Page
 * Active and past quizzes, results overview
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

// Mock quizzes data (in a real system, this would come from the database)
$quizzes = [
    [
        'id' => 1,
        'title' => 'Web Development Fundamentals',
        'description' => 'Test your knowledge of HTML, CSS, and JavaScript basics',
        'subject' => 'Web Development',
        'duration' => 30, // minutes
        'total_questions' => 20,
        'max_score' => 100,
        'status' => 'available',
        'start_date' => '2024-01-10',
        'end_date' => '2024-01-25',
        'attempts_allowed' => 3,
        'attempts_used' => 0,
        'best_score' => null,
        'last_attempt' => null,
        'questions' => [
            'What does HTML stand for?',
            'Which CSS property is used to change text color?',
            'What is the correct way to declare a variable in JavaScript?'
        ]
    ],
    [
        'id' => 2,
        'title' => 'Database Concepts Quiz',
        'description' => 'Evaluate your understanding of database design and SQL',
        'subject' => 'Database Systems',
        'duration' => 45,
        'total_questions' => 25,
        'max_score' => 100,
        'status' => 'completed',
        'start_date' => '2024-01-05',
        'end_date' => '2024-01-20',
        'attempts_allowed' => 2,
        'attempts_used' => 2,
        'best_score' => 85,
        'last_attempt' => '2024-01-18',
        'questions' => [
            'What is a primary key?',
            'Which SQL command is used to retrieve data?',
            'What is normalization in database design?'
        ]
    ],
    [
        'id' => 3,
        'title' => 'Programming Logic Test',
        'description' => 'Assess your programming logic and problem-solving skills',
        'subject' => 'Programming',
        'duration' => 60,
        'total_questions' => 30,
        'max_score' => 100,
        'status' => 'in_progress',
        'start_date' => '2024-01-15',
        'end_date' => '2024-01-30',
        'attempts_allowed' => 1,
        'attempts_used' => 1,
        'best_score' => null,
        'last_attempt' => '2024-01-20',
        'questions' => [
            'What is the time complexity of bubble sort?',
            'Explain the concept of recursion',
            'What is the difference between stack and queue?'
        ]
    ],
    [
        'id' => 4,
        'title' => 'Network Security Assessment',
        'description' => 'Test your knowledge of network security principles',
        'subject' => 'Network Security',
        'duration' => 40,
        'total_questions' => 20,
        'max_score' => 100,
        'status' => 'upcoming',
        'start_date' => '2024-02-01',
        'end_date' => '2024-02-15',
        'attempts_allowed' => 2,
        'attempts_used' => 0,
        'best_score' => null,
        'last_attempt' => null,
        'questions' => [
            'What is the purpose of a firewall?',
            'Explain the difference between symmetric and asymmetric encryption',
            'What is a DDoS attack?'
        ]
    ]
];

$pageTitle = "Quizzes - " . $current_student['name'];
$currentPage = "quizzes";

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
                            <span class="text-muted fw-light">Student /</span> Quizzes
                        </h4>

                        <!-- Quiz Statistics -->
                        <div class="row mb-4">
                            <div class="col-lg-3 col-md-6 col-6 mb-4">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="card-title d-flex align-items-start justify-content-between">
                                            <div class="avatar flex-shrink-0">
                                                <i class="bx bx-edit text-primary"></i>
                                            </div>
                                        </div>
                                        <span class="fw-semibold d-block mb-1">Total Quizzes</span>
                                        <h3 class="card-title mb-2"><?php echo count($quizzes); ?></h3>
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
                                        <span class="fw-semibold d-block mb-1">Completed</span>
                                        <h3 class="card-title mb-2"><?php echo count(array_filter($quizzes, function($q) { return $q['status'] === 'completed'; })); ?></h3>
                                        <small class="text-success fw-semibold">
                                            <i class="bx bx-up-arrow-alt"></i> Finished
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6 col-6 mb-4">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="card-title d-flex align-items-start justify-content-between">
                                            <div class="avatar flex-shrink-0">
                                                <i class="bx bx-play-circle text-info"></i>
                                            </div>
                                        </div>
                                        <span class="fw-semibold d-block mb-1">Available</span>
                                        <h3 class="card-title mb-2"><?php echo count(array_filter($quizzes, function($q) { return $q['status'] === 'available'; })); ?></h3>
                                        <small class="text-info fw-semibold">
                                            <i class="bx bx-play"></i> Ready to take
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6 col-6 mb-4">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="card-title d-flex align-items-start justify-content-between">
                                            <div class="avatar flex-shrink-0">
                                                <i class="bx bx-star text-warning"></i>
                                            </div>
                                        </div>
                                        <span class="fw-semibold d-block mb-1">Average Score</span>
                                        <h3 class="card-title mb-2"><?php 
                                            $completed_quizzes = array_filter($quizzes, function($q) { return $q['best_score'] !== null; });
                                            $avg_score = count($completed_quizzes) > 0 ? array_sum(array_column($completed_quizzes, 'best_score')) / count($completed_quizzes) : 0;
                                            echo number_format($avg_score, 1);
                                        ?>%</h3>
                                        <small class="text-warning fw-semibold">
                                            <i class="bx bx-trending-up"></i> Performance
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Quizzes List -->
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title m-0 me-2">Quiz List</h5>
                                <div class="dropdown">
                                    <button class="btn p-0" type="button" id="quizFilter" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <i class="bx bx-dots-vertical-rounded"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end" aria-labelledby="quizFilter">
                                        <a class="dropdown-item" href="javascript:void(0);">All Quizzes</a>
                                        <a class="dropdown-item" href="javascript:void(0);">Available</a>
                                        <a class="dropdown-item" href="javascript:void(0);">Completed</a>
                                        <a class="dropdown-item" href="javascript:void(0);">In Progress</a>
                                        <a class="dropdown-item" href="javascript:void(0);">Upcoming</a>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php foreach ($quizzes as $quiz): ?>
                                        <div class="col-md-6 col-lg-4 mb-4">
                                            <div class="card h-100">
                                                <div class="card-header d-flex justify-content-between align-items-center">
                                                    <h6 class="card-title mb-0"><?php echo sanitizeOutput($quiz['title']); ?></h6>
                                                    <span class="badge bg-label-<?php 
                                                        echo $quiz['status'] === 'completed' ? 'success' : 
                                                            ($quiz['status'] === 'available' ? 'info' : 
                                                            ($quiz['status'] === 'in_progress' ? 'warning' : 'secondary')); 
                                                    ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $quiz['status'])); ?>
                                                    </span>
                                                </div>
                                                <div class="card-body">
                                                    <p class="card-text"><?php echo sanitizeOutput($quiz['description']); ?></p>
                                                    <div class="mb-3">
                                                        <small class="text-muted">
                                                            <i class="bx bx-book me-1"></i>
                                                            <?php echo sanitizeOutput($quiz['subject']); ?>
                                                        </small>
                                                    </div>
                                                    <div class="mb-3">
                                                        <small class="text-muted">
                                                            <i class="bx bx-time me-1"></i>
                                                            Duration: <?php echo $quiz['duration']; ?> minutes
                                                        </small>
                                                    </div>
                                                    <div class="mb-3">
                                                        <small class="text-muted">
                                                            <i class="bx bx-help-circle me-1"></i>
                                                            Questions: <?php echo $quiz['total_questions']; ?>
                                                        </small>
                                                    </div>
                                                    <div class="mb-3">
                                                        <small class="text-muted">
                                                            <i class="bx bx-calendar me-1"></i>
                                                            Available: <?php echo date('M d', strtotime($quiz['start_date'])); ?> - <?php echo date('M d', strtotime($quiz['end_date'])); ?>
                                                        </small>
                                                    </div>
                                                    <?php if ($quiz['best_score'] !== null): ?>
                                                        <div class="mb-3">
                                                            <small class="text-success">
                                                                <i class="bx bx-star me-1"></i>
                                                                Best Score: <?php echo $quiz['best_score']; ?>/<?php echo $quiz['max_score']; ?>
                                                            </small>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="mb-3">
                                                        <small class="text-muted">
                                                            <i class="bx bx-refresh me-1"></i>
                                                            Attempts: <?php echo $quiz['attempts_used']; ?>/<?php echo $quiz['attempts_allowed']; ?>
                                                        </small>
                                                    </div>
                                                </div>
                                                <div class="card-footer">
                                                    <div class="d-flex gap-2">
                                                        <button class="btn btn-outline-primary btn-sm flex-fill" data-bs-toggle="modal" data-bs-target="#quizModal<?php echo $quiz['id']; ?>">
                                                            <i class="bx bx-show me-1"></i>View Details
                                                        </button>
                                                        <?php if ($quiz['status'] === 'available' && $quiz['attempts_used'] < $quiz['attempts_allowed']): ?>
                                                            <button class="btn btn-primary btn-sm flex-fill">
                                                                <i class="bx bx-play me-1"></i>Start Quiz
                                                            </button>
                                                        <?php elseif ($quiz['status'] === 'in_progress'): ?>
                                                            <button class="btn btn-warning btn-sm flex-fill">
                                                                <i class="bx bx-play me-1"></i>Continue
                                                            </button>
                                                        <?php elseif ($quiz['status'] === 'completed'): ?>
                                                            <button class="btn btn-success btn-sm flex-fill">
                                                                <i class="bx bx-check me-1"></i>View Results
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Quiz Details Modal -->
                                        <div class="modal fade" id="quizModal<?php echo $quiz['id']; ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title"><?php echo sanitizeOutput($quiz['title']); ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <h6>Subject</h6>
                                                                <p><?php echo sanitizeOutput($quiz['subject']); ?></p>
                                                                
                                                                <h6>Duration</h6>
                                                                <p><?php echo $quiz['duration']; ?> minutes</p>
                                                                
                                                                <h6>Total Questions</h6>
                                                                <p><?php echo $quiz['total_questions']; ?></p>
                                                                
                                                                <h6>Max Score</h6>
                                                                <p><?php echo $quiz['max_score']; ?> points</p>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <h6>Status</h6>
                                                                <p>
                                                                    <span class="badge bg-label-<?php 
                                                                        echo $quiz['status'] === 'completed' ? 'success' : 
                                                                            ($quiz['status'] === 'available' ? 'info' : 
                                                                            ($quiz['status'] === 'in_progress' ? 'warning' : 'secondary')); 
                                                                    ?>">
                                                                        <?php echo ucfirst(str_replace('_', ' ', $quiz['status'])); ?>
                                                                    </span>
                                                                </p>
                                                                
                                                                <h6>Available Period</h6>
                                                                <p><?php echo date('M d, Y', strtotime($quiz['start_date'])); ?> - <?php echo date('M d, Y', strtotime($quiz['end_date'])); ?></p>
                                                                
                                                                <h6>Attempts</h6>
                                                                <p><?php echo $quiz['attempts_used']; ?>/<?php echo $quiz['attempts_allowed']; ?> used</p>
                                                                
                                                                <?php if ($quiz['best_score'] !== null): ?>
                                                                    <h6>Best Score</h6>
                                                                    <p><?php echo $quiz['best_score']; ?>/<?php echo $quiz['max_score']; ?> (<?php echo number_format(($quiz['best_score'] / $quiz['max_score']) * 100, 1); ?>%)</p>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        
                                                        <h6>Description</h6>
                                                        <p><?php echo sanitizeOutput($quiz['description']); ?></p>
                                                        
                                                        <h6>Sample Questions</h6>
                                                        <ul>
                                                            <?php foreach (array_slice($quiz['questions'], 0, 3) as $question): ?>
                                                                <li><?php echo sanitizeOutput($question); ?></li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                                                        <?php if ($quiz['status'] === 'available' && $quiz['attempts_used'] < $quiz['attempts_allowed']): ?>
                                                            <button type="button" class="btn btn-primary">Start Quiz</button>
                                                        <?php elseif ($quiz['status'] === 'in_progress'): ?>
                                                            <button type="button" class="btn btn-warning">Continue Quiz</button>
                                                        <?php elseif ($quiz['status'] === 'completed'): ?>
                                                            <button type="button" class="btn btn-success">View Results</button>
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
