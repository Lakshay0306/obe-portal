<?php
// PostgreSQL Database connection using PDO
$host = 'aws-1-ap-northeast-1.pooler.supabase.com';
$port = '5432';
$dbname = 'postgres';
$user = 'postgres.hqqenhwigocfrtkmkgem';
$password = 'Gambhirs@123'; // Decoded from %40

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_PERSISTENT => true
    ]);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database Connection Failed: ' . $e->getMessage()]);
    exit;
}

class Database {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function getDb() {
        return $this->pdo;
    }
}

class JWT {
    private static $secret = 'obe_portal_super_secret_key_2026!@#';

    public static function encode($payload) {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode($payload);

        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));

        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, self::$secret, true);
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    public static function decode($jwt) {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) return false;

        list($header, $payload, $signature) = $parts;

        $valid_signature = hash_hmac('sha256', $header . "." . $payload, self::$secret, true);
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($valid_signature));

        if ($base64UrlSignature === $signature) {
            return json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $payload)), true);
        }
        return false;
    }
}

if (!function_exists('getallheaders')) {
    function getallheaders() {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}

// Centralized CORS and Headers
header('Content-Type: application/json');

$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
if ($origin) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    header("Access-Control-Allow-Origin: *");
}

header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Authentication Middleware
$current_file = basename($_SERVER['SCRIPT_NAME']);
// Add login and any public endpoints to whitelist
$whitelist = ['auth.php']; 

if (!in_array($current_file, $whitelist)) {
    $headers = getallheaders();
    $auth = isset($headers['Authorization']) ? $headers['Authorization'] : '';
    if (strpos($auth, 'Bearer ') !== false) {
        $token = str_replace('Bearer ', '', $auth);
        $payload = JWT::decode($token);
        if (!$payload || !isset($payload['id'])) {
            header('HTTP/1.1 401 Unauthorized');
            echo json_encode(['success' => false, 'message' => 'Invalid or expired token']);
            exit;
        }
        // Pass payload to globals so other files can use user id
        $GLOBALS['auth_user'] = $payload;
    } else {
        header('HTTP/1.1 401 Unauthorized');
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }
}
// XSS Mitigation: Sanitize all JSON output globally
function sanitize_output($data) {
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $data[$key] = sanitize_output($value);
        }
    } elseif (is_string($data)) {
        return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
    return $data;
}

ob_start(function($buffer) {
    $data = json_decode($buffer, true);
    if ($data !== null) {
        $data = sanitize_output($data);
        return json_encode($data);
    }
    return $buffer;
});

?>