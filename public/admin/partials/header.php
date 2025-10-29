<?php
/**
 * Admin Header Partial
 * Common head section with CSS and meta tags
 */

// Set default values if not defined
$pageTitle = $pageTitle ?? 'Admin Dashboard';
$pageCSS = $pageCSS ?? [];
$pageJS = $pageJS ?? [];
?>
<!doctype html>
<html lang="en" class="layout-menu-fixed layout-compact" data-assets-path="assets/" data-template="vertical-menu-template-free">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    
    <title><?php echo sanitizeOutput($pageTitle); ?> - <?php echo function_exists('getProjectShortName') ? getProjectShortName() : 'QR Attendance'; ?></title>
    <meta name="description" content="<?php echo function_exists('getProjectName') ? getProjectName() : 'QR Code Attendance System'; ?> - Admin Dashboard" />
    
    <!-- Favicon -->
    <link rel="icon" href="<?php echo getAdminFaviconUrl(); ?>" />
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet" />
    
    <link rel="stylesheet" href="<?php echo getAdminAssetUrl('vendor/fonts/iconify-icons.css'); ?>" />
    
    <!-- Core CSS -->
    <link rel="stylesheet" href="<?php echo getAdminAssetUrl('vendor/css/core.css'); ?>" />
    <link rel="stylesheet" href="<?php echo getAdminAssetUrl('css/demo.css'); ?>" />
    
    <!-- Vendors CSS -->
    <link rel="stylesheet" href="<?php echo getAdminAssetUrl('vendor/libs/perfect-scrollbar/perfect-scrollbar.css'); ?>" />
    
    <!-- UI Helpers (direct path, not in assets/) -->
    <link rel="stylesheet" href="css/ui-helpers.css" />
    
    <!-- Feature Enhancements -->
    <link rel="stylesheet" href="css/advanced-search.css" />
    <link rel="stylesheet" href="css/keyboard-shortcuts.css" />
    <link rel="stylesheet" href="css/mobile-optimize.css" />
    <link rel="stylesheet" href="css/print.css" media="print" />
    
    <!-- Page-specific CSS -->
    <?php foreach ($pageCSS as $css): ?>
        <link rel="stylesheet" href="<?php echo getAdminAssetUrl($css); ?>" />
    <?php endforeach; ?>
    
    <!-- Helpers -->
    <script src="<?php echo getAdminAssetUrl('vendor/js/helpers.js'); ?>"></script>
    <script src="<?php echo getAdminAssetUrl('js/config.js'); ?>"></script>
    <script src="js/ui-helpers.js"></script>
    
    <!-- Feature Enhancements -->
    <script src="js/advanced-search.js"></script>
    <script src="js/keyboard-shortcuts.js"></script>
    
    <!-- Toast Notification System -->
    <script src="<?php echo getAdminAssetUrl('js/toast-notifications.js?v=' . time()); ?>"></script>
</head>

<body>
    <!-- Layout wrapper -->
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
