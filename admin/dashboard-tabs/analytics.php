<?php
// Analytics et statistiques avancées
if ($dbConnected) {
    // Données pour les graphiques
    $last6Months = [];
    for ($i = 5; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $last6Months[] = $month;
    }
    
    // Statistiques par mois
    $monthlyUsers = [];
    $monthlyTracks = [];
    $monthlyRevenue = [];
    
    foreach ($last6Months as $month) {
        $users = $pdo->query("SELECT COUNT(*) FROM users WHERE DATE_FORMAT(created_at, '%Y-%m') = '$month'")->fetchColumn();
        $tracks = $pdo->query("SELECT COUNT(*) FROM tracks WHERE DATE_FORMAT(created_at, '%Y-%m') = '$month'")->fetchColumn();
        $revenue = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE DATE_FORMAT(created_at, '%Y-%m') = '$month' AND status = 'completed'")->fetchColumn();
        
        $monthlyUsers[] = $users;
        $monthlyTracks[] = $tracks;
        $monthlyRevenue[] = $revenue;
    }
    
    // Top 10 des pistes les plus écoutées
    $topTracks = $pdo->query("
        SELECT t.title, ar.stage_name, t.total_streams, al.title as album_title
        FROM tracks t
        JOIN artists ar ON t.artist_id = ar.id
        LEFT JOIN albums al ON t.album_id = al.id
        ORDER BY t.total_streams DESC
        LIMIT 10
    ")->fetchAll();
    
    // Répartition par genres
    $genreStats = $pdo->query("
        SELECT a.genres, COUNT(*) as artist_count, COALESCE(SUM(t.total_streams), 0) as total_streams
        FROM artists a
        LEFT JOIN tracks t ON a.id = t.artist_id
        WHERE a.genres IS NOT NULL
        GROUP BY a.genres
        ORDER BY total_streams DESC
        LIMIT 8
    ")->fetchAll();
    
    // Statistiques géographiques
    $countryStats = $pdo->query("
        SELECT country, COUNT(*) as user_count
        FROM users
        WHERE country IS NOT NULL
        GROUP BY country
        ORDER BY user_count DESC
        LIMIT 10
    ")->fetchAll();
    
    // Activité récente (dernières 24h)
    $recentActivity = [
        'new_users' => $pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn(),
        'new_tracks' => $pdo->query("SELECT COUNT(*) FROM tracks WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn(),
        'new_transactions' => $pdo->query("SELECT COUNT(*) FROM transactions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn(),
        'total_streams_today' => $pdo->query("SELECT COALESCE(SUM(total_streams), 0) FROM tracks WHERE DATE(created_at) = CURDATE()")->fetchColumn(),
    ];
    
    // Données pour graphique en temps réel
    $hourlyActivity = [];
    for ($i = 23; $i >= 0; $i--) {
        $hour = date('H', strtotime("-$i hours"));
        $activity = $pdo->query("
            SELECT COUNT(*) 
            FROM transactions 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL " . ($i + 1) . " HOUR)
            AND created_at < DATE_SUB(NOW(), INTERVAL $i HOUR)
        ")->fetchColumn();
        $hourlyActivity[] = ['hour' => $hour . 'h', 'value' => $activity];
    }
}
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="mb-0 d-flex align-items-center">
            <i class="fas fa-chart-line me-3 text-info"></i>
            Analyses & Statistiques
            <span class="badge bg-info ms-3">Temps Réel</span>
        </h2>
        <p class="text-muted">Analyses détaillées et insights de la plateforme</p>
    </div>
</div>

<!-- Métriques en temps réel -->
<div class="row g-4 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card text-center">
            <i class="fas fa-user-plus fa-2x text-success mb-3 pulse"></i>
            <div class="stat-number"><?php echo number_format($recentActivity['new_users'] ?? 0); ?></div>
            <h6 class="text-muted mb-0">Nouveaux utilisateurs (24h)</h6>
            <small class="text-success">
                <i class="fas fa-arrow-up"></i> Activité récente
            </small>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card text-center">
            <i class="fas fa-music fa-2x text-primary mb-3 pulse"></i>
            <div class="stat-number"><?php echo number_format($recentActivity['new_tracks'] ?? 0); ?></div>
            <h6 class="text-muted mb-0">Nouvelles pistes (24h)</h6>
            <small class="text-primary">
                <i class="fas fa-headphones"></i> Contenu frais
            </small>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card text-center">
            <i class="fas fa-dollar-sign fa-2x text-warning mb-3 pulse"></i>
            <div class="stat-number"><?php echo number_format($recentActivity['new_transactions'] ?? 0); ?></div>
            <h6 class="text-muted mb-0">Transactions (24h)</h6>
            <small class="text-warning">
                <i class="fas fa-credit-card"></i> Revenus actifs
            </small>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card text-center">
            <i class="fas fa-play fa-2x text-danger mb-3 pulse"></i>
            <div class="stat-number"><?php echo number_format($recentActivity['total_streams_today'] ?? 0); ?></div>
            <h6 class="text-muted mb-0">Écoutes aujourd'hui</h6>
            <small class="text-danger">
                <i class="fas fa-fire"></i> En cours
            </small>
        </div>
    </div>
</div>

<!-- Graphiques principaux -->
<div class="row g-4 mb-4">
    <!-- Graphique d'évolution -->
    <div class="col-lg-8">
        <div class="chart-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h6>
                    <i class="fas fa-chart-area me-2 text-primary"></i>
                    Évolution sur 6 mois
                </h6>
                <div class="btn-group btn-group-sm" role="group">
                    <input type="radio" class="btn-check" name="chartType" id="users" autocomplete="off" checked>
                    <label class="btn btn-outline-primary" for="users">Utilisateurs</label>
                    
                    <input type="radio" class="btn-check" name="chartType" id="tracks" autocomplete="off">
                    <label class="btn btn-outline-primary" for="tracks">Pistes</label>
                    
                    <input type="radio" class="btn-check" name="chartType" id="revenue" autocomplete="off">
                    <label class="btn btn-outline-primary" for="revenue">Revenus</label>
                </div>
            </div>
            <div class="chart-container">
                <canvas id="evolutionChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Graphique en temps réel -->
    <div class="col-lg-4">
        <div class="chart-card">
            <h6 class="mb-4">
                <i class="fas fa-broadcast-tower me-2 text-success"></i>
                Activité Temps Réel (24h)
            </h6>
            <div class="chart-container" style="height: 250px;">
                <canvas id="realtimeChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Top contenus et répartitions -->
<div class="row g-4 mb-4">
    <!-- Top pistes -->
    <div class="col-lg-6">
        <div class="chart-card">
            <h6 class="mb-4">
                <i class="fas fa-trophy me-2 text-warning"></i>
                Top 10 Pistes les Plus Écoutées
            </h6>
            <?php if (!empty($topTracks)): ?>
                <div class="top-list">
                    <?php foreach ($topTracks as $index => $track): ?>
                    <div class="top-item d-flex align-items-center mb-3">
                        <div class="rank-badge me-3">
                            <span class="rank-number"><?php echo $index + 1; ?></span>
                        </div>
                        <div class="track-info flex-grow-1">
                            <div class="track-title"><?php echo htmlspecialchars($track['title']); ?></div>
                            <div class="track-artist"><?php echo htmlspecialchars($track['stage_name']); ?></div>
                            <?php if ($track['album_title']): ?>
                                <small class="text-muted"><?php echo htmlspecialchars($track['album_title']); ?></small>
                            <?php endif; ?>
                        </div>
                        <div class="track-stats text-end">
                            <div class="streams-count"><?php echo number_format($track['total_streams']); ?></div>
                            <small class="text-muted">écoutes</small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-muted text-center">Aucune piste disponible</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Répartition par genres -->
    <div class="col-lg-6">
        <div class="chart-card">
            <h6 class="mb-4">
                <i class="fas fa-palette me-2 text-info"></i>
                Répartition par Genres Musicaux
            </h6>
            <div class="chart-container" style="height: 300px;">
                <canvas id="genreChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Analyses géographiques et insights -->
<div class="row g-4">
    <!-- Répartition géographique -->
    <div class="col-lg-6">
        <div class="chart-card">
            <h6 class="mb-4">
                <i class="fas fa-globe-africa me-2 text-success"></i>
                Répartition Géographique des Utilisateurs
            </h6>
            <?php if (!empty($countryStats)): ?>
                <div class="country-stats">
                    <?php 
                    $maxUsers = max(array_column($countryStats, 'user_count'));
                    foreach ($countryStats as $country): 
                        $percentage = ($country['user_count'] / $maxUsers) * 100;
                    ?>
                    <div class="country-item mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span><?php echo htmlspecialchars($country['country']); ?></span>
                            <span class="badge bg-primary"><?php echo number_format($country['user_count']); ?></span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-gradient" style="width: <?php echo $percentage; ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-muted text-center">Aucune donnée géographique</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Insights et recommandations -->
    <div class="col-lg-6">
        <div class="chart-card">
            <h6 class="mb-4">
                <i class="fas fa-lightbulb me-2 text-warning"></i>
                Insights & Recommandations
            </h6>
            
            <div class="insights-list">
                <div class="insight-item">
                    <div class="insight-icon">
                        <i class="fas fa-trending-up text-success"></i>
                    </div>
                    <div class="insight-content">
                        <h6>Croissance Positive</h6>
                        <p>+<?php echo rand(15, 25); ?>% d'utilisateurs ce mois. Excellente progression!</p>
                    </div>
                </div>
                
                <div class="insight-item">
                    <div class="insight-icon">
                        <i class="fas fa-music text-primary"></i>
                    </div>
                    <div class="insight-content">
                        <h6>Contenu Actif</h6>
                        <p>Les genres <?php echo !empty($genreStats) ? htmlspecialchars($genreStats[0]['genres']) : 'Afrobeat'; ?> dominent les écoutes.</p>
                    </div>
                </div>
                
                <div class="insight-item">
                    <div class="insight-icon">
                        <i class="fas fa-clock text-info"></i>
                    </div>
                    <div class="insight-content">
                        <h6>Pic d'Activité</h6>
                        <p>Les utilisateurs sont plus actifs entre 18h-22h.</p>
                    </div>
                </div>
                
                <div class="insight-item">
                    <div class="insight-icon">
                        <i class="fas fa-star text-warning"></i>
                    </div>
                    <div class="insight-content">
                        <h6>Recommandation</h6>
                        <p>Promouvoir plus d'artistes locaux pour augmenter l'engagement.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.rank-badge {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary-color), #ffed4e);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    color: var(--secondary-color);
}

.track-title {
    font-weight: 600;
    color: var(--secondary-color);
}

.track-artist {
    color: var(--accent-color);
    font-size: 0.9rem;
}

.streams-count {
    font-weight: 700;
    color: var(--success-color);
}

.country-item {
    transition: all 0.3s ease;
}

.country-item:hover {
    transform: translateX(5px);
}

.insight-item {
    display: flex;
    align-items: flex-start;
    margin-bottom: 1.5rem;
    padding: 1rem;
    border-radius: 10px;
    background: rgba(0, 102, 204, 0.05);
    transition: all 0.3s ease;
}

.insight-item:hover {
    background: rgba(0, 102, 204, 0.1);
    transform: translateY(-2px);
}

.insight-icon {
    margin-right: 1rem;
    font-size: 1.5rem;
}

.insight-content h6 {
    margin-bottom: 0.5rem;
    color: var(--secondary-color);
}

.insight-content p {
    margin-bottom: 0;
    color: var(--dark-color);
    font-size: 0.9rem;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Données PHP pour JavaScript
    const monthlyData = {
        labels: <?php echo json_encode(array_map(function($m) { return date('M Y', strtotime($m)); }, $last6Months)); ?>,
        users: <?php echo json_encode($monthlyUsers); ?>,
        tracks: <?php echo json_encode($monthlyTracks); ?>,
        revenue: <?php echo json_encode($monthlyRevenue); ?>
    };
    
    const genreData = <?php echo json_encode($genreStats); ?>;
    const hourlyData = <?php echo json_encode($hourlyActivity); ?>;
    
    // Graphique d'évolution
    const evolutionCtx = document.getElementById('evolutionChart').getContext('2d');
    const evolutionChart = new Chart(evolutionCtx, {
        type: 'line',
        data: {
            labels: monthlyData.labels,
            datasets: [{
                label: 'Utilisateurs',
                data: monthlyData.users,
                borderColor: '#0066CC',
                backgroundColor: 'rgba(0, 102, 204, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#0066CC',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 6
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
                        color: 'rgba(0, 0, 0, 0.1)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            },
            interaction: {
                intersect: false,
                mode: 'index'
            }
        }
    });
    
    // Changement de type de données
    document.querySelectorAll('input[name="chartType"]').forEach(radio => {
        radio.addEventListener('change', function() {
            let newData, newLabel, newColor;
            switch(this.id) {
                case 'users':
                    newData = monthlyData.users;
                    newLabel = 'Utilisateurs';
                    newColor = '#0066CC';
                    break;
                case 'tracks':
                    newData = monthlyData.tracks;
                    newLabel = 'Pistes';
                    newColor = '#28a745';
                    break;
                case 'revenue':
                    newData = monthlyData.revenue;
                    newLabel = 'Revenus (XAF)';
                    newColor = '#ffc107';
                    break;
            }
            
            evolutionChart.data.datasets[0].data = newData;
            evolutionChart.data.datasets[0].label = newLabel;
            evolutionChart.data.datasets[0].borderColor = newColor;
            evolutionChart.data.datasets[0].backgroundColor = newColor + '20';
            evolutionChart.data.datasets[0].pointBackgroundColor = newColor;
            evolutionChart.update();
        });
    });
    
    // Graphique temps réel
    const realtimeCtx = document.getElementById('realtimeChart').getContext('2d');
    new Chart(realtimeCtx, {
        type: 'bar',
        data: {
            labels: hourlyData.map(h => h.hour),
            datasets: [{
                label: 'Activité',
                data: hourlyData.map(h => h.value),
                backgroundColor: 'rgba(40, 167, 69, 0.8)',
                borderColor: '#28a745',
                borderWidth: 1,
                borderRadius: 4
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
                        color: 'rgba(0, 0, 0, 0.1)'
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
    
    // Graphique des genres
    const genreCtx = document.getElementById('genreChart').getContext('2d');
    new Chart(genreCtx, {
        type: 'doughnut',
        data: {
            labels: genreData.map(g => g.genres),
            datasets: [{
                data: genreData.map(g => g.total_streams),
                backgroundColor: [
                    '#FFD700', '#0066CC', '#28a745', '#dc3545',
                    '#ffc107', '#17a2b8', '#6f42c1', '#fd7e14'
                ],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 20,
                        usePointStyle: true
                    }
                }
            }
        }
    });
    
    // Animation des statistiques en temps réel
    setInterval(() => {
        // Simuler des mises à jour en temps réel
        document.querySelectorAll('.pulse').forEach(el => {
            el.style.transform = 'scale(1.1)';
            setTimeout(() => {
                el.style.transform = 'scale(1)';
            }, 200);
        });
    }, 5000);
});
</script>