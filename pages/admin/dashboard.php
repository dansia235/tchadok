<?php
/**
 * Dashboard Admin - Tchadok Platform
 */

require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Verification des droits d'acces
if (!isLoggedIn() || !isAdmin()) {
    redirect(SITE_URL . '/login.php');
}

$pageTitle = 'Administration';
$pageDescription = 'Tableau de bord administrateur de Tchadok Platform.';

try {
    $stats = $db->fetchOne("
        SELECT
            (SELECT COUNT(*) FROM users WHERE is_active = 1) as total_users,
            (SELECT COUNT(*) FROM users WHERE created_at >= CURDATE()) as new_users_today,
            (SELECT COUNT(*) FROM artists WHERE is_active = 1) as total_artists,
            (SELECT COUNT(*) FROM tracks WHERE status = 'approved') as total_tracks,
            (SELECT COUNT(*) FROM tracks WHERE status = 'pending') as pending_tracks,
            (SELECT COUNT(*) FROM albums WHERE status = 'approved') as total_albums,
            (SELECT COALESCE(SUM(total_streams), 0) FROM tracks) as total_streams,
            (SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE status = 'completed') as total_revenue,
            (SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE status = 'completed' AND DATE(created_at) = CURDATE()) as today_revenue,
            (SELECT COUNT(*) FROM reports WHERE status = 'pending') as pending_reports
    ");

    $monthlyRevenue = $db->fetchAll("
        SELECT
            DATE_FORMAT(created_at, '%Y-%m') as month,
            SUM(amount) as revenue,
            COUNT(*) as transactions_count
        FROM transactions
        WHERE status = 'completed'
            AND created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month DESC
    ");

    $topArtists = $db->fetchAll("
        SELECT a.*, u.first_name, u.last_name,
               COALESCE(SUM(p.amount - p.commission), 0) as total_earnings,
               COUNT(t.id) as tracks_count,
               SUM(t.total_streams) as total_streams
        FROM artists a
        JOIN users u ON a.user_id = u.id
        LEFT JOIN purchases p ON a.id = p.artist_id AND p.payment_status = 'completed'
        LEFT JOIN tracks t ON a.id = t.artist_id AND t.status = 'approved'
        WHERE a.is_active = 1
        GROUP BY a.id
        ORDER BY total_earnings DESC
        LIMIT 10
    ");

    $recentTransactions = $db->fetchAll("
        SELECT t.*, u.first_name, u.last_name, u.username,
               tr.title as track_title, alb.title as album_title, a.stage_name
        FROM transactions t
        JOIN users u ON t.user_id = u.id
        LEFT JOIN purchases p ON t.reference = p.payment_reference
        LEFT JOIN tracks tr ON p.item_type = 'track' AND p.item_id = tr.id
        LEFT JOIN albums alb ON p.item_type = 'album' AND p.item_id = alb.id
        LEFT JOIN artists a ON p.artist_id = a.id
        ORDER BY t.created_at DESC
        LIMIT 10
    ");

    $pendingContent = [
        'tracks' => $db->fetchAll("
            SELECT t.*, a.stage_name as artist_name
            FROM tracks t
            JOIN artists a ON t.artist_id = a.id
            WHERE t.status = 'pending'
            ORDER BY t.created_at ASC
            LIMIT 5
        "),
        'albums' => $db->fetchAll("
            SELECT alb.*, a.stage_name as artist_name
            FROM albums alb
            JOIN artists a ON alb.artist_id = a.id
            WHERE alb.status = 'pending'
            ORDER BY alb.created_at ASC
            LIMIT 5
        "),
        'posts' => $db->fetchAll("
            SELECT bp.*, u.first_name, u.last_name, a.stage_name
            FROM blog_posts bp
            JOIN users u ON bp.author_id = u.id
            LEFT JOIN artists a ON u.id = a.user_id
            WHERE bp.status = 'draft'
            ORDER BY bp.created_at ASC
            LIMIT 5
        ")
    ];

    $recentReports = $db->fetchAll("
        SELECT r.*, u.first_name, u.last_name,
               CASE r.reported_type
                   WHEN 'track' THEN t.title
                   WHEN 'artist' THEN a.stage_name
                   WHEN 'user' THEN CONCAT(u2.first_name, ' ', u2.last_name)
               END as reported_item_name
        FROM reports r
        JOIN users u ON r.reporter_id = u.id
        LEFT JOIN tracks t ON r.reported_type = 'track' AND r.reported_id = t.id
        LEFT JOIN artists a ON r.reported_type = 'artist' AND r.reported_id = a.id
        LEFT JOIN users u2 ON r.reported_type = 'user' AND r.reported_id = u2.id
        WHERE r.status = 'pending'
        ORDER BY r.created_at DESC
        LIMIT 5
    ");

    $popularGenres = $db->fetchAll("
        SELECT g.name, g.color,
               COUNT(t.id) as tracks_count,
               SUM(t.total_streams) as total_streams
        FROM genres g
        LEFT JOIN tracks t ON g.id = t.genre_id AND t.status = 'approved'
        GROUP BY g.id
        ORDER BY total_streams DESC
        LIMIT 8
    ");
} catch (Exception $e) {
    logActivity(LOG_LEVEL_ERROR, 'Erreur dashboard admin: ' . $e->getMessage());
    $stats = array_fill_keys([
        'total_users',
        'new_users_today',
        'total_artists',
        'total_tracks',
        'pending_tracks',
        'total_albums',
        'total_streams',
        'total_revenue',
        'today_revenue',
        'pending_reports'
    ], 0);
    $monthlyRevenue = $topArtists = $recentTransactions = $recentReports = $popularGenres = [];
    $pendingContent = ['tracks' => [], 'albums' => [], 'posts' => []];
}

$revenueLabels = [];
$revenueData = [];
foreach ($monthlyRevenue as $revenue) {
    $revenueLabels[] = date('M Y', strtotime($revenue['month'] . '-01'));
    $revenueData[] = $revenue['revenue'];
}

$revenueLabels = array_reverse($revenueLabels);
$revenueData = array_reverse($revenueData);

$genrePalette = [
    ['border-emerald-400/30', 'bg-emerald-500/10', 'text-emerald-300'],
    ['border-sky-400/30', 'bg-sky-500/10', 'text-sky-300'],
    ['border-amber-400/30', 'bg-amber-500/10', 'text-amber-300'],
    ['border-rose-400/30', 'bg-rose-500/10', 'text-rose-300'],
    ['border-purple-400/30', 'bg-purple-500/10', 'text-purple-300'],
    ['border-teal-400/30', 'bg-teal-500/10', 'text-teal-300'],
    ['border-lime-400/30', 'bg-lime-500/10', 'text-lime-300'],
    ['border-cyan-400/30', 'bg-cyan-500/10', 'text-cyan-300']
];

$additionalJS = [
    'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js',
    SITE_URL . '/assets/js/admin-dashboard.js'
];

include '../../includes/header-tailwind.php';
?>

<main class="pt-24 pb-16">
    <section class="bg-bg">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-6 lg:grid-cols-[240px_1fr]">
                <aside class="rounded-3xl border border-white/10 bg-surface/60 p-5 shadow-elev-1 lg:sticky lg:top-24">
                    <p class="text-xs uppercase tracking-[0.28em] text-muted">Administration</p>
                    <h2 class="mt-2 text-lg font-semibold text-text">Tableau de bord</h2>
                    <nav class="mt-6 space-y-2 text-sm">
                        <a class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-text" href="#dashboard">
                            <i class="fas fa-chart-line text-accent"></i>
                            Vue generale
                        </a>
                        <a class="flex items-center gap-3 rounded-2xl border border-white/10 px-4 py-3 text-muted hover:text-text" href="<?php echo SITE_URL; ?>/pages/admin/users.php">
                            <i class="fas fa-users"></i>
                            Utilisateurs
                        </a>
                        <a class="flex items-center gap-3 rounded-2xl border border-white/10 px-4 py-3 text-muted hover:text-text" href="<?php echo SITE_URL; ?>/pages/admin/artists.php">
                            <i class="fas fa-microphone"></i>
                            Artistes
                        </a>
                        <a class="flex items-center gap-3 rounded-2xl border border-white/10 px-4 py-3 text-muted hover:text-text" href="<?php echo SITE_URL; ?>/pages/admin/content.php">
                            <i class="fas fa-music"></i>
                            Contenu
                            <?php if ($stats['pending_tracks'] > 0): ?>
                                <span class="ml-auto rounded-full bg-amber-400/20 px-2 py-0.5 text-xs text-amber-200">
                                    <?php echo $stats['pending_tracks']; ?>
                                </span>
                            <?php endif; ?>
                        </a>
                        <a class="flex items-center gap-3 rounded-2xl border border-white/10 px-4 py-3 text-muted hover:text-text" href="<?php echo SITE_URL; ?>/pages/admin/transactions.php">
                            <i class="fas fa-credit-card"></i>
                            Transactions
                        </a>
                        <a class="flex items-center gap-3 rounded-2xl border border-white/10 px-4 py-3 text-muted hover:text-text" href="<?php echo SITE_URL; ?>/pages/admin/reports.php">
                            <i class="fas fa-flag"></i>
                            Signalements
                            <?php if ($stats['pending_reports'] > 0): ?>
                                <span class="ml-auto rounded-full bg-rose-400/20 px-2 py-0.5 text-xs text-rose-200">
                                    <?php echo $stats['pending_reports']; ?>
                                </span>
                            <?php endif; ?>
                        </a>
                        <a class="flex items-center gap-3 rounded-2xl border border-white/10 px-4 py-3 text-muted hover:text-text" href="<?php echo SITE_URL; ?>/pages/admin/analytics.php">
                            <i class="fas fa-chart-bar"></i>
                            Analytics
                        </a>
                        <a class="flex items-center gap-3 rounded-2xl border border-white/10 px-4 py-3 text-muted hover:text-text" href="<?php echo SITE_URL; ?>/pages/admin/settings.php">
                            <i class="fas fa-cogs"></i>
                            Parametres
                        </a>
                    </nav>
                </aside>

                <div class="space-y-6" id="dashboard">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="text-xs uppercase tracking-[0.28em] text-muted">Administration</p>
                            <h1 class="mt-2 text-3xl font-display font-bold text-text">Tableau de bord administrateur</h1>
                            <p class="mt-2 text-sm text-muted">Vue d'ensemble de la plateforme Tchadok.</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-semibold text-text"
                                    data-action="export"
                                    data-export-url="<?php echo SITE_URL; ?>/pages/admin/export.php">
                                <i class="fas fa-download"></i>
                                Exporter
                            </button>
                            <button class="inline-flex items-center gap-2 rounded-full bg-accent px-4 py-2 text-xs font-semibold text-white shadow-elev-1"
                                    data-action="refresh">
                                <i class="fas fa-sync-alt"></i>
                                Actualiser
                            </button>
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div class="rounded-3xl border border-white/10 bg-surface/60 p-5 shadow-elev-1">
                            <div class="flex items-center justify-between">
                                <span class="grid h-10 w-10 place-items-center rounded-2xl bg-accent/20 text-accent">
                                    <i class="fas fa-users"></i>
                                </span>
                                <span class="text-xs text-emerald-300">+<?php echo $stats['new_users_today']; ?> aujourd'hui</span>
                            </div>
                            <p class="mt-4 text-2xl font-semibold text-text"><?php echo formatNumber($stats['total_users']); ?></p>
                            <p class="mt-1 text-xs text-muted">Utilisateurs actifs</p>
                        </div>
                        <div class="rounded-3xl border border-white/10 bg-surface/60 p-5 shadow-elev-1">
                            <div class="flex items-center justify-between">
                                <span class="grid h-10 w-10 place-items-center rounded-2xl bg-emerald-500/20 text-emerald-300">
                                    <i class="fas fa-microphone"></i>
                                </span>
                                <span class="text-xs text-muted"><?php echo formatNumber($stats['total_tracks']); ?> titres</span>
                            </div>
                            <p class="mt-4 text-2xl font-semibold text-text"><?php echo formatNumber($stats['total_artists']); ?></p>
                            <p class="mt-1 text-xs text-muted">Artistes verifies</p>
                        </div>
                        <div class="rounded-3xl border border-white/10 bg-surface/60 p-5 shadow-elev-1">
                            <div class="flex items-center justify-between">
                                <span class="grid h-10 w-10 place-items-center rounded-2xl bg-sky-500/20 text-sky-300">
                                    <i class="fas fa-play"></i>
                                </span>
                                <span class="text-xs text-muted"><?php echo formatNumber($stats['total_albums']); ?> albums</span>
                            </div>
                            <p class="mt-4 text-2xl font-semibold text-text"><?php echo formatNumber($stats['total_streams']); ?></p>
                            <p class="mt-1 text-xs text-muted">Ecoutes totales</p>
                        </div>
                        <div class="rounded-3xl border border-white/10 bg-surface/60 p-5 shadow-elev-1">
                            <div class="flex items-center justify-between">
                                <span class="grid h-10 w-10 place-items-center rounded-2xl bg-amber-500/20 text-amber-300">
                                    <i class="fas fa-money-bill"></i>
                                </span>
                                <span class="text-xs text-emerald-300"><?php echo formatPrice($stats['today_revenue']); ?> aujourd'hui</span>
                            </div>
                            <p class="mt-4 text-2xl font-semibold text-text"><?php echo formatPrice($stats['total_revenue']); ?></p>
                            <p class="mt-1 text-xs text-muted">Revenus totaux</p>
                        </div>
                    </div>
                    <?php if ($stats['pending_tracks'] > 0 || $stats['pending_reports'] > 0): ?>
                        <div class="rounded-3xl border border-amber-400/40 bg-amber-500/10 p-4 text-sm text-amber-200">
                            <div class="flex items-start gap-3">
                                <i class="fas fa-exclamation-triangle mt-1"></i>
                                <div>
                                    <p class="font-semibold text-amber-100">Attention requise</p>
                                    <p class="mt-1 text-xs text-amber-200/80">
                                        <?php if ($stats['pending_tracks'] > 0): ?>
                                            <a href="<?php echo SITE_URL; ?>/pages/admin/content.php" class="underline"><?php echo $stats['pending_tracks']; ?> titre(s) en attente</a>
                                        <?php endif; ?>
                                        <?php if ($stats['pending_reports'] > 0): ?>
                                            <?php if ($stats['pending_tracks'] > 0): ?> · <?php endif; ?>
                                            <a href="<?php echo SITE_URL; ?>/pages/admin/reports.php" class="underline"><?php echo $stats['pending_reports']; ?> signalement(s)</a>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
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
                                <canvas id="revenueChart"
                                        data-chart="revenue"
                                        data-labels='<?php echo json_encode($revenueLabels); ?>'
                                        data-values='<?php echo json_encode($revenueData); ?>'></canvas>
                            </div>
                        </div>

                        <div class="rounded-3xl border border-white/10 bg-surface/60 p-6 shadow-elev-2">
                            <h2 class="text-base font-semibold text-text">Moderation requise</h2>
                            <p class="mt-1 text-xs text-muted">Contenu a valider</p>
                            <div class="mt-5 space-y-3 text-sm">
                                <?php foreach ($pendingContent['tracks'] as $track): ?>
                                    <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                                        <p class="text-sm font-semibold text-text"><?php echo htmlspecialchars($track['title']); ?></p>
                                        <p class="text-xs text-muted">Par <?php echo htmlspecialchars($track['artist_name']); ?></p>
                                        <span class="mt-2 inline-flex rounded-full bg-amber-400/20 px-2 py-0.5 text-xs text-amber-200">Titre</span>
                                    </div>
                                <?php endforeach; ?>
                                <?php foreach ($pendingContent['albums'] as $album): ?>
                                    <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                                        <p class="text-sm font-semibold text-text"><?php echo htmlspecialchars($album['title']); ?></p>
                                        <p class="text-xs text-muted">Par <?php echo htmlspecialchars($album['artist_name']); ?></p>
                                        <span class="mt-2 inline-flex rounded-full bg-sky-400/20 px-2 py-0.5 text-xs text-sky-200">Album</span>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (empty($pendingContent['tracks']) && empty($pendingContent['albums'])): ?>
                                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4 text-center text-xs text-muted">
                                        <i class="fas fa-check-circle text-emerald-300"></i>
                                        <p class="mt-2">Aucun contenu en attente</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="grid gap-6 lg:grid-cols-2">
                        <div class="rounded-3xl border border-white/10 bg-surface/60 p-6 shadow-elev-2">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h2 class="text-base font-semibold text-text">Top artistes</h2>
                                    <p class="mt-1 text-xs text-muted">Classement par revenus</p>
                                </div>
                                <span class="text-xs text-muted">Top 5</span>
                            </div>
                            <div class="mt-5 divide-y divide-white/10 text-sm">
                                <?php foreach (array_slice($topArtists, 0, 5) as $index => $artist): ?>
                                    <div class="flex items-center justify-between py-3">
                                        <div class="flex items-center gap-3">
                                            <span class="grid h-8 w-8 place-items-center rounded-full bg-accent/20 text-xs font-semibold text-accent">
                                                <?php echo $index + 1; ?>
                                            </span>
                                            <img src="<?php echo SITE_URL . '/' . ($artist['profile_image'] ?: 'assets/images/default-avatar.png'); ?>"
                                                 alt="<?php echo htmlspecialchars($artist['stage_name']); ?>"
                                                 class="h-9 w-9 rounded-full object-cover">
                                            <div>
                                                <p class="text-sm font-semibold text-text"><?php echo htmlspecialchars($artist['stage_name']); ?></p>
                                                <p class="text-xs text-muted"><?php echo formatNumber($artist['tracks_count']); ?> titres</p>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-sm font-semibold text-emerald-300"><?php echo formatPrice($artist['total_earnings']); ?></p>
                                            <p class="text-xs text-muted"><?php echo formatNumber($artist['total_streams']); ?> ecoutes</p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (empty($topArtists)): ?>
                                    <p class="py-6 text-center text-xs text-muted">Aucun artiste trouve.</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="rounded-3xl border border-white/10 bg-surface/60 p-6 shadow-elev-2">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h2 class="text-base font-semibold text-text">Transactions recentes</h2>
                                    <p class="mt-1 text-xs text-muted">Dernieres activites</p>
                                </div>
                                <a href="<?php echo SITE_URL; ?>/pages/admin/transactions.php" class="text-xs font-semibold text-accent hover:text-accent/80">Voir tout</a>
                            </div>
                            <div class="mt-5 divide-y divide-white/10 text-sm">
                                <?php foreach (array_slice($recentTransactions, 0, 5) as $transaction): ?>
                                    <div class="py-3">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <p class="text-sm font-semibold text-text">
                                                    <?php echo htmlspecialchars($transaction['track_title'] ?: $transaction['album_title'] ?: $transaction['description']); ?>
                                                </p>
                                                <p class="text-xs text-muted">
                                                    <?php echo htmlspecialchars($transaction['first_name'] . ' ' . $transaction['last_name']); ?>
                                                    <?php if ($transaction['stage_name']): ?>
                                                        -> <?php echo htmlspecialchars($transaction['stage_name']); ?>
                                                    <?php endif; ?>
                                                </p>
                                                <p class="text-xs text-muted"><?php echo timeAgo($transaction['created_at']); ?></p>
                                            </div>
                                            <div class="text-right">
                                                <p class="text-sm font-semibold <?php echo $transaction['status'] === 'completed' ? 'text-emerald-300' : 'text-amber-300'; ?>">
                                                    <?php echo formatPrice($transaction['amount']); ?>
                                                </p>
                                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs <?php echo $transaction['status'] === 'completed' ? 'bg-emerald-500/10 text-emerald-200' : 'bg-amber-500/10 text-amber-200'; ?>">
                                                    <?php echo ucfirst($transaction['status']); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (empty($recentTransactions)): ?>
                                    <p class="py-6 text-center text-xs text-muted">Aucune transaction recente.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="grid gap-6 lg:grid-cols-2">
                        <?php if (!empty($recentReports)): ?>
                            <div class="rounded-3xl border border-white/10 bg-surface/60 p-6 shadow-elev-2">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h2 class="text-base font-semibold text-text">Signalements recents</h2>
                                        <p class="mt-1 text-xs text-muted">A traiter en priorite</p>
                                    </div>
                                    <a href="<?php echo SITE_URL; ?>/pages/admin/reports.php" class="text-xs font-semibold text-accent hover:text-accent/80">Voir tout</a>
                                </div>
                                <div class="mt-5 space-y-3 text-sm">
                                    <?php foreach ($recentReports as $report): ?>
                                        <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                                            <div class="flex items-start justify-between gap-3">
                                                <div>
                                                    <p class="text-sm font-semibold text-text"><?php echo htmlspecialchars($report['reported_item_name']); ?></p>
                                                    <p class="text-xs text-muted">Motif: <?php echo htmlspecialchars($report['reason']); ?></p>
                                                    <p class="text-xs text-muted">
                                                        Par <?php echo htmlspecialchars($report['first_name'] . ' ' . $report['last_name']); ?> ·
                                                        <?php echo timeAgo($report['created_at']); ?>
                                                    </p>
                                                </div>
                                                <span class="rounded-full px-2 py-0.5 text-xs <?php echo $report['reported_type'] === 'track' ? 'bg-amber-500/10 text-amber-200' : 'bg-rose-500/10 text-rose-200'; ?>">
                                                    <?php echo ucfirst($report['reported_type']); ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="rounded-3xl border border-white/10 bg-surface/60 p-6 shadow-elev-2">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h2 class="text-base font-semibold text-text">Genres populaires</h2>
                                    <p class="mt-1 text-xs text-muted">Classement par ecoutes</p>
                                </div>
                                <span class="text-xs text-muted">Top 8</span>
                            </div>
                            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                                <?php foreach ($popularGenres as $index => $genre): ?>
                                    <?php $palette = $genrePalette[$index % count($genrePalette)]; ?>
                                    <div class="rounded-2xl border px-4 py-3 text-sm <?php echo implode(' ', $palette); ?>">
                                        <p class="text-sm font-semibold"><?php echo htmlspecialchars($genre['name']); ?></p>
                                        <p class="mt-1 text-xs text-muted">
                                            <?php echo formatNumber($genre['tracks_count']); ?> titres · <?php echo formatNumber($genre['total_streams']); ?> ecoutes
                                        </p>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (empty($popularGenres)): ?>
                                    <p class="text-xs text-muted">Aucun genre disponible.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="rounded-3xl border border-white/10 bg-surface/60 p-6 shadow-elev-2">
                        <h2 class="text-base font-semibold text-text">Actions rapides</h2>
                        <p class="mt-1 text-xs text-muted">Acces direct aux operations frequentes.</p>
                        <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-6 text-xs">
                            <a href="<?php echo SITE_URL; ?>/pages/admin/content.php" class="rounded-2xl border border-white/10 bg-white/5 px-3 py-4 text-center font-semibold text-text hover:bg-white/10">
                                <i class="fas fa-check mb-2 text-base text-accent"></i>
                                Moderer
                            </a>
                            <a href="<?php echo SITE_URL; ?>/pages/admin/users.php" class="rounded-2xl border border-white/10 bg-white/5 px-3 py-4 text-center font-semibold text-text hover:bg-white/10">
                                <i class="fas fa-users mb-2 text-base text-emerald-300"></i>
                                Utilisateurs
                            </a>
                            <a href="<?php echo SITE_URL; ?>/pages/admin/reports.php" class="rounded-2xl border border-white/10 bg-white/5 px-3 py-4 text-center font-semibold text-text hover:bg-white/10">
                                <i class="fas fa-flag mb-2 text-base text-rose-300"></i>
                                Signalements
                            </a>
                            <a href="<?php echo SITE_URL; ?>/pages/admin/analytics.php" class="rounded-2xl border border-white/10 bg-white/5 px-3 py-4 text-center font-semibold text-text hover:bg-white/10">
                                <i class="fas fa-chart-bar mb-2 text-base text-sky-300"></i>
                                Analytics
                            </a>
                            <a href="<?php echo SITE_URL; ?>/pages/admin/backup.php" class="rounded-2xl border border-white/10 bg-white/5 px-3 py-4 text-center font-semibold text-text hover:bg-white/10">
                                <i class="fas fa-database mb-2 text-base text-amber-300"></i>
                                Backups
                            </a>
                            <a href="<?php echo SITE_URL; ?>/pages/admin/settings.php" class="rounded-2xl border border-white/10 bg-white/5 px-3 py-4 text-center font-semibold text-text hover:bg-white/10">
                                <i class="fas fa-cogs mb-2 text-base text-muted"></i>
                                Parametres
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include '../../includes/footer-tailwind.php'; ?>
