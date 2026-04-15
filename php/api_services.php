<?php
/**
 * REST-style API endpoint for church services.
 *
 * Routing by HTTP method + optional ?action= parameter.
 *
 *  GET    /php/api_services.php                → list all
 *  GET    /php/api_services.php?id=5           → get one
 *  GET    /php/api_services.php?action=week    → this week's schedule
 *  GET    /php/api_services.php?action=week&date=2026-04-20  → specific week
 *  GET    /php/api_services.php?action=search&q=литургия     → search
 *  POST   /php/api_services.php                → create
 *  PUT    /php/api_services.php                → update (id in body)
 *  DELETE /php/api_services.php?id=5           → delete
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/services_crud.php';

try {
    $method = $_SERVER['REQUEST_METHOD'];

    /* ------ GET ------ */
    if ($method === 'GET') {
        $action = $_GET['action'] ?? '';

        if ($action === 'week') {
            $date = $_GET['date'] ?? '';
            echo json_encode([
                'success' => true,
                'data'    => getServicesForWeek($date),
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($action === 'search') {
            $q = trim($_GET['q'] ?? '');
            echo json_encode([
                'success' => true,
                'data'    => searchServices($q),
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (isset($_GET['id'])) {
            $row = getService((int) $_GET['id']);
            if (!$row) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Не е намерена.']);
                exit;
            }
            echo json_encode(['success' => true, 'data' => $row], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // default: list all
        echo json_encode([
            'success' => true,
            'data'    => listServices(),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* ------ POST (create) ------ */
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);

        // ── Batch save week ──
        if (isset($input['action']) && $input['action'] === 'save_week') {
            $days = $input['days'] ?? [];
            $priest = $input['priest'] ?? null;
            $saved = 0;

            foreach ($days as $dayData) {
                $date = $dayData['service_date'] ?? '';
                $dow  = (int)($dayData['day_of_week'] ?? 0);
                if (!$date || !$dow) continue;

                // Delete existing services for this date first
                $pdo = getDbConnection();
                $del = $pdo->prepare("DELETE FROM services WHERE service_date = :d");
                $del->execute([':d' => $date]);

                $hasMorning = !empty($dayData['has_morning']);
                $hasEvening = !empty($dayData['has_evening']);

                if (!$hasMorning && !$hasEvening) continue;

                $row = [
                    'title'           => $dayData['morning_service'] ?? 'Утреня и Литургия',
                    'service_date'    => $date,
                    'start_time'      => $hasMorning ? ($dayData['start_time'] ?? '08:00') : null,
                    'end_time'        => $hasEvening ? ($dayData['end_time'] ?? '17:00') : null,
                    'morning_service' => $hasMorning ? ($dayData['morning_service'] ?? 'Утреня и Литургия') : null,
                    'evening_service' => $hasEvening ? ($dayData['evening_service'] ?? 'Вечерня') : null,
                    'day_of_week'     => $dow,
                    'priest'          => $priest,
                    'feast'           => $dayData['feast'] ?? null,
                    'description'     => $dayData['description'] ?? null,
                ];
                createService($row);
                $saved++;
            }

            echo json_encode([
                'success' => true,
                'saved'   => $saved,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // ── Single create ──
        if (!$input || empty($input['title']) || empty($input['service_date']) || empty($input['start_time'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Задължителни полета: title, service_date, start_time, day_of_week.']);
            exit;
        }
        $id = createService($input);
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'id'      => $id,
            'data'    => getService($id),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* ------ PUT (update) ------ */
    if ($method === 'PUT') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || empty($input['id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Липсва id.']);
            exit;
        }
        $id = (int) $input['id'];
        unset($input['id']);
        $ok = updateService($id, $input);
        echo json_encode([
            'success' => $ok,
            'data'    => getService($id),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* ------ DELETE ------ */
    if ($method === 'DELETE') {
        $id = (int) ($_GET['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Липсва id.']);
            exit;
        }
        $ok = deleteService($id);
        echo json_encode(['success' => $ok], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Неподдържан метод.']);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
