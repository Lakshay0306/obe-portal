<?php
require 'public/api/db.php';
try {
    $db = (new Database())->getDb();
    $stmt = $db->prepare('INSERT INTO colleges (id, name, code, "isActive", "createdAt", "updatedAt") VALUES (:id, :name, :code, true, NOW(), NOW())');
    $id = uniqid('col_');
    $stmt->execute([
        'id' => $id,
        'name' => 'CUIET',
        'code' => '100'
    ]);
    echo "Success";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
