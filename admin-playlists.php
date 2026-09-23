<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . SITE_URL . '/login.php');
    exit();
}

$pageTitle = 'Gestion Playlists';
$pageDescription = 'Créer et enrichir les playlists.';
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
        if ($action === 'create_playlist') {
            $userId = (int) ($_POST['user_id'] ?? 0);
            $name = sanitizeInput($_POST['name'] ?? '');
            $description = sanitizeInput($_POST['description'] ?? '');
            $visibility = $_POST['visibility'] ?? 'public';
            $isPublic = $visibility === 'public' ? 1 : 0;
            $isCollaborative = isset($_POST['is_collaborative']) ? 1 : 0;

            if ($userId <= 0 || $name === '') {
                throw new Exception('Utilisateur et nom requis.');
            }

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

            $stmt = $db->prepare("
                INSERT INTO playlists
                (user_id, name, description, cover_image, is_public, is_collaborative, total_tracks, total_duration, total_plays, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, 0, 0, 0, NOW(), NOW())
            ");
            $stmt->execute([$userId, $name, $description ?: null, $coverPath, $isPublic, $isCollaborative]);
            $success = 'Playlist créée avec succès.';
        }

        if ($action === 'add_track') {
            $playlistId = (int) ($_POST['playlist_id'] ?? 0);
            $trackId = (int) ($_POST['track_id'] ?? 0);
            if ($playlistId <= 0 || $trackId <= 0) {
                throw new Exception('Playlist et titre requis.');
            }

            $stmt = $db->prepare("SELECT COUNT(*) FROM playlist_tracks WHERE playlist_id = ? AND track_id = ?");
            $stmt->execute([$playlistId, $trackId]);
            if ((int) $stmt->fetchColumn() > 0) {
                throw new Exception('Ce titre est déjà dans la playlist.');
            }

            $stmt = $db->prepare("SELECT COALESCE(MAX(position), 0) + 1 FROM playlist_tracks WHERE playlist_id = ?");
            $stmt->execute([$playlistId]);
            $position = (int) $stmt->fetchColumn();

            $stmt = $db->prepare("INSERT INTO playlist_tracks (playlist_id, track_id, position) VALUES (?, ?, ?)");
            $stmt->execute([$playlistId, $trackId, $position]);
            recalculatePlaylistStats($db, $playlistId);
            $success = 'Titre ajouté à la playlist.';
        }
    } catch (Exception $e) {
        $error = GestionErreurs::messagePublic($e, 'gestion des playlists');
    }
}

$users = $db ? $db->query("SELECT id, username, first_name, last_name FROM users ORDER BY created_at DESC")->fetchAll() : [];
$playlists = $db ? $db->query("
    SELECT p.*, u.username
    FROM playlists p
    JOIN users u ON p.user_id = u.id
    ORDER BY p.updated_at DESC
")->fetchAll() : [];
$tracks = $db ? $db->query("
    SELECT t.id, t.title, ar.stage_name
    FROM tracks t
    JOIN artists ar ON t.artist_id = ar.id
    ORDER BY t.created_at DESC
    LIMIT 200
")->fetchAll() : [];

$totalTracksInPlaylists = 0;
$publicPlaylists = 0;
foreach ($playlists as $playlist) {
    $totalTracksInPlaylists += (int) ($playlist['total_tracks'] ?? 0);
    if (!empty($playlist['is_public'])) {
        $publicPlaylists++;
    }
}

$adminShellMetrics = [
    ['value' => (string) count($playlists), 'label' => 'playlists', 'tone' => 'border-white/10 bg-white/5 text-muted'],
    ['value' => (string) $totalTracksInPlaylists, 'label' => 'titres reliés', 'tone' => 'border-white/10 bg-white/5 text-muted'],
    ['value' => (string) $publicPlaylists, 'label' => 'publiques', 'tone' => 'border-emerald-400/30 bg-emerald-400/10 text-emerald-200']
];
$dashboardSecondaryNavLabel = 'Playlists admin';
$dashboardSecondaryNavItems = [
    ['label' => 'Aperçu', 'target' => 'playlists-overview', 'icon' => 'home'],
    ['label' => 'Création', 'target' => 'playlist-create', 'icon' => 'plus'],
    ['label' => 'Curation', 'target' => 'playlist-curation', 'icon' => 'music'],
    ['label' => 'Bibliothèque', 'target' => 'playlist-library', 'icon' => 'folder-open']
];

$additionalJS = [
    SITE_URL . '/assets/js/tailwind-shell.js',
    SITE_URL . '/assets/js/dashboard-sticky-header.js',
    SITE_URL . '/assets/js/dashboard-secondary-nav.js'
];

include 'includes/header-tailwind.php';
?>

<main class="min-h-screen bg-[radial-gradient(circle_at_top_right,rgba(47,109,224,0.18),transparent_26%),radial-gradient(circle_at_top_left,rgba(16,185,129,0.12),transparent_24%),#0B0F17] pb-16 pt-8">
    <?php include 'includes/admin-shell-header.php'; ?>

    <section id="playlists-overview" class="py-10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="grid h-12 w-12 place-items-center rounded-2xl bg-emerald-500/20 text-emerald-300">
                        <i class="fas fa-list"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-display font-bold text-text">Gestion des playlists</h1>
                        <p class="text-sm text-muted">Organisez les playlists et ajoutez des titres.</p>
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
                <div id="playlist-create" class="rounded-3xl border border-white/10 bg-surface/60 p-6">
                    <h2 class="text-lg font-semibold text-text"><i class="fas fa-plus"></i> Nouvelle playlist</h2>
                    <form method="POST" enctype="multipart/form-data" class="mt-4 space-y-4">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="create_playlist">
                        <div>
                            <label class="text-xs font-semibold text-muted">Utilisateur *</label>
                            <select name="user_id" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text" required>
                            <option value="">Sélectionner</option>
                                <?php foreach ($users as $u): ?>
                                    <option value="<?php echo $u['id']; ?>">
                                        <?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name'] . ' (@' . $u['username'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-muted">Nom *</label>
                            <input name="name" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text" required>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-muted">Description</label>
                            <textarea name="description" rows="3" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text"></textarea>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="inline-flex items-center gap-2 text-sm text-muted">
                                <input type="radio" name="visibility" value="public" class="h-4 w-4" checked>
                                Publique
                            </label>
                            <label class="inline-flex items-center gap-2 text-sm text-muted">
                                <input type="radio" name="visibility" value="private" class="h-4 w-4">
                                Privée
                            </label>
                        </div>
                        <label class="inline-flex items-center gap-2 text-sm text-muted">
                            <input type="checkbox" name="is_collaborative" class="h-4 w-4 rounded border-white/20 bg-bg text-accent">
                            Collaborative
                        </label>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="text-xs font-semibold text-muted">Couverture (upload)</label>
                                <input type="file" name="cover_image" accept="image/*" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text">
                            </div>
                        </div>
                        <button class="rounded-full bg-emerald-500 px-5 py-2 text-sm font-semibold text-white shadow-elev-1">
                            Créer la playlist
                        </button>
                    </form>
                </div>

                <div id="playlist-curation" class="rounded-3xl border border-white/10 bg-surface/60 p-6">
                    <h2 class="text-lg font-semibold text-text"><i class="fas fa-music"></i> Ajouter un titre</h2>
                    <form method="POST" class="mt-4 space-y-4">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="add_track">
                        <div>
                            <label class="text-xs font-semibold text-muted">Playlist *</label>
                            <select name="playlist_id" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text" required>
                            <option value="">Sélectionner</option>
                                <?php foreach ($playlists as $playlist): ?>
                                    <option value="<?php echo $playlist['id']; ?>">
                                        <?php echo htmlspecialchars($playlist['name'] . ' (@' . $playlist['username'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-muted">Titre *</label>
                            <select name="track_id" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text" required>
                            <option value="">Sélectionner</option>
                                <?php foreach ($tracks as $track): ?>
                                    <option value="<?php echo $track['id']; ?>">
                                        <?php echo htmlspecialchars($track['title'] . ' - ' . $track['stage_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button class="rounded-full bg-accent px-5 py-2 text-sm font-semibold text-white shadow-elev-1">
                            Ajouter le titre
                        </button>
                    </form>
                </div>
            </div>

            <div id="playlist-library" class="mt-10 rounded-3xl border border-white/10 bg-surface/60 p-6">
                <h3 class="text-lg font-semibold text-text">Playlists récentes</h3>
                <div class="mt-4 space-y-3">
                    <?php if (empty($playlists)): ?>
                        <p class="text-sm text-muted">Aucune playlist pour le moment.</p>
                    <?php else: ?>
                        <?php foreach ($playlists as $playlist): ?>
                            <div class="flex items-center justify-between rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                                <div>
                                    <p class="text-sm font-semibold text-text"><?php echo htmlspecialchars($playlist['name']); ?></p>
                                    <p class="text-xs text-muted">Par @<?php echo htmlspecialchars($playlist['username']); ?> • <?php echo (int) $playlist['total_tracks']; ?> titres</p>
                                </div>
                                <span class="text-xs text-muted"><?php echo $playlist['is_public'] ? 'Publique' : 'Privée'; ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include 'includes/footer-tailwind.php'; ?>

<?php
function recalculatePlaylistStats($db, $playlistId) {
    $stmt = $db->prepare("
        SELECT COUNT(*) AS total_tracks,
               COALESCE(SUM(t.duration), 0) AS total_duration
        FROM playlist_tracks pt
        JOIN tracks t ON pt.track_id = t.id
        WHERE pt.playlist_id = ?
    ");
    $stmt->execute([$playlistId]);
    $stats = $stmt->fetch();

    $stmt = $db->prepare("
        UPDATE playlists
        SET total_tracks = ?, total_duration = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([
        (int) ($stats['total_tracks'] ?? 0),
        (int) ($stats['total_duration'] ?? 0),
        $playlistId
    ]);
}
?>
