<?php
require_once 'db.php';


$method = $_SERVER['REQUEST_METHOD'];
$db = (new Database())->getDb();

if ($method === 'GET') {
    try {
        $stmt = $db->query('
            SELECT s.id, s."studentId" as roll_no, s.name, s.email, s."isActive",
                   p.name as program_name, b.name as batch_name, sec.name as section_name
            FROM students s
            LEFT JOIN programs p ON s."programId" = p.id
            LEFT JOIN batches b ON s."batchId" = b.id
            LEFT JOIN sections sec ON s."sectionId" = sec.id
            ORDER BY s."createdAt" DESC
        ');
        $students = $stmt->fetchAll();
        
        foreach ($students as &$s) {
            $s['isActive'] = (bool)$s['isActive'];
        }
        
        echo json_encode(['success' => true, 'data' => $students]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    try {
        $stmt = $db->prepare('INSERT INTO students (id, "studentId", name, email, "collegeId", "programId", "batchId", "isActive", "createdAt", "updatedAt") 
                              VALUES (:id, :studentId, :name, :email, :collegeId, :programId, :batchId, true, NOW(), NOW())');
        $id = uniqid('stu_');
        $stmt->execute([
            'id' => $id,
            'studentId' => $data['studentId'],
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'collegeId' => $data['collegeId'],
            'programId' => $data['programId'],
            'batchId' => $data['batchId']
        ]);
        echo json_encode(['success' => true, 'message' => 'Student created', 'id' => $id]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'DELETE') {
    $id = $_GET['id'] ?? '';
    try {
        $stmt = $db->prepare('DELETE FROM students WHERE id = :id');
        $stmt->execute(['id' => $id]);
        echo json_encode(['success' => true, 'message' => 'Student deleted']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
    exit;
}
?>
