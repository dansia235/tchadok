/*!
 * Tchadok Platform - Main JavaScript
 * La plateforme musicale de référence du Tchad
 * Version 1.0
 */

(function() {
    'use strict';
    
    // Configuration globale
    const TCHADOK = window.TCHADOK || {};
    
    // Variables globales
    let currentTrack = null;
    let isPlaying = false;
    let currentAudio = null;
    let currentPlaylist = [];
    let currentTrackIndex = 0;
    
    // Initialisation avec gestion d'erreurs
    document.addEventListener('DOMContentLoaded', function() {
        try {
            initializeApp();
            initializeAudioPlayer();
            initializeCookieConsent();
            initializeScrollToTop();
            initializeTooltips();
            initializeNotifications();
            initializePWA();
        } catch (error) {
            console.warn('Erreur lors de l\'initialisation:', error);
            // S'assurer que le loader disparaît même en cas d'erreur
            const loader = document.getElementById('pageLoader');
            if (loader) {
                loader.style.display = 'none';
            }
        }
    });
    
    /**
     * Initialisation principale de l'application
     */
    function initializeApp() {
        console.log('🎵 Tchadok Platform v' + (window.APP_VERSION || '1.0') + ' initialized');
        
        // Gestion des erreurs JavaScript
        window.addEventListener('error', function(e) {
            console.error('JavaScript Error:', e.error);
            showNotification('Une erreur est survenue', 'error');
        });
        
        // Gestion des erreurs de promesse non capturées
        window.addEventListener('unhandledrejection', function(e) {
            console.error('Unhandled Promise Rejection:', e.reason);
            e.preventDefault();
        });
        
        // Chargement des préférences utilisateur
        loadUserPreferences();
        
        // Initialisation des composants
        initializeForms();
        initializeSearch();
    }
    
    /**
     * Initialisation du lecteur audio
     */
    function initializeAudioPlayer() {
        // Vérification du support audio
        if (!window.Audio) {
            console.warn('Audio not supported in this browser');
            return;
        }
        
        // Événements du lecteur
        document.addEventListener('click', function(e) {
            if (e.target.closest('.play-btn') || e.target.closest('[data-play-track]')) {
                e.preventDefault();
                const trackId = e.target.closest('[data-play-track]')?.dataset.playTrack || 
                              e.target.closest('.play-btn')?.dataset.trackId;
                if (trackId) {
                    playTrack(parseInt(trackId));
                }
            }
            
            if (e.target.closest('.control-btn')) {
                e.preventDefault();
                handlePlayerControl(e.target.closest('.control-btn'));
            }
        });
        
        // Contrôles clavier
        document.addEventListener('keydown', function(e) {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
            
            switch(e.code) {
                case 'Space':
                    e.preventDefault();
                    togglePlayPause();
                    break;
                case 'ArrowRight':
                    if (e.ctrlKey) {
                        e.preventDefault();
                        nextTrack();
                    }
                    break;
                case 'ArrowLeft':
                    if (e.ctrlKey) {
                        e.preventDefault();
                        previousTrack();
                    }
                    break;
            }
        });
    }
    
    /**
     * Jouer un titre (version dynamique)
     */
    window.playTrack = async function(trackId, playlist = null) {
        try {
            const id = parseInt(trackId, 10);
            if (Number.isNaN(id)) return;

            if (Array.isArray(playlist)) {
                currentPlaylist = playlist;
                currentTrackIndex = playlist.findIndex((item) => (typeof item === 'object' ? item.id : item) == id);
                if (currentTrackIndex < 0) currentTrackIndex = 0;
            } else {
                currentPlaylist = [];
                currentTrackIndex = 0;
            }

            const track = await fetchTrackData(id, 'resolve');
            loadTrack(track);
        } catch (error) {
            console.error('Erreur dans playTrack:', error);
            showNotification(error.message || 'Erreur lors de la lecture', 'error');
        }
    };
    
    /**
     * Ajouter/Retirer des favoris (version dynamique)
     */
    window.toggleFavorite = async function(itemId, type = 'track') {
        try {
            if (!window.TCHADOK || !TCHADOK.IS_LOGGED_IN) {
                showNotification('Connectez-vous pour utiliser les favoris', 'warning', 2500);
                return;
            }

            const response = await fetch(`${TCHADOK.SITE_URL}/api/playlists.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': TCHADOK.CSRF_TOKEN
                },
                body: JSON.stringify({
                    action: 'toggle_favorite',
                    item_id: itemId,
                    item_type: type
                })
            });
            const payload = await response.json();
            const isFavorite = payload?.data?.is_favorite;

            showNotification(isFavorite ? 'Ajouté aux favoris' : 'Retiré des favoris', 'success', 2000);

            const button = document.querySelector(`[data-favorite-id="${itemId}"]`);
            if (button) {
                const icon = button.querySelector('i');
                if (icon) {
                    icon.className = isFavorite ? 'fas fa-heart text-danger' : 'far fa-heart';
                }
            }
        } catch (error) {
            console.error('Erreur dans toggleFavorite:', error);
            showNotification('Erreur lors de l\'ajout aux favoris', 'error');
        }
    };
    
    /**
     * Ajouter à une playlist (version dynamique)
     */
    window.addToPlaylist = async function(itemId) {
        try {
            if (!window.TCHADOK || !TCHADOK.IS_LOGGED_IN) {
                showNotification('Connectez-vous pour gerer vos playlists', 'warning', 2500);
                return;
            }

            const listResponse = await fetch(`${TCHADOK.SITE_URL}/api/playlists.php?action=list`);
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

            await fetch(`${TCHADOK.SITE_URL}/api/playlists.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': TCHADOK.CSRF_TOKEN
                },
                body: JSON.stringify({
                    action: 'add_track',
                    playlist_id: playlistId,
                    track_id: itemId
                })
            });

            showNotification('Titre ajouté à la playlist', 'success', 2500);
        } catch (error) {
            console.error('Erreur dans addToPlaylist:', error);
            showNotification('Erreur lors de l\'ajout à la playlist', 'error');
        }
    };
    
    /**
     * Télécharger un titre (version dynamique)
     */
    window.downloadTrack = async function(trackId) {
        try {
            if (!window.TCHADOK || !TCHADOK.IS_LOGGED_IN) {
                showNotification('Connexion requise pour télécharger', 'warning', 3000);
                return;
            }

            const track = await fetchTrackData(trackId, 'get');

            // SEC-06 : le serveur decide. stream_url n'est fourni que si
            // l'acces complet est accorde ; aucun chemin brut n'est expose.
            // Les quotas de telechargement arriveront avec SHOP-04/05.
            if (track.access === 'full' && track.stream_url) {
                window.open(track.stream_url, '_blank');
                return;
            }

            showNotification(track.access_message || 'Achat requis pour télécharger ce titre', 'warning', 3000);
        } catch (error) {
            console.error('Erreur dans downloadTrack:', error);
            showNotification('Erreur lors du téléchargement', 'error');
        }
    };
    
    /**
     * Charger un titre dans le lecteur
     */
    async function fetchTrackData(trackId, mode = 'resolve') {
        const endpoint = `${TCHADOK.SITE_URL}/api/track.php?action=${encodeURIComponent(mode)}&id=${encodeURIComponent(trackId)}`;
        const response = await fetch(endpoint);
        const payload = await response.json();
        if (!payload.success || !payload.data) {
            throw new Error(payload.error?.message || 'Titre introuvable');
        }
        return payload.data;
    }

    async function createPlaylist(name) {
        const response = await fetch(`${TCHADOK.SITE_URL}/api/playlists.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': TCHADOK.CSRF_TOKEN
            },
            body: JSON.stringify({ action: 'create', name })
        });
        const payload = await response.json();
        if (!payload.success) {
            throw new Error(payload.error?.message || 'Erreur');
        }
        return payload.data?.playlist_id;
    }

    /**
     * Source lisible, decidee par le SERVEUR (SEC-06).
     * Voir le meme commentaire dans assets/js/player.js.
     */
    function getPlayableSource(track) {
        if (!track) return null;

        if (track.access === 'full' && track.stream_url) {
            return track.stream_url;
        }
        if (track.preview_url) {
            if (track.access_message) {
                showNotification(track.access_message + ' Extrait de 30 secondes.', 'info');
            }
            return track.preview_url;
        }
        showNotification(track.access_message || 'Titre non disponible a l\'ecoute.', 'warning');
        return null;
    }

    function loadTrack(track) {
        currentTrack = track;
        
        // Arrêter le titre précédent
        if (currentAudio) {
            currentAudio.pause();
            currentAudio = null;
        }
        
        // Créer le nouvel élément audio
        currentAudio = new Audio();
        const source = getPlayableSource(track);
        if (!source) {
            currentAudio = null;
            return;
        }
        // URL deja absolue et signee par le serveur.
        currentAudio.src = source;
        currentAudio.preload = 'metadata';
        
        // Événements audio
        currentAudio.addEventListener('loadedmetadata', updatePlayerInfo);
        currentAudio.addEventListener('timeupdate', updateProgress);
        currentAudio.addEventListener('ended', onTrackEnded);
        currentAudio.addEventListener('error', onAudioError);
        
        // Mettre à jour l'interface
        updatePlayerInterface();
        showAudioPlayer();
        
        // Enregistrer l'écoute
        recordStream(track.id);
        
        // Jouer automatiquement
        currentAudio.play().then(() => {
            isPlaying = true;
            updatePlayButton();
        }).catch(error => {
            console.error('Playback failed:', error);
            showNotification('Impossible de lire ce titre', 'error');
        });
    }
    
    /**
     * Basculer lecture/pause
     */
    function togglePlayPause() {
        if (!currentAudio) return;
        
        if (isPlaying) {
            currentAudio.pause();
            isPlaying = false;
        } else {
            currentAudio.play().then(() => {
                isPlaying = true;
            }).catch(error => {
                console.error('Playback failed:', error);
                showNotification('Erreur de lecture', 'error');
            });
        }
        
        updatePlayButton();
    }
    
    /**
     * Titre suivant
     */
    function nextTrack() {
        if (currentPlaylist.length === 0) return;
        
        currentTrackIndex = (currentTrackIndex + 1) % currentPlaylist.length;
        playTrack(currentPlaylist[currentTrackIndex].id, currentPlaylist);
    }
    
    /**
     * Titre précédent
     */
    function previousTrack() {
        if (currentPlaylist.length === 0) return;
        
        currentTrackIndex = currentTrackIndex > 0 ? currentTrackIndex - 1 : currentPlaylist.length - 1;
        playTrack(currentPlaylist[currentTrackIndex].id, currentPlaylist);
    }
    
    /**
     * Gérer les contrôles du lecteur
     */
    function handlePlayerControl(button) {
        const action = button.dataset.action;
        
        switch(action) {
            case 'play-pause':
                togglePlayPause();
                break;
            case 'previous':
                previousTrack();
                break;
            case 'next':
                nextTrack();
                break;
            case 'volume':
                toggleMute();
                break;
            case 'close':
                closePlayer();
                break;
        }
    }
    
    /**
     * Mettre à jour l'interface du lecteur
     */
    function updatePlayerInterface() {
        if (!currentTrack) return;
        
        const playerElement = document.querySelector('.audio-player');
        if (!playerElement) return;
        
        // Mise à jour des informations du titre
        const trackImage = playerElement.querySelector('.current-track-image');
        const trackTitle = playerElement.querySelector('.current-track-title');
        const trackArtist = playerElement.querySelector('.current-track-artist');
        
        if (trackImage) {
            trackImage.src = `${TCHADOK.SITE_URL}/${currentTrack.album_cover || 'assets/images/default-cover.jpg'}`;
            trackImage.alt = currentTrack.title;
        }
        
        if (trackTitle) {
            trackTitle.textContent = currentTrack.title;
        }
        
        if (trackArtist) {
            trackArtist.textContent = currentTrack.artist_name;
        }
    }
    
    /**
     * Mettre à jour le bouton de lecture
     */
    function updatePlayButton() {
        const playButtons = document.querySelectorAll('.play-pause-btn, .control-btn[data-action="play-pause"]');
        playButtons.forEach(btn => {
            const icon = btn.querySelector('i');
            if (icon) {
                icon.className = isPlaying ? 'fas fa-pause' : 'fas fa-play';
            }
        });
    }
    
    /**
     * Mettre à jour la barre de progression
     */
    function updateProgress() {
        if (!currentAudio) return;
        
        const progress = (currentAudio.currentTime / currentAudio.duration) * 100;
        const progressFill = document.querySelector('.progress-fill');
        const currentTimeEl = document.querySelector('.current-time');
        const durationEl = document.querySelector('.duration');
        
        if (progressFill) {
            progressFill.style.width = `${progress}%`;
        }
        
        if (currentTimeEl) {
            currentTimeEl.textContent = formatTime(currentAudio.currentTime);
        }
        
        if (durationEl) {
            durationEl.textContent = formatTime(currentAudio.duration);
        }
    }
    
    /**
     * Afficher le lecteur audio
     */
    function showAudioPlayer() {
        const player = document.querySelector('.audio-player');
        if (player) {
            player.style.display = 'block';
            document.body.style.paddingBottom = '100px'; // Espace pour le lecteur fixe
        }
    }
    
    /**
     * Masquer le lecteur audio
     */
    function closePlayer() {
        if (currentAudio) {
            currentAudio.pause();
            currentAudio = null;
        }
        
        isPlaying = false;
        currentTrack = null;
        
        const player = document.querySelector('.audio-player');
        if (player) {
            player.style.display = 'none';
            document.body.style.paddingBottom = '0';
        }
    }
    
    /**
     * Enregistrer une écoute
     */
    function recordStream(trackId) {
        if (!TCHADOK.IS_LOGGED_IN) return;
        
        fetch(`${TCHADOK.SITE_URL}/api/stream.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': TCHADOK.CSRF_TOKEN
            },
            body: JSON.stringify({
                track_id: trackId,
                timestamp: Date.now()
            })
        }).catch(error => {
            console.error('Error recording stream:', error);
        });
    }
    
    /**
     * Initialisation du consentement cookies
     */
    function initializeCookieConsent() {
        const hasConsent = localStorage.getItem('cookieConsent');
        const consentBanner = document.getElementById('cookieConsent');
        
        if (!hasConsent && consentBanner) {
            consentBanner.style.display = 'block';
            
            document.getElementById('acceptCookies')?.addEventListener('click', function() {
                localStorage.setItem('cookieConsent', 'accepted');
                consentBanner.style.display = 'none';
            });
            
            document.getElementById('declineCookies')?.addEventListener('click', function() {
                localStorage.setItem('cookieConsent', 'declined');
                consentBanner.style.display = 'none';
            });
        }
    }
    
    /**
     * Initialisation du bouton de retour en haut
     */
    function initializeScrollToTop() {
        const scrollBtn = document.getElementById('scrollToTop');
        if (!scrollBtn) return;
        
        window.addEventListener('scroll', function() {
            if (window.pageYOffset > 300) {
                scrollBtn.style.display = 'block';
            } else {
                scrollBtn.style.display = 'none';
            }
        });
        
        scrollBtn.addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }
    
    /**
     * Initialisation des tooltips Bootstrap
     */
    function initializeTooltips() {
        try {
            if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                tooltipTriggerList.map(function (tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                });
            }
        } catch (error) {
            console.warn('Erreur lors de l\'initialisation des tooltips:', error);
        }
    }
    
    /**
     * Initialisation des formulaires
     */
    function initializeForms() {
        // Validation en temps réel
        const forms = document.querySelectorAll('.needs-validation');
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                if (!form.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                form.classList.add('was-validated');
            });
        });
        
        // Auto-resize des textareas
        const textareas = document.querySelectorAll('textarea[data-auto-resize]');
        textareas.forEach(textarea => {
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = this.scrollHeight + 'px';
            });
        });
    }
    
    /**
     * Initialisation de la recherche
     */
    function initializeSearch() {
        const searchInputs = document.querySelectorAll('.search-input');
        
        searchInputs.forEach(input => {
            let searchTimeout;
            
            input.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                const query = this.value.trim();
                
                if (query.length >= 3) {
                    searchTimeout = setTimeout(() => {
                        performSearch(query);
                    }, 300);
                }
            });
        });
    }
    
    /**
     * Effectuer une recherche
     */
    function performSearch(query) {
        fetch(`${TCHADOK.SITE_URL}/api/search.php?q=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displaySearchResults(data.results);
                }
            })
            .catch(error => {
                console.error('Search error:', error);
            });
    }
    
    /**
     * Afficher les résultats de recherche
     */
    function displaySearchResults(results) {
        // Implémentation des résultats de recherche en temps réel
        const resultsContainer = document.querySelector('.search-results');
        if (!resultsContainer) return;
        
        resultsContainer.innerHTML = '';
        
        if (results.tracks?.length > 0 || results.artists?.length > 0 || results.albums?.length > 0) {
            resultsContainer.style.display = 'block';
            
            // Afficher les résultats par catégorie
            ['tracks', 'artists', 'albums'].forEach(category => {
                if (results[category]?.length > 0) {
                    const categoryDiv = document.createElement('div');
                    categoryDiv.className = 'search-category mb-3';
                    categoryDiv.innerHTML = `<h6>${getCategoryTitle(category)}</h6>`;
                    
                    results[category].slice(0, 5).forEach(item => {
                        const itemDiv = document.createElement('div');
                        itemDiv.className = 'search-item p-2 border-bottom';
                        itemDiv.innerHTML = createSearchItemHTML(item, category);
                        categoryDiv.appendChild(itemDiv);
                    });
                    
                    resultsContainer.appendChild(categoryDiv);
                }
            });
        } else {
            resultsContainer.style.display = 'none';
        }
    }
    
    /**
     * Initialisation des notifications
     */
    function initializeNotifications() {
        // Vérifier les nouvelles notifications pour les utilisateurs connectés
        if (TCHADOK.IS_LOGGED_IN) {
            checkNotifications();
            setInterval(checkNotifications, 30000); // Vérifier toutes les 30 secondes
        }
    }
    
    /**
     * Vérifier les nouvelles notifications
     */
    function checkNotifications() {
        fetch(`${TCHADOK.SITE_URL}/api/notifications.php`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.notifications.length > 0) {
                    updateNotificationBadge(data.unread_count);
                }
            })
            .catch(error => {
                console.error('Notification check error:', error);
            });
    }
    
    /**
     * Mettre à jour le badge de notification
     */
    function updateNotificationBadge(count) {
        const badge = document.querySelector('.notification-badge');
        if (badge) {
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.style.display = 'inline-block';
            } else {
                badge.style.display = 'none';
            }
        }
    }
    
    /**
     * Afficher une notification toast
     */
    window.showNotification = function(message, type = 'info', duration = 5000) {
        const toast = document.getElementById('notificationToast');
        if (!toast) return;
        
        const toastBody = toast.querySelector('.toast-body');
        if (toastBody) {
            toastBody.innerHTML = message;
        }
        
        // Modifier la couleur selon le type
        toast.className = `toast ${getToastClass(type)}`;
        
        if (typeof bootstrap === 'undefined' || !bootstrap.Toast) {
            console.warn('Bootstrap Toast non disponible.');
            return;
        }

        const bsToast = new bootstrap.Toast(toast, {
            autohide: true,
            delay: duration
        });
        
        bsToast.show();
    };
    
    /**
     * Initialisation PWA
     */
    function initializePWA() {
        // Prompt d'installation PWA
        let deferredPrompt;
        
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            showInstallPromotion();
        });
        
        // Gérer l'installation
        function showInstallPromotion() {
            const installBtn = document.querySelector('.install-app-btn');
            if (installBtn) {
                installBtn.style.display = 'block';
                installBtn.addEventListener('click', async () => {
                    if (deferredPrompt) {
                        deferredPrompt.prompt();
                        const { outcome } = await deferredPrompt.userChoice;
                        console.log(`PWA install outcome: ${outcome}`);
                        deferredPrompt = null;
                    }
                });
            }
        }
    }
    
    /**
     * Charger les préférences utilisateur
     */
    function loadUserPreferences() {
        const prefs = JSON.parse(localStorage.getItem('tchadokPreferences') || '{}');
        
        // Appliquer le thème
        if (prefs.theme) {
            document.documentElement.setAttribute('data-theme', prefs.theme);
        }
        
        // Appliquer le volume
        if (prefs.volume !== undefined) {
            setVolume(prefs.volume);
        }
        
        // Autres préférences
        if (prefs.autoplay !== undefined) {
            window.autoplayEnabled = prefs.autoplay;
        }
    }
    
    /**
     * Sauvegarder les préférences utilisateur
     */
    window.saveUserPreference = function(key, value) {
        const prefs = JSON.parse(localStorage.getItem('tchadokPreferences') || '{}');
        prefs[key] = value;
        localStorage.setItem('tchadokPreferences', JSON.stringify(prefs));
    };
    
    /**
     * Utilitaires
     */
    function formatTime(seconds) {
        if (isNaN(seconds)) return '0:00';
        
        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = Math.floor(seconds % 60);
        return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
    }
    
    function getCategoryTitle(category) {
        const titles = {
            tracks: 'Titres',
            artists: 'Artistes',
            albums: 'Albums'
        };
        return titles[category] || category;
    }
    
    function getToastClass(type) {
        const classes = {
            success: 'border-success',
            error: 'border-danger',
            warning: 'border-warning',
            info: 'border-info'
        };
        return classes[type] || 'border-info';
    }
    
    function showLoadingSpinner() {
        const spinner = document.getElementById('pageLoader');
        if (spinner) {
            spinner.style.display = 'flex';
        }
    }
    
    function hideLoadingSpinner() {
        const spinner = document.getElementById('pageLoader');
        if (spinner) {
            spinner.style.display = 'none';
        }
    }
    
    function showLoginModal() {
        const loginModal = document.getElementById('loginModal');
        if (loginModal && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            const modal = new bootstrap.Modal(loginModal);
            modal.show();
            return;
        }

        window.location.href = `${TCHADOK.SITE_URL}/login.php`;
    }
    
    // Gestionnaires d'événements globaux
    window.addEventListener('online', function() {
        showNotification('Connexion rétablie', 'success');
    });
    
    window.addEventListener('offline', function() {
        showNotification('Connexion perdue - Mode hors ligne activé', 'warning');
    });
    
    // Export des fonctions publiques
    window.Tchadok = {
        playTrack: window.playTrack,
        toggleFavorite: window.toggleFavorite,
        addToPlaylist: window.addToPlaylist,
        downloadTrack: window.downloadTrack,
        showNotification: window.showNotification,
        saveUserPreference: window.saveUserPreference
    };
    
})();
