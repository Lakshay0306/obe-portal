<?php
require_once 'db.php';


$method = $_SERVER['REQUEST_METHOD'];
$db = (new Database())->getDb();

if ($method === 'GET') {
    try {
        $stmt = $db->query('
            SELECT m.id, m.level, m."isActive",
                   c.name as course_name, c.code as course_code,
                   co.code as co_code, po.code as po_code
            FROM co_po_mappings m
            JOIN courses c ON m."courseId" = c.id
            JOIN cos co ON m."coId" = co.id
            JOIN pos po ON m."poId" = po.id
            ORDER BY c.code ASC, co.code ASC, po.code ASC
        ');
        $mappings = $stmt->fetchAll();
        
        foreach ($mappings as &$m) {
            $m['isActive'] = (bool)$m['isActive'];
        }
        
        echo json_encode(['success' => true, 'data' => $mappings]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    try {
        $db->beginTransaction();

        $stmtDel = $db->prepare('DELETE FROM co_po_mappings WHERE "courseId" = :courseId AND "coId" = :coId AND "poId" = :poId');
        $stmtDel->execute([
            'courseId' => $data['courseId'],
            'coId' => $data['coId'],
            'poId' => $data['poId']
        ]);

        $stmt = $db->prepare('INSERT INTO co_po_mappings (id, "coId", "poId", "courseId", level, "isActive") 
                              VALUES (:id, :coId, :poId, :courseId, :level, true)');
        $id = uniqid('map_');
        $stmt->execute([
            'id' => $id,
            'coId' => $data['coId'],
            'poId' => $data['poId'],
            'courseId' => $data['courseId'],
            'level' => $data['level']
        ]);
        
        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Mapping created successfully', 'id' => $id]);
    } catch (PDOException $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'DELETE') {
    $id = $_GET['id'] ?? '';
    try {
        $stmt = $db->prepare('DELETE FROM co_po_mappings WHERE id = :id');
        $stmt->execute(['id' => $id]);
        echo json_encode(['success' => true, 'message' => 'Mapping deleted']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
    exit;
}
?>
