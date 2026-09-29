<?php
/**
 * Verification de l'adresse e-mail (MOD-07).
 *
 *   ?jeton=...   confirme l'adresse (lien recu par e-mail, valable 48 h) ;
 *   sans jeton   etat de la verification et renvoi du lien (connecte) ;
 *   ?retour=...  page a reprendre une fois l'adresse confirmee.
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/comptes.php';

$retour = destinationInterne($_GET['retour'] ?? $_POST['retour'] ?? null);
$message = '';
$succes = false;

if (isset($_GET['jeton'])) {
    $r = Comptes::verifier((string) $_GET['jeton']);
    $message = $r['message'];
    $succes = $r['succes'];
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isLoggedIn()) {
    $r = Comptes::envoyerVerification((int) $_SESSION['user_id']);
    $message = $r['message'];
    $succes = $r['succes'];
}
$verifie = isLoggedIn() && Comptes::emailVerifie((int) $_SESSION['user_id']);

$pageTitle = 'Confirmer mon adresse e-mail';
include 'includes/header-tailwind.php';
?>

<main class="min-h-screen bg-bg pb-16 pt-24">
    <section class="mx-auto max-w-xl px-4 sm:px-6">
        <div class="rounded-3xl border border-white/10 bg-surface/75 p-6 sm:p-8">
            <h1 class="text-2xl font-display font-semibold text-text">Adresse e-mail</h1>
            <?php if ($message !== ''): ?>
                <div class="alert <?php echo $succes ? 'alert-success' : 'alert-danger'; ?> mt-6" role="status" data-verification="<?php echo $succes ? 'ok' : 'refus'; ?>"><span><?php echo e($message); ?></span></div>
            <?php endif; ?>
            <?php if ($verifie): ?>
                <p class="mt-4 text-sm text-muted">Votre adresse est confirmee.</p>
                <?php if ($retour): ?><a class="mt-4 inline-block rounded-full bg-accent px-5 py-3 text-sm font-semibold text-white" href="<?php echo e(SITE_URL . $retour); ?>">Continuer</a><?php endif; ?>
            <?php elseif (isLoggedIn()): ?>
                <p class="mt-4 text-sm text-muted">Confirmez votre adresse pour acheter, publier et commenter. Le lien envoye est valable 48 heures ; vous pouvez en demander un nouveau.</p>
                <form method="POST" class="mt-6">
                    <?php echo csrfField(); ?>
                    <?php if ($retour): ?><input type="hidden" name="retour" value="<?php echo e($retour); ?>"><?php endif; ?>
                    <button class="rounded-full bg-accent px-5 py-3 text-sm font-semibold text-white">Renvoyer le lien de confirmation</button>
                </form>
            <?php else: ?>
                <p class="mt-4 text-sm text-muted"><a class="underline" href="<?php echo SITE_URL; ?>/login.php?redirect=<?php echo rawurlencode('/verifier-email.php'); ?>">Connectez-vous</a> pour demander un nouveau lien.</p>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php include 'includes/footer-tailwind.php'; ?>
