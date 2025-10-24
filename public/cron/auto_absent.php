<?php
/**
 * Advanced Automatic Absent Marking System
 * This script can be run multiple times per day and will intelligently
 * mark students absent based on their shift and timing windows
 */

// Set up error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/auto_absent.log');

// Load configuration
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../admin/api/settings.php';

class AutoAbsentMarker {
    private $pdo;
    private $timingSettings;
    private $timezone;
    private $currentTime;
    private $currentDate;
    
    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        
        if (!$this->pdo) {
            throw new Exception("Database connection not available");
        }
        
        $this->loadSettings();
        $this->setCurrentTime();
    }
    
    private function loadSettings() {
        $settingsApi = new AdminSettingsAPI();
        $allSettings = $settingsApi->getAllSettings();
        $this->timingSettings = [];
        
        if ($allSettings['success'] && isset($allSettings['data']['timings'])) {
            foreach ($allSettings['data']['timings'] as $setting) {
                $this->timingSettings[$setting['key']] = $setting['value'];
            }
        }
        
        $this->timezone = new DateTimeZone($this->timingSettings['timezone'] ?? 'Asia/Karachi');
    }
    
    private function setCurrentTime() {
        $this->currentTime = new DateTime('now', $this->timezone);
        $this->currentDate = $this->currentTime->format('Y-m-d');
    }
    
    public function markAbsentForShift($shift) {
        $checkinEnd = $this->timingSettings[strtolower($shift) . '_checkin_end'] ?? 
                      ($shift === 'Morning' ? $this->timingSettings['morning_checkin_end'] : $this->timingSettings['evening_checkin_end']);
        
        $currentTimeOnly = $this->currentTime->format('H:i:s');
        
        // Only mark absent if check-in window has closed for this shift
        if ($currentTimeOnly <= $checkinEnd) {
            return [
                'success' => false,
                'message' => "Check-in window for {$shift} shift still open (ends at {$checkinEnd})",
                'marked_count' => 0
            ];
        }
        
        // Get students of this shift who don't have attendance today
        $stmt = $this->pdo->prepare("
            SELECT s.id, s.student_id, s.name, s.program, s.shift
            FROM students s
            LEFT JOIN attendance a ON s.student_id = a.student_id AND DATE(a.timestamp) = ?
            WHERE s.shift = ? AND a.student_id IS NULL
        ");
        $stmt->execute([$this->currentDate, $shift]);
        $absentStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $markedAbsent = 0;
        $errors = [];
        
        foreach ($absentStudents as $student) {
            try {
                $stmt = $this->pdo->prepare("
                    INSERT INTO attendance 
                    (student_id, student_name, program, shift, status, timestamp, notes, created_at) 
                    VALUES (?, ?, ?, ?, 'Absent', ?, 'Auto-marked absent after {$shift} check-in window closed', NOW())
                ");
                $stmt->execute([
                    $student['student_id'],
                    $student['name'],
                    $student['program'],
                    $student['shift'],
                    $this->currentTime->format('Y-m-d H:i:s')
                ]);
                $markedAbsent++;
            } catch (Exception $e) {
                $errors[] = "Failed to mark {$student['student_id']} as absent: " . $e->getMessage();
            }
        }
        
        return [
            'success' => true,
            'message' => "Marked {$markedAbsent} {$shift} shift students as absent",
            'marked_count' => $markedAbsent,
            'errors' => $errors
        ];
    }
    
    public function run() {
        $results = [];
        $totalMarked = 0;
        
        // Check both shifts
        $shifts = ['Morning', 'Evening'];
        
        foreach ($shifts as $shift) {
            $result = $this->markAbsentForShift($shift);
            $results[$shift] = $result;
            
            if ($result['success']) {
                $totalMarked += $result['marked_count'];
            }
            
            // Log the result
            $logMessage = "[{$this->currentTime->format('Y-m-d H:i:s')}] {$shift} shift: " . $result['message'];
            if (!empty($result['errors'])) {
                $logMessage .= " Errors: " . implode('; ', $result['errors']);
            }
            error_log($logMessage);
        }
        
        return [
            'success' => true,
            'message' => "Auto absent marking completed. Total marked: {$totalMarked}",
            'total_marked' => $totalMarked,
            'results' => $results,
            'timestamp' => $this->currentTime->format('Y-m-d H:i:s')
        ];
    }
}

// Run the auto absent marker
try {
    $marker = new AutoAbsentMarker();
    $result = $marker->run();
    
    echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
    exit(0);
    
} catch (Exception $e) {
    $errorMsg = "Auto absent marking failed: " . $e->getMessage();
    error_log($errorMsg);
    echo json_encode(['success' => false, 'error' => $errorMsg], JSON_PRETTY_PRINT) . "\n";
    exit(1);
}
?>
