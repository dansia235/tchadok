<?php
/**
 * Dashboard Admin - Tchadok Platform
 * Panneau d'administration complet
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Vérifier si l'utilisateur est admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . SITE_URL . '/login.php');
    exit();
}

$pageTitle = 'Administration';
$pageDescription = 'Panneau d\'administration Tchadok';

$user = getCurrentUser();

// Récupérer les statistiques
try {
    $dbInstance = TchadokDatabase::getInstance();
    $db = $dbInstance->getConnection();

    // Stats générales
    $stats = [
        'total_users' => $db->query("SELECT COUNT(*) FROM users")->fetchColumn(),
        'total_artists' => $db->query("SELECT COUNT(*) FROM artists")->fetchColumn(),
        'total_songs' => $db->query("SELECT COUNT(*) FROM songs")->fetchColumn(),
        'total_albums' => $db->query("SELECT COUNT(*) FROM albums")->fetchColumn(),
        'total_plays' => $db->query("SELECT COALESCE(SUM(play_count), 0) FROM songs")->fetchColumn(),
        'premium_users' => $db->query("SELECT COUNT(*) FROM users WHERE premium_status = 1")->fetchColumn(),
        'active_subscriptions' => $db->query("SELECT COUNT(*) FROM subscriptions WHERE status = 'active'")->fetchColumn(),
        'total_revenue' => $db->query("SELECT COALESCE(SUM(amount), 0) FROM payment_transactions WHERE status = 'success'")->fetchColumn(),
    ];

    // Nouveaux utilisateurs (30 derniers jours)
    $stats['new_users_30d'] = $db->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();

    // Derniers utilisateurs
    $recent_users = $db->query("SELECT id, username, email, first_name, last_name, created_at, premium_status FROM users ORDER BY created_at DESC LIMIT 10")->fetchAll();

    // Top artistes par écoutes
    $top_artists = $db->query("
        SELECT a.id, a.stage_name, a.real_name, COALESCE(SUM(s.play_count), 0) as total_plays
        FROM artists a
        LEFT JOIN songs s ON a.id = s.artist_id
        GROUP BY a.id
        ORDER BY total_plays DESC
        LIMIT 10
    ")->fetchAll();

    // Top chansons
    $top_songs = $db->query("
        SELECT s.id, s.title, a.stage_name as artist_name, s.play_count, s.is_premium
        FROM songs s
        JOIN artists a ON s.artist_id = a.id
        ORDER BY s.play_count DESC
        LIMIT 10
    ")->fetchAll();

    // Transactions récentes
    $recent_transactions = $db->query("
        SELECT t.id, t.amount, t.currency, t.payment_method, t.status, t.created_at,
               u.username, u.first_name, u.last_name
        FROM payment_transactions t
        JOIN users u ON t.user_id = u.id
        ORDER BY t.created_at DESC
        LIMIT 10
    ")->fetchAll();

} catch (Exception $e) {
    $stats = [
        'total_users' => 0,
        'total_artists' => 0,
        'total_songs' => 0,
        'total_albums' => 0,
        'total_plays' => 0,
        'premium_users' => 0,
        'active_subscriptions' => 0,
        'total_revenue' => 0,
        'new_users_30d' => 0,
    ];
    $recent_users = [];
    $top_artists = [];
    $top_songs = [];
    $recent_transactions = [];
}

include 'includes/header.php';
?>

<div class="admin-dashboard-container">
    <!-- Header -->
    <section class="admin-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="d-flex align-items-center">
                        <div class="admin-icon">
                            <i class="fas fa-crown"></i>
                        </div>
                        <div>
                            <h1 class="mb-1">Administration Tchadok</h1>
                            <p class="text-muted mb-0">
                                <i class="fas fa-user-shield me-2"></i>
                                Administrateur : <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 text-lg-end mt-3 mt-lg-0">
                    <div class="admin-quick-actions">
                        <a href="<?php echo SITE_URL; ?>/admin-add-song.php" class="btn btn-primary">
                            <i class="fas fa-music me-2"></i>Ajouter Chanson
                        </a>
                        <a href="<?php echo SITE_URL; ?>/admin-add-album.php" class="btn btn-success">
                            <i class="fas fa-compact-disc me-2"></i>Ajouter Album
                        </a>
                        <a href="<?php echo SITE_URL; ?>/admin-manage-radio.php" class="btn btn-warning">
                            <i class="fas fa-broadcast-tower me-2"></i>Gérer Radio
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Grid -->
    <section class="admin-stats py-4">
        <div class="container-fluid">
            <div class="stats-grid">
                <!-- Total Utilisateurs -->
                <div class="stat-card stat-users">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">Utilisateurs</div>
                        <div class="stat-value"><?php echo number_format($stats['total_users']); ?></div>
                        <div class="stat-change positive">
                            <i class="fas fa-arrow-up"></i> <?php echo number_format($stats['new_users_30d']); ?> ce mois
                        </div>
                    </div>
                </div>

                <!-- Total Artistes -->
                <div class="stat-card stat-artists">
                    <div class="stat-icon">
                        <i class="fas fa-microphone-alt"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">Artistes</div>
                        <div class="stat-value"><?php echo number_format($stats['total_artists']); ?></div>
                        <div class="stat-info">
                            Contributeurs actifs
                        </div>
                    </div>
                </div>

                <!-- Total Chansons -->
                <div class="stat-card stat-songs">
                    <div class="stat-icon">
                        <i class="fas fa-music"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">Chansons</div>
                        <div class="stat-value"><?php echo number_format($stats['total_songs']); ?></div>
                        <div class="stat-info">
                            <?php echo number_format($stats['total_albums']); ?> albums
                        </div>
                    </div>
                </div>

                <!-- Total Écoutes -->
                <div class="stat-card stat-plays">
                    <div class="stat-icon">
                        <i class="fas fa-headphones"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">Écoutes Totales</div>
                        <div class="stat-value"><?php echo number_format($stats['total_plays']); ?></div>
                        <div class="stat-info">
                            Toutes les chansons
                        </div>
                    </div>
                </div>

                <!-- Premium Users -->
                <div class="stat-card stat-premium">
                    <div class="stat-icon">
                        <i class="fas fa-crown"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">Abonnés Premium</div>
                        <div class="stat-value"><?php echo number_format($stats['premium_users']); ?></div>
                        <div class="stat-info">
                            <?php echo number_format($stats['active_subscriptions']); ?> actifs
                        </div>
                    </div>
                </div>

                <!-- Revenus -->
                <div class="stat-card stat-revenue">
                    <div class="stat-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">Revenus Totaux</div>
                        <div class="stat-value"><?php echo number_format($stats['total_revenue'], 0, ',', ' '); ?> <small>XAF</small></div>
                        <div class="stat-info">
                            Tous les paiements
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Content Grid -->
    <section class="admin-content py-4">
        <div class="container-fluid">
            <div class="row g-4">
                <!-- Colonne Principale -->
                <div class="col-lg-8">
                    <!-- Derniers Utilisateurs -->
                    <div class="admin-card mb-4">
                        <div class="card-header-admin">
                            <h5><i class="fas fa-user-plus me-2"></i>Derniers Utilisateurs</h5>
                            <a href="#" class="btn btn-sm btn-outline-primary">Voir tout</a>
                        </div>
                        <div class="card-body-admin">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Utilisateur</th>
                                            <th>Email</th>
                                            <th>Type</th>
                                            <th>Date d'inscription</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_users as $u): ?>
                                        <tr>
                                            <td>
                                                <div class="user-info">
                                                    <div class="user-avatar-small">
                                                        <?php echo strtoupper(substr($u['first_name'], 0, 1) . substr($u['last_name'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?></strong>
                                                        <br><small class="text-muted">@<?php echo htmlspecialchars($u['username']); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($u['email']); ?></td>
                                            <td>
                                                <?php if ($u['premium_status']): ?>
                                                    <span class="badge bg-warning text-dark">
                                                        <i class="fas fa-crown"></i> Premium
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Gratuit</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('d/m/Y', strtotime($u['created_at'])); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" title="Voir">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" title="Supprimer">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Top Chansons -->
                    <div class="admin-card">
                        <div class="card-header-admin">
                            <h5><i class="fas fa-fire me-2"></i>Top Chansons</h5>
                            <a href="#" class="btn btn-sm btn-outline-primary">Voir tout</a>
                        </div>
                        <div class="card-body-admin">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Titre</th>
                                            <th>Artiste</th>
                                            <th>Écoutes</th>
                                            <th>Type</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($top_songs as $index => $song): ?>
                                        <tr>
                                            <td><strong><?php echo $index + 1; ?></strong></td>
                                            <td><?php echo htmlspecialchars($song['title']); ?></td>
                                            <td><?php echo htmlspecialchars($song['artist_name']); ?></td>
                                            <td>
                                                <span class="badge bg-primary">
                                                    <?php echo number_format($song['play_count']); ?> <i class="fas fa-play ms-1"></i>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($song['is_premium']): ?>
                                                    <span class="badge bg-warning text-dark"><i class="fas fa-star"></i> Premium</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Gratuit</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="col-lg-4">
                    <!-- Top Artistes -->
                    <div class="admin-card mb-4">
                        <div class="card-header-admin">
                            <h5><i class="fas fa-trophy me-2"></i>Top Artistes</h5>
                        </div>
                        <div class="card-body-admin">
                            <div class="top-artists-list">
                                <?php foreach (array_slice($top_artists, 0, 5) as $index => $artist): ?>
                                <div class="artist-item">
                                    <div class="artist-rank"><?php echo $index + 1; ?></div>
                                    <div class="artist-info">
                                        <strong><?php echo htmlspecialchars($artist['stage_name']); ?></strong>
                                        <small class="text-muted d-block"><?php echo number_format($artist['total_plays']); ?> écoutes</small>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Transactions Récentes -->
                    <div class="admin-card">
                        <div class="card-header-admin">
                            <h5><i class="fas fa-receipt me-2"></i>Transactions</h5>
                        </div>
                        <div class="card-body-admin">
                            <div class="transactions-list">
                                <?php foreach (array_slice($recent_transactions, 0, 5) as $trans): ?>
                                <div class="transaction-item">
                                    <div class="transaction-info">
                                        <strong><?php echo htmlspecialchars($trans['first_name'] . ' ' . $trans['last_name']); ?></strong>
                                        <small class="text-muted d-block"><?php echo date('d/m/Y H:i', strtotime($trans['created_at'])); ?></small>
                                    </div>
                                    <div class="transaction-amount">
                                        <strong class="text-success"><?php echo number_format($trans['amount'], 0, ',', ' '); ?> XAF</strong>
                                        <small class="d-block text-muted"><?php echo htmlspecialchars($trans['payment_method']); ?></small>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<style>
:root {
    --bleu-tchadien: #0066CC;
    --jaune-solaire: #FFD700;
    --rouge-terre: #CC3333;
    --vert-savane: #228B22;
    --gris-harmattan: #2C3E50;
}

.admin-dashboard-container {
    background: #f5f7fa;
    min-height: 100vh;
    padding-top: 80px;
}

/* Admin Header */
.admin-header {
    background: linear-gradient(135deg, var(--gris-harmattan), #1a252f);
    padding: 2rem 0;
    color: white;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
}

.admin-icon {
    width: 70px;
    height: 70px;
    background: linear-gradient(135deg, var(--jaune-solaire), #FFC700);
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--gris-harmattan);
    font-size: 2rem;
    margin-right: 1.5rem;
    box-shadow: 0 5px 20px rgba(255, 215, 0, 0.4);
}

.admin-header h1 {
    font-weight: 700;
    font-size: 2rem;
}

.admin-quick-actions {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
    justify-content: flex-end;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
}

.stat-card {
    background: white;
    padding: 1.75rem;
    border-radius: 15px;
    box-shadow: 0 3px 15px rgba(0, 0, 0, 0.08);
    display: flex;
    align-items: center;
    gap: 1.25rem;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 100px;
    height: 100px;
    border-radius: 50%;
    opacity: 0.1;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

.stat-card .stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    color: white;
    flex-shrink: 0;
}

.stat-users .stat-icon {
    background: linear-gradient(135deg, var(--bleu-tchadien), #0052a3);
}

.stat-users::before {
    background: var(--bleu-tchadien);
}

.stat-artists .stat-icon {
    background: linear-gradient(135deg, var(--vert-savane), #1a6b1a);
}

.stat-artists::before {
    background: var(--vert-savane);
}

.stat-songs .stat-icon {
    background: linear-gradient(135deg, #9C27B0, #7B1FA2);
}

.stat-songs::before {
    background: #9C27B0;
}

.stat-plays .stat-icon {
    background: linear-gradient(135deg, #FF9800, #F57C00);
}

.stat-plays::before {
    background: #FF9800;
}

.stat-premium .stat-icon {
    background: linear-gradient(135deg, var(--jaune-solaire), #FFC700);
    color: var(--gris-harmattan);
}

.stat-premium::before {
    background: var(--jaune-solaire);
}

.stat-revenue .stat-icon {
    background: linear-gradient(135deg, var(--rouge-terre), #a32929);
}

.stat-revenue::before {
    background: var(--rouge-terre);
}

.stat-content {
    flex: 1;
}

.stat-label {
    font-size: 0.9rem;
    color: #6c757d;
    font-weight: 500;
    margin-bottom: 0.5rem;
}

.stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: var(--gris-harmattan);
    line-height: 1;
    margin-bottom: 0.5rem;
}

.stat-value small {
    font-size: 1rem;
    font-weight: 600;
}

.stat-change {
    font-size: 0.85rem;
    font-weight: 600;
}

.stat-change.positive {
    color: var(--vert-savane);
}

.stat-info {
    font-size: 0.85rem;
    color: #6c757d;
}

/* Admin Cards */
.admin-card {
    background: white;
    border-radius: 15px;
    box-shadow: 0 3px 15px rgba(0, 0, 0, 0.08);
    overflow: hidden;
}

.card-header-admin {
    padding: 1.5rem;
    border-bottom: 1px solid #f0f0f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-header-admin h5 {
    margin: 0;
    color: var(--gris-harmattan);
    font-weight: 600;
}

.card-body-admin {
    padding: 1.5rem;
}

/* Table Styles */
.table {
    margin: 0;
}

.table thead th {
    border-bottom: 2px solid #e9ecef;
    color: var(--gris-harmattan);
    font-weight: 600;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.user-avatar-small {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, var(--bleu-tchadien), var(--jaune-solaire));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 0.9rem;
}

/* Top Artists List */
.top-artists-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.artist-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.75rem;
    background: #f8f9fa;
    border-radius: 10px;
    transition: all 0.3s ease;
}

.artist-item:hover {
    background: #e9ecef;
    transform: translateX(5px);
}

.artist-rank {
    width: 35px;
    height: 35px;
    background: var(--bleu-tchadien);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1rem;
}

.artist-info {
    flex: 1;
}

/* Transactions List */
.transactions-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.transaction-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 10px;
    border-left: 4px solid var(--vert-savane);
}

.transaction-info strong {
    color: var(--gris-harmattan);
}

.transaction-amount {
    text-align: right;
}

/* Responsive */
@media (max-width: 991px) {
    .admin-dashboard-container {
        padding-top: 70px;
    }

    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .admin-quick-actions {
        justify-content: flex-start;
    }
}

@media (max-width: 576px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }

    .stat-value {
        font-size: 1.5rem;
    }
}
</style>

<?php include 'includes/footer.php'; ?>
