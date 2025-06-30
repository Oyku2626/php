<?php
// Gerekli dosyaları dahil et
require_once __DIR__ . '/dbo_test.php';
require_once __DIR__ . '/config/core.php';
require_once __DIR__ . '/libs/src/JWT.php';
require_once __DIR__ . '/libs/src/Key.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// JSON verisini al
$data = json_decode(file_get_contents("php://input"), true);

// Gerekli alanların olup olmadığını kontrol et
if (!isset($data['name'], $data['email'], $data['password_hash'])) {
    http_response_code(400);
    echo json_encode(["message" => "Tüm alanlar gereklidir."]);
    exit;
}

$name = htmlspecialchars(strip_tags($data['name']));
$email = htmlspecialchars(strip_tags($data['email']));
$password = $data['password_hash'];

// E-posta benzersizlik kontrolü (lecturers ve admins tablolarında)
$query = "SELECT id FROM lecturers WHERE email = :email
          UNION
          SELECT id FROM admins WHERE email = :email";

$stmt = $pdo->prepare($query);
$stmt->bindParam(':email', $email);
$stmt->execute();

if ($stmt->rowCount() > 0) {
    http_response_code(409);
    echo json_encode(["message" => "Bu e-posta adresi zaten kullanımda."]);
    exit;
}

// Şifreyi hashle
$passwordHash = password_hash($password, PASSWORD_BCRYPT);

// Veritabanına ekleme
$insertQuery = "INSERT INTO lecturers (name, email, password_hash) VALUES (:name, :email, :password_hash)";
$insertStmt = $pdo->prepare($insertQuery);
$insertStmt->bindParam(':name', $name);
$insertStmt->bindParam(':email', $email);
$insertStmt->bindParam(':password_hash', $passwordHash);

try {
    $insertStmt->execute();
    $userId = $pdo->lastInsertId();

    // JWT oluşturma
    $payload = [
        "id" => $userId,
        "role" => "lecturer",
        "iat" => time(),
        "exp" => time() + (60 * 60) // 1 saat geçerli
    ];

    $jwt = JWT::encode($payload, JWT_SECRET_KEY, 'HS256');

    http_response_code(201);
    echo json_encode([
        "message" => "User was successfully registered.",
        "token" => $jwt
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["message" => "Kayıt sırasında bir hata oluştu.", "error" => $e->getMessage()]);
}
?>
