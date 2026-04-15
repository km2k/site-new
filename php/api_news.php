<?php
/**
 * REST-style API endpoint for church news.
 *
 * Routing by HTTP method + optional ?action= parameter.
 *
 *  GET    /php/api_news.php                        → list (newest first)
 *  GET    /php/api_news.php?id=3                   → get one
 *  GET    /php/api_news.php?action=search&q=ремонт → search
 *  GET    /php/api_news.php?limit=5&offset=0       → paginated list
 *  POST   /php/api_news.php                        → create
 *  PUT    /php/api_news.php                        → update (id in body)
 *  DELETE /php/api_news.php?id=3                   → delete
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/news_crud.php';

try {
    $method = $_SERVER['REQUEST_METHOD'];

    /* ------ GET ------ */
    if ($method === 'GET') {
        $action = $_GET['action'] ?? '';

        // search
        if ($action === 'search') {
            $q = trim($_GET['q'] ?? '');
            echo json_encode([
                'success' => true,
                'data'    => searchNews($q),
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // single by id
        if (isset($_GET['id'])) {
            $row = getNews((int) $_GET['id']);
            if (!$row) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Новината не е намерена.']);
                exit;
            }
            echo json_encode(['success' => true, 'data' => $row], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // list with optional pagination; ?all=1 includes inactive (admin)
        $activeOnly = !isset($_GET['all']);
        $limit  = isset($_GET['limit'])  ? (int) $_GET['limit']  : 0;
        $offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;
        $total  = countNews($activeOnly);
        $rows   = listNews($activeOnly, $limit, $offset);

        echo json_encode([
            'success' => true,
            'total'   => $total,
            'data'    => $rows,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* ------ POST (create) ------ */
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || empty($input['title']) || empty($input['body'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Задължителни полета: title, body.']);
            exit;
        }
        $id = createNews($input);
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'id'      => $id,
            'data'    => getNews($id),
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
        $ok = updateNews($id, $input);
        echo json_encode([
            'success' => $ok,
            'data'    => getNews($id),
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
        $ok = deleteNews($id);
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

