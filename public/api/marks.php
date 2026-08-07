<?php
require_once 'db.php';


$method = $_SERVER['REQUEST_METHOD'];
$db = (new Database())->getDb();

if ($method === 'GET') {
    try {
        $stmt = $db->query('
            SELECT m.id, m."obtainedMarks", m."maxMarks", m."academicYear",
                   q.question, 
                   st.name as student_name, st."studentId" as roll_no,
                   sec.name as section_name,
                   a.name as assessment_name,
                   c.code as course_code
            FROM student_marks m
            JOIN questions q ON m."questionId" = q.id
            JOIN assessments a ON q."assessmentId" = a.id
            JOIN courses c ON a."courseId" = c.id
            JOIN students st ON m."studentId" = st.id
            LEFT JOIN sections sec ON m."sectionId" = sec.id
            ORDER BY c.code ASC, a.name ASC, st."studentId" ASC
        ');
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    $action = isset($_GET['action']) ? $_GET['action'] : '';

    if ($action === 'batch') {
        if (!isset($data['marks']) || !is_array($data['marks'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid batch data format']);
            exit;
        }

        try {
            $db->beginTransaction();
            // Delete existing marks for these student-question combinations to perform an upsert-like operation
            $delStmt = $db->prepare('DELETE FROM student_marks WHERE "studentId" = :studentId AND "questionId" = :questionId');
            
            $insStmt = $db->prepare('INSERT INTO student_marks (id, "questionId", "studentId", "sectionId", "obtainedMarks", "maxMarks", "academicYear", "submittedAt") 
                                  VALUES (:id, :questionId, :studentId, :sectionId, :obtainedMarks, :maxMarks, :academicYear, NOW())');
            
            foreach ($data['marks'] as $mark) {
                // Remove existing
                $delStmt->execute([
                    'studentId' => $mark['studentId'],
                    'questionId' => $mark['questionId']
                ]);

                // Insert new
                $insStmt->execute([
                    'id' => uniqid('mrk_'),
                    'questionId' => $mark['questionId'],
                    'studentId' => $mark['studentId'],
                    'sectionId' => empty($mark['sectionId']) ? null : $mark['sectionId'],
                    'obtainedMarks' => ($mark['obtainedMarks'] === 'U') ? null : $mark['obtainedMarks'],
                    'maxMarks' => $mark['maxMarks'],
                    'academicYear' => empty($mark['academicYear']) ? null : $mark['academicYear']
                ]);
            }

            $db->commit();
            echo json_encode(['success' => true, 'message' => 'Batch marks recorded']);
        } catch (PDOException $e) {
            $db->rollBack();
            echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
        }
        exit;
    }

    // Single POST logic (legacy)
    try {
        $stmt = $db->prepare('INSERT INTO student_marks (id, "questionId", "studentId", "sectionId", "obtainedMarks", "maxMarks", "academicYear", "submittedAt") 
                              VALUES (:id, :questionId, :studentId, :sectionId, :obtainedMarks, :maxMarks, :academicYear, NOW())');
        $id = uniqid('mrk_');
        $stmt->execute([
            'id' => $id,
            'questionId' => $data['questionId'],
            'studentId' => $data['studentId'],
            'sectionId' => empty($data['sectionId']) ? null : $data['sectionId'],
            'obtainedMarks' => ($data['obtainedMarks'] === 'U') ? null : $data['obtainedMarks'],
            'maxMarks' => $data['maxMarks'],
            'academicYear' => empty($data['academicYear']) ? null : $data['academicYear']
        ]);
        echo json_encode(['success' => true, 'message' => 'Mark recorded', 'id' => $id]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'DELETE') {
    $id = $_GET['id'] ?? '';
    try {
        $stmt = $db->prepare('DELETE FROM student_marks WHERE id = :id');
        $stmt->execute(['id' => $id]);
        echo json_encode(['success' => true, 'message' => 'Mark deleted']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
    exit;
}
?>
