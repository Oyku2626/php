<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once 'dbo_test.php';

function isRoomAvailable($pdo, $room_id, $date, $start_time, $end_time) {
    $stmt = $pdo->prepare("
        SELECT id FROM room_reservations
        WHERE room_id = ? AND date = ? AND status != 'rejected'
        AND start_time < ? AND end_time > ?
    ");
    $stmt->execute([$room_id, $date, $end_time, $start_time]);
    return $stmt->rowCount() === 0; }

    function isLecturerAvailable($pdo, $lecturer_id, $date, $start_time, $end_time) {
    $stmt = $pdo->prepare("
        SELECT id FROM room_reservations
        WHERE lecturer_id = ? AND date = ? AND status != 'rejected'
        AND start_time < ? AND end_time > ?
    ");
    $stmt->execute([$lecturer_id, $date, $end_time, $start_time]);
    return $stmt->rowCount() === 0; }

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['lecturer_id']) && $_GET['lecturer_id'] !== '') {
            $lecturer_id = $_GET['lecturer_id'];
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
                WHERE
                    res.lecturer_id = ?
                ORDER BY
                    res.date DESC, res.start_time
            ";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$lecturer_id]);
            $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($reservations);
        } else {
            // lecturer_id parametresi yok veya boşsa tüm rezervasyonları listele
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
                ORDER BY
                    res.date DESC, res.start_time
            ";
            $stmt = $pdo->query($query);
            $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($reservations);
        }
        break;

    case 'POST':
    $data = json_decode(file_get_contents("php://input"));

    if (
        !empty($data->room_id) &&
        !empty($data->lecturer_id) &&
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

        if (!isLecturerAvailable($pdo, $data->lecturer_id, $data->date, $data->start_time, $data->end_time)) {
            http_response_code(409);
            echo json_encode([
                'status' => 'error',
                'message' => 'Akademisyenin bu saatte zaten başka bir rezervasyonu bulunuyor.'
            ]);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO room_reservations (lecturer_id, room_id, date, start_time, end_time, status) VALUES (?, ?, ?, ?, ?, 'pending')");

        if ($stmt->execute([
            $data->lecturer_id,
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
            'message' => 'Incomplete data. All fields are required.'
        ]);
    }
    break;


    case 'PUT':
        // (önceki PUT kod bloğu korunuyor)
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

    default:
        http_response_code(405); // Method Not Allowed
        echo json_encode(['status' => 'error', 'message' => 'Method not allowed for reservations.']);
        break;

            case 'DELETE':
               

        $data = json_decode(file_get_contents("php://input"));
         if (!empty($data->id) && !empty($data->lecturer_id)) {
    // Kontrol: doğru kişi mi, durum 'pending' mi?
   $checkStmt = $pdo->prepare("SELECT * FROM room_reservations WHERE id = ? AND lecturer_id = ? AND status = 'pending'");
        $checkStmt->execute([$data->id, $data->lecturer_id]);
   if ($checkStmt->rowCount() > 0) {
                // 2. Silme Adımı
                $deleteStmt = $pdo->prepare("DELETE FROM room_reservations WHERE id = ?");
                if ($deleteStmt->execute([$data->id])) {
                    echo json_encode(['status' => 'success', 'message' => 'Reservation deleted successfully.']);
                } else {
                    http_response_code(503);
                    echo json_encode(['status' => 'error', 'message' => 'Unable to delete reservation.']);
                }
            } else {
                http_response_code(403);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Bu işlemi yapmaya yetkiniz yok veya rezervasyon zaten onaylanmış.'
                ]);
            }
        } else {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Eksik veri. Hem id hem de lecturer_id gereklidir.'
            ]);
        }
        break;
 }
?>



