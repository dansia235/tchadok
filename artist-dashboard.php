<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';

if (!isLoggedIn() || !isArtist()) {
    header('Location: ' . SITE_URL . '/login.php');
    exit();
}

$pageTitle = 'Dashboard Artiste';
$user = getCurrentUser();
$dbInstance = TchadokDatabase::getInstance();
$db = $dbInstance->getConnection();
$userId = $_SESSION['user_id'];

// Récupérer l'artiste
$stmt = $db->prepare("SELECT * FROM artists WHERE user_id = ?");
$stmt->execute([$userId]);
$artist = $stmt->fetch();

if (!$artist) {
    die('Profil artiste non trouvé');
}

// Stats artiste
$artistId = $artist['id'];
$stats = [
    'total_songs' => $db->query("SELECT COUNT(*) FROM songs WHERE artist_id = $artistId")->fetchColumn(),
    'total_albums' => $db->query("SELECT COUNT(*) FROM albums WHERE artist_id = $artistId")->fetchColumn(),
    'total_plays' => $db->query("SELECT COALESCE(SUM(play_count), 0) FROM songs WHERE artist_id = $artistId")->fetchColumn(),
    'followers' => $db->query("SELECT COUNT(*) FROM artist_followers WHERE artist_id = $artistId")->fetchColumn(),
    'revenue' => 0, // À calculer selon le modèle de revenu
];

// Top chansons de l'artiste
$top_songs = $db->query("SELECT id, title, play_count, is_premium FROM songs WHERE artist_id = $artistId ORDER BY play_count DESC LIMIT 10")->fetchAll();

// Albums
$albums = $db->query("SELECT * FROM albums WHERE artist_id = $artistId ORDER BY release_date DESC LIMIT 5")->fetchAll();

include 'includes/header.php';
?>
<div class="artist-dashboard-container">
    <section class="artist-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="d-flex align-items-center">
                        <div class="artist-avatar-large">
                            <?php echo strtoupper(substr($artist['stage_name'], 0, 2)); ?>
                        </div>
                        <div>
                            <h1 class="mb-1"><?php echo htmlspecialchars($artist['stage_name']); ?></h1>
                            <p class="text-muted mb-0"><i class="fas fa-microphone-alt me-2"></i>Artiste depuis <?php echo date('Y', strtotime($artist['created_at'])); ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 text-lg-end mt-3 mt-lg-0">
                    <a href="<?php echo SITE_URL; ?>/artist-add-song.php" class="btn btn-primary"><i class="fas fa-music me-2"></i>Ajouter Chanson</a>
                    <a href="<?php echo SITE_URL; ?>/artist-add-album.php" class="btn btn-success"><i class="fas fa-compact-disc me-2"></i>Ajouter Album</a>
                </div>
            </div>
        </div>
    </section>
    
    <section class="artist-stats py-4">
        <div class="container-fluid">
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-icon" style="background: linear-gradient(135deg, #9C27B0, #7B1FA2);"><i class="fas fa-music"></i></div><div class="stat-content"><div class="stat-label">Chansons</div><div class="stat-value"><?php echo number_format($stats['total_songs']); ?></div></div></div>
                <div class="stat-card"><div class="stat-icon" style="background: linear-gradient(135deg, #0066CC, #0052a3);"><i class="fas fa-compact-disc"></i></div><div class="stat-content"><div class="stat-label">Albums</div><div class="stat-value"><?php echo number_format($stats['total_albums']); ?></div></div></div>
                <div class="stat-card"><div class="stat-icon" style="background: linear-gradient(135deg, #FF9800, #F57C00);"><i class="fas fa-headphones"></i></div><div class="stat-content"><div class="stat-label">Total Écoutes</div><div class="stat-value"><?php echo number_format($stats['total_plays']); ?></div></div></div>
                <div class="stat-card"><div class="stat-icon" style="background: linear-gradient(135deg, #228B22, #1a6b1a);"><i class="fas fa-users"></i></div><div class="stat-content"><div class="stat-label">Abonnés</div><div class="stat-value"><?php echo number_format($stats['followers']); ?></div></div></div>
                <div class="stat-card"><div class="stat-icon" style="background: linear-gradient(135deg, #FFD700, #FFC700); color: #2C3E50;"><i class="fas fa-money-bill-wave"></i></div><div class="stat-content"><div class="stat-label">Revenus Estimés</div><div class="stat-value"><?php echo number_format($stats['revenue'], 0, ',', ' '); ?> <small>XAF</small></div></div></div>
            </div>
        </div>
    </section>
    
    <section class="artist-content py-4">
        <div class="container-fluid">
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="content-card mb-4">
                        <div class="card-header-custom"><h5><i class="fas fa-fire me-2"></i>Mes Meilleures Chansons</h5></div>
                        <div class="card-body-custom">
                            <table class="table table-hover">
                                <thead><tr><th>#</th><th>Titre</th><th>Écoutes</th><th>Type</th><th>Actions</th></tr></thead>
                                <tbody>
                                    <?php foreach ($top_songs as $i => $s): ?>
                                    <tr>
                                        <td><?php echo $i + 1; ?></td>
                                        <td><?php echo htmlspecialchars($s['title']); ?></td>
                                        <td><span class="badge bg-primary"><?php echo number_format($s['play_count']); ?> <i class="fas fa-play ms-1"></i></span></td>
                                        <td><?php echo $s['is_premium'] ? '<span class="badge bg-warning text-dark"><i class="fas fa-star"></i> Premium</span>' : '<span class="badge bg-success">Gratuit</span>'; ?></td>
                                        <td><button class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></button></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div class="content-card">
                        <div class="card-header-custom"><h5><i class="fas fa-compact-disc me-2"></i>Mes Albums</h5></div>
                        <div class="card-body-custom">
                            <?php if (empty($albums)): ?>
                                <div class="text-center py-4"><i class="fas fa-compact-disc fa-3x text-muted mb-3"></i><p class="text-muted">Aucun album. Créez votre premier album !</p><a href="<?php echo SITE_URL; ?>/artist-add-album.php" class="btn btn-success"><i class="fas fa-plus me-2"></i>Créer un Album</a></div>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($albums as $al): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="album-card">
                                            <h6><?php echo htmlspecialchars($al['title']); ?></h6>
                                            <small class="text-muted"><?php echo date('Y', strtotime($al['release_date'])); ?> • <?php echo $al['total_tracks']; ?> titres</small>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="content-card mb-4">
                        <div class="card-header-custom"><h5><i class="fas fa-chart-line me-2"></i>Statistiques</h5></div>
                        <div class="card-body-custom">
                            <div class="stat-item-small"><i class="fas fa-play-circle text-primary"></i><div><strong><?php echo number_format($stats['total_plays']); ?></strong><small class="d-block text-muted">Écoutes totales</small></div></div>
                            <div class="stat-item-small"><i class="fas fa-users text-success"></i><div><strong><?php echo number_format($stats['followers']); ?></strong><small class="d-block text-muted">Abonnés</small></div></div>
                            <div class="stat-item-small"><i class="fas fa-music text-warning"></i><div><strong><?php echo number_format($stats['total_songs']); ?></strong><small class="d-block text-muted">Chansons</small></div></div>
                        </div>
                    </div>
                    
                    <div class="content-card">
                        <div class="card-header-custom"><h5><i class="fas fa-info-circle me-2"></i>Infos Profil</h5></div>
                        <div class="card-body-custom">
                            <p><strong>Nom de scène:</strong><br><?php echo htmlspecialchars($artist['stage_name']); ?></p>
                            <p><strong>Vrai nom:</strong><br><?php echo htmlspecialchars($artist['real_name']); ?></p>
                            <p><strong>Pays:</strong><br><?php echo htmlspecialchars($artist['country'] ?? 'Tchad'); ?></p>
                            <a href="<?php echo SITE_URL; ?>/edit-profile.php" class="btn btn-outline-primary btn-sm w-100"><i class="fas fa-edit me-2"></i>Modifier Profil</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
<style>
.artist-dashboard-container{background:#f5f7fa;min-height:100vh;padding-top:80px}.artist-header{background:linear-gradient(135deg,#9C27B0,#7B1FA2);padding:2rem 0;color:white;box-shadow:0 5px 20px rgba(0,0,0,.1)}.artist-avatar-large{width:80px;height:80px;background:linear-gradient(135deg,#FFD700,#FFC700);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#2C3E50;font-size:2rem;font-weight:700;margin-right:1.5rem;border:4px solid rgba(255,255,255,.3)}.artist-header h1{font-weight:700}.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1.5rem}.stat-card{background:white;padding:1.5rem;border-radius:15px;box-shadow:0 3px 15px rgba(0,0,0,.08);display:flex;align-items:center;gap:1rem}.stat-icon{width:55px;height:55px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;color:white;flex-shrink:0}.stat-content{flex:1}.stat-label{font-size:.85rem;color:#6c757d;margin-bottom:.3rem}.stat-value{font-size:1.8rem;font-weight:700;color:#2C3E50}.content-card{background:white;border-radius:15px;box-shadow:0 3px 15px rgba(0,0,0,.08);overflow:hidden}.card-header-custom{padding:1.25rem;border-bottom:1px solid #f0f0f0}.card-header-custom h5{margin:0;color:#2C3E50;font-weight:600}.card-body-custom{padding:1.25rem}.album-card{background:#f8f9fa;padding:1rem;border-radius:10px;border-left:4px solid #9C27B0}.stat-item-small{display:flex;align-items:center;gap:1rem;padding:1rem;background:#f8f9fa;border-radius:10px;margin-bottom:1rem}.stat-item-small i{font-size:1.5rem}
</style>
<?php include 'includes/footer.php'; ?>
ENDOFFILE
echo "artist-dashboard.php créé"
