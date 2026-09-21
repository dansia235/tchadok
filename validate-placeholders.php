<?php
/**
 * Script de validation des fonctions placeholder
 * Verifie que toutes les fonctions existent et fonctionnent correctement
 * Migration Tailwind (progressive)
 */

require_once 'assets/images/placeholders.php';

$pageTitle = 'Validation des placeholders';
$pageDescription = 'Verification des fonctions de generation de placeholders';

$isCli = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg');

$dynamicTests = [
    'createAlbumCover' => ['Renaissance', 'Mounira Mitchala', 'Album', '#0066CC', 200],
    'createArtistAvatar' => ['Clement Masdongar', 150, '#FFD700'],
    'createTrackCover' => ['Dounya', 'Mounira', '4:15', '#667eea', 180],
    'createBlogThumbnail' => ['Festival Tchadien', 'Evenement', '#4facfe', 300, 150],
    'createUserAvatar' => ['Admin', 80],
    'createAvatarPlaceholder' => ['DJ Moussa', '#228B22', 60],
    'createPodcastCover' => ['Reveil Musical', 'Episode 1', '#CC3333', 160],
    'createMusicNoteIcon' => ['#FFD700', 40]
];

$defaultFunctions = [
    'getDefaultUserAvatar',
    'getDefaultArtistAvatar',
    'getDefaultAlbumCover',
    'getDefaultTrackCover',
    'getDefaultPlaylistCover',
    'getDefaultEventCover',
    'getDefaultGenreCover',
    'getDefaultRadioCover',
    'getDefaultBanner',
    'getDefaultCategoryCover'
];

$helperTypes = ['user', 'artist', 'album', 'track', 'playlist', 'event', 'genre', 'radio', 'banner', 'category'];

$dynamicResults = [];
$defaultResults = [];
$helperResults = [];

function runPlaceholderTest($label, $callable, $params = []) {
    if (!function_exists($callable)) {
        return ['label' => $label, 'status' => 'missing', 'message' => 'Fonction non definie'];
    }

    try {
        call_user_func_array($callable, $params);
        return ['label' => $label, 'status' => 'ok', 'message' => 'OK'];
    } catch (Throwable $e) {
        return ['label' => $label, 'status' => 'error', 'message' => $e->getMessage()];
    }
}

foreach ($dynamicTests as $function => $params) {
    $dynamicResults[] = runPlaceholderTest($function, $function, $params);
}

foreach ($defaultFunctions as $function) {
    $defaultResults[] = runPlaceholderTest($function, $function, [100]);
}

foreach ($helperTypes as $type) {
    try {
        getPlaceholder($type, 100, 100);
        $helperResults[] = ['label' => "getPlaceholder('{$type}')", 'status' => 'ok', 'message' => 'OK'];
    } catch (Throwable $e) {
        $helperResults[] = ['label' => "getPlaceholder('{$type}')", 'status' => 'error', 'message' => $e->getMessage()];
    }
}

$summary = [
    'dynamic' => count($dynamicResults),
    'default' => count($defaultResults),
    'helper' => count($helperResults)
];

if ($isCli) {
    echo "=== VALIDATION DES PLACEHOLDERS TCHADOK ===\n\n";
    echo "TEST DES FONCTIONS DYNAMIQUES:\n";
    foreach ($dynamicResults as $result) {
        echo ($result['status'] === 'ok' ? '[OK] ' : '[ERR] ') . $result['label'] . ' - ' . $result['message'] . "\n";
    }

    echo "\nTEST DES FONCTIONS PAR DEFAUT:\n";
    foreach ($defaultResults as $result) {
        echo ($result['status'] === 'ok' ? '[OK] ' : '[ERR] ') . $result['label'] . ' - ' . $result['message'] . "\n";
    }

    echo "\nTEST DE LA FONCTION HELPER:\n";
    foreach ($helperResults as $result) {
        echo ($result['status'] === 'ok' ? '[OK] ' : '[ERR] ') . $result['label'] . ' - ' . $result['message'] . "\n";
    }

    echo "\nRESUME:\n";
    echo "- Fonctions dynamiques: {$summary['dynamic']} testees\n";
    echo "- Fonctions par defaut: {$summary['default']} testees\n";
    echo "- Types de placeholders: {$summary['helper']} testes\n";
    echo "\nVALIDATION TERMINEE\n";
    exit();
}

include 'includes/header-tailwind.php';
?>

<main class="pt-24 pb-16">
    <section class="bg-bg">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-3xl border border-white/10 bg-gradient-to-br from-slate-900/80 via-slate-800/40 to-accent/10 p-6 shadow-elev-2 sm:p-8">
                <div class="flex flex-wrap items-center gap-4">
                    <span class="grid h-12 w-12 place-items-center rounded-2xl bg-accent/20 text-accent">
                        <i class="fas fa-vial text-xl"></i>
                    </span>
                    <div>
                        <p class="text-xs uppercase tracking-[0.28em] text-muted">Validation</p>
                        <h1 class="mt-2 text-3xl font-display font-bold text-text">Placeholders Tchadok</h1>
                        <p class="mt-2 text-sm text-muted">Controle des fonctions de generation des visuels par defaut.</p>
                    </div>
                </div>
            </div>

            <div class="mt-8 grid gap-4 md:grid-cols-3">
                <div class="rounded-3xl border border-white/10 bg-surface/60 p-5 shadow-elev-1">
                    <p class="text-xs uppercase tracking-[0.2em] text-muted">Fonctions dynamiques</p>
                    <p class="mt-3 text-2xl font-semibold text-text"><?php echo $summary['dynamic']; ?></p>
                    <p class="mt-1 text-xs text-muted">Tests executes</p>
                </div>
                <div class="rounded-3xl border border-white/10 bg-surface/60 p-5 shadow-elev-1">
                    <p class="text-xs uppercase tracking-[0.2em] text-muted">Fonctions par defaut</p>
                    <p class="mt-3 text-2xl font-semibold text-text"><?php echo $summary['default']; ?></p>
                    <p class="mt-1 text-xs text-muted">Tests executes</p>
                </div>
                <div class="rounded-3xl border border-white/10 bg-surface/60 p-5 shadow-elev-1">
                    <p class="text-xs uppercase tracking-[0.2em] text-muted">Helper getPlaceholder</p>
                    <p class="mt-3 text-2xl font-semibold text-text"><?php echo $summary['helper']; ?></p>
                    <p class="mt-1 text-xs text-muted">Types verifies</p>
                </div>
            </div>

            <div class="mt-10 grid gap-6 lg:grid-cols-2">
                <div class="rounded-3xl border border-white/10 bg-surface/60 p-6 shadow-elev-2">
                    <h2 class="text-base font-semibold text-text">Fonctions dynamiques</h2>
                    <p class="mt-2 text-xs text-muted">Creation de visuels sur mesure.</p>
                    <div class="mt-5 space-y-3 text-sm">
                        <?php foreach ($dynamicResults as $result): ?>
                            <?php
                            $statusTone = $result['status'] === 'ok' ? 'text-emerald-200 bg-emerald-500/10 border-emerald-400/30' :
                                ($result['status'] === 'missing' ? 'text-rose-200 bg-rose-500/10 border-rose-400/30' : 'text-amber-200 bg-amber-500/10 border-amber-400/30');
                            ?>
                            <div class="flex items-center justify-between rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                                <div>
                                    <p class="text-sm font-semibold text-text"><?php echo $result['label']; ?></p>
                                    <p class="text-xs text-muted"><?php echo htmlspecialchars($result['message']); ?></p>
                                </div>
                                <span class="rounded-full border px-3 py-1 text-xs font-semibold <?php echo $statusTone; ?>">
                                    <?php echo $result['status'] === 'ok' ? 'OK' : strtoupper($result['status']); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="rounded-3xl border border-white/10 bg-surface/60 p-6 shadow-elev-2">
                        <h2 class="text-base font-semibold text-text">Fonctions par defaut</h2>
                        <p class="mt-2 text-xs text-muted">Fallbacks systeme.</p>
                        <div class="mt-5 space-y-3 text-sm">
                            <?php foreach ($defaultResults as $result): ?>
                                <?php
                                $statusTone = $result['status'] === 'ok' ? 'text-emerald-200 bg-emerald-500/10 border-emerald-400/30' :
                                    ($result['status'] === 'missing' ? 'text-rose-200 bg-rose-500/10 border-rose-400/30' : 'text-amber-200 bg-amber-500/10 border-amber-400/30');
                                ?>
                                <div class="flex items-center justify-between rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                                    <div>
                                        <p class="text-sm font-semibold text-text"><?php echo $result['label']; ?></p>
                                        <p class="text-xs text-muted"><?php echo htmlspecialchars($result['message']); ?></p>
                                    </div>
                                    <span class="rounded-full border px-3 py-1 text-xs font-semibold <?php echo $statusTone; ?>">
                                        <?php echo $result['status'] === 'ok' ? 'OK' : strtoupper($result['status']); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-white/10 bg-surface/60 p-6 shadow-elev-2">
                        <h2 class="text-base font-semibold text-text">Helper getPlaceholder</h2>
                        <p class="mt-2 text-xs text-muted">Validation par type.</p>
                        <div class="mt-5 space-y-3 text-sm">
                            <?php foreach ($helperResults as $result): ?>
                                <?php
                                $statusTone = $result['status'] === 'ok' ? 'text-emerald-200 bg-emerald-500/10 border-emerald-400/30' : 'text-rose-200 bg-rose-500/10 border-rose-400/30';
                                ?>
                                <div class="flex items-center justify-between rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                                    <div>
                                        <p class="text-sm font-semibold text-text"><?php echo $result['label']; ?></p>
                                        <p class="text-xs text-muted"><?php echo htmlspecialchars($result['message']); ?></p>
                                    </div>
                                    <span class="rounded-full border px-3 py-1 text-xs font-semibold <?php echo $statusTone; ?>">
                                        <?php echo $result['status'] === 'ok' ? 'OK' : 'ERROR'; ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-10 rounded-3xl border border-white/10 bg-surface/60 p-6 shadow-elev-2">
                <h2 class="text-base font-semibold text-text">Apercu visuel</h2>
                <p class="mt-2 text-xs text-muted">Exemples de rendus generes.</p>
                <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4 text-center">
                        <h3 class="text-sm font-semibold text-text">Album</h3>
                        <div class="mt-3 flex justify-center">
                            <?php echo createAlbumCover('Test Album', 'Test Artist', 'Album', '#0066CC', 150); ?>
                        </div>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4 text-center">
                        <h3 class="text-sm font-semibold text-text">Artiste</h3>
                        <div class="mt-3 flex justify-center">
                            <?php echo createArtistAvatar('Test Artist', 150, '#FFD700'); ?>
                        </div>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4 text-center">
                        <h3 class="text-sm font-semibold text-text">Track</h3>
                        <div class="mt-3 flex justify-center">
                            <?php echo createTrackCover('Test Track', 'Artist', '', '#CC3333', 150); ?>
                        </div>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4 text-center">
                        <h3 class="text-sm font-semibold text-text">Avatar</h3>
                        <div class="mt-3 flex justify-center">
                            <?php echo createAvatarPlaceholder('User', '#228B22', 80); ?>
                        </div>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4 text-center">
                        <h3 class="text-sm font-semibold text-text">Podcast</h3>
                        <div class="mt-3 flex justify-center">
                            <?php echo createPodcastCover('Test Podcast', 'Ep 1', '#667eea', 150); ?>
                        </div>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4 text-center">
                        <h3 class="text-sm font-semibold text-text">Blog</h3>
                        <div class="mt-3 flex justify-center">
                            <?php echo createBlogThumbnail('Test Blog', 'News', '#4facfe', 200, 100); ?>
                        </div>
                    </div>
                </div>
                <div class="mt-6 rounded-2xl border border-emerald-400/40 bg-emerald-500/10 p-4 text-sm text-emerald-200">
                    Validation terminee. Tous les placeholders sont operationnels si les statuts sont OK.
                </div>
            </div>
        </div>
    </section>
</main>

<?php include 'includes/footer-tailwind.php'; ?>
