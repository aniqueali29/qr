<?php
/**
 * Auto-Absent Marking Cron Job
 * 
 * Marks students as absent automatically based on check-in deadlines
 * 
 * Usage (CLI):
 *   php absent.php [morning|evening]   # Optional shift filter
 * 
 * Scheduled: Daily at 9:00 PM (after all shifts end)
 * Hostinger: 0 21 * * * php /home/yourusername/public_html/qr/public/cron/absent.php
 */

require_once __DIR__ . '/../includes/config.php';

// Initialize logging
function cron_log($tag, $message) {
    $timestamp = date('Y-m-d H:i:s');
    $line = "[{$timestamp}] {$message}\n";
    echo $line;
}

// Helper function to get setting
function getSetting($pdo, $key, $default = null) {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['setting_value'] : $default;
    } catch (Exception $e) {
        cron_log('ABSENT', "Error getting setting {$key}: " . $e->getMessage());
        return $default;
    }
}

// Check if deadline has passed
function isPastDeadline($timeHHMMSS) {
    return date('H:i:s') > $timeHHMMSS;
}

// Get shifts that need processing
function getShiftsToProcess($pdo) {
    $shifts = [];
    $morning_deadline = getSetting($pdo, 'morning_checkin_end', null);
    if ($morning_deadline && isPastDeadline($morning_deadline)) {
        $shifts[] = ['key' => 'morning', 'name' => 'Morning'];
    }
    $evening_deadline = getSetting($pdo, 'evening_checkin_end', null);
    if ($evening_deadline && isPastDeadline($evening_deadline)) {
        $shifts[] = ['key' => 'evening', 'name' => 'Evening'];
    }
    return $shifts;
}

// Mark absent for students who didn't check in
function markAbsentForMissingCheckin($pdo, $shift_key, $shift_name) {
    cron_log('ABSENT', "Starting absent marking for missing check-in ({$shift_name} shift)");
    
    $deadline = getSetting($pdo, "{$shift_key}_checkin_end", null);
    if (!$deadline) {
        cron_log('ABSENT', "ERROR: No check-in deadline found for {$shift_name} shift");
        return ['success' => false, 'message' => 'No deadline configured'];
    }
    
    if (!isPastDeadline($deadline)) {
        cron_log('ABSENT', 'Current time is before check-in deadline. Skipping.');
        return ['success' => false, 'message' => 'Not past deadline yet'];
    }
    
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT student_id, roll_number, name, shift FROM students WHERE is_active = 1 AND shift = ?");
    $stmt->execute([$shift_name]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    cron_log('ABSENT', 'Found ' . count($students) . " active students in {$shift_name} shift");
    
    if (!$students) {
        return ['success' => true, 'message' => 'No students to process', 'marked_absent' => 0, 'already_marked' => 0, 'errors' => 0];
    }
    
    $marked = 0; 
    $already = 0; 
    $errors = 0;
    
    foreach ($students as $student) {
        try {
            // Check if student already has attendance record for today
            $stmt = $pdo->prepare("SELECT id, status FROM attendance WHERE student_id = ? AND DATE(timestamp) = ? LIMIT 1");
            $stmt->execute([$student['student_id'], $today]);
            $attendance = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($attendance) {
                if ($attendance['status'] === 'Absent') { 
                    $already++; 
                }
                continue;
            }
            
            // Mark absent
            $stmt = $pdo->prepare("INSERT INTO attendance (student_id, student_name, timestamp, status, shift, notes, created_at) VALUES (?, ?, ?, 'Absent', ?, 'Auto-marked absent: No check-in by deadline', NOW())");
            $check_in_time = $today . ' ' . $deadline;
            $stmt->execute([$student['student_id'], $student['name'], $check_in_time, $shift_name]);
            $marked++;
            
            cron_log('ABSENT', "Marked absent: {$student['roll_number']} - {$student['name']}");
        } catch (Exception $e) {
            $errors++;
            cron_log('ABSENT', "ERROR marking {$student['roll_number']}: " . $e->getMessage());
        }
    }
    
    cron_log('ABSENT', "Phase 1 Summary: Marked {$marked} as absent, {$already} already marked, {$errors} errors");
    return ['success' => true, 'marked_absent' => $marked, 'already_marked' => $already, 'errors' => $errors];
}

// Mark absent for students who didn't check out
function markAbsentForMissingCheckout($pdo, $shift_key, $shift_name) {
    cron_log('ABSENT', "Starting absent marking for missing check-out ({$shift_name} shift)");
    
    $deadline = getSetting($pdo, "{$shift_key}_checkout_end", null);
    if (!$deadline) {
        cron_log('ABSENT', "No checkout deadline found for {$shift_name} shift, skipping phase 2");
        return ['success' => true, 'message' => 'No checkout deadline configured', 'marked_absent' => 0, 'errors' => 0];
    }
    
    if (!isPastDeadline($deadline)) {
        cron_log('ABSENT', 'Current time is before checkout deadline. Skipping phase 2.');
        return ['success' => true, 'message' => 'Not past checkout deadline yet', 'marked_absent' => 0, 'errors' => 0];
    }
    
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("
        SELECT a.id, a.student_id, s.roll_number, a.student_name
        FROM attendance a
        INNER JOIN students s ON a.student_id = s.student_id
        WHERE DATE(a.timestamp) = ? 
        AND a.shift = ?
        AND a.status IN ('Check-in', 'Present')
        AND NOT EXISTS (
            SELECT 1 
            FROM attendance a2 
            WHERE a2.student_id = a.student_id 
            AND DATE(a2.timestamp) = ?
            AND a2.status = 'Checked-out'
            LIMIT 1
        )
    ");
    $stmt->execute([$today, $shift_name, $today]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    cron_log('ABSENT', 'Found ' . count($rows) . " students who checked in but didn't check out");
    
    $marked = 0; 
    $errors = 0;
    
    foreach ($rows as $r) {
        try {
            $stmt = $pdo->prepare("UPDATE attendance SET status = 'Absent', notes = CONCAT(COALESCE(notes, ''), ' | Auto-marked absent: No checkout by deadline'), updated_at = NOW() WHERE id = ?");
            $stmt->execute([$r['id']]);
            $marked++;
            cron_log('ABSENT', "Marked absent (no checkout): {$r['roll_number']} - {$r['student_name']}");
        } catch (Exception $e) {
            $errors++;
            cron_log('ABSENT', "ERROR marking {$r['roll_number']}: " . $e->getMessage());
        }
    }
    
    cron_log('ABSENT', "Phase 2 Summary: Marked {$marked} as absent for missing checkout, {$errors} errors");
    return ['success' => true, 'marked_absent' => $marked, 'errors' => $errors];
}

// Main execution
function main() {
    global $pdo;
    
    cron_log('ABSENT', str_repeat('=', 50));
    cron_log('ABSENT', 'Auto Absent Marking - START');
    cron_log('ABSENT', str_repeat('=', 50));
    
    // Check if enabled
    $enabled = getSetting($pdo, 'enable_auto_absent', 'false');
    if ($enabled !== 'true' && $enabled !== '1') {
        cron_log('ABSENT', 'Auto-absent marking is DISABLED in settings. Exiting.');
        exit(0);
    }
    
    // Check for shift filter
    $shiftFilter = isset($argv[1]) ? $argv[1] : null;
    
    // Determine shifts to process
    if ($shiftFilter) {
        $k = strtolower(trim($shiftFilter));
        if (!in_array($k, ['morning','evening'], true)) {
            cron_log('ABSENT', 'Invalid shift filter. Use morning or evening.');
            exit(2);
        }
        $name = ucfirst($k);
        $deadline = getSetting($pdo, "{$k}_checkin_end", null);
        $shifts = [];
        if ($deadline && isPastDeadline($deadline)) {
            $shifts[] = ['key' => $k, 'name' => $name];
        }
    } else {
        $shifts = getShiftsToProcess($pdo);
    }
    
    if (empty($shifts)) {
        cron_log('ABSENT', 'No shifts need processing at this time.');
        cron_log('ABSENT', 'Current time: ' . date('H:i:s'));
        cron_log('ABSENT', 'Morning check-in deadline: ' . getSetting($pdo, 'morning_checkin_end', 'not set'));
        cron_log('ABSENT', 'Evening check-in deadline: ' . getSetting($pdo, 'evening_checkin_end', 'not set'));
        exit(0);
    }
    
    cron_log('ABSENT', 'Found ' . count($shifts) . ' shift(s) to process: ' . implode(', ', array_column($shifts, 'name')));
    
    $grand_checkin = 0; 
    $grand_checkout = 0;
    
    foreach ($shifts as $shift) {
        $key = $shift['key']; 
        $name = $shift['name'];
        
        cron_log('ABSENT', str_repeat('-', 50));
        cron_log('ABSENT', "PROCESSING: {$name} Shift");
        
        // Phase 1: Missing check-in
        $r1 = markAbsentForMissingCheckin($pdo, $key, $name);
        if (!empty($r1['marked_absent'])) { 
            $grand_checkin += $r1['marked_absent']; 
        }
        
        // Phase 2: Missing check-out
        $r2 = markAbsentForMissingCheckout($pdo, $key, $name);
        if (!empty($r2['marked_absent'])) { 
            $grand_checkout += $r2['marked_absent']; 
        }
        
        $total = ($r1['marked_absent'] ?? 0) + ($r2['marked_absent'] ?? 0);
        cron_log('ABSENT', "{$name} Shift Total: {$total} students marked absent");
    }
    
    $grand = $grand_checkin + $grand_checkout;
    
    cron_log('ABSENT', str_repeat('=', 50));
    cron_log('ABSENT', 'GRAND TOTAL: ' . $grand . ' students marked absent');
    cron_log('ABSENT', '  - Missing check-in: ' . $grand_checkin);
    cron_log('ABSENT', '  - Missing check-out: ' . $grand_checkout);
    cron_log('ABSENT', str_repeat('=', 50));
    cron_log('ABSENT', 'Auto Absent Marking - END');
    
    exit(0);
}

// Run only if called from CLI
if (php_sapi_name() === 'cli' && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    main();
}

