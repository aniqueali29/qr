<?php
/**
 * Year Progression System for D.A.E Students
 * Automatically updates student year levels every September
 * Can be run manually or via scheduled task
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'config.php';
require_once 'auth_system.php';

// Check if this is a manual trigger (requires admin auth)
$manual_trigger = isset($_GET['manual']) && $_GET['manual'] === 'true';
if ($manual_trigger) {
    if (!isLoggedIn() || !hasRole('admin')) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Authentication required for manual trigger']);
        exit();
    }
}

try {
    $current_date = new DateTime();
    $current_month = $current_date->format('n');
    $current_year = $current_date->format('Y');
    
    // Check if it's September (month 9) or manual trigger
    if ($current_month != 9 && !$manual_trigger) {
        echo json_encode([
            'success' => true,
            'message' => 'Year progression not needed',
            'current_month' => $current_month,
            'progression_month' => 9,
            'next_progression' => $current_year . '-09-30'
        ]);
        exit();
    }
    
    // Get all students with admission_year
    $stmt = $pdo->prepare("
        SELECT s.id, s.student_id, s.name, s.admission_year, s.year_level, s.program, s.shift
        FROM students s 
        WHERE s.admission_year IS NOT NULL 
        AND s.admission_year > 0
        ORDER BY s.admission_year, s.student_id
    ");
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($students)) {
        echo json_encode([
            'success' => true,
            'message' => 'No students found with admission year data',
            'total_students' => 0,
            'updated_students' => 0,
            'graduated_students' => 0
        ]);
        exit();
    }
    
    $updated_students = [];
    $graduated_students = [];
    $errors = [];
    
    // Calculate academic year
    $academic_year = $current_year;
    if ($current_month < 9) {
        $academic_year--;
    }
    
    foreach ($students as $student) {
        try {
            $admission_year = intval($student['admission_year']);
            $years_in_program = $academic_year - $admission_year + 1;
            
            // Calculate new year level
            $new_year_level = min(max($years_in_program, 1), 3);
            $is_graduated = $years_in_program > 3;
            
            // Determine status
            $status = $is_graduated ? 'Completed' : $new_year_level . 'st';
            
            // Update student record
            $stmt = $pdo->prepare("
                UPDATE students 
                SET year_level = ?, 
                    last_year_update = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$status, $student['id']]);
            
            $updated_students[] = [
                'student_id' => $student['student_id'],
                'name' => $student['name'],
                'admission_year' => $admission_year,
                'old_year_level' => $student['year_level'],
                'new_year_level' => $status,
                'years_in_program' => $years_in_program,
                'is_graduated' => $is_graduated
            ];
            
            if ($is_graduated) {
                $graduated_students[] = $student['student_id'];
            }
            
        } catch (Exception $e) {
            $errors[] = [
                'student_id' => $student['student_id'],
                'name' => $student['name'],
                'error' => $e->getMessage()
            ];
        }
    }
    
    // Update section student counts
    updateSectionStudentCounts($pdo);
    
    // Log progression
    $log_data = [
        'date' => $current_date->format('Y-m-d H:i:s'),
        'academic_year' => $academic_year,
        'total_students' => count($students),
        'updated_students' => count($updated_students),
        'graduated_students' => count($graduated_students),
        'errors' => count($errors),
        'details' => [
            'updated' => $updated_students,
            'graduated' => $graduated_students,
            'errors' => $errors
        ]
    ];
    
    // Save progression log
    $log_file = "logs/year_progression_" . $current_date->format('Y-m') . ".json";
    if (!is_dir('logs')) {
        mkdir('logs', 0755, true);
    }
    file_put_contents($log_file, json_encode($log_data, JSON_PRETTY_PRINT));
    
    // Removed legacy offline sync (Python-based)
    
    echo json_encode([
        'success' => true,
        'message' => 'Year progression completed successfully',
        'academic_year' => $academic_year,
        'total_students' => count($students),
        'updated_students' => count($updated_students),
        'graduated_students' => count($graduated_students),
        'errors' => count($errors),
        'log_file' => $log_file,
        'details' => [
            'updated' => $updated_students,
            'graduated' => $graduated_students,
            'errors' => $errors
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Year progression failed: ' . $e->getMessage()
    ]);
}

/**
 * Update section student counts
 */
function updateSectionStudentCounts($pdo) {
    try {
        // Get all sections
        $stmt = $pdo->prepare("
            SELECT sec.id, sec.program_id, sec.year_level, sec.shift, sec.section_name
            FROM sections sec
            WHERE sec.is_active = TRUE
        ");
        $stmt->execute();
        $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($sections as $section) {
            // Count students in this section
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as student_count
                FROM students s
                WHERE s.section_id = ? AND s.year_level = ?
            ");
            $stmt->execute([$section['id'], $section['year_level']]);
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['student_count'];
            
            // Update section count
            $stmt = $pdo->prepare("
                UPDATE sections 
                SET current_students = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$count, $section['id']]);
        }
        
    } catch (Exception $e) {
        error_log("Error updating section counts: " . $e->getMessage());
    }
}

// Removed legacy syncToOfflineSystem: Python integration removed
?>
