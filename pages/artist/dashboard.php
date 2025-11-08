<?php
/**
 * Dashboard Artiste - Tchadok Platform
 * @author Tchadok Team
 * @version 1.0
 */

require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Vérification des droits d'accès
if (!isLoggedIn() || !isArtist()) {
    redirect(SITE_URL . '/login.php');
}

$pageTitle = 'Dashboard Artiste';
$pageDescription = 'Gérez votre musique, consultez vos statistiques et suivez vos revenus sur Tchadok.';

$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => '']
];

$artistId = $_SESSION['artist_id'];

try {
    // Informations de l'artiste
    $artist = $db->fetchOne("
        SELECT a.*, u.first_name, u.last_name, u.email, u.phone, u.profile_image
        FROM artists a
        JOIN users u ON a.user_id = u.id
        WHERE a.id = ?
    ", [$artistId]);
    
    // Statistiques générales
    $stats = $db->fetchOne("
        SELECT 
            COUNT(DISTINCT t.id) as total_tracks,
            COUNT(DISTINCT alb.id) as total_albums,
            COALESCE(SUM(t.total_streams), 0) as total_streams,
            COALESCE(SUM(t.total_sales), 0) as total_sales,
            COALESCE(a.total_earnings, 0) as total_earnings,
            COUNT(DISTINCT f.user_id) as followers_count
        FROM artists a
        LEFT JOIN tracks t ON a.id = t.artist_id AND t.status = 'approved'
        LEFT JOIN albums alb ON a.id = alb.artist_id AND alb.status = 'approved'
        LEFT JOIN follows f ON a.id = f.followed_id AND f.followed_type = 'artist'
        WHERE a.id = ?
        GROUP BY a.id
    ", [$artistId]);
    
    // Revenus par mois (12 derniers mois)
    $monthlyEarnings = $db->fetchAll("
        SELECT 
            DATE_FORMAT(p.created_at, '%Y-%m') as month,
            SUM(p.amount - p.commission) as earnings,
            COUNT(p.id) as sales_count
        FROM purchases p
        WHERE p.artist_id = ? AND p.payment_status = 'completed'
            AND p.created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(p.created_at, '%Y-%m')
        ORDER BY month DESC
    ", [$artistId]);
    
    // Top 10 des titres les plus écoutés
    $topTracks = $db->fetchAll("
        SELECT t.*, alb.title as album_title, alb.cover_image as album_cover,
               g.name as genre_name
        FROM tracks t
        LEFT JOIN albums alb ON t.album_id = alb.id
        LEFT JOIN genres g ON t.genre_id = g.id
        WHERE t.artist_id = ? AND t.status = 'approved'
        ORDER BY t.total_streams DESC
        LIMIT 10
    ", [$artistId]);
    
    // Ventes récentes
    $recentSales = $db->fetchAll("
        SELECT p.*, t.title as track_title, alb.title as album_title,
               u.first_name, u.last_name, u.username
        FROM purchases p
        LEFT JOIN tracks t ON p.item_type = 'track' AND p.item_id = t.id
        LEFT JOIN albums alb ON p.item_type = 'album' AND p.item_id = alb.id
        JOIN users u ON p.user_id = u.id
        WHERE p.artist_id = ? AND p.payment_status = 'completed'
        ORDER BY p.created_at DESC
        LIMIT 10
    ", [$artistId]);
    
    // Statistiques d'écoute par pays
    $streamsByCountry = $db->fetchAll("
        SELECT s.country, COUNT(*) as streams_count
        FROM streams s
        JOIN tracks t ON s.track_id = t.id
        WHERE t.artist_id = ? AND s.country IS NOT NULL
        GROUP BY s.country
        ORDER BY streams_count DESC
        LIMIT 10
    ", [$artistId]);
    
    // Titres en attente de validation
    $pendingTracks = $db->fetchAll("
        SELECT t.*, alb.title as album_title
        FROM tracks t
        LEFT JOIN albums alb ON t.album_id = alb.id
        WHERE t.artist_id = ? AND t.status IN ('draft', 'pending')
        ORDER BY t.created_at DESC
    ", [$artistId]);
    
    // Commentaires récents
    $recentComments = $db->fetchAll("
        SELECT tc.*, t.title as track_title, u.first_name, u.last_name, u.username
        FROM track_comments tc
        JOIN tracks t ON tc.track_id = t.id
        JOIN users u ON tc.user_id = u.id
        WHERE t.artist_id = ? AND tc.status = 'approved'
        ORDER BY tc.created_at DESC
        LIMIT 5
    ", [$artistId]);
    
} catch (Exception $e) {
    logActivity(LOG_LEVEL_ERROR, "Erreur dashboard artiste: " . $e->getMessage());
    $stats = ['total_tracks' => 0, 'total_albums' => 0, 'total_streams' => 0, 'total_sales' => 0, 'total_earnings' => 0, 'followers_count' => 0];
    $monthlyEarnings = $topTracks = $recentSales = $streamsByCountry = $pendingTracks = $recentComments = [];
}

$additionalCSS = [
    SITE_URL . '/assets/css/dashboard.css',
    'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.css'
];

include '../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <!-- Sidebar Navigation -->
        <div class="col-md-3 col-lg-2">
            <div class="sidebar">
                <h5 class="sidebar-title">Navigation</h5>
                <nav class="nav flex-column">
                    <a class="nav-link sidebar-item active" href="#overview">
                        <i class="fas fa-chart-line me-2"></i>Vue d'ensemble
                    </a>
                    <a class="nav-link sidebar-item" href="<?php echo SITE_URL; ?>/pages/artist/upload.php">
                        <i class="fas fa-upload me-2"></i>Uploader
                    </a>
                    <a class="nav-link sidebar-item" href="<?php echo SITE_URL; ?>/pages/artist/tracks.php">
                        <i class="fas fa-music me-2"></i>Mes Titres
                    </a>
                    <a class="nav-link sidebar-item" href="<?php echo SITE_URL; ?>/pages/artist/albums.php">
                        <i class="fas fa-compact-disc me-2"></i>Mes Albums
                    </a>
                    <a class="nav-link sidebar-item" href="<?php echo SITE_URL; ?>/pages/artist/analytics.php">
                        <i class="fas fa-chart-bar me-2"></i>Analytics
                    </a>
                    <a class="nav-link sidebar-item" href="<?php echo SITE_URL; ?>/pages/artist/earnings.php">
                        <i class="fas fa-dollar-sign me-2"></i>Revenus
                    </a>
                    <a class="nav-link sidebar-item" href="<?php echo SITE_URL; ?>/pages/artist/profile.php">
                        <i class="fas fa-user me-2"></i>Mon Profil
                    </a>
                    <a class="nav-link sidebar-item" href="<?php echo SITE_URL; ?>/pages/artist/fans.php">
                        <i class="fas fa-users me-2"></i>Mes Fans
                    </a>
                </nav>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-9 col-lg-10">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-1">Bonjour, <?php echo htmlspecialchars($artist['stage_name']); ?> 👋</h1>
                    <p class="text-muted mb-0">Voici un aperçu de vos performances musicales</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?php echo SITE_URL; ?>/pages/artist/upload.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Nouveau Titre
                    </a>
                    <a href="<?php echo SITE_URL; ?>/artist.php?id=<?php echo $artistId; ?>" class="btn btn-outline-primary" target="_blank">
                        <i class="fas fa-external-link-alt me-2"></i>Voir Profil Public
                    </a>
                </div>
            </div>

            <!-- Cards de statistiques -->
            <div class="row g-4 mb-4">
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon bg-primary">
                                <i class="fas fa-music text-white"></i>
                            </div>
                            <div class="ms-3">
                                <div class="stat-number"><?php echo formatNumber($stats['total_tracks']); ?></div>
                                <div class="stat-label">Titres</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon bg-success">
                                <i class="fas fa-play text-white"></i>
                            </div>
                            <div class="ms-3">
                                <div class="stat-number"><?php echo formatNumber($stats['total_streams']); ?></div>
                                <div class="stat-label">Écoutes</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon bg-warning">
                                <i class="fas fa-users text-white"></i>
                            </div>
                            <div class="ms-3">
                                <div class="stat-number"><?php echo formatNumber($stats['followers_count']); ?></div>
                                <div class="stat-label">Fans</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon bg-info">
                                <i class="fas fa-dollar-sign text-white"></i>
                            </div>
                            <div class="ms-3">
                                <div class="stat-number"><?php echo formatPrice($stats['total_earnings']); ?></div>
                                <div class="stat-label">Revenus</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <!-- Graphique des revenus -->
                <div class="col-lg-8">
                    <div class="card-tchadok">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-chart-line me-2"></i>
                                Évolution des Revenus (12 derniers mois)
                            </h5>
                        </div>
                        <div class="card-body">
                            <canvas id="earningsChart" height="300"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Titres en attente -->
                <div class="col-lg-4">
                    <div class="card-tchadok">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-clock me-2"></i>
                                Titres en Attente
                                <?php if (count($pendingTracks) > 0): ?>
                                <span class="badge bg-warning ms-2"><?php echo count($pendingTracks); ?></span>
                                <?php endif; ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($pendingTracks)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-check-circle fs-1 mb-3"></i>
                                <p>Tous vos titres sont validés !</p>
                            </div>
                            <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($pendingTracks as $track): ?>
                                <div class="list-group-item border-0 px-0">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($track['title']); ?></h6>
                                            <small class="text-muted">
                                                Statut: 
                                                <span class="badge <?php echo $track['status'] === 'pending' ? 'bg-warning' : 'bg-secondary'; ?>">
                                                    <?php echo ucfirst($track['status']); ?>
                                                </span>
                                            </small>
                                        </div>
                                        <a href="<?php echo SITE_URL; ?>/pages/artist/edit-track.php?id=<?php echo $track['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mt-2">
                <!-- Top Titres -->
                <div class="col-lg-6">
                    <div class="card-tchadok">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-trophy me-2"></i>
                                Top Titres
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($topTracks)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-music fs-1 mb-3"></i>
                                <p>Uploadez vos premiers titres pour voir les statistiques</p>
                                <a href="<?php echo SITE_URL; ?>/pages/artist/upload.php" class="btn btn-primary">
                                    <i class="fas fa-upload me-2"></i>Uploader un titre
                                </a>
                            </div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Titre</th>
                                            <th>Écoutes</th>
                                            <th>Ventes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($topTracks, 0, 5) as $index => $track): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-primary"><?php echo $index + 1; ?></span>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <img src="<?php echo SITE_URL . '/' . ($track['album_cover'] ?: 'assets/images/default-cover.jpg'); ?>" 
                                                         alt="<?php echo htmlspecialchars($track['title']); ?>" 
                                                         class="rounded me-2" 
                                                         style="width: 40px; height: 40px; object-fit: cover;">
                                                    <div>
                                                        <h6 class="mb-0"><?php echo htmlspecialchars($track['title']); ?></h6>
                                                        <small class="text-muted"><?php echo htmlspecialchars($track['album_title'] ?: 'Single'); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="fw-bold"><?php echo formatNumber($track['total_streams']); ?></span>
                                            </td>
                                            <td>
                                                <span class="fw-bold"><?php echo formatNumber($track['total_sales']); ?></span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Ventes récentes -->
                <div class="col-lg-6">
                    <div class="card-tchadok">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-shopping-cart me-2"></i>
                                Ventes Récentes
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recentSales)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-shopping-cart fs-1 mb-3"></i>
                                <p>Aucune vente pour le moment</p>
                            </div>
                            <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach (array_slice($recentSales, 0, 5) as $sale): ?>
                                <div class="list-group-item border-0 px-0">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1">
                                                <?php echo htmlspecialchars($sale['track_title'] ?: $sale['album_title']); ?>
                                            </h6>
                                            <p class="mb-1 text-muted">
                                                Par <?php echo htmlspecialchars($sale['first_name'] . ' ' . $sale['last_name']); ?>
                                            </p>
                                            <small class="text-muted"><?php echo timeAgo($sale['created_at']); ?></small>
                                        </div>
                                        <div class="text-end">
                                            <div class="fw-bold text-success"><?php echo formatPrice($sale['amount']); ?></div>
                                            <small class="text-muted">Commission: <?php echo formatPrice($sale['commission']); ?></small>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Commentaires récents -->
            <?php if (!empty($recentComments)): ?>
            <div class="row g-4 mt-2">
                <div class="col-12">
                    <div class="card-tchadok">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-comments me-2"></i>
                                Commentaires Récents
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <?php foreach ($recentComments as $comment): ?>
                                <div class="col-md-6">
                                    <div class="border rounded p-3">
                                        <div class="d-flex align-items-center mb-2">
                                            <strong><?php echo htmlspecialchars($comment['first_name'] . ' ' . $comment['last_name']); ?></strong>
                                            <?php if ($comment['rating']): ?>
                                            <div class="ms-2">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star <?php echo $i <= $comment['rating'] ? 'text-warning' : 'text-muted'; ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <p class="mb-2"><?php echo htmlspecialchars($comment['content']); ?></p>
                                        <small class="text-muted">
                                            Sur "<?php echo htmlspecialchars($comment['track_title']); ?>" • 
                                            <?php echo timeAgo($comment['created_at']); ?>
                                        </small>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Actions rapides -->
            <div class="row g-4 mt-2">
                <div class="col-12">
                    <div class="card-tchadok">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-bolt me-2"></i>
                                Actions Rapides
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <a href="<?php echo SITE_URL; ?>/pages/artist/upload.php" class="btn btn-primary w-100">
                                        <i class="fas fa-upload d-block mb-2 fs-4"></i>
                                        Uploader un Titre
                                    </a>
                                </div>
                                <div class="col-md-3">
                                    <a href="<?php echo SITE_URL; ?>/pages/artist/create-album.php" class="btn btn-success w-100">
                                        <i class="fas fa-compact-disc d-block mb-2 fs-4"></i>
                                        Créer un Album
                                    </a>
                                </div>
                                <div class="col-md-3">
                                    <a href="<?php echo SITE_URL; ?>/pages/artist/analytics.php" class="btn btn-info w-100">
                                        <i class="fas fa-chart-bar d-block mb-2 fs-4"></i>
                                        Voir Analytics
                                    </a>
                                </div>
                                <div class="col-md-3">
                                    <a href="<?php echo SITE_URL; ?>/pages/artist/promote.php" class="btn btn-warning w-100">
                                        <i class="fas fa-bullhorn d-block mb-2 fs-4"></i>
                                        Promouvoir
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.stat-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: transform 0.2s;
}

.stat-card:hover {
    transform: translateY(-2px);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.stat-number {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--primary-color);
}

.stat-label {
    color: #6c757d;
    font-size: 0.875rem;
    font-weight: 500;
}

.sidebar {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    position: sticky;
    top: 100px;
}

.sidebar-item {
    color: #6c757d;
    padding: 0.75rem;
    border-radius: 8px;
    transition: all 0.2s;
    margin-bottom: 0.25rem;
}

.sidebar-item:hover,
.sidebar-item.active {
    background: var(--primary-color);
    color: white;
    text-decoration: none;
}

#earningsChart {
    max-height: 300px;
}
</style>

<?php
$additionalJS = [
    'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js',
    SITE_URL . '/assets/js/dashboard.js'
];

// Données pour le graphique des revenus
$earningsData = [];
$earningsLabels = [];
foreach ($monthlyEarnings as $earning) {
    $earningsLabels[] = date('M Y', strtotime($earning['month'] . '-01'));
    $earningsData[] = $earning['earnings'];
}

// Inversion pour afficher chronologiquement
$earningsLabels = array_reverse($earningsLabels);
$earningsData = array_reverse($earningsData);
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Graphique des revenus
    const ctx = document.getElementById('earningsChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($earningsLabels); ?>,
            datasets: [{
                label: 'Revenus (FCFA)',
                data: <?php echo json_encode($earningsData); ?>,
                borderColor: 'rgb(0, 102, 204)',
                backgroundColor: 'rgba(0, 102, 204, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return new Intl.NumberFormat('fr-FR', {
                                style: 'currency',
                                currency: 'XAF',
                                minimumFractionDigits: 0
                            }).format(value);
                        }
                    }
                }
            },
            elements: {
                point: {
                    radius: 6,
                    hoverRadius: 8
                }
            }
        }
    });
    
    // Animation des cartes de stats
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
        card.classList.add('animate-fade-in-up');
    });
});
</script>

<?php include '../../includes/footer.php'; ?>