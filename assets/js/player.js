/*!
 * Tchadok Platform - Audio Player Module
 * Module de lecteur audio pour la plateforme Tchadok
 * Version 1.0
 */

(function() {
    'use strict';
    
    // Variables du lecteur
    let audioPlayer = null;
    let currentTrackData = null;
    let isPlayerReady = false;
    let playbackPosition = 0;
    
    /**
     * Initialisation du module player
     */
    function initializePlayer() {
        console.log('🎵 Audio Player Module initialized');
        
        // Créer l'interface du lecteur si elle n'existe pas
        createPlayerInterface();
        
        // Initialiser les événements
        initializePlayerEvents();
        
        isPlayerReady = true;
    }
    
    /**
     * Créer l'interface du lecteur
     */
    function createPlayerInterface() {
        const existingPlayer = document.querySelector('.audio-player');
        if (existingPlayer) return;
        
        const playerHTML = `
            <div class="audio-player fixed-bottom bg-dark text-white p-3" style="display: none; z-index: 1050;">
                <div class="container-fluid">
                    <div class="row align-items-center">
                        <div class="col-md-3">
                            <div class="d-flex align-items-center">
                                <img src="" alt="" class="current-track-image rounded me-3" style="width: 50px; height: 50px; object-fit: cover;">
                                <div>
                                    <div class="current-track-title fw-bold mb-0 small">Aucun titre</div>
                                    <div class="current-track-artist text-muted small">Aucun artiste</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex justify-content-center align-items-center mb-2">
                                <button class="btn btn-link text-white me-2 control-btn" data-action="previous">
                                    <i class="fas fa-step-backward"></i>
                                </button>
                                <button class="btn btn-light rounded-circle me-2 control-btn" data-action="play-pause" style="width: 40px; height: 40px;">
                                    <i class="fas fa-play"></i>
                                </button>
                                <button class="btn btn-link text-white control-btn" data-action="next">
                                    <i class="fas fa-step-forward"></i>
                                </button>
                            </div>
                            <div class="progress" style="height: 4px; cursor: pointer;" id="progressBar">
                                <div class="progress-bar progress-fill" style="width: 0%;"></div>
                            </div>
                            <div class="d-flex justify-content-between small text-muted mt-1">
                                <span class="current-time">0:00</span>
                                <span class="duration">0:00</span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="d-flex justify-content-end align-items-center">
                                <button class="btn btn-link text-white me-2 control-btn" data-action="volume">
                                    <i class="fas fa-volume-up"></i>
                                </button>
                                <button class="btn btn-link text-white control-btn" data-action="close">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', playerHTML);
    }
    
    /**
     * Initialiser les événements du lecteur
     */
    function initializePlayerEvents() {
        // Événement de clic sur la barre de progression
        const progressBar = document.getElementById('progressBar');
        if (progressBar) {
            progressBar.addEventListener('click', function(e) {
                if (audioPlayer && audioPlayer.duration) {
                    const rect = this.getBoundingClientRect();
                    const percentage = (e.clientX - rect.left) / rect.width;
                    const newTime = percentage * audioPlayer.duration;
                    audioPlayer.currentTime = newTime;
                }
            });
        }
    }
    
    /**
     * Charger et jouer un titre
     */
    window.loadAndPlayTrack = function(trackData) {
        if (!trackData || !trackData.audio_file) {
            console.error('Invalid track data');
            return;
        }
        
        currentTrackData = trackData;
        
        // Arrêter le titre précédent
        if (audioPlayer) {
            audioPlayer.pause();
            audioPlayer = null;
        }
        
        // Créer un nouveau lecteur audio
        audioPlayer = new Audio();
        audioPlayer.src = trackData.audio_file.startsWith('http') ? 
            trackData.audio_file : 
            `${window.TCHADOK?.SITE_URL || ''}/${trackData.audio_file}`;
        
        // Événements audio
        audioPlayer.addEventListener('loadedmetadata', updatePlayerMetadata);
        audioPlayer.addEventListener('timeupdate', updateProgress);
        audioPlayer.addEventListener('ended', onTrackEnded);
        audioPlayer.addEventListener('error', onAudioError);
        audioPlayer.addEventListener('canplay', function() {
            console.log('Audio ready to play');
        });
        
        // Mettre à jour l'interface
        updatePlayerInterface();
        showPlayer();
        
        // Jouer automatiquement
        audioPlayer.play().then(() => {
            console.log('Playback started');
            updatePlayButton(true);
        }).catch(error => {
            console.error('Playback failed:', error);
            if (window.showNotification) {
                window.showNotification('Impossible de lire ce titre', 'error');
            }
        });
    };
    
    /**
     * Mettre à jour les métadonnées du lecteur
     */
    function updatePlayerMetadata() {
        const durationEl = document.querySelector('.duration');
        if (durationEl && audioPlayer) {
            durationEl.textContent = formatTime(audioPlayer.duration);
        }
    }
    
    /**
     * Mettre à jour l'interface du lecteur
     */
    function updatePlayerInterface() {
        if (!currentTrackData) return;
        
        const trackImage = document.querySelector('.current-track-image');
        const trackTitle = document.querySelector('.current-track-title');
        const trackArtist = document.querySelector('.current-track-artist');
        
        if (trackImage) {
            const imageUrl = currentTrackData.album_cover || 'assets/images/default-cover.jpg';
            trackImage.src = imageUrl.startsWith('http') ? 
                imageUrl : 
                `${window.TCHADOK?.SITE_URL || ''}/${imageUrl}`;
            trackImage.alt = currentTrackData.title || 'Titre inconnu';
        }
        
        if (trackTitle) {
            trackTitle.textContent = currentTrackData.title || 'Titre inconnu';
        }
        
        if (trackArtist) {
            trackArtist.textContent = currentTrackData.artist_name || 'Artiste inconnu';
        }
    }
    
    /**
     * Mettre à jour la barre de progression
     */
    function updateProgress() {
        if (!audioPlayer) return;
        
        const progress = (audioPlayer.currentTime / audioPlayer.duration) * 100;
        const progressFill = document.querySelector('.progress-fill');
        const currentTimeEl = document.querySelector('.current-time');
        
        if (progressFill) {
            progressFill.style.width = `${progress || 0}%`;
        }
        
        if (currentTimeEl) {
            currentTimeEl.textContent = formatTime(audioPlayer.currentTime);
        }
    }
    
    /**
     * Mettre à jour le bouton de lecture
     */
    function updatePlayButton(isPlaying) {
        const playPauseBtn = document.querySelector('.control-btn[data-action="play-pause"] i');
        if (playPauseBtn) {
            playPauseBtn.className = isPlaying ? 'fas fa-pause' : 'fas fa-play';
        }
    }
    
    /**
     * Basculer lecture/pause
     */
    window.togglePlayPause = function() {
        if (!audioPlayer) return;
        
        if (audioPlayer.paused) {
            audioPlayer.play().then(() => {
                updatePlayButton(true);
            }).catch(error => {
                console.error('Play failed:', error);
            });
        } else {
            audioPlayer.pause();
            updatePlayButton(false);
        }
    };
    
    /**
     * Afficher le lecteur
     */
    function showPlayer() {
        const player = document.querySelector('.audio-player');
        if (player) {
            player.style.display = 'block';
            document.body.style.paddingBottom = '120px';
        }
    }
    
    /**
     * Masquer le lecteur
     */
    window.hidePlayer = function() {
        if (audioPlayer) {
            audioPlayer.pause();
            audioPlayer = null;
        }
        
        const player = document.querySelector('.audio-player');
        if (player) {
            player.style.display = 'none';
            document.body.style.paddingBottom = '0';
        }
        
        currentTrackData = null;
    };
    
    /**
     * Gérer la fin du titre
     */
    function onTrackEnded() {
        updatePlayButton(false);
        console.log('Track ended');
        
        // Ici on pourrait ajouter la logique pour le titre suivant
        if (window.nextTrack && typeof window.nextTrack === 'function') {
            window.nextTrack();
        }
    }
    
    /**
     * Gérer les erreurs audio
     */
    function onAudioError(e) {
        console.error('Audio error:', e);
        updatePlayButton(false);
        
        if (window.showNotification) {
            window.showNotification('Erreur de lecture audio', 'error');
        }
    }
    
    /**
     * Formater le temps en mm:ss
     */
    function formatTime(seconds) {
        if (isNaN(seconds) || !isFinite(seconds)) return '0:00';
        
        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = Math.floor(seconds % 60);
        return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
    }
    
    /**
     * Compatibilité avec la fonction playTrack existante
     */
    window.playTrack = function(trackId, playlist = null) {
        console.log('playTrack called with:', trackId);
        
        // Si on a une fonction TCHADOK globale, l'utiliser
        if (window.Tchadok && window.Tchadok.playTrack) {
            return window.Tchadok.playTrack(trackId, playlist);
        }
        
        // Sinon, implémentation basique
        if (!window.TCHADOK?.IS_LOGGED_IN) {
            if (window.showNotification) {
                window.showNotification('Veuillez vous connecter pour écouter de la musique', 'warning');
            }
            return;
        }
        
        // Simuler le chargement des données du titre
        const trackData = {
            id: trackId,
            title: `Titre ${trackId}`,
            artist_name: 'Artiste inconnu',
            audio_file: `uploads/audio/track_${trackId}.mp3`,
            album_cover: 'assets/images/default-cover.jpg'
        };
        
        window.loadAndPlayTrack(trackData);
    };
    
    // Initialiser le module quand le DOM est prêt
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializePlayer);
    } else {
        initializePlayer();
    }
    
    // Export des fonctions publiques
    window.PlayerModule = {
        loadAndPlayTrack: window.loadAndPlayTrack,
        togglePlayPause: window.togglePlayPause,
        hidePlayer: window.hidePlayer,
        playTrack: window.playTrack
    };
    
})();