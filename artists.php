<?php
/**
 * Page des artistes - Tchadok Platform
 * Migration Tailwind (progressive)
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'assets/images/placeholders.php';

$pageTitle = 'Artistes';
$pageDescription = 'Découvrez les artistes tchadiens talentueux sur Tchadok.';

$featuredArtists = getFeaturedArtists(3);
$allArtists      = getAllArtists(12);
$genres          = getGenres();
$platformStats   = getPlatformStats();
$totalArtists    = (int) countArtists();

$additionalCSS = [
    SITE_URL . '/assets/css/artists-tailwind.css'
];

$additionalJS = [
    SITE_URL . '/assets/js/artists.js'
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
            <div class="grid items-center gap-10 lg:grid-cols-2">
                <div class="page-hero-content">
                    <div class="page-hero-pill inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold text-text">
                        <i class="fas fa-users text-accent"></i>
                        Artistes
                    </div>
                    <h1 class="page-hero-title mt-5 text-4xl font-display font-bold leading-tight text-text sm:text-5xl">Talents du Tchad</h1>
                    <p class="page-hero-subtitle mt-4 text-base text-muted sm:text-lg">
                        Découvrez les voix authentiques qui font vibrer notre scène musicale. Des légendes
                        sacrées aux étoiles montantes, chaque artiste raconte une histoire.
                    </p>

                    <div class="mt-6 rounded-2xl border border-white/10 bg-white/5 p-3">
                        <div class="flex items-center gap-3">
                            <span class="grid h-10 w-10 place-items-center rounded-xl bg-white/10 text-muted">
                                <i class="fas fa-search"></i>
                            </span>
                            <input type="text" class="w-full bg-transparent text-sm text-text placeholder:text-muted focus:outline-none" placeholder="Rechercher un artiste..." data-search-input>
                            <button class="grid h-10 w-10 place-items-center rounded-xl bg-accent text-white shadow-elev-1" type="button" data-action="voice-search" aria-label="Recherche vocale">
                                <i class="fas fa-microphone"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mt-6 flex flex-wrap gap-3 text-xs text-muted">
                        <span class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1">
                            <i class="fas fa-star"></i><?php echo (int) $totalArtists; ?> artistes
                        </span>
                        <span class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1">
                            <i class="fas fa-bolt"></i><?php echo (int) ($platformStats['total_tracks'] ?? 0); ?> titres
                        </span>
                        <span class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1">
                            <i class="fas fa-layer-group"></i><?php echo (int) ($platformStats['total_genres'] ?? 0); ?> genres
                        </span>
                    </div>
                </div>

                <div class="rounded-3xl border border-white/10 bg-surface/70 p-6 shadow-elev-2">
                    <p class="text-xs uppercase tracking-[0.2em] text-muted">Statistiques</p>
                    <div class="mt-5 grid gap-4 sm:grid-cols-3">
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4 text-center">
                            <p class="text-xs uppercase tracking-[0.2em] text-muted">Artistes</p>
                            <p class="mt-2 text-3xl font-semibold text-text" data-count="<?php echo (int) $totalArtists; ?>">0</p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4 text-center">
                            <p class="text-xs uppercase tracking-[0.2em] text-muted">Genres</p>
                            <p class="mt-2 text-3xl font-semibold text-text" data-count="<?php echo (int) ($platformStats['total_genres'] ?? 0); ?>">0</p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4 text-center">
                            <p class="text-xs uppercase tracking-[0.2em] text-muted">Titres</p>
                            <p class="mt-2 text-3xl font-semibold text-text" data-count="<?php echo (int) ($platformStats['total_tracks'] ?? 0); ?>">0</p>
                        </div>
                    </div>
                    <div class="mt-6 rounded-2xl border border-white/10 bg-white/5 p-4 text-sm text-muted">
                        Explorez les profils vérifiés, suivez vos artistes préférés et découvrez les sons du moment.
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-3xl border border-white/10 bg-surface/60 p-5">
                <div class="flex flex-wrap gap-3">
                    <button class="filter-pill is-active inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-semibold text-text" data-filter="all" type="button">
                        <i class="fas fa-globe"></i> Tous
                    </button>
                    <button class="filter-pill inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-semibold text-text" data-filter="verified" type="button">
                        <i class="fas fa-check-circle"></i> Vérifiés
                    </button>
                    <button class="filter-pill inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-semibold text-text" data-filter="trending" type="button">
                        <i class="fas fa-fire"></i> Tendances
                    </button>
                    <button class="filter-pill inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-semibold text-text" data-filter="new" type="button">
                        <i class="fas fa-star"></i> Nouveaux
                    </button>
                </div>

                <div class="mt-6 flex flex-wrap items-center justify-between gap-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs uppercase tracking-[0.2em] text-muted">Genres</span>
                        <div class="flex flex-wrap gap-2">
                            <button class="genre-pill is-active rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold text-text" data-genre="all" type="button">Tous</button>
                            <?php if (!empty($genres)): ?>
                                <?php foreach ($genres as $genre): ?>
                                    <?php
                                        $genreName = $genre['name_french'] ?? $genre['name'] ?? '';
                                        $genreSlug = preg_replace('/[^a-z0-9]/', '', strtolower($genre['name'] ?? $genreName));
                                    ?>
                                    <button class="genre-pill rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold text-text" data-genre="<?php echo htmlspecialchars($genreSlug); ?>" type="button">
                                        <?php echo htmlspecialchars($genreName); ?>
                                    </button>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="text-xs text-muted">Aucun genre disponible</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <div class="flex items-center gap-2 text-xs text-muted">
                            <i class="fas fa-layer-group"></i>
                            <span data-visible-count><?php echo min(12, $totalArtists); ?></span>/<span><?php echo (int) $totalArtists; ?></span>
                        </div>
                        <select class="rounded-full border border-white/10 bg-bg px-4 py-2 text-xs font-semibold text-text" data-sort-select>
                            <option value="popularity">Popularité</option>
                            <option value="alphabetical">Alphabétique</option>
                            <option value="newest">Plus récents</option>
                            <option value="plays">Plus écoutés</option>
                        </select>
                        <div class="flex items-center gap-2 rounded-full border border-white/10 bg-white/5 p-1">
                            <button class="view-btn is-active grid h-8 w-8 place-items-center rounded-full text-text" data-view="grid" type="button" aria-label="Vue grille">
                                <i class="fas fa-th"></i>
                            </button>
                            <button class="view-btn grid h-8 w-8 place-items-center rounded-full text-text" data-view="list" type="button" aria-label="Vue liste">
                                <i class="fas fa-list"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-12">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-display font-bold text-text">Artistes en vedette</h2>
                    <p class="text-sm text-muted">Les stars qui font vibrer le Tchad</p>
                </div>
                <button class="rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-semibold text-text" data-action="shuffle-featured" type="button">
                    <i class="fas fa-random"></i> Mélanger
                </button>
            </div>

            <?php if (empty($featuredArtists)): ?>
                <div class="rounded-3xl border border-white/10 bg-white/5 p-8 text-center text-muted">
                    <i class="fas fa-star text-2xl"></i>
                    <h4 class="mt-3 text-base font-semibold text-text">Aucun artiste en vedette</h4>
                    <p class="mt-2 text-sm text-muted">Les artistes vérifiés apparaîtront ici.</p>
                </div>
            <?php else: ?>
                <div class="grid gap-6 lg:grid-cols-3" data-featured-grid>
                    <?php foreach ($featuredArtists as $artist): ?>
                        <?php
                            $artistName = $artist['name'] ?? 'Artiste';
                            $artistGenre = $artist['genre'] ?? 'Artiste';
                            $artistPlays = $artist['plays'] ?? '0';
                            $artistFollowers = $artist['followers'] ?? '0';
                            $artistTracks = (int) ($artist['tracks_count'] ?? 0);
                            $avatar = createArtistAvatar($artistName, 300, '#' . ($artist['color'] ?: '0066CC'));
                            $avatar = str_replace('class="img-fluid rounded-circle"', 'class="h-full w-full object-cover"', $avatar);
                        ?>
                        <article class="group rounded-3xl border border-white/10 bg-surface/60 p-4 transition hover:-translate-y-1 hover:shadow-elev-2">
                            <div class="relative overflow-hidden rounded-2xl bg-white/5">
                                <div class="aspect-square">
                                    <?php echo $avatar; ?>
                                </div>
                                <div class="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 transition group-hover:opacity-100">
                                    <button class="grid h-12 w-12 place-items-center rounded-full bg-accent text-white shadow-elev-1" data-action="play" data-artist-id="<?php echo (int) $artist['id']; ?>" type="button" aria-label="Lecture artiste">
                                        <i class="fas fa-play"></i>
                                    </button>
                                </div>
                                <?php if (!empty($artist['verified'])): ?>
                                    <div class="absolute left-3 top-3 rounded-full border border-emerald-400/30 bg-emerald-400/10 px-3 py-1 text-xs font-semibold text-emerald-200">
                                        <i class="fas fa-check-circle"></i> Vérifié
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($artist['trending'])): ?>
                                    <div class="absolute right-3 top-3 rounded-full border border-amber-400/30 bg-amber-400/10 px-3 py-1 text-xs font-semibold text-amber-200">
                                        <i class="fas fa-fire"></i> Tendance
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="mt-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <h3 class="text-lg font-semibold text-text"><?php echo htmlspecialchars($artistName); ?></h3>
                                        <p class="text-sm text-muted"><?php echo htmlspecialchars($artistGenre); ?></p>
                                    </div>
                                    <button class="grid h-9 w-9 place-items-center rounded-full border border-white/10 bg-white/5 text-text hover:bg-white/10" data-action="follow" data-artist-id="<?php echo (int) $artist['id']; ?>" type="button" aria-label="Suivre">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>

                                <div class="mt-4 flex flex-wrap gap-3 text-xs text-muted">
                                    <span class="inline-flex items-center gap-1"><i class="fas fa-play"></i> <?php echo htmlspecialchars($artistPlays); ?></span>
                                    <span class="inline-flex items-center gap-1"><i class="fas fa-users"></i> <?php echo htmlspecialchars($artistFollowers); ?></span>
                                    <span class="inline-flex items-center gap-1"><i class="fas fa-music"></i> <?php echo $artistTracks; ?> titres</span>
                                </div>

                                <div class="mt-4 flex flex-wrap gap-2">
                                    <button class="rounded-full bg-accent px-4 py-2 text-xs font-semibold text-white shadow-elev-1" data-action="listen" data-artist-id="<?php echo (int) $artist['id']; ?>" type="button">Écouter</button>
                                    <button class="rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-semibold text-text" data-action="share" data-artist-id="<?php echo (int) $artist['id']; ?>" type="button">Partager</button>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="py-12">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-display font-bold text-text">Tous les artistes</h2>
                    <p class="text-sm text-muted">Explorez la scène musicale tchadienne</p>
                </div>
                <div class="flex items-center gap-2 text-xs text-muted">
                    <i class="fas fa-music"></i>
                    <span data-visible-count><?php echo min(12, $totalArtists); ?></span> artistes affichés
                </div>
            </div>

            <div class="artists-grid grid gap-6 sm:grid-cols-2 lg:grid-cols-3" id="artistsGrid" data-grid>
                <?php if (empty($allArtists)): ?>
                    <div class="col-span-full rounded-3xl border border-white/10 bg-white/5 p-8 text-center text-muted">
                        <i class="fas fa-users text-2xl"></i>
                        <h4 class="mt-3 text-base font-semibold text-text">Aucun artiste trouvé</h4>
                        <p class="mt-2 text-sm text-muted">Les artistes apparaîtront dès qu'ils seront ajoutés.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($allArtists as $artist): ?>
                        <?php
                            $artistName = $artist['name'] ?? 'Artiste';
                            $artistGenre = $artist['genre'] ?? 'Artiste';
                            $genreSlug = preg_replace('/[^a-z0-9]/', '', strtolower($artistGenre));
                            $playsRaw = $artist['plays'] ?? 0;
                            $followersRaw = $artist['followers'] ?? 0;
                            $playsNumber = is_numeric($playsRaw) ? (int) $playsRaw : (int) preg_replace('/\D/', '', (string) $playsRaw);
                            $followersNumber = is_numeric($followersRaw) ? (int) $followersRaw : (int) preg_replace('/\D/', '', (string) $followersRaw);
                            $trackCount = (int) ($artist['tracks_count'] ?? 0);
                            $avatar = createArtistAvatar($artistName, 220, '#' . ($artist['color'] ?: '0066CC'));
                            $avatar = str_replace('class="img-fluid rounded-circle"', 'class="h-full w-full object-cover"', $avatar);
                        ?>
                        <article class="artist-card group rounded-3xl border border-white/10 bg-surface/60 p-4 transition hover:-translate-y-1 hover:shadow-elev-2"
                            data-name="<?php echo htmlspecialchars($artistName, ENT_QUOTES); ?>"
                            data-verified="<?php echo !empty($artist['verified']) ? 'true' : 'false'; ?>"
                            data-new="<?php echo !empty($artist['new']) ? 'true' : 'false'; ?>"
                            data-trending="<?php echo !empty($artist['featured']) || !empty($artist['trending']) ? 'true' : 'false'; ?>"
                            data-genre="<?php echo htmlspecialchars($genreSlug); ?>"
                            data-genre-label="<?php echo htmlspecialchars(strtolower($artistGenre), ENT_QUOTES); ?>"
                            data-plays="<?php echo $playsNumber; ?>"
                            data-followers="<?php echo $followersNumber; ?>">
                            <div class="artist-media relative overflow-hidden rounded-2xl bg-white/5">
                                <div class="aspect-square">
                                    <?php echo $avatar; ?>
                                </div>
                                <div class="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 transition group-hover:opacity-100">
                                    <button class="grid h-11 w-11 place-items-center rounded-full bg-accent text-white shadow-elev-1" data-action="play" data-artist-id="<?php echo (int) $artist['id']; ?>" type="button" aria-label="Lecture rapide">
                                        <i class="fas fa-play"></i>
                                    </button>
                                </div>
                                <?php if (!empty($artist['verified'])): ?>
                                    <div class="absolute left-3 top-3 rounded-full border border-emerald-400/30 bg-emerald-400/10 px-3 py-1 text-xs font-semibold text-emerald-200">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($artist['new'])): ?>
                                    <div class="absolute right-3 top-3 rounded-full border border-amber-400/30 bg-amber-400/10 px-3 py-1 text-xs font-semibold text-amber-200">Nouveau</div>
                                <?php endif; ?>
                            </div>

                            <div class="artist-body mt-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <h4 class="text-base font-semibold text-text"><?php echo htmlspecialchars($artistName); ?></h4>
                                        <p class="text-xs text-muted"><?php echo htmlspecialchars($artistGenre); ?></p>
                                    </div>
                                    <button class="grid h-9 w-9 place-items-center rounded-full border border-white/10 bg-white/5 text-text hover:bg-white/10" data-action="follow" data-artist-id="<?php echo (int) $artist['id']; ?>" type="button" aria-label="Suivre">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>

                                <div class="mt-4 flex flex-wrap gap-3 text-xs text-muted">
                                    <span class="inline-flex items-center gap-1"><i class="fas fa-play"></i> <?php echo htmlspecialchars($playsRaw); ?></span>
                                    <span class="inline-flex items-center gap-1"><i class="fas fa-music"></i> <?php echo $trackCount; ?> titres</span>
                                </div>

                                <div class="mt-4 flex items-center gap-2">
                                    <button class="grid h-9 w-9 place-items-center rounded-full border border-white/10 bg-white/5 text-text hover:bg-white/10" data-action="play" data-artist-id="<?php echo (int) $artist['id']; ?>" type="button" aria-label="Lecture">
                                        <i class="fas fa-play"></i>
                                    </button>
                                    <button class="grid h-9 w-9 place-items-center rounded-full border border-white/10 bg-white/5 text-text hover:bg-white/10" data-action="add" data-artist-id="<?php echo (int) $artist['id']; ?>" type="button" aria-label="Ajouter">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                    <button class="grid h-9 w-9 place-items-center rounded-full border border-white/10 bg-white/5 text-text hover:bg-white/10" data-action="share" data-artist-id="<?php echo (int) $artist['id']; ?>" type="button" aria-label="Partager">
                                        <i class="fas fa-share-alt"></i>
                                    </button>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php if ($totalArtists > 12): ?>
        <section class="py-10">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 text-center">
                <button class="rounded-full border border-white/10 bg-white/5 px-6 py-3 text-sm font-semibold text-text shadow-elev-1" data-action="load-more" type="button">
                    <i class="fas fa-plus-circle"></i> Charger plus d'artistes
                </button>
                <p class="mt-3 text-xs text-muted">Affichage de <span data-visible-count><?php echo min(12, $totalArtists); ?></span> sur <?php echo (int) $totalArtists; ?> artistes</p>
            </div>
        </section>
    <?php endif; ?>

    <section class="py-14">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-3xl border border-white/10 bg-surface/60 p-8">
                <div class="grid gap-6 lg:grid-cols-2 lg:items-center">
                    <div>
                        <h3 class="text-2xl font-display font-bold text-text">Restez connecté avec vos artistes préférés</h3>
                        <p class="mt-3 text-sm text-muted">Recevez les dernières actualités, sorties et concerts de vos artistes tchadiens favoris.</p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <input type="email" class="w-full flex-1 rounded-full border border-white/10 bg-bg px-4 py-3 text-sm text-text placeholder:text-muted focus:outline-none" placeholder="Votre adresse email">
                        <button class="rounded-full bg-accent px-6 py-3 text-sm font-semibold text-white shadow-elev-1" data-action="subscribe" type="button">
                            <i class="fas fa-paper-plane"></i> S'abonner
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include 'includes/footer-tailwind.php'; ?>
