<?php
/**
 * Page d'accueil - Tchadok Platform
 * Migration Tailwind (progressive)
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/database.php';
require_once 'assets/images/placeholders.php';

$pageTitle = 'Accueil';
$pageDescription = 'La musique tchadienne à portée de clic. Découvrez, écoutez et soutenez vos artistes préférés du Tchad.';

$additionalCSS = [
    SITE_URL . '/assets/css/home-tailwind.css'
];

$homeJsVersion = @filemtime(__DIR__ . '/assets/js/home.js') ?: time();

$additionalJS = [
    SITE_URL . '/assets/js/home.js?v=' . $homeJsVersion
];

include 'includes/header-tailwind.php';
?>

<main class="pt-0">
    <section class="home-hero relative overflow-hidden bg-bg pb-14 pt-12 sm:pt-16 lg:pt-20" id="accueil">
        <span class="home-hero-note note-one">&#9835;</span>
        <span class="home-hero-note note-two">&#9833;</span>
        <span class="home-hero-note note-three">&#9835;</span>
        <div class="absolute -left-32 -top-32 h-72 w-72 rounded-full bg-[#0066CC]/20 blur-3xl"></div>
        <div class="absolute -bottom-40 right-0 h-96 w-96 rounded-full bg-[#FFD700]/20 blur-3xl"></div>
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="home-hero-grid grid items-center gap-12 lg:grid-cols-2">
                <div class="home-hero-content">
                    <div class="home-hero-pill inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold text-muted">
                        <span class="h-2 w-2 rounded-full bg-[#0066CC]"></span>
                        Radio tchadienne 24/7
                    </div>
                    <h1 class="home-hero-title mt-5 text-4xl font-display font-bold leading-tight text-text sm:text-5xl lg:text-6xl">
                        La Musique Tchadienne à Portée de Clic
                    </h1>
                    <p class="home-hero-subtitle mt-4 text-base text-muted sm:text-lg">
                        Découvrez, écoutez et partagez le meilleur de la scène musicale tchadienne.
                        Des artistes légendaires aux nouveaux talents, vivez l'expérience authentique.
                    </p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <?php if (!isLoggedIn()): ?>
                            <a href="<?php echo SITE_URL; ?>/register.php" class="home-primary-btn inline-flex items-center gap-2 rounded-full bg-[#0066CC] px-5 py-3 text-sm font-semibold text-white shadow-elev-1 transition hover:-translate-y-0.5 hover:shadow-elev-2">
                                <i class="fas fa-play-circle"></i> Commencer l'aventure
                            </a>
                            <a href="#decouvrir" class="home-secondary-btn inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-5 py-3 text-sm font-semibold text-text hover:bg-white/10">
                                Explorer
                            </a>
                        <?php else: ?>
                            <button class="home-primary-btn inline-flex items-center gap-2 rounded-full bg-[#0066CC] px-5 py-3 text-sm font-semibold text-white shadow-elev-1 transition hover:-translate-y-0.5 hover:shadow-elev-2" type="button" data-radio-play>
                                <i class="fas fa-broadcast-tower"></i> Radio Live
                            </button>
                            <a href="#decouvrir" class="home-secondary-btn inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-5 py-3 text-sm font-semibold text-text hover:bg-white/10">
                                Découvrir
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="mt-8 flex flex-wrap gap-6 text-xs text-muted">
                        <div class="home-feature flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-[#0066CC]"></span>
                            Première plateforme musicale tchadienne
                        </div>
                        <div class="home-feature flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-[#FFD700]"></span>
                            Catalogue local en croissance
                        </div>
                    </div>
                </div>
                <div class="home-hero-visual relative mx-auto w-64 lg:w-80">
                    <div class="absolute inset-0 rounded-full bg-gradient-to-br from-[#0066CC]/30 via-transparent to-[#FFD700]/30 blur-3xl"></div>
                    <div class="relative aspect-square rounded-full border border-white/10 bg-surface/70 p-8 shadow-elev-3">
                        <div class="logo-float">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" class="h-full w-full">
                                <defs>
                                    <linearGradient id="logoGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                        <stop offset="0%" stop-color="#0066CC" stop-opacity="1" />
                                        <stop offset="100%" stop-color="#0052A3" stop-opacity="1" />
                                    </linearGradient>
                                </defs>
                                <circle cx="50" cy="50" r="48" fill="url(#logoGrad)"/>
                                <path d="M30 45 L30 55 L40 60 L40 40 Z M45 35 L45 65 L55 70 L55 30 Z M60 40 L60 60 L70 55 L70 45 Z" fill="#FFD700"/>
                                <circle cx="50" cy="50" r="40" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="0.6"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="relative z-10 -mt-10 scroll-mt-24" id="radio">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="home-radio-card rounded-3xl border border-white/10 bg-gradient-to-br from-surface/90 via-surface/70 to-bg p-6 shadow-elev-2">
                <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex items-center gap-4">
                        <div class="home-radio-icon grid h-12 w-12 place-items-center rounded-2xl bg-[#0066CC]/15 text-[#0066CC]">
                            <i class="fas fa-broadcast-tower"></i>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-[0.2em] text-muted">Radio live</p>
                            <h3 class="text-lg font-semibold text-text">Tchadok Radio</h3>
                            <p class="text-sm text-muted">En direct du cœur du Tchad</p>
                        </div>
                    </div>
                    <div class="flex items-center justify-center">
                        <div class="flex h-10 items-end gap-1">
                            <span class="visualizer-bar h-2 w-1 rounded-full bg-[#FFD700]"></span>
                            <span class="visualizer-bar h-4 w-1 rounded-full bg-[#FFD700]"></span>
                            <span class="visualizer-bar h-6 w-1 rounded-full bg-[#FFD700]"></span>
                            <span class="visualizer-bar h-3 w-1 rounded-full bg-[#FFD700]"></span>
                            <span class="visualizer-bar h-7 w-1 rounded-full bg-[#FFD700]"></span>
                            <span class="visualizer-bar h-4 w-1 rounded-full bg-[#FFD700]"></span>
                            <span class="visualizer-bar h-5 w-1 rounded-full bg-[#FFD700]"></span>
                            <span class="visualizer-bar h-3 w-1 rounded-full bg-[#FFD700]"></span>
                        </div>
                    </div>
                    <div class="flex items-center justify-center gap-4">
                        <button class="home-radio-button grid h-12 w-12 place-items-center rounded-full bg-[#0066CC] text-white shadow-elev-2" id="radioToggle" aria-label="Lecture radio" aria-pressed="false" type="button">
                            <i class="fas fa-play" id="radioPlayIcon"></i>
                        </button>
                        <div class="flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-muted">
                            <i class="fas fa-volume-up"></i>
                            <span>80%</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="home-releases-section scroll-mt-24 py-16" id="decouvrir">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="home-section-header mb-8 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h2 class="home-section-title text-2xl font-display font-bold text-text">Nouvelles Sorties</h2>
                    <p class="home-section-subtitle text-sm text-muted">Les derniers hits de la scène musicale tchadienne</p>
                </div>
                <a href="<?php echo SITE_URL; ?>/albums.php" class="home-section-link text-sm font-semibold text-muted hover:text-text">Voir tout</a>
            </div>
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <?php
                $releases = getNewReleases(4);
                foreach ($releases as $release):
                    $badgeTone = 'border-white/10 bg-white/5 text-muted';
                    if (!empty($release['is_featured'])) {
                        $badgeTone = 'border-emerald-500/30 bg-emerald-500/15 text-emerald-200';
                    } elseif (!empty($release['is_free'])) {
                        $badgeTone = 'border-amber-500/30 bg-amber-500/15 text-amber-200';
                    }
                    $metaText = isset($release['extra']) ? $release['extra'] : $release['price'];
                    $metaText = str_replace('text-danger', 'text-rose-400', $metaText);
                    $cover = createAlbumCover($release['title'], $release['artist'], $release['type'], '#' . $release['color']);
                    $cover = str_replace('class="img-fluid"', 'class="h-full w-full object-cover"', $cover);
                ?>
                <article class="home-music-card group relative overflow-hidden rounded-2xl border border-white/10 bg-white/5 p-3 transition hover:-translate-y-1 hover:shadow-elev-2">
                    <div class="relative aspect-square overflow-hidden rounded-xl">
                        <?php echo $cover; ?>
                        <div class="home-play-overlay absolute inset-0 bg-gradient-to-t from-black/60 via-black/0 to-black/0 opacity-0 transition group-hover:opacity-100"></div>
                        <button class="home-play-btn absolute bottom-3 right-3 grid h-11 w-11 place-items-center rounded-full bg-[#0066CC] text-white opacity-0 shadow-elev-2 transition group-hover:opacity-100" data-play-track="<?php echo $release['id']; ?>" aria-label="Lire">
                            <i class="fas fa-play"></i>
                        </button>
                    </div>
                    <div class="mt-3">
                        <h3 class="truncate text-sm font-semibold text-text"><?php echo htmlspecialchars((string) ($release['title'] ?? 'Sortie')); ?></h3>
                        <p class="truncate text-xs text-muted"><?php echo htmlspecialchars((string) ($release['artist'] ?? 'Artiste')); ?></p>
                        <div class="mt-3 flex items-center justify-between text-xs">
                            <span class="rounded-full border px-2 py-1 text-[11px] uppercase tracking-wide <?php echo $badgeTone; ?>">
                                <?php echo htmlspecialchars((string) ($release['badge'] ?? '')); ?>
                            </span>
                            <span class="text-muted"><?php echo $metaText; ?></span>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="home-emissions-section scroll-mt-24 py-16" id="emissions">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="home-section-header mb-8">
                <h2 class="home-section-title text-2xl font-display font-bold text-text">Emissions Musicales</h2>
                <p class="home-section-subtitle text-sm text-muted">Ne manquez pas vos émissions préférées</p>
            </div>
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                <?php
                $radioShows = getRadioShows(4);
                $showThemes = [
                    'home-emission-theme-1',
                    'home-emission-theme-2',
                    'home-emission-theme-3',
                    'home-emission-theme-4'
                ];
                ?>
                <?php if (empty($radioShows)): ?>
                    <div class="col-span-full rounded-3xl border border-white/10 bg-white/5 p-8 text-center text-muted">
                        <i class="fas fa-podcast text-2xl"></i>
                        <h4 class="mt-3 text-base font-semibold text-text">Aucune émission programmée</h4>
                        <p class="mt-2 text-sm text-muted">Les émissions apparaîtront dès qu'elles seront ajoutées.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($radioShows as $index => $show): ?>
                        <?php $theme = $showThemes[$index % count($showThemes)]; ?>
                        <article class="home-emission-card group relative flex h-full flex-col overflow-hidden rounded-3xl border border-white/10 bg-gradient-to-br <?php echo $theme; ?> p-6">
                            <div class="home-emission-glow"></div>
                            <div class="home-emission-top">
                                <span class="home-emission-time inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold text-text">
                                    <i class="fas fa-clock text-muted"></i>
                                    <?php echo htmlspecialchars((string) ($show['time_display'] ?? 'Horaire à confirmer')); ?>
                                </span>
                                <span class="home-emission-tag">Radio Live</span>
                            </div>
                            <h3 class="home-emission-title text-lg font-semibold text-text"><?php echo htmlspecialchars((string) ($show['title'] ?? 'Émission')); ?></h3>
                            <p class="home-emission-desc mt-2 text-sm text-muted"><?php echo htmlspecialchars((string) ($show['description'] ?? 'Programme en cours de mise à jour.')); ?></p>
                            <div class="home-emission-meta mt-4">
                                <span class="home-emission-host text-xs text-muted">
                                    Animé par <?php echo htmlspecialchars((string) ($show['host'] ?? 'Équipe Tchadok')); ?>
                                </span>
                                <div class="home-emission-wave" aria-hidden="true">
                                    <span></span><span></span><span></span><span></span><span></span>
                                </div>
                            </div>
                            <button class="home-emission-action mt-6 inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-text hover:bg-white/10" type="button">
                                <i class="fas fa-calendar-plus"></i> Programmer
                            </button>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="scroll-mt-24 py-16" id="artistes">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-8">
                <h2 class="home-section-title text-2xl font-display font-bold text-text">Artistes Populaires</h2>
                <p class="home-section-subtitle text-sm text-muted">Les stars de la musique tchadienne</p>
            </div>
            <div class="grid grid-cols-2 gap-6 sm:grid-cols-3 lg:grid-cols-6">
                <?php
                $artists = getPopularArtists(6);
                $badgeClasses = [
                    'artist-badge--blue',
                    'artist-badge--blue-dark',
                    'artist-badge--gold',
                    'artist-badge--gold-soft'
                ];
                foreach ($artists as $index => $artist):
                    $words = preg_split('/\s+/', trim($artist['name']));
                    $initials = '';
                    $letters = 0;
                    foreach ($words as $word) {
                        if ($word === '') {
                            continue;
                        }
                        $char = function_exists('mb_substr') ? mb_substr($word, 0, 1, 'UTF-8') : substr($word, 0, 1);
                        $initials .= function_exists('mb_strtoupper') ? mb_strtoupper($char, 'UTF-8') : strtoupper($char);
                        $letters++;
                        if ($letters >= 2) {
                            break;
                        }
                    }
                    if ($initials === '') {
                        $initials = 'TD';
                    }
                    $badgeClass = $badgeClasses[$index % count($badgeClasses)];
                ?>
                <div class="home-artist-card group flex flex-col items-center text-center">
                    <div class="relative">
                        <div class="absolute inset-0 rounded-full bg-gradient-to-br from-[#0066CC]/30 to-[#FFD700]/30 blur-lg opacity-0 transition group-hover:opacity-100"></div>
                        <div class="home-artist-avatar relative h-24 w-24 rounded-full border border-white/10 bg-surface/60 p-1 sm:h-28 sm:w-28">
                            <div class="artist-badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($initials); ?></div>
                        </div>
                    </div>
                    <h3 class="mt-3 text-sm font-semibold text-text"><?php echo htmlspecialchars((string) ($artist['name'] ?? 'Artiste')); ?></h3>
                    <p class="text-xs text-muted"><?php echo htmlspecialchars((string) ($artist['genre'] ?? 'Genre non renseigné')); ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="home-stats-section py-14">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <?php $stats = getPlatformStats(); ?>
                <div class="home-stat-card rounded-2xl border border-white/10 bg-white/5 p-6">
                    <div class="stat-number text-3xl font-display font-bold text-text" data-count="<?php echo $stats['total_tracks']; ?>">0</div>
                    <p class="mt-2 text-xs uppercase tracking-[0.2em] text-muted">Titres disponibles</p>
                </div>
                <div class="home-stat-card rounded-2xl border border-white/10 bg-white/5 p-6">
                    <div class="stat-number text-3xl font-display font-bold text-text" data-count="<?php echo $stats['total_artists']; ?>">0</div>
                    <p class="mt-2 text-xs uppercase tracking-[0.2em] text-muted">Artistes partenaires</p>
                </div>
                <div class="home-stat-card rounded-2xl border border-white/10 bg-white/5 p-6">
                    <div class="stat-number text-3xl font-display font-bold text-text" data-count="<?php echo $stats['total_users']; ?>">0</div>
                    <p class="mt-2 text-xs uppercase tracking-[0.2em] text-muted">Utilisateurs actifs</p>
                </div>
                <div class="home-stat-card rounded-2xl border border-white/10 bg-white/5 p-6">
                    <div class="stat-number text-3xl font-display font-bold text-text" data-count="<?php echo $stats['streaming_hours']; ?>">0</div>
                    <p class="mt-2 text-xs uppercase tracking-[0.2em] text-muted">Streaming / jour</p>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include 'includes/footer-tailwind.php'; ?>
