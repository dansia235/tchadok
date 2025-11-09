<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . SITE_URL . '/login.php');
    exit();
}

$pageTitle = 'Ajouter un Album';
$success = '';
$error = '';

$dbInstance = TchadokDatabase::getInstance();
$db = $dbInstance->getConnection();
$artists = $db->query("SELECT id, stage_name FROM artists ORDER BY stage_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitizeInput($_POST['title'] ?? '');
    $artistId = intval($_POST['artist_id'] ?? 0);
    $releaseDate = sanitizeInput($_POST['release_date'] ?? date('Y-m-d'));
    $description = sanitizeInput($_POST['description'] ?? '');

    if (empty($title) || $artistId <= 0) {
        $error = 'Titre et artiste obligatoires.';
    } else {
        try {
            $slug = strtolower(str_replace([' ', '\''], ['-', ''], $title));
            $stmt = $db->prepare("INSERT INTO albums (artist_id, title, slug, release_date, description, is_active, created_at) VALUES (?, ?, ?, ?, ?, 1, NOW())");
            $stmt->execute([$artistId, $title, $slug, $releaseDate, $description]);
            $success = '✅ Album ajouté avec succès !';
            header('refresh:2;url=' . SITE_URL . '/admin-dashboard.php');
        } catch (Exception $e) {
            $error = 'Erreur: ' . $e->getMessage();
        }
    }
}

include 'includes/header.php';
?>
<div class="container mt-5 pt-5">
    <h2 class="mb-4"><i class="fas fa-compact-disc me-2"></i>Ajouter un Album</h2>
    <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
    <form method="POST" class="bg-white p-4 rounded shadow">
        <div class="mb-3"><label>Titre de l'Album *</label><input type="text" name="title" class="form-control" required></div>
        <div class="mb-3"><label>Artiste *</label><select name="artist_id" class="form-control" required><?php foreach ($artists as $a): ?><option value="<?php echo $a['id']; ?>"><?php echo htmlspecialchars($a['stage_name']); ?></option><?php endforeach; ?></select></div>
        <div class="mb-3"><label>Date de Sortie</label><input type="date" name="release_date" class="form-control" value="<?php echo date('Y-m-d'); ?>"></div>
        <div class="mb-3"><label>Description</label><textarea name="description" class="form-control" rows="4"></textarea></div>
        <button type="submit" class="btn btn-success"><i class="fas fa-save me-2"></i>Enregistrer</button>
        <a href="<?php echo SITE_URL; ?>/admin-dashboard.php" class="btn btn-secondary">Annuler</a>
    </form>
</div>
<?php include 'includes/footer.php'; ?>
