<?php
/**
 * Validation des dossiers artistes (MOD-06). Permission : artiste.valider.
 *
 * Dossiers soumis, etapes, pieces d'identite (dechiffrees a la demande,
 * chaque consultation journalisee), decision motivee : valider a un niveau,
 * demander un complement, refuser.
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/comptes.php';
require_once __DIR__ . '/../includes/taxonomie.php';
require_once __DIR__ . '/../includes/paiement/chargement.php';
require_once __DIR__ . '/../includes/dossier-artiste.php';

Autorisations::exiger('artiste.valider');
$moi = (int) $_SESSION['user_id'];

// Piece d'identite : servie dechiffree, jamais mise en cache.
if (isset($_GET['piece'], $_GET['artiste'])) {
    $piece = DossierArtiste::lirePiece((int) $_GET['artiste'], (string) $_GET['piece'], $moi);
    if ($piece === null) {
        show404();
    }
    header('Content-Type: ' . $piece['mime']);
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: inline; filename="piece"');
    echo $piece['contenu'];
    exit;
}

$erreur = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $commission = trim((string) ($_POST['commission'] ?? '')) === '' ? null : (float) str_replace(',', '.', (string) $_POST['commission']);
    $r = DossierArtiste::decider((int) ($_POST['artiste'] ?? 0), (string) ($_POST['decision'] ?? ''), (string) ($_POST['niveau'] ?? ''), (string) ($_POST['motif'] ?? ''), $commission, $moi);
    if ($r['succes']) {
        setFlashMessage(FLASH_SUCCESS, $r['message']);
        redirect(SITE_URL . '/admin/dossiers-artistes.php');
    }
    $erreur = $r['message'];
}
$dossiers = DossierArtiste::aTraiter();
$champ = 'w-full rounded-xl border border-white/10 bg-bg px-2 py-1.5 text-xs text-text';

$pageTitle = 'Dossiers artistes';
$hideTopNav = true;
$hideFooter = true;
include __DIR__ . '/../includes/header-tailwind.php';
?>

<main class="min-h-screen bg-bg pb-16 pt-8">
    <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
        <section class="rounded-3xl border border-white/10 bg-surface/75 p-6 sm:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div><p class="text-xs uppercase tracking-[0.28em] text-muted">Console admin · Catalogue</p>
                    <h1 class="mt-2 text-3xl font-display font-bold text-text">Dossiers artistes</h1>
                    <p class="mt-2 text-sm text-muted">Un artiste non valide ne publie pas. Chaque ouverture d'une piece d'identite est journalisee.</p></div>
                <a href="<?php echo SITE_URL; ?>/admin-dashboard.php" class="rounded-full border border-white/15 px-4 py-2 text-xs font-semibold text-text hover:bg-white/10"><i class="fas fa-arrow-left mr-2"></i>Console</a>
            </div>
            <?php displayFlashMessages(); ?>
            <?php if ($erreur): ?><div class="alert alert-danger mt-6" role="alert"><span><?php echo e($erreur); ?></span></div><?php endif; ?>
        </section>

        <?php if ($dossiers === []): ?><p class="text-sm text-muted">Aucun dossier en attente.</p><?php endif; ?>
        <?php foreach ($dossiers as $d): $etapes = DossierArtiste::etapes((int) $d['artist_id']); ?>
            <article class="rounded-3xl border border-white/10 bg-surface/75 p-6" data-dossier="<?php echo (int) $d['artist_id']; ?>">
                <h2 class="text-lg font-semibold text-text"><?php echo e($d['stage_name']); ?> <span class="text-xs text-muted">soumis le <?php echo e(date('d/m/Y H:i', strtotime((string) $d['submitted_at']))); ?></span></h2>
                <p class="mt-1 text-xs text-muted"><?php echo e($d['email']); ?> · <?php echo e($d['phone'] ?? '—'); ?> · etapes : <?php echo e(implode(', ', array_map(fn($k, $v) => $k . ($v ? ' ✔' : ' ✘'), array_keys($etapes), $etapes))); ?></p>
                <p class="mt-2 text-sm text-muted"><?php echo e(mb_substr((string) $d['bio'], 0, 400)); ?></p>
                <p class="mt-2 text-xs"><a class="underline" target="_blank" rel="noopener" href="?artiste=<?php echo (int) $d['artist_id']; ?>&piece=identite">Piece d'identite</a> · <a class="underline" target="_blank" rel="noopener" href="?artiste=<?php echo (int) $d['artist_id']; ?>&piece=selfie">Selfie</a></p>
                <form method="POST" class="mt-4 grid gap-2 sm:grid-cols-[150px_140px_110px_1fr_auto] sm:items-end">
                    <?php echo csrfField(); ?><input type="hidden" name="artiste" value="<?php echo (int) $d['artist_id']; ?>">
                    <label class="text-xs text-muted">Decision<select name="decision" class="<?php echo $champ; ?>"><option value="valide">Valider</option><option value="a_completer">A completer</option><option value="refuse">Refuser</option></select></label>
                    <label class="text-xs text-muted">Niveau<select name="niveau" class="<?php echo $champ; ?>"><option value="decouverte">Decouverte</option><option value="verifie">Verifie (identite controlee)</option><option value="partenaire">Partenaire</option></select></label>
                    <label class="text-xs text-muted">Commission %<input name="commission" placeholder="partenaire" class="<?php echo $champ; ?>"></label>
                    <label class="text-xs text-muted">Motif (obligatoire si complement ou refus)<input name="motif" class="<?php echo $champ; ?>"></label>
                    <button class="rounded-full bg-accent px-4 py-2 text-xs font-semibold text-white">Enregistrer</button>
                </form>
            </article>
        <?php endforeach; ?>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer-tailwind.php'; ?>
