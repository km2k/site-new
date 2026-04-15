/**
 * admin-news.js – CRUD operations for church news / announcements
 */
(function () {
  'use strict';

  const API = '/php/api_news.php';

  const tbody     = document.getElementById('news-tbody');
  const form      = document.getElementById('news-form');
  const formTitle = document.getElementById('form-title');
  const editId    = document.getElementById('edit-id');
  const btnSave   = document.getElementById('btn-save');
  const btnCancel = document.getElementById('btn-cancel');
  const toast     = document.getElementById('toast');

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
      day: 'numeric', month: 'short', year: 'numeric',
      hour: '2-digit', minute: '2-digit'
    });
  }

  function truncate(str, len) {
    if (!str) return '';
    return str.length > len ? str.slice(0, len) + '…' : str;
  }

  /* ── load all ─────────────────────────────── */

  function loadAll() {
    // Admin list: show ALL (active + inactive) — use no limit
    // The public API filters by is_active, but for admin we want everything.
    // We'll fetch without limit to get all rows.
    fetch(API + '?all=1')
      .then(r => r.json())
      .then(json => {
        if (!json.success) { tbody.innerHTML = '<tr><td colspan="6">Грешка</td></tr>'; return; }
        renderTable(json.data);
      })
      .catch(() => {
        tbody.innerHTML = '<tr><td colspan="6">Грешка при зареждане.</td></tr>';
      });
  }

  function renderTable(rows) {
    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#999;">Няма новини.</td></tr>';
      return;
    }

    tbody.innerHTML = rows.map(n => {
      const badges = [];
      if (parseInt(n.is_active)) badges.push('<span class="badge badge-active">активна</span>');
      else                       badges.push('<span class="badge badge-inactive">скрита</span>');
      if (parseInt(n.is_pinned)) badges.push('<span class="badge badge-pinned">📌 закачена</span>');

      return '<tr>' +
        '<td data-label="ID">' + n.id + '</td>' +
        '<td data-label="Заглавие"><strong>' + escHtml(truncate(n.title, 50)) + '</strong></td>' +
        '<td data-label="Автор">' + escHtml(n.author || '—') + '</td>' +
        '<td data-label="Публикувана">' + fmtDate(n.published_at) + '</td>' +
        '<td data-label="Статус">' + badges.join(' ') + '</td>' +
        '<td class="actions">' +
          '<button class="btn btn-sm btn-secondary" onclick="editNews(' + n.id + ')">Редактирай</button>' +
          '<button class="btn btn-sm btn-danger" onclick="confirmDeleteNews(' + n.id + ')">Изтрий</button>' +
        '</td>' +
      '</tr>';
    }).join('');
  }

  /* ── create / update ──────────────────────── */

  form.addEventListener('submit', function (e) {
    e.preventDefault();

    const publishedRaw = document.getElementById('f-published').value;
    const publishedAt  = publishedRaw
      ? publishedRaw.replace('T', ' ') + ':00'
      : new Date().toISOString().slice(0, 19).replace('T', ' ');

    const data = {
      title:        document.getElementById('f-title').value.trim(),
      summary:      document.getElementById('f-summary').value.trim() || null,
      body:         document.getElementById('f-body').value.trim(),
      author:       document.getElementById('f-author').value.trim() || null,
      published_at: publishedAt,
      image_url:    document.getElementById('f-image').value.trim() || null,
      is_pinned:    document.getElementById('f-pinned').checked ? 1 : 0,
      is_active:    document.getElementById('f-active').checked ? 1 : 0,
    };

    const id = editId.value;

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
            showToast('Новината е обновена.');
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
            showToast('Новината е публикувана.');
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

  window.editNews = function (id) {
    fetch(API + '?id=' + id)
      .then(r => r.json())
      .then(json => {
        if (!json.success || !json.data) { showToast('Не е намерена.'); return; }

        const n = json.data;
        editId.value = n.id;
        document.getElementById('f-title').value   = n.title || '';
        document.getElementById('f-summary').value = n.summary || '';
        document.getElementById('f-body').value    = n.body || '';
        document.getElementById('f-author').value  = n.author || '';
        document.getElementById('f-image').value   = n.image_url || '';
        document.getElementById('f-pinned').checked = parseInt(n.is_pinned) === 1;
        document.getElementById('f-active').checked = parseInt(n.is_active) === 1;

        // datetime-local needs "YYYY-MM-DDTHH:MM" format
        if (n.published_at) {
          document.getElementById('f-published').value =
            n.published_at.replace(' ', 'T').slice(0, 16);
        }

        formTitle.textContent = 'Редактиране на новина #' + n.id;
        btnSave.textContent   = 'Обнови';
        btnCancel.style.display = '';

        document.getElementById('form-panel').scrollIntoView({ behavior: 'smooth' });
      })
      .catch(() => showToast('Грешка при зареждане.'));
  };

  /* ── delete ───────────────────────────────── */

  window.confirmDeleteNews = function (id) {
    const overlay = document.createElement('div');
    overlay.className = 'confirm-overlay';
    overlay.innerHTML =
      '<div class="confirm-box">' +
        '<p>Сигурни ли сте, че искате да изтриете новина #' + id + '?</p>' +
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
            showToast('Новината е изтрита.');
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
    formTitle.textContent   = 'Нова новина';
    btnSave.textContent     = 'Публикувай';
    btnCancel.style.display = 'none';
    document.getElementById('f-active').checked = true;
  }

  btnCancel.addEventListener('click', resetForm);

  /* ── init ─────────────────────────────────── */
  loadAll();
})();

