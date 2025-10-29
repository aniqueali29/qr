<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
http_response_code(410);
echo json_encode([
  'success' => false,
  'message' => 'Year progression is disabled. The system now uses semester-only progression.',
  'use' => 'admin/api/promote_students.php?action=preview or action=promote'
]);
?>
