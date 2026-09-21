/* Genres page interactions - Tailwind migration */
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

    const tabButtons = Array.from(document.querySelectorAll('[data-tab-target]'));
    const tabPanels = Array.from(document.querySelectorAll('[data-tab-panel]'));

    function activateTab(target) {
        tabButtons.forEach((btn) => btn.classList.toggle('is-active', btn.dataset.tabTarget === target));
        tabPanels.forEach((panel) => panel.classList.toggle('hidden', panel.dataset.tabPanel !== target));
    }

    tabButtons.forEach((btn) => {
        btn.addEventListener('click', () => activateTab(btn.dataset.tabTarget));
    });

    document.addEventListener('click', (event) => {
        const actionBtn = event.target.closest('[data-action]');
        if (!actionBtn) return;
        const action = actionBtn.dataset.action;

        if (action === 'explore') {
            const genreName = actionBtn.dataset.genreName || '';
            showToast(`Exploration du genre ${genreName}`);
        }

        if (action === 'search') {
            const genreName = actionBtn.dataset.genreName || '';
            window.location.href = `${window.TCHADOK?.SITE_URL || ''}/search.php?q=${encodeURIComponent(genreName)}`;
        }

        if (action === 'play-artist') {
            showToast('Lecture de l\'artiste...');
        }
    });

    function showToast(message) {
        document.querySelectorAll('.tchadok-toast').forEach((toast) => toast.remove());
        const toast = document.createElement('div');
        toast.className = 'tchadok-toast';
        toast.textContent = message;
        toast.setAttribute('role', 'status');
        document.body.appendChild(toast);

        setTimeout(() => {
            toast.classList.add('is-hiding');
            setTimeout(() => toast.remove(), 300);
        }, 2600);
    }
})();
