<?php
require_once 'db.php';


$method = $_SERVER['REQUEST_METHOD'];
$db = (new Database())->getDb();

if ($method === 'GET') {
    try {
        $stmt = $db->query('
            SELECT e.id, e."isActive",
                   c.name as course_name, c.code as course_code,
                   st.name as student_name, st."studentId" as roll_no,
                   sec.name as section_name
            FROM enrollments e
            JOIN courses c ON e."courseId" = c.id
            JOIN students st ON e."studentId" = st.id
            LEFT JOIN sections sec ON e."sectionId" = sec.id
            ORDER BY c.code ASC, st."studentId" ASC
        ');
        $enrollments = $stmt->fetchAll();
        
        foreach ($enrollments as &$e) {
            $e['isActive'] = (bool)$e['isActive'];
        }
        
        echo json_encode(['success' => true, 'data' => $enrollments]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    try {
        $stmt = $db->prepare('INSERT INTO enrollments (id, "courseId", "studentId", "sectionId", "isActive", "createdAt", "updatedAt") 
                              VALUES (:id, :courseId, :studentId, :sectionId, true, NOW(), NOW())');
        $id = uniqid('enr_');
        $stmt->execute([
            'id' => $id,
            'courseId' => $data['courseId'],
            'studentId' => $data['studentId'],
            'sectionId' => empty($data['sectionId']) ? null : $data['sectionId']
        ]);
        echo json_encode(['success' => true, 'message' => 'Enrollment created', 'id' => $id]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'DELETE') {
    $id = $_GET['id'] ?? '';
    try {
        $stmt = $db->prepare('DELETE FROM enrollments WHERE id = :id');
        $stmt->execute(['id' => $id]);
        echo json_encode(['success' => true, 'message' => 'Enrollment deleted']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
    exit;
}
?>
