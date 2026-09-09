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
            $db->beginTransaction();
            
            // 1. Save File record
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

            // 2. Parse CSV and Insert Questions if it's a CSV
            $questionsAdded = 0;
            if (strtolower($ext) === 'csv') {
                // Get course COs mapping (code => id)
                $stmtCo = $db->prepare('SELECT id, code FROM cos WHERE "courseId" = :courseId');
                $stmtCo->execute(['courseId' => $courseId]);
                $courseCos = [];
                foreach ($stmtCo->fetchAll(PDO::FETCH_ASSOC) as $co) {
                    $courseCos[strtoupper(trim($co['code']))] = $co['id'];
                }

                $handle = fopen($destination, "r");
                if ($handle !== FALSE) {
                    $headerRow = true;
                    $insertQ = $db->prepare('INSERT INTO questions (id, "assessmentId", question, "maxMarks", "isActive", "createdAt", "updatedAt") VALUES (:id, :assessmentId, :question, :maxMarks, true, NOW(), NOW())');
                    $insertMap = $db->prepare('INSERT INTO question_co_mappings (id, "questionId", "coId", "isActive", "createdAt") VALUES (:id, :questionId, :coId, true, NOW())');
                    
                    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                        // Skip header row if it contains 'question' or similar
                        if ($headerRow) {
                            $headerRow = false;
                            if (stripos($data[0], 'question') !== false || stripos($data[0], 'text') !== false) {
                                continue;
                            }
                        }
                        
                        if (count($data) >= 2) {
                            $qText = trim($data[0]);
                            $qMarks = (float)trim($data[1]);
                            $coString = isset($data[2]) ? trim($data[2]) : '';

                            if (!empty($qText) && $qMarks > 0) {
                                $qId = uniqid('que_');
                                $insertQ->execute([
                                    'id' => $qId,
                                    'assessmentId' => $assessmentId,
                                    'question' => $qText,
                                    'maxMarks' => $qMarks
                                ]);
                                $questionsAdded++;

                                // Parse COs (e.g. "CO1, CO2")
                                if (!empty($coString)) {
                                    $coCodes = explode(',', $coString);
                                    foreach ($coCodes as $code) {
                                        $c = strtoupper(trim($code));
                                        if (isset($courseCos[$c])) {
                                            $insertMap->execute([
                                                'id' => uniqid('qco_'),
                                                'questionId' => $qId,
                                                'coId' => $courseCos[$c]
                                            ]);
                                        }
                                    }
                                }
                            }
                        }
                    }
                    fclose($handle);
                }
            }

            $db->commit();
            
            echo json_encode([
                'success' => true,
                'message' => $questionsAdded > 0 ? "Successfully uploaded and parsed $questionsAdded questions." : 'File uploaded and stored successfully. (CSV required for auto-parsing questions)',
                'data' => [
                    'id' => $fileId,
                    'path' => 'uploads/' . $filename,
                    'parsed' => $questionsAdded
                ]
            ]);
        } catch (Exception $e) {
            $db->rollBack();
            echo json_encode(['success' => false, 'message' => 'Database error while parsing and saving file info: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file']);
    }
    exit;
}
?>
