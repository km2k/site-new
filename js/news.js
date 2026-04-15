/**
 * news.js – Public news page
 * Fetches data from php/api_news.php, newest first with pagination.
 */

(function () {
  'use strict';

  const API       = 'php/api_news.php';
  const PER_PAGE  = 5;

  const container = document.getElementById('news-container');
  const btnMore   = document.getElementById('load-more');
  const searchIn  = document.getElementById('news-search');
  const searchBtn = document.getElementById('news-search-btn');

  let offset    = 0;
  let total     = 0;
  let searching = false;

  /* ── helpers ──────────────────────────────── */

  function escHtml(str) {
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
  }

  /** Convert \n to <br> for display */
  function nl2br(str) {
    return escHtml(str).replace(/\\n/g, '<br>').replace(/\n/g, '<br>');
  }

  /** Format "2026-04-10 10:00:00" → "10 април 2026, 10:00" */
  function fmtDate(str) {
    if (!str) return '';
    const d = new Date(str.replace(' ', 'T'));
    if (isNaN(d)) return str;
    const dateStr = d.toLocaleDateString('bg-BG', {
      day: 'numeric', month: 'long', year: 'numeric'
    });
    const timeStr = d.toLocaleTimeString('bg-BG', {
      hour: '2-digit', minute: '2-digit'
    });
    return dateStr + ', ' + timeStr;
  }

  /* ── render ───────────────────────────────── */

  function renderArticle(n) {
    const card = document.createElement('article');
    card.className = 'news-card';

    const pinBadge = parseInt(n.is_pinned)
      ? '<span class="news-pin-badge">📌 Важно</span>'
      : '';

    const imgHtml = n.image_url
      ? '<div class="news-image"><img src="' + escHtml(n.image_url) + '" alt="" loading="lazy"></div>'
      : '';

    card.innerHTML =
      '<div class="news-card-header">' +
        '<h2>' + escHtml(n.title) + pinBadge + '</h2>' +
        '<div class="news-meta">' +
          '<span class="news-date">' + fmtDate(n.published_at) + '</span>' +
          (n.author ? '<span class="news-author">' + escHtml(n.author) + '</span>' : '') +
        '</div>' +
      '</div>' +
      imgHtml +
      (n.summary
        ? '<p class="news-summary">' + escHtml(n.summary) + '</p>'
        : '') +
      '<div class="news-body">' + nl2br(n.body) + '</div>';

    return card;
  }

  function appendArticles(articles) {
    articles.forEach(n => {
      container.appendChild(renderArticle(n));
    });
  }

  function showEmpty() {
    container.innerHTML =
      '<p class="news-empty">Няма публикувани новини.</p>';
  }

  function showError(msg) {
    container.innerHTML =
      '<p class="news-error">Грешка: ' + escHtml(msg || 'неизвестна') + '</p>';
  }

  function updateMoreBtn() {
    if (searching || offset >= total) {
      btnMore.style.display = 'none';
    } else {
      btnMore.style.display = '';
    }
  }

  /* ── fetch ────────────────────────────────── */

  function loadNews(append) {
    if (!append) {
      container.innerHTML =
        '<p class="news-loading">Зареждане…</p>';
      offset = 0;
    }

    const url = API + '?limit=' + PER_PAGE + '&offset=' + offset;

    fetch(url)
      .then(r => r.json())
      .then(json => {
        if (!append) container.innerHTML = '';

        if (!json.success) {
          showError(json.error);
          return;
        }

        total = json.total || 0;

        if (json.data.length === 0 && offset === 0) {
          showEmpty();
        } else {
          appendArticles(json.data);
          offset += json.data.length;
        }

        updateMoreBtn();
      })
      .catch(() => {
        if (!append) container.innerHTML = '';
        showError('Грешка при зареждане на данните.');
      });
  }

  function doSearch() {
    const q = (searchIn.value || '').trim();
    if (!q) {
      searching = false;
      loadNews(false);
      return;
    }

    searching = true;
    container.innerHTML = '<p class="news-loading">Търсене…</p>';
    btnMore.style.display = 'none';

    fetch(API + '?action=search&q=' + encodeURIComponent(q))
      .then(r => r.json())
      .then(json => {
        container.innerHTML = '';
        if (!json.success) {
          showError(json.error);
          return;
        }
        if (json.data.length === 0) {
          container.innerHTML =
            '<p class="news-empty">Няма намерени резултати.</p>';
        } else {
          appendArticles(json.data);
        }
      })
      .catch(() => {
        container.innerHTML = '';
        showError('Грешка при търсене.');
      });
  }

  /* ── events ───────────────────────────────── */

  btnMore.addEventListener('click', () => {
    loadNews(true);
  });

  if (searchBtn) {
    searchBtn.addEventListener('click', doSearch);
  }

  if (searchIn) {
    searchIn.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') doSearch();
    });
    // clear search → reload all
    searchIn.addEventListener('input', () => {
      if (searchIn.value.trim() === '' && searching) {
        searching = false;
        loadNews(false);
      }
    });
  }

  // initial load
  loadNews(false);
})();

