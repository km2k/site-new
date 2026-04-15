/**
 * admin-users.js – CRUD operations for users
 */
(function () {
  'use strict';

  const API = '/php/api_users.php';

  const tbody     = document.getElementById('users-tbody');
  const form      = document.getElementById('user-form');
  const formTitle = document.getElementById('form-title');
  const editId    = document.getElementById('edit-id');
  const btnSave   = document.getElementById('btn-save');
  const btnCancel = document.getElementById('btn-cancel');
  const toast     = document.getElementById('toast');
  const pwdInput  = document.getElementById('f-password');
  const pwdLabel  = document.getElementById('lbl-password');
  const pwdHint   = document.getElementById('password-hint');

  /* ── helpers ──────────────────────────────── */

  function escHtml(s) {
    const d = document.createElement('div');
    d.textContent = s || '';
    return d.innerHTML;
  }

  function showToast(msg) {
    toast.textContent = msg;
    toast.classList.add('visible');
    setTimeout(() => toast.classList.remove('visible'), 2500);
  }

  function fmtDate(str) {
    if (!str) return '—';
    const d = new Date(str.replace(' ', 'T'));
    if (isNaN(d)) return str;
    return d.toLocaleDateString('bg-BG', {
      day: 'numeric', month: 'short', year: 'numeric'
    });
  }

  /* ── load all ─────────────────────────────── */

  function loadAll() {
    fetch(API)
      .then(r => r.json())
      .then(json => {
        if (!json.success) { tbody.innerHTML = '<tr><td colspan="8">Грешка</td></tr>'; return; }
        renderTable(json.data);
      })
      .catch(() => {
        tbody.innerHTML = '<tr><td colspan="8">Грешка при зареждане.</td></tr>';
      });
  }

  function renderTable(rows) {
    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:#999;">Няма потребители.</td></tr>';
      return;
    }

    tbody.innerHTML = rows.map(u => {
      const roleBadge = parseInt(u.is_admin)
        ? '<span class="badge badge-admin">админ</span>'
        : '<span class="badge badge-user">потребител</span>';

      const statusBadge = parseInt(u.is_active)
        ? '<span class="badge badge-active">активен</span>'
        : '<span class="badge badge-inactive">деактивиран</span>';

      return '<tr>' +
        '<td data-label="ID">' + u.id + '</td>' +
        '<td data-label="Потребител"><strong>' + escHtml(u.username) + '</strong></td>' +
        '<td data-label="Имейл">' + escHtml(u.email) + '</td>' +
        '<td data-label="Име">' + escHtml(u.display_name || '—') + '</td>' +
        '<td data-label="Роля">' + roleBadge + '</td>' +
        '<td data-label="Статус">' + statusBadge + '</td>' +
        '<td data-label="Създаден">' + fmtDate(u.created_at) + '</td>' +
        '<td class="actions">' +
          '<button class="btn btn-sm btn-secondary" onclick="editUser(' + u.id + ')">Редактирай</button>' +
          '<button class="btn btn-sm btn-danger" onclick="confirmDeleteUser(' + u.id + ')">Изтрий</button>' +
        '</td>' +
      '</tr>';
    }).join('');
  }

  /* ── create / update ──────────────────────── */

  form.addEventListener('submit', function (e) {
    e.preventDefault();

    const id       = editId.value;
    const password = pwdInput.value.trim();

    // Validate password for new users
    if (!id && !password) {
      showToast('Паролата е задължителна за нов потребител.');
      pwdInput.focus();
      return;
    }

    const data = {
      username:     document.getElementById('f-username').value.trim(),
      email:        document.getElementById('f-email').value.trim(),
      display_name: document.getElementById('f-display').value.trim() || null,
      is_admin:     document.getElementById('f-admin').checked ? 1 : 0,
      is_active:    document.getElementById('f-active').checked ? 1 : 0,
    };

    // Only include password if it was entered
    if (password) {
      data.password = password;
    }

    if (id) {
      // UPDATE
      data.id = parseInt(id);
      fetch(API, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data),
      })
        .then(r => r.json())
        .then(json => {
          if (json.success) {
            showToast('Потребителят е обновен.');
            resetForm();
            loadAll();
          } else {
            showToast('Грешка: ' + (json.error || 'неизвестна'));
          }
        })
        .catch(() => showToast('Грешка при обновяване.'));
    } else {
      // CREATE
      fetch(API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data),
      })
        .then(r => r.json())
        .then(json => {
          if (json.success) {
            showToast('Потребителят е създаден.');
            resetForm();
            loadAll();
          } else {
            showToast('Грешка: ' + (json.error || 'неизвестна'));
          }
        })
        .catch(() => showToast('Грешка при създаване.'));
    }
  });

  /* ── edit (populate form) ─────────────────── */

  window.editUser = function (id) {
    fetch(API + '?id=' + id)
      .then(r => r.json())
      .then(json => {
        if (!json.success || !json.data) { showToast('Не е намерен.'); return; }

        const u = json.data;
        editId.value = u.id;
        document.getElementById('f-username').value = u.username || '';
        document.getElementById('f-email').value    = u.email || '';
        document.getElementById('f-display').value  = u.display_name || '';
        document.getElementById('f-admin').checked  = parseInt(u.is_admin) === 1;
        document.getElementById('f-active').checked = parseInt(u.is_active) === 1;

        // Password: optional on edit
        pwdInput.value       = '';
        pwdInput.required    = false;
        pwdLabel.textContent = 'Парола (нова)';
        pwdHint.style.display = '';

        formTitle.textContent   = 'Редактиране на потребител #' + u.id;
        btnSave.textContent     = 'Обнови';
        btnCancel.style.display = '';

        document.getElementById('form-panel').scrollIntoView({ behavior: 'smooth' });
      })
      .catch(() => showToast('Грешка при зареждане.'));
  };

  /* ── delete ───────────────────────────────── */

  window.confirmDeleteUser = function (id) {
    const overlay = document.createElement('div');
    overlay.className = 'confirm-overlay';
    overlay.innerHTML =
      '<div class="confirm-box">' +
        '<p>Сигурни ли сте, че искате да изтриете потребител #' + id + '?</p>' +
        '<div class="form-actions">' +
          '<button class="btn btn-danger" id="confirm-yes">Изтрий</button>' +
          '<button class="btn btn-secondary" id="confirm-no">Отказ</button>' +
        '</div>' +
      '</div>';

    document.body.appendChild(overlay);

    document.getElementById('confirm-no').addEventListener('click', () => overlay.remove());
    document.getElementById('confirm-yes').addEventListener('click', () => {
      fetch(API + '?id=' + id, { method: 'DELETE' })
        .then(r => r.json())
        .then(json => {
          overlay.remove();
          if (json.success) {
            showToast('Потребителят е изтрит.');
            loadAll();
          } else {
            showToast('Грешка: ' + (json.error || 'неизвестна'));
          }
        })
        .catch(() => { overlay.remove(); showToast('Грешка при изтриване.'); });
    });
  };

  /* ── reset form ───────────────────────────── */

  function resetForm() {
    form.reset();
    editId.value = '';
    formTitle.textContent   = 'Нов потребител';
    btnSave.textContent     = 'Създай';
    btnCancel.style.display = 'none';
    pwdInput.required       = false;
    pwdLabel.textContent    = 'Парола *';
    pwdHint.style.display   = 'none';
    document.getElementById('f-active').checked = true;
  }

  btnCancel.addEventListener('click', resetForm);

  /* ── init ─────────────────────────────────── */
  loadAll();
})();

