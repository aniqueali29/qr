<?php
/**
 * Automatic Absent Marking Cron Job
 * This script should be run via cron after check-in windows close
 * 
 * Usage examples:
 * # Run at 11:30 AM daily (after morning check-in window)
 * 30 11 * * * /usr/bin/php /path/to/qr_attendance/public/cron/mark_absent.php
 * 
 * # Run at 5:30 PM daily (after evening check-in window)  
 * 30 17 * * * /usr/bin/php /path/to/qr_attendance/public/cron/mark_absent.php
 */

// Set up error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/cron_errors.log');

// Load configuration
require_once __DIR__ . '/../includes/config.php';

// Load settings API
require_once __DIR__ . '/../admin/api/settings.php';

try {
    // Initialize database connection
    global $pdo;
    
    if (!$pdo) {
        throw new Exception("Database connection not available");
    }
    
    // Load settings
    $settingsApi = new AdminSettingsAPI();
    $allSettings = $settingsApi->getAllSettings();
    $timingSettings = [];
    
    if ($allSettings['success'] && isset($allSettings['data']['timings'])) {
        foreach ($allSettings['data']['timings'] as $setting) {
            $timingSettings[$setting['key']] = $setting['value'];
        }
    }
    
    // Get current time in configured timezone
    $timezone = new DateTimeZone($timingSettings['timezone'] ?? 'Asia/Karachi');
    $currentTime = new DateTime('now', $timezone);
    $currentDate = $currentTime->format('Y-m-d');
    $currentTimeOnly = $currentTime->format('H:i:s');
    
    // Get check-in end times for both shifts
    // All timing settings must be loaded from database
    if (empty($timingSettings['morning_checkin_end']) || empty($timingSettings['evening_checkin_end'])) {
        error_log('Timing settings not configured - cannot mark absent students');
        return;
    }
    
    $morningCheckinEnd = $timingSettings['morning_checkin_end'];
    $eveningCheckinEnd = $timingSettings['evening_checkin_end'];
    
    $markedAbsent = 0;
    $errors = [];
    $logMessage = "Cron job started at " . $currentTime->format('Y-m-d H:i:s');
    
    // Check if we should mark absent (after both check-in windows close)
    if ($currentTimeOnly > $morningCheckinEnd && $currentTimeOnly > $eveningCheckinEnd) {
        $logMessage .= " - Both check-in windows closed, proceeding with absent marking";
        
        // Get all students who don't have any attendance record for today
        $stmt = $pdo->prepare("
            SELECT s.id, s.student_id, s.name, s.program, s.shift
            FROM students s
            LEFT JOIN attendance a ON s.student_id = a.student_id AND DATE(a.timestamp) = ?
            WHERE a.student_id IS NULL
        ");
        $stmt->execute([$currentDate]);
        $absentStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $logMessage .= " - Found " . count($absentStudents) . " students without attendance records";
        
        foreach ($absentStudents as $student) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO attendance 
                    (student_id, student_name, program, shift, status, timestamp, notes, created_at) 
                    VALUES (?, ?, ?, ?, 'Absent', ?, 'Automatically marked absent by cron job after check-in window closed', NOW())
                ");
                $stmt->execute([
                    $student['student_id'],
                    $student['name'],
                    $student['program'],
                    $student['shift'],
                    $currentTime->format('Y-m-d H:i:s')
                ]);
                $markedAbsent++;
            } catch (Exception $e) {
                $errorMsg = "Failed to mark {$student['student_id']} as absent: " . $e->getMessage();
                $errors[] = $errorMsg;
                error_log($errorMsg);
            }
        }
        
        $logMessage .= " - Successfully marked {$markedAbsent} students as absent";
        
        if (!empty($errors)) {
            $logMessage .= " - Errors: " . implode('; ', $errors);
        }
        
    } else {
        $logMessage .= " - Check-in windows still open (Morning ends: {$morningCheckinEnd}, Evening ends: {$eveningCheckinEnd}), skipping absent marking";
    }
    
    // Log the result
    error_log($logMessage);
    
    // Output for cron logging
    echo $logMessage . "\n";
    
    // Exit with success code
    exit(0);
    
} catch (Exception $e) {
    $errorMsg = "Cron job error: " . $e->getMessage();
    error_log($errorMsg);
    echo $errorMsg . "\n";
    exit(1);
}
?>
