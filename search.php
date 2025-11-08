<?php
/**
 * Page de recherche - Tchadok Platform
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';

$query = $_GET['q'] ?? '';
$filter = $_GET['filter'] ?? 'all'; // all, tracks, artists, albums

$pageTitle = !empty($query) ? 'Résultats pour "' . htmlspecialchars($query) . '"' : 'Recherche';
$pageDescription = 'Recherchez vos artistes, titres et albums tchadiens préférés sur Tchadok.';

// Simuler des résultats de recherche
$searchResults = [
    'tracks' => [],
    'artists' => [],
    'albums' => []
];

if (!empty($query)) {
    // Simulation de résultats de titres
    for ($i = 1; $i <= 8; $i++) {
        if (stripos("Titre Tchadien $i", $query) !== false || $query === '*') {
            $searchResults['tracks'][] = [
                'id' => $i,
                'title' => "Titre Tchadien $i",
                'artist_name' => "Artiste " . rand(1, 5),
                'album_cover' => 'assets/images/default-cover.jpg',
                'duration' => rand(180, 300),
                'total_streams' => rand(1000, 50000),
                'is_free' => rand(0, 1),
                'price' => rand(500, 2000)
            ];
        }
    }
    
    // Simulation de résultats d'artistes
    for ($i = 1; $i <= 6; $i++) {
        if (stripos("Artiste $i", $query) !== false || $query === '*') {
            $searchResults['artists'][] = [
                'id' => $i,
                'stage_name' => "Artiste Tchadien $i",
                'profile_image' => 'assets/images/default-avatar.png',
                'verified' => $i <= 3,
                'total_streams' => rand(10000, 100000),
                'track_count' => rand(5, 25)
            ];
        }
    }
    
    // Simulation de résultats d'albums
    for ($i = 1; $i <= 4; $i++) {
        if (stripos("Album $i", $query) !== false || $query === '*') {
            $searchResults['albums'][] = [
                'id' => $i,
                'title' => "Album Tchadien $i",
                'artist_name' => "Artiste " . rand(1, 5),
                'album_cover' => 'assets/images/default-cover.jpg',
                'release_date' => date('Y-m-d', strtotime('-' . rand(30, 365) . ' days')),
                'track_count' => rand(8, 15)
            ];
        }
    }
}

$totalResults = count($searchResults['tracks']) + count($searchResults['artists']) + count($searchResults['albums']);

include 'includes/header.php';
?>

<div class="container py-4">
    <div class="row">
        <div class="col-12">
            <!-- Header de recherche -->
            <div class="search-header mb-4">
                <div class="row align-items-center">
                    <div class="col-lg-8">
                        <?php if (!empty($query)): ?>
                            <h1 class="h3 mb-2">
                                Résultats pour <span class="text-primary">"<?php echo htmlspecialchars($query); ?>"</span>
                            </h1>
                            <p class="text-muted mb-0">
                                <?php echo $totalResults; ?> résultat<?php echo $totalResults > 1 ? 's' : ''; ?> trouvé<?php echo $totalResults > 1 ? 's' : ''; ?>
                            </p>
                        <?php else: ?>
                            <h1 class="h3 mb-2">Recherche Musicale</h1>
                            <p class="text-muted mb-0">Découvrez la musique tchadienne</p>
                        <?php endif; ?>
                    </div>
                    <div class="col-lg-4">
                        <!-- Formulaire de recherche amélioré -->
                        <form method="GET" class="search-form">
                            <div class="input-group">
                                <input type="text" name="q" class="form-control" 
                                       placeholder="Rechercher un titre, artiste, album..." 
                                       value="<?php echo htmlspecialchars($query); ?>"
                                       autocomplete="off">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($query)): ?>
            <!-- Filtres -->
            <div class="search-filters mb-4">
                <div class="btn-group" role="group">
                    <a href="?q=<?php echo urlencode($query); ?>&filter=all" 
                       class="btn <?php echo $filter === 'all' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                        Tout (<?php echo $totalResults; ?>)
                    </a>
                    <a href="?q=<?php echo urlencode($query); ?>&filter=tracks" 
                       class="btn <?php echo $filter === 'tracks' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                        Titres (<?php echo count($searchResults['tracks']); ?>)
                    </a>
                    <a href="?q=<?php echo urlencode($query); ?>&filter=artists" 
                       class="btn <?php echo $filter === 'artists' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                        Artistes (<?php echo count($searchResults['artists']); ?>)
                    </a>
                    <a href="?q=<?php echo urlencode($query); ?>&filter=albums" 
                       class="btn <?php echo $filter === 'albums' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                        Albums (<?php echo count($searchResults['albums']); ?>)
                    </a>
                </div>
            </div>
            
            <!-- Résultats -->
            <?php if ($totalResults > 0): ?>
                
                <!-- Titres -->
                <?php if (($filter === 'all' || $filter === 'tracks') && !empty($searchResults['tracks'])): ?>
                <section class="search-section mb-5">
                    <h2 class="h4 mb-3">
                        <i class="fas fa-music me-2"></i>Titres
                    </h2>
                    <div class="row g-3">
                        <?php foreach ($searchResults['tracks'] as $track): ?>
                        <div class="col-lg-6">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-auto">
                                            <div class="position-relative">
                                                <img src="<?php echo SITE_URL; ?>/<?php echo $track['album_cover']; ?>" 
                                                     alt="<?php echo htmlspecialchars($track['title']); ?>" 
                                                     class="rounded" style="width: 60px; height: 60px; object-fit: cover;">
                                                <button class="btn btn-primary btn-sm position-absolute top-50 start-50 translate-middle"
                                                        onclick="playTrack(<?php echo $track['id']; ?>)"
                                                        style="width: 30px; height: 30px; padding: 0;">
                                                    <i class="fas fa-play" style="font-size: 12px;"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="col">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($track['title']); ?></h6>
                                            <p class="text-muted mb-1 small"><?php echo htmlspecialchars($track['artist_name']); ?></p>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted">
                                                    <i class="fas fa-play me-1"></i>
                                                    <?php echo formatNumber($track['total_streams']); ?>
                                                </small>
                                                <div class="d-flex gap-2">
                                                    <button class="btn btn-outline-primary btn-sm"
                                                            onclick="toggleFavorite(<?php echo $track['id']; ?>, 'track')"
                                                            title="Favoris">
                                                        <i class="fas fa-heart"></i>
                                                    </button>
                                                    <?php if (!$track['is_free']): ?>
                                                    <button class="btn btn-warning btn-sm"
                                                            onclick="downloadTrack(<?php echo $track['id']; ?>)"
                                                            title="Acheter">
                                                        <i class="fas fa-download"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>
                
                <!-- Artistes -->
                <?php if (($filter === 'all' || $filter === 'artists') && !empty($searchResults['artists'])): ?>
                <section class="search-section mb-5">
                    <h2 class="h4 mb-3">
                        <i class="fas fa-users me-2"></i>Artistes
                    </h2>
                    <div class="row g-3">
                        <?php foreach ($searchResults['artists'] as $artist): ?>
                        <div class="col-lg-3 col-md-4 col-sm-6">
                            <div class="card border-0 shadow-sm text-center h-100">
                                <div class="card-body">
                                    <div class="position-relative d-inline-block mb-3">
                                        <img src="<?php echo SITE_URL; ?>/<?php echo $artist['profile_image']; ?>" 
                                             alt="<?php echo htmlspecialchars($artist['stage_name']); ?>" 
                                             class="rounded-circle" style="width: 80px; height: 80px; object-fit: cover;">
                                        <?php if ($artist['verified']): ?>
                                        <i class="fas fa-check-circle text-primary position-absolute bottom-0 end-0"></i>
                                        <?php endif; ?>
                                    </div>
                                    <h6 class="mb-2"><?php echo htmlspecialchars($artist['stage_name']); ?></h6>
                                    <p class="text-muted small mb-2"><?php echo $artist['track_count']; ?> titre<?php echo $artist['track_count'] > 1 ? 's' : ''; ?></p>
                                    <div class="d-flex justify-content-center gap-2">
                                        <button class="btn btn-primary btn-sm"
                                                onclick="playTrack(<?php echo $artist['id']; ?>)">
                                            <i class="fas fa-play"></i>
                                        </button>
                                        <button class="btn btn-outline-secondary btn-sm"
                                                onclick="toggleFavorite(<?php echo $artist['id']; ?>, 'artist')">
                                            <i class="fas fa-heart"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>
                
                <!-- Albums -->
                <?php if (($filter === 'all' || $filter === 'albums') && !empty($searchResults['albums'])): ?>
                <section class="search-section mb-5">
                    <h2 class="h4 mb-3">
                        <i class="fas fa-compact-disc me-2"></i>Albums
                    </h2>
                    <div class="row g-3">
                        <?php foreach ($searchResults['albums'] as $album): ?>
                        <div class="col-lg-3 col-md-4 col-sm-6">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="position-relative">
                                    <img src="<?php echo SITE_URL; ?>/<?php echo $album['album_cover']; ?>" 
                                         alt="<?php echo htmlspecialchars($album['title']); ?>" 
                                         class="card-img-top" style="height: 200px; object-fit: cover;">
                                    <div class="position-absolute top-50 start-50 translate-middle">
                                        <button class="btn btn-primary rounded-circle"
                                                onclick="playTrack(<?php echo $album['id']; ?>)"
                                                style="width: 50px; height: 50px;">
                                            <i class="fas fa-play"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($album['title']); ?></h6>
                                    <p class="text-muted small mb-2"><?php echo htmlspecialchars($album['artist_name']); ?></p>
                                    <small class="text-muted">
                                        <?php echo $album['track_count']; ?> titre<?php echo $album['track_count'] > 1 ? 's' : ''; ?> • 
                                        <?php echo date('Y', strtotime($album['release_date'])); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>
                
            <?php else: ?>
                <!-- Aucun résultat -->
                <div class="text-center py-5">
                    <i class="fas fa-search text-muted" style="font-size: 4rem; opacity: 0.3;"></i>
                    <h3 class="mt-3">Aucun résultat trouvé</h3>
                    <p class="text-muted">Essayez avec d'autres mots-clés ou explorez nos suggestions ci-dessous.</p>
                </div>
            <?php endif; ?>
            
            <?php else: ?>
            <!-- Suggestions quand pas de recherche -->
            <div class="suggestions">
                <h2 class="h4 mb-4">Suggestions Populaires</h2>
                <div class="row g-3">
                    <div class="col-lg-3 col-md-6">
                        <a href="?q=afrobeat" class="card text-decoration-none border-0 shadow-sm">
                            <div class="card-body text-center">
                                <i class="fas fa-music text-primary fs-2 mb-3"></i>
                                <h6>Afrobeat</h6>
                                <small class="text-muted">Genre populaire</small>
                            </div>
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <a href="?q=traditionnel" class="card text-decoration-none border-0 shadow-sm">
                            <div class="card-body text-center">
                                <i class="fas fa-drum text-warning fs-2 mb-3"></i>
                                <h6>Traditionnel</h6>
                                <small class="text-muted">Musique ancestrale</small>
                            </div>
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <a href="?q=hip-hop" class="card text-decoration-none border-0 shadow-sm">
                            <div class="card-body text-center">
                                <i class="fas fa-microphone text-success fs-2 mb-3"></i>
                                <h6>Hip-Hop</h6>
                                <small class="text-muted">Rap tchadien</small>
                            </div>
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <a href="?q=gospel" class="card text-decoration-none border-0 shadow-sm">
                            <div class="card-body text-center">
                                <i class="fas fa-cross text-info fs-2 mb-3"></i>
                                <h6>Gospel</h6>
                                <small class="text-muted">Musique spirituelle</small>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.search-form .input-group {
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    border-radius: 8px;
    overflow: hidden;
}

.search-form .form-control {
    border: none;
    padding: 12px 16px;
}

.search-form .btn {
    border: none;
    padding: 12px 20px;
}

.search-section {
    position: relative;
}

.search-section h2 {
    color: #333;
    font-weight: 600;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.15) !important;
    transition: all 0.3s ease;
}

.suggestions .card:hover {
    background-color: #f8f9fa;
}

.btn-group .btn {
    border-radius: 0;
}

.btn-group .btn:first-child {
    border-top-left-radius: 6px;
    border-bottom-left-radius: 6px;
}

.btn-group .btn:last-child {
    border-top-right-radius: 6px;
    border-bottom-right-radius: 6px;
}
</style>

<?php include 'includes/footer.php'; ?>