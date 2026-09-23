<?php
/**
 * Parametres - Tchadok Platform
 * Migration Tailwind (progressive)
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';

if (!isLoggedIn()) {
    header('Location: ' . SITE_URL . '/login.php?redirect=settings');
    exit();
}

$pageTitle = 'Parametres';
$pageDescription = 'Pilotez vos preferences de compte';
$hideTopNav = true;
$hideFooter = true;

$user = getCurrentUser();
if (!$user) {
    header('Location: ' . SITE_URL . '/login.php?redirect=settings');
    exit();
}
$success = '';
$error = '';

$dashboardUrl = SITE_URL . '/user-dashboard.php';
$workspaceLabel = 'Espace fan';
$roleLabel = 'Fan';

if (isAdmin()) {
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
$memberSince = !empty($user['created_at']) ? date('M Y', strtotime($user['created_at'])) : date('M Y');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $error = 'Tous les champs sont obligatoires.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Les nouveaux mots de passe ne correspondent pas.';
    } elseif (strlen($newPassword) < 8) {
        $error = 'Le mot de passe doit contenir au moins 8 caracteres.';
    } else {
        try {
            $dbInstance = TchadokDatabase::getInstance();
            $db = $dbInstance->getConnection();

            $userId = $_SESSION['user_id'];

            $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $userPassword = $stmt->fetchColumn();

            if (!verifyPassword($currentPassword, $userPassword)) {
                $error = 'Le mot de passe actuel est incorrect.';
            } else {
                $newPasswordHash = hashPassword($newPassword);

                // DATA-02 : une seule colonne de mot de passe.
                $stmt = $db->prepare('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?');
                $stmt->execute([$newPasswordHash, $userId]);

                // SEC-10 : les autres appareils sont deconnectes, et la session
                // courante recoit un nouvel identifiant. Si le mot de passe est
                // change parce qu'il a fuite, une session ouverte ailleurs par un
                // tiers ne doit pas survivre au changement.
                $revoquees = revoquerSessionsUtilisateur((int) $userId, true);
                renouvelerIdentifiantSession();

                $success = 'Mot de passe modifie avec succes !'
                    . ($revoquees > 0 ? " {$revoquees} autre(s) session(s) ont ete fermee(s)." : '');
            }
        } catch (Exception $e) {
            $error = 'Une erreur est survenue lors de la modification du mot de passe.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_notifications') {
    $success = 'Parametres de notification mis a jour !';
}

// SEC-11 : ecran « Appareils connectes ». Une session ou un jeton de connexion
// automatique se revoque a l'unite, ou tous d'un coup.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $userId = (int) $_SESSION['user_id'];

    if ($_POST['action'] === 'revoke_session') {
        if (revoquerSessionParEmpreinte((string) ($_POST['empreinte'] ?? ''), $userId)) {
            $success = 'Session fermee. Cet appareil devra saisir le mot de passe pour revenir.';
        } else {
            $error = 'Cette session n\'existe plus.';
        }
    } elseif ($_POST['action'] === 'revoke_device') {
        if (RememberMe::revoquerUn((int) ($_POST['token_id'] ?? 0), $userId)) {
            $success = 'Connexion automatique retiree pour cet appareil.';
        } else {
            $error = 'Cet appareil n\'est plus enregistre.';
        }
    } elseif ($_POST['action'] === 'revoke_all') {
        $sessions = revoquerSessionsUtilisateur($userId, true);
        renouvelerIdentifiantSession();
        $success = $sessions > 0
            ? "{$sessions} autre(s) session(s) fermee(s), et connexion automatique retiree partout."
            : 'Aucune autre session ouverte. La connexion automatique a ete retiree partout.';
    }
}

$sessionsOuvertes = sessionsUtilisateur((int) $_SESSION['user_id']);
$appareilsMemorises = RememberMe::lister((int) $_SESSION['user_id']);

include 'includes/header-tailwind.php';
?>

<main class="min-h-screen bg-[radial-gradient(circle_at_top_right,rgba(47,109,224,0.18),transparent_28%),radial-gradient(circle_at_top_left,rgba(16,185,129,0.14),transparent_24%),#0B0F17] pb-16 pt-8">
    <section>
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-6 lg:grid-cols-[minmax(0,1.45fr)_340px]">
                <div class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2 sm:p-8">
                    <div class="flex flex-wrap items-start justify-between gap-6">
                        <div class="flex items-start gap-4">
                            <a href="<?php echo $dashboardUrl; ?>" class="grid h-12 w-12 place-items-center rounded-2xl border border-white/10 bg-white/5 text-text hover:bg-white/10">
                                <i class="fas fa-arrow-left"></i>
                            </a>
                            <div class="grid h-16 w-16 place-items-center rounded-3xl bg-white/10 text-xl font-semibold text-text">
                                <?php echo htmlspecialchars($initials); ?>
                            </div>
                            <div>
                                <p class="text-xs uppercase tracking-[0.28em] text-muted"><?php echo htmlspecialchars($workspaceLabel); ?></p>
                                <h1 class="mt-2 text-3xl font-display font-bold text-text sm:text-4xl">Parametres</h1>
                                <p class="mt-3 max-w-2xl text-sm leading-6 text-muted">
                                    Un centre de pilotage unique pour la securite, la confidentialite, les notifications
                                    et les reglages de votre compte.
                                </p>
                                <div class="mt-4 flex flex-wrap gap-2 text-xs">
                                    <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-muted"><?php echo htmlspecialchars($roleLabel); ?></span>
                                    <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-muted">Membre depuis <?php echo htmlspecialchars($memberSince); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="grid gap-3 sm:min-w-[240px]">
                            <a href="<?php echo SITE_URL; ?>/security-settings.php" class="inline-flex items-center justify-center gap-2 rounded-full bg-accent px-5 py-3 text-sm font-semibold text-white shadow-elev-1">
                                <i class="fas fa-shield-alt"></i>
                                Centre de securite
                            </a>
                            <a href="<?php echo SITE_URL; ?>/edit-profile.php" class="inline-flex items-center justify-center gap-2 rounded-full border border-white/10 bg-white/5 px-5 py-3 text-sm font-semibold text-text hover:bg-white/10">
                                <i class="fas fa-user-pen"></i>
                                Modifier le profil
                            </a>
                        </div>
                    </div>
                </div>

                <div class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2">
                    <p class="text-xs uppercase tracking-[0.24em] text-muted">Synthese</p>
                    <div class="mt-4 space-y-4">
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                            <p class="text-sm text-muted">Compte</p>
                            <p class="mt-2 text-xl font-semibold text-text"><?php echo !empty($user['premium_status']) ? 'Premium' : 'Gratuit'; ?></p>
                            <p class="mt-2 text-xs leading-5 text-muted"><?php echo htmlspecialchars($fullName); ?> gere ici ses preferences principales.</p>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
                            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                                <p class="text-xs uppercase tracking-[0.2em] text-muted">Securite</p>
                                <p class="mt-2 text-sm font-semibold text-text">Acces protege</p>
                            </div>
                            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                                <p class="text-xs uppercase tracking-[0.2em] text-muted">Confidentialite</p>
                                <p class="mt-2 text-sm font-semibold text-text">Reglages centralises</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 grid gap-6 lg:grid-cols-4">
                <aside class="rounded-3xl border border-white/10 bg-surface/75 p-4 shadow-elev-2 lg:sticky lg:top-8">
                    <nav class="space-y-2 text-sm">
                        <a href="#security" class="flex items-center gap-3 rounded-2xl px-4 py-3 text-muted hover:bg-white/5 hover:text-text">
                            <i class="fas fa-shield-alt"></i> Securite
                        </a>
                        <a href="#notifications" class="flex items-center gap-3 rounded-2xl px-4 py-3 text-muted hover:bg-white/5 hover:text-text">
                            <i class="fas fa-bell"></i> Notifications
                        </a>
                        <a href="#privacy" class="flex items-center gap-3 rounded-2xl px-4 py-3 text-muted hover:bg-white/5 hover:text-text">
                            <i class="fas fa-lock"></i> Confidentialite
                        </a>
                        <a href="#account" class="flex items-center gap-3 rounded-2xl px-4 py-3 text-muted hover:bg-white/5 hover:text-text">
                            <i class="fas fa-user-circle"></i> Compte
                        </a>
                    </nav>
                </aside>

                <div class="space-y-6 lg:col-span-3">
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle mt-0.5"></i>
                            <span><?php echo $success; ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle mt-0.5"></i>
                            <span><?php echo $error; ?></span>
                        </div>
                    <?php endif; ?>

                    <section id="security" class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2 sm:p-8">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h2 class="text-lg font-semibold text-text">Securite</h2>
                                <p class="mt-2 text-sm text-muted">Gerez la securite de votre compte.</p>
                            </div>
                        </div>

                        <details class="mt-6 rounded-2xl border border-white/10 bg-white/5 p-4">
                            <summary class="cursor-pointer list-none text-sm font-semibold text-text">Changer le mot de passe</summary>
                            <form method="POST" action="" class="mt-4 space-y-4">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="change_password">

                                <div>
                                    <label for="current_password" class="text-sm font-semibold text-text">Mot de passe actuel</label>
                                    <input type="password" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text focus:outline-none focus:ring-2 focus:ring-accent/60"
                                           id="current_password" name="current_password" required>
                                </div>

                                <div>
                                    <label for="new_password" class="text-sm font-semibold text-text">Nouveau mot de passe</label>
                                    <input type="password" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text focus:outline-none focus:ring-2 focus:ring-accent/60"
                                           id="new_password" name="new_password" minlength="8" required>
                                    <p class="mt-2 text-xs text-muted">Minimum 8 caracteres.</p>
                                </div>

                                <div>
                                    <label for="confirm_password" class="text-sm font-semibold text-text">Confirmer le mot de passe</label>
                                    <input type="password" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text focus:outline-none focus:ring-2 focus:ring-accent/60"
                                           id="confirm_password" name="confirm_password" required>
                                </div>

                                <button type="submit" class="rounded-full bg-accent px-5 py-2 text-sm font-semibold text-white">
                                    <i class="fas fa-save mr-2"></i>Enregistrer
                                </button>
                            </form>
                        </details>

                        <div class="mt-4 flex items-center justify-between rounded-2xl border border-white/10 bg-white/5 p-4">
                            <div>
                                <p class="text-sm font-semibold text-text">Authentification a deux facteurs</p>
                                <p class="text-xs text-muted">Ajoutez une couche de securite supplementaire.</p>
                            </div>
                            <div class="text-xs text-muted">Prochainement</div>
                        </div>
                    </section>

                    <!-- SEC-11 : appareils connectes -->
                    <section id="devices" class="mt-6 rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2 sm:p-8">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h2 class="text-lg font-semibold text-text">Appareils connectes</h2>
                                <p class="mt-2 text-sm text-muted">
                                    Les sessions ouvertes sur votre compte, et les appareils autorises a vous
                                    reconnecter sans mot de passe. Si vous ne reconnaissez pas une ligne, fermez-la
                                    et changez votre mot de passe.
                                </p>
                            </div>
                            <form method="POST" action="">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="revoke_all">
                                <button type="submit" class="rounded-full border border-white/15 px-4 py-2 text-xs font-semibold text-text hover:bg-white/10">
                                    <i class="fas fa-power-off mr-2"></i>Tout fermer ailleurs
                                </button>
                            </form>
                        </div>

                        <h3 class="mt-6 text-sm font-semibold text-text">Sessions ouvertes</h3>
                        <?php if (!$sessionsOuvertes): ?>
                            <p class="mt-2 text-xs text-muted">Aucune session enregistree.</p>
                        <?php else: ?>
                            <ul class="mt-3 space-y-3">
                                <?php foreach ($sessionsOuvertes as $s): ?>
                                    <li class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-white/10 bg-white/5 p-4">
                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold text-text">
                                                <?php echo htmlspecialchars($s['appareil'], ENT_QUOTES, 'UTF-8'); ?>
                                                <?php if ($s['courante']): ?>
                                                    <span class="ml-2 rounded-full bg-accent/20 px-2 py-0.5 text-[11px] text-accent">Cet appareil</span>
                                                <?php endif; ?>
                                            </p>
                                            <p class="mt-1 text-xs text-muted">
                                                <?php echo htmlspecialchars((string) ($s['ip_address'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?>
                                                &middot; active le <?php echo date('d/m/Y \a H\hi', strtotime((string) $s['last_activity'])); ?>
                                            </p>
                                        </div>
                                        <?php if (!$s['courante']): ?>
                                            <form method="POST" action="">
                                                <?php echo csrfField(); ?>
                                                <input type="hidden" name="action" value="revoke_session">
                                                <input type="hidden" name="empreinte" value="<?php echo htmlspecialchars((string) $s['empreinte'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <button type="submit" class="rounded-full border border-white/15 px-4 py-2 text-xs font-semibold text-text hover:bg-white/10">Fermer</button>
                                            </form>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <h3 class="mt-6 text-sm font-semibold text-text">Connexion automatique</h3>
                        <?php if (!$appareilsMemorises): ?>
                            <p class="mt-2 text-xs text-muted">Aucun appareil ne peut vous reconnecter sans mot de passe.</p>
                        <?php else: ?>
                            <ul class="mt-3 space-y-3">
                                <?php foreach ($appareilsMemorises as $d): ?>
                                    <li class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-white/10 bg-white/5 p-4">
                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold text-text">
                                                <?php echo htmlspecialchars((string) ($d['device_label'] ?: 'Appareil inconnu'), ENT_QUOTES, 'UTF-8'); ?>
                                                <?php if ($d['courant']): ?>
                                                    <span class="ml-2 rounded-full bg-accent/20 px-2 py-0.5 text-[11px] text-accent">Cet appareil</span>
                                                <?php endif; ?>
                                            </p>
                                            <p class="mt-1 text-xs text-muted">
                                                <?php echo htmlspecialchars((string) ($d['ip_address'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?>
                                                &middot; dernier usage
                                                <?php echo $d['last_used_at'] ? date('d/m/Y \a H\hi', strtotime((string) $d['last_used_at'])) : 'jamais'; ?>
                                                &middot; expire le <?php echo date('d/m/Y', strtotime((string) $d['expires_at'])); ?>
                                            </p>
                                        </div>
                                        <form method="POST" action="">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="action" value="revoke_device">
                                            <input type="hidden" name="token_id" value="<?php echo (int) $d['id']; ?>">
                                            <button type="submit" class="rounded-full border border-white/15 px-4 py-2 text-xs font-semibold text-text hover:bg-white/10">Retirer</button>
                                        </form>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </section>

                    <section id="notifications" class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2 sm:p-8">
                        <h2 class="text-lg font-semibold text-text">Notifications</h2>
                        <p class="mt-2 text-sm text-muted">Gerez vos preferences de notification.</p>

                        <form method="POST" action="" class="mt-6 space-y-4">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="update_notifications">

                            <label class="flex items-center justify-between rounded-2xl border border-white/10 bg-white/5 p-4 text-sm text-text">
                                <span>Notifications par email</span>
                                <input type="checkbox" class="h-4 w-4 rounded border-white/20 bg-bg text-accent focus:ring-accent/60" checked>
                            </label>
                            <label class="flex items-center justify-between rounded-2xl border border-white/10 bg-white/5 p-4 text-sm text-text">
                                <span>Nouvelles sorties</span>
                                <input type="checkbox" class="h-4 w-4 rounded border-white/20 bg-bg text-accent focus:ring-accent/60" checked>
                            </label>
                            <label class="flex items-center justify-between rounded-2xl border border-white/10 bg-white/5 p-4 text-sm text-text">
                                <span>Activite sociale</span>
                                <input type="checkbox" class="h-4 w-4 rounded border-white/20 bg-bg text-accent focus:ring-accent/60">
                            </label>

                            <button type="submit" class="rounded-full bg-emerald-500 px-5 py-2 text-sm font-semibold text-white">
                                <i class="fas fa-save mr-2"></i>Enregistrer
                            </button>
                        </form>
                    </section>

                    <section id="privacy" class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2 sm:p-8">
                        <h2 class="text-lg font-semibold text-text">Confidentialite</h2>
                        <p class="mt-2 text-sm text-muted">Controlez la visibilite de votre profil.</p>

                        <div class="mt-6 space-y-4">
                            <label class="flex items-center justify-between rounded-2xl border border-white/10 bg-white/5 p-4 text-sm text-text">
                                <span>Profil public</span>
                                <input type="checkbox" class="h-4 w-4 rounded border-white/20 bg-bg text-accent focus:ring-accent/60" checked>
                            </label>
                            <label class="flex items-center justify-between rounded-2xl border border-white/10 bg-white/5 p-4 text-sm text-text">
                                <span>Afficher l historique d ecoute</span>
                                <input type="checkbox" class="h-4 w-4 rounded border-white/20 bg-bg text-accent focus:ring-accent/60" checked>
                            </label>
                            <label class="flex items-center justify-between rounded-2xl border border-white/10 bg-white/5 p-4 text-sm text-text">
                                <span>Playlists publiques par defaut</span>
                                <input type="checkbox" class="h-4 w-4 rounded border-white/20 bg-bg text-accent focus:ring-accent/60" checked>
                            </label>
                        </div>
                    </section>

                    <section id="account" class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2 sm:p-8">
                        <h2 class="text-lg font-semibold text-text">Compte</h2>
                        <p class="mt-2 text-sm text-muted">Gerez votre abonnement et vos donnees.</p>

                        <div class="mt-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-white/10 bg-white/5 p-4 text-sm">
                            <div>
                                <p class="font-semibold text-text">Type de compte</p>
                                <p class="text-muted"><?php echo !empty($user['premium_status']) ? 'Premium' : 'Gratuit'; ?></p>
                            </div>
                            <?php if (empty($user['premium_status'])): ?>
                                <a href="<?php echo SITE_URL; ?>/premium.php" class="rounded-full bg-amber-400 px-4 py-2 text-xs font-semibold text-bg">
                                    <i class="fas fa-crown mr-2"></i>Passer a Premium
                                </a>
                            <?php else: ?>
                                <span class="rounded-full bg-amber-400/20 px-4 py-2 text-xs font-semibold text-amber-300">
                                    <i class="fas fa-crown mr-1"></i>Premium
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="mt-6 rounded-2xl border border-rose-400/40 bg-rose-500/10 p-4 text-sm text-rose-200">
                            <p class="font-semibold">Zone dangereuse</p>
                            <p class="mt-1 text-xs text-rose-100/80">Contactez le support pour supprimer votre compte.</p>
                            <button class="mt-4 rounded-full border border-rose-400/40 bg-rose-500/10 px-4 py-2 text-xs font-semibold text-rose-200" type="button" disabled>
                                <i class="fas fa-trash mr-2"></i>Supprimer mon compte
                            </button>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include 'includes/footer-tailwind.php'; ?>
