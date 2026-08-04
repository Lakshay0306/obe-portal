<?php
require_once 'db.php';


$method = $_SERVER['REQUEST_METHOD'];
$db = (new Database())->getDb();

// Ensure table exists
try {
    $db->exec('CREATE TABLE IF NOT EXISTS uploaded_files (
        id VARCHAR(255) PRIMARY KEY,
        "courseId" VARCHAR(255),
        "assessmentId" VARCHAR(255),
        "filename" VARCHAR(255),
        "originalName" VARCHAR(255),
        "uploadedAt" TIMESTAMP
    )');
} catch (PDOException $e) {}

if ($method === 'GET') {
    $courseId = $_GET['courseId'] ?? '';
    if (!$courseId) {
        echo json_encode(['success' => false, 'message' => 'Missing courseId']);
        exit;
    }

    try {
        $stmt = $db->prepare('
            SELECT uf.*, a.name as assessment_name 
            FROM uploaded_files uf
            LEFT JOIN assessments a ON uf."assessmentId" = a.id
            WHERE uf."courseId" = :courseId
            ORDER BY uf."uploadedAt" DESC
        ');
        $stmt->execute(['courseId' => $courseId]);
        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $files]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    exit;
}

if ($method === 'POST') {
    if (!isset($_FILES['file'])) {
        echo json_encode(['success' => false, 'message' => 'No file uploaded']);
        exit;
    }

    $courseId = $_POST['courseId'] ?? '';
    $assessmentId = $_POST['assessmentId'] ?? '';

    if (!$courseId) {
        echo json_encode(['success' => false, 'message' => 'Missing courseId']);
        exit;
    }

    $file = $_FILES['file'];
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    
    if (!in_array(strtolower($ext), ['xlsx', 'csv', 'xls'])) {
        echo json_encode(['success' => false, 'message' => 'Only Excel or CSV files are allowed']);
        exit;
    }

    $uploadDir = '../uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = uniqid('upload_') . '_' . time() . '.' . $ext;
    $destination = $uploadDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $destination)) {
        try {
            $stmt = $db->prepare('
                INSERT INTO uploaded_files (id, "courseId", "assessmentId", "filename", "originalName", "uploadedAt")
                VALUES (:id, :courseId, :assessmentId, :filename, :originalName, NOW())
            ');
            $fileId = uniqid('file_');
            $stmt->execute([
                'id' => $fileId,
                'courseId' => $courseId,
                'assessmentId' => $assessmentId,
                'filename' => $filename,
                'originalName' => $file['name']
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'File uploaded and stored successfully',
                'data' => [
                    'id' => $fileId,
                    'path' => 'uploads/' . $filename
                ]
            ]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error while saving file info']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file']);
    }
    exit;
}
?>
