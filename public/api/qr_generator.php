<?php
/**
 * QR Code Generator API for QR Code Attendance System
 * Handles QR code generation, storage, and management
 */

require_once 'config.php';
require_once 'auth_system.php';

// Simple QR Code library (you can replace with phpqrcode or similar)
class SimpleQRCode {
    private $size;
    private $margin;
    
    public function __construct($size = 200, $margin = 10) {
        $this->size = $size;
        $this->margin = $margin;
    }
    
    /**
     * Generate QR code data (simplified version)
     * In production, use a proper QR code library like phpqrcode
     */
    public function generate($data) {
        // This is a simplified implementation
        // In production, use a proper QR code library
        $qr_data = [
            'data' => $data,
            'size' => $this->size,
            'margin' => $this->margin,
            'timestamp' => time()
        ];
        
        return $qr_data;
    }
    
    /**
     * Create QR code image (placeholder)
     * In production, this would generate an actual QR code image
     */
    public function createImage($data, $filename) {
        // Create a simple placeholder image
        $image = imagecreate($this->size, $this->size);
        $bg_color = imagecolorallocate($image, 255, 255, 255);
        $text_color = imagecolorallocate($image, 0, 0, 0);
        
        // Fill background
        imagefill($image, 0, 0, $bg_color);
        
        // Add text (in production, this would be the actual QR code)
        imagestring($image, 5, 10, $this->size/2 - 10, 'QR: ' . substr($data, 0, 10), $text_color);
        
        // Save image
        if (!is_dir(dirname($filename))) {
            mkdir(dirname($filename), 0755, true);
        }
        
        imagepng($image, $filename);
        imagedestroy($image);
        
        return $filename;
    }
}

class QRCodeManager {
    private $pdo;
    private $qr_generator;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->qr_generator = new SimpleQRCode(QR_CODE_SIZE, QR_CODE_MARGIN);
    }
    
    /**
     * Generate QR code for a student
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
            
            // Generate QR code
            $qr_info = $this->qr_generator->generate($qr_data);
            
            // Create filename
            $filename = 'qr_' . $student_id . '_' . time() . '.png';
            $filepath = QR_CODE_PATH . $filename;
            $full_path = $this->qr_generator->createImage($qr_data, $filepath);
            
            // Deactivate old QR codes for this student
            $stmt = $this->pdo->prepare("UPDATE qr_codes SET is_active = 0 WHERE student_id = ?");
            $stmt->execute([$student_id]);
            
            // Store QR code in database
            $stmt = $this->pdo->prepare("
                INSERT INTO qr_codes (student_id, qr_data, qr_image_path, created_by) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$student_id, $qr_data, $full_path, $user_id]);
            
            return [
                'success' => true,
                'message' => 'QR code generated successfully',
                'data' => [
                    'student_id' => $student_id,
                    'qr_data' => $qr_data,
                    'image_path' => $full_path,
                    'image_url' => QR_CODE_URL . $filename,
                    'download_url' => 'api/qr_download.php?student_id=' . $student_id
                ]
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'QR generation failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get QR code for a student
     */
    public function getStudentQR($student_id) {
        try {
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
                    'image_url' => QR_CODE_URL . basename($qr['qr_image_path']),
                    'generated_at' => $qr['generated_at']
                ]
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to get QR code: ' . $e->getMessage()];
        }
    }
    
    /**
     * Regenerate QR code for a student
     */
    public function regenerateStudentQR($student_id, $user_id = null) {
        return $this->generateStudentQR($student_id, $user_id);
    }
    
    /**
     * Bulk generate QR codes for all students
     */
    public function bulkGenerateQR($user_id = null) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT student_id FROM students WHERE is_active = 1
            ");
            $stmt->execute();
            $students = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $results = [];
            $success_count = 0;
            $error_count = 0;
            
            foreach ($students as $student_id) {
                $result = $this->generateStudentQR($student_id, $user_id);
                if ($result['success']) {
                    $success_count++;
                } else {
                    $error_count++;
                }
                $results[] = $result;
            }
            
            return [
                'success' => true,
                'message' => "Bulk generation completed: {$success_count} successful, {$error_count} failed",
                'data' => [
                    'total' => count($students),
                    'successful' => $success_count,
                    'failed' => $error_count,
                    'results' => $results
                ]
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Bulk generation failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get QR code statistics
     */
    public function getQRStats() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_qr_codes,
                    COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_qr_codes,
                    COUNT(CASE WHEN DATE(generated_at) = CURDATE() THEN 1 END) as generated_today
                FROM qr_codes
            ");
            $stmt->execute();
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as students_without_qr 
                FROM students s 
                LEFT JOIN qr_codes q ON s.student_id = q.student_id AND q.is_active = 1 
                WHERE s.is_active = 1 AND q.id IS NULL
            ");
            $stmt->execute();
            $without_qr = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'data' => array_merge($stats, $without_qr)
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to get QR stats: ' . $e->getMessage()];
        }
    }
    
    /**
     * Delete QR code
     */
    public function deleteQR($student_id) {
        try {
            $stmt = $this->pdo->prepare("UPDATE qr_codes SET is_active = 0 WHERE student_id = ?");
            $stmt->execute([$student_id]);
            
            return ['success' => true, 'message' => 'QR code deactivated successfully'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to delete QR code: ' . $e->getMessage()];
        }
    }
}

// Initialize QR code manager
$qr_manager = new QRCodeManager($pdo);

// Handle API requests
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => 'Invalid action'];
    
    // Check authentication for most operations
    if (!in_array($action, ['get_student_qr'])) {
        requireLogin();
    }
    
    switch ($action) {
        case 'generate_student_qr':
            $student_id = $_POST['student_id'] ?? '';
            $user_id = $_SESSION['user_id'] ?? null;
            $response = $qr_manager->generateStudentQR($student_id, $user_id);
            break;
            
        case 'regenerate_student_qr':
            $student_id = $_POST['student_id'] ?? '';
            $user_id = $_SESSION['user_id'] ?? null;
            $response = $qr_manager->regenerateStudentQR($student_id, $user_id);
            break;
            
        case 'bulk_generate':
            $user_id = $_SESSION['user_id'] ?? null;
            $response = $qr_manager->bulkGenerateQR($user_id);
            break;
            
        case 'delete_qr':
            $student_id = $_POST['student_id'] ?? '';
            $response = $qr_manager->deleteQR($student_id);
            break;
    }
    
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    $response = ['success' => false, 'message' => 'Invalid action'];
    
    switch ($action) {
        case 'get_student_qr':
            $student_id = $_GET['student_id'] ?? '';
            if (empty($student_id)) {
                $response = ['success' => false, 'message' => 'Student ID required'];
            } else {
                $response = $qr_manager->getStudentQR($student_id);
            }
            break;
            
        case 'get_stats':
            requireLogin();
            $response = $qr_manager->getQRStats();
            break;
    }
    
    echo json_encode($response);
    exit;
}

// Default response
echo json_encode(['success' => false, 'message' => 'Invalid request method']);
?>
