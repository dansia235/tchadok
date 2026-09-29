<?php
/**
 * File de moderation (MOD-02). Permission : catalogue.moderer.
 *
 * Liste des soumissions en attente (anciennete, artiste, format, genre,
 * moderateur), fiche de revue (ecoute des titres, metadonnees, pochette,
 * controles automatiques, droits declares, historique de l'artiste), grille
 * formalisee et decision : approuver, refuser, demander une correction.
 * Indicateurs : volume en attente, delai moyen, refus par motif.
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/media-access.php';
require_once __DIR__ . '/../includes/duree-audio.php';
require_once __DIR__ . '/../includes/controles-depot.php';
require_once __DIR__ . '/../includes/comptes.php';
require_once __DIR__ . '/../includes/taxonomie.php';
require_once __DIR__ . '/../includes/moderation.php';
require_once __DIR__ . '/../includes/paiement/chargement.php';
require_once __DIR__ . '/../includes/dossier-artiste.php';

Autorisations::exiger('catalogue.moderer');
$moi = (int) $_SESSION['user_id'];
$erreur = '';
$revueId = (int) ($_GET['revue'] ?? $_POST['revue'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $r = match ((string) ($_POST['action'] ?? '')) {
        'prendre' => Moderation::assigner($revueId, $moi),
        'decider' => Moderation::decider($revueId, (string) ($_POST['decision'] ?? ''), (string) ($_POST['motif_code'] ?? ''), (string) ($_POST['motif'] ?? ''), (array) ($_POST['grille'] ?? []), $moi),
        default   => ['succes' => false, 'message' => 'Action inconnue.'],
    };
    if ($r['succes']) {
        setFlashMessage(FLASH_SUCCESS, $r['message']);
        redirect(SITE_URL . '/admin/moderation.php' . (($_POST['action'] ?? '') === 'prendre' ? '?revue=' . $revueId : ''));
    }
    $erreur = $r['message'];
}

$db = TchadokDatabase::getInstance()->getConnection();
$file = Moderation::fileAttente();
$indicateurs = Moderation::indicateurs();
$revue = $revueId > 0 ? Moderation::revue($revueId) : null;
$contenu = null;
$titres = [];
$dossier = null;
if ($revue) {
    $table = $revue['content_type'] === 'release' ? 'releases' : 'tracks';
    $stmt = $db->prepare("SELECT c.*, a.stage_name, g.name AS genre FROM {$table} c JOIN artists a ON a.id = c.artist_id LEFT JOIN genres g ON g.id = c.genre_id WHERE c.id = ?");
    $stmt->execute([$revue['content_id']]);
    $contenu = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt = $db->prepare($revue['content_type'] === 'release'
        ? 'SELECT t.*, g.name AS genre FROM tracks t LEFT JOIN genres g ON g.id = t.genre_id WHERE t.release_id = ? AND t.deleted_at IS NULL ORDER BY t.track_number, t.id'
        : 'SELECT t.*, g.name AS genre FROM tracks t LEFT JOIN genres g ON g.id = t.genre_id WHERE t.id = ?');
    $stmt->execute([$revue['content_id']]);
    $titres = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $dossier = DossierArtiste::dossier((int) $revue['artist_id']);
}

$pageTitle = 'Moderation';
$hideTopNav = true;
$hideFooter = true;
include __DIR__ . '/../includes/header-tailwind.php';
?>

<main class="min-h-screen bg-bg pb-16 pt-8">
    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        <section class="rounded-3xl border border-white/10 bg-surface/75 p-6 sm:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-[0.28em] text-muted">Console admin · Catalogue</p>
                    <h1 class="mt-2 text-3xl font-display font-bold text-text">Moderation</h1>
                </div>
                <a href="<?php echo SITE_URL; ?>/admin-dashboard.php" class="rounded-full border border-white/15 px-4 py-2 text-xs font-semibold text-text hover:bg-white/10"><i class="fas fa-arrow-left mr-2"></i>Console</a>
            </div>
            <?php displayFlashMessages(); ?>
            <?php if ($erreur): ?><div class="alert alert-danger mt-6" role="alert"><i class="fas fa-exclamation-circle mt-0.5"></i><span><?php echo e($erreur); ?></span></div><?php endif; ?>
            <dl class="mt-6 grid gap-3 text-sm sm:grid-cols-3">
                <div class="rounded-2xl bg-white/5 p-4"><dt class="text-xs text-muted">En attente</dt><dd class="mt-1 text-2xl font-semibold text-text" data-en-attente><?php echo (int) $indicateurs['en_attente']; ?></dd></div>
                <div class="rounded-2xl bg-white/5 p-4"><dt class="text-xs text-muted">Delai moyen de traitement (30 j)</dt><dd class="mt-1 text-2xl font-semibold text-text"><?php echo $indicateurs['delai_moyen_heures'] === null ? '—' : e($indicateurs['delai_moyen_heures']) . ' h'; ?></dd></div>
                <div class="rounded-2xl bg-white/5 p-4"><dt class="text-xs text-muted">Refus et corrections par motif (30 j)</dt><dd class="mt-1 text-xs text-text"><?php echo $indicateurs['refus_par_motif'] === [] ? '—' : e(implode(', ', array_map(fn($k, $v) => (Moderation::MOTIFS[$k] ?? $k) . ' : ' . $v, array_keys($indicateurs['refus_par_motif']), $indicateurs['refus_par_motif']))); ?></dd></div>
            </dl>
        </section>

        <?php if ($revue && $contenu): ?>
            <section class="rounded-3xl border border-white/10 bg-surface/75 p-6" aria-labelledby="titre-revue">
                <h2 id="titre-revue" class="text-xl font-semibold text-text"><?php echo e($contenu['title']); ?> — <?php echo e($contenu['stage_name']); ?></h2>
                <p class="mt-1 text-sm text-muted"><?php echo e($revue['content_type'] === 'release' ? Sorties::libelle((string) $contenu['format']) : 'Titre'); ?> · genre <?php echo e($contenu['genre'] ?? '—'); ?> · langue <?php echo e($contenu['language'] ?? '—'); ?> · sortie le <?php echo e($contenu['release_date'] ?? '—'); ?>
                    · soumis le <?php echo e(date('d/m/Y H:i', strtotime((string) $revue['submitted_at']))); ?></p>
                <div class="mt-4 grid gap-6 lg:grid-cols-[220px_1fr]">
                    <div>
                        <?php if (!empty($contenu['cover_image'])): ?><img src="<?php echo e(SITE_URL . '/' . $contenu['cover_image']); ?>" alt="Pochette" class="w-full rounded-2xl border border-white/10"><?php else: ?><p class="text-xs text-amber-200">Pas de pochette.</p><?php endif; ?>
                        <p class="mt-3 text-xs text-muted">Dossier artiste : <?php echo e(DossierArtiste::NIVEAUX[$dossier['level']] ?? $dossier['level']); ?>, droits declares <?php echo $dossier['rights_declared_at'] ? 'le ' . e(date('d/m/Y', strtotime((string) $dossier['rights_declared_at']))) : 'NON'; ?>.</p>
                        <p class="mt-1 text-xs text-muted">Historique : <?php echo (int) ($file[array_search($revueId, array_map('intval', array_column($file, 'id')), true)]['approuves'] ?? 0); ?> approuve(s), <?php echo (int) ($file[array_search($revueId, array_map('intval', array_column($file, 'id')), true)]['refuses'] ?? 0); ?> refuse(s).</p>
                    </div>
                    <ol class="space-y-4">
                        <?php foreach ($titres as $t): ?>
                            <li class="rounded-2xl border border-white/10 p-4">
                                <p class="text-sm font-semibold text-text"><?php echo (int) $t['track_number']; ?>. <?php echo e($t['title']); ?><?php echo (int) $t['explicit_content'] === 1 ? ' <span class="text-xs text-rose-300">explicite</span>' : ''; ?></p>
                                <p class="text-xs text-muted"><?php echo e($t['genre'] ?? '—'); ?> · credits : <?php echo e($t['credits'] ?? '—'); ?> · <?php echo (int) $t['audio_bitrate']; ?> kbit/s, <?php echo e((string) ($t['audio_sample_rate'] ?? '?')); ?> Hz · <?php echo (int) $t['price']; ?> FCFA</p>
                                <audio controls preload="none" class="mt-2 w-full" src="<?php echo e(MediaAccess::urlSignee((int) $t['id'], MediaAccess::TYPE_AUDIO)); ?>"></audio>
                                <ul class="mt-2 text-xs">
                                    <?php foreach (ControlesDepot::resultats((int) $t['id']) as $c): ?>
                                        <li class="<?php echo $c['result'] === 'signal' ? 'text-amber-200' : ($c['result'] === 'non_verifie' ? 'text-muted' : 'text-emerald-300'); ?>" data-controle="<?php echo e($c['check_code']); ?>"><?php echo e($c['check_code']); ?> : <?php echo e($c['detail']); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </div>

                <?php if ($revue['decision'] === null): ?>
                    <?php if ($revue['assigned_to'] === null): ?>
                        <form method="POST" class="mt-6"><?php echo csrfField(); ?><input type="hidden" name="action" value="prendre"><input type="hidden" name="revue" value="<?php echo $revueId; ?>">
                            <button class="rounded-full bg-accent px-5 py-2.5 text-sm font-semibold text-white">Prendre ce dossier</button></form>
                    <?php elseif ((int) $revue['assigned_to'] !== $moi): ?>
                        <p class="mt-6 text-sm text-amber-200">Dossier pris en charge par un autre moderateur.</p>
                    <?php else: ?>
                        <form method="POST" class="mt-6 space-y-4 rounded-2xl border border-white/10 bg-white/5 p-4">
                            <?php echo csrfField(); ?><input type="hidden" name="action" value="decider"><input type="hidden" name="revue" value="<?php echo $revueId; ?>">
                            <fieldset><legend class="text-sm font-semibold text-text">Grille de revue</legend>
                                <?php foreach (Moderation::GRILLE as $cle => $libelle): ?><label class="mt-1 flex items-center gap-2 text-sm text-text"><input type="checkbox" name="grille[]" value="<?php echo e($cle); ?>"> <?php echo e($libelle); ?></label><?php endforeach; ?>
                            </fieldset>
                            <div class="grid gap-3 sm:grid-cols-[200px_1fr]">
                                <label class="text-xs text-muted">Motif (refus, correction)<select name="motif_code" class="mt-1 w-full rounded-xl border border-white/10 bg-bg px-3 py-2 text-sm text-text"><?php foreach (Moderation::MOTIFS as $c => $l): ?><option value="<?php echo e($c); ?>"><?php echo e($l); ?></option><?php endforeach; ?></select></label>
                                <label class="text-xs text-muted">Explication transmise a l'artiste<input name="motif" class="mt-1 w-full rounded-xl border border-white/10 bg-bg px-3 py-2 text-sm text-text"></label>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button name="decision" value="approved" class="rounded-full bg-emerald-600 px-4 py-2 text-xs font-semibold text-white">Approuver</button>
                                <button name="decision" value="correction" class="rounded-full border border-white/15 px-4 py-2 text-xs font-semibold text-text">Demander une correction</button>
                                <button name="decision" value="rejected" class="rounded-full bg-rose-600 px-4 py-2 text-xs font-semibold text-white">Refuser</button>
                            </div>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <section class="rounded-3xl border border-white/10 bg-surface/75 shadow-elev-2" aria-labelledby="titre-file">
            <h2 id="titre-file" class="px-6 pt-6 text-lg font-semibold text-text">File d'attente</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-white/10 text-xs uppercase tracking-wide text-muted"><tr><th class="px-4 py-3">Anciennete</th><th class="px-4 py-3">Contenu</th><th class="px-4 py-3">Artiste</th><th class="px-4 py-3">Format</th><th class="px-4 py-3">Genre</th><th class="px-4 py-3">Moderateur</th><th class="px-4 py-3"></th></tr></thead>
                    <tbody>
                        <?php if ($file === []): ?><tr><td colspan="7" class="px-4 py-6 text-center text-muted">Aucune soumission en attente.</td></tr><?php endif; ?>
                        <?php foreach ($file as $f): ?>
                            <tr class="border-b border-white/5" data-revue="<?php echo (int) $f['id']; ?>">
                                <td class="px-4 py-3 text-xs <?php echo (int) $f['anciennete_heures'] > 72 ? 'text-rose-300' : 'text-muted'; ?>"><?php echo (int) $f['anciennete_heures']; ?> h</td>
                                <td class="px-4 py-3 text-text"><?php echo e($f['titre']); ?></td>
                                <td class="px-4 py-3 text-muted"><?php echo e($f['stage_name']); ?></td>
                                <td class="px-4 py-3 text-muted"><?php echo e($f['format'] ? Sorties::libelle($f['format']) . ' (' . (int) $f['titres'] . ')' : 'titre'); ?></td>
                                <td class="px-4 py-3 text-muted"><?php echo e($f['genre'] ?? '—'); ?></td>
                                <td class="px-4 py-3 text-muted"><?php echo e($f['moderateur'] ?? '—'); ?></td>
                                <td class="px-4 py-3"><a class="text-xs underline" href="?revue=<?php echo (int) $f['id']; ?>">Revoir</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer-tailwind.php'; ?>
