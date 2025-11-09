<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . SITE_URL . '/login.php');
    exit();
}

$pageTitle = 'Ajouter une Chanson';
$success = '';
$error = '';

// Récupérer artistes, albums, genres
$dbInstance = TchadokDatabase::getInstance();
$db = $dbInstance->getConnection();

$artists = $db->query("SELECT id, stage_name FROM artists ORDER BY stage_name")->fetchAll();
$albums = $db->query("SELECT id, title, artist_id FROM albums ORDER BY title")->fetchAll();
$genres = $db->query("SELECT id, name FROM genres ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitizeInput($_POST['title'] ?? '');
    $artistId = intval($_POST['artist_id'] ?? 0);
    $albumId = !empty($_POST['album_id']) ? intval($_POST['album_id']) : null;
    $genreId = !empty($_POST['genre_id']) ? intval($_POST['genre_id']) : null;
    $duration = intval($_POST['duration'] ?? 0);
    $youtubeUrl = sanitizeInput($_POST['youtube_url'] ?? '');
    $isPremium = isset($_POST['is_premium']) ? 1 : 0;

    if (empty($title) || $artistId <= 0) {
        $error = 'Titre et artiste obligatoires.';
    } else {
        try {
            $slug = strtolower(str_replace([' ', '\''], ['-', ''], $title));
            $stmt = $db->prepare("INSERT INTO songs (artist_id, album_id, genre_id, title, slug, duration, youtube_url, is_premium, is_active, release_date, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, CURDATE(), NOW())");
            $stmt->execute([$artistId, $albumId, $genreId, $title, $slug, $duration, $youtubeUrl, $isPremium]);
            $success = '✅ Chanson ajoutée avec succès !';
            header('refresh:2;url=' . SITE_URL . '/admin-dashboard.php');
        } catch (Exception $e) {
            $error = 'Erreur: ' . $e->getMessage();
        }
    }
}

include 'includes/header.php';
?>
<div class="container mt-5 pt-5">
    <h2 class="mb-4"><i class="fas fa-music me-2"></i>Ajouter une Chanson</h2>
    <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
    <form method="POST" class="bg-white p-4 rounded shadow">
        <div class="mb-3"><label>Titre *</label><input type="text" name="title" class="form-control" required></div>
        <div class="mb-3"><label>Artiste *</label><select name="artist_id" class="form-control" required><?php foreach ($artists as $a): ?><option value="<?php echo $a['id']; ?>"><?php echo htmlspecialchars($a['stage_name']); ?></option><?php endforeach; ?></select></div>
        <div class="mb-3"><label>Album (optionnel)</label><select name="album_id" class="form-control"><option value="">-- Aucun --</option><?php foreach ($albums as $al): ?><option value="<?php echo $al['id']; ?>"><?php echo htmlspecialchars($al['title']); ?></option><?php endforeach; ?></select></div>
        <div class="mb-3"><label>Genre</label><select name="genre_id" class="form-control"><?php foreach ($genres as $g): ?><option value="<?php echo $g['id']; ?>"><?php echo htmlspecialchars($g['name']); ?></option><?php endforeach; ?></select></div>
        <div class="mb-3"><label>Durée (secondes)</label><input type="number" name="duration" class="form-control" value="180"></div>
        <div class="mb-3"><label>URL YouTube</label><input type="url" name="youtube_url" class="form-control" placeholder="https://youtube.com/..."></div>
        <div class="mb-3 form-check"><input type="checkbox" name="is_premium" class="form-check-input" id="premium"><label class="form-check-label" for="premium">Chanson Premium</label></div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Enregistrer</button>
        <a href="<?php echo SITE_URL; ?>/admin-dashboard.php" class="btn btn-secondary">Annuler</a>
    </form>
</div>
<?php include 'includes/footer.php'; ?>
