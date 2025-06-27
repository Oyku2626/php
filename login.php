<?php
// Veritabanı ve JWT yapılandırmaları
require_once __DIR__ . '/dbo_test.php';
require_once __DIR__ . '/config/core.php';

// JWT dosyaları (sıralama önemli!)
require_once __DIR__ . '/libs/src/JWTExceptionWithPayloadInterface.php';
require_once __DIR__ . '/libs/src/ExpiredException.php';
require_once __DIR__ . '/libs/src/SignatureInvalidException.php';
require_once __DIR__ . '/libs/src/JWT.php';
require_once __DIR__ . '/libs/src/Key.php';

use Firebase\JWT\JWT;

// Yanıt formatı JSON
header("Content-Type: application/json");

// Gelen veriyi al
$data = json_decode(file_get_contents("php://input"));
$email = $data->email ?? null;
$password = $data->password ?? null;

// E-posta veya şifre eksikse
if (!$email || !$password) {
    http_response_code(400);
    echo json_encode(['error' => 'Email ve parola gerekli']);
    exit;
}

$user = null;
$role = null;

// 1. Adminlerde ara
$stmt = $pdo->prepare("SELECT id, password_hash FROM admins WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    $role = 'admin';
} else {
    // 2. Akademisyenlerde ara
    $stmt = $pdo->prepare("SELECT id, password_hash FROM lecturers WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $role = 'lecturer';
    }
}


// Kullanıcı hiç yoksa veya şifre hatalıysa
if (!$user || !password_verify($password, $user['password_hash'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Login failed']);
    exit;
}

// JWT payload
$payload = [
    'iss' => 'https://baskent.edu.tr',     // Yayıncı
    'iat' => time(),                       // Yayınlanma zamanı
    'exp' => time() + 3600,                // 1 saat geçerli
    'data' => [
        'id' => $user['id'],
        'role' => $role
    ]
];

// Token oluştur
$token = JWT::encode($payload, JWT_SECRET_KEY, 'HS256');

// Başarı yanıtı
http_response_code(200);
echo json_encode([
    'message' => 'Login successful',
    'token' => $token
]);
