<?php
require_once 'db.php';


$action = isset($_GET['action']) ? $_GET['action'] : '';
$db = (new Database())->getDb();

if ($action === 'login') {
    $data = json_decode(file_get_contents("php://input"), true);
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';

    if (empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Email and password required']);
        exit;
    }

    try {
        $stmt = $db->prepare('SELECT id, name, role, password FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $token = JWT::encode(['id' => $user['id'], 'role' => $user['role'], 'name' => $user['name']]);
            echo json_encode(['success' => true, 'message' => 'Login successful', 'token' => $token, 'role' => $user['role'], 'name' => $user['name']]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    exit;
}

if ($action === 'logout') {
    echo json_encode(['success' => true, 'message' => 'Logged out']);
    exit;
}

if ($action === 'me') {
    $headers = getallheaders();
    $auth = isset($headers['Authorization']) ? $headers['Authorization'] : '';
    
    if (strpos($auth, 'Bearer ') !== false) {
        $token = str_replace('Bearer ', '', $auth);
        $payload = JWT::decode($token);
        if ($payload && isset($payload['id'])) {
            echo json_encode(['success' => true, 'user_id' => $payload['id'], 'role' => $payload['role'], 'name' => $payload['name']]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid token']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>
