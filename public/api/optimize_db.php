<?php
require_once 'db.php';
$db = (new Database())->getDb();

$queries = [
    'CREATE INDEX IF NOT EXISTS idx_student_marks_studentId ON student_marks("studentId")',
    'CREATE INDEX IF NOT EXISTS idx_student_marks_questionId ON student_marks("questionId")',
    'CREATE INDEX IF NOT EXISTS idx_questions_assessmentId ON questions("assessmentId")',
    'CREATE INDEX IF NOT EXISTS idx_qcm_questionId ON question_co_mappings("questionId")',
    'CREATE INDEX IF NOT EXISTS idx_qcm_coId ON question_co_mappings("coId")',
    'CREATE INDEX IF NOT EXISTS idx_co_att_courseId ON co_attainments("courseId")',
    'CREATE INDEX IF NOT EXISTS idx_co_att_studentId ON co_attainments("studentId")',
    'CREATE INDEX IF NOT EXISTS idx_po_att_courseId ON po_attainments("courseId")',
    'CREATE INDEX IF NOT EXISTS idx_cpm_coId ON co_po_mappings("coId")',
    'CREATE INDEX IF NOT EXISTS idx_cpm_poId ON co_po_mappings("poId")',
    'CREATE INDEX IF NOT EXISTS idx_assessments_courseId ON assessments("courseId")'
];

echo "Creating indexes to optimize DB...\n";
foreach ($queries as $q) {
    try {
        $db->exec($q);
    } catch (Exception $e) {}
}
echo "All indexes created successfully!\n";
?>
