<?php
/**
 * Page des genres musicaux - Tchadok Platform
 * Migration Tailwind (progressive)
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'assets/images/placeholders.php';

$pageTitle = 'Genres Musicaux';
$pageDescription = 'Explorez la diversite musicale du Tchad a travers ses differents genres.';

$genres = getGenresWithStats();
$platformStats = getPlatformStats();
$monthlyStreams = getMonthlyStreamsCount();
$totalTracks = 0;
foreach ($genres as $genreItem) {
    $totalTracks += (int) ($genreItem['track_count'] ?? 0);
}

$additionalCSS = [
    SITE_URL . '/assets/css/genres-tailwind.css'
];

$additionalJS = [
    SITE_URL . '/assets/js/genres.js'
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
                        <i class="fas fa-music text-accent"></i>
                        Genres musicaux
                    </div>
                    <h1 class="page-hero-title mt-5 text-4xl font-display font-bold leading-tight text-text sm:text-5xl">Genres musicaux tchadiens</h1>
                    <p class="page-hero-subtitle mt-4 text-base text-muted sm:text-lg">
                        Decouvrez la richesse et la diversite de la musique tchadienne. Chaque genre raconte
                        une partie de notre histoire et de notre culture.
                    </p>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <a class="rounded-full bg-accent px-6 py-3 text-sm font-semibold text-white shadow-elev-1" href="#genres-grid">
                            <i class="fas fa-compass"></i> Explorer
                        </a>
                        <a class="rounded-full border border-white/10 bg-white/5 px-6 py-3 text-sm font-semibold text-text" href="<?php echo SITE_URL; ?>/search.php">
                            <i class="fas fa-search"></i> Rechercher
                        </a>
                    </div>
                </div>

                <div class="flex items-center justify-center">
                    <div class="genres-visual grid place-items-center rounded-3xl border border-white/10 bg-surface/70 p-8 shadow-elev-2">
                        <div class="grid h-20 w-20 place-items-center rounded-full bg-accent/20 text-accent">
                            <i class="fas fa-compact-disc fa-spin text-2xl"></i>
                        </div>
                        <p class="mt-4 text-xs text-muted">Explorez les couleurs du son tchadien</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-3xl border border-white/10 bg-white/5 p-5 text-center">
                    <p class="text-xs uppercase tracking-[0.2em] text-muted">Genres</p>
                    <p class="mt-2 text-3xl font-semibold text-text"><?php echo count($genres); ?></p>
                </div>
                <div class="rounded-3xl border border-white/10 bg-white/5 p-5 text-center">
                    <p class="text-xs uppercase tracking-[0.2em] text-muted">Titres</p>
                    <p class="mt-2 text-3xl font-semibold text-text"><?php echo (int) $totalTracks; ?></p>
                </div>
                <div class="rounded-3xl border border-white/10 bg-white/5 p-5 text-center">
                    <p class="text-xs uppercase tracking-[0.2em] text-muted">Artistes actifs</p>
                    <p class="mt-2 text-3xl font-semibold text-text"><?php echo (int) ($platformStats['total_artists'] ?? 0); ?></p>
                </div>
                <div class="rounded-3xl border border-white/10 bg-white/5 p-5 text-center">
                    <p class="text-xs uppercase tracking-[0.2em] text-muted">Ecoutes mensuelles</p>
                    <p class="mt-2 text-3xl font-semibold text-text"><?php echo formatNumber($monthlyStreams); ?></p>
                </div>
            </div>
        </div>
    </section>

    <section class="py-12" id="genres-grid">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-6 text-center">
                <h2 class="text-2xl font-display font-bold text-text">Explorez par genre</h2>
                <p class="text-sm text-muted">Chaque genre a sa propre identite et ses artistes emblematiques</p>
            </div>

            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <?php if (empty($genres)): ?>
                    <div class="col-span-full rounded-3xl border border-white/10 bg-white/5 p-8 text-center text-muted">
                        <i class="fas fa-music text-2xl"></i>
                        <h4 class="mt-3 text-base font-semibold text-text">Aucun genre disponible</h4>
                        <p class="mt-2 text-sm text-muted">Les genres apparaitront des qu'ils seront ajoutes.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($genres as $genre): ?>
                        <?php
                            $genreName = $genre['name_french'] ?? $genre['name'] ?? 'Genre';
                            $genreColor = ltrim((string) ($genre['color'] ?? ''), '#');
                            if ($genreColor === '') {
                                $genreColor = getColorForGenre($genreName);
                            }
                            $genreColor = '#' . $genreColor;
                            $genreIcon = $genre['icon'] ?: 'fas fa-music';
                        ?>
                        <article class="genre-card rounded-3xl border border-white/10 bg-surface/60 p-5" style="--genre-color: <?php echo htmlspecialchars($genreColor); ?>">
                            <div class="flex items-center gap-3 text-sm font-semibold text-text">
                                <span class="grid h-10 w-10 place-items-center rounded-xl" style="background: color-mix(in srgb, <?php echo $genreColor; ?> 35%, transparent); color: <?php echo $genreColor; ?>;">
                                    <i class="<?php echo htmlspecialchars($genreIcon); ?>"></i>
                                </span>
                                <?php echo htmlspecialchars($genreName); ?>
                            </div>
                            <p class="mt-3 text-sm text-muted"><?php echo htmlspecialchars($genre['description'] ?? ''); ?></p>
                            <div class="mt-4 flex flex-wrap gap-3 text-xs text-muted">
                                <span><i class="fas fa-music"></i> <?php echo (int) ($genre['track_count'] ?? 0); ?> titres</span>
                                <span><i class="fas fa-fire"></i> <?php echo (int) ($genre['popularity'] ?? 0); ?>% populaire</span>
                            </div>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <button class="rounded-full bg-accent px-4 py-2 text-xs font-semibold text-white shadow-elev-1" data-action="explore" data-genre-id="<?php echo (int) $genre['id']; ?>" data-genre-name="<?php echo htmlspecialchars($genreName, ENT_QUOTES); ?>" type="button">
                                    <i class="fas fa-play"></i> Ecouter
                                </button>
                                <button class="rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-semibold text-text" data-action="search" data-genre-name="<?php echo htmlspecialchars($genreName, ENT_QUOTES); ?>" type="button">
                                    <i class="fas fa-search"></i> Explorer
                                </button>
                            </div>
                            <div class="mt-4 h-2 w-full overflow-hidden rounded-full bg-white/5">
                                <div class="h-full rounded-full" style="width: <?php echo (int) ($genre['popularity'] ?? 0); ?>%; background: <?php echo htmlspecialchars($genreColor); ?>;"></div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="py-12">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-6 text-center">
                <h2 class="text-2xl font-display font-bold text-text">Artistes par genre</h2>
                <p class="text-sm text-muted">Decouvrez les talents de chaque style musical</p>
            </div>

            <?php if (empty($genres)): ?>
                <div class="rounded-3xl border border-white/10 bg-white/5 p-8 text-center text-muted">
                    <i class="fas fa-users text-2xl"></i>
                    <h4 class="mt-3 text-base font-semibold text-text">Aucun artiste disponible</h4>
                    <p class="mt-2 text-sm text-muted">Les artistes apparaitront des qu'ils seront ajoutes.</p>
                </div>
            <?php else: ?>
                <?php $tabGenres = array_slice($genres, 0, 4); ?>
                <?php $firstTab = $tabGenres[0]['id']; ?>
                <div class="flex flex-wrap justify-center gap-2">
                    <?php foreach ($tabGenres as $genre): ?>
                        <?php $genreLabel = $genre['name_french'] ?? $genre['name'] ?? 'Genre'; ?>
                        <button class="tab-button rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-semibold text-text <?php echo $genre['id'] === $firstTab ? 'is-active' : ''; ?>" data-tab-target="genre-<?php echo (int) $genre['id']; ?>" type="button">
                            <i class="<?php echo htmlspecialchars($genre['icon'] ?: 'fas fa-music'); ?>"></i>
                            <?php echo htmlspecialchars($genreLabel); ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <div class="mt-8 space-y-6">
                    <?php foreach ($tabGenres as $genre): ?>
                        <?php
                            $genreLabel = $genre['name_french'] ?? $genre['name'] ?? 'Genre';
                            $genreArtists = getTopArtistsByGenre($genre['id'], 4);
                        ?>
                        <div class="<?php echo $genre['id'] === $firstTab ? '' : 'hidden'; ?>" data-tab-panel="genre-<?php echo (int) $genre['id']; ?>">
                            <?php if (empty($genreArtists)): ?>
                                <div class="rounded-3xl border border-white/10 bg-white/5 p-6 text-center text-muted">
                                    <i class="fas fa-user-music text-2xl"></i>
                                    <p class="mt-2 text-sm text-muted">Aucun artiste pour ce genre pour le moment.</p>
                                </div>
                            <?php else: ?>
                                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                    <?php foreach ($genreArtists as $artist): ?>
                                        <?php
                                            $artistName = $artist['name'] ?? 'Artiste';
                                            $avatar = createArtistAvatar($artistName, 120, '#' . getColorForGenre($genreLabel));
                                            $avatar = str_replace('class="img-fluid rounded-circle"', 'class="h-full w-full object-cover"', $avatar);
                                        ?>
                                        <article class="rounded-3xl border border-white/10 bg-surface/60 p-4 text-center">
                                            <div class="relative mx-auto h-20 w-20 overflow-hidden rounded-full border border-white/10 bg-white/5">
                                                <?php echo $avatar; ?>
                                                <button class="absolute inset-0 flex items-center justify-center bg-black/40 text-white opacity-0 transition hover:opacity-100" data-action="play-artist" type="button" aria-label="Lecture">
                                                    <i class="fas fa-play"></i>
                                                </button>
                                            </div>
                                            <h6 class="mt-3 text-sm font-semibold text-text"><?php echo htmlspecialchars($artistName); ?></h6>
                                            <p class="text-xs text-muted"><?php echo htmlspecialchars($genreLabel); ?></p>
                                            <div class="mt-2 text-xs text-muted">
                                                <i class="fas fa-play"></i> <?php echo formatNumber($artist['total_streams'] ?? 0); ?>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="py-12">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <div class="cta-box rounded-3xl border border-white/10 bg-gradient-to-br from-accent/30 via-surface/80 to-accent-2/20 p-8 text-center">
                <h2 class="text-2xl font-display font-bold text-text">Vous etes artiste ?</h2>
                <p class="mt-3 text-sm text-muted">
                    Partagez votre talent avec le monde entier. Rejoignez la communaute Tchadok et faites decouvrir votre genre musical.
                </p>
                <div class="mt-6 flex flex-wrap justify-center gap-3">
                    <a href="<?php echo SITE_URL; ?>/artist-signup.php" class="rounded-full bg-accent px-6 py-3 text-sm font-semibold text-white shadow-elev-1">
                        <i class="fas fa-microphone"></i> Devenir artiste
                    </a>
                    <a href="<?php echo SITE_URL; ?>/upload.php" class="rounded-full border border-white/10 bg-white/5 px-6 py-3 text-sm font-semibold text-text">
                        <i class="fas fa-upload"></i> Uploader
                    </a>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include 'includes/footer-tailwind.php'; ?>
