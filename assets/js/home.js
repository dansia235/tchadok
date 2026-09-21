/* Home page interactions - Tailwind migration */
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

    document.querySelectorAll('a[href^="#"]').forEach((link) => {
        link.addEventListener('click', (event) => {
            const targetId = link.getAttribute('href');
            if (!targetId || targetId === '#') return;
            const target = document.querySelector(targetId);
            if (target) {
                event.preventDefault();
                const y = target.getBoundingClientRect().top + window.pageYOffset - 88;
                window.scrollTo({ top: y, behavior: 'smooth' });
                if (navMenu && !navMenu.classList.contains('hidden')) {
                    navMenu.classList.add('hidden');
                    navToggle?.setAttribute('aria-expanded', 'false');
                }
            }
        });
    });

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

    const radioToggle = document.getElementById('radioToggle');
    const radioIcon = document.getElementById('radioPlayIcon');
    let radioAudio = null;
    let radioStreamUrl = null;
    let radioPlaying = false;

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

    function handleRadioPlayError(error, resolvedUrl) {
        const errorName = error?.name || '';

        if (window.location.protocol === 'https:' && /^http:\/\//i.test(resolvedUrl || '')) {
            showToast('Flux HTTP bloque sur page HTTPS. Configurez un flux HTTPS.', 'warning');
            return;
        }

        if (errorName === 'NotAllowedError') {
            showToast('Lecture bloquee par le navigateur. Cliquez a nouveau.', 'warning');
            return;
        }

        if (errorName === 'NotSupportedError') {
            showToast('Flux non supporte. Verifiez l URL du stream.', 'error');
            return;
        }

        showToast('Impossible de demarrer le direct', 'error');
    }

    async function fetchRadioMetadata() {
        try {
            const response = await fetch(`${window.TCHADOK?.SITE_URL || ''}/api/radio/metadata.php`);
            const payload = await response.json();
            if (payload?.success && payload?.station?.stream_url) {
                radioStreamUrl = normalizeStreamUrl(payload.station.stream_url);
            }
        } catch (error) {
            radioStreamUrl = null;
        }
    }

    async function toggleRadio() {
        if (!radioStreamUrl) {
            showToast('Initialisation du flux en cours. Cliquez a nouveau dans un instant.', 'info');
            fetchRadioMetadata();
            return;
        }

        const resolvedUrl = resolveStreamUrl(radioStreamUrl);
        if (!resolvedUrl) {
            showToast('Flux radio indisponible pour le moment', 'warning');
            return;
        }

        if (!radioAudio) {
            radioAudio = new Audio();
            radioAudio.src = resolvedUrl;
            radioAudio.preload = 'none';
            radioAudio.volume = 0.8;
            radioAudio.addEventListener('play', () => updateRadioState(true));
            radioAudio.addEventListener('pause', () => updateRadioState(false));
            radioAudio.addEventListener('error', () => {
                updateRadioState(false);
                showToast('Erreur de lecture du direct', 'error');
            });
        } else if (radioAudio.src !== resolvedUrl) {
            radioAudio.src = resolvedUrl;
        }

        if (radioPlaying) {
            radioAudio.pause();
        } else {
            try {
                await radioAudio.play();
                updateRadioState(true);
            } catch (error) {
                handleRadioPlayError(error, resolvedUrl);
            }
        }
    }

    function updateRadioState(isPlaying) {
        radioPlaying = isPlaying;
        if (radioToggle) {
            radioToggle.setAttribute('data-playing', String(isPlaying));
            radioToggle.setAttribute('aria-pressed', String(isPlaying));
        }
        if (radioIcon) {
            radioIcon.classList.toggle('fa-play', !isPlaying);
            radioIcon.classList.toggle('fa-pause', isPlaying);
        }
    }

    if (radioToggle) {
        radioToggle.addEventListener('click', () => {
            toggleRadio();
        });
    }

    document.addEventListener('click', (event) => {
        const playButton = event.target.closest('[data-play-track]');
        if (playButton) {
            const trackId = parseInt(playButton.dataset.playTrack, 10);
            if (!Number.isNaN(trackId)) {
                window.playTrack?.(trackId);
            }
        }

        const radioShortcut = event.target.closest('[data-radio-play]');
        if (radioShortcut) {
            event.preventDefault();
            toggleRadio();
        }
    });

    document.querySelectorAll('.btn-close').forEach((btn) => {
        btn.addEventListener('click', () => {
            const alert = btn.closest('.alert');
            if (alert) {
                alert.remove();
            }
        });
    });

    function showToast(message, type = 'info') {
        const tones = {
            success: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-200',
            error: 'border-rose-500/30 bg-rose-500/10 text-rose-200',
            warning: 'border-amber-500/30 bg-amber-500/10 text-amber-200',
            info: 'border-white/10 bg-surface/90 text-text'
        };

        const toast = document.createElement('div');
        toast.className = `fixed right-4 top-4 z-50 max-w-xs rounded-2xl border px-4 py-3 text-sm shadow-elev-2 transition ${tones[type] || tones.info}`;
        toast.textContent = message;

        document.body.appendChild(toast);

        setTimeout(() => {
            toast.classList.add('opacity-0', 'translate-y-2');
        }, 2200);

        setTimeout(() => {
            toast.remove();
        }, 2600);
    }

    if (!window.showNotification) {
        window.showNotification = (message) => showToast(message, 'info');
    }

    window.toggleRadio = toggleRadio;
    window.playRadio = toggleRadio;

    fetchRadioMetadata();
})();
