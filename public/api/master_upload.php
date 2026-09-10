<?php
require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];
$db = (new Database())->getDb();

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['data']) || !is_array($input['data'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid or empty JSON data provided.']);
        exit;
    }

    $rows = $input['data'];

    try {
        $db->beginTransaction();

        $stats = ['colleges' => 0, 'programs' => 0, 'batches' => 0, 'courses' => 0, 'students' => 0, 'enrollments' => 0];
        
        // Caches to avoid repeated DB queries for every row
        $cache = ['colleges' => [], 'programs' => [], 'batches' => [], 'courses' => [], 'students' => [], 'enrollments' => []];

        // Pre-load caches to make it blazing fast (no SELECTs inside the loop)
        foreach ($db->query('SELECT id, name FROM colleges')->fetchAll() as $row) {
            $cache['colleges'][strtolower($row['name'])] = $row['id'];
        }
        foreach ($db->query('SELECT id, "collegeId", name FROM programs')->fetchAll() as $row) {
            $cache['programs'][$row['collegeId'] . '_' . strtolower($row['name'])] = $row['id'];
        }
        foreach ($db->query('SELECT id, "programId", name FROM batches')->fetchAll() as $row) {
            $cache['batches'][$row['programId'] . '_' . strtolower($row['name'])] = $row['id'];
        }
        foreach ($db->query('SELECT id, "batchId", code FROM courses')->fetchAll() as $row) {
            $cache['courses'][$row['batchId'] . '_' . strtolower($row['code'])] = $row['id'];
        }
        foreach ($db->query('SELECT id, "studentId" FROM students')->fetchAll() as $row) {
            $cache['students'][strtolower($row['studentId'])] = $row['id'];
        }
        foreach ($db->query('SELECT "studentId", "courseId" FROM enrollments')->fetchAll() as $row) {
            $cache['enrollments'][$row['studentId'] . '_' . $row['courseId']] = true;
        }

        foreach ($rows as $data) {
            if (!is_array($data) || empty(trim($data[0] ?? ''))) continue; // Skip empty rows

            $cName = trim($data[0] ?? '');
            $pName = trim($data[1] ?? '');
            $pLevel = trim($data[2] ?? 'UG');
            $bName = trim($data[3] ?? '');
            $crCode = trim($data[4] ?? '');
            $crName = trim($data[5] ?? '');
            $stuRoll = trim($data[6] ?? '');
            $stuName = trim($data[7] ?? '');

            // 1. Process College
            $collegeId = null;
            $cacheKeyC = strtolower($cName);
            if (isset($cache['colleges'][$cacheKeyC])) {
                $collegeId = $cache['colleges'][$cacheKeyC];
            } else {
                $collegeId = uniqid('col_');
                $code = strtoupper(substr($cName, 0, 3)) . '_' . rand(1000, 9999);
                $ins = $db->prepare('INSERT INTO colleges (id, name, code, "isActive", "createdAt", "updatedAt") VALUES (?, ?, ?, true, NOW(), NOW())');
                $ins->execute([$collegeId, $cName, $code]);
                $stats['colleges']++;
                $cache['colleges'][$cacheKeyC] = $collegeId;
            }

            if (empty($pName)) continue;

            // 2. Process Program
            $programId = null;
            $cacheKeyP = $collegeId . '_' . strtolower($pName);
            if (isset($cache['programs'][$cacheKeyP])) {
                $programId = $cache['programs'][$cacheKeyP];
            } else {
                $programId = uniqid('prog_');
                $code = strtoupper(substr($pName, 0, 3)) . '_' . rand(1000, 9999);
                $ins = $db->prepare('INSERT INTO programs (id, "collegeId", name, code, duration, level, threshold, "isActive", "createdAt", "updatedAt") VALUES (?, ?, ?, ?, 4, ?, 60, true, NOW(), NOW())');
                $ins->execute([$programId, $collegeId, $pName, $code, $pLevel]);
                $stats['programs']++;
                $cache['programs'][$cacheKeyP] = $programId;
            }

            if (empty($bName)) continue;

            // 3. Process Batch
            $batchId = null;
            $cacheKeyB = $programId . '_' . strtolower($bName);
            if (isset($cache['batches'][$cacheKeyB])) {
                $batchId = $cache['batches'][$cacheKeyB];
            } else {
                $batchId = uniqid('bat_');
                // try to parse year from something like 2021-2025
                $startYear = 2024; $endYear = 2028;
                if (preg_match('/(\d{4})/', $bName, $matches)) {
                    $startYear = (int)$matches[1];
                    $endYear = $startYear + 4;
                }
                $ins = $db->prepare('INSERT INTO batches (id, "programId", name, "startYear", "endYear", "isActive", "createdAt", "updatedAt") VALUES (?, ?, ?, ?, ?, true, NOW(), NOW())');
                $ins->execute([$batchId, $programId, $bName, $startYear, $endYear]);
                $stats['batches']++;
                $cache['batches'][$cacheKeyB] = $batchId;
            }

            if (empty($crCode) || empty($crName)) continue;

            // 4. Process Course
            $courseId = null;
            $cacheKeyCr = $batchId . '_' . strtolower($crCode);
            if (isset($cache['courses'][$cacheKeyCr])) {
                $courseId = $cache['courses'][$cacheKeyCr];
            } else {
                $courseId = uniqid('crs_');
                $ins = $db->prepare('INSERT INTO courses (id, "batchId", name, code, status, "targetPercentage", "level1Threshold", "level2Threshold", "level3Threshold", "isActive", "createdAt", "updatedAt") VALUES (?, ?, ?, ?, \'ACTIVE\', 50, 50, 70, 80, true, NOW(), NOW())');
                $ins->execute([$courseId, $batchId, $crName, $crCode]);
                $stats['courses']++;
                $cache['courses'][$cacheKeyCr] = $courseId;
            }

            if (empty($stuRoll) || empty($stuName)) continue;

            // 5. Process Student
            $studentId = null;
            $cacheKeyStu = strtolower($stuRoll);
            if (isset($cache['students'][$cacheKeyStu])) {
                $studentId = $cache['students'][$cacheKeyStu];
            } else {
                $studentId = uniqid('stu_');
                $ins = $db->prepare('INSERT INTO students (id, "studentId", name, "collegeId", "programId", "batchId", "isActive", "createdAt", "updatedAt") VALUES (?, ?, ?, ?, ?, ?, true, NOW(), NOW())');
                $ins->execute([$studentId, $stuRoll, $stuName, $collegeId, $programId, $batchId]);
                $stats['students']++;
                $cache['students'][$cacheKeyStu] = $studentId;
            }

            // 6. Process Enrollment
            $cacheKeyEnr = $studentId . '_' . $courseId;
            if (!isset($cache['enrollments'][$cacheKeyEnr])) {
                $ins = $db->prepare('INSERT INTO enrollments (id, "studentId", "courseId", "isActive", "createdAt", "updatedAt") VALUES (?, ?, ?, true, NOW(), NOW())');
                $ins->execute([uniqid('enr_'), $studentId, $courseId]);
                $stats['enrollments']++;
                $cache['enrollments'][$cacheKeyEnr] = true;
            }
        }

        $db->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Upload successful!',
            'stats' => $stats
        ]);

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        echo json_encode(['success' => false, 'message' => 'Error processing file: ' . $e->getMessage()]);
    }
    exit;
}
?>
