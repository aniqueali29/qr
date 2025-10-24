<?php
/**
 * D.A.E Year Progression System
 * Automatically promotes students to the next year level at the end of September
 */

require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');

try {
    // Check if it's time for year progression (after September 30th)
    $current_date = new DateTime();
    $current_month = (int)$current_date->format('n');
    $current_day = (int)$current_date->format('j');
    
    // Only run progression after September 30th
    if ($current_month < 9 || ($current_month == 9 && $current_day < 30)) {
        $response = [
            'success' => false,
            'message' => 'Year progression only runs after September 30th',
            'current_date' => $current_date->format('Y-m-d'),
            'next_progression' => date('Y-09-30')
        ];
        echo json_encode($response);
        exit;
    }
    
    // Get all active students who haven't completed the program
    $stmt = $pdo->query("
        SELECT id, student_id, name, admission_year, year_level, program, shift
        FROM students 
        WHERE is_graduated = 0 OR is_graduated IS NULL
        ORDER BY admission_year, program, shift, student_id
    ");
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $promoted_count = 0;
    $graduated_count = 0;
    $errors = [];
    
    foreach ($students as $student) {
        try {
            // Calculate years in program
            $current_year = (int)$current_date->format('Y');
            $admission_year = (int)$student['admission_year'];
            $years_in_program = $current_year - $admission_year + 1;
            
            // Determine new year level
            if ($years_in_program > 3) {
                // Student has completed the program
                $new_year_level = 'Completed';
                $is_graduated = 1;
                $graduated_count++;
            } else {
                // Promote to next year
                $new_year_level = $years_in_program . ($years_in_program == 1 ? 'st' : ($years_in_program == 2 ? 'nd' : 'rd'));
                $is_graduated = 0;
                $promoted_count++;
            }
            
            // Update student record
            $update_stmt = $pdo->prepare("
                UPDATE students 
                SET year_level = ?, is_graduated = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $update_stmt->execute([$new_year_level, $is_graduated, $student['id']]);
            
        } catch (Exception $e) {
            $errors[] = "Error updating student {$student['student_id']}: " . $e->getMessage();
        }
    }
    
    // Log the progression
    $log_entry = [
        'timestamp' => $current_date->format('Y-m-d H:i:s'),
        'promoted_count' => $promoted_count,
        'graduated_count' => $graduated_count,
        'total_processed' => count($students),
        'errors' => $errors
    ];
    
    // Save log to file
    $log_file = '../logs/year_progression_' . $current_date->format('Y-m-d') . '.json';
    file_put_contents($log_file, json_encode($log_entry, JSON_PRETTY_PRINT));
    
    $response = [
        'success' => true,
        'message' => 'Year progression completed successfully',
        'data' => [
            'promoted_count' => $promoted_count,
            'graduated_count' => $graduated_count,
            'total_processed' => count($students),
            'errors' => $errors,
            'log_file' => $log_file
        ]
    ];
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => 'Error during year progression: ' . $e->getMessage()
    ];
}

echo json_encode($response);
?>
