<?php
require_once 'db.php';


$method = $_SERVER['REQUEST_METHOD'];
$db = (new Database())->getDb();

if ($method === 'GET') {
    try {
        $stmt = $db->query('SELECT c.id, c.code, c.name, c.status, c.description, c."isActive", 
                                   c."targetPercentage", c."level1Threshold", c."level2Threshold", c."level3Threshold",
                                   b.name as batch_name, p.name as program_name
                            FROM courses c 
                            LEFT JOIN batches b ON c."batchId" = b.id
                            LEFT JOIN programs p ON b."programId" = p.id
                            ORDER BY c."createdAt" DESC');
        $courses = $stmt->fetchAll();
        
        foreach ($courses as &$c) {
            $c['isActive'] = (bool)$c['isActive'];
        }
        
        echo json_encode(['success' => true, 'data' => $courses]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    try {
        $stmt = $db->prepare('INSERT INTO courses (id, name, code, "batchId", description, status, "targetPercentage", "level1Threshold", "level2Threshold", "level3Threshold", "isActive", "createdAt", "updatedAt") 
                              VALUES (:id, :name, :code, :batchId, :description, :status, :targetPercentage, :level1Threshold, :level2Threshold, :level3Threshold, true, NOW(), NOW())');
        $id = uniqid('crs_');
        $stmt->execute([
            'id' => $id,
            'name' => $data['name'],
            'code' => $data['code'],
            'batchId' => $data['batchId'],
            'description' => isset($data['description']) ? $data['description'] : null,
            'status' => $data['status'],
            'targetPercentage' => isset($data['targetPercentage']) ? $data['targetPercentage'] : 50.0,
            'level1Threshold' => isset($data['level1Threshold']) ? $data['level1Threshold'] : 50.0,
            'level2Threshold' => isset($data['level2Threshold']) ? $data['level2Threshold'] : 70.0,
            'level3Threshold' => isset($data['level3Threshold']) ? $data['level3Threshold'] : 80.0
        ]);
        echo json_encode(['success' => true, 'message' => 'Course created', 'id' => $id]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'DELETE') {
    $id = $_GET['id'] ?? '';
    try {
        $stmt = $db->prepare('DELETE FROM courses WHERE id = :id');
        $stmt->execute(['id' => $id]);
        echo json_encode(['success' => true, 'message' => 'Course deleted']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
    exit;
}
?>
