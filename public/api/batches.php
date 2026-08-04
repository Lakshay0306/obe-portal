<?php
require_once 'db.php';


$method = $_SERVER['REQUEST_METHOD'];
$db = (new Database())->getDb();

if ($method === 'GET') {
    try {
        $stmt = $db->query('
            SELECT b.id, b.name, b."startYear", b."endYear", b."isActive", p.name as program_name
            FROM batches b
            LEFT JOIN programs p ON b."programId" = p.id
            ORDER BY b."createdAt" DESC
        ');
        $batches = $stmt->fetchAll();
        
        foreach ($batches as &$b) {
            $b['isActive'] = (bool)$b['isActive'];
        }
        
        echo json_encode(['success' => true, 'data' => $batches]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    try {
        $stmt = $db->prepare('INSERT INTO batches (id, name, "programId", "startYear", "endYear", "isActive", "createdAt", "updatedAt") 
                              VALUES (:id, :name, :programId, :startYear, :endYear, true, NOW(), NOW())');
        $id = uniqid('bat_');
        $stmt->execute([
            'id' => $id,
            'name' => $data['name'],
            'programId' => $data['programId'],
            'startYear' => $data['startYear'],
            'endYear' => $data['endYear']
        ]);
        echo json_encode(['success' => true, 'message' => 'Batch created', 'id' => $id]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'DELETE') {
    $id = $_GET['id'] ?? '';
    try {
        $stmt = $db->prepare('DELETE FROM batches WHERE id = :id');
        $stmt->execute(['id' => $id]);
        echo json_encode(['success' => true, 'message' => 'Batch deleted']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
    exit;
}
?>
