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
     * Jouer un titre (version démo)
     */
    window.playTrack = function(trackId, playlist = null) {
        try {
            // Version démo - affiche une notification
            showNotification(`🎵 Lecture du titre #${trackId}<br><small>Fonctionnalité de lecture en cours de développement</small>`, 'info', 3000);
            
            // Simulation d'un lecteur audio
            console.log(`🎵 Playing track ${trackId}`);
            
            // Si pas connecté, proposer la connexion
            if (!window.TCHADOK || !TCHADOK.IS_LOGGED_IN) {
                setTimeout(() => {
                    if (confirm('Connectez-vous pour accéder à toutes les fonctionnalités !')) {
                        window.location.href = `${TCHADOK.SITE_URL}/login-new.php`;
                    }
                }, 1000);
                return;
            }
            
        } catch (error) {
            console.error('Erreur dans playTrack:', error);
            showNotification('Erreur lors de la lecture', 'error');
        }
    };
    
    /**
     * Ajouter/Retirer des favoris (version démo)
     */
    window.toggleFavorite = function(itemId, type = 'track') {
        try {
            const isFavorite = Math.random() > 0.5; // Simulation
            const message = isFavorite 
                ? `❤️ Ajouté aux favoris`
                : `💔 Retiré des favoris`;
            
            showNotification(message, 'success', 2000);
            
            // Mettre à jour l'icône si elle existe
            const button = document.querySelector(`[onclick*="toggleFavorite(${itemId}"]`);
            if (button) {
                const icon = button.querySelector('i');
                if (icon) {
                    icon.className = isFavorite ? 'fas fa-heart text-danger' : 'fas fa-heart';
                }
            }
            
        } catch (error) {
            console.error('Erreur dans toggleFavorite:', error);
            showNotification('Erreur lors de l\'ajout aux favoris', 'error');
        }
    };
    
    /**
     * Ajouter à une playlist (version démo)
     */
    window.addToPlaylist = function(itemId) {
        try {
            const playlists = ['Ma Playlist', 'Favoris Tchadiens', 'Découvertes', 'Workout'];
            const randomPlaylist = playlists[Math.floor(Math.random() * playlists.length)];
            
            showNotification(`📝 Ajouté à "${randomPlaylist}"<br><small>Fonctionnalité de playlist en cours de développement</small>`, 'success', 3000);
            
        } catch (error) {
            console.error('Erreur dans addToPlaylist:', error);
            showNotification('Erreur lors de l\'ajout à la playlist', 'error');
        }
    };
    
    /**
     * Télécharger un titre (version démo)
     */
    window.downloadTrack = function(trackId) {
        try {
            // Vérifier si l'utilisateur est connecté
            if (!window.TCHADOK || !TCHADOK.IS_LOGGED_IN) {
                showNotification('⚠️ Connexion requise<br><small>Connectez-vous pour télécharger</small>', 'warning', 3000);
                setTimeout(() => {
                    if (confirm('Connectez-vous pour télécharger ce titre !')) {
                        window.location.href = `${TCHADOK.SITE_URL}/login-new.php`;
                    }
                }, 1500);
                return;
            }
            
            // Simulation d'un achat/téléchargement
            showNotification(`💰 Achat en cours...<br><small>Titre #${trackId}</small>`, 'info', 2000);
            
            setTimeout(() => {
                const success = Math.random() > 0.3; // 70% de chance de succès
                if (success) {
                    showNotification(`✅ Achat réussi !<br><small>Téléchargement disponible dans "Mes Achats"</small>`, 'success', 4000);
                } else {
                    showNotification(`❌ Échec du paiement<br><small>Vérifiez votre solde ou méthode de paiement</small>`, 'error', 3000);
                }
            }, 2500);
            
        } catch (error) {
            console.error('Erreur dans downloadTrack:', error);
            showNotification('Erreur lors du téléchargement', 'error');
        }
    };
    
    /**
     * Charger un titre dans le lecteur
     */
    function loadTrack(track) {
        currentTrack = track;
        
        // Arrêter le titre précédent
        if (currentAudio) {
            currentAudio.pause();
            currentAudio = null;
        }
        
        // Créer le nouvel élément audio
        currentAudio = new Audio();
        currentAudio.src = `${TCHADOK.SITE_URL}/${track.audio_file}`;
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
        if (loginModal) {
            const modal = new bootstrap.Modal(loginModal);
            modal.show();
        } else {
            window.location.href = `${TCHADOK.SITE_URL}/login.php`;
        }
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