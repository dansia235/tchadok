/*!
 * Tchadok Platform - Audio Player Module (Dynamic)
 * Lecteur audio global avec chargement des titres via l'API.
 */

(function () {
    'use strict';

    let audioPlayer = null;
    let currentTrackData = null;
    let currentPlaylist = [];
    let currentTrackIndex = 0;
    let hasRecordedStream = false;

    const playerSelectors = {
        root: '.audio-player',
        title: '.current-track-title',
        artist: '.current-track-artist',
        image: '.current-track-image',
        progress: '[data-progress]',
        progressFill: '.progress-fill',
        currentTime: '.current-time',
        duration: '.duration',
        volumeSlider: '.volume-slider',
        playPauseIcon: '[data-action="play-pause"] i'
    };

    function siteUrl() {
        return (window.TCHADOK && window.TCHADOK.SITE_URL) ? window.TCHADOK.SITE_URL : '';
    }

    function createPlayerInterface() {
        if (document.querySelector(playerSelectors.root)) {
            return;
        }

        const playerHTML = `
            <div class="audio-player fixed bottom-4 left-1/2 z-50 hidden w-[min(95vw,860px)] -translate-x-1/2 rounded-3xl border border-white/10 bg-surface/90 p-4 shadow-elev-2 backdrop-blur">
                <div class="flex flex-wrap items-center gap-4">
                    <div class="flex min-w-0 items-center gap-3">
                        <img class="current-track-image h-12 w-12 rounded-xl object-cover" src="" alt="">
                        <div class="min-w-0">
                            <p class="current-track-title truncate text-sm font-semibold text-text">Aucun titre</p>
                            <p class="current-track-artist truncate text-xs text-muted">---</p>
                        </div>
                    </div>
                    <div class="flex min-w-[220px] flex-1 flex-col gap-2">
                        <div class="flex items-center justify-center gap-3">
                            <button class="control-btn grid h-9 w-9 place-items-center rounded-full border border-white/10 bg-white/5 text-text hover:bg-white/10" data-action="previous" type="button" aria-label="Titre precedent">
                                <i class="fas fa-step-backward"></i>
                            </button>
                            <button class="control-btn grid h-11 w-11 place-items-center rounded-full bg-accent text-white shadow-elev-1" data-action="play-pause" type="button" aria-label="Lecture/Pause">
                                <i class="fas fa-play"></i>
                            </button>
                            <button class="control-btn grid h-9 w-9 place-items-center rounded-full border border-white/10 bg-white/5 text-text hover:bg-white/10" data-action="next" type="button" aria-label="Titre suivant">
                                <i class="fas fa-step-forward"></i>
                            </button>
                        </div>
                        <div class="flex items-center gap-2 text-xs text-muted">
                            <span class="current-time">0:00</span>
                            <div class="progress-bar flex-1 cursor-pointer rounded-full bg-white/10" data-progress>
                                <div class="progress-fill h-1 rounded-full bg-accent" style="width: 0%;"></div>
                            </div>
                            <span class="duration">0:00</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button class="control-btn grid h-9 w-9 place-items-center rounded-full border border-white/10 bg-white/5 text-text hover:bg-white/10" data-action="volume" type="button" aria-label="Volume">
                            <i class="fas fa-volume-up"></i>
                        </button>
                        <input type="range" min="0" max="100" value="80" class="volume-slider h-1 w-24 accent-accent">
                        <button class="control-btn grid h-9 w-9 place-items-center rounded-full border border-white/10 bg-white/5 text-text hover:bg-white/10" data-action="close" type="button" aria-label="Fermer">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', playerHTML);
    }

    function formatTime(seconds) {
        if (!Number.isFinite(seconds)) return '0:00';
        const mins = Math.floor(seconds / 60);
        const secs = Math.floor(seconds % 60);
        return `${mins}:${secs.toString().padStart(2, '0')}`;
    }

    function setPlayerVisible(visible) {
        const player = document.querySelector(playerSelectors.root);
        if (!player) return;
        player.classList.toggle('hidden', !visible);
        document.body.style.paddingBottom = visible ? '120px' : '0';
    }

    function updatePlayerInterface() {
        if (!currentTrackData) return;
        const titleEl = document.querySelector(playerSelectors.title);
        const artistEl = document.querySelector(playerSelectors.artist);
        const imageEl = document.querySelector(playerSelectors.image);
        const durationEl = document.querySelector(playerSelectors.duration);

        if (titleEl) titleEl.textContent = currentTrackData.title || 'Titre inconnu';
        if (artistEl) artistEl.textContent = currentTrackData.artist_name || currentTrackData.artist || 'Artiste inconnu';

        if (imageEl) {
            const cover = currentTrackData.album_cover || 'assets/images/default-cover.jpg';
            imageEl.src = cover.startsWith('http') ? cover : `${siteUrl()}/${cover}`;
            imageEl.alt = currentTrackData.title || 'Titre';
        }

        if (durationEl && audioPlayer?.duration) {
            durationEl.textContent = formatTime(audioPlayer.duration);
        }
    }

    function updateProgress() {
        if (!audioPlayer) return;
        const progressFill = document.querySelector(playerSelectors.progressFill);
        const currentTimeEl = document.querySelector(playerSelectors.currentTime);
        const durationEl = document.querySelector(playerSelectors.duration);
        const ratio = audioPlayer.duration ? (audioPlayer.currentTime / audioPlayer.duration) : 0;

        if (progressFill) {
            progressFill.style.width = `${Math.min(100, Math.max(0, ratio * 100))}%`;
        }

        if (currentTimeEl) {
            currentTimeEl.textContent = formatTime(audioPlayer.currentTime);
        }

        if (durationEl && audioPlayer.duration) {
            durationEl.textContent = formatTime(audioPlayer.duration);
        }
    }

    function updatePlayIcon(isPlaying) {
        const icon = document.querySelector(playerSelectors.playPauseIcon);
        if (!icon) return;
        icon.className = isPlaying ? 'fas fa-pause' : 'fas fa-play';
    }

    function getPlayableSource(track) {
        if (!track) return null;
        const isFree = Number(track.is_free) === 1;
        const price = Number(track.price || 0);
        const isPremium = window.TCHADOK && window.TCHADOK.IS_PREMIUM;

        if (isFree || isPremium) {
            return track.audio_file || track.preview_file || null;
        }

        if (track.preview_file) {
            return track.preview_file;
        }

        if (price > 0) {
            showNotification('Ce titre est payant. Passez au paiement pour l\'ecouter.', 'warning');
            return null;
        }

        showNotification('Ce titre est reserve aux abonnes Premium.', 'warning');
        return null;
    }

    async function fetchTrackData(trackId, mode = 'resolve') {
        const endpoint = `${siteUrl()}/api/track.php?action=${encodeURIComponent(mode)}&id=${encodeURIComponent(trackId)}`;
        const response = await fetch(endpoint);
        const payload = await response.json();
        if (!payload.success || !payload.data) {
            throw new Error(payload.error?.message || 'Titre introuvable');
        }
        return payload.data;
    }

    function initAudio(track) {
        if (audioPlayer) {
            audioPlayer.pause();
        }

        audioPlayer = new Audio();
        const source = getPlayableSource(track);
        if (!source) {
            audioPlayer = null;
            updatePlayIcon(false);
            return false;
        }

        audioPlayer.src = source.startsWith('http') ? source : `${siteUrl()}/${source}`;
        audioPlayer.preload = 'metadata';
        const slider = document.querySelector(playerSelectors.volumeSlider);
        if (slider) {
            const volume = Math.max(0, Math.min(100, parseInt(slider.value, 10) || 80));
            audioPlayer.volume = volume / 100;
        }
        hasRecordedStream = false;

        audioPlayer.addEventListener('loadedmetadata', updatePlayerInterface);
        audioPlayer.addEventListener('timeupdate', updateProgress);
        audioPlayer.addEventListener('ended', () => {
            updatePlayIcon(false);
            if (currentPlaylist.length) {
                nextTrack();
            }
        });
        audioPlayer.addEventListener('play', () => {
            if (!hasRecordedStream) {
                recordStream(track.id);
                hasRecordedStream = true;
            }
            updatePlayIcon(true);
        });
        audioPlayer.addEventListener('pause', () => updatePlayIcon(false));
        audioPlayer.addEventListener('error', () => {
            showNotification('Erreur de lecture audio', 'error');
            updatePlayIcon(false);
        });

        return true;
    }

    async function loadAndPlayTrack(trackData) {
        currentTrackData = trackData;
        updatePlayerInterface();
        setPlayerVisible(true);

        if (!initAudio(trackData)) {
            return;
        }

        try {
            await audioPlayer.play();
            updatePlayIcon(true);
        } catch (error) {
            showNotification('Lecture bloquee par le navigateur.', 'warning');
            updatePlayIcon(false);
        }
    }

    function togglePlayPause() {
        if (!audioPlayer) return;
        if (audioPlayer.paused) {
            audioPlayer.play().then(() => updatePlayIcon(true)).catch(() => {
                showNotification('Lecture impossible', 'error');
            });
        } else {
            audioPlayer.pause();
            updatePlayIcon(false);
        }
    }

    function nextTrack() {
        if (!currentPlaylist.length) return;
        currentTrackIndex = (currentTrackIndex + 1) % currentPlaylist.length;
        const nextItem = currentPlaylist[currentTrackIndex];
        const nextId = typeof nextItem === 'object' ? nextItem.id : nextItem;
        if (nextId) {
            window.playTrack(nextId, currentPlaylist);
        }
    }

    function previousTrack() {
        if (!currentPlaylist.length) return;
        currentTrackIndex = currentTrackIndex > 0 ? currentTrackIndex - 1 : currentPlaylist.length - 1;
        const prevItem = currentPlaylist[currentTrackIndex];
        const prevId = typeof prevItem === 'object' ? prevItem.id : prevItem;
        if (prevId) {
            window.playTrack(prevId, currentPlaylist);
        }
    }

    function hidePlayer() {
        if (audioPlayer) {
            audioPlayer.pause();
        }
        audioPlayer = null;
        currentTrackData = null;
        updatePlayIcon(false);
        setPlayerVisible(false);
    }

    function handleProgressClick(event) {
        if (!audioPlayer || !audioPlayer.duration) return;
        const rect = event.currentTarget.getBoundingClientRect();
        const ratio = (event.clientX - rect.left) / rect.width;
        audioPlayer.currentTime = Math.max(0, Math.min(audioPlayer.duration, ratio * audioPlayer.duration));
    }

    function handleVolumeChange(value) {
        if (!audioPlayer) return;
        const volume = Math.max(0, Math.min(100, parseInt(value, 10) || 0));
        audioPlayer.volume = volume / 100;
    }

    function recordStream(trackId) {
        if (!window.TCHADOK || !window.TCHADOK.IS_LOGGED_IN) return;
        fetch(`${siteUrl()}/api/stream.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.TCHADOK.CSRF_TOKEN || ''
            },
            body: JSON.stringify({
                track_id: trackId,
                source: 'web'
            })
        }).catch(() => {});
    }

    function showNotification(message, type = 'info') {
        if (typeof window.showNotification === 'function') {
            window.showNotification(message, type);
            return;
        }
        const toast = document.createElement('div');
        toast.className = 'fixed right-4 top-4 z-50 max-w-xs rounded-2xl border border-white/10 bg-surface/90 px-4 py-3 text-sm text-text shadow-elev-2';
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(() => {
            toast.remove();
        }, 2500);
    }

    function registerControls() {
        document.addEventListener('click', (event) => {
            const control = event.target.closest('[data-action]');
            if (!control) return;
            const action = control.dataset.action;
            if (action === 'play-pause') togglePlayPause();
            if (action === 'next') nextTrack();
            if (action === 'previous') previousTrack();
            if (action === 'close') hidePlayer();
        });

        const progress = document.querySelector(playerSelectors.progress);
        if (progress) {
            progress.addEventListener('click', handleProgressClick);
        }

        const volumeSlider = document.querySelector(playerSelectors.volumeSlider);
        if (volumeSlider) {
            volumeSlider.addEventListener('input', (event) => handleVolumeChange(event.target.value));
        }
    }

    async function playTrack(trackId, playlist = null) {
        if (!trackId) return;
        const id = parseInt(trackId, 10);
        if (Number.isNaN(id)) return;

        if (Array.isArray(playlist)) {
            currentPlaylist = playlist;
            const index = playlist.findIndex((item) => (typeof item === 'object' ? item.id : item) == id);
            currentTrackIndex = index >= 0 ? index : 0;
        } else {
            currentPlaylist = [];
            currentTrackIndex = 0;
        }

        try {
            const track = await fetchTrackData(id, 'resolve');
            await loadAndPlayTrack(track);
        } catch (error) {
            showNotification(error.message || 'Titre indisponible', 'error');
        }
    }

    async function playArtist(artistId) {
        if (!artistId) return;
        const id = parseInt(artistId, 10);
        if (Number.isNaN(id)) return;
        try {
            const track = await fetchTrackData(id, 'artist');
            await loadAndPlayTrack(track);
        } catch (error) {
            showNotification(error.message || 'Aucun titre disponible pour cet artiste', 'error');
        }
    }

    function initializePlayer() {
        createPlayerInterface();
        registerControls();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializePlayer);
    } else {
        initializePlayer();
    }

    window.playTrack = playTrack;
    window.playArtist = playArtist;
    window.togglePlayPause = togglePlayPause;
    window.hidePlayer = hidePlayer;
    window.nextTrack = nextTrack;
    window.previousTrack = previousTrack;
})();
