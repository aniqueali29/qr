<?php
header('Content-Type: application/json');
http_response_code(410);
echo json_encode([
  'success' => false,
  'message' => 'Year progression is disabled. The system now uses semester-only progression.',
  'use' => '../api/promote_students.php?action=preview or action=promote'
]);
?>
