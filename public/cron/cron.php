<?php
/**
 * Unified Cron Entrypoint for QR Attendance System
 *
 * Usage (CLI only):
 *   php cron.php absent [morning|evening]   # auto-absent (both phases). Optional shift filter.
 *   php cron.php cleanup                    # log cleanup
 *   php cron.php backup                     # database backup (respects settings)
 *   php cron.php all                        # run all (each respects its own conditions)
 */

// Common includes
require_once __DIR__ . '/../includes/config.php';

// -----------------------------------------------------------------------------
// Utilities
// -----------------------------------------------------------------------------
function cron_log($tag, $message) {
    $timestamp = date('Y-m-d H:i:s');
    $line = "[{$timestamp}] {$message}\n";
    echo $line;
    error_log("[{$tag}] {$message}");
}

function getSetting($pdo, $key, $default = null) {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['setting_value'] : $default;
    } catch (Exception $e) {
        cron_log('CRON', "Error getting setting {$key}: " . $e->getMessage());
        return $default;
    }
}

function isPastDeadline($timeHHMMSS) {
    return date('H:i:s') > $timeHHMMSS;
}

// -----------------------------------------------------------------------------
// Auto Absent (from mark_absent.php)
// -----------------------------------------------------------------------------
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

function absent_mark_for_missing_checkin($pdo, $shift_key, $shift_name) {
    cron_log('CRON_ABSENT', "Starting absent marking for missing check-in ({$shift_name} shift)");
    $deadline = getSetting($pdo, "{$shift_key}_checkin_end", null);
    if (!$deadline) {
        cron_log('CRON_ABSENT', "ERROR: No check-in deadline found for {$shift_name} shift");
        return ['success' => false, 'message' => 'No deadline configured'];
    }
    if (!isPastDeadline($deadline)) {
        cron_log('CRON_ABSENT', 'Current time is before check-in deadline. Skipping.');
        return ['success' => false, 'message' => 'Not past deadline yet'];
    }
    $today = date('Y-m-d');
    $stmt = $pdo->prepare(
        "SELECT student_id, roll_number, name, shift FROM students WHERE is_active = 1 AND shift = ?"
    );
    $stmt->execute([$shift_name]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    cron_log('CRON_ABSENT', 'Found ' . count($students) . " active students in {$shift_name} shift");
    if (!$students) {
        return ['success' => true, 'message' => 'No students to process', 'marked_absent' => 0, 'already_marked' => 0, 'errors' => 0];
    }
    $marked = 0; $already = 0; $errors = 0;
    foreach ($students as $student) {
        try {
            $stmt = $pdo->prepare(
                "SELECT id, status FROM attendance WHERE student_id = ? AND DATE(timestamp) = ? LIMIT 1"
            );
            $stmt->execute([$student['student_id'], $today]);
            $attendance = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($attendance) {
                if ($attendance['status'] === 'Absent') { $already++; }
                continue;
            }
            $ins = $pdo->prepare(
                "INSERT INTO attendance (student_id, student_name, timestamp, status, shift, notes, created_at) VALUES (?, ?, ?, 'Absent', ?, 'Auto-marked absent by system', NOW())"
            );
            $check_in_time = $today . ' ' . $deadline;
            $ins->execute([$student['student_id'], $student['name'], $check_in_time, $shift_name]);
            $marked++;
            cron_log('CRON_ABSENT', "Marked absent: {$student['roll_number']} - {$student['name']}");
        } catch (Exception $e) {
            $errors++;
            cron_log('CRON_ABSENT', "ERROR marking {$student['roll_number']}: " . $e->getMessage());
        }
    }
    cron_log('CRON_ABSENT', "Summary: Marked {$marked} as absent, {$already} already marked, {$errors} errors");
    return ['success' => true, 'message' => "Processed {$shift_name} shift check-in", 'marked_absent' => $marked, 'already_marked' => $already, 'errors' => $errors];
}

function absent_mark_for_missing_checkout($pdo, $shift_key, $shift_name) {
    cron_log('CRON_ABSENT', "Starting absent marking for missing check-out ({$shift_name} shift)");
    $deadline = getSetting($pdo, "{$shift_key}_checkout_end", null);
    if (!$deadline) {
        cron_log('CRON_ABSENT', "No check-out deadline configured for {$shift_name} shift. Skipping.");
        return ['success' => true, 'message' => 'No checkout deadline configured', 'marked_absent' => 0, 'errors' => 0];
    }
    if (!isPastDeadline($deadline)) {
        cron_log('CRON_ABSENT', 'Current time is before check-out deadline. Skipping.');
        return ['success' => false, 'message' => 'Not past checkout deadline yet'];
    }
    $today = date('Y-m-d');
    $stmt = $pdo->prepare(
        "SELECT a.id, a.student_id, a.student_name, s.roll_number FROM attendance a INNER JOIN students s ON a.student_id = s.student_id WHERE DATE(a.timestamp) = ? AND a.shift = ? AND a.check_in_time IS NOT NULL AND a.check_out_time IS NULL AND a.status != 'Absent'"
    );
    $stmt->execute([$today, $shift_name]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    cron_log('CRON_ABSENT', 'Found ' . count($rows) . ' students who checked in but did not check out');
    if (!$rows) { return ['success' => true, 'message' => 'No students to process for checkout', 'marked_absent' => 0, 'errors' => 0]; }
    $marked = 0; $errors = 0;
    foreach ($rows as $r) {
        try {
            $upd = $pdo->prepare(
                "UPDATE attendance SET status = 'Absent', notes = CONCAT(COALESCE(notes, ''), ' | Auto-marked absent: No checkout by deadline'), updated_at = NOW() WHERE id = ?"
            );
            $upd->execute([$r['id']]);
            $marked++;
            cron_log('CRON_ABSENT', "Marked absent (no checkout): {$r['roll_number']} - {$r['student_name']}");
        } catch (Exception $e) {
            $errors++;
            cron_log('CRON_ABSENT', "ERROR marking {$r['roll_number']}: " . $e->getMessage());
        }
    }
    cron_log('CRON_ABSENT', "Summary: Marked {$marked} as absent for missing checkout, {$errors} errors");
    return ['success' => true, 'message' => "Processed {$shift_name} shift checkout", 'marked_absent' => $marked, 'errors' => $errors];
}

function run_absent($pdo, $shiftFilter = null) {
    cron_log('CRON_ABSENT', str_repeat('=', 40));
    cron_log('CRON_ABSENT', 'Auto Absent Marking - START');
    cron_log('CRON_ABSENT', str_repeat('=', 40));

    $enabled = getSetting($pdo, 'enable_auto_absent', 'false');
    if ($enabled !== 'true' && $enabled !== '1') {
        cron_log('CRON_ABSENT', 'Auto-absent marking is DISABLED in settings. Exiting.');
        return 0;
    }
    // Determine shifts to process
    if ($shiftFilter) {
        $k = strtolower(trim($shiftFilter));
        if (!in_array($k, ['morning','evening'], true)) {
            cron_log('CRON_ABSENT', 'Invalid shift filter. Use morning or evening.');
            return 2;
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
        cron_log('CRON_ABSENT', 'No shifts need processing at this time.');
        cron_log('CRON_ABSENT', 'Current time: ' . date('H:i:s'));
        cron_log('CRON_ABSENT', 'Morning check-in deadline: ' . getSetting($pdo, 'morning_checkin_end', 'not set'));
        cron_log('CRON_ABSENT', 'Evening check-in deadline: ' . getSetting($pdo, 'evening_checkin_end', 'not set'));
        return 0;
    }
    cron_log('CRON_ABSENT', 'Found ' . count($shifts) . ' shift(s) to process: ' . implode(', ', array_column($shifts, 'name')));
    $grand_checkin = 0; $grand_checkout = 0;
    foreach ($shifts as $shift) {
        $key = $shift['key']; $name = $shift['name'];
        cron_log('CRON_ABSENT', str_repeat('-', 40));
        cron_log('CRON_ABSENT', "PROCESSING: {$name} Shift");
        $r1 = absent_mark_for_missing_checkin($pdo, $key, $name);
        if (!empty($r1['marked_absent'])) { $grand_checkin += $r1['marked_absent']; }
        $r2 = absent_mark_for_missing_checkout($pdo, $key, $name);
        if (!empty($r2['marked_absent'])) { $grand_checkout += $r2['marked_absent']; }
        $total = ($r1['marked_absent'] ?? 0) + ($r2['marked_absent'] ?? 0);
        cron_log('CRON_ABSENT', "{$name} Shift Total: {$total} students marked absent");
    }
    $grand = $grand_checkin + $grand_checkout;
    cron_log('CRON_ABSENT', str_repeat('=', 40));
    cron_log('CRON_ABSENT', 'GRAND TOTAL: ' . $grand . ' students marked absent');
    cron_log('CRON_ABSENT', '  - Missing check-in: ' . $grand_checkin);
    cron_log('CRON_ABSENT', '  - Missing check-out: ' . $grand_checkout);
    cron_log('CRON_ABSENT', str_repeat('=', 40));
    cron_log('CRON_ABSENT', 'Auto Absent Marking - END');
    return 0;
}

// -----------------------------------------------------------------------------
// Log Cleanup (from cleanup_logs.php)
// -----------------------------------------------------------------------------
function run_cleanup($pdo) {
    cron_log('CRON_CLEANUP', 'Starting log cleanup...');
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'log_retention_days'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $retentionDays = $result ? (int)$result['setting_value'] : 30;
    cron_log('CRON_CLEANUP', 'Log retention days: ' . $retentionDays);

    $cutoff = time() - ($retentionDays * 24 * 60 * 60);
    $deleted = 0;

    // Audit logs
    $auditDir = __DIR__ . '/../logs/audit/';
    if (file_exists($auditDir)) {
        foreach (glob($auditDir . 'audit_*.log') ?: [] as $f) {
            if (filemtime($f) < $cutoff && @unlink($f)) { $deleted++; cron_log('CRON_CLEANUP', 'Deleted: ' . basename($f)); }
        }
    }
    // Student logs
    $studentDir = __DIR__ . '/../logs/';
    if (file_exists($studentDir)) {
        foreach (glob($studentDir . 'student_*.log') ?: [] as $f) {
            if (filemtime($f) < $cutoff && @unlink($f)) { $deleted++; cron_log('CRON_CLEANUP', 'Deleted: ' . basename($f)); }
        }
    }
    // Cron logs (90-day cap)
    $cronDir = __DIR__ . '/';
    if (file_exists($cronDir)) {
        $cronCutoff = time() - (90 * 24 * 60 * 60);
        foreach (glob($cronDir . 'cron_*.log') ?: [] as $f) {
            if (filemtime($f) < $cronCutoff && @unlink($f)) { $deleted++; cron_log('CRON_CLEANUP', 'Deleted: ' . basename($f)); }
        }
    }

    // DB audit prune
    $stmt = $pdo->prepare("DELETE FROM audit_logs WHERE timestamp < DATE_SUB(NOW(), INTERVAL ? DAY)");
    $stmt->execute([$retentionDays]);
    $dbDeleted = $stmt->rowCount();

    cron_log('CRON_CLEANUP', 'Total log files deleted: ' . $deleted);
    cron_log('CRON_CLEANUP', 'Database audit records deleted: ' . $dbDeleted);
    cron_log('CRON_CLEANUP', 'Log cleanup complete');
    return 0;
}

// -----------------------------------------------------------------------------
// Database Backup (from backup_database.php)
// -----------------------------------------------------------------------------
function run_backup($pdo) {
    cron_log('CRON_BACKUP', 'Starting database backup...');
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('backup_frequency','backup_retention_days')");
    $stmt->execute();
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $frequency = $settings['backup_frequency'] ?? 'weekly';
    $retentionDays = (int)($settings['backup_retention_days'] ?? 30);
    cron_log('CRON_BACKUP', 'Backup frequency: ' . $frequency);
    cron_log('CRON_BACKUP', 'Backup retention days: ' . $retentionDays);

    if ($frequency === 'disabled') {
        cron_log('CRON_BACKUP', 'Automatic backups are disabled.');
        return 0;
    }

    $backupDir = __DIR__ . '/../backups/database/';
    if (!file_exists($backupDir)) { @mkdir($backupDir, 0755, true); }

    $timestamp = date('Y-m-d_His');
    $backupFile = $backupDir . "backup_{$timestamp}.sql";

    $host = DB_HOST; $user = DB_USER; $pass = DB_PASS; $dbname = DB_NAME;
    $mysqldumpPath = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';

    $command = sprintf(
        '"%s" --host=%s --user=%s --password=%s --databases %s --result-file="%s" 2>&1',
        $mysqldumpPath,
        escapeshellarg($host),
        escapeshellarg($user),
        escapeshellarg($pass),
        escapeshellarg($dbname),
        $backupFile
    );

    cron_log('CRON_BACKUP', 'Creating backup: ' . basename($backupFile));
    exec($command, $output, $code);
    if ($code !== 0) {
        throw new Exception('Backup failed: ' . implode("\n", (array)$output));
    }
    if (!file_exists($backupFile) || filesize($backupFile) < 1000) {
        throw new Exception('Backup file is invalid or too small');
    }

    $gzFile = $backupFile . '.gz';
    $fp = gzopen($gzFile, 'w9');
    gzwrite($fp, file_get_contents($backupFile));
    gzclose($fp);
    @unlink($backupFile);
    $sizeMb = round(filesize($gzFile) / 1024 / 1024, 2);
    cron_log('CRON_BACKUP', 'Backup created: ' . basename($gzFile) . " ({$sizeMb} MB)");

    // Cleanup old backups
    $cutoff = time() - ($retentionDays * 24 * 60 * 60);
    $files = glob($backupDir . 'backup_*.sql.gz') ?: [];
    $deleted = 0;
    foreach ($files as $f) {
        if (filemtime($f) < $cutoff && @unlink($f)) { $deleted++; cron_log('CRON_BACKUP', 'Deleted old backup: ' . basename($f)); }
    }
    cron_log('CRON_BACKUP', 'Old backups deleted: ' . $deleted);

    // Optional audit log hook
    $audit = __DIR__ . '/../includes/audit_logger.php';
    if (file_exists($audit)) {
        require_once $audit;
        if (function_exists('auditLog')) { auditLog('database_backup', 'admin', 'system', 'Automatic backup created: ' . basename($gzFile)); }
    }

    cron_log('CRON_BACKUP', 'Database backup completed successfully');
    return 0;
}

// -----------------------------------------------------------------------------
// Programmatic dispatcher (for compatibility wrappers)
// -----------------------------------------------------------------------------
// -----------------------------------------------------------------------------
// Student Promotion (from promote_students.php)
// -----------------------------------------------------------------------------
function run_promote($pdo) {
    cron_log('CRON_PROMOTE', 'Starting student promotion...');
    try {
        require_once __DIR__ . '/../admin/api/promote_students.php';
        $api = new StudentPromotionAPI();
        $result = $api->promoteByDate();
        
        if ($result['success']) {
            cron_log('CRON_PROMOTE', "Success: {$result['message']}");
            cron_log('CRON_PROMOTE', "Promoted: {$result['promoted']}, Graduated: {$result['graduated']}, Unchanged: {$result['unchanged']}");
            return 0;
        } else {
            cron_log('CRON_PROMOTE', "FAILED: {$result['message']}");
            return 1;
        }
    } catch (Exception $e) {
        cron_log('CRON_PROMOTE', 'FATAL: ' . $e->getMessage());
        return 1;
    }
}

function cron_dispatch($action, $opt = null) {
    global $pdo;
    $action = strtolower(trim((string)$action));
    try {
        switch ($action) {
            case 'absent':
                return run_absent($pdo, $opt);
            case 'cleanup':
                return run_cleanup($pdo);
            case 'backup':
                return run_backup($pdo);
            case 'promote':
                return run_promote($pdo);
            case 'all':
                $ec1 = run_absent($pdo);
                $ec2 = run_cleanup($pdo);
                $ec3 = run_backup($pdo);
                $ec4 = run_promote($pdo);
                return ($ec1 || $ec2 || $ec3 || $ec4) ? 1 : 0;
            default:
                echo "Usage: php cron.php [absent [morning|evening] | cleanup | backup | promote | all]\n";
                return 2;
        }
    } catch (Exception $e) {
        cron_log('CRON', 'FATAL: ' . $e->getMessage());
        return 1;
    }
}

// -----------------------------------------------------------------------------
// CLI entry
// -----------------------------------------------------------------------------
if (php_sapi_name() === 'cli' && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    $action = $argv[1] ?? '';
    $opt = $argv[2] ?? null;
    if (empty($action)) {
        echo "Usage: php cron.php [absent [morning|evening] | cleanup | backup | promote | all]\n";
        exit(2);
    }
    $code = cron_dispatch($action, $opt);
    exit($code);
}
