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

if (!$artist) {
    die('Profil artiste non trouve');
}

$pageTitle = 'Creer un Album';
$pageDescription = 'Creez un nouvel album pour votre catalogue.';
$success = '';
$error = '';

$genres = $db ? $db->query("SELECT id, name FROM genres ORDER BY name")->fetchAll() : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitizeInput($_POST['title'] ?? '');
    $genreId = !empty($_POST['genre_id']) ? (int) $_POST['genre_id'] : null;
    $type = sanitizeInput($_POST['type'] ?? 'album');
    $releaseDate = sanitizeInput($_POST['release_date'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $prixSaisi = validerPrix($_POST['price'] ?? 0);
    $price = $prixSaisi['valeur'];
    $isFree = isset($_POST['is_free']) ? 1 : 0;

    // DATA-03 : les regles de format sont verifiees ici, cote serveur. Un
    // controle qui ne vit que dans le formulaire se contourne en envoyant la
    // requete directement.
    $erreursFormat = Sorties::validerEnregistrement($type, $isFree ? null : $price, (bool) $isFree);

    if (!$prixSaisi['valide']) {
        $error = $prixSaisi['message'];
    } elseif ($erreursFormat !== []) {
        $error = implode(' ', $erreursFormat);
    } elseif (empty($title)) {
        $error = 'Le titre est obligatoire.';
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

            // DATA-03 : la sortie est enregistree en BROUILLON. Elle ne part en
            // moderation qu'une fois ses titres ajoutes : un album vide ne
            // respecte aucune regle de composition.
            $stmt = $db->prepare("
                INSERT INTO releases
                (artist_id, title, slug, description, cover_image, genre_id, format, price_bundle,
                 release_date, is_free, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', NOW())
            ");
            $stmt->execute([
                $artist['id'],
                $title,
                Sorties::slug($title, 'releases'),
                $description ?: null,
                $coverPath,
                $genreId,
                $type,
                $isFree ? null : ($price ?: null),
                $releaseDate ?: null,
                $isFree
            ]);
            $success = sprintf(
                '%s enregistre en brouillon. Ajoutez-y %s, puis envoyez-le en moderation.',
                Sorties::libelle($type),
                Sorties::attendu($type)
            );
            header('refresh:2;url=' . SITE_URL . '/artist-dashboard.php');
        } catch (Exception $e) {
            $error = GestionErreurs::messagePublic($e, 'ajout d\'un album (artiste)');
        }
    }
}

$additionalJS = [
    SITE_URL . '/assets/js/tailwind-shell.js'
];

include 'includes/header-tailwind.php';
?>

<main class="pt-20">
    <section class="py-12">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <div class="mb-6 flex items-start gap-4">
                <div class="grid h-12 w-12 place-items-center rounded-2xl bg-accent/20 text-accent">
                    <i class="fas fa-compact-disc"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-display font-bold text-text">Creer un album</h1>
                    <p class="mt-1 text-sm text-muted">Publiez un nouvel album dans votre catalogue.</p>
                </div>
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

            <form method="POST" enctype="multipart/form-data" class="rounded-3xl border border-white/10 bg-surface/60 p-6 space-y-5">
                <?php echo csrfField(); ?>
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="text-xs font-semibold text-muted" for="title">Titre *</label>
                        <input id="title" type="text" name="title" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text" value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" required>
                    </div>
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
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <label class="text-xs font-semibold text-muted" for="genre">Genre</label>
                        <select id="genre" name="genre_id" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text">
                            <option value="">Selectionner un genre</option>
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
                    <div>
                        <label class="text-xs font-semibold text-muted" for="price">Prix (FCFA)</label>
                        <input id="price" type="number" min="0" step="50" name="price" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text" value="<?php echo htmlspecialchars($_POST['price'] ?? '0'); ?>">
                    </div>
                </div>

                <label class="inline-flex items-center gap-2 text-sm text-muted">
                    <input type="checkbox" name="is_free" class="h-4 w-4 rounded border-white/20 bg-bg text-accent focus:ring-accent/40" <?php echo isset($_POST['is_free']) ? 'checked' : ''; ?>>
                    Album gratuit
                </label>

                <div>
                    <label class="text-xs font-semibold text-muted" for="description">Description</label>
                    <textarea id="description" name="description" rows="4" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="text-xs font-semibold text-muted" for="cover_image">Couverture (upload)</label>
                        <input id="cover_image" type="file" name="cover_image" accept="image/*" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text">
                    </div>
                </div>

                <div class="flex flex-wrap gap-3">
                    <button type="submit" class="rounded-full bg-emerald-500 px-6 py-3 text-sm font-semibold text-white shadow-elev-1">
                        <i class="fas fa-save"></i> Creer l'album
                    </button>
                    <a href="<?php echo SITE_URL; ?>/artist-dashboard.php" class="rounded-full border border-white/10 bg-white/5 px-6 py-3 text-sm font-semibold text-text">
                        Annuler
                    </a>
                </div>
            </form>
        </div>
    </section>
</main>

<?php include 'includes/footer-tailwind.php'; ?>
