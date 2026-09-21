<?php
// Gestion de la musique (albums et pistes)
if ($dbConnected) {
    $activeTab = $_GET['music_tab'] ?? 'tracks';
    $page = (int)($_GET['page'] ?? 1);
    $limit = 12;
    $offset = ($page - 1) * $limit;

    $search = $_GET['search'] ?? '';
    $artistFilter = $_GET['artist'] ?? '';

    if ($activeTab === 'tracks') {
        $whereConditions = [];
        if ($search) {
            $whereConditions[] = "(t.title LIKE '%$search%' OR ar.stage_name LIKE '%$search%')";
        }
        if ($artistFilter) {
            $whereConditions[] = "t.artist_id = $artistFilter";
        }
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        $tracks = $pdo->query("
            SELECT t.*, ar.stage_name, al.title as album_title,
                   COALESCE(t.total_streams, 0) as streams,
                   COALESCE(t.total_downloads, 0) as downloads
            FROM tracks t
            JOIN artists ar ON t.artist_id = ar.id
            LEFT JOIN albums al ON t.album_id = al.id
            $whereClause
            ORDER BY t.created_at DESC, t.total_streams DESC
            LIMIT $limit OFFSET $offset
        ")->fetchAll();

        $totalTracks = $pdo->query("SELECT COUNT(*) FROM tracks t JOIN artists ar ON t.artist_id = ar.id $whereClause")->fetchColumn();
        $totalPages = ceil($totalTracks / $limit);
    } elseif ($activeTab === 'albums') {
        $whereConditions = [];
        if ($search) {
            $whereConditions[] = "(al.title LIKE '%$search%' OR ar.stage_name LIKE '%$search%')";
        }
        if ($artistFilter) {
            $whereConditions[] = "al.artist_id = $artistFilter";
        }
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        $albums = $pdo->query("
            SELECT al.*, ar.stage_name,
                   COALESCE(al.total_tracks, 0) as track_count,
                   COALESCE(al.total_streams, 0) as streams,
                   COALESCE(al.total_sales, 0) as sales
            FROM albums al
            JOIN artists ar ON al.artist_id = ar.id
            $whereClause
            ORDER BY al.created_at DESC, al.total_streams DESC
            LIMIT $limit OFFSET $offset
        ")->fetchAll();

        $totalAlbums = $pdo->query("SELECT COUNT(*) FROM albums al JOIN artists ar ON al.artist_id = ar.id $whereClause")->fetchColumn();
        $totalPages = ceil($totalAlbums / $limit);
    } else {
        $whereConditions = [];
        if ($search) {
            $whereConditions[] = "(p.name LIKE '%$search%' OR u.username LIKE '%$search%')";
        }
        if ($artistFilter) {
            $whereConditions[] = "p.user_id = $artistFilter";
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
    }

    $artists = $pdo->query("SELECT id, stage_name FROM artists ORDER BY stage_name")->fetchAll();

    $musicStats = [
        'total_tracks' => $pdo->query("SELECT COUNT(*) FROM tracks")->fetchColumn(),
        'total_albums' => $pdo->query("SELECT COUNT(*) FROM albums")->fetchColumn(),
        'total_streams' => $pdo->query("SELECT COALESCE(SUM(total_streams), 0) FROM tracks")->fetchColumn(),
        'avg_duration' => $pdo->query("SELECT COALESCE(AVG(duration), 0) FROM tracks")->fetchColumn(),
    ];
}

$addModal = $activeTab === 'tracks' ? 'addTrackModal' : ($activeTab === 'albums' ? 'addAlbumModal' : 'addPlaylistModal');
?>

<section class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-semibold text-text">Gestion de la musique</h2>
            <p class="text-sm text-muted">Pistes, albums et playlists de la plateforme.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button class="rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-semibold text-text hover:bg-white/10" data-modal-open="bulkMusicModal">
                <i class="fas fa-layer-group"></i>
                Actions groupees
            </button>
            <button class="rounded-full bg-accent px-4 py-2 text-xs font-semibold text-white shadow-elev-1" data-modal-open="<?php echo $addModal; ?>">
                <i class="fas fa-plus"></i>
                Ajouter <?php echo $activeTab === 'tracks' ? 'piste' : ($activeTab === 'albums' ? 'album' : 'playlist'); ?>
            </button>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-3xl border border-white/10 bg-surface/70 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-muted">Pistes</p>
            <p class="mt-3 text-2xl font-semibold text-text"><?php echo number_format($musicStats['total_tracks'] ?? 0); ?></p>
            <p class="text-xs text-muted">Total catalogue</p>
        </div>
        <div class="rounded-3xl border border-white/10 bg-surface/70 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-muted">Albums</p>
            <p class="mt-3 text-2xl font-semibold text-text"><?php echo number_format($musicStats['total_albums'] ?? 0); ?></p>
            <p class="text-xs text-muted">Sorties publiees</p>
        </div>
        <div class="rounded-3xl border border-white/10 bg-surface/70 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-muted">Ecoutes</p>
            <p class="mt-3 text-2xl font-semibold text-text"><?php echo number_format($musicStats['total_streams'] ?? 0); ?></p>
            <p class="text-xs text-muted">Total streams</p>
        </div>
        <div class="rounded-3xl border border-white/10 bg-surface/70 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-muted">Duree moyenne</p>
            <p class="mt-3 text-2xl font-semibold text-text"><?php echo gmdate("i:s", $musicStats['avg_duration'] ?? 0); ?></p>
            <p class="text-xs text-muted">Par piste</p>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-3 rounded-3xl border border-white/10 bg-surface/60 p-4">
        <a class="rounded-full px-4 py-2 text-xs font-semibold <?php echo $activeTab === 'tracks' ? 'bg-accent text-white' : 'border border-white/10 text-muted hover:text-text'; ?>" href="?tab=music&music_tab=tracks">Pistes</a>
        <a class="rounded-full px-4 py-2 text-xs font-semibold <?php echo $activeTab === 'albums' ? 'bg-accent text-white' : 'border border-white/10 text-muted hover:text-text'; ?>" href="?tab=music&music_tab=albums">Albums</a>
        <a class="rounded-full px-4 py-2 text-xs font-semibold <?php echo $activeTab === 'playlists' ? 'bg-accent text-white' : 'border border-white/10 text-muted hover:text-text'; ?>" href="?tab=music&music_tab=playlists">Playlists</a>
    </div>

    <div class="rounded-3xl border border-white/10 bg-surface/60 p-4">
        <form method="GET" class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_200px_200px_auto] lg:items-center">
            <input type="hidden" name="tab" value="music">
            <input type="hidden" name="music_tab" value="<?php echo $activeTab; ?>">
            <?php if ($artistFilter): ?>
                <input type="hidden" name="artist" value="<?php echo $artistFilter; ?>">
            <?php endif; ?>
            <div class="flex items-center gap-2 rounded-2xl border border-white/10 bg-bg px-4 py-2">
                <i class="fas fa-search text-muted"></i>
                <input type="text" class="w-full bg-transparent text-sm text-text placeholder:text-muted focus:outline-none" name="search" placeholder="Rechercher..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <select class="rounded-2xl border border-white/10 bg-bg px-3 py-2 text-sm text-text" onchange="filterByArtist(this.value)">
                <option value="">Tous les artistes</option>
                <?php foreach ($artists as $artist): ?>
                    <option value="<?php echo $artist['id']; ?>" <?php echo $artistFilter == $artist['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($artist['stage_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select class="rounded-2xl border border-white/10 bg-bg px-3 py-2 text-sm text-text" onchange="sortMusic(this.value)">
                <option value="recent">Plus recents</option>
                <option value="popular">Plus populaires</option>
                <option value="alphabetical">Alphabetique</option>
                <option value="duration">Par duree</option>
            </select>
            <button type="submit" class="rounded-full bg-white/10 px-4 py-2 text-xs font-semibold text-text hover:bg-white/20">
                Filtrer
            </button>
        </form>
    </div>

    <?php if ($activeTab === 'tracks'): ?>
        <div class="rounded-3xl border border-white/10 bg-surface/60 p-4">
            <?php if (!empty($tracks)): ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-muted">
                        <thead class="border-b border-white/10 text-xs uppercase text-muted">
                            <tr>
                                <th class="py-3"><input type="checkbox" id="selectAllTracks" class="h-4 w-4 rounded border-white/20 bg-bg text-accent"></th>
                                <th class="py-3">Piste</th>
                                <th class="py-3">Artiste</th>
                                <th class="py-3">Album</th>
                                <th class="py-3">Duree</th>
                                <th class="py-3">Ecoutes</th>
                                <th class="py-3">Statut</th>
                                <th class="py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/10">
                            <?php foreach ($tracks as $track): ?>
                                <tr class="hover:bg-white/5">
                                    <td class="py-3">
                                        <input type="checkbox" class="track-checkbox h-4 w-4 rounded border-white/20 bg-bg text-accent" value="<?php echo $track['id']; ?>">
                                    </td>
                                    <td class="py-3">
                                        <div class="flex items-center gap-3">
                                            <div class="grid h-10 w-10 place-items-center rounded-2xl bg-white/5 text-accent">
                                                <i class="fas fa-music"></i>
                                            </div>
                                            <div>
                                                <p class="text-sm font-semibold text-text"><?php echo htmlspecialchars($track['title']); ?></p>
                                                <p class="text-xs text-muted">ID <?php echo $track['id']; ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3 text-sm text-text"><?php echo htmlspecialchars($track['stage_name']); ?></td>
                                    <td class="py-3 text-sm text-muted">
                                        <?php echo $track['album_title'] ? htmlspecialchars($track['album_title']) : 'Single'; ?>
                                    </td>
                                    <td class="py-3 text-sm text-muted"><?php echo gmdate("i:s", $track['duration'] ?? 0); ?></td>
                                    <td class="py-3">
                                        <p class="text-sm font-semibold text-text"><?php echo number_format($track['streams']); ?></p>
                                        <p class="text-xs text-muted"><?php echo number_format($track['downloads']); ?> downloads</p>
                                    </td>
                                    <td class="py-3">
                                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold <?php echo getStatusBadgeClass($track['status']); ?>">
                                            <?php echo ucfirst($track['status']); ?>
                                        </span>
                                    </td>
                                    <td class="py-3 text-right">
                                        <div class="inline-flex gap-2">
                                            <button class="grid h-8 w-8 place-items-center rounded-full border border-white/10 bg-white/5 text-text hover:bg-white/10" onclick="playTrack(<?php echo $track['id']; ?>)" title="Ecouter">
                                                <i class="fas fa-play text-xs"></i>
                                            </button>
                                            <button class="grid h-8 w-8 place-items-center rounded-full border border-white/10 bg-white/5 text-text hover:bg-white/10" onclick="editTrack(<?php echo $track['id']; ?>)" title="Modifier">
                                                <i class="fas fa-edit text-xs"></i>
                                            </button>
                                            <button class="grid h-8 w-8 place-items-center rounded-full border border-white/10 bg-white/5 text-rose-200 hover:bg-rose-500/20" onclick="deleteTrack(<?php echo $track['id']; ?>)" title="Supprimer">
                                                <i class="fas fa-trash text-xs"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="rounded-2xl border border-white/10 bg-white/5 p-6 text-center">
                    <i class="fas fa-music text-3xl text-muted"></i>
                    <p class="mt-3 text-sm text-muted">Aucune piste trouvee.</p>
                    <button class="mt-4 rounded-full bg-accent px-4 py-2 text-xs font-semibold text-white" data-modal-open="addTrackModal">
                        Ajouter la premiere piste
                    </button>
                </div>
            <?php endif; ?>
        </div>
    <?php elseif ($activeTab === 'albums'): ?>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            <?php if (!empty($albums)): ?>
                <?php foreach ($albums as $album): ?>
                    <div class="rounded-3xl border border-white/10 bg-surface/60 p-4 shadow-elev-1">
                        <div class="h-36 rounded-2xl bg-white/5 p-4 text-center text-muted">
                            <i class="fas fa-compact-disc text-3xl"></i>
                        </div>
                        <div class="mt-4">
                            <h3 class="text-sm font-semibold text-text"><?php echo htmlspecialchars($album['title']); ?></h3>
                            <p class="text-xs text-muted"><?php echo htmlspecialchars($album['stage_name']); ?></p>
                            <div class="mt-2 flex flex-wrap gap-2 text-xs">
                                <span class="rounded-full border border-white/10 px-2 py-1 text-muted"><?php echo strtoupper($album['type'] ?? 'ALBUM'); ?></span>
                                <span class="rounded-full border border-white/10 px-2 py-1 text-muted"><?php echo $album['track_count']; ?> pistes</span>
                            </div>
                            <p class="mt-2 text-xs text-muted"><?php echo number_format($album['streams']); ?> ecoutes</p>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <button class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-text" onclick="editAlbum(<?php echo $album['id']; ?>)">Modifier</button>
                            <button class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-text" onclick="manageAlbumTracks(<?php echo $album['id']; ?>)">Pistes</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="rounded-2xl border border-white/10 bg-white/5 p-6 text-center">
                    <i class="fas fa-compact-disc text-3xl text-muted"></i>
                    <p class="mt-3 text-sm text-muted">Aucun album trouve.</p>
                    <button class="mt-4 rounded-full bg-accent px-4 py-2 text-xs font-semibold text-white" data-modal-open="addAlbumModal">
                        Ajouter le premier album
                    </button>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <?php if (!empty($playlists)): ?>
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
                        <div class="mt-3 text-xs text-muted">
                            <?php echo $playlist['track_count']; ?> pistes • <?php echo gmdate("H:i", $playlist['total_duration']); ?>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <button class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-text" onclick="viewPlaylist(<?php echo $playlist['id']; ?>)">Details</button>
                            <button class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-text" onclick="editPlaylist(<?php echo $playlist['id']; ?>)">Modifier</button>
                            <button class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-text" onclick="managePlaylistTracks(<?php echo $playlist['id']; ?>)">Pistes</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="rounded-2xl border border-white/10 bg-white/5 p-6 text-center">
                    <i class="fas fa-list-music text-3xl text-muted"></i>
                    <p class="mt-3 text-sm text-muted">Aucune playlist trouvee.</p>
                    <button class="mt-4 rounded-full bg-accent px-4 py-2 text-xs font-semibold text-white" data-modal-open="addPlaylistModal">
                        Creer la premiere playlist
                    </button>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($totalPages) && $totalPages > 1): ?>
        <div class="flex flex-wrap items-center justify-center gap-2 text-xs">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a class="rounded-full px-3 py-1 <?php echo $i === $page ? 'bg-accent text-white' : 'border border-white/10 text-muted hover:text-text'; ?>"
                   href="?tab=music&music_tab=<?php echo $activeTab; ?>&page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?><?php echo $artistFilter ? '&artist=' . $artistFilter : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</section>

<div id="audioPlayer" class="fixed bottom-6 right-6 hidden w-80 rounded-3xl border border-white/10 bg-surface/90 p-4 shadow-elev-3">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-sm font-semibold text-text track-title-mini">Titre</p>
            <p class="text-xs text-muted artist-name-mini">Artiste</p>
        </div>
        <button class="grid h-8 w-8 place-items-center rounded-full border border-white/10 text-muted hover:text-text" id="closePlayer">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="mt-4 flex items-center gap-3">
        <button class="grid h-8 w-8 place-items-center rounded-full border border-white/10 text-muted hover:text-text" id="prevBtn">
            <i class="fas fa-step-backward"></i>
        </button>
        <button class="grid h-10 w-10 place-items-center rounded-full bg-accent text-white" id="playPauseBtn">
            <i class="fas fa-play"></i>
        </button>
        <button class="grid h-8 w-8 place-items-center rounded-full border border-white/10 text-muted hover:text-text" id="nextBtn">
            <i class="fas fa-step-forward"></i>
        </button>
    </div>
    <div class="mt-4">
        <div class="h-1.5 w-full rounded-full bg-white/10">
            <div class="h-1.5 w-1/3 rounded-full bg-accent"></div>
        </div>
        <div class="mt-2 flex justify-between text-xs text-muted">
            <span class="current-time">0:00</span>
            <span class="total-time">0:00</span>
        </div>
    </div>
    <audio id="audioElement"></audio>
</div>

<script>
function playTrack(trackId) {
    fetch(`../api/track.php?action=get&id=${trackId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAudioPlayer(data.track);
            }
        });
}

function showAudioPlayer(track) {
    var player = document.getElementById('audioPlayer');
    if (!player) return;
    player.querySelector('.track-title-mini').textContent = track.title || 'Titre';
    player.querySelector('.artist-name-mini').textContent = track.artist || 'Artiste';
    player.classList.remove('hidden');
}

function editTrack(trackId) {
    AdminModal.open('editTrackModal');
    if (typeof loadTrackForEdit === 'function') {
        loadTrackForEdit(trackId);
    }
}

function editAlbum(albumId) {
    AdminModal.open('editAlbumModal');
    if (typeof loadAlbumForEdit === 'function') {
        loadAlbumForEdit(albumId);
    }
}

function editPlaylist(playlistId) {
    AdminModal.open('editPlaylistModal');
    if (typeof loadPlaylistForEdit === 'function') {
        loadPlaylistForEdit(playlistId);
    }
}

function deleteTrack(trackId) {
    if (confirm('Supprimer cette piste ?')) {
        fetch(`../api/track.php?action=delete&id=${trackId}`, { method: 'DELETE' })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Erreur: ' + data.error);
                }
            });
    }
}

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

function manageAlbumTracks(albumId) {
    window.location.href = `?tab=music&music_tab=tracks&album=${albumId}`;
}

function managePlaylistTracks(playlistId) {
    window.location.href = `?tab=music&music_tab=tracks&playlist_id=${playlistId}`;
}

function filterByArtist(artistId) {
    var currentUrl = new URL(window.location);
    if (artistId) {
        currentUrl.searchParams.set('artist', artistId);
    } else {
        currentUrl.searchParams.delete('artist');
    }
    currentUrl.searchParams.set('page', '1');
    window.location.href = currentUrl.toString();
}

function sortMusic(sortBy) {
    var currentUrl = new URL(window.location);
    currentUrl.searchParams.set('sort', sortBy);
    currentUrl.searchParams.set('page', '1');
    window.location.href = currentUrl.toString();
}

document.getElementById('closePlayer')?.addEventListener('click', function() {
    document.getElementById('audioPlayer')?.classList.add('hidden');
});

document.getElementById('playPauseBtn')?.addEventListener('click', function() {
    var icon = this.querySelector('i');
    if (!icon) return;
    if (icon.classList.contains('fa-play')) {
        icon.classList.remove('fa-play');
        icon.classList.add('fa-pause');
    } else {
        icon.classList.remove('fa-pause');
        icon.classList.add('fa-play');
    }
});

document.getElementById('selectAllTracks')?.addEventListener('change', function(event) {
    var checkboxes = document.querySelectorAll('.track-checkbox');
    checkboxes.forEach(function(cb) {
        cb.checked = event.target.checked;
    });
});
</script>

<?php
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'approved':
            return 'border border-emerald-400/30 bg-emerald-400/10 text-emerald-200';
        case 'pending':
            return 'border border-amber-400/30 bg-amber-400/10 text-amber-200';
        case 'draft':
            return 'border border-white/10 bg-white/5 text-muted';
        case 'rejected':
            return 'border border-rose-400/30 bg-rose-400/10 text-rose-200';
        default:
            return 'border border-white/10 bg-white/5 text-muted';
    }
}

function getTypeBadgeClass($type) {
    switch ($type) {
        case 'album':
            return 'border border-accent/30 bg-accent/10 text-accent';
        case 'ep':
            return 'border border-sky-400/30 bg-sky-400/10 text-sky-200';
        case 'single':
            return 'border border-emerald-400/30 bg-emerald-400/10 text-emerald-200';
        case 'maxi_single':
            return 'border border-amber-400/30 bg-amber-400/10 text-amber-200';
        default:
            return 'border border-white/10 bg-white/5 text-muted';
    }
}
?>
