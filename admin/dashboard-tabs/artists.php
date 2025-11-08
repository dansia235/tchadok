<?php
// Gestion des artistes
if ($dbConnected) {
    // Récupération des artistes avec leurs statistiques
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
    
    // Statistiques artistes
    $artistStats = [
        'total' => $pdo->query("SELECT COUNT(*) FROM artists")->fetchColumn(),
        'verified' => $pdo->query("SELECT COUNT(*) FROM artists WHERE verified = 1")->fetchColumn(),
        'active' => $pdo->query("SELECT COUNT(*) FROM artists WHERE is_active = 1")->fetchColumn(),
        'featured' => $pdo->query("SELECT COUNT(*) FROM artists WHERE featured = 1")->fetchColumn(),
    ];
    
    // Top genres
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

<div class="row mb-4">
    <div class="col-12">
        <h2 class="mb-0 d-flex align-items-center">
            <i class="fas fa-microphone me-3 text-warning"></i>
            Gestion des Artistes
            <span class="badge bg-warning text-dark ms-3"><?php echo number_format($artistStats['total'] ?? 0); ?> artistes</span>
        </h2>
        <p class="text-muted">Gérer tous les artistes et leur contenu musical</p>
    </div>
</div>

<!-- Statistiques artistes -->
<div class="row g-4 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card text-center">
            <i class="fas fa-microphone fa-2x text-warning mb-3"></i>
            <div class="stat-number"><?php echo number_format($artistStats['total'] ?? 0); ?></div>
            <h6 class="text-muted mb-0">Total Artistes</h6>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card text-center">
            <i class="fas fa-certificate fa-2x text-primary mb-3"></i>
            <div class="stat-number"><?php echo number_format($artistStats['verified'] ?? 0); ?></div>
            <h6 class="text-muted mb-0">Vérifiés</h6>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card text-center">
            <i class="fas fa-star fa-2x text-info mb-3"></i>
            <div class="stat-number"><?php echo number_format($artistStats['featured'] ?? 0); ?></div>
            <h6 class="text-muted mb-0">En Vedette</h6>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card text-center">
            <i class="fas fa-music fa-2x text-success mb-3"></i>
            <div class="stat-number"><?php echo number_format($artistStats['active'] ?? 0); ?></div>
            <h6 class="text-muted mb-0">Actifs</h6>
        </div>
    </div>
</div>

<!-- Barre d'outils -->
<div class="row mb-4">
    <div class="col-12">
        <div class="chart-card">
            <div class="row align-items-center">
                <div class="col-md-4">
                    <form method="GET" class="d-flex">
                        <input type="hidden" name="tab" value="artists">
                        <div class="input-group">
                            <input type="text" class="form-control" name="search" placeholder="Rechercher un artiste..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                            <button class="btn btn-admin" type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>
                </div>
                <div class="col-md-4">
                    <!-- Filtres -->
                    <select class="form-select" onchange="filterArtists(this.value)">
                        <option value="">Tous les artistes</option>
                        <option value="verified">Vérifiés seulement</option>
                        <option value="featured">En vedette</option>
                        <option value="top">Top streams</option>
                    </select>
                </div>
                <div class="col-md-4 text-end">
                    <button class="btn btn-success-admin" data-bs-toggle="modal" data-bs-target="#addArtistModal">
                        <i class="fas fa-microphone-alt me-2"></i>
                        Nouvel Artiste
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Grille des artistes -->
<?php if (!empty($artists)): ?>
<div class="row g-4 mb-4">
    <?php foreach ($artists as $artist): ?>
    <div class="col-xl-3 col-lg-4 col-md-6">
        <div class="chart-card artist-card" style="position: relative;">
            <!-- Badge de statut -->
            <div class="position-absolute top-0 end-0 p-2">
                <?php if ($artist['verified']): ?>
                    <span class="badge bg-primary">
                        <i class="fas fa-check-circle"></i> Vérifié
                    </span>
                <?php endif; ?>
                <?php if ($artist['featured']): ?>
                    <span class="badge bg-warning text-dark">
                        <i class="fas fa-star"></i> Vedette
                    </span>
                <?php endif; ?>
            </div>
            
            <!-- Avatar de l'artiste -->
            <div class="text-center mb-3">
                <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ccircle cx='50' cy='50' r='45' fill='%23<?php echo substr(md5($artist['stage_name']), 0, 6); ?>'/%3E%3Ctext x='50' y='60' text-anchor='middle' font-size='20' fill='white'%3E<?php echo strtoupper(substr($artist['stage_name'], 0, 2)); ?>%3C/text%3E%3C/svg%3E" 
                     width="80" height="80" class="rounded-circle">
            </div>
            
            <!-- Informations de l'artiste -->
            <div class="text-center">
                <h6 class="mb-1"><?php echo htmlspecialchars($artist['stage_name']); ?></h6>
                <?php if ($artist['real_name']): ?>
                <small class="text-muted d-block"><?php echo htmlspecialchars($artist['real_name']); ?></small>
                <?php endif; ?>
                
                <!-- Genre -->
                <?php if ($artist['genres']): ?>
                <span class="badge bg-secondary mt-2"><?php echo htmlspecialchars($artist['genres']); ?></span>
                <?php endif; ?>
            </div>
            
            <!-- Statistiques -->
            <div class="row text-center mt-3">
                <div class="col-4">
                    <div class="stat-mini">
                        <div class="stat-number-mini"><?php echo $artist['album_count']; ?></div>
                        <small class="text-muted">Albums</small>
                    </div>
                </div>
                <div class="col-4">
                    <div class="stat-mini">
                        <div class="stat-number-mini"><?php echo $artist['track_count']; ?></div>
                        <small class="text-muted">Pistes</small>
                    </div>
                </div>
                <div class="col-4">
                    <div class="stat-mini">
                        <div class="stat-number-mini"><?php echo number_format($artist['total_streams']); ?></div>
                        <small class="text-muted">Écoutes</small>
                    </div>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="text-center mt-3">
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-primary" onclick="viewArtist(<?php echo $artist['id']; ?>)" title="Voir profil">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn btn-outline-warning" onclick="editArtist(<?php echo $artist['id']; ?>)" title="Modifier">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-outline-success" onclick="manageMusic(<?php echo $artist['id']; ?>)" title="Gérer musique">
                        <i class="fas fa-music"></i>
                    </button>
                    <button class="btn btn-outline-info" onclick="viewStats(<?php echo $artist['id']; ?>)" title="Statistiques">
                        <i class="fas fa-chart-bar"></i>
                    </button>
                </div>
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
            <a class="page-link" href="?tab=artists&page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>">
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
            <i class="fas fa-microphone fa-4x text-muted mb-3"></i>
            <h5 class="text-muted">Aucun artiste trouvé</h5>
            <p class="text-muted">Aucun artiste ne correspond à vos critères de recherche.</p>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addArtistModal">
                Ajouter le premier artiste
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Genres populaires -->
<div class="row">
    <div class="col-12">
        <div class="chart-card">
            <h6 class="mb-4">
                <i class="fas fa-tags me-2 text-success"></i>
                Genres Populaires
            </h6>
            
            <?php if (!empty($topGenres)): ?>
            <div class="row">
                <?php foreach ($topGenres as $genre): ?>
                <div class="col-md-2 col-6 mb-3">
                    <div class="text-center">
                        <div class="genre-circle mb-2" style="background: linear-gradient(135deg, #<?php echo substr(md5($genre['genres']), 0, 6); ?>, #<?php echo substr(md5($genre['genres']), 6, 6); ?>);">
                            <span class="genre-count"><?php echo $genre['count']; ?></span>
                        </div>
                        <small class="text-muted"><?php echo htmlspecialchars($genre['genres']); ?></small>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="text-muted">Aucun genre disponible</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.artist-card {
    transition: all 0.3s ease;
    cursor: pointer;
}

.artist-card:hover {
    transform: translateY(-5px);
}

.stat-mini {
    padding: 0.5rem 0;
}

.stat-number-mini {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--accent-color);
}

.genre-circle {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.genre-count {
    font-size: 1.2rem;
    font-weight: 700;
    color: white;
    text-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
}
</style>

<script>
function viewArtist(artistId) {
    // Afficher le profil complet de l'artiste
    fetch(`../api/artist.php?action=get&id=${artistId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showArtistProfile(data.artist);
            }
        });
}

function editArtist(artistId) {
    // Ouvrir le modal d'édition
    const modal = new bootstrap.Modal(document.getElementById('editArtistModal'));
    modal.show();
    loadArtistForEdit(artistId);
}

function manageMusic(artistId) {
    // Rediriger vers la gestion de la musique
    window.location.href = `?tab=music&artist=${artistId}`;
}

function viewStats(artistId) {
    // Afficher les statistiques détaillées
    fetch(`../api/artist.php?action=stats&id=${artistId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showArtistStats(data.stats);
            }
        });
}

function filterArtists(filter) {
    const currentUrl = new URL(window.location);
    if (filter) {
        currentUrl.searchParams.set('filter', filter);
    } else {
        currentUrl.searchParams.delete('filter');
    }
    currentUrl.searchParams.set('page', '1');
    window.location.href = currentUrl.toString();
}

function showArtistProfile(artist) {
    // Créer et afficher un modal avec le profil complet
    const modalHtml = `
        <div class="modal fade" id="artistProfileModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Profil - ${artist.stage_name}</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-4 text-center">
                                <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ccircle cx='50' cy='50' r='45' fill='%23${artist.stage_name.substring(0,6)}'/%3E%3Ctext x='50' y='60' text-anchor='middle' font-size='20' fill='white'%3E${artist.stage_name.substring(0,2).toUpperCase()}%3C/text%3E%3C/svg%3E" 
                                     width="120" height="120" class="rounded-circle mb-3">
                                <h5>${artist.stage_name}</h5>
                                <p class="text-muted">${artist.real_name || ''}</p>
                            </div>
                            <div class="col-md-8">
                                <h6>Informations</h6>
                                <p><strong>Genre:</strong> ${artist.genres || 'N/A'}</p>
                                <p><strong>Bio:</strong> ${artist.bio || 'Aucune biographie'}</p>
                                <p><strong>Membre depuis:</strong> ${new Date(artist.created_at).toLocaleDateString()}</p>
                                
                                <h6 class="mt-4">Réseaux sociaux</h6>
                                <div class="d-flex gap-2">
                                    ${artist.website ? `<a href="${artist.website}" class="btn btn-sm btn-outline-primary" target="_blank"><i class="fas fa-globe"></i></a>` : ''}
                                    ${artist.facebook ? `<a href="${artist.facebook}" class="btn btn-sm btn-outline-primary" target="_blank"><i class="fab fa-facebook"></i></a>` : ''}
                                    ${artist.instagram ? `<a href="${artist.instagram}" class="btn btn-sm btn-outline-primary" target="_blank"><i class="fab fa-instagram"></i></a>` : ''}
                                    ${artist.twitter ? `<a href="${artist.twitter}" class="btn btn-sm btn-outline-primary" target="_blank"><i class="fab fa-twitter"></i></a>` : ''}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Supprimer l'ancien modal s'il existe
    const existingModal = document.getElementById('artistProfileModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Ajouter le nouveau modal
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const modal = new bootstrap.Modal(document.getElementById('artistProfileModal'));
    modal.show();
}
</script>