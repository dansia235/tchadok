<?php
/**
 * Dashboard artiste - Tchadok Platform
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';

if (!isLoggedIn() || !isArtist()) {
    header('Location: ' . SITE_URL . '/login.php');
    exit();
}

$pageTitle = 'Espace artiste';
$pageDescription = 'Pilotez votre catalogue, votre audience et vos revenus';
$hideTopNav = true;
$hideFooter = true;

$user = getCurrentUser();
$userId = (int) ($_SESSION['user_id'] ?? 0);

$dbInstance = TchadokDatabase::getInstance();
$db = $dbInstance->getConnection();

if (!$db) {
    die('Connexion base indisponible');
}

$stmt = $db->prepare("SELECT * FROM artists WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$artist = $stmt->fetch();

if (!$artist) {
    die('Profil artiste non trouve');
}

$artistId = (int) $artist['id'];
$stats = [
    'total_tracks' => 0,
    'approved_tracks' => 0,
    'pending_tracks' => 0,
    'total_albums' => 0,
    'total_streams' => 0,
    'total_downloads' => 0,
    'followers' => 0,
    'gross_revenue' => 0,
    'net_revenue' => 0
];

$recentTracks = [];
$topTracks = [];
$albums = [];
$monthlyLabels = [];
$monthlyValues = [];

try {
    $stmt = $db->prepare("SELECT COUNT(*) FROM tracks WHERE artist_id = ?");
    $stmt->execute([$artistId]);
    $stats['total_tracks'] = (int) $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM tracks WHERE artist_id = ? AND status = 'approved'");
    $stmt->execute([$artistId]);
    $stats['approved_tracks'] = (int) $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM tracks WHERE artist_id = ? AND status = 'pending'");
    $stmt->execute([$artistId]);
    $stats['pending_tracks'] = (int) $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM albums WHERE artist_id = ?");
    $stmt->execute([$artistId]);
    $stats['total_albums'] = (int) $stmt->fetchColumn();

    $stmt = $db->prepare("
        SELECT COALESCE(SUM(total_streams), 0), COALESCE(SUM(total_downloads), 0)
        FROM tracks
        WHERE artist_id = ?
    ");
    $stmt->execute([$artistId]);
    $trackRollup = $stmt->fetch(PDO::FETCH_NUM);
    $stats['total_streams'] = (int) ($trackRollup[0] ?? 0);
    $stats['total_downloads'] = (int) ($trackRollup[1] ?? 0);

    if (tableExists('follows')) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM follows WHERE followed_id = ? AND followed_type = 'artist'");
        $stmt->execute([$artistId]);
        $stats['followers'] = (int) $stmt->fetchColumn();
    }

    // DATA-05 : les revenus se lisent dans les lignes de commande payees. La table
    // purchases a disparu : elle n'etait jamais ecrite, ces chiffres etaient
    // donc toujours nuls.
    if (tableExists('order_items')) {
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(oi.unit_price * oi.quantity), 0), COALESCE(SUM(oi.artist_net), 0)
            FROM order_items oi
            JOIN orders o ON o.id = oi.order_id
            WHERE oi.artist_id = ? AND o.status = 'paid'
        ");
        $stmt->execute([$artistId]);
        $revenueRollup = $stmt->fetch(PDO::FETCH_NUM);
        $stats['gross_revenue'] = (float) ($revenueRollup[0] ?? 0);
        $stats['net_revenue'] = (float) ($revenueRollup[1] ?? 0);
    }

    $stmt = $db->prepare("
        SELECT id, title, status, total_streams, total_downloads, is_free, price, created_at
        FROM tracks
        WHERE artist_id = ?
        ORDER BY created_at DESC
        LIMIT 6
    ");
    $stmt->execute([$artistId]);
    $recentTracks = $stmt->fetchAll();

    $stmt = $db->prepare("
        SELECT id, title, total_streams, total_downloads, is_free, price
        FROM tracks
        WHERE artist_id = ?
        ORDER BY total_streams DESC, created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$artistId]);
    $topTracks = $stmt->fetchAll();

    $stmt = $db->prepare("
        SELECT id, title, type, release_date, total_tracks, total_streams
        FROM albums
        WHERE artist_id = ?
        ORDER BY release_date DESC, created_at DESC
        LIMIT 4
    ");
    $stmt->execute([$artistId]);
    $albums = $stmt->fetchAll();

    for ($i = 5; $i >= 0; $i--) {
        $monthKey = date('Y-m', strtotime("-$i months"));
        $monthlyLabels[] = date('M Y', strtotime($monthKey . '-01'));

        if (tableExists('order_items')) {
            // DATA-07 : un intervalle, pas un formatage de la colonne. Appliquer
            // une fonction a la colonne filtree interdit a MySQL d'utiliser l'index
            // `encaissees` : il parcourrait toutes les commandes de la
            // plateforme pour chacun des six mois affiches.
            $debutMois = $monthKey . '-01 00:00:00';
            $moisSuivant = date('Y-m-d H:i:s', strtotime($monthKey . '-01 +1 month'));

            $stmt = $db->prepare("
                SELECT COALESCE(SUM(oi.artist_net), 0)
                FROM order_items oi
                JOIN orders o ON o.id = oi.order_id
                WHERE oi.artist_id = ? AND o.status = 'paid'
                  AND o.paid_at >= ? AND o.paid_at < ?
            ");
            $stmt->execute([$artistId, $debutMois, $moisSuivant]);
            $monthlyValues[] = (float) $stmt->fetchColumn();
        } else {
            $monthlyValues[] = 0;
        }
    }
} catch (Exception $e) {
    $recentTracks = [];
    $topTracks = [];
    $albums = [];
    $monthlyLabels = ['Jan', 'Fev', 'Mar', 'Avr', 'Mai', 'Juin'];
    $monthlyValues = [0, 0, 0, 0, 0, 0];
}

$initials = strtoupper(substr(($artist['stage_name'] ?? 'AR'), 0, 2));
$memberSince = !empty($artist['created_at']) ? date('Y', strtotime($artist['created_at'])) : date('Y');
$artistBio = trim((string) ($artist['bio'] ?? ''));
// TAXO-02 : genres rattaches au referentiel (artist_genres), plus de texte libre.
require_once __DIR__ . '/includes/taxonomie.php';
$principal = Taxonomie::genrePrincipal((int) $artist['id']);
$genres = $principal ? (string) ($principal['name_french'] ?? $principal['name']) : '';
$country = $artist['country'] ?? ($user['country'] ?? 'Tchad');

$dashboardSecondaryNavLabel = 'Parcours artiste';
$dashboardSecondaryNavItems = [
    ['label' => 'Apercu', 'target' => 'artist-overview', 'icon' => 'home'],
    ['label' => 'KPIs', 'target' => 'artist-metrics', 'icon' => 'chart-line'],
    ['label' => 'Catalogue', 'target' => 'artist-catalog', 'icon' => 'compact-disc'],
    ['label' => 'Actions', 'target' => 'artist-actions', 'icon' => 'bolt']
];

$additionalJS = [
    'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js',
    SITE_URL . '/assets/js/dashboard-sticky-header.js',
    SITE_URL . '/assets/js/dashboard-secondary-nav.js',
    SITE_URL . '/assets/js/artist-dashboard.js'
];

include 'includes/header-tailwind.php';
?>

<main class="min-h-screen bg-[radial-gradient(circle_at_top_right,rgba(16,185,129,0.18),transparent_24%),radial-gradient(circle_at_top_left,rgba(47,109,224,0.18),transparent_28%),#0B0F17] pb-16 pt-8">
    <header class="sticky top-3 z-40 mx-auto max-w-7xl px-4 sm:px-6 lg:px-8" data-dashboard-header>
        <div class="rounded-3xl border border-white/10 bg-surface/70 p-5 shadow-elev-2 backdrop-blur transition-all duration-300" data-dashboard-header-shell>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="grid h-12 w-12 place-items-center rounded-2xl bg-emerald-500/20 text-emerald-300">
                        <i class="fas fa-wave-square text-lg"></i>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-[0.24em] text-muted transition-all duration-300" data-dashboard-header-title>Tchadok Artist Studio</p>
                        <p class="mt-1 text-sm font-semibold text-text"><?php echo htmlspecialchars($artist['stage_name']); ?></p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2 text-xs transition-all duration-300" data-dashboard-header-meta>
                    <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-muted"><?php echo formatNumber($stats['total_tracks']); ?> titres</span>
                    <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-muted"><?php echo formatNumber($stats['pending_tracks']); ?> en attente</span>
                    <span class="rounded-full border border-emerald-400/30 bg-emerald-400/10 px-3 py-1 text-emerald-200"><?php echo formatPrice($stats['net_revenue']); ?> net</span>
                </div>
            </div>

            <div class="mt-5 grid gap-3 overflow-hidden transition-all duration-300 sm:grid-cols-2 xl:grid-cols-4" data-dashboard-header-links>
                <a href="<?php echo SITE_URL; ?>/publier.php" class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-text hover:bg-white/10">
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-accent/20 text-accent"><i class="fas fa-music"></i></span>
                    Ajouter un titre
                </a>
                <a href="<?php echo SITE_URL; ?>/publier.php" class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-text hover:bg-white/10">
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-emerald-500/20 text-emerald-300"><i class="fas fa-compact-disc"></i></span>
                    Ajouter un album
                </a>
                <a href="<?php echo SITE_URL; ?>/edit-profile.php" class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-text hover:bg-white/10">
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-sky-500/20 text-sky-200"><i class="fas fa-user-pen"></i></span>
                    Profil artiste
                </a>
                <a href="<?php echo SITE_URL; ?>/contact.php" class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-text hover:bg-white/10">
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-amber-400/20 text-amber-200"><i class="fas fa-headset"></i></span>
                    Support createur
                </a>
            </div>

            <?php include 'includes/dashboard-secondary-nav.php'; ?>
        </div>
    </header>

    <section id="artist-overview" class="mx-auto mt-6 max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1.45fr)_360px]">
            <div class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2 sm:p-8">
                <div class="flex flex-wrap items-start justify-between gap-6">
                    <div class="flex items-start gap-4">
                        <div class="grid h-20 w-20 place-items-center rounded-3xl bg-accent/20 text-2xl font-semibold text-accent">
                            <?php echo htmlspecialchars($initials); ?>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-[0.28em] text-muted">Espace artiste</p>
                            <h1 class="mt-2 text-3xl font-display font-bold text-text sm:text-4xl"><?php echo htmlspecialchars($artist['stage_name']); ?></h1>
                            <p class="mt-3 max-w-2xl text-sm leading-6 text-muted">
                                Suivez la performance de vos sorties, l'etat de votre catalogue et les revenus generes par votre audience.
                            </p>
                            <div class="mt-4 flex flex-wrap gap-2 text-xs">
                                <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-muted">Artiste depuis <?php echo htmlspecialchars($memberSince); ?></span>
                                <?php if (!empty($artist['verified'])): ?>
                                    <span class="rounded-full border border-accent/30 bg-accent/10 px-3 py-1 text-accent">Profil verifie</span>
                                <?php endif; ?>
                                <?php if (!empty($artist['featured'])): ?>
                                    <span class="rounded-full border border-amber-400/30 bg-amber-400/10 px-3 py-1 text-amber-200">Mis en avant</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="grid gap-3 sm:min-w-[250px]">
                        <a href="<?php echo SITE_URL; ?>/publier.php" class="inline-flex items-center justify-center gap-2 rounded-full bg-accent px-5 py-3 text-sm font-semibold text-white shadow-elev-1">
                            <i class="fas fa-music"></i>
                            Ajouter un titre
                        </a>
                        <a href="<?php echo SITE_URL; ?>/publier.php" class="inline-flex items-center justify-center gap-2 rounded-full border border-white/10 bg-white/5 px-5 py-3 text-sm font-semibold text-text hover:bg-white/10">
                            <i class="fas fa-compact-disc"></i>
                            Ajouter un album
                        </a>
                    </div>
                </div>
            </div>

            <div class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2">
                <p class="text-xs uppercase tracking-[0.24em] text-muted">Identite artiste</p>
                <div class="mt-4 space-y-4">
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p class="text-xs uppercase tracking-[0.2em] text-muted">Nom reel</p>
                        <p class="mt-2 text-sm font-semibold text-text"><?php echo htmlspecialchars($artist['real_name'] ?: 'Non renseigne'); ?></p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p class="text-xs uppercase tracking-[0.2em] text-muted">Genres</p>
                        <p class="mt-2 text-sm font-semibold text-text"><?php echo htmlspecialchars($genres !== '' ? $genres : 'A definir'); ?></p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p class="text-xs uppercase tracking-[0.2em] text-muted">Pays</p>
                        <p class="mt-2 text-sm font-semibold text-text"><?php echo htmlspecialchars($country); ?></p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p class="text-xs uppercase tracking-[0.2em] text-muted">Bio</p>
                        <p class="mt-2 text-sm leading-6 text-muted"><?php echo htmlspecialchars($artistBio !== '' ? $artistBio : 'Ajoutez une biographie pour renforcer votre profil public.'); ?></p>
                    </div>
                    <a href="<?php echo SITE_URL; ?>/edit-profile.php" class="inline-flex w-full items-center justify-center gap-2 rounded-full border border-white/10 bg-white/5 px-5 py-3 text-sm font-semibold text-text hover:bg-white/10">
                        <i class="fas fa-user-pen"></i>
                        Mettre a jour mon profil
                    </a>
                </div>
            </div>
        </div>

        <section id="artist-metrics" class="mt-8 grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <div class="rounded-3xl border border-white/10 bg-surface/75 p-5 shadow-elev-1">
                <p class="text-xs uppercase tracking-[0.2em] text-muted">Titres</p>
                <p class="mt-4 text-3xl font-semibold text-text"><?php echo formatNumber($stats['total_tracks']); ?></p>
                <p class="mt-2 text-xs text-muted"><?php echo formatNumber($stats['approved_tracks']); ?> publies</p>
            </div>
            <div class="rounded-3xl border border-white/10 bg-surface/75 p-5 shadow-elev-1">
                <p class="text-xs uppercase tracking-[0.2em] text-muted">En revue</p>
                <p class="mt-4 text-3xl font-semibold text-text"><?php echo formatNumber($stats['pending_tracks']); ?></p>
                <p class="mt-2 text-xs text-muted">Titres pending</p>
            </div>
            <div class="rounded-3xl border border-white/10 bg-surface/75 p-5 shadow-elev-1">
                <p class="text-xs uppercase tracking-[0.2em] text-muted">Ecoutes</p>
                <p class="mt-4 text-3xl font-semibold text-text"><?php echo formatNumber($stats['total_streams']); ?></p>
                <p class="mt-2 text-xs text-muted"><?php echo formatNumber($stats['total_downloads']); ?> telechargements</p>
            </div>
            <div class="rounded-3xl border border-white/10 bg-surface/75 p-5 shadow-elev-1">
                <p class="text-xs uppercase tracking-[0.2em] text-muted">Audience</p>
                <p class="mt-4 text-3xl font-semibold text-text"><?php echo formatNumber($stats['followers']); ?></p>
                <p class="mt-2 text-xs text-muted">Abonnes artistes</p>
            </div>
            <div class="rounded-3xl border border-white/10 bg-surface/75 p-5 shadow-elev-1">
                <p class="text-xs uppercase tracking-[0.2em] text-muted">Revenu net</p>
                <p class="mt-4 text-3xl font-semibold text-text"><?php echo formatPrice($stats['net_revenue']); ?></p>
                <p class="mt-2 text-xs text-muted"><?php echo formatPrice($stats['gross_revenue']); ?> brut</p>
            </div>
        </section>

        <section id="artist-catalog" class="mt-8 grid gap-6 lg:grid-cols-[minmax(0,1.45fr)_360px]">
            <div class="space-y-6">
                <div id="artist-actions" class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold text-text">Tendance des revenus</h2>
                            <p class="mt-1 text-xs text-muted">Projection sur les 6 derniers mois.</p>
                        </div>
                        <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-muted">XAF</span>
                    </div>
                    <div class="mt-5 h-64">
                        <canvas data-chart="earnings"
                                data-labels='<?php echo json_encode($monthlyLabels); ?>'
                                data-values='<?php echo json_encode($monthlyValues); ?>'></canvas>
                    </div>
                </div>

                <div class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold text-text">Catalogue recent</h2>
                            <p class="mt-1 text-xs text-muted">Vos derniers titres et leur statut de publication.</p>
                        </div>
                        <a href="<?php echo SITE_URL; ?>/publier.php" class="text-xs font-semibold text-accent hover:text-accent/80">Publier</a>
                    </div>
                    <div class="mt-5 overflow-x-auto">
                        <table class="w-full text-left text-sm text-muted">
                            <thead class="border-b border-white/10 text-xs uppercase text-muted">
                                <tr>
                                    <th class="py-3">Titre</th>
                                    <th class="py-3">Statut</th>
                                    <th class="py-3">Ecoutes</th>
                                    <th class="py-3">Monetisation</th>
                                    <th class="py-3">Ajout</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/10">
                                <?php if (empty($recentTracks)): ?>
                                    <tr>
                                        <td colspan="5" class="py-5 text-center text-muted">Aucun titre publie pour le moment.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentTracks as $track): ?>
                                        <?php
                                            $statusClass = 'border-white/10 bg-white/5 text-muted';
                                            if (($track['status'] ?? '') === 'approved') {
                                                $statusClass = 'border-emerald-400/30 bg-emerald-400/10 text-emerald-200';
                                            } elseif (($track['status'] ?? '') === 'pending') {
                                                $statusClass = 'border-amber-400/30 bg-amber-400/10 text-amber-200';
                                            } elseif (($track['status'] ?? '') === 'rejected') {
                                                $statusClass = 'border-rose-400/30 bg-rose-400/10 text-rose-200';
                                            }
                                        ?>
                                        <tr class="hover:bg-white/5">
                                            <td class="py-3 font-semibold text-text"><?php echo htmlspecialchars($track['title']); ?></td>
                                            <td class="py-3">
                                                <span class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold <?php echo $statusClass; ?>">
                                                    <?php echo htmlspecialchars(ucfirst($track['status'])); ?>
                                                </span>
                                            </td>
                                            <td class="py-3"><?php echo formatNumber($track['total_streams']); ?></td>
                                            <td class="py-3">
                                                <?php if (!empty($track['is_free'])): ?>
                                                    <span class="inline-flex rounded-full border border-emerald-400/30 bg-emerald-400/10 px-3 py-1 text-xs font-semibold text-emerald-200">Libre</span>
                                                <?php else: ?>
                                                    <span class="inline-flex rounded-full border border-amber-400/30 bg-amber-400/10 px-3 py-1 text-xs font-semibold text-amber-200"><?php echo formatPrice($track['price']); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-3 text-xs text-muted"><?php echo !empty($track['created_at']) ? date('d/m/Y', strtotime($track['created_at'])) : '-'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2">
                    <h3 class="text-base font-semibold text-text">Top titres</h3>
                    <div class="mt-5 space-y-3">
                        <?php if (empty($topTracks)): ?>
                            <p class="text-sm text-muted">Vos meilleures performances apparaitront ici.</p>
                        <?php else: ?>
                            <?php foreach ($topTracks as $index => $track): ?>
                                <div class="flex items-center justify-between rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <span class="grid h-8 w-8 place-items-center rounded-full bg-accent/20 text-xs font-semibold text-accent"><?php echo $index + 1; ?></span>
                                        <div>
                                            <p class="text-sm font-semibold text-text"><?php echo htmlspecialchars($track['title']); ?></p>
                                            <p class="text-xs text-muted"><?php echo formatNumber($track['total_downloads']); ?> telechargements</p>
                                        </div>
                                    </div>
                                    <span class="text-xs font-semibold text-text"><?php echo formatNumber($track['total_streams']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2">
                    <h3 class="text-base font-semibold text-text">Albums et sorties</h3>
                    <div class="mt-5 space-y-3">
                        <?php if (empty($albums)): ?>
                            <div class="rounded-2xl border border-dashed border-white/10 bg-white/5 p-6 text-center">
                                <i class="fas fa-compact-disc text-2xl text-muted"></i>
                                <p class="mt-3 text-sm text-muted">Aucun album publie pour le moment.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($albums as $album): ?>
                                <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-semibold text-text"><?php echo htmlspecialchars($album['title']); ?></p>
                                            <p class="text-xs text-muted">
                                                <?php echo htmlspecialchars(strtoupper($album['type'] ?: 'album')); ?>
                                                - <?php echo formatNumber($album['total_tracks'] ?? 0); ?> titres
                                            </p>
                                        </div>
                                        <span class="text-xs text-muted"><?php echo !empty($album['release_date']) ? date('Y', strtotime($album['release_date'])) : 'A venir'; ?></span>
                                    </div>
                                    <p class="mt-2 text-xs text-muted"><?php echo formatNumber($album['total_streams'] ?? 0); ?> ecoutes</p>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2">
                    <h3 class="text-base font-semibold text-text">Actions rapides</h3>
                    <div class="mt-5 grid gap-3">
                        <a href="<?php echo SITE_URL; ?>/publier.php" class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-text hover:bg-white/10">
                            <span class="grid h-9 w-9 place-items-center rounded-xl bg-accent/20 text-accent"><i class="fas fa-wave-square"></i></span>
                            Declarer un nouveau single
                        </a>
                        <a href="<?php echo SITE_URL; ?>/publier.php" class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-text hover:bg-white/10">
                            <span class="grid h-9 w-9 place-items-center rounded-xl bg-emerald-500/20 text-emerald-300"><i class="fas fa-compact-disc"></i></span>
                            Lancer un projet
                        </a>
                        <a href="<?php echo SITE_URL; ?>/publier.php" class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-text hover:bg-white/10">
                            <span class="grid h-9 w-9 place-items-center rounded-xl bg-sky-500/20 text-sky-300"><i class="fas fa-upload"></i></span>
                            Centraliser les uploads
                        </a>
                        <a href="<?php echo SITE_URL; ?>/artiste-revenus.php" class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-text hover:bg-white/10">
                            <span class="grid h-9 w-9 place-items-center rounded-xl bg-emerald-500/20 text-emerald-300"><i class="fas fa-money-bill-transfer"></i></span>
                            Revenus et versements
                        </a>
                        <a href="<?php echo SITE_URL; ?>/artiste-dossier.php" class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-text hover:bg-white/10">
                            <span class="grid h-9 w-9 place-items-center rounded-xl bg-sky-500/20 text-sky-200"><i class="fas fa-id-card"></i></span>
                            Mon dossier artiste
                        </a>
                        <a href="<?php echo SITE_URL; ?>/artiste-signalements.php" class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-text hover:bg-white/10">
                            <span class="grid h-9 w-9 place-items-center rounded-xl bg-rose-500/20 text-rose-200"><i class="fas fa-flag"></i></span>
                            Signalements
                        </a>
                        <a href="<?php echo SITE_URL; ?>/contrat.php" class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-text hover:bg-white/10">
                            <span class="grid h-9 w-9 place-items-center rounded-xl bg-white/10 text-text"><i class="fas fa-file-signature"></i></span>
                            Contrat de distribution
                        </a>
                        <a href="<?php echo SITE_URL; ?>/artists.php" class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-text hover:bg-white/10">
                            <span class="grid h-9 w-9 place-items-center rounded-xl bg-amber-400/20 text-amber-200"><i class="fas fa-users"></i></span>
                            Observer la scene
                        </a>
                    </div>
                </div>
            </div>
        </section>
    </section>
</main>

<?php include 'includes/footer-tailwind.php'; ?>
