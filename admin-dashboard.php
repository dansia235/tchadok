<?php
/**
 * Dashboard Admin - Tchadok Platform
 * Migration Tailwind (progressive)
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';

// SEC-19 : l'acces depend d'une permission nommee, verifiee cote serveur.
// Masquer l'entree de menu ne protege rien : l'adresse se tape.
Autorisations::exiger('admin.acces');

$pageTitle = 'Administration';
$pageDescription = "Panneau d'administration Tchadok";
$hideTopNav = true;
$hideFooter = true;

$user = getCurrentUser();
$management_users = [];
$dashboardSecondaryNavLabel = 'Parcours admin';
$dashboardSecondaryNavItems = [
    ['label' => 'Console', 'target' => 'admin-overview', 'icon' => 'home'],
    ['label' => 'Modules', 'target' => 'admin-modules', 'icon' => 'th-large'],
    ['label' => 'KPIs', 'target' => 'admin-kpis', 'icon' => 'chart-line'],
    ['label' => 'Utilisateurs', 'target' => 'gestion-utilisateurs', 'icon' => 'users']
];
$admin_modules = [
    [
        'title' => 'Utilisateurs',
        'description' => 'Créer, modifier, supprimer et superviser les comptes.',
        'href' => '#gestion-utilisateurs',
        'icon' => 'fa-users',
        'accent' => 'text-accent'
    ],
    [
        'title' => 'Musique',
        'description' => 'Ajouter des titres et structurer les sorties.',
        'href' => SITE_URL . '/admin-add-song.php',
        'icon' => 'fa-music',
        'accent' => 'text-emerald-200'
    ],
    [
        'title' => 'Albums',
        'description' => 'Construire les projets et les catalogues.',
        'href' => SITE_URL . '/admin-add-album.php',
        'icon' => 'fa-compact-disc',
        'accent' => 'text-sky-200'
    ],
    [
        'title' => 'Radio',
        'description' => 'Piloter la diffusion live et la programmation.',
        'href' => SITE_URL . '/admin-manage-radio.php',
        'icon' => 'fa-broadcast-tower',
        'accent' => 'text-amber-200'
    ],
    [
        'title' => 'Playlists',
        'description' => 'Administrer la curation et les selections.',
        'href' => SITE_URL . '/admin-playlists.php',
        'icon' => 'fa-list',
        'accent' => 'text-emerald-200'
    ],
    [
        'title' => 'Podcasts',
        'description' => 'Gérer les émissions et leurs épisodes.',
        'href' => SITE_URL . '/admin-podcasts.php',
        'icon' => 'fa-podcast',
        'accent' => 'text-cyan-200'
    ],
    [
        'title' => 'Blog',
        'description' => 'Animer le studio editorial de la plateforme.',
        'href' => SITE_URL . '/admin-blog.php',
        'icon' => 'fa-newspaper',
        'accent' => 'text-sky-200'
    ]
];

// DATA-04 et SEC-19 : deux ecrans reserves a une permission nommee. Sans ces
// entrees, ils n'etaient joignables qu'en tapant leur adresse.
if (Autorisations::peut('tarif.modifier')) {
    $admin_modules[] = [
        'title' => 'Tarifs',
        'description' => 'Planchers, prix suggeres et commission.',
        'href' => SITE_URL . '/admin/tarifs.php',
        'icon' => 'fa-tags',
        'accent' => 'text-amber-200'
    ];
}

if (Autorisations::peut('journal.lire')) {
    $admin_modules[] = [
        'title' => 'Journal d\'audit',
        'description' => 'Qui a fait quoi, et quand.',
        'href' => SITE_URL . '/admin/journal.php',
        'icon' => 'fa-clipboard-list',
        'accent' => 'text-rose-200'
    ];
}

try {
    $dbInstance = TchadokDatabase::getInstance();
    $db = $dbInstance->getConnection();

    $stats = [
        'total_users' => $db->query("SELECT COUNT(*) FROM users")->fetchColumn(),
        'active_users' => $db->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn(),
        'verified_users' => $db->query("SELECT COUNT(*) FROM users WHERE email_verified = 1")->fetchColumn(),
        'total_artists' => $db->query("SELECT COUNT(*) FROM artists")->fetchColumn(),
        'total_tracks' => $db->query("SELECT COUNT(*) FROM tracks")->fetchColumn(),
        'total_albums' => $db->query("SELECT COUNT(*) FROM albums")->fetchColumn(),
        'total_streams' => $db->query("SELECT COALESCE(SUM(total_streams), 0) FROM tracks")->fetchColumn(),
        'premium_users' => $db->query("SELECT COUNT(*) FROM users WHERE premium_status = 1")->fetchColumn(),
        'total_playlists' => $db->query("SELECT COUNT(*) FROM playlists WHERE deleted_at IS NULL")->fetchColumn(),
        'total_revenue' => $db->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE status = 'completed'")->fetchColumn(),
        'total_podcasts' => tableExists('podcasts') ? $db->query("SELECT COUNT(*) FROM podcasts")->fetchColumn() : 0,
        'total_podcast_episodes' => tableExists('podcast_episodes') ? $db->query("SELECT COUNT(*) FROM podcast_episodes")->fetchColumn() : 0,
        'monthly_streams' => tableExists('streams') ? $db->query("SELECT COUNT(*) FROM streams WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn() : 0
    ];

    $stats['new_users_30d'] = $db->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();

    $management_users = $db->query("
        SELECT u.id, u.username, u.email, u.first_name, u.last_name, u.phone, u.country, u.city,
               u.created_at, u.last_login, u.premium_status, u.email_verified, u.is_active,
               CASE
                   WHEN ad.user_id IS NOT NULL THEN 'admin'
                   WHEN ar.user_id IS NOT NULL THEN 'artist'
                   ELSE 'fan'
               END AS user_type
        FROM users u
        LEFT JOIN admins ad ON ad.user_id = u.id
        LEFT JOIN artists ar ON ar.user_id = u.id
        ORDER BY u.created_at DESC
        LIMIT 15
    ")->fetchAll();

    $top_artists = $db->query("
        SELECT id, stage_name, total_streams
        FROM artists
        ORDER BY total_streams DESC
        LIMIT 10
    ")->fetchAll();

    $top_songs = $db->query("
        SELECT t.id, t.title, a.stage_name as artist_name, t.total_streams, t.is_free, t.price
        FROM tracks t
        JOIN artists a ON t.artist_id = a.id
        ORDER BY t.total_streams DESC
        LIMIT 10
    ")->fetchAll();

    $recent_transactions = $db->query("
        SELECT t.id, t.amount, t.currency, t.type, t.status, t.created_at,
               u.username, u.first_name, u.last_name
        FROM transactions t
        LEFT JOIN users u ON t.user_id = u.id
        ORDER BY t.created_at DESC
        LIMIT 10
    ")->fetchAll();

} catch (Exception $e) {
    $stats = [
        'total_users' => 0,
        'active_users' => 0,
        'verified_users' => 0,
        'total_artists' => 0,
        'total_tracks' => 0,
        'total_albums' => 0,
        'total_streams' => 0,
        'premium_users' => 0,
        'total_playlists' => 0,
        'total_revenue' => 0,
        'new_users_30d' => 0,
        'total_podcasts' => 0,
        'total_podcast_episodes' => 0,
        'monthly_streams' => 0
    ];
    $management_users = [];
    $top_artists = [];
    $top_songs = [];
    $recent_transactions = [];
}

$adminPendingVerifications = max(0, (int) $stats['total_users'] - (int) $stats['verified_users']);
$adminInactiveUsers = max(0, (int) $stats['total_users'] - (int) $stats['active_users']);
$adminCatalogVolume = (int) $stats['total_tracks'] + (int) $stats['total_albums'] + (int) $stats['total_podcasts'];
$adminContentOperations = (int) $stats['total_playlists'] + (int) $stats['total_podcast_episodes'];
$adminModuleSnapshots = [
    'Utilisateurs' => number_format($stats['total_users']) . ' comptes',
    'Musique' => number_format($stats['total_tracks']) . ' titres',
    'Albums' => number_format($stats['total_albums']) . ' projets',
    'Radio' => number_format($stats['monthly_streams']) . ' streams / 30j',
    'Playlists' => number_format($stats['total_playlists']) . ' playlists',
    'Podcasts' => number_format($stats['total_podcasts']) . ' series',
    'Blog' => 'studio editorial'
];

$adminShellMetrics = [
    ['value' => number_format($stats['total_users']), 'label' => 'utilisateurs', 'tone' => 'border-white/10 bg-white/5 text-muted'],
    ['value' => number_format($stats['monthly_streams']), 'label' => 'streams 30j', 'tone' => 'border-white/10 bg-white/5 text-muted'],
    ['value' => number_format((float) $stats['total_revenue'], 0, ',', ' '), 'label' => 'XAF', 'tone' => 'border-amber-400/30 bg-amber-400/10 text-amber-200']
];

$additionalJS = [
    SITE_URL . '/assets/js/tailwind-shell.js',
    SITE_URL . '/assets/js/dashboard-sticky-header.js',
    SITE_URL . '/assets/js/dashboard-secondary-nav.js',
    SITE_URL . '/assets/js/admin-panel.js'
];

include 'includes/header-tailwind.php';
?>

<main class="min-h-screen bg-[radial-gradient(circle_at_top_right,rgba(47,109,224,0.18),transparent_26%),radial-gradient(circle_at_top_left,rgba(245,158,11,0.12),transparent_24%),#0B0F17] pb-16 pt-8">
    <?php include 'includes/admin-shell-header.php'; ?>

    <section id="admin-overview" class="pb-6 pt-6" aria-label="Aperçu administration">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_360px]">
                <div class="rounded-3xl border border-white/10 bg-surface/60 p-6 shadow-elev-1">
                    <p class="text-xs uppercase tracking-[0.24em] text-muted">Console de pilotage</p>
                    <h1 class="mt-2 text-2xl font-display font-semibold text-text">Centre de pilotage Tchadok</h1>
                    <p class="mt-3 max-w-3xl text-sm text-muted">
                        Suivez les indicateurs clés, ouvrez les modules critiques et pilotez les opérations depuis une interface unique. Cette page centralise l'état de la plateforme sans dupliquer les accès.
                    </p>

                    <div class="mt-5 flex flex-wrap gap-2 text-xs">
                        <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-muted"><?php echo number_format($stats['active_users']); ?> comptes actifs</span>
                        <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-muted"><?php echo number_format($adminPendingVerifications); ?> vérifications en attente</span>
                        <span class="rounded-full border border-amber-400/30 bg-amber-400/10 px-3 py-1 text-amber-200"><?php echo number_format($stats['premium_users']); ?> premium</span>
                    </div>

                    <div class="mt-6 grid gap-4 md:grid-cols-3">
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                            <p class="text-xs uppercase tracking-[0.2em] text-muted">Utilisateurs actifs</p>
                            <p class="mt-3 text-2xl font-semibold text-text"><?php echo number_format($stats['active_users']); ?></p>
                            <p class="mt-2 text-xs text-muted"><?php echo number_format($adminInactiveUsers); ?> comptes inactifs</p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                            <p class="text-xs uppercase tracking-[0.2em] text-muted">Volume catalogue</p>
                            <p class="mt-3 text-2xl font-semibold text-text"><?php echo number_format($adminCatalogVolume); ?></p>
                            <p class="mt-2 text-xs text-muted"><?php echo number_format($stats['total_tracks']); ?> titres, <?php echo number_format($stats['total_albums']); ?> albums, <?php echo number_format($stats['total_podcasts']); ?> podcasts</p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                            <p class="text-xs uppercase tracking-[0.2em] text-muted">Revenu confirme</p>
                            <p class="mt-3 text-2xl font-semibold text-text"><?php echo number_format($stats['total_revenue'], 0, ',', ' '); ?> <span class="text-xs text-muted">XAF</span></p>
                            <p class="mt-2 text-xs text-muted">Suivi des paiements valides</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-3xl border border-white/10 bg-surface/60 p-6 shadow-elev-1">
                    <p class="text-xs uppercase tracking-[0.24em] text-muted">Priorites operationnelles</p>
                    <div class="mt-4 space-y-3">
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                            <p class="text-sm font-semibold text-text"><?php echo number_format($adminPendingVerifications); ?> comptes à vérifier</p>
                            <p class="mt-2 text-xs text-muted">Traitez les validations email et activez les profils en attente depuis la gouvernance utilisateurs.</p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                            <p class="text-sm font-semibold text-text"><?php echo number_format($stats['new_users_30d']); ?> nouveaux comptes en 30 jours</p>
                            <p class="mt-2 text-xs text-muted">Mesure de croissance récente à surveiller avec les conversions premium.</p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                            <p class="text-sm font-semibold text-text"><?php echo number_format($adminContentOperations); ?> operations contenu</p>
                            <p class="mt-2 text-xs text-muted">Playlists et épisodes publiés à superviser avec les modules de curation et podcasts.</p>
                        </div>
                    </div>

                    <a href="#gestion-utilisateurs" class="mt-5 inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-semibold text-text hover:bg-white/10">
                        <i class="fas fa-arrow-down"></i> Ouvrir la gouvernance utilisateurs
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section id="admin-modules" class="pb-4">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-3xl border border-white/10 bg-surface/60 p-6 shadow-elev-1">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p class="text-xs uppercase tracking-[0.24em] text-muted">Modules d'administration</p>
                        <h2 class="mt-2 text-xl font-semibold text-text">Accès directs aux espaces de gestion</h2>
                        <p class="mt-2 text-sm text-muted">Une cartographie unique des modules de la plateforme. Cette section remplace les anciens raccourcis répétés et concentre l'accès aux domaines de travail.</p>
                    </div>
                    <div class="flex flex-wrap gap-2 text-xs">
                        <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-muted"><?php echo number_format(count($admin_modules)); ?> modules</span>
                        <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-muted"><?php echo number_format(count($management_users)); ?> profils chargés</span>
                    </div>
                </div>

                <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <?php foreach ($admin_modules as $module): ?>
                        <?php $moduleSnapshot = $adminModuleSnapshots[$module['title']] ?? 'module admin'; ?>
                        <a href="<?php echo $module['href']; ?>" class="rounded-3xl border border-white/10 bg-white/5 p-5 transition hover:bg-white/10">
                            <div class="flex items-start justify-between gap-3">
                                <div class="<?php echo $module['accent']; ?>">
                                    <i class="fas <?php echo $module['icon']; ?> text-lg"></i>
                                </div>
                                <span class="rounded-full border border-white/10 bg-black/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-muted">
                                    <?php echo htmlspecialchars($moduleSnapshot); ?>
                                </span>
                            </div>
                            <h3 class="mt-4 text-sm font-semibold text-text"><?php echo htmlspecialchars($module['title']); ?></h3>
                            <p class="mt-2 text-xs text-muted"><?php echo htmlspecialchars($module['description']); ?></p>
                            <p class="mt-4 text-xs font-semibold text-accent">Ouvrir le module <i class="fas fa-arrow-right text-[10px]"></i></p>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <section id="admin-kpis" class="py-6">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div class="rounded-3xl border border-white/10 bg-surface/60 p-5">
                    <div class="flex items-center justify-between">
                        <p class="text-xs uppercase tracking-[0.2em] text-muted">Utilisateurs</p>
                        <span class="grid h-10 w-10 place-items-center rounded-xl bg-accent/20 text-accent">
                            <i class="fas fa-users"></i>
                        </span>
                    </div>
                    <p class="mt-4 text-3xl font-semibold text-text"><?php echo number_format($stats['total_users']); ?></p>
                    <p class="mt-2 text-xs text-emerald-200"><i class="fas fa-arrow-up"></i> <?php echo number_format($stats['new_users_30d']); ?> ce mois</p>
                </div>

                <div class="rounded-3xl border border-white/10 bg-surface/60 p-5">
                    <div class="flex items-center justify-between">
                        <p class="text-xs uppercase tracking-[0.2em] text-muted">Artistes</p>
                        <span class="grid h-10 w-10 place-items-center rounded-xl bg-emerald-400/20 text-emerald-200">
                            <i class="fas fa-microphone-alt"></i>
                        </span>
                    </div>
                    <p class="mt-4 text-3xl font-semibold text-text"><?php echo number_format($stats['total_artists']); ?></p>
                    <p class="mt-2 text-xs text-muted">Contributeurs actifs</p>
                </div>

                <div class="rounded-3xl border border-white/10 bg-surface/60 p-5">
                    <div class="flex items-center justify-between">
                        <p class="text-xs uppercase tracking-[0.2em] text-muted">Titres</p>
                        <span class="grid h-10 w-10 place-items-center rounded-xl bg-sky-400/20 text-sky-200">
                            <i class="fas fa-music"></i>
                        </span>
                    </div>
                    <p class="mt-4 text-3xl font-semibold text-text"><?php echo number_format($stats['total_tracks']); ?></p>
                    <p class="mt-2 text-xs text-muted"><?php echo number_format($stats['total_albums']); ?> albums</p>
                </div>

                <div class="rounded-3xl border border-white/10 bg-surface/60 p-5">
                    <div class="flex items-center justify-between">
                        <p class="text-xs uppercase tracking-[0.2em] text-muted">Écoutes totales</p>
                        <span class="grid h-10 w-10 place-items-center rounded-xl bg-violet-400/20 text-violet-200">
                            <i class="fas fa-headphones"></i>
                        </span>
                    </div>
                    <p class="mt-4 text-3xl font-semibold text-text"><?php echo number_format($stats['total_streams']); ?></p>
                    <p class="mt-2 text-xs text-muted">Toutes les écoutes</p>
                </div>

                <div class="rounded-3xl border border-white/10 bg-surface/60 p-5">
                    <div class="flex items-center justify-between">
                        <p class="text-xs uppercase tracking-[0.2em] text-muted">Playlists</p>
                        <span class="grid h-10 w-10 place-items-center rounded-xl bg-emerald-400/20 text-emerald-200">
                            <i class="fas fa-list"></i>
                        </span>
                    </div>
                    <p class="mt-4 text-3xl font-semibold text-text"><?php echo number_format($stats['total_playlists']); ?></p>
                    <p class="mt-2 text-xs text-muted"><?php echo number_format($stats['total_podcasts']); ?> podcasts</p>
                </div>

                <div class="rounded-3xl border border-white/10 bg-surface/60 p-5">
                    <div class="flex items-center justify-between">
                        <p class="text-xs uppercase tracking-[0.2em] text-muted">Revenus totaux</p>
                        <span class="grid h-10 w-10 place-items-center rounded-xl bg-emerald-400/20 text-emerald-200">
                            <i class="fas fa-money-bill-wave"></i>
                        </span>
                    </div>
                    <p class="mt-4 text-3xl font-semibold text-text"><?php echo number_format($stats['total_revenue'], 0, ',', ' '); ?> <span class="text-xs text-muted">XAF</span></p>
                    <p class="mt-2 text-xs text-muted">Tous les paiements</p>
                </div>
            </div>
        </div>
    </section>

    <section class="py-10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-6 lg:grid-cols-3">
                <div class="lg:col-span-2 space-y-6">
                    <div class="rounded-3xl border border-white/10 bg-surface/60 p-6">
                        <div class="flex items-center justify-between">
                            <h2 class="text-lg font-semibold text-text"><i class="fas fa-fire"></i> Top titres</h2>
                            <a href="<?php echo SITE_URL; ?>/admin-add-song.php" class="text-xs font-semibold text-accent">Gérer le catalogue</a>
                        </div>
                        <div class="mt-4 overflow-x-auto">
                            <table class="w-full text-left text-sm text-muted">
                                <thead class="text-xs uppercase text-muted border-b border-white/10">
                                    <tr>
                                        <th class="py-3">#</th>
                                        <th class="py-3">Titre</th>
                                        <th class="py-3">Artiste</th>
                                        <th class="py-3">Écoutes</th>
                                        <th class="py-3">Accès</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/10">
                                    <?php if (empty($top_songs)): ?>
                                        <tr>
                                            <td colspan="5" class="py-4 text-center text-muted">Aucun titre disponible.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($top_songs as $index => $song): ?>
                                            <tr class="hover:bg-white/5">
                                                <td class="py-3 font-semibold text-text"><?php echo $index + 1; ?></td>
                                                <td class="py-3"><?php echo htmlspecialchars($song['title']); ?></td>
                                                <td class="py-3"><?php echo htmlspecialchars($song['artist_name']); ?></td>
                                                <td class="py-3">
                                                    <span class="inline-flex items-center gap-1 rounded-full border border-accent/30 bg-accent/10 px-3 py-1 text-xs font-semibold text-accent">
                                                        <?php echo number_format($song['total_streams']); ?> <i class="fas fa-play"></i>
                                                    </span>
                                                </td>
                                                <td class="py-3">
                                                    <?php if (!empty($song['is_free'])): ?>
                                                        <span class="inline-flex items-center gap-1 rounded-full border border-emerald-400/30 bg-emerald-400/10 px-3 py-1 text-xs font-semibold text-emerald-200">
                                                            Gratuit
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="inline-flex items-center gap-1 rounded-full border border-amber-400/30 bg-amber-400/10 px-3 py-1 text-xs font-semibold text-amber-200">
                                                            <?php echo number_format((float) ($song['price'] ?? 0), 0, ',', ' '); ?> FCFA
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="rounded-3xl border border-white/10 bg-surface/60 p-6">
                        <div class="flex items-center justify-between">
                            <h2 class="text-lg font-semibold text-text"><i class="fas fa-trophy"></i> Top artistes</h2>
                        </div>
                        <div class="mt-4 space-y-3">
                            <?php if (empty($top_artists)): ?>
                                <p class="text-sm text-muted">Aucun artiste disponible.</p>
                            <?php else: ?>
                                <?php foreach (array_slice($top_artists, 0, 5) as $index => $artist): ?>
                                    <div class="flex items-center justify-between rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <span class="grid h-8 w-8 place-items-center rounded-full bg-accent/20 text-accent text-xs font-semibold"><?php echo $index + 1; ?></span>
                                            <div>
                                                <p class="text-sm font-semibold text-text"><?php echo htmlspecialchars($artist['stage_name']); ?></p>
                                                <p class="text-xs text-muted"><?php echo number_format($artist['total_streams']); ?> écoutes</p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-white/10 bg-surface/60 p-6">
                        <div class="flex items-center justify-between">
                            <h2 class="text-lg font-semibold text-text"><i class="fas fa-receipt"></i> Transactions</h2>
                        </div>
                        <div class="mt-4 space-y-3">
                            <?php if (empty($recent_transactions)): ?>
                                <p class="text-sm text-muted">Aucune transaction récente.</p>
                            <?php else: ?>
                                <?php foreach (array_slice($recent_transactions, 0, 5) as $trans): ?>
                                    <?php
                                        $transName = trim(($trans['first_name'] ?? '') . ' ' . ($trans['last_name'] ?? ''));
                                        if ($transName === '') {
                                            $transName = 'Système';
                                        }
                                    ?>
                                    <div class="flex items-center justify-between rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                                        <div>
                                            <p class="text-sm font-semibold text-text"><?php echo htmlspecialchars($transName); ?></p>
                                            <p class="text-xs text-muted"><?php echo date('d/m/Y H:i', strtotime($trans['created_at'])); ?></p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-sm font-semibold text-emerald-200"><?php echo number_format($trans['amount'], 0, ',', ' '); ?> XAF</p>
                                            <p class="text-xs text-muted"><?php echo htmlspecialchars($trans['type'] . ' • ' . $trans['status']); ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="gestion-utilisateurs" class="py-4">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-6 xl:grid-cols-[minmax(0,1.45fr)_340px]">
                <div class="space-y-6">
                    <div class="rounded-3xl border border-white/10 bg-surface/60 p-6 shadow-elev-1">
                        <div class="flex flex-wrap items-center justify-between gap-4">
                            <div>
                                <p class="text-xs uppercase tracking-[0.24em] text-muted">Page de gestion</p>
                                <h2 class="mt-2 text-xl font-semibold text-text">Gestion complète des utilisateurs</h2>
                                <p class="mt-2 text-sm text-muted">Créez, modifiez, supprimez et vérifiez les comptes depuis un même espace de pilotage.</p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" class="rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-semibold text-text hover:bg-white/10" data-modal-open="bulkActionsModal">
                                    <i class="fas fa-layer-group"></i> Actions groupées
                                </button>
                                <button type="button" class="rounded-full bg-accent px-4 py-2 text-xs font-semibold text-white shadow-elev-1" data-modal-open="addUserModal">
                                    <i class="fas fa-user-plus"></i> Nouvel utilisateur
                                </button>
                            </div>
                        </div>

                        <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                                <p class="text-xs uppercase tracking-[0.2em] text-muted">Total</p>
                                <p class="mt-3 text-2xl font-semibold text-text"><?php echo number_format($stats['total_users']); ?></p>
                            </div>
                            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                                <p class="text-xs uppercase tracking-[0.2em] text-muted">Actifs</p>
                                <p class="mt-3 text-2xl font-semibold text-text"><?php echo number_format($stats['active_users']); ?></p>
                            </div>
                            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                                <p class="text-xs uppercase tracking-[0.2em] text-muted">Vérifiés</p>
                                <p class="mt-3 text-2xl font-semibold text-text"><?php echo number_format($stats['verified_users']); ?></p>
                            </div>
                            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                                <p class="text-xs uppercase tracking-[0.2em] text-muted">Premium</p>
                                <p class="mt-3 text-2xl font-semibold text-text"><?php echo number_format($stats['premium_users']); ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-white/10 bg-surface/60 p-6 shadow-elev-1">
                        <div class="flex flex-wrap items-center justify-between gap-4">
                            <div class="flex flex-1 items-center gap-2 rounded-2xl border border-white/10 bg-bg px-4 py-3">
                                <i class="fas fa-search text-muted"></i>
                                <input id="userManagementSearch" type="text" class="w-full bg-transparent text-sm text-text placeholder:text-muted focus:outline-none" placeholder="Filtrer par nom, email ou identifiant...">
                            </div>
                            <p class="text-xs text-muted"><?php echo number_format(count($management_users)); ?> profils récents affichés</p>
                        </div>

                        <div class="mt-5 overflow-x-auto">
                            <table class="w-full text-left text-sm text-muted">
                                <thead class="border-b border-white/10 text-xs uppercase text-muted">
                                    <tr>
                                        <th class="py-3">
                                            <input id="dashboardSelectAllUsers" type="checkbox" class="h-4 w-4 rounded border-white/20 bg-bg text-accent">
                                        </th>
                                        <th class="py-3">Profil</th>
                                        <th class="py-3">Type</th>
                                        <th class="py-3">Statut</th>
                                        <th class="py-3">Localisation</th>
                                        <th class="py-3">Inscription</th>
                                        <th class="py-3 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="userManagementBody" class="divide-y divide-white/10">
                                    <?php if (empty($management_users)): ?>
                                        <tr>
                                            <td colspan="7" class="py-5 text-center text-muted">Aucun utilisateur disponible.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($management_users as $managedUser): ?>
                                            <?php
                                                $managedName = trim(($managedUser['first_name'] ?? '') . ' ' . ($managedUser['last_name'] ?? ''));
                                                if ($managedName === '') {
                                                    $managedName = '@' . ($managedUser['username'] ?? 'utilisateur');
                                                }
                                                $initialSeed = trim(($managedUser['first_name'] ?? '') . ($managedUser['last_name'] ?? ''));
                                                if ($initialSeed === '') {
                                                    $initialSeed = $managedUser['username'] ?? 'U';
                                                }
                                                $userInitials = strtoupper(substr($initialSeed, 0, 2));
                                                $typeLabel = $managedUser['user_type'] ?? 'fan';
                                                $typeClass = 'border-accent/30 bg-accent/10 text-accent';

                                                if ($typeLabel === 'admin') {
                                                    $typeClass = 'border-rose-400/30 bg-rose-400/10 text-rose-200';
                                                } elseif ($typeLabel === 'artist') {
                                                    $typeClass = 'border-emerald-400/30 bg-emerald-400/10 text-emerald-200';
                                                }
                                            ?>
                                            <tr class="hover:bg-white/5 user-management-row"
                                                data-user-search="<?php echo htmlspecialchars(strtolower($managedName . ' ' . $managedUser['username'] . ' ' . $managedUser['email'])); ?>">
                                                <td class="py-3">
                                                    <input type="checkbox" class="user-checkbox h-4 w-4 rounded border-white/20 bg-bg text-accent" value="<?php echo (int) $managedUser['id']; ?>" <?php echo (int) $managedUser['id'] === 1 ? 'disabled' : ''; ?>>
                                                </td>
                                                <td class="py-3">
                                                    <div class="flex items-center gap-3">
                                                        <span class="grid h-10 w-10 place-items-center rounded-full bg-accent/20 text-xs font-semibold text-accent">
                                                            <?php echo htmlspecialchars($userInitials); ?>
                                                        </span>
                                                        <div>
                                                            <p class="text-sm font-semibold text-text"><?php echo htmlspecialchars($managedName); ?></p>
                                                            <p class="text-xs text-muted">@<?php echo htmlspecialchars($managedUser['username']); ?> - <?php echo htmlspecialchars($managedUser['email']); ?></p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="py-3">
                                                    <span class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold <?php echo $typeClass; ?>">
                                                        <?php echo htmlspecialchars(ucfirst($typeLabel)); ?>
                                                    </span>
                                                    <?php if (!empty($managedUser['premium_status'])): ?>
                                                        <div class="mt-2">
                                                            <span class="inline-flex rounded-full border border-amber-400/30 bg-amber-400/10 px-3 py-1 text-[11px] font-semibold text-amber-200">
                                                                <i class="fas fa-crown"></i> Premium
                                                            </span>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="py-3">
                                                    <?php if (!empty($managedUser['is_active'])): ?>
                                                        <span class="inline-flex rounded-full border border-emerald-400/30 bg-emerald-400/10 px-3 py-1 text-xs font-semibold text-emerald-200">Actif</span>
                                                    <?php else: ?>
                                                        <span class="inline-flex rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold text-muted">Inactif</span>
                                                    <?php endif; ?>
                                                    <div class="mt-2 text-xs <?php echo !empty($managedUser['email_verified']) ? 'text-emerald-300' : 'text-muted'; ?>">
                                                        <i class="fas <?php echo !empty($managedUser['email_verified']) ? 'fa-check-circle' : 'fa-clock'; ?>"></i>
                                                        <?php echo !empty($managedUser['email_verified']) ? 'Email vérifié' : 'Email en attente'; ?>
                                                    </div>
                                                </td>
                                                <td class="py-3">
                                                    <p class="text-sm text-text"><?php echo htmlspecialchars($managedUser['country'] ?: 'Non renseigné'); ?></p>
                                                    <p class="text-xs text-muted"><?php echo htmlspecialchars($managedUser['city'] ?: 'Ville non renseignée'); ?></p>
                                                </td>
                                                <td class="py-3 text-xs text-muted">
                                                    <?php echo date('d/m/Y', strtotime($managedUser['created_at'])); ?>
                                                    <div><?php echo date('H:i', strtotime($managedUser['created_at'])); ?></div>
                                                </td>
                                                <td class="py-3 text-right">
                                                    <div class="inline-flex gap-2">
                                                        <button type="button" class="grid h-8 w-8 place-items-center rounded-full border border-white/10 bg-white/5 text-text hover:bg-white/10" onclick="viewDashboardUser(<?php echo (int) $managedUser['id']; ?>)" title="Voir">
                                                            <i class="fas fa-eye text-xs"></i>
                                                        </button>
                                                        <button type="button" class="grid h-8 w-8 place-items-center rounded-full border border-white/10 bg-white/5 text-text hover:bg-white/10" onclick="editDashboardUser(<?php echo (int) $managedUser['id']; ?>)" title="Modifier">
                                                            <i class="fas fa-pen text-xs"></i>
                                                        </button>
                                                        <?php if ((int) $managedUser['id'] !== 1): ?>
                                                            <button type="button" class="grid h-8 w-8 place-items-center rounded-full border border-rose-400/30 bg-rose-400/10 text-rose-200 hover:bg-rose-500/20" onclick="deleteDashboardUser(<?php echo (int) $managedUser['id']; ?>)" title="Supprimer">
                                                                <i class="fas fa-trash text-xs"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="rounded-3xl border border-white/10 bg-surface/60 p-6 shadow-elev-1">
                        <h3 class="text-lg font-semibold text-text">Règles de gouvernance</h3>
                        <div class="mt-4 space-y-3 text-sm text-muted">
                            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                                La création de comptes depuis l'administration est temporairement suspendue :
                                l'API concernée est en cours de réécriture avec contrôle d'accès (SEC-01).
                            </div>
                            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                                Aucun mot de passe initial n'est attribué par la plateforme. À la réouverture,
                                la création d'un compte enverra un lien d'invitation à usage unique.
                            </div>
                            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                                La promotion au rôle administrateur relèvera d'une action dédiée, réservée au
                                super-administrateur et journalisée.
                            </div>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-white/10 bg-surface/60 p-6 shadow-elev-1">
                        <h3 class="text-lg font-semibold text-text">Outils de plateforme</h3>
                        <div class="mt-4 space-y-2 text-sm">
                            <a href="<?php echo SITE_URL; ?>/security-settings.php" class="flex items-center justify-between rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-text hover:bg-white/10">
                                <span><i class="fas fa-user-shield text-sky-300"></i> Paramètres sécurité</span>
                                <i class="fas fa-arrow-right text-xs text-muted"></i>
                            </a>
                            <a href="<?php echo SITE_URL; ?>/admin/reset-password.php" class="flex items-center justify-between rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-text hover:bg-white/10">
                                <span><i class="fas fa-unlock-keyhole text-accent"></i> Reset compte admin</span>
                                <i class="fas fa-arrow-right text-xs text-muted"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

</main>

<?php include 'admin/dashboard-modals/modals.php'; ?>

<script>
window.ADMIN_API_BASE = <?php echo json_encode(rtrim(SITE_URL, '/') . '/api'); ?>;

function dashboardRequestJson(url, options) {
    return fetch(url, options || {}).then(function (response) {
        return response.json();
    });
}

function viewDashboardUser(userId) {
    dashboardRequestJson(window.ADMIN_API_BASE + '/user.php?action=get&id=' + userId)
        .then(function (data) {
            if (!data.success) {
                alert('Erreur: ' + data.error);
                return;
            }

            var user = data.user;
            var name = ((user.first_name || '') + ' ' + (user.last_name || '')).trim() || ('@' + (user.username || 'utilisateur'));
            var premium = Number(user.is_premium) === 1 ? 'Oui' : 'Non';
            var verified = Number(user.email_verified) === 1 ? 'Vérifié' : 'En attente';
            var active = Number(user.is_active) === 1 ? 'Actif' : 'Inactif';

            var bodyHtml = ''
                + '<div class="space-y-3 text-sm text-muted">'
                + '<div><span class="text-text font-semibold">' + escapeDashboardHtml(name) + '</span> (@' + escapeDashboardHtml(user.username || '') + ')</div>'
                + '<div>Email: ' + escapeDashboardHtml(user.email || '') + '</div>'
                + '<div>Type: ' + escapeDashboardHtml(user.user_type || 'fan') + '</div>'
                + '<div>Statut: ' + active + '</div>'
                + '<div>Email: ' + verified + '</div>'
                + '<div>Premium: ' + premium + '</div>'
                + '</div>';

            if (typeof showDetailModal === 'function') {
                showDetailModal('Profil utilisateur', bodyHtml, '');
            }
        })
        .catch(function () {
            alert('Impossible de charger ce profil.');
        });
}

function editDashboardUser(userId) {
    if (typeof AdminModal !== 'undefined') {
        AdminModal.open('editUserModal');
    }
    if (typeof loadUserForEdit === 'function') {
        loadUserForEdit(userId);
    }
}

function deleteDashboardUser(userId) {
    if (Number(userId) === 1) {
        alert('Le compte administrateur principal ne peut pas être supprimé.');
        return;
    }

    if (!confirm('Supprimer cet utilisateur ?')) {
        return;
    }

    dashboardRequestJson(window.ADMIN_API_BASE + '/user.php?action=delete&id=' + userId, {
        method: 'POST'
    })
        .then(function (data) {
            if (data.success) {
                window.location.reload();
            } else {
                alert('Erreur: ' + data.error);
            }
        })
        .catch(function () {
            alert('Suppression impossible.');
        });
}

function bulkAction(action) {
    var selectedIds = Array.from(document.querySelectorAll('#userManagementBody .user-checkbox:checked')).map(function (checkbox) {
        return checkbox.value;
    });

    if (selectedIds.length === 0) {
        alert('Veuillez sélectionner au moins un utilisateur.');
        return;
    }

    if (!confirm('Appliquer l action "' + action + '" à ' + selectedIds.length + ' utilisateur(s) ?')) {
        return;
    }

    var payload = new URLSearchParams();
    payload.set('action', 'bulk');
    payload.set('operation', action);
    selectedIds.forEach(function (id) {
        payload.append('users[]', id);
    });

    dashboardRequestJson(window.ADMIN_API_BASE + '/user.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
        },
        body: payload.toString()
    })
        .then(function (data) {
            if (data.success) {
                window.location.reload();
            } else {
                alert('Erreur: ' + data.error);
            }
        })
        .catch(function () {
            alert('Action groupée impossible.');
        });
}

function escapeDashboardHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

document.getElementById('dashboardSelectAllUsers')?.addEventListener('change', function (event) {
    document.querySelectorAll('#userManagementBody .user-checkbox:not(:disabled)').forEach(function (checkbox) {
        checkbox.checked = event.target.checked;
    });
});

document.getElementById('userManagementSearch')?.addEventListener('input', function (event) {
    var term = event.target.value.trim().toLowerCase();
    document.querySelectorAll('.user-management-row').forEach(function (row) {
        var haystack = row.getAttribute('data-user-search') || '';
        row.style.display = haystack.indexOf(term) !== -1 ? '' : 'none';
    });
});
</script>

<?php include 'includes/footer-tailwind.php'; ?>
