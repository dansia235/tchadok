<?php
// Gestion des artistes
if ($dbConnected) {
    $page = (int)($_GET['page'] ?? 1);
    $limit = 8;
    $offset = ($page - 1) * $limit;

    $search = $_GET['search'] ?? '';
    $whereClause = $search ? "WHERE a.stage_name LIKE '%$search%' OR a.real_name LIKE '%$search%' OR a.genres LIKE '%$search%'" : '';

    $artists = $pdo->query("
        SELECT a.*, u.username, u.email, u.first_name, u.last_name,
               COUNT(DISTINCT al.id) as album_count,
               COUNT(DISTINCT t.id) as track_count,
               COALESCE(SUM(t.total_streams), 0) as total_streams
        FROM artists a
        LEFT JOIN users u ON a.user_id = u.id
        LEFT JOIN albums al ON a.id = al.artist_id
        LEFT JOIN tracks t ON a.id = t.artist_id
        $whereClause
        GROUP BY a.id
        ORDER BY a.total_streams DESC, a.created_at DESC
        LIMIT $limit OFFSET $offset
    ")->fetchAll();

    $totalArtists = $pdo->query("SELECT COUNT(*) FROM artists a $whereClause")->fetchColumn();
    $totalPages = ceil($totalArtists / $limit);

    $artistStats = [
        'total' => $pdo->query("SELECT COUNT(*) FROM artists")->fetchColumn(),
        'verified' => $pdo->query("SELECT COUNT(*) FROM artists WHERE verified = 1")->fetchColumn(),
        'active' => $pdo->query("SELECT COUNT(*) FROM artists WHERE is_active = 1")->fetchColumn(),
        'featured' => $pdo->query("SELECT COUNT(*) FROM artists WHERE featured = 1")->fetchColumn(),
    ];

    $topGenres = $pdo->query("
        SELECT genres, COUNT(*) as count 
        FROM artists 
        WHERE genres IS NOT NULL 
        GROUP BY genres 
        ORDER BY count DESC 
        LIMIT 5
    ")->fetchAll();
}
?>

<section class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-semibold text-text">Gestion des artistes</h2>
            <p class="text-sm text-muted">Suivi des artistes, audiences et contenus.</p>
        </div>
        <button class="rounded-full bg-accent px-4 py-2 text-xs font-semibold text-white shadow-elev-1" data-modal-open="addArtistModal">
            <i class="fas fa-microphone-alt"></i>
            Nouvel artiste
        </button>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-3xl border border-white/10 bg-surface/70 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-muted">Total</p>
            <p class="mt-3 text-2xl font-semibold text-text"><?php echo number_format($artistStats['total'] ?? 0); ?></p>
            <p class="text-xs text-muted">Artistes</p>
        </div>
        <div class="rounded-3xl border border-white/10 bg-surface/70 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-muted">Verifies</p>
            <p class="mt-3 text-2xl font-semibold text-text"><?php echo number_format($artistStats['verified'] ?? 0); ?></p>
            <p class="text-xs text-muted">Badges valides</p>
        </div>
        <div class="rounded-3xl border border-white/10 bg-surface/70 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-muted">En vedette</p>
            <p class="mt-3 text-2xl font-semibold text-text"><?php echo number_format($artistStats['featured'] ?? 0); ?></p>
            <p class="text-xs text-muted">Selection maison</p>
        </div>
        <div class="rounded-3xl border border-white/10 bg-surface/70 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-muted">Actifs</p>
            <p class="mt-3 text-2xl font-semibold text-text"><?php echo number_format($artistStats['active'] ?? 0); ?></p>
            <p class="text-xs text-muted">Avec contenu</p>
        </div>
    </div>

    <div class="rounded-3xl border border-white/10 bg-surface/60 p-4">
        <form method="GET" class="flex flex-wrap items-center gap-3">
            <input type="hidden" name="tab" value="artists">
            <div class="flex flex-1 items-center gap-2 rounded-2xl border border-white/10 bg-bg px-4 py-2">
                <i class="fas fa-search text-muted"></i>
                <input type="text" class="w-full bg-transparent text-sm text-text placeholder:text-muted focus:outline-none" name="search" placeholder="Rechercher un artiste..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <button type="submit" class="rounded-full bg-white/10 px-4 py-2 text-xs font-semibold text-text hover:bg-white/20">
                Rechercher
            </button>
        </form>
    </div>

    <?php if (!empty($artists)): ?>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            <?php foreach ($artists as $artist): ?>
                <div class="relative rounded-3xl border border-white/10 bg-surface/60 p-4 shadow-elev-1">
                    <div class="absolute right-4 top-4 flex flex-col gap-2 text-xs">
                        <?php if (!empty($artist['verified'])): ?>
                            <span class="rounded-full border border-accent/30 bg-accent/10 px-2 py-1 text-accent">Verifie</span>
                        <?php endif; ?>
                        <?php if (!empty($artist['featured'])): ?>
                            <span class="rounded-full border border-amber-400/40 bg-amber-400/10 px-2 py-1 text-amber-200">Vedette</span>
                        <?php endif; ?>
                    </div>

                    <div class="flex flex-col items-center text-center">
                        <div class="grid h-16 w-16 place-items-center rounded-full bg-accent/20 text-sm font-semibold text-accent">
                            <?php echo strtoupper(substr($artist['stage_name'], 0, 2)); ?>
                        </div>
                        <h3 class="mt-3 text-sm font-semibold text-text"><?php echo htmlspecialchars($artist['stage_name']); ?></h3>
                        <?php if (!empty($artist['real_name'])): ?>
                            <p class="text-xs text-muted"><?php echo htmlspecialchars($artist['real_name']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($artist['genres'])): ?>
                            <span class="mt-2 rounded-full border border-white/10 px-2 py-1 text-xs text-muted"><?php echo htmlspecialchars($artist['genres']); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="mt-4 grid grid-cols-3 gap-2 text-center text-xs text-muted">
                        <div>
                            <p class="text-sm font-semibold text-text"><?php echo $artist['album_count']; ?></p>
                            Albums
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-text"><?php echo $artist['track_count']; ?></p>
                            Pistes
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-text"><?php echo number_format($artist['total_streams']); ?></p>
                            Ecoutes
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap justify-center gap-2">
                        <button class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-text" onclick="viewArtist(<?php echo $artist['id']; ?>)">
                            Profil
                        </button>
                        <button class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-text" onclick="editArtist(<?php echo $artist['id']; ?>)">
                            Modifier
                        </button>
                        <button class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-text" onclick="manageMusic(<?php echo $artist['id']; ?>)">
                            Musique
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="mt-4 flex flex-wrap items-center justify-center gap-2 text-xs">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a class="rounded-full px-3 py-1 <?php echo $i === $page ? 'bg-accent text-white' : 'border border-white/10 text-muted hover:text-text'; ?>"
                       href="?tab=artists&page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="rounded-2xl border border-white/10 bg-white/5 p-6 text-center">
            <i class="fas fa-microphone text-3xl text-muted"></i>
            <p class="mt-3 text-sm text-muted">Aucun artiste trouve.</p>
            <button class="mt-4 rounded-full bg-accent px-4 py-2 text-xs font-semibold text-white" data-modal-open="addArtistModal">
                Ajouter le premier artiste
            </button>
        </div>
    <?php endif; ?>

    <div class="rounded-3xl border border-white/10 bg-surface/60 p-4">
        <h3 class="text-sm font-semibold text-text"><i class="fas fa-tags text-emerald-300"></i> Genres populaires</h3>
        <?php if (!empty($topGenres)): ?>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <?php foreach ($topGenres as $genre): ?>
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-3 text-center">
                        <p class="text-sm font-semibold text-text"><?php echo htmlspecialchars($genre['genres']); ?></p>
                        <p class="text-xs text-muted"><?php echo $genre['count']; ?> artistes</p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="mt-3 text-sm text-muted">Aucun genre disponible.</p>
        <?php endif; ?>
    </div>
</section>

<script>
function viewArtist(artistId) {
    fetch(`../api/artist.php?action=get&id=${artistId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                var artist = data.artist;
                var bodyHtml = `
                    <div class="space-y-3 text-sm text-muted">
                        <div class="text-text font-semibold">${artist.stage_name}</div>
                        <div>${artist.real_name || ''}</div>
                        <div>Genres: ${artist.genres || 'N/A'}</div>
                        <div>${artist.bio || 'Aucune biographie'}</div>
                    </div>
                `;
                showDetailModal('Profil artiste', bodyHtml, '');
            }
        });
}

function editArtist(artistId) {
    AdminModal.open('editArtistModal');
    if (typeof loadArtistForEdit === 'function') {
        loadArtistForEdit(artistId);
    }
}

function manageMusic(artistId) {
    window.location.href = `?tab=music&artist=${artistId}`;
}
</script>
