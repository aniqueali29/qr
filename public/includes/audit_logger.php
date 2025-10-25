<?php
/**
 * Audit Logger
 * Centralized logging system for all user actions
 * Respects enable_audit_log setting from system_settings
 */

require_once __DIR__ . '/config.php';

class AuditLogger {
    private static $instance = null;
    private $pdo;
    private $enabled = true;
    private $logDir;
    
    private function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        $this->logDir = __DIR__ . '/../logs/audit/';
        
        // Create audit log directory if it doesn't exist
        if (!file_exists($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }
        
        // Check if audit logging is enabled from settings
        $this->checkIfEnabled();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function checkIfEnabled() {
        try {
            $stmt = $this->pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'enable_audit_log'");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $this->enabled = ($result['setting_value'] === 'true' || $result['setting_value'] === '1' || $result['setting_value'] === 1);
            }
        } catch (Exception $e) {
            error_log("Error checking audit log setting: " . $e->getMessage());
            // Default to enabled if setting not found
            $this->enabled = true;
        }
    }
    
    /**
     * Log an action
     * 
     * @param string $action Action performed (e.g., 'login', 'logout', 'update_student')
     * @param string $user_type Type of user ('admin', 'student')
     * @param string $user_id User identifier (username, roll number, etc.)
     * @param string $details Additional details about the action
     * @param array $data Optional data to log as JSON
     */
    public function log($action, $user_type, $user_id, $details = '', $data = null) {
        if (!$this->enabled) {
            return;
        }
        
        try {
            $timestamp = date('Y-m-d H:i:s');
            $date = date('Y-m-d');
            $ip = $this->getClientIP();
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            
            // Prepare log entry
            $logEntry = [
                'timestamp' => $timestamp,
                'action' => $action,
                'user_type' => $user_type,
                'user_id' => $user_id,
                'ip' => $ip,
                'user_agent' => $user_agent,
                'details' => $details,
                'data' => $data
            ];
            
            // Write to daily log file
            $logFile = $this->logDir . "audit_{$date}.log";
            $logLine = json_encode($logEntry) . PHP_EOL;
            file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
            
            // Also store in database for quick queries
            $this->storeInDatabase($logEntry);
            
        } catch (Exception $e) {
            error_log("Audit logging error: " . $e->getMessage());
        }
    }
    
    private function storeInDatabase($logEntry) {
        try {
            // Check if audit_logs table exists
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'audit_logs'");
            if ($stmt->rowCount() == 0) {
                $this->createAuditTable();
            }
            
            $stmt = $this->pdo->prepare("
                INSERT INTO audit_logs 
                (timestamp, action, user_type, user_id, ip_address, user_agent, details, data)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $logEntry['timestamp'],
                $logEntry['action'],
                $logEntry['user_type'],
                $logEntry['user_id'],
                $logEntry['ip'],
                $logEntry['user_agent'],
                $logEntry['details'],
                json_encode($logEntry['data'])
            ]);
        } catch (Exception $e) {
            error_log("Error storing audit log in database: " . $e->getMessage());
        }
    }
    
    private function createAuditTable() {
        $sql = "CREATE TABLE IF NOT EXISTS audit_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            timestamp DATETIME NOT NULL,
            action VARCHAR(100) NOT NULL,
            user_type ENUM('admin', 'student') NOT NULL,
            user_id VARCHAR(100) NOT NULL,
            ip_address VARCHAR(50) NOT NULL,
            user_agent TEXT,
            details TEXT,
            data JSON,
            INDEX idx_timestamp (timestamp),
            INDEX idx_action (action),
            INDEX idx_user (user_type, user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->pdo->exec($sql);
    }
    
    private function getClientIP() {
        $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                        return $ip;
                    }
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Get recent audit logs
     */
    public function getRecentLogs($limit = 100, $user_type = null, $user_id = null) {
        try {
            $sql = "SELECT * FROM audit_logs WHERE 1=1";
            $params = [];
            
            if ($user_type) {
                $sql .= " AND user_type = ?";
                $params[] = $user_type;
            }
            
            if ($user_id) {
                $sql .= " AND user_id = ?";
                $params[] = $user_id;
            }
            
            $sql .= " ORDER BY timestamp DESC LIMIT ?";
            $params[] = $limit;
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error fetching audit logs: " . $e->getMessage());
            return [];
        }
    }
}

// Helper functions for easy logging
function auditLog($action, $user_type, $user_id, $details = '', $data = null) {
    try {
        AuditLogger::getInstance()->log($action, $user_type, $user_id, $details, $data);
    } catch (Exception $e) {
        error_log("Audit log helper error: " . $e->getMessage());
    }
}

function auditLogAdmin($action, $details = '', $data = null) {
    $admin_id = $_SESSION['admin_username'] ?? 'unknown';
    auditLog($action, 'admin', $admin_id, $details, $data);
}

function auditLogStudent($action, $details = '', $data = null) {
    $student_id = $_SESSION['student_roll_number'] ?? $_SESSION['student_username'] ?? 'unknown';
    auditLog($action, 'student', $student_id, $details, $data);
}
?>
