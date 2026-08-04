<?php
require_once 'db.php';


$db = (new Database())->getDb();
$courseId = isset($_GET['course_id']) ? $_GET['course_id'] : null;

try {
    // 1. Get all courses (for the dropdown selector)
    $stmtCourses = $db->query('SELECT id, name, code, "batchId" FROM courses ORDER BY code ASC');
    $courses = $stmtCourses->fetchAll();

    $data = ['courses' => $courses];

    // If a course is selected, fetch its specific data
    if ($courseId) {
        // Fetch the course to get its programId (via batch)
        $stmtC = $db->prepare('
            SELECT c.id, c.code, c.name, b."programId" 
            FROM courses c
            LEFT JOIN batches b ON c."batchId" = b.id
            WHERE c.id = :courseId
        ');
        $stmtC->execute(['courseId' => $courseId]);
        $courseObj = $stmtC->fetch();

        if ($courseObj) {
            $programId = $courseObj['programId'];

            // Fetch POs for this program
            $stmtPOs = $db->prepare('SELECT id, code, description FROM pos WHERE "programId" = :programId ORDER BY code ASC');
            $stmtPOs->execute(['programId' => $programId]);
            $pos = $stmtPOs->fetchAll();

            // Fetch COs for this course
            $stmtCOs = $db->prepare('SELECT id, code, description FROM cos WHERE "courseId" = :courseId ORDER BY code ASC');
            $stmtCOs->execute(['courseId' => $courseId]);
            $cos = $stmtCOs->fetchAll();

            // Fetch mappings
            $stmtMap = $db->prepare('SELECT "coId", "poId", level FROM co_po_mappings WHERE "courseId" = :courseId');
            $stmtMap->execute(['courseId' => $courseId]);
            $mappings = $stmtMap->fetchAll();

            $data['selectedCourse'] = [
                'details' => $courseObj,
                'pos' => $pos,
                'cos' => $cos,
                'mappings' => $mappings
            ];
        }
    }

    echo json_encode(['success' => true, 'data' => $data]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
}
?>
