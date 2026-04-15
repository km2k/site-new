<?php
/**
 * CRUD module for users.
 *
 * Pure functions — no output, no routing.
 * Passwords are hashed with bcrypt via password_hash().
 */

require_once __DIR__ . '/config.php';

/* =========================================================
   CREATE
   ========================================================= */

/**
 * Insert a new user.
 *
 * @param  array  $data  Must include: username, email, password.
 * @return int    The new row id.
 */
function createUser(array $data): int
{
    $pdo = getDbConnection();

    $sql = "INSERT INTO users
            (username, email, password_hash, display_name, is_admin, is_active)
            VALUES
            (:username, :email, :password_hash, :display_name, :is_admin, :is_active)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':username'      => $data['username'],
        ':email'         => $data['email'],
        ':password_hash' => password_hash($data['password'], PASSWORD_BCRYPT),
        ':display_name'  => $data['display_name'] ?? null,
        ':is_admin'      => $data['is_admin']     ?? 0,
        ':is_active'     => $data['is_active']    ?? 1,
    ]);

    return (int) $pdo->lastInsertId();
}

/* =========================================================
   READ
   ========================================================= */

/**
 * Get one user by id (without password_hash).
 */
function getUser(int $id): ?array
{
    $pdo  = getDbConnection();
    $stmt = $pdo->prepare(
        "SELECT id, username, email, display_name, is_admin, is_active, created_at, updated_at
         FROM users WHERE id = :id LIMIT 1"
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * List all users (without password hashes).
 */
function listUsers(): array
{
    $pdo = getDbConnection();
    return $pdo->query(
        "SELECT id, username, email, display_name, is_admin, is_active, created_at, updated_at
         FROM users
         ORDER BY id ASC"
    )->fetchAll();
}

/**
 * Find user by username (includes password_hash for login verification).
 */
function getUserByUsername(string $username): ?array
{
    $pdo  = getDbConnection();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :u LIMIT 1");
    $stmt->execute([':u' => $username]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Find user by email (includes password_hash for login verification).
 */
function getUserByEmail(string $email): ?array
{
    $pdo  = getDbConnection();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :e LIMIT 1");
    $stmt->execute([':e' => $email]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/* =========================================================
   UPDATE
   ========================================================= */

/**
 * Update a user.
 *
 * @param  int    $id
 * @param  array  $data  Only the columns you want to change.
 *                       If 'password' is present it will be re-hashed.
 * @return bool
 */
function updateUser(int $id, array $data): bool
{
    $allowed = [
        'username', 'email', 'display_name', 'is_admin', 'is_active',
    ];

    $sets   = [];
    $params = [':id' => $id];

    // Handle password separately (hash it)
    if (!empty($data['password'])) {
        $sets[]                    = "`password_hash` = :password_hash";
        $params[':password_hash']  = password_hash($data['password'], PASSWORD_BCRYPT);
    }

    foreach ($data as $col => $val) {
        if (!in_array($col, $allowed, true)) continue;
        $sets[]          = "`$col` = :$col";
        $params[":$col"] = $val;
    }

    if (empty($sets)) return false;

    $sql  = "UPDATE users SET " . implode(', ', $sets) . " WHERE id = :id";
    $stmt = getDbConnection()->prepare($sql);
    $stmt->execute($params);

    return $stmt->rowCount() > 0;
}

/* =========================================================
   DELETE
   ========================================================= */

/**
 * Permanently delete a user.
 */
function deleteUser(int $id): bool
{
    $stmt = getDbConnection()->prepare("DELETE FROM users WHERE id = :id");
    $stmt->execute([':id' => $id]);
    return $stmt->rowCount() > 0;
}

/* =========================================================
   AUTH HELPERS
   ========================================================= */

/**
 * Verify a password against a stored hash.
 */
function verifyPassword(string $password, string $hash): bool
{
    return password_verify($password, $hash);
}

