/**
 * admin-auth.js – Handles logout button on all admin pages.
 * Included after the page-specific admin JS.
 */
(function () {
  'use strict';

  var btn = document.getElementById('btn-logout');
  if (!btn) return;

  btn.addEventListener('click', function (e) {
    e.preventDefault();

    fetch('/php/api_auth.php?action=logout', {
      method: 'POST',
      credentials: 'same-origin',
    })
      .then(function () {
        window.location.href = '/admin/login.php';
      })
      .catch(function () {
        window.location.href = '/admin/login.php';
      });
  });
})();

