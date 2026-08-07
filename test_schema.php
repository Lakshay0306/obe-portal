<?php
require 'public/api/db.php';
try {
    $db = (new Database())->getDb();
    $stmt = $db->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'student_marks'");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
