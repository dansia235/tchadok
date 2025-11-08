<?php
/**
 * Page des artistes - Tchadok Platform
 * Design innovant et moderne
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'assets/images/placeholders.php';

$pageTitle = 'Artistes';
$pageDescription = 'Découvrez les artistes tchadiens talentueux sur Tchadok.';

include 'includes/header.php';
?>

<!-- Artists Hero Section -->
<section class="artists-hero-section">
    <div class="floating-music-notes" style="top: 15%; left: 8%;">♪</div>
    <div class="floating-music-notes" style="top: 65%; right: 10%; animation-delay: 2s;">♫</div>
    <div class="floating-music-notes" style="bottom: 25%; left: 15%; animation-delay: 4s;">♪</div>
    
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <div class="hero-content">
                    <h1>Artistes Tchadiens</h1>
                    <p>Découvrez les voix authentiques du Tchad. Des légendes aux nouvelles étoiles, explorez la richesse musicale de notre pays.</p>
                    
                    <div class="search-bar-container">
                        <div class="search-bar">
                            <i class="fas fa-search"></i>
                            <input type="text" placeholder="Rechercher un artiste..." id="artistSearch">
                            <button class="search-btn">
                                <i class="fas fa-microphone"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="hero-stats">
                    <div class="stat-item">
                        <div class="stat-number" data-count="247">0</div>
                        <div class="stat-label">Artistes</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number" data-count="15">0</div>
                        <div class="stat-label">Genres</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number" data-count="1205">0</div>
                        <div class="stat-label">Titres</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Filters and Search -->
<section class="container my-5">
    <div class="filters-container">
        <div class="filter-tabs">
            <button class="filter-tab active" data-filter="all">
                <i class="fas fa-globe"></i>
                Tous
            </button>
            <button class="filter-tab" data-filter="verified">
                <i class="fas fa-check-circle"></i>
                Vérifiés
            </button>
            <button class="filter-tab" data-filter="trending">
                <i class="fas fa-fire"></i>
                Tendances
            </button>
            <button class="filter-tab" data-filter="new">
                <i class="fas fa-star"></i>
                Nouveaux
            </button>
        </div>
        
        <div class="filter-options">
            <div class="genre-filters">
                <span class="filter-label">Genres:</span>
                <div class="genre-tags">
                    <button class="genre-tag active" data-genre="all">Tous</button>
                    <button class="genre-tag" data-genre="afrobeat">Afrobeat</button>
                    <button class="genre-tag" data-genre="hiphop">Hip-Hop</button>
                    <button class="genre-tag" data-genre="gospel">Gospel</button>
                    <button class="genre-tag" data-genre="traditional">Traditionnel</button>
                    <button class="genre-tag" data-genre="rnb">R&B</button>
                </div>
            </div>
            
            <div class="sort-options">
                <select class="sort-select" id="sortSelect">
                    <option value="popularity">Popularité</option>
                    <option value="alphabetical">Alphabétique</option>
                    <option value="newest">Plus récents</option>
                    <option value="plays">Plus écoutés</option>
                </select>
            </div>
        </div>
    </div>
</section>
<!-- Featured Artists Spotlight -->
<section class="container my-5">
    <div class="section-header">
        <h2>Artistes En Vedette</h2>
        <p class="text-muted">Les stars qui font vibrer le Tchad</p>
    </div>
    
    <div class="featured-artists-grid">
        <?php
        $featuredArtists = [
            ['name' => 'Mounira Mitchala', 'genre' => 'Soul • R&B', 'verified' => true, 'trending' => true, 'plays' => '2.3M', 'followers' => '89K', 'color' => '0066CC', 'bg' => 'FFFFFF'],
            ['name' => 'Clément Masdongar', 'genre' => 'Afrobeat', 'verified' => true, 'trending' => false, 'plays' => '1.8M', 'followers' => '67K', 'color' => 'FFD700', 'bg' => '000000'],
            ['name' => 'H2O Assoumane', 'genre' => 'Hip Hop', 'verified' => true, 'trending' => true, 'plays' => '1.5M', 'followers' => '45K', 'color' => '228B22', 'bg' => 'FFFFFF'],
        ];
        
        foreach ($featuredArtists as $index => $artist):
        ?>
        <div class="featured-artist-card">
            <div class="artist-image-container">
                <?php echo createArtistAvatar($artist['name'], $artist['genre'], '#' . $artist['color']); ?>
                <div class="artist-overlay">
                    <button class="play-btn-large" onclick="playArtist(<?php echo $index + 1; ?>)">
                        <i class="fas fa-play"></i>
                    </button>
                </div>
                <?php if ($artist['verified']): ?>
                <div class="verified-badge">
                    <i class="fas fa-check-circle"></i>
                </div>
                <?php endif; ?>
                <?php if ($artist['trending']): ?>
                <div class="trending-badge">
                    <i class="fas fa-fire"></i>
                    Tendance
                </div>
                <?php endif; ?>
            </div>
            
            <div class="artist-info">
                <h3><?php echo htmlspecialchars($artist['name']); ?></h3>
                <p class="genre"><?php echo htmlspecialchars($artist['genre']); ?></p>
                
                <div class="artist-stats">
                    <div class="stat">
                        <i class="fas fa-play"></i>
                        <span><?php echo $artist['plays']; ?></span>
                    </div>
                    <div class="stat">
                        <i class="fas fa-users"></i>
                        <span><?php echo $artist['followers']; ?></span>
                    </div>
                </div>
                
                <div class="artist-actions">
                    <button class="btn btn-primary-custom">
                        <i class="fas fa-play me-2"></i>Écouter
                    </button>
                    <button class="btn-icon-action" onclick="followArtist(<?php echo $index + 1; ?>)">
                        <i class="fas fa-plus"></i>
                    </button>
                    <button class="btn-icon-action" onclick="shareArtist(<?php echo $index + 1; ?>)">
                        <i class="fas fa-share-alt"></i>
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- All Artists Grid -->
<section class="container my-5">
    <div class="section-header">
        <h2>Tous les Artistes</h2>
        <div class="view-toggle">
            <button class="view-btn active" data-view="grid">
                <i class="fas fa-th"></i>
            </button>
            <button class="view-btn" data-view="list">
                <i class="fas fa-list"></i>
            </button>
        </div>
    </div>
    
    <div class="artists-grid" id="artistsContainer">
        <?php
        $allArtists = [
            ['name' => 'Maimouna Youssouf', 'genre' => 'Traditionnel', 'verified' => false, 'new' => false, 'plays' => '892K', 'tracks' => 15, 'color' => 'CC3333'],
            ['name' => 'Caleb Rimtobaye', 'genre' => 'Gospel', 'verified' => true, 'new' => false, 'plays' => '745K', 'tracks' => 23, 'color' => '667eea'],
            ['name' => 'Abakar Sultan', 'genre' => 'Jazz Fusion', 'verified' => false, 'new' => true, 'plays' => '234K', 'tracks' => 8, 'color' => 'f093fb'],
            ['name' => 'Fatima Al-Zahra', 'genre' => 'Pop Afro', 'verified' => true, 'new' => false, 'plays' => '1.2M', 'tracks' => 31, 'color' => '43e97b'],
            ['name' => 'Omar Tchango', 'genre' => 'Reggae', 'verified' => false, 'new' => true, 'plays' => '156K', 'tracks' => 5, 'color' => 'fa709a'],
            ['name' => 'Achta Mahamat', 'genre' => 'R&B', 'verified' => true, 'new' => false, 'plays' => '678K', 'tracks' => 19, 'color' => 'a8edea'],
            ['name' => 'DJ Tchad Mix', 'genre' => 'Electronic', 'verified' => false, 'new' => true, 'plays' => '445K', 'tracks' => 12, 'color' => '4158d0'],
            ['name' => 'Banda Naba', 'genre' => 'Afrobeat', 'verified' => true, 'new' => false, 'plays' => '923K', 'tracks' => 27, 'color' => 'ff9a9e'],
            ['name' => 'Sarah Koulagna', 'genre' => 'Folk', 'verified' => false, 'new' => true, 'plays' => '321K', 'tracks' => 14, 'color' => 'fecfef'],
        ];
        
        foreach ($allArtists as $index => $artist):
        ?>
        <div class="artist-card" data-verified="<?php echo $artist['verified'] ? 'true' : 'false'; ?>" data-new="<?php echo $artist['new'] ? 'true' : 'false'; ?>" data-genre="<?php echo strtolower(str_replace(' ', '', $artist['genre'])); ?>">
            <div class="artist-card-image">
                <?php echo createArtistAvatar($artist['name'], $artist['genre'], '#' . $artist['color']); ?>
                <div class="artist-card-overlay">
                    <button class="play-btn-card" onclick="playArtist(<?php echo $index + 4; ?>)">
                        <i class="fas fa-play"></i>
                    </button>
                </div>
                <?php if ($artist['verified']): ?>
                <div class="verified-mini">
                    <i class="fas fa-check-circle"></i>
                </div>
                <?php endif; ?>
                <?php if ($artist['new']): ?>
                <div class="new-badge">
                    Nouveau
                </div>
                <?php endif; ?>
            </div>
            
            <div class="artist-card-content">
                <h4><?php echo htmlspecialchars($artist['name']); ?></h4>
                <p class="genre"><?php echo htmlspecialchars($artist['genre']); ?></p>
                
                <div class="mini-stats">
                    <span><i class="fas fa-play"></i> <?php echo $artist['plays']; ?></span>
                    <span><i class="fas fa-music"></i> <?php echo $artist['tracks']; ?> titres</span>
                </div>
                
                <div class="quick-actions">
                    <button class="quick-btn" onclick="quickPlay(<?php echo $index + 4; ?>)" title="Lecture rapide">
                        <i class="fas fa-play"></i>
                    </button>
                    <button class="quick-btn" onclick="addToLibrary(<?php echo $index + 4; ?>)" title="Ajouter à ma bibliothèque">
                        <i class="fas fa-plus"></i>
                    </button>
                    <button class="quick-btn" onclick="shareArtist(<?php echo $index + 4; ?>)" title="Partager">
                        <i class="fas fa-share-alt"></i>
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- Load More Button -->
<section class="container text-center my-5">
    <button class="btn btn-secondary-custom btn-lg" id="loadMoreBtn" onclick="loadMoreArtists()">
        <i class="fas fa-plus-circle me-2"></i>
        Charger plus d'artistes
    </button>
    <p class="text-muted mt-3">Affichage de <span id="currentCount">12</span> sur <span id="totalCount">247</span> artistes</p>
</section>

<!-- Newsletter Section -->
<section class="newsletter-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h3>Restez connecté avec vos artistes préférés</h3>
                <p>Recevez les dernières actualités, sorties et concerts de vos artistes tchadiens favoris.</p>
            </div>
            <div class="col-lg-6">
                <div class="newsletter-form">
                    <div class="input-group">
                        <input type="email" class="form-control" placeholder="Votre adresse email">
                        <button class="btn btn-primary-custom" type="button">
                            <i class="fas fa-paper-plane me-2"></i>S'abonner
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
/* Artists Page Custom Styles */
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

/* Hero Section */
.artists-hero-section {
    background: linear-gradient(135deg, rgba(0, 102, 204, 0.85), rgba(255, 215, 0, 0.6)), 
                url('data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"%3E%3Cpath fill="%230066CC" fill-opacity="0.1" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,122.7C672,117,768,139,864,133.3C960,128,1056,96,1152,90.7C1248,85,1344,107,1392,117.3L1440,128L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"%3E%3C/path%3E%3C/svg%3E');
    background-size: cover;
    background-position: center;
    padding: 6rem 0 4rem;
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

.hero-content {
    color: white;
    z-index: 2;
    position: relative;
}

.hero-content h1 {
    font-size: 4rem;
    font-weight: 900;
    color: white;
    margin-bottom: 1.5rem;
    text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
    animation: fadeInUp 1s ease;
}

.hero-content p {
    font-size: 1.3rem;
    margin-bottom: 2.5rem;
    opacity: 0.9;
    animation: fadeInUp 1.2s ease;
}

.search-bar-container {
    animation: fadeInUp 1.4s ease;
}

.search-bar {
    background: rgba(255, 255, 255, 0.95);
    border-radius: 25px;
    padding: 0.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    backdrop-filter: blur(10px);
    max-width: 500px;
}

.search-bar i {
    color: var(--gris-harmattan);
    margin-left: 1rem;
}

.search-bar input {
    border: none;
    outline: none;
    background: transparent;
    flex-grow: 1;
    padding: 0.75rem 0;
    font-size: 1.1rem;
    color: var(--gris-harmattan);
}

.search-bar input::placeholder {
    color: #6c757d;
}

.search-btn {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--bleu-tchadien), #0052a3);
    border: none;
    color: white;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.search-btn:hover {
    transform: scale(1.1);
    box-shadow: 0 5px 15px rgba(0, 102, 204, 0.4);
}

.hero-stats {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    padding: 2rem;
    border: 1px solid rgba(255, 255, 255, 0.2);
    z-index: 2;
    position: relative;
}

.stat-item {
    text-align: center;
    margin-bottom: 1.5rem;
}

.stat-item:last-child {
    margin-bottom: 0;
}

.stat-number {
    font-size: 2.5rem;
    font-weight: 900;
    color: var(--jaune-solaire);
    margin-bottom: 0.5rem;
}

.stat-label {
    color: white;
    font-weight: 600;
    opacity: 0.9;
}

/* Filters */
.filters-container {
    background: white;
    border-radius: 20px;
    padding: 2rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    margin-bottom: 3rem;
}

.filter-tabs {
    display: flex;
    gap: 1rem;
    margin-bottom: 2rem;
    flex-wrap: wrap;
}

.filter-tab {
    background: transparent;
    border: 2px solid #e9ecef;
    color: var(--gris-harmattan);
    padding: 0.75rem 1.5rem;
    border-radius: 25px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.filter-tab:hover {
    border-color: var(--bleu-tchadien);
    color: var(--bleu-tchadien);
}

.filter-tab.active {
    background: var(--bleu-tchadien);
    border-color: var(--bleu-tchadien);
    color: white;
}

.filter-options {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 2rem;
}

.genre-filters {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.filter-label {
    font-weight: 600;
    color: var(--gris-harmattan);
}

.genre-tags {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.genre-tag {
    background: transparent;
    border: 1px solid #e9ecef;
    color: #6c757d;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.9rem;
}

.genre-tag:hover {
    border-color: var(--jaune-solaire);
    color: var(--gris-harmattan);
}

.genre-tag.active {
    background: var(--jaune-solaire);
    border-color: var(--jaune-solaire);
    color: var(--gris-harmattan);
    font-weight: 600;
}

.sort-select {
    border: 2px solid #e9ecef;
    border-radius: 12px;
    padding: 0.75rem 1rem;
    font-weight: 600;
    color: var(--gris-harmattan);
    background: white;
    cursor: pointer;
    transition: all 0.3s ease;
}

.sort-select:focus {
    border-color: var(--bleu-tchadien);
    outline: none;
    box-shadow: 0 0 0 0.2rem rgba(0, 102, 204, 0.25);
}

/* Section Headers */
.section-header {
    text-align: center;
    margin-bottom: 3rem;
    position: relative;
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

.view-toggle {
    position: absolute;
    right: 0;
    top: 50%;
    transform: translateY(-50%);
    display: flex;
    gap: 0.5rem;
}

.view-btn {
    width: 40px;
    height: 40px;
    border: 2px solid #e9ecef;
    background: white;
    color: #6c757d;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.view-btn:hover {
    border-color: var(--bleu-tchadien);
    color: var(--bleu-tchadien);
}

.view-btn.active {
    background: var(--bleu-tchadien);
    border-color: var(--bleu-tchadien);
    color: white;
}

/* Featured Artists Grid */
.featured-artists-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 2rem;
    margin-bottom: 4rem;
}

.featured-artist-card {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    position: relative;
}

.featured-artist-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 25px 60px rgba(0, 0, 0, 0.15);
}

.artist-image-container {
    position: relative;
    height: 300px;
    overflow: hidden;
}

.artist-image-container img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.featured-artist-card:hover .artist-image-container img {
    transform: scale(1.05);
}

.artist-overlay {
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

.featured-artist-card:hover .artist-overlay {
    opacity: 1;
}

.play-btn-large {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: white;
    border: none;
    color: var(--bleu-tchadien);
    font-size: 2rem;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
}

.play-btn-large:hover {
    transform: scale(1.1);
}

.verified-badge {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: var(--vert-savane);
    color: white;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.trending-badge {
    position: absolute;
    top: 1rem;
    left: 1rem;
    background: var(--rouge-terre);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

.artist-info {
    padding: 2rem;
}

.artist-info h3 {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    color: var(--gris-harmattan);
}

.artist-info .genre {
    color: #6c757d;
    margin-bottom: 1.5rem;
    font-size: 1rem;
}

.artist-stats {
    display: flex;
    gap: 2rem;
    margin-bottom: 2rem;
}

.artist-stats .stat {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #6c757d;
    font-size: 0.9rem;
}

.artist-stats .stat i {
    color: var(--bleu-tchadien);
}

.artist-actions {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.btn-primary-custom {
    background: linear-gradient(135deg, var(--bleu-tchadien), #0052a3);
    border: none;
    color: white;
    font-weight: 600;
    padding: 0.75rem 1.5rem;
    border-radius: 12px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(0, 102, 204, 0.3);
    flex-grow: 1;
}

.btn-primary-custom:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 25px rgba(0, 102, 204, 0.4);
    color: white;
}

.btn-icon-action {
    width: 45px;
    height: 45px;
    border: 2px solid #e9ecef;
    background: white;
    color: #6c757d;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.btn-icon-action:hover {
    border-color: var(--jaune-solaire);
    background: var(--jaune-solaire);
    color: var(--gris-harmattan);
}

/* Artists Grid */
.artists-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 2rem;
}

.artist-card {
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    cursor: pointer;
}

.artist-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
}

.artist-card-image {
    position: relative;
    height: 200px;
    overflow: hidden;
}

.artist-card-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.artist-card:hover .artist-card-image img {
    transform: scale(1.1);
}

.artist-card-overlay {
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

.artist-card:hover .artist-card-overlay {
    opacity: 1;
}

.play-btn-card {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: white;
    border: none;
    color: var(--bleu-tchadien);
    font-size: 1.5rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.play-btn-card:hover {
    transform: scale(1.1);
}

.verified-mini {
    position: absolute;
    top: 0.75rem;
    right: 0.75rem;
    background: var(--vert-savane);
    color: white;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
}

.new-badge {
    position: absolute;
    top: 0.75rem;
    left: 0.75rem;
    background: var(--jaune-solaire);
    color: var(--gris-harmattan);
    padding: 0.25rem 0.75rem;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: 700;
}

.artist-card-content {
    padding: 1.5rem;
}

.artist-card-content h4 {
    font-size: 1.2rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    color: var(--gris-harmattan);
}

.artist-card-content .genre {
    color: #6c757d;
    margin-bottom: 1rem;
    font-size: 0.9rem;
}

.mini-stats {
    display: flex;
    justify-content: space-between;
    margin-bottom: 1rem;
    font-size: 0.8rem;
    color: #6c757d;
}

.mini-stats span {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.mini-stats i {
    color: var(--bleu-tchadien);
}

.quick-actions {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
}

.quick-btn {
    width: 35px;
    height: 35px;
    border: 1px solid #e9ecef;
    background: white;
    color: #6c757d;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
}

.quick-btn:hover {
    border-color: var(--bleu-tchadien);
    background: var(--bleu-tchadien);
    color: white;
}

/* Newsletter Section */
.newsletter-section {
    background: linear-gradient(135deg, var(--gris-harmattan), #1a2332);
    color: white;
    padding: 4rem 0;
    margin-top: 4rem;
}

.newsletter-section h3 {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 1rem;
    color: white !important;
}

.newsletter-section p {
    font-size: 1.1rem;
    opacity: 0.9;
    margin-bottom: 0;
    color: rgba(255, 255, 255, 0.9) !important;
}

.newsletter-form {
    max-width: 400px;
    margin-left: auto;
}

.newsletter-form .form-control {
    border: none;
    border-radius: 12px 0 0 12px;
    padding: 0.75rem 1rem;
    font-size: 1rem;
}

.newsletter-form .btn {
    border-radius: 0 12px 12px 0;
    padding: 0.75rem 1.5rem;
}

.btn-secondary-custom {
    background: transparent;
    border: 2px solid var(--jaune-solaire);
    color: var(--gris-harmattan);
    font-weight: 600;
    padding: 0.75rem 2rem;
    border-radius: 12px;
    transition: all 0.3s ease;
}

.btn-secondary-custom:hover {
    background: var(--jaune-solaire);
    color: var(--gris-harmattan);
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(255, 215, 0, 0.4);
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
    .hero-content h1 {
        font-size: 2.5rem;
    }
    
    .search-bar {
        margin: 0;
    }
    
    .hero-stats {
        margin-top: 2rem;
    }
    
    .stat-item {
        display: inline-block;
        margin: 0 1rem 1rem 0;
    }
    
    .section-header h2 {
        font-size: 2rem;
    }
    
    .view-toggle {
        position: static;
        transform: none;
        margin-top: 1rem;
        justify-content: center;
    }
    
    .featured-artists-grid {
        grid-template-columns: 1fr;
    }
    
    .artists-grid {
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    }
    
    .filter-options {
        flex-direction: column;
        align-items: stretch;
    }
    
    .newsletter-section .row {
        text-align: center;
    }
    
    .newsletter-form {
        margin: 2rem auto 0;
    }
}
</style>

<script>
// Artists Page JavaScript
$(document).ready(function() {
    // Animate stats numbers
    function animateStats() {
        $('.stat-number').each(function() {
            var $this = $(this);
            var countTo = $this.attr('data-count');
            
            $({ countNum: $this.text() }).animate({
                countNum: countTo
            }, {
                duration: 2000,
                easing: 'linear',
                step: function() {
                    $this.text(Math.floor(this.countNum));
                },
                complete: function() {
                    $this.text(this.countNum);
                }
            });
        });
    }
    
    // Initialize stats animation
    animateStats();
    
    // Filter functionality
    $('.filter-tab').click(function() {
        $('.filter-tab').removeClass('active');
        $(this).addClass('active');
        
        var filter = $(this).data('filter');
        filterArtists(filter);
    });
    
    $('.genre-tag').click(function() {
        $('.genre-tag').removeClass('active');
        $(this).addClass('active');
        
        var genre = $(this).data('genre');
        filterByGenre(genre);
    });
    
    // View toggle
    $('.view-btn').click(function() {
        $('.view-btn').removeClass('active');
        $(this).addClass('active');
        
        var view = $(this).data('view');
        toggleView(view);
    });
    
    // Search functionality
    $('#artistSearch').on('input', function() {
        var searchTerm = $(this).val().toLowerCase();
        searchArtists(searchTerm);
    });
    
    // Sort functionality
    $('#sortSelect').change(function() {
        var sortBy = $(this).val();
        sortArtists(sortBy);
    });
});

function filterArtists(filter) {
    $('.artist-card').each(function() {
        var $card = $(this);
        var show = false;
        
        switch(filter) {
            case 'all':
                show = true;
                break;
            case 'verified':
                show = $card.data('verified') === true;
                break;
            case 'trending':
                show = $card.find('.trending-badge').length > 0;
                break;
            case 'new':
                show = $card.data('new') === true;
                break;
        }
        
        if (show) {
            $card.fadeIn(300);
        } else {
            $card.fadeOut(300);
        }
    });
    
    showNotification(`Filtre "${filter}" appliqué`);
}

function filterByGenre(genre) {
    $('.artist-card').each(function() {
        var $card = $(this);
        var cardGenre = $card.data('genre');
        
        if (genre === 'all' || cardGenre === genre) {
            $card.fadeIn(300);
        } else {
            $card.fadeOut(300);
        }
    });
    
    showNotification(`Genre "${genre}" sélectionné`);
}

function toggleView(view) {
    var $container = $('#artistsContainer');
    
    if (view === 'list') {
        $container.removeClass('artists-grid').addClass('artists-list');
    } else {
        $container.removeClass('artists-list').addClass('artists-grid');
    }
    
    showNotification(`Vue ${view === 'list' ? 'liste' : 'grille'} activée`);
}

function searchArtists(searchTerm) {
    $('.artist-card').each(function() {
        var $card = $(this);
        var artistName = $card.find('h4').text().toLowerCase();
        var genre = $card.find('.genre').text().toLowerCase();
        
        if (artistName.includes(searchTerm) || genre.includes(searchTerm)) {
            $card.fadeIn(300);
        } else {
            $card.fadeOut(300);
        }
    });
}

function sortArtists(sortBy) {
    var $container = $('#artistsContainer');
    var $cards = $container.find('.artist-card').sort(function(a, b) {
        var aVal, bVal;
        
        switch(sortBy) {
            case 'alphabetical':
                aVal = $(a).find('h4').text();
                bVal = $(b).find('h4').text();
                return aVal.localeCompare(bVal);
            case 'newest':
                aVal = $(a).data('new') ? 1 : 0;
                bVal = $(b).data('new') ? 1 : 0;
                return bVal - aVal;
            case 'plays':
                aVal = parseInt($(a).find('.mini-stats span:first').text().replace(/[^\d]/g, ''));
                bVal = parseInt($(b).find('.mini-stats span:first').text().replace(/[^\d]/g, ''));
                return bVal - aVal;
            default: // popularity
                return Math.random() - 0.5;
        }
    });
    
    $container.append($cards);
    showNotification(`Tri par ${sortBy} appliqué`);
}

function loadMoreArtists() {
    showNotification('⚠️ Fonctionnalité en développement');
    // Simulate loading more artists
    setTimeout(() => {
        $('#currentCount').text(parseInt($('#currentCount').text()) + 6);
        showNotification('✅ 6 nouveaux artistes chargés');
    }, 1000);
}

function playArtist(artistId) {
    showNotification(`🎵 Lecture de l'artiste #${artistId}`);
}

function followArtist(artistId) {
    showNotification(`➕ Artiste #${artistId} suivi`);
}

function shareArtist(artistId) {
    if (navigator.share) {
        navigator.share({
            title: `Artiste Tchadien`,
            text: `Découvrez cet artiste incroyable sur Tchadok !`,
            url: window.location.href
        });
    } else {
        navigator.clipboard.writeText(window.location.href).then(() => {
            showNotification('📋 Lien copié dans le presse-papiers');
        });
    }
}

function quickPlay(artistId) {
    showNotification(`⚡ Lecture rapide de l'artiste #${artistId}`);
}

function addToLibrary(artistId) {
    showNotification(`📚 Artiste #${artistId} ajouté à votre bibliothèque`);
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
</script>

<?php include 'includes/footer.php'; ?>