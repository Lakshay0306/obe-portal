<?php
require_once 'db.php';


$method = $_SERVER['REQUEST_METHOD'];
$db = (new Database())->getDb();

if ($method === 'GET') {
    try {
        try {
            $db->exec('ALTER TABLE colleges ADD COLUMN IF NOT EXISTS vision TEXT');
            $db->exec('ALTER TABLE colleges ADD COLUMN IF NOT EXISTS mission TEXT');
        } catch(PDOException $e) {}

        $stmt = $db->query('SELECT id AS _id, name, code, vision, mission, "isActive" FROM colleges');
        $colleges = $stmt->fetchAll();
        
        // Convert boolean if needed
        foreach ($colleges as &$c) {
            $c['isActive'] = (bool)$c['isActive'];
        }
        echo json_encode(['success' => true, 'data' => $colleges]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    try {
        try {
            $db->exec('ALTER TABLE colleges ADD COLUMN IF NOT EXISTS vision TEXT');
            $db->exec('ALTER TABLE colleges ADD COLUMN IF NOT EXISTS mission TEXT');
        } catch(PDOException $e) {}

        $stmt = $db->prepare('INSERT INTO colleges (id, name, code, vision, mission, "isActive", "createdAt", "updatedAt") VALUES (:id, :name, :code, :vision, :mission, true, NOW(), NOW())');
        $id = uniqid('col_');
        $stmt->execute([
            'id' => $id,
            'name' => $data['name'],
            'code' => $data['code'],
            'vision' => $data['vision'] ?? null,
            'mission' => $data['mission'] ?? null
        ]);
        echo json_encode(['success' => true, 'message' => 'College created', 'id' => $id]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'DELETE') {
    $id = $_GET['id'] ?? '';
    try {
        $stmt = $db->prepare('DELETE FROM colleges WHERE id = :id');
        $stmt->execute(['id' => $id]);
        echo json_encode(['success' => true, 'message' => 'College deleted']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
    exit;
}
?>
