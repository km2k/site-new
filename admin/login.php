<?php
require_once __DIR__ . '/../php/auth.php';

// Already logged in as admin? redirect to admin dashboard
if (isAdmin()) {
    header('Location: /admin/services.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="bg">
<head>
  <meta charset="UTF-8">
  <title>Вход – Администрация | Храм „Света Троица"</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">

  <link rel="icon" type="image/png" href="/favicon.png">

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Lora:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">

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
  </div>
</header>

<!-- ═══════ LOGIN ═══════ -->
<main class="services-page">
  <div class="login-container">
    <div class="login-card">
      <h1>Администрация</h1>
      <p class="login-subtitle">Вход в административния панел</p>

      <form id="login-form" autocomplete="on">
        <div class="form-group full">
          <label for="f-username">Потребителско име</label>
          <input type="text" id="f-username" name="username" required autofocus autocomplete="username">
        </div>

        <div class="form-group full">
          <label for="f-password">Парола</label>
          <input type="password" id="f-password" name="password" required autocomplete="current-password">
        </div>

        <div class="login-error" id="login-error"></div>

        <div class="form-actions" style="justify-content:center;">
          <button type="submit" class="btn btn-primary" id="btn-login">Вход</button>
        </div>
      </form>
    </div>
  </div>
</main>

<script>
(function () {
  'use strict';

  const form    = document.getElementById('login-form');
  const errBox  = document.getElementById('login-error');
  const btnLogin = document.getElementById('btn-login');

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    errBox.textContent = '';
    btnLogin.disabled = true;
    btnLogin.textContent = 'Зареждане…';

    const data = {
      username: document.getElementById('f-username').value.trim(),
      password: document.getElementById('f-password').value,
    };

    fetch('/php/api_auth.php?action=login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(data),
    })
      .then(r => r.json())
      .then(json => {
        if (json.success) {
          window.location.href = '/admin/services.php';
        } else {
          errBox.textContent = json.error || 'Грешка при вход.';
          btnLogin.disabled = false;
          btnLogin.textContent = 'Вход';
        }
      })
      .catch(() => {
        errBox.textContent = 'Грешка при свързване със сървъра.';
        btnLogin.disabled = false;
        btnLogin.textContent = 'Вход';
      });
  });
})();
</script>

</body>
</html>

