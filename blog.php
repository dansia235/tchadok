<?php
/**
 * Page Blog - Tchadok Platform
 * Design innovant et moderne pour le blog musical tchadien
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'assets/images/placeholders.php';

$pageTitle = 'Blog Musical';
$pageDescription = 'Découvrez les dernières actualités de la musique tchadienne, interviews d\'artistes exclusives et analyses musicales approfondies.';

include 'includes/header.php';
?>

<!-- Hero Blog Section -->
<section class="blog-hero">
    <div class="floating-music-notes" style="top: 12%; left: 8%;">♪</div>
    <div class="floating-music-notes" style="top: 68%; right: 10%; animation-delay: 2s;">♫</div>
    <div class="floating-music-notes" style="bottom: 20%; left: 15%; animation-delay: 4s;">♪</div>
    <div class="floating-music-notes" style="top: 35%; right: 25%; animation-delay: 1s;">♫</div>
    
    <div class="container">
        <div class="row align-items-center min-vh-100">
            <div class="col-lg-6">
                <div class="hero-content">
                    <div class="blog-badge">
                        <i class="fas fa-newspaper"></i>
                        Actualités Musicales
                    </div>
                    <h1>Découvrez l'Univers Musical Tchadien</h1>
                    <p>Plongez dans les coulisses de la musique tchadienne avec nos articles exclusifs, interviews d'artistes et analyses culturelles approfondies.</p>
                    
                    <div class="blog-stats">
                        <div class="stat-item">
                            <div class="stat-number">150+</div>
                            <div class="stat-label">Articles</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number">50+</div>
                            <div class="stat-label">Interviews</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number">25k+</div>
                            <div class="stat-label">Lecteurs</div>
                        </div>
                    </div>
                    
                    <div class="hero-search">
                        <div class="search-container">
                            <i class="fas fa-search"></i>
                            <input type="text" placeholder="Rechercher un article, artiste..." id="blogSearch">
                            <button class="search-btn">
                                <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="hero-visual">
                    <div class="blog-animation">
                        <div class="article-cards">
                            <div class="floating-card card-1">
                                <div class="card-header"></div>
                                <div class="card-lines"></div>
                            </div>
                            <div class="floating-card card-2">
                                <div class="card-header"></div>
                                <div class="card-lines"></div>
                            </div>
                            <div class="floating-card card-3">
                                <div class="card-header"></div>
                                <div class="card-lines"></div>
                            </div>
                        </div>
                        <div class="pen-icon">
                            <i class="fas fa-feather-alt"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Featured Articles Section -->
<section class="container my-5">
    <div class="section-header">
        <h2>Articles en Vedette</h2>
        <p class="text-muted">Les histoires qui font vibrer la scène musicale tchadienne</p>
    </div>
    
    <div class="featured-articles">
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="main-featured-article">
                    <div class="article-image">
                        <?php echo createBlogThumbnail('Interview Exclusive', 'Mounira Mitchala', '#0066CC'); ?>
                        <div class="article-overlay">
                            <div class="category-badge featured">
                                <i class="fas fa-star"></i>
                                En vedette
                            </div>
                        </div>
                    </div>
                    <div class="article-content">
                        <h3>Interview Exclusive : Mounira Mitchala raconte son parcours musical</h3>
                        <p>Découvrez l'histoire inspirante de l'une des voix les plus emblématiques de la musique tchadienne, ses influences et ses projets futurs.</p>
                        <div class="article-meta">
                            <div class="author-info">
                                <?php echo createAvatarPlaceholder('Jean-Marie Tchadou', '#FFD700'); ?>
                                <div>
                                    <span class="author-name">Jean-Marie Tchadou</span>
                                    <span class="publish-date">Il y a 2 jours</span>
                                </div>
                            </div>
                            <div class="article-stats">
                                <span><i class="fas fa-eye"></i> 2.1k</span>
                                <span><i class="fas fa-heart"></i> 89</span>
                                <span><i class="fas fa-comment"></i> 24</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="secondary-articles">
                    <div class="secondary-article">
                        <div class="article-image-small">
                            <?php echo createBlogThumbnail('Festival', 'Dary 2024', '#FFD700'); ?>
                            <div class="category-badge music">Événement</div>
                        </div>
                        <div class="article-content-small">
                            <h4>Festival Dary 2024 : Le programme dévoilé</h4>
                            <p>Plus de 30 artistes tchadiens et internationaux se produiront...</p>
                            <div class="read-time">
                                <i class="fas fa-clock"></i> 3 min de lecture
                            </div>
                        </div>
                    </div>
                    
                    <div class="secondary-article">
                        <div class="article-image-small">
                            <?php echo createBlogThumbnail('Analyse', 'Musique Traditionnelle', '#228B22'); ?>
                            <div class="category-badge culture">Culture</div>
                        </div>
                        <div class="article-content-small">
                            <h4>L'évolution de la musique traditionnelle tchadienne</h4>
                            <p>Comment les artistes modernes réinventent les sonorités ancestrales...</p>
                            <div class="read-time">
                                <i class="fas fa-clock"></i> 5 min de lecture
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Categories Filter -->
<section class="container my-5">
    <div class="categories-filter">
        <h3 class="mb-4">Explorer par Catégories</h3>
        <div class="filter-tabs">
            <button class="filter-btn active" data-category="all">
                <i class="fas fa-globe"></i>
                Tout
            </button>
            <button class="filter-btn" data-category="interviews">
                <i class="fas fa-microphone"></i>
                Interviews
            </button>
            <button class="filter-btn" data-category="actualites">
                <i class="fas fa-newspaper"></i>
                Actualités
            </button>
            <button class="filter-btn" data-category="analyses">
                <i class="fas fa-chart-line"></i>
                Analyses
            </button>
            <button class="filter-btn" data-category="evenements">
                <i class="fas fa-calendar-star"></i>
                Événements
            </button>
            <button class="filter-btn" data-category="culture">
                <i class="fas fa-palette"></i>
                Culture
            </button>
        </div>
    </div>
</section>

<!-- Articles Grid -->
<section class="container my-5">
    <div class="articles-grid" id="articlesGrid">
        <div class="row g-4">
            <?php
            // Simulation d'articles pour la démonstration
            $articles = [
                [
                    'title' => 'Clément Masdongar présente son nouvel album "Racines Modernes"',
                    'category' => 'actualites',
                    'excerpt' => 'L\'artiste tchadien dévoile un projet musical ambitieux mêlant traditions et modernité...',
                    'author' => 'Sarah Abderamane',
                    'date' => 'Il y a 1 jour',
                    'image_color' => '#CC3333',
                    'views' => '1.8k',
                    'likes' => '67',
                    'comments' => '18',
                    'read_time' => '4 min'
                ],
                [
                    'title' => 'Interview : H2O Assoumane, l\'avenir du hip-hop tchadien',
                    'category' => 'interviews',
                    'excerpt' => 'Rencontre avec le rappeur qui révolutionne la scène hip-hop de N\'Djamena...',
                    'author' => 'Moussa Hassan',
                    'date' => 'Il y a 3 jours',
                    'image_color' => '#667eea',
                    'views' => '3.2k',
                    'likes' => '124',
                    'comments' => '45',
                    'read_time' => '6 min'
                ],
                [
                    'title' => 'Analyse : L\'impact des plateformes numériques sur la musique tchadienne',
                    'category' => 'analyses',
                    'excerpt' => 'Comment le streaming transforme la distribution musicale au Tchad...',
                    'author' => 'Dr. Abakar Moussa',
                    'date' => 'Il y a 5 jours',
                    'image_color' => '#4facfe',
                    'views' => '2.5k',
                    'likes' => '89',
                    'comments' => '32',
                    'read_time' => '8 min'
                ],
                [
                    'title' => 'Festival Sahel Sounds : Une vitrine pour les talents émergents',
                    'category' => 'evenements',
                    'excerpt' => 'Retour sur un événement qui met en lumière la nouvelle génération d\'artistes...',
                    'author' => 'Fatima Ali',
                    'date' => 'Il y a 1 semaine',
                    'image_color' => '#fa709a',
                    'views' => '4.1k',
                    'likes' => '156',
                    'comments' => '67',
                    'read_time' => '5 min'
                ],
                [
                    'title' => 'Les femmes dans la musique tchadienne : Portrait d\'une révolution silencieuse',
                    'category' => 'culture',
                    'excerpt' => 'Focus sur les artistes féminines qui redéfinissent le paysage musical national...',
                    'author' => 'Achta Djibrine',
                    'date' => 'Il y a 1 semaine',
                    'image_color' => '#fee140',
                    'views' => '2.9k',
                    'likes' => '198',
                    'comments' => '89',
                    'read_time' => '7 min'
                ],
                [
                    'title' => 'Maimouna Youssouf : Gardienne des traditions musicales sara',
                    'category' => 'interviews',
                    'excerpt' => 'Rencontre avec une artiste qui perpétue les chants ancestraux de son peuple...',
                    'author' => 'Emmanuel Ngarlejy',
                    'date' => 'Il y a 10 jours',
                    'image_color' => '#a8edea',
                    'views' => '1.7k',
                    'likes' => '78',
                    'comments' => '23',
                    'read_time' => '6 min'
                ]
            ];
            
            foreach ($articles as $index => $article):
            ?>
            <div class="col-lg-4 col-md-6 article-item" data-category="<?php echo $article['category']; ?>">
                <article class="blog-card">
                    <div class="blog-card-image">
                        <?php echo createBlogThumbnail($article['title'], $article['author'], $article['image_color']); ?>
                        <div class="image-overlay">
                            <button class="read-btn" onclick="readArticle(<?php echo $index + 1; ?>)">
                                <i class="fas fa-book-open"></i>
                                Lire l'article
                            </button>
                        </div>
                        <div class="category-badge <?php echo $article['category']; ?>">
                            <?php 
                            $categoryNames = [
                                'interviews' => 'Interview',
                                'actualites' => 'Actualité',
                                'analyses' => 'Analyse',
                                'evenements' => 'Événement',
                                'culture' => 'Culture'
                            ];
                            echo $categoryNames[$article['category']];
                            ?>
                        </div>
                        <div class="read-time-badge">
                            <i class="fas fa-clock"></i>
                            <?php echo $article['read_time']; ?>
                        </div>
                    </div>
                    
                    <div class="blog-card-content">
                        <h3><?php echo htmlspecialchars($article['title']); ?></h3>
                        <p><?php echo htmlspecialchars($article['excerpt']); ?></p>
                        
                        <div class="article-footer">
                            <div class="author-section">
                                <?php echo createAvatarPlaceholder($article['author'], '#0066CC'); ?>
                                <div class="author-details">
                                    <span class="author-name"><?php echo htmlspecialchars($article['author']); ?></span>
                                    <span class="publish-date"><?php echo $article['date']; ?></span>
                                </div>
                            </div>
                            
                            <div class="article-interactions">
                                <button class="interaction-btn like-btn" onclick="likeArticle(<?php echo $index + 1; ?>)">
                                    <i class="far fa-heart"></i>
                                    <span><?php echo $article['likes']; ?></span>
                                </button>
                                <button class="interaction-btn bookmark-btn" onclick="bookmarkArticle(<?php echo $index + 1; ?>)">
                                    <i class="far fa-bookmark"></i>
                                </button>
                                <button class="interaction-btn share-btn" onclick="shareArticle(<?php echo $index + 1; ?>)">
                                    <i class="fas fa-share-alt"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="article-stats">
                            <span><i class="fas fa-eye"></i> <?php echo $article['views']; ?></span>
                            <span><i class="fas fa-comment"></i> <?php echo $article['comments']; ?></span>
                        </div>
                    </div>
                </article>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Load More Button -->
    <div class="text-center mt-5">
        <button class="btn btn-secondary-custom btn-lg" onclick="loadMoreArticles()">
            <i class="fas fa-plus-circle me-2"></i>
            Charger plus d'articles
        </button>
    </div>
</section>

<!-- Newsletter Section -->
<section class="newsletter-blog-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <div class="newsletter-content">
                    <h3>Restez à la Pointe de l'Actualité Musicale</h3>
                    <p>Recevez chaque semaine notre sélection d'articles, interviews exclusives et analyses culturelles directement dans votre boîte mail.</p>
                    <div class="newsletter-features">
                        <div class="feature">
                            <i class="fas fa-check-circle"></i>
                            <span>Articles exclusifs en avant-première</span>
                        </div>
                        <div class="feature">
                            <i class="fas fa-check-circle"></i>
                            <span>Interviews d'artistes inédites</span>
                        </div>
                        <div class="feature">
                            <i class="fas fa-check-circle"></i>
                            <span>Analyses culturelles approfondies</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="newsletter-form-container">
                    <div class="newsletter-form">
                        <h4>Newsletter Tchadok</h4>
                        <form id="newsletterForm">
                            <div class="form-group">
                                <input type="email" placeholder="Votre adresse email" required>
                                <button type="submit">
                                    <i class="fas fa-paper-plane"></i>
                                    S'abonner
                                </button>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" id="newsletter-consent" required>
                                <label for="newsletter-consent">
                                    J'accepte de recevoir la newsletter et les offres de Tchadok
                                </label>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
/* Blog Page Styles */
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

/* Hero Section */
.blog-hero {
    background: linear-gradient(135deg, rgba(0, 102, 204, 0.9), rgba(255, 215, 0, 0.7)), 
                url('data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"%3E%3Cpath fill="%230066CC" fill-opacity="0.1" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,122.7C672,117,768,139,864,133.3C960,128,1056,96,1152,90.7C1248,85,1344,107,1392,117.3L1440,128L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"%3E%3C/path%3E%3C/svg%3E');
    background-size: cover;
    background-position: center;
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

.blog-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(255, 215, 0, 0.9);
    color: var(--gris-harmattan);
    padding: 0.75rem 1.5rem;
    border-radius: 25px;
    font-weight: 700;
    margin-bottom: 2rem;
}

.hero-content h1 {
    font-size: 4rem;
    font-weight: 900;
    color: white;
    text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
    margin-bottom: 1.5rem;
    line-height: 1.2;
}

.hero-content p {
    font-size: 1.3rem;
    color: white;
    margin-bottom: 2rem;
    opacity: 0.9;
}

.blog-stats {
    display: flex;
    gap: 2rem;
    margin-bottom: 2rem;
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

.hero-search {
    margin-top: 2rem;
}

.search-container {
    position: relative;
    max-width: 500px;
    background: rgba(255, 255, 255, 0.95);
    border-radius: 50px;
    padding: 0.5rem;
    display: flex;
    align-items: center;
    backdrop-filter: blur(10px);
}

.search-container i {
    color: var(--gris-harmattan);
    margin-left: 1rem;
}

.search-container input {
    border: none;
    background: transparent;
    flex: 1;
    padding: 0.75rem 1rem;
    font-size: 1rem;
    outline: none;
}

.search-btn {
    background: var(--bleu-tchadien);
    color: white;
    border: none;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.search-btn:hover {
    background: #0052a3;
    transform: scale(1.05);
}

/* Hero Animation */
.hero-visual {
    display: flex;
    justify-content: center;
    align-items: center;
    height: 500px;
}

.blog-animation {
    position: relative;
    width: 300px;
    height: 300px;
}

.article-cards {
    position: relative;
    width: 100%;
    height: 100%;
}

.floating-card {
    position: absolute;
    width: 80px;
    height: 100px;
    background: white;
    border-radius: 10px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    padding: 10px;
    animation: cardFloat 4s ease-in-out infinite;
}

.floating-card.card-1 {
    top: 20px;
    left: 50px;
    animation-delay: 0s;
}

.floating-card.card-2 {
    top: 80px;
    right: 30px;
    animation-delay: 1s;
}

.floating-card.card-3 {
    bottom: 60px;
    left: 20px;
    animation-delay: 2s;
}

@keyframes cardFloat {
    0%, 100% { transform: translateY(0) rotate(0deg); }
    50% { transform: translateY(-15px) rotate(2deg); }
}

.card-header {
    width: 100%;
    height: 8px;
    background: var(--bleu-tchadien);
    border-radius: 4px;
    margin-bottom: 8px;
}

.card-lines {
    width: 100%;
    height: 4px;
    background: #e9ecef;
    border-radius: 2px;
    margin-bottom: 4px;
}

.card-lines::before,
.card-lines::after {
    content: '';
    display: block;
    width: 80%;
    height: 3px;
    background: #e9ecef;
    border-radius: 2px;
    margin-top: 6px;
}

.card-lines::after {
    width: 60%;
}

.pen-icon {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 3rem;
    color: var(--jaune-solaire);
    animation: penWrite 3s ease-in-out infinite;
}

@keyframes penWrite {
    0%, 100% { transform: translate(-50%, -50%) rotate(0deg); }
    50% { transform: translate(-50%, -50%) rotate(15deg); }
}

/* Featured Articles */
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

.main-featured-article {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}

.main-featured-article:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
}

.article-image {
    position: relative;
    height: 300px;
    overflow: hidden;
}

.article-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.main-featured-article:hover .article-image img {
    transform: scale(1.05);
}

.article-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, rgba(0, 102, 204, 0.8), rgba(255, 215, 0, 0.3));
    opacity: 0;
    transition: opacity 0.3s ease;
}

.main-featured-article:hover .article-overlay {
    opacity: 1;
}

.category-badge {
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

.category-badge.featured {
    background: var(--jaune-solaire);
    color: var(--gris-harmattan);
}

.article-content {
    padding: 2rem;
}

.article-content h3 {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--gris-harmattan);
    margin-bottom: 1rem;
    line-height: 1.3;
}

.article-content p {
    color: #6c757d;
    margin-bottom: 1.5rem;
    line-height: 1.6;
}

.article-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.author-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.author-info img {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    border: 2px solid var(--jaune-solaire);
}

.author-name {
    font-weight: 600;
    color: var(--gris-harmattan);
    display: block;
}

.publish-date {
    font-size: 0.85rem;
    color: #6c757d;
}

.article-stats {
    display: flex;
    gap: 1rem;
}

.article-stats span {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.85rem;
    color: #6c757d;
}

/* Secondary Articles */
.secondary-articles {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.secondary-article {
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}

.secondary-article:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
}

.article-image-small {
    position: relative;
    height: 120px;
    overflow: hidden;
}

.article-image-small img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.category-badge.music {
    background: var(--rouge-terre);
    color: white;
}

.category-badge.culture {
    background: var(--vert-savane);
    color: white;
}

.article-content-small {
    padding: 1rem;
}

.article-content-small h4 {
    font-size: 1rem;
    font-weight: 700;
    color: var(--gris-harmattan);
    margin-bottom: 0.5rem;
    line-height: 1.3;
}

.article-content-small p {
    font-size: 0.85rem;
    color: #6c757d;
    margin-bottom: 0.75rem;
}

.read-time {
    font-size: 0.75rem;
    color: var(--bleu-tchadien);
    font-weight: 600;
}

.read-time i {
    margin-right: 0.25rem;
}

/* Categories Filter */
.categories-filter {
    text-align: center;
    margin-bottom: 3rem;
}

.categories-filter h3 {
    color: var(--gris-harmattan);
    font-weight: 700;
}

.filter-tabs {
    display: flex;
    justify-content: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.filter-btn {
    background: white;
    border: 2px solid #e9ecef;
    color: var(--gris-harmattan);
    padding: 0.75rem 1.5rem;
    border-radius: 50px;
    font-weight: 600;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.filter-btn:hover,
.filter-btn.active {
    background: var(--bleu-tchadien);
    border-color: var(--bleu-tchadien);
    color: white;
    transform: translateY(-2px);
}

/* Blog Cards */
.blog-card {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    height: 100%;
    cursor: pointer;
}

.blog-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
}

.blog-card-image {
    position: relative;
    height: 250px;
    overflow: hidden;
}

.blog-card-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.blog-card:hover .blog-card-image img {
    transform: scale(1.1);
}

.image-overlay {
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

.blog-card:hover .image-overlay {
    opacity: 1;
}

.read-btn {
    background: white;
    color: var(--bleu-tchadien);
    border: none;
    padding: 1rem 2rem;
    border-radius: 50px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transform: scale(0);
    transition: transform 0.3s ease;
}

.blog-card:hover .read-btn {
    transform: scale(1);
}

.category-badge.interviews {
    background: var(--bleu-tchadien);
    color: white;
}

.category-badge.actualites {
    background: var(--rouge-terre);
    color: white;
}

.category-badge.analyses {
    background: var(--vert-savane);
    color: white;
}

.category-badge.evenements {
    background: var(--jaune-solaire);
    color: var(--gris-harmattan);
}

.read-time-badge {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: rgba(0, 0, 0, 0.7);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 15px;
    font-size: 0.75rem;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.blog-card-content {
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    height: calc(100% - 250px);
}

.blog-card-content h3 {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--gris-harmattan);
    margin-bottom: 0.75rem;
    line-height: 1.3;
}

.blog-card-content p {
    color: #6c757d;
    margin-bottom: 1rem;
    flex-grow: 1;
    line-height: 1.5;
}

.article-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.author-section {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.author-section img {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    border: 2px solid var(--jaune-solaire);
}

.author-details {
    display: flex;
    flex-direction: column;
}

.author-name {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--gris-harmattan);
}

.publish-date {
    font-size: 0.75rem;
    color: #6c757d;
}

.article-interactions {
    display: flex;
    gap: 0.5rem;
}

.interaction-btn {
    background: none;
    border: none;
    color: #6c757d;
    padding: 0.25rem;
    border-radius: 50%;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    font-size: 0.85rem;
}

.interaction-btn:hover {
    background: var(--bleu-tchadien);
    color: white;
    transform: scale(1.1);
}

.like-btn span {
    margin-left: 0.25rem;
    font-size: 0.75rem;
}

.article-stats {
    display: flex;
    justify-content: space-between;
    font-size: 0.75rem;
    color: #6c757d;
    padding-top: 1rem;
    border-top: 1px solid #e9ecef;
}

.article-stats span {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

/* Newsletter Section */
.newsletter-blog-section {
    background: linear-gradient(135deg, var(--gris-harmattan), #1a2332);
    color: white;
    padding: 5rem 0;
    margin: 4rem 0 0;
}

.newsletter-content h3 {
    font-size: 2.5rem;
    font-weight: 900;
    margin-bottom: 1.5rem;
    color: white !important;
}

.newsletter-content p {
    font-size: 1.2rem;
    margin-bottom: 2rem;
    opacity: 0.9;
    color: rgba(255, 255, 255, 0.9) !important;
}

.newsletter-features {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.feature {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.feature i {
    color: var(--jaune-solaire);
    font-size: 1.1rem;
}

.newsletter-form-container {
    display: flex;
    justify-content: center;
}

.newsletter-form {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    padding: 2rem;
    border: 1px solid rgba(255, 255, 255, 0.2);
    max-width: 400px;
    width: 100%;
}

.newsletter-form h4 {
    text-align: center;
    margin-bottom: 1.5rem;
    color: white !important;
}

.form-group {
    position: relative;
    margin-bottom: 1rem;
}

.form-group input {
    width: 100%;
    padding: 1rem 1rem 1rem 3rem;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-radius: 15px;
    background: rgba(255, 255, 255, 0.1);
    color: white;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.form-group input::placeholder {
    color: rgba(255, 255, 255, 0.7);
}

.form-group input:focus {
    outline: none;
    border-color: var(--jaune-solaire);
    background: rgba(255, 255, 255, 0.2);
}

.form-group button {
    position: absolute;
    right: 5px;
    top: 50%;
    transform: translateY(-50%);
    background: var(--jaune-solaire);
    color: var(--gris-harmattan);
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 12px;
    font-weight: 700;
    transition: all 0.3s ease;
}

.form-group button:hover {
    background: #e6c200;
    transform: translateY(-50%) scale(1.05);
}

.form-check {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
}

.form-check input {
    width: auto;
}

/* Button Styles */
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
    
    .blog-stats {
        gap: 1rem;
        justify-content: center;
    }
    
    .stat-number {
        font-size: 1.5rem;
    }
    
    .filter-tabs {
        flex-direction: column;
        align-items: center;
    }
    
    .filter-btn {
        width: 200px;
        justify-content: center;
    }
    
    .article-footer {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }
    
    .floating-music-notes {
        font-size: 1.5rem;
    }
    
    .newsletter-content h3 {
        font-size: 2rem;
    }
    
    .newsletter-features {
        margin-bottom: 2rem;
    }
}

/* Hide class for filtering */
.article-item.hidden {
    display: none !important;
}
</style>

<script>
// JavaScript for Blog Page Interactions
$(document).ready(function() {
    // Search functionality
    $('#blogSearch').on('input', function() {
        const searchTerm = $(this).val().toLowerCase();
        $('.article-item').each(function() {
            const title = $(this).find('h3').text().toLowerCase();
            const excerpt = $(this).find('p').text().toLowerCase();
            
            if (title.includes(searchTerm) || excerpt.includes(searchTerm)) {
                $(this).removeClass('hidden');
            } else {
                $(this).addClass('hidden');
            }
        });
    });

    // Category filtering
    $('.filter-btn').on('click', function() {
        $('.filter-btn').removeClass('active');
        $(this).addClass('active');
        
        const category = $(this).data('category');
        
        $('.article-item').each(function() {
            if (category === 'all' || $(this).data('category') === category) {
                $(this).removeClass('hidden');
            } else {
                $(this).addClass('hidden');
            }
        });
        
        showNotification(`📂 Filtrage par catégorie: ${$(this).text().trim()}`);
    });

    // Newsletter form submission
    $('#newsletterForm').on('submit', function(e) {
        e.preventDefault();
        const email = $(this).find('input[type="email"]').val();
        
        // Simulate newsletter subscription
        showNotification(`📧 Merci ! Vous êtes maintenant abonné(e) à notre newsletter.`);
        $(this).find('input[type="email"]').val('');
        $(this).find('input[type="checkbox"]').prop('checked', false);
    });

    // Smooth scrolling for internal links
    $('a[href^="#"]').on('click', function(e) {
        e.preventDefault();
        const target = $($(this).attr('href'));
        if (target.length) {
            $('html, body').animate({
                scrollTop: target.offset().top - 80
            }, 800);
        }
    });
});

// Article interactions
function readArticle(articleId) {
    showNotification(`📖 Ouverture de l'article #${articleId}`);
    console.log('Reading article:', articleId);
    // Ici on redirigerait vers la page de l'article
}

function likeArticle(articleId) {
    const btn = event.target.closest('.like-btn');
    const icon = btn.querySelector('i');
    const count = btn.querySelector('span');
    
    if (icon.classList.contains('far')) {
        icon.classList.remove('far');
        icon.classList.add('fas');
        icon.style.color = '#e74c3c';
        count.textContent = parseInt(count.textContent) + 1;
        showNotification(`❤️ Article ajouté aux favoris`);
    } else {
        icon.classList.remove('fas');
        icon.classList.add('far');
        icon.style.color = '';
        count.textContent = parseInt(count.textContent) - 1;
        showNotification(`💔 Article retiré des favoris`);
    }
}

function bookmarkArticle(articleId) {
    const btn = event.target.closest('.bookmark-btn');
    const icon = btn.querySelector('i');
    
    if (icon.classList.contains('far')) {
        icon.classList.remove('far');
        icon.classList.add('fas');
        icon.style.color = '#f39c12';
        showNotification(`🔖 Article sauvegardé`);
    } else {
        icon.classList.remove('fas');
        icon.classList.add('far');
        icon.style.color = '';
        showNotification(`🗑️ Article retiré des sauvegardes`);
    }
}

function shareArticle(articleId) {
    if (navigator.share) {
        navigator.share({
            title: 'Article Tchadok',
            text: 'Découvrez cet article passionnant sur la musique tchadienne',
            url: window.location.href
        });
    } else {
        // Fallback: copy to clipboard
        navigator.clipboard.writeText(window.location.href);
        showNotification(`🔗 Lien copié dans le presse-papiers`);
    }
}

function loadMoreArticles() {
    showNotification(`⏳ Chargement de nouveaux articles...`);
    
    // Simulate loading more articles
    setTimeout(() => {
        showNotification(`✅ 6 nouveaux articles chargés`);
    }, 1500);
}

// Notification function
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

// Animation styles
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

// Intersection Observer for animations
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

// Observer blog cards
document.querySelectorAll('.blog-card').forEach(card => {
    observer.observe(card);
});
</script>

<?php include 'includes/footer.php'; ?>