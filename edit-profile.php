<?php
/**
 * Modifier le profil - Tchadok Platform
 * Migration Tailwind (progressive)
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Verifier si l'utilisateur est connecte
if (!isLoggedIn()) {
    header('Location: ' . SITE_URL . '/login.php?redirect=edit-profile');
    exit();
}

$pageTitle = 'Modifier le profil';
$pageDescription = 'Mettez a jour vos informations personnelles';
$hideTopNav = true;
$hideFooter = true;

$user = getCurrentUser();
if (!$user) {
    header('Location: ' . SITE_URL . '/login.php?redirect=edit-profile');
    exit();
}
$success = '';
$error = '';

// Traiter la soumission du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = sanitizeInput($_POST['first_name'] ?? '');
    $lastName = sanitizeInput($_POST['last_name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $city = sanitizeInput($_POST['city'] ?? '');
    $bio = sanitizeInput($_POST['bio'] ?? '');

    // Validation
    if (empty($firstName) || empty($lastName)) {
        $error = 'Le prenom et le nom sont obligatoires.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Adresse email invalide.';
    } else {
        try {
            $dbInstance = TchadokDatabase::getInstance();
            $db = $dbInstance->getConnection();

            $userId = $_SESSION['user_id'];

            // Verifier si l'email est deja utilise par un autre utilisateur
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $userId]);

            if ($stmt->fetch()) {
                $error = 'Cet email est deja utilise par un autre compte.';
            } else {
                // Mettre a jour le profil
                $stmt = $db->prepare("
                    UPDATE users
                    SET first_name = ?,
                        last_name = ?,
                        email = ?,
                        phone = ?,
                        city = ?,
                        bio = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");

                $stmt->execute([
                    $firstName,
                    $lastName,
                    $email,
                    $phone,
                    $city,
                    $bio,
                    $userId
                ]);

                // Mettre a jour la session
                $_SESSION['user_data']['first_name'] = $firstName;
                $_SESSION['user_data']['last_name'] = $lastName;
                $_SESSION['user_data']['email'] = $email;

                // Recharger les donnees utilisateur
                $user = getCurrentUser();

                $success = 'Profil mis a jour avec succes !';
            }
        } catch (Exception $e) {
            $error = 'Une erreur est survenue lors de la mise a jour du profil.';
        }
    }
}

$bioLength = strlen($user['bio'] ?? '');
$completion = 0;
if (!empty($user['first_name'])) $completion += 15;
if (!empty($user['last_name'])) $completion += 15;
if (!empty($user['email'])) $completion += 15;
if (!empty($user['phone'])) $completion += 15;
if (!empty($user['city'])) $completion += 20;
if (!empty($user['bio'])) $completion += 20;

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
                                <h1 class="mt-2 text-3xl font-display font-bold text-text sm:text-4xl">Modifier le profil</h1>
                                <p class="mt-3 max-w-2xl text-sm leading-6 text-muted">
                                    Affinez votre identite, vos coordonnees et votre presentation avec une interface
                                    plus claire, plus lisible et orientee action.
                                </p>
                                <div class="mt-4 flex flex-wrap gap-2 text-xs">
                                    <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-muted"><?php echo htmlspecialchars($roleLabel); ?></span>
                                    <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-muted">Membre depuis <?php echo htmlspecialchars($memberSince); ?></span>
                                    <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-muted">@<?php echo htmlspecialchars($user['username'] ?? ''); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="grid gap-3 sm:min-w-[240px]">
                            <a href="<?php echo SITE_URL; ?>/settings.php" class="inline-flex items-center justify-center gap-2 rounded-full bg-accent px-5 py-3 text-sm font-semibold text-white shadow-elev-1">
                                <i class="fas fa-sliders-h"></i>
                                Parametres
                            </a>
                            <a href="<?php echo $dashboardUrl; ?>" class="inline-flex items-center justify-center gap-2 rounded-full border border-white/10 bg-white/5 px-5 py-3 text-sm font-semibold text-text hover:bg-white/10">
                                <i class="fas fa-columns"></i>
                                Retour au dashboard
                            </a>
                        </div>
                    </div>
                </div>

                <div class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2">
                    <p class="text-xs uppercase tracking-[0.24em] text-muted">Synthese</p>
                    <div class="mt-4 space-y-4">
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                            <p class="text-sm text-muted">Completude du profil</p>
                            <div class="mt-3 h-2 w-full rounded-full bg-white/10">
                                <div class="h-2 rounded-full bg-gradient-to-r from-accent to-emerald-400" style="width: <?php echo $completion; ?>%"></div>
                            </div>
                            <p class="mt-3 text-2xl font-semibold text-text"><?php echo $completion; ?>%</p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                            <p class="text-xs uppercase tracking-[0.2em] text-muted">Identite</p>
                            <p class="mt-2 text-sm font-semibold text-text"><?php echo htmlspecialchars($fullName); ?></p>
                            <p class="mt-1 text-xs text-muted"><?php echo htmlspecialchars($user['email'] ?? ''); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="mt-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-6 lg:grid-cols-[minmax(0,1.2fr)_360px]">
                <div class="space-y-6">
                    <div class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2 sm:p-8">
                        <h2 class="text-xl font-display font-semibold text-text">Informations personnelles</h2>
                        <p class="mt-2 text-sm text-muted">Gardez vos informations a jour pour une meilleure experience.</p>

                        <?php if ($success): ?>
                            <div class="alert alert-success mt-5">
                                <i class="fas fa-check-circle mt-0.5"></i>
                                <span><?php echo $success; ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if ($error): ?>
                            <div class="alert alert-danger mt-5">
                                <i class="fas fa-exclamation-circle mt-0.5"></i>
                                <span><?php echo $error; ?></span>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="" class="mt-6 space-y-6">
                            <?php echo csrfField(); ?>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="first_name" class="text-sm font-semibold text-text">Prenom *</label>
                                    <input type="text"
                                           class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text focus:outline-none focus:ring-2 focus:ring-accent/60"
                                           id="first_name"
                                           name="first_name"
                                           autocomplete="given-name"
                                           value="<?php echo htmlspecialchars($user['first_name']); ?>"
                                           required>
                                </div>
                                <div>
                                    <label for="last_name" class="text-sm font-semibold text-text">Nom *</label>
                                    <input type="text"
                                           class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text focus:outline-none focus:ring-2 focus:ring-accent/60"
                                           id="last_name"
                                           name="last_name"
                                           autocomplete="family-name"
                                           value="<?php echo htmlspecialchars($user['last_name']); ?>"
                                           required>
                                </div>
                            </div>

                            <div>
                                <label for="email" class="text-sm font-semibold text-text">Email *</label>
                                <input type="email"
                                       class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text focus:outline-none focus:ring-2 focus:ring-accent/60"
                                       id="email"
                                       name="email"
                                       autocomplete="email"
                                       value="<?php echo htmlspecialchars($user['email']); ?>"
                                       required>
                            </div>

                            <div>
                                <label for="phone" class="text-sm font-semibold text-text">Telephone</label>
                                <input type="tel"
                                       class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text focus:outline-none focus:ring-2 focus:ring-accent/60"
                                       id="phone"
                                       name="phone"
                                       autocomplete="tel"
                                       placeholder="+235 XX XX XX XX"
                                       value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                            </div>

                            <div>
                                <label for="city" class="text-sm font-semibold text-text">Ville</label>
                                <select class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text focus:outline-none focus:ring-2 focus:ring-accent/60" id="city" name="city">
                                    <option value="">Choisir une ville...</option>
                                    <option value="N'Djamena" <?php echo ($user['city'] ?? '') === "N'Djamena" ? 'selected' : ''; ?>>N'Djamena</option>
                                    <option value="Moundou" <?php echo ($user['city'] ?? '') === 'Moundou' ? 'selected' : ''; ?>>Moundou</option>
                                    <option value="Sarh" <?php echo ($user['city'] ?? '') === 'Sarh' ? 'selected' : ''; ?>>Sarh</option>
                                    <option value="Ab&#233;ch&#233;" <?php echo ($user['city'] ?? '') === "Ab\xC3\xA9ch\xC3\xA9" || ($user['city'] ?? '') === 'Ab&#233;ch&#233;' ? 'selected' : ''; ?>>Ab&#233;ch&#233;</option>
                                    <option value="Kelo" <?php echo ($user['city'] ?? '') === 'Kelo' ? 'selected' : ''; ?>>Kelo</option>
                                    <option value="Koumra" <?php echo ($user['city'] ?? '') === 'Koumra' ? 'selected' : ''; ?>>Koumra</option>
                                    <option value="Pala" <?php echo ($user['city'] ?? '') === 'Pala' ? 'selected' : ''; ?>>Pala</option>
                                    <option value="Am Timan" <?php echo ($user['city'] ?? '') === 'Am Timan' ? 'selected' : ''; ?>>Am Timan</option>
                                    <option value="Bongor" <?php echo ($user['city'] ?? '') === 'Bongor' ? 'selected' : ''; ?>>Bongor</option>
                                    <option value="Mongo" <?php echo ($user['city'] ?? '') === 'Mongo' ? 'selected' : ''; ?>>Mongo</option>
                                    <option value="Doba" <?php echo ($user['city'] ?? '') === 'Doba' ? 'selected' : ''; ?>>Doba</option>
                                </select>
                            </div>

                            <div>
                                <label for="bio" class="text-sm font-semibold text-text">Biographie</label>
                                <textarea class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text focus:outline-none focus:ring-2 focus:ring-accent/60"
                                          id="bio"
                                          name="bio"
                                          rows="4"
                                          maxlength="500"
                                          placeholder="Parlez-nous de vous..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                                <p class="mt-2 text-xs text-muted"><?php echo $bioLength; ?>/500 caracteres</p>
                            </div>

                            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                                <a href="<?php echo $dashboardUrl; ?>" class="inline-flex items-center justify-center rounded-full border border-white/10 bg-white/5 px-6 py-3 text-sm font-semibold text-text hover:bg-white/10">
                                    <i class="fas fa-times mr-2"></i>Annuler
                                </a>
                                <button type="submit" class="inline-flex items-center justify-center rounded-full bg-accent px-6 py-3 text-sm font-semibold text-white shadow-elev-1 hover:shadow-elev-2">
                                    <i class="fas fa-save mr-2"></i>Enregistrer
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2">
                        <h3 class="text-base font-semibold text-text">Photo de profil</h3>
                        <p class="mt-2 text-sm text-muted">Ajoutez une photo pour personnaliser votre compte.</p>
                        <div class="mt-6 flex flex-col items-center gap-4 text-center">
                            <div class="grid h-28 w-28 place-items-center rounded-full bg-gradient-to-br from-accent to-accent-2 text-4xl font-semibold text-bg">
                                <?php echo strtoupper(substr($user['first_name'] ?? '', 0, 1) . substr($user['last_name'] ?? '', 0, 1)); ?>
                            </div>
                            <button type="button" class="inline-flex items-center justify-center rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-semibold text-text">
                                <i class="fas fa-upload mr-2"></i>Changer la photo
                            </button>
                            <p class="text-xs text-muted">JPG, PNG (max 2MB)</p>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2">
                        <h3 class="text-base font-semibold text-text">Completion du profil</h3>
                        <p class="mt-2 text-sm text-muted">Completez votre profil pour plus de visibilite.</p>

                        <div class="mt-6 flex flex-col items-center gap-5">
                            <div class="relative h-36 w-36">
                                <svg class="h-full w-full -rotate-90" viewBox="0 0 100 100">
                                    <circle cx="50" cy="50" r="45" fill="none" stroke="currentColor" stroke-width="8" class="text-white/10"></circle>
                                    <circle cx="50" cy="50" r="45" fill="none" stroke="currentColor" stroke-width="8"
                                            stroke-dasharray="<?php echo $completion * 2.827; ?> 283"
                                            stroke-linecap="round"
                                            class="text-accent"></circle>
                                </svg>
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <span class="text-3xl font-semibold text-text"><?php echo $completion; ?>%</span>
                                </div>
                            </div>

                            <div class="w-full space-y-2 text-sm">
                                <div class="flex items-center gap-2 rounded-2xl border border-white/10 bg-white/5 px-3 py-2 <?php echo !empty($user['phone']) ? 'text-emerald-200 bg-emerald-500/10 border-emerald-400/30' : 'text-muted'; ?>">
                                    <i class="fas fa-check-circle"></i>
                                    <span>Numero de telephone</span>
                                </div>
                                <div class="flex items-center gap-2 rounded-2xl border border-white/10 bg-white/5 px-3 py-2 <?php echo !empty($user['city']) ? 'text-emerald-200 bg-emerald-500/10 border-emerald-400/30' : 'text-muted'; ?>">
                                    <i class="fas fa-check-circle"></i>
                                    <span>Ville</span>
                                </div>
                                <div class="flex items-center gap-2 rounded-2xl border border-white/10 bg-white/5 px-3 py-2 <?php echo !empty($user['bio']) ? 'text-emerald-200 bg-emerald-500/10 border-emerald-400/30' : 'text-muted'; ?>">
                                    <i class="fas fa-check-circle"></i>
                                    <span>Biographie</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include 'includes/footer-tailwind.php'; ?>
