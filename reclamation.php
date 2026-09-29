<?php
/**
 * Reclamation sur un achat (SHOP-07, cote client).
 *
 *   reclamation.php?commande=TCHK-2026-XXXXXXXX
 *
 * Le client explique son probleme ; l'equipe finance decide (rembourser ou
 * refuser, avec motif) et il en est informe par e-mail. Delai de reponse
 * annonce : Remboursements::DELAI_REPONSE.
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/paiement/chargement.php';

$reference = (string) ($_GET['commande'] ?? $_POST['commande'] ?? '');
if (!isLoggedIn()) {
    redirect(SITE_URL . '/login.php?redirect=' . urlencode('/reclamation.php?commande=' . $reference));
}
$userId = (int) $_SESSION['user_id'];
$db = TchadokDatabase::getInstance()->getConnection();

$stmt = $db->prepare('SELECT id, reference, status, total, paid_at FROM orders WHERE reference = ? AND user_id = ?');
$stmt->execute([$reference, $userId]);
$commande = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$commande) {
    show404();
}

$erreur = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    LimiteDebit::appliquer('contact');
    $resultat = Remboursements::demander($userId, $reference, (string) ($_POST['motif'] ?? ''), (string) ($_POST['message'] ?? ''));
    if ($resultat['succes']) {
        setFlashMessage(FLASH_SUCCESS, $resultat['message']);
        redirect(SITE_URL . '/reclamation.php?commande=' . urlencode($reference));
    }
    $erreur = $resultat['message'];
}

$dossier = Remboursements::dossier((int) $commande['id']);
$ouvert = $dossier !== null && in_array($dossier['status'], ['demande', 'en_cours', 'manuel', 'echoue'], true);
$possible = !$ouvert && in_array($commande['status'], ['paid', 'review'], true) && ($dossier === null || $dossier['status'] === 'refusee');

$pageTitle = 'Reclamation';
$pageDescription = 'Un probleme avec un achat';

include 'includes/header-tailwind.php';
?>

<main class="min-h-screen bg-[radial-gradient(circle_at_top_right,rgba(47,109,224,0.18),transparent_28%),#0B0F17] pb-16 pt-24">
    <section class="mx-auto max-w-2xl px-4 sm:px-6">
        <div class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2 sm:p-8">
            <h1 class="text-2xl font-display font-semibold text-text">Un probleme avec cet achat ?</h1>
            <p class="mt-1 text-sm text-muted">Commande <span class="font-mono text-text"><?php echo e($commande['reference']); ?></span> · <?php echo e(number_format((float) $commande['total'], 0, ',', ' ')); ?> FCFA</p>

            <?php displayFlashMessages(); ?>
            <?php if ($erreur): ?>
                <div class="alert alert-danger mt-6" role="alert"><i class="fas fa-exclamation-circle mt-0.5"></i><span><?php echo e($erreur); ?></span></div>
            <?php endif; ?>

            <?php if ($dossier !== null): ?>
                <div class="alert <?php echo $dossier['status'] === 'refusee' ? 'alert-warning' : 'alert-info'; ?> mt-6" role="status">
                    <i class="fas fa-circle-info mt-0.5"></i>
                    <span>
                        <strong><?php echo e(Remboursements::LIBELLES[$dossier['status']] ?? $dossier['status']); ?></strong>
                        <?php if ($dossier['status'] === 'demande'): ?> — reponse sous <?php echo e(Remboursements::DELAI_REPONSE); ?>.<?php endif; ?>
                        <?php if ($dossier['status'] === 'refusee' && $dossier['decision_reason']): ?><br>Motif : <?php echo e($dossier['decision_reason']); ?><?php endif; ?>
                    </span>
                </div>
            <?php endif; ?>

            <?php if ($possible): ?>
                <form method="POST" class="mt-6 space-y-5">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="commande" value="<?php echo e($commande['reference']); ?>">
                    <fieldset>
                        <legend class="text-sm font-semibold text-text">Que s'est-il passe ?</legend>
                        <div class="mt-3 space-y-2">
                            <?php foreach (Remboursements::MOTIFS_CLIENT as $code => $libelle): ?>
                                <label class="flex cursor-pointer items-center gap-3 rounded-2xl border border-white/10 bg-bg/60 px-4 py-3 text-sm text-text has-[:checked]:border-accent">
                                    <input type="radio" name="motif" value="<?php echo e($code); ?>" required> <?php echo e($libelle); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                    <div>
                        <label for="message" class="text-sm font-semibold text-text">Precisions (facultatif)</label>
                        <textarea id="message" name="message" rows="4" maxlength="1000" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text"></textarea>
                    </div>
                    <button type="submit" class="w-full rounded-full bg-accent px-5 py-3 text-sm font-semibold text-white">Envoyer ma reclamation</button>
                    <p class="text-xs text-muted">Notre equipe vous repond sous <?php echo e(Remboursements::DELAI_REPONSE); ?>, par e-mail. En cas de remboursement, les contenus de la commande ne seront plus accessibles.</p>
                </form>
            <?php elseif ($dossier === null): ?>
                <p class="mt-6 text-sm text-muted">Cette commande ne peut pas faire l'objet d'une reclamation en ligne. Ecrivez-nous depuis la page <a class="underline" href="<?php echo SITE_URL; ?>/contact.php">Contact</a>.</p>
            <?php endif; ?>

            <a href="<?php echo SITE_URL; ?>/bibliotheque.php" class="mt-6 inline-flex text-sm text-text underline">Retour a ma bibliotheque</a>
        </div>
    </section>
</main>

<?php include 'includes/footer-tailwind.php'; ?>
