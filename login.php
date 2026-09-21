<?php
/**
 * Page de connexion - Tchadok Platform
 * Migration Tailwind (progressive)
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';

$pageTitle = 'Connexion';
$pageDescription = 'Connectez-vous à votre compte Tchadok pour accéder à votre musique préférée.';

// Redirection si deja connecte
if (isLoggedIn()) {
    redirect(SITE_URL . '/');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if (empty($email) || empty($password)) {
        $error = 'Veuillez remplir tous les champs.';
    } else {
        // Utiliser le systeme d'authentification reel
        if ($auth) {
            $result = $auth->login($email, $password, $remember);

            if ($result['success']) {
                // Connexion réussie
                setFlashMessage(FLASH_SUCCESS, 'Connexion réussie ! Bienvenue sur Tchadok');
                redirect(SITE_URL . '/');
            } else {
                $error = $result['error'] ?? 'Email ou mot de passe incorrect.';
            }
        } else {
            $error = 'Erreur de connexion à la base de données. Veuillez réessayer.';
        }
    }
}

$additionalJS = [
    SITE_URL . '/assets/js/login.js'
];

include 'includes/header-tailwind.php';
?>

<main class="pt-24 pb-16">
    <section class="relative overflow-hidden py-12">
        <div class="absolute -left-40 top-10 h-72 w-72 rounded-full bg-accent/30 blur-3xl"></div>
        <div class="absolute right-0 top-0 h-96 w-96 rounded-full bg-accent-2/15 blur-3xl"></div>
        <div class="absolute bottom-10 left-1/2 h-64 w-64 -translate-x-1/2 rounded-full bg-white/5 blur-3xl"></div>

        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <div class="grid overflow-hidden rounded-3xl border border-white/10 bg-surface/60 shadow-elev-3 lg:grid-cols-2">
                <div class="relative hidden lg:block">
                    <div class="absolute inset-0 bg-gradient-to-br from-accent to-[#18294a]"></div>
                    <div class="absolute inset-0 opacity-20">
                        <div class="absolute left-10 top-16 text-4xl text-white/30 animate-pulse">
                            <i class="fas fa-music"></i>
                        </div>
                        <div class="absolute right-16 top-32 text-5xl text-white/20 animate-pulse">
                            <i class="fas fa-wave-square"></i>
                        </div>
                        <div class="absolute bottom-20 left-20 text-4xl text-white/20 animate-pulse">
                            <i class="fas fa-headphones"></i>
                        </div>
                        <div class="absolute bottom-28 right-24 text-4xl text-white/20 animate-pulse">
                            <i class="fas fa-microphone"></i>
                        </div>
                    </div>
                    <div class="relative z-10 flex h-full flex-col justify-between p-10 text-white">
                        <div>
                            <div class="flex items-center gap-3">
                                <span class="grid h-12 w-12 place-items-center rounded-2xl bg-white/15 text-white">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" class="h-6 w-6">
                                        <circle cx="50" cy="50" r="45" fill="currentColor" opacity="0.2"/>
                                        <path d="M30 45 L30 55 L40 60 L40 40 Z M45 35 L45 65 L55 70 L55 30 Z M60 40 L60 60 L70 55 L70 45 Z" fill="currentColor"/>
                                    </svg>
                                </span>
                                <span class="text-lg font-display font-semibold">Tchadok</span>
                            </div>
                            <h2 class="mt-8 text-3xl font-display font-bold">Bienvenue sur Tchadok</h2>
                            <p class="mt-3 text-sm text-white/80">La plateforme musicale tchadienne par excellence.</p>
                        </div>
                        <div class="space-y-4">
                            <div class="flex items-start gap-3 rounded-2xl border border-white/15 bg-white/10 p-4">
                                <span class="grid h-10 w-10 place-items-center rounded-xl bg-white/20 text-white">
                                    <i class="fas fa-music"></i>
                                </span>
                                <div>
                                    <h3 class="text-sm font-semibold">Musique illimitee</h3>
                                    <p class="mt-1 text-xs text-white/75">Accedez a des milliers de titres tchadiens.</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3 rounded-2xl border border-white/15 bg-white/10 p-4">
                                <span class="grid h-10 w-10 place-items-center rounded-xl bg-white/20 text-white">
                                    <i class="fas fa-broadcast-tower"></i>
                                </span>
                                <div>
                                    <h3 class="text-sm font-semibold">Radio en direct</h3>
                                    <p class="mt-1 text-xs text-white/75">Ecoutez la radio 24/7 gratuitement.</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3 rounded-2xl border border-white/15 bg-white/10 p-4">
                                <span class="grid h-10 w-10 place-items-center rounded-xl bg-white/20 text-white">
                                    <i class="fas fa-heart"></i>
                                </span>
                                <div>
                                    <h3 class="text-sm font-semibold">Playlists personnalisees</h3>
                                    <p class="mt-1 text-xs text-white/75">Creez vos propres collections musicales.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col justify-between bg-surface/70 p-6 sm:p-10">
                    <div>
                        <div class="text-center">
                            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-accent/20 text-accent lg:hidden">
                                <i class="fas fa-user text-2xl"></i>
                            </div>
                            <h1 class="mt-4 text-2xl font-display font-bold text-text">Connexion</h1>
                            <p class="mt-2 text-sm text-muted">Accedez a votre univers musical tchadien.</p>
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

                        <form method="POST" data-login-form class="mt-6 space-y-5">
                            <div>
                                <label for="email" class="text-sm font-semibold text-text">Adresse email</label>
                                <div class="relative mt-2">
                                    <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted">
                                        <i class="fas fa-envelope"></i>
                                    </span>
                                    <input type="email"
                                           class="w-full rounded-2xl border border-white/10 bg-bg px-10 py-3 text-sm text-text placeholder:text-muted focus:outline-none focus:ring-2 focus:ring-accent/60"
                                           id="email"
                                           name="email"
                                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                           placeholder="exemple@tchadok.td"
                                           autocomplete="email"
                                           required>
                                </div>
                            </div>

                            <div>
                                <label for="password" class="text-sm font-semibold text-text">Mot de passe</label>
                                <div class="relative mt-2">
                                    <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                    <input type="password"
                                           class="w-full rounded-2xl border border-white/10 bg-bg px-10 py-3 text-sm text-text placeholder:text-muted focus:outline-none focus:ring-2 focus:ring-accent/60"
                                           id="password"
                                           name="password"
                                           placeholder="Votre mot de passe"
                                           autocomplete="current-password"
                                           data-password-input
                                           required>
                                    <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted hover:text-text" data-password-toggle aria-label="Afficher le mot de passe">
                                        <i class="fas fa-eye" data-password-icon></i>
                                    </button>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center justify-between gap-3 text-xs text-muted">
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" class="h-4 w-4 rounded border-white/20 bg-bg text-accent focus:ring-accent/60" id="remember" name="remember">
                                    <span>Se souvenir de moi</span>
                                </label>
                                <a href="#" class="font-semibold text-accent hover:text-accent-2">Mot de passe oublie ?</a>
                            </div>

                            <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-full bg-accent px-6 py-3 text-sm font-semibold text-white shadow-elev-1 hover:shadow-elev-2" data-submit>
                                <span data-btn-text><i class="fas fa-sign-in-alt mr-2"></i>Se connecter</span>
                                <span class="hidden items-center gap-2" data-btn-loader>
                                    <i class="fas fa-spinner fa-spin"></i>Connexion...
                                </span>
                            </button>

                            <div class="flex items-center gap-4 text-xs text-muted">
                                <span class="h-px flex-1 bg-white/10"></span>
                                ou
                                <span class="h-px flex-1 bg-white/10"></span>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <button type="button" class="flex items-center justify-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-semibold text-text hover:bg-white/10" data-social-notice="Connexion Google bientôt disponible">
                                    <i class="fab fa-google text-red-400"></i>
                                    Google
                                </button>
                                <button type="button" class="flex items-center justify-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-semibold text-text hover:bg-white/10" data-social-notice="Connexion Facebook bientôt disponible">
                                    <i class="fab fa-facebook text-blue-400"></i>
                                    Facebook
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="mt-8 text-center text-sm text-muted">
                        Pas encore de compte ?
                        <a href="<?php echo SITE_URL; ?>/register.php" class="font-semibold text-accent hover:text-accent-2">Creer un compte gratuitement</a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include 'includes/footer-tailwind.php'; ?>
