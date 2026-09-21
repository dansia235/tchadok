<?php
/**
 * Page Premium - Tchadok Platform
 * Refonte interface Premium
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';

$pageTitle = 'Tchadok Premium';
$pageDescription = 'Activez Tchadok Premium et profitez d\'une expérience musicale fluide, élégante et pensée pour la scène tchadienne.';

$isLoggedIn = isLoggedIn();
$isPremium = $isLoggedIn && !empty($_SESSION['premium_status']);
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

$walletBalance = (float) ($user['wallet_balance'] ?? 0);
$profileName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
if ($profileName === '') {
    $profileName = $user['username'] ?? 'Utilisateur';
}

$publicLoginUrl = SITE_URL . '/login.php?redirect=premium';
$registerUrl = SITE_URL . '/register.php';
$walletUrl = SITE_URL . '/wallet.php';
$supportUrl = SITE_URL . '/contact.php';

$heroStats = [
    ['value' => '320 kbps', 'label' => 'qualité audio'],
    ['value' => '0 pub', 'label' => 'écoute continue'],
    ['value' => '24/7', 'label' => 'lecture sans limite'],
];

$membershipSignals = [
    [
        'label' => 'Statut',
        'value' => $isPremium ? 'Premium actif' : 'Formule gratuite',
        'tone' => $isPremium
            ? 'border-amber-300/60 bg-amber-100/90 text-amber-900 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-200'
            : 'border-slate-200 bg-white/90 text-slate-700 dark:border-white/10 dark:bg-white/5 dark:text-muted'
    ],
    [
        'label' => 'Portefeuille',
        'value' => number_format($walletBalance, 0, ',', ' ') . ' FCFA',
        'tone' => 'border-slate-200 bg-white/90 text-slate-900 dark:border-white/10 dark:bg-white/5 dark:text-text'
    ],
    [
        'label' => 'Parcours',
        'value' => $isLoggedIn ? $workspaceLabel : 'Visiteur',
        'tone' => 'border-slate-200 bg-white/90 text-slate-700 dark:border-white/10 dark:bg-white/5 dark:text-muted'
    ],
];

$offerMoments = [
    [
        'icon' => 'fa-bolt',
        'title' => 'Un flux sans rupture',
        'text' => 'Passez d’un titre à l’autre sans interruption et sans coupure publicitaire.',
        'tone' => 'from-accent/20 to-accent/5 text-accent'
    ],
    [
        'icon' => 'fa-download',
        'title' => 'Votre musique partout',
        'text' => 'Téléchargez vos morceaux et gardez vos playlists disponibles, même hors ligne.',
        'tone' => 'from-emerald-500/20 to-emerald-500/5 text-emerald-700 dark:text-emerald-300'
    ],
    [
        'icon' => 'fa-crown',
        'title' => 'Une relation premium',
        'text' => 'Support prioritaire, sorties sélectionnées et un accès mieux orchestré à vos contenus favoris.',
        'tone' => 'from-amber-400/20 to-amber-400/5 text-amber-700 dark:text-amber-200'
    ],
];

$features = [
    [
        'icon' => 'fa-infinity',
        'title' => 'Streaming illimité',
        'text' => 'Écoutez vos artistes favoris sans plafond de lecture ni restrictions inutiles.',
        'tone' => 'bg-accent/15 text-accent'
    ],
    [
        'icon' => 'fa-download',
        'title' => 'Téléchargements hors ligne',
        'text' => 'Gardez vos morceaux et playlists disponibles même sans connexion.',
        'tone' => 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300'
    ],
    [
        'icon' => 'fa-wave-square',
        'title' => 'Qualité audio HD',
        'text' => 'Profitez d’un rendu plus riche, propre et stable jusqu’à 320 kbps.',
        'tone' => 'bg-amber-400/15 text-amber-700 dark:text-amber-300'
    ],
    [
        'icon' => 'fa-ban',
        'title' => 'Sans publicité',
        'text' => 'Conservez une écoute fluide, sans interruption entre les contenus.',
        'tone' => 'bg-rose-500/15 text-rose-700 dark:text-rose-300'
    ],
    [
        'icon' => 'fa-headset',
        'title' => 'Support prioritaire',
        'text' => 'Un parcours plus rapide pour les questions de compte, paiement et activation.',
        'tone' => 'bg-sky-400/15 text-sky-700 dark:text-sky-200'
    ],
    [
        'icon' => 'fa-layer-group',
        'title' => 'Playlists enrichies',
        'text' => 'Créez une expérience d’écoute plus libre avec un usage plus intensif et plus confortable.',
        'tone' => 'bg-white/10 text-slate-700 dark:text-text'
    ]
];

$plans = [
    [
        'key' => 'monthly',
        'name' => 'Formule mensuelle',
        'price' => 2500,
        'period' => 'mois',
        'icon' => 'fa-calendar-alt',
        'highlight' => false,
        'badge' => null,
        'note' => 'Souple et sans engagement long',
        'features' => [
            'Streaming illimité',
            'Téléchargements hors ligne',
            'Qualité audio HD',
            'Sans publicité',
            'Support prioritaire'
        ],
        'button' => 'Activer cette formule'
    ],
    [
        'key' => 'yearly',
        'name' => 'Formule annuelle',
        'price' => 25000,
        'period' => 'an',
        'icon' => 'fa-calendar-check',
        'highlight' => true,
        'badge' => 'Recommandé',
        'note' => 'Économisez 5 000 FCFA sur l’année',
        'features' => [
            'Tous les avantages mensuels',
            'Tarif plus avantageux sur la durée',
            'Accès premium continu toute l’année',
            'Expérience plus stable pour les gros auditeurs',
            'Priorité durable sur le support'
        ],
        'button' => 'Choisir l’annuel'
    ]
];

$paymentMethods = [
    ['label' => 'Airtel Money', 'icon' => 'fa-mobile-alt', 'tone' => 'bg-accent/15 text-accent', 'text' => 'Paiement mobile rapide'],
    ['label' => 'Moov Money', 'icon' => 'fa-money-bill-wave', 'tone' => 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300', 'text' => 'Validation locale simplifiée'],
    ['label' => 'Ecobank', 'icon' => 'fa-university', 'tone' => 'bg-amber-400/15 text-amber-700 dark:text-amber-300', 'text' => 'Canal bancaire fiable'],
    ['label' => 'Visa', 'icon' => 'fa-credit-card', 'tone' => 'bg-white/10 text-slate-700 dark:text-text', 'text' => 'Carte internationale']
];

$faqs = [
    [
        'q' => 'Puis-je annuler mon abonnement à tout moment ?',
        'a' => 'Oui, vous pouvez annuler depuis votre profil. Les avantages restent actifs jusqu\'à la fin de la période de facturation.'
    ],
    [
        'q' => 'Que deviennent mes téléchargements si j\'annule ?',
        'a' => 'Les fichiers téléchargés légalement restent disponibles. Vous pourrez continuer à les écouter.'
    ],
    [
        'q' => 'Y a-t-il une période d\'essai gratuite ?',
        'a' => 'Oui, nous offrons 7 jours d\'essai pour les nouveaux utilisateurs Premium.'
    ],
    [
        'q' => 'Puis-je utiliser Premium sur plusieurs appareils ?',
        'a' => 'Oui, jusqu\'à 5 appareils. L\'écoute simultanée est limitée à 3 appareils.'
    ],
    [
        'q' => 'Le paiement est-il immédiatement actif ?',
        'a' => 'L’activation démarre après validation du paiement. Le délai dépend du canal utilisé, mais le parcours reste suivi depuis votre compte.'
    ]
];

$additionalJS = [
    SITE_URL . '/assets/js/premium.js'
];

include 'includes/header-tailwind.php';
?>

<main class="min-h-screen bg-[radial-gradient(circle_at_top_right,rgba(47,109,224,0.10),transparent_28%),radial-gradient(circle_at_top_left,rgba(255,193,7,0.10),transparent_24%),#f5f7fb] pb-20 pt-24 dark:bg-[radial-gradient(circle_at_top_right,rgba(47,109,224,0.18),transparent_28%),radial-gradient(circle_at_top_left,rgba(245,158,11,0.12),transparent_24%),#0B0F17]">
    <section class="relative w-full overflow-hidden bg-[linear-gradient(120deg,#f7f9fd_0%,#e7efff_34%,#fff7dd_70%,#d8e5ff_100%)] py-16 lg:py-20 dark:bg-[linear-gradient(120deg,#0B0F17_0%,#141A26_34%,#1B2433_70%,#2F6DE0_100%)]">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,rgba(255,255,255,0.78),transparent_56%)] dark:bg-[radial-gradient(circle_at_top,rgba(255,255,255,0.08),transparent_52%)]"></div>
        <div class="absolute -left-24 top-12 h-72 w-72 rounded-full bg-accent/12 blur-3xl dark:bg-accent/25"></div>
        <div class="absolute right-0 top-10 h-96 w-96 rounded-full bg-amber-400/16 blur-3xl dark:bg-amber-400/12"></div>
        <div class="absolute inset-y-0 right-[14%] hidden w-px bg-slate-300/60 dark:bg-white/10 lg:block"></div>

        <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-8 lg:grid-cols-[minmax(0,1.2fr)_400px]">
                <div class="rounded-[2rem] border border-slate-200/90 bg-[linear-gradient(160deg,rgba(255,255,255,0.96),rgba(234,242,255,0.90))] p-8 shadow-elev-3 backdrop-blur-sm dark:border-white/15 dark:bg-[linear-gradient(160deg,rgba(20,26,38,0.86),rgba(27,36,51,0.82))] sm:p-10">
                    <div class="inline-flex items-center gap-2 rounded-full border border-amber-300/60 bg-amber-100/90 px-4 py-2 text-xs font-semibold uppercase tracking-[0.22em] text-amber-900 dark:border-amber-400/30 dark:bg-amber-400/12 dark:text-amber-200">
                        <i class="fas fa-crown"></i>
                        Offre premium
                    </div>

                    <h1 class="mt-6 max-w-3xl text-4xl font-display font-bold leading-tight text-slate-900 dark:text-white sm:text-5xl lg:text-6xl">
                        Une écoute premium, pensée pour la scène tchadienne.
                    </h1>

                    <p class="mt-5 max-w-2xl text-base leading-8 text-slate-600 dark:text-white/78 sm:text-lg">
                        Tchadok Premium revient dans un vrai parcours public :
                        même header que le reste du site, meilleure lisibilité, couleurs cohérentes
                        et une mise en page plus propre que l’ancienne version en double bloc.
                    </p>

                    <div class="mt-6 flex flex-wrap gap-2 text-xs">
                        <?php foreach ($membershipSignals as $signal): ?>
                            <span class="rounded-full border px-3 py-1 <?php echo $signal['tone']; ?>">
                                <span class="font-semibold"><?php echo htmlspecialchars($signal['label']); ?> :</span>
                                <?php echo htmlspecialchars($signal['value']); ?>
                            </span>
                        <?php endforeach; ?>
                        <?php if ($isLoggedIn): ?>
                            <span class="rounded-full border border-slate-200 bg-white/90 px-3 py-1 text-slate-700 dark:border-white/15 dark:bg-white/10 dark:text-white/80">
                                <?php echo htmlspecialchars($profileName); ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="mt-8 flex flex-wrap gap-3">
                        <?php if (!$isLoggedIn): ?>
                            <a href="<?php echo $publicLoginUrl; ?>" class="inline-flex items-center gap-2 rounded-full bg-accent px-6 py-3 text-sm font-semibold text-white shadow-elev-1 transition hover:-translate-y-0.5 hover:shadow-elev-2">
                                <i class="fas fa-right-to-bracket"></i>
                                Se connecter pour activer
                            </a>
                            <a href="<?php echo $registerUrl; ?>" class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-6 py-3 text-sm font-semibold text-text hover:bg-white/10">
                                <i class="fas fa-user-plus"></i>
                                Créer un compte
                            </a>
                        <?php elseif ($isPremium): ?>
                            <a href="<?php echo $walletUrl; ?>" class="inline-flex items-center gap-2 rounded-full bg-amber-400 px-6 py-3 text-sm font-semibold text-bg shadow-elev-1 transition hover:-translate-y-0.5 hover:shadow-elev-2">
                                <i class="fas fa-wallet"></i>
                                Voir mon portefeuille
                            </a>
                            <a href="<?php echo $dashboardUrl; ?>" class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-6 py-3 text-sm font-semibold text-text hover:bg-white/10">
                                <i class="fas fa-columns"></i>
                                Retour à mon espace
                            </a>
                            <a href="<?php echo $supportUrl; ?>" class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-6 py-3 text-sm font-semibold text-text hover:bg-white/10">
                                <i class="fas fa-headset"></i>
                                Support premium
                            </a>
                        <?php else: ?>
                            <a href="#plans" class="inline-flex items-center gap-2 rounded-full bg-amber-400 px-6 py-3 text-sm font-semibold text-bg shadow-elev-1 transition hover:-translate-y-0.5 hover:shadow-elev-2">
                                <i class="fas fa-crown"></i>
                                Découvrir les formules
                            </a>
                            <a href="<?php echo $walletUrl; ?>" class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-6 py-3 text-sm font-semibold text-text hover:bg-white/10">
                                <i class="fas fa-wallet"></i>
                                Préparer mon paiement
                            </a>
                            <a href="<?php echo $dashboardUrl; ?>" class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-6 py-3 text-sm font-semibold text-text hover:bg-white/10">
                                <i class="fas fa-arrow-left"></i>
                                Retour à mon espace
                            </a>
                        <?php endif; ?>
                    </div>

                    <div class="mt-10 grid gap-4 sm:grid-cols-3">
                        <?php foreach ($heroStats as $stat): ?>
                            <div class="rounded-3xl border border-slate-200 bg-white/85 p-4 dark:border-white/15 dark:bg-white/10">
                                <p class="text-xs uppercase tracking-[0.2em] text-slate-500 dark:text-white/60"><?php echo htmlspecialchars($stat['label']); ?></p>
                                <p class="mt-3 text-2xl font-semibold text-slate-900 dark:text-white"><?php echo htmlspecialchars($stat['value']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <aside class="rounded-[2rem] border border-slate-200/90 bg-[linear-gradient(180deg,rgba(255,255,255,0.96),rgba(245,247,251,0.94))] p-6 shadow-elev-2 backdrop-blur-sm dark:border-white/15 dark:bg-[linear-gradient(180deg,rgba(20,26,38,0.9),rgba(11,15,23,0.88))]">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs uppercase tracking-[0.24em] text-slate-500 dark:text-white/55">Activation</p>
                            <h2 class="mt-2 text-2xl font-display font-semibold text-slate-900 dark:text-white">
                                <?php echo $isPremium ? 'Premium déjà actif' : 'Prêt à passer au niveau supérieur'; ?>
                            </h2>
                        </div>
                        <span class="grid h-14 w-14 place-items-center rounded-2xl bg-amber-100 text-amber-700 dark:bg-amber-400/15 dark:text-amber-300">
                            <i class="fas fa-crown text-2xl"></i>
                        </span>
                    </div>

                    <div class="mt-6 space-y-4">
                        <?php foreach ($offerMoments as $moment): ?>
                            <div class="rounded-3xl border border-slate-200 bg-white/80 p-5 dark:border-white/15 dark:bg-white/8">
                                <div class="flex items-start gap-4">
                                    <span class="grid h-12 w-12 place-items-center rounded-2xl bg-gradient-to-br <?php echo $moment['tone']; ?>">
                                        <i class="fas <?php echo $moment['icon']; ?>"></i>
                                    </span>
                                    <div>
                                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white"><?php echo $moment['title']; ?></h3>
                                        <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-white/72"><?php echo $moment['text']; ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="mt-6 rounded-3xl border border-emerald-300/40 bg-emerald-100/70 p-5 dark:border-emerald-400/20 dark:bg-emerald-500/10">
                        <p class="text-xs uppercase tracking-[0.24em] text-emerald-700 dark:text-emerald-100/80">Paiement</p>
                        <p class="mt-3 text-sm leading-6 text-emerald-800 dark:text-emerald-100">
                            Le paiement reste guidé, tracé et redirige vers l’écran de validation dédié pour un parcours plus net.
                        </p>
                    </div>
                </aside>
            </div>
        </div>
    </section>

    <section class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-4 lg:grid-cols-3">
                <?php foreach ($offerMoments as $moment): ?>
                    <div class="rounded-3xl border border-white/10 bg-surface/60 p-5 shadow-elev-1">
                        <div class="flex items-center gap-3">
                            <span class="grid h-11 w-11 place-items-center rounded-2xl bg-white/5 text-text">
                                <i class="fas <?php echo $moment['icon']; ?>"></i>
                            </span>
                            <div>
                                <p class="text-sm font-semibold text-text"><?php echo $moment['title']; ?></p>
                                <p class="mt-1 text-xs text-muted"><?php echo $moment['text']; ?></p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section id="features" class="py-12">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-[0.24em] text-muted">Avantages</p>
                    <h2 class="mt-2 text-3xl font-display font-bold text-text">Une offre plus claire, plus complète, plus utile</h2>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-muted">
                        Les bénéfices sont désormais présentés comme un vrai produit public :
                        contraste lisible, hiérarchie stable et une grammaire visuelle alignée avec les autres interfaces.
                    </p>
                </div>
                <?php if ($isLoggedIn): ?>
                    <a href="<?php echo $dashboardUrl; ?>" class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-5 py-3 text-sm font-semibold text-text hover:bg-white/10">
                        <i class="fas fa-arrow-left"></i>
                        Retour à mon espace
                    </a>
                <?php endif; ?>
            </div>

            <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <?php foreach ($features as $feature): ?>
                    <article class="rounded-3xl border border-white/10 bg-surface/60 p-6 shadow-elev-1 transition hover:-translate-y-1 hover:shadow-elev-2">
                        <div class="grid h-14 w-14 place-items-center rounded-2xl <?php echo $feature['tone']; ?>">
                            <i class="fas <?php echo $feature['icon']; ?> text-xl"></i>
                        </div>
                        <h3 class="mt-5 text-lg font-semibold text-text"><?php echo $feature['title']; ?></h3>
                        <p class="mt-3 text-sm leading-6 text-muted"><?php echo $feature['text']; ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section id="plans" class="py-12">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-[2rem] border border-white/10 bg-[linear-gradient(180deg,rgba(255,255,255,0.96),rgba(231,239,255,0.92))] p-8 shadow-elev-2 dark:bg-[linear-gradient(180deg,rgba(255,255,255,0.05),rgba(20,26,38,0.92))] sm:p-10">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p class="text-xs uppercase tracking-[0.24em] text-muted">Formules</p>
                        <h2 class="mt-2 text-3xl font-display font-bold text-text">Choisissez un rythme d’abonnement cohérent</h2>
                        <p class="mt-3 max-w-2xl text-sm leading-6 text-muted">
                            Les montants affichés sont alignés avec l’écran de paiement pour éviter tout décalage
                            entre la promesse de la page et la validation finale.
                        </p>
                    </div>
                    <div class="rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs text-muted">
                        Activation après validation du paiement
                    </div>
                </div>

                <div class="mt-10 grid gap-6 lg:grid-cols-2">
                    <?php foreach ($plans as $plan): ?>
                        <article class="relative flex h-full flex-col rounded-[2rem] border border-white/10 bg-white/5 p-6 shadow-elev-1 <?php echo $plan['highlight'] ? 'ring-2 ring-amber-400/40' : ''; ?>">
                            <?php if ($plan['badge']): ?>
                                <span class="absolute -top-4 left-6 rounded-full bg-amber-400 px-4 py-1 text-xs font-semibold text-bg shadow-elev-1">
                                    <?php echo $plan['badge']; ?>
                                </span>
                            <?php endif; ?>

                            <div class="flex items-center justify-between gap-4">
                                <div class="flex items-center gap-3">
                                    <span class="grid h-12 w-12 place-items-center rounded-2xl <?php echo $plan['highlight'] ? 'bg-amber-100 text-amber-700 dark:bg-amber-400/15 dark:text-amber-300' : 'bg-accent/15 text-accent'; ?>">
                                        <i class="fas <?php echo $plan['icon']; ?>"></i>
                                    </span>
                                    <div>
                                        <p class="text-xs uppercase tracking-[0.2em] text-muted">Formule</p>
                                        <h3 class="text-lg font-semibold text-text"><?php echo $plan['name']; ?></h3>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-3xl font-semibold text-text"><?php echo number_format($plan['price'], 0, ',', ' '); ?></p>
                                    <p class="text-xs text-muted">FCFA / <?php echo $plan['period']; ?></p>
                                </div>
                            </div>

                            <?php if ($plan['note']): ?>
                                <p class="mt-5 rounded-2xl border border-white/10 bg-white/80 px-4 py-3 text-sm text-muted dark:bg-bg/60">
                                    <?php echo $plan['note']; ?>
                                </p>
                            <?php endif; ?>

                            <ul class="mt-6 space-y-3 text-sm text-muted">
                                <?php foreach ($plan['features'] as $item): ?>
                                    <li class="flex items-start gap-3">
                                        <i class="fas fa-check-circle mt-0.5 text-emerald-700 dark:text-emerald-300"></i>
                                        <span><?php echo $item; ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>

                            <div class="mt-8">
                                <?php if (!$isLoggedIn): ?>
                                    <a href="<?php echo $publicLoginUrl; ?>" class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-accent px-6 py-3 text-sm font-semibold text-white shadow-elev-1 hover:shadow-elev-2">
                                        <i class="fas fa-right-to-bracket"></i>
                                        Se connecter pour continuer
                                    </a>
                                <?php elseif ($isPremium): ?>
                                    <button type="button" class="inline-flex w-full items-center justify-center gap-2 rounded-full border border-amber-300/60 bg-amber-100/90 px-6 py-3 text-sm font-semibold text-amber-900 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-200" disabled>
                                        <i class="fas fa-check-circle"></i>
                                        Déjà actif sur votre compte
                                    </button>
                                <?php else: ?>
                                    <button type="button"
                                            class="inline-flex w-full items-center justify-center gap-2 rounded-full px-6 py-3 text-sm font-semibold shadow-elev-1 transition hover:shadow-elev-2 <?php echo $plan['highlight'] ? 'bg-amber-400 text-bg' : 'bg-accent text-white'; ?>"
                                            data-subscribe
                                            data-plan="<?php echo $plan['key']; ?>">
                                        <i class="fas fa-crown"></i>
                                        <?php echo $plan['button']; ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <?php if (!$isLoggedIn): ?>
                    <div class="mt-8 rounded-3xl border border-accent/30 bg-accent/10 p-5 text-sm text-accent">
                        <div class="flex flex-wrap items-center gap-2">
                            <i class="fas fa-info-circle"></i>
                            <span>Vous devez être connecté pour lancer la souscription et accéder à l’écran de paiement.</span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="py-12">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-6 lg:grid-cols-[minmax(0,1.1fr)_420px]">
                <div class="rounded-[2rem] border border-white/10 bg-surface/60 p-8 shadow-elev-1">
                    <p class="text-xs uppercase tracking-[0.24em] text-muted">Paiement</p>
                    <h2 class="mt-2 text-3xl font-display font-bold text-text">Méthodes de paiement acceptées</h2>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-muted">
                        Le bloc paiement reprend le même niveau de finition que le reste du front :
                        cartes homogènes, contraste stable et meilleure lisibilité sur desktop comme mobile.
                    </p>

                    <div class="mt-8 grid gap-4 sm:grid-cols-2">
                        <?php foreach ($paymentMethods as $method): ?>
                            <div class="rounded-3xl border border-white/10 bg-white/5 p-5">
                                <div class="flex items-center gap-4">
                                    <span class="grid h-12 w-12 place-items-center rounded-2xl <?php echo $method['tone']; ?>">
                                        <i class="fas <?php echo $method['icon']; ?>"></i>
                                    </span>
                                    <div>
                                        <p class="text-sm font-semibold text-text"><?php echo $method['label']; ?></p>
                                        <p class="mt-1 text-xs text-muted"><?php echo $method['text']; ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="rounded-[2rem] border border-white/10 bg-[linear-gradient(180deg,rgba(47,109,224,0.08),rgba(255,255,255,0.96))] p-8 shadow-elev-2 dark:bg-[linear-gradient(180deg,rgba(47,109,224,0.12),rgba(20,26,38,0.9))]">
                    <p class="text-xs uppercase tracking-[0.24em] text-muted">Confiance</p>
                    <h2 class="mt-2 text-2xl font-display font-semibold text-text">Un parcours plus professionnel</h2>
                    <div class="mt-6 space-y-4 text-sm text-muted">
                        <div class="rounded-3xl border border-white/10 bg-white/5 p-4">
                            <span class="flex items-center gap-2 text-emerald-700 dark:text-emerald-300">
                                <i class="fas fa-shield-alt"></i>
                                Paiements sécurisés et cryptés
                            </span>
                        </div>
                        <div class="rounded-3xl border border-white/10 bg-white/5 p-4">
                            <span class="flex items-center gap-2 text-sky-700 dark:text-sky-200">
                                <i class="fas fa-route"></i>
                                Redirection claire vers l’écran de paiement
                            </span>
                        </div>
                        <div class="rounded-3xl border border-white/10 bg-white/5 p-4">
                            <span class="flex items-center gap-2 text-amber-700 dark:text-amber-200">
                                <i class="fas fa-headset"></i>
                                Support prioritaire pour les demandes premium
                            </span>
                        </div>
                    </div>

                    <div class="mt-8 flex flex-wrap gap-3">
                        <a href="<?php echo $supportUrl; ?>" class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-5 py-3 text-sm font-semibold text-text hover:bg-white/10">
                            <i class="fas fa-headset"></i>
                            Contacter le support
                        </a>
                        <?php if ($isLoggedIn): ?>
                            <a href="<?php echo $walletUrl; ?>" class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-5 py-3 text-sm font-semibold text-text hover:bg-white/10">
                                <i class="fas fa-wallet"></i>
                                Ouvrir le portefeuille
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-12">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <p class="text-xs uppercase tracking-[0.24em] text-muted">FAQ</p>
                <h2 class="mt-2 text-3xl font-display font-bold text-text">Questions fréquentes</h2>
                <p class="mt-3 text-sm text-muted">Tout ce qu’il faut savoir avant d’activer Premium.</p>
            </div>

            <div class="mt-10 space-y-4">
                <?php foreach ($faqs as $faq): ?>
                    <details class="rounded-3xl border border-white/10 bg-surface/60 p-5 shadow-elev-1">
                        <summary class="cursor-pointer list-none text-sm font-semibold text-text">
                            <div class="flex items-center justify-between gap-4">
                                <span><?php echo $faq['q']; ?></span>
                                <i class="fas fa-chevron-down text-xs text-muted"></i>
                            </div>
                        </summary>
                        <p class="mt-3 text-sm leading-6 text-muted"><?php echo $faq['a']; ?></p>
                    </details>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
</main>

<?php include 'includes/footer-tailwind.php'; ?>
