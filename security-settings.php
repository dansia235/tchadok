<?php
/**
 * Paramètres de sécurité - Tchadok Platform
 * Migration Tailwind (progressive)
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/advanced-auth.php';

if (!isLoggedIn()) {
    header('Location: ' . SITE_URL . '/login.php?redirect=security-settings');
    exit();
}

$pageTitle = 'Paramètres de sécurité';
$pageDescription = 'Gérez la sécurité de votre compte Tchadok';
$hideTopNav = true;
$hideFooter = true;

$userId = $_SESSION['user_id'];
$user = getCurrentUser();
if (!$user) {
    header('Location: ' . SITE_URL . '/login.php?redirect=security-settings');
    exit();
}
$user2FA = get2FASettings($userId);

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'enable_2fa_totp':
            $result = enable2FA($userId, 'totp');
            if ($result['success']) {
                $_SESSION['2fa_setup'] = $result;
                $message = 'Authentification à deux facteurs configurée avec succès';
                $messageType = 'success';
            } else {
                $message = $result['error'];
                $messageType = 'error';
            }
            break;

        case 'enable_2fa_sms':
            $phone = $_POST['phone'] ?? '';
            $result = enable2FA($userId, 'sms', $phone);
            if ($result['success']) {
                $message = 'SMS 2FA activée avec succès';
                $messageType = 'success';
            } else {
                $message = $result['error'];
                $messageType = 'error';
            }
            break;

        case 'change_password':
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
                $strength = checkPasswordStrength($newPassword);
                if ($strength['score'] < 50) {
                    $message = 'Mot de passe trop faible: ' . implode(', ', $strength['feedback']);
                    $messageType = 'error';
                } else {
                    try {
                        $dbInstance = TchadokDatabase::getInstance();
                        $db = $dbInstance->getConnection();

                        $stmt = $db->prepare("
                            SELECT COALESCE(NULLIF(password_hash, ''), password) AS current_hash
                            FROM users
                            WHERE id = ?
                        ");
                        $stmt->execute([$userId]);
                        $currentHash = $stmt->fetchColumn();

                        if (!$currentHash || !verifyPassword($currentPassword, $currentHash)) {
                            $message = 'Le mot de passe actuel est incorrect';
                            $messageType = 'error';
                        } else {
                            $newPasswordHash = hashPassword($newPassword);
                            $stmt = $db->prepare("
                                UPDATE users
                                SET password = ?,
                                    password_hash = ?,
                                    updated_at = NOW()
                                WHERE id = ?
                            ");
                            $stmt->execute([$newPasswordHash, $newPasswordHash, $userId]);

                            $_SESSION['strong_password'] = true;
                            $message = 'Mot de passe modifié avec succès';
                            $messageType = 'success';
                        }
                    } catch (Exception $e) {
                        $message = 'Impossible de mettre à jour le mot de passe pour le moment';
                        $messageType = 'error';
                    }
                }
            }
            break;
    }
}

$securityScore = 60;
if (!empty($user2FA['enabled'])) {
    $securityScore += 30;
}
if (isset($_SESSION['strong_password'])) {
    $securityScore += 10;
}
$securityScore = min(100, $securityScore);
$scoreDash = $securityScore * 2.827;
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
$memberSince = !empty($user['created_at']) ? date('M Y', strtotime($user['created_at'])) : date('M Y');

$twoFactorSetup = isset($_SESSION['2fa_setup']) && is_array($_SESSION['2fa_setup']) ? $_SESSION['2fa_setup'] : null;
$totpSecret = !empty($twoFactorSetup['secret']) ? $twoFactorSetup['secret'] : 'JBSWY3DPEHPK3PXP';
$totpQrPayload = !empty($twoFactorSetup['qr_url'])
    ? $twoFactorSetup['qr_url']
    : generateTOTPQRCodeURL($totpSecret, 'Tchadok', $user['email'] ?? 'user@example.com');
$totpQrImage = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . rawurlencode($totpQrPayload);
$backupCodes = !empty($twoFactorSetup['backup_codes']) && is_array($twoFactorSetup['backup_codes'])
    ? $twoFactorSetup['backup_codes']
    : [];
$autoOpenModal = (!empty($twoFactorSetup['method']) && $twoFactorSetup['method'] === 'totp') ? 'totp' : '';

$activities = [
    ['action' => 'Connexion réussie', 'location' => "N'Djamena", 'time' => 'Il y a 2h', 'icon' => 'sign-in-alt', 'tone' => 'text-emerald-300'],
    ['action' => 'Mot de passe modifié', 'location' => "N'Djamena", 'time' => 'Il y a 3 jours', 'icon' => 'key', 'tone' => 'text-sky-300'],
    ['action' => 'Connexion échouée', 'location' => 'Abeche', 'time' => 'Il y a 1 semaine', 'icon' => 'exclamation-triangle', 'tone' => 'text-amber-300'],
    ['action' => 'Email vérifié', 'location' => "N'Djamena", 'time' => 'Il y a 2 semaines', 'icon' => 'envelope-check', 'tone' => 'text-emerald-300']
];

if ($isAdminSecurityView) {
    $adminShellMetrics = [
        ['value' => $securityScore . '%', 'label' => 'score sécurité', 'tone' => 'border-blue-200 bg-blue-50 text-blue-700'],
        ['value' => !empty($user2FA['enabled']) ? '2FA activée' : '2FA à configurer', 'label' => '', 'tone' => !empty($user2FA['enabled']) ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700'],
        ['value' => 'console admin', 'label' => 'protégée', 'tone' => 'border-slate-200 bg-slate-50 text-slate-700']
    ];
    $dashboardSecondaryNavLabel = 'Sécurité admin';
    $dashboardSecondaryNavItems = [
        ['label' => 'Aperçu', 'target' => 'security-overview', 'icon' => 'home'],
        ['label' => '2FA', 'target' => 'security-2fa', 'icon' => 'mobile-alt'],
        ['label' => 'Mot de passe', 'target' => 'security-password', 'icon' => 'key'],
        ['label' => 'Outils', 'target' => 'security-admin-tools', 'icon' => 'terminal']
    ];
}

$additionalJS = [];
if ($isAdminSecurityView) {
    $additionalJS[] = SITE_URL . '/assets/js/dashboard-sticky-header.js';
    $additionalJS[] = SITE_URL . '/assets/js/dashboard-secondary-nav.js';
}
$additionalJS[] = SITE_URL . '/assets/js/security-settings.js';

include 'includes/header-tailwind.php';
?>

<main class="min-h-screen bg-[radial-gradient(circle_at_top_right,rgba(47,109,224,0.18),transparent_28%),radial-gradient(circle_at_top_left,rgba(16,185,129,0.14),transparent_24%),#0B0F17] pb-16 pt-8">
    <?php if ($isAdminSecurityView): ?>
        <?php include 'includes/admin-shell-header.php'; ?>
    <?php endif; ?>

    <section id="security-overview">
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
                                <h1 class="mt-2 text-3xl font-display font-bold text-text sm:text-4xl">Paramètres de sécurité</h1>
                                <p class="mt-3 max-w-2xl text-sm leading-6 text-muted">
                                    Protégez votre compte avec une vue claire sur la 2FA, les mots de passe,
                                    les appareils connectés et l'activité récente.
                                </p>
                                <div class="mt-4 flex flex-wrap gap-2 text-xs">
                                    <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-muted"><?php echo htmlspecialchars($roleLabel); ?></span>
                                    <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-muted">Membre depuis <?php echo htmlspecialchars($memberSince); ?></span>
                                    <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-muted"><?php echo htmlspecialchars($fullName); ?></span>
                                    <?php if ($isAdminSecurityView): ?>
                                        <span class="rounded-full border border-slate-200/20 bg-slate-500/10 px-3 py-1 text-slate-200">Accès admin et maintenance sensibles</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="grid gap-3 sm:min-w-[240px]">
                            <a href="<?php echo SITE_URL; ?>/settings.php" class="inline-flex items-center justify-center gap-2 rounded-full bg-accent px-5 py-3 text-sm font-semibold text-white shadow-elev-1">
                                <i class="fas fa-sliders-h"></i>
                                Paramètres généraux
                            </a>
                            <a href="<?php echo $dashboardUrl; ?>" class="inline-flex items-center justify-center gap-2 rounded-full border border-white/10 bg-white/5 px-5 py-3 text-sm font-semibold text-text hover:bg-white/10">
                                <i class="fas fa-columns"></i>
                                Retour au dashboard
                            </a>
                        </div>
                    </div>
                </div>

                <div class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2">
                    <p class="text-xs uppercase tracking-[0.24em] text-muted">Niveau de protection</p>
                    <div class="mt-4 space-y-4">
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-muted">Score</span>
                                <span class="text-sm font-semibold text-text"><?php echo $securityScore; ?>%</span>
                            </div>
                            <div class="mt-3 h-2 w-full rounded-full bg-white/10">
                                <div class="h-2 rounded-full <?php echo $securityScore >= 80 ? 'bg-emerald-400' : ($securityScore >= 60 ? 'bg-amber-400' : 'bg-rose-400'); ?>" style="width: <?php echo $securityScore; ?>%"></div>
                            </div>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
                            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                                <p class="text-xs uppercase tracking-[0.2em] text-muted">2FA</p>
                                <p class="mt-2 text-sm font-semibold text-text"><?php echo !empty($user2FA['enabled']) ? 'Activée' : 'À configurer'; ?></p>
                            </div>
                            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                                <p class="text-xs uppercase tracking-[0.2em] text-muted">Surveillance</p>
                                <p class="mt-2 text-sm font-semibold text-text">Activité récente visible</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="mt-6 alert <?php echo $messageType === 'success' ? 'alert-success' : 'alert-danger'; ?>">
                    <i class="fas <?php echo $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> mt-0.5"></i>
                    <span><?php echo htmlspecialchars($message); ?></span>
                </div>
            <?php endif; ?>

            <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1.2fr)_360px]">
                <div class="space-y-6">
                    <div class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2 sm:p-8">
                        <div class="flex items-start gap-4">
                            <div class="relative h-20 w-20">
                                <svg class="h-full w-full -rotate-90" viewBox="0 0 100 100">
                                    <circle cx="50" cy="50" r="45" fill="none" stroke="currentColor" stroke-width="8" class="text-white/10"></circle>
                                    <circle cx="50" cy="50" r="45" fill="none" stroke="currentColor" stroke-width="8"
                                            stroke-dasharray="<?php echo $scoreDash; ?> 283"
                                            stroke-linecap="round"
                                            class="<?php echo $securityScore >= 80 ? 'text-emerald-400' : ($securityScore >= 60 ? 'text-amber-300' : 'text-rose-400'); ?>"></circle>
                                </svg>
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <span class="text-lg font-semibold text-text"><?php echo $securityScore; ?>%</span>
                                </div>
                            </div>
                            <div>
                                <h2 class="text-lg font-semibold text-text">État de la sécurité</h2>
                                <p class="mt-1 text-sm text-muted">
                                    Score:
                                    <?php if ($securityScore >= 80): ?>
                                        <span class="text-emerald-300">Excellent</span>
                                    <?php elseif ($securityScore >= 60): ?>
                                        <span class="text-amber-300">Bon</span>
                                    <?php else: ?>
                                        <span class="text-rose-300">À améliorer</span>
                                    <?php endif; ?>
                                </p>
                                <div class="mt-4 grid gap-2 text-sm text-muted sm:grid-cols-2">
                                    <div class="flex items-center gap-2">
                                        <i class="fas fa-check-circle text-emerald-300"></i>
                                        Mot de passe sécurisé
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <i class="fas fa-<?php echo !empty($user2FA['enabled']) ? 'check-circle text-emerald-300' : 'times-circle text-rose-300'; ?>"></i>
                                        2FA activée
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <i class="fas fa-check-circle text-emerald-300"></i>
                                        Email vérifié
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <i class="fas fa-times-circle text-amber-300"></i>
                                        Appareils de confiance
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="security-2fa" class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2 sm:p-8">
                        <h2 class="text-lg font-semibold text-text">Authentification à deux facteurs</h2>
                        <p class="mt-2 text-sm text-muted">Ajoutez une couche de protection supplémentaire.</p>

                        <?php if (empty($user2FA['enabled'])): ?>
                            <div class="mt-4 rounded-2xl border border-amber-400/40 bg-amber-400/10 p-4 text-sm text-amber-200">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                La 2FA n'est pas activée.
                            </div>
                            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                                <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                                    <div class="flex items-center gap-3">
                                        <span class="grid h-11 w-11 place-items-center rounded-xl bg-accent/20 text-accent">
                                            <i class="fas fa-mobile-alt"></i>
                                        </span>
                                        <div>
                                            <p class="text-sm font-semibold text-text">Application d'authentification</p>
                                            <p class="text-xs text-muted">Google Authenticator ou similaire.</p>
                                        </div>
                                    </div>
                                    <div class="mt-4 flex items-center justify-between gap-3">
                                        <span class="text-[11px] uppercase tracking-[0.2em] text-muted">Guide 3 étapes</span>
                                        <button class="rounded-full bg-accent px-4 py-2 text-xs font-semibold text-white" type="button" data-modal-open="totp">Configurer</button>
                                    </div>
                                </div>
                                <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                                    <div class="flex items-center gap-3">
                                        <span class="grid h-11 w-11 place-items-center rounded-xl bg-emerald-500/20 text-emerald-300">
                                            <i class="fas fa-sms"></i>
                                        </span>
                                        <div>
                                            <p class="text-sm font-semibold text-text">SMS</p>
                                            <p class="text-xs text-muted">Recevez des codes par SMS.</p>
                                        </div>
                                    </div>
                                    <div class="mt-4 flex items-center justify-between gap-3">
                                        <span class="text-[11px] uppercase tracking-[0.2em] text-muted">Vérification mobile</span>
                                        <button class="rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-semibold text-text" type="button" data-modal-open="sms">Configurer</button>
                                    </div>
                                </div>
                            </div>

                            <?php if ($twoFactorSetup): ?>
                                <div class="mt-5 rounded-3xl border border-emerald-400/25 bg-emerald-500/10 p-5">
                                    <div class="flex flex-wrap items-start justify-between gap-4">
                                        <div>
                                            <p class="text-xs uppercase tracking-[0.24em] text-emerald-200/80">Configuration en cours</p>
                                            <h3 class="mt-2 text-base font-semibold text-emerald-100">Éléments de récupération prêts</h3>
                                            <p class="mt-2 text-sm text-emerald-100/80">
                                                Conservez votre code manuel et vos codes de récupération avant de fermer cette session.
                                            </p>
                                        </div>
                                        <button class="inline-flex items-center gap-2 rounded-full border border-emerald-300/30 bg-emerald-500/10 px-4 py-2 text-xs font-semibold text-emerald-100" type="button" data-modal-open="totp">
                                            <i class="fas fa-qrcode"></i>
                                            Rouvrir la configuration
                                        </button>
                                    </div>
                                    <div class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1fr)_220px]">
                                        <div class="rounded-2xl border border-white/10 bg-black/10 p-4">
                                            <div class="flex items-center justify-between gap-3">
                                                <span class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-100/80">Code manuel</span>
                                                <button class="text-xs font-semibold text-emerald-100" type="button" data-copy-value="<?php echo htmlspecialchars($totpSecret); ?>" data-copy-label="Secret TOTP copié">
                                                    Copier
                                                </button>
                                            </div>
                                            <p class="mt-3 break-all font-mono text-sm text-white"><?php echo htmlspecialchars($totpSecret); ?></p>
                                        </div>
                                        <div class="rounded-2xl border border-white/10 bg-black/10 p-4">
                                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-100/80">Codes de secours</p>
                                            <div class="mt-3 grid grid-cols-2 gap-2 text-[11px] font-semibold text-white/90">
                                                <?php foreach (array_slice($backupCodes, 0, 4) as $code): ?>
                                                    <div class="rounded-xl border border-white/10 bg-white/5 px-2 py-2 text-center"><?php echo htmlspecialchars($code); ?></div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="mt-4 rounded-2xl border border-emerald-400/30 bg-emerald-500/10 p-4 text-sm text-emerald-200">
                                <i class="fas fa-check-circle mr-2"></i>
                                2FA activée via <?php echo htmlspecialchars(ucfirst($user2FA['method'])); ?>
                            </div>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <button class="rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-semibold text-text" type="button" data-ui-action="manage-2fa">
                                    <i class="fas fa-cog mr-2"></i>Gérer la 2FA
                                </button>
                                <button class="rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-semibold text-text" type="button" data-ui-action="show-backup-codes">
                                    <i class="fas fa-download mr-2"></i>Codes de récupération
                                </button>
                                <button class="rounded-full border border-rose-400/40 bg-rose-500/10 px-4 py-2 text-xs font-semibold text-rose-200" type="button" data-ui-action="disable-2fa">
                                    <i class="fas fa-times mr-2"></i>Désactiver
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div id="security-password" class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2 sm:p-8">
                        <h2 class="text-lg font-semibold text-text">Changer le mot de passe</h2>
                        <p class="mt-2 text-sm text-muted">Utilisez un mot de passe fort et unique.</p>

                        <form method="POST" class="mt-5 space-y-4" data-password-form>
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
                                       id="new_password" name="new_password" data-password-input autocomplete="new-password" required>
                                <div class="mt-3 hidden" data-strength-wrapper>
                                    <div class="flex justify-between text-xs text-muted">
                                        <span>Force du mot de passe</span>
                                        <span data-strength-text></span>
                                    </div>
                                    <div class="mt-2 h-2 w-full rounded-full bg-white/10">
                                        <div class="h-2 w-0 rounded-full" data-strength-bar></div>
                                    </div>
                                    <ul class="mt-2 space-y-1 text-xs text-rose-200" data-strength-feedback></ul>
                                </div>
                                <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                    <div class="rounded-2xl border border-white/10 bg-white/5 px-3 py-2 text-xs text-muted" data-password-rule="length">
                                        <i class="fas fa-circle-notch mr-2 text-[10px]"></i>8 caractères minimum
                                    </div>
                                    <div class="rounded-2xl border border-white/10 bg-white/5 px-3 py-2 text-xs text-muted" data-password-rule="mixed">
                                        <i class="fas fa-circle-notch mr-2 text-[10px]"></i>Minuscules et majuscules
                                    </div>
                                    <div class="rounded-2xl border border-white/10 bg-white/5 px-3 py-2 text-xs text-muted" data-password-rule="number">
                                        <i class="fas fa-circle-notch mr-2 text-[10px]"></i>Au moins un chiffre
                                    </div>
                                    <div class="rounded-2xl border border-white/10 bg-white/5 px-3 py-2 text-xs text-muted" data-password-rule="symbol">
                                        <i class="fas fa-circle-notch mr-2 text-[10px]"></i>Un symbole recommandé
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label for="confirm_password" class="text-sm font-semibold text-text">Confirmer le mot de passe</label>
                                <input type="password" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text focus:outline-none focus:ring-2 focus:ring-accent/60"
                                       id="confirm_password" name="confirm_password" data-confirm-input autocomplete="new-password" required>
                                <div class="mt-2 hidden rounded-2xl border border-white/10 bg-white/5 px-3 py-2 text-xs text-muted" data-confirm-status></div>
                            </div>

                            <button type="submit" class="rounded-full bg-accent px-6 py-3 text-sm font-semibold text-white shadow-elev-1 hover:shadow-elev-2">
                                <i class="fas fa-save mr-2"></i>Changer le mot de passe
                            </button>
                        </form>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2">
                        <h3 class="text-base font-semibold text-text">Activité récente</h3>
                        <div class="mt-4 space-y-3">
                            <?php foreach ($activities as $activity): ?>
                                <div class="flex items-start gap-3 rounded-2xl border border-white/10 bg-white/5 p-3">
                                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-white/10 <?php echo $activity['tone']; ?>">
                                        <i class="fas fa-<?php echo $activity['icon']; ?>"></i>
                                    </span>
                                    <div>
                                        <p class="text-sm font-semibold text-text"><?php echo $activity['action']; ?></p>
                                        <p class="text-xs text-muted"><?php echo $activity['location']; ?> - <?php echo $activity['time']; ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button class="mt-4 w-full rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-semibold text-text" type="button" data-ui-action="view-security-history">
                            Voir tout l'historique
                        </button>
                    </div>

                    <div class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2">
                        <h3 class="text-base font-semibold text-text">Appareils connectés</h3>
                        <div class="mt-4 space-y-3">
                            <div class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 p-3">
                                <span class="grid h-10 w-10 place-items-center rounded-xl bg-accent/20 text-accent">
                                    <i class="fas fa-laptop"></i>
                                </span>
                                <div class="flex-1">
                                    <p class="text-sm font-semibold text-text">Chrome sur Windows</p>
                                    <p class="text-xs text-emerald-300">Actuel</p>
                                </div>
                                <button class="text-rose-300" type="button" data-ui-action="revoke-device" data-device-label="Chrome sur Windows"><i class="fas fa-times"></i></button>
                            </div>
                            <div class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 p-3">
                                <span class="grid h-10 w-10 place-items-center rounded-xl bg-sky-400/20 text-sky-200">
                                    <i class="fas fa-mobile-alt"></i>
                                </span>
                                <div class="flex-1">
                                    <p class="text-sm font-semibold text-text">Safari sur iPhone</p>
                                    <p class="text-xs text-muted">Il y a 2 jours</p>
                                </div>
                                <button class="text-rose-300" type="button" data-ui-action="revoke-device" data-device-label="Safari sur iPhone"><i class="fas fa-times"></i></button>
                            </div>
                        </div>
                        <button class="mt-4 w-full rounded-full border border-rose-400/40 bg-rose-500/10 px-4 py-2 text-xs font-semibold text-rose-200" type="button" data-ui-action="disconnect-all-devices">
                            Déconnecter tous les appareils
                        </button>
                    </div>

                    <?php if ($isAdminSecurityView): ?>
                        <div id="security-admin-tools" class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2">
                            <h3 class="text-base font-semibold text-text">Outils admin sensibles</h3>
                            <p class="mt-2 text-sm text-muted">
                                Centralisez ici les actions de sécurité avancée et de maintenance technique sans surcharger le header principal.
                            </p>
                            <div class="mt-4 space-y-3 text-sm">
                                <a href="<?php echo SITE_URL; ?>/admin/reset-password.php" class="flex items-center justify-between rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-text hover:bg-white/10">
                                    <span class="inline-flex items-center gap-2">
                                        <i class="fas fa-unlock-keyhole text-accent"></i>
                                        Réinitialiser un accès administrateur
                                    </span>
                                    <i class="fas fa-arrow-right text-xs text-muted"></i>
                                </a>
                                <a href="<?php echo SITE_URL; ?>/admin-dashboard.php" class="flex items-center justify-between rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-text hover:bg-white/10">
                                    <span class="inline-flex items-center gap-2">
                                        <i class="fas fa-shield-alt text-accent"></i>
                                        Retour à la console admin
                                    </span>
                                    <i class="fas fa-arrow-right text-xs text-muted"></i>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2">
                        <h3 class="text-base font-semibold text-text">Conseils de sécurité</h3>
                        <ul class="mt-4 space-y-3 text-sm text-muted">
                            <li class="flex items-center gap-2"><i class="fas fa-shield-alt text-emerald-300"></i> Utilisez un mot de passe unique</li>
                            <li class="flex items-center gap-2"><i class="fas fa-lock text-accent"></i> Activez la 2FA</li>
                            <li class="flex items-center gap-2"><i class="fas fa-eye text-amber-300"></i> Vérifiez votre activité</li>
                            <li class="flex items-center gap-2"><i class="fas fa-wifi text-rose-300"></i> Évitez les WiFi publics</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<div class="fixed inset-0 z-50 hidden items-center justify-center bg-black/75 p-4" data-modal="totp" data-modal-auto-open="<?php echo $autoOpenModal === 'totp' ? 'true' : 'false'; ?>">
    <div class="w-full max-w-3xl translate-y-4 scale-95 rounded-3xl border border-white/10 bg-surface/95 p-6 opacity-0 shadow-elev-3 transition duration-200 sm:p-8" data-modal-panel>
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-[0.24em] text-muted">Authentification 2FA</p>
                <h3 class="mt-2 text-xl font-semibold text-text">Configuration TOTP</h3>
            </div>
            <button class="grid h-10 w-10 place-items-center rounded-2xl border border-white/10 bg-white/5 text-muted hover:text-text" type="button" data-modal-close>&times;</button>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
            <div>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p class="text-xs uppercase tracking-[0.2em] text-muted">1</p>
                        <p class="mt-2 text-sm font-semibold text-text">Installez l'application</p>
                        <p class="mt-1 text-xs text-muted">Google Authenticator, Microsoft Authenticator ou équivalent.</p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p class="text-xs uppercase tracking-[0.2em] text-muted">2</p>
                        <p class="mt-2 text-sm font-semibold text-text">Scannez ou copiez</p>
                        <p class="mt-1 text-xs text-muted">Utilisez le QR code ou le code manuel si besoin.</p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p class="text-xs uppercase tracking-[0.2em] text-muted">3</p>
                        <p class="mt-2 text-sm font-semibold text-text">Finalisez</p>
                        <p class="mt-1 text-xs text-muted">Entrez un code à 6 chiffres pour valider.</p>
                    </div>
                </div>

                <div class="mt-5 rounded-3xl border border-white/10 bg-white p-5 text-center">
                    <img src="<?php echo htmlspecialchars($totpQrImage); ?>" alt="QR Code 2FA" class="mx-auto h-44 w-44 rounded-2xl">
                    <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-left">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Code manuel</span>
                            <button class="text-xs font-semibold text-slate-700" type="button" data-copy-value="<?php echo htmlspecialchars($totpSecret); ?>" data-copy-label="Code TOTP copié">
                                Copier
                            </button>
                        </div>
                        <p class="mt-2 break-all font-mono text-sm text-slate-700"><?php echo htmlspecialchars($totpSecret); ?></p>
                    </div>
                </div>
            </div>

            <div class="space-y-4">
                <div class="rounded-3xl border border-white/10 bg-white/5 p-5">
                    <h4 class="text-sm font-semibold text-text">Codes de récupération</h4>
                    <p class="mt-2 text-xs leading-5 text-muted">Conservez-les hors ligne pour récupérer votre compte si vous perdez l'application.</p>
                    <?php if (!empty($backupCodes)): ?>
                        <div class="mt-4 grid grid-cols-2 gap-2 text-[11px] font-semibold text-text">
                            <?php foreach (array_slice($backupCodes, 0, 6) as $code): ?>
                                <div class="rounded-xl border border-white/10 bg-bg/70 px-3 py-2 text-center"><?php echo htmlspecialchars($code); ?></div>
                            <?php endforeach; ?>
                        </div>
                        <button class="mt-4 w-full rounded-full border border-white/10 bg-bg/70 px-4 py-2 text-xs font-semibold text-text" type="button" data-copy-value="<?php echo htmlspecialchars(implode(', ', $backupCodes)); ?>" data-copy-label="Codes de récupération copiés">
                            <i class="fas fa-copy mr-2"></i>Copier les codes
                        </button>
                    <?php else: ?>
                        <div class="mt-4 rounded-2xl border border-dashed border-white/10 bg-bg/60 p-4 text-xs text-muted">
                            Ils seront générés juste après l'initialisation de la 2FA et visibles dans cette fenêtre.
                        </div>
                        <button class="mt-4 w-full rounded-full border border-white/10 bg-bg/70 px-4 py-2 text-xs font-semibold text-muted opacity-60" type="button" disabled>
                            <i class="fas fa-copy mr-2"></i>Codes disponibles après setup
                        </button>
                    <?php endif; ?>
                </div>

                <form method="POST" class="rounded-3xl border border-white/10 bg-white/5 p-5 space-y-4">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="enable_2fa_totp">
                    <div>
                        <label for="totp_code" class="text-sm font-semibold text-text">Code de vérification</label>
                        <input type="text" id="totp_code" name="totp_code" maxlength="6" inputmode="numeric" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-center text-sm tracking-[0.35em] text-text focus:outline-none focus:ring-2 focus:ring-accent/60" placeholder="000000" data-modal-focus required>
                        <p class="mt-2 text-xs text-muted">Le code change toutes les 30 secondes.</p>
                    </div>
                    <button type="submit" class="w-full rounded-full bg-accent px-4 py-3 text-sm font-semibold text-white">Activer la 2FA</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="fixed inset-0 z-50 hidden items-center justify-center bg-black/75 p-4" data-modal="sms" data-modal-auto-open="false">
    <div class="w-full max-w-xl translate-y-4 scale-95 rounded-3xl border border-white/10 bg-surface/95 p-6 opacity-0 shadow-elev-3 transition duration-200 sm:p-8" data-modal-panel>
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-[0.24em] text-muted">Authentification 2FA</p>
                <h3 class="mt-2 text-xl font-semibold text-text">Configuration SMS</h3>
            </div>
            <button class="grid h-10 w-10 place-items-center rounded-2xl border border-white/10 bg-white/5 text-muted hover:text-text" type="button" data-modal-close>&times;</button>
        </div>
        <div class="mt-5 rounded-3xl border border-white/10 bg-white/5 p-5">
            <div class="grid gap-3 sm:grid-cols-3 text-xs text-muted">
                <div class="rounded-2xl border border-white/10 bg-bg/60 p-3">Entrez un numéro valide</div>
                <div class="rounded-2xl border border-white/10 bg-bg/60 p-3">Recevez un code temporaire</div>
                <div class="rounded-2xl border border-white/10 bg-bg/60 p-3">Confirmez vos connexions sensibles</div>
            </div>
        </div>
        <form method="POST" class="mt-5 space-y-4 rounded-3xl border border-white/10 bg-white/5 p-5">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="enable_2fa_sms">
            <div>
                <label for="phone" class="text-sm font-semibold text-text">Numéro de téléphone</label>
                <input type="tel" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text focus:outline-none focus:ring-2 focus:ring-accent/60"
                       id="phone" name="phone" placeholder="+235 XX XX XX XX" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" data-phone-input data-modal-focus required>
                <p class="mt-2 text-xs text-muted">Format tchadien requis. Le numéro sera normalisé automatiquement.</p>
            </div>
            <button type="submit" class="w-full rounded-full bg-accent px-4 py-3 text-sm font-semibold text-white">Configurer SMS 2FA</button>
        </form>
    </div>
</div>

<?php include 'includes/footer-tailwind.php'; ?>
