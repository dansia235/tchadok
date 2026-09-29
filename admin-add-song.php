<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/duree-audio.php';
require_once 'includes/agregats.php';

// SEC-19 : l'acces depend d'une permission nommee, verifiee cote serveur.
// Masquer l'entree de menu ne protege rien : l'adresse se tape.
Autorisations::exiger('catalogue.editer');

$pageTitle = 'Ajouter une chanson';
$pageDescription = 'Ajoutez une chanson à la bibliothèque.';
$hideTopNav = true;
$hideFooter = true;
$success = '';
$error = '';
$user = getCurrentUser();

$dbInstance = TchadokDatabase::getInstance();
$db = $dbInstance->getConnection();

$artists = $db ? $db->query("SELECT id, stage_name FROM artists ORDER BY stage_name")->fetchAll() : [];
$albums = $db ? $db->query("SELECT id, title, artist_id FROM albums ORDER BY title")->fetchAll() : [];
$genres = $db ? getGenresSelectionnables() : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitizeInput($_POST['title'] ?? '');
    $artistId = (int) ($_POST['artist_id'] ?? 0);
    $albumId = !empty($_POST['album_id']) ? (int) $_POST['album_id'] : null;
    $genreId = !empty($_POST['genre_id']) ? (int) $_POST['genre_id'] : null;
    // STAT-02 : la duree est lue dans le fichier, jamais saisie.
    $duration = 0;
    $trackNumber = !empty($_POST['track_number']) ? (int) $_POST['track_number'] : null;
    $releaseDate = sanitizeInput($_POST['release_date'] ?? '');
    $language = sanitizeInput($_POST['language'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $lyrics = sanitizeInput($_POST['lyrics'] ?? '');
    // DATA-04 : le prix est verifie contre la grille administree.
    $prixSaisi = Tarifs::valider($_POST['price'] ?? 0, 'track');
    $price = $prixSaisi['valeur'];
    $isFree = isset($_POST['is_free']) ? 1 : 0;
    $downloadAllowed = isset($_POST['download_allowed']) ? 1 : 0;
    $explicitContent = isset($_POST['explicit_content']) ? 1 : 0;
    $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
    $status = sanitizeInput($_POST['status'] ?? 'draft');

    if (!$prixSaisi['valide']) {
        $error = $prixSaisi['message'];
    } elseif (empty($title) || $artistId <= 0) {
        $error = 'Titre et artiste obligatoires.';
    } elseif (!estGenreSelectionnable($genreId)) {
        // TAXO-02 : le genre est obligatoire a la soumission (statistiques par genre).
        $error = 'Le genre est obligatoire : choisissez-le dans la liste (ou proposez-en un nouveau).';
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
                    // Message ecrit pour la personne qui depose : affiche tel quel.
                    throw new DepotRefuse($upload['message']);
                }
                $audioPath = AUDIO_PATH . $upload['filename'];
            }

            if (!$audioPath) {
                throw new DepotRefuse('Fichier audio requis.');
            }
            $duration = DureeAudio::duDepot(__DIR__ . '/' . $audioPath);

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

            $allowedStatus = ['draft', 'pending', 'approved', 'rejected'];
            if (!in_array($status, $allowedStatus, true)) {
                $status = 'draft';
            }

            $stmt = $db->prepare("
                INSERT INTO tracks
                (album_id, release_id, slug, artist_id, title, description, genre_id, audio_file, preview_file, lyrics, duration, track_number, price, is_free, download_allowed, language, release_date, explicit_content, status, is_featured, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $albumId,
                $albumId,
                Sorties::slug($title, 'tracks'),
                $artistId,
                $title,
                $description ?: null,
                $genreId,
                $audioPath,
                $previewPath,
                $lyrics ?: null,
                $duration,
                $trackNumber,
                $price,
                $isFree,
                $downloadAllowed,
                $language ?: null,
                $releaseDate ?: null,
                $explicitContent,
                $status,
                $isFeatured
            ]);

            // SEC-19 : l'ajout au catalogue depuis la console est trace, avec
            // le statut donne au depart (un titre publie directement en
            // "approved" saute la file de moderation).
            JournalAudit::enregistrer('contenu.cree', [
                'cible_type' => 'titre',
                'cible_id'   => $db->lastInsertId(),
                'apres'      => ['titre' => $title, 'artiste_id' => $artistId, 'statut' => $status, 'prix' => $price],
            ]);

            // STAT-06 : nombre de titres et duree de la sortie, sans declencheur.
            Compteurs::sortie($albumId ? (int) $albumId : null);
            $success = 'Chanson ajoutée avec succès !';
            header('refresh:2;url=' . SITE_URL . '/admin-dashboard.php');
        } catch (DepotRefuse $e) {
            $error = $e->messagePourArtiste();
        } catch (Exception $e) {
            $error = GestionErreurs::messagePublic($e, 'ajout d\'un titre (admin)');
        }
    }
}

$adminShellMetrics = [
    ['value' => (string) count($artists), 'label' => 'artistes', 'tone' => 'border-white/10 bg-white/5 text-muted'],
    ['value' => (string) count($albums), 'label' => 'albums', 'tone' => 'border-white/10 bg-white/5 text-muted'],
    ['value' => $success !== '' ? 'enregistré' : 'nouveau titre', 'label' => '', 'tone' => $success !== '' ? 'border-emerald-400/30 bg-emerald-400/10 text-emerald-200' : 'border-indigo-400/30 bg-indigo-400/10 text-indigo-200']
];
$dashboardSecondaryNavLabel = 'Titres admin';
$dashboardSecondaryNavItems = [
    ['label' => 'Aperçu', 'target' => 'song-overview', 'icon' => 'home'],
    ['label' => 'Formulaire', 'target' => 'song-form', 'icon' => 'edit'],
    ['label' => 'Médias', 'target' => 'song-media', 'icon' => 'file-audio'],
    ['label' => 'Paroles', 'target' => 'song-lyrics', 'icon' => 'align-left']
];

$additionalJS = [
    SITE_URL . '/assets/js/tailwind-shell.js',
    SITE_URL . '/assets/js/dashboard-sticky-header.js',
    SITE_URL . '/assets/js/dashboard-secondary-nav.js'
];

include 'includes/header-tailwind.php';
?>

<main class="min-h-screen bg-[radial-gradient(circle_at_top_right,rgba(47,109,224,0.18),transparent_26%),radial-gradient(circle_at_top_left,rgba(99,102,241,0.12),transparent_24%),#0B0F17] pb-16 pt-8">
    <?php include 'includes/admin-shell-header.php'; ?>

    <section id="song-overview" class="py-12">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
                <div class="flex items-start gap-4">
                    <div class="grid h-12 w-12 place-items-center rounded-2xl bg-accent/20 text-accent">
                        <i class="fas fa-music"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-display font-bold text-text">Ajouter une chanson</h1>
                        <p class="mt-1 text-sm text-muted">Publiez un nouveau titre dans la bibliothèque.</p>
                    </div>
                </div>
                <a href="<?php echo SITE_URL; ?>/admin-dashboard.php" class="rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-semibold text-text">
                    <i class="fas fa-arrow-left"></i> Retour dashboard
                </a>
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

            <form id="song-form" method="POST" enctype="multipart/form-data" class="rounded-3xl border border-white/10 bg-surface/60 p-6 space-y-6">
                <?php echo csrfField(); ?>
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="text-xs font-semibold text-muted" for="title">Titre *</label>
                        <input id="title" type="text" name="title" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text" value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" required>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-muted" for="artist">Artiste *</label>
                        <select id="artist" name="artist_id" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text" required>
                            <option value="">Sélectionner un artiste</option>
                            <?php foreach ($artists as $artist): ?>
                                <option value="<?php echo $artist['id']; ?>" <?php echo (isset($_POST['artist_id']) && (int) $_POST['artist_id'] === (int) $artist['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($artist['stage_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
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
                    <div>
                        <label class="text-xs font-semibold text-muted" for="genre">Genre</label>
                        <select id="genre" name="genre_id" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text">
                            <option value="">Sélectionner un genre</option>
                            <?php echo optionsGenres($genres, $_POST['genre_id'] ?? null); ?>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-muted" for="track_number">Numéro de piste</label>
                        <input id="track_number" type="number" min="1" name="track_number" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text" value="<?php echo htmlspecialchars($_POST['track_number'] ?? ''); ?>">
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-4">
                    <div>
                        <span class="text-xs font-semibold text-muted">Durée</span>
                        <p class="mt-2 rounded-2xl border border-white/10 bg-bg/40 px-4 py-3 text-sm text-muted">Lue automatiquement dans le fichier audio.</p>
                    </div>
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
                        Téléchargement autorisé
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm text-muted">
                        <input type="checkbox" name="explicit_content" class="h-4 w-4 rounded border-white/20 bg-bg text-accent focus:ring-accent/40" <?php echo isset($_POST['explicit_content']) ? 'checked' : ''; ?>>
                        Contenu explicite
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm text-muted">
                        <input type="checkbox" name="is_featured" class="h-4 w-4 rounded border-white/20 bg-bg text-accent focus:ring-accent/40" <?php echo isset($_POST['is_featured']) ? 'checked' : ''; ?>>
                        Mettre en avant
                    </label>
                </div>

                <div id="song-media" class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="text-xs font-semibold text-muted" for="status">Statut</label>
                        <select id="status" name="status" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text">
                            <?php
                            $statusOptions = ['draft' => 'Brouillon', 'pending' => 'En attente', 'approved' => 'Approuvé', 'rejected' => 'Rejeté'];
                            $currentStatus = $_POST['status'] ?? 'draft';
                            foreach ($statusOptions as $value => $label): ?>
                                <option value="<?php echo $value; ?>" <?php echo $currentStatus === $value ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-muted" for="audio_file">Fichier audio *</label>
                        <input id="audio_file" type="file" name="audio_file" accept="audio/*" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text">
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="text-xs font-semibold text-muted" for="preview_file">Extrait audio (optionnel)</label>
                        <input id="preview_file" type="file" name="preview_file" accept="audio/*" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-muted" for="description">Description</label>
                        <textarea id="description" name="description" rows="4" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    </div>
                </div>

                <div id="song-lyrics">
                    <label class="text-xs font-semibold text-muted" for="lyrics">Paroles (optionnel)</label>
                    <textarea id="lyrics" name="lyrics" rows="4" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text"><?php echo htmlspecialchars($_POST['lyrics'] ?? ''); ?></textarea>
                </div>

                <div class="flex flex-wrap gap-3">
                    <button type="submit" class="rounded-full bg-accent px-6 py-3 text-sm font-semibold text-white shadow-elev-1">
                        <i class="fas fa-save"></i> Enregistrer
                    </button>
                    <a href="<?php echo SITE_URL; ?>/admin-dashboard.php" class="rounded-full border border-white/10 bg-white/5 px-6 py-3 text-sm font-semibold text-text">
                        Annuler
                    </a>
                </div>
            </form>
        </div>
    </section>
</main>

<?php include 'includes/footer-tailwind.php'; ?>
