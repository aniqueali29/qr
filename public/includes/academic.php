<?php
/**
 * Academic configuration utilities
 * Centralized helpers for academic year/semester settings and computations
 */

if (!isset($pdo)) {
    // Load default config only if PDO not already provided by caller (avoid function redeclare clashes)
    @require_once __DIR__ . '/config.php';
}

/**
 * Fetch a system setting value from database, with default fallback
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function academic_get_setting($key, $default = null) {
    try {
        global $pdo;
        if (!$pdo) return $default;
        $stmt = $pdo->prepare("SELECT setting_value, setting_type FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return $default;
        $val = $row['setting_value'];
        $type = $row['setting_type'];
        switch ($type) {
            case 'integer': return (int)$val;
            case 'boolean': return in_array(strtolower($val), ['1','true','yes'], true);
            case 'json':
                $decoded = json_decode($val, true);
                return $decoded !== null ? $decoded : $default;
            default: return $val;
        }
    } catch (Throwable $e) {
        error_log('academic_get_setting error: ' . $e->getMessage());
        return $default;
    }
}

/**
 * Get the academic configuration as an array
 * Keys: mode, year_start_month, semesters_per_year, semester_names, semester_start_months
 */
function get_academic_config() {
    // Force semester-only mode regardless of DB setting
    $mode = 'semester';
    $year_start_month = (int) academic_get_setting('academic_year_start_month', 9); // 1-12
    $semesters_per_year = (int) academic_get_setting('semesters_per_year', 2);
    $semester_names = academic_get_setting('semester_names', [ 'Semester 1', 'Semester 2' ]);
    if (is_string($semester_names)) {
        $decoded = json_decode($semester_names, true);
        if (is_array($decoded)) $semester_names = $decoded; else $semester_names = [ 'Semester 1', 'Semester 2' ];
    }
    $semester_start_months = academic_get_setting('semester_start_months', [ $year_start_month, ((($year_start_month+5)-1)%12)+1 ]);
    if (is_string($semester_start_months)) {
        $decoded = json_decode($semester_start_months, true);
        if (is_array($decoded)) $semester_start_months = $decoded; else $semester_start_months = [ $year_start_month, ((($year_start_month+5)-1)%12)+1 ];
    }
    // Normalize arrays to length
    $semester_names = array_values(array_slice($semester_names, 0, max(1,$semesters_per_year)));
    while (count($semester_names) < $semesters_per_year) { $semester_names[] = 'Semester ' . (count($semester_names)+1); }
    $semester_start_months = array_values(array_slice($semester_start_months, 0, max(1,$semesters_per_year)));
    while (count($semester_start_months) < $semesters_per_year) { $semester_start_months[] = $year_start_month; }

    return [
        'mode' => $mode,
        'year_start_month' => $year_start_month,
        'semesters_per_year' => $semesters_per_year,
        'semester_names' => $semester_names,
        'semester_start_months' => $semester_start_months,
    ];
}

/**
 * Determine academic context for a given date
 * Returns: [ academic_year_label, academic_year_start, academic_year_end, current_semester_index, current_semester_name ]
 */
function determine_academic_context(DateTime $date = null) {
    if ($date === null) $date = new DateTime();
    $cfg = get_academic_config();
    $y = (int)$date->format('Y');
    $m = (int)$date->format('n');
    $start_m = (int)$cfg['year_start_month'];

    // Compute academic year start
    if ($m >= $start_m) {
        $start_year = $y;
    } else {
        $start_year = $y - 1;
    }
    $start = new DateTime(sprintf('%04d-%02d-01 00:00:00', $start_year, $start_m));
    $end = (clone $start)->modify('+1 year')->modify('-1 day');
    $label = $start->format('Y') . '-' . substr($end->format('Y'), -2);

    $sem_index = null;
    $sem_name = null;
    if ($cfg['mode'] === 'semester') {
        // Map month to semester index based on configured start months
        $starts = $cfg['semester_start_months'];
        $bestIdx = 0; $bestDelta = 13; // minimal positive month distance
        foreach ($starts as $idx => $sm) {
            // distance in months from semester start (cyclic 12 months)
            $delta = ($m - (int)$sm + 12) % 12;
            if ($delta < $bestDelta) { $bestDelta = $delta; $bestIdx = $idx; }
        }
        $sem_index = $bestIdx; // 0-based
        $sem_name = $cfg['semester_names'][$bestIdx] ?? ('Semester ' . ($bestIdx+1));
    }

    return [
        'academic_year_label' => $label,
        'academic_year_start' => $start,
        'academic_year_end' => $end,
        'current_semester_index' => $sem_index,
        'current_semester_name' => $sem_name,
        'config' => $cfg,
    ];
}

/**
 * Compute year of study given admission year and current date based on academic year start
 * Caps between 1..4 by default (can be adjusted by setting max_program_years if present)
 */
function compute_year_of_study($admission_year, DateTime $date = null) {
    if ($date === null) $date = new DateTime();
    $ctx = determine_academic_context($date);
    $start_year = (int)$ctx['academic_year_start']->format('Y');
    $year = max(1, ($start_year - (int)$admission_year) + 1);
    $max_years = (int) academic_get_setting('max_program_years', 4);
    return min($year, $max_years);
}
