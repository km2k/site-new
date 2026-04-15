<?php
require_once __DIR__ . '/../php/auth.php';
requireAdmin();
$user = currentUser();
?>
<!DOCTYPE html>
<html lang="bg">
<head>
  <meta charset="UTF-8">
  <title>Администрация – Потребители | Храм „Света Троица"</title>
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
    <a href="/admin/services.php">Служби</a>
    <a href="/admin/news.php">Новини</a>
    <a href="/admin/users.php" class="active">Потребители</a>
  </nav>

  <!-- ── CREATE / EDIT FORM ── -->
  <div class="form-panel" id="form-panel">
    <h3 id="form-title">Нов потребител</h3>

    <form id="user-form" autocomplete="off">
      <input type="hidden" id="edit-id" value="">

      <div class="form-grid">
        <div class="form-group">
          <label for="f-username">Потребителско име *</label>
          <input type="text" id="f-username" required autocomplete="off">
        </div>

        <div class="form-group">
          <label for="f-email">Имейл *</label>
          <input type="email" id="f-email" required autocomplete="off">
        </div>

        <div class="form-group">
          <label for="f-display">Показвано име</label>
          <input type="text" id="f-display">
        </div>

        <div class="form-group">
          <label for="f-password" id="lbl-password">Парола *</label>
          <input type="password" id="f-password" autocomplete="new-password">
          <small id="password-hint" style="color:#8a7b6a;font-size:12px;margin-top:3px;display:none;">
            Оставете празно, за да запазите текущата парола.
          </small>
        </div>

        <div class="form-group">
          <div class="form-check">
            <input type="checkbox" id="f-admin">
            <label for="f-admin">Администратор</label>
          </div>
        </div>

        <div class="form-group">
          <div class="form-check">
            <input type="checkbox" id="f-active" checked>
            <label for="f-active">Активен</label>
          </div>
        </div>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn btn-primary" id="btn-save">Създай</button>
        <button type="button" class="btn btn-secondary" id="btn-cancel" style="display:none;">Отказ</button>
      </div>
    </form>
  </div>

  <!-- ── TABLE ── -->
  <div class="admin-header-row">
    <h2>Всички потребители</h2>
  </div>

  <div class="table-wrap">
    <table class="services-table" id="users-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Потребител</th>
          <th>Имейл</th>
          <th>Име</th>
          <th>Роля</th>
          <th>Статус</th>
          <th>Създаден</th>
          <th>Действия</th>
        </tr>
      </thead>
      <tbody id="users-tbody"></tbody>
    </table>
  </div>
</main>

<!-- toast -->
<div class="toast" id="toast"></div>

<script src="/js/admin-users.js"></script>
<script src="/js/admin-auth.js"></script>
</body>
</html>

