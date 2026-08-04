<?php
require_once 'db.php';


$method = $_SERVER['REQUEST_METHOD'];
$db = (new Database())->getDb();

if ($method === 'GET') {
    try {
        $stmt = $db->query('
            SELECT co.id, co.code, co.description, co."isActive",
                   c.name as course_name, c.code as course_code
            FROM cos co
            JOIN courses c ON co."courseId" = c.id
            ORDER BY c.code ASC, co.code ASC
        ');
        $cos = $stmt->fetchAll();
        
        foreach ($cos as &$co) {
            $co['isActive'] = (bool)$co['isActive'];
        }
        
        echo json_encode(['success' => true, 'data' => $cos]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    try {
        $stmt = $db->prepare('INSERT INTO cos (id, "courseId", code, description, "isActive", "createdAt", "updatedAt") 
                              VALUES (:id, :courseId, :code, :description, true, NOW(), NOW())');
        $id = uniqid('co_');
        $stmt->execute([
            'id' => $id,
            'courseId' => $data['courseId'],
            'code' => $data['code'],
            'description' => $data['description']
        ]);
        echo json_encode(['success' => true, 'message' => 'CO created', 'id' => $id]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'DELETE') {
    $id = $_GET['id'] ?? '';
    try {
        $stmt = $db->prepare('DELETE FROM cos WHERE id = :id');
        $stmt->execute(['id' => $id]);
        echo json_encode(['success' => true, 'message' => 'CO deleted']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
    exit;
}
?>
