<?php
/**
 * Reinitialisation du mot de passe admin - Tchadok Platform
 */

require_once '../includes/functions.php';
require_once '../includes/database.php';

$pageTitle = 'Reinitialiser mot de passe admin';
$pageDescription = 'Reinitialisation du mot de passe administrateur';
$hideTopNav = true;
$hideFooter = true;

$success = '';
$error = '';
$mode = 'request';
$email = '';
$token = '';

$dbInstance = TchadokDatabase::getInstance();
$db = $dbInstance->getConnection();

if (!empty($_GET['email']) && !empty($_GET['token'])) {
    $mode = 'reset';
    $email = trim($_GET['email']);
    $token = trim($_GET['token']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // SEC-12 : la reinitialisation sert a la fois a envoyer des liens et a
    // essayer des jetons. Limitee par adresse, demande et reinitialisation
    // confondues.
    LimiteDebit::appliquer('mot-de-passe');

    $action = $_POST['action'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verifyCSRFToken($csrfToken)) {
        $error = 'Session invalide. Veuillez reessayer.';
    } elseif (!$db) {
        $error = 'Connexion a la base indisponible.';
    } elseif ($action === 'request') {
        $identifier = trim($_POST['identifier'] ?? '');

        if ($identifier === '') {
            $error = 'Veuillez saisir un email ou un nom d\'utilisateur.';
        } else {
            $stmt = $db->prepare(
                "SELECT u.id, u.email
                 FROM users u
                 INNER JOIN admins a ON u.id = a.user_id
                 WHERE (u.email = ? OR u.username = ?) AND u.is_active = 1
                 LIMIT 1"
            );
            $stmt->execute([$identifier, $identifier]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $rawToken = generateSecureToken(32);
                $hashedToken = hashPassword($rawToken);
                $expiresAt = date('Y-m-d H:i:s', time() + 30 * 60);

                $update = $db->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
                $update->execute([$hashedToken, $expiresAt, $user['id']]);

                $resetUrl = SITE_URL . '/admin/reset-password.php?token=' . urlencode($rawToken) .
                    '&email=' . urlencode($user['email']);

                $message = "
                    <h2>Reinitialisation du mot de passe admin</h2>
                    <p>Vous avez demande une reinitialisation. Cliquez sur le lien ci-dessous :</p>
                    <p><a href='{$resetUrl}'>{$resetUrl}</a></p>
                    <p>Ce lien expire dans 30 minutes.</p>
                    <p>Si vous n'etes pas a l'origine de cette demande, ignorez cet email.</p>
                ";

                sendEmail($user['email'], 'Reinitialisation mot de passe admin', $message);
            }

            $success = 'Si un compte admin correspond, un lien de reinitialisation a ete envoye.';
        }
    } elseif ($action === 'reset') {
        $mode = 'reset';
        $email = trim($_POST['email'] ?? '');
        $token = trim($_POST['token'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (!validateEmail($email)) {
            $error = 'Adresse email invalide.';
        } elseif (strlen($password) < MIN_PASSWORD_LENGTH) {
            $error = 'Le mot de passe doit contenir au moins ' . MIN_PASSWORD_LENGTH . ' caracteres.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Les mots de passe ne correspondent pas.';
        } else {
            $stmt = $db->prepare(
                "SELECT u.id, u.reset_token, u.reset_expires
                 FROM users u
                 INNER JOIN admins a ON u.id = a.user_id
                 WHERE u.email = ? AND u.is_active = 1
                 LIMIT 1"
            );
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || empty($user['reset_token']) || empty($user['reset_expires'])) {
                $error = 'Lien de reinitialisation invalide.';
            } elseif (strtotime($user['reset_expires']) < time()) {
                $error = 'Lien expire. Veuillez demander un nouveau lien.';
            } elseif (!password_verify($token, $user['reset_token'])) {
                $error = 'Lien de reinitialisation invalide.';
            } else {
                $newHash = hashPassword($password);
                $update = $db->prepare(
                    "UPDATE users
                     SET password = ?, password_hash = ?, reset_token = NULL, reset_expires = NULL
                     WHERE id = ?"
                );
                $update->execute([$newHash, $newHash, $user['id']]);

                // SEC-10 : toutes les sessions du compte sont fermees. La personne
                // qui reinitialise n'est pas connectee : rien a conserver.
                revoquerSessionsUtilisateur((int) $user['id'], false);

                // Cette page se supprimait elle-meme ici (unlink(__FILE__)) apres
                // chaque succes. Or admin/login.php n'affiche le lien "mot de passe
                // oublie" que si ce fichier existe : apres UNE reinitialisation,
                // plus aucun administrateur ne pouvait reinitialiser le sien.
                // Supprimer un fichier de la racine web a l'execution est par
                // ailleurs une pratique a proscrire. Retire.
                $success = 'Mot de passe reinitialise avec succes. Vous pouvez vous connecter.';
                $mode = 'request';
                $email = '';
                $token = '';
            }
        }
    }
}

include '../includes/header-tailwind.php';
?>

<main class="min-h-screen bg-bg pt-16 pb-16">
    <div class="mx-auto flex min-h-[70vh] max-w-5xl flex-col items-center justify-center gap-6 px-4 sm:px-6 lg:flex-row lg:items-stretch lg:gap-10 lg:px-8">
        <div class="w-full max-w-lg rounded-3xl border border-white/10 bg-surface/70 p-6 shadow-elev-2 sm:p-10">
            <div class="flex items-center gap-3">
                <span class="grid h-12 w-12 place-items-center rounded-2xl bg-accent/20 text-accent">
                    <i class="fas fa-key text-xl"></i>
                </span>
                <div>
                    <h1 class="text-xl font-display font-semibold text-text">Reinitialiser le mot de passe</h1>
                    <p class="text-sm text-muted">Acces reserve aux administrateurs</p>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="mt-6 rounded-2xl border border-rose-400/30 bg-rose-400/10 px-4 py-3 text-sm text-rose-200">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span class="ml-2"><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="mt-6 rounded-2xl border border-emerald-400/30 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-200">
                    <i class="fas fa-check-circle"></i>
                    <span class="ml-2"><?php echo htmlspecialchars($success); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($mode === 'reset'): ?>
                <form method="POST" class="mt-6 space-y-4">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="reset">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                    <label class="text-xs font-semibold text-muted">
                        Nouveau mot de passe
                        <input type="password" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text" name="password" required placeholder="********">
                    </label>
                    <label class="text-xs font-semibold text-muted">
                        Confirmer le mot de passe
                        <input type="password" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text" name="confirm_password" required placeholder="********">
                    </label>

                    <button type="submit" class="w-full rounded-full bg-accent px-4 py-3 text-sm font-semibold text-white shadow-elev-1">
                        Reinitialiser le mot de passe
                    </button>
                </form>

                <div class="mt-6 text-xs text-muted">
                    <a href="reset-password.php" class="text-accent hover:text-white">Demander un nouveau lien</a>
                </div>
            <?php else: ?>
                <form method="POST" class="mt-6 space-y-4">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="request">
                    <label class="text-xs font-semibold text-muted">
                        Email ou nom d'utilisateur admin
                        <input type="text" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text" name="identifier" required placeholder="admin@tchadok.td">
                    </label>
                    <button type="submit" class="w-full rounded-full bg-accent px-4 py-3 text-sm font-semibold text-white shadow-elev-1">
                        Envoyer le lien de reinitialisation
                    </button>
                </form>
            <?php endif; ?>

            <div class="mt-6 flex items-center justify-between text-xs text-muted">
                <span><i class="fas fa-shield-alt"></i> Zone securisee</span>
                <a href="login.php" class="text-accent hover:text-white">Retour a la connexion</a>
            </div>
        </div>

        <div class="w-full max-w-md rounded-3xl border border-white/10 bg-white/5 p-6 shadow-elev-1">
            <h2 class="text-sm font-semibold text-text">Conseils de securite</h2>
            <p class="mt-2 text-xs text-muted">
                Utilisez un mot de passe unique et d'au moins <?php echo MIN_PASSWORD_LENGTH; ?> caracteres.
                Le lien de reinitialisation est valide 30 minutes.
            </p>
            <div class="mt-4 rounded-2xl border border-white/10 bg-bg/60 p-4 text-xs text-muted">
                <p class="font-semibold text-text">Besoin d'aide ?</p>
                <p class="mt-1">Contactez l'equipe technique si vous n'avez pas recu l'email.</p>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/footer-tailwind.php'; ?>
