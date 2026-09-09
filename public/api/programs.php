<?php
require_once 'db.php';


$method = $_SERVER['REQUEST_METHOD'];
$db = (new Database())->getDb();

if ($method === 'GET') {
    try {
        try {
            $db->exec('ALTER TABLE programs ADD COLUMN IF NOT EXISTS "level" VARCHAR(255)');
            $db->exec('ALTER TABLE programs ADD COLUMN IF NOT EXISTS "threshold" NUMERIC');
        } catch(PDOException $e) {}

        // Select programs and join with colleges to get the college name
        $stmt = $db->query('SELECT p.id, p.name, p.code, p.duration, p."level", p."threshold", p."isActive", p."collegeId", c.name as college_name 
                            FROM programs p 
                            LEFT JOIN colleges c ON p."collegeId" = c.id
                            ORDER BY p."createdAt" DESC');
        $programs = $stmt->fetchAll();
        
        foreach ($programs as &$p) {
            $p['isActive'] = (bool)$p['isActive'];
        }
        
        echo json_encode(['success' => true, 'data' => $programs]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    try {
        try {
            $db->exec('ALTER TABLE programs ADD COLUMN IF NOT EXISTS "level" VARCHAR(255)');
            $db->exec('ALTER TABLE programs ADD COLUMN IF NOT EXISTS "threshold" NUMERIC');
        } catch(PDOException $e) {}

        $stmt = $db->prepare('INSERT INTO programs (id, name, code, duration, "level", "threshold", "collegeId", "isActive", "createdAt", "updatedAt") VALUES (:id, :name, :code, :duration, :level, :threshold, :collegeId, true, NOW(), NOW())');
        $id = uniqid('prog_');
        $stmt->execute([
            'id' => $id,
            'name' => $data['name'],
            'code' => $data['code'],
            'duration' => $data['duration'],
            'level' => $data['level'] ?? null,
            'threshold' => isset($data['threshold']) && $data['threshold'] !== '' ? (float)$data['threshold'] : null,
            'collegeId' => $data['collegeId']
        ]);
        echo json_encode(['success' => true, 'message' => 'Program created', 'id' => $id]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'DELETE') {
    $id = $_GET['id'] ?? '';
    try {
        $stmt = $db->prepare('DELETE FROM programs WHERE id = :id');
        $stmt->execute(['id' => $id]);
        echo json_encode(['success' => true, 'message' => 'Program deleted']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
    exit;
}
?>
