<?php
/**
 * CRUD module for church news / announcements.
 *
 * Pure functions — no output, no routing.
 * Each function returns data (array / bool) and throws on DB errors.
 */

require_once __DIR__ . '/config.php';

/* =========================================================
   CREATE
   ========================================================= */

/**
 * Insert a new news article.
 *
 * @param  array  $data  Associative array of column values.
 * @return int    The new row id.
 */
function createNews(array $data): int
{
    $pdo = getDbConnection();

    $sql = "INSERT INTO news
            (title, summary, body, image_url, author,
             published_at, is_pinned, is_active)
            VALUES
            (:title, :summary, :body, :image_url, :author,
             :published_at, :is_pinned, :is_active)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':title'        => $data['title'],
        ':summary'      => $data['summary']      ?? null,
        ':body'         => $data['body'],
        ':image_url'    => $data['image_url']     ?? null,
        ':author'       => $data['author']        ?? null,
        ':published_at' => $data['published_at']  ?? date('Y-m-d H:i:s'),
        ':is_pinned'    => $data['is_pinned']     ?? 0,
        ':is_active'    => $data['is_active']     ?? 1,
    ]);

    return (int) $pdo->lastInsertId();
}

/* =========================================================
   READ
   ========================================================= */

/**
 * Get one news article by id.
 */
function getNews(int $id): ?array
{
    $pdo  = getDbConnection();
    $stmt = $pdo->prepare("SELECT * FROM news WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * List news articles — newest first, pinned on top.
 *
 * @param  bool  $activeOnly  If true, only is_active = 1.
 * @param  int   $limit       Max rows (0 = unlimited).
 * @param  int   $offset      Pagination offset.
 * @return array
 */
function listNews(bool $activeOnly = false, int $limit = 0, int $offset = 0): array
{
    $pdo = getDbConnection();
    $sql = "SELECT * FROM news";

    if ($activeOnly) {
        $sql .= " WHERE is_active = 1";
    }

    // Pinned first, then newest published_at
    $sql .= " ORDER BY is_pinned DESC, published_at DESC";

    if ($limit > 0) {
        $sql .= " LIMIT " . (int) $limit . " OFFSET " . (int) $offset;
    }

    return $pdo->query($sql)->fetchAll();
}

/**
 * Count total news rows (for pagination).
 */
function countNews(bool $activeOnly = false): int
{
    $pdo = getDbConnection();
    $sql = "SELECT COUNT(*) FROM news";
    if ($activeOnly) {
        $sql .= " WHERE is_active = 1";
    }
    return (int) $pdo->query($sql)->fetchColumn();
}

/**
 * Search news by title / summary / body.
 */
function searchNews(string $query): array
{
    $pdo  = getDbConnection();
    $like = '%' . $query . '%';
    $stmt = $pdo->prepare(
        "SELECT * FROM news
         WHERE title LIKE :q1 OR summary LIKE :q2 OR body LIKE :q3
         ORDER BY is_pinned DESC, published_at DESC"
    );
    $stmt->execute([':q1' => $like, ':q2' => $like, ':q3' => $like]);
    return $stmt->fetchAll();
}

/* =========================================================
   UPDATE
   ========================================================= */

/**
 * Update a news article.
 *
 * @param  int    $id
 * @param  array  $data  Only the columns you want to change.
 * @return bool
 */
function updateNews(int $id, array $data): bool
{
    $allowed = [
        'title', 'summary', 'body', 'image_url', 'author',
        'published_at', 'is_pinned', 'is_active',
    ];

    $sets   = [];
    $params = [':id' => $id];

    foreach ($data as $col => $val) {
        if (!in_array($col, $allowed, true)) continue;
        $sets[]          = "`$col` = :$col";
        $params[":$col"] = $val;
    }

    if (empty($sets)) return false;

    $sql  = "UPDATE news SET " . implode(', ', $sets) . " WHERE id = :id";
    $stmt = getDbConnection()->prepare($sql);
    $stmt->execute($params);

    return $stmt->rowCount() > 0;
}

/* =========================================================
   DELETE
   ========================================================= */

/**
 * Permanently delete a news article.
 */
function deleteNews(int $id): bool
{
    $stmt = getDbConnection()->prepare("DELETE FROM news WHERE id = :id");
    $stmt->execute([':id' => $id]);
    return $stmt->rowCount() > 0;
}

