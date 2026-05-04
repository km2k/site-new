/**
 * schedule.js – Public weekly schedule viewer
 * Fetches data from php/api_services.php?action=week&date=…
 */

(function () {
  'use strict';

  const API = 'php/api_services.php';

  const container  = document.getElementById('schedule-container');
  const weekLabel  = document.getElementById('week-label');
  const weekLabelBottom = document.getElementById('week-label-bottom');
  const dutyPriest = document.getElementById('duty-priest');
  const btnPrev    = document.getElementById('prev-week');
  const btnNext    = document.getElementById('next-week');
  const btnPrevBottom = document.getElementById('prev-week-bottom');
  const btnNextBottom = document.getElementById('next-week-bottom');

  // Current Monday (ISO week start)
  let currentMonday = getMonday(new Date());

  /* ── helpers ──────────────────────────────── */

  function getMonday(d) {
    const dt  = new Date(d);
    const day = dt.getDay() || 7; // Sun = 7
    dt.setDate(dt.getDate() - day + 1);
    dt.setHours(0, 0, 0, 0);
    return dt;
  }

  function fmtISO(d) {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return y + '-' + m + '-' + day;
  }

  function addDays(d, n) {
    const r = new Date(d);
    r.setDate(r.getDate() + n);
    return r;
  }

  function fmtTime(t) {
    // "08:00:00" → "08:00"
    return t ? t.slice(0, 5) : '';
  }

  function updateLabel() {
    const sun = addDays(currentMonday, 6);
    const opts = { day: 'numeric', month: 'long' };
    const from = currentMonday.toLocaleDateString('bg-BG', opts);
    const to   = sun.toLocaleDateString('bg-BG', opts);
    const year = sun.getFullYear();
    weekLabel.textContent = from + ' – ' + to + ' ' + year;
    if (weekLabelBottom) weekLabelBottom.textContent = weekLabel.textContent;
  }

  /* ── render ───────────────────────────────── */

  function renderWeek(weekData) {
    container.innerHTML = '';

    // Duty priest is now a top-level field (same for the whole week)
    if (weekData.priest) {
      dutyPriest.innerHTML = '<h4>Дежурен: ' + escHtml(weekData.priest) + '</h4>';
    } else {
      dutyPriest.innerHTML = '';
    }

    var grouped = weekData.days;

    Object.keys(grouped).sort().forEach(dayNum => {
      const day  = grouped[dayNum];
      const card = document.createElement('div');
      card.className = 'day-card';

      // header
      const header = document.createElement('div');
      header.className = 'day-card-header';
      header.innerHTML =
        '<h3>' + day.dayName + '</h3>' +
        '<span class="day-date">' + day.date + '</span>';
      card.appendChild(header);

      // body
      const body = document.createElement('div');
      body.className = 'day-card-body';

      if (!day.items || day.items.length === 0) {
        body.innerHTML = '<div class="day-card-empty">Няма планирани богослужения</div>';
      } else {
        day.items.forEach(s => {
          const row = document.createElement('div');
          row.className = 'service-row';

          // Morning service line: start_time + morning_service
          const morningName = s.morning_service || '';
          const morningTime = fmtTime(s.start_time);
          let morningHtml = '';
          if (morningName) {
            morningHtml =
              '<div class="service-line">' +
                '<span class="service-time-inline">' + escHtml(morningTime) + '</span> ' +
                '<span class="service-name">' + escHtml(morningName) + '</span>' +
              '</div>';
          }

          // Evening service line: end_time + evening_service
          const eveningName = s.evening_service || '';
          const eveningTime = fmtTime(s.end_time);
          let eveningHtml = '';
          if (eveningName && eveningTime) {
            eveningHtml =
              '<div class="service-line">' +
                '<span class="service-time-inline">' + escHtml(eveningTime) + '</span> ' +
                '<span class="service-name">' + escHtml(eveningName) + '</span>' +
              '</div>';
          }

          // Determine description style based on prefix
          let descHtml = '';
          if (s.description) {
            let desc = s.description;
            let cssClass = '';
            if (desc.charAt(0) === '+') {
              cssClass = ' class="desc-major"';
            } else if (desc.charAt(0) === '*') {
              cssClass = ' class="desc-important"';
            }
            descHtml = '<p' + cssClass + '>' + escHtml(desc) + '</p>';
          }

          row.innerHTML =
            '<div class="service-info">' +
              morningHtml +
              eveningHtml +
              descHtml +
            '</div>' +
            '<div class="service-meta">' +
              (s.feast ? '<span class="feast-name">' + escHtml(s.feast) + '</span>' : '') +
            '</div>';

          body.appendChild(row);
        });
      }

      card.appendChild(body);
      container.appendChild(card);
    });
  }

  function escHtml(str) {
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
  }

  /* ── fetch ────────────────────────────────── */

  function loadWeek() {
    updateLabel();
    container.innerHTML = '<p style="text-align:center;color:#999;padding:40px 0;">Зареждане…</p>';

    const url = API + '?action=week&date=' + fmtISO(currentMonday);

    fetch(url)
      .then(r => r.json())
      .then(json => {
        if (json.success) {
          renderWeek(json.data);
        } else {
          container.innerHTML = '<p style="text-align:center;color:#c0504d;">Грешка: ' + (json.error || 'неизвестна') + '</p>';
        }
      })
      .catch(err => {
        container.innerHTML = '<p style="text-align:center;color:#c0504d;">Грешка при зареждане на данните.</p>';
        console.error(err);
      });
  }

  /* ── events ───────────────────────────────── */

  btnPrev.addEventListener('click', goBack);
  btnNext.addEventListener('click', goForward);
  if (btnPrevBottom) btnPrevBottom.addEventListener('click', goBack);
  if (btnNextBottom) btnNextBottom.addEventListener('click', goForward);

  function goBack() { currentMonday = addDays(currentMonday, -7); loadWeek(); }
  function goForward() { currentMonday = addDays(currentMonday, 7); loadWeek(); }

  // initial load
  loadWeek();
})();

