<?php
/**
 * Portefeuille (SHOP-06) : solde, rechargement, historique.
 *
 * Le solde affiche est celui du journal (`wallet_transactions`), dont
 * `users.wallet_balance` n'est que le cache. L'ancienne page lisait la table
 * `payment_transactions`, que rien n'alimentait : elle affichait un solde et
 * des mouvements sans rapport avec la realite.
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/paiement/chargement.php';

if (!isLoggedIn()) {
    redirect(SITE_URL . '/login.php?redirect=' . urlencode('/wallet.php'));
}
$userId = (int) $_SESSION['user_id'];
$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'recharger') {
    $recharge = Portefeuille::creerRecharge($userId, (int) ($_POST['montant'] ?? 0));
    if ($recharge['succes']) {
        redirect(SITE_URL . '/paiement.php?commande=' . urlencode((string) $recharge['reference']));
    }
    $erreur = (string) $recharge['erreur'];
}

$solde = Portefeuille::solde($userId);
$mouvements = Portefeuille::mouvements($userId);
$fcfa = static fn ($v): string => number_format((float) $v, 0, ',', ' ') . ' FCFA';

$pageTitle = 'Mon portefeuille';
$pageDescription = 'Rechargez une fois, achetez en un clic';

include 'includes/header-tailwind.php';
?>

<main class="min-h-screen bg-[radial-gradient(circle_at_top_right,rgba(47,109,224,0.18),transparent_28%),#0B0F17] pb-16 pt-24">
    <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6">
        <section class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2 sm:p-8" aria-labelledby="titre-portefeuille">
            <p class="text-xs uppercase tracking-[0.28em] text-muted">Portefeuille Tchadok</p>
            <h1 id="titre-portefeuille" class="mt-2 text-4xl font-display font-bold text-text"><?php echo e($fcfa($solde)); ?></h1>
            <p class="mt-2 max-w-xl text-sm text-muted">Rechargez une fois par Airtel Money, Moov Money, GIMAC ou carte, puis achetez vos titres en un clic, sans frais ni confirmation sur le telephone. Le solde n'expire pas.</p>

            <?php if ($erreur): ?>
                <div class="alert alert-danger mt-6" role="alert"><i class="fas fa-exclamation-circle mt-0.5"></i><span><?php echo e($erreur); ?></span></div>
            <?php endif; ?>

            <form method="POST" class="mt-6">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="recharger">
                <fieldset>
                    <legend class="text-sm font-semibold text-text">Recharger</legend>
                    <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <?php foreach (Portefeuille::PALIERS as $palier): ?>
                            <button type="submit" name="montant" value="<?php echo (int) $palier; ?>"
                                    class="rounded-2xl border border-white/10 bg-bg/60 px-4 py-4 text-center text-sm font-semibold text-text hover:border-accent">
                                <?php echo e($fcfa($palier)); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
            </form>
        </section>

        <section class="rounded-3xl border border-white/10 bg-surface/75 shadow-elev-2" aria-labelledby="titre-mouvements">
            <h2 id="titre-mouvements" class="px-6 pt-6 text-lg font-semibold text-text">Mouvements</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-white/10 text-xs uppercase tracking-wide text-muted">
                        <tr><th class="px-6 py-3">Date</th><th class="px-6 py-3">Operation</th><th class="px-6 py-3">Commande</th><th class="px-6 py-3 text-right">Montant</th><th class="px-6 py-3 text-right">Solde apres</th></tr>
                    </thead>
                    <tbody>
                        <?php if ($mouvements === []): ?>
                            <tr><td colspan="5" class="px-6 py-6 text-center text-muted">Aucun mouvement pour le moment.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($mouvements as $m): ?>
                            <tr class="border-b border-white/5">
                                <td class="whitespace-nowrap px-6 py-3 text-xs text-muted"><?php echo e(date('d/m/Y H:i', strtotime((string) $m['created_at']))); ?></td>
                                <td class="px-6 py-3 text-text"><?php echo e(Portefeuille::LIBELLES[$m['type']] ?? $m['type']); ?><?php echo $m['note'] ? '<span class="block text-xs text-muted">' . e($m['note']) . '</span>' : ''; ?></td>
                                <td class="px-6 py-3 font-mono text-xs text-muted"><?php echo e($m['order_reference'] ?? '—'); ?></td>
                                <td class="whitespace-nowrap px-6 py-3 text-right font-semibold <?php echo (float) $m['amount'] >= 0 ? 'text-emerald-300' : 'text-text'; ?>">
                                    <?php echo ((float) $m['amount'] >= 0 ? '+' : '−') . e($fcfa(abs((float) $m['amount']))); ?>
                                </td>
                                <td class="whitespace-nowrap px-6 py-3 text-right text-muted"><?php echo e($fcfa($m['balance_after'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</main>

<?php include 'includes/footer-tailwind.php'; ?>
