/* Albums page interactions - Tailwind migration */
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

    const filterButtons = Array.from(document.querySelectorAll('.filter-pill[data-type]'));
    const genreSelect = document.querySelector('[data-filter-genre]');
    const sortSelect = document.querySelector('[data-sort]');
    const searchInput = document.querySelector('[data-search]');
    const grid = document.getElementById('albumsGrid');

    let currentType = 'all';
    let currentGenre = 'all';
    let searchTerm = '';

    function setActiveFilter(button) {
        filterButtons.forEach((btn) => btn.classList.toggle('is-active', btn === button));
    }

    function getCards() {
        return Array.from(document.querySelectorAll('.album-card'));
    }

    function matchesFilters(card) {
        const type = card.dataset.type || '';
        const genre = card.dataset.genre || '';
        const title = card.dataset.title || '';
        const artist = card.dataset.artist || '';

        if (currentType !== 'all' && type !== currentType) return false;
        if (currentGenre !== 'all' && genre !== currentGenre) return false;
        if (searchTerm && !title.includes(searchTerm) && !artist.includes(searchTerm)) return false;
        return true;
    }

    function applyFilters() {
        getCards().forEach((card) => {
            const show = matchesFilters(card);
            card.classList.toggle('hidden', !show);
        });
    }

    function sortCards(sortBy) {
        if (!grid) return;
        const cards = getCards();
        const sorted = cards.sort((a, b) => {
            const titleA = a.dataset.title || '';
            const titleB = b.dataset.title || '';
            const tracksA = parseInt(a.dataset.tracks || '0', 10);
            const tracksB = parseInt(b.dataset.tracks || '0', 10);

            if (sortBy === 'alphabetical') {
                return titleA.localeCompare(titleB, 'fr', { sensitivity: 'base' });
            }
            if (sortBy === 'tracks') {
                return tracksB - tracksA;
            }
            return 0;
        });

        sorted.forEach((card) => grid.appendChild(card));
    }

    filterButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
            currentType = btn.dataset.type || 'all';
            setActiveFilter(btn);
            applyFilters();
        });
    });

    if (genreSelect) {
        genreSelect.addEventListener('change', () => {
            currentGenre = genreSelect.value || 'all';
            applyFilters();
        });
    }

    if (sortSelect) {
        sortSelect.addEventListener('change', () => {
            sortCards(sortSelect.value);
            applyFilters();
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', () => {
            searchTerm = searchInput.value.trim().toLowerCase();
            applyFilters();
        });
    }

    document.addEventListener('click', (event) => {
        const actionBtn = event.target.closest('[data-action]');
        if (!actionBtn) return;
        const action = actionBtn.dataset.action;

        if (action === 'play') {
            showToast('Lecture de l\'album...');
        }

        if (action === 'favorite') {
            const icon = actionBtn.querySelector('i');
            const isFav = icon?.classList.contains('far');
            if (icon) {
                icon.classList.toggle('far', !isFav);
                icon.classList.toggle('fas', isFav);
                icon.classList.toggle('text-rose-400', isFav);
            }
            showToast(isFav ? 'Ajoute aux favoris' : 'Retire des favoris');
        }

        if (action === 'page') {
            const page = actionBtn.dataset.page || '';
            showToast(`Page ${page}`);
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

    sortCards(sortSelect?.value || 'recent');
    applyFilters();
})();
