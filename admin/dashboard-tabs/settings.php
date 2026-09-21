<?php
// Paramètres système et configuration
if ($dbConnected) {
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

    $recentLogs = [
        ['level' => 'info', 'message' => 'Nouveau utilisateur inscrit: user@example.com', 'time' => '2024-01-15 14:23:45'],
        ['level' => 'warning', 'message' => 'Tentative de connexion echouee pour admin', 'time' => '2024-01-15 14:20:12'],
        ['level' => 'info', 'message' => 'Piste approuvee: \"Nouveau Son\" par Artist123', 'time' => '2024-01-15 14:15:33'],
        ['level' => 'error', 'message' => 'Erreur de paiement: Transaction #1234', 'time' => '2024-01-15 14:10:22'],
        ['level' => 'info', 'message' => 'Sauvegarde automatique effectuee', 'time' => '2024-01-15 14:00:00']
    ];
}
?>

<section class="space-y-6" id="settingsContainer">
    <div>
        <h2 class="text-xl font-semibold text-text">Parametres & configuration</h2>
        <p class="text-sm text-muted">Gerez la plateforme et les options avancees.</p>
    </div>

    <div class="rounded-3xl border border-white/10 bg-surface/60 p-6 space-y-6">
        <form id="generalSettingsForm" class="space-y-4">
            <h3 class="text-sm font-semibold text-text">Configuration generale</h3>
            <div class="grid gap-4 md:grid-cols-2">
                <label class="text-xs font-semibold text-muted">
                    Nom du site
                    <input type="text" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text" value="<?php echo $settings['site_name']; ?>" name="site_name">
                </label>
                <label class="text-xs font-semibold text-muted">
                    Description
                    <input type="text" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text" value="<?php echo $settings['site_description']; ?>" name="site_description">
                </label>
                <label class="text-xs font-semibold text-muted">
                    Fuseau horaire
                    <select class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text" name="timezone">
                        <option value="Africa/Douala" selected>Africa/Douala (GMT+1)</option>
                        <option value="Africa/Yaounde">Africa/Yaounde (GMT+1)</option>
                        <option value="UTC">UTC (GMT+0)</option>
                    </select>
                </label>
                <label class="text-xs font-semibold text-muted">
                    Devise
                    <select class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text" name="currency">
                        <option value="XAF" selected>Franc CFA (XAF)</option>
                        <option value="USD">Dollar US (USD)</option>
                        <option value="EUR">Euro (EUR)</option>
                    </select>
                </label>
            </div>
            <label class="flex items-center gap-3 text-xs text-muted">
                <input class="h-4 w-4 rounded border-white/20 bg-bg text-accent" type="checkbox" id="maintenanceMode" <?php echo $settings['maintenance_mode'] ? 'checked' : ''; ?>>
                Mode maintenance active
            </label>
            <button type="submit" class="rounded-full bg-accent px-4 py-2 text-xs font-semibold text-white">Sauvegarder</button>
        </form>

        <div class="h-px bg-white/10"></div>

        <form id="userSettingsForm" class="space-y-4">
            <h3 class="text-sm font-semibold text-text">Utilisateurs</h3>
            <div class="grid gap-4 md:grid-cols-2">
                <label class="flex items-center gap-3 text-xs text-muted">
                    <input class="h-4 w-4 rounded border-white/20 bg-bg text-accent" type="checkbox" id="userRegistration" <?php echo $settings['user_registration'] ? 'checked' : ''; ?>>
                    Autoriser les inscriptions
                </label>
                <label class="flex items-center gap-3 text-xs text-muted">
                    <input class="h-4 w-4 rounded border-white/20 bg-bg text-accent" type="checkbox" id="emailVerification" <?php echo $settings['email_verification'] ? 'checked' : ''; ?>>
                    Verification email obligatoire
                </label>
                <label class="flex items-center gap-3 text-xs text-muted">
                    <input class="h-4 w-4 rounded border-white/20 bg-bg text-accent" type="checkbox" id="socialLogin" <?php echo $settings['social_login'] ? 'checked' : ''; ?>>
                    Connexion via reseaux sociaux
                </label>
                <label class="flex items-center gap-3 text-xs text-muted">
                    <input class="h-4 w-4 rounded border-white/20 bg-bg text-accent" type="checkbox" id="autoApproval" <?php echo $settings['auto_approval'] ? 'checked' : ''; ?>>
                    Approbation automatique
                </label>
            </div>
            <button type="submit" class="rounded-full bg-accent px-4 py-2 text-xs font-semibold text-white">Sauvegarder</button>
        </form>

        <div class="h-px bg-white/10"></div>

        <form id="musicSettingsForm" class="space-y-4">
            <h3 class="text-sm font-semibold text-text">Musique</h3>
            <div class="grid gap-4 md:grid-cols-2">
                <label class="text-xs font-semibold text-muted">
                    Taille max upload
                    <select class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text" name="max_upload_size">
                        <option value="50MB" selected>50 MB</option>
                        <option value="100MB">100 MB</option>
                        <option value="200MB">200 MB</option>
                    </select>
                </label>
                <div class="text-xs font-semibold text-muted">
                    Formats autorises
                    <div class="mt-2 space-y-2 text-xs text-muted">
                        <label class="flex items-center gap-2"><input class="h-4 w-4 rounded border-white/20 bg-bg text-accent" type="checkbox" checked> MP3</label>
                        <label class="flex items-center gap-2"><input class="h-4 w-4 rounded border-white/20 bg-bg text-accent" type="checkbox" checked> WAV</label>
                        <label class="flex items-center gap-2"><input class="h-4 w-4 rounded border-white/20 bg-bg text-accent" type="checkbox" checked> FLAC</label>
                    </div>
                </div>
            </div>
            <label class="flex items-center gap-3 text-xs text-muted">
                <input class="h-4 w-4 rounded border-white/20 bg-bg text-accent" type="checkbox" id="contentModeration" <?php echo $settings['content_moderation'] ? 'checked' : ''; ?>>
                Moderation automatique activee
            </label>
            <button type="submit" class="rounded-full bg-accent px-4 py-2 text-xs font-semibold text-white">Sauvegarder</button>
        </form>

        <div class="h-px bg-white/10"></div>

        <form id="paymentSettingsForm" class="space-y-4">
            <h3 class="text-sm font-semibold text-text">Paiements</h3>
            <div class="grid gap-4 md:grid-cols-2">
                <label class="text-xs font-semibold text-muted">
                    Taux de commission (%)
                    <input type="number" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text" value="<?php echo $settings['commission_rate']; ?>" min="0" max="50" step="0.1" name="commission_rate">
                </label>
                <label class="text-xs font-semibold text-muted">
                    Seuil minimum (XAF)
                    <input type="number" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text" value="<?php echo $settings['minimum_payout']; ?>" min="1000" step="1000" name="minimum_payout">
                </label>
            </div>
            <button type="submit" class="rounded-full bg-accent px-4 py-2 text-xs font-semibold text-white">Sauvegarder</button>
        </form>

        <div class="h-px bg-white/10"></div>

        <form id="emailSettingsForm" class="space-y-4">
            <h3 class="text-sm font-semibold text-text">Email</h3>
            <div class="grid gap-4 md:grid-cols-2">
                <label class="text-xs font-semibold text-muted">
                    Serveur SMTP
                    <input type="text" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text" value="<?php echo $settings['smtp_host']; ?>" name="smtp_host">
                </label>
                <label class="text-xs font-semibold text-muted">
                    Port SMTP
                    <input type="number" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text" value="<?php echo $settings['smtp_port']; ?>" name="smtp_port">
                </label>
                <label class="text-xs font-semibold text-muted">
                    Utilisateur SMTP
                    <input type="email" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text" value="<?php echo $settings['smtp_username']; ?>" name="smtp_username">
                </label>
                <label class="text-xs font-semibold text-muted">
                    Mot de passe SMTP
                    <input type="password" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text" placeholder="••••••••" name="smtp_password">
                </label>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-semibold text-text" onclick="testEmailConfig()">Tester</button>
                <button type="submit" class="rounded-full bg-accent px-4 py-2 text-xs font-semibold text-white">Sauvegarder</button>
            </div>
        </form>
    </div>

    <div class="rounded-3xl border border-white/10 bg-surface/60 p-6 space-y-4">
        <h3 class="text-sm font-semibold text-text">Systeme</h3>
        <div class="grid gap-4 md:grid-cols-2">
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4 text-xs text-muted">
                <p class="text-sm font-semibold text-text">Infos serveur</p>
                <div class="mt-3 space-y-2">
                    <div>PHP: <?php echo $systemStats['php_version']; ?></div>
                    <div>MySQL: <?php echo $systemStats['mysql_version']; ?></div>
                    <div>Memoire: <?php echo $systemStats['server_memory']; ?></div>
                    <div>Upload max: <?php echo $systemStats['upload_max_filesize']; ?></div>
                </div>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4 text-xs text-muted">
                <p class="text-sm font-semibold text-text">Ressources</p>
                <div class="mt-3 space-y-3">
                    <div>
                        <div class="flex justify-between">
                            <span>Espace disque</span>
                            <span><?php echo $systemStats['disk_usage']; ?></span>
                        </div>
                        <div class="mt-2 h-2 w-full rounded-full bg-white/10">
                            <div class="h-2 w-1/4 rounded-full bg-amber-400"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between">
                            <span>Bande passante</span>
                            <span><?php echo $systemStats['bandwidth_usage']; ?></span>
                        </div>
                        <div class="mt-2 h-2 w-full rounded-full bg-white/10">
                            <div class="h-2 w-2/5 rounded-full bg-sky-400"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between">
                            <span>Base de donnees</span>
                            <span><?php echo $systemStats['database_size']; ?></span>
                        </div>
                        <div class="mt-2 h-2 w-full rounded-full bg-white/10">
                            <div class="h-2 w-1/6 rounded-full bg-emerald-400"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="flex flex-wrap gap-2 text-xs">
            <button class="rounded-full border border-white/10 bg-white/5 px-4 py-2 text-text" onclick="createBackup()">Sauvegarde</button>
            <button class="rounded-full border border-white/10 bg-white/5 px-4 py-2 text-text" onclick="clearCache()">Vider cache</button>
            <button class="rounded-full border border-white/10 bg-white/5 px-4 py-2 text-text" onclick="optimizeDatabase()">Optimiser BDD</button>
            <button class="rounded-full border border-white/10 bg-white/5 px-4 py-2 text-text" onclick="viewAllLogs()">Logs</button>
        </div>
    </div>

    <div class="rounded-3xl border border-white/10 bg-surface/60 p-6">
        <h3 class="text-sm font-semibold text-text">Logs recents</h3>
        <div class="mt-4 space-y-3 text-xs text-muted">
            <?php foreach ($recentLogs as $log): ?>
                <?php
                $levelClass = match($log['level']) {
                    'error' => 'border-rose-400/30 bg-rose-400/10 text-rose-200',
                    'warning' => 'border-amber-400/30 bg-amber-400/10 text-amber-200',
                    'info' => 'border-sky-400/30 bg-sky-400/10 text-sky-200',
                    default => 'border-white/10 bg-white/5 text-muted'
                };
                ?>
                <div class="flex items-center justify-between rounded-2xl border px-3 py-2 <?php echo $levelClass; ?>">
                    <span><?php echo htmlspecialchars($log['message']); ?></span>
                    <span class="text-[10px]"><?php echo $log['time']; ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<script>
function saveSettings(formId) {
    var form = document.getElementById(formId);
    var formData = new FormData(form);
    fetch('../api/settings.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('Parametres sauvegardes avec succes', 'success');
            } else {
                showAlert('Erreur: ' + data.error, 'danger');
            }
        });
}

document.querySelectorAll('form[id$="SettingsForm"]').forEach(function(form) {
    form.addEventListener('submit', function(event) {
        event.preventDefault();
        saveSettings(this.id);
    });
});

function createBackup() {
    if (confirm('Creer une sauvegarde complete ?')) {
        fetch('../api/system.php?action=backup', { method: 'POST' })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Sauvegarde creee', 'success');
                } else {
                    showAlert('Erreur: ' + data.error, 'danger');
                }
            });
    }
}

function clearCache() {
    if (confirm('Vider tous les caches ?')) {
        fetch('../api/system.php?action=clear_cache', { method: 'POST' })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Cache vide', 'success');
                } else {
                    showAlert('Erreur: ' + data.error, 'danger');
                }
            });
    }
}

function optimizeDatabase() {
    if (confirm('Optimiser la base de donnees ?')) {
        fetch('../api/system.php?action=optimize_db', { method: 'POST' })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Base de donnees optimisee', 'success');
                } else {
                    showAlert('Erreur: ' + data.error, 'danger');
                }
            });
    }
}

function testEmailConfig() {
    fetch('../api/email.php?action=test', { method: 'POST' })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('Configuration email valide', 'success');
            } else {
                showAlert('Erreur: ' + data.error, 'danger');
            }
        });
}

function viewAllLogs() {
    window.open('../api/logs.php?action=view', '_blank');
}

function showAlert(message, type) {
    var container = document.getElementById('settingsContainer');
    if (!container) {
        return;
    }
    var alert = document.createElement('div');
    alert.className = 'alert alert-' + type;
    alert.innerHTML = '<i class="fas fa-info-circle"></i><span>' + message + '</span><button class="btn-close" type="button" data-alert-close></button>';
    container.insertAdjacentElement('afterbegin', alert);
}
</script>
