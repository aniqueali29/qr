<?php
/**
 * Create Placeholder Sound Files
 * This script creates tiny silent MP3 files to prevent 404 errors
 * Replace with actual sound effects later
 */

// Minimal silent MP3 file (base64 encoded)
// This is a tiny (~1KB) silent MP3 file, 0.1 second duration
$silentMp3Base64 = '/+MYxAAEaAIEeUAQAgBgNgP/////KQQ/////Lvrg+lcWYHgtjadzsbTq+yREu495tq9c6v/7vt/of7mna9v6/btUnU17Jun9/+MYxCkT26KW+YGBAj9v6vUh+zab//v/96C3/oe/q/7Pt+lVx0pf///zv////+5hP////+MYxBQSM0sWWYI4A////03/////////////////////////////////////////////////////5P/////+MYxA8AAANIAcAAAP////////////////////////////////////////////////8';

// Decode base64 to binary
$mp3Data = base64_decode($silentMp3Base64);

// Create 4 placeholder sound files
$files = ['success.mp3', 'checkout.mp3', 'error.mp3', 'warning.mp3'];

foreach ($files as $file) {
    $filepath = __DIR__ . '/' . $file;
    if (!file_exists($filepath)) {
        file_put_contents($filepath, $mp3Data);
        echo "Created: $file\n";
    } else {
        echo "Already exists: $file\n";
    }
}

echo "\nPlaceholder sound files created successfully!\n";
echo "These are silent MP3 files. Replace with actual sound effects for production.\n";
?>

