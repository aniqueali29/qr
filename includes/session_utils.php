<?php
/**
 * Session utilities: compute codes, validate terms, find/create sessions
 */

if (!function_exists('db_pdo')) {
    function db_pdo() {
        global $pdo; return $pdo;
    }
}

function session_term_from_month(int $month): string {
    if ($month >= 1 && $month <= 4) return 'Spring';
    if ($month >= 5 && $month <= 7) return 'Summer';
    if ($month >= 8 && $month <= 12) return 'Fall';
    return 'Fall';
}

function session_code(string $term, int $year): string {
    $map = ['Spring' => 'SP', 'Summer' => 'SU', 'Fall' => 'F', 'Winter' => 'W'];
    $p = $map[$term] ?? 'F';
    return $p . $year;
}

function get_session_by_code(string $code) {
    $stmt = db_pdo()->prepare('SELECT * FROM sessions WHERE code = ? LIMIT 1');
    $stmt->execute([$code]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function get_or_create_session(string $term, int $year, ?string $label = null, ?string $start = null, ?string $end = null) {
    $term = ucfirst(strtolower($term));
    if (!in_array($term, ['Spring','Summer','Fall','Winter'], true)) {
        throw new InvalidArgumentException('Invalid session term');
    }
    $code = session_code($term, $year);
    $existing = get_session_by_code($code);
    if ($existing) return $existing;
    $label = $label ?: ($term . ' ' . $year);
    $stmt = db_pdo()->prepare('INSERT INTO sessions (code, label, term, year, start_date, end_date) VALUES (?,?,?,?,?,?)');
    $stmt->execute([$code, $label, $term, $year, $start, $end]);
    $id = db_pdo()->lastInsertId();
    return ['id' => (int)$id, 'code' => $code, 'label' => $label, 'term' => $term, 'year' => $year, 'start_date' => $start, 'end_date' => $end];
}

function list_sessions(array $opts = []) : array {
    $sql = 'SELECT id, code, label, term, year, start_date, end_date, is_active FROM sessions ORDER BY year DESC, FIELD(term,\'Spring\',\'Summer\',\'Fall\',\'Winter\')';
    $stmt = db_pdo()->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function infer_enrollment_session_for_student(array $student): ?int {
    // Use created_at month if available, else assume Fall of admission_year
    $year = (int)($student['admission_year'] ?? 0);
    if ($year <= 0) return null;
    $month = null;
    if (!empty($student['created_at'])) {
        $t = strtotime($student['created_at']);
        if ($t !== false) $month = (int)date('n', $t);
    }
    if ($month === null) $month = 9; // default to September
    $term = session_term_from_month($month);
    $code = session_code($term, $year);
    $sess = get_session_by_code($code);
    if ($sess) return (int)$sess['id'];
    $sess = get_or_create_session($term, $year);
    return (int)$sess['id'];
}
