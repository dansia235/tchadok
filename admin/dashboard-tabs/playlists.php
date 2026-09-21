<?php
// Gestion des playlists
if ($dbConnected) {
    $page = (int)($_GET['page'] ?? 1);
    $limit = 12;
    $offset = ($page - 1) * $limit;

    $search = $_GET['search'] ?? '';
    $userFilter = $_GET['user_filter'] ?? '';
    $statusFilter = $_GET['status_filter'] ?? '';

    $whereConditions = [];
    if ($search) {
        $whereConditions[] = "(p.name LIKE '%$search%' OR u.username LIKE '%$search%')";
    }
    if ($userFilter) {
        $whereConditions[] = "p.user_id = $userFilter";
    }
    if ($statusFilter) {
        $whereConditions[] = "p.is_public = " . ($statusFilter === 'public' ? '1' : '0');
    }

    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

    $playlists = $pdo->query("
        SELECT p.*, u.username, u.first_name, u.last_name,
               COUNT(pt.track_id) as track_count,
               COALESCE(SUM(t.duration), 0) as total_duration
        FROM playlists p
        LEFT JOIN users u ON p.user_id = u.id
        LEFT JOIN playlist_tracks pt ON p.id = pt.playlist_id
        LEFT JOIN tracks t ON pt.track_id = t.id
        $whereClause
        GROUP BY p.id
        ORDER BY p.created_at DESC
        LIMIT $limit OFFSET $offset
    ")->fetchAll();

    $totalPlaylists = $pdo->query("SELECT COUNT(*) FROM playlists p LEFT JOIN users u ON p.user_id = u.id $whereClause")->fetchColumn();
    $totalPages = ceil($totalPlaylists / $limit);

    $playlistStats = [
        'total' => $pdo->query("SELECT COUNT(*) FROM playlists")->fetchColumn(),
        'public' => $pdo->query("SELECT COUNT(*) FROM playlists WHERE is_public = 1")->fetchColumn(),
        'private' => $pdo->query("SELECT COUNT(*) FROM playlists WHERE is_public = 0")->fetchColumn(),
        'avg_tracks' => $pdo->query("SELECT COALESCE(AVG(track_count), 0) FROM (SELECT COUNT(track_id) as track_count FROM playlist_tracks GROUP BY playlist_id) as counts")->fetchColumn(),
    ];

    $topPlaylistCreators = $pdo->query("
        SELECT u.username, u.first_name, u.last_name, COUNT(p.id) as playlist_count
        FROM users u
        JOIN playlists p ON u.id = p.user_id
        GROUP BY u.id
        ORDER BY playlist_count DESC
        LIMIT 5
    ")->fetchAll();
}
?>

<section class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-semibold text-text">Gestion des playlists</h2>
            <p class="text-sm text-muted">Playlists creees par la communaute.</p>
        </div>
        <button class="rounded-full bg-accent px-4 py-2 text-xs font-semibold text-white shadow-elev-1" data-modal-open="addPlaylistModal">
            <i class="fas fa-plus"></i>
            Nouvelle playlist
        </button>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-3xl border border-white/10 bg-surface/70 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-muted">Total</p>
            <p class="mt-3 text-2xl font-semibold text-text"><?php echo number_format($playlistStats['total'] ?? 0); ?></p>
            <p class="text-xs text-muted">Playlists</p>
        </div>
        <div class="rounded-3xl border border-white/10 bg-surface/70 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-muted">Publiques</p>
            <p class="mt-3 text-2xl font-semibold text-text"><?php echo number_format($playlistStats['public'] ?? 0); ?></p>
        </div>
        <div class="rounded-3xl border border-white/10 bg-surface/70 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-muted">Privees</p>
            <p class="mt-3 text-2xl font-semibold text-text"><?php echo number_format($playlistStats['private'] ?? 0); ?></p>
        </div>
        <div class="rounded-3xl border border-white/10 bg-surface/70 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-muted">Moyenne pistes</p>
            <p class="mt-3 text-2xl font-semibold text-text"><?php echo number_format($playlistStats['avg_tracks'] ?? 0, 1); ?></p>
        </div>
    </div>

    <div class="rounded-3xl border border-white/10 bg-surface/60 p-4">
        <form method="GET" class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_200px_200px_auto] lg:items-center">
            <input type="hidden" name="tab" value="music">
            <input type="hidden" name="music_tab" value="playlists">
            <?php foreach (['user_filter', 'status_filter'] as $param): ?>
                <?php if (!empty($_GET[$param])): ?>
                    <input type="hidden" name="<?php echo $param; ?>" value="<?php echo htmlspecialchars($_GET[$param]); ?>">
                <?php endif; ?>
            <?php endforeach; ?>
            <div class="flex items-center gap-2 rounded-2xl border border-white/10 bg-bg px-4 py-2">
                <i class="fas fa-search text-muted"></i>
                <input type="text" class="w-full bg-transparent text-sm text-text placeholder:text-muted focus:outline-none" name="search" placeholder="Rechercher playlist..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <select class="rounded-2xl border border-white/10 bg-bg px-3 py-2 text-sm text-text" onchange="filterByStatus(this.value)">
                <option value="">Tous les statuts</option>
                <option value="public" <?php echo $statusFilter === 'public' ? 'selected' : ''; ?>>Publiques</option>
                <option value="private" <?php echo $statusFilter === 'private' ? 'selected' : ''; ?>>Privees</option>
            </select>
            <select class="rounded-2xl border border-white/10 bg-bg px-3 py-2 text-sm text-text" onchange="filterByUser(this.value)" id="playlistUserFilter">
                <option value="">Tous les utilisateurs</option>
            </select>
            <button type="submit" class="rounded-full bg-white/10 px-4 py-2 text-xs font-semibold text-text hover:bg-white/20">
                Filtrer
            </button>
        </form>
    </div>

    <?php if (!empty($playlists)): ?>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($playlists as $playlist): ?>
                <div class="rounded-3xl border border-white/10 bg-surface/60 p-4 shadow-elev-1">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-semibold text-text"><?php echo htmlspecialchars($playlist['name']); ?></h3>
                            <p class="text-xs text-muted">@<?php echo htmlspecialchars($playlist['username']); ?></p>
                        </div>
                        <span class="rounded-full border border-white/10 px-2 py-1 text-xs text-muted">
                            <?php echo $playlist['is_public'] ? 'Public' : 'Prive'; ?>
                        </span>
                    </div>
                    <?php if ($playlist['description']): ?>
                        <p class="mt-3 text-xs text-muted"><?php echo htmlspecialchars(substr($playlist['description'], 0, 90)); ?><?php echo strlen($playlist['description']) > 90 ? '...' : ''; ?></p>
                    <?php endif; ?>
                    <div class="mt-3 text-xs text-muted">
                        <?php echo $playlist['track_count']; ?> pistes • <?php echo gmdate("H:i", $playlist['total_duration']); ?>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <button class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-text" onclick="viewPlaylist(<?php echo $playlist['id']; ?>)">Details</button>
                        <button class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-text" onclick="editPlaylist(<?php echo $playlist['id']; ?>)">Modifier</button>
                        <button class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-text" onclick="managePlaylistTracks(<?php echo $playlist['id']; ?>)">Pistes</button>
                    </div>
                    <p class="mt-3 text-xs text-muted">Creee le <?php echo date('d/m/Y', strtotime($playlist['created_at'])); ?></p>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="flex flex-wrap items-center justify-center gap-2 text-xs">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a class="rounded-full px-3 py-1 <?php echo $i === $page ? 'bg-accent text-white' : 'border border-white/10 text-muted hover:text-text'; ?>"
                       href="?tab=music&music_tab=playlists&page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?><?php echo $userFilter ? '&user_filter=' . $userFilter : ''; ?><?php echo $statusFilter ? '&status_filter=' . $statusFilter : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="rounded-2xl border border-white/10 bg-white/5 p-6 text-center">
            <i class="fas fa-list-music text-3xl text-muted"></i>
            <p class="mt-3 text-sm text-muted">Aucune playlist trouvee.</p>
            <button class="mt-4 rounded-full bg-accent px-4 py-2 text-xs font-semibold text-white" data-modal-open="addPlaylistModal">
                Creer la premiere playlist
            </button>
        </div>
    <?php endif; ?>

    <?php if (!empty($topPlaylistCreators)): ?>
        <div class="rounded-3xl border border-white/10 bg-surface/60 p-6">
            <h3 class="text-sm font-semibold text-text"><i class="fas fa-crown text-amber-300"></i> Top createurs</h3>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <?php foreach ($topPlaylistCreators as $index => $creator): ?>
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-3 text-center">
                        <p class="text-xs text-muted">#<?php echo $index + 1; ?></p>
                        <p class="text-sm font-semibold text-text"><?php echo htmlspecialchars($creator['first_name'] . ' ' . $creator['last_name']); ?></p>
                        <p class="text-xs text-muted">@<?php echo htmlspecialchars($creator['username']); ?></p>
                        <span class="mt-2 inline-flex rounded-full border border-emerald-400/30 bg-emerald-400/10 px-2 py-1 text-xs text-emerald-200">
                            <?php echo $creator['playlist_count']; ?> playlists
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</section>

<script>
function viewPlaylist(playlistId) {
    fetch(`../api/playlist.php?action=get&id=${playlistId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                var playlist = data.playlist;
                var bodyHtml = `
                    <div class="space-y-2 text-sm text-muted">
                        <div class="text-text font-semibold">${playlist.name}</div>
                        <div>${playlist.description || 'Aucune description'}</div>
                        <div>${playlist.track_count} pistes</div>
                    </div>
                `;
                showDetailModal('Details playlist', bodyHtml, '');
            }
        });
}

function editPlaylist(playlistId) {
    AdminModal.open('editPlaylistModal');
    if (typeof loadPlaylistForEdit === 'function') {
        loadPlaylistForEdit(playlistId);
    }
}

function managePlaylistTracks(playlistId) {
    window.location.href = `?tab=music&music_tab=tracks&playlist_id=${playlistId}`;
}

function filterByStatus(status) {
    var currentUrl = new URL(window.location);
    if (status) {
        currentUrl.searchParams.set('status_filter', status);
    } else {
        currentUrl.searchParams.delete('status_filter');
    }
    currentUrl.searchParams.set('page', '1');
    window.location.href = currentUrl.toString();
}

function filterByUser(userId) {
    var currentUrl = new URL(window.location);
    if (userId) {
        currentUrl.searchParams.set('user_filter', userId);
    } else {
        currentUrl.searchParams.delete('user_filter');
    }
    currentUrl.searchParams.set('page', '1');
    window.location.href = currentUrl.toString();
}

document.addEventListener('DOMContentLoaded', function() {
    fetch('../api/user.php?action=list')
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                return;
            }
            var select = document.getElementById('playlistUserFilter');
            if (!select) {
                return;
            }
            data.users.forEach(function(user) {
                var option = document.createElement('option');
                option.value = user.id;
                option.textContent = `${user.first_name} ${user.last_name} (@${user.username})`;
                if (String(user.id) === '<?php echo $userFilter; ?>') {
                    option.selected = true;
                }
                select.appendChild(option);
            });
        });
});
</script>
