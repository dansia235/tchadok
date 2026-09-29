<?php
/**
 * Page des albums - Tchadok Platform
 * Migration Tailwind (progressive)
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'assets/images/placeholders.php';

$pageTitle = 'Albums';
$pageDescription = 'Decouvrez les albums de musique tchadienne sur Tchadok.';

$albumTypes = getAlbumTypes();
$genres = getGenres();
$platformStats = getPlatformStats();
$totalAlbums = countAlbums();
$albums = getAlbums(12);

$additionalCSS = [
    SITE_URL . '/assets/css/albums-tailwind.css'
];

$additionalJS = [
    SITE_URL . '/assets/js/albums.js'
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
                        <i class="fas fa-compact-disc text-accent"></i>
                        Albums
                    </div>
                    <h1 class="page-hero-title mt-5 text-4xl font-display font-bold leading-tight text-text sm:text-5xl">Discotheque tchadienne</h1>
                    <p class="page-hero-subtitle mt-4 text-base text-muted sm:text-lg">
                        Explorez les oeuvres completes des artistes qui faconnent la culture musicale du Tchad.
                    </p>

                    <div class="mt-6 flex flex-wrap gap-3 text-xs text-muted">
                        <span class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1">
                            <i class="fas fa-layer-group"></i> <?php echo (int) $totalAlbums; ?> albums
                        </span>
                        <span class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1">
                            <i class="fas fa-users"></i> <?php echo (int) ($platformStats['total_artists'] ?? 0); ?> artistes
                        </span>
                        <span class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1">
                            <i class="fas fa-music"></i> <?php echo (int) ($platformStats['total_tracks'] ?? 0); ?> titres
                        </span>
                    </div>
                </div>

                <div class="rounded-3xl border border-white/10 bg-surface/70 p-6 shadow-elev-2">
                    <p class="text-xs uppercase tracking-[0.2em] text-muted">Filtres rapides</p>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="text-xs text-muted">Genre</label>
                            <select class="mt-2 w-full rounded-full border border-white/10 bg-bg px-4 py-2 text-xs font-semibold text-text" data-filter-genre>
                                <option value="all">Tous</option>
                                <?php if (!empty($genres)): ?>
                                    <?php foreach ($genres as $genre): ?>
                                        <?php
                                        $genreName = $genre['name_french'] ?? $genre['name'] ?? '';
                                        $genreSlug = preg_replace('/[^a-z0-9]/', '', strtolower($genre['name'] ?? $genreName));
                                    ?>
                                        <option value="<?php echo htmlspecialchars($genreSlug); ?>"><?php echo htmlspecialchars($genreName); ?></option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="all" disabled>Aucun genre disponible</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs text-muted">Trier</label>
                            <select class="mt-2 w-full rounded-full border border-white/10 bg-bg px-4 py-2 text-xs font-semibold text-text" data-sort>
                                <option value="recent">Plus recents</option>
                                <option value="popular">Populaires</option>
                                <option value="alphabetical">Alphabetique</option>
                                <option value="tracks">Nombre de titres</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-4 rounded-2xl border border-white/10 bg-white/5 px-4 py-2">
                        <div class="flex items-center gap-3">
                            <i class="fas fa-search text-muted"></i>
                            <input type="text" class="w-full bg-transparent text-sm text-text placeholder:text-muted focus:outline-none" placeholder="Rechercher un album ou un artiste..." data-search>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-display font-bold text-text">Albums populaires</h2>
                    <p class="text-sm text-muted">Les sorties les plus ecoutees</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button class="filter-pill is-active rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-semibold text-text" data-type="all" type="button">
                        Tout
                    </button>
                    <?php if (!empty($albumTypes)): ?>
                        <?php foreach ($albumTypes as $type): ?>
                            <?php $label = ucwords(str_replace('_', ' ', $type)); ?>
                            <button class="filter-pill rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-semibold text-text" data-type="<?php echo htmlspecialchars($type); ?>" type="button">
                                <?php echo htmlspecialchars($label); ?>
                            </button>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="text-xs text-muted">Aucun type disponible</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4" id="albumsGrid">
                <?php if (empty($albums)): ?>
                    <div class="col-span-full rounded-3xl border border-white/10 bg-white/5 p-8 text-center text-muted">
                        <i class="fas fa-compact-disc text-2xl"></i>
                        <h4 class="mt-3 text-base font-semibold text-text">Aucun album disponible</h4>
                        <p class="mt-2 text-sm text-muted">Les albums apparaitront des qu'ils seront ajoutes.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($albums as $album): ?>
                        <?php
                            $albumTitle = $album['title'] ?? 'Album';
                            $albumArtist = $album['artist'] ?? 'Artiste';
                            $albumGenre = $album['genre'] ?? 'inconnu';
                            $genreSlug = strtolower(preg_replace('/[^a-z0-9]/', '', $albumGenre));
                            $albumType = $album['type'] ?? 'album';
                            $albumTypeLabel = ucwords(str_replace('_', ' ', $albumType));
                            $tracksCount = (int) ($album['total_tracks'] ?? 0);
                            $coverHtml = '';
                            if (!empty($album['cover_image'])) {
                                $coverPath = $album['cover_image'];
                                $coverUrl = (str_starts_with($coverPath, 'http://') || str_starts_with($coverPath, 'https://'))
                                    ? $coverPath
                                    : SITE_URL . '/' . ltrim($coverPath, '/');
                                $coverHtml = '<img src="' . htmlspecialchars($coverUrl) . '" alt="' . htmlspecialchars($albumTitle) . '" class="h-full w-full object-cover">';
                            } else {
                                $coverHtml = createAlbumCover($albumTitle, $albumArtist, $albumTypeLabel, '#' . ($album['color'] ?? '0066CC'), 300);
                                $coverHtml = str_replace('class="img-fluid"', 'class="h-full w-full object-cover"', $coverHtml);
                            }
                        ?>
                        <article class="album-card group rounded-3xl border border-white/10 bg-surface/60 p-4 transition hover:-translate-y-1 hover:shadow-elev-2"
                            data-type="<?php echo htmlspecialchars($albumType); ?>"
                            data-genre="<?php echo htmlspecialchars($genreSlug); ?>"
                            data-title="<?php echo htmlspecialchars(strtolower($albumTitle), ENT_QUOTES); ?>"
                            data-artist="<?php echo htmlspecialchars(strtolower($albumArtist), ENT_QUOTES); ?>"
                            data-tracks="<?php echo $tracksCount; ?>">
                            <div class="relative overflow-hidden rounded-2xl bg-white/5">
                                <div class="aspect-square">
                                    <?php echo $coverHtml; ?>
                                </div>
                                <div class="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 transition group-hover:opacity-100">
                                    <button class="grid h-12 w-12 place-items-center rounded-full bg-accent text-white shadow-elev-1" data-action="play" data-album="<?php echo (int) $album['id']; ?>" type="button" aria-label="Lecture">
                                        <i class="fas fa-play"></i>
                                    </button>
                                </div>
                                <div class="absolute left-3 top-3 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold text-text">
                                    <?php echo htmlspecialchars($albumTypeLabel); ?>
                                </div>
                                <div class="absolute right-3 top-3 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold text-text">
                                    <?php echo $tracksCount; ?> titres
                                </div>
                            </div>

                            <div class="mt-4">
                                <h3 class="text-base font-semibold text-text"><?php echo htmlspecialchars($albumTitle); ?></h3>
                                <p class="text-xs text-muted"><?php echo htmlspecialchars($albumArtist); ?> - <?php echo htmlspecialchars($albumGenre); ?></p>

                                <div class="mt-4 flex items-center gap-2">
                                    <button class="flex-1 rounded-full bg-accent px-4 py-2 text-xs font-semibold text-white shadow-elev-1" data-action="play" data-album="<?php echo (int) $album['id']; ?>" type="button">
                                        <i class="fas fa-play"></i> Ecouter
                                    </button>
                                    <?php echo Panier::bouton('release', (int) $album['id'], (float) ($album['price'] ?? 0), !empty($album['is_free'])); ?>
                                    <button class="grid h-9 w-9 place-items-center rounded-full border border-white/10 bg-white/5 text-text hover:bg-white/10" data-action="favorite" type="button" aria-label="Favori">
                                        <i class="far fa-heart"></i>
                                    </button>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="mt-10 flex flex-wrap items-center justify-center gap-2 text-xs font-semibold text-muted">
                <span>Affichage de <?php echo min(count($albums), 12); ?> sur <?php echo (int) $totalAlbums; ?> albums</span>
            </div>
        </div>
    </section>
</main>

<?php include 'includes/footer-tailwind.php'; ?>
