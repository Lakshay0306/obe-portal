<?php
require_once 'db.php';


$method = $_SERVER['REQUEST_METHOD'];
$db = (new Database())->getDb();

if ($method === 'GET') {
    try {
        $stmt = $db->query('
            SELECT ta.id, ta."isActive", 
                   u.name as teacher_name, u.email as teacher_email,
                   c.name as course_name, c.code as course_code,
                   s.name as section_name
            FROM teacher_assignments ta
            JOIN users u ON ta."teacherId" = u.id
            JOIN courses c ON ta."courseId" = c.id
            LEFT JOIN sections s ON ta."sectionId" = s.id
            ORDER BY ta."createdAt" DESC
        ');
        $assignments = $stmt->fetchAll();
        
        foreach ($assignments as &$ta) {
            $ta['isActive'] = (bool)$ta['isActive'];
        }
        
        echo json_encode(['success' => true, 'data' => $assignments]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
    exit;
}

// Additional helper endpoint to fetch only TEACHERS for the dropdown
if ($method === 'POST' && isset($_GET['fetch_teachers'])) {
    try {
        $stmt = $db->query("SELECT id, name, email FROM users WHERE role = 'TEACHER' ORDER BY name ASC");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    try {
        $stmt = $db->prepare('INSERT INTO teacher_assignments (id, "courseId", "sectionId", "teacherId", "isActive", "createdAt", "updatedAt") 
                              VALUES (:id, :courseId, :sectionId, :teacherId, true, NOW(), NOW())');
        $id = uniqid('ta_');
        $stmt->execute([
            'id' => $id,
            'courseId' => $data['courseId'],
            'sectionId' => empty($data['sectionId']) ? null : $data['sectionId'],
            'teacherId' => $data['teacherId']
        ]);
        echo json_encode(['success' => true, 'message' => 'Assignment created', 'id' => $id]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'DELETE') {
    $id = $_GET['id'] ?? '';
    try {
        $stmt = $db->prepare('DELETE FROM teacher_assignments WHERE id = :id');
        $stmt->execute(['id' => $id]);
        echo json_encode(['success' => true, 'message' => 'Assignment deleted']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
    exit;
}
?>
