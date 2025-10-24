<?php
/**
 * Public Sync Status API
 * Provides sync status without authentication
 */

require_once 'config.php';

// Handle API requests
header('Content-Type: application/json');

try {
    // Get website stats
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM attendance");
    $stmt->execute();
    $websiteRecords = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM students");
    $stmt->execute();
    $studentCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get last sync time from sync data file
    $lastSync = 'Never';
    if (file_exists('sync_data.json')) {
        $syncData = json_decode(file_get_contents('sync_data.json'), true);
        if ($syncData && isset($syncData['timestamp'])) {
            $lastSync = $syncData['timestamp'];
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'website_records' => $studentCount,
            'student_count' => $studentCount,
            'attendance_records' => $websiteRecords,
            'last_sync' => $lastSync,
            'database_status' => 'Connected'
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error getting sync status: ' . $e->getMessage()]);
}
?>
