/* Radio live page interactions - Dynamic */
(function () {
    'use strict';

    const dataEl = document.getElementById('radioData');
    let fallbackTracks = [];
    if (dataEl?.dataset.tracks) {
        try {
            fallbackTracks = JSON.parse(dataEl.dataset.tracks);
        } catch (error) {
            fallbackTracks = [];
        }
    }

    const playButton = document.getElementById('mainPlayBtn');
    const playIcon = document.getElementById('mainPlayIcon');
    const volumeSlider = document.getElementById('volumeSlider');
    const volumeDisplay = document.getElementById('volumeDisplay');
    const currentTrackEl = document.getElementById('currentTrack');
    const currentArtistEl = document.getElementById('currentArtist');
    const currentShowEl = document.getElementById('currentShow');
    const currentHostEl = document.getElementById('currentHost');
    const visualizer = document.querySelector('.visualizer-bars');
    const favoriteIcon = document.getElementById('favoriteIcon');

    let streamUrl = normalizeStreamUrl(dataEl?.dataset.streamUrl || null);
    let isPlaying = false;
    let radioAudio = null;
    let currentTrackId = null;

    const metadataUrl = `${window.TCHADOK?.SITE_URL || ''}/api/radio/metadata.php`;

    function normalizeStreamUrl(rawUrl) {
        if (!rawUrl) return null;
        return String(rawUrl).trim().replace(/&amp;/gi, '&');
    }

    function resolveStreamUrl(rawUrl) {
        const cleanUrl = normalizeStreamUrl(rawUrl);
        if (!cleanUrl) return null;
        if (cleanUrl.startsWith('http://') || cleanUrl.startsWith('https://')) {
            return cleanUrl;
        }
        const baseUrl = (window.TCHADOK?.SITE_URL || '').replace(/\/+$/, '');
        const path = cleanUrl.replace(/^\/+/, '');
        return `${baseUrl}/${path}`;
    }

    function handlePlayError(error, resolvedUrl) {
        const errorName = error?.name || '';

        if (window.location.protocol === 'https:' && /^http:\/\//i.test(resolvedUrl || '')) {
            showToast('Flux HTTP bloque sur une page HTTPS. Utilisez une URL de stream en HTTPS.');
            return;
        }

        if (errorName === 'NotAllowedError') {
            showToast('Lecture bloquee par le navigateur. Cliquez une nouvelle fois pour demarrer.');
            return;
        }

        if (errorName === 'NotSupportedError') {
            showToast('Flux non supporte. Verifiez l URL du stream.');
            return;
        }

        showToast('Impossible de demarrer le direct.');
    }

    async function fetchMetadata() {
        try {
            const response = await fetch(metadataUrl);
            const payload = await response.json();
            if (!payload?.success) {
                return;
            }

            if (payload.station?.stream_url) {
                streamUrl = normalizeStreamUrl(payload.station.stream_url);
            }

            if (payload.current_track) {
                currentTrackId = payload.current_track.id || null;
                updateTrackInfo(payload.current_track.title, payload.current_track.artist);
            } else if (fallbackTracks.length) {
                const next = fallbackTracks[0];
                currentTrackId = next.id || null;
                updateTrackInfo(next.title, next.artist);
            }

            if (payload.current_show) {
                if (currentShowEl) currentShowEl.textContent = payload.current_show.title || 'Programmation en cours';
                if (currentHostEl) {
                    const host = payload.current_show.host || 'Tchadok Radio';
                    const time = payload.current_show.start_time && payload.current_show.end_time
                        ? `${payload.current_show.start_time} - ${payload.current_show.end_time}`
                        : '';
                    currentHostEl.textContent = time ? `${host} • ${time}` : host;
                }
            }
        } catch (error) {
            // keep existing UI
        }
    }

    function updateTrackInfo(title, artist) {
        if (currentTrackEl) currentTrackEl.textContent = title || 'Aucun titre';
        if (currentArtistEl) currentArtistEl.textContent = artist || 'Radio Live';
    }

    async function togglePlay() {
        if (!streamUrl) {
            showToast('Initialisation du flux en cours. Cliquez a nouveau dans un instant.');
            fetchMetadata();
            return;
        }

        const resolvedUrl = resolveStreamUrl(streamUrl);
        if (!resolvedUrl) {
            showToast('Flux radio indisponible pour le moment');
            return;
        }

        if (!radioAudio) {
            radioAudio = new Audio();
            radioAudio.src = resolvedUrl;
            radioAudio.preload = 'none';
            if (volumeSlider) {
                const volume = parseInt(volumeSlider.value, 10) || 80;
                radioAudio.volume = Math.max(0, Math.min(1, volume / 100));
            }
            radioAudio.addEventListener('play', () => updatePlayState(true));
            radioAudio.addEventListener('pause', () => updatePlayState(false));
            radioAudio.addEventListener('error', () => {
                updatePlayState(false);
                showToast('Erreur de lecture du direct');
            });
        } else if (radioAudio.src !== resolvedUrl) {
            radioAudio.src = resolvedUrl;
        }

        if (isPlaying) {
            radioAudio.pause();
        } else {
            try {
                await radioAudio.play();
                updatePlayState(true);
            } catch (error) {
                handlePlayError(error, resolvedUrl);
            }
        }
    }

    function updatePlayState(playing) {
        isPlaying = playing;
        if (playIcon) {
            playIcon.classList.toggle('fa-play', !playing);
            playIcon.classList.toggle('fa-pause', playing);
        }
        if (visualizer) {
            visualizer.classList.toggle('is-playing', playing);
        }
    }

    if (playButton) {
        playButton.addEventListener('click', togglePlay);
    }

    if (volumeSlider && volumeDisplay) {
        volumeSlider.addEventListener('input', () => {
            const volume = parseInt(volumeSlider.value, 10) || 0;
            volumeDisplay.textContent = `${volume}%`;
            if (radioAudio) {
                radioAudio.volume = Math.max(0, Math.min(1, volume / 100));
            }
        });
    }

    document.addEventListener('click', (event) => {
        const actionButton = event.target.closest('[data-action]');
        if (!actionButton) return;
        const action = actionButton.dataset.action;

        if (action === 'favorite' && currentTrackId) {
            toggleFavorite(currentTrackId, 'track', favoriteIcon);
        }

        if (action === 'share') {
            shareCurrentTrack();
        }

        if (action === 'replay') {
            const trackId = actionButton.dataset.id;
            if (trackId && typeof window.playTrack === 'function') {
                window.playTrack(trackId);
            }
        }

        if (action === 'like') {
            const trackId = actionButton.dataset.id || currentTrackId;
            if (trackId) {
                toggleFavorite(trackId, 'track', actionButton.querySelector('i'));
            }
        }
    });

    async function toggleFavorite(itemId, itemType, iconEl) {
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
                    item_type: itemType
                })
            });
            const payload = await response.json();
            if (payload.success) {
                const isFav = payload.data?.is_favorite;
                if (iconEl) {
                    iconEl.classList.toggle('far', !isFav);
                    iconEl.classList.toggle('fas', isFav);
                    iconEl.classList.toggle('text-rose-400', isFav);
                }
                showToast(isFav ? 'Ajoute aux favoris' : 'Retire des favoris');
            }
        } catch (error) {
            showToast('Impossible de modifier les favoris');
        }
    }

    function shareCurrentTrack() {
        const title = currentTrackEl?.textContent || '';
        const artist = currentArtistEl?.textContent || '';
        if (navigator.share) {
            navigator.share({
                title: `${title} - ${artist}`,
                text: `Ecoutez "${title}" de ${artist} sur Tchadok Radio Live !`,
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

    fetchMetadata();
    setInterval(fetchMetadata, 30000);
})();
