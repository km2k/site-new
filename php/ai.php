<?php
/**
 * Orthodox Christian Assistant (Bulgarian) — Streaming UI
 *
 * This page renders the chat UI and streams responses from ai_stream.php
 * via Server-Sent Events for real-time word-by-word display.
 */

// ─── Error logging (shared log with ai_stream.php) ──────────────────────────
ini_set('display_errors', 0);
error_reporting(E_ALL);

define('AI_PAGE_LOG', __DIR__ . '/ai_debug.log');

function ai_page_log(string $msg): void
{
    // Logging disabled — remove the return to re-enable
    return;
    $ts = date('Y-m-d H:i:s');
    @file_put_contents(AI_PAGE_LOG, "[{$ts}] [ai.php] {$msg}\n", FILE_APPEND | LOCK_EX);
}

set_error_handler(function ($severity, $message, $file, $line) {
    ai_page_log("Error [{$severity}]: {$message} in {$file}:{$line}");
    return false;
});

register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ai_page_log("FATAL: {$err['message']} in {$err['file']}:{$err['line']}");
    }
});

session_start();

if (!isset($_SESSION['conversation_history'])) {
    $_SESSION['conversation_history'] = [];
}

// Clear conversation on GET with ?reset=1
if (isset($_GET['reset'])) {
    $_SESSION['conversation_history'] = [];
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

$history = $_SESSION['conversation_history'];

/**
 * Convert basic Markdown (bold, italic, headings, lists) to HTML.
 */
function mdToHtml(string $text): string
{
    $text = htmlspecialchars($text);

    // Headings: ### and ####
    $text = preg_replace('/^####\s+(.+)$/m', '<h4>$1</h4>', $text);
    $text = preg_replace('/^###\s+(.+)$/m', '<h3>$1</h3>', $text);

    // Bold **...**  then italic *...*
    $text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text);
    $text = preg_replace('/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/s', '<em>$1</em>', $text);

    // Convert lines into paragraphs, handling lists
    $lines = explode("\n", $text);
    $html = '';
    $inList = false;
    $listType = '';

    foreach ($lines as $line) {
        $trimmed = trim($line);

        // Unordered list item: - or •
        if (preg_match('/^[-•]\s+(.+)$/', $trimmed, $m)) {
            if (!$inList || $listType !== 'ul') {
                if ($inList) $html .= '</' . $listType . '>';
                $html .= '<ul>';
                $inList = true;
                $listType = 'ul';
            }
            $html .= '<li>' . $m[1] . '</li>';
            continue;
        }

        // Ordered list item: 1. 2. etc.
        if (preg_match('/^\d+[\.\)]\s+(.+)$/', $trimmed, $m)) {
            if (!$inList || $listType !== 'ol') {
                if ($inList) $html .= '</' . $listType . '>';
                $html .= '<ol>';
                $inList = true;
                $listType = 'ol';
            }
            $html .= '<li>' . $m[1] . '</li>';
            continue;
        }

        // Close any open list
        if ($inList) {
            $html .= '</' . $listType . '>';
            $inList = false;
        }

        // Empty line → skip (paragraph break)
        if ($trimmed === '') {
            continue;
        }

        // Headings already converted to tags — pass through
        if (preg_match('/^<h[34]>/', $trimmed)) {
            $html .= $trimmed;
        } else {
            $html .= '<p>' . $trimmed . '</p>';
        }
    }

    if ($inList) {
        $html .= '</' . $listType . '>';
    }

    return $html;
}

?>
<!DOCTYPE html>
<html lang="bg">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Православен асистент | Храм „Света Троица"</title>
  <meta name="robots" content="noindex, nofollow">

  <link rel="icon" type="image/png" href="/favicon.png">

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">

  <!-- Site CSS -->
  <link rel="stylesheet" href="/css/style.css">

  <style>
    /* ── AI chat styles ── */
    .ai-page {
      max-width: 800px;
      margin: 0 auto;
      padding: 30px 20px 60px;
    }
    .ai-page h1 {
      font-family: 'Playfair Display', serif;
      text-align: center;
      color: #7b1818;
      margin-bottom: 4px;
    }
    .ai-page .subtitle {
      text-align: center;
      font-style: italic;
      color: #8b7355;
      margin-bottom: 28px;
    }
    .chat-history { margin-bottom: 20px; }
    .message {
      padding: 14px 18px;
      margin-bottom: 12px;
      border-radius: 8px;
      line-height: 1.7;
      font-family: 'Cormorant Garamond', serif;
      font-size: 17px;
    }
    .message.user {
      background: #e8dcc8;
      border-left: 4px solid #c4a265;
    }
    .message.user .label { color: #8b7355; font-weight: bold; font-size: 0.85em; }
    .message.assistant {
      background: #fff;
      border-left: 4px solid #7b1818;
      box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    }
    .message.assistant .label { color: #7b1818; font-weight: bold; font-size: 0.85em; }
    .message .content { margin-top: 6px; }
    .message .content p { margin: 0 0 0.6em; }
    .message .content p:last-child { margin-bottom: 0; }
    .message .content ul, .message .content ol { margin: 0.4em 0 0.6em 1.4em; padding: 0; }
    .message .content li { margin-bottom: 0.2em; }
    .message .content strong { color: #4a1010; }
    .message .content h3 { font-size: 1.05em; margin: 0.8em 0 0.3em; color: #7b1818; }
    .message .content h4 { font-size: 1em; margin: 0.6em 0 0.2em; color: #7b1818; }

    .ai-form { display: flex; gap: 10px; flex-wrap: wrap; }
    .ai-form textarea {
      flex: 1 1 100%;
      padding: 12px;
      border: 2px solid #c4a265;
      border-radius: 6px;
      font-family: 'Cormorant Garamond', serif;
      font-size: 17px;
      resize: vertical;
      min-height: 80px;
      background: #fff;
    }
    .ai-form textarea:focus { outline: none; border-color: #7b1818; }
    .ai-buttons { display: flex; gap: 10px; width: 100%; }
    .ai-buttons button {
      padding: 10px 24px;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-family: 'Cormorant Garamond', serif;
      font-size: 16px;
      background: #7b1818;
      color: #fff;
    }
    .ai-buttons button:hover { background: #5a1010; }
    .ai-buttons button:disabled { background: #a88; cursor: wait; }
    .btn-reset {
      background: #e8dcc8;
      color: #2c1810;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      padding: 10px 18px;
      border-radius: 6px;
      font-size: 15px;
      font-family: 'Cormorant Garamond', serif;
    }
    .btn-reset:hover { background: #d4c4a8; }
    .ai-error {
      background: #f8d7da;
      color: #721c24;
      padding: 12px;
      border-radius: 6px;
      margin-bottom: 15px;
    }
    .model-info {
      font-size: 0.8em;
      color: #aaa;
      text-align: center;
      margin-top: 30px;
      padding-top: 15px;
      border-top: 1px solid #d4c4a8;
    }

    /* Streaming indicator — skeleton loader */
    .streaming-indicator {
      display: none;
      padding: 0;
    }
    .streaming-indicator.visible { display: block; }

    .skeleton-bubble {
      background: #fff;
      border-left: 4px solid #7b1818;
      border-radius: 8px;
      padding: 14px 18px;
      margin-bottom: 12px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    }
    .skeleton-bubble .skeleton-label {
      width: 160px;
      height: 12px;
      background: #e8dcc8;
      border-radius: 4px;
      margin-bottom: 12px;
    }
    .skeleton-line {
      height: 14px;
      background: linear-gradient(90deg, #f0ebe3 25%, #e4ddd2 50%, #f0ebe3 75%);
      background-size: 200% 100%;
      animation: shimmer 1.5s ease-in-out infinite;
      border-radius: 4px;
      margin-bottom: 10px;
    }
    .skeleton-line:nth-child(2) { width: 95%; }
    .skeleton-line:nth-child(3) { width: 88%; }
    .skeleton-line:nth-child(4) { width: 72%; }
    .skeleton-line:nth-child(5) { width: 80%; }
    .skeleton-line:nth-child(6) { width: 55%; }
    @keyframes shimmer {
      0%   { background-position: 200% 0; }
      100% { background-position: -200% 0; }
    }
    .streaming-indicator .timer {
      font-size: 0.8em;
      color: #bbb;
      text-align: center;
      margin-top: 8px;
      font-style: italic;
    }

    /* Blinking cursor while streaming */
    .streaming-cursor { white-space: pre-wrap; }
    .streaming-cursor::after {
      content: '▌';
      animation: blink 0.7s infinite;
      color: #7b1818;
    }
    @keyframes blink { 50% { opacity: 0; } }

    /* Orthodox cross SVG alignment */
    .orthodox-cross {
      display: inline-block;
      vertical-align: middle;
      position: relative;
      top: -1px;
    }
  </style>
</head>

<body id="top">

<!-- ═══════ HEADER ═══════ -->
<header class="site-header">
  <div class="header-inner">
    <div class="logo">
      <a href="/index.html">
        <img src="/images/logo.png" alt="Храм Света Троица" loading="lazy">
        <span>Храм „Света Троица"</span>
      </a>
    </div>
    <button class="hamburger" id="hamburger"
            aria-label="Отвори меню" aria-controls="main-menu"
            aria-expanded="false" type="button">
      <span></span><span></span><span></span>
    </button>
    <nav class="main-nav" id="main-menu">
      <ul>
        <li><a href="/index.html#history">История</a></li>
        <li><a href="/index.html#clergy">Свещенослужители</a></li>
        <li><a href="/index.html#musician">Хор</a></li>
        <li><a href="/sedmichno-razpisanie.html">Служби</a></li>
        <li><a href="/novini.html">Новини</a></li>
        <li><a href="/propovedi.html">Проповеди</a></li>
        <li><a href="/index.html#donations">Дарения</a></li>
      </ul>
    </nav>
  </div>
</header>

<!-- ═══════ MAIN ═══════ -->
<main class="ai-page">
  <a href="/index.html" class="back-link" style="display:inline-block;margin-bottom:16px;color:#8b7355;text-decoration:none;font-size:14px;">← Начало</a>

  <h1><svg class="orthodox-cross" width="22" height="28" viewBox="0 0 22 28" fill="currentColor" aria-hidden="true"><rect x="9" y="0" width="4" height="28"/><rect x="8" y="4" width="6" height="2.5"/><rect x="2" y="8" width="18" height="3"/><rect x="5" y="20.5" width="12" height="2.5" transform="rotate(25 11 21.75)"/></svg> Асистент с AI</h1>
  <p class="subtitle">Православен богословски помощник</p>
  <p style="text-align:center;font-size:13px;color:#a89880;margin-top:-18px;margin-bottom:24px;">Това е машина за по-добрo търсене и може да допуска грешки. За по-задълбочени и сложни теми, моля, обърнете се към вашия духовник.</p>

  <!-- Conversation history (server-rendered from session) -->
  <div class="chat-history" id="chat-history">
    <?php foreach ($history as $msg): ?>
      <div class="message <?= $msg['role'] ?>">
        <div class="label">
          <?= $msg['role'] === 'user' ? 'Вие:' : '<svg class="orthodox-cross" width="14" height="18" viewBox="0 0 22 28" fill="currentColor" aria-hidden="true"><rect x="9" y="0" width="4" height="28"/><rect x="8" y="4" width="6" height="2.5"/><rect x="2" y="8" width="18" height="3"/><rect x="5" y="20.5" width="12" height="2.5" transform="rotate(25 11 21.75)"/></svg> Православен Асистент:' ?>
        </div>
        <div class="content"><?= $msg['role'] === 'assistant' ? mdToHtml($msg['content']) : nl2br(htmlspecialchars($msg['content'])) ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Streaming indicator — skeleton loader -->
  <div class="streaming-indicator" id="streaming-indicator">
    <div class="skeleton-bubble">
      <div class="skeleton-label"></div>
      <div class="skeleton-line"></div>
      <div class="skeleton-line"></div>
      <div class="skeleton-line"></div>
      <div class="skeleton-line"></div>
      <div class="skeleton-line"></div>
    </div>
    <div class="timer" id="stream-timer"></div>
  </div>

  <!-- Input form -->
  <form id="ask-form" class="ai-form">
    <textarea name="question" id="question-input" rows="3"
              placeholder="Задайте въпрос за вярата, светиите, постите, празниците..."
              autofocus></textarea>
    <div class="ai-buttons">
      <button type="submit" id="btn-submit">Попитай</button>
      <a href="?reset=1" class="btn-reset" id="btn-reset"
         style="<?= empty($history) ? 'display:none' : '' ?>">Нов разговор</a>
    </div>
  </form>

  <div class="model-info">Храм „Света Троица"</div>
</main>

<!-- ═══════ FOOTER ═══════ -->
<footer class="site-footer">
  <div class="social-links">
    <a href="https://www.facebook.com/sveta.troica.sofia" target="_blank" rel="noopener">
      <img src="/images/facebook.png" alt="Facebook">
    </a>
    <a href="https://www.youtube.com/@SvetaTroica" target="_blank" rel="noopener">
      <img src="/images/youtube.png" alt="YouTube">
    </a>
    <a href="http://www.flickr.com/photos/svetatroicasofia/" target="_blank" rel="noopener">
      <img src="/images/flickr.png" alt="Flickr">
    </a>
  </div>
  <p><br>© 2026 Храм „Света Троица"</p>
</footer>

<script src="/js/main.js"></script>
<script>
(function () {
  'use strict';

  var form       = document.getElementById('ask-form');
  var input      = document.getElementById('question-input');
  var btn        = document.getElementById('btn-submit');
  var chatBox    = document.getElementById('chat-history');
  var indicator  = document.getElementById('streaming-indicator');
  var timerEl    = document.getElementById('stream-timer');
  var btnReset   = document.getElementById('btn-reset');
  var isStreaming = false;

  function escHtml(s) {
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  }

  /** Lightweight Markdown → HTML (bold, italic, headings, lists) */
  function renderMarkdown(raw) {
    var text = escHtml(raw);

    // Headings
    text = text.replace(/^####\s+(.+)$/gm, '<h4>$1</h4>');
    text = text.replace(/^###\s+(.+)$/gm, '<h3>$1</h3>');

    // Bold **...** then italic *...*
    text = text.replace(/\*\*(.+?)\*\*/gs, '<strong>$1</strong>');
    text = text.replace(/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/gs, '<em>$1</em>');

    // Process lines for lists and paragraphs
    var lines = text.split('\n');
    var html = '';
    var inList = false;
    var listType = '';

    for (var i = 0; i < lines.length; i++) {
      var trimmed = lines[i].trim();
      var m;

      // Unordered list: - or •
      if ((m = trimmed.match(/^[-•]\s+(.+)$/))) {
        if (!inList || listType !== 'ul') {
          if (inList) html += '</' + listType + '>';
          html += '<ul>';
          inList = true;
          listType = 'ul';
        }
        html += '<li>' + m[1] + '</li>';
        continue;
      }

      // Ordered list: 1. 2) etc.
      if ((m = trimmed.match(/^\d+[\.\)]\s+(.+)$/))) {
        if (!inList || listType !== 'ol') {
          if (inList) html += '</' + listType + '>';
          html += '<ol>';
          inList = true;
          listType = 'ol';
        }
        html += '<li>' + m[1] + '</li>';
        continue;
      }

      // Close open list
      if (inList) {
        html += '</' + listType + '>';
        inList = false;
      }

      if (trimmed === '') continue;

      if (/^<h[34]>/.test(trimmed)) {
        html += trimmed;
      } else {
        html += '<p>' + trimmed + '</p>';
      }
    }

    if (inList) html += '</' + listType + '>';
    return html;
  }

  function scrollToBottom() {
    window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
  }

  /** Auto-scroll only if user is already near the bottom (within 150px) */
  function scrollIfNearBottom() {
    var scrollPos = window.scrollY + window.innerHeight;
    var docHeight = document.body.scrollHeight;
    if (docHeight - scrollPos < 150) {
      window.scrollTo({ top: docHeight, behavior: 'smooth' });
    }
  }

  function addUserMessage(text) {
    var div = document.createElement('div');
    div.className = 'message user';
    div.innerHTML = '<div class="label">Вие:</div><div class="content">' + escHtml(text) + '</div>';
    chatBox.appendChild(div);
    scrollToBottom();
  }

  function createAssistantBubble() {
    var div = document.createElement('div');
    div.className = 'message assistant';
    div.innerHTML = '<div class="label"><svg class="orthodox-cross" width="14" height="18" viewBox="0 0 22 28" fill="currentColor" aria-hidden="true"><rect x="9" y="0" width="4" height="28"/><rect x="8" y="4" width="6" height="2.5"/><rect x="2" y="8" width="18" height="3"/><rect x="5" y="20.5" width="12" height="2.5" transform="rotate(25 11 21.75)"/></svg> Православен Асистент:</div><div class="content streaming-cursor"></div>';
    chatBox.appendChild(div);
    return div.querySelector('.content');
  }

  // Submit on Enter (Shift+Enter for new line)
  input.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      form.dispatchEvent(new Event('submit', { cancelable: true }));
    }
  });

  /**
   * Simulate streaming: type out the full response word-by-word.
   * Adds a ~400ms pause before each new paragraph (after the first).
   * Words appear every ~30ms for a fast but visible typewriter effect.
   */
  function typeOutResponse(fullText, contentEl, bubble, onDone) {
    // If first token already showed the bubble, scroll to it
    if (bubble.style.display === 'none') {
      bubble.style.display = '';
      bubble.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // Split text into "chunks": words and double-newlines (paragraph breaks)
    var chunks = [];
    var parts = fullText.split(/(\n\n)/);
    for (var p = 0; p < parts.length; p++) {
      if (parts[p] === '\n\n') {
        chunks.push({ type: 'para', text: '\n\n' });
      } else {
        // Split by whitespace but keep the spaces
        var words = parts[p].split(/(\s+)/);
        for (var w = 0; w < words.length; w++) {
          if (words[w] !== '') {
            chunks.push({ type: 'word', text: words[w] });
          }
        }
      }
    }

    var displayed = '';
    var idx = 0;
    var isFirstPara = true;

    function typeNext() {
      if (idx >= chunks.length) {
        // Done typing — render final markdown
        contentEl.innerHTML = renderMarkdown(fullText);
        if (onDone) onDone();
        return;
      }

      var chunk = chunks[idx];
      idx++;

      if (chunk.type === 'para' && !isFirstPara) {
        // Pause before new paragraph
        displayed += chunk.text;
        contentEl.textContent = displayed;
        scrollIfNearBottom();
        setTimeout(typeNext, 400);
        return;
      }

      if (chunk.type === 'para') {
        isFirstPara = false;
      }

      displayed += chunk.text;
      contentEl.textContent = displayed;
      scrollIfNearBottom();

      // Words appear quickly (~30ms)
      setTimeout(typeNext, 30);
    }

    typeNext();
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (isStreaming) return;

    var question = input.value.trim();
    if (!question) return;

    isStreaming = true;
    btn.disabled = true;
    btn.textContent = 'Изчакайте…';
    input.value = '';

    // Show user message
    addUserMessage(question);

    // Show skeleton indicator with timer
    indicator.classList.add('visible');
    var seconds = 0;
    timerEl.textContent = '';
    var timerInterval = setInterval(function () {
      seconds++;
      timerEl.textContent = seconds + ' сек.';
    }, 1000);

    // Create assistant bubble (hidden until first token)
    var contentEl = createAssistantBubble();
    var assistantBubble = contentEl.parentElement;
    assistantBubble.style.display = 'none';
    scrollIfNearBottom();

    // Collect full response, then simulate typewriter
    var rawText = '';
    var hasError = false;
    fetch('/php/ai_stream.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ question: question })
    })
    .then(function (response) {
      if (!response.ok) throw new Error('HTTP ' + response.status);

      var reader = response.body.getReader();
      var decoder = new TextDecoder('utf-8');
      var sseBuffer = '';

      function read() {
        return reader.read().then(function (result) {
          if (result.done) { finish(); return; }

          sseBuffer += decoder.decode(result.value, { stream: true });

          // Process complete SSE lines
          var lines = sseBuffer.split('\n');
          sseBuffer = lines.pop(); // keep incomplete line in buffer

          for (var i = 0; i < lines.length; i++) {
            var line = lines[i].trim();
            if (!line || line.indexOf('data: ') !== 0) continue;

            var payload = line.substring(6);
            if (payload === '[DONE]') { finish(); return; }

            try {
              var obj = JSON.parse(payload);
              if (obj.token) {
                rawText += obj.token;
              }
              if (obj.error) {
                rawText += '\n⚠ Грешка: ' + obj.error;
                hasError = true;
                finish();
                return;
              }
            } catch (ex) { /* skip malformed */ }
          }

          return read();
        });
      }

      return read();
    })
    .catch(function (err) {
      rawText += '\n⚠ Грешка при връзката: ' + err.message;
      hasError = true;
      finish();
    });

    function finish() {
      clearInterval(timerInterval);
      indicator.classList.remove('visible');
      assistantBubble.style.display = '';
      assistantBubble.scrollIntoView({ behavior: 'smooth', block: 'start' });

      if (hasError || rawText === '') {
        // Show error immediately, no typewriter
        contentEl.classList.remove('streaming-cursor');
        contentEl.innerHTML = renderMarkdown(rawText || '⚠ Не получихме отговор.');
        isStreaming = false;
        btn.disabled = false;
        btn.textContent = 'Попитай';
        btnReset.style.display = '';
      } else {
        // Always simulate typewriter effect
        contentEl.textContent = '';
        typeOutResponse(rawText, contentEl, assistantBubble, function () {
          contentEl.classList.remove('streaming-cursor');
          isStreaming = false;
          btn.disabled = false;
          btn.textContent = 'Попитай';
          btnReset.style.display = '';
        });
      }
    }
  });
})();
</script>

</body>
</html>