<?php
/**
 * Dashboard Artiste - Tchadok Platform
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Vérifier si l'utilisateur est connecté et est un artiste
if (!isLoggedIn()) {
    header('Location: ' . SITE_URL . '/login.php?redirect=artist-dashboard');
    exit();
}

// Vérifier le statut artiste
$isArtist = $_SESSION['user_type'] ?? 'listener' === 'artist';
if (!$isArtist) {
    $_SESSION['error'] = 'Accès réservé aux artistes.';
    header('Location: ' . SITE_URL . '/');
    exit();
}

$pageTitle = 'Dashboard Artiste';
$pageDescription = 'Gérez votre musique et suivez vos performances';

// Données simulées de l'artiste
$artistStats = [
    'total_tracks' => rand(10, 50),
    'total_plays' => rand(10000, 500000),
    'monthly_listeners' => rand(1000, 50000),
    'total_revenue' => rand(50000, 1000000),
    'followers' => rand(500, 25000),
    'playlists_inclusion' => rand(5, 50)
];

include 'includes/header.php';
?>

<div class="artist-dashboard">
    <!-- Header Section -->
    <section class="dashboard-header py-4" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
        <div class="container">
            <div class="row align-items-center text-white">
                <div class="col-lg-8">
                    <div class="d-flex align-items-center mb-3">
                        <img src="<?php echo SITE_URL; ?>/assets/images/default-avatar.png" 
                             alt="Artist" class="rounded-circle me-3" style="width: 80px; height: 80px;">
                        <div>
                            <h1 class="h2 mb-1">
                                <?php echo htmlspecialchars($_SESSION['artist_name'] ?? $_SESSION['first_name'] ?? 'Artiste'); ?>
                                <?php if (rand(0, 1)): ?>
                                <i class="fas fa-check-circle text-warning ms-2" title="Artiste vérifié"></i>
                                <?php endif; ?>
                            </h1>
                            <p class="mb-0 opacity-75">Artiste <?php echo ['Afrobeat', 'Hip-Hop', 'Gospel'][rand(0, 2)]; ?></p>
                        </div>
                    </div>
                    <div class="artist-badges">
                        <span class="badge bg-warning text-dark me-2">
                            <i class="fas fa-star me-1"></i> Top Artist
                        </span>
                        <span class="badge bg-light text-dark me-2">
                            <i class="fas fa-music me-1"></i> <?php echo $artistStats['total_tracks']; ?> Titres
                        </span>
                        <span class="badge bg-light text-dark">
                            <i class="fas fa-users me-1"></i> <?php echo formatNumber($artistStats['followers']); ?> Fans
                        </span>
                    </div>
                </div>
                <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                    <a href="<?php echo SITE_URL; ?>/upload.php" class="btn btn-warning btn-lg">
                        <i class="fas fa-upload me-2"></i> Nouveau Upload
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Statistics Overview -->
    <section class="py-4">
        <div class="container">
            <div class="row g-3">
                <div class="col-md-4 col-lg-2">
                    <div class="stat-card">
                        <div class="stat-icon bg-primary">
                            <i class="fas fa-play"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo formatNumber($artistStats['total_plays']); ?></h3>
                            <p>Écoutes totales</p>
                            <small class="text-success">
                                <i class="fas fa-arrow-up"></i> +12.5%
                            </small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-lg-2">
                    <div class="stat-card">
                        <div class="stat-icon bg-success">
                            <i class="fas fa-headphones"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo formatNumber($artistStats['monthly_listeners']); ?></h3>
                            <p>Auditeurs/mois</p>
                            <small class="text-success">
                                <i class="fas fa-arrow-up"></i> +8.2%
                            </small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-lg-2">
                    <div class="stat-card">
                        <div class="stat-icon bg-warning">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo formatNumber($artistStats['total_revenue']); ?></h3>
                            <p>Revenus (FCFA)</p>
                            <small class="text-success">
                                <i class="fas fa-arrow-up"></i> +15.7%
                            </small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-lg-2">
                    <div class="stat-card">
                        <div class="stat-icon bg-info">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo formatNumber($artistStats['followers']); ?></h3>
                            <p>Abonnés</p>
                            <small class="text-success">
                                <i class="fas fa-arrow-up"></i> +5.3%
                            </small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-lg-2">
                    <div class="stat-card">
                        <div class="stat-icon bg-danger">
                            <i class="fas fa-heart"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo formatNumber(rand(1000, 20000)); ?></h3>
                            <p>J'aime</p>
                            <small class="text-success">
                                <i class="fas fa-arrow-up"></i> +18.9%
                            </small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-lg-2">
                    <div class="stat-card">
                        <div class="stat-icon bg-secondary">
                            <i class="fas fa-list"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $artistStats['playlists_inclusion']; ?></h3>
                            <p>Playlists</p>
                            <small class="text-success">
                                <i class="fas fa-arrow-up"></i> +3.1%
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Dashboard Content -->
    <section class="py-4">
        <div class="container">
            <div class="row">
                <!-- Left Column -->
                <div class="col-lg-8">
                    <!-- Performance Chart -->
                    <div class="dashboard-card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Performance des 30 derniers jours</h5>
                            <div class="btn-group btn-group-sm" role="group">
                                <button type="button" class="btn btn-outline-primary active">Écoutes</button>
                                <button type="button" class="btn btn-outline-primary">Revenus</button>
                                <button type="button" class="btn btn-outline-primary">Abonnés</button>
                            </div>
                        </div>
                        <div class="card-body">
                            <canvas id="performanceChart" height="100"></canvas>
                        </div>
                    </div>

                    <!-- Recent Tracks -->
                    <div class="dashboard-card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Vos derniers titres</h5>
                            <a href="<?php echo SITE_URL; ?>/artist-tracks.php" class="btn btn-sm btn-link">
                                Voir tout <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Titre</th>
                                            <th>Écoutes</th>
                                            <th>Revenus</th>
                                            <th>Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <img src="<?php echo SITE_URL; ?>/assets/images/default-cover.jpg" 
                                                         alt="Cover" class="rounded me-3" style="width: 40px; height: 40px;">
                                                    <div>
                                                        <div class="fw-bold">Titre <?php echo $i; ?></div>
                                                        <small class="text-muted">
                                                            <?php echo ['Single', 'Album: Best Of', 'EP: Summer Vibes'][rand(0, 2)]; ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo formatNumber(rand(1000, 50000)); ?></td>
                                            <td><?php echo formatNumber(rand(5000, 100000)); ?> FCFA</td>
                                            <td><?php echo date('d/m/Y', strtotime('-' . rand(1, 60) . ' days')); ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-primary" title="Statistiques">
                                                        <i class="fas fa-chart-line"></i>
                                                    </button>
                                                    <button class="btn btn-outline-secondary" title="Modifier">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endfor; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Top Locations -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h5 class="mb-0">Répartition géographique des écoutes</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <canvas id="locationChart" height="200"></canvas>
                                </div>
                                <div class="col-md-6">
                                    <div class="location-list">
                                        <?php 
                                        $locations = [
                                            ['Tchad', rand(50, 70), '#0066CC'],
                                            ['Cameroun', rand(10, 20), '#479030'],
                                            ['France', rand(5, 15), '#EF4135'],
                                            ['Centrafrique', rand(3, 8), '#003082'],
                                            ['Autres', rand(2, 7), '#6C757D']
                                        ];
                                        foreach ($locations as $loc): 
                                        ?>
                                        <div class="location-item mb-3">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span><?php echo $loc[0]; ?></span>
                                                <span class="fw-bold"><?php echo $loc[1]; ?>%</span>
                                            </div>
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar" role="progressbar" 
                                                     style="width: <?php echo $loc[1]; ?>%; background-color: <?php echo $loc[2]; ?>"></div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="col-lg-4">
                    <!-- Quick Actions -->
                    <div class="dashboard-card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Actions rapides</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="<?php echo SITE_URL; ?>/upload.php" class="btn btn-primary">
                                    <i class="fas fa-upload me-2"></i> Uploader un titre
                                </a>
                                <a href="<?php echo SITE_URL; ?>/artist-analytics.php" class="btn btn-outline-primary">
                                    <i class="fas fa-chart-bar me-2"></i> Analytics détaillés
                                </a>
                                <a href="<?php echo SITE_URL; ?>/artist-profile.php" class="btn btn-outline-primary">
                                    <i class="fas fa-user-edit me-2"></i> Modifier le profil
                                </a>
                                <a href="<?php echo SITE_URL; ?>/artist-withdraw.php" class="btn btn-outline-success">
                                    <i class="fas fa-money-bill-wave me-2"></i> Retirer des fonds
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="dashboard-card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Activité récente</h5>
                        </div>
                        <div class="card-body">
                            <div class="activity-timeline">
                                <?php 
                                $activities = [
                                    ['icon' => 'fas fa-play', 'color' => 'primary', 'text' => '1,234 nouvelles écoutes', 'time' => 'Il y a 2h'],
                                    ['icon' => 'fas fa-user-plus', 'color' => 'success', 'text' => '15 nouveaux abonnés', 'time' => 'Il y a 5h'],
                                    ['icon' => 'fas fa-list', 'color' => 'info', 'text' => 'Ajouté à "Top Afrobeat"', 'time' => 'Hier'],
                                    ['icon' => 'fas fa-dollar-sign', 'color' => 'warning', 'text' => 'Paiement de 25,000 FCFA', 'time' => 'Il y a 2 jours'],
                                    ['icon' => 'fas fa-heart', 'color' => 'danger', 'text' => '89 nouveaux j\'aime', 'time' => 'Il y a 3 jours']
                                ];
                                foreach ($activities as $activity):
                                ?>
                                <div class="activity-item">
                                    <div class="activity-icon bg-<?php echo $activity['color']; ?>">
                                        <i class="<?php echo $activity['icon']; ?>"></i>
                                    </div>
                                    <div class="activity-content">
                                        <p class="mb-1"><?php echo $activity['text']; ?></p>
                                        <small class="text-muted"><?php echo $activity['time']; ?></small>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Upcoming Events -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h5 class="mb-0">Prochains événements</h5>
                        </div>
                        <div class="card-body">
                            <div class="event-list">
                                <div class="event-item mb-3">
                                    <div class="d-flex">
                                        <div class="event-date">
                                            <div class="date-day">15</div>
                                            <div class="date-month">DÉC</div>
                                        </div>
                                        <div class="event-details ms-3">
                                            <h6 class="mb-1">Concert à N'Djamena</h6>
                                            <p class="text-muted mb-0">Place de la Nation, 20h00</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="event-item mb-3">
                                    <div class="d-flex">
                                        <div class="event-date">
                                            <div class="date-day">22</div>
                                            <div class="date-month">DÉC</div>
                                        </div>
                                        <div class="event-details ms-3">
                                            <h6 class="mb-1">Interview Radio FM</h6>
                                            <p class="text-muted mb-0">Studio principal, 15h00</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-center mt-3">
                                    <a href="#" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-calendar me-2"></i> Gérer les événements
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<style>
.artist-dashboard {
    background: #f8f9fa;
    min-height: calc(100vh - 200px);
}

.dashboard-header {
    position: relative;
    overflow: hidden;
}

.artist-badges .badge {
    font-size: 0.85rem;
    padding: 0.5rem 1rem;
}

.stat-card {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    transition: transform 0.3s ease;
    height: 100%;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.25rem;
    margin-bottom: 1rem;
}

.stat-content h3 {
    font-size: 1.75rem;
    font-weight: bold;
    margin-bottom: 0.25rem;
}

.stat-content p {
    color: #6c757d;
    margin-bottom: 0.5rem;
}

.stat-content small {
    font-size: 0.875rem;
}

.dashboard-card {
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    overflow: hidden;
}

.dashboard-card .card-header {
    background: white;
    border-bottom: 1px solid #f0f0f0;
    padding: 1.25rem;
}

.dashboard-card .card-body {
    padding: 1.5rem;
}

.activity-timeline {
    position: relative;
}

.activity-item {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;
    position: relative;
}

.activity-item:not(:last-child)::after {
    content: '';
    position: absolute;
    left: 20px;
    top: 40px;
    bottom: -20px;
    width: 2px;
    background: #e9ecef;
}

.activity-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    flex-shrink: 0;
}

.activity-content {
    flex: 1;
}

.location-item {
    padding: 0.5rem 0;
}

.event-date {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 0.75rem;
    text-align: center;
    min-width: 60px;
}

.date-day {
    font-size: 1.5rem;
    font-weight: bold;
    line-height: 1;
}

.date-month {
    font-size: 0.75rem;
    text-transform: uppercase;
    color: #6c757d;
}

.event-details h6 {
    font-size: 0.95rem;
}

.table img {
    object-fit: cover;
}

@media (max-width: 768px) {
    .stat-card {
        margin-bottom: 1rem;
    }
    
    .dashboard-header h1 {
        font-size: 1.5rem;
    }
    
    .artist-badges .badge {
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
// Performance Chart
const performanceCtx = document.getElementById('performanceChart').getContext('2d');
const performanceChart = new Chart(performanceCtx, {
    type: 'line',
    data: {
        labels: Array.from({length: 30}, (_, i) => {
            const date = new Date();
            date.setDate(date.getDate() - (29 - i));
            return date.getDate() + '/' + (date.getMonth() + 1);
        }),
        datasets: [{
            label: 'Écoutes',
            data: Array.from({length: 30}, () => Math.floor(Math.random() * 2000) + 500),
            borderColor: '#007bff',
            backgroundColor: 'rgba(0, 123, 255, 0.1)',
            tension: 0.4,
            fill: true
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
                grid: {
                    borderDash: [5, 5]
                }
            },
            x: {
                grid: {
                    display: false
                }
            }
        }
    }
});

// Location Chart
const locationCtx = document.getElementById('locationChart').getContext('2d');
const locationChart = new Chart(locationCtx, {
    type: 'doughnut',
    data: {
        labels: ['Tchad', 'Cameroun', 'France', 'Centrafrique', 'Autres'],
        datasets: [{
            data: [65, 15, 10, 5, 5],
            backgroundColor: ['#0066CC', '#479030', '#EF4135', '#003082', '#6C757D']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Chart switching
document.querySelectorAll('.btn-group button').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.btn-group button').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        
        // Update chart data based on selection
        const type = this.textContent.trim();
        let newData;
        
        switch(type) {
            case 'Revenus':
                newData = Array.from({length: 30}, () => Math.floor(Math.random() * 50000) + 10000);
                performanceChart.data.datasets[0].label = 'Revenus (FCFA)';
                performanceChart.data.datasets[0].borderColor = '#28a745';
                performanceChart.data.datasets[0].backgroundColor = 'rgba(40, 167, 69, 0.1)';
                break;
            case 'Abonnés':
                newData = Array.from({length: 30}, () => Math.floor(Math.random() * 50) + 5);
                performanceChart.data.datasets[0].label = 'Nouveaux abonnés';
                performanceChart.data.datasets[0].borderColor = '#dc3545';
                performanceChart.data.datasets[0].backgroundColor = 'rgba(220, 53, 69, 0.1)';
                break;
            default:
                newData = Array.from({length: 30}, () => Math.floor(Math.random() * 2000) + 500);
                performanceChart.data.datasets[0].label = 'Écoutes';
                performanceChart.data.datasets[0].borderColor = '#007bff';
                performanceChart.data.datasets[0].backgroundColor = 'rgba(0, 123, 255, 0.1)';
        }
        
        performanceChart.data.datasets[0].data = newData;
        performanceChart.update();
    });
});
</script>

<?php include 'includes/footer.php'; ?>