<?php
/**
 * Souscription Premium (SUB-02).
 *
 *   premium-payment.php?plan=monthly|yearly
 *
 * AVANT : la page inserait une ligne `pending` dans subscriptions et une
 * autre dans payment_transactions, puis s'arretait -- aucun encaissement,
 * aucune activation. Personne ne pouvait devenir Premium.
 *
 * MAINTENANT : la formule est confirmee ici, la commande d'abonnement est
 * creee, et le reglement passe par le tunnel COMMUN (paiement.php : Airtel
 * Money, Moov Money, GIMAC, carte, portefeuille). L'abonnement ne s'active
 * qu'a l'encaissement confirme par l'operateur (Abonnements::activer).
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/paiement/chargement.php';

$codes = ['monthly' => 'premium_monthly', 'yearly' => 'premium_annual'];
$choix = (string) ($_GET['plan'] ?? $_POST['plan'] ?? 'monthly');
$code = $codes[$choix] ?? (in_array($choix, $codes, true) ? $choix : 'premium_monthly');

if (!isLoggedIn()) {
    redirect(SITE_URL . '/login.php?redirect=' . urlencode('/premium-payment.php?plan=' . $choix));
}
$userId = (int) $_SESSION['user_id'];

$plans = Abonnements::plans();
if (!isset($plans[$code])) {
    redirect(SITE_URL . '/premium.php');
}
$plan = $plans[$code];
$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $souscription = Abonnements::souscrire($userId, $code);
    if ($souscription['succes']) {
        redirect(SITE_URL . '/paiement.php?commande=' . urlencode((string) $souscription['reference']));
    }
    $erreur = (string) $souscription['erreur'];
}

$situation = Abonnements::situation($userId);
$fcfa = static fn ($v): string => number_format((float) $v, 0, ',', ' ') . ' FCFA';

$pageTitle = 'Souscrire a Premium';
$pageDescription = 'Confirmez votre formule Premium';

include 'includes/header-tailwind.php';
?>

<main class="min-h-screen bg-[radial-gradient(circle_at_top_right,rgba(245,158,11,0.14),transparent_28%),#0B0F17] pb-16 pt-24">
    <section class="mx-auto max-w-xl px-4 sm:px-6">
        <div class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2 sm:p-8">
            <p class="text-xs uppercase tracking-[0.28em] text-amber-300"><i class="fas fa-crown mr-2"></i>Tchadok Premium</p>
            <h1 class="mt-2 text-2xl font-display font-semibold text-text"><?php echo e($plan['label']); ?></h1>
            <p class="mt-1 text-3xl font-bold text-text"><?php echo e($fcfa($plan['prix'])); ?>
                <span class="text-sm font-normal text-muted">pour <?php echo $plan['duree'] >= 12 ? '12 mois' : $plan['duree'] . ' mois'; ?></span></p>

            <?php if ($erreur): ?>
                <div class="alert alert-warning mt-6" role="alert"><i class="fas fa-circle-info mt-0.5"></i><span><?php echo e($erreur); ?></span></div>
            <?php elseif ($situation['fin'] !== null): ?>
                <div class="alert alert-info mt-6" role="status"><i class="fas fa-circle-info mt-0.5"></i>
                    <span>Vous etes deja Premium jusqu'au <?php echo e(date('d/m/Y', strtotime($situation['fin']))); ?>. Une nouvelle periode commencera a cette date, sans chevauchement.</span></div>
            <?php endif; ?>

            <ul class="mt-6 space-y-3 text-sm">
                <?php foreach (Abonnements::AVANTAGES as $avantage): ?>
                    <li class="flex gap-3"><i class="fas <?php echo e($avantage['icone']); ?> mt-1 text-amber-300"></i>
                        <span><strong class="text-text"><?php echo e($avantage['titre']); ?></strong><span class="block text-muted"><?php echo e($avantage['texte']); ?></span></span></li>
                <?php endforeach; ?>
            </ul>

            <form method="POST" class="mt-8">
                <?php echo csrfField(); ?>
                <input type="hidden" name="plan" value="<?php echo e($choix); ?>">
                <button type="submit" class="w-full rounded-full bg-amber-400 px-5 py-3 text-sm font-semibold text-bg">Continuer vers le paiement</button>
            </form>
            <p class="mt-3 text-xs text-muted">Airtel Money, Moov Money, GIMAC, carte VISA ou portefeuille Tchadok. Aucun prelevement automatique : nous vous rappelons l'echeance, vous renouvelez si vous le souhaitez.</p>
            <div class="mt-6 flex flex-wrap gap-4 text-xs">
                <a class="text-muted underline hover:text-text" href="<?php echo SITE_URL; ?>/premium-payment.php?plan=<?php echo $choix === 'yearly' ? 'monthly' : 'yearly'; ?>">
                    Voir la formule <?php echo $choix === 'yearly' ? 'mensuelle' : 'annuelle'; ?></a>
                <a class="text-muted underline hover:text-text" href="<?php echo SITE_URL; ?>/abonnement.php">Mon abonnement</a>
            </div>
        </div>
    </section>
</main>

<?php include 'includes/footer-tailwind.php'; ?>
