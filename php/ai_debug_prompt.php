<?php
/**
 * ai_debug_prompt.php — Debug endpoint to see the full instructions sent to the LLM.
 *
 * Call from browser: https://svetatroica.com/php/ai_debug_prompt.php
 * Shows the complete system prompt including schedule data from the database.
 *
 * DELETE THIS FILE from production when done debugging!
 */

header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/config.php';

// ─── Same SYSTEM_INSTRUCTIONS as ai_stream.php ──────────────────────────────

define('SYSTEM_INSTRUCTIONS', <<<'PROMPT'
Ти си православен богословски асистент на храм „Света Троица" в София.
Твоята ЕДИНСТВЕНА цел е да помагаш на хората с въпроси за православната
християнска вяра. Отговаряш ЕДИНСТВЕНО на български език, без изключения.
... (truncated for brevity — same as ai_stream.php) ...
PROMPT);

// ─── Date/time ───────────────────────────────────────────────────────────────

$bgDays = [
    'Monday' => 'понеделник', 'Tuesday' => 'вторник', 'Wednesday' => 'сряда',
    'Thursday' => 'четвъртък', 'Friday' => 'петък', 'Saturday' => 'събота',
    'Sunday' => 'неделя',
];
$bgMonths = [
    1 => 'януари', 2 => 'февруари', 3 => 'март', 4 => 'април',
    5 => 'май', 6 => 'юни', 7 => 'юли', 8 => 'август',
    9 => 'септември', 10 => 'октомври', 11 => 'ноември', 12 => 'декември',
];

$now = new DateTime('now', new DateTimeZone('Europe/Sofia'));
$dayName = $bgDays[$now->format('l')] ?? $now->format('l');
$dateStr = (int)$now->format('d') . ' ' . $bgMonths[(int)$now->format('m')] . ' ' . $now->format('Y');
$timeStr = $now->format('H:i');

$dynamicInstructions = SYSTEM_INSTRUCTIONS
    . "\n\n═══ ТЕКУЩА ДАТА И ЧАС ═══\n"
    . "Днес е {$dayName}, {$dateStr} г., {$timeStr} ч. (часова зона: Европа/София).\n"
    . "Когато потребителят пита за днешната дата, текущия ден или нещо свързано с \"днес\", \"утре\", \"тази седмица\" — използвай тази информация.";

// ─── Schedule from DB (same logic as ai_stream.php) ──────────────────────────

$scheduleRows = 0;
try {
    $pdo = getDbConnection();
    $todayDt = new DateTime('now', new DateTimeZone('Europe/Sofia'));
    $today = $todayDt->format('Y-m-d');
    $endDt = new DateTime('+30 days', new DateTimeZone('Europe/Sofia'));
    $endDate = $endDt->format('Y-m-d');

    echo "=== DB Query: services WHERE service_date BETWEEN {$today} AND {$endDate} ===\n\n";

    $stmt = $pdo->prepare(
        "SELECT * FROM services
         WHERE service_date BETWEEN :from AND :to
         ORDER BY service_date ASC, start_time ASC"
    );
    $stmt->execute([':from' => $today, ':to' => $endDate]);
    $rows = $stmt->fetchAll();

    echo "Rows found: " . count($rows) . "\n\n";

    if (!empty($rows)) {
        $scheduleText = "\n\n" . '═══ РАЗПИСАНИЕ НА ХРАМ „СВЕТА ТРОИЦА" (следващите 30 дни) ═══' . "\n";
        $scheduleText .= 'Използвай тези данни, когато потребителят пита за служби, дежурни свещеници, литургии, вечерни и др.' . "\n";
        $scheduleText .= 'Описанията, отбелязани с + са ГОЛЕМИ ПРАЗНИЦИ.' . "\n";
        $scheduleText .= 'Описанията, отбелязани с * са ВАЖНИ ДНИ (значими празници).' . "\n\n";

        foreach ($rows as $row) {
            $d = new DateTimeImmutable($row['service_date']);
            $bgDay = $bgDays[$d->format('l')] ?? $d->format('l');
            $bgDate = (int)$d->format('d') . ' ' . $bgMonths[(int)$d->format('m')];

            $line = "• {$bgDay}, {$bgDate}:";

            $morning = $row['morning_service'] ?? $row['title'] ?? '';
            $evening = $row['evening_service'] ?? '';

            if (!empty($morning)) {
                $t = !empty($row['start_time']) ? substr($row['start_time'], 0, 5) : '';
                $line .= " {$t} {$morning}";
            }
            if (!empty($evening)) {
                $t = !empty($row['end_time']) ? substr($row['end_time'], 0, 5) : '';
                $line .= " | {$t} {$evening}";
            }
            if (!empty($row['priest'])) {
                $line .= " — дежурен: {$row['priest']}";
            }
            if (!empty($row['feast'])) {
                $line .= " [{$row['feast']}]";
            }
            if (!empty($row['description'])) {
                $desc = $row['description'];
                if ($desc[0] === '+') {
                    $desc = '★ ' . ltrim($desc, '+* ');
                } elseif ($desc[0] === '*') {
                    $desc = '☆ ' . ltrim($desc, '*+ ');
                }
                $line .= " ({$desc})";
            }

            $scheduleText .= $line . "\n";
            $scheduleRows++;
        }

        $dynamicInstructions .= $scheduleText;
    }
} catch (Throwable $e) {
    echo "!!! DB ERROR: " . $e->getMessage() . "\n\n";
}

// ─── Output ──────────────────────────────────────────────────────────────────

echo "Schedule rows injected: {$scheduleRows}\n";
echo "Total instructions length: " . mb_strlen($dynamicInstructions, 'UTF-8') . " chars\n";
echo "\n" . str_repeat('=', 80) . "\n";
echo "FULL DYNAMIC INSTRUCTIONS SENT TO LLM:\n";
echo str_repeat('=', 80) . "\n\n";
echo $dynamicInstructions;
echo "\n";

