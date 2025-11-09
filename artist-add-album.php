<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';

if (!isLoggedIn() || !isArtist()) {
    header('Location: ' . SITE_URL . '/login.php');
    exit();
}

$user = getCurrentUser();
$dbInstance = TchadokDatabase::getInstance();
$db = $dbInstance->getConnection();

$stmt = $db->prepare("SELECT * FROM artists WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$artist = $stmt->fetch();

$pageTitle = 'Créer un Album';
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitizeInput($_POST['title'] ?? '');
    $releaseDate = sanitizeInput($_POST['release_date'] ?? date('Y-m-d'));
    $description = sanitizeInput($_POST['description'] ?? '');

    if (empty($title)) {
        $error = 'Le titre est obligatoire.';
    } else {
        try {
            $slug = strtolower(str_replace([' ', '\''], ['-', ''], $title));
            $stmt = $db->prepare("INSERT INTO albums (artist_id, title, slug, release_date, description, is_active, created_at) VALUES (?, ?, ?, ?, ?, 1, NOW())");
            $stmt->execute([$artist['id'], $title, $slug, $releaseDate, $description]);
            $success = '✅ Album créé avec succès !';
            header('refresh:2;url=' . SITE_URL . '/artist-dashboard.php');
        } catch (Exception $e) {
            $error = 'Erreur: ' . $e->getMessage();
        }
    }
}

include 'includes/header.php';
?>
<div class="container mt-5 pt-5">
    <h2 class="mb-4"><i class="fas fa-compact-disc me-2"></i>Créer un Album</h2>
    <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
    <form method="POST" class="bg-white p-4 rounded shadow">
        <div class="mb-3"><label>Titre de l'Album *</label><input type="text" name="title" class="form-control" required></div>
        <div class="mb-3"><label>Date de Sortie</label><input type="date" name="release_date" class="form-control" value="<?php echo date('Y-m-d'); ?>"></div>
        <div class="mb-3"><label>Description</label><textarea name="description" class="form-control" rows="4" placeholder="Décrivez votre album..."></textarea></div>
        <button type="submit" class="btn btn-success"><i class="fas fa-save me-2"></i>Créer l'Album</button>
        <a href="<?php echo SITE_URL; ?>/artist-dashboard.php" class="btn btn-secondary">Annuler</a>
    </form>
</div>
<?php include 'includes/footer.php'; ?>
