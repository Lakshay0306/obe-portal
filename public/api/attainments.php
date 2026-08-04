<?php
require_once 'db.php';


$method = $_SERVER['REQUEST_METHOD'];
$db = (new Database())->getDb();

if ($method === 'GET') {
    try {
        $stmt = $db->query('
            SELECT a.id, a.percentage, a."metTarget", a."academicYear", a."calculatedAt",
                   c.code as course_code, c.name as course_name,
                   co.code as co_code,
                   st.name as student_name, st."studentId" as roll_no,
                   sec.name as section_name
            FROM co_attainments a
            JOIN courses c ON a."courseId" = c.id
            JOIN cos co ON a."coId" = co.id
            JOIN students st ON a."studentId" = st.id
            LEFT JOIN sections sec ON a."sectionId" = sec.id
            ORDER BY c.code ASC, co.code ASC, st."studentId" ASC
        ');
        $coAttainments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($coAttainments as &$a) {
            $a['metTarget'] = (bool)$a['metTarget'];
            $a['percentage'] = round((float)$a['percentage'], 1);
        }

        $poAttainments = [];
        try {
            $stmtPo = $db->query('
                SELECT pa.id, pa."attainmentLevel", pa."calculatedAt",
                       p.code as po_code,
                       c.code as course_code,
                       st.name as student_name, st."studentId" as roll_no
                FROM po_attainments pa
                JOIN pos p ON pa."poId" = p.id
                JOIN courses c ON pa."courseId" = c.id
                JOIN students st ON pa."studentId" = st.id
                ORDER BY c.code ASC, p.code ASC, st."studentId" ASC
            ');
            $poAttainments = $stmtPo->fetchAll(PDO::FETCH_ASSOC);
            foreach ($poAttainments as &$a) {
                $a['attainmentLevel'] = round((float)$a['attainmentLevel'], 2);
            }
        } catch (PDOException $e) {}

        $courseCoAttainments = [];
        try {
            $stmtCourseCo = $db->query('
                SELECT cca.id, cca."attainmentScore", cca."studentsAttempted", cca."studentsMetTarget", cca."percentageMet", cca."calculatedAt",
                       c.code as course_code, c.name as course_name,
                       co.code as co_code
                FROM course_co_attainments cca
                JOIN courses c ON cca."courseId" = c.id
                JOIN cos co ON cca."coId" = co.id
                ORDER BY c.code ASC, co.code ASC
            ');
            $courseCoAttainments = $stmtCourseCo->fetchAll(PDO::FETCH_ASSOC);
            foreach ($courseCoAttainments as &$cca) {
                $cca['percentageMet'] = round((float)$cca['percentageMet'], 2);
                $cca['attainmentScore'] = (int)$cca['attainmentScore'];
            }
        } catch (PDOException $e) {}

        $coursePoAttainments = [];
        try {
            $stmtCoursePo = $db->query('
                SELECT cpa.id, cpa."directLevel", cpa."indirectLevel", cpa."attainmentLevel", cpa."calculatedAt",
                       c.code as course_code, c.name as course_name,
                       po.code as po_code
                FROM course_po_attainments cpa
                JOIN courses c ON cpa."courseId" = c.id
                JOIN pos po ON cpa."poId" = po.id
                ORDER BY c.code ASC, po.code ASC
            ');
            $coursePoAttainments = $stmtCoursePo->fetchAll(PDO::FETCH_ASSOC);
            foreach ($coursePoAttainments as &$cpa) {
                $cpa['directLevel'] = round((float)$cpa['directLevel'], 2);
                $cpa['indirectLevel'] = round((float)$cpa['indirectLevel'], 2);
                $cpa['attainmentLevel'] = round((float)$cpa['attainmentLevel'], 2);
            }
        } catch (PDOException $e) {}
        
        echo json_encode([
            'success' => true, 
            'data' => [
                'co' => $coAttainments,
                'po' => $poAttainments,
                'course_co' => $courseCoAttainments,
                'course_po' => $coursePoAttainments
            ]
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['action']) || $data['action'] !== 'calculate' || !isset($data['courseId'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit;
    }
    
    $courseId = $data['courseId'];
    $academicYear = $data['academicYear'] ?? date('Y');
    
    try {
        $db->beginTransaction();
        
        // Ensure tables exist
        $db->exec('CREATE TABLE IF NOT EXISTS course_co_attainments (
            id VARCHAR(255) PRIMARY KEY,
            "courseId" VARCHAR(255),
            "coId" VARCHAR(255),
            "attainmentScore" INT,
            "studentsAttempted" INT,
            "studentsMetTarget" INT,
            "percentageMet" NUMERIC,
            "academicYear" VARCHAR(255),
            "calculatedAt" TIMESTAMP
        )');

        $db->exec('CREATE TABLE IF NOT EXISTS course_po_attainments (
            id VARCHAR(255) PRIMARY KEY,
            "courseId" VARCHAR(255),
            "poId" VARCHAR(255),
            "directLevel" NUMERIC DEFAULT 0,
            "indirectLevel" NUMERIC DEFAULT 0,
            "attainmentLevel" NUMERIC,
            "academicYear" VARCHAR(255),
            "calculatedAt" TIMESTAMP
        )');

        // Backwards compatibility for existing course_po_attainments table
        try {
            $db->exec('ALTER TABLE course_po_attainments ADD COLUMN "directLevel" NUMERIC DEFAULT 0');
            $db->exec('ALTER TABLE course_po_attainments ADD COLUMN "indirectLevel" NUMERIC DEFAULT 0');
        } catch (PDOException $e) {}

        // Fetch OBE Settings
        $stmtSettings = $db->prepare('SELECT * FROM course_obe_settings WHERE "courseId" = :courseId');
        $stmtSettings->execute(['courseId' => $courseId]);
        $settings = $stmtSettings->fetch(PDO::FETCH_ASSOC);
        
        $targetThreshold = $settings ? (float)$settings['targetThreshold'] : 60;
        $score3 = $settings ? (float)$settings['score3Threshold'] : 80;
        $score2 = $settings ? (float)$settings['score2Threshold'] : 70;
        $score1 = $settings ? (float)$settings['score1Threshold'] : 60;
        $iw = $settings ? (float)$settings['internalWeight'] / 100.0 : 0.4;
        $ew = $settings ? (float)$settings['externalWeight'] / 100.0 : 0.6;

        // Alter schema if needed for new columns
        try {
            $db->exec('ALTER TABLE co_attainments ADD COLUMN "internalPercentage" NUMERIC DEFAULT 0');
            $db->exec('ALTER TABLE co_attainments ADD COLUMN "internalMetTarget" BOOLEAN DEFAULT false');
            $db->exec('ALTER TABLE co_attainments ADD COLUMN "externalPercentage" NUMERIC DEFAULT 0');
            $db->exec('ALTER TABLE co_attainments ADD COLUMN "externalMetTarget" BOOLEAN DEFAULT false');
        } catch (PDOException $e) {}

        try {
            $db->exec('ALTER TABLE course_co_attainments ADD COLUMN "internalScore" NUMERIC DEFAULT 0');
            $db->exec('ALTER TABLE course_co_attainments ADD COLUMN "externalScore" NUMERIC DEFAULT 0');
            $db->exec('ALTER TABLE course_co_attainments ALTER COLUMN "attainmentScore" TYPE NUMERIC USING "attainmentScore"::NUMERIC');
        } catch (PDOException $e) {}

        // 1. Delete old attainments for this course
        $db->prepare('DELETE FROM co_attainments WHERE "courseId" = :courseId')->execute(['courseId' => $courseId]);
        
        // 2. Calculate Student-Level CO Attainments
        $calcQuery = '
            INSERT INTO co_attainments (id, "courseId", "coId", "studentId", "sectionId", percentage, "metTarget", "internalPercentage", "internalMetTarget", "externalPercentage", "externalMetTarget", "academicYear", "calculatedAt")
            SELECT 
                concat(\'att_\', substr(md5(random()::text), 1, 10)),
                :courseId,
                qcm."coId",
                sm."studentId",
                sm."sectionId",
                (SUM(sm."obtainedMarks") * 100.0 / NULLIF(SUM(sm."maxMarks"), 0)) as percentage,
                (SUM(sm."obtainedMarks") * 100.0 / NULLIF(SUM(sm."maxMarks"), 0)) >= :targetThreshold as metTarget,
                
                (SUM(CASE WHEN a.type = \'Internal\' THEN sm."obtainedMarks" ELSE 0 END) * 100.0 / NULLIF(SUM(CASE WHEN a.type = \'Internal\' THEN sm."maxMarks" ELSE 0 END), 0)) as internalPercentage,
                (SUM(CASE WHEN a.type = \'Internal\' THEN sm."obtainedMarks" ELSE 0 END) * 100.0 / NULLIF(SUM(CASE WHEN a.type = \'Internal\' THEN sm."maxMarks" ELSE 0 END), 0)) >= :targetThreshold as internalMetTarget,
                
                (SUM(CASE WHEN a.type = \'External\' THEN sm."obtainedMarks" ELSE 0 END) * 100.0 / NULLIF(SUM(CASE WHEN a.type = \'External\' THEN sm."maxMarks" ELSE 0 END), 0)) as externalPercentage,
                (SUM(CASE WHEN a.type = \'External\' THEN sm."obtainedMarks" ELSE 0 END) * 100.0 / NULLIF(SUM(CASE WHEN a.type = \'External\' THEN sm."maxMarks" ELSE 0 END), 0)) >= :targetThreshold as externalMetTarget,
                
                :academicYear,
                NOW()
            FROM student_marks sm
            JOIN questions q ON sm."questionId" = q.id
            JOIN assessments a ON q."assessmentId" = a.id
            JOIN question_co_mappings qcm ON q.id = qcm."questionId"
            WHERE a."courseId" = :courseId2
            GROUP BY sm."studentId", sm."sectionId", qcm."coId"
        ';
        $stmtCalc = $db->prepare($calcQuery);
        $stmtCalc->execute(['courseId' => $courseId, 'targetThreshold' => $targetThreshold, 'academicYear' => $academicYear, 'courseId2' => $courseId]);

        // 3. Calculate Student-Level PO Attainments (Intermediate)
        try {
            $db->exec('CREATE TABLE IF NOT EXISTS po_attainments (
                id VARCHAR(255) PRIMARY KEY,
                "courseId" VARCHAR(255),
                "poId" VARCHAR(255),
                "studentId" VARCHAR(255),
                "attainmentLevel" NUMERIC,
                "calculatedAt" TIMESTAMP
            )');
            $db->prepare('DELETE FROM po_attainments WHERE "courseId" = :courseId')->execute(['courseId' => $courseId]);
            $poCalcQuery = '
                INSERT INTO po_attainments (id, "courseId", "poId", "studentId", "attainmentLevel", "calculatedAt")
                SELECT 
                    concat(\'poatt_\', substr(md5(random()::text), 1, 10)),
                    ca."courseId",
                    cp."poId",
                    ca."studentId",
                    AVG(CASE WHEN ca."metTarget" THEN cp."level" ELSE 0 END) as attainmentLevel,
                    NOW()
                FROM co_attainments ca
                JOIN co_po_mappings cp ON ca."coId" = cp."coId"
                WHERE ca."courseId" = :courseId
                GROUP BY ca."studentId", ca."courseId", cp."poId"
            ';
            $db->prepare($poCalcQuery)->execute(['courseId' => $courseId]);
        } catch (PDOException $e) {}

        // 4. Calculate Course-Level CO Attainment (3-2-1 Score with 40/60 Split)
        $db->prepare('DELETE FROM course_co_attainments WHERE "courseId" = :courseId')->execute(['courseId' => $courseId]);
        $courseCoCalcQuery = '
            INSERT INTO course_co_attainments (id, "courseId", "coId", "attainmentScore", "internalScore", "externalScore", "studentsAttempted", "studentsMetTarget", "percentageMet", "academicYear", "calculatedAt")
            SELECT
                concat(\'cca_\', substr(md5(random()::text), 1, 10)),
                "courseId",
                "coId",
                (
                    (CASE 
                        WHEN (COUNT(CASE WHEN "internalMetTarget" THEN 1 END) * 100.0 / NULLIF(COUNT(CASE WHEN "internalPercentage" IS NOT NULL THEN 1 END), 0)) >= :score3_1 THEN 3
                        WHEN (COUNT(CASE WHEN "internalMetTarget" THEN 1 END) * 100.0 / NULLIF(COUNT(CASE WHEN "internalPercentage" IS NOT NULL THEN 1 END), 0)) >= :score2_1 THEN 2
                        WHEN (COUNT(CASE WHEN "internalMetTarget" THEN 1 END) * 100.0 / NULLIF(COUNT(CASE WHEN "internalPercentage" IS NOT NULL THEN 1 END), 0)) >= :score1_1 THEN 1
                        ELSE 0 
                    END) * :iw +
                    (CASE 
                        WHEN (COUNT(CASE WHEN "externalMetTarget" THEN 1 END) * 100.0 / NULLIF(COUNT(CASE WHEN "externalPercentage" IS NOT NULL THEN 1 END), 0)) >= :score3_2 THEN 3
                        WHEN (COUNT(CASE WHEN "externalMetTarget" THEN 1 END) * 100.0 / NULLIF(COUNT(CASE WHEN "externalPercentage" IS NOT NULL THEN 1 END), 0)) >= :score2_2 THEN 2
                        WHEN (COUNT(CASE WHEN "externalMetTarget" THEN 1 END) * 100.0 / NULLIF(COUNT(CASE WHEN "externalPercentage" IS NOT NULL THEN 1 END), 0)) >= :score1_2 THEN 1
                        ELSE 0 
                    END) * :ew
                ) as attainmentScore,
                CASE 
                    WHEN (COUNT(CASE WHEN "internalMetTarget" THEN 1 END) * 100.0 / NULLIF(COUNT(CASE WHEN "internalPercentage" IS NOT NULL THEN 1 END), 0)) >= :score3_3 THEN 3
                    WHEN (COUNT(CASE WHEN "internalMetTarget" THEN 1 END) * 100.0 / NULLIF(COUNT(CASE WHEN "internalPercentage" IS NOT NULL THEN 1 END), 0)) >= :score2_3 THEN 2
                    WHEN (COUNT(CASE WHEN "internalMetTarget" THEN 1 END) * 100.0 / NULLIF(COUNT(CASE WHEN "internalPercentage" IS NOT NULL THEN 1 END), 0)) >= :score1_3 THEN 1
                    ELSE 0 
                END as internalScore,
                CASE 
                    WHEN (COUNT(CASE WHEN "externalMetTarget" THEN 1 END) * 100.0 / NULLIF(COUNT(CASE WHEN "externalPercentage" IS NOT NULL THEN 1 END), 0)) >= :score3_4 THEN 3
                    WHEN (COUNT(CASE WHEN "externalMetTarget" THEN 1 END) * 100.0 / NULLIF(COUNT(CASE WHEN "externalPercentage" IS NOT NULL THEN 1 END), 0)) >= :score2_4 THEN 2
                    WHEN (COUNT(CASE WHEN "externalMetTarget" THEN 1 END) * 100.0 / NULLIF(COUNT(CASE WHEN "externalPercentage" IS NOT NULL THEN 1 END), 0)) >= :score1_4 THEN 1
                    ELSE 0 
                END as externalScore,
                COUNT(*) as studentsAttempted,
                COUNT(CASE WHEN "metTarget" THEN 1 END) as studentsMetTarget,
                (COUNT(CASE WHEN "metTarget" THEN 1 END) * 100.0 / COUNT(*)) as percentageMet,
                :academicYear,
                NOW()
            FROM co_attainments
            WHERE "courseId" = :courseId
            GROUP BY "courseId", "coId"
        ';
        $db->prepare($courseCoCalcQuery)->execute([
            'courseId' => $courseId, 
            'score3_1' => $score3, 'score2_1' => $score2, 'score1_1' => $score1,
            'score3_2' => $score3, 'score2_2' => $score2, 'score1_2' => $score1,
            'score3_3' => $score3, 'score2_3' => $score2, 'score1_3' => $score1,
            'score3_4' => $score3, 'score2_4' => $score2, 'score1_4' => $score1,
            'iw' => $iw, 'ew' => $ew,
            'academicYear' => $academicYear
        ]);

        // 5. Calculate Course-Level PO Attainment (Execution Averages + Indirect 80/20 Split)
        $db->prepare('DELETE FROM course_po_attainments WHERE "courseId" = :courseId')->execute(['courseId' => $courseId]);
        $coursePoCalcQuery = '
            INSERT INTO course_po_attainments (id, "courseId", "poId", "directLevel", "indirectLevel", "attainmentLevel", "academicYear", "calculatedAt")
            SELECT
                concat(\'cpa_\', substr(md5(random()::text), 1, 10)),
                cca."courseId",
                cp."poId",
                AVG(cca."attainmentScore" * (cp."level" / 3.0)) as directLevel,
                COALESCE(MAX(cip."indirectLevel"), 0) as indirectLevel,
                (0.8 * AVG(cca."attainmentScore" * (cp."level" / 3.0))) + (0.2 * COALESCE(MAX(cip."indirectLevel"), 0)) as attainmentLevel,
                :academicYear,
                NOW()
            FROM course_co_attainments cca
            JOIN co_po_mappings cp ON cca."coId" = cp."coId"
            LEFT JOIN course_indirect_po cip ON cip."courseId" = cca."courseId" AND cip."poId" = cp."poId"
            WHERE cca."courseId" = :courseId
            GROUP BY cca."courseId", cp."poId"
        ';
        $db->prepare($coursePoCalcQuery)->execute(['courseId' => $courseId, 'academicYear' => $academicYear]);
        
        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Attainments calculated successfully']);
    } catch (PDOException $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
    exit;
}
?>
