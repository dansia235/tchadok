<?php
// Paramètres système et configuration
if ($dbConnected) {
    // Configuration de base
    $settings = [
        'site_name' => 'Tchadok',
        'site_description' => 'Plateforme musicale camerounaise',
        'site_logo' => '/assets/logo.png',
        'maintenance_mode' => false,
        'user_registration' => true,
        'email_verification' => true,
        'max_upload_size' => '50MB',
        'allowed_formats' => ['mp3', 'wav', 'flac'],
        'commission_rate' => 15.0,
        'minimum_payout' => 10000,
        'smtp_host' => 'smtp.gmail.com',
        'smtp_port' => 587,
        'smtp_username' => '',
        'smtp_password' => '',
        'backup_frequency' => 'daily',
        'analytics_enabled' => true,
        'cdn_enabled' => false,
        'payment_methods' => ['mobile_money', 'bank_transfer', 'paypal'],
        'currency' => 'XAF',
        'timezone' => 'Africa/Douala',
        'language' => 'fr',
        'social_login' => true,
        'auto_approval' => false,
        'content_moderation' => true
    ];
    
    // Statistiques système
    $systemStats = [
        'disk_usage' => '2.3 GB',
        'bandwidth_usage' => '15.7 GB',
        'database_size' => '127 MB',
        'total_files' => 1247,
        'server_uptime' => '15 jours, 4 heures',
        'php_version' => phpversion(),
        'mysql_version' => $pdo->query("SELECT VERSION()")->fetchColumn(),
        'server_memory' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size')
    ];
    
    // Logs récents
    $recentLogs = [
        ['level' => 'info', 'message' => 'Nouveau utilisateur inscrit: user@example.com', 'time' => '2024-01-15 14:23:45'],
        ['level' => 'warning', 'message' => 'Tentative de connexion échouée pour admin', 'time' => '2024-01-15 14:20:12'],
        ['level' => 'info', 'message' => 'Piste approuvée: "Nouveau Son" par Artist123', 'time' => '2024-01-15 14:15:33'],
        ['level' => 'error', 'message' => 'Erreur de traitement de paiement: Transaction #1234', 'time' => '2024-01-15 14:10:22'],
        ['level' => 'info', 'message' => 'Sauvegarde automatique effectuée', 'time' => '2024-01-15 14:00:00']
    ];
}
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="mb-0 d-flex align-items-center">
            <i class="fas fa-cogs me-3 text-secondary"></i>
            Paramètres & Configuration
            <span class="badge bg-secondary ms-3">Système</span>
        </h2>
        <p class="text-muted">Configuration générale et paramètres avancés de la plateforme</p>
    </div>
</div>

<!-- Onglets de configuration -->
<div class="row mb-4">
    <div class="col-12">
        <div class="chart-card">
            <ul class="nav nav-tabs mb-4" id="settingsTabs">
                <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="tab" href="#general">
                        <i class="fas fa-cog me-2"></i>Général
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#users">
                        <i class="fas fa-users me-2"></i>Utilisateurs
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#music">
                        <i class="fas fa-music me-2"></i>Musique
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#payments">
                        <i class="fas fa-credit-card me-2"></i>Paiements
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#email">
                        <i class="fas fa-envelope me-2"></i>Email
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#system">
                        <i class="fas fa-server me-2"></i>Système
                    </a>
                </li>
            </ul>
            
            <div class="tab-content">
                <!-- Configuration générale -->
                <div class="tab-pane fade show active" id="general">
                    <form id="generalSettingsForm">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label">Nom du site</label>
                                <input type="text" class="form-control" value="<?php echo $settings['site_name']; ?>" name="site_name">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Description</label>
                                <input type="text" class="form-control" value="<?php echo $settings['site_description']; ?>" name="site_description">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Logo du site</label>
                                <input type="file" class="form-control" accept="image/*">
                                <small class="text-muted">Formats acceptés: PNG, JPG (max 2MB)</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Fuseau horaire</label>
                                <select class="form-select" name="timezone">
                                    <option value="Africa/Douala" selected>Africa/Douala (GMT+1)</option>
                                    <option value="Africa/Yaounde">Africa/Yaounde (GMT+1)</option>
                                    <option value="UTC">UTC (GMT+0)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Langue par défaut</label>
                                <select class="form-select" name="language">
                                    <option value="fr" selected>Français</option>
                                    <option value="en">English</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Devise</label>
                                <select class="form-select" name="currency">
                                    <option value="XAF" selected>Franc CFA (XAF)</option>
                                    <option value="USD">Dollar US (USD)</option>
                                    <option value="EUR">Euro (EUR)</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="maintenanceMode" <?php echo $settings['maintenance_mode'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="maintenanceMode">
                                        Mode maintenance activé
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <hr class="my-4">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-2"></i>Sauvegarder les paramètres généraux
                        </button>
                    </form>
                </div>
                
                <!-- Configuration utilisateurs -->
                <div class="tab-pane fade" id="users">
                    <form id="userSettingsForm">
                        <div class="row g-4">
                            <div class="col-12">
                                <h6 class="text-primary">Inscription et Comptes</h6>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="userRegistration" <?php echo $settings['user_registration'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="userRegistration">
                                        Permettre l'inscription
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="emailVerification" <?php echo $settings['email_verification'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="emailVerification">
                                        Vérification email obligatoire
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="socialLogin" <?php echo $settings['social_login'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="socialLogin">
                                        Connexion via réseaux sociaux
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="autoApproval" <?php echo $settings['auto_approval'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="autoApproval">
                                        Approbation automatique des comptes
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <hr class="my-4">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-2"></i>Sauvegarder les paramètres utilisateurs
                        </button>
                    </form>
                </div>
                
                <!-- Configuration musique -->
                <div class="tab-pane fade" id="music">
                    <form id="musicSettingsForm">
                        <div class="row g-4">
                            <div class="col-12">
                                <h6 class="text-primary">Upload et Formats</h6>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Taille maximale de fichier</label>
                                <select class="form-select" name="max_upload_size">
                                    <option value="50MB" selected>50 MB</option>
                                    <option value="100MB">100 MB</option>
                                    <option value="200MB">200 MB</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Formats autorisés</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="formatMp3" checked>
                                    <label class="form-check-label" for="formatMp3">MP3</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="formatWav" checked>
                                    <label class="form-check-label" for="formatWav">WAV</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="formatFlac" checked>
                                    <label class="form-check-label" for="formatFlac">FLAC</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <h6 class="text-primary mt-4">Modération du Contenu</h6>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="contentModeration" <?php echo $settings['content_moderation'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="contentModeration">
                                        Modération automatique activée
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="autoApprovalMusic" <?php echo $settings['auto_approval'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="autoApprovalMusic">
                                        Approbation automatique des pistes
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <hr class="my-4">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-2"></i>Sauvegarder les paramètres musique
                        </button>
                    </form>
                </div>
                
                <!-- Configuration paiements -->
                <div class="tab-pane fade" id="payments">
                    <form id="paymentSettingsForm">
                        <div class="row g-4">
                            <div class="col-12">
                                <h6 class="text-primary">Commissions et Seuils</h6>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Taux de commission (%)</label>
                                <input type="number" class="form-control" value="<?php echo $settings['commission_rate']; ?>" min="0" max="50" step="0.1" name="commission_rate">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Seuil minimum de retrait (XAF)</label>
                                <input type="number" class="form-control" value="<?php echo $settings['minimum_payout']; ?>" min="1000" step="1000" name="minimum_payout">
                            </div>
                            <div class="col-12">
                                <h6 class="text-primary mt-4">Méthodes de Paiement</h6>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="mobileMoney" checked>
                                    <label class="form-check-label" for="mobileMoney">
                                        <i class="fas fa-mobile-alt me-2 text-success"></i>Mobile Money
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="bankTransfer" checked>
                                    <label class="form-check-label" for="bankTransfer">
                                        <i class="fas fa-university me-2 text-primary"></i>Virement bancaire
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="paypal" checked>
                                    <label class="form-check-label" for="paypal">
                                        <i class="fab fa-paypal me-2 text-info"></i>PayPal
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <hr class="my-4">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-2"></i>Sauvegarder les paramètres de paiement
                        </button>
                    </form>
                </div>
                
                <!-- Configuration email -->
                <div class="tab-pane fade" id="email">
                    <form id="emailSettingsForm">
                        <div class="row g-4">
                            <div class="col-12">
                                <h6 class="text-primary">Configuration SMTP</h6>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Serveur SMTP</label>
                                <input type="text" class="form-control" value="<?php echo $settings['smtp_host']; ?>" name="smtp_host">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Port SMTP</label>
                                <input type="number" class="form-control" value="<?php echo $settings['smtp_port']; ?>" name="smtp_port">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Nom d'utilisateur SMTP</label>
                                <input type="email" class="form-control" value="<?php echo $settings['smtp_username']; ?>" name="smtp_username">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Mot de passe SMTP</label>
                                <input type="password" class="form-control" placeholder="••••••••" name="smtp_password">
                            </div>
                            <div class="col-12">
                                <button type="button" class="btn btn-outline-primary" onclick="testEmailConfig()">
                                    <i class="fas fa-paper-plane me-2"></i>Tester la configuration
                                </button>
                            </div>
                        </div>
                        
                        <hr class="my-4">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-2"></i>Sauvegarder les paramètres email
                        </button>
                    </form>
                </div>
                
                <!-- Configuration système -->
                <div class="tab-pane fade" id="system">
                    <div class="row g-4">
                        <!-- Informations système -->
                        <div class="col-lg-6">
                            <h6 class="text-primary mb-3">Informations Système</h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <tr>
                                        <td><strong>Version PHP</strong></td>
                                        <td><?php echo $systemStats['php_version']; ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Version MySQL</strong></td>
                                        <td><?php echo $systemStats['mysql_version']; ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Mémoire serveur</strong></td>
                                        <td><?php echo $systemStats['server_memory']; ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Taille upload max</strong></td>
                                        <td><?php echo $systemStats['upload_max_filesize']; ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Temps d'exécution max</strong></td>
                                        <td><?php echo $systemStats['max_execution_time']; ?>s</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Utilisation des ressources -->
                        <div class="col-lg-6">
                            <h6 class="text-primary mb-3">Utilisation des Ressources</h6>
                            <div class="stat-mini-cards">
                                <div class="mini-card mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span>Espace disque</span>
                                        <strong class="text-warning"><?php echo $systemStats['disk_usage']; ?></strong>
                                    </div>
                                    <div class="progress mt-2" style="height: 6px;">
                                        <div class="progress-bar bg-warning" style="width: 23%"></div>
                                    </div>
                                </div>
                                <div class="mini-card mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span>Bande passante</span>
                                        <strong class="text-info"><?php echo $systemStats['bandwidth_usage']; ?></strong>
                                    </div>
                                    <div class="progress mt-2" style="height: 6px;">
                                        <div class="progress-bar bg-info" style="width: 45%"></div>
                                    </div>
                                </div>
                                <div class="mini-card mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span>Base de données</span>
                                        <strong class="text-success"><?php echo $systemStats['database_size']; ?></strong>
                                    </div>
                                    <div class="progress mt-2" style="height: 6px;">
                                        <div class="progress-bar bg-success" style="width: 12%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Actions système -->
                        <div class="col-12">
                            <h6 class="text-primary mb-3">Actions Système</h6>
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <button class="btn btn-outline-primary w-100" onclick="createBackup()">
                                        <i class="fas fa-download me-2"></i>Créer une sauvegarde
                                    </button>
                                </div>
                                <div class="col-md-3">
                                    <button class="btn btn-outline-warning w-100" onclick="clearCache()">
                                        <i class="fas fa-broom me-2"></i>Vider le cache
                                    </button>
                                </div>
                                <div class="col-md-3">
                                    <button class="btn btn-outline-info w-100" onclick="optimizeDatabase()">
                                        <i class="fas fa-database me-2"></i>Optimiser la BDD
                                    </button>
                                </div>
                                <div class="col-md-3">
                                    <button class="btn btn-outline-danger w-100" onclick="viewErrorLogs()">
                                        <i class="fas fa-bug me-2"></i>Logs d'erreur
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Logs récents -->
<div class="row">
    <div class="col-12">
        <div class="chart-card">
            <h6 class="mb-4">
                <i class="fas fa-list-alt me-2 text-info"></i>
                Logs Récents du Système
            </h6>
            
            <div class="logs-container">
                <?php foreach ($recentLogs as $log): ?>
                <div class="log-item d-flex align-items-center mb-3">
                    <div class="log-level me-3">
                        <?php
                        $levelClass = match($log['level']) {
                            'error' => 'bg-danger',
                            'warning' => 'bg-warning text-dark',
                            'info' => 'bg-primary',
                            default => 'bg-secondary'
                        };
                        $levelIcon = match($log['level']) {
                            'error' => 'fa-exclamation-triangle',
                            'warning' => 'fa-exclamation-circle',
                            'info' => 'fa-info-circle',
                            default => 'fa-circle'
                        };
                        ?>
                        <span class="badge <?php echo $levelClass; ?>">
                            <i class="fas <?php echo $levelIcon; ?> me-1"></i>
                            <?php echo strtoupper($log['level']); ?>
                        </span>
                    </div>
                    <div class="log-message flex-grow-1">
                        <?php echo htmlspecialchars($log['message']); ?>
                    </div>
                    <div class="log-time">
                        <small class="text-muted"><?php echo $log['time']; ?></small>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="text-center mt-4">
                <button class="btn btn-outline-secondary" onclick="viewAllLogs()">
                    <i class="fas fa-eye me-2"></i>Voir tous les logs
                </button>
                <button class="btn btn-outline-danger ms-2" onclick="clearLogs()">
                    <i class="fas fa-trash me-2"></i>Effacer les logs
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.mini-card {
    padding: 1rem;
    border-radius: 8px;
    background: rgba(0, 102, 204, 0.05);
    border: 1px solid rgba(0, 102, 204, 0.1);
}

.log-item {
    padding: 0.75rem;
    border-radius: 8px;
    background: rgba(0, 0, 0, 0.02);
    border-left: 4px solid transparent;
    transition: all 0.3s ease;
}

.log-item:hover {
    background: rgba(0, 102, 204, 0.05);
    border-left-color: var(--primary-color);
}

.log-level .badge {
    min-width: 80px;
}

.logs-container {
    max-height: 400px;
    overflow-y: auto;
}

.form-check-label {
    cursor: pointer;
}

.nav-tabs .nav-link {
    color: var(--secondary-color);
}

.nav-tabs .nav-link.active {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
    color: white;
}
</style>

<script>
// Gestion des paramètres
function saveSettings(formId) {
    const form = document.getElementById(formId);
    const formData = new FormData(form);
    
    fetch('../api/settings.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Paramètres sauvegardés avec succès', 'success');
        } else {
            showAlert('Erreur: ' + data.error, 'danger');
        }
    });
}

// Configuration des formulaires
document.querySelectorAll('form[id$="SettingsForm"]').forEach(form => {
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        saveSettings(this.id);
    });
});

// Actions système
function createBackup() {
    if (confirm('Créer une sauvegarde complète du système ?')) {
        fetch('../api/system.php?action=backup', {method: 'POST'})
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('Sauvegarde créée avec succès', 'success');
            } else {
                showAlert('Erreur: ' + data.error, 'danger');
            }
        });
    }
}

function clearCache() {
    if (confirm('Vider tous les caches système ?')) {
        fetch('../api/system.php?action=clear_cache', {method: 'POST'})
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('Cache vidé avec succès', 'success');
            } else {
                showAlert('Erreur: ' + data.error, 'danger');
            }
        });
    }
}

function optimizeDatabase() {
    if (confirm('Optimiser la base de données ? Cette opération peut prendre du temps.')) {
        fetch('../api/system.php?action=optimize_db', {method: 'POST'})
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('Base de données optimisée', 'success');
            } else {
                showAlert('Erreur: ' + data.error, 'danger');
            }
        });
    }
}

function testEmailConfig() {
    fetch('../api/email.php?action=test', {method: 'POST'})
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Configuration email valide', 'success');
        } else {
            showAlert('Erreur de configuration: ' + data.error, 'danger');
        }
    });
}

function viewAllLogs() {
    window.open('../api/logs.php?action=view', '_blank');
}

function clearLogs() {
    if (confirm('Effacer tous les logs ? Cette action est irréversible.')) {
        fetch('../api/logs.php?action=clear', {method: 'POST'})
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                showAlert('Erreur: ' + data.error, 'danger');
            }
        });
    }
}

function showAlert(message, type) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    document.querySelector('.chart-card').insertAdjacentHTML('afterbegin', alertHtml);
}
</script>