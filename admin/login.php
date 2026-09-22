<?php
/**
 * Page de connexion administrateur - Tchadok Platform
 */

require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Redirection si deja connecte
if (isLoggedIn() && isAdmin()) {
    header('Location: ' . SITE_URL . '/admin-dashboard.php');
    exit;
}

$error = '';
// SEC-10 : raison d'une fermeture de session. Les sessions d'administration
// sont fermees apres ADMIN_SESSION_LIFETIME d'inactivite (15 min en production).
$info = messageFinSession() ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Veuillez remplir tous les champs';
    } else {
        if (!$auth) {
            $error = 'Service d\'authentification indisponible';
        } else {
            $result = $auth->login($username, $password, false);
            if (!empty($result['success'])) {
                if (isAdmin()) {
                    header('Location: ' . SITE_URL . '/admin-dashboard.php');
                    exit;
                }
                $auth->logout();
                $error = 'Acces reserve aux administrateurs';
            } else {
                $error = $result['error'] ?? 'Identifiants incorrects';
            }
        }
    }
}

$pageTitle = 'Administration';
$pageDescription = 'Connexion administrateur Tchadok';
$hideTopNav = true;
$hideFooter = true;

include '../includes/header-tailwind.php';
?>

<main class="min-h-screen bg-bg pt-16 pb-16">
    <div class="mx-auto flex min-h-[70vh] max-w-5xl flex-col items-center justify-center gap-6 px-4 sm:px-6 lg:flex-row lg:items-stretch lg:gap-10 lg:px-8">
        <div class="w-full max-w-lg rounded-3xl border border-white/10 bg-surface/70 p-6 shadow-elev-2 sm:p-10">
            <div class="flex items-center gap-3">
                <span class="grid h-12 w-12 place-items-center rounded-2xl bg-accent/20 text-accent">
                    <i class="fas fa-shield-alt text-xl"></i>
                </span>
                <div>
                    <h1 class="text-xl font-display font-semibold text-text">Administration Tchadok</h1>
                    <p class="text-sm text-muted">Acces reserve aux administrateurs</p>
                </div>
            </div>

            <?php if ($info): ?>
                <div class="mt-6 rounded-2xl border border-amber-400/30 bg-amber-400/10 px-4 py-3 text-sm text-amber-100" role="status" data-fin-session>
                    <i class="fas fa-clock" aria-hidden="true"></i>
                    <span class="ml-2"><?php echo htmlspecialchars($info, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="mt-6 rounded-2xl border border-rose-400/30 bg-rose-400/10 px-4 py-3 text-sm text-rose-200">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span class="ml-2"><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" class="mt-6 space-y-4">
                <?php echo csrfField(); ?>
                <label class="text-xs font-semibold text-muted">
                    Nom d'utilisateur
                    <input type="text" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text" id="username" name="username" required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                </label>
                <label class="text-xs font-semibold text-muted">
                    Mot de passe
                    <input type="password" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text" id="password" name="password" required placeholder="••••••••">
                </label>
                <button type="submit" class="w-full rounded-full bg-accent px-4 py-3 text-sm font-semibold text-white shadow-elev-1">
                    Se connecter
                </button>
            </form>

            <?php if (file_exists(__DIR__ . '/reset-password.php')): ?>
                <div class="mt-4 text-xs text-muted">
                    <a href="reset-password.php" class="font-semibold text-accent hover:text-white">Mot de passe oublie ?</a>
                </div>
            <?php endif; ?>

            <div class="mt-6 flex items-center justify-between text-xs text-muted">
                <span><i class="fas fa-shield-alt"></i> Zone securisee</span>
                <a href="../index.php" class="text-accent hover:text-white">Retour au site</a>
            </div>
        </div>

        <div class="w-full max-w-md rounded-3xl border border-white/10 bg-white/5 p-6 shadow-elev-1">
            <h2 class="text-sm font-semibold text-text">Acces securise</h2>
            <p class="mt-2 text-xs text-muted">
                Ce panneau est reserve aux administrateurs de la plateforme. Utilisez vos identifiants officiels.
            </p>
        </div>
    </div>
</main>

<?php include '../includes/footer-tailwind.php'; ?>
