/* Discover page interactions - Tailwind migration */
(function() {
    'use strict';

    const filterButtons = Array.from(document.querySelectorAll('.filter-card[data-filter]'));
    const filterShortcuts = Array.from(document.querySelectorAll('[data-filter-shortcut]'));
    const sections = Array.from(document.querySelectorAll('[data-section]'));
    const shuffleButtons = Array.from(document.querySelectorAll('[data-shuffle]'));
    const wheel = document.querySelector('[data-wheel]');
    const wheelCenter = document.querySelector('[data-wheel-center]');
    const wheelSegments = Array.from(document.querySelectorAll('[data-genre]'));
    const genreButtons = Array.from(document.querySelectorAll('[data-filter-genre]'));

    let currentFilter = 'trending';

    function setActiveFilter(filter) {
        filterButtons.forEach((btn) => {
            const isActive = btn.dataset.filter === filter;
            btn.classList.toggle('is-active', isActive);
            btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
    }

    function switchSection(filter) {
        currentFilter = filter;
        setActiveFilter(filter);

        sections.forEach((section) => {
            const isTarget = section.dataset.section === filter;
            if (isTarget) {
                section.classList.remove('hidden');
                section.classList.add('opacity-0', 'translate-y-2');
                requestAnimationFrame(() => {
                    section.classList.remove('opacity-0', 'translate-y-2');
                });
            } else {
                section.classList.add('hidden');
            }
        });
    }

    filterButtons.forEach((btn) => {
        btn.addEventListener('click', () => switchSection(btn.dataset.filter));
    });

    filterShortcuts.forEach((btn) => {
        btn.addEventListener('click', () => switchSection(btn.dataset.filterShortcut));
    });

    shuffleButtons.forEach((btn) => {
        btn.addEventListener('click', () => shuffleContent(btn.dataset.shuffle));
    });

    function shuffleContent(section) {
        const container = document.querySelector(`[data-section="${section}"] [data-grid]`);
        if (!container) return;
        const cards = Array.from(container.children);

        for (let i = cards.length - 1; i > 0; i -= 1) {
            const j = Math.floor(Math.random() * (i + 1));
            [cards[i], cards[j]] = [cards[j], cards[i]];
        }

        cards.forEach((card) => container.appendChild(card));
        showToast('Contenu melange');
    }

    function highlightSegment(segment) {
        wheelSegments.forEach((seg) => seg.classList.remove('is-highlight'));
        segment.classList.add('is-highlight');
    }

    if (wheel && wheelCenter && wheelSegments.length) {
        wheelCenter.addEventListener('click', () => {
            const randomSegment = wheelSegments[Math.floor(Math.random() * wheelSegments.length)];
            highlightSegment(randomSegment);
            const genre = randomSegment.dataset.genre || 'tchadok';
            showToast(`Genre decouvert: ${genre}`);
        });
    }

    wheelSegments.forEach((segment) => {
        segment.addEventListener('click', (event) => {
            event.stopPropagation();
            highlightSegment(segment);
            const genre = segment.dataset.genre || 'tchadok';
            showToast(`Exploration du genre: ${genre}`);
            switchSection('trending');
        });
    });

    genreButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
            const genre = btn.dataset.filterGenre || 'tchadok';
            showToast(`Recommandations pour ${genre}`);
            switchSection('trending');
        });
    });

    document.addEventListener('click', (event) => {
        const playBtn = event.target.closest('[data-play-id]');
        if (playBtn) {
            const title = playBtn.dataset.playTitle || 'Piste';
            if (typeof window.playTrack === 'function') {
                window.playTrack(playBtn.dataset.playId);
            } else {
                showToast(`Lecture: ${title}`);
            }
            return;
        }

        const actionBtn = event.target.closest('[data-action]');
        if (!actionBtn) return;
        const action = actionBtn.dataset.action;

        if (action === 'like') {
            const itemId = actionBtn.dataset.id;
            if (itemId) {
                toggleFavorite(actionBtn, itemId);
            }
        }

        if (action === 'playlist') {
            const itemId = actionBtn.dataset.id;
            if (itemId) {
                addToPlaylist(itemId);
            }
        }

        if (action === 'share') {
            shareItem();
        }
    });

    async function toggleFavorite(button, itemId) {
        if (!window.TCHADOK?.IS_LOGGED_IN) {
            showToast('Connectez-vous pour ajouter aux favoris');
            return;
        }

        try {
            const response = await fetch(`${window.TCHADOK?.SITE_URL || ''}/api/playlists.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.TCHADOK?.CSRF_TOKEN || ''
                },
                body: JSON.stringify({
                    action: 'toggle_favorite',
                    item_id: itemId,
                    item_type: 'track'
                })
            });
            const payload = await response.json();
            if (!payload.success) {
                throw new Error(payload.error?.message || 'Erreur');
            }
            const icon = button.querySelector('i');
            const isFav = payload.data?.is_favorite;
            if (icon) {
                icon.classList.toggle('far', !isFav);
                icon.classList.toggle('fas', isFav);
                icon.classList.toggle('text-rose-400', isFav);
            }
            showToast(isFav ? 'Ajoute aux favoris' : 'Retire des favoris');
        } catch (error) {
            showToast('Impossible de mettre a jour les favoris');
        }
    }

    async function addToPlaylist(trackId) {
        if (!window.TCHADOK?.IS_LOGGED_IN) {
            showToast('Connectez-vous pour gerer vos playlists');
            return;
        }

        try {
            const listResponse = await fetch(`${window.TCHADOK?.SITE_URL || ''}/api/playlists.php?action=list`);
            const listPayload = await listResponse.json();
            const playlists = listPayload?.data?.playlists || [];

            let playlistId = null;
            if (playlists.length) {
                const names = playlists.map((p) => p.name).join(', ');
                const chosen = window.prompt(`Ajouter a quelle playlist ?\nDisponible: ${names}`);
                if (!chosen) return;
                const match = playlists.find((p) => p.name.toLowerCase() === chosen.toLowerCase());
                if (match) {
                    playlistId = match.id;
                } else {
                    playlistId = await createPlaylist(chosen);
                }
            } else {
                const name = window.prompt('Creez votre premiere playlist:');
                if (!name) return;
                playlistId = await createPlaylist(name);
            }

            if (!playlistId) return;

            await fetch(`${window.TCHADOK?.SITE_URL || ''}/api/playlists.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.TCHADOK?.CSRF_TOKEN || ''
                },
                body: JSON.stringify({
                    action: 'add_track',
                    playlist_id: playlistId,
                    track_id: trackId
                })
            });

            showToast('Titre ajoute a la playlist');
        } catch (error) {
            showToast('Impossible d\'ajouter le titre');
        }
    }

    async function createPlaylist(name) {
        const response = await fetch(`${window.TCHADOK?.SITE_URL || ''}/api/playlists.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.TCHADOK?.CSRF_TOKEN || ''
            },
            body: JSON.stringify({ action: 'create', name })
        });
        const payload = await response.json();
        if (payload.success) {
            return payload.data?.playlist_id;
        }
        throw new Error(payload.error?.message || 'Erreur');
    }

    function shareItem() {
        if (navigator.share) {
            navigator.share({
                title: 'Decouverte musicale sur Tchadok',
                text: 'Ecoutez cette pepite de la musique tchadienne',
                url: window.location.href
            }).catch(() => {});
        } else if (navigator.clipboard) {
            navigator.clipboard.writeText(window.location.href).then(() => {
                showToast('Lien copie dans le presse-papiers');
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

    switchSection(currentFilter);
})();
