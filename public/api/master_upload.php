<?php
require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];
$db = (new Database())->getDb();

if ($method === 'POST') {
    if (!isset($_FILES['file'])) {
        echo json_encode(['success' => false, 'message' => 'No file uploaded']);
        exit;
    }

    $file = $_FILES['file'];
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);

    if (strtolower($ext) !== 'csv') {
        echo json_encode(['success' => false, 'message' => 'Please upload a CSV file. If you have Excel, save it as CSV first.']);
        exit;
    }

    try {
        $db->beginTransaction();

        $handle = fopen($file['tmp_name'], "r");
        if ($handle !== FALSE) {
            $header = fgetcsv($handle, 1000, ",");
            
            // Expected headers: College_Name, Program_Name, Level, Batch_Name, Course_Code, Course_Name, Student_Roll, Student_Name
            // We will just map by position assuming the user follows the template exactly.
            // Pos 0: College_Name
            // Pos 1: Program_Name
            // Pos 2: Level (UG/PG)
            // Pos 3: Batch_Name (e.g. 2021-2025)
            // Pos 4: Course_Code
            // Pos 5: Course_Name
            // Pos 6: Student_Roll (Optional)
            // Pos 7: Student_Name (Optional)

            $stats = ['colleges' => 0, 'programs' => 0, 'batches' => 0, 'courses' => 0, 'students' => 0, 'enrollments' => 0];
            
            // Caches to avoid repeated DB queries for every row
            $cache = ['colleges' => [], 'programs' => [], 'batches' => [], 'courses' => [], 'students' => []];

            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                if (empty(trim($data[0]))) continue; // Skip empty rows

                $cName = trim($data[0]);
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
                    $stmt = $db->prepare('SELECT id FROM colleges WHERE name ILIKE :name');
                    $stmt->execute(['name' => $cName]);
                    $row = $stmt->fetch();
                    if ($row) {
                        $collegeId = $row['id'];
                    } else {
                        $collegeId = uniqid('col_');
                        $code = strtoupper(substr($cName, 0, 3));
                        $ins = $db->prepare('INSERT INTO colleges (id, name, code, "isActive", "createdAt") VALUES (?, ?, ?, true, NOW())');
                        $ins->execute([$collegeId, $cName, $code]);
                        $stats['colleges']++;
                    }
                    $cache['colleges'][$cacheKeyC] = $collegeId;
                }

                if (empty($pName)) continue;

                // 2. Process Program
                $programId = null;
                $cacheKeyP = $collegeId . '_' . strtolower($pName);
                if (isset($cache['programs'][$cacheKeyP])) {
                    $programId = $cache['programs'][$cacheKeyP];
                } else {
                    $stmt = $db->prepare('SELECT id FROM programs WHERE name ILIKE :name AND "collegeId" = :cid');
                    $stmt->execute(['name' => $pName, 'cid' => $collegeId]);
                    $row = $stmt->fetch();
                    if ($row) {
                        $programId = $row['id'];
                    } else {
                        $programId = uniqid('prog_');
                        $code = strtoupper(substr($pName, 0, 3));
                        $ins = $db->prepare('INSERT INTO programs (id, "collegeId", name, code, duration, level, threshold, "isActive", "createdAt") VALUES (?, ?, ?, ?, 4, ?, 60, true, NOW())');
                        $ins->execute([$programId, $collegeId, $pName, $code, $pLevel]);
                        $stats['programs']++;
                    }
                    $cache['programs'][$cacheKeyP] = $programId;
                }

                if (empty($bName)) continue;

                // 3. Process Batch
                $batchId = null;
                $cacheKeyB = $programId . '_' . strtolower($bName);
                if (isset($cache['batches'][$cacheKeyB])) {
                    $batchId = $cache['batches'][$cacheKeyB];
                } else {
                    $stmt = $db->prepare('SELECT id FROM batches WHERE name ILIKE :name AND "programId" = :pid');
                    $stmt->execute(['name' => $bName, 'pid' => $programId]);
                    $row = $stmt->fetch();
                    if ($row) {
                        $batchId = $row['id'];
                    } else {
                        $batchId = uniqid('bat_');
                        // try to parse year from something like 2021-2025
                        $startYear = 2024; $endYear = 2028;
                        if (preg_match('/(\d{4})/', $bName, $matches)) {
                            $startYear = (int)$matches[1];
                            $endYear = $startYear + 4;
                        }
                        $ins = $db->prepare('INSERT INTO batches (id, "programId", name, "startYear", "endYear", "isActive", "createdAt") VALUES (?, ?, ?, ?, ?, true, NOW())');
                        $ins->execute([$batchId, $programId, $bName, $startYear, $endYear]);
                        $stats['batches']++;
                    }
                    $cache['batches'][$cacheKeyB] = $batchId;
                }

                if (empty($crCode) || empty($crName)) continue;

                // 4. Process Course
                $courseId = null;
                $cacheKeyCr = $batchId . '_' . strtolower($crCode);
                if (isset($cache['courses'][$cacheKeyCr])) {
                    $courseId = $cache['courses'][$cacheKeyCr];
                } else {
                    $stmt = $db->prepare('SELECT id FROM courses WHERE code ILIKE :code AND "batchId" = :bid');
                    $stmt->execute(['code' => $crCode, 'bid' => $batchId]);
                    $row = $stmt->fetch();
                    if ($row) {
                        $courseId = $row['id'];
                    } else {
                        $courseId = uniqid('crs_');
                        $ins = $db->prepare('INSERT INTO courses (id, "batchId", name, code, status, "targetPercentage", "level1Threshold", "level2Threshold", "level3Threshold", "isActive", "createdAt") VALUES (?, ?, ?, ?, \'ACTIVE\', 50, 50, 70, 80, true, NOW())');
                        $ins->execute([$courseId, $batchId, $crName, $crCode]);
                        $stats['courses']++;
                    }
                    $cache['courses'][$cacheKeyCr] = $courseId;
                }

                if (empty($stuRoll) || empty($stuName)) continue;

                // 5. Process Student
                $studentId = null;
                $cacheKeyStu = strtolower($stuRoll);
                if (isset($cache['students'][$cacheKeyStu])) {
                    $studentId = $cache['students'][$cacheKeyStu];
                } else {
                    $stmt = $db->prepare('SELECT id FROM students WHERE "studentId" ILIKE :roll');
                    $stmt->execute(['roll' => $stuRoll]);
                    $row = $stmt->fetch();
                    if ($row) {
                        $studentId = $row['id'];
                    } else {
                        $studentId = uniqid('stu_');
                        $ins = $db->prepare('INSERT INTO students (id, "studentId", name, "collegeId", "programId", "batchId", "isActive", "createdAt") VALUES (?, ?, ?, ?, ?, ?, true, NOW())');
                        $ins->execute([$studentId, $stuRoll, $stuName, $collegeId, $programId, $batchId]);
                        $stats['students']++;
                    }
                    $cache['students'][$cacheKeyStu] = $studentId;
                }

                // 6. Process Enrollment
                $stmt = $db->prepare('SELECT id FROM enrollments WHERE "studentId" = :sid AND "courseId" = :cid');
                $stmt->execute(['sid' => $studentId, 'cid' => $courseId]);
                if (!$stmt->fetch()) {
                    $ins = $db->prepare('INSERT INTO enrollments (id, "studentId", "courseId", "enrolledAt") VALUES (?, ?, ?, NOW())');
                    $ins->execute([uniqid('enr_'), $studentId, $courseId]);
                    $stats['enrollments']++;
                }
            }
            fclose($handle);
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
