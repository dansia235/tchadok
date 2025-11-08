<?php
/**
 * Page de paramètres de sécurité - Tchadok Platform
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/advanced-auth.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    header('Location: ' . SITE_URL . '/login.php?redirect=security-settings');
    exit();
}

$pageTitle = 'Paramètres de Sécurité';
$pageDescription = 'Gérez la sécurité de votre compte Tchadok';

$userId = $_SESSION['user_id'];
$user2FA = get2FASettings($userId);

// Traitement des actions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'enable_2fa_totp':
            $result = enable2FA($userId, 'totp');
            if ($result['success']) {
                $_SESSION['2fa_setup'] = $result;
                $message = 'Authentification à deux facteurs configurée avec succès';
                $messageType = 'success';
            } else {
                $message = $result['error'];
                $messageType = 'error';
            }
            break;
            
        case 'enable_2fa_sms':
            $phone = $_POST['phone'] ?? '';
            $result = enable2FA($userId, 'sms', $phone);
            if ($result['success']) {
                $message = 'SMS 2FA activé avec succès';
                $messageType = 'success';
            } else {
                $message = $result['error'];
                $messageType = 'error';
            }
            break;
            
        case 'change_password':
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            
            // Validation
            if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                $message = 'Tous les champs sont requis';
                $messageType = 'error';
            } elseif ($newPassword !== $confirmPassword) {
                $message = 'Les nouveaux mots de passe ne correspondent pas';
                $messageType = 'error';
            } else {
                $strength = checkPasswordStrength($newPassword);
                if ($strength['score'] < 50) {
                    $message = 'Mot de passe trop faible: ' . implode(', ', $strength['feedback']);
                    $messageType = 'error';
                } else {
                    // Simuler le changement de mot de passe
                    $message = 'Mot de passe modifié avec succès';
                    $messageType = 'success';
                }
            }
            break;
    }
}

include 'includes/header.php';
?>

<div class="security-settings">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <!-- Header -->
                <div class="d-flex align-items-center mb-4">
                    <a href="<?php echo SITE_URL; ?>/user-dashboard.php" class="btn btn-outline-secondary me-3">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <div>
                        <h1 class="h3 mb-1">Paramètres de Sécurité</h1>
                        <p class="text-muted mb-0">Protégez votre compte avec des mesures de sécurité avancées</p>
                    </div>
                </div>

                <!-- Messages -->
                <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Left Column -->
                    <div class="col-lg-8">
                        <!-- Security Overview -->
                        <div class="security-card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-shield-alt me-2"></i>
                                    État de la sécurité
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="security-score mb-4">
                                    <?php 
                                    $securityScore = 60; // Score de base
                                    if ($user2FA['enabled']) $securityScore += 30;
                                    if (isset($_SESSION['strong_password'])) $securityScore += 10;
                                    $securityScore = min(100, $securityScore);
                                    ?>
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="score-circle me-3">
                                            <div class="score-text"><?php echo $securityScore; ?>%</div>
                                        </div>
                                        <div>
                                            <h6 class="mb-1">Score de sécurité: 
                                                <?php if ($securityScore >= 80): ?>
                                                    <span class="text-success">Excellent</span>
                                                <?php elseif ($securityScore >= 60): ?>
                                                    <span class="text-warning">Bon</span>
                                                <?php else: ?>
                                                    <span class="text-danger">À améliorer</span>
                                                <?php endif; ?>
                                            </h6>
                                            <p class="text-muted mb-0">Votre compte est 
                                                <?php echo $securityScore >= 80 ? 'bien' : 'partiellement'; ?> protégé
                                            </p>
                                        </div>
                                    </div>
                                    <div class="progress" style="height: 10px;">
                                        <div class="progress-bar <?php echo $securityScore >= 80 ? 'bg-success' : ($securityScore >= 60 ? 'bg-warning' : 'bg-danger'); ?>" 
                                             role="progressbar" style="width: <?php echo $securityScore; ?>%"></div>
                                    </div>
                                </div>

                                <div class="security-items">
                                    <div class="security-item">
                                        <i class="fas fa-check-circle text-success"></i>
                                        <span>Mot de passe sécurisé</span>
                                    </div>
                                    <div class="security-item">
                                        <i class="fas fa-<?php echo $user2FA['enabled'] ? 'check-circle text-success' : 'times-circle text-danger'; ?>"></i>
                                        <span>Authentification à deux facteurs</span>
                                    </div>
                                    <div class="security-item">
                                        <i class="fas fa-check-circle text-success"></i>
                                        <span>Email vérifié</span>
                                    </div>
                                    <div class="security-item">
                                        <i class="fas fa-times-circle text-warning"></i>
                                        <span>Connexions d'appareils de confiance</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Two-Factor Authentication -->
                        <div class="security-card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-lock me-2"></i>
                                    Authentification à deux facteurs (2FA)
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (!$user2FA['enabled']): ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    La 2FA n'est pas activée. Activez-la pour sécuriser davantage votre compte.
                                </div>

                                <div class="row g-3">
                                    <!-- Google Authenticator -->
                                    <div class="col-md-6">
                                        <div class="auth-method">
                                            <div class="method-icon">
                                                <i class="fas fa-mobile-alt"></i>
                                            </div>
                                            <h6>Application d'authentification</h6>
                                            <p>Utilisez Google Authenticator ou une app similaire</p>
                                            <button class="btn btn-primary btn-sm" onclick="enable2FA('totp')">
                                                Configurer
                                            </button>
                                        </div>
                                    </div>

                                    <!-- SMS -->
                                    <div class="col-md-6">
                                        <div class="auth-method">
                                            <div class="method-icon">
                                                <i class="fas fa-sms"></i>
                                            </div>
                                            <h6>SMS</h6>
                                            <p>Recevez des codes par SMS sur votre téléphone</p>
                                            <button class="btn btn-outline-primary btn-sm" onclick="enable2FA('sms')">
                                                Configurer
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle me-2"></i>
                                    2FA activée via <?php echo ucfirst($user2FA['method']); ?>
                                </div>

                                <div class="d-flex gap-2">
                                    <button class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-cog me-1"></i> Gérer la 2FA
                                    </button>
                                    <button class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-download me-1"></i> Codes de récupération
                                    </button>
                                    <button class="btn btn-outline-danger btn-sm">
                                        <i class="fas fa-times me-1"></i> Désactiver
                                    </button>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Password Change -->
                        <div class="security-card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-key me-2"></i>
                                    Changer le mot de passe
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" id="passwordForm">
                                    <input type="hidden" name="action" value="change_password">
                                    
                                    <div class="mb-3">
                                        <label for="current_password" class="form-label">Mot de passe actuel</label>
                                        <input type="password" class="form-control" id="current_password" 
                                               name="current_password" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">Nouveau mot de passe</label>
                                        <input type="password" class="form-control" id="new_password" 
                                               name="new_password" required>
                                        <div class="password-strength mt-2" style="display: none;">
                                            <div class="d-flex justify-content-between mb-1">
                                                <small>Force du mot de passe:</small>
                                                <small class="strength-text"></small>
                                            </div>
                                            <div class="progress" style="height: 5px;">
                                                <div class="progress-bar strength-bar" role="progressbar"></div>
                                            </div>
                                            <ul class="strength-feedback mt-2" style="font-size: 0.8rem;"></ul>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirmer le nouveau mot de passe</label>
                                        <input type="password" class="form-control" id="confirm_password" 
                                               name="confirm_password" required>
                                    </div>

                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>
                                        Changer le mot de passe
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div class="col-lg-4">
                        <!-- Recent Activity -->
                        <div class="security-card mb-4">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="fas fa-history me-2"></i>
                                    Activité récente
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="activity-list">
                                    <?php 
                                    $activities = [
                                        ['action' => 'Connexion réussie', 'location' => 'N\'Djamena', 'time' => 'Il y a 2h', 'icon' => 'sign-in-alt', 'color' => 'success'],
                                        ['action' => 'Mot de passe modifié', 'location' => 'N\'Djamena', 'time' => 'Il y a 3 jours', 'icon' => 'key', 'color' => 'info'],
                                        ['action' => 'Connexion échouée', 'location' => 'Abéché', 'time' => 'Il y a 1 semaine', 'icon' => 'exclamation-triangle', 'color' => 'warning'],
                                        ['action' => 'Email vérifié', 'location' => 'N\'Djamena', 'time' => 'Il y a 2 semaines', 'icon' => 'envelope-check', 'color' => 'success']
                                    ];
                                    foreach ($activities as $activity):
                                    ?>
                                    <div class="activity-item">
                                        <i class="fas fa-<?php echo $activity['icon']; ?> text-<?php echo $activity['color']; ?>"></i>
                                        <div class="activity-details">
                                            <div class="activity-action"><?php echo $activity['action']; ?></div>
                                            <small class="text-muted"><?php echo $activity['location']; ?> • <?php echo $activity['time']; ?></small>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <a href="#" class="btn btn-sm btn-outline-primary w-100 mt-3">
                                    Voir tout l'historique
                                </a>
                            </div>
                        </div>

                        <!-- Connected Devices -->
                        <div class="security-card mb-4">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="fas fa-devices me-2"></i>
                                    Appareils connectés
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="device-list">
                                    <div class="device-item">
                                        <i class="fas fa-laptop text-primary"></i>
                                        <div class="device-info">
                                            <div class="device-name">Chrome sur Windows</div>
                                            <small class="text-success">
                                                <i class="fas fa-circle"></i> Actuel
                                            </small>
                                        </div>
                                        <button class="btn btn-sm btn-link text-danger">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                    
                                    <div class="device-item">
                                        <i class="fas fa-mobile-alt text-info"></i>
                                        <div class="device-info">
                                            <div class="device-name">Safari sur iPhone</div>
                                            <small class="text-muted">Il y a 2 jours</small>
                                        </div>
                                        <button class="btn btn-sm btn-link text-danger">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                                <button class="btn btn-sm btn-outline-danger w-100 mt-3">
                                    <i class="fas fa-sign-out-alt me-1"></i>
                                    Déconnecter tous les appareils
                                </button>
                            </div>
                        </div>

                        <!-- Security Tips -->
                        <div class="security-card">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="fas fa-lightbulb me-2"></i>
                                    Conseils de sécurité
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="tips-list">
                                    <div class="tip-item">
                                        <i class="fas fa-shield-alt text-success"></i>
                                        <span>Utilisez un mot de passe unique et fort</span>
                                    </div>
                                    <div class="tip-item">
                                        <i class="fas fa-lock text-primary"></i>
                                        <span>Activez la 2FA pour plus de sécurité</span>
                                    </div>
                                    <div class="tip-item">
                                        <i class="fas fa-eye text-warning"></i>
                                        <span>Vérifiez régulièrement votre activité</span>
                                    </div>
                                    <div class="tip-item">
                                        <i class="fas fa-wifi text-danger"></i>
                                        <span>Évitez les réseaux WiFi publics</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 2FA Setup Modals -->
<!-- TOTP Setup Modal -->
<div class="modal fade" id="totpSetupModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Configuration de l'authentification</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-center">
                    <h6>Étape 1: Installez une application</h6>
                    <p>Téléchargez Google Authenticator ou Microsoft Authenticator</p>
                    
                    <h6 class="mt-4">Étape 2: Scannez le QR Code</h6>
                    <div class="qr-code mb-3">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=otpauth://totp/Tchadok:user@example.com?secret=JBSWY3DPEHPK3PXP&issuer=Tchadok" 
                             alt="QR Code" class="img-fluid">
                    </div>
                    
                    <div class="manual-entry">
                        <small>Code manuel: <code>JBSWY3DPEHPK3PXP</code></small>
                    </div>
                    
                    <h6 class="mt-4">Étape 3: Entrez le code</h6>
                    <form method="POST">
                        <input type="hidden" name="action" value="enable_2fa_totp">
                        <div class="mb-3">
                            <input type="text" class="form-control text-center" 
                                   placeholder="000000" maxlength="6" name="totp_code" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Activer la 2FA</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SMS Setup Modal -->
<div class="modal fade" id="smsSetupModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Configuration SMS 2FA</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="enable_2fa_sms">
                    <div class="mb-3">
                        <label for="phone" class="form-label">Numéro de téléphone</label>
                        <input type="tel" class="form-control" id="phone" name="phone" 
                               placeholder="+235 XX XX XX XX" required>
                        <small class="text-muted">Format tchadien requis</small>
                    </div>
                    <button type="submit" class="btn btn-primary">Configurer SMS 2FA</button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.security-settings {
    background: #f8f9fa;
    min-height: calc(100vh - 200px);
}

.security-card {
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    overflow: hidden;
}

.security-card .card-header {
    background: white;
    border-bottom: 1px solid #f0f0f0;
    padding: 1.25rem;
}

.security-card .card-body {
    padding: 1.5rem;
}

.score-circle {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: conic-gradient(#28a745 0deg <?php echo $securityScore * 3.6; ?>deg, #e9ecef <?php echo $securityScore * 3.6; ?>deg 360deg);
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}

.score-circle::before {
    content: '';
    position: absolute;
    width: 60px;
    height: 60px;
    background: white;
    border-radius: 50%;
}

.score-text {
    position: relative;
    font-weight: bold;
    font-size: 1.1rem;
    color: #333;
}

.security-items {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
}

.security-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.5rem 0;
}

.security-item i {
    font-size: 1.2rem;
}

.auth-method {
    text-align: center;
    padding: 1.5rem;
    border: 2px solid #f0f0f0;
    border-radius: 10px;
    transition: all 0.3s ease;
}

.auth-method:hover {
    border-color: #007bff;
    transform: translateY(-2px);
}

.method-icon {
    width: 60px;
    height: 60px;
    background: #f8f9fa;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1rem;
    font-size: 1.5rem;
    color: #007bff;
}

.auth-method h6 {
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.auth-method p {
    color: #6c757d;
    font-size: 0.9rem;
    margin-bottom: 1rem;
}

.password-strength .strength-feedback {
    list-style: none;
    padding: 0;
    margin: 0;
}

.password-strength .strength-feedback li {
    font-size: 0.8rem;
    color: #dc3545;
}

.activity-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.75rem 0;
    border-bottom: 1px solid #f0f0f0;
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-item i {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    background: #f8f9fa;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.activity-details {
    flex: 1;
}

.activity-action {
    font-weight: 500;
    margin-bottom: 0.25rem;
}

.device-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.75rem 0;
    border-bottom: 1px solid #f0f0f0;
}

.device-item:last-child {
    border-bottom: none;
}

.device-item i {
    font-size: 1.5rem;
    width: 40px;
    text-align: center;
}

.device-info {
    flex: 1;
}

.device-name {
    font-weight: 500;
    margin-bottom: 0.25rem;
}

.tip-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.5rem 0;
    border-bottom: 1px solid #f0f0f0;
}

.tip-item:last-child {
    border-bottom: none;
}

.tip-item i {
    width: 30px;
    text-align: center;
}

.qr-code {
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 10px;
    display: inline-block;
}

.manual-entry {
    background: #f8f9fa;
    padding: 0.5rem 1rem;
    border-radius: 5px;
    margin: 1rem 0;
}

@media (max-width: 768px) {
    .security-items {
        grid-template-columns: 1fr;
    }
    
    .score-circle {
        width: 60px;
        height: 60px;
    }
    
    .score-circle::before {
        width: 45px;
        height: 45px;
    }
    
    .score-text {
        font-size: 0.9rem;
    }
}
</style>

<script>
function enable2FA(method) {
    if (method === 'totp') {
        new bootstrap.Modal(document.getElementById('totpSetupModal')).show();
    } else if (method === 'sms') {
        new bootstrap.Modal(document.getElementById('smsSetupModal')).show();
    }
}

// Password strength checker
document.getElementById('new_password').addEventListener('input', function() {
    const password = this.value;
    const strengthDiv = document.querySelector('.password-strength');
    
    if (password.length === 0) {
        strengthDiv.style.display = 'none';
        return;
    }
    
    strengthDiv.style.display = 'block';
    
    // Check password strength
    const strength = checkPasswordStrength(password);
    
    const strengthBar = document.querySelector('.strength-bar');
    const strengthText = document.querySelector('.strength-text');
    const feedbackList = document.querySelector('.strength-feedback');
    
    // Update progress bar
    strengthBar.style.width = strength.score + '%';
    strengthBar.className = 'progress-bar';
    
    if (strength.score < 50) {
        strengthBar.classList.add('bg-danger');
        strengthText.textContent = 'Faible';
        strengthText.className = 'strength-text text-danger';
    } else if (strength.score < 75) {
        strengthBar.classList.add('bg-warning');
        strengthText.textContent = 'Moyen';
        strengthText.className = 'strength-text text-warning';
    } else {
        strengthBar.classList.add('bg-success');
        strengthText.textContent = 'Fort';
        strengthText.className = 'strength-text text-success';
    }
    
    // Update feedback
    feedbackList.innerHTML = '';
    strength.feedback.forEach(feedback => {
        const li = document.createElement('li');
        li.textContent = feedback;
        feedbackList.appendChild(li);
    });
});

function checkPasswordStrength(password) {
    let score = 0;
    const feedback = [];
    
    // Length
    if (password.length >= 8) score += 25;
    else feedback.push('Au moins 8 caractères requis');
    
    if (password.length >= 12) score += 10;
    
    // Complexity
    if (/[a-z]/.test(password)) score += 15;
    else feedback.push('Ajouter des lettres minuscules');
    
    if (/[A-Z]/.test(password)) score += 15;
    else feedback.push('Ajouter des lettres majuscules');
    
    if (/[0-9]/.test(password)) score += 15;
    else feedback.push('Ajouter des chiffres');
    
    if (/[^a-zA-Z0-9]/.test(password)) score += 20;
    else feedback.push('Ajouter des caractères spéciaux');
    
    // Common patterns (penalty)
    if (/^(password|123456|qwerty)/i.test(password)) {
        score -= 50;
        feedback.push('Éviter les mots de passe courants');
    }
    
    score = Math.max(0, Math.min(100, score));
    
    return {
        score: score,
        feedback: feedback
    };
}

// Form validation
document.getElementById('passwordForm').addEventListener('submit', function(e) {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if (newPassword !== confirmPassword) {
        e.preventDefault();
        alert('Les nouveaux mots de passe ne correspondent pas');
        return;
    }
    
    const strength = checkPasswordStrength(newPassword);
    if (strength.score < 50) {
        e.preventDefault();
        alert('Le mot de passe est trop faible. Veuillez en choisir un plus fort.');
        return;
    }
});

// Phone number formatting for SMS 2FA
document.getElementById('phone').addEventListener('input', function() {
    let value = this.value.replace(/\D/g, '');
    
    if (value.startsWith('235')) {
        value = '+' + value;
    } else if (value.length === 8 && /^[679]/.test(value)) {
        value = '+235' + value;
    }
    
    this.value = value;
});
</script>

<?php include 'includes/footer.php'; ?>