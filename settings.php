<?php
/**
 * Paramètres - Tchadok Platform
 * Gestion des paramètres de compte et préférences
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    header('Location: ' . SITE_URL . '/login.php?redirect=settings');
    exit();
}

$pageTitle = 'Paramètres';
$pageDescription = 'Gérez vos paramètres de compte';

$user = getCurrentUser();
$success = '';
$error = '';

// Traiter la modification du mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $error = 'Tous les champs sont obligatoires.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Les nouveaux mots de passe ne correspondent pas.';
    } elseif (strlen($newPassword) < 8) {
        $error = 'Le mot de passe doit contenir au moins 8 caractères.';
    } else {
        try {
            $dbInstance = TchadokDatabase::getInstance();
            $db = $dbInstance->getConnection();

            $userId = $_SESSION['user_id'];

            // Vérifier le mot de passe actuel
            $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $userPassword = $stmt->fetchColumn();

            if (!verifyPassword($currentPassword, $userPassword)) {
                $error = 'Le mot de passe actuel est incorrect.';
            } else {
                // Mettre à jour le mot de passe
                $newPasswordHash = hashPassword($newPassword);

                $stmt = $db->prepare("
                    UPDATE users
                    SET password = ?,
                        password_hash = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");

                $stmt->execute([$newPasswordHash, $newPasswordHash, $userId]);

                $success = '✅ Mot de passe modifié avec succès !';
            }

        } catch (Exception $e) {
            $error = 'Une erreur est survenue lors de la modification du mot de passe.';
        }
    }
}

// Traiter les paramètres de notification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_notifications') {
    // À implémenter avec une table de préférences
    $success = '✅ Paramètres de notification mis à jour !';
}

include 'includes/header.php';
?>

<div class="settings-container">
    <!-- Header -->
    <section class="page-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-12">
                    <div class="d-flex align-items-center">
                        <a href="<?php echo SITE_URL; ?>/user-dashboard.php" class="btn btn-outline-secondary me-3">
                            <i class="fas fa-arrow-left"></i>
                        </a>
                        <div class="header-icon-lg">
                            <i class="fas fa-cog"></i>
                        </div>
                        <div>
                            <h1 class="mb-2">Paramètres</h1>
                            <p class="text-muted mb-0">
                                <i class="fas fa-user me-2"></i>
                                <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contenu -->
    <section class="settings-content py-5">
        <div class="container">
            <div class="row">
                <!-- Menu Latéral -->
                <div class="col-lg-3 mb-4">
                    <div class="settings-nav">
                        <a href="#security" class="settings-nav-item active">
                            <i class="fas fa-shield-alt"></i>
                            <span>Sécurité</span>
                        </a>
                        <a href="#notifications" class="settings-nav-item">
                            <i class="fas fa-bell"></i>
                            <span>Notifications</span>
                        </a>
                        <a href="#privacy" class="settings-nav-item">
                            <i class="fas fa-lock"></i>
                            <span>Confidentialité</span>
                        </a>
                        <a href="#account" class="settings-nav-item">
                            <i class="fas fa-user-circle"></i>
                            <span>Compte</span>
                        </a>
                    </div>
                </div>

                <!-- Contenu Principal -->
                <div class="col-lg-9">
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-modern">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo $success; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-modern">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Sécurité -->
                    <div id="security" class="settings-section">
                        <div class="section-card">
                            <div class="section-header">
                                <h4>
                                    <i class="fas fa-shield-alt me-2 text-primary"></i>
                                    Sécurité
                                </h4>
                                <p class="text-muted">Gérez la sécurité de votre compte</p>
                            </div>

                            <!-- Changer le Mot de Passe -->
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h5>Changer le Mot de Passe</h5>
                                    <p class="text-muted">Modifiez votre mot de passe régulièrement pour sécuriser votre compte</p>
                                </div>
                                <button class="btn btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#passwordForm">
                                    <i class="fas fa-key me-2"></i>Modifier
                                </button>
                            </div>

                            <div class="collapse mt-3" id="passwordForm">
                                <form method="POST" action="" class="password-form">
                                    <input type="hidden" name="action" value="change_password">

                                    <div class="form-group mb-3">
                                        <label for="current_password" class="form-label">Mot de passe actuel</label>
                                        <input type="password"
                                               class="form-control form-control-modern"
                                               id="current_password"
                                               name="current_password"
                                               required>
                                    </div>

                                    <div class="form-group mb-3">
                                        <label for="new_password" class="form-label">Nouveau mot de passe</label>
                                        <input type="password"
                                               class="form-control form-control-modern"
                                               id="new_password"
                                               name="new_password"
                                               minlength="8"
                                               required>
                                        <small class="text-muted">Minimum 8 caractères</small>
                                    </div>

                                    <div class="form-group mb-3">
                                        <label for="confirm_password" class="form-label">Confirmer le nouveau mot de passe</label>
                                        <input type="password"
                                               class="form-control form-control-modern"
                                               id="confirm_password"
                                               name="confirm_password"
                                               required>
                                    </div>

                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Enregistrer
                                    </button>
                                </form>
                            </div>

                            <!-- Authentification à Deux Facteurs -->
                            <div class="setting-item mt-4">
                                <div class="setting-info">
                                    <h5>Authentification à Deux Facteurs</h5>
                                    <p class="text-muted">Ajoutez une couche de sécurité supplémentaire</p>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="twoFactorSwitch" disabled>
                                    <label class="form-check-label text-muted" for="twoFactorSwitch">
                                        Prochainement
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Notifications -->
                    <div id="notifications" class="settings-section mt-4">
                        <div class="section-card">
                            <div class="section-header">
                                <h4>
                                    <i class="fas fa-bell me-2 text-success"></i>
                                    Notifications
                                </h4>
                                <p class="text-muted">Gérez vos préférences de notification</p>
                            </div>

                            <form method="POST" action="">
                                <input type="hidden" name="action" value="update_notifications">

                                <div class="setting-item">
                                    <div class="setting-info">
                                        <h5>Notifications par Email</h5>
                                        <p class="text-muted">Recevez des mises à jour par email</p>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="emailNotif" checked>
                                    </div>
                                </div>

                                <div class="setting-item mt-3">
                                    <div class="setting-info">
                                        <h5>Nouvelles Sorties</h5>
                                        <p class="text-muted">Soyez notifié des nouvelles sorties musicales</p>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="releaseNotif" checked>
                                    </div>
                                </div>

                                <div class="setting-item mt-3">
                                    <div class="setting-info">
                                        <h5>Activité Sociale</h5>
                                        <p class="text-muted">Notifications sur les interactions sociales</p>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="socialNotif">
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-success mt-4">
                                    <i class="fas fa-save me-2"></i>Enregistrer les Préférences
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Confidentialité -->
                    <div id="privacy" class="settings-section mt-4">
                        <div class="section-card">
                            <div class="section-header">
                                <h4>
                                    <i class="fas fa-lock me-2 text-warning"></i>
                                    Confidentialité
                                </h4>
                                <p class="text-muted">Contrôlez la visibilité de votre profil</p>
                            </div>

                            <div class="setting-item">
                                <div class="setting-info">
                                    <h5>Profil Public</h5>
                                    <p class="text-muted">Permettre aux autres de voir votre profil</p>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="publicProfile" checked>
                                </div>
                            </div>

                            <div class="setting-item mt-3">
                                <div class="setting-info">
                                    <h5>Afficher l'Historique d'Écoute</h5>
                                    <p class="text-muted">Partager ce que vous écoutez</p>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="showHistory" checked>
                                </div>
                            </div>

                            <div class="setting-item mt-3">
                                <div class="setting-info">
                                    <h5>Playlists Publiques</h5>
                                    <p class="text-muted">Rendre vos playlists visibles par défaut</p>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="publicPlaylists" checked>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Compte -->
                    <div id="account" class="settings-section mt-4">
                        <div class="section-card">
                            <div class="section-header">
                                <h4>
                                    <i class="fas fa-user-circle me-2 text-danger"></i>
                                    Compte
                                </h4>
                                <p class="text-muted">Gérez votre compte Tchadok</p>
                            </div>

                            <div class="setting-item">
                                <div class="setting-info">
                                    <h5>Type de Compte</h5>
                                    <p class="text-muted">
                                        <?php echo $user['premium_status'] ? 'Premium' : 'Gratuit'; ?>
                                    </p>
                                </div>
                                <?php if (!$user['premium_status']): ?>
                                <a href="<?php echo SITE_URL; ?>/premium.php" class="btn btn-warning">
                                    <i class="fas fa-crown me-2"></i>Passer à Premium
                                </a>
                                <?php else: ?>
                                <span class="badge bg-warning text-dark">
                                    <i class="fas fa-crown me-1"></i>Premium
                                </span>
                                <?php endif; ?>
                            </div>

                            <div class="setting-item mt-4 pt-4 border-top">
                                <div class="setting-info">
                                    <h5 class="text-danger">Zone Dangereuse</h5>
                                    <p class="text-muted">Actions irréversibles sur votre compte</p>
                                </div>
                            </div>

                            <button class="btn btn-outline-danger mt-3" disabled>
                                <i class="fas fa-trash me-2"></i>Supprimer mon Compte
                            </button>
                            <small class="d-block text-muted mt-2">
                                Contactez le support pour supprimer votre compte
                            </small>
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

.settings-container {
    background: #f5f7fa;
    min-height: 100vh;
    padding-bottom: 3rem;
}

/* Page Header */
.page-header {
    background: white;
    padding: 2rem 0;
    margin-bottom: 2rem;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    margin-top: 80px;
}

.header-icon-lg {
    width: 70px;
    height: 70px;
    background: linear-gradient(135deg, var(--gris-harmattan), #1a252f);
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 2rem;
    margin-right: 1.5rem;
    box-shadow: 0 5px 20px rgba(44, 62, 80, 0.3);
}

.page-header h1 {
    color: var(--gris-harmattan);
    font-weight: 700;
    font-size: 2rem;
}

/* Settings Navigation */
.settings-nav {
    background: white;
    border-radius: 15px;
    padding: 1rem;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
    position: sticky;
    top: 100px;
}

.settings-nav-item {
    display: flex;
    align-items: center;
    padding: 1rem;
    color: #6c757d;
    text-decoration: none;
    border-radius: 10px;
    transition: all 0.3s ease;
    margin-bottom: 0.5rem;
}

.settings-nav-item:last-child {
    margin-bottom: 0;
}

.settings-nav-item:hover {
    background: #f8f9fa;
    color: var(--bleu-tchadien);
}

.settings-nav-item.active {
    background: var(--bleu-tchadien);
    color: white;
}

.settings-nav-item i {
    font-size: 1.2rem;
    margin-right: 1rem;
    width: 25px;
}

/* Section Card */
.section-card {
    background: white;
    padding: 2rem;
    border-radius: 20px;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
}

.section-header {
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #f0f0f0;
}

.section-header h4 {
    color: var(--gris-harmattan);
    font-weight: 600;
    margin-bottom: 0.5rem;
}

/* Setting Item */
.setting-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    background: #f8f9fa;
    border-radius: 12px;
}

.setting-info h5 {
    color: var(--gris-harmattan);
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.setting-info p {
    margin: 0;
    font-size: 0.9rem;
}

/* Form Switch */
.form-check-input {
    width: 3rem;
    height: 1.5rem;
    cursor: pointer;
}

.form-check-input:checked {
    background-color: var(--vert-savane);
    border-color: var(--vert-savane);
}

/* Form Controls */
.form-control-modern {
    border: 2px solid #e9ecef;
    border-radius: 12px;
    padding: 0.875rem 1.25rem;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.form-control-modern:focus {
    border-color: var(--bleu-tchadien);
    box-shadow: 0 0 0 0.2rem rgba(0, 102, 204, 0.1);
}

.password-form {
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    border: 2px solid #e9ecef;
}

/* Alerts */
.alert-modern {
    border-radius: 12px;
    border: none;
    padding: 1.25rem;
    margin-bottom: 2rem;
}

/* Responsive */
@media (max-width: 991px) {
    .page-header {
        margin-top: 70px;
    }

    .settings-nav {
        position: relative;
        top: 0;
    }

    .setting-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
}
</style>

<script>
// Smooth scroll pour les ancres
document.querySelectorAll('.settings-nav-item').forEach(item => {
    item.addEventListener('click', function(e) {
        e.preventDefault();

        // Remove active class from all items
        document.querySelectorAll('.settings-nav-item').forEach(link => {
            link.classList.remove('active');
        });

        // Add active class to clicked item
        this.classList.add('active');

        // Scroll to section
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>
