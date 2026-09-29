<?php
/**
 * Versements aux artistes, cote finance (PAYOUT-02, PAYOUT-03).
 *
 * Consultation : finance.transaction.lire.
 * Valider, refuser, suspendre, verifier un compte, ajuster un solde :
 *   finance.versement.creer.
 * Executer : finance.versement.executer.
 *
 * La base refuse qu'une meme personne demande et valide, ou valide et
 * execute (contraintes CHECK de `payouts`) : l'ecran l'annonce, il ne peut
 * pas le contourner.
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/paiement/chargement.php';

Autorisations::exiger('finance.transaction.lire');
$moi = (int) $_SESSION['user_id'];
$peutValider = Autorisations::peut('finance.versement.creer');
$peutExecuter = Autorisations::peut('finance.versement.executer');
$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['versement'] ?? 0);
    $motif = (string) ($_POST['motif'] ?? '');
    Autorisations::exiger($action === 'executer' ? 'finance.versement.executer' : 'finance.versement.creer');

    $resultat = match ($action) {
        'approuver' => Versements::approuver($id, $moi),
        'refuser'   => Versements::decider($id, 'rejected', $motif, $moi),
        'suspendre' => Versements::decider($id, 'on_hold', $motif, $moi),
        'executer'  => Versements::executer($id, $moi),
        'verifier'  => Versements::verifierCompte((int) ($_POST['artiste'] ?? 0), $motif, $moi)
            ? ['succes' => true, 'message' => 'Compte verifie.'] : ['succes' => false, 'message' => 'Verification impossible : motif (5 caracteres) et compte non encore verifie.'],
        'ajuster'   => Versements::ajuster((int) ($_POST['artiste'] ?? 0), (float) str_replace([' ', ','], ['', '.'], (string) ($_POST['montant'] ?? '0')), $motif, $moi)
            ? ['succes' => true, 'message' => 'Ajustement enregistre.'] : ['succes' => false, 'message' => 'Ajustement refuse : montant non nul et motif obligatoire.'],
        default     => ['succes' => false, 'message' => 'Action inconnue.'],
    };
    if ($resultat['succes']) {
        setFlashMessage(FLASH_SUCCESS, $resultat['message']);
        redirect(SITE_URL . '/admin/versements.php');
    }
    $erreur = $resultat['message'];
}

$db = TchadokDatabase::getInstance()->getConnection();
$file = Versements::aTraiter();
$comptes = $db->query(
    'SELECT ac.*, a.stage_name, u.first_name, u.last_name FROM artist_payout_accounts ac
       JOIN artists a ON a.id = ac.artist_id JOIN users u ON u.id = a.user_id WHERE ac.verified_at IS NULL ORDER BY ac.updated_at'
)->fetchAll(PDO::FETCH_ASSOC);
$recents = $db->query(
    "SELECT p.*, a.stage_name FROM payouts p JOIN artists a ON a.id = p.artist_id WHERE p.status IN ('paid', 'rejected') ORDER BY p.updated_at DESC LIMIT 15"
)->fetchAll(PDO::FETCH_ASSOC);
$artistes = $db->query('SELECT id, stage_name FROM artists WHERE deleted_at IS NULL ORDER BY stage_name LIMIT 500')->fetchAll(PDO::FETCH_KEY_PAIR);
$fcfa = static fn ($v): string => number_format((float) $v, 0, ',', ' ') . ' FCFA';
$bouton = static fn (string $action, string $libelle, string $classe = 'border border-white/15 text-text'): string =>
    '<button name="action" value="' . $action . '" class="rounded-full px-3 py-1.5 text-xs font-semibold ' . $classe . '">' . $libelle . '</button>';

$pageTitle = 'Versements';
$pageDescription = 'Versements aux artistes';
$hideTopNav = true;
$hideFooter = true;

include __DIR__ . '/../includes/header-tailwind.php';
?>

<main class="min-h-screen bg-[radial-gradient(circle_at_top_right,rgba(47,109,224,0.18),transparent_28%),#0B0F17] pb-16 pt-8">
    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        <section class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2 sm:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-[0.28em] text-muted">Console admin · Finance</p>
                    <h1 class="mt-2 text-3xl font-display font-bold text-text">Versements aux artistes</h1>
                    <p class="mt-3 max-w-3xl text-sm text-muted">L'artiste demande, une personne valide, une <strong>autre</strong> execute : la base refuse toute confusion de ces roles. Un envoi en echec revient en file et se reprend sans double paiement.</p>
                </div>
                <a href="<?php echo SITE_URL; ?>/admin-dashboard.php" class="rounded-full border border-white/15 px-4 py-2 text-xs font-semibold text-text hover:bg-white/10"><i class="fas fa-arrow-left mr-2"></i>Console</a>
            </div>
            <?php displayFlashMessages(); ?>
            <?php if ($erreur): ?><div class="alert alert-danger mt-6" role="alert"><i class="fas fa-exclamation-circle mt-0.5"></i><span><?php echo e($erreur); ?></span></div><?php endif; ?>
        </section>

        <section class="rounded-3xl border border-white/10 bg-surface/75 shadow-elev-2" aria-labelledby="titre-file">
            <h2 id="titre-file" class="px-6 pt-6 text-lg font-semibold text-text">A traiter <span class="text-muted">(<?php echo count($file); ?>)</span></h2>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-white/10 text-xs uppercase tracking-wide text-muted">
                        <tr><th class="px-4 py-3">Demande</th><th class="px-4 py-3">Artiste</th><th class="px-4 py-3">Montant</th><th class="px-4 py-3">Vers</th><th class="px-4 py-3">Etat</th><th class="px-4 py-3">Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php if (!$file): ?><tr><td colspan="6" class="px-4 py-6 text-center text-muted">Aucun versement en attente.</td></tr><?php endif; ?>
                        <?php foreach ($file as $v): ?>
                            <tr class="border-b border-white/5 align-top">
                                <td class="whitespace-nowrap px-4 py-3 text-xs text-muted">#<?php echo (int) $v['id']; ?> · <?php echo e(date('d/m/Y', strtotime((string) ($v['requested_at'] ?? $v['created_at'])))); ?><span class="block">par <?php echo e($v['demandeur'] ?? '?'); ?></span></td>
                                <td class="px-4 py-3 text-text"><?php echo e($v['stage_name']); ?></td>
                                <td class="whitespace-nowrap px-4 py-3 font-semibold text-text"><?php echo e($fcfa($v['net'])); ?><a class="block text-xs font-normal underline" href="<?php echo SITE_URL; ?>/releve-versement.php?id=<?php echo (int) $v['id']; ?>">releve</a></td>
                                <td class="px-4 py-3 text-xs text-muted"><?php echo e(Factures::libelleMoyen($v['method'])); ?> <?php echo e($v['destination']); ?><span class="block"><?php echo e($v['holder_name'] ?? ''); ?></span></td>
                                <td class="px-4 py-3 text-xs text-text"><?php echo e(Versements::LIBELLES[$v['status']] ?? $v['status']); ?>
                                    <?php if ($v['validateur']): ?><span class="block text-muted">valide par <?php echo e($v['validateur']); ?></span><?php endif; ?>
                                    <?php if ($v['failure_reason']): ?><span class="block text-amber-300"><?php echo e($v['failure_reason']); ?></span><?php endif; ?>
                                    <?php if ($v['decision_reason']): ?><span class="block text-muted"><?php echo e($v['decision_reason']); ?></span><?php endif; ?></td>
                                <td class="px-4 py-3">
                                    <form method="POST" class="flex min-w-[280px] flex-wrap gap-2">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="versement" value="<?php echo (int) $v['id']; ?>">
                                        <?php if ($peutValider && in_array($v['status'], ['draft', 'on_hold'], true)): ?><?php echo $bouton('approuver', 'Valider', 'bg-accent text-white'); ?><?php endif; ?>
                                        <?php if ($peutExecuter && in_array($v['status'], ['approved', 'failed', 'processing'], true)): ?><?php echo $bouton('executer', $v['status'] === 'approved' ? 'Executer' : 'Reprendre l\'envoi', 'bg-emerald-600 text-white'); ?><?php endif; ?>
                                        <?php if ($peutValider && $v['status'] !== 'processing'): ?>
                                            <label class="sr-only" for="motif-<?php echo (int) $v['id']; ?>">Motif</label>
                                            <input id="motif-<?php echo (int) $v['id']; ?>" name="motif" placeholder="Motif (refus, suspension)" class="w-full rounded-xl border border-white/10 bg-bg px-3 py-1.5 text-xs text-text">
                                            <?php echo $bouton('refuser', 'Refuser'); ?><?php echo $bouton('suspendre', 'Suspendre'); ?>
                                        <?php endif; ?>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2" aria-labelledby="titre-comptes">
                <h2 id="titre-comptes" class="text-lg font-semibold text-text">Comptes a verifier</h2>
                <p class="mt-1 text-xs text-muted">Le compte doit etre au nom de l'artiste (piece d'identite, contrat). Tout changement de numero revient ici.</p>
                <?php if (!$comptes): ?><p class="mt-4 text-sm text-muted">Aucun.</p><?php endif; ?>
                <?php foreach ($comptes as $c): ?>
                    <form method="POST" class="mt-4 rounded-2xl border border-white/10 bg-white/5 p-4">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="artiste" value="<?php echo (int) $c['artist_id']; ?>">
                        <p class="text-sm text-text"><?php echo e($c['stage_name']); ?> (<?php echo e(trim($c['first_name'] . ' ' . $c['last_name'])); ?>)</p>
                        <p class="text-xs text-muted"><?php echo e(Factures::libelleMoyen($c['method'])); ?> <?php echo e($c['msisdn']); ?> — titulaire declare : <?php echo e($c['holder_name']); ?></p>
                        <?php if ($peutValider): ?>
                            <div class="mt-2 flex gap-2"><input name="motif" required minlength="5" placeholder="Comment le titulaire a ete verifie" class="w-full rounded-xl border border-white/10 bg-bg px-3 py-1.5 text-xs text-text"><?php echo $bouton('verifier', 'Verifier', 'bg-accent text-white'); ?></div>
                        <?php endif; ?>
                    </form>
                <?php endforeach; ?>
            </section>

            <section class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2" aria-labelledby="titre-ajustement">
                <h2 id="titre-ajustement" class="text-lg font-semibold text-text">Ajuster un solde</h2>
                <p class="mt-1 text-xs text-muted">Correction, avance (montant positif) ou retenue (negatif). Motif obligatoire ; l'ajustement ne se modifie ni ne se supprime.</p>
                <?php if ($peutValider): ?>
                    <form method="POST" class="mt-4 space-y-3">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="ajuster">
                        <label class="block text-xs text-muted">Artiste
                            <select name="artiste" class="mt-1 w-full rounded-xl border border-white/10 bg-bg px-3 py-2 text-sm text-text"><?php foreach ($artistes as $aid => $nom): ?><option value="<?php echo (int) $aid; ?>"><?php echo e($nom); ?></option><?php endforeach; ?></select></label>
                        <label class="block text-xs text-muted">Montant (FCFA)<input name="montant" required class="mt-1 w-full rounded-xl border border-white/10 bg-bg px-3 py-2 text-sm text-text"></label>
                        <label class="block text-xs text-muted">Motif<input name="motif" required minlength="5" class="mt-1 w-full rounded-xl border border-white/10 bg-bg px-3 py-2 text-sm text-text"></label>
                        <button class="rounded-full bg-accent px-4 py-2 text-xs font-semibold text-white">Enregistrer l'ajustement</button>
                    </form>
                <?php endif; ?>
                <h3 class="mt-6 text-sm font-semibold text-text">Derniers versements traites</h3>
                <ul class="mt-2 text-xs text-muted">
                    <?php foreach ($recents as $v): ?>
                        <li class="py-1">#<?php echo (int) $v['id']; ?> · <?php echo e($v['stage_name']); ?> · <?php echo e($fcfa($v['net'])); ?> · <?php echo e(Versements::LIBELLES[$v['status']] ?? $v['status']); ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        </div>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer-tailwind.php'; ?>
