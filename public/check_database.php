<?php
/**
 * Database Diagnostic Script
 * This script checks database structure and identifies potential issues
 */

require_once __DIR__ . '/includes/config.php';

echo "<h2>Database Diagnostic Report</h2>";
echo "<p>Generated at: " . date('Y-m-d H:i:s') . "</p>";

try {
    // Check if check_in_sessions table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'check_in_sessions'");
    $checkInSessionsExists = $stmt->fetch() !== false;
    
    echo "<h3>Table Status</h3>";
    echo "<p>check_in_sessions table: " . ($checkInSessionsExists ? "✅ EXISTS" : "❌ MISSING") . "</p>";
    
    if (!$checkInSessionsExists) {
        echo "<h4>Creating check_in_sessions table...</h4>";
        $createTable = "
            CREATE TABLE IF NOT EXISTS `check_in_sessions` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `student_id` varchar(20) NOT NULL,
                `student_name` varchar(100) NOT NULL,
                `check_in_time` datetime NOT NULL,
                `is_active` tinyint(1) NOT NULL DEFAULT 1,
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_active_session` (`student_id`, `is_active`),
                KEY `idx_student_id` (`student_id`),
                KEY `idx_is_active` (`is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        $pdo->exec($createTable);
        echo "<p style='color: green;'>✅ check_in_sessions table created successfully</p>";
    }
    
    // Check attendance table structure
    echo "<h3>Attendance Table Structure</h3>";
    $stmt = $pdo->query("DESCRIBE attendance");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>{$column['Field']}</td>";
        echo "<td>{$column['Type']}</td>";
        echo "<td>{$column['Null']}</td>";
        echo "<td>{$column['Key']}</td>";
        echo "<td>{$column['Default']}</td>";
        echo "<td>{$column['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check for recent attendance records
    echo "<h3>Recent Attendance Records (Last 5)</h3>";
    $stmt = $pdo->query("
        SELECT student_id, student_name, status, timestamp, check_in_time, check_out_time 
        FROM attendance 
        ORDER BY timestamp DESC 
        LIMIT 5
    ");
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($records)) {
        echo "<p>No attendance records found.</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Student ID</th><th>Name</th><th>Status</th><th>Timestamp</th><th>Check-in</th><th>Check-out</th></tr>";
        foreach ($records as $record) {
            echo "<tr>";
            echo "<td>{$record['student_id']}</td>";
            echo "<td>{$record['student_name']}</td>";
            echo "<td>{$record['status']}</td>";
            echo "<td>{$record['timestamp']}</td>";
            echo "<td>{$record['check_in_time']}</td>";
            echo "<td>{$record['check_out_time']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Check for duplicate records today
    echo "<h3>Duplicate Records Today</h3>";
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("
        SELECT student_id, COUNT(*) as count 
        FROM attendance 
        WHERE DATE(timestamp) = ? 
        GROUP BY student_id 
        HAVING COUNT(*) > 1
    ");
    $stmt->execute([$today]);
    $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($duplicates)) {
        echo "<p style='color: green;'>✅ No duplicate records found for today.</p>";
    } else {
        echo "<p style='color: red;'>❌ Found duplicate records:</p>";
        echo "<ul>";
        foreach ($duplicates as $dup) {
            echo "<li>Student {$dup['student_id']}: {$dup['count']} records</li>";
        }
        echo "</ul>";
    }
    
    echo "<h3>Database Connection Test</h3>";
    echo "<p style='color: green;'>✅ Database connection successful</p>";
    echo "<p>Database: " . DB_NAME . "</p>";
    echo "<p>Host: " . DB_HOST . "</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<p><a href='admin/scan.php'>Go to Scan Page</a></p>";
?>
