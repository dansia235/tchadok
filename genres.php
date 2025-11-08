<?php
/**
 * Page des genres musicaux - Tchadok Platform
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';

$pageTitle = 'Genres Musicaux';
$pageDescription = 'Explorez la diversité musicale du Tchad à travers ses différents genres.';

// Genres musicaux tchadiens
$genres = [
    [
        'id' => 1,
        'name' => 'Afrobeat',
        'description' => 'Fusion moderne entre rythmes africains et sonorités contemporaines',
        'color' => '#FF6B6B',
        'icon' => 'fas fa-drum',
        'track_count' => rand(50, 200),
        'popularity' => 95
    ],
    [
        'id' => 2,
        'name' => 'Traditionnel',
        'description' => 'Musique ancestrale tchadienne préservant notre héritage culturel',
        'color' => '#4ECDC4',
        'icon' => 'fas fa-music',
        'track_count' => rand(30, 150),
        'popularity' => 88
    ],
    [
        'id' => 3,
        'name' => 'Hip-Hop',
        'description' => 'Rap tchadien exprimant les réalités de la jeunesse',
        'color' => '#45B7D1',
        'icon' => 'fas fa-microphone',
        'track_count' => rand(40, 180),
        'popularity' => 92
    ],
    [
        'id' => 4,
        'name' => 'Gospel',
        'description' => 'Musique spirituelle et de louange en langues locales',
        'color' => '#F7DC6F',
        'icon' => 'fas fa-cross',
        'track_count' => rand(25, 120),
        'popularity' => 85
    ],
    [
        'id' => 5,
        'name' => 'Reggae',
        'description' => 'Reggae tchadien avec des influences rastafari locales',
        'color' => '#58D68D',
        'icon' => 'fas fa-leaf',
        'track_count' => rand(20, 80),
        'popularity' => 75
    ],
    [
        'id' => 6,
        'name' => 'R&B',
        'description' => 'Rhythm and Blues moderne avec des touches tchadiennes',
        'color' => '#BB8FCE',
        'icon' => 'fas fa-heart',
        'track_count' => rand(30, 100),
        'popularity' => 80
    ],
    [
        'id' => 7,
        'name' => 'Folk',
        'description' => 'Musique folklorique des différentes régions du Tchad',
        'color' => '#F4A460',
        'icon' => 'fas fa-guitar',
        'track_count' => rand(15, 60),
        'popularity' => 70
    ],
    [
        'id' => 8,
        'name' => 'Zouk',
        'description' => 'Zouk tropical adapté aux goûts tchadiens',
        'color' => '#FF9F43',
        'icon' => 'fas fa-sun',
        'track_count' => rand(25, 90),
        'popularity' => 78
    ]
];

include 'includes/header.php';
?>

<div class="genres-page">
    <!-- Hero Section -->
    <section class="genres-hero py-5" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
        <div class="container">
            <div class="row align-items-center text-white">
                <div class="col-lg-8">
                    <h1 class="display-4 fw-bold mb-3">
                        <i class="fas fa-music me-3"></i>
                        Genres Musicaux
                    </h1>
                    <p class="lead mb-4">
                        Découvrez la richesse et la diversité de la musique tchadienne. 
                        Chaque genre raconte une partie de notre histoire et de notre culture.
                    </p>
                    <div class="d-flex gap-3">
                        <a href="#genres-grid" class="btn btn-light btn-lg">
                            <i class="fas fa-compass me-2"></i>
                            Explorer
                        </a>
                        <a href="<?php echo SITE_URL; ?>/search.php" class="btn btn-outline-light btn-lg">
                            <i class="fas fa-search me-2"></i>
                            Rechercher
                        </a>
                    </div>
                </div>
                <div class="col-lg-4 text-center">
                    <div class="genres-illustration">
                        <i class="fas fa-compact-disc fa-spin" style="font-size: 8rem; opacity: 0.3;"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Genres populaires (stats) -->
    <section class="py-4 bg-light">
        <div class="container">
            <div class="row text-center">
                <div class="col-md-3 col-6 mb-3">
                    <div class="stat-item">
                        <h3 class="text-primary fw-bold"><?php echo count($genres); ?></h3>
                        <p class="mb-0 text-muted">Genres disponibles</p>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-3">
                    <div class="stat-item">
                        <h3 class="text-success fw-bold"><?php echo array_sum(array_column($genres, 'track_count')); ?></h3>
                        <p class="mb-0 text-muted">Titres au total</p>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-3">
                    <div class="stat-item">
                        <h3 class="text-warning fw-bold"><?php echo rand(15, 35); ?></h3>
                        <p class="mb-0 text-muted">Artistes actifs</p>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-3">
                    <div class="stat-item">
                        <h3 class="text-info fw-bold"><?php echo rand(500, 999); ?>k</h3>
                        <p class="mb-0 text-muted">Écoutes mensuelles</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Grille des genres -->
    <section id="genres-grid" class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="display-6 fw-bold">Explorez par Genre</h2>
                <p class="lead text-muted">Chaque genre a sa propre identité et ses artistes emblématiques</p>
            </div>
            
            <div class="row g-4">
                <?php foreach ($genres as $genre): ?>
                <div class="col-lg-3 col-md-6">
                    <div class="genre-card" style="--genre-color: <?php echo $genre['color']; ?>">
                        <div class="genre-content">
                            <div class="genre-icon">
                                <i class="<?php echo $genre['icon']; ?>"></i>
                            </div>
                            <h4 class="genre-name"><?php echo $genre['name']; ?></h4>
                            <p class="genre-description"><?php echo $genre['description']; ?></p>
                            
                            <div class="genre-stats">
                                <div class="stat">
                                    <i class="fas fa-music me-2"></i>
                                    <?php echo $genre['track_count']; ?> titres
                                </div>
                                <div class="stat">
                                    <i class="fas fa-fire me-2"></i>
                                    <?php echo $genre['popularity']; ?>% populaire
                                </div>
                            </div>
                            
                            <div class="genre-actions">
                                <button class="btn-genre-primary" onclick="exploreGenre(<?php echo $genre['id']; ?>, '<?php echo $genre['name']; ?>')">
                                    <i class="fas fa-play me-2"></i>
                                    Écouter
                                </button>
                                <button class="btn-genre-secondary" onclick="searchGenre('<?php echo $genre['name']; ?>')">
                                    <i class="fas fa-search me-2"></i>
                                    Explorer
                                </button>
                            </div>
                        </div>
                        
                        <!-- Barre de popularité -->
                        <div class="popularity-bar">
                            <div class="popularity-fill" style="width: <?php echo $genre['popularity']; ?>%"></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    
    <!-- Section artistes par genre -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="h3 fw-bold">Artistes par Genre</h2>
                <p class="text-muted">Découvrez les talents de chaque style musical</p>
            </div>
            
            <div class="row">
                <div class="col-12">
                    <!-- Tabs des genres -->
                    <ul class="nav nav-pills justify-content-center mb-4" id="genreTabs" role="tablist">
                        <?php foreach (array_slice($genres, 0, 4) as $index => $genre): ?>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?php echo $index === 0 ? 'active' : ''; ?>" 
                                    id="tab-<?php echo $genre['id']; ?>" 
                                    data-bs-toggle="pill" 
                                    data-bs-target="#content-<?php echo $genre['id']; ?>" 
                                    type="button" 
                                    role="tab">
                                <i class="<?php echo $genre['icon']; ?> me-2"></i>
                                <?php echo $genre['name']; ?>
                            </button>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    
                    <!-- Contenu des tabs -->
                    <div class="tab-content" id="genreTabContent">
                        <?php foreach (array_slice($genres, 0, 4) as $index => $genre): ?>
                        <div class="tab-pane fade <?php echo $index === 0 ? 'show active' : ''; ?>" 
                             id="content-<?php echo $genre['id']; ?>" 
                             role="tabpanel">
                            <div class="row g-3">
                                <?php for ($i = 1; $i <= 4; $i++): ?>
                                <div class="col-lg-3 col-md-6">
                                    <div class="artist-card">
                                        <div class="artist-image">
                                            <img src="<?php echo SITE_URL; ?>/assets/images/icon-music.svg" 
                                                 alt="Artiste" class="rounded-circle">
                                            <div class="play-overlay">
                                                <button class="btn btn-primary btn-sm rounded-circle"
                                                        onclick="playTrack(<?php echo $i; ?>)">
                                                    <i class="fas fa-play"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="artist-info">
                                            <h6 class="artist-name">Artiste <?php echo $genre['name']; ?> <?php echo $i; ?></h6>
                                            <p class="artist-genre"><?php echo $genre['name']; ?></p>
                                            <div class="artist-stats">
                                                <small class="text-muted">
                                                    <i class="fas fa-play me-1"></i>
                                                    <?php echo formatNumber(rand(1000, 50000)); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Call to action -->
    <section class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8 text-center">
                    <div class="cta-box p-5 rounded" style="background: linear-gradient(135deg, #FFD700 0%, #0066CC 100%);">
                        <h2 class="text-white fw-bold mb-3">Vous êtes artiste ?</h2>
                        <p class="text-white lead mb-4">
                            Partagez votre talent avec le monde entier. 
                            Rejoignez la communauté Tchadok et faites découvrir votre genre musical.
                        </p>
                        <div class="d-flex gap-3 justify-content-center">
                            <a href="<?php echo SITE_URL; ?>/artist-signup.php" class="btn btn-light btn-lg">
                                <i class="fas fa-microphone me-2"></i>
                                Devenir Artiste
                            </a>
                            <a href="<?php echo SITE_URL; ?>/upload.php" class="btn btn-outline-light btn-lg">
                                <i class="fas fa-upload me-2"></i>
                                Uploader
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<style>
.genre-card {
    background: white;
    border-radius: 20px;
    padding: 2rem;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    height: 100%;
    display: flex;
    flex-direction: column;
}

.genre-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.15);
}

.genre-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--genre-color);
}

.genre-content {
    flex: 1;
    display: flex;
    flex-direction: column;
}

.genre-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: var(--genre-color);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1.5rem;
    color: white;
    font-size: 1.5rem;
}

.genre-name {
    font-size: 1.5rem;
    font-weight: bold;
    margin-bottom: 1rem;
    color: #333;
}

.genre-description {
    color: #666;
    margin-bottom: 1.5rem;
    flex: 1;
    line-height: 1.6;
}

.genre-stats {
    margin-bottom: 1.5rem;
}

.genre-stats .stat {
    color: #888;
    font-size: 0.9rem;
    margin-bottom: 0.5rem;
}

.genre-actions {
    display: flex;
    gap: 0.5rem;
}

.btn-genre-primary, .btn-genre-secondary {
    flex: 1;
    padding: 0.75rem;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-genre-primary {
    background: var(--genre-color);
    color: white;
}

.btn-genre-primary:hover {
    transform: scale(1.05);
    filter: brightness(110%);
}

.btn-genre-secondary {
    background: rgba(0,0,0,0.05);
    color: #666;
}

.btn-genre-secondary:hover {
    background: rgba(0,0,0,0.1);
    color: #333;
}

.popularity-bar {
    height: 4px;
    background: rgba(0,0,0,0.1);
    border-radius: 2px;
    margin-top: 1rem;
    overflow: hidden;
}

.popularity-fill {
    height: 100%;
    background: var(--genre-color);
    border-radius: 2px;
    transition: width 1s ease;
}

.artist-card {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    text-align: center;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    transition: transform 0.3s ease;
}

.artist-card:hover {
    transform: translateY(-5px);
}

.artist-image {
    position: relative;
    display: inline-block;
    margin-bottom: 1rem;
}

.artist-image img {
    width: 80px;
    height: 80px;
    object-fit: cover;
}

.play-overlay {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.artist-image:hover .play-overlay {
    opacity: 1;
}

.artist-name {
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.artist-genre {
    color: #666;
    font-size: 0.9rem;
    margin-bottom: 0.5rem;
}

.nav-pills .nav-link {
    color: #666;
    border-radius: 25px;
    padding: 0.75rem 1.5rem;
    margin: 0 0.25rem;
}

.nav-pills .nav-link.active {
    background: linear-gradient(135deg, #0066CC, #FFD700);
    color: white;
}

.cta-box {
    position: relative;
    overflow: hidden;
}

.cta-box::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="2" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="40" r="1.5" fill="rgba(255,255,255,0.1)"/><circle cx="60" cy="80" r="1" fill="rgba(255,255,255,0.1)"/></svg>');
}

@media (max-width: 768px) {
    .genre-card {
        padding: 1.5rem;
    }
    
    .genre-actions {
        flex-direction: column;
    }
    
    .btn-genre-primary, .btn-genre-secondary {
        flex: none;
    }
}
</style>

<script>
function exploreGenre(genreId, genreName) {
    console.log('Explore genre:', genreId, genreName);
    
    const notification = document.createElement('div');
    notification.innerHTML = `
        <div style="display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-music" style="font-size: 20px;"></i>
            <div>
                <div><strong>🎵 Exploration du genre</strong></div>
                <small>${genreName}</small><br>
                <small>Lecture des titres populaires...</small>
            </div>
        </div>
    `;
    notification.style.cssText = `
        position: fixed; 
        top: 20px; 
        right: 20px; 
        background: #17a2b8; 
        color: white; 
        padding: 15px 20px; 
        border-radius: 8px; 
        z-index: 10000;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        max-width: 300px;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    `;
    document.body.appendChild(notification);
    setTimeout(() => notification.remove(), 4000);
}

function searchGenre(genreName) {
    window.location.href = `<?php echo SITE_URL; ?>/search.php?q=${encodeURIComponent(genreName)}`;
}

// Animation d'entrée pour les cartes
document.addEventListener('DOMContentLoaded', function() {
    const genreCards = document.querySelectorAll('.genre-card');
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry, index) => {
            if (entry.isIntersecting) {
                setTimeout(() => {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }, index * 100);
            }
        });
    });
    
    genreCards.forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px)';
        card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(card);
    });
});
</script>

<?php include 'includes/footer.php'; ?>