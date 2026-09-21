/* Aide page interactions - Tailwind migration */
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

    const searchInput = document.querySelector('[data-search-input]');
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            const value = searchInput.value.trim();
            if (value.length > 2) {
                showToast(`Recherche de "${value}" dans la base de connaissances...`);
            }
        });
    }

    document.addEventListener('click', (event) => {
        const actionBtn = event.target.closest('[data-action]');
        if (!actionBtn) return;
        const action = actionBtn.dataset.action;

        if (action === 'category') {
            const label = actionBtn.querySelector('h3')?.textContent || 'Categorie';
            showToast(`Ouverture de la categorie: ${label}`);
        }

        if (action === 'open-chat') {
            showToast('Connexion au chat en direct...');
            setTimeout(() => {
                showToast('Chat en direct connecte ! Un agent va vous repondre.');
            }, 1800);
        }
    });

    document.querySelectorAll('[data-faq-toggle]').forEach((toggle) => {
        toggle.addEventListener('click', () => {
            const parent = toggle.closest('[data-faq]');
            if (!parent) return;
            const isOpen = parent.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
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
