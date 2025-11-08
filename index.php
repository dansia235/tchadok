<?php
/**
 * Page d'accueil - Tchadok Platform
 * Basée sur le design de tchadok-homepage.html
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/database.php';
require_once 'assets/images/placeholders.php';

$pageTitle = 'Accueil';
$pageDescription = 'La musique tchadienne à portée de clic. Découvrez, écoutez et soutenez vos artistes préférés du Tchad.';

include 'includes/header.php';
?>

<!-- Hero Section -->
<section class="hero-section" id="accueil">
    <div class="floating-music-notes" style="top: 20%; left: 10%;">♪</div>
    <div class="floating-music-notes" style="top: 60%; right: 15%; animation-delay: 2s;">♫</div>
    <div class="floating-music-notes" style="bottom: 30%; left: 20%; animation-delay: 4s;">♪</div>
    
    <div class="container">
        <div class="row align-items-center min-vh-100">
            <div class="col-lg-6">
                <div class="hero-content">
                    <h1>La Musique Tchadienne à Portée de Clic</h1>
                    <p>Découvrez, écoutez et partagez le meilleur de la musique tchadienne. Des artistes légendaires aux nouveaux talents, vivez l'expérience musicale authentique du Tchad.</p>
                    <div class="hero-buttons">
                        <?php if (!isLoggedIn()): ?>
                        <a href="<?php echo SITE_URL; ?>/register.php" class="btn btn-primary-custom btn-lg me-3">
                            <i class="fas fa-play-circle me-2"></i>Écouter Maintenant
                        </a>
                        <a href="#decouvrir" class="btn btn-secondary-custom btn-lg">
                            <i class="fas fa-music me-2"></i>Explorer
                        </a>
                        <?php else: ?>
                        <button class="btn btn-primary-custom btn-lg me-3" onclick="playRadio()">
                            <i class="fas fa-play-circle me-2"></i>Écouter Maintenant
                        </button>
                        <a href="#decouvrir" class="btn btn-secondary-custom btn-lg">
                            <i class="fas fa-music me-2"></i>Explorer
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <!-- Animated music visualization -->
                <div class="hero-visual-placeholder">
                    <div class="animated-logo">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="200" height="200">
                            <circle cx="50" cy="50" r="45" fill="#0066CC"/>
                            <path d="M30 45 L30 55 L40 60 L40 40 Z M45 35 L45 65 L55 70 L55 30 Z M60 40 L60 60 L70 55 L70 45 Z" fill="#FFD700"/>
                        </svg>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Radio Player Section -->
<section class="container" id="radio">
    <div class="radio-player">
        <div class="row align-items-center">
            <div class="col-md-4">
                <h3 class="mb-0" style="color: white !important;">
                    <i class="fas fa-broadcast-tower me-2"></i>
                    Tchadok Radio Live
                </h3>
                <p class="mb-0 opacity-75" style="color: rgba(255, 255, 255, 0.8) !important;">24/7 Musique Tchadienne</p>
            </div>
            <div class="col-md-4">
                <div class="radio-visualizer">
                    <div class="bar" style="height: 20px;"></div>
                    <div class="bar" style="height: 35px;"></div>
                    <div class="bar" style="height: 15px;"></div>
                    <div class="bar" style="height: 40px;"></div>
                    <div class="bar" style="height: 25px;"></div>
                    <div class="bar" style="height: 30px;"></div>
                    <div class="bar" style="height: 20px;"></div>
                    <div class="bar" style="height: 35px;"></div>
                    <div class="bar" style="height: 15px;"></div>
                    <div class="bar" style="height: 40px;"></div>
                    <div class="bar" style="height: 25px;"></div>
                    <div class="bar" style="height: 30px;"></div>
                </div>
            </div>
            <div class="col-md-4 text-end">
                <button class="btn btn-warning btn-lg rounded-circle me-3" onclick="toggleRadio()">
                    <i class="fas fa-play" id="radioPlayIcon"></i>
                </button>
                <button class="btn btn-light btn-sm">
                    <i class="fas fa-volume-up me-1"></i> 80%
                </button>
            </div>
        </div>
    </div>
</section>

<!-- Nouvelles Sorties -->
<section class="container my-5" id="decouvrir">
    <div class="section-header">
        <h2>Nouvelles Sorties</h2>
        <p class="text-muted">Les derniers hits de la scène musicale tchadienne</p>
    </div>
    
    <div class="row g-4">
        <?php
        // Récupération des nouvelles sorties depuis la base de données
        $releases = getNewReleases(4);
        
        foreach ($releases as $index => $release):
        ?>
        <div class="col-md-4 col-lg-3">
            <div class="music-card">
                <div class="music-card-img">
                    <?php echo createAlbumCover($release['title'], $release['artist'], $release['type'], '#' . $release['color']); ?>
                    <div class="play-overlay">
                        <div class="play-btn" onclick="playTrack(<?php echo $release['id']; ?>)">
                            <i class="fas fa-play"></i>
                        </div>
                    </div>
                </div>
                <div class="p-3">
                    <h5 class="mb-1"><?php echo htmlspecialchars($release['title']); ?></h5>
                    <p class="text-muted mb-2"><?php echo htmlspecialchars($release['artist']); ?></p>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="badge <?php echo $release['badge_class']; ?>"><?php echo $release['badge']; ?></span>
                        <small class="text-muted">
                            <?php echo isset($release['extra']) ? $release['extra'] : $release['price']; ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- Emissions Section -->
<section class="container my-5" id="emissions">
    <div class="section-header">
        <h2>Émissions Musicales</h2>
        <p class="text-muted">Ne manquez pas vos émissions préférées</p>
    </div>
    
    <div class="row g-4">
        <?php
        // Récupération des émissions radio depuis la base de données
        $radioShows = getRadioShows(4);
        
        foreach ($radioShows as $show):
        ?>
        <div class="col-md-6 col-lg-3">
            <div class="emission-card" style="background: <?php echo $show['background']; ?>;">
                <div class="emission-time">
                    <i class="fas fa-clock me-2"></i><?php echo $show['time_display']; ?>
                </div>
                <h4><?php echo htmlspecialchars($show['title']); ?></h4>
                <p><?php echo htmlspecialchars($show['description']); ?></p>
                <small class="text-light">Animé par <?php echo htmlspecialchars($show['host']); ?></small>
                <button class="btn btn-light btn-sm mt-auto">
                    <i class="fas fa-calendar-plus me-2"></i>Programmer
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- Artistes Populaires -->
<section class="container my-5" id="artistes">
    <div class="section-header">
        <h2>Artistes Populaires</h2>
        <p class="text-muted">Les stars de la musique tchadienne</p>
    </div>
    
    <div class="row g-4">
        <?php
        // Récupération des artistes populaires depuis la base de données
        $artists = getPopularArtists(6);
        
        foreach ($artists as $index => $artist):
        ?>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="artist-card">
                <div class="artist-img">
                    <?php echo createArtistAvatar($artist['name'], 150, '#' . $artist['color']); ?>
                </div>
                <h6 class="mt-3 mb-1"><?php echo htmlspecialchars($artist['name']); ?></h6>
                <small class="text-muted"><?php echo htmlspecialchars($artist['genre']); ?></small>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- Stats Section -->
<section class="stats-section">
    <div class="container">
        <div class="row">
            <?php
            // Récupération des statistiques depuis la base de données
            $stats = getPlatformStats();
            ?>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number" data-count="<?php echo $stats['total_tracks']; ?>">0</div>
                    <h5>Titres Disponibles</h5>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number" data-count="<?php echo $stats['total_artists']; ?>">0</div>
                    <h5>Artistes Partenaires</h5>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number" data-count="<?php echo $stats['total_users']; ?>">0</div>
                    <h5>Utilisateurs Actifs</h5>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number" data-count="<?php echo $stats['streaming_hours']; ?>">0</div>
                    <h5>Streaming/Jour</h5>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
/* CSS personnalisé basé sur tchadok-homepage.html */
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

body {
    font-family: 'Open Sans', sans-serif;
    color: var(--gris-harmattan);
    overflow-x: hidden;
    background-color: #f5f5f5;
}

h1, h2, h3, h4, h5, h6 {
    font-family: 'Montserrat', sans-serif;
    font-weight: 700;
}

/* Boutons personnalisés */
.btn-primary-custom {
    background: linear-gradient(135deg, var(--bleu-tchadien), #0052a3);
    border: none;
    color: white;
    font-weight: 600;
    padding: 0.75rem 1.5rem;
    border-radius: 50px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(0, 102, 204, 0.3);
}

.btn-primary-custom:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 25px rgba(0, 102, 204, 0.4);
    color: white;
}

.btn-secondary-custom {
    background: transparent;
    border: 2px solid var(--jaune-solaire);
    color: var(--gris-harmattan);
    font-weight: 600;
    padding: 0.75rem 1.5rem;
    border-radius: 50px;
    transition: all 0.3s ease;
}

.btn-secondary-custom:hover {
    background: var(--jaune-solaire);
    color: var(--gris-harmattan);
    transform: translateY(-2px);
}

/* Hero Section */
.hero-section {
    background: linear-gradient(135deg, rgba(0, 102, 204, 0.9), rgba(255, 215, 0, 0.7)), url('data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"%3E%3Cpath fill="%230066CC" fill-opacity="0.1" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,122.7C672,117,768,139,864,133.3C960,128,1056,96,1152,90.7C1248,85,1344,107,1392,117.3L1440,128L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"%3E%3C/path%3E%3C/svg%3E');
    background-size: cover;
    background-position: center;
    min-height: 100vh;
    display: flex;
    align-items: center;
    position: relative;
    overflow: hidden;
}

.hero-content h1 {
    font-size: 4rem;
    font-weight: 900;
    color: white;
    text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
    margin-bottom: 1.5rem;
    animation: fadeInUp 1s ease;
}

.hero-content p {
    font-size: 1.3rem;
    color: white;
    margin-bottom: 2rem;
    animation: fadeInUp 1.2s ease;
}

.hero-buttons {
    animation: fadeInUp 1.4s ease;
}

.floating-music-notes {
    position: absolute;
    font-size: 2rem;
    color: rgba(255, 255, 255, 0.2);
    animation: float 6s ease-in-out infinite;
}

.hero-visual-placeholder {
    display: flex;
    justify-content: center;
    align-items: center;
    height: 400px;
}

.animated-logo {
    animation: logoFloat 3s ease-in-out infinite;
}

@keyframes logoFloat {
    0%, 100% { transform: translateY(0) rotate(0deg); }
    50% { transform: translateY(-20px) rotate(5deg); }
}

@keyframes float {
    0%, 100% { transform: translateY(0) rotate(0deg); }
    50% { transform: translateY(-20px) rotate(10deg); }
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

/* Radio Player Section */
.radio-player {
    background: linear-gradient(135deg, var(--gris-harmattan), #1a2332);
    color: white;
    padding: 2rem;
    border-radius: 20px;
    margin: 3rem 0;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
    position: relative;
    overflow: hidden;
}

.radio-player::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255, 215, 0, 0.1) 0%, transparent 70%);
    animation: pulse 3s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(0.8); opacity: 0.5; }
    50% { transform: scale(1.2); opacity: 0.8; }
}

.radio-visualizer {
    height: 60px;
    display: flex;
    align-items: flex-end;
    justify-content: center;
    gap: 3px;
    margin: 1rem 0;
}

.bar {
    width: 4px;
    background: var(--jaune-solaire);
    border-radius: 2px;
    animation: dance 1s ease-in-out infinite;
}

.bar:nth-child(odd) {
    animation-delay: 0.1s;
}

.bar:nth-child(even) {
    animation-delay: 0.2s;
}

@keyframes dance {
    0%, 100% { height: 10px; }
    50% { height: 40px; }
}

/* Music Cards */
.music-card {
    background: white;
    border-radius: 15px;
    overflow: hidden;
    transition: all 0.3s ease;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
    cursor: pointer;
    height: 100%;
}

.music-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
}

.music-card-img {
    position: relative;
    overflow: hidden;
    height: 250px;
}

.music-card-img img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.music-card:hover .music-card-img img {
    transform: scale(1.1);
}

.play-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 102, 204, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.music-card:hover .play-overlay {
    opacity: 1;
}

.play-btn {
    width: 60px;
    height: 60px;
    background: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: var(--bleu-tchadien);
    transform: scale(0);
    transition: transform 0.3s ease;
    cursor: pointer;
}

.music-card:hover .play-btn {
    transform: scale(1);
}

/* Artist Cards */
.artist-card {
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.artist-img {
    width: 150px;
    height: 150px;
    margin: 0 auto;
    border-radius: 50%;
    overflow: hidden;
    border: 4px solid transparent;
    background: linear-gradient(white, white) padding-box,
                linear-gradient(135deg, var(--bleu-tchadien), var(--jaune-solaire)) border-box;
    transition: all 0.3s ease;
}

.artist-img img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.artist-card:hover .artist-img {
    transform: scale(1.1);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
}

/* Emission Cards */
.emission-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 15px;
    padding: 2rem;
    text-align: center;
    transition: all 0.3s ease;
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.emission-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 40px rgba(102, 126, 234, 0.4);
}

.emission-time {
    background: rgba(255, 255, 255, 0.2);
    display: inline-block;
    padding: 0.5rem 1rem;
    border-radius: 50px;
    font-weight: 600;
    margin-bottom: 1rem;
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

/* Stats Section */
.stats-section {
    background: linear-gradient(135deg, var(--bleu-tchadien), #0052a3);
    color: white;
    padding: 4rem 0;
    margin: 4rem 0;
}

.stat-card {
    text-align: center;
    padding: 2rem;
}

.stat-number {
    font-size: 3rem;
    font-weight: 900;
    color: var(--jaune-solaire);
    margin-bottom: 0.5rem;
}

/* Mobile Responsiveness */
@media (max-width: 768px) {
    .hero-content h1 {
        font-size: 2.5rem;
    }
    
    .section-header h2 {
        font-size: 2rem;
    }
    
    .music-card-img {
        height: 200px;
    }
}
</style>

<script>
// JavaScript basé sur tchadok-homepage.html
$(document).ready(function() {
    // Smooth scrolling for navigation links
    $('a[href^="#"]').on('click', function(e) {
        e.preventDefault();
        var target = $($(this).attr('href'));
        if(target.length) {
            $('html, body').animate({
                scrollTop: target.offset().top - 80
            }, 800);
        }
    });
    
    // Animate numbers in stats section
    var counted = false;
    function animateNumbers() {
        if (!counted && isElementInViewport($('.stats-section'))) {
            counted = true;
            $('.stat-number').each(function() {
                var $this = $(this);
                var countTo = $this.attr('data-count');
                
                $({ countNum: $this.text() }).animate({
                    countNum: countTo
                }, {
                    duration: 2000,
                    easing: 'linear',
                    step: function() {
                        $this.text(Math.floor(this.countNum).toLocaleString());
                    },
                    complete: function() {
                        $this.text(this.countNum.toLocaleString());
                    }
                });
            });
        }
    }
    
    // Check if element is in viewport
    function isElementInViewport(el) {
        var rect = el[0].getBoundingClientRect();
        return (
            rect.top >= 0 &&
            rect.left >= 0 &&
            rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
            rect.right <= (window.innerWidth || document.documentElement.clientWidth)
        );
    }
    
    // Trigger animation on scroll
    $(window).scroll(function() {
        animateNumbers();
    });
    
    // Initial check
    animateNumbers();
    
    // Add hover effect to music cards
    $('.music-card').hover(
        function() {
            $(this).find('.play-overlay').css('opacity', '1');
            $(this).find('.play-btn').css('transform', 'scale(1)');
        },
        function() {
            $(this).find('.play-overlay').css('opacity', '0');
            $(this).find('.play-btn').css('transform', 'scale(0)');
        }
    );
});

// Functions pour les interactions
function playTrack(trackId) {
    console.log('Playing track:', trackId);
    // Ajouter la logique de lecture ici
    showNotification('🎵 Lecture du titre #' + trackId);
}

function toggleRadio() {
    const icon = document.getElementById('radioPlayIcon');
    if (icon.classList.contains('fa-play')) {
        icon.classList.remove('fa-play');
        icon.classList.add('fa-pause');
        showNotification('📻 Radio Tchadok Live démarrée');
    } else {
        icon.classList.remove('fa-pause');
        icon.classList.add('fa-play');
        showNotification('📻 Radio Tchadok Live arrêtée');
    }
}

function playRadio() {
    toggleRadio();
}

function showNotification(message) {
    // Créer une notification simple
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
    `;
    document.body.appendChild(notification);
    setTimeout(() => notification.remove(), 3000);
}
</script>

<?php include 'includes/footer.php'; ?>