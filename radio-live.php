<?php
/**
 * Radio Live - Tchadok Platform
 * Migration Tailwind (progressive)
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'assets/images/placeholders.php';

$pageTitle = 'Radio Live';
$pageDescription = 'Écoutez Tchadok Radio en direct - 24/7 de la meilleure musique tchadienne en continu.';

$recentStreams = getRecentStreams(5);
$trendingTracks = getTrendingTracks(5);
$liveInfo = getRadioLiveInfo();

$schedule = getRadioSchedule(8);

$currentShowName = 'Programmation indisponible';
$currentHostName = 'Aucune émission programmée';
if (!empty($liveInfo['current_show'])) {
    $currentShowName = $liveInfo['current_show']['title'] ?? $currentShowName;
    $currentHostName = 'avec ' . ($liveInfo['current_show']['host'] ?? 'Tchadok Radio');
    $timeRange = formatTimeRange($liveInfo['current_show']['start_time'] ?? null, $liveInfo['current_show']['end_time'] ?? null);
    if ($timeRange) {
        $currentHostName .= ' - ' . $timeRange;
    }
}
foreach ($schedule as &$program) {
    $start = $program['start_time'] ?? null;
    $end = $program['end_time'] ?? null;
    $program['time'] = $program['time_display'] ?? '';
    $program['show'] = $program['title'] ?? 'Émission';
    $program['host'] = $program['host'] ?? 'Tchadok Radio';

    if ($start && $end) {
        $today = date('Y-m-d');
        $startTs = strtotime($today . ' ' . $start);
        $endTs = strtotime($today . ' ' . $end);
        if ($endTs <= $startTs) {
            $endTs = strtotime('+1 day', $endTs);
        }
        $now = time();
        if ($now >= $startTs && $now < $endTs) {
            $program['status'] = 'current';
            $currentShowName = $program['show'];
            $currentHostName = 'avec ' . $program['host'] . ' - ' . $program['time'];
        } elseif ($now >= $endTs) {
            $program['status'] = 'past';
        } else {
            $program['status'] = 'upcoming';
        }
    } else {
        $program['status'] = 'upcoming';
    }
}
unset($program);

$jsTracks = [];
if (!empty($recentStreams)) {
    foreach ($recentStreams as $s) {
        $jsTracks[] = ['title' => $s['title'], 'artist' => $s['artist'], 'id' => $s['track_id'] ?? null];
    }
} elseif (!empty($trendingTracks)) {
    foreach ($trendingTracks as $t) {
        $jsTracks[] = ['title' => $t['title'], 'artist' => $t['artist'], 'id' => $t['id'] ?? null];
    }
}
if (empty($jsTracks)) {
    $jsTracks = [];
}

$currentTrackTitle = $liveInfo['current_track']['title'] ?? ($jsTracks[0]['title'] ?? 'Aucun titre');
$currentTrackArtist = $liveInfo['current_track']['artist'] ?? ($jsTracks[0]['artist'] ?? 'Radio Live');
$streamUrl = $liveInfo['stream_url'] ?? null;

$additionalCSS = [
    SITE_URL . '/assets/css/radio-live-tailwind.css'
];

$radioLiveJsVersion = @filemtime(__DIR__ . '/assets/js/radio-live.js') ?: time();

$additionalJS = [
    SITE_URL . '/assets/js/radio-live.js?v=' . $radioLiveJsVersion
];

include 'includes/header-tailwind.php';
?>

<main class="pt-0">
    <div id="radioData"
         data-tracks="<?php echo htmlspecialchars(json_encode($jsTracks, JSON_UNESCAPED_UNICODE), ENT_QUOTES); ?>"
         data-stream-url="<?php echo htmlspecialchars((string) $streamUrl, ENT_QUOTES); ?>">
    </div>

    <section class="page-hero relative min-h-screen items-center overflow-hidden bg-bg pb-14 pt-12 sm:pt-16 lg:pt-20 lg:flex" id="radio-hero">
        <div class="absolute -left-32 -top-32 h-72 w-72 rounded-full bg-[#0066CC]/20 blur-3xl"></div>
        <div class="absolute -bottom-40 right-0 h-96 w-96 rounded-full bg-[#FFD700]/20 blur-3xl"></div>
        <span class="page-hero-note note-1">&#9834;</span>
        <span class="page-hero-note note-2">&#9835;</span>
        <span class="page-hero-note note-3">&#9834;</span>
        <span class="page-hero-note note-4">&#9835;</span>

        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid items-center gap-12 lg:grid-cols-2">
                <div class="page-hero-content">
                    <?php if (!empty($liveInfo['is_live'])): ?>
                        <div class="inline-flex items-center gap-2 rounded-full border border-emerald-400/30 bg-emerald-400/10 px-3 py-1 text-xs font-semibold text-emerald-200">
                            <span class="live-dot"></span>
                            EN DIRECT
                        </div>
                    <?php else: ?>
                        <div class="inline-flex items-center gap-2 rounded-full border border-amber-400/30 bg-amber-400/10 px-3 py-1 text-xs font-semibold text-amber-200">
                            <i class="fas fa-circle-notch"></i>
                            HORS LIGNE
                        </div>
                    <?php endif; ?>
                    <h1 class="page-hero-title mt-5 text-4xl font-display font-bold leading-tight text-text sm:text-5xl">
                        Tchadok Radio Live
                    </h1>
                    <p class="page-hero-subtitle mt-4 text-base text-muted sm:text-lg">
                        24/7 de la meilleure musique tchadienne. Découvrez les hits du moment, les classiques
                        intemporels et les nouveaux talents de la scène musicale tchadienne.
                    </p>

                    <div class="mt-6 rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p class="text-xs uppercase tracking-[0.2em] text-muted">Émission en cours</p>
                        <h5 class="mt-2 text-lg font-semibold text-text" id="currentShow"><?php echo htmlspecialchars($currentShowName); ?></h5>
                        <p class="text-sm text-muted" id="currentHost"><?php echo htmlspecialchars($currentHostName); ?></p>
                    </div>

                    <div class="mt-6 flex flex-wrap items-center gap-4">
                        <button class="grid h-14 w-14 place-items-center rounded-full bg-accent text-white shadow-elev-2 transition hover:-translate-y-0.5" id="mainPlayBtn" data-radio-play type="button" aria-label="Lecture ou pause de la radio">
                            <i class="fas fa-play" id="mainPlayIcon"></i>
                        </button>
                        <div class="flex items-center gap-3 rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs text-muted">
                            <i class="fas fa-volume-up"></i>
                            <input type="range" class="h-1 w-28 accent-accent" min="0" max="100" value="80" id="volumeSlider">
                            <span id="volumeDisplay">80%</span>
                        </div>
                    </div>
                </div>

                <div class="rounded-3xl border border-white/10 bg-surface/70 p-6 shadow-elev-2">
                    <div class="visualizer-bars flex h-32 items-end justify-between gap-2">
                        <?php for ($i = 0; $i < 24; $i++): ?>
                            <span class="radio-bar"></span>
                        <?php endfor; ?>
                    </div>

                    <?php
                    $cover = createTrackCover($currentTrackTitle, $currentTrackArtist, '#2F6DE0');
                    $cover = str_replace('class="img-fluid"', 'class="h-full w-full object-cover"', $cover);
                    ?>
                    <div class="mt-6 flex flex-wrap items-center gap-4 rounded-2xl border border-white/10 bg-white/5 p-4">
                        <div class="h-16 w-16 overflow-hidden rounded-xl">
                            <?php echo $cover; ?>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h6 class="truncate text-sm font-semibold text-text" id="currentTrack"><?php echo htmlspecialchars($currentTrackTitle); ?></h6>
                            <p class="truncate text-xs text-muted" id="currentArtist"><?php echo htmlspecialchars($currentTrackArtist); ?></p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button class="grid h-9 w-9 place-items-center rounded-full border border-white/10 bg-white/5 text-text hover:bg-white/10" data-action="favorite" aria-label="Ajouter aux favoris">
                                <i class="far fa-heart" id="favoriteIcon"></i>
                            </button>
                            <button class="grid h-9 w-9 place-items-center rounded-full border border-white/10 bg-white/5 text-text hover:bg-white/10" data-action="share" aria-label="Partager le titre">
                                <i class="fas fa-share-alt"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-14">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-6">
                <h2 class="text-2xl font-display font-bold text-text">Programme de la journée</h2>
                <p class="text-sm text-muted">Découvrez nos émissions et horaires</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <?php if (empty($schedule)): ?>
                    <div class="col-span-full rounded-3xl border border-white/10 bg-white/5 p-8 text-center text-muted">
                        <i class="fas fa-calendar text-2xl"></i>
                        <h4 class="mt-3 text-base font-semibold text-text">Aucune émission programmée</h4>
                        <p class="mt-2 text-sm text-muted">Le programme sera disponible dès qu'il sera configuré.</p>
                    </div>
                <?php else: ?>
                    <?php
                    $scheduleThemes = [
                        'from-sky-500/20 via-sky-500/10 to-transparent',
                        'from-emerald-500/20 via-emerald-500/10 to-transparent',
                        'from-amber-400/25 via-amber-400/10 to-transparent',
                        'from-rose-400/20 via-rose-400/10 to-transparent',
                        'from-cyan-300/20 via-cyan-300/10 to-transparent',
                        'from-violet-500/20 via-violet-500/10 to-transparent',
                        'from-fuchsia-400/20 via-fuchsia-400/10 to-transparent',
                        'from-indigo-500/20 via-indigo-500/10 to-transparent',
                    ];
                    foreach ($schedule as $index => $program):
                        $theme = $scheduleThemes[$index % count($scheduleThemes)];
                        $statusClass = $program['status'] === 'current' ? 'ring-1 ring-accent/40' : ($program['status'] === 'past' ? 'opacity-70' : '');
                    ?>
                    <div class="rounded-3xl border border-white/10 bg-gradient-to-br <?php echo $theme; ?> p-5 <?php echo $statusClass; ?>">
                        <div class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold text-text">
                            <i class="fas fa-clock text-muted"></i><?php echo htmlspecialchars($program['time']); ?>
                        </div>
                        <h3 class="mt-4 text-lg font-semibold text-text"><?php echo htmlspecialchars($program['show']); ?></h3>
                        <p class="mt-2 text-sm text-muted">avec <?php echo htmlspecialchars($program['host']); ?></p>

                        <?php if ($program['status'] === 'current'): ?>
                            <div class="mt-4 inline-flex items-center gap-2 rounded-full border border-emerald-400/30 bg-emerald-400/10 px-3 py-1 text-xs font-semibold text-emerald-200">
                                <span class="live-dot"></span>
                                EN DIRECT
                            </div>
                        <?php elseif ($program['status'] === 'upcoming'): ?>
                            <button class="mt-4 inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold text-text hover:bg-white/10" type="button">
                                <i class="fas fa-bell"></i> Rappel
                            </button>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="py-14">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-6">
                <h2 class="text-2xl font-display font-bold text-text">Récemment diffusé</h2>
                <p class="text-sm text-muted">Les derniers titres passés à l'antenne</p>
            </div>

            <div class="space-y-3">
                <?php if (empty($recentStreams)): ?>
                    <?php if (!empty($trendingTracks)): ?>
                        <?php foreach ($trendingTracks as $index => $track): ?>
                            <div class="flex flex-wrap items-center gap-4 rounded-2xl border border-white/10 bg-white/5 p-4">
                                <div class="text-sm font-semibold text-muted"><?php echo $index + 1; ?></div>
                                <div class="min-w-0 flex-1">
                                    <h4 class="truncate text-sm font-semibold text-text"><?php echo htmlspecialchars($track['title']); ?></h4>
                                    <p class="truncate text-xs text-muted"><?php echo htmlspecialchars($track['artist']); ?></p>
                                </div>
                                <div class="text-xs text-muted"><?php echo htmlspecialchars($track['duration_formatted'] ?? '0:00'); ?></div>
                                <div class="flex items-center gap-2">
                                    <button class="grid h-9 w-9 place-items-center rounded-full border border-white/10 bg-white/5 text-text hover:bg-white/10" data-action="replay" data-id="<?php echo (int)$track['id']; ?>" aria-label="Rejouer">
                                        <i class="fas fa-redo"></i>
                                    </button>
                                    <button class="grid h-9 w-9 place-items-center rounded-full border border-white/10 bg-white/5 text-text hover:bg-white/10" data-action="like" data-id="<?php echo (int)$track['id']; ?>" aria-label="Aimer">
                                        <i class="far fa-heart"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-8 text-center text-muted">
                            <i class="fas fa-music text-2xl"></i>
                            <h4 class="mt-3 text-sm font-semibold text-text">Aucun titre récent</h4>
                            <p class="mt-2 text-xs text-muted">Les titres diffusés apparaîtront ici.</p>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <?php foreach ($recentStreams as $index => $stream): ?>
                        <div class="flex flex-wrap items-center gap-4 rounded-2xl border border-white/10 bg-white/5 p-4">
                            <div class="text-sm font-semibold text-muted"><?php echo $index + 1; ?></div>
                            <div class="min-w-0 flex-1">
                                <h4 class="truncate text-sm font-semibold text-text"><?php echo htmlspecialchars($stream['title']); ?></h4>
                                <p class="truncate text-xs text-muted"><?php echo htmlspecialchars($stream['artist']); ?></p>
                            </div>
                            <div class="text-xs text-muted">
                                <span><?php echo htmlspecialchars($stream['time'] ?? ''); ?></span>
                                <span class="ml-2"><?php echo htmlspecialchars($stream['duration_formatted'] ?? ''); ?></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <button class="grid h-9 w-9 place-items-center rounded-full border border-white/10 bg-white/5 text-text hover:bg-white/10" data-action="replay" data-id="<?php echo (int)($stream['track_id'] ?? 0); ?>" aria-label="Rejouer">
                                    <i class="fas fa-redo"></i>
                                </button>
                                <button class="grid h-9 w-9 place-items-center rounded-full border border-white/10 bg-white/5 text-text hover:bg-white/10" data-action="like" data-id="<?php echo $index; ?>" aria-label="Aimer">
                                    <i class="far fa-heart"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="py-14">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-6">
                <h2 class="text-2xl font-display font-bold text-text">Réseaux sociaux</h2>
                <p class="text-sm text-muted">Suivez-nous et partagez vos moments musicaux</p>
            </div>
            <div class="grid gap-4 lg:grid-cols-3">
                <div class="rounded-3xl border border-white/10 bg-white/5 p-5">
                    <div class="flex items-center gap-3 text-sm font-semibold text-text">
                        <span class="grid h-10 w-10 place-items-center rounded-xl bg-blue-500/20 text-blue-200">
                            <i class="fab fa-facebook-f"></i>
                        </span>
                        Facebook
                    </div>
                    <p class="mt-4 text-sm text-muted">En direct maintenant : "<?php echo htmlspecialchars($currentShowName); ?>" !</p>
                    <button class="mt-4 inline-flex items-center gap-2 rounded-full border border-blue-500/30 bg-blue-500/10 px-4 py-2 text-xs font-semibold text-blue-200" type="button">Suivre</button>
                </div>
                <div class="rounded-3xl border border-white/10 bg-white/5 p-5">
                    <div class="flex items-center gap-3 text-sm font-semibold text-text">
                        <span class="grid h-10 w-10 place-items-center rounded-xl bg-sky-500/20 text-sky-200">
                            <i class="fab fa-twitter"></i>
                        </span>
                        Twitter / X
                    </div>
                    <p class="mt-4 text-sm text-muted">#TchadokRadio - Actuellement : "<?php echo htmlspecialchars($currentTrackTitle); ?>" de <?php echo htmlspecialchars($currentTrackArtist); ?>.</p>
                    <button class="mt-4 inline-flex items-center gap-2 rounded-full border border-sky-500/30 bg-sky-500/10 px-4 py-2 text-xs font-semibold text-sky-200" type="button">Suivre</button>
                </div>
                <div class="rounded-3xl border border-white/10 bg-white/5 p-5">
                    <div class="flex items-center gap-3 text-sm font-semibold text-text">
                        <span class="grid h-10 w-10 place-items-center rounded-xl bg-rose-500/20 text-rose-200">
                            <i class="fab fa-instagram"></i>
                        </span>
                        Instagram
                    </div>
                    <p class="mt-4 text-sm text-muted">Story du studio en direct ! Écoutez Tchadok Radio Live 24/7.</p>
                    <button class="mt-4 inline-flex items-center gap-2 rounded-full border border-rose-500/30 bg-rose-500/10 px-4 py-2 text-xs font-semibold text-rose-200" type="button">Suivre</button>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include 'includes/footer-tailwind.php'; ?>

