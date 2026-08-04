<?php
require_once 'db.php';


$method = $_SERVER['REQUEST_METHOD'];
$db = (new Database())->getDb();

if ($method === 'GET') {
    try {
        $stmt = $db->query('
            SELECT u.id, u.name, u.email, u."employeeId", u.role, u."isActive",
                   c.name as college_name, p.name as program_name
            FROM users u
            LEFT JOIN colleges c ON u."collegeId" = c.id
            LEFT JOIN programs p ON u."programId" = p.id
            ORDER BY u."createdAt" DESC
        ');
        $users = $stmt->fetchAll();
        
        foreach ($users as &$u) {
            $u['isActive'] = (bool)$u['isActive'];
        }
        
        echo json_encode(['success' => true, 'data' => $users]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    try {
        $stmt = $db->prepare('INSERT INTO users (id, name, email, "employeeId", password, role, "collegeId", "programId", "isActive", "createdAt", "updatedAt") 
                              VALUES (:id, :name, :email, :employeeId, :password, CAST(:role AS "UserRole"), :collegeId, :programId, true, NOW(), NOW())');
        $id = uniqid('usr_');
        $password = password_hash($data['password'], PASSWORD_DEFAULT);
        
        $stmt->execute([
            'id' => $id,
            'name' => $data['name'],
            'email' => empty($data['email']) ? null : $data['email'],
            'employeeId' => empty($data['employeeId']) ? null : $data['employeeId'],
            'password' => $password,
            'role' => $data['role'],
            'collegeId' => empty($data['collegeId']) ? null : $data['collegeId'],
            'programId' => empty($data['programId']) ? null : $data['programId']
        ]);
        echo json_encode(['success' => true, 'message' => 'User created', 'id' => $id]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'DELETE') {
    $id = $_GET['id'] ?? '';
    try {
        $stmt = $db->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
        echo json_encode(['success' => true, 'message' => 'User deleted']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
    exit;
}
?>
