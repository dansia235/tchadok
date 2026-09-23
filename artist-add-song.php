<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';

if (!isLoggedIn() || !isArtist()) {
    header('Location: ' . SITE_URL . '/login.php');
    exit();
}

$user = getCurrentUser();
$dbInstance = TchadokDatabase::getInstance();
$db = $dbInstance->getConnection();

$stmt = $db->prepare("SELECT * FROM artists WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$artist = $stmt->fetch();

if (!$artist) {
    die('Profil artiste non trouve');
}

$pageTitle = 'Ajouter une Chanson';
$pageDescription = 'Publiez un nouveau titre dans votre catalogue.';
$success = '';
$error = '';

$genres = $db ? $db->query("SELECT id, name FROM genres ORDER BY name")->fetchAll() : [];
$albums = $db ? $db->query("SELECT id, title FROM albums WHERE artist_id = {$artist['id']} ORDER BY title")->fetchAll() : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitizeInput($_POST['title'] ?? '');
    $albumId = !empty($_POST['album_id']) ? (int) $_POST['album_id'] : null;
    $genreId = !empty($_POST['genre_id']) ? (int) $_POST['genre_id'] : null;
    $duration = (int) ($_POST['duration'] ?? 0);
    $trackNumber = !empty($_POST['track_number']) ? (int) $_POST['track_number'] : null;
    $releaseDate = sanitizeInput($_POST['release_date'] ?? '');
    $language = sanitizeInput($_POST['language'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $lyrics = sanitizeInput($_POST['lyrics'] ?? '');
    // DATA-04 : le prix est verifie contre la grille administree, pas contre
    // des bornes ecrites dans le code.
    $prixSaisi = Tarifs::valider($_POST['price'] ?? 0, 'track');
    $price = $prixSaisi['valeur'];
    $isFree = isset($_POST['is_free']) ? 1 : 0;
    $downloadAllowed = isset($_POST['download_allowed']) ? 1 : 0;
    $explicitContent = isset($_POST['explicit_content']) ? 1 : 0;

    if (!$prixSaisi['valide']) {
        $error = $prixSaisi['message'];
    } elseif (empty($title)) {
        $error = 'Le titre est obligatoire.';
    } else {
        try {
            $audioPath = null;
            if (!empty($_FILES['audio_file']['tmp_name'])) {
                $upload = uploadFile(
                    $_FILES['audio_file'],
                    __DIR__ . '/' . AUDIO_PATH,
                    ALLOWED_AUDIO_TYPES,
                    MAX_AUDIO_SIZE
                );
                if (!$upload['success']) {
                    throw new Exception($upload['message']);
                }
                $audioPath = AUDIO_PATH . $upload['filename'];
            }

            if (!$audioPath) {
                throw new Exception('Fichier audio requis.');
            }

            $previewPath = null;
            if (!empty($_FILES['preview_file']['tmp_name'])) {
                $uploadPreview = uploadFile(
                    $_FILES['preview_file'],
                    __DIR__ . '/' . AUDIO_PATH,
                    ALLOWED_AUDIO_TYPES,
                    MAX_AUDIO_SIZE
                );
                if ($uploadPreview['success']) {
                    $previewPath = AUDIO_PATH . $uploadPreview['filename'];
                }
            }

            if ($isFree) {
                $price = 0;
            }

            $stmt = $db->prepare("
                INSERT INTO tracks
                (album_id, release_id, slug, artist_id, title, description, genre_id, audio_file, preview_file, lyrics, duration, track_number, price, is_free, download_allowed, language, release_date, explicit_content, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([
                $albumId,
                $albumId,
                Sorties::slug($title, 'tracks'),
                $artist['id'],
                $title,
                $description ?: null,
                $genreId,
                $audioPath,
                $previewPath,
                $lyrics ?: null,
                max(1, $duration),
                $trackNumber,
                $price,
                $isFree,
                $downloadAllowed,
                $language ?: null,
                $releaseDate ?: null,
                $explicitContent
            ]);
            $success = 'Chanson ajoutee avec succes !';
            header('refresh:2;url=' . SITE_URL . '/artist-dashboard.php');
        } catch (Exception $e) {
            $error = GestionErreurs::messagePublic($e, 'ajout d\'un titre (artiste)');
        }
    }
}

$additionalJS = [
    SITE_URL . '/assets/js/tailwind-shell.js'
];

include 'includes/header-tailwind.php';
?>

<main class="pt-20">
    <section class="py-12">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <div class="mb-6 flex items-start gap-4">
                <div class="grid h-12 w-12 place-items-center rounded-2xl bg-accent/20 text-accent">
                    <i class="fas fa-music"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-display font-bold text-text">Ajouter une chanson</h1>
                    <p class="mt-1 text-sm text-muted">Publiez un nouveau titre dans votre catalogue.</p>
                </div>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success" role="alert">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="rounded-3xl border border-white/10 bg-surface/60 p-6 space-y-6">
                <?php echo csrfField(); ?>
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="text-xs font-semibold text-muted" for="title">Titre *</label>
                        <input id="title" type="text" name="title" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text" value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" required>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-muted" for="album">Album</label>
                        <select id="album" name="album_id" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text">
                            <option value="">-- Single --</option>
                            <?php foreach ($albums as $album): ?>
                                <option value="<?php echo $album['id']; ?>" <?php echo (isset($_POST['album_id']) && (int) $_POST['album_id'] === (int) $album['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($album['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <label class="text-xs font-semibold text-muted" for="genre">Genre</label>
                        <select id="genre" name="genre_id" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text">
                            <option value="">Selectionner un genre</option>
                            <?php foreach ($genres as $genre): ?>
                                <option value="<?php echo $genre['id']; ?>" <?php echo (isset($_POST['genre_id']) && (int) $_POST['genre_id'] === (int) $genre['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($genre['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-muted" for="track_number">Numero de piste</label>
                        <input id="track_number" type="number" min="1" name="track_number" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text" value="<?php echo htmlspecialchars($_POST['track_number'] ?? ''); ?>">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-muted" for="duration">Duree (sec)</label>
                        <input id="duration" type="number" min="1" name="duration" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text" value="<?php echo htmlspecialchars($_POST['duration'] ?? '180'); ?>">
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <label class="text-xs font-semibold text-muted" for="release_date">Date de sortie</label>
                        <input id="release_date" type="date" name="release_date" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text" value="<?php echo htmlspecialchars($_POST['release_date'] ?? ''); ?>">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-muted" for="language">Langue</label>
                        <input id="language" type="text" name="language" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text" value="<?php echo htmlspecialchars($_POST['language'] ?? ''); ?>">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-muted" for="price">Prix (FCFA)</label>
                        <?php $regleDePrix = Tarifs::regle('track'); ?>
                        <input id="price" type="number" min="0" max="<?php echo (int) ($regleDePrix['max'] ?? 500000); ?>" step="50" name="price" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text" value="<?php echo htmlspecialchars($_POST['price'] ?? '0'); ?>">
                        <p class="mt-2 text-xs text-muted"><?php echo htmlspecialchars(Tarifs::indication('track')); ?></p>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <label class="inline-flex items-center gap-2 text-sm text-muted">
                        <input type="checkbox" name="is_free" class="h-4 w-4 rounded border-white/20 bg-bg text-accent focus:ring-accent/40" <?php echo isset($_POST['is_free']) ? 'checked' : ''; ?>>
                        Titre gratuit
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm text-muted">
                        <input type="checkbox" name="download_allowed" class="h-4 w-4 rounded border-white/20 bg-bg text-accent focus:ring-accent/40" <?php echo isset($_POST['download_allowed']) ? 'checked' : ''; ?>>
                        Telechargement autorise
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm text-muted">
                        <input type="checkbox" name="explicit_content" class="h-4 w-4 rounded border-white/20 bg-bg text-accent focus:ring-accent/40" <?php echo isset($_POST['explicit_content']) ? 'checked' : ''; ?>>
                        Contenu explicite
                    </label>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="text-xs font-semibold text-muted" for="audio_file">Fichier audio *</label>
                        <input id="audio_file" type="file" name="audio_file" accept="audio/*" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-muted" for="preview_file">Extrait audio (optionnel)</label>
                        <input id="preview_file" type="file" name="preview_file" accept="audio/*" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text">
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="text-xs font-semibold text-muted" for="description">Description</label>
                        <textarea id="description" name="description" rows="4" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-muted" for="lyrics">Paroles (optionnel)</label>
                        <textarea id="lyrics" name="lyrics" rows="4" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text"><?php echo htmlspecialchars($_POST['lyrics'] ?? ''); ?></textarea>
                    </div>
                </div>

                <div class="flex flex-wrap gap-3">
                    <button type="submit" class="rounded-full bg-accent px-6 py-3 text-sm font-semibold text-white shadow-elev-1">
                        <i class="fas fa-save"></i> Publier
                    </button>
                    <a href="<?php echo SITE_URL; ?>/artist-dashboard.php" class="rounded-full border border-white/10 bg-white/5 px-6 py-3 text-sm font-semibold text-text">
                        Annuler
                    </a>
                </div>
            </form>
        </div>
    </section>
</main>

<?php include 'includes/footer-tailwind.php'; ?>
