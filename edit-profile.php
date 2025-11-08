<?php
/**
 * Modifier le Profil - Tchadok Platform
 * Permet aux utilisateurs de modifier leurs informations personnelles
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    header('Location: ' . SITE_URL . '/login.php?redirect=edit-profile');
    exit();
}

$pageTitle = 'Modifier le Profil';
$pageDescription = 'Mettez à jour vos informations personnelles';

$user = getCurrentUser();
$success = '';
$error = '';

// Traiter la soumission du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = sanitizeInput($_POST['first_name'] ?? '');
    $lastName = sanitizeInput($_POST['last_name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $city = sanitizeInput($_POST['city'] ?? '');
    $bio = sanitizeInput($_POST['bio'] ?? '');

    // Validation
    if (empty($firstName) || empty($lastName)) {
        $error = 'Le prénom et le nom sont obligatoires.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Adresse email invalide.';
    } else {
        try {
            $dbInstance = TchadokDatabase::getInstance();
            $db = $dbInstance->getConnection();

            $userId = $_SESSION['user_id'];

            // Vérifier si l'email est déjà utilisé par un autre utilisateur
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $userId]);

            if ($stmt->fetch()) {
                $error = 'Cet email est déjà utilisé par un autre compte.';
            } else {
                // Mettre à jour le profil
                $stmt = $db->prepare("
                    UPDATE users
                    SET first_name = ?,
                        last_name = ?,
                        email = ?,
                        phone = ?,
                        city = ?,
                        bio = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");

                $stmt->execute([
                    $firstName,
                    $lastName,
                    $email,
                    $phone,
                    $city,
                    $bio,
                    $userId
                ]);

                // Mettre à jour la session
                $_SESSION['user_data']['first_name'] = $firstName;
                $_SESSION['user_data']['last_name'] = $lastName;
                $_SESSION['user_data']['email'] = $email;

                // Recharger les données utilisateur
                $user = getCurrentUser();

                $success = '✅ Profil mis à jour avec succès !';
            }

        } catch (Exception $e) {
            $error = 'Une erreur est survenue lors de la mise à jour du profil.';
        }
    }
}

include 'includes/header.php';
?>

<div class="edit-profile-container">
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
                            <i class="fas fa-user-edit"></i>
                        </div>
                        <div>
                            <h1 class="mb-2">Modifier le Profil</h1>
                            <p class="text-muted mb-0">
                                <i class="fas fa-at me-2"></i>
                                <?php echo htmlspecialchars($user['username']); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Formulaire -->
    <section class="form-section py-5">
        <div class="container">
            <div class="row">
                <!-- Colonne Principale -->
                <div class="col-lg-8">
                    <div class="form-card mb-4">
                        <h4 class="mb-4">
                            <i class="fas fa-info-circle me-2 text-primary"></i>
                            Informations Personnelles
                        </h4>

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

                        <form method="POST" action="">
                            <div class="row">
                                <!-- Prénom -->
                                <div class="col-md-6 mb-4">
                                    <label for="first_name" class="form-label">
                                        <i class="fas fa-user me-2"></i>Prénom *
                                    </label>
                                    <input type="text"
                                           class="form-control form-control-modern"
                                           id="first_name"
                                           name="first_name"
                                           value="<?php echo htmlspecialchars($user['first_name']); ?>"
                                           required>
                                </div>

                                <!-- Nom -->
                                <div class="col-md-6 mb-4">
                                    <label for="last_name" class="form-label">
                                        <i class="fas fa-user me-2"></i>Nom *
                                    </label>
                                    <input type="text"
                                           class="form-control form-control-modern"
                                           id="last_name"
                                           name="last_name"
                                           value="<?php echo htmlspecialchars($user['last_name']); ?>"
                                           required>
                                </div>
                            </div>

                            <!-- Email -->
                            <div class="form-group mb-4">
                                <label for="email" class="form-label">
                                    <i class="fas fa-envelope me-2"></i>Email *
                                </label>
                                <input type="email"
                                       class="form-control form-control-modern"
                                       id="email"
                                       name="email"
                                       value="<?php echo htmlspecialchars($user['email']); ?>"
                                       required>
                            </div>

                            <!-- Téléphone -->
                            <div class="form-group mb-4">
                                <label for="phone" class="form-label">
                                    <i class="fas fa-phone me-2"></i>Téléphone
                                </label>
                                <input type="tel"
                                       class="form-control form-control-modern"
                                       id="phone"
                                       name="phone"
                                       placeholder="+235 XX XX XX XX"
                                       value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                            </div>

                            <!-- Ville -->
                            <div class="form-group mb-4">
                                <label for="city" class="form-label">
                                    <i class="fas fa-map-marker-alt me-2"></i>Ville
                                </label>
                                <select class="form-control form-control-modern" id="city" name="city">
                                    <option value="">Choisir une ville...</option>
                                    <option value="N'Djamena" <?php echo ($user['city'] ?? '') === "N'Djamena" ? 'selected' : ''; ?>>N'Djamena</option>
                                    <option value="Moundou" <?php echo ($user['city'] ?? '') === 'Moundou' ? 'selected' : ''; ?>>Moundou</option>
                                    <option value="Sarh" <?php echo ($user['city'] ?? '') === 'Sarh' ? 'selected' : ''; ?>>Sarh</option>
                                    <option value="Abéché" <?php echo ($user['city'] ?? '') === 'Abéché' ? 'selected' : ''; ?>>Abéché</option>
                                    <option value="Kelo" <?php echo ($user['city'] ?? '') === 'Kelo' ? 'selected' : ''; ?>>Kelo</option>
                                    <option value="Koumra" <?php echo ($user['city'] ?? '') === 'Koumra' ? 'selected' : ''; ?>>Koumra</option>
                                    <option value="Pala" <?php echo ($user['city'] ?? '') === 'Pala' ? 'selected' : ''; ?>>Pala</option>
                                    <option value="Am Timan" <?php echo ($user['city'] ?? '') === 'Am Timan' ? 'selected' : ''; ?>>Am Timan</option>
                                    <option value="Bongor" <?php echo ($user['city'] ?? '') === 'Bongor' ? 'selected' : ''; ?>>Bongor</option>
                                    <option value="Mongo" <?php echo ($user['city'] ?? '') === 'Mongo' ? 'selected' : ''; ?>>Mongo</option>
                                    <option value="Doba" <?php echo ($user['city'] ?? '') === 'Doba' ? 'selected' : ''; ?>>Doba</option>
                                </select>
                            </div>

                            <!-- Biographie -->
                            <div class="form-group mb-4">
                                <label for="bio" class="form-label">
                                    <i class="fas fa-pencil-alt me-2"></i>Biographie
                                </label>
                                <textarea class="form-control form-control-modern"
                                          id="bio"
                                          name="bio"
                                          rows="4"
                                          placeholder="Parlez-nous de vous..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                                <small class="text-muted">
                                    <?php
                                    $bioLength = strlen($user['bio'] ?? '');
                                    echo $bioLength;
                                    ?>/500 caractères
                                </small>
                            </div>

                            <!-- Boutons -->
                            <div class="form-actions">
                                <a href="<?php echo SITE_URL; ?>/user-dashboard.php" class="btn btn-outline-secondary btn-lg">
                                    <i class="fas fa-times me-2"></i>Annuler
                                </a>
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save me-2"></i>Enregistrer
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="col-lg-4">
                    <!-- Avatar -->
                    <div class="form-card mb-4">
                        <h5 class="mb-3">
                            <i class="fas fa-camera me-2 text-primary"></i>
                            Photo de Profil
                        </h5>
                        <div class="avatar-upload-section">
                            <div class="avatar-preview-large">
                                <span class="avatar-initial-large">
                                    <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                </span>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm mt-3 w-100">
                                <i class="fas fa-upload me-2"></i>Changer la Photo
                            </button>
                            <small class="text-muted d-block mt-2 text-center">JPG, PNG (Max 2MB)</small>
                        </div>
                    </div>

                    <!-- Statistiques du Profil -->
                    <div class="form-card">
                        <h5 class="mb-3">
                            <i class="fas fa-chart-line me-2 text-success"></i>
                            Complétion du Profil
                        </h5>
                        <div class="profile-completion-widget">
                            <?php
                            $completion = 0;
                            if (!empty($user['first_name'])) $completion += 15;
                            if (!empty($user['last_name'])) $completion += 15;
                            if (!empty($user['email'])) $completion += 15;
                            if (!empty($user['phone'])) $completion += 15;
                            if (!empty($user['city'])) $completion += 20;
                            if (!empty($user['bio'])) $completion += 20;
                            ?>
                            <div class="completion-circle">
                                <svg viewBox="0 0 100 100">
                                    <circle cx="50" cy="50" r="45" fill="none" stroke="#e9ecef" stroke-width="8"/>
                                    <circle cx="50" cy="50" r="45" fill="none" stroke="#0066CC" stroke-width="8"
                                            stroke-dasharray="<?php echo $completion * 2.827; ?> 283"
                                            stroke-linecap="round"
                                            transform="rotate(-90 50 50)"/>
                                </svg>
                                <div class="completion-text">
                                    <span class="completion-value"><?php echo $completion; ?>%</span>
                                </div>
                            </div>
                            <div class="completion-details mt-3">
                                <div class="completion-item <?php echo !empty($user['phone']) ? 'completed' : ''; ?>">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <span>Numéro de téléphone</span>
                                </div>
                                <div class="completion-item <?php echo !empty($user['city']) ? 'completed' : ''; ?>">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <span>Ville</span>
                                </div>
                                <div class="completion-item <?php echo !empty($user['bio']) ? 'completed' : ''; ?>">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <span>Biographie</span>
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
:root {
    --bleu-tchadien: #0066CC;
    --jaune-solaire: #FFD700;
    --rouge-terre: #CC3333;
    --vert-savane: #228B22;
    --gris-harmattan: #2C3E50;
}

.edit-profile-container {
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
    background: linear-gradient(135deg, var(--bleu-tchadien), #0052a3);
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 2rem;
    margin-right: 1.5rem;
    box-shadow: 0 5px 20px rgba(0, 102, 204, 0.3);
}

.page-header h1 {
    color: var(--gris-harmattan);
    font-weight: 700;
    font-size: 2rem;
}

/* Form Card */
.form-card {
    background: white;
    padding: 2rem;
    border-radius: 20px;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
}

.form-label {
    font-weight: 600;
    color: var(--gris-harmattan);
    margin-bottom: 0.75rem;
}

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

/* Avatar Upload */
.avatar-upload-section {
    text-align: center;
}

.avatar-preview-large {
    width: 150px;
    height: 150px;
    background: linear-gradient(135deg, var(--bleu-tchadien), var(--jaune-solaire));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1rem;
    box-shadow: 0 8px 30px rgba(0, 102, 204, 0.3);
}

.avatar-initial-large {
    font-size: 3rem;
    font-weight: 700;
    color: white;
}

/* Profile Completion */
.profile-completion-widget {
    text-align: center;
}

.completion-circle {
    position: relative;
    width: 150px;
    height: 150px;
    margin: 0 auto;
}

.completion-circle svg {
    width: 100%;
    height: 100%;
}

.completion-text {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
}

.completion-value {
    font-size: 2rem;
    font-weight: 700;
    color: var(--bleu-tchadien);
}

.completion-details {
    text-align: left;
}

.completion-item {
    display: flex;
    align-items: center;
    padding: 0.75rem;
    border-radius: 8px;
    margin-bottom: 0.5rem;
    color: #6c757d;
}

.completion-item.completed {
    color: var(--vert-savane);
    background: rgba(34, 139, 34, 0.05);
}

.completion-item i {
    font-size: 1.1rem;
}

/* Alerts */
.alert-modern {
    border-radius: 12px;
    border: none;
    padding: 1.25rem;
    margin-bottom: 2rem;
}

/* Form Actions */
.form-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 1px solid #e9ecef;
}

/* Responsive */
@media (max-width: 991px) {
    .page-header {
        margin-top: 70px;
    }

    .form-card {
        padding: 1.5rem;
    }
}

@media (max-width: 576px) {
    .form-actions {
        flex-direction: column-reverse;
    }

    .form-actions .btn {
        width: 100%;
    }
}
</style>

<?php include 'includes/footer.php'; ?>
