<?php
/**
 * Page Émissions - Tchadok Platform
 * Migration Tailwind (progressive)
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'assets/images/placeholders.php';

$pageTitle = 'Émissions';
$pageDescription = 'Découvrez nos émissions radio exclusives et podcasts. Musique, culture et débats.';

$radioSchedule = getRadioSchedule(20);
$liveInfo = getRadioLiveInfo();
$liveShow = null;
$programme = [
    'programme' => [
        'label' => 'Programme',
        'items' => []
    ]
];

foreach ($radioSchedule as $show) {
    $action = 'remind';
    $featured = false;
    if (!empty($liveInfo['current_show']) && (int) $liveInfo['current_show']['id'] === (int) $show['id']) {
        $action = 'live';
        $featured = true;
    }
    $programme['programme']['items'][] = [
        'time' => $show['time_display'] ?? '',
        'title' => $show['title'] ?? 'Emission',
        'desc' => $show['description'] ?? '',
        'host' => $show['host'] ?? 'Tchadok Radio',
        'color' => '#2F6DE0',
        'action' => $action,
        'featured' => $featured
    ];
}

if (!empty($programme['programme']['items'])) {
    if (!empty($liveInfo['current_show'])) {
        $liveShow = [
            'title' => $liveInfo['current_show']['title'],
            'host' => $liveInfo['current_show']['host'] ?? 'Tchadok Radio',
            'time' => formatTimeRange($liveInfo['current_show']['start_time'], $liveInfo['current_show']['end_time']),
            'subtitle' => !empty($liveInfo['current_track'])
                ? 'En cours : ' . $liveInfo['current_track']['title'] . ' • ' . $liveInfo['current_track']['artist']
                : 'Emission en cours de programmation.',
            'listeners' => formatNumber((int) ($liveInfo['listeners_count'] ?? 0)),
            'action' => 'live'
        ];
    } else {
        $liveShow = $programme['programme']['items'][0];
        $liveShow['subtitle'] = $liveShow['desc'] ?: 'Emission en cours de programmation.';
        $liveShow['listeners'] = formatNumber((int) ($liveInfo['listeners_count'] ?? 0));
        $liveShow['action'] = 'live';
    }
}

$podcasts = getPodcasts(6);
$categories = getPodcastCategories();
$showsCount = getRadioShowsCount();
$podcastsCount = getPodcastsCount();
$listenersCount = (int) ($liveInfo['listeners_count'] ?? 0);
$isLive = !empty($liveInfo['is_live']);

$additionalCSS = [
    SITE_URL . '/assets/css/emissions-tailwind.css'
];

$emissionsJsVersion = @filemtime(__DIR__ . '/assets/js/emissions.js') ?: time();

$additionalJS = [
    SITE_URL . '/assets/js/emissions.js?v=' . $emissionsJsVersion
];

include 'includes/header-tailwind.php';
?>

<main class="pt-0">
    <section class="page-hero relative min-h-screen items-center overflow-hidden bg-bg pb-14 pt-12 sm:pt-16 lg:pt-20 lg:flex">
        <div class="absolute -left-32 -top-32 h-72 w-72 rounded-full bg-[#0066CC]/20 blur-3xl"></div>
        <div class="absolute -bottom-40 right-0 h-96 w-96 rounded-full bg-[#FFD700]/20 blur-3xl"></div>
        <span class="page-hero-note note-1">&#9834;</span>
        <span class="page-hero-note note-2">&#9835;</span>
        <span class="page-hero-note note-3">&#9834;</span>
        <span class="page-hero-note note-4">&#9835;</span>

        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid items-center gap-12 lg:grid-cols-2">
                <div class="page-hero-content">
                    <div class="page-hero-pill inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold text-text">
                        <i class="fas fa-podcast text-accent"></i>
                        Contenu exclusif
                    </div>
                    <h1 class="page-hero-title mt-5 text-4xl font-display font-bold leading-tight text-text sm:text-5xl">Émissions & Podcasts Tchadiens</h1>
                    <p class="page-hero-subtitle mt-4 text-base text-muted sm:text-lg">
                        Plongez dans l'univers radiophonique tchadien avec nos émissions exclusives.
                        Musique, culture, débats et interviews avec les personnalités qui font le Tchad d'aujourd'hui.
                    </p>

                    <div class="mt-6 grid gap-4 sm:grid-cols-3">
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4 text-center">
                            <p class="text-xs uppercase tracking-[0.2em] text-muted">Diffusion</p>
                            <p class="mt-2 text-2xl font-semibold text-text"><?php echo $isLive ? 'LIVE' : 'OFF'; ?></p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4 text-center">
                            <p class="text-xs uppercase tracking-[0.2em] text-muted">Émissions</p>
                            <p class="mt-2 text-2xl font-semibold text-text"><?php echo number_format($showsCount); ?></p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4 text-center">
                            <p class="text-xs uppercase tracking-[0.2em] text-muted">Auditeurs</p>
                            <p class="mt-2 text-2xl font-semibold text-text"><?php echo formatNumber($listenersCount); ?></p>
                        </div>
                    </div>

                    <div class="mt-7 flex flex-wrap gap-3">
                        <button class="rounded-full bg-accent px-6 py-3 text-sm font-semibold text-white shadow-elev-1" data-action="play-live" type="button">
                            <i class="fas fa-broadcast-tower"></i> Ecouter en direct
                        </button>
                        <a class="rounded-full border border-white/10 bg-white/5 px-6 py-3 text-sm font-semibold text-text" href="#programmes">
                            <i class="fas fa-calendar-alt"></i> Voir le programme
                        </a>
                    </div>
                </div>

                <div class="flex items-center justify-center">
                    <div class="radio-visual relative grid place-items-center rounded-3xl border border-white/10 bg-surface/70 p-8 shadow-elev-2" data-radio-visual>
                        <div class="radio-waves">
                            <span class="radio-wave"></span>
                            <span class="radio-wave"></span>
                            <span class="radio-wave"></span>
                            <span class="radio-wave"></span>
                        </div>
                        <div class="grid h-20 w-20 place-items-center rounded-full bg-accent/20 text-accent">
                            <i class="fas fa-microphone text-2xl"></i>
                        </div>
                        <div class="mt-6 rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs text-muted">
                            Podcasts immersifs et emissions live
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <?php if (!empty($liveShow)): ?>
                <div class="live-card flex flex-wrap items-center justify-between gap-6 rounded-3xl border border-white/10 bg-surface/70 p-6">
                    <div>
                        <div class="inline-flex items-center gap-2 rounded-full border border-emerald-400/30 bg-emerald-400/10 px-3 py-1 text-xs font-semibold text-emerald-200">
                            <span class="live-dot"></span>
                            EN DIRECT
                        </div>
                        <h3 class="mt-4 text-xl font-semibold text-text"><?php echo htmlspecialchars($liveShow['title']); ?> avec <?php echo htmlspecialchars($liveShow['host']); ?></h3>
                        <p class="mt-2 text-sm text-muted"><?php echo htmlspecialchars($liveShow['subtitle']); ?></p>
                        <div class="mt-3 flex flex-wrap items-center gap-3 text-xs text-muted">
                            <span><i class="fas fa-clock"></i> <?php echo htmlspecialchars($liveShow['time']); ?></span>
                            <span class="h-1 w-1 rounded-full bg-muted/40"></span>
                            <span><i class="fas fa-users"></i> <?php echo htmlspecialchars($liveShow['listeners']); ?> auditeurs</span>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-4">
                        <button class="grid h-14 w-14 place-items-center rounded-full bg-accent text-white shadow-elev-2" data-action="play-live" type="button" aria-label="Lecture ou pause du direct">
                            <i class="fas fa-play" id="livePlayIcon"></i>
                        </button>
                        <div class="flex items-center gap-3 rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs text-muted">
                            <i class="fas fa-volume-up"></i>
                            <input type="range" class="volume-slider w-28" min="0" max="100" value="75" data-volume-slider>
                            <span data-volume-display>75%</span>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="rounded-3xl border border-white/10 bg-white/5 p-8 text-center text-muted">
                    <i class="fas fa-broadcast-tower text-2xl"></i>
                    <h4 class="mt-3 text-base font-semibold text-text">Aucune emission en direct</h4>
                    <p class="mt-2 text-sm text-muted">Les emissions en direct apparaitront ici.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="py-12" id="programmes">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-6 text-center">
                <h2 class="text-2xl font-display font-bold text-text">Programme de la semaine</h2>
                <p class="text-sm text-muted">Ne manquez aucune de vos emissions preferees</p>
            </div>

            <?php $firstTab = array_key_first($programme); ?>
            <div class="flex flex-wrap justify-center gap-2">
                <?php foreach ($programme as $key => $day): ?>
                    <button class="tab-button rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-semibold text-text <?php echo $key === $firstTab ? 'is-active' : ''; ?>" data-tab-target="<?php echo $key; ?>" type="button">
                        <?php echo htmlspecialchars($day['label']); ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="mt-8 space-y-6">
                <?php foreach ($programme as $key => $day): ?>
                    <div class="<?php echo $key === $firstTab ? '' : 'hidden'; ?>" data-tab-panel="<?php echo $key; ?>">
                        <?php if (empty($day['items'])): ?>
                            <div class="rounded-3xl border border-white/10 bg-white/5 p-8 text-center text-muted">
                                <i class="fas fa-calendar-plus text-2xl"></i>
                                <h4 class="mt-3 text-base font-semibold text-text">Programme <?php echo htmlspecialchars($day['label']); ?></h4>
                                <p class="mt-2 text-sm text-muted">Contenu en cours de programmation...</p>
                            </div>
                        <?php else: ?>
                            <div class="space-y-4">
                                <?php foreach ($day['items'] as $item): ?>
                                    <?php
                                        $avatar = createAvatarPlaceholder($item['host'], $item['color'] ?? '#2F6DE0');
                                        $avatar = str_replace('class="img-fluid"', 'class="h-full w-full object-cover"', $avatar);
                                        $isFeatured = !empty($item['featured']);
                                        $actionType = $item['action'] ?? 'remind';
                                        $actionLabel = 'Rappel';
                                        $actionIcon = 'fa-bell';
                                        $actionClasses = 'border border-white/10 bg-white/5 text-text';
                                        if ($actionType === 'live') {
                                            $actionLabel = 'En direct';
                                            $actionIcon = 'fa-play';
                                            $actionClasses = 'bg-accent text-white shadow-elev-1';
                                        } elseif ($actionType === 'podcast') {
                                            $actionLabel = 'Podcast';
                                            $actionIcon = 'fa-podcast';
                                            $actionClasses = 'border border-white/10 bg-white/5 text-text';
                                        }
                                    ?>
                                    <div class="programme-card flex flex-wrap items-center justify-between gap-6 rounded-3xl border border-white/10 bg-surface/60 p-5 <?php echo $isFeatured ? 'is-featured' : ''; ?>">
                                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                                            <div class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold text-text">
                                                <i class="fas fa-clock text-muted"></i> <?php echo htmlspecialchars($item['time']); ?>
                                            </div>
                                            <div>
                                                <h4 class="text-lg font-semibold text-text"><?php echo htmlspecialchars($item['title']); ?></h4>
                                                <p class="text-sm text-muted"><?php echo htmlspecialchars($item['desc']); ?></p>
                                                <div class="mt-2 flex items-center gap-2 text-xs text-muted">
                                                    <span class="h-9 w-9 overflow-hidden rounded-full border border-white/10 bg-white/5">
                                                        <?php echo $avatar; ?>
                                                    </span>
                                                    <span><?php echo htmlspecialchars($item['host']); ?></span>
                                                </div>
                                                <?php if ($isFeatured): ?>
                                                    <div class="mt-2 inline-flex items-center gap-2 rounded-full border border-amber-400/30 bg-amber-400/10 px-3 py-1 text-xs font-semibold text-amber-200">
                                                        <i class="fas fa-star"></i> Emission phare
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <button class="remind-btn inline-flex items-center gap-2 rounded-full px-4 py-2 text-xs font-semibold <?php echo $actionClasses; ?>" data-action="<?php echo $actionType === 'live' ? 'play-live' : 'remind'; ?>" type="button">
                                            <i class="fas <?php echo $actionIcon; ?>"></i> <?php echo $actionLabel; ?>
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="py-12">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-6">
                <h2 class="text-2xl font-display font-bold text-text">Podcasts populaires</h2>
                <p class="text-sm text-muted">Ecoutez quand vous voulez, ou vous voulez</p>
            </div>

            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                <?php if (empty($podcasts)): ?>
                    <div class="col-span-full rounded-3xl border border-white/10 bg-white/5 p-8 text-center text-muted">
                        <i class="fas fa-podcast text-2xl"></i>
                        <h4 class="mt-3 text-base font-semibold text-text">Aucun podcast disponible</h4>
                        <p class="mt-2 text-sm text-muted">Les podcasts apparaitront des qu'ils seront ajoutes.</p>
                    </div>
                <?php else: ?>
                    <?php
                    $podcastPalette = ['#2F6DE0', '#FFD700', '#22C55E', '#F97316', '#EC4899', '#38BDF8'];
                    ?>
                    <?php foreach ($podcasts as $index => $podcast): ?>
                        <?php
                            $podcastColor = $podcastPalette[$index % count($podcastPalette)];
                            if (!empty($podcast['cover_image'])) {
                                $cover = '<img src="' . SITE_URL . '/' . htmlspecialchars($podcast['cover_image']) . '" alt="' . htmlspecialchars($podcast['title']) . '" class="h-full w-full object-cover">';
                            } else {
                                $subtitle = $podcast['host_name'] ?: ($podcast['category'] ?? '');
                                $cover = createPodcastCover($podcast['title'], $subtitle, $podcastColor);
                                $cover = str_replace('class="img-fluid"', 'class="h-full w-full object-cover"', $cover);
                            }
                        ?>
                        <article class="group rounded-3xl border border-white/10 bg-surface/60 p-4 transition hover:-translate-y-1 hover:shadow-elev-2">
                            <div class="relative overflow-hidden rounded-2xl bg-white/5">
                                <div class="aspect-square">
                                    <?php echo $cover; ?>
                                </div>
                                <div class="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 transition group-hover:opacity-100">
                                    <button class="grid h-11 w-11 place-items-center rounded-full bg-accent text-white shadow-elev-1" data-action="podcast" data-podcast-title="<?php echo htmlspecialchars($podcast['title'], ENT_QUOTES); ?>" type="button" aria-label="Lire le podcast">
                                        <i class="fas fa-play"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="mt-4">
                                <h5 class="text-lg font-semibold text-text"><?php echo htmlspecialchars($podcast['title']); ?></h5>
                                <p class="text-sm text-muted"><?php echo htmlspecialchars(truncateText($podcast['description'] ?? '')); ?></p>
                                <div class="mt-3 flex flex-wrap gap-3 text-xs text-muted">
                                    <span><i class="fas fa-microphone"></i> <?php echo (int) ($podcast['episodes_count'] ?? 0); ?> episodes</span>
                                    <span><i class="fas fa-clock"></i> <?php echo htmlspecialchars($podcast['duration_label'] ?? '0 min'); ?></span>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="py-12">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-6">
                <h2 class="text-2xl font-display font-bold text-text">Explorer par categories</h2>
                <p class="text-sm text-muted">Trouvez le contenu qui vous correspond</p>
            </div>

            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <?php if (empty($categories)): ?>
                    <div class="col-span-full rounded-3xl border border-white/10 bg-white/5 p-8 text-center text-muted">
                        <i class="fas fa-layer-group text-2xl"></i>
                        <h4 class="mt-3 text-base font-semibold text-text">Aucune categorie disponible</h4>
                        <p class="mt-2 text-sm text-muted">Les categories apparaitront des qu'elles seront ajoutees.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($categories as $category): ?>
                        <button class="category-card group rounded-3xl border border-white/10 bg-gradient-to-br <?php echo $category['tone']; ?> p-5 text-left" data-action="category" data-category="<?php echo htmlspecialchars($category['key'], ENT_QUOTES); ?>" type="button">
                            <div class="flex items-center gap-3 text-sm font-semibold text-text">
                                <span class="grid h-10 w-10 place-items-center rounded-xl bg-white/10 text-text">
                                    <i class="fas <?php echo htmlspecialchars($category['icon']); ?>"></i>
                                </span>
                                <?php echo htmlspecialchars($category['title']); ?>
                            </div>
                            <p class="mt-3 text-sm text-muted"><?php echo htmlspecialchars($category['desc']); ?></p>
                            <div class="mt-4 text-xs font-semibold text-muted"><?php echo htmlspecialchars($category['count']); ?></div>
                        </button>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>

<?php include 'includes/footer-tailwind.php'; ?>
