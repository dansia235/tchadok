<?php
/**
 * Interface d'upload pour artistes - Tchadok Platform
 * Migration Tailwind (progressive)
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Verifier si l'utilisateur est connecte et est un artiste
if (!isLoggedIn()) {
    header('Location: ' . SITE_URL . '/login.php?redirect=upload');
    exit();
}

if (!isArtist()) {
    $_SESSION['error'] = 'Seuls les artistes peuvent uploader des titres.';
    header('Location: ' . SITE_URL . '/artist-signup.php');
    exit();
}

$dbInstance = TchadokDatabase::getInstance();
$db = $dbInstance->getConnection();

$artist = null;
if ($db) {
    $stmt = $db->prepare("SELECT * FROM artists WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $artist = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$artist) {
    $_SESSION['error'] = 'Profil artiste introuvable.';
    header('Location: ' . SITE_URL . '/artist-dashboard.php');
    exit();
}

$genres = $db ? $db->query("SELECT id, name FROM genres WHERE is_active = 1 ORDER BY name ASC")->fetchAll() : [];
$artistStats = [
    'tracks' => 0,
    'streams' => 0,
    'fans' => 0
];

if ($db) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM tracks WHERE artist_id = ?");
    $stmt->execute([$artist['id']]);
    $artistStats['tracks'] = (int) $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COALESCE(SUM(total_streams), 0) FROM tracks WHERE artist_id = ?");
    $stmt->execute([$artist['id']]);
    $artistStats['streams'] = (int) $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM follows WHERE followed_id = ? AND followed_type = 'artist'");
    $stmt->execute([$artist['id']]);
    $artistStats['fans'] = (int) $stmt->fetchColumn();
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitizeInput($_POST['title'] ?? '');
    $artistName = sanitizeInput($_POST['artist'] ?? '');
    $featuring = sanitizeInput($_POST['featuring'] ?? '');
    $genreId = !empty($_POST['genre_id']) ? (int) $_POST['genre_id'] : null;
    $customGenre = sanitizeInput($_POST['genre_name'] ?? '');
    $albumTitle = sanitizeInput($_POST['album'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $releaseDate = sanitizeInput($_POST['release_date'] ?? '');
    $language = sanitizeInput($_POST['language'] ?? '');
    $lyrics = sanitizeInput($_POST['lyrics'] ?? '');
    $explicitContent = isset($_POST['explicit']) ? 1 : 0;
    $distribution = sanitizeInput($_POST['distribution'] ?? 'free');
    $price = (float) ($_POST['price'] ?? 0);

    if ($title === '') {
        $error = 'Le titre est obligatoire.';
    } else {
        try {
            if ($customGenre && !$genreId) {
                $stmt = $db->prepare("INSERT INTO genres (name, name_french, is_active, created_at) VALUES (?, ?, 1, NOW())");
                $stmt->execute([$customGenre, $customGenre]);
                $genreId = (int) $db->lastInsertId();
            }

            if ($artistName && $artistName !== $artist['stage_name']) {
                $stmt = $db->prepare("UPDATE artists SET stage_name = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$artistName, $artist['id']]);
                $artist['stage_name'] = $artistName;
            }

            $audioPath = null;
            if (!empty($_FILES['audio_file']['tmp_name'])) {
                $upload = uploadFile(
                    $_FILES['audio_file'],
                    __DIR__ . '/' . AUDIO_PATH,
                    ALLOWED_AUDIO_TYPES,
                    MAX_AUDIO_SIZE
                );
                if (!$upload['success']) {
                    throw new Exception($upload['message']);
                }
                $audioPath = AUDIO_PATH . $upload['filename'];
            }

            if (!$audioPath) {
                throw new Exception('Le fichier audio est requis.');
            }

            $coverPath = null;
            if (!empty($_FILES['cover_image']['tmp_name'])) {
                $coverUpload = uploadFile(
                    $_FILES['cover_image'],
                    __DIR__ . '/' . IMAGES_PATH,
                    ALLOWED_IMAGE_TYPES,
                    MAX_IMAGE_SIZE
                );
                if ($coverUpload['success']) {
                    $coverPath = IMAGES_PATH . $coverUpload['filename'];
                }
            }

            $albumId = null;
            if ($albumTitle !== '') {
                $stmt = $db->prepare("SELECT id FROM albums WHERE artist_id = ? AND title = ? LIMIT 1");
                $stmt->execute([$artist['id'], $albumTitle]);
                $albumId = $stmt->fetchColumn();

                if (!$albumId) {
                    $stmt = $db->prepare("
                        INSERT INTO albums (artist_id, title, description, cover_image, genre_id, type, price, release_date, language, is_free, status, created_at)
                        VALUES (?, ?, ?, ?, ?, 'album', 0, ?, ?, 1, 'draft', NOW())
                    ");
                    $stmt->execute([
                        $artist['id'],
                        $albumTitle,
                        $description ?: null,
                        $coverPath,
                        $genreId,
                        $releaseDate ?: null,
                        $language ?: null
                    ]);
                    $albumId = (int) $db->lastInsertId();
                } elseif ($coverPath) {
                    $stmt = $db->prepare("UPDATE albums SET cover_image = COALESCE(cover_image, ?) WHERE id = ?");
                    $stmt->execute([$coverPath, $albumId]);
                }
            } elseif ($coverPath) {
                $stmt = $db->prepare("
                    INSERT INTO albums (artist_id, title, description, cover_image, genre_id, type, price, release_date, language, is_free, status, created_at)
                    VALUES (?, ?, ?, ?, ?, 'single', 0, ?, ?, 1, 'draft', NOW())
                ");
                $stmt->execute([
                    $artist['id'],
                    $title,
                    $description ?: null,
                    $coverPath,
                    $genreId,
                    $releaseDate ?: null,
                    $language ?: null
                ]);
                $albumId = (int) $db->lastInsertId();
            }

            if ($featuring) {
                $description = trim($description . "\nFeaturing: " . $featuring);
            }

            $isFree = 1;
            $downloadAllowed = 1;
            if ($distribution === 'paid') {
                $isFree = 0;
                $price = max(0, $price);
            } elseif ($distribution === 'premium') {
                $isFree = 0;
                $price = 0;
                $downloadAllowed = 0;
            } else {
                $price = 0;
            }

            $stmt = $db->prepare("
                INSERT INTO tracks
                (album_id, artist_id, title, description, genre_id, audio_file, preview_file, lyrics, duration, price, is_free, download_allowed, language, release_date, explicit_content, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([
                $albumId,
                $artist['id'],
                $title,
                $description ?: null,
                $genreId,
                $audioPath,
                null,
                $lyrics ?: null,
                1,
                $price,
                $isFree,
                $downloadAllowed,
                $language ?: null,
                $releaseDate ?: null,
                $explicitContent
            ]);

            $success = 'Votre titre a bien ete soumis. Il sera publie apres validation.';
            header('refresh:2;url=' . SITE_URL . '/artist-dashboard.php');
        } catch (Exception $e) {
            $error = 'Erreur: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Upload de musique';
$pageDescription = 'Partagez votre musique avec le monde';

$additionalJS = [
    SITE_URL . '/assets/js/upload.js'
];

include 'includes/header-tailwind.php';
?>

<main class="pt-24 pb-16">
    <section class="bg-bg">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-6 lg:grid-cols-12">
                <div class="lg:col-span-8">
                    <div class="rounded-3xl border border-white/10 bg-gradient-to-br from-slate-900/70 via-slate-800/40 to-emerald-500/10 p-6 shadow-elev-2 sm:p-8">
                        <div class="flex flex-wrap items-start gap-4">
                            <span class="grid h-14 w-14 place-items-center rounded-2xl bg-accent/20 text-accent">
                                <i class="fas fa-cloud-arrow-up text-2xl"></i>
                            </span>
                            <div class="min-w-[200px] flex-1">
                                <p class="text-xs uppercase tracking-[0.28em] text-muted">Studio createur</p>
                                <h1 class="mt-2 text-3xl font-display font-bold text-text sm:text-4xl">Upload de musique</h1>
                                <p class="mt-3 text-sm text-muted sm:text-base">
                                    Partagez votre talent avec des milliers d'auditeurs et donnez vie a vos titres
                                    grace a une experience d'upload fluide et premium.
                                </p>
                                <div class="mt-6 flex flex-wrap items-center gap-3 text-xs text-muted">
                                    <span class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1">
                                        <i class="fas fa-check-circle text-emerald-300"></i>
                                        Format audio HD recommande
                                    </span>
                                    <span class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1">
                                        <i class="fas fa-shield-alt text-sky-300"></i>
                                        Droits verifies
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="lg:col-span-4">
                    <div class="rounded-3xl border border-white/10 bg-surface/60 p-6 shadow-elev-2">
                        <h2 class="text-sm font-semibold text-text">Statistiques artistes</h2>
                        <p class="mt-2 text-xs text-muted">Apercu de votre audience actuelle.</p>
                        <div class="mt-5 space-y-3">
                            <div class="flex items-center justify-between rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                                <span class="text-xs text-muted">Titres uploades</span>
                                <span class="text-lg font-semibold text-text"><?php echo number_format($artistStats['tracks']); ?></span>
                            </div>
                            <div class="flex items-center justify-between rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                                <span class="text-xs text-muted">Ecoutes totales</span>
                                <span class="text-lg font-semibold text-text"><?php echo formatNumber($artistStats['streams']); ?></span>
                            </div>
                            <div class="flex items-center justify-between rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                                <span class="text-xs text-muted">Fans engages</span>
                                <span class="text-lg font-semibold text-text"><?php echo formatNumber($artistStats['fans']); ?></span>
                            </div>
                        </div>
                        <div class="mt-5 rounded-2xl border border-emerald-400/30 bg-emerald-500/10 p-4 text-xs text-emerald-200">
                            <i class="fas fa-bolt mr-2"></i>
                            Publiez regulierement pour rester visible sur la homepage.
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-8 rounded-3xl border border-white/10 bg-surface/60 p-6 shadow-elev-2 sm:p-8">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <p class="text-xs uppercase tracking-[0.28em] text-muted">Processus</p>
                        <h2 class="mt-2 text-2xl font-display font-bold text-text">Publier un nouveau titre</h2>
                        <p class="mt-2 text-sm text-muted">Completez les 3 etapes pour mettre votre musique en ligne.</p>
                    </div>
                    <div class="rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-semibold text-text">
                        Etape <span data-step-count>1</span>/3
                    </div>
                </div>

                <?php if ($success): ?>
                    <div class="mt-6 rounded-2xl border border-emerald-400/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">
                        <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="mt-6 rounded-2xl border border-rose-400/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">
                        <i class="fas fa-exclamation-triangle mr-2"></i><?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <div class="relative mt-8">
                    <div class="absolute left-6 right-6 top-5 h-px bg-white/10"></div>
                    <div class="relative z-10 flex items-center justify-between">
                        <div class="flex flex-col items-center gap-2" data-stepper-item data-step="1">
                            <div class="flex h-10 w-10 items-center justify-center rounded-full border text-sm font-semibold transition" data-step-circle>1</div>
                            <span class="text-[11px] font-semibold uppercase tracking-[0.2em]" data-step-label>Infos</span>
                        </div>
                        <div class="flex flex-col items-center gap-2" data-stepper-item data-step="2">
                            <div class="flex h-10 w-10 items-center justify-center rounded-full border text-sm font-semibold transition" data-step-circle>2</div>
                            <span class="text-[11px] font-semibold uppercase tracking-[0.2em]" data-step-label>Fichiers</span>
                        </div>
                        <div class="flex flex-col items-center gap-2" data-stepper-item data-step="3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-full border text-sm font-semibold transition" data-step-circle>3</div>
                            <span class="text-[11px] font-semibold uppercase tracking-[0.2em]" data-step-label>Final</span>
                        </div>
                    </div>
                </div>

                <form id="uploadForm" method="POST" enctype="multipart/form-data" class="mt-8 space-y-10">
                    <?php echo csrfField(); ?>
                    <div data-form-step="1">
                        <div class="grid gap-6 lg:grid-cols-2">
                            <div class="lg:col-span-2">
                                <label for="title" class="text-sm font-semibold text-text">Titre de la chanson *</label>
                                <input type="text" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text placeholder:text-muted focus:outline-none focus:ring-2 focus:ring-accent/60"
                                       id="title" name="title" required placeholder="Ex: Sahara Beat" maxlength="100">
                            </div>

                            <div>
                                <label for="artist" class="text-sm font-semibold text-text">Nom d'artiste *</label>
                                <input type="text" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text placeholder:text-muted focus:outline-none focus:ring-2 focus:ring-accent/60"
                                       id="artist" name="artist" required
                                       value="<?php echo htmlspecialchars($artist['stage_name'] ?? ''); ?>"
                                       placeholder="Votre nom d'artiste" readonly>
                            </div>
                            <div>
                                <label for="featuring" class="text-sm font-semibold text-text">Featuring (optionnel)</label>
                                <input type="text" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text placeholder:text-muted focus:outline-none focus:ring-2 focus:ring-accent/60"
                                       id="featuring" name="featuring" placeholder="Ex: Artiste 1, Artiste 2">
                            </div>

                            <div>
                                <label for="genre" class="text-sm font-semibold text-text">Genre *</label>
                                <select class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text focus:outline-none focus:ring-2 focus:ring-accent/60"
                                        id="genre" name="genre_id" <?php echo empty($genres) ? '' : 'required'; ?>>
                                    <option value="">Selectionnez un genre</option>
                                    <?php foreach ($genres as $genre): ?>
                                        <option value="<?php echo (int) $genre['id']; ?>">
                                            <?php echo htmlspecialchars($genre['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (empty($genres)): ?>
                                    <input type="text" class="mt-3 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text"
                                           name="genre_name" placeholder="Saisir un nouveau genre">
                                    <p class="mt-2 text-xs text-muted">Aucun genre disponible. Ajoutez-en un pour continuer.</p>
                                <?php endif; ?>
                            </div>
                            <div>
                                <label for="album" class="text-sm font-semibold text-text">Album (optionnel)</label>
                                <input type="text" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text placeholder:text-muted focus:outline-none focus:ring-2 focus:ring-accent/60"
                                       id="album" name="album" placeholder="Nom de l'album">
                            </div>

                            <div class="lg:col-span-2">
                                <label for="description" class="text-sm font-semibold text-text">Description</label>
                                <textarea class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text placeholder:text-muted focus:outline-none focus:ring-2 focus:ring-accent/60"
                                          id="description" name="description" rows="4" maxlength="1000"
                                          placeholder="Parlez de votre titre, de son inspiration..."></textarea>
                                <div class="mt-2 text-xs text-muted">
                                    <span data-description-count>0</span>/1000 caracteres
                                </div>
                            </div>

                            <div>
                                <label for="release_date" class="text-sm font-semibold text-text">Date de sortie</label>
                                <input type="date" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text focus:outline-none focus:ring-2 focus:ring-accent/60"
                                       id="release_date" name="release_date" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div>
                                <label for="language" class="text-sm font-semibold text-text">Langue principale</label>
                                <select class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text focus:outline-none focus:ring-2 focus:ring-accent/60"
                                        id="language" name="language">
                                    <option value="fr">Francais</option>
                                    <option value="ar">Arabe</option>
                                    <option value="sara">Sara</option>
                                    <option value="kanembou">Kanembou</option>
                                    <option value="other">Autre</option>
                                </select>
                            </div>
                        </div>

                        <label class="mt-6 flex items-start gap-3 text-sm text-muted">
                            <input class="mt-1 h-4 w-4 rounded border-white/20 bg-bg text-accent focus:ring-accent/60"
                                   type="checkbox" id="explicit" name="explicit">
                            <span>Contenu explicite (paroles inappropriees pour les mineurs)</span>
                        </label>

                        <div class="mt-8 flex justify-end">
                            <button type="button" class="flex items-center gap-2 rounded-full bg-accent px-6 py-3 text-sm font-semibold text-white shadow-elev-1 hover:shadow-elev-2"
                                    data-step-next>
                                Suivant
                                <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>

                    <div class="hidden" data-form-step="2">
                        <div class="space-y-6">
                            <div>
                                <h3 class="text-lg font-semibold text-text">Upload des fichiers</h3>
                                <p class="mt-2 text-sm text-muted">Ajoutez vos pistes et visuels pour une publication complete.</p>
                            </div>

                            <div class="space-y-4">
                                <div class="rounded-2xl border border-dashed border-white/20 bg-white/5 p-6 transition" data-dropzone="audio">
                                    <input type="file" id="audioFile" name="audio_file" accept="audio/*" required class="hidden" data-file-input>
                                    <div class="flex flex-col items-center gap-3 text-center" data-file-placeholder>
                                        <span class="grid h-12 w-12 place-items-center rounded-2xl bg-accent/20 text-accent">
                                            <i class="fas fa-music text-xl"></i>
                                        </span>
                                        <div>
                                            <p class="text-sm font-semibold text-text">Fichier audio *</p>
                                            <p class="mt-1 text-xs text-muted">Glissez-deposez ou cliquez pour selectionner</p>
                                        </div>
                                        <p class="text-xs text-muted">MP3, WAV, M4A - Max 50MB - Qualite recommande</p>
                                    </div>
                                    <div class="hidden w-full items-center gap-3 rounded-2xl border border-white/10 bg-bg/60 p-3" data-file-info>
                                        <span class="grid h-12 w-12 place-items-center rounded-xl bg-white/10 text-text">
                                            <i class="fas fa-headphones"></i>
                                        </span>
                                        <div class="flex-1">
                                            <p class="text-sm font-semibold text-text" data-file-name>Nom du fichier</p>
                                            <p class="text-xs text-muted" data-file-meta>0 MB</p>
                                        </div>
                                        <button type="button" class="rounded-full border border-white/10 bg-white/5 p-2 text-rose-200 hover:text-rose-100" data-file-remove>
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="rounded-2xl border border-dashed border-white/20 bg-white/5 p-6 transition" data-dropzone="cover">
                                    <input type="file" id="coverFile" name="cover_image" accept="image/*" class="hidden" data-file-input>
                                    <div class="flex flex-col items-center gap-3 text-center" data-file-placeholder>
                                        <span class="grid h-12 w-12 place-items-center rounded-2xl bg-sky-500/20 text-sky-200">
                                            <i class="fas fa-image text-xl"></i>
                                        </span>
                                        <div>
                                            <p class="text-sm font-semibold text-text">Image de couverture</p>
                                            <p class="mt-1 text-xs text-muted">Ajoutez une image impactante pour votre titre</p>
                                        </div>
                                        <p class="text-xs text-muted">JPG, PNG - Min 500x500px - Max 5MB</p>
                                    </div>
                                    <div class="hidden w-full items-center gap-3 rounded-2xl border border-white/10 bg-bg/60 p-3" data-file-info>
                                        <img class="h-14 w-14 rounded-xl object-cover" alt="Apercu" data-file-preview>
                                        <div class="flex-1">
                                            <p class="text-sm font-semibold text-text" data-file-name>Nom du fichier</p>
                                            <p class="text-xs text-muted" data-file-meta>0 MB</p>
                                        </div>
                                        <button type="button" class="rounded-full border border-white/10 bg-white/5 p-2 text-rose-200 hover:text-rose-100" data-file-remove>
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label for="lyrics" class="text-sm font-semibold text-text">Paroles (optionnel)</label>
                                <textarea class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text placeholder:text-muted focus:outline-none focus:ring-2 focus:ring-accent/60"
                                          id="lyrics" name="lyrics" rows="6" placeholder="Ajoutez les paroles de votre chanson..."></textarea>
                            </div>
                        </div>

                        <div class="mt-8 flex flex-wrap justify-between gap-3">
                            <button type="button" class="flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-6 py-3 text-sm font-semibold text-text hover:bg-white/10"
                                    data-step-prev>
                                <i class="fas fa-arrow-left"></i>
                                Precedent
                            </button>
                            <button type="button" class="flex items-center gap-2 rounded-full bg-accent px-6 py-3 text-sm font-semibold text-white shadow-elev-1 hover:shadow-elev-2"
                                    data-step-next>
                                Suivant
                                <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>

                    <div class="hidden" data-form-step="3">
                        <div class="space-y-6">
                            <div>
                                <h3 class="text-lg font-semibold text-text">Finalisation</h3>
                                <p class="mt-2 text-sm text-muted">Choisissez la distribution et confirmez vos droits.</p>
                            </div>

                            <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
                                <h4 class="text-sm font-semibold text-text">Options de distribution</h4>
                                <div class="mt-4 grid gap-3 sm:grid-cols-3">
                                    <label class="block">
                                        <input type="radio" name="distribution" value="free" class="peer sr-only" checked>
                                        <div class="h-full rounded-2xl border border-white/10 bg-bg/40 p-4 text-sm transition peer-checked:border-accent peer-checked:bg-accent/10">
                                            <p class="font-semibold text-text">Gratuit</p>
                                            <p class="mt-2 text-xs text-muted">Accessible a tous les utilisateurs.</p>
                                        </div>
                                    </label>
                                    <label class="block">
                                        <input type="radio" name="distribution" value="premium" class="peer sr-only">
                                        <div class="h-full rounded-2xl border border-white/10 bg-bg/40 p-4 text-sm transition peer-checked:border-amber-400/60 peer-checked:bg-amber-400/10">
                                            <p class="font-semibold text-text">Premium</p>
                                            <p class="mt-2 text-xs text-muted">Reserve aux abonnes Premium.</p>
                                        </div>
                                    </label>
                                    <label class="block">
                                        <input type="radio" name="distribution" value="paid" class="peer sr-only">
                                        <div class="h-full rounded-2xl border border-white/10 bg-bg/40 p-4 text-sm transition peer-checked:border-emerald-400/60 peer-checked:bg-emerald-500/10">
                                            <p class="font-semibold text-text">Payant</p>
                                            <p class="mt-2 text-xs text-muted">Vente a l'unite.</p>
                                        </div>
                                    </label>
                                </div>

                                <div class="mt-4 hidden max-w-sm" data-price-wrapper>
                                    <label for="price" class="text-sm font-semibold text-text">Prix (FCFA)</label>
                                    <input type="number" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text placeholder:text-muted focus:outline-none focus:ring-2 focus:ring-accent/60"
                                           id="price" name="price" min="500" max="10000" step="500" placeholder="Ex: 1000">
                                    <p class="mt-2 text-xs text-muted">Prix entre 500 et 10 000 FCFA.</p>
                                </div>
                            </div>

                            <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
                                <h4 class="text-sm font-semibold text-text">Droits et permissions</h4>
                                <div class="mt-4 space-y-3 text-sm text-muted">
                                    <label class="flex items-start gap-3">
                                        <input class="mt-1 h-4 w-4 rounded border-white/20 bg-bg text-accent focus:ring-accent/60"
                                               type="checkbox" id="terms" name="terms" required>
                                        <span>J'accepte les <a href="<?php echo SITE_URL; ?>/conditions.php" class="text-accent hover:text-accent/80">conditions d'utilisation</a> *</span>
                                    </label>
                                    <label class="flex items-start gap-3">
                                        <input class="mt-1 h-4 w-4 rounded border-white/20 bg-bg text-accent focus:ring-accent/60"
                                               type="checkbox" id="copyright" name="copyright" required>
                                        <span>Je confirme etre le proprietaire des droits de cette oeuvre *</span>
                                    </label>
                                    <label class="flex items-start gap-3">
                                        <input class="mt-1 h-4 w-4 rounded border-white/20 bg-bg text-accent focus:ring-accent/60"
                                               type="checkbox" id="newsletter" name="newsletter" checked>
                                        <span>Recevoir des notifications sur les performances de mon titre</span>
                                    </label>
                                </div>
                            </div>

                            <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
                                <h4 class="text-sm font-semibold text-text">Resume de l'upload</h4>
                                <div class="mt-4 grid gap-2 text-sm text-muted">
                                    <div class="flex items-center justify-between">
                                        <span>Titre</span>
                                        <span class="font-semibold text-text" data-summary-title>-</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span>Artiste</span>
                                        <span class="font-semibold text-text" data-summary-artist>-</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span>Genre</span>
                                        <span class="font-semibold text-text" data-summary-genre>-</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span>Distribution</span>
                                        <span class="font-semibold text-text" data-summary-distribution>Gratuit</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-8 flex flex-wrap justify-between gap-3">
                            <button type="button" class="flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-6 py-3 text-sm font-semibold text-text hover:bg-white/10"
                                    data-step-prev>
                                <i class="fas fa-arrow-left"></i>
                                Precedent
                            </button>
                            <button type="submit" class="flex items-center gap-2 rounded-full bg-emerald-400 px-6 py-3 text-sm font-semibold text-bg shadow-elev-1 hover:shadow-elev-2">
                                <i class="fas fa-cloud-arrow-up"></i>
                                Publier le titre
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="mt-12 grid gap-4 md:grid-cols-3">
                <div class="rounded-3xl border border-white/10 bg-surface/60 p-6 text-center shadow-elev-1">
                    <div class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-accent/20 text-accent">
                        <i class="fas fa-music"></i>
                    </div>
                    <h3 class="mt-4 text-base font-semibold text-text">Qualite audio</h3>
                    <p class="mt-2 text-sm text-muted">Publiez en 320kbps minimum pour une ecoute optimale.</p>
                </div>
                <div class="rounded-3xl border border-white/10 bg-surface/60 p-6 text-center shadow-elev-1">
                    <div class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-sky-500/20 text-sky-200">
                        <i class="fas fa-image"></i>
                    </div>
                    <h3 class="mt-4 text-base font-semibold text-text">Visuel impactant</h3>
                    <p class="mt-2 text-sm text-muted">Une pochette soignee augmente la decouverte.</p>
                </div>
                <div class="rounded-3xl border border-white/10 bg-surface/60 p-6 text-center shadow-elev-1">
                    <div class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-emerald-500/20 text-emerald-200">
                        <i class="fas fa-tags"></i>
                    </div>
                    <h3 class="mt-4 text-base font-semibold text-text">Metadonnees</h3>
                    <p class="mt-2 text-sm text-muted">Renseignez chaque champ pour un meilleur reach.</p>
                </div>
            </div>
        </div>
    </section>
</main>

<div class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 p-4" data-upload-modal>
    <div class="w-full max-w-md rounded-3xl border border-white/10 bg-surface p-6 text-center shadow-elev-3">
        <div class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-accent/20 text-accent">
            <i class="fas fa-cloud-arrow-up text-2xl"></i>
        </div>
        <h4 class="mt-4 text-lg font-semibold text-text">Upload en cours...</h4>
        <p class="mt-2 text-sm text-muted">Veuillez ne pas fermer cette fenetre.</p>
        <div class="mt-6 h-3 w-full rounded-full bg-white/10">
            <div class="h-3 w-0 rounded-full bg-accent transition-all duration-300" data-progress-bar></div>
        </div>
        <p class="mt-3 text-xs text-muted" data-progress-text>0%</p>
    </div>
</div>

<?php include 'includes/footer-tailwind.php'; ?>
