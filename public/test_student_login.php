<?php
/**
 * Student Login Diagnostic Test
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Student Login Diagnostic</h1><pre>";

// Test 1: Config
echo "\n1. Testing config load...\n";
try {
    require_once 'includes/config.php';
    echo "✓ Config loaded\n";
    echo "  - PDO: " . (isset($pdo) ? "OK" : "MISSING") . "\n";
} catch (Exception $e) {
    echo "✗ Config failed: " . $e->getMessage() . "\n";
    die();
}

// Test 2: Auth
echo "\n2. Testing auth load...\n";
try {
    require_once 'includes/auth.php';
    echo "✓ Auth loaded\n";
    echo "  - isStudentLoggedIn function: " . (function_exists('isStudentLoggedIn') ? "EXISTS" : "MISSING") . "\n";
} catch (Exception $e) {
    echo "✗ Auth failed: " . $e->getMessage() . "\n";
    die();
}

// Test 3: Auth middleware
echo "\n3. Testing auth_middleware load...\n";
try {
    require_once __DIR__ . '/includes_ext/auth_middleware.php';
    echo "✓ AuthMiddleware loaded\n";
    echo "  - AuthMiddleware class: " . (class_exists('AuthMiddleware') ? "EXISTS" : "MISSING") . "\n";
} catch (Exception $e) {
    echo "✗ AuthMiddleware failed: " . $e->getMessage() . "\n";
}

// Test 4: Secure session
echo "\n4. Testing secure session load...\n";
try {
    require_once __DIR__ . '/includes_ext/secure_session.php';
    echo "✓ SecureSession loaded\n";
    echo "  - SecureSession class: " . (class_exists('SecureSession') ? "EXISTS" : "MISSING") . "\n";
} catch (Exception $e) {
    echo "✗ SecureSession failed: " . $e->getMessage() . "\n";
}

// Test 5: Check assets
echo "\n5. Testing asset files...\n";
$assets = [
    'assets/vendor/css/core.css',
    'assets/vendor/js/helpers.js',
    'assets/js/config.js',
    'assets/vendor/fonts/iconify-icons.css'
];

foreach ($assets as $asset) {
    $exists = file_exists($asset);
    echo ($exists ? "✓" : "✗") . " $asset" . ($exists ? "" : " - MISSING") . "\n";
}

echo "\n=== Diagnostic Complete ===\n";
echo "</pre>";
?>
