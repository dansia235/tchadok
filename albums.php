<?php
/**
 * Page des albums - Tchadok Platform
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';

$pageTitle = 'Albums';
$pageDescription = 'Découvrez les albums de musique tchadienne sur Tchadok.';

include 'includes/header.php';
?>

<div class="container py-5">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="display-5 fw-bold">
                        <i class="fas fa-compact-disc text-primary me-3"></i>
                        Albums Tchadiens
                    </h1>
                    <p class="lead text-muted">Les meilleures compilations musicales du Tchad</p>
                </div>
                
                <div class="d-flex gap-2">
                    <select class="form-select">
                        <option>Tous les genres</option>
                        <option>Afrobeat</option>
                        <option>Hip-Hop</option>
                        <option>Gospel</option>
                        <option>Traditionnel</option>
                    </select>
                    <select class="form-select">
                        <option>Plus récents</option>
                        <option>Plus populaires</option>
                        <option>Alphabétique</option>
                    </select>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row g-4">
        <?php for ($i = 1; $i <= 12; $i++): 
            $albumTypes = ['Album', 'EP', 'Single', 'Compilation'];
            $randomType = $albumTypes[array_rand($albumTypes)];
            $tracksCount = $randomType === 'Single' ? 1 : rand(3, 15);
        ?>
        <div class="col-lg-3 col-md-4 col-sm-6">
            <div class="card-tchadok h-100">
                <div class="card-body text-center">
                    <div class="album-cover mb-3 position-relative">
                        <div class="bg-secondary rounded d-flex align-items-center justify-content-center" 
                             style="width: 100%; height: 200px;">
                            <img src="<?php echo SITE_URL; ?>/assets/images/logo-compact.svg" 
                                 alt="Album" style="width: 60px; height: 60px;">
                        </div>
                        <div class="play-overlay position-absolute top-50 start-50 translate-middle">
                            <button class="btn btn-primary rounded-circle p-3" 
                                    onclick="playTrack(<?php echo $i; ?>)" 
                                    title="Jouer l'album">
                                <i class="fas fa-play"></i>
                            </button>
                        </div>
                    </div>
                    
                    <h5 class="card-title">
                        <a href="#" class="text-decoration-none">
                            Album Tchadien <?php echo $i; ?>
                        </a>
                    </h5>
                    
                    <p class="text-muted mb-2">
                        par <a href="#" class="text-decoration-none">Artiste <?php echo rand(1, 8); ?></a>
                    </p>
                    
                    <div class="d-flex justify-content-between text-muted small mb-3">
                        <span>
                            <i class="fas fa-music me-1"></i>
                            <?php echo $tracksCount; ?> titre<?php echo $tracksCount > 1 ? 's' : ''; ?>
                        </span>
                        <span class="badge bg-secondary"><?php echo $randomType; ?></span>
                    </div>
                    
                    <div class="d-flex justify-content-between text-muted small mb-3">
                        <span>
                            <i class="fas fa-play me-1"></i>
                            <?php echo formatNumber(rand(5000, 100000)); ?>
                        </span>
                        <span>
                            <i class="fas fa-calendar me-1"></i>
                            <?php echo date('Y', strtotime('-' . rand(0, 730) . ' days')); ?>
                        </span>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary-tchadok btn-sm flex-fill"
                                onclick="playTrack(<?php echo $i; ?>)">
                            <i class="fas fa-play me-1"></i>Écouter
                        </button>
                        <button class="btn btn-outline-secondary btn-sm"
                                onclick="toggleFavorite(<?php echo $i; ?>, 'album')"
                                title="Ajouter aux favoris">
                            <i class="fas fa-heart"></i>
                        </button>
                        <button class="btn btn-outline-primary btn-sm"
                                onclick="addToPlaylist(<?php echo $i; ?>)"
                                title="Ajouter à une playlist">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endfor; ?>
    </div>
    
    <!-- Pagination -->
    <div class="row mt-5">
        <div class="col-12">
            <nav aria-label="Navigation des albums">
                <ul class="pagination justify-content-center">
                    <li class="page-item disabled">
                        <span class="page-link">Précédent</span>
                    </li>
                    <li class="page-item active">
                        <span class="page-link">1</span>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="#">2</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="#">3</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="#">Suivant</a>
                    </li>
                </ul>
            </nav>
        </div>
    </div>
</div>

<style>
.album-cover {
    position: relative;
    overflow: hidden;
    border-radius: 8px;
}

.play-overlay {
    opacity: 0;
    transition: opacity 0.3s ease;
    pointer-events: none;
}

.album-cover:hover .play-overlay {
    opacity: 1;
    pointer-events: all;
}

.album-cover:hover::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.4);
    border-radius: 8px;
}
</style>

<?php include 'includes/footer.php'; ?>