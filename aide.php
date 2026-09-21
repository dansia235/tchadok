<?php
/**
 * Page d'Aide - Tchadok Platform
 * Migration Tailwind (progressive)
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';

$pageTitle = "Centre d'Aide";
$pageDescription = "Trouvez rapidement des réponses à vos questions sur Tchadok.";

$categories = [
    [
        'key' => 'account',
        'title' => 'Compte & Profil',
        'desc' => 'Création de compte, paramètres de profil, connexion et sécurité',
        'count' => '15 articles',
        'icon' => 'fa-user-circle'
    ],
    [
        'key' => 'music',
        'title' => 'Musique & Écoute',
        'desc' => 'Lecture de musique, playlists, téléchargements et qualité audio',
        'count' => '25 articles',
        'icon' => 'fa-music'
    ],
    [
        'key' => 'payment',
        'title' => 'Paiements & Premium',
        'desc' => 'Abonnements, methodes de paiement, factures et remboursements',
        'count' => '12 articles',
        'icon' => 'fa-credit-card'
    ],
    [
        'key' => 'artists',
        'title' => 'Espace Artiste',
        'desc' => 'Publier de la musique, gestion des droits, statistiques et revenus',
        'count' => '18 articles',
        'icon' => 'fa-microphone'
    ],
    [
        'key' => 'mobile',
        'title' => 'Applications Mobiles',
        'desc' => 'Installation, utilisation hors ligne, notifications et synchronisation',
        'count' => '10 articles',
        'icon' => 'fa-mobile-alt'
    ],
    [
        'key' => 'technical',
        'title' => 'Support Technique',
        'desc' => 'Problèmes de connexion, bugs, compatibilité et dépannage',
        'count' => '20 articles',
        'icon' => 'fa-cog'
    ],
];

$faqs = [
    [
        'question' => 'Comment créer un compte sur Tchadok ?',
        'answer' => 'Cliquez sur "S\'inscrire", choisissez votre type de compte, puis confirmez votre email. La création est gratuite et rapide.'
    ],
    [
        'question' => 'Comment écouter de la musique gratuitement ?',
        'answer' => 'Créez un compte gratuit et accédez à des milliers de titres. Le Premium ajoute la qualité audio HD et l\'écoute hors ligne.'
    ],
    [
        'question' => "Quels sont les avantages de l'abonnement Premium ?",
        'answer' => "Écoute illimitée sans publicité, qualité audio HD, téléchargement hors ligne, accès prioritaire et support dédié."
    ],
    [
        'question' => 'Comment publier ma musique en tant qu\'artiste ?',
        'answer' => 'Créez un compte Artiste, complétez votre profil, puis téléchargez vos titres. Validation généralement sous 24-48h.'
    ],
    [
        'question' => 'Quelles methodes de paiement acceptez-vous ?',
        'answer' => 'Cartes bancaires (Visa, Mastercard), virements et paiements mobiles (Airtel Money, Moov Money).'
    ],
    [
        'question' => 'Puis-je utiliser Tchadok sur mon telephone ?',
        'answer' => 'Oui, Tchadok est optimise pour mobile. Les applications iOS/Android arrivent bientot.'
    ],
];

$additionalCSS = [
    SITE_URL . '/assets/css/aide-tailwind.css'
];

$additionalJS = [
    SITE_URL . '/assets/js/aide.js'
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

        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid items-center gap-12 lg:grid-cols-2">
                <div class="page-hero-content">
                    <div class="page-hero-pill inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold text-muted">
                        <i class="fas fa-question-circle text-accent-2"></i>
                        Centre d'aide
                    </div>
                    <h1 class="page-hero-title mt-5 text-4xl font-display font-bold leading-tight text-text sm:text-5xl">Comment pouvons-nous vous aider ?</h1>
                    <p class="page-hero-subtitle mt-4 text-base text-muted sm:text-lg">
                        Trouvez rapidement des réponses à vos questions ou contactez notre équipe de support.
                    </p>

                    <div class="mt-6 rounded-2xl border border-white/10 bg-white/5 p-3 backdrop-blur">
                        <div class="flex items-center gap-3">
                            <span class="grid h-10 w-10 place-items-center rounded-xl bg-white/5 text-muted">
                                <i class="fas fa-search"></i>
                            </span>
                            <input type="text" class="w-full bg-transparent text-sm text-text placeholder:text-muted focus:outline-none" placeholder="Rechercher dans l'aide..." data-search-input>
                            <button class="grid h-10 w-10 place-items-center rounded-xl bg-accent text-white shadow-elev-1" type="button" data-action="search-submit" aria-label="Rechercher">
                                <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mt-6 grid gap-4 sm:grid-cols-3">
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4 text-center text-text">
                            <p class="text-xs uppercase tracking-[0.2em] text-muted">Articles</p>
                            <p class="mt-2 text-2xl font-semibold text-text">200+</p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4 text-center text-text">
                            <p class="text-xs uppercase tracking-[0.2em] text-muted">Support</p>
                            <p class="mt-2 text-2xl font-semibold text-text">24/7</p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4 text-center text-text">
                            <p class="text-xs uppercase tracking-[0.2em] text-muted">Satisfaction</p>
                            <p class="mt-2 text-2xl font-semibold text-text">95%</p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-center">
                    <div class="help-orbit relative grid place-items-center rounded-3xl border border-white/10 bg-surface/60 p-8 shadow-elev-2">
                        <div class="central-icon">
                            <i class="fas fa-headset"></i>
                        </div>
                        <div class="orbit-icons">
                            <span class="orbit-icon" style="--angle: 0deg;"><i class="fas fa-music"></i></span>
                            <span class="orbit-icon" style="--angle: 60deg;"><i class="fas fa-user"></i></span>
                            <span class="orbit-icon" style="--angle: 120deg;"><i class="fas fa-credit-card"></i></span>
                            <span class="orbit-icon" style="--angle: 180deg;"><i class="fas fa-download"></i></span>
                            <span class="orbit-icon" style="--angle: 240deg;"><i class="fas fa-settings"></i></span>
                            <span class="orbit-icon" style="--angle: 300deg;"><i class="fas fa-shield-alt"></i></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-12">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-6 text-center">
                <h2 class="text-2xl font-display font-bold text-text">Categories d'aide</h2>
                <p class="text-sm text-muted">Trouvez rapidement l'aide dont vous avez besoin</p>
            </div>

            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <?php foreach ($categories as $category): ?>
                    <button class="help-category-card group rounded-3xl border border-white/10 bg-surface/60 p-6 text-left transition hover:-translate-y-1 hover:shadow-elev-2" data-action="category" data-category="<?php echo htmlspecialchars($category['key']); ?>" type="button">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="grid h-12 w-12 place-items-center rounded-2xl bg-accent/20 text-accent">
                                    <i class="fas <?php echo htmlspecialchars($category['icon']); ?>"></i>
                                </div>
                                <h3 class="mt-4 text-lg font-semibold text-text"><?php echo htmlspecialchars($category['title']); ?></h3>
                                <p class="mt-2 text-sm text-muted"><?php echo htmlspecialchars($category['desc']); ?></p>
                            </div>
                            <span class="grid h-9 w-9 place-items-center rounded-full border border-white/10 bg-white/5 text-text">
                                <i class="fas fa-arrow-right"></i>
                            </span>
                        </div>
                        <div class="mt-4 inline-flex rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold text-text">
                            <?php echo htmlspecialchars($category['count']); ?>
                        </div>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="py-12">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <div class="mb-6 text-center">
                <h2 class="text-2xl font-display font-bold text-text">Questions frequentes</h2>
                <p class="text-sm text-muted">Les réponses aux questions les plus courantes</p>
            </div>

            <div class="space-y-3">
                <?php foreach ($faqs as $index => $faq): ?>
                    <div class="faq-item rounded-3xl border border-white/10 bg-surface/60 p-5" data-faq>
                        <button class="flex w-full items-center justify-between gap-4 text-left" data-faq-toggle type="button" aria-expanded="false">
                            <span class="text-sm font-semibold text-text"><?php echo htmlspecialchars($faq['question']); ?></span>
                            <i class="fas fa-chevron-down text-muted transition"></i>
                        </button>
                        <div class="faq-answer mt-3 text-sm text-muted">
                            <?php echo htmlspecialchars($faq['answer']); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="support-section py-14">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid items-center gap-10 lg:grid-cols-2">
                <div>
                    <h3 class="text-3xl font-display font-bold text-white">Besoin d'aide personnalisee ?</h3>
                    <p class="mt-3 text-sm text-white/80">
                        Notre équipe de support est là pour vous accompagner. Contactez-nous pour une assistance rapide.
                    </p>

                    <div class="mt-6 space-y-4">
                        <div class="support-method rounded-2xl border border-white/10 bg-white/10 p-4">
                            <div class="flex items-center gap-4">
                                <span class="grid h-12 w-12 place-items-center rounded-full bg-accent-2 text-black">
                                    <i class="fas fa-comments"></i>
                                </span>
                                <div>
                                    <h4 class="text-base font-semibold text-white">Chat en direct</h4>
                                    <p class="text-xs text-white/70">Reponse immediate de 8h a 20h</p>
                                    <button class="mt-2 rounded-full bg-accent px-4 py-2 text-xs font-semibold text-white" data-action="open-chat" type="button">Demarrer le chat</button>
                                </div>
                            </div>
                        </div>

                        <div class="support-method rounded-2xl border border-white/10 bg-white/10 p-4">
                            <div class="flex items-center gap-4">
                                <span class="grid h-12 w-12 place-items-center rounded-full bg-accent-2 text-black">
                                    <i class="fas fa-envelope"></i>
                                </span>
                                <div>
                                    <h4 class="text-base font-semibold text-white">Email</h4>
                                    <p class="text-xs text-white/70">Reponse sous 24h ouvre</p>
                                    <a class="mt-2 inline-flex rounded-full border border-white/20 px-4 py-2 text-xs font-semibold text-white" href="mailto:support@tchadok.td">Envoyer un email</a>
                                </div>
                            </div>
                        </div>

                        <div class="support-method rounded-2xl border border-white/10 bg-white/10 p-4">
                            <div class="flex items-center gap-4">
                                <span class="grid h-12 w-12 place-items-center rounded-full bg-accent-2 text-black">
                                    <i class="fas fa-phone"></i>
                                </span>
                                <div>
                                    <h4 class="text-base font-semibold text-white">Telephone</h4>
                                    <p class="text-xs text-white/70">Du lundi au vendredi, 8h-18h</p>
                                    <a class="mt-2 inline-flex rounded-full border border-white/20 px-4 py-2 text-xs font-semibold text-white" href="tel:+23566123456">+235 66 12 34 56</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-center">
                    <div class="support-visual relative grid place-items-center">
                        <span class="pulse-ring"></span>
                        <span class="pulse-ring pulse-ring-2"></span>
                        <span class="grid h-24 w-24 place-items-center rounded-full bg-accent-2 text-black">
                            <i class="fas fa-headset text-3xl"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include 'includes/footer-tailwind.php'; ?>
