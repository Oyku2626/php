<?php
include_once 'dbo_test.php';

try {
    // Admins şifrelerini hashle
    $stmt = $pdo->query("SELECT id, password_hash FROM admins WHERE password_hash IS NOT NULL");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $hashed = password_hash($row['password_hash'], PASSWORD_BCRYPT);
        $update = $pdo->prepare("UPDATE admins SET password_hash = :hash WHERE id = :id");
        $update->execute([':hash' => $hashed, ':id' => $row['id']]);
    }

    // Lecturers şifrelerini hashle
    $stmt = $pdo->query("SELECT id, password_hash FROM lecturers WHERE password_hash IS NOT NULL");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $hashed = password_hash($row['password_hash'], PASSWORD_BCRYPT);
        $update = $pdo->prepare("UPDATE lecturers SET password_hash = :hash WHERE id = :id");
        $update->execute([':hash' => $hashed, ':id' => $row['id']]);
    }

    echo "Şifreler başarıyla hashlenip güncellendi.\n";

} catch (PDOException $e) {
    echo "Hata: " . $e->getMessage();
}