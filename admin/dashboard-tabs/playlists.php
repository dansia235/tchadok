<?php
// Gestion des playlists
if ($dbConnected) {
    $page = (int)($_GET['page'] ?? 1);
    $limit = 12;
    $offset = ($page - 1) * $limit;
    
    $search = $_GET['search'] ?? '';
    $userFilter = $_GET['user_filter'] ?? '';
    $statusFilter = $_GET['status_filter'] ?? '';
    
    // Construction des conditions WHERE
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
    
    // Récupération des playlists
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
    
    // Statistiques des playlists
    $playlistStats = [
        'total' => $pdo->query("SELECT COUNT(*) FROM playlists")->fetchColumn(),
        'public' => $pdo->query("SELECT COUNT(*) FROM playlists WHERE is_public = 1")->fetchColumn(),
        'private' => $pdo->query("SELECT COUNT(*) FROM playlists WHERE is_public = 0")->fetchColumn(),
        'avg_tracks' => $pdo->query("SELECT COALESCE(AVG(track_count), 0) FROM (SELECT COUNT(track_id) as track_count FROM playlist_tracks GROUP BY playlist_id) as counts")->fetchColumn(),
    ];
    
    // Top utilisateurs par playlists
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

<div class="row mb-4">
    <div class="col-12">
        <h2 class="mb-0 d-flex align-items-center">
            <i class="fas fa-list-music me-3 text-success"></i>
            Gestion des Playlists
            <span class="badge bg-success ms-3"><?php echo number_format($playlistStats['total'] ?? 0); ?> playlists</span>
        </h2>
        <p class="text-muted">Gérer toutes les playlists créées par les utilisateurs</p>
    </div>
</div>

<!-- Statistiques des playlists -->
<div class="row g-4 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card text-center">
            <i class="fas fa-list-music fa-2x text-success mb-3"></i>
            <div class="stat-number"><?php echo number_format($playlistStats['total'] ?? 0); ?></div>
            <h6 class="text-muted mb-0">Total Playlists</h6>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card text-center">
            <i class="fas fa-globe fa-2x text-primary mb-3"></i>
            <div class="stat-number"><?php echo number_format($playlistStats['public'] ?? 0); ?></div>
            <h6 class="text-muted mb-0">Publiques</h6>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card text-center">
            <i class="fas fa-lock fa-2x text-warning mb-3"></i>
            <div class="stat-number"><?php echo number_format($playlistStats['private'] ?? 0); ?></div>
            <h6 class="text-muted mb-0">Privées</h6>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card text-center">
            <i class="fas fa-music fa-2x text-info mb-3"></i>
            <div class="stat-number"><?php echo number_format($playlistStats['avg_tracks'] ?? 0, 1); ?></div>
            <h6 class="text-muted mb-0">Pistes/Playlist (moy.)</h6>
        </div>
    </div>
</div>

<!-- Filtres et recherche -->
<div class="row mb-4">
    <div class="col-12">
        <div class="chart-card">
            <div class="row align-items-center">
                <div class="col-md-3">
                    <form method="GET" class="d-flex">
                        <input type="hidden" name="tab" value="music">
                        <input type="hidden" name="music_tab" value="playlists">
                        <?php foreach (['user_filter', 'status_filter'] as $param): ?>
                            <?php if (!empty($_GET[$param])): ?>
                                <input type="hidden" name="<?php echo $param; ?>" value="<?php echo htmlspecialchars($_GET[$param]); ?>">
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <div class="input-group">
                            <input type="text" class="form-control" name="search" placeholder="Rechercher playlist..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                            <button class="btn btn-admin" type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>
                </div>
                <div class="col-md-2">
                    <select class="form-select" onchange="filterByStatus(this.value)">
                        <option value="">Tous les statuts</option>
                        <option value="public" <?php echo $statusFilter === 'public' ? 'selected' : ''; ?>>Publiques</option>
                        <option value="private" <?php echo $statusFilter === 'private' ? 'selected' : ''; ?>>Privées</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" onchange="filterByUser(this.value)">
                        <option value="">Tous les utilisateurs</option>
                        <!-- Options chargées dynamiquement -->
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" onchange="sortPlaylists(this.value)">
                        <option value="recent">Plus récentes</option>
                        <option value="popular">Plus populaires</option>
                        <option value="longest">Plus longues</option>
                        <option value="tracks">Plus de pistes</option>
                    </select>
                </div>
                <div class="col-md-2 text-end">
                    <button class="btn btn-success-admin" data-bs-toggle="modal" data-bs-target="#addPlaylistModal">
                        <i class="fas fa-plus me-2"></i>
                        Nouvelle Playlist
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Grille des playlists -->
<?php if (!empty($playlists)): ?>
<div class="row g-4 mb-4">
    <?php foreach ($playlists as $playlist): ?>
    <div class="col-xl-4 col-lg-6">
        <div class="chart-card playlist-card">
            <!-- En-tête de la playlist -->
            <div class="playlist-header d-flex justify-content-between align-items-start mb-3">
                <div class="playlist-info flex-grow-1">
                    <h6 class="playlist-title mb-1"><?php echo htmlspecialchars($playlist['name']); ?></h6>
                    <div class="playlist-creator">
                        <small class="text-muted">
                            par <strong><?php echo htmlspecialchars($playlist['first_name'] . ' ' . $playlist['last_name']); ?></strong>
                            (@<?php echo htmlspecialchars($playlist['username']); ?>)
                        </small>
                    </div>
                </div>
                <div class="playlist-status">
                    <?php if ($playlist['is_public']): ?>
                        <span class="badge bg-primary">
                            <i class="fas fa-globe me-1"></i>Public
                        </span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark">
                            <i class="fas fa-lock me-1"></i>Privé
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Pochette de la playlist -->
            <div class="playlist-cover mb-3">
                <div class="playlist-placeholder">
                    <i class="fas fa-list-music fa-3x"></i>
                </div>
                <div class="playlist-overlay">
                    <button class="btn btn-light btn-sm" onclick="playPlaylist(<?php echo $playlist['id']; ?>)">
                        <i class="fas fa-play"></i>
                    </button>
                </div>
            </div>
            
            <!-- Description -->
            <?php if ($playlist['description']): ?>
            <div class="playlist-description mb-3">
                <p class="text-muted small"><?php echo htmlspecialchars(substr($playlist['description'], 0, 100)); ?><?php echo strlen($playlist['description']) > 100 ? '...' : ''; ?></p>
            </div>
            <?php endif; ?>
            
            <!-- Statistiques -->
            <div class="playlist-stats row text-center mb-3">
                <div class="col-6">
                    <div class="stat-mini">
                        <div class="stat-number-mini"><?php echo $playlist['track_count']; ?></div>
                        <small class="text-muted">Pistes</small>
                    </div>
                </div>
                <div class="col-6">
                    <div class="stat-mini">
                        <div class="stat-number-mini"><?php echo gmdate("H:i", $playlist['total_duration']); ?></div>
                        <small class="text-muted">Durée</small>
                    </div>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="playlist-actions">
                <div class="btn-group btn-group-sm w-100">
                    <button class="btn btn-outline-primary" onclick="viewPlaylist(<?php echo $playlist['id']; ?>)" title="Voir détails">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn btn-outline-warning" onclick="editPlaylist(<?php echo $playlist['id']; ?>)" title="Modifier">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-outline-info" onclick="managePlaylistTracks(<?php echo $playlist['id']; ?>)" title="Gérer pistes">
                        <i class="fas fa-music"></i>
                    </button>
                    <?php if ($playlist['user_id'] > 1): // Ne pas supprimer les playlists de l'admin ?>
                    <button class="btn btn-outline-danger" onclick="deletePlaylist(<?php echo $playlist['id']; ?>)" title="Supprimer">
                        <i class="fas fa-trash"></i>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Date de création -->
            <div class="playlist-date mt-2">
                <small class="text-muted">
                    <i class="fas fa-calendar me-1"></i>
                    Créée le <?php echo date('d/m/Y', strtotime($playlist['created_at'])); ?>
                </small>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<nav class="mb-4">
    <ul class="pagination justify-content-center">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
            <a class="page-link" href="?tab=music&music_tab=playlists&page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?><?php echo $userFilter ? '&user_filter=' . $userFilter : ''; ?><?php echo $statusFilter ? '&status_filter=' . $statusFilter : ''; ?>">
                <?php echo $i; ?>
            </a>
        </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

<?php else: ?>
<div class="row">
    <div class="col-12">
        <div class="chart-card text-center py-5">
            <i class="fas fa-list-music fa-4x text-muted mb-3"></i>
            <h5 class="text-muted">Aucune playlist trouvée</h5>
            <p class="text-muted">Aucune playlist ne correspond à vos critères de recherche.</p>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPlaylistModal">
                Créer la première playlist
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Top créateurs de playlists -->
<?php if (!empty($topPlaylistCreators)): ?>
<div class="row">
    <div class="col-12">
        <div class="chart-card">
            <h6 class="mb-4">
                <i class="fas fa-crown me-2 text-warning"></i>
                Top Créateurs de Playlists
            </h6>
            
            <div class="row">
                <?php foreach ($topPlaylistCreators as $index => $creator): ?>
                <div class="col-md-2 col-6 mb-3">
                    <div class="text-center">
                        <div class="creator-rank-badge mb-2">
                            <span class="rank-position"><?php echo $index + 1; ?></span>
                        </div>
                        <div class="creator-info">
                            <strong><?php echo htmlspecialchars($creator['first_name'] . ' ' . $creator['last_name']); ?></strong>
                            <br>
                            <small class="text-muted">@<?php echo htmlspecialchars($creator['username']); ?></small>
                            <br>
                            <span class="badge bg-success"><?php echo $creator['playlist_count']; ?> playlists</span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
.playlist-card {
    transition: all 0.3s ease;
    cursor: pointer;
    border: 1px solid rgba(0, 0, 0, 0.1);
}

.playlist-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

.playlist-title {
    font-weight: 600;
    color: var(--secondary-color);
}

.playlist-cover {
    position: relative;
    overflow: hidden;
    border-radius: 10px;
}

.playlist-placeholder {
    width: 100%;
    height: 150px;
    background: linear-gradient(135deg, var(--success-color), #20c997);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.playlist-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.playlist-cover:hover .playlist-overlay {
    opacity: 1;
}

.creator-rank-badge {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--warning-color), #e0a800);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
    color: var(--dark-color);
    font-weight: bold;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.rank-position {
    font-size: 1.2rem;
    font-weight: 700;
}

.playlist-description {
    border-left: 3px solid var(--success-color);
    padding-left: 0.75rem;
}

.stat-mini {
    padding: 0.5rem 0;
}

.stat-number-mini {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--accent-color);
}
</style>

<script>
// Fonctions de gestion des playlists
function playPlaylist(playlistId) {
    // Simuler la lecture d'une playlist
    fetch(`../api/playlist.php?action=play&id=${playlistId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Lecture de la playlist:', data.playlist);
                // Intégrer avec le lecteur audio
            }
        });
}

function viewPlaylist(playlistId) {
    fetch(`../api/playlist.php?action=get&id=${playlistId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showPlaylistDetails(data.playlist);
            }
        });
}

function editPlaylist(playlistId) {
    const modal = new bootstrap.Modal(document.getElementById('editPlaylistModal'));
    modal.show();
    loadPlaylistForEdit(playlistId);
}

function deletePlaylist(playlistId) {
    if (confirm('Êtes-vous sûr de vouloir supprimer cette playlist ?')) {
        fetch(`../api/playlist.php?action=delete&id=${playlistId}`, {
            method: 'DELETE'
        })
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

function managePlaylistTracks(playlistId) {
    // Rediriger vers la gestion des pistes de la playlist
    window.location.href = `?tab=music&action=manage_tracks&playlist_id=${playlistId}`;
}

function filterByStatus(status) {
    const currentUrl = new URL(window.location);
    if (status) {
        currentUrl.searchParams.set('status_filter', status);
    } else {
        currentUrl.searchParams.delete('status_filter');
    }
    currentUrl.searchParams.set('page', '1');
    window.location.href = currentUrl.toString();
}

function filterByUser(userId) {
    const currentUrl = new URL(window.location);
    if (userId) {
        currentUrl.searchParams.set('user_filter', userId);
    } else {
        currentUrl.searchParams.delete('user_filter');
    }
    currentUrl.searchParams.set('page', '1');
    window.location.href = currentUrl.toString();
}

function sortPlaylists(sortBy) {
    const currentUrl = new URL(window.location);
    currentUrl.searchParams.set('sort', sortBy);
    currentUrl.searchParams.set('page', '1');
    window.location.href = currentUrl.toString();
}

function showPlaylistDetails(playlist) {
    // Créer et afficher un modal avec les détails de la playlist
    const modalHtml = `
        <div class="modal fade" id="playlistDetailsModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title">Détails - ${playlist.name}</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-4 text-center">
                                <div class="playlist-cover-large mb-3">
                                    <i class="fas fa-list-music fa-4x text-success"></i>
                                </div>
                                <h5>${playlist.name}</h5>
                                <p class="text-muted">par ${playlist.creator_name}</p>
                                <span class="badge ${playlist.is_public ? 'bg-primary' : 'bg-warning text-dark'}">
                                    ${playlist.is_public ? 'Public' : 'Privé'}
                                </span>
                            </div>
                            <div class="col-md-8">
                                <h6>Description</h6>
                                <p>${playlist.description || 'Aucune description'}</p>
                                
                                <h6>Statistiques</h6>
                                <div class="row">
                                    <div class="col-6">
                                        <strong>Pistes:</strong> ${playlist.track_count}
                                    </div>
                                    <div class="col-6">
                                        <strong>Durée:</strong> ${playlist.total_duration}
                                    </div>
                                </div>
                                
                                <h6 class="mt-3">Date de création</h6>
                                <p>${new Date(playlist.created_at).toLocaleDateString()}</p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                        <button type="button" class="btn btn-primary" onclick="editPlaylist(${playlist.id})">
                            <i class="fas fa-edit me-2"></i>Modifier
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Supprimer l'ancien modal s'il existe
    const existingModal = document.getElementById('playlistDetailsModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Ajouter le nouveau modal
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const modal = new bootstrap.Modal(document.getElementById('playlistDetailsModal'));
    modal.show();
}
</script>