<?php
/**
 * Page Découvrir - Tchadok Platform
 * Migration Tailwind (progressive)
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'assets/images/placeholders.php';

$pageTitle = 'Découvrir';
$pageDescription = 'Explorez la richesse de la musique tchadienne. Découvrez de nouveaux artistes, genres et tendances.';

$trendingTracks = getTrendingTracks(6);
$newTracks      = getNewTracks(6);
$classicTracks  = getClassicTracks(6);
$risingArtists  = getRisingArtists(6);
$genres         = getGenres();
$platformStats  = getPlatformStats();

$discoveryStats = ['artists_discovered' => 0, 'listening_hours' => 0, 'favorites_count' => 0];
if (isLoggedIn()) {
    $discoveryStats = getUserDiscoveryStats($_SESSION['user_id']);
}

$additionalCSS = [
    SITE_URL . '/assets/css/decouvrir-tailwind.css'
];

$additionalJS = [
    SITE_URL . '/assets/js/decouvrir.js'
];

include 'includes/header-tailwind.php';
?>

<main class="pt-0">
    <section class="page-hero relative min-h-screen items-center overflow-hidden bg-bg pb-14 pt-12 sm:pt-16 lg:pt-20 lg:flex" id="decouvrir-hero">
        <div class="absolute -left-32 -top-32 h-72 w-72 rounded-full bg-[#0066CC]/20 blur-3xl"></div>
        <div class="absolute -bottom-40 right-0 h-96 w-96 rounded-full bg-[#FFD700]/20 blur-3xl"></div>
        <span class="page-hero-note note-1">&#9834;</span>
        <span class="page-hero-note note-2">&#9835;</span>
        <span class="page-hero-note note-3">&#9834;</span>
        <span class="page-hero-note note-4">&#9835;</span>

        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid items-center gap-12 lg:grid-cols-2">
                <div class="page-hero-content">
                    <div class="page-hero-pill inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold text-muted">
                        <i class="fas fa-compass text-accent"></i>
                        Exploration musicale
                    </div>
                    <h1 class="page-hero-title mt-5 text-4xl font-display font-bold leading-tight text-text sm:text-5xl">
                        Découvrez votre prochaine obsession musicale
                    </h1>
                    <p class="page-hero-subtitle mt-4 text-base text-muted sm:text-lg">
                        Plongez dans l'univers riche et diversifié de la musique tchadienne.
                        Des sons traditionnels aux créations contemporaines, laissez-vous guider
                        par nos recommandations.
                    </p>
                    <div class="mt-6 grid gap-3 sm:grid-cols-3">
                        <div class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-xs text-muted">
                            <span class="grid h-8 w-8 place-items-center rounded-lg bg-accent/20 text-accent">
                                <i class="fas fa-magic"></i>
                            </span>
                            Recommandations IA
                        </div>
                        <div class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-xs text-muted">
                            <span class="grid h-8 w-8 place-items-center rounded-lg bg-accent-2/20 text-accent-2">
                                <i class="fas fa-headphones"></i>
                            </span>
                            Écoute immersive
                        </div>
                        <div class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-xs text-muted">
                            <span class="grid h-8 w-8 place-items-center rounded-lg bg-white/10 text-text">
                                <i class="fas fa-heart"></i>
                            </span>
                            Favoris personnalisés
                        </div>
                    </div>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <a href="#filtres" class="inline-flex items-center gap-2 rounded-full bg-accent px-5 py-3 text-sm font-semibold text-white shadow-elev-1 transition hover:-translate-y-0.5 hover:shadow-elev-2">
                            Explorer maintenant
                        </a>
                        <?php if (!isLoggedIn()): ?>
                            <a href="<?php echo SITE_URL; ?>/register.php" class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-5 py-3 text-sm font-semibold text-text hover:bg-white/10">
                                Créer un compte
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="discovery-wheel-container">
                    <?php
                    $wheelGenres = $genres;
                    $segmentCount = min(count($wheelGenres), 8);
                    ?>
                    <?php if (empty($wheelGenres)): ?>
                        <div class="rounded-3xl border border-white/10 bg-white/5 p-8 text-center text-muted">
                            <i class="fas fa-layer-group text-2xl"></i>
                            <h4 class="mt-3 text-base font-semibold text-text">Aucun genre disponible</h4>
                            <p class="mt-2 text-sm text-muted">Ajoutez des genres depuis l'administration pour activer la roue de découverte.</p>
                        </div>
                    <?php else: ?>
                        <div class="discovery-wheel segments-<?php echo max($segmentCount, 1); ?>" data-wheel>
                            <button class="wheel-center" type="button" data-wheel-center aria-label="Lancer la découverte">
                                <i class="fas fa-play"></i>
                                Découvrir
                            </button>
                            <?php for ($i = 0; $i < $segmentCount; $i++): ?>
                                <?php
                                $genreName = htmlspecialchars($wheelGenres[$i]['name_french'] ?? $wheelGenres[$i]['name']);
                                $genreSlug = strtolower(str_replace(' ', '', $wheelGenres[$i]['name']));
                                ?>
                                <button class="wheel-segment segment-<?php echo $i; ?>" type="button" data-genre="<?php echo $genreSlug; ?>">
                                    <span><?php echo $genreName; ?></span>
                                </button>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <section class="py-10" id="filtres">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-6">
                <h2 class="text-2xl font-display font-bold text-text">Explorez par préférences</h2>
                <p class="text-sm text-muted">Filtrez selon votre humeur et vos envies.</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <button class="filter-card is-active flex w-full flex-col gap-2 rounded-2xl border border-white/10 bg-white/5 p-4 text-left text-sm text-muted" data-filter="trending" aria-pressed="true" type="button">
                    <span class="filter-icon grid h-10 w-10 place-items-center rounded-xl bg-rose-500/15 text-rose-300">
                        <i class="fas fa-fire"></i>
                    </span>
                    <h4 class="text-sm font-semibold text-text">Tendances</h4>
                    <p class="text-xs text-muted">Les hits du moment</p>
                </button>
                <button class="filter-card flex w-full flex-col gap-2 rounded-2xl border border-white/10 bg-white/5 p-4 text-left text-sm text-muted" data-filter="new" aria-pressed="false" type="button">
                    <span class="filter-icon grid h-10 w-10 place-items-center rounded-xl bg-amber-500/15 text-amber-300">
                        <i class="fas fa-star"></i>
                    </span>
                    <h4 class="text-sm font-semibold text-text">Nouveautés</h4>
                    <p class="text-xs text-muted">Dernieres sorties</p>
                </button>
                <button class="filter-card flex w-full flex-col gap-2 rounded-2xl border border-white/10 bg-white/5 p-4 text-left text-sm text-muted" data-filter="classics" aria-pressed="false" type="button">
                    <span class="filter-icon grid h-10 w-10 place-items-center rounded-xl bg-yellow-500/15 text-yellow-300">
                        <i class="fas fa-crown"></i>
                    </span>
                    <h4 class="text-sm font-semibold text-text">Classiques</h4>
                    <p class="text-xs text-muted">Intemporels tchadiens</p>
                </button>
                <button class="filter-card flex w-full flex-col gap-2 rounded-2xl border border-white/10 bg-white/5 p-4 text-left text-sm text-muted" data-filter="rising" aria-pressed="false" type="button">
                    <span class="filter-icon grid h-10 w-10 place-items-center rounded-xl bg-cyan-500/15 text-cyan-300">
                        <i class="fas fa-rocket"></i>
                    </span>
                    <h4 class="text-sm font-semibold text-text">Talents émergents</h4>
                    <p class="text-xs text-muted">Nouvelles voix</p>
                </button>
            </div>
        </div>
    </section>

    <section class="py-12">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="space-y-10" id="discoveryGrid">
                <div class="discovery-section" data-section="trending">
                    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <span class="grid h-10 w-10 place-items-center rounded-xl bg-rose-500/15 text-rose-300">
                                <i class="fas fa-fire"></i>
                            </span>
                            <div>
                                <h3 class="text-lg font-semibold text-text">Tendances actuelles</h3>
                                <p class="text-xs text-muted">Les titres qui montent</p>
                            </div>
                        </div>
                        <button class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-semibold text-text hover:bg-white/10" data-shuffle="trending" type="button">
                            <i class="fas fa-random"></i> Mélanger
                        </button>
                    </div>

                    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3" data-grid>
                        <?php if (empty($trendingTracks)): ?>
                            <div class="col-span-full rounded-2xl border border-white/10 bg-white/5 p-8 text-center text-muted">
                                <i class="fas fa-music text-2xl"></i>
                                <h4 class="mt-3 text-sm font-semibold text-text">Aucune tendance pour le moment</h4>
                                <p class="mt-2 text-xs text-muted">Les tendances apparaîtront dès que des pistes seront ajoutées.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($trendingTracks as $track): ?>
                                <?php
                                $cover = createTrackCover($track['title'], $track['artist'], '#' . ($track['color'] ?: '0066CC'));
                                $cover = str_replace('class="img-fluid"', 'class="h-full w-full object-cover"', $cover);
                                ?>
                                <article class="group relative overflow-hidden rounded-2xl border border-white/10 bg-white/5 p-3 transition hover:-translate-y-1 hover:shadow-elev-2">
                                    <div class="relative aspect-square overflow-hidden rounded-xl">
                                        <?php echo $cover; ?>
                                        <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/0 to-black/0 opacity-0 transition group-hover:opacity-100"></div>
                                        <span class="absolute left-3 top-3 rounded-full bg-black/60 px-2 py-1 text-[11px] text-white">
                                            <i class="fas fa-arrow-up text-emerald-300"></i>
                                            <?php echo htmlspecialchars($track['plays']); ?>
                                        </span>
                                        <div class="absolute right-3 top-3 flex flex-col gap-2 opacity-0 transition group-hover:opacity-100">
                                            <button class="grid h-9 w-9 place-items-center rounded-full border border-white/10 bg-black/50 text-white backdrop-blur transition hover:bg-white/10" data-action="like" data-id="<?php echo (int)$track['id']; ?>" aria-label="Aimer">
                                                <i class="far fa-heart"></i>
                                            </button>
                                            <button class="grid h-9 w-9 place-items-center rounded-full border border-white/10 bg-black/50 text-white backdrop-blur transition hover:bg-white/10" data-action="playlist" data-id="<?php echo (int)$track['id']; ?>" aria-label="Ajouter à la playlist">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                            <button class="grid h-9 w-9 place-items-center rounded-full border border-white/10 bg-black/50 text-white backdrop-blur transition hover:bg-white/10" data-action="share" data-id="<?php echo (int)$track['id']; ?>" aria-label="Partager">
                                                <i class="fas fa-share-alt"></i>
                                            </button>
                                        </div>
                                        <button class="absolute bottom-3 right-3 grid h-11 w-11 place-items-center rounded-full bg-accent text-white opacity-0 shadow-elev-2 transition group-hover:opacity-100" data-play-id="<?php echo (int)$track['id']; ?>" data-play-title="<?php echo htmlspecialchars($track['title'], ENT_QUOTES); ?>" aria-label="Lire">
                                            <i class="fas fa-play"></i>
                                        </button>
                                    </div>
                                    <div class="mt-3">
                                        <h4 class="truncate text-sm font-semibold text-text"><?php echo htmlspecialchars($track['title']); ?></h4>
                                        <p class="truncate text-xs text-muted"><?php echo htmlspecialchars($track['artist']); ?></p><?php echo Panier::bouton('track', (int) $track['id'], (float) ($track['price'] ?? 0), !empty($track['is_free']), 'mt-2'); ?>
                                        <div class="mt-3 flex items-center justify-between text-xs text-muted">
                                            <span class="flex items-center gap-1"><i class="fas fa-play"></i> <?php echo htmlspecialchars($track['plays']); ?></span>
                                            <span><?php echo htmlspecialchars($track['duration_formatted'] ?? '0:00'); ?></span>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="discovery-section hidden" data-section="new">
                    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <span class="grid h-10 w-10 place-items-center rounded-xl bg-amber-500/15 text-amber-300">
                                <i class="fas fa-star"></i>
                            </span>
                            <div>
                                <h3 class="text-lg font-semibold text-text">Nouveautés</h3>
                                <p class="text-xs text-muted">Dernières sorties</p>
                            </div>
                        </div>
                        <button class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-semibold text-text hover:bg-white/10" data-shuffle="new" type="button">
                            <i class="fas fa-random"></i> Mélanger
                        </button>
                    </div>

                    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3" data-grid>
                        <?php if (empty($newTracks)): ?>
                            <div class="col-span-full rounded-2xl border border-white/10 bg-white/5 p-8 text-center text-muted">
                                <i class="fas fa-star text-2xl"></i>
                                <h4 class="mt-3 text-sm font-semibold text-text">Aucune nouveauté pour le moment</h4>
                                <p class="mt-2 text-xs text-muted">Les nouvelles sorties apparaîtront ici.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($newTracks as $track): ?>
                                <?php
                                $cover = createTrackCover($track['title'], $track['artist'], '#' . ($track['color'] ?: '43e97b'), 'NEW');
                                $cover = str_replace('class="img-fluid"', 'class="h-full w-full object-cover"', $cover);
                                ?>
                                <article class="group relative overflow-hidden rounded-2xl border border-white/10 bg-white/5 p-3 transition hover:-translate-y-1 hover:shadow-elev-2">
                                    <div class="relative aspect-square overflow-hidden rounded-xl">
                                        <?php echo $cover; ?>
                                        <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/0 to-black/0 opacity-0 transition group-hover:opacity-100"></div>
                                        <span class="absolute left-3 top-3 rounded-full border border-amber-500/30 bg-amber-500/20 px-2 py-1 text-[11px] text-amber-200">
                                            <i class="fas fa-star"></i> Nouveau
                                        </span>
                                        <div class="absolute right-3 top-3 flex flex-col gap-2 opacity-0 transition group-hover:opacity-100">
                                            <button class="grid h-9 w-9 place-items-center rounded-full border border-white/10 bg-black/50 text-white backdrop-blur transition hover:bg-white/10" data-action="like" data-id="<?php echo (int)$track['id']; ?>" aria-label="Aimer">
                                                <i class="far fa-heart"></i>
                                            </button>
                                            <button class="grid h-9 w-9 place-items-center rounded-full border border-white/10 bg-black/50 text-white backdrop-blur transition hover:bg-white/10" data-action="playlist" data-id="<?php echo (int)$track['id']; ?>" aria-label="Ajouter à la playlist">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                            <button class="grid h-9 w-9 place-items-center rounded-full border border-white/10 bg-black/50 text-white backdrop-blur transition hover:bg-white/10" data-action="share" data-id="<?php echo (int)$track['id']; ?>" aria-label="Partager">
                                                <i class="fas fa-share-alt"></i>
                                            </button>
                                        </div>
                                        <button class="absolute bottom-3 right-3 grid h-11 w-11 place-items-center rounded-full bg-accent text-white opacity-0 shadow-elev-2 transition group-hover:opacity-100" data-play-id="<?php echo (int)$track['id']; ?>" data-play-title="<?php echo htmlspecialchars($track['title'], ENT_QUOTES); ?>" aria-label="Lire">
                                            <i class="fas fa-play"></i>
                                        </button>
                                    </div>
                                    <div class="mt-3">
                                        <h4 class="truncate text-sm font-semibold text-text"><?php echo htmlspecialchars($track['title']); ?></h4>
                                        <p class="truncate text-xs text-muted"><?php echo htmlspecialchars($track['artist']); ?></p><?php echo Panier::bouton('track', (int) $track['id'], (float) ($track['price'] ?? 0), !empty($track['is_free']), 'mt-2'); ?>
                                        <div class="mt-3 text-xs text-muted">
                                            <span class="flex items-center gap-1"><i class="fas fa-calendar"></i> Il y a <?php echo htmlspecialchars($track['release'] ?? 'récemment'); ?></span>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="discovery-section hidden" data-section="classics">
                    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <span class="grid h-10 w-10 place-items-center rounded-xl bg-yellow-500/15 text-yellow-300">
                                <i class="fas fa-crown"></i>
                            </span>
                            <div>
                                <h3 class="text-lg font-semibold text-text">Classiques intemporels</h3>
                                <p class="text-xs text-muted">Les légendes de la scène</p>
                            </div>
                        </div>
                        <button class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-semibold text-text hover:bg-white/10" data-shuffle="classics" type="button">
                            <i class="fas fa-random"></i> Mélanger
                        </button>
                    </div>

                    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3" data-grid>
                        <?php if (empty($classicTracks)): ?>
                            <div class="col-span-full rounded-2xl border border-white/10 bg-white/5 p-8 text-center text-muted">
                                <i class="fas fa-crown text-2xl"></i>
                                <h4 class="mt-3 text-sm font-semibold text-text">Aucun classique pour le moment</h4>
                                <p class="mt-2 text-xs text-muted">Les classiques apparaîtront ici.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($classicTracks as $track): ?>
                                <?php
                                $cover = createTrackCover($track['title'], $track['artist'], '#' . ($track['color'] ?: '8B4513'));
                                $cover = str_replace('class="img-fluid"', 'class="h-full w-full object-cover"', $cover);
                                ?>
                                <article class="group relative overflow-hidden rounded-2xl border border-white/10 bg-white/5 p-3 transition hover:-translate-y-1 hover:shadow-elev-2">
                                    <div class="relative aspect-square overflow-hidden rounded-xl">
                                        <?php echo $cover; ?>
                                        <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/0 to-black/0 opacity-0 transition group-hover:opacity-100"></div>
                                        <span class="absolute left-3 top-3 rounded-full border border-yellow-500/30 bg-yellow-500/20 px-2 py-1 text-[11px] text-yellow-200">
                                            <i class="fas fa-crown"></i> Classique
                                        </span>
                                        <div class="absolute right-3 top-3 flex flex-col gap-2 opacity-0 transition group-hover:opacity-100">
                                            <button class="grid h-9 w-9 place-items-center rounded-full border border-white/10 bg-black/50 text-white backdrop-blur transition hover:bg-white/10" data-action="like" data-id="<?php echo (int)$track['id']; ?>" aria-label="Aimer">
                                                <i class="far fa-heart"></i>
                                            </button>
                                            <button class="grid h-9 w-9 place-items-center rounded-full border border-white/10 bg-black/50 text-white backdrop-blur transition hover:bg-white/10" data-action="playlist" data-id="<?php echo (int)$track['id']; ?>" aria-label="Ajouter à la playlist">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                            <button class="grid h-9 w-9 place-items-center rounded-full border border-white/10 bg-black/50 text-white backdrop-blur transition hover:bg-white/10" data-action="share" data-id="<?php echo (int)$track['id']; ?>" aria-label="Partager">
                                                <i class="fas fa-share-alt"></i>
                                            </button>
                                        </div>
                                        <button class="absolute bottom-3 right-3 grid h-11 w-11 place-items-center rounded-full bg-accent text-white opacity-0 shadow-elev-2 transition group-hover:opacity-100" data-play-id="<?php echo (int)$track['id']; ?>" data-play-title="<?php echo htmlspecialchars($track['title'], ENT_QUOTES); ?>" aria-label="Lire">
                                            <i class="fas fa-play"></i>
                                        </button>
                                    </div>
                                    <div class="mt-3">
                                        <h4 class="truncate text-sm font-semibold text-text"><?php echo htmlspecialchars($track['title']); ?></h4>
                                        <p class="truncate text-xs text-muted"><?php echo htmlspecialchars($track['artist']); ?></p><?php echo Panier::bouton('track', (int) $track['id'], (float) ($track['price'] ?? 0), !empty($track['is_free']), 'mt-2'); ?>
                                        <div class="mt-3 flex items-center justify-between text-xs text-muted">
                                            <span class="flex items-center gap-1"><i class="fas fa-history"></i> <?php echo htmlspecialchars($track['year'] ?? ''); ?></span>
                                            <span><?php echo htmlspecialchars($track['duration_formatted'] ?? '0:00'); ?></span>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="discovery-section hidden" data-section="rising">
                    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <span class="grid h-10 w-10 place-items-center rounded-xl bg-cyan-500/15 text-cyan-300">
                                <i class="fas fa-rocket"></i>
                            </span>
                            <div>
                                <h3 class="text-lg font-semibold text-text">Talents émergents</h3>
                                <p class="text-xs text-muted">Nouvelles voix à suivre</p>
                            </div>
                        </div>
                        <button class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-semibold text-text hover:bg-white/10" data-shuffle="rising" type="button">
                            <i class="fas fa-random"></i> Mélanger
                        </button>
                    </div>

                    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3" data-grid>
                        <?php if (empty($risingArtists)): ?>
                            <div class="col-span-full rounded-2xl border border-white/10 bg-white/5 p-8 text-center text-muted">
                                <i class="fas fa-rocket text-2xl"></i>
                                <h4 class="mt-3 text-sm font-semibold text-text">Aucun talent émergent pour le moment</h4>
                                <p class="mt-2 text-xs text-muted">Les nouvelles voix apparaîtront ici.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($risingArtists as $artist): ?>
                                <?php
                                $avatar = createArtistAvatar($artist['artist'], 200, '#' . ($artist['color'] ?: '0066CC'));
                                $avatar = str_replace('class="img-fluid rounded-circle"', 'class="h-full w-full rounded-full object-cover"', $avatar);
                                ?>
                                <article class="group relative overflow-hidden rounded-2xl border border-white/10 bg-white/5 p-4 transition hover:-translate-y-1 hover:shadow-elev-2">
                                    <div class="relative flex items-center justify-center">
                                        <div class="absolute -inset-6 rounded-full bg-gradient-to-br from-accent/30 to-accent-2/20 opacity-0 blur-2xl transition group-hover:opacity-100"></div>
                                        <div class="relative h-32 w-32 rounded-full border border-white/10 bg-surface/60 p-1">
                                            <?php echo $avatar; ?>
                                        </div>
                                        <span class="absolute right-4 top-4 rounded-full border border-cyan-500/30 bg-cyan-500/20 px-2 py-1 text-[11px] text-cyan-200">
                                            <i class="fas fa-rocket"></i> Émergent
                                        </span>
                                    </div>
                                    <div class="mt-4 text-center">
                                        <h4 class="text-sm font-semibold text-text"><?php echo htmlspecialchars($artist['artist']); ?></h4>
                                        <p class="text-xs text-muted"><?php echo htmlspecialchars($artist['artist_genres'] ?? 'Artiste'); ?></p>
                                        <div class="mt-3 flex items-center justify-center gap-4 text-xs text-muted">
                                            <span class="flex items-center gap-1"><i class="fas fa-users"></i> <?php echo htmlspecialchars($artist['followers'] ?? 0); ?> fans</span>
                                            <span class="flex items-center gap-1"><i class="fas fa-music"></i> <?php echo (int)($artist['tracks_count'] ?? 0); ?> titres</span>
                                        </div>
                                    </div>
                                    <div class="mt-4 flex items-center justify-center gap-2">
                                        <button class="grid h-9 w-9 place-items-center rounded-full border border-white/10 bg-white/5 text-text transition hover:bg-white/10" data-action="like" data-id="<?php echo (int)$artist['id']; ?>" aria-label="Suivre">
                                            <i class="far fa-heart"></i>
                                        </button>
                                        <button class="grid h-9 w-9 place-items-center rounded-full border border-white/10 bg-white/5 text-text transition hover:bg-white/10" data-action="share" data-id="<?php echo (int)$artist['id']; ?>" aria-label="Partager">
                                            <i class="fas fa-share-alt"></i>
                                        </button>
                                        <button class="grid h-9 w-9 place-items-center rounded-full bg-accent text-white shadow-elev-2" data-play-id="<?php echo (int)$artist['id']; ?>" data-play-title="<?php echo htmlspecialchars($artist['artist'], ENT_QUOTES); ?>" aria-label="Écouter">
                                            <i class="fas fa-play"></i>
                                        </button>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-14">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-8 lg:grid-cols-3">
                <div class="lg:col-span-2">
                    <h2 class="text-2xl font-display font-bold text-text">Recommandations personnalisées</h2>
                    <p class="mt-2 text-sm text-muted">Basées sur vos goûts musicaux et votre historique.</p>

                    <div class="mt-6 grid gap-4">
                        <?php if (!empty($genres)): ?>
                            <?php
                            $recGenres = array_slice($genres, 0, 3);
                            $recIcons = ['fas fa-brain', 'fas fa-music', 'fas fa-chart-line'];
                            $recLabels = ['Parce que vous aimez', 'Similaire à vos favoris', 'Tendance pour vous'];
                            foreach ($recGenres as $gi => $rg):
                                $gName = htmlspecialchars($rg['name_french'] ?? $rg['name']);
                            ?>
                                <div class="flex items-start gap-4 rounded-2xl border border-white/10 bg-white/5 p-4">
                                    <div class="grid h-10 w-10 place-items-center rounded-xl bg-accent/20 text-accent">
                                        <i class="<?php echo $recIcons[$gi]; ?>"></i>
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-semibold text-text"><?php echo $recLabels[$gi]; ?> le <?php echo $gName; ?></h4>
                                        <p class="mt-1 text-xs text-muted">Découvrez les artistes et pistes du genre <?php echo $gName; ?>.</p>
                                        <button class="mt-3 inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-semibold text-text hover:bg-white/10" data-filter-genre="<?php echo htmlspecialchars($rg['name'], ENT_QUOTES); ?>" type="button">
                                            Découvrir
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="flex items-start gap-4 rounded-2xl border border-white/10 bg-white/5 p-4">
                                <div class="grid h-10 w-10 place-items-center rounded-xl bg-accent/20 text-accent">
                                    <i class="fas fa-brain"></i>
                                </div>
                                <div>
                                    <h4 class="text-sm font-semibold text-text">Explorez la musique tchadienne</h4>
                                    <p class="mt-1 text-xs text-muted">Commencez par écouter des pistes pour recevoir des recommandations.</p>
                                    <button class="mt-3 inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-semibold text-text hover:bg-white/10" data-filter-shortcut="trending" type="button">
                                        Commencer
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="rounded-3xl border border-white/10 bg-surface/70 p-6">
                    <h3 class="text-lg font-semibold text-text">Vos statistiques de découverte</h3>
                    <div class="mt-6 space-y-4">
                        <div class="flex items-start gap-3">
                            <div class="grid h-10 w-10 place-items-center rounded-xl bg-accent/20 text-accent">
                                <i class="fas fa-compass"></i>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-text"><?php echo isLoggedIn() ? (int)$discoveryStats['artists_discovered'] : '--'; ?></p>
                                <p class="text-xs text-muted">Artistes découverts ce mois</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <div class="grid h-10 w-10 place-items-center rounded-xl bg-accent-2/20 text-accent-2">
                                <i class="fas fa-headphones"></i>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-text"><?php echo isLoggedIn() ? (int)$discoveryStats['listening_hours'] . 'h' : '--'; ?></p>
                                <p class="text-xs text-muted">Temps d'exploration cette semaine</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <div class="grid h-10 w-10 place-items-center rounded-xl bg-white/10 text-text">
                                <i class="fas fa-heart"></i>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-text"><?php echo isLoggedIn() ? (int)$discoveryStats['favorites_count'] : '--'; ?></p>
                                <p class="text-xs text-muted">Titres ajoutés aux favoris</p>
                            </div>
                        </div>
                    </div>
                    <?php if (!isLoggedIn()): ?>
                        <a href="<?php echo SITE_URL; ?>/login.php" class="mt-6 inline-flex w-full items-center justify-center rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-semibold text-text hover:bg-white/10">
                            Connectez-vous pour voir vos stats
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include 'includes/footer-tailwind.php'; ?>
