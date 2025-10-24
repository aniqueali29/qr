<?php
/**
 * Professional Student Attendance Card Portal
 * Generate print-ready student ID cards with QR codes
 */

require_once 'config.php';

$student_id = $_GET['student_id'] ?? '';

if (empty($student_id)) {
    http_response_code(400);
    die('Student ID required');
}

try {
    // Get QR code information
    $stmt = $pdo->prepare("
        SELECT qr_data, qr_image_path, generated_at 
        FROM qr_codes 
        WHERE student_id = ? AND is_active = 1 
        ORDER BY generated_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$student_id]);
    $qr = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$qr) {
        http_response_code(404);
        die('QR code not found');
    }
    
    // Get student information
    $stmt = $pdo->prepare("
        SELECT s.name, s.email, s.phone 
        FROM students s 
        WHERE s.student_id = ?
    ");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        http_response_code(404);
        die('Student not found');
    }
    
    // Generate QR code URL - use only student ID
    $qr_data_encoded = urlencode($student_id);
    $qr_size = 300;
    $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size={$qr_size}x{$qr_size}&data={$qr_data_encoded}&margin=10";
    
    // Render professional attendance card
    renderAttendanceCard($student, $student_id, $qr_url, $qr);
    
} catch (Exception $e) {
    http_response_code(500);
    die('Failed to generate card: ' . $e->getMessage());
}

function renderAttendanceCard($student, $student_id, $qr_url, $qr) {
    $current_year = date('Y');
    $card_id = 'AC' . strtoupper(substr(md5($student_id), 0, 8));
    
    header('Content-Type: text/html; charset=UTF-8');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Student Attendance Card - <?= htmlspecialchars($student['name']) ?></title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%);
                min-height: 100vh;
                padding: 20px;
                display: flex;
                justify-content: center;
                align-items: center;
            }
            
            .container {
                max-width: 1200px;
                width: 100%;
            }
            
            .controls {
                text-align: center;
                margin-bottom: 30px;
            }
            
            .print-btn {
                background: white;
                color: #1e3a8a;
                border: none;
                padding: 15px 40px;
                font-size: 16px;
                font-weight: 600;
                border-radius: 50px;
                cursor: pointer;
                box-shadow: 0 4px 15px rgba(0,0,0,0.2);
                transition: all 0.3s ease;
                margin: 0 10px;
            }
            
            .print-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(0,0,0,0.3);
            }
            
            .card-wrapper {
                display: flex;
                justify-content: center;
            }
            
            /* Standard ID Card Size: 3.375" x 2.125" (85.6mm x 54mm) */
            .attendance-card {
                width: 85.6mm;
                height: 55mm;
                background: white;
                border-radius: 12px;
                box-shadow: 0 10px 40px rgba(0,0,0,0.3);
                overflow: hidden;
                position: relative;
                display: flex;
                flex-direction: column;
            }
            
            .card-header {
                background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
                padding: 6px 8px;
                display: flex;
                align-items: center;
                gap: 6px;
                color: white;
            }
            
            .logo-section {
                width: 25px;
                height: 25px;
                background: white;
                border-radius: 4px;
                display: flex;
                align-items: center !important  ;
                justify-content: center !important;
                flex-shrink: 0;
                /* border: 2px solid #fbbf24; */
            }
            .logo-section img {
                width: 25px;
                height: 25px;
                display: flex;
                align-items: center !important  ;
                justify-content: center !important;
            }
            
            .logo-placeholder {
                font-size: 14px;
                font-weight: 800;
                color: #1e3a8a;
            }
            
            .header-text {
                flex: 1;
                text-align: center;
            }
            
            .institution-name {
                font-size: 9.5px;
                font-weight: 700;
                letter-spacing: 0.5px;
                margin-bottom: 1px;
                text-shadow: 0 1px 2px rgba(0,0,0,0.2);
            }
            
            .card-type {
                font-size: 7px;
                font-weight: 500;
                opacity: 0.95;
                text-transform: uppercase;
                letter-spacing: 0.3px;
            }
            
            .card-body {
                padding: 6px 10px;
                display: flex;
                gap: 8px;
                flex: 1;
            }
            
            .qr-section {
                width: 38mm;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
            }
            
            .qr-code-container {
                background: white;
                padding: 3px;
                border-radius: 6px;
                border: 2px solid #1e3a8a;
            }
            
            .qr-code-container img {
                display: block;
                width: 32mm;
                height: 32mm;
            }
            
            .student-info {
                flex: 1;
                display: flex;
                flex-direction: column;
                justify-content: center;
                gap: 3px;
            }
            
            .info-item {
                font-size: 7px;
                line-height: 1.3;
            }
            
            .info-label {
                color: #666;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.3px;
            }
            
            .info-value {
                color: #333;
                font-weight: 700;
                font-size: 8px;
                margin-top: 1px;
                word-wrap: break-word;
            }
            
            .student-name {
                font-size: 9.5px !important;
                color: #1e3a8a !important;
                font-weight: 800 !important;
                margin-bottom: 3px;
                line-height: 1.2;
            }
            
            .card-footer {
                background: #f8f9fa;
                padding: 3px 8px;
                font-size: 6px;
                color: #666;
                text-align: center;
                border-top: 1px solid #e0e0e0;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .scan-text {
                font-size: 5.5px;
                color: #1e3a8a;
                font-weight: 600;
                text-align: center;
                margin-top: 2px;
            }
            
            /* Print Styles */
            @media print {
                body {
                    background: white;
                    padding: 0;
                }
                
                .controls {
                    display: none;
                }
                
                .container {
                    max-width: none;
                }
                
                .card-wrapper {
                    page-break-inside: avoid;
                }
                
                .attendance-card {
                    box-shadow: none;
                    border: 1px solid #ddd;
                    page-break-inside: avoid;
                }
            }
            
            @page {
                size: A4;
                margin: 15mm;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="controls">
                <button class="print-btn" onclick="window.print()">
                    🖨️ Print Attendance Card
                </button>
            </div>
            
            <div class="card-wrapper">
                <div class="attendance-card">
                    <div class="card-header">
                        <div class="logo-section">
                            <div class="logo-placeholder"><img src="../assets/img/logo.jpeg" alt="JPI" style="width: 20px; height: 20px;"></div>
                        </div>
                        <div class="header-text">
                            <div class="institution-name">JINNAH POLYTECHNIC INSTITUTE</div>
                            <div class="card-type">Student Attendance Card</div>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <div class="qr-section">
                            <div>
                                <div class="qr-code-container">
                                    <img src="<?= $qr_url ?>" alt="QR Code" />
                                </div>
                                <div class="scan-text">SCAN FOR ATTENDANCE</div>
                            </div>
                        </div>
                        
                        <div class="student-info">
                            <div class="info-item">
                                <div class="info-value student-name"><?= htmlspecialchars($student['name']) ?></div>
                            </div>
                            
                            <div class="info-item">
                                <div class="info-label">Student ID</div>
                                <div class="info-value"><?= htmlspecialchars($student_id) ?></div>
                            </div>
                            
                            <div class="info-item">
                                <div class="info-label">Email</div>
                                <div class="info-value"><?= htmlspecialchars($student['email']) ?></div>
                            </div>
                            
                            <div class="info-item">
                                <div class="info-label">Phone</div>
                                <div class="info-value"><?= htmlspecialchars($student['phone']) ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-footer">
                        <span>Valid: <?= $current_year ?>-<?= $current_year + 1 ?></span>
                        <span>Card ID: <?= $card_id ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
            window.addEventListener('load', function() {
            });
        </script>
    </body>
    </html>
    <?php
}
?>