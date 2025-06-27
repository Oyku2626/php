<?php
require_once __DIR__ . '/../libs/src/JWT.php';
require_once __DIR__ . '/../libs/src/Key.php';
require_once __DIR__ . '/../libs/src/ExpiredException.php';
require_once __DIR__ . '/../libs/src/SignatureInvalidException.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once 'dbo_test.php';

// Auth Guard'ı dahil et ve token doğrula
include_once __DIR__ . '/../config/auth-guard.php';
$user_data = validate_token();

function isRoomAvailable($pdo, $room_id, $date, $start_time, $end_time) {
    $stmt = $pdo->prepare("
        SELECT id FROM room_reservations
        WHERE room_id = ? AND date = ? AND status != 'rejected'
        AND start_time < ? AND end_time > ?
    ");
    $stmt->execute([$room_id, $date, $end_time, $start_time]);
    return $stmt->rowCount() === 0;
}

function isLecturerAvailable($pdo, $lecturer_id, $date, $start_time, $end_time) {
    $stmt = $pdo->prepare("
        SELECT id FROM room_reservations
        WHERE lecturer_id = ? AND date = ? AND status != 'rejected'
        AND start_time < ? AND end_time > ?
    ");
    $stmt->execute([$lecturer_id, $date, $end_time, $start_time]);
    return $stmt->rowCount() === 0;
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $lecturer_id = isset($_GET['lecturer_id']) ? intval($_GET['lecturer_id']) : null;

        $query = "
            SELECT
                res.id,
                res.lecturer_id,
                res.room_id,
                res.date,
                res.start_time,
                res.end_time,
                res.status,
                res.created_at,
                r.name as rooms_name,
                l.name as lecturers_name
            FROM
                room_reservations res
            LEFT JOIN
                rooms r ON res.room_id = r.id
            LEFT JOIN
                lecturers l ON res.lecturer_id = l.id
        ";

        if ($lecturer_id) {
            // Sadece kendi rezervasyonlarını görebilsin, rolü kontrol et
            if ($user_data->role === 'lecturer' && $user_data->id !== $lecturer_id) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'Access denied. You can only see your own reservations.']);
                exit();
            }
            $query .= " WHERE res.lecturer_id = :lecturer_id";
        }

        $query .= " ORDER BY res.date DESC, res.start_time";

        $stmt = $pdo->prepare($query);

        if ($lecturer_id) {
            $stmt->bindValue(':lecturer_id', $lecturer_id, PDO::PARAM_INT);
        }

        $stmt->execute();

        $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($reservations);
        break;

    case 'POST':
        $data = json_decode(file_get_contents("php://input"));

        // Lecturer id token'dan alınmalı, dışarıdan gelmemeli
        $lecturer_id = $user_data->id;

        if (
            !empty($data->room_id) &&
            !empty($data->date) &&
            !empty($data->start_time) &&
            !empty($data->end_time)
        ) {
            if (!isRoomAvailable($pdo, $data->room_id, $data->date, $data->start_time, $data->end_time)) {
                http_response_code(409);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Bu zaman aralığında seçilen oda dolu.'
                ]);
                exit;
            }

            if (!isLecturerAvailable($pdo, $lecturer_id, $data->date, $data->start_time, $data->end_time)) {
                http_response_code(409);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Akademisyenin bu saatte zaten başka bir rezervasyonu bulunuyor.'
                ]);
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO room_reservations (lecturer_id, room_id, date, start_time, end_time, status) VALUES (?, ?, ?, ?, ?, 'pending')");

            if ($stmt->execute([
                $lecturer_id,
                $data->room_id,
                $data->date,
                $data->start_time,
                $data->end_time
            ])) {
                http_response_code(201);
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Reservation request submitted and pending approval.'
                ]);
            } else {
                http_response_code(503);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Unable to create reservation request.'
                ]);
            }
        } else {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Incomplete data. All fields are required except lecturer_id.'
            ]);
        }
        break;

    case 'PUT':
        // Sadece admin izinli olsun (rezervasyon onayı/reddi vb.)
        if ($user_data->role !== 'admin') {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Access denied. Only admins can update reservation status.']);
            exit();
        }

        $data = json_decode(file_get_contents("php://input"));
        if (!empty($data->id) && !empty($data->status)) {
            $allowed_statuses = ['pending', 'approved', 'rejected'];
            if (in_array($data->status, $allowed_statuses)) {
                $stmt = $pdo->prepare("UPDATE room_reservations SET status = ? WHERE id = ?");
                if ($stmt->execute([$data->status, $data->id])) {
                    echo json_encode(['status' => 'success', 'message' => 'Reservation status updated.']);
                } else {
                    http_response_code(503);
                    echo json_encode(['status' => 'error', 'message' => 'Unable to update reservation.']);
                }
            } else {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Invalid status value.']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Incomplete data. ID and status are required.']);
        }
        break;

    case 'DELETE':
        $data = json_decode(file_get_contents("php://input"));

        // Lecturer kendi rezervasyonunu sadece iptal edebilir, admin ise tüm rezervasyonları silebilir
        if (!empty($data->id)) {
            // Eğer lecturer ise id tokendan alınmalı, dışarıdan gelen lecturer_id kullanılmaz
            $reservationStmt = $pdo->prepare("SELECT * FROM room_reservations WHERE id = ?");
            $reservationStmt->execute([$data->id]);
            $reservation = $reservationStmt->fetch(PDO::FETCH_ASSOC);

            if (!$reservation) {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Reservation not found.']);
                exit;
            }

            if ($user_data->role === 'lecturer') {
                if ($reservation['lecturer_id'] != $user_data->id) {
                    http_response_code(403);
                    echo json_encode(['status' => 'error', 'message' => 'Access denied. You can only delete your own reservations.']);
                    exit;
                }
                if ($reservation['status'] !== 'pending') {
                    http_response_code(403);
                    echo json_encode(['status' => 'error', 'message' => 'Only pending reservations can be deleted.']);
                    exit;
                }
            }
            // admin ise herhangi bir kısıtlama yok

            $deleteStmt = $pdo->prepare("DELETE FROM room_reservations WHERE id = ?");
            if ($deleteStmt->execute([$data->id])) {
                echo json_encode(['status' => 'success', 'message' => 'Reservation deleted successfully.']);
            } else {
                http_response_code(503);
                echo json_encode(['status' => 'error', 'message' => 'Unable to delete reservation.']);
            }
        } else {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'ID is required for deletion.'
            ]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method not allowed for reservations.']);
        break;
}
?>
