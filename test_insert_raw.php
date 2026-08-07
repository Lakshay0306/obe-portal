<?php
$host = 'aws-1-ap-northeast-1.pooler.supabase.com';
$port = '5432';
$dbname = 'postgres';
$user = 'postgres.hqqenhwigocfrtkmkgem';
$password = 'Gambhirs@123';

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $db = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
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
