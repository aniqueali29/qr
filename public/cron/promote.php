<?php
/**
 * Student Promotion Cron Job
 * 
 * Automatically promotes students to next semester based on elapsed time
 * 
 * Usage (CLI):
 *   php promote.php
 * 
 * Scheduled: Monthly on 1st at 3:00 AM
 * Hostinger: 0 3 1 * * php /home/yourusername/public_html/qr/public/cron/promote.php
 */

require_once __DIR__ . '/../includes/config.php';

// Initialize logging
function cron_log($tag, $message) {
    $timestamp = date('Y-m-d H:i:s');
    $line = "[{$timestamp}] {$message}\n";
    echo $line;
}

/**
 * Get academic configuration from settings
 */
function getAcademicConfig($pdo) {
    $settings = [];
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'academic_%' OR setting_key LIKE 'semester_%' OR setting_key LIKE 'max_program_%'");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    return [
        'mode' => $settings['academic_structure_mode'] ?? 'semester',
        'semesters_per_year' => (int)($settings['semesters_per_year'] ?? 2),
        'total_stages' => (int)($settings['max_program_years'] ?? 4) * (int)($settings['semesters_per_year'] ?? 2),
        'semester_names' => json_decode($settings['semester_names'] ?? '["Semester 1","Semester 2"]', true)
    ];
}

/**
 * Expected semester calculation for a student
 */
function expectedSemesterFor($student, $config) {
    if ($config['mode'] !== 'semester') {
        return null;
    }
    
    $monthsPerSemester = 12 / $config['semesters_per_year'];
    $admissionDate = new DateTime($student['admission_year'] . '-09-01'); // Assuming September start
    $now = new DateTime();
    $monthsElapsed = ($admissionDate->diff($now)->y * 12) + $admissionDate->diff($now)->m;
    $expectedSemester = min(floor($monthsElapsed / $monthsPerSemester) + 1, $config['total_stages']);
    
    return [
        'semester' => $expectedSemester,
        'year_level' => 'Year ' . ceil($expectedSemester / $config['semesters_per_year'])
    ];
}

// Main execution
function main() {
    global $pdo;
    
    cron_log('PROMOTE', str_repeat('=', 50));
    cron_log('PROMOTE', 'Student Promotion - START');
    cron_log('PROMOTE', str_repeat('=', 50));
    
    try {
        $config = getAcademicConfig($pdo);
        
        cron_log('PROMOTE', 'Academic Mode: ' . $config['mode']);
        cron_log('PROMOTE', 'Semesters per Year: ' . $config['semesters_per_year']);
        cron_log('PROMOTE', 'Total Stages: ' . $config['total_stages']);
        
        $promoted = 0;
        $graduated = 0;
        $unchanged = 0;
        
        // Get all active students
        $stmt = $pdo->query("
            SELECT id, student_id, admission_year, current_semester, year_level, is_graduated
            FROM students 
            WHERE is_active = 1 AND (is_graduated = 0 OR is_graduated IS NULL)
        ");
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        cron_log('PROMOTE', 'Found ' . count($students) . ' active students to evaluate');
        
        foreach ($students as $student) {
            try {
                $expected = expectedSemesterFor($student, $config);
                
                if (!$expected) {
                    $unchanged++;
                    continue;
                }
                
                $currentSem = (int)$student['current_semester'];
                $expectedSem = $expected['semester'];
                
                // Already at expected or ahead
                if ($currentSem >= $expectedSem) {
                    $unchanged++;
                    continue;
                }
                
                $pdo->beginTransaction();
                
                try {
                    // Check if this is the final semester
                    if ($expectedSem >= $config['total_stages']) {
                        // Graduate
                        $stmt = $pdo->prepare("
                            UPDATE students 
                            SET is_graduated = 1, updated_at = NOW() 
                            WHERE id = ?
                        ");
                        $stmt->execute([$student['id']]);
                        $graduated++;
                        cron_log('PROMOTE', "Graduated: {$student['student_id']}");
                        
                        $pdo->commit();
                        continue;
                    }
                    
                    // Promote to next semester
                    $stmt = $pdo->prepare("
                        UPDATE students 
                        SET current_semester = ?, year_level = ?, updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->execute([$expectedSem, $expected['year_level'], $student['id']]);
                    $promoted++;
                    
                    cron_log('PROMOTE', "Promoted: {$student['student_id']} to Semester {$expectedSem}");
                    
                    $pdo->commit();
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    cron_log('PROMOTE', "ERROR promoting {$student['student_id']}: " . $e->getMessage());
                }
                
            } catch (Exception $e) {
                cron_log('PROMOTE', "ERROR processing {$student['student_id']}: " . $e->getMessage());
            }
        }
        
        cron_log('PROMOTE', str_repeat('=', 50));
        cron_log('PROMOTE', 'Promotion Summary:');
        cron_log('PROMOTE', '  - Promoted: ' . $promoted);
        cron_log('PROMOTE', '  - Graduated: ' . $graduated);
        cron_log('PROMOTE', '  - Unchanged: ' . $unchanged);
        cron_log('PROMOTE', str_repeat('=', 50));
        cron_log('PROMOTE', 'Student Promotion - END');
        
        exit(0);
        
    } catch (Exception $e) {
        cron_log('PROMOTE', 'FATAL: ' . $e->getMessage());
        exit(1);
    }
}

// Run only if called from CLI
if (php_sapi_name() === 'cli' && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    main();
}

