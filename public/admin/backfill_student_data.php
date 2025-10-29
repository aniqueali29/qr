<?php
/**
 * Backfill Student Data Script
 * Assign existing students to sessions and semesters
 */

require_once 'includes/config.php';

echo "Backfilling student data...\n";

try {
    // Backfill current_semester from textual year_level
    $stmt = $pdo->query("
        UPDATE students s
        LEFT JOIN (
          SELECT 'Semester 1' AS y, 1 AS n UNION ALL
          SELECT 'Semester 2', 2 UNION ALL
          SELECT 'Semester 3', 3 UNION ALL
          SELECT 'Semester 4', 4 UNION ALL
          SELECT 'Semester 5', 5 UNION ALL
          SELECT 'Semester 6', 6 UNION ALL
          SELECT 'Semester 7', 7 UNION ALL
          SELECT 'Semester 8', 8
        ) map ON TRIM(s.year_level) = map.y
        SET s.current_semester = COALESCE(s.current_semester, map.n)
    ");
    echo "Updated semesters: " . $stmt->rowCount() . "\n";

    // Backfill enrollment_session_id using admission_year
    $stmt = $pdo->query("
        UPDATE students st
        LEFT JOIN sessions sess
          ON sess.code = CONCAT(
              CASE 
                WHEN MONTH(COALESCE(st.created_at, STR_TO_DATE(CONCAT(st.admission_year,'-09-01'), '%Y-%m-%d'))) BETWEEN 1 AND 4 THEN 'SP'
                WHEN MONTH(COALESCE(st.created_at, STR_TO_DATE(CONCAT(st.admission_year,'-09-01'), '%Y-%m-%d'))) BETWEEN 5 AND 7 THEN 'SU'
                ELSE 'F'
              END,
              st.admission_year
            )
        SET st.enrollment_session_id = sess.id
        WHERE st.enrollment_session_id IS NULL AND st.admission_year IS NOT NULL
    ");
    echo "Updated sessions: " . $stmt->rowCount() . "\n";

    // Show final counts
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM students WHERE enrollment_session_id IS NOT NULL');
    $assigned = $stmt->fetch()['count'];
    
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM students WHERE current_semester IS NOT NULL');
    $semesters = $stmt->fetch()['count'];
    
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM students WHERE is_active = 1');
    $total = $stmt->fetch()['count'];

    echo "Final counts:\n";
    echo "- Total active students: $total\n";
    echo "- Students with sessions: $assigned\n";
    echo "- Students with semesters: $semesters\n";
    echo "Backfill completed!\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
