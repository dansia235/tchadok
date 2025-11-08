<?php
/**
 * Dashboard Admin - Tchadok Platform
 * @author Tchadok Team
 * @version 1.0
 */

require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Vérification des droits d'accès
if (!isLoggedIn() || !isAdmin()) {
    redirect(SITE_URL . '/login.php');
}

$pageTitle = 'Administration';
$pageDescription = 'Tableau de bord administrateur de Tchadok Platform.';

$breadcrumbs = [
    ['title' => 'Administration', 'url' => '']
];

try {
    // Statistiques générales
    $stats = $db->fetchOne("
        SELECT 
            (SELECT COUNT(*) FROM users WHERE is_active = 1) as total_users,
            (SELECT COUNT(*) FROM users WHERE created_at >= CURDATE()) as new_users_today,
            (SELECT COUNT(*) FROM artists WHERE is_active = 1) as total_artists,
            (SELECT COUNT(*) FROM tracks WHERE status = 'approved') as total_tracks,
            (SELECT COUNT(*) FROM tracks WHERE status = 'pending') as pending_tracks,
            (SELECT COUNT(*) FROM albums WHERE status = 'approved') as total_albums,
            (SELECT COALESCE(SUM(total_streams), 0) FROM tracks) as total_streams,
            (SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE status = 'completed') as total_revenue,
            (SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE status = 'completed' AND DATE(created_at) = CURDATE()) as today_revenue,
            (SELECT COUNT(*) FROM reports WHERE status = 'pending') as pending_reports
    ");
    
    // Revenus par mois (12 derniers mois)
    $monthlyRevenue = $db->fetchAll("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            SUM(amount) as revenue,
            COUNT(*) as transactions_count
        FROM transactions 
        WHERE status = 'completed' 
            AND created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month DESC
    ");
    
    // Utilisateurs actifs par jour (30 derniers jours)
    $dailyActiveUsers = $db->fetchAll("
        SELECT 
            DATE(last_login) as date,
            COUNT(DISTINCT id) as active_users
        FROM users 
        WHERE last_login >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DATE(last_login)
        ORDER BY date DESC
    ");
    
    // Top artistes par revenus
    $topArtists = $db->fetchAll("
        SELECT a.*, u.first_name, u.last_name,
               COALESCE(SUM(p.amount - p.commission), 0) as total_earnings,
               COUNT(t.id) as tracks_count,
               SUM(t.total_streams) as total_streams
        FROM artists a
        JOIN users u ON a.user_id = u.id
        LEFT JOIN purchases p ON a.id = p.artist_id AND p.payment_status = 'completed'
        LEFT JOIN tracks t ON a.id = t.artist_id AND t.status = 'approved'
        WHERE a.is_active = 1
        GROUP BY a.id
        ORDER BY total_earnings DESC
        LIMIT 10
    ");
    
    // Dernières transactions
    $recentTransactions = $db->fetchAll("
        SELECT t.*, u.first_name, u.last_name, u.username,
               tr.title as track_title, alb.title as album_title, a.stage_name
        FROM transactions t
        JOIN users u ON t.user_id = u.id
        LEFT JOIN purchases p ON t.reference = p.payment_reference
        LEFT JOIN tracks tr ON p.item_type = 'track' AND p.item_id = tr.id
        LEFT JOIN albums alb ON p.item_type = 'album' AND p.item_id = alb.id
        LEFT JOIN artists a ON p.artist_id = a.id
        ORDER BY t.created_at DESC
        LIMIT 10
    ");
    
    // Contenus en attente de modération
    $pendingContent = [
        'tracks' => $db->fetchAll("
            SELECT t.*, a.stage_name as artist_name
            FROM tracks t
            JOIN artists a ON t.artist_id = a.id
            WHERE t.status = 'pending'
            ORDER BY t.created_at ASC
            LIMIT 5
        "),
        'albums' => $db->fetchAll("
            SELECT alb.*, a.stage_name as artist_name
            FROM albums alb
            JOIN artists a ON alb.artist_id = a.id
            WHERE alb.status = 'pending'
            ORDER BY alb.created_at ASC
            LIMIT 5
        "),
        'posts' => $db->fetchAll("
            SELECT bp.*, u.first_name, u.last_name, a.stage_name
            FROM blog_posts bp
            JOIN users u ON bp.author_id = u.id
            LEFT JOIN artists a ON u.id = a.user_id
            WHERE bp.status = 'draft'
            ORDER BY bp.created_at ASC
            LIMIT 5
        ")
    ];
    
    // Rapports récents
    $recentReports = $db->fetchAll("
        SELECT r.*, u.first_name, u.last_name,
               CASE r.reported_type
                   WHEN 'track' THEN t.title
                   WHEN 'artist' THEN a.stage_name
                   WHEN 'user' THEN CONCAT(u2.first_name, ' ', u2.last_name)
               END as reported_item_name
        FROM reports r
        JOIN users u ON r.reporter_id = u.id
        LEFT JOIN tracks t ON r.reported_type = 'track' AND r.reported_id = t.id
        LEFT JOIN artists a ON r.reported_type = 'artist' AND r.reported_id = a.id
        LEFT JOIN users u2 ON r.reported_type = 'user' AND r.reported_id = u2.id
        WHERE r.status = 'pending'
        ORDER BY r.created_at DESC
        LIMIT 5
    ");
    
    // Statistiques de téléchargement par pays
    $downloadsByCountry = $db->fetchAll("
        SELECT s.country, COUNT(*) as downloads_count
        FROM streams s
        WHERE s.country IS NOT NULL
            AND s.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY s.country
        ORDER BY downloads_count DESC
        LIMIT 10
    ");
    
    // Genres les plus populaires
    $popularGenres = $db->fetchAll("
        SELECT g.name, g.color,
               COUNT(t.id) as tracks_count,
               SUM(t.total_streams) as total_streams
        FROM genres g
        LEFT JOIN tracks t ON g.id = t.genre_id AND t.status = 'approved'
        GROUP BY g.id
        ORDER BY total_streams DESC
        LIMIT 8
    ");
    
} catch (Exception $e) {
    logActivity(LOG_LEVEL_ERROR, "Erreur dashboard admin: " . $e->getMessage());
    $stats = array_fill_keys(['total_users', 'new_users_today', 'total_artists', 'total_tracks', 'pending_tracks', 'total_albums', 'total_streams', 'total_revenue', 'today_revenue', 'pending_reports'], 0);
    $monthlyRevenue = $dailyActiveUsers = $topArtists = $recentTransactions = $recentReports = $downloadsByCountry = $popularGenres = [];
    $pendingContent = ['tracks' => [], 'albums' => [], 'posts' => []];
}

$additionalCSS = [
    SITE_URL . '/assets/css/admin.css',
    'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.css'
];

include '../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <!-- Sidebar Admin -->
        <div class="col-md-3 col-lg-2">
            <div class="sidebar">
                <h5 class="sidebar-title">Administration</h5>
                <nav class="nav flex-column">
                    <a class="nav-link sidebar-item active" href="#dashboard">
                        <i class="fas fa-chart-line me-2"></i>Tableau de Bord
                    </a>
                    <a class="nav-link sidebar-item" href="<?php echo SITE_URL; ?>/pages/admin/users.php">
                        <i class="fas fa-users me-2"></i>Utilisateurs
                    </a>
                    <a class="nav-link sidebar-item" href="<?php echo SITE_URL; ?>/pages/admin/artists.php">
                        <i class="fas fa-microphone me-2"></i>Artistes
                    </a>
                    <a class="nav-link sidebar-item" href="<?php echo SITE_URL; ?>/pages/admin/content.php">
                        <i class="fas fa-music me-2"></i>Contenu
                        <?php if ($stats['pending_tracks'] > 0): ?>
                        <span class="badge bg-warning ms-2"><?php echo $stats['pending_tracks']; ?></span>
                        <?php endif; ?>
                    </a>
                    <a class="nav-link sidebar-item" href="<?php echo SITE_URL; ?>/pages/admin/transactions.php">
                        <i class="fas fa-credit-card me-2"></i>Transactions
                    </a>
                    <a class="nav-link sidebar-item" href="<?php echo SITE_URL; ?>/pages/admin/reports.php">
                        <i class="fas fa-flag me-2"></i>Signalements
                        <?php if ($stats['pending_reports'] > 0): ?>
                        <span class="badge bg-danger ms-2"><?php echo $stats['pending_reports']; ?></span>
                        <?php endif; ?>
                    </a>
                    <a class="nav-link sidebar-item" href="<?php echo SITE_URL; ?>/pages/admin/analytics.php">
                        <i class="fas fa-chart-bar me-2"></i>Analytics
                    </a>
                    <a class="nav-link sidebar-item" href="<?php echo SITE_URL; ?>/pages/admin/settings.php">
                        <i class="fas fa-cogs me-2"></i>Paramètres
                    </a>
                </nav>
            </div>
        </div>

        <!-- Contenu Principal -->
        <div class="col-md-9 col-lg-10">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-1">Tableau de Bord Administrateur</h1>
                    <p class="text-muted mb-0">Vue d'ensemble de la plateforme Tchadok</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary" onclick="exportData()">
                        <i class="fas fa-download me-2"></i>Exporter
                    </button>
                    <button class="btn btn-primary" onclick="refreshDashboard()">
                        <i class="fas fa-sync-alt me-2"></i>Actualiser
                    </button>
                </div>
            </div>

            <!-- Cartes de statistiques -->
            <div class="row g-4 mb-4">
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon bg-primary">
                                <i class="fas fa-users text-white"></i>
                            </div>
                            <div class="ms-3">
                                <div class="stat-number"><?php echo formatNumber($stats['total_users']); ?></div>
                                <div class="stat-label">Utilisateurs</div>
                                <small class="text-success">
                                    <i class="fas fa-arrow-up"></i> +<?php echo $stats['new_users_today']; ?> aujourd'hui
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="stat-card">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon bg-success">
                                <i class="fas fa-microphone text-white"></i>
                            </div>
                            <div class="ms-3">
                                <div class="stat-number"><?php echo formatNumber($stats['total_artists']); ?></div>
                                <div class="stat-label">Artistes</div>
                                <small class="text-info"><?php echo formatNumber($stats['total_tracks']); ?> titres</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="stat-card">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon bg-info">
                                <i class="fas fa-play text-white"></i>
                            </div>
                            <div class="ms-3">
                                <div class="stat-number"><?php echo formatNumber($stats['total_streams']); ?></div>
                                <div class="stat-label">Écoutes Totales</div>
                                <small class="text-muted"><?php echo formatNumber($stats['total_albums']); ?> albums</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="stat-card">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon bg-warning">
                                <i class="fas fa-money-bill text-white"></i>
                            </div>
                            <div class="ms-3">
                                <div class="stat-number"><?php echo formatPrice($stats['total_revenue']); ?></div>
                                <div class="stat-label">Revenus Totaux</div>
                                <small class="text-success">
                                    <i class="fas fa-arrow-up"></i> <?php echo formatPrice($stats['today_revenue']); ?> aujourd'hui
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Alertes et notifications -->
            <?php if ($stats['pending_tracks'] > 0 || $stats['pending_reports'] > 0): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="alert alert-warning d-flex align-items-center" role="alert">
                        <i class="fas fa-exclamation-triangle me-3"></i>
                        <div>
                            <strong>Attention requise :</strong>
                            <?php if ($stats['pending_tracks'] > 0): ?>
                            <a href="<?php echo SITE_URL; ?>/pages/admin/content.php" class="alert-link">
                                <?php echo $stats['pending_tracks']; ?> titre(s) en attente de validation
                            </a>
                            <?php endif; ?>
                            <?php if ($stats['pending_reports'] > 0): ?>
                            <?php if ($stats['pending_tracks'] > 0): ?> • <?php endif; ?>
                            <a href="<?php echo SITE_URL; ?>/pages/admin/reports.php" class="alert-link">
                                <?php echo $stats['pending_reports']; ?> signalement(s) à traiter
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="row g-4">
                <!-- Graphique des revenus -->
                <div class="col-lg-8">
                    <div class="card-tchadok">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-chart-area me-2"></i>
                                Évolution des Revenus (12 derniers mois)
                            </h5>
                        </div>
                        <div class="card-body">
                            <canvas id="revenueChart" height="300"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Modération en attente -->
                <div class="col-lg-4">
                    <div class="card-tchadok">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-clock me-2"></i>
                                Modération Requise
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <?php foreach ($pendingContent['tracks'] as $track): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($track['title']); ?></h6>
                                        <small class="text-muted">Par <?php echo htmlspecialchars($track['artist_name']); ?></small>
                                    </div>
                                    <span class="badge bg-warning">Titre</span>
                                </div>
                                <?php endforeach; ?>

                                <?php foreach ($pendingContent['albums'] as $album): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($album['title']); ?></h6>
                                        <small class="text-muted">Par <?php echo htmlspecialchars($album['artist_name']); ?></small>
                                    </div>
                                    <span class="badge bg-info">Album</span>
                                </div>
                                <?php endforeach; ?>

                                <?php if (empty($pendingContent['tracks']) && empty($pendingContent['albums'])): ?>
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-check-circle fs-1 mb-3"></i>
                                    <p>Aucun contenu en attente</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mt-2">
                <!-- Top Artistes -->
                <div class="col-lg-6">
                    <div class="card-tchadok">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-trophy me-2"></i>
                                Top Artistes (par revenus)
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Artiste</th>
                                            <th>Titres</th>
                                            <th>Revenus</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($topArtists, 0, 5) as $index => $artist): ?>
                                        <tr>
                                            <td><span class="badge bg-primary"><?php echo $index + 1; ?></span></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <img src="<?php echo SITE_URL . '/' . ($artist['profile_image'] ?: 'assets/images/default-avatar.png'); ?>" 
                                                         alt="<?php echo htmlspecialchars($artist['stage_name']); ?>" 
                                                         class="rounded-circle me-2" 
                                                         style="width: 32px; height: 32px; object-fit: cover;">
                                                    <div>
                                                        <h6 class="mb-0"><?php echo htmlspecialchars($artist['stage_name']); ?></h6>
                                                        <?php if ($artist['verified']): ?>
                                                        <i class="fas fa-check-circle text-primary" title="Vérifié"></i>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo formatNumber($artist['tracks_count']); ?></td>
                                            <td class="fw-bold text-success"><?php echo formatPrice($artist['total_earnings']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Transactions récentes -->
                <div class="col-lg-6">
                    <div class="card-tchadok">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-credit-card me-2"></i>
                                Transactions Récentes
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <?php foreach (array_slice($recentTransactions, 0, 5) as $transaction): ?>
                                <div class="list-group-item border-0 px-0">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1">
                                                <?php echo htmlspecialchars($transaction['track_title'] ?: $transaction['album_title'] ?: $transaction['description']); ?>
                                            </h6>
                                            <p class="mb-1 text-muted">
                                                <?php echo htmlspecialchars($transaction['first_name'] . ' ' . $transaction['last_name']); ?>
                                                <?php if ($transaction['stage_name']): ?>
                                                → <?php echo htmlspecialchars($transaction['stage_name']); ?>
                                                <?php endif; ?>
                                            </p>
                                            <small class="text-muted"><?php echo timeAgo($transaction['created_at']); ?></small>
                                        </div>
                                        <div class="text-end">
                                            <div class="fw-bold <?php echo $transaction['status'] === 'completed' ? 'text-success' : 'text-warning'; ?>">
                                                <?php echo formatPrice($transaction['amount']); ?>
                                            </div>
                                            <span class="badge bg-<?php echo $transaction['status'] === 'completed' ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst($transaction['status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Signalements et genres populaires -->
            <div class="row g-4 mt-2">
                <!-- Signalements récents -->
                <?php if (!empty($recentReports)): ?>
                <div class="col-lg-6">
                    <div class="card-tchadok">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-flag me-2"></i>
                                Signalements Récents
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <?php foreach ($recentReports as $report): ?>
                                <div class="list-group-item border-0 px-0">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($report['reported_item_name']); ?></h6>
                                            <p class="mb-1 text-muted">
                                                Motif: <?php echo htmlspecialchars($report['reason']); ?>
                                            </p>
                                            <small class="text-muted">
                                                Par <?php echo htmlspecialchars($report['first_name'] . ' ' . $report['last_name']); ?> •
                                                <?php echo timeAgo($report['created_at']); ?>
                                            </small>
                                        </div>
                                        <span class="badge bg-<?php echo $report['reported_type'] === 'track' ? 'warning' : 'danger'; ?>">
                                            <?php echo ucfirst($report['reported_type']); ?>
                                        </span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Genres populaires -->
                <div class="col-lg-<?php echo empty($recentReports) ? '12' : '6'; ?>">
                    <div class="card-tchadok">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-music me-2"></i>
                                Genres Populaires
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <?php foreach ($popularGenres as $genre): ?>
                                <div class="col-md-<?php echo empty($recentReports) ? '3' : '6'; ?>">
                                    <div class="text-center p-3 border rounded" style="border-color: <?php echo $genre['color']; ?>22 !important; background: <?php echo $genre['color']; ?>11;">
                                        <div class="fs-4 mb-2" style="color: <?php echo $genre['color']; ?>;">
                                            <i class="fas fa-music"></i>
                                        </div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($genre['name']); ?></h6>
                                        <small class="text-muted">
                                            <?php echo formatNumber($genre['tracks_count']); ?> titres •
                                            <?php echo formatNumber($genre['total_streams']); ?> écoutes
                                        </small>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions rapides admin -->
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
                                <div class="col-md-2">
                                    <a href="<?php echo SITE_URL; ?>/pages/admin/content.php" class="btn btn-primary w-100">
                                        <i class="fas fa-check d-block mb-2 fs-4"></i>
                                        Modérer Contenu
                                    </a>
                                </div>
                                <div class="col-md-2">
                                    <a href="<?php echo SITE_URL; ?>/pages/admin/users.php" class="btn btn-success w-100">
                                        <i class="fas fa-users d-block mb-2 fs-4"></i>
                                        Gérer Utilisateurs
                                    </a>
                                </div>
                                <div class="col-md-2">
                                    <a href="<?php echo SITE_URL; ?>/pages/admin/reports.php" class="btn btn-warning w-100">
                                        <i class="fas fa-flag d-block mb-2 fs-4"></i>
                                        Traiter Signalements
                                    </a>
                                </div>
                                <div class="col-md-2">
                                    <a href="<?php echo SITE_URL; ?>/pages/admin/analytics.php" class="btn btn-info w-100">
                                        <i class="fas fa-chart-bar d-block mb-2 fs-4"></i>
                                        Analytics Détaillés
                                    </a>
                                </div>
                                <div class="col-md-2">
                                    <a href="<?php echo SITE_URL; ?>/pages/admin/backup.php" class="btn btn-secondary w-100">
                                        <i class="fas fa-database d-block mb-2 fs-4"></i>
                                        Sauvegardes
                                    </a>
                                </div>
                                <div class="col-md-2">
                                    <a href="<?php echo SITE_URL; ?>/pages/admin/settings.php" class="btn btn-dark w-100">
                                        <i class="fas fa-cogs d-block mb-2 fs-4"></i>
                                        Paramètres
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
    height: 100%;
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
    position: relative;
}

.sidebar-item:hover,
.sidebar-item.active {
    background: var(--primary-color);
    color: white;
    text-decoration: none;
}

.badge {
    font-size: 0.7rem;
}
</style>

<?php
$additionalJS = [
    'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js'
];

// Données pour le graphique des revenus
$revenueData = [];
$revenueLabels = [];
foreach ($monthlyRevenue as $revenue) {
    $revenueLabels[] = date('M Y', strtotime($revenue['month'] . '-01'));
    $revenueData[] = $revenue['revenue'];
}

// Inversion pour afficher chronologiquement
$revenueLabels = array_reverse($revenueLabels);
$revenueData = array_reverse($revenueData);
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Graphique des revenus
    const ctx = document.getElementById('revenueChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($revenueLabels); ?>,
            datasets: [{
                label: 'Revenus (FCFA)',
                data: <?php echo json_encode($revenueData); ?>,
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
            }
        }
    });
});

function exportData() {
    window.open('<?php echo SITE_URL; ?>/pages/admin/export.php', '_blank');
}

function refreshDashboard() {
    location.reload();
}
</script>

<?php include '../../includes/footer.php'; ?>