<?php
/**
 * Lecteur audio global - Tchadok Platform
 * Affiché uniquement si l'utilisateur écoute de la musique
 */

// Vérifier s'il y a une session de lecture active
$isPlaying = false;
$currentTrack = null;

// En développement, on peut simuler une session de lecture
if (isset($_SESSION['current_track_id']) && !empty($_SESSION['current_track_id'])) {
    $isPlaying = true;
    // Récupérer les informations du titre en cours (simulation)
    $currentTrack = [
        'id' => $_SESSION['current_track_id'],
        'title' => 'Titre en cours',
        'artist' => 'Artiste Tchadien',
        'album_cover' => 'assets/images/default-cover.jpg',
        'duration' => '3:45'
    ];
}
?>

<?php if ($isPlaying && $currentTrack): ?>
<!-- Lecteur Audio Fixe -->
<div id="audioPlayer" class="audio-player position-fixed bottom-0 start-0 end-0 bg-white border-top shadow-lg" style="z-index: 1040; height: 80px;">
    <div class="container-fluid h-100">
        <div class="row h-100 align-items-center">
            <!-- Info du titre -->
            <div class="col-md-3 col-4">
                <div class="d-flex align-items-center">
                    <img src="<?php echo SITE_URL; ?>/<?php echo $currentTrack['album_cover']; ?>" 
                         alt="<?php echo htmlspecialchars($currentTrack['title']); ?>" 
                         class="rounded me-3" 
                         style="width: 50px; height: 50px; object-fit: cover;">
                    <div class="d-none d-md-block">
                        <div class="fw-semibold text-truncate" style="max-width: 150px;">
                            <?php echo htmlspecialchars($currentTrack['title']); ?>
                        </div>
                        <div class="text-muted small text-truncate" style="max-width: 150px;">
                            <?php echo htmlspecialchars($currentTrack['artist']); ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Contrôles -->
            <div class="col-md-6 col-4 text-center">
                <div class="d-flex align-items-center justify-content-center gap-3">
                    <button class="btn btn-link text-dark p-1" id="prevBtn" title="Précédent">
                        <i class="fas fa-step-backward"></i>
                    </button>
                    
                    <button class="btn btn-primary rounded-circle p-2" id="playPauseBtn" title="Lecture/Pause">
                        <i class="fas fa-pause"></i>
                    </button>
                    
                    <button class="btn btn-link text-dark p-1" id="nextBtn" title="Suivant">
                        <i class="fas fa-step-forward"></i>
                    </button>
                </div>
                
                <!-- Barre de progression -->
                <div class="progress mt-2" style="height: 4px;">
                    <div class="progress-bar bg-primary" role="progressbar" style="width: 45%"></div>
                </div>
                
                <!-- Temps -->
                <div class="d-flex justify-content-between small text-muted mt-1">
                    <span id="currentTime">1:32</span>
                    <span id="totalTime"><?php echo $currentTrack['duration']; ?></span>
                </div>
            </div>
            
            <!-- Actions et volume -->
            <div class="col-md-3 col-4">
                <div class="d-flex align-items-center justify-content-end gap-2">
                    <button class="btn btn-link text-dark p-1 d-none d-md-inline" id="favoriteBtn" title="Favoris">
                        <i class="fas fa-heart"></i>
                    </button>
                    
                    <button class="btn btn-link text-dark p-1 d-none d-md-inline" id="volumeBtn" title="Volume">
                        <i class="fas fa-volume-up"></i>
                    </button>
                    
                    <div class="d-none d-lg-block" style="width: 80px;">
                        <input type="range" class="form-range" min="0" max="100" value="80" id="volumeSlider">
                    </div>
                    
                    <button class="btn btn-link text-dark p-1" id="closePlayerBtn" title="Fermer">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const audioPlayer = document.getElementById('audioPlayer');
    const playPauseBtn = document.getElementById('playPauseBtn');
    const closePlayerBtn = document.getElementById('closePlayerBtn');
    const favoriteBtn = document.getElementById('favoriteBtn');
    const volumeBtn = document.getElementById('volumeBtn');
    const volumeSlider = document.getElementById('volumeSlider');
    
    let isPlaying = true;
    
    // Contrôle lecture/pause
    if (playPauseBtn) {
        playPauseBtn.addEventListener('click', function() {
            isPlaying = !isPlaying;
            const icon = this.querySelector('i');
            if (isPlaying) {
                icon.className = 'fas fa-pause';
                console.log('▶️ Lecture');
            } else {
                icon.className = 'fas fa-play';
                console.log('⏸️ Pause');
            }
        });
    }
    
    // Fermer le lecteur
    if (closePlayerBtn) {
        closePlayerBtn.addEventListener('click', function() {
            audioPlayer.style.display = 'none';
            document.body.style.paddingBottom = '0';
            console.log('❌ Lecteur fermé');
        });
    }
    
    // Favoris
    if (favoriteBtn) {
        favoriteBtn.addEventListener('click', function() {
            const icon = this.querySelector('i');
            if (icon.classList.contains('far')) {
                icon.className = 'fas fa-heart text-danger';
                console.log('❤️ Ajouté aux favoris');
            } else {
                icon.className = 'far fa-heart';
                console.log('💔 Retiré des favoris');
            }
        });
    }
    
    // Volume
    if (volumeSlider) {
        volumeSlider.addEventListener('input', function() {
            const volume = this.value;
            console.log('🔊 Volume:', volume + '%');
            
            // Mettre à jour l'icône du volume
            if (volumeBtn) {
                const icon = volumeBtn.querySelector('i');
                if (volume == 0) {
                    icon.className = 'fas fa-volume-mute';
                } else if (volume < 50) {
                    icon.className = 'fas fa-volume-down';
                } else {
                    icon.className = 'fas fa-volume-up';
                }
            }
        });
    }
    
    // Ajuster le padding du body pour le lecteur
    if (audioPlayer) {
        document.body.style.paddingBottom = '80px';
    }
    
    console.log('🎵 Lecteur audio initialisé');
});
</script>

<style>
.audio-player {
    backdrop-filter: blur(10px);
    background-color: rgba(255, 255, 255, 0.95) !important;
}

.audio-player .btn-link {
    border: none;
    text-decoration: none;
}

.audio-player .btn-link:hover {
    color: var(--primary-color) !important;
}

.audio-player .progress {
    cursor: pointer;
}

.audio-player .form-range {
    height: 4px;
}

@media (max-width: 768px) {
    .audio-player {
        height: 70px;
    }
    
    .audio-player .col-4 {
        padding: 0 5px;
    }
}
</style>

<?php endif; ?>

<?php
/**
 * Fonctions utilitaires pour le lecteur
 */

// Fonction pour démarrer une session de lecture
function startPlayingTrack($trackId) {
    $_SESSION['current_track_id'] = $trackId;
    $_SESSION['player_started_at'] = time();
}

// Fonction pour arrêter la lecture
function stopPlaying() {
    unset($_SESSION['current_track_id']);
    unset($_SESSION['player_started_at']);
}

// Fonction pour vérifier si un titre est en cours de lecture
function isCurrentlyPlaying($trackId = null) {
    if ($trackId === null) {
        return isset($_SESSION['current_track_id']);
    }
    return isset($_SESSION['current_track_id']) && $_SESSION['current_track_id'] == $trackId;
}
?>