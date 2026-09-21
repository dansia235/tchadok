/* Shared shell interactions for Tailwind pages */
(function() {
    'use strict';

    const navToggle = document.querySelector('[data-nav-toggle]');
    const navMenu = document.querySelector('[data-nav-menu]');

    if (navToggle && navMenu) {
        navToggle.addEventListener('click', () => {
            const isOpen = !navMenu.classList.contains('hidden');
            navMenu.classList.toggle('hidden');
            navToggle.setAttribute('aria-expanded', String(!isOpen));
        });
    }
})();
