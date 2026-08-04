<?php
require_once 'db.php';


$method = $_SERVER['REQUEST_METHOD'];
$db = (new Database())->getDb();

if ($method === 'GET') {
    try {
        $stmt = $db->query('
            SELECT peo.id, peo.code, peo.description, peo."isActive", p.name as program_name
            FROM peos peo
            JOIN programs p ON peo."programId" = p.id
            ORDER BY p.name ASC, peo.code ASC
        ');
        $peos = $stmt->fetchAll();
        
        foreach ($peos as &$peo) {
            $peo['isActive'] = (bool)$peo['isActive'];
        }
        
        echo json_encode(['success' => true, 'data' => $peos]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    try {
        $stmt = $db->prepare('INSERT INTO peos (id, "programId", code, description, "isActive", "createdAt", "updatedAt") 
                              VALUES (:id, :programId, :code, :description, true, NOW(), NOW())');
        $id = uniqid('peo_');
        $stmt->execute([
            'id' => $id,
            'programId' => $data['programId'],
            'code' => $data['code'],
            'description' => $data['description']
        ]);
        echo json_encode(['success' => true, 'message' => 'PEO created', 'id' => $id]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'DELETE') {
    $id = $_GET['id'] ?? '';
    try {
        $stmt = $db->prepare('DELETE FROM peos WHERE id = :id');
        $stmt->execute(['id' => $id]);
        echo json_encode(['success' => true, 'message' => 'PEO deleted']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
    exit;
}
?>
