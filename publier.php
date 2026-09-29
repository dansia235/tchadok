<?php
/**
 * Publier une sortie (MOD-05) : le SEUL parcours de publication.
 *
 *   format -> fichiers -> metadonnees -> prix -> recapitulatif -> soumission
 *
 * Chaque etape est enregistree en base ; un brouillon se reprend depuis la
 * liste « A reprendre ». Les regles (composition, grille tarifaire, niveau du
 * dossier, controles de fichiers, metadonnees obligatoires) sont appliquees
 * par includes/publication.php, cote serveur.
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/duree-audio.php';
require_once 'includes/controles-depot.php';
require_once 'includes/agregats.php';
require_once 'includes/comptes.php';
require_once 'includes/taxonomie.php';
require_once 'includes/moderation.php';
require_once 'includes/paiement/chargement.php';
require_once 'includes/dossier-artiste.php';
require_once 'includes/publication.php';

if (!isLoggedIn()) {
    redirect(SITE_URL . '/login.php?redirect=' . urlencode('/publier.php'));
}
$userId = (int) $_SESSION['user_id'];
$db = TchadokDatabase::getInstance()->getConnection();
$stmt = $db->prepare('SELECT * FROM artists WHERE user_id = ? AND deleted_at IS NULL');
$stmt->execute([$userId]);
$artiste = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$artiste) {
    show404();
}
$artistId = (int) $artiste['id'];

// PAYOUT-05 : le contrat d'abord (lien direct, comme avant).
if (Contrats::acceptationRequise($artistId)) {
    redirect(SITE_URL . '/contrat.php?retour=' . urlencode('/publier.php'));
}
$conditions = DossierArtiste::conditionsPublication($artistId, $userId);

$releaseId = (int) ($_GET['sortie'] ?? $_POST['sortie'] ?? 0);
$sortie = $releaseId > 0 ? Publication::sortie($releaseId, $artistId) : null;
if ($releaseId > 0 && $sortie === null) {
    setFlashMessage(FLASH_ERROR, 'Cette sortie n\'est pas (ou plus) modifiable : elle est peut-etre en moderation.');
    redirect(SITE_URL . '/publier.php');
}
$etapes = ['fichiers' => 'Fichiers', 'metadonnees' => 'Metadonnees', 'prix' => 'Prix', 'recapitulatif' => 'Recapitulatif'];
$etape = array_key_exists($_GET['etape'] ?? '', $etapes) ? (string) $_GET['etape'] : 'fichiers';
$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $conditions === []) {
    $action = (string) ($_POST['action'] ?? '');
    $r = match ($action) {
        'creer'       => Publication::creerSortie($artistId, (string) ($_POST['format'] ?? ''), (string) ($_POST['titre'] ?? '')),
        'piste'       => Publication::ajouterPiste($releaseId, $artistId, $_FILES['audio'] ?? []),
        'retirer'     => Publication::retirerPiste((int) ($_POST['titre_id'] ?? 0), $artistId),
        'pochette'    => Publication::deposerPochette($releaseId, $artistId, $_FILES['pochette'] ?? []),
        'metadonnees' => Publication::enregistrerMetadonnees($releaseId, $artistId, (array) ($_POST['sortie_meta'] ?? []), (array) ($_POST['titres'] ?? [])),
        'prix'        => Publication::enregistrerPrix($releaseId, $artistId, !empty($_POST['gratuit']), (string) ($_POST['prix_sortie'] ?? ''), (array) ($_POST['prix'] ?? [])),
        'soumettre'   => Publication::soumettre($releaseId, $artistId, $userId),
        default       => ['succes' => false, 'message' => 'Action inconnue.'],
    };
    if ($r['succes']) {
        setFlashMessage(FLASH_SUCCESS, $r['message']);
        $suite = match ($action) {
            'creer'       => '?sortie=' . $r['id'] . '&etape=fichiers',
            'metadonnees' => '?sortie=' . $releaseId . '&etape=prix',
            'prix'        => '?sortie=' . $releaseId . '&etape=recapitulatif',
            'soumettre'   => '',
            default       => '?sortie=' . $releaseId . '&etape=' . $etape,
        };
        redirect(SITE_URL . '/publier.php' . $suite);
    }
    $erreur = $r['message'];
}

$titres = $sortie ? Publication::titres($releaseId) : [];
$genres = getGenresSelectionnables();
$aReprendre = Publication::aReprendre($artistId);
$peutVendre = DossierArtiste::peutVendre($artistId);
$champ = 'mt-1 w-full rounded-xl border border-white/10 bg-bg px-3 py-2 text-sm text-text';
$pageTitle = 'Publier';
include 'includes/header-tailwind.php';
?>

<main class="min-h-screen bg-bg pb-16 pt-24">
    <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6">
        <header class="rounded-3xl border border-white/10 bg-surface/75 p-6">
            <p class="text-xs uppercase tracking-[0.28em] text-muted">Espace artiste · <?php echo e($artiste['stage_name']); ?></p>
            <h1 class="mt-2 text-2xl font-display font-bold text-text"><?php echo $sortie ? e($sortie['title']) . ' <span class="text-base text-muted">(' . e(Sorties::libelle($sortie['format'])) . ')</span>' : 'Publier une sortie'; ?></h1>
            <?php displayFlashMessages(); ?>
            <?php if ($erreur): ?><div class="alert alert-danger mt-4" role="alert" data-erreur-publication><i class="fas fa-exclamation-circle mt-0.5"></i><span><?php echo e($erreur); ?></span></div><?php endif; ?>
            <?php if ($sortie && $sortie['status'] === 'rejected' && $sortie['rejected_reason']): ?>
                <div class="alert alert-warning mt-4" role="status"><span>Retour de la moderation : <?php echo e($sortie['rejected_reason']); ?> — corrigez puis soumettez a nouveau.</span></div>
            <?php endif; ?>
        </header>

        <?php if ($conditions !== []): ?>
            <section class="rounded-3xl border border-amber-400/30 bg-amber-400/10 p-6" data-conditions-publication>
                <h2 class="text-lg font-semibold text-text">Avant de publier</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    <?php foreach ($conditions as $cle => $c): ?><li data-condition="<?php echo e($cle); ?>"><a class="text-amber-200 underline" href="<?php echo e(SITE_URL . $c['lien']); ?>"><?php echo e($c['message']); ?></a></li><?php endforeach; ?>
                </ul>
            </section>
        <?php elseif (!$sortie): ?>
            <section class="rounded-3xl border border-white/10 bg-surface/75 p-6">
                <h2 class="text-lg font-semibold text-text">Nouvelle sortie</h2>
                <form method="POST" class="mt-4 grid gap-3 sm:grid-cols-[1fr_200px_auto] sm:items-end">
                    <?php echo csrfField(); ?><input type="hidden" name="action" value="creer">
                    <label class="text-xs text-muted">Titre de la sortie<input name="titre" required maxlength="200" class="<?php echo $champ; ?>"></label>
                    <label class="text-xs text-muted">Format<select name="format" class="<?php echo $champ; ?>">
                        <?php foreach (Sorties::FORMATS as $cle => $f): ?><option value="<?php echo e($cle); ?>"><?php echo e($f['libelle'] . ' — ' . Sorties::attendu($cle)); ?></option><?php endforeach; ?></select></label>
                    <button class="rounded-full bg-accent px-5 py-2.5 text-sm font-semibold text-white">Commencer</button>
                </form>
                <?php if (!$peutVendre): ?><p class="mt-3 text-xs text-muted">Niveau Decouverte : sorties gratuites, <?php echo DossierArtiste::PLAFOND_DECOUVERTE; ?> titres par 30 jours.</p><?php endif; ?>
            </section>
            <?php if ($aReprendre !== []): ?>
                <section class="rounded-3xl border border-white/10 bg-surface/75 p-6">
                    <h2 class="text-lg font-semibold text-text">A reprendre</h2>
                    <ul class="mt-3 divide-y divide-white/5 text-sm">
                        <?php foreach ($aReprendre as $b): ?>
                            <li class="flex items-center justify-between py-2"><span class="text-text"><?php echo e($b['title']); ?> <span class="text-xs text-muted"><?php echo e(Sorties::libelle($b['format'])); ?> · <?php echo (int) $b['titres']; ?> titre(s) · <?php echo $b['status'] === 'rejected' ? 'a corriger' : 'brouillon'; ?></span></span>
                                <a class="text-xs underline" href="<?php echo SITE_URL; ?>/publier.php?sortie=<?php echo (int) $b['id']; ?>">Reprendre</a></li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>
        <?php else: ?>
            <nav class="flex flex-wrap gap-2 text-xs" aria-label="Etapes">
                <?php foreach ($etapes as $cle => $libelle): ?>
                    <a href="?sortie=<?php echo $releaseId; ?>&etape=<?php echo $cle; ?>" class="rounded-full border px-3 py-1.5 <?php echo $cle === $etape ? 'border-accent bg-accent text-white' : 'border-white/15 text-text'; ?>"<?php echo $cle === $etape ? ' aria-current="step"' : ''; ?>><?php echo e($libelle); ?></a>
                <?php endforeach; ?>
            </nav>

            <section class="rounded-3xl border border-white/10 bg-surface/75 p-6">
                <?php if ($etape === 'fichiers'): ?>
                    <h2 class="text-lg font-semibold text-text">Fichiers</h2>
                    <p class="mt-1 text-xs text-muted"><?php echo e(Sorties::attendu($sortie['format'])); ?>. MP3, WAV, FLAC ou M4A, 128 kbit/s et 44,1 kHz au moins ; la duree est lue dans le fichier.</p>
                    <ol class="mt-4 divide-y divide-white/5 text-sm">
                        <?php foreach ($titres as $t): ?>
                            <li class="flex items-center justify-between gap-3 py-2"><span class="text-text"><?php echo e($t['title']); ?> <span class="text-xs text-muted"><?php echo intdiv((int) $t['duration'], 60) . ':' . str_pad((string) ((int) $t['duration'] % 60), 2, '0', STR_PAD_LEFT); ?> · <?php echo (int) $t['audio_bitrate']; ?> kbit/s</span></span>
                                <form method="POST"><?php echo csrfField(); ?><input type="hidden" name="action" value="retirer"><input type="hidden" name="sortie" value="<?php echo $releaseId; ?>"><input type="hidden" name="titre_id" value="<?php echo (int) $t['id']; ?>"><button class="text-xs underline">Retirer</button></form></li>
                        <?php endforeach; ?>
                    </ol>
                    <form method="POST" enctype="multipart/form-data" class="mt-4 flex flex-wrap items-end gap-3">
                        <?php echo csrfField(); ?><input type="hidden" name="action" value="piste"><input type="hidden" name="sortie" value="<?php echo $releaseId; ?>">
                        <label class="text-xs text-muted">Ajouter un titre<input type="file" name="audio" accept=".mp3,.wav,.flac,.m4a" required class="mt-1 block text-sm text-text"></label>
                        <button class="rounded-full bg-accent px-4 py-2 text-xs font-semibold text-white">Deposer</button>
                    </form>
                    <form method="POST" enctype="multipart/form-data" class="mt-6 flex flex-wrap items-end gap-3">
                        <?php echo csrfField(); ?><input type="hidden" name="action" value="pochette"><input type="hidden" name="sortie" value="<?php echo $releaseId; ?>">
                        <label class="text-xs text-muted">Pochette (1400 x 1400 minimum)<?php echo $sortie['cover_image'] ? ' — deposee' : ''; ?><input type="file" name="pochette" accept=".jpg,.jpeg,.png,.webp" required class="mt-1 block text-sm text-text"></label>
                        <button class="rounded-full border border-white/15 px-4 py-2 text-xs font-semibold text-text">Envoyer la pochette</button>
                    </form>
                    <a class="mt-6 inline-block rounded-full bg-accent px-5 py-2.5 text-sm font-semibold text-white" href="?sortie=<?php echo $releaseId; ?>&etape=metadonnees">Continuer</a>

                <?php elseif ($etape === 'metadonnees'): ?>
                    <h2 class="text-lg font-semibold text-text">Metadonnees</h2>
                    <form method="POST" class="mt-4 space-y-5">
                        <?php echo csrfField(); ?><input type="hidden" name="action" value="metadonnees"><input type="hidden" name="sortie" value="<?php echo $releaseId; ?>">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="text-xs text-muted">Titre de la sortie<input name="sortie_meta[title]" value="<?php echo e($sortie['title']); ?>" class="<?php echo $champ; ?>"></label>
                            <label class="text-xs text-muted">Genre<select name="sortie_meta[genre_id]" class="<?php echo $champ; ?>"><option value="">—</option><?php echo optionsGenres($genres, $sortie['genre_id']); ?></select>
                                <a class="mt-1 inline-block text-xs underline" href="<?php echo SITE_URL; ?>/proposer-genre.php">Votre genre manque ? Proposez-le</a></label>
                            <label class="text-xs text-muted">Langue<select name="sortie_meta[language]" class="<?php echo $champ; ?>"><option value="">—</option><?php foreach (Publication::LANGUES as $c => $l): ?><option value="<?php echo e($c); ?>"<?php echo $sortie['language'] === $c ? ' selected' : ''; ?>><?php echo e($l); ?></option><?php endforeach; ?></select></label>
                            <label class="text-xs text-muted">Date de sortie<input type="date" name="sortie_meta[release_date]" value="<?php echo e($sortie['release_date'] ?? ''); ?>" class="<?php echo $champ; ?>"></label>
                        </div>
                        <label class="block text-xs text-muted">Description<textarea name="sortie_meta[description]" rows="3" class="<?php echo $champ; ?>"><?php echo e($sortie['description'] ?? ''); ?></textarea></label>
                        <?php foreach ($titres as $t): $n = 'titres[' . (int) $t['id'] . ']'; ?>
                            <fieldset class="rounded-2xl border border-white/10 p-4">
                                <legend class="px-2 text-xs text-muted">Titre n°<?php echo (int) $t['track_number']; ?></legend>
                                <div class="grid gap-3 sm:grid-cols-[80px_1fr_1fr]">
                                    <label class="text-xs text-muted">N°<input type="number" min="1" name="<?php echo $n; ?>[track_number]" value="<?php echo (int) $t['track_number']; ?>" class="<?php echo $champ; ?>"></label>
                                    <label class="text-xs text-muted">Titre<input name="<?php echo $n; ?>[title]" value="<?php echo e($t['title']); ?>" class="<?php echo $champ; ?>"></label>
                                    <label class="text-xs text-muted">Genre<select name="<?php echo $n; ?>[genre_id]" class="<?php echo $champ; ?>"><option value="">(celui de la sortie)</option><?php echo optionsGenres($genres, $t['genre_id']); ?></select></label>
                                </div>
                                <label class="mt-3 block text-xs text-muted">Credits (auteur, compositeur, producteur…)<input name="<?php echo $n; ?>[credits]" value="<?php echo e($t['credits'] ?? ''); ?>" class="<?php echo $champ; ?>"></label>
                                <label class="mt-3 flex items-center gap-2 text-xs text-muted"><input type="checkbox" name="<?php echo $n; ?>[explicit]" value="1"<?php echo (int) $t['explicit_content'] === 1 ? ' checked' : ''; ?>> Contenu explicite</label>
                                <details class="mt-2 text-xs text-muted"><summary>Paroles (facultatif)</summary><textarea name="<?php echo $n; ?>[lyrics]" rows="4" class="<?php echo $champ; ?>"><?php echo e($t['lyrics'] ?? ''); ?></textarea></details>
                            </fieldset>
                        <?php endforeach; ?>
                        <button class="rounded-full bg-accent px-5 py-2.5 text-sm font-semibold text-white">Enregistrer et continuer</button>
                    </form>

                <?php elseif ($etape === 'prix'): ?>
                    <h2 class="text-lg font-semibold text-text">Prix</h2>
                    <form method="POST" class="mt-4 space-y-4">
                        <?php echo csrfField(); ?><input type="hidden" name="action" value="prix"><input type="hidden" name="sortie" value="<?php echo $releaseId; ?>">
                        <label class="flex items-center gap-2 text-sm text-text"><input type="checkbox" name="gratuit" value="1"<?php echo (int) $sortie['is_free'] === 1 ? ' checked' : ''; ?><?php echo $peutVendre ? '' : ' checked disabled'; ?>> Sortie gratuite</label>
                        <?php if (!$peutVendre): ?><input type="hidden" name="gratuit" value="1"><p class="text-xs text-muted">Niveau Decouverte : la vente s'ouvre au niveau Verifie.</p><?php endif; ?>
                        <?php foreach ($titres as $t): ?>
                            <label class="flex items-center justify-between gap-3 text-sm text-text"><?php echo e($t['title']); ?>
                                <input name="prix[<?php echo (int) $t['id']; ?>]" inputmode="numeric" value="<?php echo (float) $t['price'] > 0 ? (int) $t['price'] : ''; ?>" placeholder="<?php echo (int) Tarifs::regle('track')['suggere']; ?>" class="w-32 rounded-xl border border-white/10 bg-bg px-3 py-2 text-right text-sm text-text"></label>
                        <?php endforeach; ?>
                        <p class="text-xs text-muted"><?php echo e(Tarifs::indication('track')); ?></p>
                        <label class="flex items-center justify-between gap-3 text-sm text-text">Prix de la sortie complete
                            <input name="prix_sortie" inputmode="numeric" value="<?php echo $sortie['price_bundle'] !== null ? (int) $sortie['price_bundle'] : ''; ?>" placeholder="<?php echo (int) (Tarifs::regle('release', $sortie['format'])['suggere'] ?? 0); ?>" class="w-32 rounded-xl border border-white/10 bg-bg px-3 py-2 text-right text-sm text-text"></label>
                        <p class="text-xs text-muted"><?php echo e(Tarifs::indication('release', $sortie['format'])); ?></p>
                        <button class="rounded-full bg-accent px-5 py-2.5 text-sm font-semibold text-white">Enregistrer et continuer</button>
                    </form>

                <?php else: $manques = Publication::verifier($releaseId); ?>
                    <h2 class="text-lg font-semibold text-text">Recapitulatif</h2>
                    <p class="mt-2 text-sm text-muted"><?php echo e(Sorties::libelle($sortie['format'])); ?> · <?php echo count($titres); ?> titre(s) · <?php echo (int) $sortie['is_free'] === 1 ? 'gratuit' : e(number_format((float) $sortie['price_bundle'], 0, ',', ' ')) . ' FCFA'; ?></p>
                    <?php if ($manques !== []): ?>
                        <ul class="mt-4 space-y-1 text-sm text-amber-200" data-manques><?php foreach ($manques as $m): ?><li>• <?php echo e($m); ?></li><?php endforeach; ?></ul>
                    <?php else: ?>
                        <p class="mt-4 text-sm text-emerald-300">Tout est pret. Apres soumission, la sortie passe en moderation ; vous serez prevenu de la decision.</p>
                    <?php endif; ?>
                    <form method="POST" class="mt-6">
                        <?php echo csrfField(); ?><input type="hidden" name="action" value="soumettre"><input type="hidden" name="sortie" value="<?php echo $releaseId; ?>">
                        <button class="rounded-full bg-accent px-5 py-3 text-sm font-semibold text-white"<?php echo $manques !== [] ? ' disabled' : ''; ?>>Soumettre a la moderation</button>
                    </form>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
</main>

<?php include 'includes/footer-tailwind.php'; ?>
