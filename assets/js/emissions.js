/* Emissions page interactions - Tailwind migration */
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

    const liveButtons = Array.from(document.querySelectorAll('[data-action="play-live"]'));
    const liveIcon = document.getElementById('livePlayIcon');
    const radioVisual = document.querySelector('[data-radio-visual]');
    const volumeSlider = document.querySelector('[data-volume-slider]');
    const volumeDisplay = document.querySelector('[data-volume-display]');

    let isLivePlaying = false;
    let liveAudio = null;
    let streamUrl = null;

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
        return `${baseUrl}/${cleanUrl.replace(/^\/+/, '')}`;
    }

    function handleLivePlayError(error, resolvedUrl) {
        const errorName = error?.name || '';

        if (window.location.protocol === 'https:' && /^http:\/\//i.test(resolvedUrl || '')) {
            showToast('Flux HTTP bloque sur page HTTPS. Configurez un flux HTTPS.');
            return;
        }

        if (errorName === 'NotAllowedError') {
            showToast('Lecture bloquee par le navigateur. Cliquez a nouveau.');
            return;
        }

        if (errorName === 'NotSupportedError') {
            showToast('Flux non supporte. Verifiez l URL du stream.');
            return;
        }

        showToast('Impossible de demarrer le direct.');
    }

    async function fetchRadioMetadata() {
        try {
            const response = await fetch(`${window.TCHADOK?.SITE_URL || ''}/api/radio/metadata.php`);
            const payload = await response.json();
            if (payload?.success && payload?.station?.stream_url) {
                streamUrl = normalizeStreamUrl(payload.station.stream_url);
            }
        } catch (error) {
            streamUrl = null;
        }
    }

    async function toggleLive() {
        if (!streamUrl) {
            showToast('Initialisation du flux en cours. Cliquez a nouveau dans un instant.');
            fetchRadioMetadata();
            return;
        }

        const resolvedUrl = resolveStreamUrl(streamUrl);
        if (!resolvedUrl) {
            showToast('Le flux radio est indisponible pour le moment.');
            return;
        }

        if (!liveAudio) {
            liveAudio = new Audio();
            liveAudio.src = resolvedUrl;
            liveAudio.preload = 'none';
            if (volumeSlider) {
                const volume = parseInt(volumeSlider.value, 10) || 75;
                liveAudio.volume = Math.max(0, Math.min(1, volume / 100));
            }
            liveAudio.addEventListener('play', () => updateLiveState(true));
            liveAudio.addEventListener('pause', () => updateLiveState(false));
            liveAudio.addEventListener('error', () => {
                updateLiveState(false);
                showToast('Erreur de lecture du direct.');
            });
        } else if (liveAudio.src !== resolvedUrl) {
            liveAudio.src = resolvedUrl;
        }

        if (isLivePlaying) {
            liveAudio.pause();
        } else {
            try {
                await liveAudio.play();
                updateLiveState(true);
            } catch (error) {
                handleLivePlayError(error, resolvedUrl);
            }
        }
    }

    function updateLiveState(isPlaying) {
        isLivePlaying = isPlaying;
        liveButtons.forEach((btn) => btn.setAttribute('aria-pressed', String(isPlaying)));
        if (liveIcon) {
            liveIcon.classList.toggle('fa-play', !isPlaying);
            liveIcon.classList.toggle('fa-pause', isPlaying);
        }
        if (radioVisual) {
            radioVisual.classList.toggle('is-playing', isPlaying);
        }
    }

    liveButtons.forEach((btn) => btn.addEventListener('click', toggleLive));

    function updateVolume(value) {
        if (!volumeSlider || !volumeDisplay) return;
        const volume = Math.max(0, Math.min(100, parseInt(value, 10) || 0));
        volumeDisplay.textContent = `${volume}%`;
        volumeSlider.style.setProperty('--volume', `${volume}%`);
        if (liveAudio) {
            liveAudio.volume = volume / 100;
        }
    }

    if (volumeSlider) {
        volumeSlider.addEventListener('input', () => updateVolume(volumeSlider.value));
        updateVolume(volumeSlider.value);
    }

    const tabButtons = Array.from(document.querySelectorAll('[data-tab-target]'));
    const tabPanels = Array.from(document.querySelectorAll('[data-tab-panel]'));

    function activateTab(target) {
        tabButtons.forEach((btn) => {
            btn.classList.toggle('is-active', btn.dataset.tabTarget === target);
        });
        tabPanels.forEach((panel) => {
            panel.classList.toggle('hidden', panel.dataset.tabPanel !== target);
        });
    }

    tabButtons.forEach((btn) => {
        btn.addEventListener('click', () => activateTab(btn.dataset.tabTarget));
    });

    document.addEventListener('click', (event) => {
        const actionBtn = event.target.closest('[data-action]');
        if (!actionBtn) return;
        const action = actionBtn.dataset.action;

        if (action === 'remind') {
            const isActive = actionBtn.classList.toggle('is-active');
            actionBtn.innerHTML = isActive ? '<i class="fas fa-check"></i> Programme' : '<i class="fas fa-bell"></i> Rappel';
            showToast(isActive ? 'Rappel programme' : 'Rappel annule');
        }

        if (action === 'podcast') {
            const title = actionBtn.dataset.podcastTitle || 'Podcast';
            showToast(`Lecture du podcast "${title}"`);
        }

        if (action === 'category') {
            const category = actionBtn.dataset.category || '';
            showToast(`Filtrage par categorie: ${category}`);
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

    fetchRadioMetadata();
})();
