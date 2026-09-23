<?php
/**
 * Page Contact - Tchadok Platform
 * Migration Tailwind (progressive)
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/captcha.php';
require_once 'assets/images/placeholders.php';

$pageTitle = 'Nous Contacter';
$pageDescription = "Contactez l'équipe Tchadok. Nous sommes là pour vous aider avec vos questions, suggestions et partenariats.";

$isLoggedIn = isLoggedIn();
$user = $isLoggedIn ? getCurrentUser() : null;
$dashboardUrl = SITE_URL . '/user-dashboard.php';
$workspaceLabel = 'Espace fan';

if ($isLoggedIn && isAdmin()) {
    $dashboardUrl = SITE_URL . '/admin-dashboard.php';
    $workspaceLabel = 'Console admin';
} elseif ($isLoggedIn && isArtist()) {
    $dashboardUrl = SITE_URL . '/artist-dashboard.php';
    $workspaceLabel = 'Studio artiste';
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // SEC-12 : le formulaire de contact est une cible classique d'envois
    // automatises. Limite par adresse, avec question de verification au-dela
    // de deux envois.
    LimiteDebit::appliquer('contact');
    $captchaRequis = Captcha::requis('contact');
    $captchaValide = !$captchaRequis || Captcha::verifier('contact', (string) ($_POST['captcha'] ?? ''));

    $name = sanitizeInput($_POST['name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $subject = sanitizeInput($_POST['subject'] ?? '');
    $message = sanitizeInput($_POST['message'] ?? '');
    $type = sanitizeInput($_POST['type'] ?? 'general');

    if (!$captchaValide) {
        $error = 'La reponse a la question de verification est incorrecte.';
    } elseif (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error = 'Veuillez remplir tous les champs obligatoires.';
    } elseif (!validateEmail($email)) {
        $error = 'Adresse email invalide.';
    } elseif (strlen($message) < 10) {
        $error = 'Le message doit contenir au moins 10 caracteres.';
    } else {
        $success = 'Votre message a ete envoye avec succes ! Nous vous repondrons dans les plus brefs delais.';
    }
}

$additionalCSS = [
    SITE_URL . '/assets/css/contact-tailwind.css'
];

$additionalJS = [
    SITE_URL . '/assets/js/contact.js'
];

include 'includes/header-tailwind.php';
?>

<main class="pt-0">
    <section class="page-hero relative min-h-screen items-center overflow-hidden bg-bg pb-14 pt-12 sm:pt-16 lg:pt-20 lg:flex">
        <div class="absolute -left-32 -top-32 h-72 w-72 rounded-full bg-[#0066CC]/20 blur-3xl"></div>
        <div class="absolute -bottom-40 right-0 h-96 w-96 rounded-full bg-[#FFD700]/20 blur-3xl"></div>
        <span class="page-hero-note note-1">&#9834;</span>
        <span class="page-hero-note note-2">&#9835;</span>
        <span class="page-hero-note note-3">&#9834;</span>
        <span class="page-hero-note note-4">&#9835;</span>

        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid items-center gap-12 lg:grid-cols-2">
                <div class="page-hero-content">
                    <div class="page-hero-pill inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold text-muted">
                        <i class="fas fa-envelope text-accent-2"></i>
                        Contactez-nous
                    </div>
                    <h1 class="page-hero-title mt-5 text-4xl font-display font-bold leading-tight text-text sm:text-5xl">Nous sommes a votre ecoute</h1>
                    <p class="page-hero-subtitle mt-4 text-base text-muted sm:text-lg">
                        Une question, une suggestion, un partenariat ? Notre équipe dédiée est là pour vous accompagner.
                    </p>
                    <div class="mt-6 flex flex-wrap gap-4 text-sm text-muted">
                        <span class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-2">
                            <i class="fas fa-clock"></i> Reponse sous 24h
                        </span>
                        <span class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-2">
                            <i class="fas fa-users"></i> Equipe dediee
                        </span>
                        <span class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-2">
                            <i class="fas fa-globe-africa"></i> Support multilingue
                        </span>
                    </div>
                </div>

                <div class="flex items-center justify-center">
                    <div class="contact-visual relative grid place-items-center rounded-3xl border border-white/10 bg-surface/60 p-8 shadow-elev-2">
                        <div class="message-bubble bubble-1">
                            <i class="fas fa-music"></i>
                            <span>Musique</span>
                        </div>
                        <div class="message-bubble bubble-2">
                            <i class="fas fa-handshake"></i>
                            <span>Partenariat</span>
                        </div>
                        <div class="message-bubble bubble-3">
                            <i class="fas fa-question-circle"></i>
                            <span>Support</span>
                        </div>
                        <div class="central-contact-icon">
                            <i class="fas fa-envelope-open"></i>
                        </div>
                        <p class="mt-24 text-xs text-muted">Echangeons ensemble</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php if ($isLoggedIn): ?>
        <section class="-mt-8 pb-4">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="rounded-3xl border border-white/10 bg-surface/75 p-6 shadow-elev-2">
                    <div class="flex flex-wrap items-start justify-between gap-6">
                        <div>
                            <p class="text-xs uppercase tracking-[0.24em] text-muted"><?php echo htmlspecialchars($workspaceLabel); ?></p>
                            <h2 class="mt-2 text-2xl font-display font-semibold text-text">Support dirige vers le bon module</h2>
                            <p class="mt-3 max-w-2xl text-sm leading-6 text-muted">
                                Avant d'ouvrir une demande, accédez directement au bon point d'action : sécurité,
                                portefeuille, préférences ou assistance générale.
                            </p>
                        </div>
                        <a href="<?php echo $dashboardUrl; ?>" class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-5 py-3 text-sm font-semibold text-text hover:bg-white/10">
                            <i class="fas fa-arrow-left"></i>
                            Retour au dashboard
                        </a>
                    </div>

                    <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        <a href="<?php echo SITE_URL; ?>/security-settings.php" class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-text hover:bg-white/10">
                            <span class="grid h-10 w-10 place-items-center rounded-xl bg-accent/20 text-accent"><i class="fas fa-shield-alt"></i></span>
                            Securite du compte
                        </a>
                        <a href="<?php echo SITE_URL; ?>/wallet.php" class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-text hover:bg-white/10">
                            <span class="grid h-10 w-10 place-items-center rounded-xl bg-emerald-500/20 text-emerald-300"><i class="fas fa-wallet"></i></span>
                            Portefeuille
                        </a>
                        <a href="<?php echo SITE_URL; ?>/premium.php" class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-text hover:bg-white/10">
                            <span class="grid h-10 w-10 place-items-center rounded-xl bg-amber-400/20 text-amber-200"><i class="fas fa-crown"></i></span>
                            Offre Premium
                        </a>
                        <a href="<?php echo SITE_URL; ?>/settings.php" class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-text hover:bg-white/10">
                            <span class="grid h-10 w-10 place-items-center rounded-xl bg-rose-500/20 text-rose-300"><i class="fas fa-sliders-h"></i></span>
                            Preferences
                        </a>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <section class="py-12">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-6 text-center">
                <h2 class="text-2xl font-display font-bold text-text">Moyens de contact</h2>
                <p class="text-sm text-muted">Choisissez le moyen qui vous convient le mieux</p>
            </div>

            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                <div class="rounded-3xl border border-white/10 bg-surface/60 p-6 text-center">
                    <div class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-accent/20 text-accent">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <h3 class="mt-4 text-lg font-semibold text-text">Adresse</h3>
                    <p class="mt-2 text-sm text-muted">Avenue Charles de Gaulle<br>Quartier Klemat<br>N'Djamena, Tchad</p>
                    <button class="mt-4 inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-semibold text-text" data-action="open-map" type="button">
                        <i class="fas fa-external-link-alt"></i> Voir sur la carte
                    </button>
                </div>

                <div class="rounded-3xl border border-white/10 bg-surface/60 p-6 text-center">
                    <div class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-accent/20 text-accent">
                        <i class="fas fa-phone"></i>
                    </div>
                    <h3 class="mt-4 text-lg font-semibold text-text">Telephone</h3>
                    <p class="mt-2 text-sm text-muted">Support client<br><strong>+235 66 12 34 56</strong><br>Lun-Ven: 8h00 - 18h00</p>
                    <button class="mt-4 inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-semibold text-text" data-action="call" type="button">
                        <i class="fas fa-phone"></i> Appeler maintenant
                    </button>
                </div>

                <div class="rounded-3xl border border-white/10 bg-surface/60 p-6 text-center">
                    <div class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-accent/20 text-accent">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <h3 class="mt-4 text-lg font-semibold text-text">Email</h3>
                    <p class="mt-2 text-sm text-muted">Contact général<br><strong>contact@tchadok.td</strong><br>Réponse sous 24h ouvrées</p>
                    <button class="mt-4 inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-semibold text-text" data-action="email" type="button">
                        <i class="fas fa-envelope"></i> Envoyer un email
                    </button>
                </div>
            </div>
        </div>
    </section>

    <section class="py-12">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <div class="mb-6 text-center">
                <h2 class="text-2xl font-display font-bold text-text">Envoyez-nous un message</h2>
                <p class="text-sm text-muted">Remplissez le formulaire ci-dessous et nous vous repondrons rapidement</p>
            </div>

            <div class="rounded-3xl border border-white/10 bg-surface/60 p-6">
                <?php if ($success): ?>
                    <div class="alert alert-success" role="alert">
                        <i class="fas fa-check-circle"></i>
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-5" data-contact-form novalidate>
                    <?php echo csrfField(); ?>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="text-xs font-semibold text-muted" for="name">Nom complet *</label>
                            <div class="mt-2 relative">
                                <input type="text" class="contact-input w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text placeholder:text-muted focus:outline-none" id="name" name="name" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required data-field>
                                <i class="fas fa-user form-icon"></i>
                            </div>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-muted" for="email">Adresse email *</label>
                            <div class="mt-2 relative">
                                <input type="email" class="contact-input w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text placeholder:text-muted focus:outline-none" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required data-field>
                                <i class="fas fa-envelope form-icon"></i>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="text-xs font-semibold text-muted" for="type">Type de demande</label>
                        <div class="mt-2 relative">
                            <select class="contact-input w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text focus:outline-none" id="type" name="type" data-field>
                                <option value="general" <?php echo ($_POST['type'] ?? '') === 'general' ? 'selected' : ''; ?>>Question générale</option>
                                <option value="support" <?php echo ($_POST['type'] ?? '') === 'support' ? 'selected' : ''; ?>>Support technique</option>
                                <option value="partnership" <?php echo ($_POST['type'] ?? '') === 'partnership' ? 'selected' : ''; ?>>Partenariat</option>
                                <option value="artist" <?php echo ($_POST['type'] ?? '') === 'artist' ? 'selected' : ''; ?>>Espace artiste</option>
                                <option value="press" <?php echo ($_POST['type'] ?? '') === 'press' ? 'selected' : ''; ?>>Relations presse</option>
                                <option value="other" <?php echo ($_POST['type'] ?? '') === 'other' ? 'selected' : ''; ?>>Autre</option>
                            </select>
                            <i class="fas fa-list form-icon"></i>
                        </div>
                    </div>

                    <div>
                        <label class="text-xs font-semibold text-muted" for="subject">Sujet *</label>
                        <div class="mt-2 relative">
                            <input type="text" class="contact-input w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text placeholder:text-muted focus:outline-none" id="subject" name="subject" placeholder="Resumez votre demande en quelques mots" value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>" required data-field>
                            <i class="fas fa-tag form-icon"></i>
                        </div>
                    </div>

                    <div>
                        <label class="text-xs font-semibold text-muted" for="message">Message *</label>
                        <div class="mt-2 relative">
                            <textarea class="contact-input contact-textarea w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text placeholder:text-muted focus:outline-none" id="message" name="message" rows="6" placeholder="Decrivez votre demande en detail..." required data-field data-message><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                            <i class="fas fa-comment form-icon"></i>
                        </div>
                        <div class="mt-2 flex items-center justify-between text-xs text-muted">
                            <span>Minimum 10 caracteres</span>
                            <span class="character-counter"><span data-char-count>0</span>/1000 caracteres</span>
                        </div>
                    </div>

                    <?php if (Captcha::requis('contact')): ?>
                        <?php echo Captcha::champ('contact'); ?>
                    <?php endif; ?>

                    <div class="flex flex-wrap gap-3">
                        <button type="submit" class="rounded-full bg-accent px-6 py-3 text-sm font-semibold text-white shadow-elev-1">
                            <i class="fas fa-paper-plane"></i> Envoyer le message
                        </button>
                        <button type="reset" class="rounded-full border border-white/10 bg-white/5 px-6 py-3 text-sm font-semibold text-text">
                            <i class="fas fa-undo"></i> Effacer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <section class="py-12">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-8 lg:grid-cols-2">
                <div class="rounded-3xl border border-white/10 bg-surface/60 p-6">
                    <h3 class="text-xl font-semibold text-text">Heures d'ouverture</h3>
                    <div class="mt-4 space-y-3 text-sm text-muted">
                        <div class="flex items-center justify-between"><span>Lundi - Vendredi</span><span>8h00 - 18h00</span></div>
                        <div class="flex items-center justify-between"><span>Samedi</span><span>9h00 - 15h00</span></div>
                        <div class="flex items-center justify-between"><span>Dimanche</span><span>Ferme</span></div>
                    </div>
                    <div class="mt-6 rounded-2xl border border-white/10 bg-white/5 p-4 text-sm text-muted">
                        <p class="text-text font-semibold">Contact d'urgence</p>
                        <p class="mt-2">Pour les problemes techniques critiques :</p>
                        <a class="mt-2 inline-flex items-center gap-2 text-accent" href="tel:+23566123456">
                            <i class="fas fa-phone"></i> +235 66 12 34 56
                        </a>
                    </div>
                </div>

                <div class="rounded-3xl border border-white/10 bg-surface/60 p-6">
                    <h3 class="text-xl font-semibold text-text">Notre équipe</h3>
                    <div class="mt-4 space-y-4">
                        <div class="flex items-center gap-4">
                            <div class="h-12 w-12 overflow-hidden rounded-full border border-white/10 bg-white/5">
                                <?php echo createAvatarPlaceholder('Abakar Mahamat', '#2F6DE0'); ?>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-text">Abakar Moussa</p>
                                <p class="text-xs text-muted">Directeur général</p>
                                <a class="text-xs text-accent" href="mailto:abakar@tchadok.td">abakar@tchadok.td</a>
                            </div>
                        </div>
                        <div class="flex items-center gap-4">
                            <div class="h-12 w-12 overflow-hidden rounded-full border border-white/10 bg-white/5">
                                <?php echo createAvatarPlaceholder('Fatima Hassan', '#FFC107'); ?>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-text">Fatima Hassan</p>
                                <p class="text-xs text-muted">Responsable support</p>
                                <a class="text-xs text-accent" href="mailto:support@tchadok.td">support@tchadok.td</a>
                            </div>
                        </div>
                        <div class="flex items-center gap-4">
                            <div class="h-12 w-12 overflow-hidden rounded-full border border-white/10 bg-white/5">
                                <?php echo createAvatarPlaceholder('Moussa Ngarlejy', '#22c55e'); ?>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-text">Mahamat Nour</p>
                                <p class="text-xs text-muted">Partenariats artistes</p>
                                <a class="text-xs text-accent" href="mailto:artistes@tchadok.td">artistes@tchadok.td</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include 'includes/footer-tailwind.php'; ?>
