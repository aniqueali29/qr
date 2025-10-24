<?php
/**
 * Sync API for QR Code Attendance System
 * Handles synchronization logging and conflict resolution
 */

require_once 'config.php';
require_once 'auth_system.php';

// Handle API requests
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => 'Invalid action'];
    
    switch ($action) {
        case 'log_sync':
            $response = logSyncActivity();
            break;
            
        case 'get_sync_logs':
            $response = getSyncLogs();
            break;
            
        case 'resolve_conflict':
            $response = resolveConflict();
            break;
            
        case 'get_sync_status':
            $response = getSyncStatus();
            break;
    }
    
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    $response = ['success' => false, 'message' => 'Invalid action'];
    
    switch ($action) {
        case 'get_sync_logs':
            $response = getSyncLogs();
            break;
            
        case 'get_sync_status':
            $response = getSyncStatus();
            break;
            
        case 'get_conflicts':
            $response = getConflicts();
            break;
    }
    
    echo json_encode($response);
    exit;
}

// Default response
echo json_encode(['success' => false, 'message' => 'Invalid request method']);

/**
 * Log sync activity
 */
function logSyncActivity() {
    global $pdo;
    
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $sync_data = $input['sync_data'] ?? [];
        
        if (empty($sync_data)) {
            return ['success' => false, 'message' => 'Sync data required'];
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO sync_logs (
                sync_type, status, records_processed, records_failed, 
                error_message, sync_duration, ip_address, user_agent
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $sync_data['sync_type'] ?? 'unknown',
            $sync_data['status'] ?? 'unknown',
            $sync_data['records_processed'] ?? 0,
            $sync_data['records_failed'] ?? 0,
            $sync_data['error_message'] ?? null,
            $sync_data['sync_duration'] ?? 0,
            $sync_data['ip_address'] ?? getClientIP(),
            $sync_data['user_agent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]);
        
        return ['success' => true, 'message' => 'Sync activity logged successfully'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Failed to log sync activity: ' . $e->getMessage()];
    }
}

/**
 * Get sync logs
 */
function getSyncLogs($limit = 50) {
    global $pdo;
    
    try {
        $limit = $_GET['limit'] ?? $limit;
        $offset = $_GET['offset'] ?? 0;
        
        $stmt = $pdo->prepare("
            SELECT * FROM sync_logs 
            ORDER BY created_at DESC 
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$limit, $offset]);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'data' => $logs
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Failed to get sync logs: ' . $e->getMessage()];
    }
}

/**
 * Get sync status
 */
function getSyncStatus() {
    global $pdo;
    
    try {
        // Get recent sync activity
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_syncs,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful_syncs,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_syncs,
                AVG(sync_duration) as avg_duration,
                MAX(created_at) as last_sync
            FROM sync_logs 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->execute();
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get current sync status
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as pending_records
            FROM attendance 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute();
        $pending = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'data' => [
                'total_syncs' => (int)$stats['total_syncs'],
                'successful_syncs' => (int)$stats['successful_syncs'],
                'failed_syncs' => (int)$stats['failed_syncs'],
                'success_rate' => $stats['total_syncs'] > 0 ? 
                    round(($stats['successful_syncs'] / $stats['total_syncs']) * 100, 1) : 0,
                'avg_duration' => round($stats['avg_duration'] ?? 0, 3),
                'last_sync' => $stats['last_sync'],
                'pending_records' => (int)$pending['pending_records']
            ]
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Failed to get sync status: ' . $e->getMessage()];
    }
}

/**
 * Get conflicts
 */
function getConflicts() {
    global $pdo;
    
    try {
        // Find potential conflicts (same student, same timestamp, different status)
        $stmt = $pdo->prepare("
            SELECT 
                a1.student_id,
                a1.student_name,
                a1.timestamp,
                a1.status as local_status,
                a2.status as web_status,
                a1.created_at as local_created,
                a2.created_at as web_created
            FROM attendance a1
            JOIN attendance a2 ON a1.student_id = a2.student_id 
                AND a1.timestamp = a2.timestamp 
                AND a1.id != a2.id
            WHERE a1.status != a2.status
            ORDER BY a1.timestamp DESC
        ");
        $stmt->execute();
        $conflicts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'data' => $conflicts
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Failed to get conflicts: ' . $e->getMessage()];
    }
}

/**
 * Resolve conflict
 */
function resolveConflict() {
    global $pdo;
    
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $student_id = $input['student_id'] ?? '';
        $timestamp = $input['timestamp'] ?? '';
        $resolution = $input['resolution'] ?? ''; // 'local' or 'web'
        
        if (empty($student_id) || empty($timestamp) || empty($resolution)) {
            return ['success' => false, 'message' => 'Missing required parameters'];
        }
        
        if ($resolution === 'local') {
            // Keep local version, remove web version
            $stmt = $pdo->prepare("
                DELETE FROM attendance 
                WHERE student_id = ? AND timestamp = ? 
                AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            $stmt->execute([$student_id, $timestamp]);
        } else {
            // Keep web version, remove local version
            $stmt = $pdo->prepare("
                DELETE FROM attendance 
                WHERE student_id = ? AND timestamp = ? 
                AND created_at <= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            $stmt->execute([$student_id, $timestamp]);
        }
        
        return ['success' => true, 'message' => 'Conflict resolved successfully'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Failed to resolve conflict: ' . $e->getMessage()];
    }
}

/**
 * Get client IP address
 */
function getClientIP() {
    $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

/**
 * Clean up old sync logs
 */
function cleanupSyncLogs($days = 30) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            DELETE FROM sync_logs 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$days]);
        
        return $stmt->rowCount();
        
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Get sync statistics
 */
function getSyncStatistics($days = 7) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as total_syncs,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful_syncs,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_syncs,
                AVG(sync_duration) as avg_duration,
                SUM(records_processed) as total_records
            FROM sync_logs 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ");
        $stmt->execute([$days]);
        $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'data' => $stats
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Failed to get sync statistics: ' . $e->getMessage()];
    }
}
?>
