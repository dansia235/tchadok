<?php
/**
 * Verification du second facteur a la connexion (SEC-20).
 *
 * On arrive ici apres un mot de passe correct, quand le compte a active la
 * double authentification. La session n'est pas encore ouverte : seule une
 * attente de cinq minutes est conservee.
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';

$attente = $_SESSION['deux_facteurs_attente'] ?? null;

if (!is_array($attente) || ($attente['expire'] ?? 0) < time()) {
    unset($_SESSION['deux_facteurs_attente']);
    redirect(SITE_URL . '/login.php');
}

$pageTitle = 'Verification';
$pageDescription = 'Saisissez le code de votre application d\'authentification';
$hideTopNav = true;
$hideFooter = true;

$error = '';
$modeSecours = ($_GET['secours'] ?? '') === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // SEC-12 : un code a six chiffres se devine en cent mille essais. La
    // limitation de debit est ici indispensable, pas optionnelle.
    LimiteDebit::appliquer('second-facteur');

    $codeDeSecours = isset($_POST['code_secours']);
    $code = trim((string) ($_POST['code'] ?? ''));

    if ($code === '') {
        $error = 'Saisissez le code.';
    } else {
        // Lue AVANT la connexion, qui vide la session (SEC-10).
        $apres = destinationInterne($attente['apres'] ?? null);
        $resultat = $auth ? $auth->terminerConnexionDeuxFacteurs($code, $codeDeSecours) : ['success' => false, 'error' => 'Service indisponible.'];

        if (!empty($resultat['success'])) {
            setFlashMessage(FLASH_SUCCESS, 'Connexion reussie ! Bienvenue sur Tchadok');
            redirect(SITE_URL . ($apres ?? '/'));
        }

        $error = $resultat['error'] ?: 'Code incorrect.';
        $modeSecours = $codeDeSecours;
    }
}

include 'includes/header-tailwind.php';
?>

<main class="min-h-screen bg-[radial-gradient(circle_at_top_right,rgba(47,109,224,0.18),transparent_28%),#0B0F17] pb-16 pt-24">
    <section>
        <div class="mx-auto max-w-lg px-4 sm:px-6">
            <div class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2 sm:p-8">
                <div class="flex items-center gap-3">
                    <span class="grid h-12 w-12 place-items-center rounded-2xl bg-accent/20 text-accent">
                        <i class="fas fa-shield-halved text-xl"></i>
                    </span>
                    <div>
                        <h1 class="text-xl font-display font-semibold text-text">Verification en deux temps</h1>
                        <p class="text-sm text-muted">
                            <?php echo $modeSecours
                                ? 'Saisissez un de vos codes de secours.'
                                : 'Saisissez le code affiche par votre application d\'authentification.'; ?>
                        </p>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger mt-6" role="alert">
                        <i class="fas fa-exclamation-circle mt-0.5"></i>
                        <span><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" class="mt-6 space-y-4">
                    <?php echo csrfField(); ?>
                    <?php if ($modeSecours): ?>
                        <input type="hidden" name="code_secours" value="1">
                    <?php endif; ?>

                    <div>
                        <label for="code" class="text-sm font-semibold text-text">
                            <?php echo $modeSecours ? 'Code de secours' : 'Code a six chiffres'; ?>
                        </label>
                        <input id="code" name="code" type="text" autocomplete="one-time-code" autofocus required
                               inputmode="<?php echo $modeSecours ? 'text' : 'numeric'; ?>"
                               class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-center text-lg tracking-[0.3em] text-text focus:outline-none focus:ring-2 focus:ring-accent/60">
                    </div>

                    <button type="submit" class="w-full rounded-full bg-accent px-5 py-3 text-sm font-semibold text-white">
                        Verifier
                    </button>
                </form>

                <div class="mt-6 flex flex-wrap items-center justify-between gap-3 text-xs text-muted">
                    <?php if ($modeSecours): ?>
                        <a class="text-text hover:underline" href="2fa-verification.php">Utiliser l'application d'authentification</a>
                    <?php else: ?>
                        <a class="text-text hover:underline" href="2fa-verification.php?secours=1">Telephone perdu : utiliser un code de secours</a>
                    <?php endif; ?>
                    <a class="hover:underline" href="<?php echo SITE_URL; ?>/login.php">Annuler</a>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include 'includes/footer-tailwind.php'; ?>
