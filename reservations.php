<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once 'dbo_test.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // (önceki GET kod bloğu korunuyor)
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
        break;

    case 'POST':
        // Yeni rezervasyon oluşturma
        $data = json_decode(file_get_contents("php://input"));

        if (
            !empty($data->room_id) &&
            !empty($data->lecturer_id) &&
            !empty($data->date) &&
            !empty($data->start_time) &&
            !empty($data->end_time)
        ) {
            $stmt = $pdo->prepare("INSERT INTO room_reservations (lecturer_id, room_id, date, start_time, end_time, status) VALUES (?, ?, ?, ?, ?, 'pending')");

            if ($stmt->execute([
                $data->lecturer_id,
                $data->room_id,
                $data->date,
                $data->start_time,
                $data->end_time
            ])) {
                http_response_code(201); // Created
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Reservation request submitted and pending approval.'
                ]);
            } else {
                http_response_code(503); // Service Unavailable
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Unable to create reservation request.'
                ]);
            }
        } else {
            http_response_code(400); // Bad Request
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
}
?>
