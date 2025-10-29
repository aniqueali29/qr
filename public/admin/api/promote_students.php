<?php
/**
 * Student Promotion API
 * Handles bulk promotion of students to next year
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../includes/academic.php';

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
     * Get max program years from central settings helper
     */
    private function getMaxYears() {
        try {
            return (int) academic_get_setting('max_program_years', 4);
        } catch (Exception $e) {
            return 4;
        }
    }
    
    /**
     * Get promotion preview - shows what will happen
     */
    public function getPromotionPreview() {
        try {
            $cfg = $this->getAcademicConfig();
            // Normalize data for semester mode to ensure labels are consistent
            $this->normalizeSemesterData($cfg);
            $spi = ($cfg['mode'] === 'semester') ? max(1,(int)$cfg['semesters_per_year']) : 1;
            $totalStages = (int)$cfg['total_stages'];

            // Build WHERE clause with filters
            $where = ["s.is_active = 1", "(s.is_graduated = 0 OR s.is_graduated IS NULL)"];
            $params = [];
            
            // Add filters
            if (!empty($_GET['session'])) {
                $where[] = "s.enrollment_session_id IN (SELECT id FROM enrollment_sessions WHERE code = ?)";
                $params[] = $_GET['session'];
            }
            if (!empty($_GET['current_semester'])) {
                $where[] = "s.current_semester = ?";
                $params[] = (int)$_GET['current_semester'];
            }
            if (!empty($_GET['program'])) {
                $where[] = "s.program = ?";
                $params[] = $_GET['program'];
            }
            
            $whereClause = implode(' AND ', $where);
            
            $stmt = $this->pdo->prepare("
                SELECT admission_year, current_year, year_level, current_semester
                FROM students s
                WHERE $whereClause
            ");
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $totals = [ 'total_active' => 0, 'will_promote' => 0, 'will_graduate' => 0 ];
            $summaryMap = [];

            foreach ($rows as $r) {
                $totals['total_active']++;
                
                // Use current_semester if available, otherwise compute from year_level
                $currentSem = !empty($r['current_semester']) ? (int)$r['current_semester'] : null;
                
                if ($currentSem !== null) {
                    // Use the current_semester directly
                    $stage = $currentSem - 1; // Convert to 0-based
                } else {
                    // Fallback to old calculation
                $stage = $this->computeStageForStudent($r, $cfg); // 0-based
                }
                
                $willGrad = ($stage + 1) >= $totalStages;
                if ($willGrad) $totals['will_graduate']++; else $totals['will_promote']++;

                if ($cfg['mode'] === 'semester') {
                    $year = (int)floor($stage / $spi) + 1;
                    $absSem = $stage + 1; // absolute semester number across the whole program
                    // Prefer absolute-name if provided; otherwise fallback to "Semester N"
                    $label = $cfg['semester_names'][$absSem-1] ?? ('Semester ' . $absSem);
                    $nextLabel = ($absSem + 1) > $totalStages
                        ? 'Graduate'
                        : ($cfg['semester_names'][$absSem] ?? ('Semester ' . ($absSem + 1)));
                    $key = (string)$absSem;
                    if (!isset($summaryMap[$key])) {
                        $summaryMap[$key] = [
                            'current_year' => $year,
                            'year_level' => $label,
                            'student_count' => 0,
                            'after_label' => $nextLabel,
                            'will_graduate' => ($absSem + 1) > $totalStages
                        ];
                    }
                    $summaryMap[$key]['student_count']++;
                } else {
                    $year = $stage + 1;
                    $key = (string)$year;
                    if (!isset($summaryMap[$key])) {
                        $summaryMap[$key] = [
                            'current_year' => $year,
                            'year_level' => $this->getYearLevel($year),
                            'student_count' => 0,
                            'after_label' => ($year >= $totalStages) ? 'Graduate' : ('Promote to Year ' . ($year+1)),
                            'will_graduate' => ($year >= $totalStages)
                        ];
                    }
                    $summaryMap[$key]['student_count']++;
                }
            }

            $summary = array_values($summaryMap);
            usort($summary, function($a,$b){
                if ($a['current_year'] === $b['current_year']) return strcmp($a['year_level'],$b['year_level']);
                return $a['current_year'] <=> $b['current_year'];
            });

            return [
                'success' => true,
                'summary' => $summary,
                'totals' => $totals,
                'max_years' => $cfg['max_program_years'],
                'mode' => $cfg['mode'],
                'semesters_per_year' => $spi,
                'semester_names' => $cfg['semester_names'],
                'total_stages' => $totalStages,
                'message' => 'Promotion preview generated'
            ];
            
        } catch (Throwable $e) {
            error_log("Promotion preview error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to generate preview: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Promote all eligible students (stage-based)
     * Keeps advancing by one stage from current label
     */
    public function promoteAllStudents() {
        try {
            $this->pdo->beginTransaction();
            
            $cfg = $this->getAcademicConfig();
            // Normalize prior to promotion so rows can advance correctly
            $this->normalizeSemesterData($cfg);
            $spi = ($cfg['mode'] === 'semester') ? max(1,(int)$cfg['semesters_per_year']) : 1;
            $totalStages = (int)$cfg['total_stages'];
            $today = date('Y-m-d');
            $promoted = 0;
            $graduated = 0;
            $errors = 0;
            $details = [];
            
            // Build WHERE clause with filters
            $where = ["s.is_active = 1", "(s.is_graduated = 0 OR s.is_graduated IS NULL)"];
            $params = [];
            
            // Add filters
            if (!empty($_POST['session'])) {
                $where[] = "s.enrollment_session_id IN (SELECT id FROM enrollment_sessions WHERE code = ?)";
                $params[] = $_POST['session'];
            }
            if (!empty($_POST['current_semester'])) {
                $where[] = "s.current_semester = ?";
                $params[] = (int)$_POST['current_semester'];
            }
            if (!empty($_POST['program'])) {
                $where[] = "s.program = ?";
                $params[] = $_POST['program'];
            }
            
            $whereClause = implode(' AND ', $where);
            
            // Get all active, non-graduated students with filters
            $stmt = $this->pdo->prepare("
                SELECT id, student_id, roll_number, name, admission_year, current_year, year_level
                FROM students s
                WHERE $whereClause
                ORDER BY roll_number
            ");
            $stmt->execute($params);
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($students as $student) {
                try {
                    $stage = $this->computeStageForStudent($student, $cfg); // 0-based
                    if (($stage + 1) >= $totalStages) {
                        // Graduate
                        $stmt = $this->pdo->prepare("
                            UPDATE students 
                            SET is_graduated = 1, 
                                is_active = 0,
                                last_year_update = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([$today, $student['id']]);
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
                        // Promote to next stage
                        $newStage = $stage + 1;
                        if ($cfg['mode'] === 'semester') {
                            $newYear = (int)floor($newStage / $spi) + 1;
                            $absSem = $newStage + 1; // absolute semester number across the whole program
                            $newLevel = $cfg['semester_names'][$absSem-1] ?? ('Semester ' . $absSem);
                        } else {
                            $newYear = ($student['current_year'] ? (int)$student['current_year'] : ($stage+1)) + 1;
                            $newLevel = $this->getYearLevel($newYear);
                            $absSem = $newYear; // For year mode, use year as semester equivalent
                        }
                        
                        $stmt = $this->pdo->prepare("
                            UPDATE students 
                            SET current_year = ?,
                                year_level = ?,
                                current_semester = ?,
                                last_year_update = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([$newYear, $newLevel, $absSem, $today, $student['id']]);
                        $promoted++;
                        
                        $details[] = [
                            'student_id' => $student['student_id'],
                            'roll_number' => $student['roll_number'],
                            'name' => $student['name'],
                            'action' => 'promoted',
                            'from' => $student['year_level'],
                            'to' => $newLevel
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
            
            // Commit if we made any changes; rollback only if nothing succeeded
            if (($promoted + $graduated) > 0) {
                $this->pdo->commit();
                // Log the promotion activity
                $this->logPromotionActivity($promoted, $graduated);
                $msg = "Promoted {$promoted}, Graduated {$graduated}." . ($errors > 0 ? " {$errors} records had errors." : "");
                return [
                    'success' => true,
                    'message' => $msg,
                    'promoted' => $promoted,
                    'graduated' => $graduated,
                    'errors' => $errors,
                    'details' => $details
                ];
            } else {
                $this->pdo->rollBack();
                return [
                    'success' => false,
                    'message' => "No students were updated. {$errors} errors occurred.",
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
     * Compute expected semester label based on current date (6-month cycle)
     */
    private function expectedSemesterFor(DateTime $now, $admission_year, $cfg) {
        $spi = max(1,(int)($cfg['semesters_per_year'] ?? 2));
        $totalStages = (int)$cfg['total_stages'];
        // year of study (1..max)
        $yos = max(1, (int) compute_year_of_study((int)$admission_year, $now));
        // current semester index in this academic year (0-based) from config months
        $ctx = determine_academic_context($now);
        $semIdx = (int)($ctx['current_semester_index'] ?? 0);
        $absSem = ($yos - 1) * $spi + $semIdx + 1; // 1-based absolute semester number
        if ($absSem > $totalStages) {
            return [ 'graduated' => true, 'label' => 'Graduated', 'current_year' => $yos ];
        }
        $label = $cfg['semester_names'][$absSem-1] ?? ('Semester ' . $absSem);
        $curYear = (int)floor(($absSem - 1) / $spi) + 1;
        return [ 'graduated' => false, 'label' => $label, 'current_year' => $curYear, 'current_semester' => $absSem ];
    }

    // --- Academic helpers for dynamic (Year/Semester) progression ---
    private function getAcademicConfig() {
        try {
            $cfg = get_academic_config();
            $cfg['max_program_years'] = $this->getMaxYears();
            $cfg['total_stages'] = ($cfg['mode'] === 'semester')
                ? ($cfg['max_program_years'] * max(1,(int)$cfg['semesters_per_year']))
                : $cfg['max_program_years'];
            return $cfg;
        } catch (Throwable $e) {
            return [
                'mode' => 'year',
                'max_program_years' => $this->getMaxYears(),
                'semesters_per_year' => 1,
                'semester_names' => ['Semester 1'],
                'total_stages' => $this->getMaxYears()
            ];
        }
    }

    private function parseSemesterIndex($label, $cfg) {
        foreach ((array)$cfg['semester_names'] as $i => $name) {
            if (strcasecmp(trim((string)$label), trim((string)$name)) === 0) return $i;
        }
        if (preg_match('/Semester\s*(\d+)/i', (string)$label, $m)) {
            $n = max(1, (int)$m[1]);
            return $n - 1;
        }
        return 0;
    }

    private function computeStageForStudent($student, $cfg) {
        if (($cfg['mode'] ?? 'year') === 'semester') {
            $spi = max(1,(int)$cfg['semesters_per_year']);
            // Prefer explicit current_year if present; otherwise derive from admission_year
            $year = isset($student['current_year']) && (int)$student['current_year'] > 0
                ? (int)$student['current_year']
                : (int) compute_year_of_study((int)($student['admission_year'] ?? date('Y')));
            $year = max(1, $year);
            $semIndex = $this->parseSemesterIndex($student['year_level'] ?? '', $cfg) % $spi;
            return ($year - 1) * $spi + $semIndex; // 0-based stage
        }
        $cy = isset($student['current_year']) && (int)$student['current_year'] > 0
            ? (int)$student['current_year']
            : (int) compute_year_of_study((int)($student['admission_year'] ?? date('Y')));
        return max(1,$cy) - 1; // 0-based stage
    }

    /**
     * Normalize student data for semester mode so promotions can advance
     */
    private function normalizeSemesterData($cfg) {
        if (($cfg['mode'] ?? 'year') !== 'semester') return;
        try {
            $spi = max(1,(int)($cfg['semesters_per_year'] ?? 2));
            // Ensure current_year baseline
            @$this->pdo->exec("UPDATE students SET current_year=1 WHERE (current_year IS NULL OR current_year=0) AND (is_graduated=0 OR is_graduated IS NULL)");
            // Map common year-level labels to the FIRST semester of that year
            // Year 1 -> Semester 1; Year 2 -> Semester 1 of year 2 (absolute Semester (spi+1)); etc.
            $mapYearToSem = function($year) use ($spi, $cfg) {
                $absSem = (($year - 1) * $spi) + 1;
                return $cfg['semester_names'][$absSem-1] ?? ('Semester ' . $absSem);
            };
            // Year 1 aliases -> Semester 1
            $stmt = $this->pdo->prepare("UPDATE students SET year_level=? WHERE (is_graduated=0 OR is_graduated IS NULL) AND (year_level IS NULL OR year_level='' OR year_level IN ('1st','First','Year 1','Year1'))");
            $stmt->execute([$mapYearToSem(1)]);
            // Year 2 aliases -> first semester of year 2
            $stmt = $this->pdo->prepare("UPDATE students SET year_level=? WHERE (is_graduated=0 OR is_graduated IS NULL) AND year_level IN ('2nd','Second','Year 2','Year2')");
            $stmt->execute([$mapYearToSem(2)]);
            // Year 3 aliases -> first semester of year 3
            $stmt = $this->pdo->prepare("UPDATE students SET year_level=? WHERE (is_graduated=0 OR is_graduated IS NULL) AND year_level IN ('3rd','Third','Year 3','Year3')");
            $stmt->execute([$mapYearToSem(3)]);
            // Year 4 aliases -> first semester of year 4
            $stmt = $this->pdo->prepare("UPDATE students SET year_level=? WHERE (is_graduated=0 OR is_graduated IS NULL) AND year_level IN ('4th','Fourth','Year 4','Year4')");
            $stmt->execute([$mapYearToSem(4)]);
            // Do NOT collapse unknown labels; preserve existing semester labels if set
        } catch (Throwable $e) {
            // Best-effort normalization; ignore errors
        }
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
     * Promote students by current date (6-month semester cycle)
     */
    public function promoteByDate() {
        try {
            $this->pdo->beginTransaction();
            $cfg = $this->getAcademicConfig();
            if (($cfg['mode'] ?? 'year') !== 'semester') {
                // Force semester logic anyway
                $cfg['mode'] = 'semester';
                $cfg['semesters_per_year'] = max(1,(int)($cfg['semesters_per_year'] ?? 2));
                $cfg['total_stages'] = (int)($cfg['max_program_years'] * $cfg['semesters_per_year']);
            }
            $now = new DateTime();
            $today = $now->format('Y-m-d');
            $promoted = 0; $graduated = 0; $unchanged = 0; $errors = 0; $details = [];
            
// Build WHERE clause with filters
            $where = ["s.is_active = 1"];
            $params = [];
            
            // Add filters
            if (!empty($_POST['session'])) {
                $where[] = "s.enrollment_session_id IN (SELECT id FROM enrollment_sessions WHERE code = ?)";
                $params[] = $_POST['session'];
            }
            if (!empty($_POST['current_semester'])) {
                $where[] = "s.current_semester = ?";
                $params[] = (int)$_POST['current_semester'];
            }
            if (!empty($_POST['program'])) {
                $where[] = "s.program = ?";
                $params[] = $_POST['program'];
            }
            
            $whereClause = implode(' AND ', $where);
            
$stmt = $this->pdo->prepare("SELECT id, student_id, roll_number, name, admission_year, current_year, year_level, is_graduated FROM students s WHERE $whereClause");
            $stmt->execute($params);
            $list = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($list as $s) {
                try {
                    if (!(int)$s['admission_year']) { $unchanged++; continue; }
                    $exp = $this->expectedSemesterFor($now, (int)$s['admission_year'], $cfg);
                    if ($exp['graduated']) {
                        if ((int)$s['is_graduated'] !== 1) {
$u = $this->pdo->prepare("UPDATE students SET is_graduated=1, is_active=0, last_year_update=? WHERE id=?");
                            $u->execute([$today, $s['id']]);
                            $graduated++;
                            $details[] = ['student_id'=>$s['student_id'],'action'=>'graduated','from'=>$s['year_level'],'to'=>'Graduated'];
                        } else { $unchanged++; }
                        continue;
                    }
                    // If already at expected label, skip
                    if (trim((string)$s['year_level']) === $exp['label'] && (int)$s['current_year'] === (int)$exp['current_year']) {
                        $unchanged++; continue;
                    }
$u = $this->pdo->prepare("UPDATE students SET current_year=?, year_level=?, current_semester=?, last_year_update=? WHERE id=?");
                    $u->execute([$exp['current_year'], $exp['label'], $exp['current_semester'], $today, $s['id']]);
                    $promoted++;
                    $details[] = ['student_id'=>$s['student_id'],'action'=>'set','from'=>$s['year_level'],'to'=>$exp['label']];
                } catch (Throwable $e) {
                    $errors++;
                    $details[] = ['student_id'=>$s['student_id'],'action'=>'error','error'=>$e->getMessage()];
                }
            }
            $this->pdo->commit();
            return [
                'success'=> true,
                'message'=> "Updated {$promoted}, Graduated {$graduated}, Unchanged {$unchanged}." . ($errors?" {$errors} errors.":''),
                'promoted'=> $promoted,
                'graduated'=> $graduated,
                'unchanged'=> $unchanged,
                'errors'=> $errors,
                'details'=> $details
            ];
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            return ['success'=>false,'message'=>'Promotion by date failed: '.$e->getMessage()];
        }
    }

    /**
     * Promote students by elapsed months (default 6 months per semester)
     */
    public function promoteByElapsedMonths() {
        try {
            $this->pdo->beginTransaction();
            $cfg = $this->getAcademicConfig();
            $spi = max(1,(int)($cfg['semesters_per_year'] ?? 2));
            $totalStages = (int)$cfg['total_stages'];
            $semLen = max(1,(int) academic_get_setting('semester_length_months', 6));
            $today = new DateTime();
            $todayStr = $today->format('Y-m-d');
            $promoted=0; $graduated=0; $unchanged=0; $errors=0; $details=[];

$stmt = $this->pdo->prepare("SELECT id, student_id, roll_number, name, admission_year, current_year, year_level, is_graduated, created_at, last_year_update FROM students WHERE is_active=1");
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $s) {
                try {
                    if ((int)$s['is_graduated'] === 1) { $unchanged++; continue; }
                    $stage = $this->computeStageForStudent($s, $cfg); // 0-based current stage
                    // Determine last change date
                    $last = null;
                    if (!empty($s['last_year_update'])) { $last = new DateTime($s['last_year_update']); }
                    elseif (!empty($s['created_at'])) { $last = new DateTime($s['created_at']); }
                    else {
                        $ysm = (int)($cfg['year_start_month'] ?? 9);
                        $last = new DateTime(sprintf('%04d-%02d-01', (int)($s['admission_year'] ?? date('Y')), $ysm));
                    }
                    $diff = $last->diff($today);
                    $months = $diff->y * 12 + $diff->m; // ignore days for simplicity
                    $incs = intdiv($months, $semLen);
                    if ($incs < 1) { $unchanged++; continue; }
                    $newStage = min($stage + $incs, $totalStages - 1);
                    if ($newStage === $stage) { $unchanged++; continue; }
                    if (($newStage + 1) >= $totalStages) {
                        // Graduate
$u = $this->pdo->prepare("UPDATE students SET is_graduated=1, is_active=0, last_year_update=? WHERE id=?");
                        $u->execute([$todayStr, $s['id']]);
                        $graduated++; $details[] = ['student_id'=>$s['student_id'],'action'=>'graduated','from'=>$s['year_level'],'to'=>'Graduated'];
                        continue;
                    }
                    $absSem = $newStage + 1;
                    $newYear = (int)floor($newStage / $spi) + 1;
                    $newLevel = $cfg['semester_names'][$absSem-1] ?? ('Semester ' . $absSem);
$u = $this->pdo->prepare("UPDATE students SET current_year=?, year_level=?, current_semester=?, last_year_update=? WHERE id=?");
                    $u->execute([$newYear, $newLevel, $absSem, $todayStr, $s['id']]);
                    $promoted++; $details[] = ['student_id'=>$s['student_id'],'action'=>'promoted','from'=>$s['year_level'],'to'=>$newLevel];
                } catch (Throwable $e) {
                    $errors++; $details[] = ['student_id'=>$s['student_id'],'action'=>'error','error'=>$e->getMessage()];
                }
            }
            $this->pdo->commit();
            return [
                'success'=> true,
                'message'=> "Promoted {$promoted}, Graduated {$graduated}, Unchanged {$unchanged}." . ($errors?" {$errors} errors.":''),
                'promoted'=> $promoted,
                'graduated'=> $graduated,
                'unchanged'=> $unchanged,
                'errors'=> $errors,
                'details'=> $details
            ];
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            return ['success'=>false,'message'=>'Promotion by elapsed months failed: '.$e->getMessage()];
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
SELECT id, student_id, roll_number, name, current_year, year_level, current_semester, is_graduated
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
WHERE id = ?
                    ");
                    $stmt->execute([$student['id']]);
                } else {
                    // Rollback promotion - use current_semester if available
                    $currentSem = !empty($student['current_semester']) ? (int)$student['current_semester'] : null;
                    
                    if ($currentSem !== null && $currentSem > 1) {
                        // Rollback semester-based
                        $prev_sem = $currentSem - 1;
                        $prev_year = (int)floor(($prev_sem - 1) / 2) + 1; // Assuming 2 semesters per year
                        $prev_year_level = "Semester " . $prev_sem;
                        
                        $stmt = $this->pdo->prepare("
                            UPDATE students 
                            SET current_year = ?,
                                year_level = ?,
                                current_semester = ?,
                                last_year_update = NULL
                            WHERE id = ?
                        ");
                        $stmt->execute([$prev_year, $prev_year_level, $prev_sem, $student['id']]);
                    } else {
                        // Fallback to year-based rollback
                    $prev_year = (int)$student['current_year'] - 1;
                    $prev_year_level = $this->getYearLevel($prev_year);
                    
                    $stmt = $this->pdo->prepare("
                        UPDATE students 
                        SET current_year = ?,
                            year_level = ?,
                            last_year_update = NULL
WHERE id = ?
                    ");
                    $stmt->execute([$prev_year, $prev_year_level, $student['id']]);
                    }
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
        case 'promote_by_date':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
                exit;
            }
            echo json_encode($api->promoteByDate());
            break;
        case 'promote_by_elapsed':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
                exit;
            }
            echo json_encode($api->promoteByElapsedMonths());
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
    
} catch (Throwable $e) {
    error_log("Promotion API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error', 'error' => $e->getMessage()]);
}
?>
