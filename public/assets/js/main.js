/**
 * public/assets/js/main.js
 *
 * Поки що мінімум JavaScript — увесь сайт працює без JS.
 * Тут залишаємо точку входу для майбутніх покращень
 * (lightbox, AJAX-пагінація і т.д.).
 */
'use strict';

document.addEventListener('DOMContentLoaded', () => {
    // Підсвітити поточний пункт меню за збігом шляху.
    const path = location.pathname.replace(/\/$/, '') || '/';
    document.querySelectorAll('.site-nav a').forEach((a) => {
        const href = a.getAttribute('href') || '';
        const hrefPath = href.replace(/\/$/, '') || '/';
        if (hrefPath === path) {
            a.style.color = 'var(--fg-strong)';
        }
    });
});
