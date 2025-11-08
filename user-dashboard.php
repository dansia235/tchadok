<?php
/**
 * Dashboard Utilisateur - Tchadok Platform v2.0
 * Design moderne et innovant
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    header('Location: ' . SITE_URL . '/login.php?redirect=user-dashboard');
    exit();
}

$pageTitle = 'Tableau de Bord';
$pageDescription = 'Gérez votre compte et vos préférences musicales';

// Récupérer les données utilisateur
$user = getCurrentUser();

// Récupérer les statistiques réelles depuis la BD
try {
    $dbInstance = TchadokDatabase::getInstance();
    $db = $dbInstance->getConnection();

    $userId = $_SESSION['user_id'];

    // Stats de base (simulées pour le moment)
    $userStats = [
        'total_plays' => 0,
        'listening_hours' => 0,
        'favorite_tracks' => 0,
        'playlists_created' => 0,
        'subscription_type' => $user['premium_status'] ? 'premium' : 'free',
        'member_since' => $user['created_at'] ?? date('Y-m-d')
    ];

} catch (Exception $e) {
    $userStats = [
        'total_plays' => 0,
        'listening_hours' => 0,
        'favorite_tracks' => 0,
        'playlists_created' => 0,
        'subscription_type' => 'free',
        'member_since' => date('Y-m-d')
    ];
}

include 'includes/header.php';
?>

<div class="dashboard-container">
    <!-- Hero Header avec Gradient Tchadok -->
    <section class="dashboard-hero">
        <div class="hero-gradient"></div>
        <div class="container position-relative">
            <div class="row align-items-center py-5">
                <div class="col-lg-8">
                    <div class="welcome-section">
                        <div class="user-avatar-wrapper">
                            <div class="avatar-circle">
                                <span class="avatar-initial">
                                    <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                </span>
                            </div>
                            <?php if ($userStats['subscription_type'] === 'premium'): ?>
                            <div class="premium-badge">
                                <i class="fas fa-crown"></i>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="welcome-text">
                            <h1 class="display-5 text-white mb-2 animate-fade-in">
                                Bienvenue, <?php echo htmlspecialchars($user['first_name']); ?> !
                            </h1>
                            <p class="text-white-50 mb-0">
                                <i class="fas fa-calendar me-2"></i>
                                Membre depuis <?php echo date('F Y', strtotime($userStats['member_since'])); ?>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
                    <?php if ($userStats['subscription_type'] !== 'premium'): ?>
                    <a href="<?php echo SITE_URL; ?>/premium.php" class="btn btn-premium btn-lg">
                        <i class="fas fa-crown me-2"></i>
                        Passer à Premium
                    </a>
                    <?php else: ?>
                    <div class="premium-status-card">
                        <i class="fas fa-crown text-warning fs-3 mb-2"></i>
                        <div class="text-white">Membre Premium</div>
                        <small class="text-white-50">Profitez de tous les avantages</small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Statistiques Rapides -->
    <section class="stats-section">
        <div class="container">
            <div class="stats-grid">
                <div class="stat-card stat-primary">
                    <div class="stat-icon">
                        <i class="fas fa-play-circle"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value" data-count="<?php echo $userStats['total_plays']; ?>">0</div>
                        <div class="stat-label">Écoutes</div>
                    </div>
                    <div class="stat-trend">
                        <i class="fas fa-arrow-up"></i> +12%
                    </div>
                </div>

                <div class="stat-card stat-success">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value" data-count="<?php echo $userStats['listening_hours']; ?>">0</div>
                        <div class="stat-label">Heures d'écoute</div>
                    </div>
                    <div class="stat-trend">
                        <i class="fas fa-arrow-up"></i> +8%
                    </div>
                </div>

                <div class="stat-card stat-warning">
                    <div class="stat-icon">
                        <i class="fas fa-heart"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value" data-count="<?php echo $userStats['favorite_tracks']; ?>">0</div>
                        <div class="stat-label">Favoris</div>
                    </div>
                    <div class="stat-trend">
                        <i class="fas fa-arrow-up"></i> +24%
                    </div>
                </div>

                <div class="stat-card stat-info">
                    <div class="stat-icon">
                        <i class="fas fa-stream"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value" data-count="<?php echo $userStats['playlists_created']; ?>">0</div>
                        <div class="stat-label">Playlists</div>
                    </div>
                    <div class="stat-trend">
                        <i class="fas fa-minus"></i> 0%
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contenu Principal -->
    <section class="dashboard-content py-5">
        <div class="container">
            <div class="row g-4">
                <!-- Colonne Principale -->
                <div class="col-lg-8">
                    <!-- Activité Récente -->
                    <div class="content-card mb-4">
                        <div class="card-header-custom">
                            <div class="d-flex align-items-center">
                                <div class="header-icon bg-primary">
                                    <i class="fas fa-history"></i>
                                </div>
                                <div>
                                    <h5 class="mb-0">Écoutés récemment</h5>
                                    <small class="text-muted">Vos dernières découvertes</small>
                                </div>
                            </div>
                            <a href="<?php echo SITE_URL; ?>/history.php" class="btn btn-link">Voir tout <i class="fas fa-arrow-right ms-1"></i></a>
                        </div>
                        <div class="card-body-custom">
                            <div class="empty-state">
                                <i class="fas fa-music fa-3x text-muted mb-3"></i>
                                <h6 class="text-muted">Aucune écoute récente</h6>
                                <p class="text-muted">Commencez à découvrir la musique tchadienne !</p>
                                <a href="<?php echo SITE_URL; ?>/decouvrir.php" class="btn btn-primary">
                                    <i class="fas fa-compass me-2"></i>Découvrir
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Vos Playlists -->
                    <div class="content-card">
                        <div class="card-header-custom">
                            <div class="d-flex align-items-center">
                                <div class="header-icon bg-success">
                                    <i class="fas fa-list-music"></i>
                                </div>
                                <div>
                                    <h5 class="mb-0">Vos playlists</h5>
                                    <small class="text-muted">Organisez votre musique</small>
                                </div>
                            </div>
                            <a href="<?php echo SITE_URL; ?>/create-playlist.php" class="btn btn-sm btn-success">
                                <i class="fas fa-plus me-2"></i>Créer
                            </a>
                        </div>
                        <div class="card-body-custom">
                            <div class="empty-state">
                                <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                                <h6 class="text-muted">Aucune playlist</h6>
                                <p class="text-muted">Créez votre première playlist personnalisée</p>
                                <a href="<?php echo SITE_URL; ?>/create-playlist.php" class="btn btn-success">
                                    <i class="fas fa-plus me-2"></i>Créer ma première playlist
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Colonne Latérale -->
                <div class="col-lg-4">
                    <!-- Profil -->
                    <div class="content-card mb-4">
                        <div class="card-header-custom">
                            <div class="d-flex align-items-center">
                                <div class="header-icon bg-warning">
                                    <i class="fas fa-user-circle"></i>
                                </div>
                                <div>
                                    <h5 class="mb-0">Profil</h5>
                                </div>
                            </div>
                        </div>
                        <div class="card-body-custom">
                            <div class="profile-completion">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Profil complété</span>
                                    <span class="fw-bold text-primary">85%</span>
                                </div>
                                <div class="progress-custom">
                                    <div class="progress-fill" style="width: 85%"></div>
                                </div>
                            </div>
                            <div class="profile-details mt-4">
                                <div class="detail-item">
                                    <i class="fas fa-envelope text-primary"></i>
                                    <span><?php echo htmlspecialchars($user['email']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-phone text-success"></i>
                                    <span><?php echo htmlspecialchars($user['phone'] ?? 'Non renseigné'); ?></span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-map-marker-alt text-danger"></i>
                                    <span><?php echo htmlspecialchars($user['city'] ?? 'Tchad'); ?></span>
                                </div>
                            </div>
                            <div class="mt-4">
                                <a href="<?php echo SITE_URL; ?>/edit-profile.php" class="btn btn-outline-primary btn-sm w-100 mb-2">
                                    <i class="fas fa-edit me-2"></i>Modifier le profil
                                </a>
                                <a href="<?php echo SITE_URL; ?>/settings.php" class="btn btn-outline-secondary btn-sm w-100">
                                    <i class="fas fa-cog me-2"></i>Paramètres
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Liens Rapides -->
                    <div class="content-card">
                        <div class="card-header-custom">
                            <div class="d-flex align-items-center">
                                <div class="header-icon bg-info">
                                    <i class="fas fa-bolt"></i>
                                </div>
                                <div>
                                    <h5 class="mb-0">Accès rapide</h5>
                                </div>
                            </div>
                        </div>
                        <div class="card-body-custom">
                            <div class="quick-links-grid">
                                <a href="<?php echo SITE_URL; ?>/decouvrir.php" class="quick-link-item">
                                    <div class="quick-link-icon bg-primary">
                                        <i class="fas fa-compass"></i>
                                    </div>
                                    <span>Découvrir</span>
                                </a>
                                <a href="<?php echo SITE_URL; ?>/artists.php" class="quick-link-item">
                                    <div class="quick-link-icon bg-success">
                                        <i class="fas fa-microphone-alt"></i>
                                    </div>
                                    <span>Artistes</span>
                                </a>
                                <a href="<?php echo SITE_URL; ?>/radio-live.php" class="quick-link-item">
                                    <div class="quick-link-icon bg-danger">
                                        <i class="fas fa-broadcast-tower"></i>
                                    </div>
                                    <span>Radio</span>
                                </a>
                                <a href="<?php echo SITE_URL; ?>/premium.php" class="quick-link-item">
                                    <div class="quick-link-icon bg-warning">
                                        <i class="fas fa-crown"></i>
                                    </div>
                                    <span>Premium</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<style>
/* Couleurs principales Tchadok */
:root {
    --bleu-tchadien: #0066CC;
    --jaune-solaire: #FFD700;
    --rouge-terre: #CC3333;
    --vert-savane: #228B22;
    --gris-harmattan: #2C3E50;
    --blanc-coton: #FFFFFF;
}

.dashboard-container {
    background: #f5f7fa;
    min-height: 100vh;
    padding-bottom: 3rem;
}

/* Hero Section */
.dashboard-hero {
    position: relative;
    overflow: hidden;
    margin-bottom: -60px;
    padding-top: 100px; /* Espace pour le header fixe */
    padding-bottom: 80px; /* Espace pour les cartes de stats */
}

.hero-gradient {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, var(--bleu-tchadien) 0%, #1a75d2 50%, var(--jaune-solaire) 100%);
    opacity: 1;
}

.hero-gradient::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="rgba(255,255,255,0.05)" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,122.7C672,117,768,139,864,133.3C960,128,1056,96,1152,90.7C1248,85,1344,107,1392,117.3L1440,128L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>');
    background-size: cover;
    animation: waveMove 15s ease-in-out infinite;
}

@keyframes waveMove {
    0%, 100% { transform: translateX(0); }
    50% { transform: translateX(-50px); }
}

.welcome-section {
    display: flex;
    align-items: center;
    gap: 2rem;
}

.user-avatar-wrapper {
    position: relative;
}

.avatar-circle {
    width: 90px;
    height: 90px;
    background: linear-gradient(135deg, var(--jaune-solaire), var(--rouge-terre));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 4px solid rgba(255, 255, 255, 0.3);
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
}

.avatar-initial {
    font-size: 2rem;
    font-weight: 700;
    color: white;
}

.premium-badge {
    position: absolute;
    bottom: -5px;
    right: -5px;
    width: 35px;
    height: 35px;
    background: var(--jaune-solaire);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 3px solid white;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    animation: pulse 2s ease-in-out infinite;
}

.premium-badge i {
    color: var(--bleu-tchadien);
    font-size: 1.1rem;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

.btn-premium {
    background: linear-gradient(135deg, var(--jaune-solaire), #FFC700);
    color: var(--bleu-tchadien);
    border: none;
    font-weight: 700;
    padding: 1rem 2rem;
    border-radius: 50px;
    box-shadow: 0 8px 25px rgba(255, 215, 0, 0.4);
    transition: all 0.3s ease;
}

.btn-premium:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 35px rgba(255, 215, 0, 0.6);
    color: var(--bleu-tchadien);
}

.premium-status-card {
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    padding: 1.5rem;
    border-radius: 20px;
    text-align: center;
    border: 2px solid rgba(255, 255, 255, 0.3);
}

/* Stats Grid */
.stats-section {
    margin-top: -30px;
    margin-bottom: 2rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}

.stat-card {
    background: white;
    padding: 2rem;
    border-radius: 20px;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
    display: flex;
    align-items: center;
    gap: 1.5rem;
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
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
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
}

.stat-card.stat-primary::before {
    background: var(--bleu-tchadien);
}

.stat-card.stat-success::before {
    background: var(--vert-savane);
}

.stat-card.stat-warning::before {
    background: var(--jaune-solaire);
}

.stat-card.stat-info::before {
    background: var(--rouge-terre);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    flex-shrink: 0;
}

.stat-card.stat-primary .stat-icon {
    background: linear-gradient(135deg, var(--bleu-tchadien), #0052a3);
    color: white;
}

.stat-card.stat-success .stat-icon {
    background: linear-gradient(135deg, var(--vert-savane), #1a6b1a);
    color: white;
}

.stat-card.stat-warning .stat-icon {
    background: linear-gradient(135deg, var(--jaune-solaire), #ddb800);
    color: var(--bleu-tchadien);
}

.stat-card.stat-info .stat-icon {
    background: linear-gradient(135deg, var(--rouge-terre), #a32929);
    color: white;
}

.stat-content {
    flex: 1;
}

.stat-value {
    font-size: 2.5rem;
    font-weight: 700;
    line-height: 1;
    margin-bottom: 0.25rem;
}

.stat-card.stat-primary .stat-value {
    color: var(--bleu-tchadien);
}

.stat-card.stat-success .stat-value {
    color: var(--vert-savane);
}

.stat-card.stat-warning .stat-value {
    color: #d4a500;
}

.stat-card.stat-info .stat-value {
    color: var(--rouge-terre);
}

.stat-label {
    color: #6c757d;
    font-size: 0.95rem;
    font-weight: 500;
}

.stat-trend {
    position: absolute;
    top: 1rem;
    right: 1rem;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--vert-savane);
}

/* Content Cards */
.content-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
    overflow: hidden;
    transition: all 0.3s ease;
}

.content-card:hover {
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
}

.card-header-custom {
    padding: 1.5rem;
    border-bottom: 1px solid #f0f0f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.header-icon {
    width: 45px;
    height: 45px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 1rem;
    color: white;
    font-size: 1.2rem;
}

.card-body-custom {
    padding: 1.5rem;
}

.empty-state {
    text-align: center;
    padding: 3rem 1rem;
}

/* Profile Section */
.progress-custom {
    height: 8px;
    background: #f0f0f0;
    border-radius: 50px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--bleu-tchadien), var(--jaune-solaire));
    border-radius: 50px;
    transition: width 1s ease;
}

.profile-details {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    background: #f8f9fa;
    border-radius: 10px;
}

.detail-item i {
    font-size: 1.1rem;
}

.detail-item span {
    font-size: 0.9rem;
    color: #495057;
}

/* Quick Links */
.quick-links-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}

.quick-link-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 1.25rem;
    background: #f8f9fa;
    border-radius: 15px;
    text-decoration: none;
    color: #333;
    transition: all 0.3s ease;
}

.quick-link-item:hover {
    background: #e9ecef;
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

.quick-link-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 0.75rem;
    color: white;
    font-size: 1.3rem;
}

.quick-link-item span {
    font-weight: 600;
    font-size: 0.9rem;
}

/* Animations */
.animate-fade-in {
    animation: fadeIn 0.8s ease-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Responsive */
@media (max-width: 991px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .stat-value {
        font-size: 2rem;
    }

    .welcome-section {
        flex-direction: column;
        text-align: center;
    }
}

@media (max-width: 576px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }

    .quick-links-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<script>
// Animation des compteurs
document.addEventListener('DOMContentLoaded', function() {
    const counters = document.querySelectorAll('.stat-value[data-count]');

    counters.forEach(counter => {
        const target = parseInt(counter.getAttribute('data-count'));
        const duration = 2000;
        const step = target / (duration / 16);
        let current = 0;

        const updateCounter = () => {
            current += step;
            if (current < target) {
                counter.textContent = Math.floor(current).toLocaleString();
                requestAnimationFrame(updateCounter);
            } else {
                counter.textContent = target.toLocaleString();
            }
        };

        // Observer pour démarrer l'animation quand visible
        const observer = new IntersectionObserver((entries) => {
            if (entries[0].isIntersecting) {
                updateCounter();
                observer.disconnect();
            }
        });

        observer.observe(counter);
    });
});
</script>

<?php include 'includes/footer.php'; ?>
