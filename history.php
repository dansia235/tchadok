<?php
/**
 * Historique d'ecoute - Tchadok Platform
 * Migration Tailwind (progressive)
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Verifier si l'utilisateur est connecte
if (!isLoggedIn()) {
    header('Location: ' . SITE_URL . '/login.php?redirect=history');
    exit();
}

$pageTitle = 'Historique d\'ecoute';
$pageDescription = 'Votre historique d\'ecoute musicale';

$user = getCurrentUser();

// Recuperer l'historique depuis la base de donnees
try {
    $dbInstance = TchadokDatabase::getInstance();
    $db = $dbInstance->getConnection();

    $userId = $_SESSION['user_id'];

    $history = [];
    if ($db) {
        $stmt = $db->prepare("
            SELECT s.id, s.created_at,
                   t.id AS track_id, t.title, t.duration,
                   ar.stage_name AS artist,
                   al.cover_image
            FROM streams s
            JOIN tracks t ON s.track_id = t.id
            JOIN artists ar ON s.artist_id = ar.id
            LEFT JOIN albums al ON t.album_id = al.id
            WHERE s.user_id = ?
            ORDER BY s.created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll();

        foreach ($rows as $row) {
            $history[] = [
                'id' => (int) $row['track_id'],
                'title' => $row['title'],
                'artist' => $row['artist'],
                'duration' => formatDurationShort($row['duration']),
                'played_at' => date('d/m/Y H:i', strtotime($row['created_at'])),
                'cover' => $row['cover_image'] ? SITE_URL . '/' . $row['cover_image'] : SITE_URL . '/' . DEFAULT_COVER
            ];
        }
    }
} catch (Exception $e) {
    $history = [];
}

include 'includes/header-tailwind.php';
?>

<main class="pt-24 pb-16">
    <section class="bg-bg">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-3xl border border-white/10 bg-surface/60 p-6 shadow-elev-2 sm:p-8">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex flex-wrap items-center gap-4">
                        <div class="grid h-14 w-14 place-items-center rounded-2xl bg-accent/20 text-accent">
                            <i class="fas fa-history text-2xl"></i>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-[0.2em] text-muted">Bibliotheque</p>
                            <h1 class="mt-1 text-3xl font-display font-bold text-text">Historique d'ecoute</h1>
                            <p class="mt-2 text-sm text-muted">
                                <i class="fas fa-user mr-2"></i>
                                <?php echo htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')); ?>
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-semibold text-text">
                            <i class="fas fa-filter"></i>
                            Filtrer
                        </button>
                        <button class="inline-flex items-center gap-2 rounded-full border border-rose-400/40 bg-rose-500/10 px-4 py-2 text-xs font-semibold text-rose-200">
                            <i class="fas fa-trash"></i>
                            Effacer
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="mt-8">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <?php if (empty($history)): ?>
                <div class="rounded-3xl border border-white/10 bg-surface/60 p-10 text-center shadow-elev-2">
                    <div class="mx-auto flex h-24 w-24 items-center justify-center rounded-full bg-accent/20 text-accent">
                        <i class="fas fa-music text-3xl"></i>
                    </div>
                    <h3 class="mt-5 text-xl font-semibold text-text">Aucun historique d'ecoute</h3>
                    <p class="mt-2 text-sm text-muted">
                        Commencez a ecouter de la musique tchadienne pour voir votre historique ici.
                    </p>
                    <a href="<?php echo SITE_URL; ?>/decouvrir.php" class="mt-6 inline-flex items-center justify-center rounded-full bg-accent px-6 py-3 text-sm font-semibold text-white shadow-elev-1 hover:shadow-elev-2">
                        <i class="fas fa-compass mr-2"></i>Découvrir de la musique
                    </a>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($history as $item): ?>
                        <article class="flex flex-col gap-4 rounded-3xl border border-white/10 bg-surface/60 p-4 shadow-elev-1 transition hover:border-white/20 sm:p-5 md:flex-row md:items-center">
                            <div class="relative h-16 w-16 flex-shrink-0 overflow-hidden rounded-2xl">
                                <img src="<?php echo htmlspecialchars($item['cover']); ?>" alt="Cover" class="h-full w-full object-cover">
                                <button class="absolute inset-0 grid place-items-center bg-black/60 text-white opacity-0 transition hover:opacity-100" type="button">
                                    <i class="fas fa-play"></i>
                                </button>
                            </div>
                            <div class="flex-1">
                                <h4 class="text-base font-semibold text-text"><?php echo htmlspecialchars($item['title']); ?></h4>
                                <p class="mt-1 text-sm text-muted">
                                    <i class="fas fa-user mr-1"></i>
                                    <?php echo htmlspecialchars($item['artist']); ?>
                                </p>
                            </div>
                            <div class="hidden items-center gap-2 text-xs text-muted md:flex">
                                <i class="far fa-clock"></i>
                                <span><?php echo htmlspecialchars($item['duration']); ?></span>
                            </div>
                            <div class="hidden items-center text-xs text-muted lg:flex">
                                <?php echo htmlspecialchars($item['played_at']); ?>
                            </div>
                            <div class="flex items-center gap-2">
                                <button class="grid h-9 w-9 place-items-center rounded-full border border-white/10 bg-white/5 text-text hover:bg-white/10" title="Ajouter aux favoris">
                                    <i class="far fa-heart"></i>
                                </button>
                                <button class="grid h-9 w-9 place-items-center rounded-full border border-white/10 bg-white/5 text-text hover:bg-white/10" title="Ajouter a une playlist">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php include 'includes/footer-tailwind.php'; ?>
