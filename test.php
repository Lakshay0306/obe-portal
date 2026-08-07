<?php
require 'public/api/db.php';
$db = (new Database())->getDb();
$stmt = $db->query('SELECT * FROM colleges');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
