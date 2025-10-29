<?php
/**
 * Student Portal Helper Functions
 * Common utility functions for the student portal
 */

require_once 'config.php';

// getCurrentStudent() function is already defined in auth.php

// getStudentAttendanceStats() function is already defined in auth.php

// getStudentRecentAttendance() function is already defined in auth.php

// getStudentUpcomingEvents() function is already defined in auth.php

/**
 * Get system logo URL for student portal
 */
function getStudentSystemLogo() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'sidebar_logo'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && $result['setting_value']) {
            $logoPath = $result['setting_value']; // Use the path directly since we're in public/ directory
            // Check if file exists using absolute path
            // __DIR__ is: public/includes
            // We need: public/uploads/logos/filename.jpg
            $fullPath = __DIR__ . '/../' . $result['setting_value'];
            
            if (file_exists($fullPath)) {
                return $logoPath;
            } else {
                // Logo setting exists but file doesn't - clear it
                error_log("Student portal logo file not found: {$fullPath}");
                $clearStmt = $pdo->prepare("DELETE FROM system_settings WHERE setting_key = 'sidebar_logo'");
                $clearStmt->execute();
            }
        }
    } catch (Exception $e) {
        error_log("Student portal logo fetch error: " . $e->getMessage());
    }
    
    // Return null to use default SVG
    return null;
}

/**
 * Get project name for student portal
 */
function getStudentProjectName() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'project_name'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && $result['setting_value']) {
            return $result['setting_value'];
        }
    } catch (Exception $e) {
        error_log("Student portal project name fetch error: " . $e->getMessage());
    }
    
    return 'QR Attendance System';
}

/**
 * Get project short name for student portal
 */
function getStudentProjectShortName() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'project_short_name'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && $result['setting_value']) {
            return $result['setting_value'];
        }
    } catch (Exception $e) {
        error_log("Student portal project short name fetch error: " . $e->getMessage());
    }
    
    return 'QAS';
}

/**
 * Generate favicon from uploaded logo for student portal
 */
function generateStudentFaviconFromLogo($logoPath) {
    // Check if GD extension is available
    if (!extension_loaded('gd')) {
        error_log("GD extension not available for student favicon generation");
        return null;
    }
    
    if (!$logoPath || !file_exists($logoPath)) {
        return null;
    }
    
    try {
        // Create favicon directory if it doesn't exist
        $faviconDir = __DIR__ . '/../assets/img/favicon/';
        if (!is_dir($faviconDir)) {
            mkdir($faviconDir, 0755, true);
        }
        
        // Get image info
        $imageInfo = getimagesize($logoPath);
        if (!$imageInfo) {
            return null;
        }
        
        $mimeType = $imageInfo['mime'];
        
        // Create image resource based on type
        switch ($mimeType) {
            case 'image/jpeg':
            case 'image/jpg':
                $sourceImage = imagecreatefromjpeg($logoPath);
                break;
            case 'image/png':
                $sourceImage = imagecreatefrompng($logoPath);
                break;
            case 'image/gif':
                $sourceImage = imagecreatefromgif($logoPath);
                break;
            default:
                return null;
        }
        
        if (!$sourceImage) {
            return null;
        }
        
        // Create favicon sizes
        $sizes = [
            ['size' => 16, 'filename' => 'favicon-16x16.png'],
            ['size' => 32, 'filename' => 'favicon-32x32.png'],
            ['size' => 48, 'filename' => 'favicon-48x48.png'],
            ['size' => 64, 'filename' => 'favicon-64x64.png']
        ];
        
        $generatedFiles = [];
        
        foreach ($sizes as $favicon) {
            // Create resized image
            $resizedImage = imagecreatetruecolor($favicon['size'], $favicon['size']);
            
            // Preserve transparency for PNG
            if ($mimeType === 'image/png') {
                imagealphablending($resizedImage, false);
                imagesavealpha($resizedImage, true);
                $transparent = imagecolorallocatealpha($resizedImage, 255, 255, 255, 127);
                imagefilledrectangle($resizedImage, 0, 0, $favicon['size'], $favicon['size'], $transparent);
            }
            
            // Resize image
            imagecopyresampled(
                $resizedImage, $sourceImage,
                0, 0, 0, 0,
                $favicon['size'], $favicon['size'],
                imagesx($sourceImage), imagesy($sourceImage)
            );
            
            // Save PNG favicon
            $faviconPath = $faviconDir . $favicon['filename'];
            if (imagepng($resizedImage, $faviconPath)) {
                $generatedFiles[] = $favicon['filename'];
            }
            
            imagedestroy($resizedImage);
        }
        
        // Generate ICO file (16x16 and 32x32 combined)
        $icoPath = $faviconDir . 'favicon.ico';
        if (file_exists($faviconDir . 'favicon-16x16.png') && file_exists($faviconDir . 'favicon-32x32.png')) {
            // For now, we'll copy the 32x32 PNG as ICO (simplified approach)
            copy($faviconDir . 'favicon-32x32.png', $icoPath);
        }
        
        imagedestroy($sourceImage);
        
        return $generatedFiles;
        
    } catch (Exception $e) {
        error_log("Student favicon generation error: " . $e->getMessage());
        return null;
    }
}

/**
 * Get favicon URL for student portal
 */
function getStudentFaviconUrl() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'sidebar_logo'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && $result['setting_value']) {
            $logoPath = __DIR__ . '/../' . $result['setting_value'];
            
            if (file_exists($logoPath)) {
                // Generate favicon from logo (only if GD extension is available)
                if (extension_loaded('gd')) {
                    $generatedFiles = generateStudentFaviconFromLogo($logoPath);
                    
                    if ($generatedFiles && file_exists(__DIR__ . '/../assets/img/favicon/favicon-32x32.png')) {
                        // Return the 32x32 favicon
                        return 'assets/img/favicon/favicon-32x32.png';
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log("Student favicon fetch error: " . $e->getMessage());
    }
    
    // Return default favicon
    return 'assets/img/favicon/favicon.ico';
}

/**
 * Get student attendance chart data for the last 6 months
 */
function getStudentAttendanceChartData($student_id) {
    global $pdo;
    
    try {
        $chart_data = [];
        $months = [];
        
        // Get last 6 months
        for ($i = 5; $i >= 0; $i--) {
            $month = date('Y-m', strtotime("-$i months"));
            $months[] = date('M Y', strtotime("-$i months"));
            
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_days,
                    SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_days
                FROM attendance 
                WHERE student_id = ? AND DATE_FORMAT(timestamp, '%Y-%m') = ?
            ");
            $stmt->execute([$student_id, $month]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $percentage = $stats['total_days'] > 0 ? 
                ($stats['present_days'] / $stats['total_days']) * 100 : 0;
            
            $chart_data[] = round($percentage, 1);
        }
        
        return [
            'months' => $months,
            'percentages' => $chart_data
        ];
    } catch (PDOException $e) {
        error_log("Error fetching chart data: " . $e->getMessage());
        return [
            'months' => [],
            'percentages' => []
        ];
    }
}

/**
 * Get student performance summary
 */
function getStudentPerformanceSummary($student_id) {
    global $pdo;
    
    try {
        // For now, return mock data since assignment/quiz tables don't exist yet
        // This can be updated when those features are implemented
        
        return [
            'assignments' => [
                'total' => 0,
                'average_grade' => 0,
                'excellent_count' => 0
            ],
            'quizzes' => [
                'total' => 0,
                'average_score' => 0,
                'best_score' => 0
            ]
        ];
    } catch (PDOException $e) {
        error_log("Error fetching performance summary: " . $e->getMessage());
        return [
            'assignments' => ['total' => 0, 'average_grade' => 0, 'excellent_count' => 0],
            'quizzes' => ['total' => 0, 'average_score' => 0, 'best_score' => 0]
        ];
    }
}

// logStudentAction() function is already defined in config.php

/**
 * Format date for display
 */
function formatDate($date, $format = 'M d, Y') {
    return date($format, strtotime($date));
}

/**
 * Format time for display
 */
function formatTime($time, $format = 'H:i') {
    return $time ? date($format, strtotime($time)) : 'N/A';
}

/**
 * Get status badge class
 */
function getStatusBadgeClass($status) {
    switch (strtolower($status)) {
        case 'present':
            return 'success';
        case 'absent':
            return 'danger';
        case 'late':
            return 'warning';
        case 'excused':
            return 'info';
        default:
            return 'secondary';
    }
}

/**
 * Get status icon
 */
function getStatusIcon($status) {
    switch (strtolower($status)) {
        case 'present':
            return 'check';
        case 'absent':
            return 'x';
        case 'late':
            return 'time';
        case 'excused':
            return 'calendar';
        default:
            return 'help-circle';
    }
}

/**
 * Calculate days until due date
 */
function getDaysUntilDue($due_date) {
    $today = new DateTime();
    $due = new DateTime($due_date);
    $diff = $today->diff($due);
    
    if ($due < $today) {
        return -$diff->days; // Overdue
    }
    
    return $diff->days;
}

/**
 * Get urgency class for due dates
 */
function getUrgencyClass($due_date) {
    $days = getDaysUntilDue($due_date);
    
    if ($days < 0) {
        return 'danger'; // Overdue
    } elseif ($days <= 1) {
        return 'warning'; // Due today or tomorrow
    } elseif ($days <= 3) {
        return 'info'; // Due soon
    }
    
    return 'success'; // Not urgent
}
?>
