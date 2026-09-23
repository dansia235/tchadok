<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// SEC-19 : l'acces depend d'une permission nommee, verifiee cote serveur.
// Masquer l'entree de menu ne protege rien : l'adresse se tape.
Autorisations::exiger('editorial.gerer');

$pageTitle = 'Gestion Podcasts';
$pageDescription = 'Ajoutez des podcasts et épisodes.';
$hideTopNav = true;
$hideFooter = true;
$success = '';
$error = '';
$user = getCurrentUser();

$dbInstance = TchadokDatabase::getInstance();
$db = $dbInstance->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create_podcast') {
            $title = sanitizeInput($_POST['title'] ?? '');
            $host = sanitizeInput($_POST['host_name'] ?? '');
            $category = sanitizeInput($_POST['category'] ?? '');
            $language = sanitizeInput($_POST['language'] ?? '');
            $description = sanitizeInput($_POST['description'] ?? '');
            $status = sanitizeInput($_POST['status'] ?? 'active');
            $isFeatured = isset($_POST['is_featured']) ? 1 : 0;

            if ($title === '') {
                throw new Exception('Titre obligatoire.');
            }

            $slug = slugifyText($title);
            $slug = ensureUniquePodcastSlug($db, $slug);

            $coverPath = null;
            if (!empty($_FILES['cover_image']['tmp_name'])) {
                $upload = uploadFile(
                    $_FILES['cover_image'],
                    __DIR__ . '/' . IMAGES_PATH . 'podcasts/',
                    ALLOWED_IMAGE_TYPES,
                    MAX_IMAGE_SIZE
                );
                if (!$upload['success']) {
                    throw new Exception($upload['message']);
                }
                $coverPath = IMAGES_PATH . 'podcasts/' . $upload['filename'];
            }

            $stmt = $db->prepare("
                INSERT INTO podcasts
                (title, slug, description, host_name, category, cover_image, language, is_featured, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $title,
                $slug,
                $description ?: null,
                $host ?: null,
                $category ?: null,
                $coverPath,
                $language ?: null,
                $isFeatured,
                $status
            ]);
            $success = 'Podcast ajouté avec succès.';
        }

        if ($action === 'create_episode') {
            $podcastId = (int) ($_POST['podcast_id'] ?? 0);
            $title = sanitizeInput($_POST['episode_title'] ?? '');
            $description = sanitizeInput($_POST['episode_description'] ?? '');
            $duration = (int) ($_POST['duration'] ?? 0);
            $seasonNumber = (int) ($_POST['season_number'] ?? 1);
            $episodeNumber = (int) ($_POST['episode_number'] ?? 1);
            $releaseDate = sanitizeInput($_POST['release_date'] ?? '');
            $status = sanitizeInput($_POST['episode_status'] ?? 'published');
            $isFeatured = isset($_POST['episode_featured']) ? 1 : 0;

            if ($podcastId <= 0 || $title === '') {
                throw new Exception('Podcast et titre obligatoires.');
            }

            $audioPath = null;
            if (!empty($_FILES['audio_file']['tmp_name'])) {
                $upload = uploadFile(
                    $_FILES['audio_file'],
                    __DIR__ . '/' . PODCAST_AUDIO_PATH,
                    ALLOWED_AUDIO_TYPES,
                    MAX_AUDIO_SIZE
                );
                if (!$upload['success']) {
                    throw new Exception($upload['message']);
                }
                $audioPath = PODCAST_AUDIO_PATH . $upload['filename'];
            }
            if (!$audioPath) {
                throw new Exception('Fichier audio requis.');
            }

            $stmt = $db->prepare("
                INSERT INTO podcast_episodes
                (podcast_id, title, description, audio_file, duration, season_number, episode_number, release_date, is_featured, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $podcastId,
                $title,
                $description ?: null,
                $audioPath,
                $duration,
                $seasonNumber,
                $episodeNumber,
                $releaseDate ?: null,
                $isFeatured,
                $status
            ]);

            $stmt = $db->prepare("
                UPDATE podcasts
                SET total_episodes = (
                    SELECT COUNT(*) FROM podcast_episodes WHERE podcast_id = ? AND status = 'published'
                )
                WHERE id = ?
            ");
            $stmt->execute([$podcastId, $podcastId]);

            $success = 'Épisode ajouté avec succès.';
        }
    } catch (Exception $e) {
        $error = GestionErreurs::messagePublic($e, 'gestion des podcasts');
    }
}

$podcasts = $db ? $db->query("
    SELECT p.*,
           (SELECT COUNT(*) FROM podcast_episodes e WHERE e.podcast_id = p.id) AS episodes_count
    FROM podcasts p
    ORDER BY p.created_at DESC
")->fetchAll() : [];

$episodes = $db ? $db->query("
    SELECT e.id, e.title, e.duration, e.release_date, e.status, p.title AS podcast_title
    FROM podcast_episodes e
    JOIN podcasts p ON e.podcast_id = p.id
    ORDER BY e.created_at DESC
    LIMIT 8
")->fetchAll() : [];

$totalPublishedEpisodes = 0;
$activePodcasts = 0;
foreach ($podcasts as $podcast) {
    $totalPublishedEpisodes += (int) ($podcast['episodes_count'] ?? 0);
    if (($podcast['status'] ?? '') === 'active') {
        $activePodcasts++;
    }
}

$adminShellMetrics = [
    ['value' => (string) count($podcasts), 'label' => 'podcasts', 'tone' => 'border-white/10 bg-white/5 text-muted'],
    ['value' => (string) $totalPublishedEpisodes, 'label' => 'épisodes', 'tone' => 'border-white/10 bg-white/5 text-muted'],
    ['value' => (string) $activePodcasts, 'label' => 'actifs', 'tone' => 'border-fuchsia-400/30 bg-fuchsia-400/10 text-fuchsia-200']
];
$dashboardSecondaryNavLabel = 'Podcasts admin';
$dashboardSecondaryNavItems = [
    ['label' => 'Aperçu', 'target' => 'podcasts-overview', 'icon' => 'home'],
    ['label' => 'Podcast', 'target' => 'podcast-create', 'icon' => 'plus'],
    ['label' => 'Épisode', 'target' => 'episode-create', 'icon' => 'wave-square'],
    ['label' => 'Catalogue', 'target' => 'podcasts-library', 'icon' => 'folder-open']
];

$additionalJS = [
    SITE_URL . '/assets/js/tailwind-shell.js',
    SITE_URL . '/assets/js/dashboard-sticky-header.js',
    SITE_URL . '/assets/js/dashboard-secondary-nav.js'
];

include 'includes/header-tailwind.php';
?>

<main class="min-h-screen bg-[radial-gradient(circle_at_top_right,rgba(47,109,224,0.18),transparent_26%),radial-gradient(circle_at_top_left,rgba(217,70,239,0.12),transparent_24%),#0B0F17] pb-16 pt-8">
    <?php include 'includes/admin-shell-header.php'; ?>

    <section id="podcasts-overview" class="py-10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="grid h-12 w-12 place-items-center rounded-2xl bg-accent/20 text-accent">
                        <i class="fas fa-podcast"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-display font-bold text-text">Gestion des podcasts</h1>
                        <p class="text-sm text-muted">Ajoutez des podcasts, épisodes et contenus audio.</p>
                    </div>
                </div>
                <a href="<?php echo SITE_URL; ?>/admin-dashboard.php" class="rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-semibold text-text">
                    <i class="fas fa-arrow-left"></i> Retour
                </a>
            </div>

            <?php if ($success): ?>
                <div class="mt-6 alert alert-success" role="alert">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="mt-6 alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <div class="mt-8 grid gap-6 lg:grid-cols-2">
                <div id="podcast-create" class="rounded-3xl border border-white/10 bg-surface/60 p-6">
                    <h2 class="text-lg font-semibold text-text"><i class="fas fa-plus"></i> Nouveau podcast</h2>
                    <form method="POST" enctype="multipart/form-data" class="mt-4 space-y-4">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="create_podcast">
                        <div>
                            <label class="text-xs font-semibold text-muted">Titre *</label>
                            <input name="title" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text" required>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="text-xs font-semibold text-muted">Animateur</label>
                                <input name="host_name" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text">
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-muted">Catégorie</label>
                                <input name="category" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text" placeholder="Culture, Musique...">
                            </div>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="text-xs font-semibold text-muted">Langue</label>
                                <input name="language" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text">
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-muted">Statut</label>
                                <select name="status" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text">
                                    <option value="active">Actif</option>
                                    <option value="inactive">Inactif</option>
                                    <option value="archived">Archivé</option>
                                </select>
                            </div>
                        </div>
                        <label class="inline-flex items-center gap-2 text-sm text-muted">
                            <input type="checkbox" name="is_featured" class="h-4 w-4 rounded border-white/20 bg-bg text-accent">
                            Mettre en avant
                        </label>
                        <div>
                            <label class="text-xs font-semibold text-muted">Description</label>
                            <textarea name="description" rows="3" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text"></textarea>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="text-xs font-semibold text-muted">Couverture (upload)</label>
                                <input type="file" name="cover_image" accept="image/*" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text">
                            </div>
                        </div>
                        <button class="rounded-full bg-accent px-5 py-2 text-sm font-semibold text-white shadow-elev-1">
                            Ajouter le podcast
                        </button>
                    </form>
                </div>

                <div id="episode-create" class="rounded-3xl border border-white/10 bg-surface/60 p-6">
                    <h2 class="text-lg font-semibold text-text"><i class="fas fa-waveform"></i> Nouvel épisode</h2>
                    <form method="POST" enctype="multipart/form-data" class="mt-4 space-y-4">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="create_episode">
                        <div>
                            <label class="text-xs font-semibold text-muted">Podcast *</label>
                            <select name="podcast_id" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text" required>
                                <option value="">Sélectionner</option>
                                <?php foreach ($podcasts as $podcast): ?>
                                    <option value="<?php echo $podcast['id']; ?>"><?php echo htmlspecialchars($podcast['title']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-muted">Titre *</label>
                            <input name="episode_title" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text" required>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="text-xs font-semibold text-muted">Saison</label>
                                <input type="number" name="season_number" min="1" value="1" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text">
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-muted">Épisode</label>
                                <input type="number" name="episode_number" min="1" value="1" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text">
                            </div>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="text-xs font-semibold text-muted">Durée (sec)</label>
                                <input type="number" name="duration" min="0" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text">
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-muted">Date de sortie</label>
                                <input type="date" name="release_date" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text">
                            </div>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-muted">Statut</label>
                            <select name="episode_status" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text">
                                <option value="published">Publié</option>
                                <option value="draft">Brouillon</option>
                                <option value="archived">Archive</option>
                            </select>
                        </div>
                        <label class="inline-flex items-center gap-2 text-sm text-muted">
                            <input type="checkbox" name="episode_featured" class="h-4 w-4 rounded border-white/20 bg-bg text-accent">
                            Épisode en avant
                        </label>
                        <div>
                            <label class="text-xs font-semibold text-muted">Description</label>
                            <textarea name="episode_description" rows="3" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text"></textarea>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="text-xs font-semibold text-muted">Audio (upload)</label>
                                <input type="file" name="audio_file" accept="audio/*" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text">
                            </div>
                        </div>
                        <button class="rounded-full bg-emerald-500 px-5 py-2 text-sm font-semibold text-white shadow-elev-1">
                            Ajouter l'épisode
                        </button>
                    </form>
                </div>
            </div>

            <div id="podcasts-library" class="mt-10 grid gap-6 lg:grid-cols-2">
                <div class="rounded-3xl border border-white/10 bg-surface/60 p-6">
                    <h3 class="text-lg font-semibold text-text">Podcasts existants</h3>
                    <div class="mt-4 space-y-3">
                        <?php if (empty($podcasts)): ?>
                            <p class="text-sm text-muted">Aucun podcast pour le moment.</p>
                        <?php else: ?>
                            <?php foreach ($podcasts as $podcast): ?>
                                <div class="flex items-center justify-between rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                                    <div>
                                        <p class="text-sm font-semibold text-text"><?php echo htmlspecialchars($podcast['title']); ?></p>
                                        <p class="text-xs text-muted"><?php echo htmlspecialchars($podcast['category'] ?? ''); ?> • <?php echo (int) $podcast['episodes_count']; ?> épisodes</p>
                                    </div>
                                    <span class="text-xs text-muted"><?php echo htmlspecialchars($podcast['status']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="rounded-3xl border border-white/10 bg-surface/60 p-6">
                    <h3 class="text-lg font-semibold text-text">Épisodes récents</h3>
                    <div class="mt-4 space-y-3">
                        <?php if (empty($episodes)): ?>
                            <p class="text-sm text-muted">Aucun épisode récent.</p>
                        <?php else: ?>
                            <?php foreach ($episodes as $episode): ?>
                                <div class="flex items-center justify-between rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                                    <div>
                                        <p class="text-sm font-semibold text-text"><?php echo htmlspecialchars($episode['title']); ?></p>
                                        <p class="text-xs text-muted"><?php echo htmlspecialchars($episode['podcast_title']); ?></p>
                                    </div>
                                    <span class="text-xs text-muted"><?php echo htmlspecialchars($episode['status']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include 'includes/footer-tailwind.php'; ?>

<?php
function ensureUniquePodcastSlug($db, $slug) {
    $base = $slug ?: 'podcast';
    $slug = $base;
    $i = 1;
    while (podcastSlugExists($db, $slug)) {
        $slug = $base . '-' . $i;
        $i++;
    }
    return $slug;
}

function podcastSlugExists($db, $slug) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM podcasts WHERE slug = ?");
    $stmt->execute([$slug]);
    return (int) $stmt->fetchColumn() > 0;
}
?>
