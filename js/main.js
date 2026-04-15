// =========================
// Helpers
// =========================
function getHeaderHeight() {
  const header = document.querySelector('.site-header');
  return header ? header.offsetHeight : 0;
}

function smoothScrollTo(targetEl) {
  const headerOffset = getHeaderHeight();
  const elementTop = targetEl.getBoundingClientRect().top + window.pageYOffset;
  const style = window.getComputedStyle(targetEl);
  const padTop = parseFloat(style.paddingTop) || 0;
  // Scroll so the content (after padding) appears just below the header
  const y = Math.max(0, elementTop - headerOffset + padTop - 20);
  window.scrollTo({ top: y, behavior: 'smooth' });
}

// =========================
// Mobile menu
// =========================
const hamburger = document.getElementById('hamburger');
const nav = document.querySelector('.main-nav');

function openMenu() {
  if (!nav) return;
  nav.classList.add('active');
  if (hamburger) {
    hamburger.setAttribute('aria-expanded', 'true');
    hamburger.setAttribute('aria-label', 'Затвори меню');
  }
  document.body.style.overflow = 'hidden';
}

function closeMenu() {
  if (!nav) return;
  nav.classList.remove('active');
  if (hamburger) {
    hamburger.setAttribute('aria-expanded', 'false');
    hamburger.setAttribute('aria-label', 'Отвори меню');
  }
  document.body.style.overflow = '';
}

function toggleMenu() {
  if (!nav) return;
  nav.classList.contains('active') ? closeMenu() : openMenu();
}

if (hamburger && nav) {
  // accessibility defaults (works even if you didn't change HTML)
  hamburger.setAttribute('aria-expanded', 'false');
  hamburger.setAttribute('aria-label', 'Отвори меню');

  hamburger.addEventListener('click', (e) => {
    e.stopPropagation();
    toggleMenu();
  });

  // close on click outside
  /*document.addEventListener('click', (e) => {
    if (!nav.classList.contains('active')) return;
    const clickedInsideNav = nav.contains(e.target);
    const clickedHamburger = hamburger.contains(e.target);
    if (!clickedInsideNav && !clickedHamburger) closeMenu();
  });
*/
  document.addEventListener('click', (e) => {
    if (!nav.classList.contains('active')) return;
    if (!nav.contains(e.target) && !hamburger.contains(e.target)) {
      nav.classList.remove('active');
    }
  });

  // close on ESC
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && nav.classList.contains('active')) closeMenu();
  });
}

// =========================
// Smooth scroll for in-page anchors (with fixed header offset)
// =========================
document.querySelectorAll('a[href^="#"]').forEach(link => {
  link.addEventListener('click', function (e) {
    const href = this.getAttribute('href');
    if (!href || href === '#') return;

    const target = document.querySelector(href);
    if (!target) return;

    e.preventDefault();

    if (nav && nav.classList.contains('active')) closeMenu();

    smoothScrollTo(target);
  });
});

// =========================
// Gallery grid: open image in new tab (if used)
// =========================
document.querySelectorAll('.gallery-grid img').forEach(img => {
  img.addEventListener('click', () => {
    window.open(img.src, '_blank', 'noopener');
  });
});

// =========================
// Lightbox for horizontal gallery
// =========================
const galleryImages = document.querySelectorAll('.horizontal-gallery img');
const lightbox = document.getElementById('lightbox');
const lightboxImage = document.querySelector('.lightbox-image');
const lightboxClose = document.querySelector('.lightbox-close');

function closeLightbox() {
  if (!lightbox) return;
  lightbox.classList.remove('active');
  document.body.style.overflow = '';
}

if (galleryImages.length && lightbox && lightboxImage && lightboxClose) {
  galleryImages.forEach(img => {
    img.addEventListener('click', () => {
      lightboxImage.src = img.src;
      lightbox.classList.add('active');
      document.body.style.overflow = 'hidden';
    });
  });

  lightboxClose.addEventListener('click', closeLightbox);

  lightbox.addEventListener('click', (e) => {
    if (e.target === lightbox) closeLightbox();
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && lightbox.classList.contains('active')) {
      closeLightbox();
    }
  });
}
