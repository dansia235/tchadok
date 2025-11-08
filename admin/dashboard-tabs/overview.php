<?php
// Vue d'ensemble - Dashboard principal
?>

<!-- En-tête avec statistiques principales -->
<div class="row mb-4">
    <div class="col-12">
        <h2 class="mb-0 d-flex align-items-center">
            <i class="fas fa-tachometer-alt me-3 text-primary"></i>
            Vue d'ensemble
            <span class="badge bg-success ms-3">Système Actif</span>
        </h2>
        <p class="text-muted">Tableau de bord principal de la plateforme Tchadok</p>
    </div>
</div>

<!-- Statistiques principales -->
<div class="row g-4 mb-5">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card text-center pulse">
            <i class="fas fa-users fa-3x text-primary mb-3"></i>
            <div class="stat-number"><?php echo number_format($stats['users'] ?? 0); ?></div>
            <h6 class="text-muted mb-0">Utilisateurs</h6>
            <small class="text-success">
                <i class="fas fa-arrow-up"></i> +12% ce mois
            </small>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="stat-card text-center">
            <i class="fas fa-microphone fa-3x text-warning mb-3"></i>
            <div class="stat-number"><?php echo number_format($stats['artists'] ?? 0); ?></div>
            <h6 class="text-muted mb-0">Artistes</h6>
            <small class="text-success">
                <i class="fas fa-arrow-up"></i> +8% ce mois
            </small>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="stat-card text-center">
            <i class="fas fa-music fa-3x text-info mb-3"></i>
            <div class="stat-number"><?php echo number_format($stats['tracks'] ?? 0); ?></div>
            <h6 class="text-muted mb-0">Pistes</h6>
            <small class="text-success">
                <i class="fas fa-arrow-up"></i> +25% ce mois
            </small>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="stat-card text-center">
            <i class="fas fa-coins fa-3x text-success mb-3"></i>
            <div class="stat-number"><?php echo number_format($stats['revenue'] ?? 0); ?></div>
            <h6 class="text-muted mb-0">Revenus (XAF)</h6>
            <small class="text-success">
                <i class="fas fa-arrow-up"></i> +18% ce mois
            </small>
        </div>
    </div>
</div>

<!-- Actions Rapides -->
<div class="row g-4 mb-5">
    <div class="col-12">
        <div class="chart-card">
            <h5 class="mb-4">
                <i class="fas fa-bolt me-2 text-warning"></i>
                Actions Rapides
            </h5>
            
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="quick-action-card text-center" data-bs-toggle="modal" data-bs-target="#generateDataModal">
                        <i class="fas fa-database fa-2x mb-3"></i>
                        <h6>Générer Données</h6>
                        <p class="mb-0">Créer des données de test</p>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="quick-action-card text-center" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="fas fa-user-plus fa-2x mb-3"></i>
                        <h6>Nouvel Utilisateur</h6>
                        <p class="mb-0">Ajouter un utilisateur</p>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="quick-action-card text-center" data-bs-toggle="modal" data-bs-target="#addTrackModal">
                        <i class="fas fa-music fa-2x mb-3"></i>
                        <h6>Nouvelle Musique</h6>
                        <p class="mb-0">Ajouter une piste</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Graphiques et Activités -->
<div class="row g-4">
    <!-- Graphique des inscriptions -->
    <div class="col-lg-8">
        <div class="chart-card">
            <h6 class="mb-4">
                <i class="fas fa-chart-area me-2 text-primary"></i>
                Croissance des Utilisateurs
            </h6>
            <div class="chart-container">
                <canvas id="usersChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Top Artistes -->
    <div class="col-lg-4">
        <div class="chart-card">
            <h6 class="mb-4">
                <i class="fas fa-star me-2 text-warning"></i>
                Top Artistes
            </h6>
            <?php if (!empty($topArtists)): ?>
                <?php foreach (array_slice($topArtists, 0, 5) as $index => $artist): ?>
                <div class="activity-item d-flex align-items-center">
                    <div class="me-3">
                        <span class="badge badge-custom bg-primary">#<?php echo $index + 1; ?></span>
                    </div>
                    <div class="flex-grow-1">
                        <strong><?php echo htmlspecialchars($artist['stage_name']); ?></strong>
                        <?php if ($artist['verified']): ?>
                            <i class="fas fa-check-circle text-primary ms-1" title="Vérifié"></i>
                        <?php endif; ?>
                        <br>
                        <small class="text-muted"><?php echo number_format($artist['total_streams']); ?> écoutes</small>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted text-center">Aucun artiste disponible</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Activités Récentes -->
<div class="row g-4 mt-2">
    <div class="col-md-6">
        <div class="chart-card">
            <h6 class="mb-4">
                <i class="fas fa-user-clock me-2 text-info"></i>
                Utilisateurs Récents
            </h6>
            <?php if (!empty($recentUsers)): ?>
                <?php foreach ($recentUsers as $user): ?>
                <div class="activity-item">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ccircle cx='50' cy='50' r='45' fill='%23<?php echo substr(md5($user['username']), 0, 6); ?>'/%3E%3Ctext x='50' y='60' text-anchor='middle' font-size='30' fill='white'%3E<?php echo strtoupper(substr($user['first_name'], 0, 1)); ?><?php echo strtoupper(substr($user['last_name'], 0, 1)); ?>%3C/text%3E%3C/svg%3E" 
                                 width="40" height="40" class="rounded-circle">
                        </div>
                        <div class="flex-grow-1">
                            <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong>
                            <br>
                            <small class="text-muted">@<?php echo htmlspecialchars($user['username']); ?></small>
                            <br>
                            <small class="text-muted">
                                <i class="fas fa-clock"></i>
                                <?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?>
                            </small>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted text-center">Aucune activité récente</p>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="chart-card">
            <h6 class="mb-4">
                <i class="fas fa-dollar-sign me-2 text-success"></i>
                Transactions Récentes
            </h6>
            <?php if (!empty($recentTransactions)): ?>
                <?php foreach ($recentTransactions as $transaction): ?>
                <div class="activity-item">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong><?php echo number_format($transaction['amount']); ?> XAF</strong>
                            <br>
                            <small><?php echo htmlspecialchars($transaction['description']); ?></small>
                            <br>
                            <small class="text-muted">par @<?php echo htmlspecialchars($transaction['username']); ?></small>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-success">Payé</span>
                            <br>
                            <small class="text-muted"><?php echo date('d/m H:i', strtotime($transaction['created_at'])); ?></small>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted text-center">Aucune transaction récente</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal pour génération de données -->
<div class="modal fade" id="generateDataModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-database me-2"></i>
                    Gestion des Données
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Choisissez l'action à effectuer sur les données de la plateforme :</p>
                
                <div class="d-grid gap-3">
                    <form method="POST" onsubmit="return confirm('Générer de nouvelles données de test ?')">
                        <input type="hidden" name="action" value="generate_data">
                        <button type="submit" class="btn btn-success-admin w-100">
                            <i class="fas fa-plus-circle me-2"></i>
                            Générer Données de Test
                        </button>
                    </form>
                    
                    <form method="POST" onsubmit="return confirm('ATTENTION: Supprimer toutes les données (sauf admin) ?')">
                        <input type="hidden" name="action" value="clear_data">
                        <button type="submit" class="btn btn-danger-admin w-100">
                            <i class="fas fa-trash me-2"></i>
                            Vider les Données
                        </button>
                    </form>
                    
                    <a href="reset-database.php" class="btn btn-warning-admin w-100" onclick="return confirm('Réinitialiser complètement la base de données ?')">
                        <i class="fas fa-database me-2"></i>
                        Réinitialiser Base de Données
                    </a>
                </div>
                
                <div class="mt-3 alert alert-info">
                    <small>
                        <i class="fas fa-info-circle me-1"></i>
                        <strong>Info:</strong> La génération crée ~50 utilisateurs, ~25 artistes, ~40 albums et centaines de pistes avec données réalistes.
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Graphique des utilisateurs
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('usersChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin'],
            datasets: [{
                label: 'Nouveaux Utilisateurs',
                data: [12, 19, 8, 25, 32, 45],
                borderColor: '#0066CC',
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
});
</script>