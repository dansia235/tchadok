<?php
/**
 * Mon abonnement (SUB-03) : statut, echeance, periodes, resiliation.
 *
 * Resiliation en un clic : plus de rappel, l'acces reste jusqu'a la fin de
 * la periode deja payee (exigence de loyaute contractuelle, SUB-02).
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/paiement/chargement.php';

if (!isLoggedIn()) {
    redirect(SITE_URL . '/login.php?redirect=' . urlencode('/abonnement.php'));
}
$userId = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'resilier') {
    if (Abonnements::resilier($userId)) {
        setFlashMessage(FLASH_SUCCESS, 'Abonnement resilie. Vos avantages restent actifs jusqu\'a la fin de la periode payee.');
    }
    redirect(SITE_URL . '/abonnement.php');
}

$situation = Abonnements::situation($userId);
$premium = Abonnements::estPremium($userId);
$courant = $situation['courant'];
$resilie = $courant !== null && $courant['cancelled_at'] !== null;
$renouvelable = $situation['suivant'] === null && ($situation['fin'] === null || strtotime($situation['fin']) - time() <= 7 * 86400);
$fcfa = static fn ($v): string => number_format((float) $v, 0, ',', ' ') . ' FCFA';
$etat = static fn (array $s): string => match (true) {
    $s['status'] === 'active' && strtotime((string) $s['start_date']) > time() => 'A venir',
    $s['status'] === 'active' && $s['cancelled_at'] !== null                    => 'Resilie, actif jusqu\'a l\'echeance',
    $s['status'] === 'active'                                                    => 'Actif',
    $s['status'] === 'expired'                                                   => 'Termine',
    $s['status'] === 'cancelled'                                                 => 'Arrete',
    default                                                                      => (string) $s['status'],
};

$pageTitle = 'Mon abonnement';
$pageDescription = 'Votre abonnement Premium';

include 'includes/header-tailwind.php';
?>

<main class="min-h-screen bg-[radial-gradient(circle_at_top_right,rgba(245,158,11,0.14),transparent_28%),#0B0F17] pb-16 pt-24">
    <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6">
        <section class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2 sm:p-8">
            <p class="text-xs uppercase tracking-[0.28em] text-amber-300"><i class="fas fa-crown mr-2"></i>Mon abonnement</p>
            <?php displayFlashMessages(); ?>
            <?php if ($premium): ?>
                <h1 class="mt-3 text-2xl font-display font-semibold text-text">Premium <?php echo $resilie ? 'resilie' : 'actif'; ?></h1>
                <p class="mt-2 text-sm text-muted">
                    <?php echo $resilie ? 'Vos avantages restent actifs jusqu\'au ' : 'Periode en cours jusqu\'au '; ?>
                    <strong class="text-text"><?php echo e(date('d/m/Y', strtotime((string) $situation['fin']))); ?></strong>.
                    <?php if ($situation['suivant'] !== null): ?>La periode suivante est deja payee.<?php endif; ?>
                </p>
            <?php else: ?>
                <h1 class="mt-3 text-2xl font-display font-semibold text-text">Vous n'etes pas abonne(e)</h1>
                <p class="mt-2 text-sm text-muted">Premium donne l'ecoute integrale de tout le catalogue, des playlists illimitees et un traitement prioritaire de vos demandes.</p>
            <?php endif; ?>

            <div class="mt-6 flex flex-wrap gap-3">
                <?php if ($renouvelable): ?>
                    <a href="<?php echo SITE_URL; ?>/premium-payment.php?plan=monthly" class="rounded-full bg-amber-400 px-5 py-2.5 text-sm font-semibold text-bg"><?php echo $premium ? 'Renouveler (mensuel)' : 'Passer Premium'; ?></a>
                    <a href="<?php echo SITE_URL; ?>/premium-payment.php?plan=yearly" class="rounded-full border border-white/15 px-5 py-2.5 text-sm font-semibold text-text hover:bg-white/10">Formule annuelle</a>
                <?php elseif ($premium): ?>
                    <p class="text-xs text-muted">Le renouvellement ouvre 7 jours avant l'echeance ; nous vous le rappellerons par e-mail.</p>
                <?php endif; ?>
                <?php if ($premium && !$resilie && $courant !== null): ?>
                    <form method="POST" class="m-0" onsubmit="return confirm('Resilier ? Vos avantages resteront actifs jusqu\'a la fin de la periode payee.')">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="resilier">
                        <button class="rounded-full border border-white/15 px-5 py-2.5 text-sm font-semibold text-text hover:bg-white/10">Resilier</button>
                    </form>
                <?php endif; ?>
            </div>
        </section>

        <section class="rounded-3xl border border-white/10 bg-surface/75 shadow-elev-2" aria-labelledby="titre-periodes">
            <h2 id="titre-periodes" class="px-6 pt-6 text-lg font-semibold text-text">Periodes</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-white/10 text-xs uppercase tracking-wide text-muted">
                        <tr><th class="px-6 py-3">Formule</th><th class="px-6 py-3">Du</th><th class="px-6 py-3">Au</th><th class="px-6 py-3">Montant</th><th class="px-6 py-3">Etat</th><th class="px-6 py-3">Facture</th></tr>
                    </thead>
                    <tbody>
                        <?php if ($situation['historique'] === []): ?><tr><td colspan="6" class="px-6 py-6 text-center text-muted">Aucune periode.</td></tr><?php endif; ?>
                        <?php foreach ($situation['historique'] as $s): ?>
                            <tr class="border-b border-white/5">
                                <td class="px-6 py-3 text-text"><?php echo e($s['label'] ?? 'Premium'); ?></td>
                                <td class="px-6 py-3 text-xs text-muted"><?php echo e(date('d/m/Y', strtotime((string) $s['start_date']))); ?></td>
                                <td class="px-6 py-3 text-xs text-muted"><?php echo e(date('d/m/Y', strtotime((string) $s['end_date']))); ?></td>
                                <td class="px-6 py-3 text-text"><?php echo e($fcfa($s['amount'])); ?></td>
                                <td class="px-6 py-3 text-xs text-text"><?php echo e($etat($s)); ?></td>
                                <td class="px-6 py-3 text-xs"><?php if ($s['order_reference']): ?><a class="underline" href="<?php echo SITE_URL; ?>/facture.php?commande=<?php echo e(rawurlencode((string) $s['order_reference'])); ?>">Voir</a><?php endif; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</main>

<?php include 'includes/footer-tailwind.php'; ?>
