<?php
require_once 'db.php';


$method = $_SERVER['REQUEST_METHOD'];
$db = (new Database())->getDb();

// Ensure tables exist
try {
    $db->exec('CREATE TABLE IF NOT EXISTS course_obe_settings (
        "courseId" VARCHAR(255) PRIMARY KEY,
        "targetThreshold" NUMERIC DEFAULT 60,
        "score3Threshold" NUMERIC DEFAULT 80,
        "score2Threshold" NUMERIC DEFAULT 70,
        "score1Threshold" NUMERIC DEFAULT 60,
        "internalWeight" NUMERIC DEFAULT 40,
        "externalWeight" NUMERIC DEFAULT 60
    )');

    // Backward compatibility
    try {
        $db->exec('ALTER TABLE course_obe_settings ADD COLUMN "internalWeight" NUMERIC DEFAULT 40');
        $db->exec('ALTER TABLE course_obe_settings ADD COLUMN "externalWeight" NUMERIC DEFAULT 60');
    } catch (PDOException $e) {}

    $db->exec('CREATE TABLE IF NOT EXISTS course_indirect_po (
        id VARCHAR(255) PRIMARY KEY,
        "courseId" VARCHAR(255),
        "poId" VARCHAR(255),
        "indirectLevel" NUMERIC DEFAULT 0,
        UNIQUE("courseId", "poId")
    )');
} catch (PDOException $e) {}

if ($method === 'GET') {
    $courseId = $_GET['courseId'] ?? '';
    if (!$courseId) {
        echo json_encode(['success' => false, 'message' => 'Missing courseId']);
        exit;
    }

    try {
        $stmt = $db->prepare('SELECT * FROM course_obe_settings WHERE "courseId" = :courseId');
        $stmt->execute(['courseId' => $courseId]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$settings) {
            $settings = [
                'targetThreshold' => 60,
                'score3Threshold' => 80,
                'score2Threshold' => 70,
                'score1Threshold' => 60,
                'internalWeight' => 40,
                'externalWeight' => 60
            ];
        }

        $stmtPo = $db->prepare('
            SELECT cip."poId", p.code, cip."indirectLevel" 
            FROM course_indirect_po cip
            JOIN pos p ON cip."poId" = p.id
            WHERE cip."courseId" = :courseId
            ORDER BY p.code ASC
        ');
        $stmtPo->execute(['courseId' => $courseId]);
        $indirectPos = $stmtPo->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => [
                'settings' => $settings,
                'indirectPos' => $indirectPos
            ]
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $courseId = $data['courseId'] ?? '';
    
    if (!$courseId) {
        echo json_encode(['success' => false, 'message' => 'Missing courseId']);
        exit;
    }

    try {
        $db->beginTransaction();

        // Update settings
        if (isset($data['settings'])) {
            $s = $data['settings'];
            $stmt = $db->prepare('
                INSERT INTO course_obe_settings ("courseId", "targetThreshold", "score3Threshold", "score2Threshold", "score1Threshold", "internalWeight", "externalWeight")
                VALUES (:courseId, :target, :s3, :s2, :s1, :iw, :ew)
                ON CONFLICT ("courseId") DO UPDATE 
                SET "targetThreshold" = EXCLUDED."targetThreshold",
                    "score3Threshold" = EXCLUDED."score3Threshold",
                    "score2Threshold" = EXCLUDED."score2Threshold",
                    "score1Threshold" = EXCLUDED."score1Threshold",
                    "internalWeight" = EXCLUDED."internalWeight",
                    "externalWeight" = EXCLUDED."externalWeight"
            ');
            $stmt->execute([
                'courseId' => $courseId,
                'target' => $s['targetThreshold'],
                's3' => $s['score3Threshold'],
                's2' => $s['score2Threshold'],
                's1' => $s['score1Threshold'],
                'iw' => $s['internalWeight'],
                'ew' => $s['externalWeight']
            ]);
        }

        // Update indirect POs
        if (isset($data['indirectPos'])) {
            $stmt = $db->prepare('
                INSERT INTO course_indirect_po (id, "courseId", "poId", "indirectLevel")
                VALUES (:id, :courseId, :poId, :indirectLevel)
                ON CONFLICT ("courseId", "poId") DO UPDATE
                SET "indirectLevel" = EXCLUDED."indirectLevel"
            ');
            foreach ($data['indirectPos'] as $poId => $level) {
                $stmt->execute([
                    'id' => uniqid('ipo_'),
                    'courseId' => $courseId,
                    'poId' => $poId,
                    'indirectLevel' => $level
                ]);
            }
        }

        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Settings saved successfully']);
    } catch (PDOException $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
    exit;
}
?>
