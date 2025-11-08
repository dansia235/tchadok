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
    <section class="premium-hero py-5" style="background: linear-gradient(135deg, #FFD700 0%, #0066CC 100%);">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 text-white">
                    <div class="crown-icon mb-4">
                        <i class="fas fa-crown" style="font-size: 3rem; color: #FFD700;"></i>
                    </div>
                    <h1 class="display-4 fw-bold mb-3">Tchadok Premium</h1>
                    <p class="lead mb-4">
                        Vivez la musique tchadienne comme jamais auparavant. 
                        Streaming illimité, qualité premium et accès exclusif à vos artistes préférés.
                    </p>
                    
                    <?php if (!$isPremium): ?>
                    <div class="d-flex gap-3">
                        <a href="#plans" class="btn btn-warning btn-lg px-4">
                            <i class="fas fa-crown me-2"></i>
                            Devenir Premium
                        </a>
                        <a href="#features" class="btn btn-outline-light btn-lg">
                            En savoir plus
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-warning d-inline-flex align-items-center">
                        <i class="fas fa-crown me-2"></i>
                        <strong>Vous êtes déjà membre Premium !</strong>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="col-lg-6 text-center">
                    <div class="premium-showcase">
                        <img src="<?php echo SITE_URL; ?>/assets/images/logo.svg" 
                             alt="Tchadok Premium" 
                             style="width: 300px; height: auto; filter: drop-shadow(0 10px 30px rgba(0,0,0,0.3));">
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Features Section -->
    <section id="features" class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="display-6 fw-bold">Pourquoi choisir Premium ?</h2>
                <p class="lead text-muted">Débloquez l'expérience musicale complète</p>
            </div>
            
            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card card border-0 shadow h-100">
                        <div class="card-body text-center p-4">
                            <div class="feature-icon mb-3">
                                <i class="fas fa-infinity text-primary" style="font-size: 2.5rem;"></i>
                            </div>
                            <h5 class="card-title">Streaming Illimité</h5>
                            <p class="card-text text-muted">
                                Écoutez autant que vous voulez, quand vous voulez. 
                                Aucune limite sur le nombre de titres ou le temps d'écoute.
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card card border-0 shadow h-100">
                        <div class="card-body text-center p-4">
                            <div class="feature-icon mb-3">
                                <i class="fas fa-download text-success" style="font-size: 2.5rem;"></i>
                            </div>
                            <h5 class="card-title">Téléchargements Gratuits</h5>
                            <p class="card-text text-muted">
                                Téléchargez vos titres préférés pour les écouter hors ligne. 
                                Accès permanent à votre collection.
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card card border-0 shadow h-100">
                        <div class="card-body text-center p-4">
                            <div class="feature-icon mb-3">
                                <i class="fas fa-hd-video text-warning" style="font-size: 2.5rem;"></i>
                            </div>
                            <h5 class="card-title">Qualité Audio HD</h5>
                            <p class="card-text text-muted">
                                Profitez de la meilleure qualité audio avec notre streaming HD. 
                                Son cristallin jusqu'à 320kbps.
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card card border-0 shadow h-100">
                        <div class="card-body text-center p-4">
                            <div class="feature-icon mb-3">
                                <i class="fas fa-ban text-danger" style="font-size: 2.5rem;"></i>
                            </div>
                            <h5 class="card-title">Sans Publicité</h5>
                            <p class="card-text text-muted">
                                Écoutez votre musique sans interruption. 
                                Fini les publicités qui coupent vos moments musicaux.
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card card border-0 shadow h-100">
                        <div class="card-body text-center p-4">
                            <div class="feature-icon mb-3">
                                <i class="fas fa-star text-info" style="font-size: 2.5rem;"></i>
                            </div>
                            <h5 class="card-title">Accès Exclusif</h5>
                            <p class="card-text text-muted">
                                Nouveautés en avant-première, concerts privés, 
                                et contenus exclusifs de vos artistes préférés.
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card card border-0 shadow h-100">
                        <div class="card-body text-center p-4">
                            <div class="feature-icon mb-3">
                                <i class="fas fa-headphones text-secondary" style="font-size: 2.5rem;"></i>
                            </div>
                            <h5 class="card-title">Support Prioritaire</h5>
                            <p class="card-text text-muted">
                                Support client dédié et prioritaire. 
                                Assistance rapide pour tous vos besoins.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Pricing Plans -->
    <section id="plans" class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="display-6 fw-bold">Choisissez votre plan</h2>
                <p class="lead text-muted">Des tarifs adaptés à vos besoins</p>
            </div>
            
            <div class="row justify-content-center g-4">
                <!-- Plan Mensuel -->
                <div class="col-lg-4 col-md-6">
                    <div class="pricing-card card border-0 shadow h-100">
                        <div class="card-header bg-white text-center border-0 pt-4">
                            <h4 class="text-primary">Premium Mensuel</h4>
                            <div class="price-display">
                                <span class="price-currency">FCFA</span>
                                <span class="price-amount">2,500</span>
                                <span class="price-period">/mois</span>
                            </div>
                        </div>
                        <div class="card-body d-flex flex-column">
                            <ul class="list-unstyled mb-4">
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    Streaming illimité
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    Téléchargements gratuits
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    Qualité audio HD
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    Sans publicité
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    Support prioritaire
                                </li>
                            </ul>
                            <div class="mt-auto">
                                <button class="btn btn-outline-primary w-100" 
                                        onclick="subscribePremium('monthly')"
                                        <?php echo !$isLoggedIn ? 'disabled title="Connexion requise"' : ''; ?>>
                                    <i class="fas fa-crown me-2"></i>
                                    Choisir ce plan
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Plan Annuel (Recommandé) -->
                <div class="col-lg-4 col-md-6">
                    <div class="pricing-card card border-warning shadow h-100 position-relative">
                        <div class="badge bg-warning text-dark position-absolute top-0 start-50 translate-middle px-3 py-2">
                            <i class="fas fa-star me-1"></i>
                            Recommandé
                        </div>
                        <div class="card-header bg-warning text-center border-0 pt-4">
                            <h4 class="text-dark">Premium Annuel</h4>
                            <div class="price-display">
                                <span class="price-currency text-dark">FCFA</span>
                                <span class="price-amount text-dark">25,000</span>
                                <span class="price-period text-dark">/an</span>
                            </div>
                            <small class="text-dark">
                                <s>30,000 FCFA</s> - Économisez 5,000 FCFA !
                            </small>
                        </div>
                        <div class="card-body d-flex flex-column">
                            <ul class="list-unstyled mb-4">
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    Tout du plan mensuel
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    <strong>2 mois gratuits</strong>
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    Accès exclusif aux concerts
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    Badge VIP sur le profil
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    Playlists personnalisées
                                </li>
                            </ul>
                            <div class="mt-auto">
                                <button class="btn btn-warning w-100 text-dark fw-bold" 
                                        onclick="subscribePremium('yearly')"
                                        <?php echo !$isLoggedIn ? 'disabled title="Connexion requise"' : ''; ?>>
                                    <i class="fas fa-crown me-2"></i>
                                    Choisir ce plan
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Plan Étudiant -->
                <div class="col-lg-4 col-md-6">
                    <div class="pricing-card card border-0 shadow h-100">
                        <div class="card-header bg-white text-center border-0 pt-4">
                            <h4 class="text-info">Premium Étudiant</h4>
                            <div class="price-display">
                                <span class="price-currency">FCFA</span>
                                <span class="price-amount">1,500</span>
                                <span class="price-period">/mois</span>
                            </div>
                            <small class="text-muted">
                                Justificatif de scolarité requis
                            </small>
                        </div>
                        <div class="card-body d-flex flex-column">
                            <ul class="list-unstyled mb-4">
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    Streaming illimité
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    Téléchargements limités (50/mois)
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    Qualité audio standard
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    Sans publicité
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-graduation-cap text-info me-2"></i>
                                    Tarif étudiant spécial
                                </li>
                            </ul>
                            <div class="mt-auto">
                                <button class="btn btn-outline-info w-100" 
                                        onclick="subscribePremium('student')"
                                        <?php echo !$isLoggedIn ? 'disabled title="Connexion requise"' : ''; ?>>
                                    <i class="fas fa-graduation-cap me-2"></i>
                                    Choisir ce plan
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if (!$isLoggedIn): ?>
            <div class="text-center mt-4">
                <p class="text-muted">
                    <i class="fas fa-info-circle me-2"></i>
                    Vous devez être connecté pour souscrire à un abonnement Premium.
                    <a href="<?php echo SITE_URL; ?>/login-new.php" class="text-decoration-none">Se connecter</a>
                </p>
            </div>
            <?php endif; ?>
        </div>
    </section>
    
    <!-- Payment Methods -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="h3 fw-bold">Méthodes de paiement acceptées</h2>
                <p class="text-muted">Paiement sécurisé avec vos moyens préférés</p>
            </div>
            
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="payment-methods d-flex justify-content-center align-items-center gap-4 flex-wrap">
                        <div class="payment-method">
                            <div class="p-3 bg-white rounded shadow-sm text-center">
                                <i class="fas fa-mobile-alt text-primary fs-2 mb-2"></i>
                                <div><strong>Airtel Money</strong></div>
                            </div>
                        </div>
                        <div class="payment-method">
                            <div class="p-3 bg-white rounded shadow-sm text-center">
                                <i class="fas fa-money-bill-wave text-success fs-2 mb-2"></i>
                                <div><strong>Moov Money</strong></div>
                            </div>
                        </div>
                        <div class="payment-method">
                            <div class="p-3 bg-white rounded shadow-sm text-center">
                                <i class="fas fa-university text-warning fs-2 mb-2"></i>
                                <div><strong>Ecobank</strong></div>
                            </div>
                        </div>
                        <div class="payment-method">
                            <div class="p-3 bg-white rounded shadow-sm text-center">
                                <i class="fab fa-cc-visa text-info fs-2 mb-2"></i>
                                <div><strong>Visa</strong></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- FAQ Section -->
    <section class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="h3 fw-bold">Questions Fréquentes</h2>
            </div>
            
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="accordion" id="premiumFAQ">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
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
.premium-hero {
    position: relative;
    overflow: hidden;
}

.premium-hero::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="60" cy="70" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="40" r="1" fill="rgba(255,255,255,0.1)"/></svg>');
    opacity: 0.5;
}

.feature-card:hover {
    transform: translateY(-5px);
    transition: transform 0.3s ease;
}

.pricing-card {
    transition: transform 0.3s ease;
}

.pricing-card:hover {
    transform: scale(1.02);
}

.price-display {
    margin: 1rem 0;
}

.price-currency {
    font-size: 1rem;
    vertical-align: top;
    margin-top: 0.5rem;
}

.price-amount {
    font-size: 3rem;
    font-weight: bold;
    margin: 0 0.25rem;
}

.price-period {
    font-size: 1rem;
    color: #6c757d;
}

.payment-method {
    transition: transform 0.3s ease;
}

.payment-method:hover {
    transform: scale(1.05);
}

.accordion-button:not(.collapsed) {
    background-color: #f8f9fa;
    color: #0066CC;
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
        <div style="display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-crown" style="font-size: 20px; color: #FFD700;"></i>
            <div>
                <div><strong>🎵 Souscription Premium</strong></div>
                <small>${planNames[plan]}</small><br>
                <small>Redirection vers le paiement...</small>
            </div>
        </div>
    `;
    notification.style.cssText = `
        position: fixed; 
        top: 20px; 
        right: 20px; 
        background: #28a745; 
        color: white; 
        padding: 15px 20px; 
        border-radius: 8px; 
        z-index: 10000;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        max-width: 350px;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    `;
    document.body.appendChild(notification);
    setTimeout(() => notification.remove(), 5000);
    
    // Simuler la redirection vers le paiement
    setTimeout(() => {
        if (confirm(`Confirmer la souscription au plan ${planNames[plan]} ?`)) {
            alert('Redirection vers la page de paiement...\n(Fonctionnalité en cours de développement)');
        }
    }, 1500);
}
</script>

<?php include 'includes/footer.php'; ?>