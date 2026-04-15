<?php
/**
 * CRUD module for church services.
 *
 * Pure functions — no output, no routing.
 * Each function returns data (array / bool) and throws on DB errors.
 */

require_once __DIR__ . '/config.php';

/* =========================================================
   Bulgarian day-of-week helpers
   ========================================================= */

/** ISO day number → Bulgarian name */
function dayName(int $num): string
{
    $map = [
        1 => 'Понеделник',
        2 => 'Вторник',
        3 => 'Сряда',
        4 => 'Четвъртък',
        5 => 'Петък',
        6 => 'Събота',
        7 => 'Неделя',
    ];
    return $map[$num] ?? '';
}

/* =========================================================
   CREATE
   ========================================================= */

/**
 * Insert a new service.
 *
 * @param  array  $data  Associative array of column values.
 * @return int    The new row id.
 */
function createService(array $data): int
{
    $pdo = getDbConnection();

    $sql = "INSERT INTO services
            (title, description, service_date, start_time, end_time,
             morning_service, evening_service,
             day_of_week, priest, feast)
            VALUES
            (:title, :description, :service_date, :start_time, :end_time,
             :morning_service, :evening_service,
             :day_of_week, :priest, :feast)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':title'           => $data['title'],
        ':description'     => $data['description']     ?? null,
        ':service_date'    => $data['service_date'],
        ':start_time'      => $data['start_time'],
        ':end_time'        => $data['end_time']         ?? null,
        ':morning_service' => $data['morning_service']  ?? null,
        ':evening_service' => $data['evening_service']  ?? null,
        ':day_of_week'     => $data['day_of_week'],
        ':priest'          => $data['priest']           ?? null,
        ':feast'           => $data['feast']            ?? null,
    ]);

    return (int) $pdo->lastInsertId();
}

/* =========================================================
   READ
   ========================================================= */

/**
 * Get one service by id.
 */
function getService(int $id): ?array
{
    $pdo  = getDbConnection();
    $stmt = $pdo->prepare("SELECT * FROM services WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * List all services.
 */
function listServices(): array
{
    $pdo = getDbConnection();
    $sql = "SELECT * FROM services";
    $sql .= " ORDER BY service_date ASC, start_time ASC";
    return $pdo->query($sql)->fetchAll();
}

/**
 * Get services for a particular ISO week (Mon–Sun).
 *
 * @param  string  $dateInWeek  Any date string (Y-m-d) that falls in the
 *                              desired week.  Defaults to "this week".
 * @return array   { priest: string, days: { 1..7: { dayName, date, items[] } } }
 */
function getServicesForWeek(string $dateInWeek = ''): array
{
    if ($dateInWeek === '') {
        $dateInWeek = date('Y-m-d');
    }

    // Calculate Monday and Sunday of that ISO week
    $dt     = new DateTimeImmutable($dateInWeek);
    $dow    = (int) $dt->format('N');                       // 1=Mon
    $monday = $dt->modify('-' . ($dow - 1) . ' days');
    $sunday = $monday->modify('+6 days');

    $pdo  = getDbConnection();

    $sql  = "SELECT morning_service, start_time, evening_service, end_time,
                    description, priest, feast, day_of_week
             FROM services
             WHERE service_date BETWEEN :mon AND :sun
             ORDER BY day_of_week ASC, start_time ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':mon' => $monday->format('Y-m-d'),
        ':sun' => $sunday->format('Y-m-d'),
    ]);

    $rows = $stmt->fetchAll();

    // Extract the weekly duty priest (same for the whole week)
    $weekPriest = '';
    foreach ($rows as $row) {
        if (!empty($row['priest'])) {
            $weekPriest = $row['priest'];
            break;
        }
    }

    // Group by day
    $grouped = [];
    for ($d = 1; $d <= 7; $d++) {
        $grouped[$d] = [
            'dayName' => dayName($d),
            'date'    => $monday->modify('+' . ($d - 1) . ' days')->format('d.m.Y'),
            'items'   => [],
        ];
    }

    foreach ($rows as $row) {
        $d = (int) $row['day_of_week'];
        $item = [
            'morning_service' => $row['morning_service'],
            'start_time'      => $row['start_time'],
            'evening_service' => $row['evening_service'],
            'end_time'        => $row['end_time'],
        ];
        if (!empty($row['description'])) {
            $item['description'] = $row['description'];
        }
        if (!empty($row['feast'])) {
            $item['feast'] = $row['feast'];
        }
        $grouped[$d]['items'][] = $item;
    }

    return [
        'priest' => $weekPriest,
        'days'   => $grouped,
    ];
}

/**
 * Search services by title / description.
 */
function searchServices(string $query): array
{
    $pdo  = getDbConnection();
    $like = '%' . $query . '%';
    $stmt = $pdo->prepare(
        "SELECT * FROM services
         WHERE title LIKE :q OR description LIKE :q2
         ORDER BY service_date ASC, start_time ASC"
    );
    $stmt->execute([':q' => $like, ':q2' => $like]);
    return $stmt->fetchAll();
}

/* =========================================================
   UPDATE
   ========================================================= */

/**
 * Update a service.
 *
 * @param  int    $id
 * @param  array  $data  Only the columns you want to change.
 * @return bool
 */
function updateService(int $id, array $data): bool
{
    $allowed = [
        'title', 'description', 'service_date', 'start_time', 'end_time',
        'morning_service', 'evening_service',
        'day_of_week', 'priest', 'feast',
    ];

    $sets   = [];
    $params = [':id' => $id];

    foreach ($data as $col => $val) {
        if (!in_array($col, $allowed, true)) continue;
        $sets[]            = "`$col` = :$col";
        $params[":$col"]   = $val;
    }

    if (empty($sets)) return false;

    $sql  = "UPDATE services SET " . implode(', ', $sets) . " WHERE id = :id";
    $stmt = getDbConnection()->prepare($sql);
    $stmt->execute($params);

    return $stmt->rowCount() > 0;
}

/* =========================================================
   DELETE
   ========================================================= */

/**
 * Permanently delete a service.
 */
function deleteService(int $id): bool
{
    $stmt = getDbConnection()->prepare("DELETE FROM services WHERE id = :id");
    $stmt->execute([':id' => $id]);
    return $stmt->rowCount() > 0;
}

