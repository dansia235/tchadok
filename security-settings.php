<?php
/**
 * Parametres de securite - Tchadok Platform
 *
 * SEC-14 : cette page presentait des donnees fabriquees comme si elles
 * etaient reelles -- un historique de connexions invente (une adresse et des
 * villes en dur), une liste d'appareils ecrite dans le HTML, et une
 * authentification a deux facteurs qui n'enregistrait rien, construite autour
 * du secret d'exemple public JBSWY3DPEHPK3PXP, identique pour tout le monde,
 * transmis a un service tiers pour fabriquer le QR code.
 *
 * La page ne montre plus que ce que la plateforme sait reellement :
 * l'historique vient de login_attempts (SEC-12), les sessions et appareils du
 * registre des sessions et des jetons de connexion automatique (SEC-10,
 * SEC-11). L'authentification a deux facteurs est annoncee comme indisponible
 * tant qu'elle n'est pas implementee (SEC-20) : mieux vaut l'absence d'une
 * protection que son illusion.
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';

if (!isLoggedIn()) {
    header('Location: ' . SITE_URL . '/login.php?redirect=security-settings');
    exit();
}

$pageTitle = 'Parametres de securite';
$pageDescription = 'Gerez la securite de votre compte Tchadok';
$hideTopNav = true;
$hideFooter = true;

$userId = (int) $_SESSION['user_id'];
$user = getCurrentUser();
if (!$user) {
    header('Location: ' . SITE_URL . '/login.php?redirect=security-settings');
    exit();
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $message = 'Tous les champs sont requis';
        $messageType = 'error';
    } elseif ($newPassword !== $confirmPassword) {
        $message = 'Les nouveaux mots de passe ne correspondent pas';
        $messageType = 'error';
    } else {
        $force = forceMotDePasse($newPassword);
        if ($force['score'] < 50) {
            $message = 'Mot de passe trop faible : ' . implode(', ', $force['conseils']);
            $messageType = 'error';
        } else {
            try {
                $db = TchadokDatabase::getInstance()->getConnection();

                $stmt = $db->prepare(
                    "SELECT COALESCE(NULLIF(password_hash, ''), password) AS current_hash FROM users WHERE id = ?"
                );
                $stmt->execute([$userId]);
                $currentHash = $stmt->fetchColumn();

                if (!$currentHash || !verifyPassword($currentPassword, $currentHash)) {
                    $message = 'Le mot de passe actuel est incorrect';
                    $messageType = 'error';
                } else {
                    $newPasswordHash = hashPassword($newPassword);
                    $stmt = $db->prepare(
                        'UPDATE users SET password = ?, password_hash = ?, updated_at = NOW() WHERE id = ?'
                    );
                    $stmt->execute([$newPasswordHash, $newPasswordHash, $userId]);

                    // SEC-10 / SEC-11 : autres appareils deconnectes, connexion
                    // automatique retiree, nouvel identifiant pour la session.
                    $revoquees = revoquerSessionsUtilisateur($userId, true);
                    renouvelerIdentifiantSession();

                    $message = 'Mot de passe modifie avec succes'
                        . ($revoquees > 0 ? " ({$revoquees} autre(s) session(s) fermee(s))" : '');
                    $messageType = 'success';
                }
            } catch (Exception $e) {
                error_log('[Tchadok][securite] changement de mot de passe : ' . $e->getMessage());
                $message = 'Impossible de mettre a jour le mot de passe pour le moment';
                $messageType = 'error';
            }
        }
    }
}

// --- Etat reel du compte -------------------------------------------------
$sessionsOuvertes = sessionsUtilisateur($userId);
$appareilsMemorises = RememberMe::lister($userId);
$historique = historiqueConnexions([$user['email'] ?? '', $user['username'] ?? ''], 12);
$echecsRecents = count(array_filter($historique, static fn ($l) => (int) $l['success'] === 0));
$emailVerifie = !empty($user['email_verified']);
$derniereConnexion = !empty($user['last_login']) ? strtotime((string) $user['last_login']) : null;

$isAdminSecurityView = isAdmin();

$dashboardUrl = SITE_URL . '/user-dashboard.php';
$workspaceLabel = 'Espace fan';
$roleLabel = 'Fan';

if ($isAdminSecurityView) {
    $dashboardUrl = SITE_URL . '/admin-dashboard.php';
    $workspaceLabel = 'Console admin';
    $roleLabel = 'Admin';
} elseif (isArtist()) {
    $dashboardUrl = SITE_URL . '/artist-dashboard.php';
    $workspaceLabel = 'Studio artiste';
    $roleLabel = 'Artiste';
}

$fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
if ($fullName === '') {
    $fullName = $user['username'] ?? 'Utilisateur';
}

$initialSeed = trim(($user['first_name'] ?? '') . ($user['last_name'] ?? ''));
if ($initialSeed === '') {
    $initialSeed = $user['username'] ?? 'U';
}
$initials = strtoupper(substr($initialSeed, 0, 2));

$additionalJS = [];
if ($isAdminSecurityView) {
    $additionalJS[] = SITE_URL . '/assets/js/dashboard-sticky-header.js';
    $additionalJS[] = SITE_URL . '/assets/js/dashboard-secondary-nav.js';

    $adminShellMetrics = [
        ['value' => (string) count($sessionsOuvertes), 'label' => 'session(s) ouverte(s)', 'tone' => 'border-blue-200 bg-blue-50 text-blue-700'],
        ['value' => (string) count($appareilsMemorises), 'label' => 'appareil(s) memorise(s)', 'tone' => 'border-slate-200 bg-slate-50 text-slate-700'],
        ['value' => $echecsRecents > 0 ? $echecsRecents . ' echec(s)' : 'aucun echec', 'label' => 'de connexion', 'tone' => $echecsRecents > 0 ? 'border-amber-200 bg-amber-50 text-amber-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700'],
    ];
    $dashboardSecondaryNavLabel = 'Securite admin';
    $dashboardSecondaryNavItems = [
        ['label' => 'Apercu', 'target' => 'security-overview', 'icon' => 'home'],
        ['label' => 'Mot de passe', 'target' => 'security-password', 'icon' => 'key'],
        ['label' => 'Activite', 'target' => 'security-activity', 'icon' => 'clock-rotate-left'],
        ['label' => 'Outils', 'target' => 'security-admin-tools', 'icon' => 'terminal'],
    ];
}

include 'includes/header-tailwind.php';
?>

<main class="min-h-screen bg-[radial-gradient(circle_at_top_right,rgba(47,109,224,0.18),transparent_28%),radial-gradient(circle_at_top_left,rgba(16,185,129,0.14),transparent_24%),#0B0F17] pb-16 pt-8">
    <?php if ($isAdminSecurityView): ?>
        <?php include 'includes/admin-shell-header.php'; ?>
    <?php endif; ?>

    <section id="security-overview">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-6 lg:grid-cols-[minmax(0,1.45fr)_340px]">
                <div class="space-y-6">
                    <div class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2 sm:p-8">
                        <div class="flex items-start gap-4">
                            <a href="<?php echo $dashboardUrl; ?>" class="grid h-12 w-12 place-items-center rounded-2xl border border-white/10 bg-white/5 text-text hover:bg-white/10">
                                <i class="fas fa-arrow-left"></i>
                            </a>
                            <div class="grid h-16 w-16 place-items-center rounded-3xl bg-white/10 text-xl font-semibold text-text">
                                <?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                            <div>
                                <p class="text-xs uppercase tracking-[0.28em] text-muted"><?php echo htmlspecialchars($workspaceLabel, ENT_QUOTES, 'UTF-8'); ?></p>
                                <h1 class="mt-2 text-3xl font-display font-bold text-text sm:text-4xl">Securite du compte</h1>
                                <p class="mt-3 max-w-2xl text-sm leading-6 text-muted">
                                    Ce que la plateforme sait de votre compte : vos connexions, vos appareils,
                                    et de quoi reprendre la main si quelque chose vous echappe.
                                </p>
                                <div class="mt-4 flex flex-wrap gap-2 text-xs">
                                    <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-muted"><?php echo htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-muted"><?php echo htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if ($message): ?>
                        <div class="alert <?php echo $messageType === 'success' ? 'alert-success' : 'alert-danger'; ?>" role="status">
                            <i class="fas <?php echo $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mt-0.5"></i>
                            <span><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    <?php endif; ?>

                    <section class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2 sm:p-8">
                        <h2 class="text-lg font-semibold text-text">Etat du compte</h2>
                        <dl class="mt-4 grid gap-3 sm:grid-cols-2">
                            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                                <dt class="text-xs uppercase tracking-wide text-muted">Adresse email</dt>
                                <dd class="mt-1 text-sm font-semibold text-text">
                                    <?php echo htmlspecialchars((string) ($user['email'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?>
                                    <span class="ml-2 rounded-full px-2 py-0.5 text-[11px] <?php echo $emailVerifie ? 'bg-emerald-500/15 text-emerald-200' : 'bg-amber-500/15 text-amber-200'; ?>">
                                        <?php echo $emailVerifie ? 'verifiee' : 'non verifiee'; ?>
                                    </span>
                                </dd>
                            </div>
                            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                                <dt class="text-xs uppercase tracking-wide text-muted">Derniere connexion</dt>
                                <dd class="mt-1 text-sm font-semibold text-text">
                                    <?php echo $derniereConnexion ? date('d/m/Y \a H\hi', $derniereConnexion) : 'inconnue'; ?>
                                </dd>
                            </div>
                            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                                <dt class="text-xs uppercase tracking-wide text-muted">Sessions ouvertes</dt>
                                <dd class="mt-1 text-sm font-semibold text-text"><?php echo count($sessionsOuvertes); ?></dd>
                            </div>
                            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                                <dt class="text-xs uppercase tracking-wide text-muted">Appareils memorises</dt>
                                <dd class="mt-1 text-sm font-semibold text-text"><?php echo count($appareilsMemorises); ?></dd>
                            </div>
                        </dl>
                        <a href="<?php echo SITE_URL; ?>/settings.php#devices" class="mt-4 inline-flex items-center gap-2 rounded-full border border-white/15 px-4 py-2 text-xs font-semibold text-text hover:bg-white/10">
                            <i class="fas fa-laptop"></i> Gerer les appareils connectes
                        </a>
                    </section>

                    <section id="security-password" class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2 sm:p-8">
                        <h2 class="text-lg font-semibold text-text">Changer le mot de passe</h2>
                        <p class="mt-2 text-sm text-muted">
                            Changer votre mot de passe ferme vos sessions ouvertes ailleurs et retire la
                            connexion automatique de tous vos appareils.
                        </p>
                        <form method="POST" action="" class="mt-6 space-y-4">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="change_password">

                            <div>
                                <label for="current_password" class="text-sm font-semibold text-text">Mot de passe actuel</label>
                                <input type="password" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text focus:outline-none focus:ring-2 focus:ring-accent/60"
                                       id="current_password" name="current_password" autocomplete="current-password" required>
                            </div>

                            <div>
                                <label for="new_password" class="text-sm font-semibold text-text">Nouveau mot de passe</label>
                                <input type="password" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text focus:outline-none focus:ring-2 focus:ring-accent/60"
                                       id="new_password" name="new_password" minlength="8" autocomplete="new-password" required>
                                <p class="mt-2 text-xs text-muted">
                                    Au moins 8 caracteres, avec majuscules, minuscules, chiffres et caracteres speciaux.
                                </p>
                            </div>

                            <div>
                                <label for="confirm_password" class="text-sm font-semibold text-text">Confirmer le mot de passe</label>
                                <input type="password" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text focus:outline-none focus:ring-2 focus:ring-accent/60"
                                       id="confirm_password" name="confirm_password" autocomplete="new-password" required>
                            </div>

                            <button type="submit" class="rounded-full bg-accent px-5 py-2 text-sm font-semibold text-white">
                                <i class="fas fa-save mr-2"></i>Enregistrer
                            </button>
                        </form>
                    </section>

                    <section id="security-activity" class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2 sm:p-8">
                        <h2 class="text-lg font-semibold text-text">Connexions enregistrees</h2>
                        <p class="mt-2 text-sm text-muted">
                            Tentatives de connexion a votre compte, reussies comme echouees. Les echecs
                            disparaissent de cette liste une fois que vous vous reconnectez depuis le meme
                            appareil. Conservees 90 jours.
                        </p>

                        <?php if (!$historique): ?>
                            <p class="mt-4 text-xs text-muted">Aucune tentative enregistree pour l'instant.</p>
                        <?php else: ?>
                            <ul class="mt-4 space-y-3">
                                <?php foreach ($historique as $ligne): ?>
                                    <?php $reussie = (int) $ligne['success'] === 1; ?>
                                    <li class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-white/10 bg-white/5 p-4">
                                        <div class="flex items-start gap-3">
                                            <i class="fas <?php echo $reussie ? 'fa-right-to-bracket text-emerald-300' : 'fa-triangle-exclamation text-amber-300'; ?> mt-1"></i>
                                            <div>
                                                <p class="text-sm font-semibold text-text">
                                                    <?php echo $reussie ? 'Connexion reussie' : 'Connexion refusee'; ?>
                                                </p>
                                                <p class="mt-1 text-xs text-muted">
                                                    <?php echo htmlspecialchars($ligne['appareil'], ENT_QUOTES, 'UTF-8'); ?>
                                                    &middot; <?php echo htmlspecialchars((string) $ligne['ip_address'], ENT_QUOTES, 'UTF-8'); ?>
                                                </p>
                                            </div>
                                        </div>
                                        <span class="text-xs text-muted">
                                            <?php echo date('d/m/Y \a H\hi', strtotime((string) $ligne['created_at'])); ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <?php if ($echecsRecents >= 3): ?>
                            <p class="mt-4 rounded-2xl border border-amber-400/30 bg-amber-400/10 p-4 text-xs text-amber-100">
                                <i class="fas fa-circle-info"></i>
                                <?php echo $echecsRecents; ?> tentatives refusees figurent dans cette liste. Si elles ne
                                viennent pas de vous, changez votre mot de passe : cela fermera aussi les sessions
                                ouvertes ailleurs.
                            </p>
                        <?php endif; ?>
                    </section>
                </div>

                <div class="space-y-6">
                    <div class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2">
                        <h3 class="text-base font-semibold text-text">Authentification a deux facteurs</h3>
                        <p class="mt-2 text-sm text-muted">
                            Pas encore disponible. Elle sera proposee avec une application d'authentification,
                            des codes de secours et la possibilite de retirer un appareil.
                        </p>
                        <p class="mt-3 rounded-2xl border border-white/10 bg-white/5 p-3 text-xs text-muted">
                            Cette page affichait auparavant un ecran de configuration qui n'enregistrait rien :
                            la protection paraissait active alors qu'elle ne l'etait pas. Elle a ete retiree en
                            attendant la version reelle.
                        </p>
                    </div>

                    <?php if ($isAdminSecurityView): ?>
                        <div id="security-admin-tools" class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2">
                            <h3 class="text-base font-semibold text-text">Outils admin sensibles</h3>
                            <p class="mt-2 text-sm text-muted">
                                Actions de securite reservees a l'administration.
                            </p>
                            <div class="mt-4 space-y-3 text-sm">
                                <a href="<?php echo SITE_URL; ?>/admin/reset-password.php" class="flex items-center justify-between rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-text hover:bg-white/10">
                                    <span class="inline-flex items-center gap-2">
                                        <i class="fas fa-unlock-keyhole text-accent"></i>
                                        Reinitialiser un acces administrateur
                                    </span>
                                    <i class="fas fa-arrow-right text-xs text-muted"></i>
                                </a>
                                <a href="<?php echo SITE_URL; ?>/admin-dashboard.php" class="flex items-center justify-between rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-text hover:bg-white/10">
                                    <span class="inline-flex items-center gap-2">
                                        <i class="fas fa-shield-alt text-accent"></i>
                                        Retour a la console admin
                                    </span>
                                    <i class="fas fa-arrow-right text-xs text-muted"></i>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2">
                        <h3 class="text-base font-semibold text-text">Si votre compte vous echappe</h3>
                        <ol class="mt-4 space-y-3 text-sm text-muted">
                            <li class="flex gap-3"><span class="font-semibold text-text">1.</span> Changez votre mot de passe ci-contre : toutes vos autres sessions se ferment.</li>
                            <li class="flex gap-3"><span class="font-semibold text-text">2.</span> Verifiez la liste des appareils dans les parametres, et retirez ceux que vous ne reconnaissez pas.</li>
                            <li class="flex gap-3"><span class="font-semibold text-text">3.</span> Utilisez un mot de passe unique, qui ne sert nulle part ailleurs.</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include 'includes/footer-tailwind.php'; ?>
