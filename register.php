<?php
/**
 * Page d'inscription - Tchadok Platform
 * Migration Tailwind (progressive)
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/captcha.php';

$pageTitle = 'Inscription';
$pageDescription = 'Rejoignez la communauté Tchadok et découvrez la musique tchadienne.';

// Redirection si deja connecte
if (isLoggedIn()) {
    redirect(SITE_URL . '/');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // SEC-12 : limitation par adresse, et question de verification au-dela de
    // deux envois -- de quoi arreter la creation de comptes en serie sans
    // gener quelqu'un qui s'inscrit normalement.
    LimiteDebit::appliquer('inscription');
    $captchaRequis = Captcha::requis('inscription');
    $captchaValide = !$captchaRequis || Captcha::verifier('inscription', (string) ($_POST['captcha'] ?? ''));

    $firstName = sanitizeInput($_POST['first_name'] ?? '');
    $lastName = sanitizeInput($_POST['last_name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $userType = sanitizeInput($_POST['user_type'] ?? USER_TYPE_FAN);
    $terms = isset($_POST['terms']);
    $stageName = sanitizeInput($_POST['stage_name'] ?? '');

    if (!$captchaValide) {
        $error = 'La reponse a la question de verification est incorrecte.';
    } elseif (empty($firstName) || empty($lastName) || empty($email) || empty($password)) {
        $error = 'Veuillez remplir tous les champs obligatoires.';
    } elseif (!validateEmail($email)) {
        $error = 'Adresse email invalide.';
    } elseif (strlen($password) < MIN_PASSWORD_LENGTH) {
        $error = 'Le mot de passe doit contenir au moins ' . MIN_PASSWORD_LENGTH . ' caractères.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Les mots de passe ne correspondent pas.';
    } elseif (!$terms) {
        $error = 'Vous devez accepter les conditions d\'utilisation.';
    } else {
        try {
            $dbInstance = TchadokDatabase::getInstance();
            $db = $dbInstance->getConnection();

            if (!$db) {
                throw new Exception('Erreur de connexion à la base de données.');
            }

            if (empty($username)) {
                $username = strtolower($firstName . '_' . substr($lastName, 0, 1) . rand(100, 999));
            }

            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
            $stmt->execute([$email, $username]);

            if ($stmt->fetch()) {
                $error = 'Cet email ou nom d\'utilisateur est deja utilise.';
            } else {
                $passwordHash = hashPassword($password);

                $db->beginTransaction();

                $stmt = $db->prepare("
                    INSERT INTO users (
                        username, email, password, password_hash,
                        first_name, last_name, country,
                        email_verified, is_active, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");

                $stmt->execute([
                    $username,
                    $email,
                    $passwordHash,
                    $passwordHash,
                    $firstName,
                    $lastName,
                    'Tchad',
                    0,
                    1
                ]);

                $userId = $db->lastInsertId();

                if ($userType === USER_TYPE_ARTIST) {
                    $artistStageName = !empty($stageName) ? $stageName : "$firstName $lastName";

                    $stmt = $db->prepare("
                        INSERT INTO artists (
                            user_id, stage_name, real_name,
                            is_active, created_at
                        ) VALUES (?, ?, ?, 1, NOW())
                    ");

                    $stmt->execute([
                        $userId,
                        $artistStageName,
                        "$firstName $lastName"
                    ]);
                }

                $db->commit();

                $success = 'Inscription réussie ! Vous pouvez maintenant vous connecter avec votre email : <strong>' . htmlspecialchars($email) . '</strong>';

                $_POST = [];
            }
        } catch (Exception $e) {
            if (isset($db) && $db && $db->inTransaction()) {
                $db->rollBack();
            }
            $error = GestionErreurs::messagePublic($e, 'inscription');
        }
    }
}

$selectedUserType = $_POST['user_type'] ?? USER_TYPE_FAN;
$additionalJS = [
    SITE_URL . '/assets/js/register.js'
];

include 'includes/header-tailwind.php';
?>

<main class="pt-24 pb-16">
    <section class="relative overflow-hidden py-12">
        <div class="absolute inset-0 bg-gradient-to-br from-accent/35 via-bg to-amber-400/15"></div>
        <div class="absolute -left-32 top-10 h-72 w-72 rounded-full bg-accent/25 blur-3xl"></div>
        <div class="absolute -right-20 bottom-10 h-72 w-72 rounded-full bg-amber-400/20 blur-3xl"></div>

        <div class="relative mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-8 lg:grid-cols-[1.05fr_1fr]">
                <div class="hidden lg:flex flex-col justify-between rounded-3xl border border-white/10 bg-surface/60 p-8 text-text shadow-elev-2">
                    <div>
                        <div class="flex items-center gap-3">
                            <span class="grid h-12 w-12 place-items-center rounded-2xl bg-accent/20 text-accent">
                                <i class="fas fa-music"></i>
                            </span>
                            <span class="text-lg font-display font-semibold">Tchadok</span>
                        </div>
                        <h2 class="mt-8 text-3xl font-display font-bold">Rejoignez la communaute</h2>
                        <p class="mt-3 text-sm text-muted">
                            Creez votre compte et explorez les talents du Tchad. Profitez d une experience musicale
                            premium, personnalisee et inspiree de la scene locale.
                        </p>
                    </div>
                    <div class="space-y-4">
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                            <p class="text-sm font-semibold text-text">Streaming illimite</p>
                            <p class="mt-1 text-xs text-muted">Acces aux titres et albums du moment.</p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                            <p class="text-sm font-semibold text-text">Playlists personnalisees</p>
                            <p class="mt-1 text-xs text-muted">Creez vos collections et partagez-les.</p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                            <p class="text-sm font-semibold text-text">Radio en direct</p>
                            <p class="mt-1 text-xs text-muted">Suivez les emissions locales 24/7.</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-3xl border border-white/10 bg-surface/70 p-6 shadow-elev-3 sm:p-8">
                    <div class="text-center">
                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-accent/20 text-accent">
                            <i class="fas fa-user-plus text-2xl"></i>
                        </div>
                        <h1 class="mt-4 text-2xl font-display font-bold text-text">Creez votre compte</h1>
                        <p class="mt-2 text-sm text-muted">Rejoignez l univers musical tchadien.</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger mt-6">
                            <i class="fas fa-exclamation-triangle mt-0.5"></i>
                            <span><?php echo $error; ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success mt-6">
                            <i class="fas fa-check-circle mt-0.5"></i>
                            <span><?php echo $success; ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="mt-6 h-2 w-full rounded-full bg-white/10">
                        <div class="h-2 w-0 rounded-full bg-gradient-to-r from-accent to-amber-400" data-progress-bar></div>
                    </div>
                    <p class="mt-2 text-center text-xs text-muted">Remplissez le formulaire pour avancer.</p>

                    <form method="POST" class="mt-6 space-y-8" data-register-form>
                        <?php echo csrfField(); ?>
                        <div class="space-y-4">
                            <div class="flex items-center gap-2 text-sm font-semibold text-text">
                                <i class="fas fa-user-circle text-accent"></i>
                                Informations personnelles
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="first_name" class="text-sm font-semibold text-text">Prenom *</label>
                                    <input type="text"
                                           class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text focus:outline-none focus:ring-2 focus:ring-accent/60"
                                           id="first_name"
                                           name="first_name"
                                           value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>"
                                           autocomplete="given-name"
                                           data-progress-field
                                           required>
                                </div>
                                <div>
                                    <label for="last_name" class="text-sm font-semibold text-text">Nom *</label>
                                    <input type="text"
                                           class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text focus:outline-none focus:ring-2 focus:ring-accent/60"
                                           id="last_name"
                                           name="last_name"
                                           value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>"
                                           autocomplete="family-name"
                                           data-progress-field
                                           required>
                                </div>
                            </div>

                            <div>
                                <label for="email" class="text-sm font-semibold text-text">Adresse email *</label>
                                <input type="email"
                                       class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text focus:outline-none focus:ring-2 focus:ring-accent/60"
                                       id="email"
                                       name="email"
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                       placeholder="exemple@tchadok.td"
                                       autocomplete="email"
                                       data-progress-field
                                       required>
                            </div>

                            <div>
                                <label for="username" class="text-sm font-semibold text-text">Nom d utilisateur</label>
                                <input type="text"
                                       class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text focus:outline-none focus:ring-2 focus:ring-accent/60"
                                       id="username"
                                       name="username"
                                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                                       placeholder="optionnel, généré automatiquement">
                                <p class="mt-2 text-xs text-muted">Laissez vide pour une génération automatique.</p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="flex items-center gap-2 text-sm font-semibold text-text">
                                <i class="fas fa-shield-alt text-emerald-300"></i>
                                Securite
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="password" class="text-sm font-semibold text-text">Mot de passe *</label>
                                    <div class="relative mt-2">
                                        <input type="password"
                                               class="w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 pr-12 text-sm text-text focus:outline-none focus:ring-2 focus:ring-accent/60"
                                               id="password"
                                               name="password"
                                               placeholder="Minimum 8 caracteres"
                                               minlength="8"
                                               autocomplete="new-password"
                                               data-password-input
                                               data-progress-field
                                               required>
                                        <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted hover:text-text" data-toggle-password data-target="password">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
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
                                </div>
                                <div>
                                    <label for="confirm_password" class="text-sm font-semibold text-text">Confirmer *</label>
                                    <div class="relative mt-2">
                                        <input type="password"
                                               class="w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 pr-12 text-sm text-text focus:outline-none focus:ring-2 focus:ring-accent/60"
                                               id="confirm_password"
                                               name="confirm_password"
                                               placeholder="Ressaisir le mot de passe"
                                               autocomplete="new-password"
                                               data-confirm-input
                                               data-progress-field
                                               required>
                                        <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted hover:text-text" data-toggle-password data-target="confirm_password">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="flex items-center gap-2 text-sm font-semibold text-text">
                                <i class="fas fa-users text-amber-300"></i>
                                Type de compte
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <label class="block">
                                    <input type="radio"
                                           class="peer sr-only"
                                           name="user_type"
                                           value="<?php echo USER_TYPE_FAN; ?>"
                                           data-user-type="fan"
                                           data-progress-group="user_type"
                                           <?php echo $selectedUserType === USER_TYPE_FAN ? 'checked' : ''; ?>>
                                    <div class="h-full rounded-3xl border border-white/10 bg-white/5 p-5 transition peer-checked:border-accent peer-checked:bg-accent/10">
                                        <div class="flex items-center gap-3">
                                            <span class="grid h-11 w-11 place-items-center rounded-2xl bg-accent/20 text-accent">
                                                <i class="fas fa-heart"></i>
                                            </span>
                                            <div>
                                                <h3 class="text-sm font-semibold text-text">Melomane</h3>
                                                <p class="text-xs text-muted">Ecoutez et decouvrez la musique.</p>
                                            </div>
                                        </div>
                                        <ul class="mt-4 space-y-2 text-xs text-muted">
                                            <li class="flex items-center gap-2"><i class="fas fa-check-circle text-emerald-300"></i> Ecoute illimitee</li>
                                            <li class="flex items-center gap-2"><i class="fas fa-check-circle text-emerald-300"></i> Playlists personnalisees</li>
                                            <li class="flex items-center gap-2"><i class="fas fa-check-circle text-emerald-300"></i> Radio en direct</li>
                                        </ul>
                                    </div>
                                </label>
                                <label class="block">
                                    <input type="radio"
                                           class="peer sr-only"
                                           name="user_type"
                                           value="<?php echo USER_TYPE_ARTIST; ?>"
                                           data-user-type="artist"
                                           data-progress-group="user_type"
                                           <?php echo $selectedUserType === USER_TYPE_ARTIST ? 'checked' : ''; ?>>
                                    <div class="h-full rounded-3xl border border-white/10 bg-white/5 p-5 transition peer-checked:border-amber-400/60 peer-checked:bg-amber-400/10">
                                        <div class="flex items-center gap-3">
                                            <span class="grid h-11 w-11 place-items-center rounded-2xl bg-amber-400/20 text-amber-300">
                                                <i class="fas fa-music"></i>
                                            </span>
                                            <div>
                                                <h3 class="text-sm font-semibold text-text">Artiste</h3>
                                                <p class="text-xs text-muted">Partagez votre musique.</p>
                                            </div>
                                        </div>
                                        <ul class="mt-4 space-y-2 text-xs text-muted">
                                            <li class="flex items-center gap-2"><i class="fas fa-check-circle text-emerald-300"></i> Upload de musique</li>
                                            <li class="flex items-center gap-2"><i class="fas fa-check-circle text-emerald-300"></i> Statistiques detaillees</li>
                                            <li class="flex items-center gap-2"><i class="fas fa-check-circle text-emerald-300"></i> Revenus de streaming</li>
                                        </ul>
                                    </div>
                                </label>
                            </div>

                            <div class="<?php echo $selectedUserType === USER_TYPE_ARTIST ? '' : 'hidden'; ?>" data-artist-field>
                                <label for="stage_name" class="text-sm font-semibold text-text">Nom d artiste</label>
                                <input type="text"
                                       class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text focus:outline-none focus:ring-2 focus:ring-accent/60"
                                       id="stage_name"
                                       name="stage_name"
                                       value="<?php echo htmlspecialchars($_POST['stage_name'] ?? ''); ?>"
                                       placeholder="Votre nom de scene">
                                <p class="mt-2 text-xs text-muted">Le nom sous lequel vous apparaissez.</p>
                            </div>
                        </div>

                        <label class="flex items-start gap-3 rounded-2xl border border-white/10 bg-white/5 p-4 text-xs text-muted">
                            <input type="checkbox" class="mt-1 h-4 w-4 rounded border-white/20 bg-bg text-accent focus:ring-accent/60" id="terms" name="terms" data-progress-field required>
                            <span>J accepte les conditions d utilisation et la politique de confidentialite.</span>
                        </label>

                        <?php if (Captcha::requis('inscription')): ?>
                            <?php echo Captcha::champ('inscription'); ?>
                        <?php endif; ?>

                        <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-full bg-accent px-6 py-3 text-sm font-semibold text-white shadow-elev-1 hover:shadow-elev-2" data-submit>
                            <span data-btn-text><i class="fas fa-user-plus mr-2"></i>Créer mon compte gratuitement</span>
                            <span class="hidden items-center gap-2" data-btn-loader>
                                <i class="fas fa-spinner fa-spin"></i>Creation...
                            </span>
                        </button>

                        <div class="text-center text-xs text-muted">
                            <i class="fas fa-lock mr-1"></i>
                            Vos donnees sont securisees et protegees
                        </div>
                    </form>

                    <div class="mt-6 text-center text-sm text-muted">
                        Deja membre ?
                        <a href="<?php echo SITE_URL; ?>/login.php" class="font-semibold text-accent hover:text-accent-2">Se connecter</a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include 'includes/footer-tailwind.php'; ?>
