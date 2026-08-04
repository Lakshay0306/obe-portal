<?php
require_once 'db.php';


try {
    $db = (new Database())->getDb();
    
    // Students Count
    $stmt1 = $db->query('SELECT COUNT(*) FROM students');
    $studentsCount = $stmt1->fetchColumn();

    // Courses Count
    $stmt2 = $db->query('SELECT COUNT(*) FROM courses');
    $coursesCount = $stmt2->fetchColumn();

    // Attainment Rate
    $stmt3 = $db->query('SELECT AVG(percentage) FROM co_attainments');
    $avg = $stmt3->fetchColumn();
    $attainmentRate = $avg ? round((float)$avg, 1) : 0;

    // Pending Assessments
    $stmt4 = $db->query('
        SELECT COUNT(*) FROM assessments a 
        WHERE NOT EXISTS (
            SELECT 1 FROM questions q 
            JOIN student_marks sm ON q.id = sm."questionId" 
            WHERE q."assessmentId" = a.id
        )
    ');
    $assessmentsCount = $stmt4->fetchColumn();

    // Recent Activities
    $stmt5 = $db->query('
        SELECT \'Mark added for \' || st.name as desc, m."submittedAt" as time, \'var(--primary)\' as color
        FROM student_marks m
        JOIN students st ON m."studentId" = st.id
        ORDER BY m."submittedAt" DESC LIMIT 3
    ');
    $activities = $stmt5->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($activities)) {
        $activities = [
            ['desc' => 'System initialized. Ready for data entry.', 'time' => 'Just now', 'color' => 'var(--success)']
        ];
    }

    // Chart Data: Attainment per CO
    $stmt6 = $db->query('
        SELECT co.code as co_code, AVG(a.percentage) as avg_percentage
        FROM co_attainments a
        JOIN cos co ON a."coId" = co.id
        GROUP BY co.code
        ORDER BY co.code ASC
        LIMIT 10
    ');
    $chartData = $stmt6->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true, 
        'data' => [
            'totalStudents' => $studentsCount,
            'activeCourses' => $coursesCount,
            'attainmentRate' => $attainmentRate,
            'pendingAssessments' => $assessmentsCount,
            'recentActivities' => $activities,
            'chartData' => $chartData
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
}
?>
