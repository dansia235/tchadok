/* Artists page interactions - Tailwind migration */
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

    const filterButtons = Array.from(document.querySelectorAll('[data-filter]'));
    const genreButtons = Array.from(document.querySelectorAll('[data-genre]'));
    const viewButtons = Array.from(document.querySelectorAll('[data-view]'));
    const sortSelect = document.querySelector('[data-sort-select]');
    const searchInput = document.querySelector('[data-search-input]');
    const artistsGrid = document.getElementById('artistsGrid');
    const featuredGrid = document.querySelector('[data-featured-grid]');

    let currentFilter = 'all';
    let currentGenre = 'all';
    let currentSearch = '';

    function setActive(list, active) {
        list.forEach((btn) => btn.classList.toggle('is-active', btn === active));
    }

    function getCards() {
        return Array.from(document.querySelectorAll('.artist-card'));
    }

    function matchesFilter(card) {
        const isVerified = card.dataset.verified === 'true';
        const isTrending = card.dataset.trending === 'true';
        const isNew = card.dataset.new === 'true';
        const genres = (card.dataset.genre || '').split(',').filter(Boolean);
        const name = (card.dataset.name || '').toLowerCase();
        const genreLabel = (card.dataset.genreLabel || card.dataset.genre || '').toLowerCase();

        if (currentFilter === 'verified' && !isVerified) return false;
        if (currentFilter === 'trending' && !isTrending) return false;
        if (currentFilter === 'new' && !isNew) return false;
        if (currentGenre !== 'all' && !genres.includes(currentGenre)) return false;
        if (currentSearch && !name.includes(currentSearch) && !genreLabel.includes(currentSearch)) return false;

        return true;
    }

    function updateVisibleCount(count) {
        document.querySelectorAll('[data-visible-count]').forEach((el) => {
            el.textContent = count;
        });
    }

    function applyFilters() {
        const cards = getCards();
        let visible = 0;
        cards.forEach((card) => {
            const show = matchesFilter(card);
            card.classList.toggle('hidden', !show);
            if (show) visible += 1;
        });
        updateVisibleCount(visible);
    }

    function sortCards(sortBy) {
        if (!artistsGrid) return;
        const cards = getCards();
        const sorted = cards.sort((a, b) => {
            const nameA = (a.dataset.name || '').toLowerCase();
            const nameB = (b.dataset.name || '').toLowerCase();
            const playsA = parseInt(a.dataset.plays || '0', 10);
            const playsB = parseInt(b.dataset.plays || '0', 10);
            const followersA = parseInt(a.dataset.followers || '0', 10);
            const followersB = parseInt(b.dataset.followers || '0', 10);
            const newA = a.dataset.new === 'true' ? 1 : 0;
            const newB = b.dataset.new === 'true' ? 1 : 0;

            if (sortBy === 'alphabetical') {
                return nameA.localeCompare(nameB, 'fr', { sensitivity: 'base' });
            }
            if (sortBy === 'plays') {
                return playsB - playsA;
            }
            if (sortBy === 'newest') {
                return newB - newA;
            }
            if (sortBy === 'popularity') {
                const scoreA = playsA + followersA;
                const scoreB = playsB + followersB;
                return scoreB - scoreA;
            }
            return 0;
        });

        sorted.forEach((card) => artistsGrid.appendChild(card));
    }

    filterButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
            currentFilter = btn.dataset.filter || 'all';
            setActive(filterButtons, btn);
            applyFilters();
        });
    });

    genreButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
            currentGenre = btn.dataset.genre || 'all';
            setActive(genreButtons, btn);
            applyFilters();
        });
    });

    viewButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
            const view = btn.dataset.view || 'grid';
            setActive(viewButtons, btn);
            if (artistsGrid) {
                artistsGrid.classList.toggle('is-list', view === 'list');
            }
        });
    });

    if (sortSelect) {
        sortSelect.addEventListener('change', () => {
            sortCards(sortSelect.value);
            applyFilters();
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', () => {
            currentSearch = searchInput.value.trim().toLowerCase();
            applyFilters();
        });
    }

    function shuffleGrid(grid) {
        if (!grid) return;
        const items = Array.from(grid.children);
        for (let i = items.length - 1; i > 0; i -= 1) {
            const j = Math.floor(Math.random() * (i + 1));
            [items[i], items[j]] = [items[j], items[i]];
        }
        items.forEach((item) => grid.appendChild(item));
    }

    document.addEventListener('click', (event) => {
        const actionBtn = event.target.closest('[data-action]');
        if (!actionBtn) return;
        const action = actionBtn.dataset.action;

        if (action === 'voice-search') {
            showToast('Recherche vocale bientot disponible');
        }

        if (action === 'shuffle-featured') {
            shuffleGrid(featuredGrid);
            showToast('Selection remelangee');
        }

        if (action === 'play' || action === 'listen') {
            const artistId = actionBtn.dataset.artistId || '0';
            if (typeof window.playArtist === 'function') {
                window.playArtist(artistId);
            } else {
                showToast('Lecture en cours...');
            }
        }

        if (action === 'follow') {
            toggleFollow(actionBtn);
        }

        if (action === 'share') {
            shareArtist();
        }

        if (action === 'add') {
            showToast('Ajoute a votre bibliotheque');
        }

        if (action === 'load-more') {
            showToast('Chargement des artistes...');
        }

        if (action === 'subscribe') {
            showToast('Merci pour votre abonnement !');
        }
    });

    async function toggleFollow(button) {
        if (!window.TCHADOK?.IS_LOGGED_IN) {
            showToast('Connectez-vous pour suivre un artiste');
            return;
        }

        const artistId = button.dataset.artistId;
        if (!artistId) {
            showToast('Artiste introuvable');
            return;
        }

        try {
            const response = await fetch(`${window.TCHADOK?.SITE_URL || ''}/api/follows.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.TCHADOK?.CSRF_TOKEN || ''
                },
                body: JSON.stringify({
                    action: 'toggle',
                    followed_id: artistId,
                    followed_type: 'artist'
                })
            });
            const payload = await response.json();
            if (!payload.success) {
                throw new Error(payload.error || 'Erreur');
            }

            const isFollowing = payload.data?.is_following;
            button.dataset.following = isFollowing ? 'true' : 'false';
            const icon = button.querySelector('i');
            if (icon) {
                icon.classList.toggle('fa-plus', !isFollowing);
                icon.classList.toggle('fa-check', isFollowing);
                icon.classList.toggle('text-emerald-300', isFollowing);
            }
            showToast(isFollowing ? 'Artiste suivi' : 'Abonnement retire');
        } catch (error) {
            showToast('Impossible de modifier le suivi');
        }
    }

    function shareArtist() {
        if (navigator.share) {
            navigator.share({
                title: 'Artistes Tchadiens',
                text: 'Decouvrez ces artistes sur Tchadok !',
                url: window.location.href
            }).catch(() => {});
        } else if (navigator.clipboard) {
            navigator.clipboard.writeText(window.location.href).then(() => {
                showToast('Lien copie !');
            });
        } else {
            showToast('Partage non disponible');
        }
    }

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

    const counters = document.querySelectorAll('[data-count]');
    const animateCount = (el) => {
        const target = parseInt(el.dataset.count, 10) || 0;
        const duration = 1600;
        const startTime = performance.now();

        const tick = (now) => {
            const progress = Math.min((now - startTime) / duration, 1);
            const value = Math.floor(progress * target);
            el.textContent = value.toLocaleString('fr-FR');
            if (progress < 1) {
                requestAnimationFrame(tick);
            } else {
                el.textContent = target.toLocaleString('fr-FR');
            }
        };

        requestAnimationFrame(tick);
    };

    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    animateCount(entry.target);
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.4 });

        counters.forEach((counter) => observer.observe(counter));
    } else {
        counters.forEach((counter) => animateCount(counter));
    }

    sortCards(sortSelect?.value || 'popularity');
    applyFilters();
})();
