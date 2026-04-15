<?php
/**
 * REST-style API endpoint for users.
 *
 *  GET    /php/api_users.php            → list all
 *  GET    /php/api_users.php?id=1       → get one
 *  POST   /php/api_users.php            → create (requires: username, email, password)
 *  PUT    /php/api_users.php            → update (id in body; password optional)
 *  DELETE /php/api_users.php?id=2       → delete
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/users_crud.php';

try {
    $method = $_SERVER['REQUEST_METHOD'];

    /* ------ GET ------ */
    if ($method === 'GET') {

        if (isset($_GET['id'])) {
            $row = getUser((int) $_GET['id']);
            if (!$row) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Потребителят не е намерен.']);
                exit;
            }
            echo json_encode(['success' => true, 'data' => $row], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // list all
        echo json_encode([
            'success' => true,
            'data'    => listUsers(),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* ------ POST (create) ------ */
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input || empty($input['username']) || empty($input['email']) || empty($input['password'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Задължителни полета: username, email, password.']);
            exit;
        }

        // Check uniqueness
        if (getUserByUsername($input['username'])) {
            http_response_code(409);
            echo json_encode(['success' => false, 'error' => 'Потребителското име вече е заето.']);
            exit;
        }
        if (getUserByEmail($input['email'])) {
            http_response_code(409);
            echo json_encode(['success' => false, 'error' => 'Имейлът вече е регистриран.']);
            exit;
        }

        $id = createUser($input);
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'id'      => $id,
            'data'    => getUser($id),
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

        // If username changed, check uniqueness
        if (!empty($input['username'])) {
            $existing = getUserByUsername($input['username']);
            if ($existing && (int) $existing['id'] !== $id) {
                http_response_code(409);
                echo json_encode(['success' => false, 'error' => 'Потребителското име вече е заето.']);
                exit;
            }
        }

        // If email changed, check uniqueness
        if (!empty($input['email'])) {
            $existing = getUserByEmail($input['email']);
            if ($existing && (int) $existing['id'] !== $id) {
                http_response_code(409);
                echo json_encode(['success' => false, 'error' => 'Имейлът вече е регистриран.']);
                exit;
            }
        }

        $ok = updateUser($id, $input);
        echo json_encode([
            'success' => $ok,
            'data'    => getUser($id),
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
        $ok = deleteUser($id);
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
