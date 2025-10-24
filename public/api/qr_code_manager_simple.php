<?php
/**
 * Simplified QR Code Manager for Card Export
 * Does NOT include auth_system.php or use GD library
 * Only handles database operations for QR codes
 */

class QRCodeManager {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Generate QR code for a student (database entry only)
     */
    public function generateStudentQR($student_id, $user_id = null) {
        try {
            // Get student information
            $stmt = $this->pdo->prepare("
                SELECT s.student_id, s.name, s.email 
                FROM students s 
                WHERE s.student_id = ? AND s.is_active = 1
            ");
            $stmt->execute([$student_id]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$student) {
                return ['success' => false, 'message' => 'Student not found'];
            }
            
            // Create QR data
            $qr_data = json_encode([
                'student_id' => $student['student_id'],
                'name' => $student['name'],
                'timestamp' => time(),
                'type' => 'attendance'
            ]);
            
            // Check if qr_codes table exists, if not, create it
            try {
                $this->pdo->exec("
                    CREATE TABLE IF NOT EXISTS qr_codes (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        student_id VARCHAR(50) NOT NULL,
                        qr_data TEXT,
                        qr_image_path VARCHAR(255),
                        is_active TINYINT(1) DEFAULT 1,
                        created_by INT,
                        generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_student_id (student_id),
                        INDEX idx_active (is_active)
                    )
                ");
                
                // Deactivate old QR codes for this student
                $stmt = $this->pdo->prepare("UPDATE qr_codes SET is_active = 0 WHERE student_id = ?");
                $stmt->execute([$student_id]);
                
                // Store QR code in database
                $stmt = $this->pdo->prepare("
                    INSERT INTO qr_codes (student_id, qr_data, qr_image_path, created_by) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$student_id, $qr_data, '', $user_id]);
                
            } catch (Exception $table_error) {
                // If table operations fail, just log and continue
                error_log("QR table operation failed: " . $table_error->getMessage());
            }
            
            return [
                'success' => true,
                'message' => 'QR code generated successfully',
                'data' => [
                    'student_id' => $student_id,
                    'qr_data' => $qr_data
                ]
            ];
            
        } catch (Exception $e) {
            error_log("QR generation failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'QR generation failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get QR code for a student
     */
    public function getStudentQR($student_id) {
        try {
            // Check if qr_codes table exists
            $stmt = $this->pdo->prepare("SHOW TABLES LIKE 'qr_codes'");
            $stmt->execute();
            $table_exists = $stmt->fetch();
            
            if (!$table_exists) {
                return ['success' => false, 'message' => 'No active QR code found'];
            }
            
            $stmt = $this->pdo->prepare("
                SELECT qr_data, qr_image_path, generated_at 
                FROM qr_codes 
                WHERE student_id = ? AND is_active = 1 
                ORDER BY generated_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$student_id]);
            $qr = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$qr) {
                return ['success' => false, 'message' => 'No active QR code found'];
            }
            
            return [
                'success' => true,
                'data' => [
                    'student_id' => $student_id,
                    'qr_data' => $qr['qr_data'],
                    'image_path' => $qr['qr_image_path'],
                    'generated_at' => $qr['generated_at']
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Failed to get QR code: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to get QR code: ' . $e->getMessage()];
        }
    }
}
?>

