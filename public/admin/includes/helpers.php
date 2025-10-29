<?php
/**
 * Admin Helper Functions
 * Utility functions for admin dashboard
 */

require_once 'config.php';

/**
 * Render alert/toast message
 */
function renderAlert($message, $type = 'info', $dismissible = true) {
    $alertClass = "alert-$type";
    $dismissibleClass = $dismissible ? 'alert-dismissible fade show' : '';
    $dismissButton = $dismissible ? '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' : '';
    
    return "
    <div class=\"alert $alertClass $dismissibleClass\" role=\"alert\">
        $message
        $dismissButton
    </div>";
}

/**
 * Render success message
 */
function renderSuccess($message) {
    return renderAlert($message, 'success');
}

/**
 * Render error message
 */
function renderError($message) {
    return renderAlert($message, 'danger');
}

/**
 * Render warning message
 */
function renderWarning($message) {
    return renderAlert($message, 'warning');
}

/**
 * Render info message
 */
function renderInfo($message) {
    return renderAlert($message, 'info');
}

/**
 * Redirect to page
 */
function redirectTo($page, $params = []) {
    $url = $page;
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    header("Location: $url");
    exit();
}

/**
 * Get current page name
 */
function getCurrentPage() {
    return basename($_SERVER['PHP_SELF'], '.php');
}

/**
 * Check if current page is active
 */
function isPageActive($page) {
    return getCurrentPage() === $page;
}

/**
 * Generate breadcrumb
 */
function generateBreadcrumb($items) {
    $breadcrumb = '<nav aria-label="breadcrumb"><ol class="breadcrumb">';
    
    foreach ($items as $index => $item) {
        $isLast = ($index === count($items) - 1);
        $activeClass = $isLast ? 'active' : '';
        $href = $isLast ? '' : 'href="' . $item['url'] . '"';
        
        $breadcrumb .= "<li class=\"breadcrumb-item $activeClass\" $href>";
        if (!$isLast) {
            $breadcrumb .= '<a href="' . $item['url'] . '">' . sanitizeOutput($item['title']) . '</a>';
        } else {
            $breadcrumb .= sanitizeOutput($item['title']);
        }
        $breadcrumb .= '</li>';
    }
    
    $breadcrumb .= '</ol></nav>';
    return $breadcrumb;
}

/**
 * Format date for display
 */
function formatDate($date, $format = 'M d, Y') {
    if (empty($date)) return '-';
    return date($format, strtotime($date));
}

/**
 * Format datetime for display
 */
function formatDateTime($datetime, $format = 'M d, Y H:i') {
    if (empty($datetime)) return '-';
    return date($format, strtotime($datetime));
}

/**
 * Format time for display
 */
function formatTime($time, $format = 'H:i') {
    if (empty($time)) return '-';
    return date($format, strtotime($time));
}

/**
 * Format file size
 */
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}

/**
 * Generate pagination
 */
function generatePagination($currentPage, $totalPages, $baseUrl, $params = []) {
    if ($totalPages <= 1) return '';
    
    $pagination = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center">';
    
    // Previous button
    if ($currentPage > 1) {
        $prevPage = $currentPage - 1;
        $prevUrl = $baseUrl . '?' . http_build_query(array_merge($params, ['page' => $prevPage]));
        $pagination .= "<li class=\"page-item\"><a class=\"page-link\" href=\"$prevUrl\">Previous</a></li>";
    } else {
        $pagination .= '<li class="page-item disabled"><span class="page-link">Previous</span></li>';
    }
    
    // Page numbers
    $start = max(1, $currentPage - 2);
    $end = min($totalPages, $currentPage + 2);
    
    if ($start > 1) {
        $url = $baseUrl . '?' . http_build_query(array_merge($params, ['page' => 1]));
        $pagination .= "<li class=\"page-item\"><a class=\"page-link\" href=\"$url\">1</a></li>";
        if ($start > 2) {
            $pagination .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }
    
    for ($i = $start; $i <= $end; $i++) {
        $activeClass = ($i === $currentPage) ? 'active' : '';
        $url = $baseUrl . '?' . http_build_query(array_merge($params, ['page' => $i]));
        $pagination .= "<li class=\"page-item $activeClass\"><a class=\"page-link\" href=\"$url\">$i</a></li>";
    }
    
    if ($end < $totalPages) {
        if ($end < $totalPages - 1) {
            $pagination .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        $url = $baseUrl . '?' . http_build_query(array_merge($params, ['page' => $totalPages]));
        $pagination .= "<li class=\"page-item\"><a class=\"page-link\" href=\"$url\">$totalPages</a></li>";
    }
    
    // Next button
    if ($currentPage < $totalPages) {
        $nextPage = $currentPage + 1;
        $nextUrl = $baseUrl . '?' . http_build_query(array_merge($params, ['page' => $nextPage]));
        $pagination .= "<li class=\"page-item\"><a class=\"page-link\" href=\"$nextUrl\">Next</a></li>";
    } else {
        $pagination .= '<li class="page-item disabled"><span class="page-link">Next</span></li>';
    }
    
    $pagination .= '</ul></nav>';
    return $pagination;
}

/**
 * Generate table actions
 */
function generateTableActions($id, $actions = []) {
    $html = '<div class="dropdown">
        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
            Actions
        </button>
        <ul class="dropdown-menu">';
    
    foreach ($actions as $action) {
        $href = $action['url'] ?? '#';
        $onclick = isset($action['onclick']) ? 'onclick="' . $action['onclick'] . '"' : '';
        $class = isset($action['class']) ? 'class="' . $action['class'] . '"' : '';
        
        $html .= "<li><a $class href=\"$href\" $onclick>" . sanitizeOutput($action['label']) . "</a></li>";
    }
    
    $html .= '</ul></div>';
    return $html;
}

/**
 * Generate status badge
 */
function generateStatusBadge($status, $type = 'default') {
    $badgeClass = "badge bg-$type";
    return "<span class=\"$badgeClass\">" . sanitizeOutput($status) . "</span>";
}

/**
 * Generate progress bar
 */
function generateProgressBar($percentage, $label = '') {
    $percentage = max(0, min(100, $percentage));
    $label = $label ?: $percentage . '%';
    
    return "
    <div class=\"progress\" style=\"height: 20px;\">
        <div class=\"progress-bar\" role=\"progressbar\" style=\"width: $percentage%\" aria-valuenow=\"$percentage\" aria-valuemin=\"0\" aria-valuemax=\"100\">
            $label
        </div>
    </div>";
}

/**
 * Generate modal
 */
function generateModal($id, $title, $content, $footer = '') {
    return "
    <div class=\"modal fade\" id=\"$id\" tabindex=\"-1\">
        <div class=\"modal-dialog\">
            <div class=\"modal-content\">
                <div class=\"modal-header\">
                    <h5 class=\"modal-title\">$title</h5>
                    <button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"modal\"></button>
                </div>
                <div class=\"modal-body\">
                    $content
                </div>
                <div class=\"modal-footer\">
                    $footer
                </div>
            </div>
        </div>
    </div>";
}

/**
 * Generate form field
 */
function generateFormField($type, $name, $label, $value = '', $options = []) {
    $required = isset($options['required']) && $options['required'] ? 'required' : '';
    $placeholder = isset($options['placeholder']) ? 'placeholder="' . $options['placeholder'] . '"' : '';
    $class = isset($options['class']) ? 'class="' . $options['class'] . '"' : '';
    
    $html = "<div class=\"mb-3\">
        <label for=\"$name\" class=\"form-label\">$label</label>";
    
    switch ($type) {
        case 'text':
        case 'email':
        case 'password':
        case 'number':
            $html .= "<input type=\"$type\" id=\"$name\" name=\"$name\" value=\"$value\" $required $placeholder $class>";
            break;
            
        case 'textarea':
            $rows = isset($options['rows']) ? $options['rows'] : 3;
            $html .= "<textarea id=\"$name\" name=\"$name\" rows=\"$rows\" $required $placeholder $class>$value</textarea>";
            break;
            
        case 'select':
            $html .= "<select id=\"$name\" name=\"$name\" $required $class>";
            if (isset($options['options'])) {
                foreach ($options['options'] as $optValue => $optLabel) {
                    $selected = ($value == $optValue) ? 'selected' : '';
                    $html .= "<option value=\"$optValue\" $selected>$optLabel</option>";
                }
            }
            $html .= "</select>";
            break;
    }
    
    if (isset($options['help'])) {
        $html .= "<div class=\"form-text\">" . $options['help'] . "</div>";
    }
    
    $html .= "</div>";
    return $html;
}

/**
 * Validate email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate student ID format
 */
function isValidStudentId($student_id) {
    return preg_match('/^[A-Z0-9-]+$/', $student_id) && strlen($student_id) >= 5;
}


/**
 * Get time ago string
 */
function getTimeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    if ($time < 31536000) return floor($time/2592000) . ' months ago';
    return floor($time/31536000) . ' years ago';
}

/**
 * Check if a module is enabled
 */
function isModuleEnabled($moduleKey) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$moduleKey]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && isset($result['setting_value'])) {
            return $result['setting_value'] === 'enabled' || $result['setting_value'] === '1' || $result['setting_value'] === 'true';
        }
        
        // Default to enabled if setting doesn't exist
        return true;
    } catch (Exception $e) {
        // Default to enabled if error occurs
        return true;
    }
}

/**
 * Require module to be enabled or redirect
 */
function requireModuleEnabled($moduleKey, $redirectTo = 'index.php') {
    if (!isModuleEnabled($moduleKey)) {
        header('Location: ' . $redirectTo . '?error=module_disabled&module=' . $moduleKey);
        exit();
    }
    
    // Check if user has access to this page (unless superadmin)
    $pageUrl = basename($_SERVER['PHP_SELF']);
    if (!isSuperAdmin() && !staffHasAccess($pageUrl)) {
        header('Location: ' . $redirectTo . '?error=access_denied');
        exit();
    }
}

/**
 * Check if current user is Super Admin
 */
function isSuperAdmin() {
    $currentUser = getAdminUser();
    if (!$currentUser) {
        return false;
    }
    
    // Super Admin is only users with role = 'superadmin'
    return $currentUser['role'] === 'superadmin';
}

/**
 * Check if staff has access to a page
 */
function staffHasAccess($pageUrl) {
    global $pdo;
    
    $currentUser = getAdminUser();
    if (!$currentUser) {
        return false;
    }
    
    // Super admin has access to everything
    if (isSuperAdmin()) {
        return true;
    }
    
    // Check if staff user has permission
    try {
        $stmt = $pdo->prepare("SELECT id FROM staff_permissions WHERE user_id = ? AND page_url = ?");
        $stmt->execute([$currentUser['id'], $pageUrl]);
        return $stmt->fetch() !== false;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Require staff access or redirect
 */
function requireStaffAccess($pageUrl, $redirectTo = 'index.php') {
    if (!staffHasAccess($pageUrl)) {
        header('Location: ' . $redirectTo . '?error=access_denied');
        exit();
    }
}

/**
 * Get user's profile picture URL
 */
function getUserProfilePicture($userId = null) {
    global $pdo;
    
    if (!$userId) {
        if (!isset($_SESSION['admin_user_id'])) {
            return getAdminAssetUrl('img/avatars/1.png');
        }
        $userId = $_SESSION['admin_user_id'];
    }
    
    try {
        $stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $picData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($picData && $picData['profile_picture']) {
            // Database already contains full path like: uploads/profile_pictures/filename.jpg
            return '../' . $picData['profile_picture'];
        }
    } catch (Exception $e) {
        error_log("Profile picture fetch error: " . $e->getMessage());
    }
    
    return getAdminAssetUrl('img/avatars/1.png');
}

/**
 * Get system logo URL
 */
function getSystemLogo() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'sidebar_logo'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && $result['setting_value']) {
            $logoPath = '../' . $result['setting_value'];
            // Check if file exists using absolute path
            // __DIR__ is: public/admin/includes
            // We need: public/uploads/logos/filename.jpg
            $fullPath = __DIR__ . '/../../../public/' . $result['setting_value'];
            
            if (file_exists($fullPath)) {
                return $logoPath;
            } else {
                // Logo setting exists but file doesn't - clear it
                error_log("Logo file not found: {$fullPath}");
                $clearStmt = $pdo->prepare("DELETE FROM system_settings WHERE setting_key = 'sidebar_logo'");
                $clearStmt->execute();
            }
        }
    } catch (Exception $e) {
        error_log("Logo fetch error: " . $e->getMessage());
    }
    
    // Return null to use default SVG
    return null;
}

/**
 * Get project name
 */
function getProjectName() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'project_name'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && $result['setting_value']) {
            return $result['setting_value'];
        }
    } catch (Exception $e) {
        error_log("Project name fetch error: " . $e->getMessage());
    }
    
    return 'QR Attendance System';
}

/**
 * Generate favicon from uploaded logo
 */
function generateFaviconFromLogo($logoPath) {
    // Check if GD extension is available
    if (!extension_loaded('gd')) {
        error_log("GD extension not available for favicon generation");
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
        error_log("Favicon generation error: " . $e->getMessage());
        return null;
    }
}

/**
 * Get favicon URL for admin dashboard
 */
function getAdminFaviconUrl() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'sidebar_logo'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && $result['setting_value']) {
            $logoPath = __DIR__ . '/../../../public/' . $result['setting_value'];
            
            if (file_exists($logoPath)) {
                // Generate favicon from logo (only if GD extension is available)
                if (extension_loaded('gd')) {
                    $generatedFiles = generateFaviconFromLogo($logoPath);
                    
                    if ($generatedFiles && file_exists(__DIR__ . '/../assets/img/favicon/favicon-32x32.png')) {
                        // Return the 32x32 favicon
                        return '../assets/img/favicon/favicon-32x32.png';
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log("Admin favicon fetch error: " . $e->getMessage());
    }
    
    // Return default favicon
    return '../assets/img/favicon/favicon.ico';
}

/**
 * Get project short name
 */
function getProjectShortName() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'project_short_name'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && $result['setting_value']) {
            return $result['setting_value'];
        }
    } catch (Exception $e) {
        error_log("Project short name fetch error: " . $e->getMessage());
    }
    
    return 'QAS';
}

?>
