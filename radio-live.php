<?php
/**
 * Radio Live - Tchadok Platform
 * Page dédiée à la radio en direct
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'assets/images/placeholders.php';

$pageTitle = 'Radio Live';
$pageDescription = 'Écoutez Tchadok Radio en direct - 24/7 de la meilleure musique tchadienne en continu.';

include 'includes/header.php';
?>

<!-- Radio Live Hero Section -->
<section class="radio-hero-section">
    <div class="floating-music-notes" style="top: 10%; left: 5%;">♪</div>
    <div class="floating-music-notes" style="top: 70%; right: 8%; animation-delay: 2s;">♫</div>
    <div class="floating-music-notes" style="bottom: 15%; left: 12%; animation-delay: 4s;">♪</div>
    <div class="floating-music-notes" style="top: 30%; right: 20%; animation-delay: 1s;">♫</div>
    
    <div class="container">
        <div class="row align-items-center min-vh-100">
            <div class="col-lg-6">
                <div class="radio-hero-content">
                    <div class="live-indicator">
                        <span class="live-dot"></span>
                        EN DIRECT
                    </div>
                    <h1>Tchadok Radio Live</h1>
                    <p>24/7 de la meilleure musique tchadienne. Découvrez les hits du moment, les classiques intemporels et les nouveaux talents de la scène musicale tchadienne.</p>
                    
                    <div class="current-show">
                        <div class="show-info">
                            <h5 id="currentShow">Soirée Traditionnelle</h5>
                            <p id="currentHost">avec DJ Moussa • 19h - 21h</p>
                        </div>
                    </div>
                    
                    <div class="radio-controls">
                        <button class="btn-play-large" id="mainPlayBtn" onclick="toggleMainRadio()">
                            <i class="fas fa-play" id="mainPlayIcon"></i>
                        </button>
                        <div class="volume-control">
                            <i class="fas fa-volume-up"></i>
                            <input type="range" class="volume-slider" min="0" max="100" value="80" id="volumeSlider">
                            <span id="volumeDisplay">80%</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="radio-visualizer-large">
                    <div class="visualizer-container">
                        <div class="visualizer-bars">
                            <?php for($i = 0; $i < 24; $i++): ?>
                            <div class="bar"></div>
                            <?php endfor; ?>
                        </div>
                        <div class="radio-wave"></div>
                    </div>
                    
                    <div class="now-playing-card">
                        <div class="album-art">
                            <div id="nowPlayingArt"><?php echo createTrackCover('Titre en cours', 'Artiste', '#0066CC'); ?></div>
                        </div>
                        <div class="track-info">
                            <h6 id="currentTrack">Sahel Dreams</h6>
                            <p id="currentArtist">Mahamat Salleh</p>
                        </div>
                        <div class="track-controls">
                            <button class="btn-icon" onclick="favoriteTrack()">
                                <i class="far fa-heart" id="favoriteIcon"></i>
                            </button>
                            <button class="btn-icon" onclick="shareTrack()">
                                <i class="fas fa-share-alt"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Programming Schedule -->
<section class="container my-5">
    <div class="section-header">
        <h2>Programme de la Journée</h2>
        <p class="text-muted">Découvrez nos émissions et horaires</p>
    </div>
    
    <div class="row g-4">
        <?php
        $schedule = [
            ['time' => '6h - 9h', 'show' => 'Réveil Musical', 'host' => 'Sarah Diallo', 'status' => 'past', 'color' => 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)'],
            ['time' => '9h - 12h', 'show' => 'Morning Hits', 'host' => 'DJ Tchad', 'status' => 'past', 'color' => 'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)'],
            ['time' => '12h - 14h', 'show' => 'Pause Déjeuner', 'host' => 'Playlist Auto', 'status' => 'past', 'color' => 'linear-gradient(135deg, #fa709a 0%, #fee140 100%)'],
            ['time' => '14h - 16h', 'show' => 'Spécial Artistes', 'host' => 'Achta Banda', 'status' => 'past', 'color' => 'linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%)'],
            ['time' => '16h - 19h', 'show' => 'Hits de l\'Après-midi', 'host' => 'DJ Moussa', 'status' => 'past', 'color' => 'linear-gradient(135deg, #a8edea 0%, #fed6e3 100%)'],
            ['time' => '19h - 21h', 'show' => 'Soirée Traditionnelle', 'host' => 'DJ Moussa', 'status' => 'current', 'color' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'],
            ['time' => '21h - 23h', 'show' => 'Urban Beats', 'host' => 'MC Afro', 'status' => 'upcoming', 'color' => 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)'],
            ['time' => '23h - 2h', 'show' => 'Nuit Tchadienne', 'host' => 'DJ Night', 'status' => 'upcoming', 'color' => 'linear-gradient(135deg, #4158d0 0%, #c850c0 100%)'],
        ];
        
        foreach ($schedule as $program):
        ?>
        <div class="col-md-6 col-lg-3">
            <div class="schedule-card <?php echo $program['status']; ?>" style="background: <?php echo $program['color']; ?>;">
                <div class="schedule-time">
                    <i class="fas fa-clock me-2"></i><?php echo $program['time']; ?>
                </div>
                <h5><?php echo $program['show']; ?></h5>
                <p class="mb-0">avec <?php echo $program['host']; ?></p>
                
                <?php if ($program['status'] === 'current'): ?>
                <div class="live-badge">
                    <span class="live-dot-small"></span>
                    EN DIRECT
                </div>
                <?php elseif ($program['status'] === 'upcoming'): ?>
                <button class="btn btn-light btn-sm mt-2">
                    <i class="fas fa-bell me-1"></i>Rappel
                </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- Recent Tracks -->
<section class="container my-5">
    <div class="section-header">
        <h2>Récemment Diffusé</h2>
        <p class="text-muted">Les derniers titres passés à l'antenne</p>
    </div>
    
    <div class="recent-tracks">
        <?php
        $recentTracks = [
            ['title' => 'Sahel Dreams', 'artist' => 'Mahamat Salleh', 'time' => '19:45', 'duration' => '4:32'],
            ['title' => 'N\'Djamena Vibes', 'artist' => 'DJ Tchad', 'time' => '19:40', 'duration' => '3:28'],
            ['title' => 'Racines Profondes', 'artist' => 'Achta Band', 'time' => '19:35', 'duration' => '5:15'],
            ['title' => 'Mélodie du Coeur', 'artist' => 'Mounira Mitchala', 'time' => '19:30', 'duration' => '4:08'],
            ['title' => 'Rythme Traditionnel', 'artist' => 'Maimouna Youssouf', 'time' => '19:25', 'duration' => '6:22'],
        ];
        
        foreach ($recentTracks as $index => $track):
        ?>
        <div class="track-item">
            <div class="track-number"><?php echo $index + 1; ?></div>
            <div class="track-info">
                <h6><?php echo $track['title']; ?></h6>
                <p><?php echo $track['artist']; ?></p>
            </div>
            <div class="track-time">
                <span class="time"><?php echo $track['time']; ?></span>
                <span class="duration"><?php echo $track['duration']; ?></span>
            </div>
            <div class="track-actions">
                <button class="btn-icon-small" onclick="replayTrack(<?php echo $index; ?>)">
                    <i class="fas fa-redo"></i>
                </button>
                <button class="btn-icon-small" onclick="likeTrack(<?php echo $index; ?>)">
                    <i class="far fa-heart"></i>
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- Social Media Feed -->
<section class="container my-5">
    <div class="section-header">
        <h2>Réseaux Sociaux</h2>
        <p class="text-muted">Suivez-nous et partagez vos moments musicaux</p>
    </div>
    
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="social-card facebook">
                <div class="social-header">
                    <i class="fab fa-facebook-f"></i>
                    <span>Facebook</span>
                </div>
                <div class="social-content">
                    <p>🎵 En direct maintenant : "Soirée Traditionnelle" avec DJ Moussa ! Écoutez les plus beaux sons du Tchad traditionnel.</p>
                    <div class="social-stats">
                        <span><i class="fas fa-heart"></i> 234</span>
                        <span><i class="fas fa-comment"></i> 56</span>
                        <span><i class="fas fa-share"></i> 23</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="social-card twitter">
                <div class="social-header">
                    <i class="fab fa-twitter"></i>
                    <span>Twitter</span>
                </div>
                <div class="social-content">
                    <p>#TchadokRadio 🎶 Actuellement : "Sahel Dreams" de Mahamat Salleh. Une pure merveille de la musique tchadienne ! #MusiqueAfricaine</p>
                    <div class="social-stats">
                        <span><i class="fas fa-heart"></i> 89</span>
                        <span><i class="fas fa-retweet"></i> 34</span>
                        <span><i class="fas fa-reply"></i> 12</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="social-card instagram">
                <div class="social-header">
                    <i class="fab fa-instagram"></i>
                    <span>Instagram</span>
                </div>
                <div class="social-content">
                    <p>📻 Story du studio en direct ! DJ Moussa aux platines pour la Soirée Traditionnelle. ✨</p>
                    <div class="social-stats">
                        <span><i class="fas fa-heart"></i> 445</span>
                        <span><i class="fas fa-comment"></i> 78</span>
                        <span><i class="fas fa-paper-plane"></i> 45</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
/* Radio Live Page Styles */
:root {
    --bleu-tchadien: #0066CC;
    --jaune-solaire: #FFD700;
    --rouge-terre: #CC3333;
    --vert-savane: #228B22;
    --blanc-coton: #FFFFFF;
    --gris-harmattan: #2C3E50;
    --gris-clair: #f8f9fa;
    --noir-profond: #1a1a1a;
}

.radio-hero-section {
    background: linear-gradient(135deg, rgba(0, 102, 204, 0.9), rgba(255, 215, 0, 0.7)), 
                url('data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"%3E%3Cpath fill="%230066CC" fill-opacity="0.1" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,122.7C672,117,768,139,864,133.3C960,128,1056,96,1152,90.7C1248,85,1344,107,1392,117.3L1440,128L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"%3E%3C/path%3E%3C/svg%3E');
    background-size: cover;
    background-position: center;
    min-height: 100vh;
    display: flex;
    align-items: center;
    position: relative;
    overflow: hidden;
}

.floating-music-notes {
    position: absolute;
    font-size: 2.5rem;
    color: rgba(255, 255, 255, 0.15);
    animation: float 8s ease-in-out infinite;
    z-index: 1;
}

@keyframes float {
    0%, 100% { transform: translateY(0) rotate(0deg); }
    50% { transform: translateY(-30px) rotate(15deg); }
}

.radio-hero-content {
    color: white;
    z-index: 2;
    position: relative;
}

.live-indicator {
    display: inline-flex;
    align-items: center;
    background: rgba(204, 51, 51, 0.9);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 25px;
    font-weight: 700;
    font-size: 0.9rem;
    margin-bottom: 1.5rem;
    animation: livePulse 2s infinite;
}

.live-dot {
    width: 8px;
    height: 8px;
    background: white;
    border-radius: 50%;
    margin-right: 0.5rem;
    animation: blink 1.5s infinite;
}

@keyframes livePulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(204, 51, 51, 0.7); }
    50% { box-shadow: 0 0 0 10px rgba(204, 51, 51, 0); }
}

@keyframes blink {
    0%, 50% { opacity: 1; }
    51%, 100% { opacity: 0.3; }
}

.radio-hero-content h1 {
    font-size: 4rem;
    font-weight: 900;
    color: white;
    margin-bottom: 1.5rem;
    text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
    animation: fadeInUp 1s ease;
}

.radio-hero-content p {
    font-size: 1.3rem;
    margin-bottom: 2rem;
    opacity: 0.9;
    animation: fadeInUp 1.2s ease;
}

.current-show {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border-radius: 15px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.show-info h5 {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.show-info p {
    margin-bottom: 0;
    opacity: 0.8;
}

.radio-controls {
    display: flex;
    align-items: center;
    gap: 2rem;
    flex-wrap: wrap;
}

.btn-play-large {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--jaune-solaire), #e6c200);
    border: none;
    color: var(--gris-harmattan);
    font-size: 2rem;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 8px 25px rgba(255, 215, 0, 0.4);
}

.btn-play-large:hover {
    transform: scale(1.1);
    box-shadow: 0 12px 35px rgba(255, 215, 0, 0.6);
}

.volume-control {
    display: flex;
    align-items: center;
    gap: 1rem;
    background: rgba(255, 255, 255, 0.1);
    padding: 0.75rem 1.5rem;
    border-radius: 25px;
    backdrop-filter: blur(10px);
}

.volume-slider {
    width: 120px;
    -webkit-appearance: none;
    height: 6px;
    border-radius: 3px;
    background: rgba(255, 255, 255, 0.3);
    outline: none;
}

.volume-slider::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: var(--jaune-solaire);
    cursor: pointer;
}

.radio-visualizer-large {
    position: relative;
    z-index: 2;
}

.visualizer-container {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    padding: 3rem;
    text-align: center;
    border: 1px solid rgba(255, 255, 255, 0.2);
    position: relative;
    overflow: hidden;
}

.visualizer-bars {
    display: flex;
    align-items: flex-end;
    justify-content: center;
    gap: 4px;
    height: 120px;
    margin-bottom: 2rem;
}

.visualizer-bars .bar {
    width: 6px;
    background: var(--jaune-solaire);
    border-radius: 3px;
    animation: dance 1.5s ease-in-out infinite;
    min-height: 10px;
}

.visualizer-bars .bar:nth-child(odd) {
    animation-delay: 0.1s;
}

.visualizer-bars .bar:nth-child(even) {
    animation-delay: 0.2s;
}

@keyframes dance {
    0%, 100% { height: 20px; }
    50% { height: 80px; }
}

.radio-wave {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 200px;
    height: 200px;
    border: 2px solid rgba(255, 215, 0, 0.3);
    border-radius: 50%;
    animation: wave 3s ease-in-out infinite;
}

.radio-wave::before,
.radio-wave::after {
    content: '';
    position: absolute;
    top: -20px;
    left: -20px;
    right: -20px;
    bottom: -20px;
    border: 2px solid rgba(255, 215, 0, 0.2);
    border-radius: 50%;
    animation: wave 3s ease-in-out infinite;
}

.radio-wave::after {
    top: -40px;
    left: -40px;
    right: -40px;
    bottom: -40px;
    animation-delay: 0.5s;
}

@keyframes wave {
    0%, 100% { transform: scale(0.8); opacity: 0.8; }
    50% { transform: scale(1.2); opacity: 0.3; }
}

.now-playing-card {
    background: rgba(255, 255, 255, 0.95);
    border-radius: 15px;
    padding: 1.5rem;
    margin-top: 2rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
}

.album-art img {
    width: 60px;
    height: 60px;
    border-radius: 10px;
    object-fit: cover;
}

.track-info {
    flex-grow: 1;
    color: var(--gris-harmattan);
}

.track-info h6 {
    margin-bottom: 0.25rem;
    font-weight: 700;
}

.track-info p {
    margin-bottom: 0;
    opacity: 0.7;
    font-size: 0.9rem;
}

.track-controls {
    display: flex;
    gap: 0.5rem;
}

.btn-icon {
    width: 40px;
    height: 40px;
    border: none;
    background: transparent;
    color: var(--gris-harmattan);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-icon:hover {
    background: var(--bleu-tchadien);
    color: white;
}

/* Schedule Cards */
.schedule-card {
    color: white;
    border-radius: 15px;
    padding: 2rem;
    text-align: center;
    transition: all 0.3s ease;
    height: 100%;
    position: relative;
    overflow: hidden;
}

.schedule-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3);
}

.schedule-card.current {
    border: 3px solid var(--jaune-solaire);
    box-shadow: 0 0 25px rgba(255, 215, 0, 0.5);
}

.schedule-card.past {
    opacity: 0.7;
}

.schedule-time {
    background: rgba(255, 255, 255, 0.2);
    display: inline-block;
    padding: 0.5rem 1rem;
    border-radius: 25px;
    font-weight: 600;
    margin-bottom: 1rem;
    font-size: 0.9rem;
}

.live-badge {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: var(--rouge-terre);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    animation: livePulse 2s infinite;
}

.live-dot-small {
    width: 6px;
    height: 6px;
    background: white;
    border-radius: 50%;
    animation: blink 1.5s infinite;
}

/* Recent Tracks */
.recent-tracks {
    background: white;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.track-item {
    display: flex;
    align-items: center;
    padding: 1.5rem;
    border-bottom: 1px solid #f8f9fa;
    transition: all 0.3s ease;
}

.track-item:hover {
    background: #f8f9fa;
}

.track-item:last-child {
    border-bottom: none;
}

.track-number {
    width: 40px;
    height: 40px;
    background: var(--bleu-tchadien);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    margin-right: 1rem;
}

.track-info {
    flex-grow: 1;
}

.track-info h6 {
    margin-bottom: 0.25rem;
    color: var(--gris-harmattan);
    font-weight: 600;
}

.track-info p {
    margin-bottom: 0;
    color: #6c757d;
    font-size: 0.9rem;
}

.track-time {
    text-align: right;
    margin-right: 1rem;
}

.track-time .time {
    display: block;
    font-weight: 600;
    color: var(--gris-harmattan);
}

.track-time .duration {
    font-size: 0.8rem;
    color: #6c757d;
}

.track-actions {
    display: flex;
    gap: 0.5rem;
}

.btn-icon-small {
    width: 32px;
    height: 32px;
    border: none;
    background: transparent;
    color: #6c757d;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-icon-small:hover {
    background: var(--bleu-tchadien);
    color: white;
}

/* Social Cards */
.social-card {
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    height: 100%;
}

.social-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
}

.social-header {
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-weight: 700;
    color: white;
}

.social-card.facebook .social-header {
    background: #1877f2;
}

.social-card.twitter .social-header {
    background: #1da1f2;
}

.social-card.instagram .social-header {
    background: linear-gradient(45deg, #405de6, #5851db, #833ab4, #c13584, #e1306c, #fd1d1d);
}

.social-content {
    padding: 1.5rem;
}

.social-content p {
    color: var(--gris-harmattan);
    margin-bottom: 1rem;
    line-height: 1.6;
}

.social-stats {
    display: flex;
    gap: 1rem;
    font-size: 0.9rem;
    color: #6c757d;
}

.social-stats span {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

/* Section Headers */
.section-header {
    text-align: center;
    margin-bottom: 3rem;
}

.section-header h2 {
    font-size: 3rem;
    font-weight: 900;
    color: var(--gris-harmattan);
    margin-bottom: 1rem;
    position: relative;
    display: inline-block;
}

.section-header h2::after {
    content: '';
    position: absolute;
    bottom: -10px;
    left: 50%;
    transform: translateX(-50%);
    width: 80px;
    height: 4px;
    background: linear-gradient(to right, var(--bleu-tchadien), var(--jaune-solaire));
    border-radius: 2px;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Mobile Responsiveness */
@media (max-width: 768px) {
    .radio-hero-content h1 {
        font-size: 2.5rem;
    }
    
    .radio-controls {
        justify-content: center;
    }
    
    .btn-play-large {
        width: 60px;
        height: 60px;
        font-size: 1.5rem;
    }
    
    .volume-control {
        margin-top: 1rem;
    }
    
    .visualizer-container {
        padding: 2rem;
    }
    
    .visualizer-bars {
        height: 80px;
    }
    
    .section-header h2 {
        font-size: 2rem;
    }
    
    .track-item {
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .track-actions {
        order: 3;
        width: 100%;
        justify-content: center;
        margin-top: 0.5rem;
    }
}
</style>

<script>
// Radio Live JavaScript
let isPlaying = false;
let currentVolume = 80;

// Current track rotation
const tracks = [
    {title: 'Sahel Dreams', artist: 'Mahamat Salleh', art: 'data:image/svg+xml;base64,<?php echo base64_encode(createTrackCover('Sahel Dreams', 'Mahamat Salleh', '#0066CC')); ?>'},
    {title: 'N\'Djamena Vibes', artist: 'DJ Tchad', art: 'data:image/svg+xml;base64,<?php echo base64_encode(createTrackCover('N\'Djamena Vibes', 'DJ Tchad', '#FFD700')); ?>'},
    {title: 'Racines Profondes', artist: 'Achta Band', art: 'data:image/svg+xml;base64,<?php echo base64_encode(createTrackCover('Racines Profondes', 'Achta Band', '#228B22')); ?>'},
    {title: 'Mélodie du Coeur', artist: 'Mounira Mitchala', art: 'data:image/svg+xml;base64,<?php echo base64_encode(createTrackCover('Mélodie du Coeur', 'Mounira Mitchala', '#CC3333')); ?>'},
    {title: 'Rythme Traditionnel', artist: 'Maimouna Youssouf', art: 'data:image/svg+xml;base64,<?php echo base64_encode(createTrackCover('Rythme Traditionnel', 'Maimouna Youssouf', '#667eea')); ?>'}
];

let currentTrackIndex = 0;

function toggleMainRadio() {
    const playBtn = document.getElementById('mainPlayBtn');
    const playIcon = document.getElementById('mainPlayIcon');
    
    if (isPlaying) {
        playIcon.className = 'fas fa-play';
        showNotification('📻 Radio Tchadok Live arrêtée');
        stopVisualizer();
    } else {
        playIcon.className = 'fas fa-pause';
        showNotification('📻 Radio Tchadok Live en cours...');
        startVisualizer();
    }
    
    isPlaying = !isPlaying;
}

function startVisualizer() {
    const bars = document.querySelectorAll('.visualizer-bars .bar');
    bars.forEach((bar, index) => {
        bar.style.animationPlayState = 'running';
        bar.style.animationDelay = (index * 0.1) + 's';
    });
}

function stopVisualizer() {
    const bars = document.querySelectorAll('.visualizer-bars .bar');
    bars.forEach(bar => {
        bar.style.animationPlayState = 'paused';
    });
}

// Volume control
document.getElementById('volumeSlider').addEventListener('input', function() {
    currentVolume = this.value;
    document.getElementById('volumeDisplay').textContent = currentVolume + '%';
});

// Track rotation
function updateCurrentTrack() {
    const track = tracks[currentTrackIndex];
    document.getElementById('currentTrack').textContent = track.title;
    document.getElementById('currentArtist').textContent = track.artist;
    document.getElementById('nowPlayingArt').src = track.art;
    
    currentTrackIndex = (currentTrackIndex + 1) % tracks.length;
}

// Rotate tracks every 30 seconds for demo
setInterval(updateCurrentTrack, 30000);

function favoriteTrack() {
    const icon = document.getElementById('favoriteIcon');
    if (icon.classList.contains('far')) {
        icon.classList.remove('far');
        icon.classList.add('fas');
        icon.style.color = '#CC3333';
        showNotification('❤️ Ajouté aux favoris');
    } else {
        icon.classList.remove('fas');
        icon.classList.add('far');
        icon.style.color = '';
        showNotification('💔 Retiré des favoris');
    }
}

function shareTrack() {
    const track = document.getElementById('currentTrack').textContent;
    const artist = document.getElementById('currentArtist').textContent;
    
    if (navigator.share) {
        navigator.share({
            title: `${track} - ${artist}`,
            text: `🎵 Écoutez "${track}" de ${artist} sur Tchadok Radio Live !`,
            url: window.location.href
        });
    } else {
        // Fallback - copy to clipboard
        const text = `🎵 Écoutez "${track}" de ${artist} sur Tchadok Radio Live ! ${window.location.href}`;
        navigator.clipboard.writeText(text).then(() => {
            showNotification('📋 Lien copié dans le presse-papiers');
        });
    }
}

function replayTrack(index) {
    showNotification('🔄 Demande de rediffusion envoyée');
}

function likeTrack(index) {
    const button = event.target.closest('.btn-icon-small');
    const icon = button.querySelector('i');
    
    if (icon.classList.contains('far')) {
        icon.classList.remove('far');
        icon.classList.add('fas');
        icon.style.color = '#CC3333';
        showNotification('❤️ Titre liké');
    } else {
        icon.classList.remove('fas');
        icon.classList.add('far');
        icon.style.color = '';
        showNotification('💔 Like retiré');
    }
}

function showNotification(message) {
    const notification = document.createElement('div');
    notification.innerHTML = message;
    notification.style.cssText = `
        position: fixed; 
        top: 20px; 
        right: 20px; 
        background: #0066CC; 
        color: white; 
        padding: 15px 20px; 
        border-radius: 8px; 
        z-index: 10000;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        max-width: 300px;
        animation: slideIn 0.3s ease;
    `;
    document.body.appendChild(notification);
    setTimeout(() => notification.remove(), 3000);
}

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    updateCurrentTrack();
    
    // Auto-start visualizer demo
    setTimeout(() => {
        startVisualizer();
    }, 1000);
});
</script>

<?php include 'includes/footer.php'; ?>