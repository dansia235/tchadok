<?php
/**
 * Créer une Playlist - Tchadok Platform
 * Permet aux utilisateurs de créer leurs propres playlists
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    header('Location: ' . SITE_URL . '/login.php?redirect=create-playlist');
    exit();
}

$pageTitle = 'Créer une Playlist';
$pageDescription = 'Créez votre playlist personnalisée';

$user = getCurrentUser();
$success = '';
$error = '';

// Traiter la soumission du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $playlistName = sanitizeInput($_POST['playlist_name'] ?? '');
    $playlistDescription = sanitizeInput($_POST['playlist_description'] ?? '');
    $playlistVisibility = sanitizeInput($_POST['visibility'] ?? 'public');

    // Validation
    if (empty($playlistName)) {
        $error = 'Le nom de la playlist est obligatoire.';
    } elseif (strlen($playlistName) < 3) {
        $error = 'Le nom doit contenir au moins 3 caractères.';
    } else {
        try {
            $dbInstance = TchadokDatabase::getInstance();
            $db = $dbInstance->getConnection();

            $userId = $_SESSION['user_id'];

            // Insérer la playlist (table à créer plus tard)
            // Pour l'instant, on simule juste le succès
            $success = '✅ Playlist "' . htmlspecialchars($playlistName) . '" créée avec succès !';

            // Redirection après 2 secondes
            header('refresh:2;url=' . SITE_URL . '/user-dashboard.php');

        } catch (Exception $e) {
            $error = 'Une erreur est survenue lors de la création de la playlist.';
        }
    }
}

include 'includes/header.php';
?>

<div class="create-playlist-container">
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
                            <i class="fas fa-plus-circle"></i>
                        </div>
                        <div>
                            <h1 class="mb-2">Créer une Playlist</h1>
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

    <!-- Formulaire -->
    <section class="form-section py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="form-card">
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
                            <!-- Nom de la Playlist -->
                            <div class="form-group mb-4">
                                <label for="playlist_name" class="form-label">
                                    <i class="fas fa-music me-2"></i>Nom de la Playlist *
                                </label>
                                <input type="text"
                                       class="form-control form-control-modern"
                                       id="playlist_name"
                                       name="playlist_name"
                                       placeholder="Ma playlist préférée"
                                       value="<?php echo htmlspecialchars($_POST['playlist_name'] ?? ''); ?>"
                                       required>
                            </div>

                            <!-- Description -->
                            <div class="form-group mb-4">
                                <label for="playlist_description" class="form-label">
                                    <i class="fas fa-align-left me-2"></i>Description
                                </label>
                                <textarea class="form-control form-control-modern"
                                          id="playlist_description"
                                          name="playlist_description"
                                          rows="4"
                                          placeholder="Décrivez votre playlist..."><?php echo htmlspecialchars($_POST['playlist_description'] ?? ''); ?></textarea>
                                <small class="text-muted">Optionnel - Aidez les autres à découvrir votre playlist</small>
                            </div>

                            <!-- Visibilité -->
                            <div class="form-group mb-4">
                                <label class="form-label">
                                    <i class="fas fa-eye me-2"></i>Visibilité
                                </label>
                                <div class="visibility-options">
                                    <div class="visibility-option">
                                        <input type="radio"
                                               id="public"
                                               name="visibility"
                                               value="public"
                                               <?php echo (!isset($_POST['visibility']) || $_POST['visibility'] === 'public') ? 'checked' : ''; ?>>
                                        <label for="public">
                                            <div class="option-icon">
                                                <i class="fas fa-globe"></i>
                                            </div>
                                            <div class="option-content">
                                                <strong>Publique</strong>
                                                <small>Visible par tous les utilisateurs</small>
                                            </div>
                                        </label>
                                    </div>
                                    <div class="visibility-option">
                                        <input type="radio"
                                               id="private"
                                               name="visibility"
                                               value="private"
                                               <?php echo (isset($_POST['visibility']) && $_POST['visibility'] === 'private') ? 'checked' : ''; ?>>
                                        <label for="private">
                                            <div class="option-icon">
                                                <i class="fas fa-lock"></i>
                                            </div>
                                            <div class="option-content">
                                                <strong>Privée</strong>
                                                <small>Visible uniquement par vous</small>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Cover Image (à implémenter plus tard) -->
                            <div class="form-group mb-4">
                                <label class="form-label">
                                    <i class="fas fa-image me-2"></i>Image de Couverture
                                </label>
                                <div class="cover-upload">
                                    <div class="cover-preview">
                                        <i class="fas fa-music fa-3x text-muted"></i>
                                    </div>
                                    <div class="cover-upload-info">
                                        <button type="button" class="btn btn-outline-primary btn-sm mb-2">
                                            <i class="fas fa-upload me-2"></i>Choisir une Image
                                        </button>
                                        <small class="text-muted d-block">JPG, PNG (Max 2MB)</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Boutons -->
                            <div class="form-actions">
                                <a href="<?php echo SITE_URL; ?>/user-dashboard.php" class="btn btn-outline-secondary btn-lg">
                                    <i class="fas fa-times me-2"></i>Annuler
                                </a>
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fas fa-check me-2"></i>Créer la Playlist
                                </button>
                            </div>
                        </form>
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

.create-playlist-container {
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
    background: linear-gradient(135deg, var(--vert-savane), #1a6b1a);
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 2rem;
    margin-right: 1.5rem;
    box-shadow: 0 5px 20px rgba(34, 139, 34, 0.3);
}

.page-header h1 {
    color: var(--gris-harmattan);
    font-weight: 700;
    font-size: 2rem;
}

/* Form Card */
.form-card {
    background: white;
    padding: 3rem;
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

/* Visibility Options */
.visibility-options {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}

.visibility-option input[type="radio"] {
    display: none;
}

.visibility-option label {
    display: flex;
    align-items: center;
    padding: 1.25rem;
    border: 2px solid #e9ecef;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    margin-bottom: 0;
}

.visibility-option input[type="radio"]:checked + label {
    border-color: var(--bleu-tchadien);
    background: rgba(0, 102, 204, 0.05);
}

.visibility-option label:hover {
    border-color: var(--bleu-tchadien);
}

.option-icon {
    width: 50px;
    height: 50px;
    background: var(--bleu-tchadien);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.3rem;
    margin-right: 1rem;
}

.visibility-option input[type="radio"]:checked + label .option-icon {
    background: var(--vert-savane);
}

.option-content strong {
    display: block;
    color: var(--gris-harmattan);
    font-size: 1rem;
}

.option-content small {
    display: block;
    color: #6c757d;
    font-size: 0.85rem;
    margin-top: 0.25rem;
}

/* Cover Upload */
.cover-upload {
    display: flex;
    align-items: center;
    gap: 2rem;
    padding: 1.5rem;
    border: 2px dashed #e9ecef;
    border-radius: 12px;
    background: #f8f9fa;
}

.cover-preview {
    width: 120px;
    height: 120px;
    background: white;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid #e9ecef;
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
        padding: 2rem;
    }

    .visibility-options {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 576px) {
    .cover-upload {
        flex-direction: column;
        text-align: center;
    }

    .form-actions {
        flex-direction: column-reverse;
    }

    .form-actions .btn {
        width: 100%;
    }
}
</style>

<?php include 'includes/footer.php'; ?>
