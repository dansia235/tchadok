<?php
/**
 * Lecteur audio global - Tchadok Platform
 * Affiché uniquement si l'utilisateur écoute de la musique
 */

require_once __DIR__ . '/database.php';

// Vérifier s'il y a une session de lecture active
$isPlaying = false;
$currentTrack = null;

if (isset($_SESSION['current_track_id']) && !empty($_SESSION['current_track_id'])) {
    $dbInstance = TchadokDatabase::getInstance();
    $db = $dbInstance->getConnection();

    if ($db) {
        $stmt = $db->prepare("
            SELECT t.id, t.title, t.duration,
                   ar.stage_name AS artist,
                   al.cover_image AS album_cover
            FROM tracks t
            JOIN artists ar ON t.artist_id = ar.id
            LEFT JOIN albums al ON t.album_id = al.id
            WHERE t.id = ?
            LIMIT 1
        ");
        $stmt->execute([$_SESSION['current_track_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $isPlaying = true;
            $currentTrack = [
                'id' => $row['id'],
                'title' => $row['title'],
                'artist' => $row['artist'],
                'album_cover' => $row['album_cover'] ?: DEFAULT_COVER,
                'duration' => formatDuration((int) $row['duration'])
            ];
        }
    }
}
?>

<?php if ($isPlaying && $currentTrack): ?>
<!-- Lecteur Audio Flottant -->
<div id="audioPlayer" class="audio-player position-fixed bottom-0 start-0 end-0 shadow-lg" style="z-index: 1040;">
    <div class="container-fluid h-100">
        <div class="row h-100 align-items-center px-md-4">
            <!-- Info du titre -->
            <div class="col-md-3 col-3">
                <div class="d-flex align-items-center">
                    <div class="position-relative me-3 d-none d-sm-block">
                        <img src="<?php echo SITE_URL; ?>/<?php echo $currentTrack['album_cover']; ?>" 
                             alt="<?php echo htmlspecialchars($currentTrack['title']); ?>" 
                             class="rounded-circle shadow-sm" 
                             style="width: 55px; height: 55px; object-fit: cover;">
                        <div class="playing-indicator"></div>
                    </div>
                    <div class="text-truncate">
                        <div class="fw-bold text-dark text-truncate mb-0" style="font-size: 0.95rem;">
                            <?php echo htmlspecialchars($currentTrack['title']); ?>
                        </div>
                        <div class="text-muted small text-truncate">
                            <?php echo htmlspecialchars($currentTrack['artist']); ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Contrôles Centraux -->
            <div class="col-md-6 col-6 text-center">
                <div class="d-flex align-items-center justify-content-center gap-2 gap-md-4 mb-1">
                    <button class="btn btn-link d-none d-md-inline-block" id="shuffleBtn" title="Aléatoire">
                        <i class="fas fa-random small opacity-50"></i>
                    </button>

                    <button class="btn btn-link" id="prevBtn" title="Précédent">
                        <i class="fas fa-backward-step"></i>
                    </button>
                    
                    <button class="btn btn-primary rounded-circle shadow" id="playPauseBtn" title="Lecture/Pause">
                        <i class="fas fa-pause"></i>
                    </button>
                    
                    <button class="btn btn-link" id="nextBtn" title="Suivant">
                        <i class="fas fa-forward-step"></i>
                    </button>

                    <button class="btn btn-link d-none d-md-inline-block" id="repeatBtn" title="Répéter">
                        <i class="fas fa-repeat small opacity-50"></i>
                    </button>
                </div>
                
                <!-- Barre de progression immersive -->
                <div class="d-flex align-items-center gap-2 px-md-5">
                    <span class="small text-muted d-none d-md-inline" style="font-size: 0.75rem;">1:32</span>
                    <div class="progress flex-grow-1">
                        <div class="progress-bar" role="progressbar" style="width: 45%"></div>
                    </div>
                    <span class="small text-muted d-none d-md-inline" style="font-size: 0.75rem;"><?php echo $currentTrack['duration']; ?></span>
                </div>
            </div>
            
            <!-- Actions Secondaires -->
            <div class="col-md-3 col-3">
                <div class="d-flex align-items-center justify-content-end gap-1 gap-md-3">
                    <button class="btn btn-link d-none d-lg-inline-block" id="favoriteBtn" title="Favoris">
                        <i class="fas fa-heart text-danger"></i>
                    </button>
                    
                    <div class="d-none d-md-flex align-items-center gap-2 volume-container">
                        <button class="btn btn-link p-0" id="volumeBtn">
                            <i class="fas fa-volume-up opacity-75"></i>
                        </button>
                        <div style="width: 80px;">
                            <input type="range" class="form-range" min="0" max="100" value="80" id="volumeSlider">
                        </div>
                    </div>
                    
                    <button class="btn btn-link text-danger opacity-50" id="closePlayerBtn" title="Fermer">
                        <i class="fas fa-times-circle"></i>
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
