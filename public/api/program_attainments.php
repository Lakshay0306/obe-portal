<?php
require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];
$db = (new Database())->getDb();

if ($method === 'GET') {
    $programId = $_GET['programId'] ?? '';
    $batchId = $_GET['batchId'] ?? '';

    if (!$programId) {
        echo json_encode(['success' => false, 'message' => 'Missing programId']);
        exit;
    }

    try {
        // 1. Get POs for this program
        $stmtPo = $db->prepare('SELECT id, code, description FROM pos WHERE "programId" = :programId ORDER BY code ASC');
        $stmtPo->execute(['programId' => $programId]);
        $pos = $stmtPo->fetchAll(PDO::FETCH_ASSOC);

        // 2. Get Courses for this program (filtered by batch if provided)
        $courseQuery = 'SELECT id, code, name FROM courses WHERE "programId" = :programId';
        $courseParams = ['programId' => $programId];
        
        // In this schema, courses might belong to a program, and enrollments/students belong to a batch.
        // Actually courses might not directly link to batch, but let's just get all courses for program.
        $stmtCourse = $db->prepare($courseQuery . ' ORDER BY code ASC');
        $stmtCourse->execute($courseParams);
        $courses = $stmtCourse->fetchAll(PDO::FETCH_ASSOC);

        // 3. Get PO-CO Mapping Averages per Course
        // A mapping level between Course and PO is usually the average of mapping levels of its COs to that PO.
        $stmtMapping = $db->prepare('
            SELECT c.id as course_id, cp."poId", AVG(cp.level) as avg_mapping
            FROM courses c
            JOIN cos co ON c.id = co."courseId"
            JOIN co_po_mappings cp ON co.id = cp."coId"
            WHERE c."programId" = :programId
            GROUP BY c.id, cp."poId"
        ');
        $stmtMapping->execute(['programId' => $programId]);
        $mappings = $stmtMapping->fetchAll(PDO::FETCH_ASSOC);

        // 4. Get PO Attainments per Course
        $stmtAttainment = $db->prepare('
            SELECT c.id as course_id, cpa."poId", cpa."attainmentLevel"
            FROM courses c
            JOIN course_po_attainments cpa ON c.id = cpa."courseId"
            WHERE c."programId" = :programId
        ');
        $stmtAttainment->execute(['programId' => $programId]);
        $attainments = $stmtAttainment->fetchAll(PDO::FETCH_ASSOC);

        // Process data into matrices
        $mappingMatrix = [];
        $attainmentMatrix = [];

        foreach ($courses as $c) {
            $mappingMatrix[$c['id']] = [];
            $attainmentMatrix[$c['id']] = [];
            foreach ($pos as $p) {
                $mappingMatrix[$c['id']][$p['id']] = '-';
                $attainmentMatrix[$c['id']][$p['id']] = '-';
            }
        }

        foreach ($mappings as $m) {
            if (isset($mappingMatrix[$m['course_id']][$m['poId']])) {
                $mappingMatrix[$m['course_id']][$m['poId']] = round((float)$m['avg_mapping'], 2);
            }
        }

        foreach ($attainments as $a) {
            if (isset($attainmentMatrix[$a['course_id']][$a['poId']])) {
                $attainmentMatrix[$a['course_id']][$a['poId']] = round((float)$a['attainmentLevel'], 2);
            }
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'pos' => $pos,
                'courses' => $courses,
                'mappingMatrix' => $mappingMatrix,
                'attainmentMatrix' => $attainmentMatrix
            ]
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
    exit;
}
?>
