<?php
/**
 * Authentication helpers – session-based admin authentication.
 *
 * Usage in any admin page:
 *   require_once __DIR__ . '/../php/auth.php';
 *   requireAdmin();          // redirects to login if not authenticated
 */

require_once __DIR__ . '/users_crud.php';

// Start session (only once)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Attempt to log in a user by username and password.
 *
 * @return array|null  The user row (without hash) on success, null on failure.
 */
function attemptLogin(string $username, string $password): ?array
{
    $user = getUserByUsername($username);

    if (!$user) return null;
    if (!((int) $user['is_active'])) return null;
    if (!password_verify($password, $user['password_hash'])) return null;

    // Store in session
    $_SESSION['user_id']    = (int) $user['id'];
    $_SESSION['username']   = $user['username'];
    $_SESSION['is_admin']   = (int) $user['is_admin'];
    $_SESSION['display_name'] = $user['display_name'] ?? $user['username'];

    return [
        'id'           => (int) $user['id'],
        'username'     => $user['username'],
        'display_name' => $user['display_name'],
        'is_admin'     => (int) $user['is_admin'],
    ];
}

/**
 * Log out the current user.
 */
function logout(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']
        );
    }

    session_destroy();
}

/**
 * Check if a user is currently logged in.
 */
function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

/**
 * Check if the current user is an admin.
 */
function isAdmin(): bool
{
    return isLoggedIn() && !empty($_SESSION['is_admin']);
}

/**
 * Get the current logged-in user info from session.
 */
function currentUser(): ?array
{
    if (!isLoggedIn()) return null;

    return [
        'id'           => $_SESSION['user_id'],
        'username'     => $_SESSION['username'],
        'display_name' => $_SESSION['display_name'] ?? $_SESSION['username'],
        'is_admin'     => $_SESSION['is_admin'],
    ];
}

/**
 * Guard: require the user to be logged in AND be an admin.
 * Redirects to the login page if not authenticated.
 */
function requireAdmin(): void
{
    if (!isAdmin()) {
        // Determine the login URL relative to /admin/
        header('Location: /admin/login.php');
        exit;
    }
}

