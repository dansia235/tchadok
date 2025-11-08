<?php
/**
 * Page Découvrir - Tchadok Platform
 * Découverte musicale innovante et personnalisée
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'assets/images/placeholders.php';

$pageTitle = 'Découvrir';
$pageDescription = 'Explorez la richesse de la musique tchadienne. Découvrez de nouveaux artistes, genres et tendances.';

include 'includes/header.php';
?>

<!-- Hero Discovery Section -->
<section class="discovery-hero">
    <div class="floating-music-notes" style="top: 8%; left: 5%;">♪</div>
    <div class="floating-music-notes" style="top: 60%; right: 8%; animation-delay: 2s;">♫</div>
    <div class="floating-music-notes" style="bottom: 20%; left: 18%; animation-delay: 4s;">♪</div>
    <div class="floating-music-notes" style="top: 30%; right: 25%; animation-delay: 1s;">♫</div>
    
    <div class="container">
        <div class="row align-items-center min-vh-100">
            <div class="col-lg-6">
                <div class="hero-content">
                    <div class="discovery-badge">
                        <i class="fas fa-compass"></i>
                        Exploration Musicale
                    </div>
                    <h1>Découvrez Votre Prochaine Obsession Musicale</h1>
                    <p>Plongez dans l'univers riche et diversifié de la musique tchadienne. Des sons traditionnels aux créations contemporaines, laissez-vous guider par nos recommandations personnalisées.</p>
                    
                    <div class="discovery-features">
                        <div class="feature-item">
                            <i class="fas fa-magic"></i>
                            <span>Recommandations IA</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-headphones"></i>
                            <span>Écoute Immersive</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-heart"></i>
                            <span>Favoris Personnalisés</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="discovery-wheel-container">
                    <div class="discovery-wheel">
                        <div class="wheel-center">
                            <i class="fas fa-play"></i>
                            <span>Découvrir</span>
                        </div>
                        <div class="wheel-segment" style="--rotation: 0deg;" data-genre="traditional">
                            <span>Traditionnel</span>
                        </div>
                        <div class="wheel-segment" style="--rotation: 45deg;" data-genre="afrobeat">
                            <span>Afrobeat</span>
                        </div>
                        <div class="wheel-segment" style="--rotation: 90deg;" data-genre="hiphop">
                            <span>Hip-Hop</span>
                        </div>
                        <div class="wheel-segment" style="--rotation: 135deg;" data-genre="rnb">
                            <span>R&B</span>
                        </div>
                        <div class="wheel-segment" style="--rotation: 180deg;" data-genre="gospel">
                            <span>Gospel</span>
                        </div>
                        <div class="wheel-segment" style="--rotation: 225deg;" data-genre="jazz">
                            <span>Jazz</span>
                        </div>
                        <div class="wheel-segment" style="--rotation: 270deg;" data-genre="reggae">
                            <span>Reggae</span>
                        </div>
                        <div class="wheel-segment" style="--rotation: 315deg;" data-genre="electronic">
                            <span>Electronic</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Discovery Filters -->
<section class="container my-5">
    <div class="discovery-filters">
        <h2 class="section-title">Explorez par Préférences</h2>
        
        <div class="filter-cards">
            <div class="filter-card active" data-filter="trending">
                <div class="filter-icon">
                    <i class="fas fa-fire"></i>
                </div>
                <h4>Tendances</h4>
                <p>Les hits du moment</p>
            </div>
            
            <div class="filter-card" data-filter="new">
                <div class="filter-icon">
                    <i class="fas fa-star"></i>
                </div>
                <h4>Nouveautés</h4>
                <p>Dernières sorties</p>
            </div>
            
            <div class="filter-card" data-filter="classics">
                <div class="filter-icon">
                    <i class="fas fa-crown"></i>
                </div>
                <h4>Classiques</h4>
                <p>Intemporels tchadiens</p>
            </div>
            
            <div class="filter-card" data-filter="rising">
                <div class="filter-icon">
                    <i class="fas fa-rocket"></i>
                </div>
                <h4>Talents Émergents</h4>
                <p>Nouvelles voix</p>
            </div>
        </div>
    </div>
</section>

<!-- Dynamic Music Grid -->
<section class="container my-5">
    <div class="music-discovery-grid" id="discoveryGrid">
        <!-- Trending Section -->
        <div class="discovery-section active" data-section="trending">
            <div class="section-header">
                <h3><i class="fas fa-fire text-danger me-2"></i>Tendances Actuelles</h3>
                <button class="shuffle-btn" onclick="shuffleContent('trending')">
                    <i class="fas fa-random"></i> Mélanger
                </button>
            </div>
            
            <div class="tracks-grid">
                <?php
                $trendingTracks = [
                    ['title' => 'Sahel Rhythm', 'artist' => 'Mahamat Groove', 'plays' => '2.1M', 'trend' => '+45%', 'color' => '0066CC'],
                    ['title' => 'N\'Djamena Dreams', 'artist' => 'Achta Voice', 'plays' => '1.8M', 'trend' => '+38%', 'color' => 'FFD700'],
                    ['title' => 'Desert Vibes', 'artist' => 'Ouaddaï Sound', 'plays' => '1.5M', 'trend' => '+52%', 'color' => 'CC3333'],
                    ['title' => 'Lake Chad Blues', 'artist' => 'Sarah Melody', 'plays' => '1.3M', 'trend' => '+29%', 'color' => '228B22'],
                    ['title' => 'Savannah Beat', 'artist' => 'DJ Tchadian', 'plays' => '1.1M', 'trend' => '+41%', 'color' => '667eea'],
                    ['title' => 'Moundou Magic', 'artist' => 'Urban Tribe', 'plays' => '956K', 'trend' => '+33%', 'color' => 'f093fb'],
                ];
                
                foreach ($trendingTracks as $index => $track):
                ?>
                <div class="track-card trending-track" data-plays="<?php echo $track['plays']; ?>">
                    <div class="track-image">
                        <?php echo createTrackCover($track['title'], $track['artist'], '#' . $track['color']); ?>
                        <div class="track-overlay">
                            <button class="play-btn-disco" onclick="playTrack('<?php echo $track['title']; ?>')">
                                <i class="fas fa-play"></i>
                            </button>
                            <div class="track-actions">
                                <button class="action-btn" onclick="likeTrack(<?php echo $index; ?>)" title="Aimer">
                                    <i class="far fa-heart"></i>
                                </button>
                                <button class="action-btn" onclick="addToPlaylist(<?php echo $index; ?>)" title="Ajouter à la playlist">
                                    <i class="fas fa-plus"></i>
                                </button>
                                <button class="action-btn" onclick="shareTrack(<?php echo $index; ?>)" title="Partager">
                                    <i class="fas fa-share-alt"></i>
                                </button>
                            </div>
                        </div>
                        <div class="trend-indicator">
                            <i class="fas fa-arrow-up"></i>
                            <?php echo $track['trend']; ?>
                        </div>
                    </div>
                    <div class="track-info">
                        <h5><?php echo htmlspecialchars($track['title']); ?></h5>
                        <p><?php echo htmlspecialchars($track['artist']); ?></p>
                        <div class="track-stats">
                            <span class="plays"><i class="fas fa-play"></i> <?php echo $track['plays']; ?></span>
                            <span class="duration">3:45</span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- New Releases Section -->
        <div class="discovery-section" data-section="new">
            <div class="section-header">
                <h3><i class="fas fa-star text-warning me-2"></i>Nouveautés de la Semaine</h3>
                <button class="shuffle-btn" onclick="shuffleContent('new')">
                    <i class="fas fa-random"></i> Mélanger
                </button>
            </div>
            
            <div class="tracks-grid">
                <?php
                $newTracks = [
                    ['title' => 'Nouveau Jour', 'artist' => 'Fresh Voice', 'release' => '2 jours', 'color' => '43e97b'],
                    ['title' => 'Espoir Tchadien', 'artist' => 'Modern Soul', 'release' => '5 jours', 'color' => 'fa709a'],
                    ['title' => 'Rêves d\'Avenir', 'artist' => 'Young Talent', 'release' => '1 semaine', 'color' => '4facfe'],
                    ['title' => 'Racines Nouvelles', 'artist' => 'Neo Traditional', 'release' => '1 semaine', 'color' => 'fee140'],
                    ['title' => 'Digital Sahel', 'artist' => 'Tech Beats', 'release' => '2 semaines', 'color' => 'a8edea'],
                    ['title' => 'Fusion Moderne', 'artist' => 'Contemporary Mix', 'release' => '2 semaines', 'color' => 'fecfef'],
                ];
                
                foreach ($newTracks as $index => $track):
                ?>
                <div class="track-card new-track">
                    <div class="track-image">
                        <?php echo createTrackCover($track['title'], $track['artist'], '#' . $track['color'], 'NEW'); ?>
                        <div class="track-overlay">
                            <button class="play-btn-disco" onclick="playTrack('<?php echo $track['title']; ?>')">
                                <i class="fas fa-play"></i>
                            </button>
                            <div class="track-actions">
                                <button class="action-btn" onclick="likeTrack(<?php echo $index + 10; ?>)" title="Aimer">
                                    <i class="far fa-heart"></i>
                                </button>
                                <button class="action-btn" onclick="addToPlaylist(<?php echo $index + 10; ?>)" title="Ajouter à la playlist">
                                    <i class="fas fa-plus"></i>
                                </button>
                                <button class="action-btn" onclick="shareTrack(<?php echo $index + 10; ?>)" title="Partager">
                                    <i class="fas fa-share-alt"></i>
                                </button>
                            </div>
                        </div>
                        <div class="new-badge">
                            <i class="fas fa-star"></i>
                            Nouveau
                        </div>
                    </div>
                    <div class="track-info">
                        <h5><?php echo htmlspecialchars($track['title']); ?></h5>
                        <p><?php echo htmlspecialchars($track['artist']); ?></p>
                        <div class="track-stats">
                            <span class="release"><i class="fas fa-calendar"></i> Il y a <?php echo $track['release']; ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Classics Section -->
        <div class="discovery-section" data-section="classics">
            <div class="section-header">
                <h3><i class="fas fa-crown text-warning me-2"></i>Classiques Intemporels</h3>
                <button class="shuffle-btn" onclick="shuffleContent('classics')">
                    <i class="fas fa-random"></i> Mélanger
                </button>
            </div>
            
            <div class="tracks-grid">
                <?php
                $classicTracks = [
                    ['title' => 'Légende du Chari', 'artist' => 'Maître Traditionnel', 'year' => '1985', 'color' => '8B4513'],
                    ['title' => 'Chant des Ancêtres', 'artist' => 'Voix d\'Or', 'year' => '1992', 'color' => 'CD853F'],
                    ['title' => 'Harmonie Sahélienne', 'artist' => 'Groupe Authentique', 'year' => '1988', 'color' => 'DEB887'],
                    ['title' => 'Mélodie Éternelle', 'artist' => 'Grande Dame', 'year' => '1979', 'color' => 'F4A460'],
                    ['title' => 'Tambour Royal', 'artist' => 'Percussions Ancestrales', 'year' => '1996', 'color' => 'DAA520'],
                    ['title' => 'Sagesse des Griots', 'artist' => 'Tradition Vivante', 'year' => '1983', 'color' => 'B8860B'],
                ];
                
                foreach ($classicTracks as $index => $track):
                ?>
                <div class="track-card classic-track">
                    <div class="track-image">
                        <?php echo createTrackCover($track['title'], $track['artist'], '#' . $track['color']); ?>
                        <div class="track-overlay">
                            <button class="play-btn-disco" onclick="playTrack('<?php echo $track['title']; ?>')">
                                <i class="fas fa-play"></i>
                            </button>
                            <div class="track-actions">
                                <button class="action-btn" onclick="likeTrack(<?php echo $index + 20; ?>)" title="Aimer">
                                    <i class="far fa-heart"></i>
                                </button>
                                <button class="action-btn" onclick="addToPlaylist(<?php echo $index + 20; ?>)" title="Ajouter à la playlist">
                                    <i class="fas fa-plus"></i>
                                </button>
                                <button class="action-btn" onclick="shareTrack(<?php echo $index + 20; ?>)" title="Partager">
                                    <i class="fas fa-share-alt"></i>
                                </button>
                            </div>
                        </div>
                        <div class="classic-badge">
                            <i class="fas fa-crown"></i>
                            Classique
                        </div>
                    </div>
                    <div class="track-info">
                        <h5><?php echo htmlspecialchars($track['title']); ?></h5>
                        <p><?php echo htmlspecialchars($track['artist']); ?></p>
                        <div class="track-stats">
                            <span class="year"><i class="fas fa-history"></i> <?php echo $track['year']; ?></span>
                            <span class="rating"><i class="fas fa-star"></i> 4.8</span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Rising Talents Section -->
        <div class="discovery-section" data-section="rising">
            <div class="section-header">
                <h3><i class="fas fa-rocket text-info me-2"></i>Talents Émergents</h3>
                <button class="shuffle-btn" onclick="shuffleContent('rising')">
                    <i class="fas fa-random"></i> Mélanger
                </button>
            </div>
            
            <div class="tracks-grid">
                <?php
                $risingTracks = [
                    ['title' => 'Premier Pas', 'artist' => 'Jeune Espoir', 'followers' => '1.2K', 'growth' => '+250%', 'color' => 'ff6b6b'],
                    ['title' => 'Avenir Brillant', 'artist' => 'Nouvelle Étoile', 'followers' => '892', 'growth' => '+180%', 'color' => '4ecdc4'],
                    ['title' => 'Son Frais', 'artist' => 'Talent Brut', 'followers' => '2.1K', 'growth' => '+320%', 'color' => '45b7d1'],
                    ['title' => 'Voix Unique', 'artist' => 'Artiste Découverte', 'followers' => '756', 'growth' => '+140%', 'color' => 'f9ca24'],
                    ['title' => 'Créativité Pure', 'artist' => 'Innovation Musicale', 'followers' => '1.8K', 'growth' => '+290%', 'color' => '6c5ce7'],
                    ['title' => 'Énergie Nouvelle', 'artist' => 'Force Montante', 'followers' => '1.4K', 'growth' => '+200%', 'color' => 'fd79a8'],
                ];
                
                foreach ($risingTracks as $index => $track):
                ?>
                <div class="track-card rising-track">
                    <div class="track-image">
                        <?php echo createTrackCover($track['title'], $track['artist'], '#' . $track['color'], '🚀'); ?>
                        <div class="track-overlay">
                            <button class="play-btn-disco" onclick="playTrack('<?php echo $track['title']; ?>')">
                                <i class="fas fa-play"></i>
                            </button>
                            <div class="track-actions">
                                <button class="action-btn" onclick="likeTrack(<?php echo $index + 30; ?>)" title="Aimer">
                                    <i class="far fa-heart"></i>
                                </button>
                                <button class="action-btn" onclick="addToPlaylist(<?php echo $index + 30; ?>)" title="Ajouter à la playlist">
                                    <i class="fas fa-plus"></i>
                                </button>
                                <button class="action-btn" onclick="shareTrack(<?php echo $index + 30; ?>)" title="Partager">
                                    <i class="fas fa-share-alt"></i>
                                </button>
                            </div>
                        </div>
                        <div class="rising-badge">
                            <i class="fas fa-rocket"></i>
                            <?php echo $track['growth']; ?>
                        </div>
                    </div>
                    <div class="track-info">
                        <h5><?php echo htmlspecialchars($track['title']); ?></h5>
                        <p><?php echo htmlspecialchars($track['artist']); ?></p>
                        <div class="track-stats">
                            <span class="followers"><i class="fas fa-users"></i> <?php echo $track['followers']; ?></span>
                            <span class="growth"><?php echo $track['growth']; ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<!-- Personalized Recommendations -->
<section class="recommendations-section">
    <div class="container">
        <div class="row">
            <div class="col-lg-8">
                <h2 class="section-title">Recommandations Personnalisées</h2>
                <p class="section-subtitle">Basées sur vos goûts musicaux et votre historique d'écoute</p>
                
                <div class="recommendation-cards">
                    <div class="recommendation-card">
                        <div class="rec-icon">
                            <i class="fas fa-brain"></i>
                        </div>
                        <div class="rec-content">
                            <h4>Parce que vous aimez l'Afrobeat</h4>
                            <p>Découvrez "Rythme Moderne" de Fusion Tchad</p>
                            <button class="btn btn-primary-custom btn-sm">Écouter</button>
                        </div>
                    </div>
                    
                    <div class="recommendation-card">
                        <div class="rec-icon">
                            <i class="fas fa-music"></i>
                        </div>
                        <div class="rec-content">
                            <h4>Similaire à vos favoris</h4>
                            <p>Explorez "Harmonie Nouvelle" de Style Comparable</p>
                            <button class="btn btn-secondary-custom btn-sm">Découvrir</button>
                        </div>
                    </div>
                    
                    <div class="recommendation-card">
                        <div class="rec-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="rec-content">
                            <h4>Tendance pour vous</h4>
                            <p>Ne manquez pas "Beat Émergent" de Nouveau Talent</p>
                            <button class="btn btn-primary-custom btn-sm">Écouter</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="discovery-stats">
                    <h3>Vos Statistiques de Découverte</h3>
                    
                    <div class="stat-item">
                        <div class="stat-icon">
                            <i class="fas fa-compass"></i>
                        </div>
                        <div class="stat-content">
                            <h4>47</h4>
                            <p>Nouveaux artistes découverts ce mois</p>
                        </div>
                    </div>
                    
                    <div class="stat-item">
                        <div class="stat-icon">
                            <i class="fas fa-headphones"></i>
                        </div>
                        <div class="stat-content">
                            <h4>23h</h4>
                            <p>Temps d'exploration cette semaine</p>
                        </div>
                    </div>
                    
                    <div class="stat-item">
                        <div class="stat-icon">
                            <i class="fas fa-heart"></i>
                        </div>
                        <div class="stat-content">
                            <h4>156</h4>
                            <p>Titres ajoutés aux favoris</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
/* Discovery Page Styles */
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
.discovery-hero {
    background: linear-gradient(135deg, rgba(0, 102, 204, 0.9), rgba(255, 215, 0, 0.7)), 
                url('data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"%3E%3Cpath fill="%230066CC" fill-opacity="0.1" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,122.7C672,117,768,139,864,133.3C960,128,1056,96,1152,90.7C1248,85,1344,107,1392,117.3L1440,128L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"%3E%3C/path%3E%3C/svg%3E');
    background-size: cover;
    background-position: center;
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
    padding: 2rem 0;
}

.discovery-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(255, 215, 0, 0.9);
    color: var(--gris-harmattan);
    padding: 0.75rem 1.5rem;
    border-radius: 25px;
    font-weight: 700;
    margin-bottom: 2rem;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

.hero-content h1 {
    font-size: 4rem;
    font-weight: 900;
    margin-bottom: 2rem;
    text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
    line-height: 1.2;
}

.hero-content p {
    font-size: 1.3rem;
    margin-bottom: 3rem;
    opacity: 0.9;
    line-height: 1.6;
}

.discovery-features {
    display: flex;
    gap: 2rem;
    flex-wrap: wrap;
}

.feature-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    padding: 1rem 1.5rem;
    border-radius: 15px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    font-weight: 600;
}

.feature-item i {
    font-size: 1.2rem;
    color: var(--jaune-solaire);
}

/* Discovery Wheel */
.discovery-wheel-container {
    display: flex;
    justify-content: center;
    align-items: center;
    height: 500px;
    position: relative;
}

.discovery-wheel {
    width: 350px;
    height: 350px;
    border-radius: 50%;
    position: relative;
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border: 3px solid rgba(255, 255, 255, 0.3);
    animation: rotate 20s linear infinite;
    cursor: pointer;
}

@keyframes rotate {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.wheel-center {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 80px;
    height: 80px;
    background: var(--jaune-solaire);
    border-radius: 50%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: var(--gris-harmattan);
    font-weight: 700;
    z-index: 10;
    box-shadow: 0 5px 20px rgba(255, 215, 0, 0.4);
}

.wheel-center i {
    font-size: 1.5rem;
    margin-bottom: 0.25rem;
}

.wheel-center span {
    font-size: 0.8rem;
}

.wheel-segment {
    position: absolute;
    width: 50%;
    height: 2px;
    background: var(--jaune-solaire);
    top: 50%;
    left: 50%;
    transform-origin: 0 0;
    transform: translate(0, -50%) rotate(var(--rotation));
}

.wheel-segment span {
    position: absolute;
    right: -20px;
    top: -20px;
    background: rgba(255, 255, 255, 0.9);
    color: var(--gris-harmattan);
    padding: 0.5rem 1rem;
    border-radius: 15px;
    font-size: 0.9rem;
    font-weight: 600;
    white-space: nowrap;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
    transform: rotate(calc(-1 * var(--rotation)));
}

/* Discovery Filters */
.discovery-filters {
    text-align: center;
    margin-bottom: 4rem;
}

.section-title {
    font-size: 3rem;
    font-weight: 900;
    color: var(--gris-harmattan);
    margin-bottom: 3rem;
    position: relative;
    display: inline-block;
}

.section-title::after {
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

.filter-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 2rem;
    max-width: 1200px;
    margin: 0 auto;
}

.filter-card {
    background: white;
    border-radius: 20px;
    padding: 2rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    border: 3px solid transparent;
}

.filter-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
}

.filter-card.active {
    border-color: var(--bleu-tchadien);
    background: linear-gradient(135deg, rgba(0, 102, 204, 0.05), rgba(255, 215, 0, 0.05));
}

.filter-icon {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, var(--bleu-tchadien), var(--jaune-solaire));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
    font-size: 2rem;
    color: white;
}

.filter-card h4 {
    font-size: 1.5rem;
    margin-bottom: 1rem;
    color: var(--gris-harmattan);
}

.filter-card p {
    color: #6c757d;
    margin-bottom: 0;
}

/* Music Discovery Grid */
.music-discovery-grid {
    position: relative;
}

.discovery-section {
    display: none;
}

.discovery-section.active {
    display: block;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
}

.section-header h3 {
    font-size: 2rem;
    font-weight: 700;
    color: var(--gris-harmattan);
    margin: 0;
}

.shuffle-btn {
    background: transparent;
    border: 2px solid var(--jaune-solaire);
    color: var(--jaune-solaire);
    padding: 0.75rem 1.5rem;
    border-radius: 25px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.shuffle-btn:hover {
    background: var(--jaune-solaire);
    color: var(--gris-harmattan);
    transform: translateY(-2px);
}

.tracks-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 2rem;
}

.track-card {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    cursor: pointer;
    position: relative;
}

.track-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
}

.track-image {
    position: relative;
    height: 250px;
    overflow: hidden;
}

.track-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.track-card:hover .track-image img {
    transform: scale(1.1);
}

.track-overlay {
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

.track-card:hover .track-overlay {
    opacity: 1;
}

.play-btn-disco {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    background: var(--jaune-solaire);
    border: none;
    color: var(--gris-harmattan);
    font-size: 1.5rem;
    cursor: pointer;
    transition: all 0.3s ease;
    transform: scale(0);
}

.track-card:hover .play-btn-disco {
    transform: scale(1);
}

.play-btn-disco:hover {
    transform: scale(1.1);
    box-shadow: 0 5px 20px rgba(255, 215, 0, 0.4);
}

.track-actions {
    position: absolute;
    top: 1rem;
    right: 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.track-card:hover .track-actions {
    opacity: 1;
}

.action-btn {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.9);
    border: none;
    color: var(--gris-harmattan);
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.action-btn:hover {
    background: var(--jaune-solaire);
    transform: scale(1.1);
}

/* Badges */
.trend-indicator,
.new-badge,
.classic-badge,
.rising-badge {
    position: absolute;
    top: 1rem;
    left: 1rem;
    padding: 0.5rem 1rem;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.trend-indicator {
    background: var(--vert-savane);
    color: white;
}

.new-badge {
    background: var(--jaune-solaire);
    color: var(--gris-harmattan);
}

.classic-badge {
    background: #8B4513;
    color: white;
}

.rising-badge {
    background: var(--bleu-tchadien);
    color: white;
}

.track-info {
    padding: 1.5rem;
}

.track-info h5 {
    font-size: 1.2rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    color: var(--gris-harmattan);
}

.track-info p {
    color: #6c757d;
    margin-bottom: 1rem;
    font-size: 0.95rem;
}

.track-stats {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.85rem;
    color: #6c757d;
}

.track-stats span {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.track-stats i {
    color: var(--bleu-tchadien);
}

/* Recommendations Section */
.recommendations-section {
    background: linear-gradient(135deg, var(--gris-harmattan), #1a2332);
    color: white;
    padding: 5rem 0;
    margin: 4rem 0;
}

.recommendations-section .section-title {
    color: white !important;
    font-size: 2.5rem;
    font-weight: 900;
    margin-bottom: 1rem;
}

.recommendations-section .section-subtitle {
    color: rgba(255, 255, 255, 0.8) !important;
    font-size: 1.1rem;
    margin-bottom: 3rem;
}

.recommendation-cards {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.recommendation-card {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border-radius: 15px;
    padding: 2rem;
    display: flex;
    align-items: center;
    gap: 2rem;
    border: 1px solid rgba(255, 255, 255, 0.2);
    transition: all 0.3s ease;
}

.recommendation-card:hover {
    background: rgba(255, 255, 255, 0.15);
    transform: translateX(10px);
}

.rec-icon {
    width: 60px;
    height: 60px;
    background: var(--jaune-solaire);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: var(--gris-harmattan);
    flex-shrink: 0;
}

.rec-content h4 {
    font-size: 1.2rem;
    margin-bottom: 0.5rem;
    color: white !important;
    font-weight: 700;
}

.rec-content p {
    margin-bottom: 1rem;
    opacity: 0.9;
    color: rgba(255, 255, 255, 0.8) !important;
}

.btn-primary-custom {
    background: linear-gradient(135deg, var(--bleu-tchadien), #0052a3);
    border: none;
    color: white;
    font-weight: 600;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.btn-primary-custom:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0, 102, 204, 0.4);
    color: white;
}

.btn-secondary-custom {
    background: transparent;
    border: 2px solid var(--jaune-solaire);
    color: var(--jaune-solaire);
    font-weight: 600;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.btn-secondary-custom:hover {
    background: var(--jaune-solaire);
    color: var(--gris-harmattan);
}

/* Discovery Stats */
.discovery-stats {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    padding: 2rem;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.discovery-stats h3 {
    font-size: 1.5rem;
    margin-bottom: 2rem;
    text-align: center;
    color: white !important;
    font-weight: 700;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 2rem;
    padding: 1rem;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 10px;
}

.stat-item:last-child {
    margin-bottom: 0;
}

.stat-icon {
    width: 50px;
    height: 50px;
    background: var(--jaune-solaire);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--gris-harmattan);
    font-size: 1.2rem;
    flex-shrink: 0;
}

.stat-content h4 {
    font-size: 1.8rem;
    font-weight: 900;
    color: var(--jaune-solaire) !important;
    margin-bottom: 0.25rem;
}

.stat-content p {
    margin: 0;
    opacity: 0.9;
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.8) !important;
}

/* Mobile Responsiveness */
@media (max-width: 768px) {
    .hero-content h1 {
        font-size: 2.5rem;
    }
    
    .discovery-features {
        flex-direction: column;
    }
    
    .discovery-wheel {
        width: 250px;
        height: 250px;
    }
    
    .wheel-center {
        width: 60px;
        height: 60px;
    }
    
    .filter-cards {
        grid-template-columns: 1fr;
    }
    
    .tracks-grid {
        grid-template-columns: 1fr;
    }
    
    .section-header {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }
    
    .recommendation-card {
        flex-direction: column;
        text-align: center;
    }
    
    .floating-music-notes {
        font-size: 1.5rem;
    }
}
</style>

<script>
// Discovery Page JavaScript
let currentFilter = 'trending';

document.addEventListener('DOMContentLoaded', function() {
    initializeDiscovery();
    initializeWheel();
});

function initializeDiscovery() {
    // Filter cards
    document.querySelectorAll('.filter-card').forEach(card => {
        card.addEventListener('click', function() {
            document.querySelectorAll('.filter-card').forEach(c => c.classList.remove('active'));
            this.classList.add('active');
            
            const filter = this.dataset.filter;
            switchSection(filter);
        });
    });
    
    // Track cards
    document.querySelectorAll('.track-card').forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px) scale(1.02)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });
}

function initializeWheel() {
    const wheel = document.querySelector('.discovery-wheel');
    const center = document.querySelector('.wheel-center');
    
    center.addEventListener('click', function() {
        const genres = ['traditional', 'afrobeat', 'hiphop', 'rnb', 'gospel', 'jazz', 'reggae', 'electronic'];
        const randomGenre = genres[Math.floor(Math.random() * genres.length)];
        
        // Stop wheel animation temporarily
        wheel.style.animation = 'none';
        wheel.style.transform = 'rotate(' + (Math.random() * 360) + 'deg)';
        
        setTimeout(() => {
            wheel.style.animation = 'rotate 20s linear infinite';
            showNotification(`🎵 Genre découvert: ${randomGenre}`);
        }, 1000);
    });
    
    // Wheel segments
    document.querySelectorAll('.wheel-segment').forEach(segment => {
        segment.addEventListener('click', function() {
            const genre = this.dataset.genre;
            showNotification(`🎵 Exploration du genre: ${genre}`);
            // Simulate filtering by genre
            filterByGenre(genre);
        });
    });
}

function switchSection(section) {
    currentFilter = section;
    
    document.querySelectorAll('.discovery-section').forEach(s => s.classList.remove('active'));
    document.querySelector(`[data-section="${section}"]`).classList.add('active');
    
    // Animate transition
    const grid = document.getElementById('discoveryGrid');
    grid.style.opacity = '0';
    grid.style.transform = 'translateY(20px)';
    
    setTimeout(() => {
        grid.style.transition = 'all 0.5s ease';
        grid.style.opacity = '1';
        grid.style.transform = 'translateY(0)';
    }, 100);
    
    showNotification(`📊 Affichage: ${getSectionName(section)}`);
}

function getSectionName(section) {
    const names = {
        'trending': 'Tendances',
        'new': 'Nouveautés',
        'classics': 'Classiques',
        'rising': 'Talents Émergents'
    };
    return names[section] || section;
}

function shuffleContent(section) {
    const container = document.querySelector(`[data-section="${section}"] .tracks-grid`);
    const cards = Array.from(container.children);
    
    // Shuffle array
    for (let i = cards.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [cards[i], cards[j]] = [cards[j], cards[i]];
    }
    
    // Re-append in new order
    cards.forEach(card => container.appendChild(card));
    
    showNotification('🔀 Contenu mélangé !');
}

function playTrack(title) {
    showNotification(`🎵 Lecture: "${title}"`);
    
    // Simulate play animation
    const playButtons = document.querySelectorAll('.play-btn-disco');
    playButtons.forEach(btn => {
        btn.innerHTML = '<i class="fas fa-pause"></i>';
        setTimeout(() => {
            btn.innerHTML = '<i class="fas fa-play"></i>';
        }, 3000);
    });
}

function likeTrack(index) {
    const button = event.target.closest('.action-btn');
    const icon = button.querySelector('i');
    
    if (icon.classList.contains('far')) {
        icon.classList.remove('far');
        icon.classList.add('fas');
        icon.style.color = var(--rouge-terre);
        showNotification('❤️ Ajouté aux favoris');
    } else {
        icon.classList.remove('fas');
        icon.classList.add('far');
        icon.style.color = '';
        showNotification('💔 Retiré des favoris');
    }
}

function addToPlaylist(index) {
    showNotification('📝 Ajouté à la playlist');
}

function shareTrack(index) {
    if (navigator.share) {
        navigator.share({
            title: 'Découverte musicale sur Tchadok',
            text: 'Écoutez cette pépite de la musique tchadienne !',
            url: window.location.href
        });
    } else {
        navigator.clipboard.writeText(window.location.href).then(() => {
            showNotification('📋 Lien copié dans le presse-papiers');
        });
    }
}

function filterByGenre(genre) {
    showNotification(`🎵 Filtrage par genre: ${genre}`);
    // Simulate genre filtering
    const tracks = document.querySelectorAll('.track-card');
    tracks.forEach((track, index) => {
        if (Math.random() > 0.3) {
            track.style.display = 'block';
        } else {
            track.style.display = 'none';
        }
    });
    
    setTimeout(() => {
        tracks.forEach(track => track.style.display = 'block');
    }, 3000);
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
        border-radius: 12px; 
        z-index: 10000;
        box-shadow: 0 8px 25px rgba(0,0,0,0.3);
        max-width: 350px;
        animation: slideIn 0.3s ease;
        backdrop-filter: blur(10px);
    `;
    document.body.appendChild(notification);
    setTimeout(() => notification.remove(), 4000);
}

// Add slideIn animation
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
`;
document.head.appendChild(style);
</script>

<?php include 'includes/footer.php'; ?>