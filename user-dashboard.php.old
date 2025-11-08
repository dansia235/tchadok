<?php
/**
 * Dashboard Utilisateur - Tchadok Platform
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    header('Location: ' . SITE_URL . '/login.php?redirect=user-dashboard');
    exit();
}

$pageTitle = 'Mon Tableau de Bord';
$pageDescription = 'Gérez votre compte et vos préférences musicales';

// Données simulées de l'utilisateur
$userStats = [
    'total_plays' => rand(100, 5000),
    'listening_hours' => rand(10, 500),
    'favorite_tracks' => rand(20, 200),
    'playlists_created' => rand(2, 20),
    'artists_following' => rand(5, 50),
    'subscription_type' => rand(0, 1) ? 'premium' : 'free',
    'member_since' => date('Y-m-d', strtotime('-' . rand(30, 365) . ' days'))
];

include 'includes/header.php';
?>

<div class="user-dashboard">
    <!-- Header Section -->
    <section class="dashboard-header py-4" style="background: linear-gradient(135deg, #0066CC 0%, #FFD700 100%);">
        <div class="container">
            <div class="row align-items-center text-white">
                <div class="col-lg-8">
                    <div class="d-flex align-items-center mb-3">
                        <img src="<?php echo SITE_URL; ?>/assets/images/default-avatar.png" 
                             alt="Avatar" class="rounded-circle me-3" style="width: 80px; height: 80px;">
                        <div>
                            <h1 class="h2 mb-1">
                                Bonjour, <?php echo htmlspecialchars($_SESSION['first_name'] ?? 'Utilisateur'); ?> !
                            </h1>
                            <p class="mb-0 opacity-75">
                                <?php if ($userStats['subscription_type'] === 'premium'): ?>
                                    <i class="fas fa-crown text-warning me-1"></i> Membre Premium
                                <?php else: ?>
                                    <i class="fas fa-user me-1"></i> Membre Gratuit
                                <?php endif; ?>
                                • Membre depuis <?php echo date('F Y', strtotime($userStats['member_since'])); ?>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                    <?php if ($userStats['subscription_type'] !== 'premium'): ?>
                    <a href="<?php echo SITE_URL; ?>/premium.php" class="btn btn-warning btn-lg">
                        <i class="fas fa-crown me-2"></i> Passer à Premium
                    </a>
                    <?php else: ?>
                    <a href="<?php echo SITE_URL; ?>/account-settings.php" class="btn btn-light btn-lg">
                        <i class="fas fa-cog me-2"></i> Paramètres
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Quick Stats -->
    <section class="py-4">
        <div class="container">
            <div class="row g-3">
                <div class="col-6 col-md-3">
                    <div class="stat-card text-center">
                        <div class="stat-value text-primary">
                            <?php echo formatNumber($userStats['total_plays']); ?>
                        </div>
                        <div class="stat-label">Écoutes</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card text-center">
                        <div class="stat-value text-success">
                            <?php echo $userStats['listening_hours']; ?>h
                        </div>
                        <div class="stat-label">Temps d'écoute</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card text-center">
                        <div class="stat-value text-warning">
                            <?php echo $userStats['favorite_tracks']; ?>
                        </div>
                        <div class="stat-label">Favoris</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card text-center">
                        <div class="stat-value text-info">
                            <?php echo $userStats['playlists_created']; ?>
                        </div>
                        <div class="stat-label">Playlists</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <section class="py-4">
        <div class="container">
            <div class="row">
                <!-- Left Column -->
                <div class="col-lg-8">
                    <!-- Recently Played -->
                    <div class="content-card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-history me-2"></i>
                                Écoutés récemment
                            </h5>
                            <a href="<?php echo SITE_URL; ?>/history.php" class="btn btn-sm btn-link">
                                Voir tout <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                        <div class="card-body">
                            <div class="recent-tracks">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                <div class="track-item">
                                    <img src="<?php echo SITE_URL; ?>/assets/images/default-cover.jpg" 
                                         alt="Cover" class="track-cover">
                                    <div class="track-info">
                                        <div class="track-title">Titre Récent <?php echo $i; ?></div>
                                        <div class="track-artist">Artiste <?php echo rand(1, 20); ?></div>
                                    </div>
                                    <div class="track-actions">
                                        <button class="btn btn-sm btn-link" onclick="playTrack(<?php echo $i; ?>)">
                                            <i class="fas fa-play"></i>
                                        </button>
                                        <button class="btn btn-sm btn-link">
                                            <i class="fas fa-heart"></i>
                                        </button>
                                        <button class="btn btn-sm btn-link">
                                            <i class="fas fa-ellipsis-h"></i>
                                        </button>
                                    </div>
                                </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Your Playlists -->
                    <div class="content-card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-list me-2"></i>
                                Vos playlists
                            </h5>
                            <a href="<?php echo SITE_URL; ?>/playlists.php" class="btn btn-sm btn-primary">
                                <i class="fas fa-plus me-1"></i> Créer
                            </a>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <?php for ($i = 1; $i <= 4; $i++): ?>
                                <div class="col-6 col-md-3">
                                    <div class="playlist-card">
                                        <div class="playlist-cover">
                                            <img src="<?php echo SITE_URL; ?>/assets/images/default-playlist.jpg" 
                                                 alt="Playlist">
                                            <div class="playlist-overlay">
                                                <button class="btn btn-primary btn-sm rounded-circle">
                                                    <i class="fas fa-play"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="playlist-name">Ma Playlist <?php echo $i; ?></div>
                                        <div class="playlist-count"><?php echo rand(10, 50); ?> titres</div>
                                    </div>
                                </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Recommendations -->
                    <div class="content-card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-magic me-2"></i>
                                Recommandé pour vous
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="recommendation-tabs">
                                <ul class="nav nav-pills mb-3" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tracks-tab">
                                            Titres
                                        </button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" data-bs-toggle="pill" data-bs-target="#artists-tab">
                                            Artistes
                                        </button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" data-bs-toggle="pill" data-bs-target="#albums-tab">
                                            Albums
                                        </button>
                                    </li>
                                </ul>
                                <div class="tab-content">
                                    <div class="tab-pane fade show active" id="tracks-tab">
                                        <div class="row g-3">
                                            <?php for ($i = 1; $i <= 6; $i++): ?>
                                            <div class="col-md-6">
                                                <div class="recommendation-item">
                                                    <img src="<?php echo SITE_URL; ?>/assets/images/default-cover.jpg" 
                                                         alt="Cover">
                                                    <div class="recommendation-info">
                                                        <div class="title">Découverte <?php echo $i; ?></div>
                                                        <div class="artist">Artiste Reco <?php echo $i; ?></div>
                                                        <div class="match">
                                                            <i class="fas fa-star text-warning"></i>
                                                            <?php echo rand(80, 98); ?>% match
                                                        </div>
                                                    </div>
                                                    <button class="btn btn-sm btn-primary">
                                                        <i class="fas fa-play"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <div class="tab-pane fade" id="artists-tab">
                                        <div class="row g-3">
                                            <?php for ($i = 1; $i <= 4; $i++): ?>
                                            <div class="col-6 col-md-3 text-center">
                                                <img src="<?php echo SITE_URL; ?>/assets/images/default-avatar.png" 
                                                     alt="Artist" class="rounded-circle mb-2" style="width: 100px;">
                                                <h6>Artiste <?php echo $i; ?></h6>
                                                <button class="btn btn-sm btn-outline-primary">Suivre</button>
                                            </div>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <div class="tab-pane fade" id="albums-tab">
                                        <div class="row g-3">
                                            <?php for ($i = 1; $i <= 4; $i++): ?>
                                            <div class="col-6 col-md-3">
                                                <div class="album-card">
                                                    <img src="<?php echo SITE_URL; ?>/assets/images/default-cover.jpg" 
                                                         alt="Album" class="w-100 rounded mb-2">
                                                    <h6 class="mb-1">Album <?php echo $i; ?></h6>
                                                    <small class="text-muted">Artiste <?php echo $i; ?></small>
                                                </div>
                                            </div>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="col-lg-4">
                    <!-- Profile Completion -->
                    <?php 
                    $profileCompletion = rand(60, 90);
                    ?>
                    <div class="content-card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-user-circle me-2"></i>
                                Profil
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="profile-completion mb-3">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Complété à</span>
                                    <span class="fw-bold"><?php echo $profileCompletion; ?>%</span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar" role="progressbar" 
                                         style="width: <?php echo $profileCompletion; ?>%"></div>
                                </div>
                            </div>
                            <div class="profile-actions">
                                <a href="<?php echo SITE_URL; ?>/profile-edit.php" class="btn btn-sm btn-outline-primary d-block mb-2">
                                    <i class="fas fa-edit me-2"></i> Modifier le profil
                                </a>
                                <a href="<?php echo SITE_URL; ?>/preferences.php" class="btn btn-sm btn-outline-secondary d-block">
                                    <i class="fas fa-sliders-h me-2"></i> Préférences
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Activity Feed -->
                    <div class="content-card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-stream me-2"></i>
                                Activité récente
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="activity-feed">
                                <?php 
                                $activities = [
                                    ['icon' => 'fa-heart', 'text' => 'Vous avez aimé "Sahara Beat"', 'time' => 'Il y a 2h'],
                                    ['icon' => 'fa-list', 'text' => 'Nouvelle playlist créée', 'time' => 'Il y a 5h'],
                                    ['icon' => 'fa-user-plus', 'text' => 'Vous suivez Khalil MC', 'time' => 'Hier'],
                                    ['icon' => 'fa-music', 'text' => '50 titres écoutés cette semaine', 'time' => 'Il y a 2 jours'],
                                    ['icon' => 'fa-trophy', 'text' => 'Badge "Mélomane" débloqué', 'time' => 'Il y a 3 jours']
                                ];
                                foreach ($activities as $activity):
                                ?>
                                <div class="activity-item">
                                    <i class="fas <?php echo $activity['icon']; ?> activity-icon"></i>
                                    <div class="activity-content">
                                        <p><?php echo $activity['text']; ?></p>
                                        <small class="text-muted"><?php echo $activity['time']; ?></small>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Listening Stats -->
                    <div class="content-card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-chart-pie me-2"></i>
                                Vos genres préférés
                            </h5>
                        </div>
                        <div class="card-body">
                            <canvas id="genreChart" height="200"></canvas>
                            <div class="genre-legend mt-3">
                                <?php 
                                $genres = [
                                    ['name' => 'Afrobeat', 'percent' => 35, 'color' => '#FF6B6B'],
                                    ['name' => 'Hip-Hop', 'percent' => 25, 'color' => '#4ECDC4'],
                                    ['name' => 'R&B', 'percent' => 20, 'color' => '#45B7D1'],
                                    ['name' => 'Gospel', 'percent' => 12, 'color' => '#F7DC6F'],
                                    ['name' => 'Autres', 'percent' => 8, 'color' => '#95A5A6']
                                ];
                                foreach ($genres as $genre):
                                ?>
                                <div class="genre-item d-flex align-items-center mb-2">
                                    <div class="genre-color" style="background: <?php echo $genre['color']; ?>"></div>
                                    <span class="flex-grow-1"><?php echo $genre['name']; ?></span>
                                    <span class="fw-bold"><?php echo $genre['percent']; ?>%</span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Links -->
                    <div class="content-card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-link me-2"></i>
                                Liens rapides
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="quick-links">
                                <a href="<?php echo SITE_URL; ?>/discover.php" class="quick-link">
                                    <i class="fas fa-compass"></i>
                                    <span>Découvrir</span>
                                </a>
                                <a href="<?php echo SITE_URL; ?>/trending.php" class="quick-link">
                                    <i class="fas fa-fire"></i>
                                    <span>Tendances</span>
                                </a>
                                <a href="<?php echo SITE_URL; ?>/new-releases.php" class="quick-link">
                                    <i class="fas fa-sparkles"></i>
                                    <span>Nouveautés</span>
                                </a>
                                <a href="<?php echo SITE_URL; ?>/radio.php" class="quick-link">
                                    <i class="fas fa-broadcast-tower"></i>
                                    <span>Radio</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Subscription Banner (for free users) -->
    <?php if ($userStats['subscription_type'] !== 'premium'): ?>
    <section class="py-5">
        <div class="container">
            <div class="premium-banner text-center text-white p-5 rounded">
                <h2 class="mb-3">
                    <i class="fas fa-crown text-warning me-2"></i>
                    Passez à Tchadok Premium
                </h2>
                <p class="lead mb-4">
                    Profitez d'une expérience sans publicité, téléchargements illimités, 
                    et qualité audio supérieure.
                </p>
                <div class="d-flex gap-3 justify-content-center">
                    <a href="<?php echo SITE_URL; ?>/premium.php" class="btn btn-warning btn-lg">
                        Découvrir Premium
                    </a>
                    <button class="btn btn-outline-light btn-lg">
                        Essai gratuit 30 jours
                    </button>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>
</div>

<style>
.user-dashboard {
    background: #f8f9fa;
    min-height: calc(100vh - 200px);
}

.stat-card {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    transition: transform 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.stat-value {
    font-size: 2rem;
    font-weight: bold;
    margin-bottom: 0.5rem;
}

.stat-label {
    color: #6c757d;
    font-size: 0.9rem;
}

.content-card {
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    overflow: hidden;
}

.content-card .card-header {
    background: white;
    border-bottom: 1px solid #f0f0f0;
    padding: 1.25rem;
}

.content-card .card-body {
    padding: 1.5rem;
}

.track-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.75rem 0;
    border-bottom: 1px solid #f0f0f0;
}

.track-item:last-child {
    border-bottom: none;
}

.track-cover {
    width: 50px;
    height: 50px;
    border-radius: 8px;
    object-fit: cover;
}

.track-info {
    flex: 1;
}

.track-title {
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.track-artist {
    color: #6c757d;
    font-size: 0.9rem;
}

.track-actions {
    display: flex;
    gap: 0.5rem;
}

.playlist-card {
    cursor: pointer;
    transition: transform 0.3s ease;
}

.playlist-card:hover {
    transform: scale(1.05);
}

.playlist-cover {
    position: relative;
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 0.5rem;
}

.playlist-cover img {
    width: 100%;
    aspect-ratio: 1;
    object-fit: cover;
}

.playlist-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.playlist-card:hover .playlist-overlay {
    opacity: 1;
}

.playlist-name {
    font-weight: 600;
    font-size: 0.9rem;
    margin-bottom: 0.25rem;
}

.playlist-count {
    color: #6c757d;
    font-size: 0.8rem;
}

.recommendation-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.75rem;
    background: #f8f9fa;
    border-radius: 10px;
    transition: background 0.3s ease;
}

.recommendation-item:hover {
    background: #e9ecef;
}

.recommendation-item img {
    width: 60px;
    height: 60px;
    border-radius: 8px;
    object-fit: cover;
}

.recommendation-info {
    flex: 1;
}

.recommendation-info .title {
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.recommendation-info .artist {
    color: #6c757d;
    font-size: 0.9rem;
    margin-bottom: 0.25rem;
}

.recommendation-info .match {
    font-size: 0.8rem;
    color: #28a745;
}

.activity-feed {
    max-height: 400px;
    overflow-y: auto;
}

.activity-item {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.activity-icon {
    width: 35px;
    height: 35px;
    background: #f8f9fa;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6c757d;
    flex-shrink: 0;
}

.activity-content p {
    margin-bottom: 0.25rem;
    font-size: 0.9rem;
}

.genre-color {
    width: 20px;
    height: 20px;
    border-radius: 4px;
    margin-right: 0.75rem;
}

.quick-links {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}

.quick-link {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 10px;
    text-decoration: none;
    color: #333;
    transition: all 0.3s ease;
}

.quick-link:hover {
    background: #e9ecef;
    transform: translateY(-2px);
}

.quick-link i {
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
    color: #007bff;
}

.premium-banner {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    position: relative;
    overflow: hidden;
}

.premium-banner::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    animation: pulse 3s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(0.8); opacity: 0.5; }
    50% { transform: scale(1.2); opacity: 0.8; }
}

.nav-pills .nav-link {
    color: #666;
    border-radius: 20px;
    padding: 0.5rem 1rem;
}

.nav-pills .nav-link.active {
    background: #007bff;
}

@media (max-width: 768px) {
    .stat-value {
        font-size: 1.5rem;
    }
    
    .quick-links {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .recommendation-item {
        flex-direction: column;
        text-align: center;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
// Genre Chart
const genreCtx = document.getElementById('genreChart').getContext('2d');
const genreChart = new Chart(genreCtx, {
    type: 'doughnut',
    data: {
        labels: ['Afrobeat', 'Hip-Hop', 'R&B', 'Gospel', 'Autres'],
        datasets: [{
            data: [35, 25, 20, 12, 8],
            backgroundColor: [
                '#FF6B6B',
                '#4ECDC4',
                '#45B7D1',
                '#F7DC6F',
                '#95A5A6'
            ],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        }
    }
});

// Play track function
function playTrack(trackId) {
    console.log('Playing track:', trackId);
    
    // Notification
    const notification = document.createElement('div');
    notification.innerHTML = `
        <div style="display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-play-circle" style="font-size: 20px;"></i>
            <div>
                <div><strong>Lecture en cours</strong></div>
                <small>Titre Récent ${trackId}</small>
            </div>
        </div>
    `;
    notification.style.cssText = `
        position: fixed; 
        top: 20px; 
        right: 20px; 
        background: #28a745; 
        color: white; 
        padding: 15px 20px; 
        border-radius: 8px; 
        z-index: 10000;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    `;
    document.body.appendChild(notification);
    setTimeout(() => notification.remove(), 3000);
}

// Smooth scroll for recommendations
document.querySelectorAll('.nav-link[data-bs-toggle="pill"]').forEach(link => {
    link.addEventListener('shown.bs.tab', function() {
        const target = document.querySelector(this.getAttribute('data-bs-target'));
        if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    });
});

// Activity feed auto-refresh simulation
setInterval(() => {
    const feed = document.querySelector('.activity-feed');
    if (feed && Math.random() > 0.8) {
        const newActivity = document.createElement('div');
        newActivity.className = 'activity-item';
        newActivity.innerHTML = `
            <i class="fas fa-music activity-icon"></i>
            <div class="activity-content">
                <p>Nouvelle activité</p>
                <small class="text-muted">À l'instant</small>
            </div>
        `;
        newActivity.style.opacity = '0';
        feed.insertBefore(newActivity, feed.firstChild);
        
        // Animate entrance
        setTimeout(() => {
            newActivity.style.transition = 'opacity 0.5s ease';
            newActivity.style.opacity = '1';
        }, 100);
        
        // Remove old activities if too many
        const activities = feed.querySelectorAll('.activity-item');
        if (activities.length > 10) {
            activities[activities.length - 1].remove();
        }
    }
}, 30000); // Check every 30 seconds
</script>

<?php include 'includes/footer.php'; ?>