<?php
/**
 * Créer une playlist - Tchadok Platform
 * Migration Tailwind (progressive)
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Verifier si l'utilisateur est connecte
if (!isLoggedIn()) {
    header('Location: ' . SITE_URL . '/login.php?redirect=create-playlist');
    exit();
}

$pageTitle = 'Créer une playlist';
$pageDescription = 'Creez votre playlist personnalisee';

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
        $error = 'Le nom doit contenir au moins 3 caracteres.';
    } elseif (!($limite = Abonnements::peutCreerPlaylist((int) $_SESSION['user_id']))['permis']) {
        // SUB-04 : playlists illimitees pour les abonnes Premium seulement.
        $error = (string) $limite['message'];
    } else {
        try {
            $dbInstance = TchadokDatabase::getInstance();
            $db = $dbInstance->getConnection();

            $userId = $_SESSION['user_id'];

            $coverPath = null;
            if (!empty($_FILES['cover_image']['tmp_name'])) {
                $upload = uploadFile(
                    $_FILES['cover_image'],
                    __DIR__ . '/' . IMAGES_PATH . 'playlists/',
                    ALLOWED_IMAGE_TYPES,
                    MAX_IMAGE_SIZE
                );
                if (!$upload['success']) {
                    throw new Exception($upload['message']);
                }
                $coverPath = IMAGES_PATH . 'playlists/' . $upload['filename'];
            }


            $isPublic = $playlistVisibility === 'public' ? 1 : 0;
            $stmt = $db->prepare("
                INSERT INTO playlists (user_id, name, description, cover_image, is_public, total_tracks, total_duration, total_plays, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, 0, 0, 0, NOW(), NOW())
            ");
            $stmt->execute([$userId, $playlistName, $playlistDescription ?: null, $coverPath, $isPublic]);

            $success = 'Playlist "' . htmlspecialchars($playlistName) . '" creee avec succes !';

            // Redirection apres 2 secondes
            header('refresh:2;url=' . SITE_URL . '/user-dashboard.php');
        } catch (Exception $e) {
            $error = 'Une erreur est survenue lors de la creation de la playlist.';
        }
    }
}

include 'includes/header-tailwind.php';
?>

<main class="pt-24 pb-16">
    <section class="bg-bg">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-3xl border border-white/10 bg-surface/60 p-6 shadow-elev-2 sm:p-8">
                <div class="flex flex-wrap items-center gap-4">
                    <a href="<?php echo SITE_URL; ?>/user-dashboard.php" class="grid h-11 w-11 place-items-center rounded-2xl border border-white/10 bg-white/5 text-text hover:bg-white/10">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <div class="flex flex-wrap items-center gap-4">
                        <div class="grid h-14 w-14 place-items-center rounded-2xl bg-emerald-500/20 text-emerald-300">
                            <i class="fas fa-plus-circle text-2xl"></i>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-[0.2em] text-muted">Playlist</p>
                            <h1 class="mt-1 text-3xl font-display font-bold text-text">Créer une playlist</h1>
                            <p class="mt-2 text-sm text-muted">
                                <i class="fas fa-user mr-2"></i>
                                <?php echo htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="mt-8">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-3xl border border-white/10 bg-surface/60 p-6 shadow-elev-2 sm:p-8">
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle mt-0.5"></i>
                        <span><?php echo $success; ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle mt-0.5"></i>
                        <span><?php echo $error; ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" enctype="multipart/form-data" class="space-y-6">
                    <?php echo csrfField(); ?>
                    <div>
                        <label for="playlist_name" class="text-sm font-semibold text-text">Nom de la playlist *</label>
                        <input type="text"
                               class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text placeholder:text-muted focus:outline-none focus:ring-2 focus:ring-accent/60"
                               id="playlist_name"
                               name="playlist_name"
                               placeholder="Ma playlist préférée"
                               value="<?php echo htmlspecialchars($_POST['playlist_name'] ?? ''); ?>"
                               required>
                    </div>

                    <div>
                        <label for="playlist_description" class="text-sm font-semibold text-text">Description</label>
                        <textarea class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text placeholder:text-muted focus:outline-none focus:ring-2 focus:ring-accent/60"
                                  id="playlist_description"
                                  name="playlist_description"
                                  rows="4"
                                  placeholder="Decrivez votre playlist..."><?php echo htmlspecialchars($_POST['playlist_description'] ?? ''); ?></textarea>
                        <p class="mt-2 text-xs text-muted">Optionnel - Aidez les autres à découvrir votre playlist.</p>
                    </div>

                    <div>
                        <p class="text-sm font-semibold text-text">Visibilite</p>
                        <div class="mt-3 grid gap-4 sm:grid-cols-2">
                            <label class="block">
                                <input type="radio"
                                       id="public"
                                       name="visibility"
                                       value="public"
                                       class="peer sr-only"
                                       <?php echo (!isset($_POST['visibility']) || $_POST['visibility'] === 'public') ? 'checked' : ''; ?>>
                                <div class="flex h-full items-start gap-4 rounded-2xl border border-white/10 bg-white/5 p-4 transition hover:border-white/20 peer-checked:border-accent peer-checked:bg-accent/10">
                                    <span class="mt-1 grid h-11 w-11 place-items-center rounded-xl bg-white/10 text-accent">
                                        <i class="fas fa-globe"></i>
                                    </span>
                                    <div>
                                        <span class="block text-sm font-semibold text-text">Publique</span>
                                        <span class="mt-1 block text-xs text-muted">Visible par tous les utilisateurs</span>
                                    </div>
                                </div>
                            </label>
                            <label class="block">
                                <input type="radio"
                                       id="private"
                                       name="visibility"
                                       value="private"
                                       class="peer sr-only"
                                       <?php echo (isset($_POST['visibility']) && $_POST['visibility'] === 'private') ? 'checked' : ''; ?>>
                                <div class="flex h-full items-start gap-4 rounded-2xl border border-white/10 bg-white/5 p-4 transition hover:border-white/20 peer-checked:border-emerald-400/60 peer-checked:bg-emerald-500/10">
                                    <span class="mt-1 grid h-11 w-11 place-items-center rounded-xl bg-white/10 text-emerald-300">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                    <div>
                                        <span class="block text-sm font-semibold text-text">Privee</span>
                                        <span class="mt-1 block text-xs text-muted">Visible uniquement par vous</span>
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div>
                        <p class="text-sm font-semibold text-text">Image de couverture</p>
                        <div class="mt-3 grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="text-xs font-semibold text-muted">Upload</label>
                                <input type="file" name="cover_image" accept="image/*" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text">
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <a href="<?php echo SITE_URL; ?>/user-dashboard.php" class="inline-flex items-center justify-center rounded-full border border-white/10 bg-white/5 px-6 py-3 text-sm font-semibold text-text hover:bg-white/10">
                            <i class="fas fa-times mr-2"></i>Annuler
                        </a>
                        <button type="submit" class="inline-flex items-center justify-center rounded-full bg-emerald-500 px-6 py-3 text-sm font-semibold text-white shadow-elev-1 hover:shadow-elev-2">
                            <i class="fas fa-check mr-2"></i>Créer la playlist
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>
</main>

<?php include 'includes/footer-tailwind.php'; ?>
