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
        // Gestion des pistes
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
        // Gestion des albums
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
        // Gestion des playlists
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
    
    // Liste des artistes pour les filtres
    $artists = $pdo->query("SELECT id, stage_name FROM artists ORDER BY stage_name")->fetchAll();
    
    // Statistiques musicales
    $musicStats = [
        'total_tracks' => $pdo->query("SELECT COUNT(*) FROM tracks")->fetchColumn(),
        'total_albums' => $pdo->query("SELECT COUNT(*) FROM albums")->fetchColumn(),
        'total_streams' => $pdo->query("SELECT COALESCE(SUM(total_streams), 0) FROM tracks")->fetchColumn(),
        'avg_duration' => $pdo->query("SELECT COALESCE(AVG(duration), 0) FROM tracks")->fetchColumn(),
    ];
}
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="mb-0 d-flex align-items-center">
            <i class="fas fa-music me-3 text-info"></i>
            Gestion de la Musique
            <span class="badge bg-info ms-3"><?php echo number_format($musicStats['total_tracks'] ?? 0); ?> pistes</span>
        </h2>
        <p class="text-muted">Gérer tous les contenus musicaux de la plateforme</p>
    </div>
</div>

<!-- Statistiques musicales -->
<div class="row g-4 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card text-center">
            <i class="fas fa-music fa-2x text-primary mb-3"></i>
            <div class="stat-number"><?php echo number_format($musicStats['total_tracks'] ?? 0); ?></div>
            <h6 class="text-muted mb-0">Pistes Totales</h6>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card text-center">
            <i class="fas fa-compact-disc fa-2x text-success mb-3"></i>
            <div class="stat-number"><?php echo number_format($musicStats['total_albums'] ?? 0); ?></div>
            <h6 class="text-muted mb-0">Albums Totaux</h6>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card text-center">
            <i class="fas fa-play fa-2x text-warning mb-3"></i>
            <div class="stat-number"><?php echo number_format($musicStats['total_streams'] ?? 0); ?></div>
            <h6 class="text-muted mb-0">Écoutes Totales</h6>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card text-center">
            <i class="fas fa-clock fa-2x text-info mb-3"></i>
            <div class="stat-number"><?php echo gmdate("i:s", $musicStats['avg_duration'] ?? 0); ?></div>
            <h6 class="text-muted mb-0">Durée Moyenne</h6>
        </div>
    </div>
</div>

<!-- Onglets de navigation -->
<div class="row mb-4">
    <div class="col-12">
        <div class="chart-card">
            <ul class="nav nav-tabs mb-4">
                <li class="nav-item">
                    <a class="nav-link <?php echo $activeTab === 'tracks' ? 'active' : ''; ?>" 
                       href="?tab=music&music_tab=tracks">
                        <i class="fas fa-music me-2"></i>Pistes
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $activeTab === 'albums' ? 'active' : ''; ?>" 
                       href="?tab=music&music_tab=albums">
                        <i class="fas fa-compact-disc me-2"></i>Albums
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $activeTab === 'playlists' ? 'active' : ''; ?>" 
                       href="?tab=music&music_tab=playlists">
                        <i class="fas fa-list-music me-2"></i>Playlists
                    </a>
                </li>
            </ul>
            
            <!-- Barre d'outils -->
            <div class="row align-items-center mb-4">
                <div class="col-md-3">
                    <form method="GET" class="d-flex">
                        <input type="hidden" name="tab" value="music">
                        <input type="hidden" name="music_tab" value="<?php echo $activeTab; ?>">
                        <?php if ($artistFilter): ?>
                            <input type="hidden" name="artist" value="<?php echo $artistFilter; ?>">
                        <?php endif; ?>
                        <div class="input-group">
                            <input type="text" class="form-control" name="search" placeholder="Rechercher..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                            <button class="btn btn-admin" type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>
                </div>
                <div class="col-md-3">
                    <select class="form-select" onchange="filterByArtist(this.value)">
                        <option value="">Tous les artistes</option>
                        <?php foreach ($artists as $artist): ?>
                            <option value="<?php echo $artist['id']; ?>" 
                                    <?php echo $artistFilter == $artist['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($artist['stage_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" onchange="sortMusic(this.value)">
                        <option value="recent">Plus récents</option>
                        <option value="popular">Plus populaires</option>
                        <option value="alphabetical">Alphabétique</option>
                        <option value="duration">Par durée</option>
                    </select>
                </div>
                <div class="col-md-3 text-end">
                    <div class="btn-group">
                        <button class="btn btn-success-admin" data-bs-toggle="modal" data-bs-target="#add<?php echo ucfirst($activeTab); ?>Modal">
                            <i class="fas fa-plus me-2"></i>
                            Ajouter <?php echo $activeTab === 'tracks' ? 'Piste' : 'Album'; ?>
                        </button>
                        <button class="btn btn-warning-admin" data-bs-toggle="modal" data-bs-target="#bulkMusicModal">
                            <i class="fas fa-tasks me-2"></i>
                            Actions Groupées
                        </button>
                    </div>
                </div>
            </div>
            
            <?php if ($activeTab === 'tracks'): ?>
                <!-- Contenu Pistes -->
                <?php if (!empty($tracks)): ?>
                <div class="table-responsive">
                    <table class="table table-custom table-hover">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="selectAllTracks"></th>
                                <th>Piste</th>
                                <th>Artiste</th>
                                <th>Album</th>
                                <th>Durée</th>
                                <th>Écoutes</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tracks as $track): ?>
                            <tr class="music-item" data-id="<?php echo $track['id']; ?>">
                                <td><input type="checkbox" class="track-checkbox" value="<?php echo $track['id']; ?>"></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="track-cover me-3">
                                            <div class="music-placeholder">
                                                <i class="fas fa-music"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($track['title']); ?></strong>
                                            <?php if ($track['is_featured']): ?>
                                                <i class="fas fa-star text-warning ms-1" title="En vedette"></i>
                                            <?php endif; ?>
                                            <br>
                                            <small class="text-muted">ID: <?php echo $track['id']; ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="artist-name"><?php echo htmlspecialchars($track['stage_name']); ?></span>
                                </td>
                                <td>
                                    <?php if ($track['album_title']): ?>
                                        <span class="album-name"><?php echo htmlspecialchars($track['album_title']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">Single</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="duration"><?php echo gmdate("i:s", $track['duration'] ?? 0); ?></span>
                                </td>
                                <td>
                                    <span class="streams-count"><?php echo number_format($track['streams']); ?></span>
                                    <br>
                                    <small class="text-muted"><?php echo number_format($track['downloads']); ?> téléchargements</small>
                                </td>
                                <td>
                                    <span class="badge <?php echo getStatusBadgeClass($track['status']); ?>">
                                        <?php echo ucfirst($track['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary" onclick="playTrack(<?php echo $track['id']; ?>)" title="Écouter">
                                            <i class="fas fa-play"></i>
                                        </button>
                                        <button class="btn btn-outline-info" onclick="viewTrackStats(<?php echo $track['id']; ?>)" title="Statistiques">
                                            <i class="fas fa-chart-bar"></i>
                                        </button>
                                        <button class="btn btn-outline-warning" onclick="editTrack(<?php echo $track['id']; ?>)" title="Modifier">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-outline-danger" onclick="deleteTrack(<?php echo $track['id']; ?>)" title="Supprimer">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-music fa-4x text-muted mb-3"></i>
                    <h5 class="text-muted">Aucune piste trouvée</h5>
                    <p class="text-muted">Aucune piste ne correspond à vos critères.</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTracksModal">
                        Ajouter la première piste
                    </button>
                </div>
                <?php endif; ?>
                
            <?php elseif ($activeTab === 'albums'): ?>
                <!-- Contenu Albums -->
                <?php if (!empty($albums)): ?>
                <div class="row g-4">
                    <?php foreach ($albums as $album): ?>
                    <div class="col-xl-3 col-lg-4 col-md-6">
                        <div class="album-card chart-card">
                            <div class="album-cover">
                                <div class="music-placeholder-large">
                                    <i class="fas fa-compact-disc fa-3x"></i>
                                </div>
                                <div class="album-overlay">
                                    <button class="btn btn-primary btn-sm" onclick="playAlbum(<?php echo $album['id']; ?>)">
                                        <i class="fas fa-play"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="album-info mt-3">
                                <h6 class="album-title"><?php echo htmlspecialchars($album['title']); ?></h6>
                                <p class="artist-name text-muted mb-2"><?php echo htmlspecialchars($album['stage_name']); ?></p>
                                
                                <div class="album-meta">
                                    <span class="badge <?php echo getTypeBadgeClass($album['type']); ?>">
                                        <?php echo strtoupper($album['type']); ?>
                                    </span>
                                    <span class="badge bg-secondary ms-1">
                                        <?php echo $album['track_count']; ?> pistes
                                    </span>
                                </div>
                                
                                <div class="album-stats mt-2">
                                    <small class="text-muted">
                                        <i class="fas fa-play me-1"></i><?php echo number_format($album['streams']); ?> écoutes
                                        <i class="fas fa-download ms-2 me-1"></i><?php echo number_format($album['sales']); ?> ventes
                                    </small>
                                </div>
                                
                                <div class="album-actions mt-3">
                                    <div class="btn-group btn-group-sm w-100">
                                        <button class="btn btn-outline-primary" onclick="viewAlbum(<?php echo $album['id']; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-outline-warning" onclick="editAlbum(<?php echo $album['id']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-outline-info" onclick="manageAlbumTracks(<?php echo $album['id']; ?>)">
                                            <i class="fas fa-list"></i>
                                        </button>
                                        <button class="btn btn-outline-danger" onclick="deleteAlbum(<?php echo $album['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-compact-disc fa-4x text-muted mb-3"></i>
                    <h5 class="text-muted">Aucun album trouvé</h5>
                    <p class="text-muted">Aucun album ne correspond à vos critères.</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAlbumsModal">
                        Ajouter le premier album
                    </button>
                </div>
                <?php endif; ?>
                
            <?php else: ?>
                <!-- Contenu Playlists -->
                <?php if (!empty($playlists)): ?>
                <div class="row g-4">
                    <?php foreach ($playlists as $playlist): ?>
                    <div class="col-xl-4 col-lg-6">
                        <div class="chart-card playlist-card">
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
                            
                            <?php if ($playlist['description']): ?>
                            <div class="playlist-description mb-3">
                                <p class="text-muted small"><?php echo htmlspecialchars(substr($playlist['description'], 0, 100)); ?><?php echo strlen($playlist['description']) > 100 ? '...' : ''; ?></p>
                            </div>
                            <?php endif; ?>
                            
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
                                    <button class="btn btn-outline-danger" onclick="deletePlaylist(<?php echo $playlist['id']; ?>)" title="Supprimer">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                            
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
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-list-music fa-4x text-muted mb-3"></i>
                    <h5 class="text-muted">Aucune playlist trouvée</h5>
                    <p class="text-muted">Aucune playlist ne correspond à vos critères.</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPlaylistModal">
                        Créer la première playlist
                    </button>
                </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?tab=music&music_tab=<?php echo $activeTab; ?>&page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?><?php echo $artistFilter ? '&artist=' . $artistFilter : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Lecteur Audio Flottant -->
<div id="audioPlayer" class="audio-player-floating" style="display: none;">
    <div class="player-content">
        <div class="track-info">
            <div class="track-cover-mini">
                <i class="fas fa-music"></i>
            </div>
            <div class="track-details">
                <div class="track-title-mini">Titre de la piste</div>
                <div class="artist-name-mini">Nom de l'artiste</div>
            </div>
        </div>
        <div class="player-controls">
            <button class="btn btn-sm btn-outline-light" id="prevBtn">
                <i class="fas fa-step-backward"></i>
            </button>
            <button class="btn btn-sm btn-light" id="playPauseBtn">
                <i class="fas fa-play"></i>
            </button>
            <button class="btn btn-sm btn-outline-light" id="nextBtn">
                <i class="fas fa-step-forward"></i>
            </button>
        </div>
        <div class="player-progress">
            <div class="progress">
                <div class="progress-bar" style="width: 0%"></div>
            </div>
            <div class="time-display">
                <span class="current-time">0:00</span>
                <span class="total-time">0:00</span>
            </div>
        </div>
        <button class="btn btn-sm btn-outline-light" id="closePlayer">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <audio id="audioElement"></audio>
</div>

<style>
.music-placeholder {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.music-placeholder-large {
    width: 100%;
    height: 200px;
    background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    position: relative;
}

.album-card {
    transition: all 0.3s ease;
    cursor: pointer;
}

.album-card:hover {
    transform: translateY(-5px);
}

.album-cover {
    position: relative;
    overflow: hidden;
    border-radius: 15px;
}

.album-overlay {
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

.album-cover:hover .album-overlay {
    opacity: 1;
}

.album-title {
    font-weight: 600;
    color: var(--secondary-color);
    margin-bottom: 0.5rem;
}

.artist-name {
    color: var(--accent-color);
    font-weight: 500;
}

.streams-count {
    font-weight: 600;
    color: var(--success-color);
}

.duration {
    font-family: monospace;
    font-weight: 600;
}

.audio-player-floating {
    position: fixed;
    bottom: 20px;
    right: 20px;
    background: linear-gradient(135deg, var(--secondary-color), var(--accent-color));
    color: white;
    border-radius: 15px;
    padding: 1rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    z-index: 1000;
    min-width: 400px;
}

.player-content {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.track-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex: 1;
}

.track-cover-mini {
    width: 40px;
    height: 40px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.track-title-mini {
    font-weight: 600;
    font-size: 0.9rem;
}

.artist-name-mini {
    font-size: 0.8rem;
    opacity: 0.8;
}

.player-controls {
    display: flex;
    gap: 0.5rem;
}

.player-progress {
    flex: 1;
    margin: 0 1rem;
}

.time-display {
    display: flex;
    justify-content: space-between;
    font-size: 0.8rem;
    margin-top: 0.25rem;
}

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

.playlist-placeholder {
    width: 100%;
    height: 150px;
    background: linear-gradient(135deg, var(--success-color), #20c997);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    border-radius: 10px;
    position: relative;
    overflow: hidden;
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

.playlist-placeholder:hover .playlist-overlay {
    opacity: 1;
}

.playlist-description {
    border-left: 3px solid var(--success-color);
    padding-left: 0.75rem;
}
</style>

<script>
// Fonctions de gestion de la musique
function playTrack(trackId) {
    // Simuler la lecture d'une piste
    fetch(`../api/track.php?action=get&id=${trackId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAudioPlayer(data.track);
            }
        });
}

function showAudioPlayer(track) {
    const player = document.getElementById('audioPlayer');
    const titleElement = player.querySelector('.track-title-mini');
    const artistElement = player.querySelector('.artist-name-mini');
    
    titleElement.textContent = track.title;
    artistElement.textContent = track.artist;
    
    player.style.display = 'block';
    
    // Animation d'apparition
    setTimeout(() => {
        player.style.transform = 'translateY(0)';
        player.style.opacity = '1';
    }, 100);
}

function editTrack(trackId) {
    const modal = new bootstrap.Modal(document.getElementById('editTrackModal'));
    modal.show();
    loadTrackForEdit(trackId);
}

function deleteTrack(trackId) {
    if (confirm('Êtes-vous sûr de vouloir supprimer cette piste ?')) {
        fetch(`../api/track.php?action=delete&id=${trackId}`, {
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

function viewTrackStats(trackId) {
    fetch(`../api/track.php?action=stats&id=${trackId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showTrackStatsModal(data.stats);
            }
        });
}

function filterByArtist(artistId) {
    const currentUrl = new URL(window.location);
    if (artistId) {
        currentUrl.searchParams.set('artist', artistId);
    } else {
        currentUrl.searchParams.delete('artist');
    }
    currentUrl.searchParams.set('page', '1');
    window.location.href = currentUrl.toString();
}

function sortMusic(sortBy) {
    const currentUrl = new URL(window.location);
    currentUrl.searchParams.set('sort', sortBy);
    currentUrl.searchParams.set('page', '1');
    window.location.href = currentUrl.toString();
}

// Lecteur audio
document.getElementById('closePlayer').addEventListener('click', function() {
    document.getElementById('audioPlayer').style.display = 'none';
});

document.getElementById('playPauseBtn').addEventListener('click', function() {
    const icon = this.querySelector('i');
    if (icon.classList.contains('fa-play')) {
        icon.classList.remove('fa-play');
        icon.classList.add('fa-pause');
    } else {
        icon.classList.remove('fa-pause');
        icon.classList.add('fa-play');
    }
});

// Sélection multiple
document.getElementById('selectAllTracks')?.addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.track-checkbox');
    checkboxes.forEach(cb => cb.checked = this.checked);
});

// Fonctions de gestion des playlists
function playPlaylist(playlistId) {
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
    window.location.href = `?tab=music&action=manage_tracks&playlist_id=${playlistId}`;
}
</script>

<?php
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'approved': return 'bg-success';
        case 'pending': return 'bg-warning text-dark';
        case 'draft': return 'bg-secondary';
        case 'rejected': return 'bg-danger';
        default: return 'bg-secondary';
    }
}

function getTypeBadgeClass($type) {
    switch ($type) {
        case 'album': return 'bg-primary';
        case 'ep': return 'bg-info text-dark';
        case 'single': return 'bg-success';
        case 'maxi_single': return 'bg-warning text-dark';
        default: return 'bg-secondary';
    }
}
?>