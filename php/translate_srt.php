<?php
session_start();

// ─── PASSWORD PROTECTION ───
define('PAGE_PASSCODE', 'k0w1c1q');

if (isset($_POST['logout'])) {
    unset($_SESSION['srt_auth']);
}
if (isset($_POST['passcode'])) {
    if ($_POST['passcode'] === PAGE_PASSCODE) {
        $_SESSION['srt_auth'] = true;
    }
}

// ─── CONFIG ───
define('DEEPL_API_KEY', getenv('DEEPL_API_KEY') ?: '62ed3516-764f-4436-a640-6f13c34298e7:fx');
define('DEEPL_API_URL', 'https://api-free.deepl.com/v2/translate');

function parseSrt(string $content): array {
    $content = str_replace(["\r\n", "\r"], "\n", $content);
    $blocks  = preg_split('/\n{2,}/', trim($content));
    $result  = [];
    foreach ($blocks as $block) {
        $lines = explode("\n", trim($block));
        if (count($lines) < 3) continue;
        $index = array_shift($lines);
        $time  = array_shift($lines);
        $text  = implode("\n", $lines);
        $result[] = ['index' => $index, 'time' => $time, 'text' => $text];
    }
    return $result;
}

function translateTexts(array $texts, string $sourceLang, string $targetLang): array {
    $translated = [];
    $batches = array_chunk($texts, 50);
    foreach ($batches as $batch) {
        $payload = ['target_lang' => $targetLang, 'text' => array_values($batch)];
        if ($sourceLang) {
            $payload['source_lang'] = $sourceLang;
        }
        $ch = curl_init(DEEPL_API_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_HTTPHEADER     => [
                'Authorization: DeepL-Auth-Key ' . DEEPL_API_KEY,
                'Content-Type: application/json',
            ],
        ]);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode !== 200) {
            throw new RuntimeException("DeepL API грешка (HTTP $httpCode): $resp");
        }
        $json = json_decode($resp, true);
        if (!isset($json['translations'])) {
            throw new RuntimeException("Неочакван отговор от DeepL: $resp");
        }
        foreach ($json['translations'] as $tr) {
            $translated[] = $tr['text'];
        }
    }
    return $translated;
}

function buildSrt(array $blocks, array $translatedTexts): string {
    $out = '';
    foreach ($blocks as $i => $b) {
        $out .= $b['index'] . "\n";
        $out .= $b['time']  . "\n";
        $out .= ($translatedTexts[$i] ?? $b['text']) . "\n\n";
    }
    return $out;
}

// ─── HANDLE FILE TRANSLATION BEFORE ANY HTML OUTPUT ───
$message = '';
$msgClass = '';

if (!empty($_SESSION['srt_auth']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['srt_file'])) {
    $file = $_FILES['srt_file'];
    $sourceLang = $_POST['source_lang'] ?? 'EN';
    $targetLang = 'BG';

    if (!DEEPL_API_KEY) {
        $message = 'DeepL API ключът не е конфигуриран.';
        $msgClass = 'msg-err';
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $message = 'Грешка при качване на файла.';
        $msgClass = 'msg-err';
    } elseif (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'srt') {
        $message = 'Моля, качете файл с разширение .srt';
        $msgClass = 'msg-err';
    } else {
        try {
            $content = file_get_contents($file['tmp_name']);
            $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
            $blocks = parseSrt($content);
            if (empty($blocks)) {
                throw new RuntimeException('Файлът не съдържа валидни SRT блокове.');
            }
            $texts = array_map(fn($b) => $b['text'], $blocks);
            $translatedTexts = translateTexts($texts, $sourceLang, $targetLang);
            $outputSrt = buildSrt($blocks, $translatedTexts);

            $outName = pathinfo($file['name'], PATHINFO_FILENAME) . '_bg.srt';
            header('Content-Type: application/x-subrip; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $outName . '"');
            header('Content-Length: ' . strlen($outputSrt));
            echo $outputSrt;
            exit;
        } catch (Throwable $e) {
            $message = 'Грешка: ' . htmlspecialchars($e->getMessage());
            $msgClass = 'msg-err';
        }
    }
}

$passError = isset($_POST['passcode']) && $_POST['passcode'] !== PAGE_PASSCODE;
?>
<!DOCTYPE html>
<html lang="bg">
<head>
  <meta charset="UTF-8">
  <title>Превод на субтитри | Храм „Света Троица"</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="/favicon.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Lora:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/style.css">
  <style>
    .translate-page { max-width:700px; margin:2rem auto; padding:1.5rem; font-family:'Lora',Georgia,serif; }
    .translate-page h1 { font-family:'Playfair Display',serif; font-size:1.8rem; margin-bottom:1.5rem; text-align:center; }
    .form-group { margin-bottom:1.2rem; }
    .form-group label { display:block; font-weight:600; margin-bottom:.4rem; }
    .form-group select, .form-group input[type="file"], .form-group input[type="text"] {
      width:100%; padding:.6rem .8rem; font-size:1rem; border:1px solid #b5a67a; border-radius:4px; font-family:inherit; box-sizing:border-box;
    }
    .btn-translate { display:inline-block; padding:.7rem 2rem; background:#6b4f2e; color:#fff; border:none; border-radius:4px; font-size:1rem; cursor:pointer; font-family:inherit; }
    .btn-translate:hover { background:#523a1f; }
    .msg { padding:1rem; border-radius:4px; margin-bottom:1rem; }
    .msg-ok { background:#d4edda; color:#155724; }
    .msg-err { background:#f8d7da; color:#721c24; }
    .note { font-size:.85rem; color:#666; margin-top:.3rem; }
  </style>
</head>
<body id="top">

<header class="site-header">
  <div class="header-inner">
    <div class="logo">
      <a href="../index.html">
        <img src="../images/logo.png" alt="Храм Света Троица" loading="lazy">
        <span>Храм „Света Троица"</span>
      </a>
    </div>
  </div>
</header>

<main class="translate-page">
  <h1>Превод на субтитри (.srt)</h1>

<?php if (empty($_SESSION['srt_auth'])): ?>
  <?php if ($passError): ?>
    <div class="msg msg-err">Грешен код за достъп.</div>
  <?php endif; ?>
  <form method="POST" style="max-width:320px;margin:2rem auto;text-align:center;">
    <div class="form-group">
      <label for="passcode">Код за достъп</label>
      <input type="password" name="passcode" id="passcode" required style="text-align:center;" placeholder="Въведете кода">
    </div>
    <button type="submit" class="btn-translate">Вход</button>
  </form>
<?php else: ?>

  <?php if ($message): ?>
    <div class="msg <?= $msgClass ?>"><?= $message ?></div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data">
    <div class="form-group">
      <label for="srt_file">Файл със субтитри (.srt)</label>
      <input type="file" name="srt_file" id="srt_file" accept=".srt" required>
    </div>
    <div class="form-group">
      <label for="source_lang">Изходен език</label>
      <select name="source_lang" id="source_lang">
        <option value="EN">Английски (EN)</option>
        <option value="BG">Български (BG)</option>
        <option value="DE">Немски (DE)</option>
        <option value="FR">Френски (FR)</option>
        <option value="ES">Испански (ES)</option>
        <option value="RU">Руски (RU)</option>
        <option value="EL" selected>Гръцки (EL)</option>
        <option value="RO">Румънски (RO)</option>
        <option value="SR">Сръбски (SR)</option>
        <option value="">Автоматично разпознаване</option>
      </select>
      <p class="note">Превод винаги е към Български (BG).</p>
    </div>
    <div class="form-group">
      <button type="submit" class="btn-translate">Преведи</button>
    </div>
  </form>

  <p class="note" style="margin-top:2rem; text-align:center;">
    Използва <a href="https://www.deepl.com/docs-api" target="_blank">DeepL API</a> за машинен превод.
    Преведеният файл се изтегля автоматично.
  </p>

  <form method="POST" style="text-align:center;margin-top:1rem;">
    <input type="hidden" name="logout" value="1">
    <button type="submit" class="btn-translate" style="background:#999;font-size:.85rem;padding:.4rem 1rem;">Изход</button>
  </form>

<?php endif; ?>
</main>

</body>
</html>
