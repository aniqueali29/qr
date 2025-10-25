<?php
/**
 * Student Promotion API
 * Handles bulk promotion of students to next year
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

// Check admin authentication
if (!isAdminLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

class StudentPromotionAPI {
    private $pdo;
    
    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }
    
    /**
     * Get max program years from settings
     */
    private function getMaxYears() {
        try {
            $stmt = $this->pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'max_program_years'");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? (int)$result['setting_value'] : 4;
        } catch (Exception $e) {
            return 4; // Default to 4 years
        }
    }
    
    /**
     * Get promotion preview - shows what will happen
     */
    public function getPromotionPreview() {
        try {
            $max_years = $this->getMaxYears();
            
            // Get students by current year
            $stmt = $this->pdo->prepare("
                SELECT 
                    current_year,
                    year_level,
                    COUNT(*) as student_count
                FROM students
                WHERE is_active = 1 AND is_graduated = 0
                GROUP BY current_year, year_level
                ORDER BY current_year
            ");
            $stmt->execute();
            $summary = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get total counts
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_active,
                    SUM(CASE WHEN current_year >= ? THEN 1 ELSE 0 END) as will_graduate,
                    SUM(CASE WHEN current_year < ? THEN 1 ELSE 0 END) as will_promote
                FROM students
                WHERE is_active = 1 AND is_graduated = 0
            ");
            $stmt->execute([$max_years, $max_years]);
            $totals = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'summary' => $summary,
                'totals' => $totals,
                'max_years' => $max_years,
                'message' => 'Promotion preview generated'
            ];
            
        } catch (Exception $e) {
            error_log("Promotion preview error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to generate preview: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Promote all eligible students
     */
    public function promoteAllStudents() {
        try {
            $this->pdo->beginTransaction();
            
            $max_years = $this->getMaxYears();
            $today = date('Y-m-d');
            $promoted = 0;
            $graduated = 0;
            $errors = 0;
            $details = [];
            
            // Get all active, non-graduated students
            $stmt = $this->pdo->prepare("
                SELECT student_id, roll_number, name, current_year, year_level
                FROM students
                WHERE is_active = 1 AND is_graduated = 0
                ORDER BY current_year, roll_number
            ");
            $stmt->execute();
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($students as $student) {
                try {
                    $current_year = (int)$student['current_year'];
                    
                    if ($current_year >= $max_years) {
                        // Graduate 3rd year students
                        $stmt = $this->pdo->prepare("
                            UPDATE students 
                            SET is_graduated = 1, 
                                is_active = 0,
                                last_year_update = ?
                            WHERE student_id = ?
                        ");
                        $stmt->execute([$today, $student['student_id']]);
                        $graduated++;
                        
                        $details[] = [
                            'student_id' => $student['student_id'],
                            'roll_number' => $student['roll_number'],
                            'name' => $student['name'],
                            'action' => 'graduated',
                            'from' => $student['year_level'],
                            'to' => 'Graduated'
                        ];
                        
                    } else {
                        // Promote to next year
                        $new_year = $current_year + 1;
                        $new_year_level = $this->getYearLevel($new_year);
                        
                        $stmt = $this->pdo->prepare("
                            UPDATE students 
                            SET current_year = ?,
                                year_level = ?,
                                last_year_update = ?
                            WHERE student_id = ?
                        ");
                        $stmt->execute([$new_year, $new_year_level, $today, $student['student_id']]);
                        $promoted++;
                        
                        $details[] = [
                            'student_id' => $student['student_id'],
                            'roll_number' => $student['roll_number'],
                            'name' => $student['name'],
                            'action' => 'promoted',
                            'from' => $student['year_level'],
                            'to' => $new_year_level
                        ];
                    }
                    
                } catch (Exception $e) {
                    $errors++;
                    error_log("Error promoting student {$student['student_id']}: " . $e->getMessage());
                    $details[] = [
                        'student_id' => $student['student_id'],
                        'roll_number' => $student['roll_number'],
                        'name' => $student['name'],
                        'action' => 'error',
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            if ($errors == 0) {
                $this->pdo->commit();
                
                // Log the promotion activity
                $this->logPromotionActivity($promoted, $graduated);
                
                return [
                    'success' => true,
                    'message' => "Successfully promoted {$promoted} students and graduated {$graduated} students",
                    'promoted' => $promoted,
                    'graduated' => $graduated,
                    'errors' => $errors,
                    'details' => $details
                ];
            } else {
                $this->pdo->rollBack();
                return [
                    'success' => false,
                    'message' => "Promotion failed with {$errors} errors. No changes made.",
                    'promoted' => $promoted,
                    'graduated' => $graduated,
                    'errors' => $errors,
                    'details' => $details
                ];
            }
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Promotion error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Promotion failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get year level label from year number
     */
    private function getYearLevel($year) {
        $levels = [1 => '1st', 2 => '2nd', 3 => '3rd', 4 => '4th'];
        return $levels[$year] ?? '1st';
    }
    
    /**
     * Log promotion activity
     */
    private function logPromotionActivity($promoted, $graduated) {
        try {
            $admin_id = $_SESSION['admin_id'] ?? 'system';
            $message = "Bulk student promotion: {$promoted} promoted, {$graduated} graduated";
            
            error_log("[PROMOTION] Admin: {$admin_id} - {$message}");
            
            // You can add to an audit log table if you have one
            
        } catch (Exception $e) {
            error_log("Failed to log promotion activity: " . $e->getMessage());
        }
    }
    
    /**
     * Rollback promotion (undo)
     */
    public function rollbackPromotion() {
        try {
            $today = date('Y-m-d');
            
            $this->pdo->beginTransaction();
            
            // Get students promoted today
            $stmt = $this->pdo->prepare("
                SELECT student_id, roll_number, name, current_year, is_graduated
                FROM students
                WHERE last_year_update = ?
            ");
            $stmt->execute([$today]);
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($students) == 0) {
                return [
                    'success' => false,
                    'message' => 'No promotions found today to rollback'
                ];
            }
            
            $rolled_back = 0;
            
            foreach ($students as $student) {
                if ($student['is_graduated'] == 1) {
                    // Rollback graduation
                    $stmt = $this->pdo->prepare("
                        UPDATE students 
                        SET is_graduated = 0,
                            is_active = 1,
                            last_year_update = NULL
                        WHERE student_id = ?
                    ");
                    $stmt->execute([$student['student_id']]);
                } else {
                    // Rollback promotion
                    $prev_year = (int)$student['current_year'] - 1;
                    $prev_year_level = $this->getYearLevel($prev_year);
                    
                    $stmt = $this->pdo->prepare("
                        UPDATE students 
                        SET current_year = ?,
                            year_level = ?,
                            last_year_update = NULL
                        WHERE student_id = ?
                    ");
                    $stmt->execute([$prev_year, $prev_year_level, $student['student_id']]);
                }
                $rolled_back++;
            }
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'message' => "Successfully rolled back {$rolled_back} student promotions",
                'rolled_back' => $rolled_back
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Rollback error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Rollback failed: ' . $e->getMessage()
            ];
        }
    }
}

// Handle API requests
try {
    $api = new StudentPromotionAPI();
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'preview':
            echo json_encode($api->getPromotionPreview());
            break;
            
        case 'promote':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
                exit;
            }
            echo json_encode($api->promoteAllStudents());
            break;
            
        case 'rollback':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
                exit;
            }
            echo json_encode($api->rollbackPromotion());
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    error_log("Promotion API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>
