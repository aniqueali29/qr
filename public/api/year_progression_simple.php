<?php
/**
 * Simple Year Progression API
 * No authentication required - for dashboard status
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

try {
    $current_date = new DateTime();
    $current_month = $current_date->format('n');
    $current_year = $current_date->format('Y');
    
    // Get progression status
    $last_progression = 'Never run';
    $students_needing_update = 0;
    $current_academic_year = $current_year;
    
    // Check if it's September (month 9)
    if ($current_month < 9) {
        $current_academic_year = $current_year - 1;
    }
    
    // Count students needing update
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM students 
            WHERE admission_year IS NOT NULL
            AND (last_year_update IS NULL OR last_year_update < DATE_SUB(NOW(), INTERVAL 1 YEAR))
        ");
        $stmt->execute();
        $students_needing_update = $stmt->fetchColumn();
    } catch (Exception $e) {
        // If query fails, just set to 0
        $students_needing_update = 0;
    }
    
    // Get last progression date (if available)
    try {
        $stmt = $pdo->prepare("
            SELECT MAX(last_year_update) as last_update
            FROM students 
            WHERE last_year_update IS NOT NULL
        ");
        $stmt->execute();
        $last_update = $stmt->fetchColumn();
        if ($last_update) {
            $last_progression = date('Y-m-d H:i:s', strtotime($last_update));
        }
    } catch (Exception $e) {
        // If query fails, keep default
    }
    
    echo json_encode([
        'success' => true,
        'last_progression' => $last_progression,
        'next_progression' => $current_year . '-09-30',
        'students_needing_update' => $students_needing_update,
        'current_academic_year' => $current_academic_year,
        'current_month' => $current_month,
        'progression_needed' => $current_month == 9
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error loading progression status: ' . $e->getMessage()
    ]);
}
?>
