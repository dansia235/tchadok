<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . SITE_URL . '/login.php');
    exit();
}

$pageTitle = 'Gestion Radio';
$pageDescription = 'Configuration et émissions radio.';
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
        if ($action === 'save_live') {
            $streamUrl = trim($_POST['stream_url'] ?? '');
            if ($streamUrl !== '' && filter_var($streamUrl, FILTER_VALIDATE_URL) === false) {
                throw new Exception('URL de stream invalide.');
            }
            $currentShowId = !empty($_POST['current_show_id']) ? (int) $_POST['current_show_id'] : null;
            $currentTrackId = !empty($_POST['current_track_id']) ? (int) $_POST['current_track_id'] : null;
            $listeners = (int) ($_POST['listeners_count'] ?? 0);
            $isLive = isset($_POST['is_live']) ? 1 : 0;

            $existing = $db->query("SELECT id FROM radio_live ORDER BY id ASC LIMIT 1")->fetchColumn();
            if ($existing) {
                $stmt = $db->prepare("
                    UPDATE radio_live
                    SET stream_url = ?, current_show_id = ?, current_track_id = ?, listeners_count = ?, is_live = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$streamUrl ?: null, $currentShowId, $currentTrackId, $listeners, $isLive, $existing]);
            } else {
                $stmt = $db->prepare("
                    INSERT INTO radio_live (stream_url, current_show_id, current_track_id, listeners_count, is_live, updated_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$streamUrl ?: null, $currentShowId, $currentTrackId, $listeners, $isLive]);
            }
            $success = 'Configuration radio mise à jour.';
        }

        if ($action === 'create_show') {
            $title = sanitizeInput($_POST['title'] ?? '');
            $hostName = sanitizeInput($_POST['host_name'] ?? '');
            $description = sanitizeInput($_POST['description'] ?? '');
            $startTime = sanitizeInput($_POST['start_time'] ?? '');
            $endTime = sanitizeInput($_POST['end_time'] ?? '');
            $daysOfWeek = sanitizeInput($_POST['days_of_week'] ?? '');
            $status = sanitizeInput($_POST['status'] ?? 'active');
            $isFeatured = isset($_POST['is_featured']) ? 1 : 0;

            if ($title === '') {
                throw new Exception('Titre obligatoire.');
            }

            $coverPath = null;
            if (!empty($_FILES['cover_image']['tmp_name'])) {
                $upload = uploadFile(
                    $_FILES['cover_image'],
                    __DIR__ . '/' . IMAGES_PATH . 'radio/',
                    ALLOWED_IMAGE_TYPES,
                    MAX_IMAGE_SIZE
                );
                if (!$upload['success']) {
                    throw new Exception($upload['message']);
                }
                $coverPath = IMAGES_PATH . 'radio/' . $upload['filename'];
            }

            $stmt = $db->prepare("
                INSERT INTO radio_shows
                (title, description, host_name, start_time, end_time, days_of_week, cover_image, is_featured, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $title,
                $description ?: null,
                $hostName ?: null,
                $startTime ?: null,
                $endTime ?: null,
                $daysOfWeek ?: null,
                $coverPath,
                $isFeatured,
                $status
            ]);
            $success = 'Émission ajoutée avec succès.';
        }
    } catch (Exception $e) {
        $error = GestionErreurs::messagePublic($e, 'gestion de la radio');
    }
}

$shows = $db ? $db->query("SELECT * FROM radio_shows ORDER BY start_time ASC")->fetchAll() : [];
$tracks = $db ? $db->query("
    SELECT t.id, t.title, ar.stage_name
    FROM tracks t
    JOIN artists ar ON t.artist_id = ar.id
    ORDER BY t.created_at DESC
    LIMIT 200
")->fetchAll() : [];

$liveRow = $db ? $db->query("SELECT * FROM radio_live ORDER BY id ASC LIMIT 1")->fetch() : null;
$liveRow = $liveRow ?: ['stream_url' => '', 'current_show_id' => null, 'current_track_id' => null, 'listeners_count' => 0, 'is_live' => 1];
$envStreamUrl = radioMakeAbsoluteUrl(env('RADIO_STREAM_PUBLIC_URL', ''));
$dbStreamUrl = radioMakeAbsoluteUrl($liveRow['stream_url'] ?? null);
$preferEnvStream = radioEnvBool('RADIO_ENGINE_PREFER_STREAM', true);
$streamSource = 'fallback';
if ($preferEnvStream && $envStreamUrl) {
    $streamSource = 'env';
} elseif ($dbStreamUrl) {
    $streamSource = 'database';
} elseif ($envStreamUrl) {
    $streamSource = 'env';
}
$streamPreviewUrl = radioGetPublicStreamUrl($liveRow['stream_url'] ?? null);

$adminShellMetrics = [
    ['value' => (string) count($shows), 'label' => 'émissions', 'tone' => 'border-white/10 bg-white/5 text-muted'],
    ['value' => (string) ((int) ($liveRow['listeners_count'] ?? 0)), 'label' => 'auditeurs', 'tone' => 'border-white/10 bg-white/5 text-muted'],
    ['value' => !empty($liveRow['is_live']) ? 'radio active' : 'radio inactive', 'label' => '', 'tone' => !empty($liveRow['is_live']) ? 'border-emerald-400/30 bg-emerald-400/10 text-emerald-200' : 'border-amber-400/30 bg-amber-400/10 text-amber-200']
];
$dashboardSecondaryNavLabel = 'Radio admin';
$dashboardSecondaryNavItems = [
    ['label' => 'Aperçu', 'target' => 'radio-overview', 'icon' => 'home'],
    ['label' => 'Live', 'target' => 'radio-live-config', 'icon' => 'broadcast-tower'],
    ['label' => 'Émissions', 'target' => 'radio-shows', 'icon' => 'calendar-alt']
];

$additionalJS = [
    SITE_URL . '/assets/js/tailwind-shell.js',
    SITE_URL . '/assets/js/dashboard-sticky-header.js',
    SITE_URL . '/assets/js/dashboard-secondary-nav.js'
];

include 'includes/header-tailwind.php';
?>

<main class="min-h-screen bg-[radial-gradient(circle_at_top_right,rgba(47,109,224,0.18),transparent_26%),radial-gradient(circle_at_top_left,rgba(245,158,11,0.12),transparent_24%),#0B0F17] pb-16 pt-8">
    <?php include 'includes/admin-shell-header.php'; ?>

    <section id="radio-overview" class="py-12">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="grid h-12 w-12 place-items-center rounded-2xl bg-accent/20 text-accent">
                        <i class="fas fa-broadcast-tower"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-display font-bold text-text">Gestion de la radio</h1>
                        <p class="text-sm text-muted">Configurez le stream et les émissions.</p>
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
                <div id="radio-live-config" class="rounded-3xl border border-white/10 bg-surface/60 p-6">
                    <h2 class="text-lg font-semibold text-text"><i class="fas fa-signal"></i> Configuration live</h2>
                    <div class="mt-4 rounded-2xl border border-white/10 bg-white/5 p-3 text-xs text-muted">
                        <p><strong>Source active:</strong>
                            <?php if ($streamSource === 'env'): ?>
                                <span class="text-emerald-300">.env (RADIO_STREAM_PUBLIC_URL)</span>
                            <?php elseif ($streamSource === 'database'): ?>
                                <span class="text-sky-300">Base de données (radio_live.stream_url)</span>
                            <?php else: ?>
                                <span class="text-amber-300">Fallback /api/radio/stream</span>
                            <?php endif; ?>
                        </p>
                        <?php if ($envStreamUrl): ?>
                            <p class="mt-2"><strong>URL .env:</strong> <?php echo htmlspecialchars($envStreamUrl); ?></p>
                        <?php else: ?>
                            <p class="mt-2"><strong>URL .env:</strong> non définie</p>
                        <?php endif; ?>
                        <p class="mt-2"><strong>URL DB:</strong> <?php echo htmlspecialchars($dbStreamUrl ?: 'non définie'); ?></p>
                        <?php if ($preferEnvStream && $envStreamUrl): ?>
                            <p class="mt-2 text-amber-200">
                                Tant que <code>RADIO_ENGINE_PREFER_STREAM=true</code>, l'URL .env sera prioritaire.
                                Les changements ci-dessous sont bien enregistrés en base, mais non utilisés tant que cette priorité reste active.
                            </p>
                        <?php endif; ?>
                    </div>
                    <form method="POST" class="mt-4 space-y-4">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="save_live">
                        <div>
                            <label class="text-xs font-semibold text-muted">URL du stream</label>
                            <input name="stream_url" type="url" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text" value="<?php echo htmlspecialchars($liveRow['stream_url'] ?? ''); ?>" placeholder="https://stream.radio.com/...">
                            <p class="mt-2 text-xs text-muted">Cette valeur est sauvegardée dans <code>radio_live.stream_url</code>.</p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-muted">URL publique utilisée par le site</label>
                            <input type="text" readonly class="mt-2 w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-2 text-xs text-muted" value="<?php echo htmlspecialchars($streamPreviewUrl); ?>">
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="text-xs font-semibold text-muted">Émission en cours</label>
                                <select name="current_show_id" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text">
                                    <option value="">-- Aucune --</option>
                                    <?php foreach ($shows as $show): ?>
                                        <option value="<?php echo $show['id']; ?>" <?php echo ((int) $liveRow['current_show_id'] === (int) $show['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($show['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-muted">Titre en cours</label>
                                <select name="current_track_id" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text">
                                    <option value="">-- Aucun --</option>
                                    <?php foreach ($tracks as $track): ?>
                                        <option value="<?php echo $track['id']; ?>" <?php echo ((int) $liveRow['current_track_id'] === (int) $track['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($track['title'] . ' - ' . $track['stage_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="text-xs font-semibold text-muted">Auditeurs</label>
                                <input name="listeners_count" type="number" min="0" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text" value="<?php echo (int) ($liveRow['listeners_count'] ?? 0); ?>">
                            </div>
                            <label class="inline-flex items-center gap-2 text-sm text-muted">
                                <input type="checkbox" name="is_live" class="h-4 w-4 rounded border-white/20 bg-bg text-accent" <?php echo !empty($liveRow['is_live']) ? 'checked' : ''; ?>>
                                Radio active
                            </label>
                        </div>
                        <button class="rounded-full bg-emerald-500 px-5 py-2 text-sm font-semibold text-white shadow-elev-1">
                            Sauvegarder
                        </button>
                    </form>
                </div>

                <div class="rounded-3xl border border-white/10 bg-surface/60 p-6">
                    <h2 class="text-lg font-semibold text-text"><i class="fas fa-calendar-alt"></i> Nouvelle émission</h2>
                    <form method="POST" enctype="multipart/form-data" class="mt-4 space-y-4">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="create_show">
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
                                <label class="text-xs font-semibold text-muted">Statut</label>
                                <select name="status" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text">
                                    <option value="active">Actif</option>
                                    <option value="inactive">Inactif</option>
                                </select>
                            </div>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="text-xs font-semibold text-muted">Heure début</label>
                                <input name="start_time" type="time" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text">
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-muted">Heure fin</label>
                                <input name="end_time" type="time" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text">
                            </div>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-muted">Jours (ex: Lun,Mar,Mer)</label>
                            <input name="days_of_week" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text" placeholder="Lun,Mar,Mer">
                        </div>
                        <label class="inline-flex items-center gap-2 text-sm text-muted">
                            <input type="checkbox" name="is_featured" class="h-4 w-4 rounded border-white/20 bg-bg text-accent">
                            Émission phare
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
                            Ajouter l'émission
                        </button>
                    </form>
                </div>
            </div>

            <div id="radio-shows" class="mt-10 rounded-3xl border border-white/10 bg-surface/60 p-6">
                <h3 class="text-lg font-semibold text-text">Émissions programmées</h3>
                <div class="mt-4 space-y-3">
                    <?php if (empty($shows)): ?>
                        <p class="text-sm text-muted">Aucune émission programmée.</p>
                    <?php else: ?>
                        <?php foreach ($shows as $show): ?>
                            <div class="flex items-center justify-between rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                                <div>
                                    <p class="text-sm font-semibold text-text"><?php echo htmlspecialchars($show['title']); ?></p>
                                    <p class="text-xs text-muted">
                                        <?php echo htmlspecialchars($show['host_name'] ?: 'Tchadok Radio'); ?>
                                        <?php if ($show['start_time'] && $show['end_time']): ?>
                                            • <?php echo htmlspecialchars($show['start_time'] . ' - ' . $show['end_time']); ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <span class="text-xs text-muted"><?php echo htmlspecialchars($show['status']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include 'includes/footer-tailwind.php'; ?>
