<?php
/**
 * Auth API endpoint.
 *
 *  POST   /php/api_auth.php?action=login    { username, password }
 *  POST   /php/api_auth.php?action=logout
 *  GET    /php/api_auth.php?action=status   → current user or null
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/auth.php';

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    /* ------ LOGIN ------ */
    if ($action === 'login' && $method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);

        $username = trim($input['username'] ?? '');
        $password = $input['password'] ?? '';

        if (!$username || !$password) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Въведете потребителско име и парола.']);
            exit;
        }

        $user = attemptLogin($username, $password);

        if (!$user) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Грешно потребителско име или парола.']);
            exit;
        }

        if (!$user['is_admin']) {
            logout();
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Нямате администраторски права.']);
            exit;
        }

        echo json_encode(['success' => true, 'user' => $user], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* ------ LOGOUT ------ */
    if ($action === 'logout') {
        logout();
        echo json_encode(['success' => true]);
        exit;
    }

    /* ------ STATUS ------ */
    if ($action === 'status' && $method === 'GET') {
        echo json_encode([
            'success'  => true,
            'loggedIn' => isLoggedIn(),
            'isAdmin'  => isAdmin(),
            'user'     => currentUser(),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Невалидно действие.']);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}

