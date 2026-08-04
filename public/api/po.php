<?php
require_once 'db.php';


$method = $_SERVER['REQUEST_METHOD'];
$db = (new Database())->getDb();

if ($method === 'GET') {
    try {
        $stmt = $db->query('
            SELECT po.id, po.code, po.description, po."isActive",
                   p.name as program_name
            FROM pos po
            JOIN programs p ON po."programId" = p.id
            ORDER BY p.name ASC, po.code ASC
        ');
        $pos = $stmt->fetchAll();
        
        foreach ($pos as &$po) {
            $po['isActive'] = (bool)$po['isActive'];
        }
        
        echo json_encode(['success' => true, 'data' => $pos]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    try {
        $stmt = $db->prepare('INSERT INTO pos (id, "programId", code, description, "isActive", "createdAt", "updatedAt") 
                              VALUES (:id, :programId, :code, :description, true, NOW(), NOW())');
        $id = uniqid('po_');
        $stmt->execute([
            'id' => $id,
            'programId' => $data['programId'],
            'code' => $data['code'],
            'description' => $data['description']
        ]);
        echo json_encode(['success' => true, 'message' => 'PO created', 'id' => $id]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'DELETE') {
    $id = $_GET['id'] ?? '';
    try {
        $stmt = $db->prepare('DELETE FROM pos WHERE id = :id');
        $stmt->execute(['id' => $id]);
        echo json_encode(['success' => true, 'message' => 'PO deleted']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
    exit;
}
?>
