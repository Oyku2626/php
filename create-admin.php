
<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config/auth-guard.php';

$requesting_user = validate_token();

if ($requesting_user->role !== 'admin') {
    http_response_code(403);
    echo json_encode(["message" => "Bu işlemi yapmaya yetkiniz yok."], JSON_UNESCAPED_UNICODE);
    exit;
}
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['name'], $data['email'], $data['password_hash'])) {
    http_response_code(400);
    echo json_encode(["message" => "Tüm alanlar gereklidir."], JSON_UNESCAPED_UNICODE);
    exit;
}

$name = htmlspecialchars(strip_tags($data['name']));
$email = htmlspecialchars(strip_tags($data['email']));
$password = $data['password_hash'];


$query = "SELECT id FROM admins WHERE email = :email
          UNION
          SELECT id FROM lecturers WHERE email = :email";

$stmt = $pdo->prepare($query);
$stmt->bindParam(':email', $email);
$stmt->execute();

if ($stmt->rowCount() > 0) {
    http_response_code(409);
    echo json_encode(["message" => "Bu e-posta adresi zaten kullanımda."], JSON_UNESCAPED_UNICODE);
    exit;
}


$hashedPassword = password_hash($password, PASSWORD_BCRYPT);

$insertQuery = "INSERT INTO admins (name, email, password_hash) VALUES (:name, :email, :password_hash)";
$insertStmt = $pdo->prepare($insertQuery);
$insertStmt->bindParam(':name', $name);
$insertStmt->bindParam(':email', $email);
$insertStmt->bindParam(':password_hash', $hashedPassword);
try {
    $insertStmt->execute();

    http_response_code(201);
    echo json_encode([
        "message" => "Admin user was successfully created."
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "message" => "Kayıt sırasında bir hata oluştu.",
        "error" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
