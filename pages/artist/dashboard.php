<?php
/**
 * Dashboard Artiste - Tchadok Platform
 */

require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Verification des droits d'acces
if (!isLoggedIn() || !isArtist()) {
    redirect(SITE_URL . '/login.php');
}

$pageTitle = 'Dashboard Artiste';
$pageDescription = 'Gerez votre musique, consultez vos statistiques et suivez vos revenus sur Tchadok.';

$artistId = $_SESSION['artist_id'];

try {
    $artist = $db->fetchOne("
        SELECT a.*, u.first_name, u.last_name, u.email, u.phone, u.profile_image
        FROM artists a
        JOIN users u ON a.user_id = u.id
        WHERE a.id = ?
    ", [$artistId]);

    $stats = $db->fetchOne("
        SELECT
            COUNT(DISTINCT t.id) as total_tracks,
            COUNT(DISTINCT alb.id) as total_albums,
            COALESCE(SUM(t.total_streams), 0) as total_streams,
            COALESCE(SUM(t.total_sales), 0) as total_sales,
            COALESCE(a.total_earnings, 0) as total_earnings,
            COUNT(DISTINCT f.user_id) as followers_count
        FROM artists a
        LEFT JOIN tracks t ON a.id = t.artist_id AND t.status = 'approved'
        LEFT JOIN albums alb ON a.id = alb.artist_id AND alb.status = 'approved'
        LEFT JOIN follows f ON a.id = f.followed_id AND f.followed_type = 'artist'
        WHERE a.id = ?
        GROUP BY a.id
    ", [$artistId]);

    $monthlyEarnings = $db->fetchAll("
        SELECT
            DATE_FORMAT(p.created_at, '%Y-%m') as month,
            SUM(p.amount - p.commission) as earnings,
            COUNT(p.id) as sales_count
        FROM purchases p
        WHERE p.artist_id = ? AND p.payment_status = 'completed'
            AND p.created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(p.created_at, '%Y-%m')
        ORDER BY month DESC
    ", [$artistId]);

    $topTracks = $db->fetchAll("
        SELECT t.*, alb.title as album_title, alb.cover_image as album_cover,
               g.name as genre_name
        FROM tracks t
        LEFT JOIN albums alb ON t.album_id = alb.id
        LEFT JOIN genres g ON t.genre_id = g.id
        WHERE t.artist_id = ? AND t.status = 'approved'
        ORDER BY t.total_streams DESC
        LIMIT 10
    ", [$artistId]);

    $recentSales = $db->fetchAll("
        SELECT p.*, t.title as track_title, alb.title as album_title,
               u.first_name, u.last_name, u.username
        FROM purchases p
        LEFT JOIN tracks t ON p.item_type = 'track' AND p.item_id = t.id
        LEFT JOIN albums alb ON p.item_type = 'album' AND p.item_id = alb.id
        JOIN users u ON p.user_id = u.id
        WHERE p.artist_id = ? AND p.payment_status = 'completed'
        ORDER BY p.created_at DESC
        LIMIT 10
    ", [$artistId]);

    $streamsByCountry = $db->fetchAll("
        SELECT s.country, COUNT(*) as streams_count
        FROM streams s
        JOIN tracks t ON s.track_id = t.id
        WHERE t.artist_id = ? AND s.country IS NOT NULL
        GROUP BY s.country
        ORDER BY streams_count DESC
        LIMIT 10
    ", [$artistId]);

    $pendingTracks = $db->fetchAll("
        SELECT t.*, alb.title as album_title
        FROM tracks t
        LEFT JOIN albums alb ON t.album_id = alb.id
        WHERE t.artist_id = ? AND t.status IN ('draft', 'pending')
        ORDER BY t.created_at DESC
    ", [$artistId]);

    $recentComments = $db->fetchAll("
        SELECT tc.*, t.title as track_title, u.first_name, u.last_name, u.username
        FROM track_comments tc
        JOIN tracks t ON tc.track_id = t.id
        JOIN users u ON tc.user_id = u.id
        WHERE t.artist_id = ? AND tc.status = 'approved'
        ORDER BY tc.created_at DESC
        LIMIT 5
    ", [$artistId]);
} catch (Exception $e) {
    logActivity(LOG_LEVEL_ERROR, 'Erreur dashboard artiste: ' . $e->getMessage());
    $stats = [
        'total_tracks' => 0,
        'total_albums' => 0,
        'total_streams' => 0,
        'total_sales' => 0,
        'total_earnings' => 0,
        'followers_count' => 0
    ];
    $monthlyEarnings = $topTracks = $recentSales = $streamsByCountry = $pendingTracks = $recentComments = [];
    $artist = [
        'stage_name' => 'Artiste',
        'profile_image' => null
    ];
}

$earningsLabels = [];
$earningsData = [];
foreach ($monthlyEarnings as $earning) {
    $earningsLabels[] = date('M Y', strtotime($earning['month'] . '-01'));
    $earningsData[] = $earning['earnings'];
}

$earningsLabels = array_reverse($earningsLabels);
$earningsData = array_reverse($earningsData);

$additionalJS = [
    'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js',
    SITE_URL . '/assets/js/artist-dashboard.js'
];

include '../../includes/header-tailwind.php';
?>

<main class="pt-24 pb-16">
    <section class="bg-bg">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-6 lg:grid-cols-[240px_1fr]">
                <aside class="rounded-3xl border border-white/10 bg-surface/60 p-5 shadow-elev-1 lg:sticky lg:top-24">
                    <div class="flex items-center gap-3">
                        <img src="<?php echo SITE_URL . '/' . ($artist['profile_image'] ?: 'assets/images/default-avatar.png'); ?>"
                             alt="<?php echo htmlspecialchars($artist['stage_name']); ?>"
                             class="h-12 w-12 rounded-2xl object-cover">
                        <div>
                            <p class="text-xs uppercase tracking-[0.2em] text-muted">Artiste</p>
                            <p class="text-sm font-semibold text-text"><?php echo htmlspecialchars($artist['stage_name']); ?></p>
                        </div>
                    </div>
                    <nav class="mt-6 space-y-2 text-sm">
                        <a class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-text" href="#overview">
                            <i class="fas fa-chart-line text-accent"></i>
                            Vue d'ensemble
                        </a>
                        <a class="flex items-center gap-3 rounded-2xl border border-white/10 px-4 py-3 text-muted hover:text-text" href="<?php echo SITE_URL; ?>/pages/artist/upload.php">
                            <i class="fas fa-upload"></i>
                            Uploads
                        </a>
                        <a class="flex items-center gap-3 rounded-2xl border border-white/10 px-4 py-3 text-muted hover:text-text" href="<?php echo SITE_URL; ?>/pages/artist/tracks.php">
                            <i class="fas fa-music"></i>
                            Mes titres
                        </a>
                        <a class="flex items-center gap-3 rounded-2xl border border-white/10 px-4 py-3 text-muted hover:text-text" href="<?php echo SITE_URL; ?>/pages/artist/albums.php">
                            <i class="fas fa-compact-disc"></i>
                            Mes albums
                        </a>
                        <a class="flex items-center gap-3 rounded-2xl border border-white/10 px-4 py-3 text-muted hover:text-text" href="<?php echo SITE_URL; ?>/pages/artist/analytics.php">
                            <i class="fas fa-chart-bar"></i>
                            Analytics
                        </a>
                        <a class="flex items-center gap-3 rounded-2xl border border-white/10 px-4 py-3 text-muted hover:text-text" href="<?php echo SITE_URL; ?>/pages/artist/earnings.php">
                            <i class="fas fa-dollar-sign"></i>
                            Revenus
                        </a>
                        <a class="flex items-center gap-3 rounded-2xl border border-white/10 px-4 py-3 text-muted hover:text-text" href="<?php echo SITE_URL; ?>/pages/artist/profile.php">
                            <i class="fas fa-user"></i>
                            Mon profil
                        </a>
                        <a class="flex items-center gap-3 rounded-2xl border border-white/10 px-4 py-3 text-muted hover:text-text" href="<?php echo SITE_URL; ?>/pages/artist/fans.php">
                            <i class="fas fa-users"></i>
                            Mes fans
                        </a>
                    </nav>
                </aside>

                <div class="space-y-6" id="overview">
                    <div class="rounded-3xl border border-white/10 bg-gradient-to-br from-slate-900/80 via-slate-800/40 to-emerald-500/10 p-6 shadow-elev-2 sm:p-8">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <p class="text-xs uppercase tracking-[0.28em] text-muted">Dashboard artiste</p>
                                <h1 class="mt-2 text-3xl font-display font-bold text-text">Bonjour, <?php echo htmlspecialchars($artist['stage_name']); ?></h1>
                                <p class="mt-2 text-sm text-muted">Apercu de vos performances musicales.</p>
                                <div class="mt-4 flex flex-wrap gap-2 text-xs text-muted">
                                    <span class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1">
                                        <i class="fas fa-users text-emerald-300"></i>
                                        <?php echo formatNumber($stats['followers_count']); ?> fans
                                    </span>
                                    <span class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1">
                                        <i class="fas fa-play text-sky-300"></i>
                                        <?php echo formatNumber($stats['total_streams']); ?> ecoutes
                                    </span>
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <a href="<?php echo SITE_URL; ?>/pages/artist/upload.php" class="inline-flex items-center gap-2 rounded-full bg-accent px-5 py-3 text-xs font-semibold text-white shadow-elev-1">
                                    <i class="fas fa-plus"></i>
                                    Nouveau titre
                                </a>
                                <a href="<?php echo SITE_URL; ?>/artist.php?id=<?php echo $artistId; ?>" class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-5 py-3 text-xs font-semibold text-text" target="_blank">
                                    <i class="fas fa-external-link-alt"></i>
                                    Profil public
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div class="rounded-3xl border border-white/10 bg-surface/60 p-5 shadow-elev-1">
                            <div class="flex items-center justify-between">
                                <span class="grid h-10 w-10 place-items-center rounded-2xl bg-accent/20 text-accent">
                                    <i class="fas fa-music"></i>
                                </span>
                                <span class="text-xs text-muted"><?php echo formatNumber($stats['total_albums']); ?> albums</span>
                            </div>
                            <p class="mt-4 text-2xl font-semibold text-text"><?php echo formatNumber($stats['total_tracks']); ?></p>
                            <p class="mt-1 text-xs text-muted">Titres approuves</p>
                        </div>
                        <div class="rounded-3xl border border-white/10 bg-surface/60 p-5 shadow-elev-1">
                            <div class="flex items-center justify-between">
                                <span class="grid h-10 w-10 place-items-center rounded-2xl bg-sky-500/20 text-sky-300">
                                    <i class="fas fa-play"></i>
                                </span>
                                <span class="text-xs text-muted"><?php echo formatNumber($stats['total_sales']); ?> ventes</span>
                            </div>
                            <p class="mt-4 text-2xl font-semibold text-text"><?php echo formatNumber($stats['total_streams']); ?></p>
                            <p class="mt-1 text-xs text-muted">Ecoutes totales</p>
                        </div>
                        <div class="rounded-3xl border border-white/10 bg-surface/60 p-5 shadow-elev-1">
                            <div class="flex items-center justify-between">
                                <span class="grid h-10 w-10 place-items-center rounded-2xl bg-emerald-500/20 text-emerald-300">
                                    <i class="fas fa-users"></i>
                                </span>
                                <span class="text-xs text-muted">Communautes</span>
                            </div>
                            <p class="mt-4 text-2xl font-semibold text-text"><?php echo formatNumber($stats['followers_count']); ?></p>
                            <p class="mt-1 text-xs text-muted">Fans actifs</p>
                        </div>
                        <div class="rounded-3xl border border-white/10 bg-surface/60 p-5 shadow-elev-1">
                            <div class="flex items-center justify-between">
                                <span class="grid h-10 w-10 place-items-center rounded-2xl bg-amber-500/20 text-amber-300">
                                    <i class="fas fa-dollar-sign"></i>
                                </span>
                                <span class="text-xs text-muted">Revenus</span>
                            </div>
                            <p class="mt-4 text-2xl font-semibold text-text"><?php echo formatPrice($stats['total_earnings']); ?></p>
                            <p class="mt-1 text-xs text-muted">Gains cumules</p>
                        </div>
                    </div>
                    <div class="grid gap-6 lg:grid-cols-3">
                        <div class="rounded-3xl border border-white/10 bg-surface/60 p-6 shadow-elev-2 lg:col-span-2">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h2 class="text-base font-semibold text-text">Evolution des revenus</h2>
                                    <p class="mt-1 text-xs text-muted">12 derniers mois</p>
                                </div>
                                <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-muted">XAF</span>
                            </div>
                            <div class="mt-5 h-64">
                                <canvas id="earningsChart"
                                        data-chart="earnings"
                                        data-labels='<?php echo json_encode($earningsLabels); ?>'
                                        data-values='<?php echo json_encode($earningsData); ?>'></canvas>
                            </div>
                        </div>

                        <div class="rounded-3xl border border-white/10 bg-surface/60 p-6 shadow-elev-2">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h2 class="text-base font-semibold text-text">Titres en attente</h2>
                                    <p class="mt-1 text-xs text-muted">Brouillons et validations</p>
                                </div>
                                <?php if (count($pendingTracks) > 0): ?>
                                    <span class="rounded-full bg-amber-400/20 px-2 py-0.5 text-xs text-amber-200"><?php echo count($pendingTracks); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="mt-5 space-y-3 text-sm">
                                <?php if (empty($pendingTracks)): ?>
                                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4 text-center text-xs text-muted">
                                        <i class="fas fa-check-circle text-emerald-300"></i>
                                        <p class="mt-2">Tous vos titres sont valides.</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($pendingTracks as $track): ?>
                                        <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                                            <p class="text-sm font-semibold text-text"><?php echo htmlspecialchars($track['title']); ?></p>
                                            <div class="mt-2 flex items-center justify-between text-xs text-muted">
                                                <span><?php echo htmlspecialchars($track['album_title'] ?: 'Single'); ?></span>
                                                <span class="rounded-full px-2 py-0.5 text-xs <?php echo $track['status'] === 'pending' ? 'bg-amber-500/10 text-amber-200' : 'bg-white/10 text-muted'; ?>">
                                                    <?php echo ucfirst($track['status']); ?>
                                                </span>
                                            </div>
                                            <a href="<?php echo SITE_URL; ?>/pages/artist/edit-track.php?id=<?php echo $track['id']; ?>" class="mt-3 inline-flex items-center gap-2 text-xs font-semibold text-accent hover:text-accent/80">
                                                <i class="fas fa-edit"></i>
                                                Modifier
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="grid gap-6 lg:grid-cols-2">
                        <div class="rounded-3xl border border-white/10 bg-surface/60 p-6 shadow-elev-2">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h2 class="text-base font-semibold text-text">Top titres</h2>
                                    <p class="mt-1 text-xs text-muted">Titres les plus ecoutes</p>
                                </div>
                                <a href="<?php echo SITE_URL; ?>/pages/artist/tracks.php" class="text-xs font-semibold text-accent hover:text-accent/80">Voir tout</a>
                            </div>
                            <div class="mt-5">
                                <?php if (empty($topTracks)): ?>
                                    <div class="rounded-2xl border border-white/10 bg-white/5 p-6 text-center text-xs text-muted">
                                        <i class="fas fa-music text-xl text-muted"></i>
                                        <p class="mt-2">Uploadez vos premiers titres.</p>
                                        <a href="<?php echo SITE_URL; ?>/pages/artist/upload.php" class="mt-3 inline-flex items-center gap-2 rounded-full bg-accent px-4 py-2 text-xs font-semibold text-white">
                                            <i class="fas fa-upload"></i>
                                            Uploader un titre
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="divide-y divide-white/10 text-sm">
                                        <?php foreach (array_slice($topTracks, 0, 5) as $index => $track): ?>
                                            <div class="flex items-center justify-between py-3">
                                                <div class="flex items-center gap-3">
                                                    <span class="grid h-8 w-8 place-items-center rounded-full bg-accent/20 text-xs font-semibold text-accent">
                                                        <?php echo $index + 1; ?>
                                                    </span>
                                                    <img src="<?php echo SITE_URL . '/' . ($track['album_cover'] ?: 'assets/images/default-cover.jpg'); ?>"
                                                         alt="<?php echo htmlspecialchars($track['title']); ?>"
                                                         class="h-10 w-10 rounded-xl object-cover">
                                                    <div>
                                                        <p class="text-sm font-semibold text-text"><?php echo htmlspecialchars($track['title']); ?></p>
                                                        <p class="text-xs text-muted"><?php echo htmlspecialchars($track['album_title'] ?: 'Single'); ?></p>
                                                    </div>
                                                </div>
                                                <div class="text-right">
                                                    <p class="text-sm font-semibold text-text"><?php echo formatNumber($track['total_streams']); ?></p>
                                                    <p class="text-xs text-muted"><?php echo formatNumber($track['total_sales']); ?> ventes</p>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="rounded-3xl border border-white/10 bg-surface/60 p-6 shadow-elev-2">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h2 class="text-base font-semibold text-text">Ventes recentes</h2>
                                    <p class="mt-1 text-xs text-muted">Dernieres transactions</p>
                                </div>
                                <a href="<?php echo SITE_URL; ?>/pages/artist/earnings.php" class="text-xs font-semibold text-accent hover:text-accent/80">Voir tout</a>
                            </div>
                            <div class="mt-5 space-y-3 text-sm">
                                <?php if (empty($recentSales)): ?>
                                    <div class="rounded-2xl border border-white/10 bg-white/5 p-6 text-center text-xs text-muted">
                                        <i class="fas fa-shopping-cart text-xl text-muted"></i>
                                        <p class="mt-2">Aucune vente pour le moment.</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach (array_slice($recentSales, 0, 5) as $sale): ?>
                                        <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                                            <div class="flex items-start justify-between gap-3">
                                                <div>
                                                    <p class="text-sm font-semibold text-text">
                                                        <?php echo htmlspecialchars($sale['track_title'] ?: $sale['album_title']); ?>
                                                    </p>
                                                    <p class="text-xs text-muted">
                                                        Par <?php echo htmlspecialchars($sale['first_name'] . ' ' . $sale['last_name']); ?>
                                                    </p>
                                                    <p class="text-xs text-muted"><?php echo timeAgo($sale['created_at']); ?></p>
                                                </div>
                                                <div class="text-right">
                                                    <p class="text-sm font-semibold text-emerald-300"><?php echo formatPrice($sale['amount']); ?></p>
                                                    <p class="text-xs text-muted">Commission: <?php echo formatPrice($sale['commission']); ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php if (!empty($recentComments)): ?>
                        <div class="rounded-3xl border border-white/10 bg-surface/60 p-6 shadow-elev-2">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h2 class="text-base font-semibold text-text">Commentaires recents</h2>
                                    <p class="mt-1 text-xs text-muted">Retours sur vos titres</p>
                                </div>
                                <a href="<?php echo SITE_URL; ?>/pages/artist/tracks.php" class="text-xs font-semibold text-accent hover:text-accent/80">Voir tout</a>
                            </div>
                            <div class="mt-5 grid gap-3 md:grid-cols-2">
                                <?php foreach ($recentComments as $comment): ?>
                                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                                        <div class="flex items-center justify-between">
                                            <p class="text-sm font-semibold text-text">
                                                <?php echo htmlspecialchars($comment['first_name'] . ' ' . $comment['last_name']); ?>
                                            </p>
                                            <?php if ($comment['rating']): ?>
                                                <div class="flex items-center gap-1 text-xs">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star <?php echo $i <= $comment['rating'] ? 'text-amber-300' : 'text-white/20'; ?>"></i>
                                                    <?php endfor; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <p class="mt-2 text-xs text-muted"><?php echo htmlspecialchars($comment['content']); ?></p>
                                        <p class="mt-3 text-xs text-muted">
                                            Sur "<?php echo htmlspecialchars($comment['track_title']); ?>" · <?php echo timeAgo($comment['created_at']); ?>
                                        </p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="rounded-3xl border border-white/10 bg-surface/60 p-6 shadow-elev-2">
                        <h2 class="text-base font-semibold text-text">Actions rapides</h2>
                        <p class="mt-1 text-xs text-muted">Acces direct a vos outils.</p>
                        <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 text-xs">
                            <a href="<?php echo SITE_URL; ?>/pages/artist/upload.php" class="rounded-2xl border border-white/10 bg-white/5 px-3 py-4 text-center font-semibold text-text hover:bg-white/10">
                                <i class="fas fa-upload mb-2 text-base text-accent"></i>
                                Uploader un titre
                            </a>
                            <a href="<?php echo SITE_URL; ?>/pages/artist/create-album.php" class="rounded-2xl border border-white/10 bg-white/5 px-3 py-4 text-center font-semibold text-text hover:bg-white/10">
                                <i class="fas fa-compact-disc mb-2 text-base text-emerald-300"></i>
                                Creer un album
                            </a>
                            <a href="<?php echo SITE_URL; ?>/pages/artist/analytics.php" class="rounded-2xl border border-white/10 bg-white/5 px-3 py-4 text-center font-semibold text-text hover:bg-white/10">
                                <i class="fas fa-chart-bar mb-2 text-base text-sky-300"></i>
                                Voir analytics
                            </a>
                            <a href="<?php echo SITE_URL; ?>/pages/artist/promote.php" class="rounded-2xl border border-white/10 bg-white/5 px-3 py-4 text-center font-semibold text-text hover:bg-white/10">
                                <i class="fas fa-bullhorn mb-2 text-base text-amber-300"></i>
                                Promouvoir
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include '../../includes/footer-tailwind.php'; ?>
