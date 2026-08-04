<?php
require_once 'db.php';


$method = $_SERVER['REQUEST_METHOD'];
$db = (new Database())->getDb();

if ($method === 'GET') {
    try {
        $stmt = $db->query('
            SELECT a.id, a.name, a.type, a."maxMarks", a.weightage, a."isActive",
                   c.name as course_name, c.code as course_code,
                   s.name as section_name
            FROM assessments a
            LEFT JOIN courses c ON a."courseId" = c.id
            LEFT JOIN sections s ON a."sectionId" = s.id
            ORDER BY a."createdAt" DESC
        ');
        $assessments = $stmt->fetchAll();
        
        foreach ($assessments as &$a) {
            $a['isActive'] = (bool)$a['isActive'];
        }
        
        echo json_encode(['success' => true, 'data' => $assessments]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    try {
        $stmt = $db->prepare('INSERT INTO assessments (id, "courseId", "sectionId", name, type, "maxMarks", weightage, "isActive", "createdAt", "updatedAt") 
                              VALUES (:id, :courseId, :sectionId, :name, :type, :maxMarks, :weightage, true, NOW(), NOW())');
        $id = uniqid('ass_');
        $stmt->execute([
            'id' => $id,
            'courseId' => $data['courseId'],
            'sectionId' => empty($data['sectionId']) ? null : $data['sectionId'],
            'name' => $data['name'],
            'type' => $data['type'],
            'maxMarks' => $data['maxMarks'],
            'weightage' => $data['weightage']
        ]);
        echo json_encode(['success' => true, 'message' => 'Assessment created', 'id' => $id]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'DELETE') {
    $id = $_GET['id'] ?? '';
    try {
        $stmt = $db->prepare('DELETE FROM assessments WHERE id = :id');
        $stmt->execute(['id' => $id]);
        echo json_encode(['success' => true, 'message' => 'Assessment deleted']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
    exit;
}
?>
