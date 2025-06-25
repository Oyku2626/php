<?php
// Gerekli başlıkları ayarlayalım.
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Veritabanı bağlantısını dahil edelim.
include_once 'dbo_test.php';

// Gelen isteğin metodunu alalım.
$method = $_SERVER['REQUEST_METHOD'];

// Metoda göre işlem yapalım.
switch ($method) {
    case 'GET':
        // Bir ID belirtilmiş mi kontrol edelim (örn: /api/rooms.php?id=3)
        $id = isset($_GET['id']) ? $_GET['id'] : null;
        if ($id) {
            $stmt = $pdo->prepare("SELECT * FROM rooms WHERE id = ?");
            $stmt->execute([$id]);
            $room = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode($room);
        } else {
            $stmt = $pdo->query("SELECT * FROM rooms ORDER BY name");
            $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($rooms);
        }
        break;

    case 'POST':
        // Gelen JSON verisini alalım.
        $data = json_decode(file_get_contents("php://input"));
        // Temel bir doğrulama yapalım.
        if (!empty($data->name) && !empty($data->capacity)) {
            $stmt = $pdo->prepare("INSERT INTO rooms (name, capacity, features, building) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$data->name, $data->capacity, $data->features ?? '', $data->building ?? ''])) {
                http_response_code(201); // Created
                echo json_encode(['status' => 'success', 'message' => 'Room created successfully.']);
            } else {
                http_response_code(503); // Service Unavailable
                echo json_encode(['status' => 'error', 'message' => 'Unable to create room.']);
            }
        } else {
            http_response_code(400); // Bad Request
            echo json_encode(['status' => 'error', 'message' => 'Incomplete data.']);
        }
        break;

    case 'PUT':
        $data = json_decode(file_get_contents("php://input"));
        if (!empty($data->id) && !empty($data->name) && !empty($data->capacity)) {
            $stmt = $pdo->prepare("UPDATE rooms SET name = ?, capacity = ?, features = ?, building = ? WHERE id = ?");
            if ($stmt->execute([$data->name, $data->capacity, $data->features ?? '', $data->building ?? '', $data->id])) {
                echo json_encode(['status' => 'success', 'message' => 'Room updated successfully.']);
            } else {
                http_response_code(503);
                echo json_encode(['status' => 'error', 'message' => 'Unable to update room.']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Incomplete data. ID is required.']);
        }
        break;

    case 'DELETE':
        $data = json_decode(file_get_contents("php://input"));
        if (!empty($data->id)) {
            $stmt = $pdo->prepare("DELETE FROM rooms WHERE id = ?");
            if ($stmt->execute([$data->id])) {
                echo json_encode(['status' => 'success', 'message' => 'Room deleted successfully.']);
            } else {
                http_response_code(503);
                echo json_encode(['status' => 'error', 'message' => 'Unable to delete room.']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'ID is required.']);
        }
        break;

    default:
        http_response_code(405); // Method Not Allowed
        echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
        break;
}
?>