<?php
/**
 * Page Premium - Tchadok Platform
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';

$pageTitle = 'Tchadok Premium';
$pageDescription = 'Découvrez Tchadok Premium : streaming illimité, qualité HD, téléchargements et plus encore.';

$isLoggedIn = isLoggedIn();
$isPremium = $isLoggedIn && isset($_SESSION['premium_status']) && $_SESSION['premium_status'];

include 'includes/header.php';
?>

<div class="premium-page">
    <!-- Hero Section -->
    <section class="premium-hero">
        <div class="hero-background"></div>
        <div class="floating-notes">
            <span class="note">♪</span>
            <span class="note">♫</span>
            <span class="note">♪</span>
            <span class="note">♫</span>
            <span class="note">♪</span>
        </div>
        <div class="container position-relative">
            <div class="row align-items-center min-vh-75">
                <div class="col-lg-6 text-white">
                    <div class="crown-icon mb-4">
                        <i class="fas fa-crown"></i>
                    </div>
                    <h1 class="premium-title mb-3">Tchadok Premium</h1>
                    <p class="premium-subtitle mb-4">
                        Vivez la musique tchadienne comme jamais auparavant.
                        Streaming illimité, qualité premium et accès exclusif à vos artistes préférés.
                    </p>

                    <?php if (!$isPremium): ?>
                    <div class="d-flex gap-3 flex-wrap">
                        <a href="#plans" class="btn btn-premium-primary">
                            <i class="fas fa-crown me-2"></i>
                            Devenir Premium
                        </a>
                        <a href="#features" class="btn btn-premium-outline">
                            En savoir plus
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="premium-badge-alert">
                        <i class="fas fa-crown me-2"></i>
                        <strong>Vous êtes déjà membre Premium !</strong>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="col-lg-6 text-center mt-5 mt-lg-0">
                    <div class="premium-showcase">
                        <div class="showcase-glow"></div>
                        <img src="<?php echo SITE_URL; ?>/assets/images/logo.svg"
                             alt="Tchadok Premium"
                             class="premium-logo-image">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="features-section">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">Pourquoi choisir Premium ?</h2>
                <p class="section-subtitle">Débloquez l'expérience musicale complète</p>
            </div>

            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon-wrapper blue-gradient">
                            <i class="fas fa-infinity"></i>
                        </div>
                        <h5 class="feature-title">Streaming Illimité</h5>
                        <p class="feature-text">
                            Écoutez autant que vous voulez, quand vous voulez.
                            Aucune limite sur le nombre de titres ou le temps d'écoute.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon-wrapper green-gradient">
                            <i class="fas fa-download"></i>
                        </div>
                        <h5 class="feature-title">Téléchargements Gratuits</h5>
                        <p class="feature-text">
                            Téléchargez vos titres préférés pour les écouter hors ligne.
                            Accès permanent à votre collection.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon-wrapper yellow-gradient">
                            <i class="fas fa-hd-video"></i>
                        </div>
                        <h5 class="feature-title">Qualité Audio HD</h5>
                        <p class="feature-text">
                            Profitez de la meilleure qualité audio avec notre streaming HD.
                            Son cristallin jusqu'à 320kbps.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon-wrapper red-gradient">
                            <i class="fas fa-ban"></i>
                        </div>
                        <h5 class="feature-title">Sans Publicité</h5>
                        <p class="feature-text">
                            Écoutez votre musique sans interruption.
                            Fini les publicités qui coupent vos moments musicaux.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon-wrapper yellow-gradient">
                            <i class="fas fa-star"></i>
                        </div>
                        <h5 class="feature-title">Accès Exclusif</h5>
                        <p class="feature-text">
                            Nouveautés en avant-première, concerts privés,
                            et contenus exclusifs de vos artistes préférés.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon-wrapper blue-gradient">
                            <i class="fas fa-headphones"></i>
                        </div>
                        <h5 class="feature-title">Support Prioritaire</h5>
                        <p class="feature-text">
                            Support client dédié et prioritaire.
                            Assistance rapide pour tous vos besoins.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Plans -->
    <section id="plans" class="pricing-section">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">Choisissez votre plan</h2>
                <p class="section-subtitle">Des tarifs adaptés à vos besoins</p>
            </div>

            <div class="row justify-content-center g-4">
                <!-- Plan Mensuel -->
                <div class="col-lg-4 col-md-6">
                    <div class="pricing-card">
                        <div class="pricing-header">
                            <div class="pricing-icon blue-gradient">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <h4 class="pricing-plan-name">Premium Mensuel</h4>
                            <div class="price-display">
                                <span class="price-currency">FCFA</span>
                                <span class="price-amount">2,500</span>
                                <span class="price-period">/mois</span>
                            </div>
                        </div>
                        <div class="pricing-body">
                            <ul class="pricing-features">
                                <li>
                                    <i class="fas fa-check-circle"></i>
                                    Streaming illimité
                                </li>
                                <li>
                                    <i class="fas fa-check-circle"></i>
                                    Téléchargements gratuits
                                </li>
                                <li>
                                    <i class="fas fa-check-circle"></i>
                                    Qualité audio HD
                                </li>
                                <li>
                                    <i class="fas fa-check-circle"></i>
                                    Sans publicité
                                </li>
                                <li>
                                    <i class="fas fa-check-circle"></i>
                                    Support prioritaire
                                </li>
                            </ul>
                            <button class="btn btn-pricing"
                                    onclick="subscribePremium('monthly')"
                                    <?php echo !$isLoggedIn ? 'disabled title="Connexion requise"' : ''; ?>>
                                <i class="fas fa-crown me-2"></i>
                                Choisir ce plan
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Plan Annuel (Recommandé) -->
                <div class="col-lg-4 col-md-6">
                    <div class="pricing-card recommended">
                        <div class="recommended-badge">
                            <i class="fas fa-star me-1"></i>
                            Recommandé
                        </div>
                        <div class="pricing-header">
                            <div class="pricing-icon yellow-gradient">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <h4 class="pricing-plan-name">Premium Annuel</h4>
                            <div class="price-display">
                                <span class="price-currency">FCFA</span>
                                <span class="price-amount">25,000</span>
                                <span class="price-period">/an</span>
                            </div>
                            <div class="savings-badge">
                                <s>30,000 FCFA</s> - Économisez 5,000 FCFA !
                            </div>
                        </div>
                        <div class="pricing-body">
                            <ul class="pricing-features">
                                <li>
                                    <i class="fas fa-check-circle"></i>
                                    Tout du plan mensuel
                                </li>
                                <li>
                                    <i class="fas fa-check-circle"></i>
                                    <strong>2 mois gratuits</strong>
                                </li>
                                <li>
                                    <i class="fas fa-check-circle"></i>
                                    Accès exclusif aux concerts
                                </li>
                                <li>
                                    <i class="fas fa-check-circle"></i>
                                    Badge VIP sur le profil
                                </li>
                                <li>
                                    <i class="fas fa-check-circle"></i>
                                    Playlists personnalisées
                                </li>
                            </ul>
                            <button class="btn btn-pricing-recommended"
                                    onclick="subscribePremium('yearly')"
                                    <?php echo !$isLoggedIn ? 'disabled title="Connexion requise"' : ''; ?>>
                                <i class="fas fa-crown me-2"></i>
                                Choisir ce plan
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Plan Étudiant -->
                <div class="col-lg-4 col-md-6">
                    <div class="pricing-card">
                        <div class="pricing-header">
                            <div class="pricing-icon green-gradient">
                                <i class="fas fa-graduation-cap"></i>
                            </div>
                            <h4 class="pricing-plan-name">Premium Étudiant</h4>
                            <div class="price-display">
                                <span class="price-currency">FCFA</span>
                                <span class="price-amount">1,500</span>
                                <span class="price-period">/mois</span>
                            </div>
                            <div class="student-note">
                                <i class="fas fa-info-circle me-1"></i>
                                Justificatif de scolarité requis
                            </div>
                        </div>
                        <div class="pricing-body">
                            <ul class="pricing-features">
                                <li>
                                    <i class="fas fa-check-circle"></i>
                                    Streaming illimité
                                </li>
                                <li>
                                    <i class="fas fa-check-circle"></i>
                                    Téléchargements limités (50/mois)
                                </li>
                                <li>
                                    <i class="fas fa-check-circle"></i>
                                    Qualité audio standard
                                </li>
                                <li>
                                    <i class="fas fa-check-circle"></i>
                                    Sans publicité
                                </li>
                                <li>
                                    <i class="fas fa-graduation-cap"></i>
                                    Tarif étudiant spécial
                                </li>
                            </ul>
                            <button class="btn btn-pricing"
                                    onclick="subscribePremium('student')"
                                    <?php echo !$isLoggedIn ? 'disabled title="Connexion requise"' : ''; ?>>
                                <i class="fas fa-graduation-cap me-2"></i>
                                Choisir ce plan
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!$isLoggedIn): ?>
            <div class="text-center mt-5">
                <div class="login-notice">
                    <i class="fas fa-info-circle me-2"></i>
                    Vous devez être connecté pour souscrire à un abonnement Premium.
                    <a href="<?php echo SITE_URL; ?>/login.php" class="login-link">Se connecter</a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Payment Methods -->
    <section class="payment-section">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">Méthodes de paiement acceptées</h2>
                <p class="section-subtitle">Paiement sécurisé avec vos moyens préférés</p>
            </div>

            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="payment-methods-grid">
                        <div class="payment-method-card">
                            <div class="payment-icon blue-gradient">
                                <i class="fas fa-mobile-alt"></i>
                            </div>
                            <strong>Airtel Money</strong>
                        </div>
                        <div class="payment-method-card">
                            <div class="payment-icon green-gradient">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                            <strong>Moov Money</strong>
                        </div>
                        <div class="payment-method-card">
                            <div class="payment-icon yellow-gradient">
                                <i class="fas fa-university"></i>
                            </div>
                            <strong>Ecobank</strong>
                        </div>
                        <div class="payment-method-card">
                            <div class="payment-icon blue-gradient">
                                <i class="fab fa-cc-visa"></i>
                            </div>
                            <strong>Visa</strong>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-center mt-5">
                <div class="security-badge">
                    <i class="fas fa-shield-alt me-2"></i>
                    <span>Tous les paiements sont sécurisés et cryptés</span>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="faq-section">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">Questions Fréquentes</h2>
                <p class="section-subtitle">Tout ce que vous devez savoir</p>
            </div>

            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="accordion premium-accordion" id="premiumFAQ">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                    <i class="fas fa-question-circle me-2"></i>
                                    Puis-je annuler mon abonnement à tout moment ?
                                </button>
                            </h2>
                            <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#premiumFAQ">
                                <div class="accordion-body">
                                    Oui, vous pouvez annuler votre abonnement Premium à tout moment depuis votre profil.
                                    Vous continuerez à bénéficier des avantages Premium jusqu'à la fin de votre période de facturation.
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                    <i class="fas fa-question-circle me-2"></i>
                                    Que se passe-t-il avec mes téléchargements si j'annule ?
                                </button>
                            </h2>
                            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#premiumFAQ">
                                <div class="accordion-body">
                                    Tous les fichiers que vous avez téléchargés légalement restent vôtres.
                                    Vous pourrez continuer à les écouter même après l'annulation de votre abonnement.
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                    <i class="fas fa-question-circle me-2"></i>
                                    Y a-t-il une période d'essai gratuite ?
                                </button>
                            </h2>
                            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#premiumFAQ">
                                <div class="accordion-body">
                                    Oui ! Nous offrons 7 jours d'essai gratuit pour tous les nouveaux utilisateurs Premium.
                                    Vous pouvez annuler à tout moment pendant la période d'essai.
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                                    <i class="fas fa-question-circle me-2"></i>
                                    Puis-je utiliser Premium sur plusieurs appareils ?
                                </button>
                            </h2>
                            <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#premiumFAQ">
                                <div class="accordion-body">
                                    Oui, vous pouvez utiliser votre compte Premium sur jusqu'à 5 appareils différents.
                                    Cependant, l'écoute simultanée est limitée à 3 appareils.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<style>
:root {
    --bleu-tchadien: #0066CC;
    --jaune-solaire: #FFD700;
    --rouge-terre: #CC3333;
    --vert-savane: #228B22;
}

/* Hero Section */
.premium-hero {
    position: relative;
    min-height: 90vh;
    display: flex;
    align-items: center;
    overflow: hidden;
    padding: 100px 0;
}

.hero-background {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, var(--bleu-tchadien) 0%, #004999 50%, var(--jaune-solaire) 100%);
    z-index: -2;
}

.hero-background::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: radial-gradient(circle at 20% 50%, rgba(255, 215, 0, 0.2) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(204, 51, 51, 0.15) 0%, transparent 50%);
    animation: pulse 15s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 0.5; }
    50% { opacity: 1; }
}

.floating-notes {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: -1;
    overflow: hidden;
}

.floating-notes .note {
    position: absolute;
    font-size: 2rem;
    color: rgba(255, 255, 255, 0.15);
    animation: float 20s infinite;
    pointer-events: none;
}

.floating-notes .note:nth-child(1) {
    left: 10%;
    top: 20%;
    animation-delay: 0s;
    animation-duration: 25s;
}

.floating-notes .note:nth-child(2) {
    left: 70%;
    top: 60%;
    animation-delay: 5s;
    animation-duration: 22s;
}

.floating-notes .note:nth-child(3) {
    left: 30%;
    top: 70%;
    animation-delay: 10s;
    animation-duration: 28s;
}

.floating-notes .note:nth-child(4) {
    left: 85%;
    top: 30%;
    animation-delay: 15s;
    animation-duration: 24s;
}

.floating-notes .note:nth-child(5) {
    left: 50%;
    top: 50%;
    animation-delay: 8s;
    animation-duration: 26s;
}

@keyframes float {
    0% {
        transform: translateY(0px) rotate(0deg);
        opacity: 0;
    }
    10% {
        opacity: 0.15;
    }
    90% {
        opacity: 0.15;
    }
    100% {
        transform: translateY(-1000px) rotate(360deg);
        opacity: 0;
    }
}

.crown-icon i {
    font-size: 3.5rem;
    background: linear-gradient(135deg, var(--jaune-solaire), #FFA500);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    animation: logoFloat 3s ease-in-out infinite;
    filter: drop-shadow(0 5px 15px rgba(255, 215, 0, 0.5));
}

@keyframes logoFloat {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-15px); }
}

.premium-title {
    font-size: 4rem;
    font-weight: 900;
    color: white;
    text-shadow: 2px 2px 8px rgba(0, 0, 0, 0.3);
    animation: fadeInUp 1s ease;
    line-height: 1.2;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.premium-subtitle {
    font-size: 1.25rem;
    color: rgba(255, 255, 255, 0.95);
    line-height: 1.8;
    animation: fadeInUp 1s ease 0.2s backwards;
}

.btn-premium-primary {
    background: linear-gradient(135deg, var(--jaune-solaire), #FFA500);
    color: #1a1a1a;
    border: none;
    padding: 15px 40px;
    font-size: 1.1rem;
    font-weight: 700;
    border-radius: 50px;
    transition: all 0.3s ease;
    box-shadow: 0 5px 20px rgba(255, 215, 0, 0.4);
    position: relative;
    overflow: hidden;
}

.btn-premium-primary::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
    transition: left 0.5s;
}

.btn-premium-primary:hover::before {
    left: 100%;
}

.btn-premium-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 30px rgba(255, 215, 0, 0.6);
    color: #1a1a1a;
}

.btn-premium-outline {
    background: transparent;
    color: white;
    border: 2px solid white;
    padding: 15px 40px;
    font-size: 1.1rem;
    font-weight: 600;
    border-radius: 50px;
    transition: all 0.3s ease;
}

.btn-premium-outline:hover {
    background: white;
    color: var(--bleu-tchadien);
    transform: translateY(-2px);
}

.premium-badge-alert {
    display: inline-flex;
    align-items: center;
    background: linear-gradient(135deg, var(--jaune-solaire), #FFA500);
    color: #1a1a1a;
    padding: 15px 30px;
    border-radius: 50px;
    font-size: 1.1rem;
    box-shadow: 0 5px 20px rgba(255, 215, 0, 0.4);
    animation: fadeInUp 1s ease 0.4s backwards;
}

.premium-showcase {
    position: relative;
    animation: fadeInUp 1s ease 0.6s backwards;
}

.showcase-glow {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 400px;
    height: 400px;
    background: radial-gradient(circle, rgba(255, 215, 0, 0.3) 0%, transparent 70%);
    border-radius: 50%;
    animation: pulse 3s ease-in-out infinite;
}

.premium-logo-image {
    width: 300px;
    height: auto;
    filter: drop-shadow(0 15px 40px rgba(0, 0, 0, 0.4));
    animation: logoFloat 3s ease-in-out infinite;
}

.min-vh-75 {
    min-height: 75vh;
}

/* Features Section */
.features-section {
    padding: 100px 0;
    background: linear-gradient(180deg, #f8f9fa 0%, #ffffff 100%);
}

.section-title {
    font-size: 2.5rem;
    font-weight: 800;
    color: #1a1a1a;
    margin-bottom: 1rem;
}

.section-subtitle {
    font-size: 1.2rem;
    color: #6c757d;
}

.feature-card {
    background: white;
    border-radius: 20px;
    padding: 40px 30px;
    text-align: center;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
    transition: all 0.4s ease;
    height: 100%;
    border: 2px solid transparent;
}

.feature-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 15px 40px rgba(0, 102, 204, 0.15);
    border-color: var(--bleu-tchadien);
}

.feature-icon-wrapper {
    width: 80px;
    height: 80px;
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 25px;
    font-size: 2.5rem;
    color: white;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
}

.blue-gradient {
    background: linear-gradient(135deg, var(--bleu-tchadien), #0052a3);
}

.green-gradient {
    background: linear-gradient(135deg, var(--vert-savane), #1a6b1a);
}

.yellow-gradient {
    background: linear-gradient(135deg, var(--jaune-solaire), #FFA500);
}

.red-gradient {
    background: linear-gradient(135deg, var(--rouge-terre), #a32929);
}

.feature-title {
    font-size: 1.4rem;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 15px;
}

.feature-text {
    color: #6c757d;
    line-height: 1.7;
    margin: 0;
}

/* Pricing Section */
.pricing-section {
    padding: 100px 0;
    background: linear-gradient(180deg, #ffffff 0%, #f8f9fa 100%);
}

.pricing-card {
    background: white;
    border-radius: 25px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    transition: all 0.4s ease;
    height: 100%;
    border: 3px solid transparent;
    position: relative;
}

.pricing-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 50px rgba(0, 102, 204, 0.2);
}

.pricing-card.recommended {
    border-color: var(--jaune-solaire);
    box-shadow: 0 15px 40px rgba(255, 215, 0, 0.3);
    transform: scale(1.05);
}

.pricing-card.recommended:hover {
    transform: scale(1.08) translateY(-10px);
}

.recommended-badge {
    position: absolute;
    top: -15px;
    left: 50%;
    transform: translateX(-50%);
    background: linear-gradient(135deg, var(--jaune-solaire), #FFA500);
    color: #1a1a1a;
    padding: 8px 25px;
    border-radius: 50px;
    font-weight: 700;
    font-size: 0.9rem;
    box-shadow: 0 5px 15px rgba(255, 215, 0, 0.4);
    z-index: 10;
}

.pricing-header {
    padding: 40px 30px 30px;
    text-align: center;
}

.pricing-icon {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    font-size: 2rem;
    color: white;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
}

.pricing-plan-name {
    font-size: 1.6rem;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 20px;
}

.price-display {
    margin: 20px 0;
}

.price-currency {
    font-size: 1rem;
    color: #6c757d;
    vertical-align: top;
    margin-top: 10px;
}

.price-amount {
    font-size: 3.5rem;
    font-weight: 900;
    color: var(--bleu-tchadien);
    margin: 0 5px;
}

.price-period {
    font-size: 1rem;
    color: #6c757d;
}

.savings-badge {
    font-size: 0.95rem;
    color: var(--vert-savane);
    font-weight: 600;
    margin-top: 10px;
}

.student-note {
    font-size: 0.9rem;
    color: #6c757d;
    margin-top: 10px;
}

.pricing-body {
    padding: 30px;
}

.pricing-features {
    list-style: none;
    padding: 0;
    margin: 0 0 30px 0;
}

.pricing-features li {
    padding: 12px 0;
    color: #495057;
    font-size: 1rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

.pricing-features i {
    color: var(--vert-savane);
    font-size: 1.2rem;
    flex-shrink: 0;
}

.btn-pricing {
    width: 100%;
    background: linear-gradient(135deg, var(--bleu-tchadien), #0052a3);
    color: white;
    border: none;
    padding: 15px;
    font-size: 1.1rem;
    font-weight: 700;
    border-radius: 50px;
    transition: all 0.3s ease;
    box-shadow: 0 5px 15px rgba(0, 102, 204, 0.3);
}

.btn-pricing:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 102, 204, 0.4);
    background: linear-gradient(135deg, #0052a3, var(--bleu-tchadien));
    color: white;
}

.btn-pricing-recommended {
    width: 100%;
    background: linear-gradient(135deg, var(--jaune-solaire), #FFA500);
    color: #1a1a1a;
    border: none;
    padding: 15px;
    font-size: 1.1rem;
    font-weight: 700;
    border-radius: 50px;
    transition: all 0.3s ease;
    box-shadow: 0 5px 15px rgba(255, 215, 0, 0.4);
}

.btn-pricing-recommended:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(255, 215, 0, 0.5);
    background: linear-gradient(135deg, #FFA500, var(--jaune-solaire));
    color: #1a1a1a;
}

.login-notice {
    background: linear-gradient(135deg, rgba(0, 102, 204, 0.1), rgba(255, 215, 0, 0.1));
    color: #495057;
    padding: 20px 30px;
    border-radius: 15px;
    border: 2px solid var(--bleu-tchadien);
    display: inline-block;
}

.login-link {
    color: var(--bleu-tchadien);
    font-weight: 700;
    text-decoration: none;
    margin-left: 5px;
    transition: color 0.3s ease;
}

.login-link:hover {
    color: var(--jaune-solaire);
    text-decoration: underline;
}

/* Payment Section */
.payment-section {
    padding: 100px 0;
    background: linear-gradient(180deg, #f8f9fa 0%, #ffffff 100%);
}

.payment-methods-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 30px;
}

.payment-method-card {
    background: white;
    border-radius: 20px;
    padding: 40px 20px;
    text-align: center;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.payment-method-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0, 102, 204, 0.15);
    border-color: var(--bleu-tchadien);
}

.payment-icon {
    width: 70px;
    height: 70px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    font-size: 2rem;
    color: white;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
}

.payment-method-card strong {
    color: #1a1a1a;
    font-size: 1.1rem;
}

.security-badge {
    display: inline-flex;
    align-items: center;
    background: linear-gradient(135deg, rgba(34, 139, 34, 0.1), rgba(0, 102, 204, 0.1));
    color: var(--vert-savane);
    padding: 15px 30px;
    border-radius: 50px;
    font-weight: 600;
    border: 2px solid var(--vert-savane);
}

.security-badge i {
    font-size: 1.3rem;
}

/* FAQ Section */
.faq-section {
    padding: 100px 0;
    background: linear-gradient(180deg, #ffffff 0%, #f8f9fa 100%);
}

.premium-accordion .accordion-item {
    background: white;
    border: none;
    border-radius: 15px;
    margin-bottom: 15px;
    box-shadow: 0 3px 15px rgba(0, 0, 0, 0.08);
    overflow: hidden;
}

.premium-accordion .accordion-button {
    background: white;
    color: #1a1a1a;
    font-weight: 600;
    font-size: 1.1rem;
    padding: 20px 25px;
    border: none;
    box-shadow: none;
}

.premium-accordion .accordion-button:not(.collapsed) {
    background: linear-gradient(135deg, rgba(0, 102, 204, 0.1), rgba(255, 215, 0, 0.05));
    color: var(--bleu-tchadien);
    box-shadow: none;
}

.premium-accordion .accordion-button:focus {
    border: none;
    box-shadow: none;
}

.premium-accordion .accordion-button i {
    color: var(--bleu-tchadien);
}

.premium-accordion .accordion-body {
    padding: 20px 25px;
    color: #495057;
    line-height: 1.8;
    background: white;
}

/* Responsive Design */
@media (max-width: 991px) {
    .premium-title {
        font-size: 3rem;
    }

    .pricing-card.recommended {
        transform: scale(1);
    }

    .pricing-card.recommended:hover {
        transform: translateY(-10px);
    }
}

@media (max-width: 767px) {
    .premium-title {
        font-size: 2.5rem;
    }

    .premium-subtitle {
        font-size: 1.1rem;
    }

    .section-title {
        font-size: 2rem;
    }

    .price-amount {
        font-size: 2.5rem;
    }

    .payment-methods-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

/* Disabled State */
button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
</style>

<script>
function subscribePremium(plan) {
    console.log('Subscribe to plan:', plan);

    const planNames = {
        'monthly': 'Premium Mensuel (2,500 FCFA/mois)',
        'yearly': 'Premium Annuel (25,000 FCFA/an)',
        'student': 'Premium Étudiant (1,500 FCFA/mois)'
    };

    const notification = document.createElement('div');
    notification.innerHTML = `
        <div style="display: flex; align-items: center; gap: 15px;">
            <i class="fas fa-crown" style="font-size: 24px; color: #FFD700;"></i>
            <div>
                <div style="font-size: 1.1rem; font-weight: 700; margin-bottom: 5px;">
                    🎵 Souscription Premium
                </div>
                <div style="font-size: 0.95rem; opacity: 0.9;">${planNames[plan]}</div>
                <div style="font-size: 0.85rem; margin-top: 5px; opacity: 0.8;">
                    Redirection vers le paiement...
                </div>
            </div>
        </div>
    `;
    notification.style.cssText = `
        position: fixed;
        top: 30px;
        right: 30px;
        background: linear-gradient(135deg, #228B22, #1a6b1a);
        color: white;
        padding: 20px 25px;
        border-radius: 15px;
        z-index: 10000;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        max-width: 380px;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        animation: slideInRight 0.4s ease;
    `;

    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideInRight {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
    `;
    document.head.appendChild(style);

    document.body.appendChild(notification);
    setTimeout(() => {
        notification.style.animation = 'slideInRight 0.4s ease reverse';
        setTimeout(() => notification.remove(), 400);
    }, 4500);

    // Simuler la redirection vers le paiement
    setTimeout(() => {
        if (confirm(`Confirmer la souscription au plan ${planNames[plan]} ?\n\nVous serez redirigé vers une page de paiement sécurisée.`)) {
            alert('Redirection vers la page de paiement...\n(Fonctionnalité en cours de développement)');
        }
    }, 1500);
}
</script>

<?php include 'includes/footer.php'; ?>
