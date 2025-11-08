<?php
/**
 * Page d'Aide - Tchadok Platform
 * Centre d'assistance et FAQ pour les utilisateurs
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';

$pageTitle = 'Centre d\'Aide';
$pageDescription = 'Trouvez rapidement des réponses à vos questions sur Tchadok. Guide d\'utilisation, FAQ et support technique.';

include 'includes/header.php';
?>

<!-- Hero Help Section -->
<section class="help-hero">
    <div class="floating-music-notes" style="top: 15%; left: 10%;">♪</div>
    <div class="floating-music-notes" style="top: 70%; right: 12%; animation-delay: 2s;">♫</div>
    <div class="floating-music-notes" style="bottom: 25%; left: 18%; animation-delay: 4s;">♪</div>
    
    <div class="container">
        <div class="row align-items-center min-vh-100">
            <div class="col-lg-6">
                <div class="hero-content">
                    <div class="help-badge">
                        <i class="fas fa-question-circle"></i>
                        Centre d'Aide
                    </div>
                    <h1>Comment Pouvons-Nous Vous Aider ?</h1>
                    <p>Bienvenue dans notre centre d'assistance. Trouvez rapidement des réponses à vos questions ou contactez notre équipe de support dédiée.</p>
                    
                    <div class="help-search">
                        <div class="search-container">
                            <i class="fas fa-search"></i>
                            <input type="text" placeholder="Rechercher dans l'aide..." id="helpSearch">
                            <button class="search-btn">
                                <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="quick-stats">
                        <div class="stat-item">
                            <div class="stat-number">200+</div>
                            <div class="stat-label">Articles d'aide</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number">24/7</div>
                            <div class="stat-label">Support disponible</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number">95%</div>
                            <div class="stat-label">Satisfaction client</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="help-visual">
                    <div class="help-icon-animation">
                        <div class="central-icon">
                            <i class="fas fa-headset"></i>
                        </div>
                        <div class="floating-icons">
                            <div class="help-icon" style="--angle: 0deg;">
                                <i class="fas fa-music"></i>
                            </div>
                            <div class="help-icon" style="--angle: 60deg;">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="help-icon" style="--angle: 120deg;">
                                <i class="fas fa-credit-card"></i>
                            </div>
                            <div class="help-icon" style="--angle: 180deg;">
                                <i class="fas fa-download"></i>
                            </div>
                            <div class="help-icon" style="--angle: 240deg;">
                                <i class="fas fa-settings"></i>
                            </div>
                            <div class="help-icon" style="--angle: 300deg;">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Quick Help Categories -->
<section class="container my-5">
    <div class="section-header">
        <h2>Catégories d'Aide</h2>
        <p class="text-muted">Trouvez rapidement l'aide dont vous avez besoin</p>
    </div>
    
    <div class="help-categories">
        <div class="row g-4">
            <div class="col-lg-4 col-md-6">
                <div class="help-category-card" data-category="account">
                    <div class="category-icon">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <h3>Compte & Profil</h3>
                    <p>Création de compte, paramètres de profil, connexion et sécurité</p>
                    <div class="category-count">15 articles</div>
                    <button class="category-btn">
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6">
                <div class="help-category-card" data-category="music">
                    <div class="category-icon">
                        <i class="fas fa-music"></i>
                    </div>
                    <h3>Musique & Écoute</h3>
                    <p>Lecture de musique, playlists, téléchargements et qualité audio</p>
                    <div class="category-count">25 articles</div>
                    <button class="category-btn">
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6">
                <div class="help-category-card" data-category="payment">
                    <div class="category-icon">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <h3>Paiements & Premium</h3>
                    <p>Abonnements, méthodes de paiement, factures et remboursements</p>
                    <div class="category-count">12 articles</div>
                    <button class="category-btn">
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6">
                <div class="help-category-card" data-category="artists">
                    <div class="category-icon">
                        <i class="fas fa-microphone"></i>
                    </div>
                    <h3>Espace Artiste</h3>
                    <p>Publier de la musique, gestion des droits, statistiques et revenus</p>
                    <div class="category-count">18 articles</div>
                    <button class="category-btn">
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6">
                <div class="help-category-card" data-category="mobile">
                    <div class="category-icon">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <h3>Applications Mobiles</h3>
                    <p>Installation, utilisation hors ligne, notifications et synchronisation</p>
                    <div class="category-count">10 articles</div>
                    <button class="category-btn">
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6">
                <div class="help-category-card" data-category="technical">
                    <div class="category-icon">
                        <i class="fas fa-cog"></i>
                    </div>
                    <h3>Support Technique</h3>
                    <p>Problèmes de connexion, bugs, compatibilité et dépannage</p>
                    <div class="category-count">20 articles</div>
                    <button class="category-btn">
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FAQ Section -->
<section class="container my-5">
    <div class="section-header">
        <h2>Questions Fréquemment Posées</h2>
        <p class="text-muted">Les réponses aux questions les plus courantes</p>
    </div>
    
    <div class="faq-container">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="faq-accordion">
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFAQ(this)">
                            <h4>Comment créer un compte sur Tchadok ?</h4>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>Pour créer un compte sur Tchadok, cliquez sur "S'inscrire" en haut de la page. Choisissez votre type de compte (Mélomane ou Artiste), remplissez vos informations personnelles et validez votre email. C'est gratuit et rapide !</p>
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFAQ(this)">
                            <h4>Comment écouter de la musique gratuitement ?</h4>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>Tchadok propose un accès gratuit à des milliers de titres tchadiens. Créez simplement un compte gratuit pour commencer à écouter. Avec l'abonnement Premium, profitez d'une qualité audio supérieure et de l'écoute hors ligne.</p>
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFAQ(this)">
                            <h4>Quels sont les avantages de l'abonnement Premium ?</h4>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>L'abonnement Premium vous offre : écoute illimitée sans publicité, qualité audio HD, téléchargement pour écoute hors ligne, accès prioritaire aux nouveautés et aux concerts en direct, et support client prioritaire.</p>
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFAQ(this)">
                            <h4>Comment publier ma musique en tant qu'artiste ?</h4>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>Créez un compte Artiste, complétez votre profil avec vos informations artistiques, puis utilisez notre outil de publication pour télécharger vos titres. Nous vérifions la qualité et publions généralement sous 24-48h.</p>
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFAQ(this)">
                            <h4>Quelles méthodes de paiement acceptez-vous ?</h4>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>Nous acceptons les cartes bancaires (Visa, Mastercard), les virements bancaires, et les solutions de paiement mobile populaires au Tchad comme Airtel Money et Moov Money.</p>
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFAQ(this)">
                            <h4>Puis-je utiliser Tchadok sur mon téléphone ?</h4>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>Oui ! Tchadok est optimisé pour mobile et nous travaillons sur nos applications iOS et Android. En attendant, utilisez notre site web responsive sur votre navigateur mobile pour une expérience optimale.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Contact Support -->
<section class="support-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <div class="support-content">
                    <h3>Besoin d'Aide Personnalisée ?</h3>
                    <p>Notre équipe de support est là pour vous accompagner. Contactez-nous pour une assistance rapide et personnalisée.</p>
                    
                    <div class="support-methods">
                        <div class="support-method">
                            <div class="method-icon">
                                <i class="fas fa-comments"></i>
                            </div>
                            <div class="method-content">
                                <h4>Chat en Direct</h4>
                                <p>Réponse immédiate de 8h à 20h</p>
                                <button class="btn btn-primary-custom btn-sm" onclick="openLiveChat()">
                                    Démarrer le Chat
                                </button>
                            </div>
                        </div>
                        
                        <div class="support-method">
                            <div class="method-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="method-content">
                                <h4>Email</h4>
                                <p>Réponse sous 24h ouvrées</p>
                                <a href="mailto:support@tchadok.td" class="btn btn-secondary-custom btn-sm">
                                    Envoyer un Email
                                </a>
                            </div>
                        </div>
                        
                        <div class="support-method">
                            <div class="method-icon">
                                <i class="fas fa-phone"></i>
                            </div>
                            <div class="method-content">
                                <h4>Téléphone</h4>
                                <p>Du lundi au vendredi, 8h-18h</p>
                                <a href="tel:+23566123456" class="btn btn-outline-primary btn-sm">
                                    +235 66 12 34 56
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="support-visual">
                    <div class="support-illustration">
                        <i class="fas fa-headset"></i>
                        <div class="pulse-ring"></div>
                        <div class="pulse-ring pulse-ring-2"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
/* Help Page Styles */
:root {
    --bleu-tchadien: #0066CC;
    --jaune-solaire: #FFD700;
    --rouge-terre: #CC3333;
    --vert-savane: #228B22;
    --blanc-coton: #FFFFFF;
    --gris-harmattan: #2C3E50;
    --gris-clair: #f8f9fa;
    --noir-profond: #1a1a1a;
}

/* Hero Section */
.help-hero {
    background: linear-gradient(135deg, rgba(0, 102, 204, 0.9), rgba(255, 215, 0, 0.7)), 
                url('data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"%3E%3Cpath fill="%230066CC" fill-opacity="0.1" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,122.7C672,117,768,139,864,133.3C960,128,1056,96,1152,90.7C1248,85,1344,107,1392,117.3L1440,128L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"%3E%3C/path%3E%3C/svg%3E');
    background-size: cover;
    background-position: center;
    position: relative;
    overflow: hidden;
}

.floating-music-notes {
    position: absolute;
    font-size: 2rem;
    color: rgba(255, 255, 255, 0.2);
    animation: float 6s ease-in-out infinite;
}

@keyframes float {
    0%, 100% { transform: translateY(0) rotate(0deg); }
    50% { transform: translateY(-20px) rotate(10deg); }
}

.help-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(255, 215, 0, 0.9);
    color: var(--gris-harmattan);
    padding: 0.75rem 1.5rem;
    border-radius: 25px;
    font-weight: 700;
    margin-bottom: 2rem;
}

.hero-content h1 {
    font-size: 4rem;
    font-weight: 900;
    color: white;
    text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
    margin-bottom: 1.5rem;
    line-height: 1.2;
}

.hero-content p {
    font-size: 1.3rem;
    color: white;
    margin-bottom: 2rem;
    opacity: 0.9;
}

.help-search {
    margin-bottom: 2rem;
}

.search-container {
    position: relative;
    max-width: 500px;
    background: rgba(255, 255, 255, 0.95);
    border-radius: 50px;
    padding: 0.5rem;
    display: flex;
    align-items: center;
    backdrop-filter: blur(10px);
}

.search-container i {
    color: var(--gris-harmattan);
    margin-left: 1rem;
}

.search-container input {
    border: none;
    background: transparent;
    flex: 1;
    padding: 0.75rem 1rem;
    font-size: 1rem;
    outline: none;
}

.search-btn {
    background: var(--bleu-tchadien);
    color: white;
    border: none;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.search-btn:hover {
    background: #0052a3;
    transform: scale(1.05);
}

.quick-stats {
    display: flex;
    gap: 2rem;
    margin-top: 2rem;
}

.stat-item {
    text-align: center;
    color: white;
}

.stat-number {
    font-size: 2rem;
    font-weight: 900;
    color: var(--jaune-solaire);
    line-height: 1;
}

.stat-label {
    font-size: 0.9rem;
    opacity: 0.9;
    text-transform: uppercase;
    letter-spacing: 1px;
}

/* Help Visual Animation */
.help-visual {
    display: flex;
    justify-content: center;
    align-items: center;
    height: 500px;
}

.help-icon-animation {
    position: relative;
    width: 300px;
    height: 300px;
}

.central-icon {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 100px;
    height: 100px;
    background: var(--jaune-solaire);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    color: var(--gris-harmattan);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    animation: centralPulse 3s ease-in-out infinite;
}

@keyframes centralPulse {
    0%, 100% { transform: translate(-50%, -50%) scale(1); }
    50% { transform: translate(-50%, -50%) scale(1.05); }
}

.floating-icons {
    position: relative;
    width: 100%;
    height: 100%;
}

.help-icon {
    position: absolute;
    width: 60px;
    height: 60px;
    background: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: var(--bleu-tchadien);
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
    top: 50%;
    left: 50%;
    transform-origin: -120px 0;
    transform: translate(-50%, -50%) rotate(var(--angle)) translateX(120px) rotate(calc(-1 * var(--angle)));
    animation: iconOrbit 15s linear infinite;
}

@keyframes iconOrbit {
    from { transform: translate(-50%, -50%) rotate(var(--angle)) translateX(120px) rotate(calc(-1 * var(--angle))); }
    to { transform: translate(-50%, -50%) rotate(calc(var(--angle) + 360deg)) translateX(120px) rotate(calc(-1 * (var(--angle) + 360deg))); }
}

/* Help Categories */
.section-header {
    text-align: center;
    margin-bottom: 3rem;
}

.section-header h2 {
    font-size: 3rem;
    font-weight: 900;
    color: var(--gris-harmattan);
    margin-bottom: 1rem;
    position: relative;
    display: inline-block;
}

.section-header h2::after {
    content: '';
    position: absolute;
    bottom: -10px;
    left: 50%;
    transform: translateX(-50%);
    width: 80px;
    height: 4px;
    background: linear-gradient(to right, var(--bleu-tchadien), var(--jaune-solaire));
    border-radius: 2px;
}

.help-category-card {
    background: white;
    border-radius: 20px;
    padding: 2rem;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    cursor: pointer;
    position: relative;
    overflow: hidden;
    height: 100%;
    display: flex;
    flex-direction: column;
}

.help-category-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, rgba(0, 102, 204, 0.05), rgba(255, 215, 0, 0.05));
    opacity: 0;
    transition: opacity 0.3s ease;
}

.help-category-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
}

.help-category-card:hover::before {
    opacity: 1;
}

.category-icon {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, var(--bleu-tchadien), #0052a3);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: white;
    margin-bottom: 1.5rem;
    transition: all 0.3s ease;
}

.help-category-card:hover .category-icon {
    background: linear-gradient(135deg, var(--jaune-solaire), #e6c200);
    color: var(--gris-harmattan);
    transform: scale(1.1);
}

.help-category-card h3 {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--gris-harmattan);
    margin-bottom: 1rem;
}

.help-category-card p {
    color: #6c757d;
    margin-bottom: 1.5rem;
    flex-grow: 1;
    line-height: 1.5;
}

.category-count {
    background: rgba(0, 102, 204, 0.1);
    color: var(--bleu-tchadien);
    padding: 0.5rem 1rem;
    border-radius: 15px;
    font-weight: 600;
    font-size: 0.85rem;
    display: inline-block;
    margin-bottom: 1rem;
}

.category-btn {
    background: var(--bleu-tchadien);
    color: white;
    border: none;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    align-self: flex-end;
}

.category-btn:hover {
    background: var(--jaune-solaire);
    color: var(--gris-harmattan);
    transform: scale(1.1);
}

/* FAQ Section */
.faq-accordion {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
}

.faq-item {
    border-bottom: 1px solid #e9ecef;
}

.faq-item:last-child {
    border-bottom: none;
}

.faq-question {
    padding: 1.5rem 2rem;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.3s ease;
}

.faq-question:hover {
    background: rgba(0, 102, 204, 0.05);
}

.faq-question h4 {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--gris-harmattan);
    margin: 0;
}

.faq-question i {
    color: var(--bleu-tchadien);
    transition: transform 0.3s ease;
}

.faq-question.active i {
    transform: rotate(180deg);
}

.faq-answer {
    padding: 0 2rem;
    max-height: 0;
    overflow: hidden;
    transition: all 0.3s ease;
}

.faq-answer.active {
    padding: 0 2rem 1.5rem;
    max-height: 200px;
}

.faq-answer p {
    color: #6c757d;
    line-height: 1.6;
    margin: 0;
}

/* Support Section */
.support-section {
    background: linear-gradient(135deg, var(--gris-harmattan), #1a2332);
    color: white;
    padding: 5rem 0;
    margin-top: 4rem;
}

.support-content h3 {
    font-size: 2.5rem;
    font-weight: 900;
    margin-bottom: 1.5rem;
    color: white !important;
}

.support-content p {
    font-size: 1.2rem;
    margin-bottom: 2rem;
    opacity: 0.9;
    color: rgba(255, 255, 255, 0.9) !important;
}

.support-methods {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.support-method {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.5rem;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 15px;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    transition: all 0.3s ease;
}

.support-method:hover {
    background: rgba(255, 255, 255, 0.15);
    transform: translateX(10px);
}

.method-icon {
    width: 60px;
    height: 60px;
    background: var(--jaune-solaire);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: var(--gris-harmattan);
    flex-shrink: 0;
}

.method-content h4 {
    font-size: 1.2rem;
    margin-bottom: 0.5rem;
    color: white !important;
}

.method-content p {
    margin-bottom: 1rem;
    opacity: 0.8;
    color: rgba(255, 255, 255, 0.8) !important;
}

/* Support Visual */
.support-visual {
    display: flex;
    justify-content: center;
    align-items: center;
}

.support-illustration {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
}

.support-illustration .fas {
    font-size: 5rem;
    color: var(--jaune-solaire);
    z-index: 2;
    position: relative;
}

.pulse-ring {
    position: absolute;
    width: 200px;
    height: 200px;
    border: 3px solid rgba(255, 215, 0, 0.4);
    border-radius: 50%;
    animation: pulse 2s ease-in-out infinite;
}

.pulse-ring-2 {
    width: 250px;
    height: 250px;
    animation-delay: 1s;
}

@keyframes pulse {
    0% {
        transform: scale(0.8);
        opacity: 1;
    }
    100% {
        transform: scale(1.2);
        opacity: 0;
    }
}

/* Button Styles */
.btn-primary-custom {
    background: linear-gradient(135deg, var(--bleu-tchadien), #0052a3);
    border: none;
    color: white;
    font-weight: 600;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.btn-primary-custom:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0, 102, 204, 0.4);
    color: white;
}

.btn-secondary-custom {
    background: transparent;
    border: 2px solid var(--jaune-solaire);
    color: var(--jaune-solaire);
    font-weight: 600;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.btn-secondary-custom:hover {
    background: var(--jaune-solaire);
    color: var(--gris-harmattan);
}

/* Mobile Responsiveness */
@media (max-width: 768px) {
    .hero-content h1 {
        font-size: 2.5rem;
    }
    
    .section-header h2 {
        font-size: 2rem;
    }
    
    .quick-stats {
        gap: 1rem;
        justify-content: center;
    }
    
    .stat-number {
        font-size: 1.5rem;
    }
    
    .floating-music-notes {
        font-size: 1.5rem;
    }
    
    .support-content h3 {
        font-size: 2rem;
    }
    
    .support-method {
        flex-direction: column;
        text-align: center;
    }
    
    .method-content {
        text-align: center;
    }
}
</style>

<script>
// JavaScript for Help Page
$(document).ready(function() {
    // Search functionality
    $('#helpSearch').on('input', function() {
        const searchTerm = $(this).val().toLowerCase();
        // Simulate search results
        if (searchTerm.length > 2) {
            showNotification(`🔍 Recherche de "${searchTerm}" dans la base de connaissances...`);
        }
    });

    // Category click handlers
    $('.help-category-card').on('click', function() {
        const category = $(this).data('category');
        const categoryName = $(this).find('h3').text();
        showNotification(`📂 Ouverture de la catégorie: ${categoryName}`);
        // Ici on redirigerait vers la page de catégorie
    });
});

// FAQ Toggle Function
function toggleFAQ(element) {
    const question = $(element);
    const answer = question.next('.faq-answer');
    const icon = question.find('i');
    
    // Close all other FAQs
    $('.faq-question').not(question).removeClass('active');
    $('.faq-answer').not(answer).removeClass('active');
    
    // Toggle current FAQ
    question.toggleClass('active');
    answer.toggleClass('active');
}

// Support Functions
function openLiveChat() {
    showNotification('💬 Connexion au chat en direct...');
    // Ici on ouvrirait le widget de chat
    setTimeout(() => {
        showNotification('✅ Chat en direct connecté ! Un agent va vous répondre.');
    }, 2000);
}

// Notification function
function showNotification(message) {
    const notification = document.createElement('div');
    notification.innerHTML = message;
    notification.style.cssText = `
        position: fixed; 
        top: 20px; 
        right: 20px; 
        background: #0066CC; 
        color: white; 
        padding: 15px 20px; 
        border-radius: 8px; 
        z-index: 10000;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        max-width: 300px;
        animation: slideInRight 0.3s ease;
    `;
    document.body.appendChild(notification);
    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Animation styles
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOutRight {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
`;
document.head.appendChild(style);
</script>

<?php include 'includes/footer.php'; ?>