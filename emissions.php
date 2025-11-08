<?php
/**
 * Page Émissions - Tchadok Platform
 * Programmes radio et podcasts innovants
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'assets/images/placeholders.php';

$pageTitle = 'Émissions';
$pageDescription = 'Découvrez nos émissions radio exclusives et podcasts. De la musique traditionnelle aux débats culturels, vivez la richesse du contenu tchadien.';

include 'includes/header.php';
?>

<!-- Hero Emissions Section -->
<section class="emissions-hero">
    <div class="floating-music-notes" style="top: 10%; left: 8%;">♪</div>
    <div class="floating-music-notes" style="top: 70%; right: 12%; animation-delay: 2s;">♫</div>
    <div class="floating-music-notes" style="bottom: 25%; left: 15%; animation-delay: 4s;">♪</div>
    <div class="floating-music-notes" style="top: 40%; right: 20%; animation-delay: 1s;">♫</div>
    
    <div class="container">
        <div class="row align-items-center min-vh-100">
            <div class="col-lg-6">
                <div class="hero-content">
                    <div class="emissions-badge">
                        <i class="fas fa-podcast"></i>
                        Contenu Exclusif
                    </div>
                    <h1>Émissions & Podcasts Tchadiens</h1>
                    <p>Plongez dans l'univers radiophonique tchadien avec nos émissions exclusives. Musique, culture, débats et interviews avec les personnalités qui font le Tchad d'aujourd'hui.</p>
                    
                    <div class="emissions-stats">
                        <div class="stat-item">
                            <div class="stat-number">24/7</div>
                            <div class="stat-label">Diffusion</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number">50+</div>
                            <div class="stat-label">Émissions</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number">1M+</div>
                            <div class="stat-label">Auditeurs</div>
                        </div>
                    </div>
                    
                    <div class="hero-buttons">
                        <button class="btn btn-primary-custom btn-lg me-3" onclick="playLiveRadio()">
                            <i class="fas fa-broadcast-tower me-2"></i>Écouter en Direct
                        </button>
                        <a href="#programmes" class="btn btn-secondary-custom btn-lg">
                            <i class="fas fa-calendar-alt me-2"></i>Voir le Programme
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="emissions-visual">
                    <div class="radio-waves">
                        <div class="wave"></div>
                        <div class="wave"></div>
                        <div class="wave"></div>
                        <div class="wave"></div>
                    </div>
                    <div class="microphone-icon">
                        <i class="fas fa-microphone"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Live Now Section -->
<section class="container my-5">
    <div class="live-now-section">
        <div class="row align-items-center">
            <div class="col-md-8">
                <div class="live-info">
                    <div class="live-indicator">
                        <span class="live-dot"></span>
                        EN DIRECT
                    </div>
                    <h3>Soirée Traditionnelle avec DJ Moussa</h3>
                    <p class="mb-2">Découvrez les perles de la musique traditionnelle tchadienne</p>
                    <div class="live-details">
                        <span><i class="fas fa-clock me-1"></i> 19h00 - 21h00</span>
                        <span class="mx-3">•</span>
                        <span><i class="fas fa-users me-1"></i> 2,547 auditeurs</span>
                    </div>
                </div>
            </div>
            <div class="col-md-4 text-end">
                <div class="live-controls">
                    <button class="btn btn-warning btn-lg rounded-circle me-2" onclick="toggleLivePlayer()">
                        <i class="fas fa-play" id="livePlayIcon"></i>
                    </button>
                    <div class="volume-control">
                        <i class="fas fa-volume-up text-muted me-2"></i>
                        <input type="range" class="volume-slider" min="0" max="100" value="75">
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Programme Section -->
<section class="container my-5" id="programmes">
    <div class="section-header">
        <h2>Programme de la Semaine</h2>
        <p class="text-muted">Ne manquez aucune de vos émissions préférées</p>
    </div>
    
    <div class="programme-tabs">
        <ul class="nav nav-pills justify-content-center mb-4" id="dayTabs">
            <li class="nav-item">
                <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#lundi">Lundi</button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="pill" data-bs-target="#mardi">Mardi</button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="pill" data-bs-target="#mercredi">Mercredi</button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="pill" data-bs-target="#jeudi">Jeudi</button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="pill" data-bs-target="#vendredi">Vendredi</button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="pill" data-bs-target="#weekend">Weekend</button>
            </li>
        </ul>
        
        <div class="tab-content">
            <div class="tab-pane fade show active" id="lundi">
                <div class="programme-day">
                    <div class="programme-slot">
                        <div class="time-slot">06:00 - 09:00</div>
                        <div class="emission-info">
                            <h4>Réveil Musical</h4>
                            <p>Commencez la journée avec les hits du moment</p>
                            <div class="emission-host">
                                <?php echo createAvatarPlaceholder('Abakar Mahamat', '#0066CC'); ?>
                                <span>Abakar Mahamat</span>
                            </div>
                        </div>
                        <div class="emission-actions">
                            <button class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-bell"></i> Rappel
                            </button>
                        </div>
                    </div>
                    
                    <div class="programme-slot">
                        <div class="time-slot">12:00 - 14:00</div>
                        <div class="emission-info">
                            <h4>Déjeuner Musical</h4>
                            <p>Musique douce et actualités culturelles</p>
                            <div class="emission-host">
                                <?php echo createAvatarPlaceholder('Fatima Hassan', '#FFD700'); ?>
                                <span>Fatima Hassan</span>
                            </div>
                        </div>
                        <div class="emission-actions">
                            <button class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-bell"></i> Rappel
                            </button>
                        </div>
                    </div>
                    
                    <div class="programme-slot featured">
                        <div class="time-slot">19:00 - 21:00</div>
                        <div class="emission-info">
                            <h4>Soirée Traditionnelle</h4>
                            <p>Découvrez les sons authentiques du Tchad</p>
                            <div class="emission-host">
                                <?php echo createAvatarPlaceholder('DJ Moussa', '#228B22'); ?>
                                <span>DJ Moussa</span>
                            </div>
                            <div class="featured-badge">
                                <i class="fas fa-star"></i> Émission Phare
                            </div>
                        </div>
                        <div class="emission-actions">
                            <button class="btn btn-sm btn-warning">
                                <i class="fas fa-play"></i> En Direct
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="tab-pane fade" id="mardi">
                <div class="programme-day">
                    <div class="programme-slot">
                        <div class="time-slot">14:00 - 16:00</div>
                        <div class="emission-info">
                            <h4>Spécial Artistes</h4>
                            <p>Interviews exclusives et coulisses de la musique</p>
                            <div class="emission-host">
                                <?php echo createAvatarPlaceholder('Sarah Abderamane', '#CC3333'); ?>
                                <span>Sarah Adam</span>
                            </div>
                        </div>
                        <div class="emission-actions">
                            <button class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-podcast"></i> Podcast
                            </button>
                        </div>
                    </div>
                    
                    <div class="programme-slot">
                        <div class="time-slot">21:00 - 23:00</div>
                        <div class="emission-info">
                            <h4>Urban Beats</h4>
                            <p>Le meilleur du rap et hip-hop tchadien</p>
                            <div class="emission-host">
                                <?php echo createAvatarPlaceholder('DJ Kouka', '#667eea'); ?>
                                <span>DJ Kalash</span>
                            </div>
                        </div>
                        <div class="emission-actions">
                            <button class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-bell"></i> Rappel
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Autres jours avec contenu similaire -->
            <div class="tab-pane fade" id="mercredi">
                <div class="text-center py-5">
                    <i class="fas fa-calendar-plus fa-3x text-muted mb-3"></i>
                    <h4>Programme Mercredi</h4>
                    <p class="text-muted">Contenu en cours de programmation...</p>
                </div>
            </div>
            
            <div class="tab-pane fade" id="jeudi">
                <div class="text-center py-5">
                    <i class="fas fa-calendar-plus fa-3x text-muted mb-3"></i>
                    <h4>Programme Jeudi</h4>
                    <p class="text-muted">Contenu en cours de programmation...</p>
                </div>
            </div>
            
            <div class="tab-pane fade" id="vendredi">
                <div class="text-center py-5">
                    <i class="fas fa-calendar-plus fa-3x text-muted mb-3"></i>
                    <h4>Programme Vendredi</h4>
                    <p class="text-muted">Contenu en cours de programmation...</p>
                </div>
            </div>
            
            <div class="tab-pane fade" id="weekend">
                <div class="text-center py-5">
                    <i class="fas fa-calendar-plus fa-3x text-muted mb-3"></i>
                    <h4>Programme Weekend</h4>
                    <p class="text-muted">Contenu en cours de programmation...</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Podcasts Section -->
<section class="container my-5">
    <div class="section-header">
        <h2>Podcasts Populaires</h2>
        <p class="text-muted">Écoutez quand vous voulez, où vous voulez</p>
    </div>
    
    <div class="row g-4">
        <div class="col-md-6 col-lg-4">
            <div class="podcast-card">
                <div class="podcast-cover">
                    <?php echo createPodcastCover('Culture Talk', 'Débats & Société', '#0066CC'); ?>
                    <div class="play-overlay">
                        <button class="play-btn" onclick="playPodcast(1)">
                            <i class="fas fa-play"></i>
                        </button>
                    </div>
                </div>
                <div class="podcast-info">
                    <h5>Culture Talk</h5>
                    <p class="text-muted">Débats sur la culture tchadienne moderne</p>
                    <div class="podcast-meta">
                        <span><i class="fas fa-microphone me-1"></i> 12 épisodes</span>
                        <span class="ms-3"><i class="fas fa-clock me-1"></i> 45 min</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 col-lg-4">
            <div class="podcast-card">
                <div class="podcast-cover">
                    <?php echo createPodcastCover('Artistes Story', 'Portraits & Interviews', '#FFD700'); ?>
                    <div class="play-overlay">
                        <button class="play-btn" onclick="playPodcast(2)">
                            <i class="fas fa-play"></i>
                        </button>
                    </div>
                </div>
                <div class="podcast-info">
                    <h5>Artistes Story</h5>
                    <p class="text-muted">Histoires inspirantes des artistes tchadiens</p>
                    <div class="podcast-meta">
                        <span><i class="fas fa-microphone me-1"></i> 8 épisodes</span>
                        <span class="ms-3"><i class="fas fa-clock me-1"></i> 30 min</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 col-lg-4">
            <div class="podcast-card">
                <div class="podcast-cover">
                    <?php echo createPodcastCover('Musique Roots', 'Traditions & Heritage', '#228B22'); ?>
                    <div class="play-overlay">
                        <button class="play-btn" onclick="playPodcast(3)">
                            <i class="fas fa-play"></i>
                        </button>
                    </div>
                </div>
                <div class="podcast-info">
                    <h5>Musique Roots</h5>
                    <p class="text-muted">Exploration des racines musicales</p>
                    <div class="podcast-meta">
                        <span><i class="fas fa-microphone me-1"></i> 15 épisodes</span>
                        <span class="ms-3"><i class="fas fa-clock me-1"></i> 25 min</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Emissions Categories -->
<section class="container my-5">
    <div class="section-header">
        <h2>Explorer par Catégories</h2>
        <p class="text-muted">Trouvez le contenu qui vous correspond</p>
    </div>
    
    <div class="categories-grid">
        <div class="category-card" data-category="musique">
            <div class="category-icon">
                <i class="fas fa-music"></i>
            </div>
            <h4>Musique</h4>
            <p>Émissions dédiées à tous les genres musicaux</p>
            <div class="category-count">24 émissions</div>
        </div>
        
        <div class="category-card" data-category="culture">
            <div class="category-icon">
                <i class="fas fa-globe-africa"></i>
            </div>
            <h4>Culture</h4>
            <p>Débats et discussions culturelles</p>
            <div class="category-count">12 émissions</div>
        </div>
        
        <div class="category-card" data-category="jeunesse">
            <div class="category-icon">
                <i class="fas fa-users"></i>
            </div>
            <h4>Jeunesse</h4>
            <p>Contenu dédié à la jeunesse tchadienne</p>
            <div class="category-count">8 émissions</div>
        </div>
        
        <div class="category-card" data-category="sport">
            <div class="category-icon">
                <i class="fas fa-futbol"></i>
            </div>
            <h4>Sport</h4>
            <p>Actualités et débats sportifs</p>
            <div class="category-count">6 émissions</div>
        </div>
    </div>
</section>

<style>
/* CSS personnalisé pour la page Émissions */
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

.emissions-hero {
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
    font-size: 2rem;
    color: rgba(255, 255, 255, 0.2);
    animation: float 6s ease-in-out infinite;
}

@keyframes float {
    0%, 100% { transform: translateY(0) rotate(0deg); }
    50% { transform: translateY(-20px) rotate(10deg); }
}

.emissions-badge {
    display: inline-flex;
    align-items: center;
    background: rgba(255, 215, 0, 0.2);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 50px;
    font-weight: 600;
    margin-bottom: 1rem;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 215, 0, 0.3);
}

.emissions-badge i {
    margin-right: 0.5rem;
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

.emissions-stats {
    display: flex;
    gap: 2rem;
    margin-bottom: 2rem;
    animation: fadeInUp 1.4s ease;
}

.stat-item {
    text-align: center;
    color: white;
}

.stat-number {
    font-size: 2rem;
    font-weight: 900;
    color: var(--jaune-solaire);
    line-height: 1;
}

.stat-label {
    font-size: 0.9rem;
    opacity: 0.9;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.hero-buttons {
    animation: fadeInUp 1.6s ease;
}

@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Radio Waves Animation */
.emissions-visual {
    display: flex;
    justify-content: center;
    align-items: center;
    height: 400px;
    position: relative;
}

.radio-waves {
    position: relative;
    width: 200px;
    height: 200px;
}

.wave {
    position: absolute;
    border: 2px solid rgba(255, 215, 0, 0.6);
    border-radius: 50%;
    animation: wave 2s ease-in-out infinite;
}

.wave:nth-child(1) {
    width: 60px;
    height: 60px;
    top: 70px;
    left: 70px;
    animation-delay: 0s;
}

.wave:nth-child(2) {
    width: 100px;
    height: 100px;
    top: 50px;
    left: 50px;
    animation-delay: 0.5s;
}

.wave:nth-child(3) {
    width: 140px;
    height: 140px;
    top: 30px;
    left: 30px;
    animation-delay: 1s;
}

.wave:nth-child(4) {
    width: 180px;
    height: 180px;
    top: 10px;
    left: 10px;
    animation-delay: 1.5s;
}

@keyframes wave {
    0% {
        transform: scale(0);
        opacity: 1;
    }
    100% {
        transform: scale(1);
        opacity: 0;
    }
}

.microphone-icon {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 3rem;
    color: var(--jaune-solaire);
    background: white;
    width: 80px;
    height: 80px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    animation: pulse 2s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { transform: translate(-50%, -50%) scale(1); }
    50% { transform: translate(-50%, -50%) scale(1.1); }
}

/* Live Now Section */
.live-now-section {
    background: linear-gradient(135deg, var(--gris-harmattan), #1a2332);
    color: white;
    padding: 2rem;
    border-radius: 20px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
    position: relative;
    overflow: hidden;
}

.live-now-section::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255, 215, 0, 0.1) 0%, transparent 70%);
    animation: pulse 3s ease-in-out infinite;
}

.live-indicator {
    display: inline-flex;
    align-items: center;
    background: var(--rouge-terre);
    color: white;
    padding: 0.3rem 0.8rem;
    border-radius: 50px;
    font-weight: 700;
    font-size: 0.8rem;
    margin-bottom: 0.5rem;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.live-dot {
    width: 8px;
    height: 8px;
    background: white;
    border-radius: 50%;
    margin-right: 0.5rem;
    animation: blink 1s ease-in-out infinite;
}

@keyframes blink {
    0%, 50% { opacity: 1; }
    51%, 100% { opacity: 0.3; }
}

.live-info h3 {
    color: white !important;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.live-info p {
    color: rgba(255, 255, 255, 0.9) !important;
}

.live-details {
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.9rem;
}

.volume-control {
    display: flex;
    align-items: center;
    margin-top: 0.5rem;
}

.volume-control .fa-volume-up {
    color: rgba(255, 255, 255, 0.8) !important;
}

.volume-slider {
    width: 100px;
    height: 4px;
    background: rgba(255, 255, 255, 0.3);
    border-radius: 2px;
    outline: none;
    -webkit-appearance: none;
}

.volume-slider::-webkit-slider-thumb {
    -webkit-appearance: none;
    width: 16px;
    height: 16px;
    background: var(--jaune-solaire);
    border-radius: 50%;
    cursor: pointer;
}

/* Programme Section */
.programme-tabs .nav-pills .nav-link {
    background: transparent;
    border: 2px solid #e9ecef;
    color: var(--gris-harmattan);
    font-weight: 600;
    margin: 0 0.25rem;
    border-radius: 50px;
    transition: all 0.3s ease;
}

.programme-tabs .nav-pills .nav-link.active,
.programme-tabs .nav-pills .nav-link:hover {
    background: linear-gradient(135deg, var(--bleu-tchadien), #0052a3);
    border-color: var(--bleu-tchadien);
    color: white;
    transform: translateY(-2px);
}

.programme-slot {
    display: flex;
    align-items: center;
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    border-left: 4px solid var(--bleu-tchadien);
}

.programme-slot:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
}

.programme-slot.featured {
    border-left-color: var(--jaune-solaire);
    background: linear-gradient(135deg, rgba(255, 215, 0, 0.05), rgba(0, 102, 204, 0.02));
}

.time-slot {
    font-weight: 700;
    color: var(--bleu-tchadien);
    font-size: 1.1rem;
    min-width: 140px;
    text-align: center;
    background: rgba(0, 102, 204, 0.1);
    padding: 0.5rem 1rem;
    border-radius: 10px;
    margin-right: 1.5rem;
}

.emission-info {
    flex: 1;
}

.emission-info h4 {
    margin-bottom: 0.5rem;
    color: var(--gris-harmattan);
    font-weight: 700;
}

.emission-info p {
    color: #6c757d;
    margin-bottom: 0.5rem;
    font-size: 0.95rem;
}

.emission-host {
    display: flex;
    align-items: center;
    margin-top: 0.5rem;
}

.emission-host img {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    margin-right: 0.5rem;
    border: 2px solid var(--jaune-solaire);
}

.emission-host span {
    font-weight: 600;
    color: var(--gris-harmattan);
    font-size: 0.9rem;
}

.featured-badge {
    background: linear-gradient(135deg, var(--jaune-solaire), #e6c200);
    color: var(--gris-harmattan);
    padding: 0.2rem 0.5rem;
    border-radius: 15px;
    font-size: 0.75rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    margin-top: 0.5rem;
}

.featured-badge i {
    margin-right: 0.3rem;
}

.emission-actions {
    min-width: 120px;
    text-align: right;
}

/* Podcast Cards */
.podcast-card {
    background: white;
    border-radius: 15px;
    overflow: hidden;
    transition: all 0.3s ease;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
    cursor: pointer;
}

.podcast-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
}

.podcast-cover {
    position: relative;
    overflow: hidden;
    height: 250px;
}

.podcast-cover img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.podcast-card:hover .podcast-cover img {
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

.podcast-card:hover .play-overlay {
    opacity: 1;
}

.play-btn {
    width: 60px;
    height: 60px;
    background: white;
    border: none;
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

.podcast-card:hover .play-btn {
    transform: scale(1);
}

.podcast-info {
    padding: 1.5rem;
}

.podcast-info h5 {
    margin-bottom: 0.5rem;
    color: var(--gris-harmattan);
    font-weight: 700;
}

.podcast-meta {
    font-size: 0.85rem;
    color: #6c757d;
    margin-top: 0.5rem;
}

/* Categories Grid */
.categories-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 2rem;
    margin-top: 2rem;
}

.category-card {
    background: white;
    border-radius: 20px;
    padding: 2rem;
    text-align: center;
    transition: all 0.3s ease;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
    cursor: pointer;
    position: relative;
    overflow: hidden;
}

.category-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, rgba(0, 102, 204, 0.05), rgba(255, 215, 0, 0.05));
    opacity: 0;
    transition: opacity 0.3s ease;
}

.category-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
}

.category-card:hover::before {
    opacity: 1;
}

.category-icon {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, var(--bleu-tchadien), #0052a3);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
    font-size: 2rem;
    color: white;
    transition: all 0.3s ease;
}

.category-card:hover .category-icon {
    background: linear-gradient(135deg, var(--jaune-solaire), #e6c200);
    color: var(--gris-harmattan);
    transform: scale(1.1);
}

.category-card h4 {
    color: var(--gris-harmattan);
    margin-bottom: 0.5rem;
    font-weight: 700;
}

.category-card p {
    color: #6c757d;
    margin-bottom: 1rem;
    font-size: 0.95rem;
}

.category-count {
    background: rgba(0, 102, 204, 0.1);
    color: var(--bleu-tchadien);
    padding: 0.3rem 0.8rem;
    border-radius: 15px;
    font-weight: 600;
    font-size: 0.85rem;
    display: inline-block;
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

/* Button Styles */
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

/* Mobile Responsiveness */
@media (max-width: 768px) {
    .hero-content h1 {
        font-size: 2.5rem;
    }
    
    .section-header h2 {
        font-size: 2rem;
    }
    
    .emissions-stats {
        gap: 1rem;
    }
    
    .stat-number {
        font-size: 1.5rem;
    }
    
    .programme-slot {
        flex-direction: column;
        text-align: center;
    }
    
    .time-slot {
        margin-right: 0;
        margin-bottom: 1rem;
        min-width: auto;
    }
    
    .emission-actions {
        margin-top: 1rem;
        min-width: auto;
        text-align: center;
    }
    
    .categories-grid {
        grid-template-columns: 1fr;
    }
    
    .floating-music-notes {
        font-size: 1.5rem;
    }
}
</style>

<script>
// JavaScript pour les interactions de la page Émissions
$(document).ready(function() {
    // Animation des catégories au scroll
    const observerOptions = {
        root: null,
        rootMargin: '0px',
        threshold: 0.1
    };

    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.animationDelay = `${Math.random() * 0.5}s`;
                entry.target.classList.add('animate__animated', 'animate__fadeInUp');
            }
        });
    }, observerOptions);

    // Observer les cartes de catégories
    document.querySelectorAll('.category-card').forEach(card => {
        observer.observe(card);
    });

    // Gestion des onglets de programme
    $('#dayTabs button').on('click', function() {
        const target = $(this).data('bs-target');
        showNotification(`📅 Programme du ${$(this).text()} affiché`);
    });

    // Gestion du volume
    $('.volume-slider').on('input', function() {
        const volume = $(this).val();
        $(this).css('background', `linear-gradient(to right, #FFD700 0%, #FFD700 ${volume}%, rgba(255,255,255,0.3) ${volume}%, rgba(255,255,255,0.3) 100%)`);
    });

    // Initialiser le style du slider de volume
    $('.volume-slider').trigger('input');
});

// Fonctions d'interaction
function toggleLivePlayer() {
    const icon = document.getElementById('livePlayIcon');
    if (icon.classList.contains('fa-play')) {
        icon.classList.remove('fa-play');
        icon.classList.add('fa-pause');
        showNotification('📻 Écoute en direct démarrée');
        startLiveVisualization();
    } else {
        icon.classList.remove('fa-pause');
        icon.classList.add('fa-play');
        showNotification('📻 Écoute en direct arrêtée');
        stopLiveVisualization();
    }
}

function playLiveRadio() {
    toggleLivePlayer();
}

function playPodcast(podcastId) {
    const podcastNames = {
        1: 'Culture Talk',
        2: 'Artistes Story',
        3: 'Musique Roots'
    };
    
    showNotification(`🎧 Lecture du podcast "${podcastNames[podcastId]}"`);
    console.log('Playing podcast:', podcastId);
}

function startLiveVisualization() {
    const waves = document.querySelectorAll('.wave');
    waves.forEach((wave, index) => {
        wave.style.animationPlayState = 'running';
        wave.style.animationDelay = `${index * 0.5}s`;
    });
}

function stopLiveVisualization() {
    const waves = document.querySelectorAll('.wave');
    waves.forEach(wave => {
        wave.style.animationPlayState = 'paused';
    });
}

// Gestion des catégories
document.querySelectorAll('.category-card').forEach(card => {
    card.addEventListener('click', function() {
        const category = this.dataset.category;
        showNotification(`🎯 Filtrage par catégorie: ${category}`);
        // Ici on pourrait ajouter la logique de filtrage
    });
});

// Fonction de notification réutilisée
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
        animation: slideInRight 0.3s ease;
    `;
    document.body.appendChild(notification);
    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Animation CSS pour les notifications
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOutRight {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
`;
document.head.appendChild(style);

// Rappels d'émissions
document.querySelectorAll('.btn-outline-primary').forEach(btn => {
    if (btn.innerHTML.includes('Rappel')) {
        btn.addEventListener('click', function() {
            this.innerHTML = '<i class="fas fa-check"></i> Programmé';
            this.classList.remove('btn-outline-primary');
            this.classList.add('btn-success');
            showNotification('⏰ Rappel programmé avec succès');
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>