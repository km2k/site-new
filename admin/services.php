<?php
require_once __DIR__ . '/../php/auth.php';
requireAdmin();
$user = currentUser();
?>
<!DOCTYPE html>
<html lang="bg">
<head>
  <meta charset="UTF-8">
  <title>Администрация – Служби | Храм „Света Троица"</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">

  <link rel="icon" type="image/png" href="/favicon.png">

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">

  <!-- CSS -->
  <link rel="stylesheet" href="/css/style.css">
  <link rel="stylesheet" href="/css/services.css">
  <link rel="stylesheet" href="/css/admin.css">
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
    <div class="admin-user-info">
      <span class="admin-username"><?= htmlspecialchars($user['display_name'] ?? $user['username']) ?></span>
      <a href="#" id="btn-logout" class="admin-logout">Изход</a>
    </div>
  </div>
</header>

<!-- ═══════ MAIN ═══════ -->
<main class="services-page">
  <a href="/index.html" class="back-link">Начало</a>

  <h1>Администрация</h1>

  <!-- admin tabs -->
  <nav class="admin-tabs">
    <a href="/admin/services.php" class="active">Служби</a>
    <a href="/admin/news.php">Новини</a>
    <a href="/admin/users.php">Потребители</a>
  </nav>

  <!-- ── WEEK EDITOR ── -->

  <!-- Week navigation -->
  <div class="week-nav">
    <button id="prev-week" title="Предишна седмица"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg> Предишна</button>
    <span class="week-label" id="week-label"></span>
    <button id="next-week" title="Следваща седмица">Следваща <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 6 15 12 9 18"></polyline></svg></button>
  </div>

  <!-- Duty priest for the week -->
  <div class="form-panel" style="margin-bottom:24px;">
    <div class="form-grid" style="grid-template-columns:1fr;">
      <div class="form-group">
        <label for="week-priest">Дежурен свещеник за седмицата</label>
        <select id="week-priest">
          <option value="">— Изберете —</option>
          <option value="о. Бисер Костадинов">о. Бисер Костадинов</option>
          <option value="о. Александър Лашков">о. Александър Лашков</option>
          <option value="о. Петър Попов">о. Петър Попов</option>
          <option value="о. Георги Попов">о. Георги Попов</option>
          <option value="о. Атанас Забаданов">о. Атанас Забаданов</option>
        </select>
      </div>
    </div>
  </div>

  <!-- Day cards container -->
  <div id="week-editor"></div>

  <!-- Save button -->
  <div class="form-actions" style="margin-top:24px;justify-content:center;">
    <button class="btn btn-primary" id="btn-save-week" style="padding:14px 48px;font-size:16px;">Запази седмицата</button>
  </div>

</main>

<!-- toast -->
<div class="toast" id="toast"></div>

<script src="/js/admin-services.js?v=2"></script>
<script src="/js/admin-auth.js"></script>
</body>
</html>

