/**
 * admin-services.js – Weekly schedule editor for church services
 */
(function () {
  'use strict';

  const API = '/php/api_services.php';

  const weekEditor  = document.getElementById('week-editor');
  const weekLabel   = document.getElementById('week-label');
  const weekPriest  = document.getElementById('week-priest');
  const btnPrev     = document.getElementById('prev-week');
  const btnNext     = document.getElementById('next-week');
  const btnSave     = document.getElementById('btn-save-week');
  const toast       = document.getElementById('toast');

  const DAY_NAMES = {
    1: 'Понеделник', 2: 'Вторник', 3: 'Сряда',
    4: 'Четвъртък',  5: 'Петък',   6: 'Събота', 7: 'Неделя'
  };

  const MORNING_OPTIONS = [
    'Утреня и Литургия',
    'Утреня и Василиева Литургия'
  ];

  const EVENING_OPTIONS = [
    'Вечерня',
    'Велика вечерня',
    'Всенощно бдение'
  ];

  let currentMonday = getMonday(new Date());

  /* ── helpers ──────────────────────────────── */

  function getMonday(d) {
    const dt = new Date(d);
    const day = dt.getDay() || 7;
    dt.setDate(dt.getDate() - day + 1);
    dt.setHours(0, 0, 0, 0);
    return dt;
  }

  function fmtISO(d) {
    return d.getFullYear() + '-' +
      String(d.getMonth() + 1).padStart(2, '0') + '-' +
      String(d.getDate()).padStart(2, '0');
  }

  function addDays(d, n) {
    const r = new Date(d);
    r.setDate(r.getDate() + n);
    return r;
  }

  function fmtDate(d) {
    return String(d.getDate()).padStart(2, '0') + '.' +
      String(d.getMonth() + 1).padStart(2, '0') + '.' +
      d.getFullYear();
  }

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

  function updateLabel() {
    const sun = addDays(currentMonday, 6);
    const opts = { day: 'numeric', month: 'long' };
    const from = currentMonday.toLocaleDateString('bg-BG', opts);
    const to   = sun.toLocaleDateString('bg-BG', opts);
    weekLabel.textContent = from + ' – ' + to + ' ' + sun.getFullYear();
  }

  /* ── build select options ────────────────── */

  function buildOptions(options, selected) {
    return options.map(opt => {
      const sel = (opt === selected) ? ' selected' : '';
      return '<option value="' + escHtml(opt) + '"' + sel + '>' + escHtml(opt) + '</option>';
    }).join('');
  }

  /* ── render week editor ──────────────────── */

  function renderWeek(weekData) {
    weekEditor.innerHTML = '';

    // Duty priest is a top-level field (same for the whole week)
    if (weekData.priest) weekPriest.value = weekData.priest;

    var grouped = weekData.days;

    // Render 7 day cards
    for (let dayNum = 1; dayNum <= 7; dayNum++) {
      const dayData = grouped[dayNum] || { items: [] };
      const dateObj = addDays(currentMonday, dayNum - 1);
      const isoDate = fmtISO(dateObj);
      const item = dayData.items && dayData.items.length > 0 ? dayData.items[0] : null;

      const hasMorning = item && item.morning_service;
      const hasEvening = item && item.evening_service;
      const morningName = (item && item.morning_service) || 'Утреня и Литургия';
      const eveningName = (item && item.evening_service) || 'Вечерня';
      const desc = (item && item.description) || '';

      const card = document.createElement('div');
      card.className = 'day-card';
      card.dataset.dayNum = dayNum;
      card.dataset.date = isoDate;

      card.innerHTML =
        '<div class="day-card-header">' +
          '<h3>' + DAY_NAMES[dayNum] + '</h3>' +
          '<span class="day-date">' + fmtDate(dateObj) + '</span>' +
        '</div>' +
        '<div class="day-card-body" style="padding:16px 24px;">' +
          // Morning service row
          '<div class="admin-service-row">' +
            '<label class="form-check">' +
              '<input type="checkbox" class="chk-morning" ' + (hasMorning ? 'checked' : '') + '>' +
              '<span>Сутрешна служба (08:00)</span>' +
            '</label>' +
            '<div class="admin-service-detail morning-detail" style="' + (hasMorning ? '' : 'display:none') + '">' +
              '<select class="sel-morning">' +
                buildOptions(MORNING_OPTIONS, morningName) +
              '</select>' +
            '</div>' +
          '</div>' +
          // Evening service row
          '<div class="admin-service-row" style="margin-top:12px;">' +
            '<label class="form-check">' +
              '<input type="checkbox" class="chk-evening" ' + (hasEvening ? 'checked' : '') + '>' +
              '<span>Вечерна служба (17:00)</span>' +
            '</label>' +
            '<div class="admin-service-detail evening-detail" style="' + (hasEvening ? '' : 'display:none') + '">' +
              '<select class="sel-evening">' +
                buildOptions(EVENING_OPTIONS, eveningName) +
              '</select>' +
            '</div>' +
          '</div>' +
          // Description (read-only preview)
          (desc ? '<p style="margin:12px 0 0;font-size:13px;color:#8a7b6a;">' + escHtml(desc) + '</p>' : '') +
        '</div>';

      weekEditor.appendChild(card);

      // Toggle morning detail visibility
      const chkM = card.querySelector('.chk-morning');
      const detM = card.querySelector('.morning-detail');
      chkM.addEventListener('change', () => {
        detM.style.display = chkM.checked ? '' : 'none';
      });

      // Toggle evening detail visibility
      const chkE = card.querySelector('.chk-evening');
      const detE = card.querySelector('.evening-detail');
      chkE.addEventListener('change', () => {
        detE.style.display = chkE.checked ? '' : 'none';
      });
    }
  }

  /* ── load week ───────────────────────────── */

  function loadWeek() {
    updateLabel();
    weekEditor.innerHTML = '<p style="text-align:center;color:#999;padding:40px 0;">Зареждане…</p>';

    fetch(API + '?action=week&date=' + fmtISO(currentMonday))
      .then(r => r.json())
      .then(json => {
        if (json.success) {
          renderWeek(json.data);
        } else {
          weekEditor.innerHTML = '<p style="text-align:center;color:#c0504d;">Грешка: ' + (json.error || '') + '</p>';
        }
      })
      .catch(err => {
        weekEditor.innerHTML = '<p style="text-align:center;color:#c0504d;">Грешка при зареждане.</p>';
        console.error(err);
      });
  }

  /* ── save week ───────────────────────────── */

  function saveWeek() {
    const cards = weekEditor.querySelectorAll('.day-card');
    const days = [];

    cards.forEach(card => {
      const dayNum = parseInt(card.dataset.dayNum);
      const date   = card.dataset.date;
      const hasMorning = card.querySelector('.chk-morning').checked;
      const hasEvening = card.querySelector('.chk-evening').checked;
      const morningName = card.querySelector('.sel-morning').value;
      const eveningName = card.querySelector('.sel-evening').value;

      days.push({
        day_of_week:     dayNum,
        service_date:    date,
        has_morning:     hasMorning,
        has_evening:     hasEvening,
        morning_service: hasMorning ? morningName : null,
        evening_service: hasEvening ? eveningName : null,
        start_time:      hasMorning ? '08:00' : null,
        end_time:        hasEvening ? '17:00' : null,
      });
    });

    const priest = weekPriest.value;

    btnSave.disabled = true;
    btnSave.textContent = 'Запазване…';

    fetch(API, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'save_week',
        priest: priest || null,
        days:   days,
      }),
    })
      .then(r => r.json())
      .then(json => {
        if (json.success) {
          showToast('Седмицата е запазена (' + json.saved + ' дни).');
          loadWeek();
        } else {
          showToast('Грешка: ' + (json.error || 'неизвестна'));
        }
      })
      .catch(() => showToast('Грешка при запазване.'))
      .finally(() => {
        btnSave.disabled = false;
        btnSave.textContent = 'Запази седмицата';
      });
  }

  /* ── events ───────────────────────────────── */

  btnPrev.addEventListener('click', () => {
    currentMonday = addDays(currentMonday, -7);
    loadWeek();
  });

  btnNext.addEventListener('click', () => {
    currentMonday = addDays(currentMonday, 7);
    loadWeek();
  });

  btnSave.addEventListener('click', saveWeek);

  /* ── init ─────────────────────────────────── */
  loadWeek();
})();
