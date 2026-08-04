<?php
require_once 'db.php';


$method = $_SERVER['REQUEST_METHOD'];
$db = (new Database())->getDb();

if ($method === 'GET') {
    try {
        $stmt = $db->query('
            SELECT s.id, s.name, s."isActive", b.name as batch_name, p.name as program_name
            FROM sections s
            LEFT JOIN batches b ON s."batchId" = b.id
            LEFT JOIN programs p ON b."programId" = p.id
            ORDER BY s."createdAt" DESC
        ');
        $sections = $stmt->fetchAll();
        
        foreach ($sections as &$s) {
            $s['isActive'] = (bool)$s['isActive'];
        }
        
        echo json_encode(['success' => true, 'data' => $sections]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    try {
        $stmt = $db->prepare('INSERT INTO sections (id, name, "batchId", "isActive", "createdAt", "updatedAt") 
                              VALUES (:id, :name, :batchId, true, NOW(), NOW())');
        $id = uniqid('sec_');
        $stmt->execute([
            'id' => $id,
            'name' => $data['name'],
            'batchId' => $data['batchId']
        ]);
        echo json_encode(['success' => true, 'message' => 'Section created', 'id' => $id]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'DELETE') {
    $id = $_GET['id'] ?? '';
    try {
        $stmt = $db->prepare('DELETE FROM sections WHERE id = :id');
        $stmt->execute(['id' => $id]);
        echo json_encode(['success' => true, 'message' => 'Section deleted']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
    exit;
}
?>
