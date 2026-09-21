<?php
/**
 * Page de recherche - Tchadok Platform
 * Migration Tailwind (progressive)
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'assets/images/placeholders.php';

$query = $_GET['q'] ?? '';
$filter = $_GET['filter'] ?? 'all'; // all, tracks, artists, albums

$pageTitle = !empty($query) ? 'Resultats pour "' . htmlspecialchars($query) . '"' : 'Recherche';
$pageDescription = 'Recherchez vos artistes, titres et albums preferes sur Tchadok.';

$searchResults = [
    'tracks' => [],
    'artists' => [],
    'albums' => []
];

if (!empty($query)) {
    $searchResults = searchContent($query, 20);
}

$totalResults = count($searchResults['tracks']) + count($searchResults['artists']) + count($searchResults['albums']);

include 'includes/header-tailwind.php';
?>

<main class="pt-20 pb-16">
    <section class="relative overflow-hidden bg-bg py-14">
        <div class="absolute inset-0 bg-gradient-to-br from-accent/25 via-bg to-amber-400/10"></div>
        <div class="absolute -left-24 top-10 h-64 w-64 rounded-full bg-accent/20 blur-3xl"></div>
        <div class="absolute right-0 bottom-0 h-72 w-72 rounded-full bg-amber-400/15 blur-3xl"></div>

        <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-8 lg:grid-cols-2 lg:items-center">
                <div>
                    <div class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold text-text">
                        <i class="fas fa-search text-accent"></i>
                        Recherche
                    </div>
                    <?php if (!empty($query)): ?>
                        <h1 class="mt-5 text-3xl font-display font-bold text-text sm:text-4xl">Resultats pour "<?php echo htmlspecialchars($query); ?>"</h1>
                        <p class="mt-3 text-sm text-muted"><?php echo $totalResults; ?> resultat<?php echo $totalResults > 1 ? 's' : ''; ?> trouves.</p>
                    <?php else: ?>
                        <h1 class="mt-5 text-3xl font-display font-bold text-text sm:text-4xl">Recherche musicale</h1>
                        <p class="mt-3 text-sm text-muted">Explorez les rythmes et les talents du Tchad.</p>
                    <?php endif; ?>
                </div>
                <div class="rounded-3xl border border-white/10 bg-surface/70 p-4 shadow-elev-2">
                    <form method="GET" class="flex items-center gap-3">
                        <div class="flex flex-1 items-center gap-3 rounded-2xl border border-white/10 bg-bg px-4 py-3">
                            <i class="fas fa-search text-muted"></i>
                            <input type="text" name="q" class="w-full bg-transparent text-sm text-text placeholder:text-muted focus:outline-none"
                                   placeholder="Titres, artistes, albums..."
                                   value="<?php echo htmlspecialchars($query); ?>"
                                   autocomplete="off">
                        </div>
                        <button type="submit" class="rounded-2xl bg-accent px-5 py-3 text-sm font-semibold text-white shadow-elev-1 hover:shadow-elev-2">
                            Rechercher
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <section class="py-10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <?php if (!empty($query)): ?>
                <div class="flex flex-wrap gap-3">
                    <?php
                        $filters = [
                            'all' => 'Tout',
                            'tracks' => 'Titres',
                            'artists' => 'Artistes',
                            'albums' => 'Albums'
                        ];
                    ?>
                    <?php foreach ($filters as $key => $label): ?>
                        <?php
                            $count = $key === 'all' ? $totalResults : count($searchResults[$key]);
                            $active = $filter === $key;
                        ?>
                        <a href="?q=<?php echo urlencode($query); ?>&filter=<?php echo $key; ?>"
                           class="rounded-full border border-white/10 px-4 py-2 text-xs font-semibold <?php echo $active ? 'bg-accent text-white' : 'bg-white/5 text-text hover:bg-white/10'; ?>">
                            <?php echo $label; ?> (<?php echo $count; ?>)
                        </a>
                    <?php endforeach; ?>
                </div>

                <?php if ($totalResults > 0): ?>
                    <?php if (($filter === 'all' || $filter === 'tracks') && !empty($searchResults['tracks'])): ?>
                        <section class="mt-10">
                            <div class="flex items-center justify-between">
                                <h2 class="text-lg font-semibold text-text">Titres</h2>
                                <button class="text-xs font-semibold text-accent">Tout voir</button>
                            </div>
                            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                                <?php foreach ($searchResults['tracks'] as $track): ?>
                                    <?php
                                        $trackTitle = $track['title'] ?? 'Titre';
                                        $trackArtist = $track['artist'] ?? 'Artiste';
                                        $coverHtml = '';
                                        if (!empty($track['album_cover'])) {
                                            $coverPath = $track['album_cover'];
                                            $coverUrl = (str_starts_with($coverPath, 'http://') || str_starts_with($coverPath, 'https://'))
                                                ? $coverPath
                                                : SITE_URL . '/' . ltrim($coverPath, '/');
                                            $coverHtml = '<img src="' . htmlspecialchars($coverUrl) . '" alt="' . htmlspecialchars($trackTitle) . '" class="h-full w-full object-cover">';
                                        } else {
                                            $coverHtml = createTrackCover($trackTitle, $trackArtist, '#2F6DE0', '', 120);
                                            $coverHtml = str_replace('class="img-fluid"', 'class="h-full w-full object-cover"', $coverHtml);
                                        }
                                    ?>
                                    <div class="group flex items-center gap-4 rounded-3xl border border-white/10 bg-surface/60 p-4 shadow-elev-1">
                                        <div class="relative h-14 w-14 overflow-hidden rounded-2xl">
                                            <?php echo $coverHtml; ?>
                                            <button class="absolute inset-0 flex items-center justify-center bg-black/50 text-white opacity-0 transition group-hover:opacity-100" type="button">
                                                <i class="fas fa-play"></i>
                                            </button>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <h3 class="truncate text-sm font-semibold text-text"><?php echo htmlspecialchars($trackTitle); ?></h3>
                                            <p class="truncate text-xs text-muted"><?php echo htmlspecialchars($trackArtist); ?></p>
                                        </div>
                                        <div class="hidden text-xs text-muted sm:block">
                                            <?php echo formatDuration($track['duration'] ?? 0); ?>
                                        </div>
                                        <div class="flex items-center gap-2 text-muted">
                                            <button class="grid h-8 w-8 place-items-center rounded-full border border-white/10 bg-white/5 hover:bg-white/10" type="button">
                                                <i class="far fa-heart"></i>
                                            </button>
                                            <button class="grid h-8 w-8 place-items-center rounded-full border border-white/10 bg-white/5 hover:bg-white/10" type="button">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endif; ?>

                    <?php if (($filter === 'all' || $filter === 'artists') && !empty($searchResults['artists'])): ?>
                        <section class="mt-12">
                            <h2 class="text-lg font-semibold text-text">Artistes</h2>
                            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                <?php foreach ($searchResults['artists'] as $artist): ?>
                                    <?php
                                        $artistName = $artist['name'] ?? 'Artiste';
                                        $artistAvatar = '';
                                        if (!empty($artist['profile_image'])) {
                                            $avatarPath = $artist['profile_image'];
                                            $avatarUrl = (str_starts_with($avatarPath, 'http://') || str_starts_with($avatarPath, 'https://'))
                                                ? $avatarPath
                                                : SITE_URL . '/' . ltrim($avatarPath, '/');
                                            $artistAvatar = '<img src="' . htmlspecialchars($avatarUrl) . '" alt="' . htmlspecialchars($artistName) . '" class="h-20 w-20 rounded-full object-cover">';
                                        } else {
                                            $artistAvatar = createArtistAvatar($artistName, 160, '#2F6DE0');
                                            $artistAvatar = str_replace('class=\"img-fluid rounded-circle\"', 'class=\"h-20 w-20 rounded-full object-cover\"', $artistAvatar);
                                        }
                                    ?>
                                    <div class="rounded-3xl border border-white/10 bg-surface/60 p-4 text-center shadow-elev-1">
                                        <div class="relative mx-auto h-20 w-20">
                                            <?php echo $artistAvatar; ?>
                                            <?php if (!empty($artist['verified'])): ?>
                                                <span class="absolute -bottom-1 -right-1 grid h-6 w-6 place-items-center rounded-full bg-accent text-white">
                                                    <i class="fas fa-check text-xs"></i>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <h3 class="mt-4 text-sm font-semibold text-text"><?php echo htmlspecialchars($artistName); ?></h3>
                                        <p class="text-xs text-muted">Artiste</p>
                                        <button class="mt-3 rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-semibold text-text hover:bg-white/10" type="button">
                                            Voir profil
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endif; ?>

                    <?php if (($filter === 'all' || $filter === 'albums') && !empty($searchResults['albums'])): ?>
                        <section class="mt-12">
                            <h2 class="text-lg font-semibold text-text">Albums</h2>
                            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                <?php foreach ($searchResults['albums'] as $album): ?>
                                    <?php
                                        $albumTitle = $album['title'] ?? 'Album';
                                        $albumArtist = $album['artist'] ?? 'Artiste';
                                        $albumCover = '';
                                        if (!empty($album['cover_image'])) {
                                            $coverPath = $album['cover_image'];
                                            $coverUrl = (str_starts_with($coverPath, 'http://') || str_starts_with($coverPath, 'https://'))
                                                ? $coverPath
                                                : SITE_URL . '/' . ltrim($coverPath, '/');
                                            $albumCover = '<img src="' . htmlspecialchars($coverUrl) . '" alt="' . htmlspecialchars($albumTitle) . '" class="h-44 w-full object-cover transition group-hover:scale-105">';
                                        } else {
                                            $albumCover = createAlbumCover($albumTitle, $albumArtist, 'Album', '#2F6DE0', 300);
                                            $albumCover = str_replace('class=\"img-fluid\"', 'class=\"h-44 w-full object-cover transition group-hover:scale-105\"', $albumCover);
                                        }
                                    ?>
                                    <article class="group rounded-3xl border border-white/10 bg-surface/60 shadow-elev-1">
                                        <div class="relative overflow-hidden rounded-3xl">
                                            <?php echo $albumCover; ?>
                                            <button class="absolute bottom-3 right-3 grid h-10 w-10 place-items-center rounded-full bg-accent text-white shadow-elev-1" type="button">
                                                <i class="fas fa-play"></i>
                                            </button>
                                        </div>
                                        <div class="p-4">
                                            <h3 class="truncate text-sm font-semibold text-text"><?php echo htmlspecialchars($albumTitle); ?></h3>
                                            <p class="text-xs text-muted"><?php echo htmlspecialchars($albumArtist); ?></p>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="mt-10 rounded-3xl border border-white/10 bg-surface/60 p-10 text-center">
                        <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-white/5 text-muted">
                            <i class="fas fa-search text-2xl"></i>
                        </div>
                        <h3 class="mt-4 text-lg font-semibold text-text">Aucun resultat trouve</h3>
                        <p class="mt-2 text-sm text-muted">Essayez avec d autres mots cles ou explorez nos suggestions.</p>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="mt-8">
                    <h2 class="text-lg font-semibold text-text">Suggestions populaires</h2>
                    <?php
                        $suggestedGenres = array_slice(getGenres(), 0, 4);
                    ?>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <?php if (empty($suggestedGenres)): ?>
                            <div class="col-span-full rounded-3xl border border-white/10 bg-surface/60 p-6 text-center text-muted">
                                <i class="fas fa-search text-2xl"></i>
                                <p class="mt-2 text-sm text-muted">Aucune suggestion disponible pour le moment.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($suggestedGenres as $genre): ?>
                                <?php
                                    $genreLabel = $genre['name_french'] ?? $genre['name'] ?? 'Genre';
                                ?>
                                <a href="?q=<?php echo urlencode($genreLabel); ?>" class="rounded-3xl border border-white/10 bg-surface/60 p-6 text-center shadow-elev-1">
                                    <div class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-accent/20 text-accent">
                                        <i class="fas fa-music"></i>
                                    </div>
                                    <h3 class="mt-4 text-sm font-semibold text-text"><?php echo htmlspecialchars($genreLabel); ?></h3>
                                    <p class="text-xs text-muted">Genre</p>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php include 'includes/footer-tailwind.php'; ?>
