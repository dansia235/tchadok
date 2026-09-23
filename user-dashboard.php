<?php
/**
 * Dashboard fan - Tchadok Platform
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';

if (!isLoggedIn()) {
    header('Location: ' . SITE_URL . '/login.php?redirect=user-dashboard');
    exit();
}

if (isAdmin()) {
    header('Location: ' . SITE_URL . '/admin-dashboard.php');
    exit();
}

if (isArtist()) {
    header('Location: ' . SITE_URL . '/artist-dashboard.php');
    exit();
}

$pageTitle = 'Espace fan';
$pageDescription = 'Votre espace personnel, vos ecoutes et vos raccourcis';
$hideTopNav = true;
$hideFooter = true;

$user = getCurrentUser();
if (!$user) {
    header('Location: ' . SITE_URL . '/login.php?redirect=user-dashboard');
    exit();
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$stats = [
    'total_plays' => 0,
    'listening_hours' => 0,
    'favorite_tracks' => 0,
    'playlists_created' => 0,
    'completed_purchases' => 0,
    'total_spent' => 0,
    'wallet_balance' => (float) ($user['wallet_balance'] ?? 0),
    'loyalty_points' => (int) ($user['loyalty_points'] ?? 0),
    'subscription_type' => !empty($user['premium_status']) ? 'premium' : 'free',
    'profile_completion' => 0
];

$recentStreams = [];
$userPlaylists = [];
$favoriteArtists = [];

try {
    $dbInstance = TchadokDatabase::getInstance();
    $db = $dbInstance->getConnection();

    if ($db && tableExists('streams')) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM streams WHERE user_id = ?");
        $stmt->execute([$userId]);
        $stats['total_plays'] = (int) $stmt->fetchColumn();

        $stmt = $db->prepare("SELECT COALESCE(SUM(duration_played) / 3600, 0) FROM streams WHERE user_id = ?");
        $stmt->execute([$userId]);
        $stats['listening_hours'] = (int) round((float) $stmt->fetchColumn());

        $stmt = $db->prepare("
            SELECT t.title, ar.stage_name AS artist, t.duration, s.created_at
            FROM streams s
            JOIN tracks t ON s.track_id = t.id
            JOIN artists ar ON t.artist_id = ar.id
            WHERE s.user_id = ?
            ORDER BY s.created_at DESC
            LIMIT 5
        ");
        $stmt->execute([$userId]);
        $recentStreams = $stmt->fetchAll();
    }

    if ($db && tableExists('favorites')) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM favorites WHERE user_id = ? AND item_type = 'track'");
        $stmt->execute([$userId]);
        $stats['favorite_tracks'] = (int) $stmt->fetchColumn();
    }

    if ($db && tableExists('playlists')) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM playlists WHERE user_id = ?");
        $stmt->execute([$userId]);
        $stats['playlists_created'] = (int) $stmt->fetchColumn();

        $stmt = $db->prepare("
            SELECT id, name, total_tracks, total_duration, updated_at, is_public
            FROM playlists
            WHERE user_id = ?
            ORDER BY updated_at DESC
            LIMIT 5
        ");
        $stmt->execute([$userId]);
        $userPlaylists = $stmt->fetchAll();
    }

    // DATA-05 : les achats se comptent sur les commandes payees.
    if ($db && tableExists('orders')) {
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT o.id), COALESCE(SUM(o.total), 0)
            FROM orders o
            WHERE o.user_id = ? AND o.status = 'paid'
        ");
        $stmt->execute([$userId]);
        $purchaseRow = $stmt->fetch(PDO::FETCH_NUM);
        $stats['completed_purchases'] = (int) ($purchaseRow[0] ?? 0);
        $stats['total_spent'] = (float) ($purchaseRow[1] ?? 0);
    }

    if ($db && tableExists('follows')) {
        $stmt = $db->prepare("
            SELECT a.id, a.stage_name, COALESCE(a.total_streams, 0) AS total_streams, f.created_at
            FROM follows f
            JOIN artists a ON f.followed_id = a.id
            WHERE f.follower_id = ? AND f.followed_type = 'artist'
            ORDER BY f.created_at DESC
            LIMIT 4
        ");
        $stmt->execute([$userId]);
        $favoriteArtists = $stmt->fetchAll();
    }
} catch (Exception $e) {
    $recentStreams = [];
    $userPlaylists = [];
    $favoriteArtists = [];
}

$completionChecks = [
    !empty($user['phone']),
    !empty($user['city']),
    !empty($user['date_of_birth']),
    !empty($user['email_verified'])
];
$stats['profile_completion'] = (int) round((array_sum($completionChecks) / count($completionChecks)) * 100);

$firstName = $user['first_name'] ?? 'Fan';
$fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
if ($fullName === '') {
    $fullName = $user['username'] ?? 'Utilisateur';
}

$initialSeed = trim(($user['first_name'] ?? 'U') . ($user['last_name'] ?? ''));
$initials = strtoupper(substr($initialSeed, 0, 2));
$memberSince = !empty($user['created_at']) ? date('M Y', strtotime($user['created_at'])) : date('M Y');
$lastLogin = !empty($user['last_login']) ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Premiere session';
$premiumExpires = !empty($user['premium_expires_at']) ? date('d/m/Y', strtotime($user['premium_expires_at'])) : null;

$dashboardSecondaryNavLabel = 'Parcours fan';
$dashboardSecondaryNavItems = [
    ['label' => 'Apercu', 'target' => 'fan-overview', 'icon' => 'home'],
    ['label' => 'Stats', 'target' => 'fan-metrics', 'icon' => 'chart-line'],
    ['label' => 'Bibliotheque', 'target' => 'fan-library', 'icon' => 'folder-open'],
    ['label' => 'Actions', 'target' => 'fan-actions', 'icon' => 'bolt']
];

$additionalJS = [
    SITE_URL . '/assets/js/dashboard-sticky-header.js',
    SITE_URL . '/assets/js/dashboard-secondary-nav.js',
    SITE_URL . '/assets/js/user-dashboard.js'
];

include 'includes/header-tailwind.php';
?>

<main class="min-h-screen bg-[radial-gradient(circle_at_top_right,rgba(47,109,224,0.22),transparent_28%),radial-gradient(circle_at_top_left,rgba(16,185,129,0.14),transparent_24%),#0B0F17] pb-16 pt-8">
    <header class="sticky top-3 z-40 mx-auto max-w-7xl px-4 sm:px-6 lg:px-8" data-dashboard-header>
        <div class="rounded-3xl border border-white/10 bg-surface/70 p-5 shadow-elev-2 backdrop-blur transition-all duration-300" data-dashboard-header-shell>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="grid h-12 w-12 place-items-center rounded-2xl bg-accent/20 text-accent">
                        <i class="fas fa-headphones-alt text-lg"></i>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-[0.24em] text-muted transition-all duration-300" data-dashboard-header-title>Tchadok Fan Hub</p>
                        <p class="mt-1 text-sm font-semibold text-text"><?php echo htmlspecialchars($fullName); ?></p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2 text-xs transition-all duration-300" data-dashboard-header-meta>
                    <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-muted">Profil <?php echo $stats['profile_completion']; ?>%</span>
                    <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-muted"><?php echo formatNumber($stats['total_plays']); ?> lectures</span>
                    <span class="rounded-full border px-3 py-1 <?php echo $stats['subscription_type'] === 'premium' ? 'border-amber-400/30 bg-amber-400/10 text-amber-200' : 'border-emerald-400/30 bg-emerald-400/10 text-emerald-200'; ?>">
                        <?php echo $stats['subscription_type'] === 'premium' ? 'Premium actif' : 'Plan gratuit'; ?>
                    </span>
                </div>
            </div>

            <div class="mt-5 grid gap-3 overflow-hidden transition-all duration-300 sm:grid-cols-2 xl:grid-cols-4" data-dashboard-header-links>
                <a href="<?php echo SITE_URL; ?>/create-playlist.php" class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-text hover:bg-white/10">
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-accent/20 text-accent"><i class="fas fa-list"></i></span>
                    Nouvelle playlist
                </a>
                <a href="<?php echo SITE_URL; ?>/wallet.php" class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-text hover:bg-white/10">
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-sky-500/20 text-sky-200"><i class="fas fa-wallet"></i></span>
                    Portefeuille
                </a>
                <a href="<?php echo SITE_URL; ?>/settings.php" class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-text hover:bg-white/10">
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-emerald-500/20 text-emerald-300"><i class="fas fa-sliders-h"></i></span>
                    Mes preferences
                </a>
                <a href="<?php echo SITE_URL; ?>/contact.php" class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-text hover:bg-white/10">
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-amber-400/20 text-amber-200"><i class="fas fa-headset"></i></span>
                    Assistance
                </a>
            </div>

            <?php include 'includes/dashboard-secondary-nav.php'; ?>
        </div>
    </header>

    <section id="fan-overview" class="mx-auto mt-6 max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1.45fr)_360px]">
            <div class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2 sm:p-8">
                <div class="flex flex-wrap items-start justify-between gap-6">
                    <div class="flex items-start gap-4">
                        <div class="relative">
                            <div class="grid h-20 w-20 place-items-center rounded-3xl bg-white/10 text-2xl font-semibold text-text">
                                <?php echo htmlspecialchars($initials); ?>
                            </div>
                            <?php if ($stats['subscription_type'] === 'premium'): ?>
                                <span class="absolute -right-2 -top-2 grid h-9 w-9 place-items-center rounded-full bg-amber-400 text-bg shadow-elev-1">
                                    <i class="fas fa-crown text-sm"></i>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-[0.28em] text-muted">Espace fan</p>
                            <h1 class="mt-2 text-3xl font-display font-bold text-text sm:text-4xl">Bonjour, <?php echo htmlspecialchars($firstName); ?></h1>
                            <p class="mt-3 max-w-2xl text-sm leading-6 text-muted">
                                Votre tableau de bord met l'accent sur l'ecoute, les playlists, vos achats et la progression de votre compte.
                            </p>
                            <div class="mt-4 flex flex-wrap gap-2 text-xs">
                                <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-muted">Membre depuis <?php echo htmlspecialchars($memberSince); ?></span>
                                <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-muted">Derniere activite <?php echo htmlspecialchars($lastLogin); ?></span>
                                <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 <?php echo $stats['subscription_type'] === 'premium' ? 'text-amber-200 border-amber-400/30 bg-amber-400/10' : 'text-emerald-200 border-emerald-400/30 bg-emerald-400/10'; ?>">
                                    <?php echo $stats['subscription_type'] === 'premium' ? 'Premium actif' : 'Plan gratuit'; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="grid gap-3 sm:min-w-[250px]">
                        <a href="<?php echo SITE_URL; ?>/edit-profile.php" class="inline-flex items-center justify-center gap-2 rounded-full bg-accent px-5 py-3 text-sm font-semibold text-white shadow-elev-1">
                            <i class="fas fa-user-pen"></i>
                            Modifier mon profil
                        </a>
                        <a href="<?php echo SITE_URL; ?>/create-playlist.php" class="inline-flex items-center justify-center gap-2 rounded-full border border-white/10 bg-white/5 px-5 py-3 text-sm font-semibold text-text hover:bg-white/10">
                            <i class="fas fa-list"></i>
                            Creer une playlist
                        </a>
                    </div>
                </div>
            </div>

            <div class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2">
                <p class="text-xs uppercase tracking-[0.24em] text-muted">Etat du compte</p>
                <div class="mt-4 space-y-4">
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-muted">Completude du profil</span>
                            <span class="text-sm font-semibold text-text"><?php echo $stats['profile_completion']; ?>%</span>
                        </div>
                        <div class="mt-3 h-2 w-full rounded-full bg-white/10">
                            <div class="h-2 rounded-full bg-gradient-to-r from-accent to-emerald-400" style="width: <?php echo $stats['profile_completion']; ?>%"></div>
                        </div>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <a href="<?php echo SITE_URL; ?>/wallet.php" class="block rounded-2xl border border-white/10 bg-white/5 p-4 transition hover:bg-white/10">
                            <p class="text-xs uppercase tracking-[0.2em] text-muted">Portefeuille</p>
                            <p class="mt-2 text-xl font-semibold text-text"><?php echo formatPrice($stats['wallet_balance']); ?></p>
                        </a>
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                            <p class="text-xs uppercase tracking-[0.2em] text-muted">Points fidelite</p>
                            <p class="mt-2 text-xl font-semibold text-text"><?php echo formatNumber($stats['loyalty_points']); ?></p>
                        </div>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4 text-sm text-muted">
                        <div class="flex items-center justify-between gap-3">
                            <span>Email</span>
                            <span class="<?php echo !empty($user['email_verified']) ? 'text-emerald-300' : 'text-amber-200'; ?>">
                                <?php echo !empty($user['email_verified']) ? 'Verifie' : 'A confirmer'; ?>
                            </span>
                        </div>
                        <?php if ($premiumExpires !== null): ?>
                            <div class="mt-3 flex items-center justify-between gap-3">
                                <span>Renouvellement premium</span>
                                <span class="text-amber-200"><?php echo htmlspecialchars($premiumExpires); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($stats['subscription_type'] !== 'premium'): ?>
                        <a href="<?php echo SITE_URL; ?>/premium.php" class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-amber-400 px-5 py-3 text-sm font-semibold text-bg shadow-elev-1">
                            <i class="fas fa-crown"></i>
                            Passer a Premium
                        </a>
                    <?php else: ?>
                        <a href="<?php echo SITE_URL; ?>/premium-payment.php" class="inline-flex w-full items-center justify-center gap-2 rounded-full border border-amber-400/30 bg-amber-400/10 px-5 py-3 text-sm font-semibold text-amber-200 hover:bg-amber-400/15">
                            <i class="fas fa-sparkles"></i>
                            Gerer mes avantages
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <section id="fan-metrics" class="mt-8 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-3xl border border-white/10 bg-surface/75 p-5 shadow-elev-1">
                <p class="text-xs uppercase tracking-[0.2em] text-muted">Ecoutes</p>
                <p class="mt-4 text-3xl font-semibold text-text" data-count="<?php echo $stats['total_plays']; ?>">0</p>
                <p class="mt-2 text-xs text-muted">Lectures enregistrees</p>
            </div>
            <div class="rounded-3xl border border-white/10 bg-surface/75 p-5 shadow-elev-1">
                <p class="text-xs uppercase tracking-[0.2em] text-muted">Heures</p>
                <p class="mt-4 text-3xl font-semibold text-text" data-count="<?php echo $stats['listening_hours']; ?>">0</p>
                <p class="mt-2 text-xs text-muted">Temps d'ecoute cumule</p>
            </div>
            <div class="rounded-3xl border border-white/10 bg-surface/75 p-5 shadow-elev-1">
                <p class="text-xs uppercase tracking-[0.2em] text-muted">Favoris</p>
                <p class="mt-4 text-3xl font-semibold text-text" data-count="<?php echo $stats['favorite_tracks']; ?>">0</p>
                <p class="mt-2 text-xs text-muted">Titres sauvegardes</p>
            </div>
            <div class="rounded-3xl border border-white/10 bg-surface/75 p-5 shadow-elev-1">
                <p class="text-xs uppercase tracking-[0.2em] text-muted">Achats</p>
                <p class="mt-4 text-3xl font-semibold text-text" data-count="<?php echo $stats['completed_purchases']; ?>">0</p>
                <p class="mt-2 text-xs text-muted"><?php echo formatPrice($stats['total_spent']); ?> depenses</p>
            </div>
        </section>

        <section id="fan-library" class="mt-8 grid gap-6 lg:grid-cols-[minmax(0,1.45fr)_360px]">
            <div class="space-y-6">
                <div id="fan-actions" class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold text-text">Ecoutes recentes</h2>
                            <p class="mt-1 text-xs text-muted">Reprenez la ou vous vous etes arrete.</p>
                        </div>
                        <a href="<?php echo SITE_URL; ?>/history.php" class="text-xs font-semibold text-accent hover:text-accent/80">Historique</a>
                    </div>
                    <div class="mt-5 space-y-3">
                        <?php if (empty($recentStreams)): ?>
                            <div class="rounded-2xl border border-dashed border-white/10 bg-white/5 p-8 text-center">
                                <div class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-white/10 text-muted">
                                    <i class="fas fa-headphones text-xl"></i>
                                </div>
                                <p class="mt-4 text-sm font-semibold text-text">Aucune ecoute recente</p>
                                <p class="mt-2 text-xs text-muted">Explorez le catalogue pour lancer votre premiere session.</p>
                                <a href="<?php echo SITE_URL; ?>/decouvrir.php" class="mt-4 inline-flex items-center gap-2 rounded-full bg-accent px-5 py-2 text-xs font-semibold text-white shadow-elev-1">
                                    <i class="fas fa-compass"></i>
                                    Decouvrir
                                </a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recentStreams as $stream): ?>
                                <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                                    <div>
                                        <p class="text-sm font-semibold text-text"><?php echo htmlspecialchars($stream['title']); ?></p>
                                        <p class="text-xs text-muted"><?php echo htmlspecialchars($stream['artist']); ?></p>
                                    </div>
                                    <div class="text-right text-xs text-muted">
                                        <div><?php echo !empty($stream['created_at']) ? date('d/m H:i', strtotime($stream['created_at'])) : '-'; ?></div>
                                        <div><?php echo !empty($stream['duration']) ? formatDuration((int) $stream['duration']) : '0:00'; ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold text-text">Mes playlists</h2>
                            <p class="mt-1 text-xs text-muted">Des espaces de curation faits pour vous.</p>
                        </div>
                        <a href="<?php echo SITE_URL; ?>/create-playlist.php" class="inline-flex items-center gap-2 rounded-full border border-emerald-400/30 bg-emerald-500/10 px-4 py-2 text-xs font-semibold text-emerald-200">
                            <i class="fas fa-plus"></i>
                            Nouvelle playlist
                        </a>
                    </div>
                    <div class="mt-5 space-y-3">
                        <?php if (empty($userPlaylists)): ?>
                            <div class="rounded-2xl border border-dashed border-white/10 bg-white/5 p-8 text-center">
                                <div class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-white/10 text-muted">
                                    <i class="fas fa-list text-xl"></i>
                                </div>
                                <p class="mt-4 text-sm font-semibold text-text">Aucune playlist pour le moment</p>
                                <p class="mt-2 text-xs text-muted">Assemblez vos morceaux favoris et creez votre premiere selection.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($userPlaylists as $playlist): ?>
                                <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                                    <div>
                                        <p class="text-sm font-semibold text-text"><?php echo htmlspecialchars($playlist['name']); ?></p>
                                        <p class="text-xs text-muted">
                                            <?php echo formatNumber($playlist['total_tracks']); ?> titres
                                            - <?php echo !empty($playlist['updated_at']) ? 'maj ' . date('d/m/Y', strtotime($playlist['updated_at'])) : 'mise a jour recente'; ?>
                                        </p>
                                    </div>
                                    <div class="text-right text-xs text-muted">
                                        <div><?php echo !empty($playlist['total_duration']) ? formatDuration((int) $playlist['total_duration']) : '0:00'; ?></div>
                                        <div><?php echo !empty($playlist['is_public']) ? 'Publique' : 'Privee'; ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2">
                    <h3 class="text-base font-semibold text-text">Artistes suivis</h3>
                    <div class="mt-5 space-y-3">
                        <?php if (empty($favoriteArtists)): ?>
                            <p class="text-sm text-muted">Vous ne suivez encore aucun artiste.</p>
                        <?php else: ?>
                            <?php foreach ($favoriteArtists as $artist): ?>
                                <div class="flex items-center justify-between rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                                    <div>
                                        <p class="text-sm font-semibold text-text"><?php echo htmlspecialchars($artist['stage_name']); ?></p>
                                        <p class="text-xs text-muted"><?php echo formatNumber($artist['total_streams']); ?> ecoutes publiques</p>
                                    </div>
                                    <span class="text-xs text-muted"><?php echo !empty($artist['created_at']) ? date('d/m', strtotime($artist['created_at'])) : ''; ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2">
                    <h3 class="text-base font-semibold text-text">Actions rapides</h3>
                    <div class="mt-5 grid gap-3">
                        <a href="<?php echo SITE_URL; ?>/decouvrir.php" class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-text hover:bg-white/10">
                            <span class="grid h-9 w-9 place-items-center rounded-xl bg-accent/20 text-accent"><i class="fas fa-compass"></i></span>
                            Explorer les sorties
                        </a>
                        <a href="<?php echo SITE_URL; ?>/artists.php" class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-text hover:bg-white/10">
                            <span class="grid h-9 w-9 place-items-center rounded-xl bg-emerald-500/20 text-emerald-300"><i class="fas fa-microphone-alt"></i></span>
                            Parcourir les artistes
                        </a>
                        <a href="<?php echo SITE_URL; ?>/radio-live.php" class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-text hover:bg-white/10">
                            <span class="grid h-9 w-9 place-items-center rounded-xl bg-rose-500/20 text-rose-300"><i class="fas fa-broadcast-tower"></i></span>
                            Lancer la radio live
                        </a>
                        <a href="<?php echo SITE_URL; ?>/settings.php" class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-text hover:bg-white/10">
                            <span class="grid h-9 w-9 place-items-center rounded-xl bg-amber-400/20 text-amber-200"><i class="fas fa-gear"></i></span>
                            Regler mes preferences
                        </a>
                        <a href="<?php echo SITE_URL; ?>/wallet.php" class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-text hover:bg-white/10">
                            <span class="grid h-9 w-9 place-items-center rounded-xl bg-sky-500/20 text-sky-200"><i class="fas fa-wallet"></i></span>
                            Ouvrir mon portefeuille
                        </a>
                    </div>
                </div>

                <div class="rounded-3xl border border-white/10 bg-gradient-to-br from-white/5 to-accent/10 p-6 shadow-elev-1">
                    <p class="text-xs uppercase tracking-[0.24em] text-muted">Prochaine etape</p>
                    <p class="mt-3 text-sm font-semibold text-text">
                        <?php if ($stats['subscription_type'] === 'premium'): ?>
                            Votre profil est pret pour une experience sans interruption. Continuez a enrichir vos playlists.
                        <?php else: ?>
                            Passez en premium pour debloquer une experience plus fluide, sans pub et avec plus de controle.
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </section>
    </section>
</main>

<?php include 'includes/footer-tailwind.php'; ?>
