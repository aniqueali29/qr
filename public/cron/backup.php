<?php
/**
 * Automatic Backup Cron Job
 * Creates database backups based on admin settings
 * Schedule: Daily, Weekly, or Monthly based on system_settings
 */

require_once __DIR__ . '/../../includes/config.php';

function cron_log($message) {
    echo date('Y-m-d H:i:s') . " - " . $message . "\n";
}

function createDatabaseBackup() {
    try {
        // Get database config
        $host = getenv('DB_HOST') ?: 'localhost';
        $database = getenv('DB_NAME') ?: 'qr_attendance';
        $username = getenv('DB_USER') ?: 'root';
        $password = getenv('DB_PASSWORD') ?: '';
        
        // Backup directory
        $backupDir = __DIR__ . '/../../backups/database/';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        // Generate filename
        $filename = 'backup_' . date('Y-m-d_His') . '.sql';
        $filepath = $backupDir . $filename;
        
        // Create backup using mysqldump
        $command = sprintf(
            'mysqldump -h%s -u%s -p%s %s > %s 2>&1',
            escapeshellarg($host),
            escapeshellarg($username),
            escapeshellarg($password),
            escapeshellarg($database),
            escapeshellarg($filepath)
        );
        
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0 && file_exists($filepath) && filesize($filepath) > 0) {
            // Compress backup
            $compressedFile = $filepath . '.gz';
            $gz = gzopen($compressedFile, 'w9');
            gzwrite($gz, file_get_contents($filepath));
            gzclose($gz);
            unlink($filepath); // Delete uncompressed version
            
            // Log backup info
            $filesize = filesize($compressedFile);
            cron_log("✓ Backup created: {$filename}.gz ({$filesize} bytes)");
            
            return [
                'success' => true,
                'filename' => $filename . '.gz',
                'filepath' => $compressedFile,
                'size' => $filesize
            ];
        } else {
            cron_log("✗ Backup failed: Return code {$returnCode}");
            return ['success' => false, 'message' => 'Backup command failed'];
        }
        
    } catch (Exception $e) {
        cron_log("✗ Backup error: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function shouldRunBackup($pdo, $frequency) {
    try {
        // Get last backup timestamp from settings
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute(['last_backup_time']);
        $lastBackup = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $lastBackupTime = $lastBackup ? strtotime($lastBackup['setting_value']) : 0;
        $now = time();
        $hoursSinceLastBackup = ($now - $lastBackupTime) / 3600;
        
        cron_log("Last backup: " . ($lastBackupTime ? date('Y-m-d H:i:s', $lastBackupTime) : 'Never'));
        
        switch ($frequency) {
            case 'daily':
                return $hoursSinceLastBackup >= 24;
            case 'weekly':
                return $hoursSinceLastBackup >= 168; // 7 days
            case 'monthly':
                return $hoursSinceLastBackup >= 720; // 30 days
            case 'disabled':
                return false;
            default:
                return false;
        }
        
    } catch (Exception $e) {
        cron_log("Error checking backup schedule: " . $e->getMessage());
        return false;
    }
}

function cleanupOldBackups($pdo, $retentionDays) {
    try {
        if ($retentionDays <= 0) {
            return; // Don't delete anything if retention is 0
        }
        
        $backupDir = __DIR__ . '/../../backups/database/';
        if (!is_dir($backupDir)) {
            return;
        }
        
        $files = glob($backupDir . 'backup_*.sql.gz');
        $deletedCount = 0;
        $cutoffTime = time() - ($retentionDays * 24 * 3600);
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                unlink($file);
                $deletedCount++;
                cron_log("Deleted old backup: " . basename($file));
            }
        }
        
        if ($deletedCount > 0) {
            cron_log("✓ Cleaned up {$deletedCount} old backup(s)");
        }
        
    } catch (Exception $e) {
        cron_log("Error cleaning up backups: " . $e->getMessage());
    }
}

function updateLastBackupTime($pdo) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO system_settings (setting_key, setting_value, setting_type, category)
            VALUES (?, NOW(), 'string', 'system')
            ON DUPLICATE KEY UPDATE setting_value = NOW()
        ");
        $stmt->execute(['last_backup_time']);
    } catch (Exception $e) {
        cron_log("Error updating last backup time: " . $e->getMessage());
    }
}

// Main execution
try {
    cron_log("=== Automatic Backup Cron Job Started ===");
    
    // Initialize PDO
    $pdo = new PDO(
        "mysql:host={$config['DB_HOST']};dbname={$config['DB_NAME']}",
        $config['DB_USER'],
        $config['DB_PASSWORD']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get backup settings
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    
    $stmt->execute(['backup_frequency']);
    $frequency = $stmt->fetch(PDO::FETCH_ASSOC);
    $frequency = $frequency ? $frequency['setting_value'] : 'disabled';
    
    $stmt->execute(['backup_retention_days']);
    $retention = $stmt->fetch(PDO::FETCH_ASSOC);
    $retentionDays = $retention ? (int)$retention['setting_value'] : 30;
    
    cron_log("Backup frequency: {$frequency}");
    cron_log("Backup retention: {$retentionDays} days");
    
    if ($frequency === 'disabled') {
        cron_log("Backup is disabled, skipping...");
        exit(0);
    }
    
    // Check if backup should run
    if (!shouldRunBackup($pdo, $frequency)) {
        cron_log("Backup not needed at this time");
        exit(0);
    }
    
    // Create backup
    cron_log("Creating backup...");
    $result = createDatabaseBackup();
    
    if ($result['success']) {
        updateLastBackupTime($pdo);
        cron_log("✓ Backup completed successfully");
        
        // Cleanup old backups
        cleanupOldBackups($pdo, $retentionDays);
        
    } else {
        cron_log("✗ Backup failed: " . $result['message']);
        exit(1);
    }
    
    cron_log("=== Automatic Backup Cron Job Completed ===");
    
} catch (Exception $e) {
    cron_log("✗ Fatal error: " . $e->getMessage());
    exit(1);
}

