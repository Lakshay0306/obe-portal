<?php
require_once 'db.php';


$method = $_SERVER['REQUEST_METHOD'];
$db = (new Database())->getDb();

if ($method === 'GET') {
    try {
        $stmt = $db->query('
            SELECT q.id, q.question, q."maxMarks", q."isActive",
                   a.name as assessment_name, a."courseId",
                   (SELECT string_agg(co.code, \', \') 
                    FROM question_co_mappings qm 
                    JOIN cos co ON qm."coId" = co.id 
                    WHERE qm."questionId" = q.id) as mapped_cos
            FROM questions q
            JOIN assessments a ON q."assessmentId" = a.id
            ORDER BY a.name ASC, q."createdAt" ASC
        ');
        $questions = $stmt->fetchAll();
        
        foreach ($questions as &$q) {
            $q['isActive'] = (bool)$q['isActive'];
        }
        
        echo json_encode(['success' => true, 'data' => $questions]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    try {
        $db->beginTransaction();

        $stmt = $db->prepare('INSERT INTO questions (id, "assessmentId", question, "maxMarks", "isActive", "createdAt", "updatedAt") 
                              VALUES (:id, :assessmentId, :question, :maxMarks, true, NOW(), NOW())');
        $id = uniqid('que_');
        $stmt->execute([
            'id' => $id,
            'assessmentId' => $data['assessmentId'],
            'question' => $data['question'],
            'maxMarks' => $data['maxMarks']
        ]);

        if (!empty($data['coIds']) && is_array($data['coIds'])) {
            $stmtMap = $db->prepare('INSERT INTO question_co_mappings (id, "questionId", "coId", "isActive", "createdAt") 
                                     VALUES (:id, :questionId, :coId, true, NOW())');
            foreach ($data['coIds'] as $coId) {
                $stmtMap->execute([
                    'id' => uniqid('qco_'),
                    'questionId' => $id,
                    'coId' => $coId
                ]);
            }
        }

        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Question created', 'id' => $id]);
    } catch (PDOException $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'DELETE') {
    $id = $_GET['id'] ?? '';
    try {
        $stmt = $db->prepare('DELETE FROM questions WHERE id = :id');
        $stmt->execute(['id' => $id]);
        echo json_encode(['success' => true, 'message' => 'Question deleted']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
    exit;
}
?>
