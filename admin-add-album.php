<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// SEC-19 : l'acces depend d'une permission nommee, verifiee cote serveur.
// Masquer l'entree de menu ne protege rien : l'adresse se tape.
Autorisations::exiger('catalogue.editer');

$pageTitle = 'Ajouter un album';
$pageDescription = 'Ajoutez un nouvel album à la plateforme.';
$hideTopNav = true;
$hideFooter = true;
$success = '';
$error = '';
$user = getCurrentUser();

$dbInstance = TchadokDatabase::getInstance();
$db = $dbInstance->getConnection();

$artists = $db ? $db->query("SELECT id, stage_name FROM artists ORDER BY stage_name")->fetchAll() : [];
$genres = $db ? $db->query("SELECT id, name FROM genres ORDER BY name")->fetchAll() : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitizeInput($_POST['title'] ?? '');
    $artistId = (int) ($_POST['artist_id'] ?? 0);
    $genreId = !empty($_POST['genre_id']) ? (int) $_POST['genre_id'] : null;
    $type = sanitizeInput($_POST['type'] ?? 'album');
    $releaseDate = sanitizeInput($_POST['release_date'] ?? '');
    $language = sanitizeInput($_POST['language'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    // DATA-04 : les bornes dependent du format de sortie.
    $prixSaisi = Tarifs::valider($_POST['price'] ?? 0, 'release', $type);
    $price = $prixSaisi['valeur'];
    $isFree = isset($_POST['is_free']) ? 1 : 0;
    $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
    $status = sanitizeInput($_POST['status'] ?? 'draft');

    // DATA-03 : memes regles de format que du cote artiste. La console n'est
    // pas une porte derobee.
    $erreursFormat = Sorties::validerEnregistrement($type, $isFree ? null : $price, (bool) $isFree);

    if (!$prixSaisi['valide']) {
        $error = $prixSaisi['message'];
    } elseif ($erreursFormat !== []) {
        $error = implode(' ', $erreursFormat);
    } elseif (empty($title) || $artistId <= 0) {
        $error = 'Titre et artiste obligatoires.';
    } else {
        try {
            $coverPath = null;
            if (!empty($_FILES['cover_image']['tmp_name'])) {
                $upload = uploadFile(
                    $_FILES['cover_image'],
                    __DIR__ . '/' . IMAGES_PATH . 'albums/',
                    ALLOWED_IMAGE_TYPES,
                    MAX_IMAGE_SIZE
                );
                if (!$upload['success']) {
                    throw new Exception($upload['message']);
                }
                $coverPath = IMAGES_PATH . 'albums/' . $upload['filename'];
            }

            if ($isFree) {
                $price = 0;
            }

            $allowedStatus = ['draft', 'pending', 'approved', 'rejected'];
            if (!in_array($status, $allowedStatus, true)) {
                $status = 'draft';
            }

            // DATA-03 : la sortie est toujours creee en brouillon. Le statut
            // demande n'est applique qu'ensuite, par changerStatut(), qui
            // verifie la composition -- une sortie sans titre ne peut pas etre
            // publiee, quel que soit le formulaire qui la cree.
            $stmt = $db->prepare("
                INSERT INTO releases
                (artist_id, title, slug, description, cover_image, genre_id, format, price_bundle,
                 release_date, language, is_free, is_featured, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', NOW())
            ");
            $stmt->execute([
                $artistId,
                $title,
                Sorties::slug($title, 'releases'),
                $description ?: null,
                $coverPath,
                $genreId,
                $type,
                $isFree ? null : ($price ?: null),
                $releaseDate ?: null,
                $language ?: null,
                $isFree,
                $isFeatured
            ]);
            $releaseId = (int) $db->lastInsertId();

            // SEC-19 : trace de l'ajout, avec le statut initial et le prix.
            JournalAudit::enregistrer('contenu.cree', [
                'cible_type' => 'sortie',
                'cible_id'   => $releaseId,
                'apres'      => ['titre' => $title, 'artiste_id' => $artistId, 'format' => $type, 'statut' => 'draft', 'prix' => $price],
            ]);

            $success = sprintf('%s enregistre en brouillon.', Sorties::libelle($type));

            if ($status !== 'draft') {
                $changement = Sorties::changerStatut($releaseId, $status, (int) ($_SESSION['user_id'] ?? 0));
                if ($changement['succes']) {
                    $success = sprintf('%s enregistre (%s).', Sorties::libelle($type), $status);
                } else {
                    $success = '';
                    $error = implode(' ', $changement['erreurs'])
                        . ' La sortie reste en brouillon : ajoutez les titres, puis publiez-la.';
                }
            }

            header('refresh:2;url=' . SITE_URL . '/admin-dashboard.php');
        } catch (Exception $e) {
            $error = GestionErreurs::messagePublic($e, 'ajout d\'un album (admin)');
        }
    }
}

$adminShellMetrics = [
    ['value' => (string) count($artists), 'label' => 'artistes', 'tone' => 'border-white/10 bg-white/5 text-muted'],
    ['value' => (string) count($genres), 'label' => 'genres', 'tone' => 'border-white/10 bg-white/5 text-muted'],
    ['value' => $success !== '' ? 'enregistré' : 'nouveau brouillon', 'label' => '', 'tone' => $success !== '' ? 'border-emerald-400/30 bg-emerald-400/10 text-emerald-200' : 'border-cyan-400/30 bg-cyan-400/10 text-cyan-200']
];
$dashboardSecondaryNavLabel = 'Albums admin';
$dashboardSecondaryNavItems = [
    ['label' => 'Aperçu', 'target' => 'album-overview', 'icon' => 'home'],
    ['label' => 'Formulaire', 'target' => 'album-form', 'icon' => 'edit'],
    ['label' => 'Visuels', 'target' => 'album-assets', 'icon' => 'image']
];

$additionalJS = [
    SITE_URL . '/assets/js/tailwind-shell.js',
    SITE_URL . '/assets/js/dashboard-sticky-header.js',
    SITE_URL . '/assets/js/dashboard-secondary-nav.js'
];

include 'includes/header-tailwind.php';
?>

<main class="min-h-screen bg-[radial-gradient(circle_at_top_right,rgba(47,109,224,0.18),transparent_26%),radial-gradient(circle_at_top_left,rgba(6,182,212,0.12),transparent_24%),#0B0F17] pb-16 pt-8">
    <?php include 'includes/admin-shell-header.php'; ?>

    <section id="album-overview" class="py-12">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
                <div class="flex items-start gap-4">
                    <div class="grid h-12 w-12 place-items-center rounded-2xl bg-accent/20 text-accent">
                        <i class="fas fa-compact-disc"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-display font-bold text-text">Ajouter un album</h1>
                        <p class="mt-1 text-sm text-muted">Publiez un album directement depuis l'administration.</p>
                    </div>
                </div>
                <a href="<?php echo SITE_URL; ?>/admin-dashboard.php" class="rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-semibold text-text">
                    <i class="fas fa-arrow-left"></i> Retour dashboard
                </a>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success" role="alert">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form id="album-form" method="POST" enctype="multipart/form-data" class="rounded-3xl border border-white/10 bg-surface/60 p-6 space-y-5">
                <?php echo csrfField(); ?>
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="text-xs font-semibold text-muted" for="title">Titre *</label>
                        <input id="title" type="text" name="title" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text" value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" required>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-muted" for="artist">Artiste *</label>
                        <select id="artist" name="artist_id" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text" required>
                            <option value="">Sélectionner un artiste</option>
                            <?php foreach ($artists as $artist): ?>
                                <option value="<?php echo $artist['id']; ?>" <?php echo (isset($_POST['artist_id']) && (int) $_POST['artist_id'] === (int) $artist['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($artist['stage_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <label class="text-xs font-semibold text-muted" for="type">Type</label>
                        <select id="type" name="type" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text">
                            <?php
                            $typeOptions = ['album' => 'Album', 'ep' => 'EP', 'single' => 'Single', 'maxi_single' => 'Maxi single'];
                            $currentType = $_POST['type'] ?? 'album';
                            foreach ($typeOptions as $value => $label): ?>
                                <option value="<?php echo $value; ?>" <?php echo $currentType === $value ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-muted" for="genre">Genre</label>
                        <select id="genre" name="genre_id" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text">
                            <option value="">Sélectionner un genre</option>
                            <?php foreach ($genres as $genre): ?>
                                <option value="<?php echo $genre['id']; ?>" <?php echo (isset($_POST['genre_id']) && (int) $_POST['genre_id'] === (int) $genre['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($genre['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-muted" for="release_date">Date de sortie</label>
                        <input id="release_date" type="date" name="release_date" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text" value="<?php echo htmlspecialchars($_POST['release_date'] ?? ''); ?>">
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <label class="text-xs font-semibold text-muted" for="language">Langue</label>
                        <input id="language" type="text" name="language" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text" value="<?php echo htmlspecialchars($_POST['language'] ?? ''); ?>" placeholder="Fr, Ar, ...">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-muted" for="price">Prix (FCFA)</label>
                        <?php $regleDePrix = Tarifs::regle('release', $currentType); ?>
                        <input id="price" type="number" min="0" max="<?php echo (int) ($regleDePrix['max'] ?? 500000); ?>" step="50" name="price" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text" value="<?php echo htmlspecialchars($_POST['price'] ?? '0'); ?>">
                        <p class="mt-2 text-xs text-muted"><?php echo htmlspecialchars(Tarifs::indication('release', $currentType)); ?></p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-muted" for="status">Statut</label>
                        <select id="status" name="status" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text">
                            <?php
                            $statusOptions = ['draft' => 'Brouillon', 'pending' => 'En attente', 'approved' => 'Approuvé', 'rejected' => 'Rejeté'];
                            $currentStatus = $_POST['status'] ?? 'draft';
                            foreach ($statusOptions as $value => $label): ?>
                                <option value="<?php echo $value; ?>" <?php echo $currentStatus === $value ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <label class="inline-flex items-center gap-2 text-sm text-muted">
                        <input type="checkbox" name="is_free" class="h-4 w-4 rounded border-white/20 bg-bg text-accent focus:ring-accent/40" <?php echo isset($_POST['is_free']) ? 'checked' : ''; ?>>
                        Album gratuit
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm text-muted">
                        <input type="checkbox" name="is_featured" class="h-4 w-4 rounded border-white/20 bg-bg text-accent focus:ring-accent/40" <?php echo isset($_POST['is_featured']) ? 'checked' : ''; ?>>
                        Mettre en avant
                    </label>
                </div>

                <div>
                    <label class="text-xs font-semibold text-muted" for="description">Description</label>
                    <textarea id="description" name="description" rows="4" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                </div>

                <div id="album-assets" class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="text-xs font-semibold text-muted" for="cover_image">Couverture (upload)</label>
                        <input id="cover_image" type="file" name="cover_image" accept="image/*" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text">
                    </div>
                </div>

                <div class="flex flex-wrap gap-3">
                    <button type="submit" class="rounded-full bg-emerald-500 px-6 py-3 text-sm font-semibold text-white shadow-elev-1">
                        <i class="fas fa-save"></i> Enregistrer
                    </button>
                    <a href="<?php echo SITE_URL; ?>/admin-dashboard.php" class="rounded-full border border-white/10 bg-white/5 px-6 py-3 text-sm font-semibold text-text">
                        Annuler
                    </a>
                </div>
            </form>
        </div>
    </section>
</main>

<?php include 'includes/footer-tailwind.php'; ?>
