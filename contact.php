<?php
/**
 * Page Contact - Tchadok Platform
 * Formulaire de contact et informations de contact
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'assets/images/placeholders.php';

$pageTitle = 'Nous Contacter';
$pageDescription = 'Contactez l\'équipe Tchadok. Nous sommes là pour vous aider avec vos questions, suggestions et partenariats.';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitizeInput($_POST['name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $subject = sanitizeInput($_POST['subject'] ?? '');
    $message = sanitizeInput($_POST['message'] ?? '');
    $type = sanitizeInput($_POST['type'] ?? 'general');
    
    // Validation
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error = 'Veuillez remplir tous les champs obligatoires.';
    } elseif (!validateEmail($email)) {
        $error = 'Adresse email invalide.';
    } elseif (strlen($message) < 10) {
        $error = 'Le message doit contenir au moins 10 caractères.';
    } else {
        // Ici on enverrait l'email en réalité
        $success = 'Votre message a été envoyé avec succès ! Nous vous répondrons dans les plus brefs délais.';
    }
}

include 'includes/header.php';
?>

<!-- Hero Contact Section -->
<section class="contact-hero">
    <div class="floating-music-notes" style="top: 12%; left: 8%;">♪</div>
    <div class="floating-music-notes" style="top: 70%; right: 10%; animation-delay: 2s;">♫</div>
    <div class="floating-music-notes" style="bottom: 25%; left: 15%; animation-delay: 4s;">♪</div>
    <div class="floating-music-notes" style="top: 35%; right: 25%; animation-delay: 1s;">♫</div>
    
    <div class="container">
        <div class="row align-items-center min-vh-100">
            <div class="col-lg-6">
                <div class="hero-content">
                    <div class="contact-badge">
                        <i class="fas fa-envelope"></i>
                        Contactez-Nous
                    </div>
                    <h1>Nous Sommes à Votre Écoute</h1>
                    <p>Une question, une suggestion, un partenariat ? Notre équipe dédiée est là pour vous accompagner dans votre expérience musicale tchadienne.</p>
                    
                    <div class="contact-highlights">
                        <div class="highlight-item">
                            <i class="fas fa-clock"></i>
                            <span>Réponse sous 24h</span>
                        </div>
                        <div class="highlight-item">
                            <i class="fas fa-users"></i>
                            <span>Équipe dédiée</span>
                        </div>
                        <div class="highlight-item">
                            <i class="fas fa-globe-africa"></i>
                            <span>Support multilingue</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="contact-visual">
                    <div class="contact-animation">
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
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Contact Methods Section -->
<section class="container my-5">
    <div class="section-header">
        <h2>Moyens de Contact</h2>
        <p class="text-muted">Choisissez le moyen qui vous convient le mieux</p>
    </div>
    
    <div class="contact-methods">
        <div class="row g-4">
            <div class="col-lg-4 col-md-6">
                <div class="contact-method-card">
                    <div class="method-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <h3>Adresse</h3>
                    <p>Avenue Charles de Gaulle<br>
                    Quartier Klemat<br>
                    N'Djamena, Tchad</p>
                    <button class="method-btn" onclick="openMap()">
                        <i class="fas fa-external-link-alt"></i>
                        Voir sur la carte
                    </button>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6">
                <div class="contact-method-card">
                    <div class="method-icon">
                        <i class="fas fa-phone"></i>
                    </div>
                    <h3>Téléphone</h3>
                    <p>Support client<br>
                    <strong>+235 66 12 34 56</strong><br>
                    Lun-Ven: 8h00 - 18h00</p>
                    <button class="method-btn" onclick="callUs()">
                        <i class="fas fa-phone"></i>
                        Appeler maintenant
                    </button>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6">
                <div class="contact-method-card">
                    <div class="method-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <h3>Email</h3>
                    <p>Contact général<br>
                    <strong>contact@tchadok.td</strong><br>
                    Réponse sous 24h ouvrées</p>
                    <button class="method-btn" onclick="sendEmail()">
                        <i class="fas fa-envelope"></i>
                        Envoyer un email
                    </button>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Contact Form Section -->
<section class="container my-5">
    <div class="section-header">
        <h2>Envoyez-nous un Message</h2>
        <p class="text-muted">Remplissez le formulaire ci-dessous et nous vous répondrons rapidement</p>
    </div>
    
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="contact-form-container">
                <?php if ($success): ?>
                <div class="alert alert-success" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $success; ?>
                </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo $error; ?>
                </div>
                <?php endif; ?>
                
                <form method="POST" class="contact-form" novalidate>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="name">Nom complet *</label>
                                <input type="text" 
                                       class="form-control-contact" 
                                       id="name" 
                                       name="name" 
                                       value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                                       required>
                                <div class="form-icon">
                                    <i class="fas fa-user"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="email">Adresse email *</label>
                                <input type="email" 
                                       class="form-control-contact" 
                                       id="email" 
                                       name="email" 
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                       required>
                                <div class="form-icon">
                                    <i class="fas fa-envelope"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="type">Type de demande</label>
                        <select class="form-control-contact" id="type" name="type">
                            <option value="general" <?php echo ($_POST['type'] ?? '') === 'general' ? 'selected' : ''; ?>>Question générale</option>
                            <option value="support" <?php echo ($_POST['type'] ?? '') === 'support' ? 'selected' : ''; ?>>Support technique</option>
                            <option value="partnership" <?php echo ($_POST['type'] ?? '') === 'partnership' ? 'selected' : ''; ?>>Partenariat</option>
                            <option value="artist" <?php echo ($_POST['type'] ?? '') === 'artist' ? 'selected' : ''; ?>>Espace artiste</option>
                            <option value="press" <?php echo ($_POST['type'] ?? '') === 'press' ? 'selected' : ''; ?>>Relations presse</option>
                            <option value="other" <?php echo ($_POST['type'] ?? '') === 'other' ? 'selected' : ''; ?>>Autre</option>
                        </select>
                        <div class="form-icon">
                            <i class="fas fa-list"></i>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="subject">Sujet *</label>
                        <input type="text" 
                               class="form-control-contact" 
                               id="subject" 
                               name="subject" 
                               value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>"
                               placeholder="Résumez votre demande en quelques mots"
                               required>
                        <div class="form-icon">
                            <i class="fas fa-tag"></i>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="message">Message *</label>
                        <textarea class="form-control-contact" 
                                  id="message" 
                                  name="message" 
                                  rows="6" 
                                  placeholder="Décrivez votre demande en détail..."
                                  required><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                        <div class="form-icon">
                            <i class="fas fa-comment"></i>
                        </div>
                        <div class="character-counter">
                            <span id="charCount">0</span>/1000 caractères
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary-custom btn-lg">
                            <i class="fas fa-paper-plane me-2"></i>
                            Envoyer le message
                        </button>
                        <button type="reset" class="btn btn-secondary-custom btn-lg">
                            <i class="fas fa-undo me-2"></i>
                            Effacer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<!-- Office Hours & Team -->
<section class="office-info-section">
    <div class="container">
        <div class="row">
            <div class="col-lg-6">
                <div class="office-hours">
                    <h3>Heures d'Ouverture</h3>
                    <div class="hours-list">
                        <div class="hour-item">
                            <span class="day">Lundi - Vendredi</span>
                            <span class="time">8h00 - 18h00</span>
                        </div>
                        <div class="hour-item">
                            <span class="day">Samedi</span>
                            <span class="time">9h00 - 15h00</span>
                        </div>
                        <div class="hour-item">
                            <span class="day">Dimanche</span>
                            <span class="time">Fermé</span>
                        </div>
                    </div>
                    
                    <div class="emergency-contact">
                        <h4>Contact d'urgence</h4>
                        <p>Pour les problèmes techniques critiques :</p>
                        <a href="tel:+23566123456" class="emergency-phone">
                            <i class="fas fa-phone"></i>
                            +235 66 12 34 56
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="team-info">
                    <h3>Notre Équipe</h3>
                    <div class="team-members">
                        <div class="team-member">
                            <div class="member-avatar">
                                <?php echo createAvatarPlaceholder('Abakar Mahamat', '#0066CC'); ?>
                            </div>
                            <div class="member-info">
                                <h4>Abakar Moussa</h4>
                                <p>Directeur Général</p>
                                <a href="mailto:abakar@tchadok.td">abakar@tchadok.td</a>
                            </div>
                        </div>
                        
                        <div class="team-member">
                            <div class="member-avatar">
                                <?php echo createAvatarPlaceholder('Fatima Hassan', '#FFD700'); ?>
                            </div>
                            <div class="member-info">
                                <h4>Fatima Hassan</h4>
                                <p>Responsable Support</p>
                                <a href="mailto:support@tchadok.td">support@tchadok.td</a>
                            </div>
                        </div>
                        
                        <div class="team-member">
                            <div class="member-avatar">
                                <?php echo createAvatarPlaceholder('Moussa Ngarlejy', '#228B22'); ?>
                            </div>
                            <div class="member-info">
                                <h4>Mahamat Nour</h4>
                                <p>Partenariats Artistes</p>
                                <a href="mailto:artistes@tchadok.td">artistes@tchadok.td</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
/* Contact Page Styles */
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
.contact-hero {
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

.contact-badge {
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

.contact-highlights {
    display: flex;
    gap: 2rem;
    flex-wrap: wrap;
}

.highlight-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: white;
    background: rgba(255, 255, 255, 0.1);
    padding: 0.75rem 1rem;
    border-radius: 15px;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.highlight-item i {
    color: var(--jaune-solaire);
    font-size: 1.2rem;
}

/* Contact Animation */
.contact-visual {
    display: flex;
    justify-content: center;
    align-items: center;
    height: 500px;
}

.contact-animation {
    position: relative;
    width: 300px;
    height: 300px;
}

.message-bubble {
    position: absolute;
    background: white;
    border-radius: 20px;
    padding: 1rem 1.5rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    animation: bubbleFloat 4s ease-in-out infinite;
}

.bubble-1 {
    top: 20px;
    left: 20px;
    animation-delay: 0s;
}

.bubble-2 {
    top: 100px;
    right: 10px;
    animation-delay: 1s;
}

.bubble-3 {
    bottom: 40px;
    left: 40px;
    animation-delay: 2s;
}

@keyframes bubbleFloat {
    0%, 100% { transform: translateY(0) scale(1); }
    50% { transform: translateY(-10px) scale(1.05); }
}

.message-bubble i {
    color: var(--bleu-tchadien);
    font-size: 1.2rem;
}

.message-bubble span {
    font-weight: 600;
    color: var(--gris-harmattan);
}

.central-contact-icon {
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
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
    animation: centralPulse 3s ease-in-out infinite;
}

@keyframes centralPulse {
    0%, 100% { transform: translate(-50%, -50%) scale(1); }
    50% { transform: translate(-50%, -50%) scale(1.1); }
}

/* Section Headers */
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

/* Contact Method Cards */
.contact-method-card {
    background: white;
    border-radius: 20px;
    padding: 2rem;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    text-align: center;
    height: 100%;
    display: flex;
    flex-direction: column;
    position: relative;
    overflow: hidden;
}

.contact-method-card::before {
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

.contact-method-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
}

.contact-method-card:hover::before {
    opacity: 1;
}

.method-icon {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, var(--bleu-tchadien), #0052a3);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: white;
    margin: 0 auto 1.5rem;
    transition: all 0.3s ease;
}

.contact-method-card:hover .method-icon {
    background: linear-gradient(135deg, var(--jaune-solaire), #e6c200);
    color: var(--gris-harmattan);
    transform: scale(1.1);
}

.contact-method-card h3 {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--gris-harmattan);
    margin-bottom: 1rem;
}

.contact-method-card p {
    color: #6c757d;
    margin-bottom: 1.5rem;
    flex-grow: 1;
    line-height: 1.5;
}

.method-btn {
    background: var(--bleu-tchadien);
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 50px;
    font-weight: 600;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.method-btn:hover {
    background: var(--jaune-solaire);
    color: var(--gris-harmattan);
    transform: translateY(-2px);
}

/* Contact Form */
.contact-form-container {
    background: white;
    border-radius: 20px;
    padding: 3rem;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
    position: relative;
    overflow: hidden;
}

.contact-form-container::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 5px;
    background: linear-gradient(to right, var(--bleu-tchadien), var(--jaune-solaire));
}

.form-group {
    margin-bottom: 1.5rem;
    position: relative;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: var(--gris-harmattan);
}

.form-control-contact {
    width: 100%;
    padding: 1rem 1rem 1rem 3rem;
    border: 2px solid #e9ecef;
    border-radius: 15px;
    font-size: 1rem;
    transition: all 0.3s ease;
    background: rgba(255, 255, 255, 0.8);
}

.form-control-contact:focus {
    outline: none;
    border-color: var(--bleu-tchadien);
    box-shadow: 0 0 0 0.2rem rgba(0, 102, 204, 0.25);
    background: white;
}

.form-icon {
    position: absolute;
    top: 50%;
    left: 1rem;
    transform: translateY(-50%);
    color: var(--bleu-tchadien);
}

.form-group:has(label) .form-icon {
    top: calc(50% + 12px);
}

.character-counter {
    position: absolute;
    bottom: -20px;
    right: 0;
    font-size: 0.8rem;
    color: #6c757d;
}

.form-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    margin-top: 2rem;
}

/* Alert Styles */
.alert {
    border-radius: 15px;
    border: none;
    padding: 1rem 1.5rem;
    margin-bottom: 2rem;
    font-weight: 500;
}

.alert-success {
    background: linear-gradient(135deg, rgba(34, 139, 34, 0.1), rgba(34, 139, 34, 0.05));
    color: var(--vert-savane);
    border-left: 4px solid var(--vert-savane);
}

.alert-danger {
    background: linear-gradient(135deg, rgba(204, 51, 51, 0.1), rgba(204, 51, 51, 0.05));
    color: var(--rouge-terre);
    border-left: 4px solid var(--rouge-terre);
}

/* Office Info Section */
.office-info-section {
    background: var(--gris-clair);
    padding: 4rem 0;
    margin-top: 4rem;
}

.office-hours h3,
.team-info h3 {
    color: var(--gris-harmattan);
    font-weight: 700;
    margin-bottom: 1.5rem;
    font-size: 1.5rem;
}

.hours-list {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.hour-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px solid #e9ecef;
}

.hour-item:last-child {
    border-bottom: none;
}

.day {
    font-weight: 600;
    color: var(--gris-harmattan);
}

.time {
    color: var(--bleu-tchadien);
    font-weight: 600;
}

.emergency-contact {
    background: linear-gradient(135deg, rgba(204, 51, 51, 0.1), rgba(204, 51, 51, 0.05));
    border-radius: 15px;
    padding: 1.5rem;
    border-left: 4px solid var(--rouge-terre);
}

.emergency-contact h4 {
    color: var(--rouge-terre);
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.emergency-phone {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: var(--rouge-terre);
    color: white;
    text-decoration: none;
    padding: 0.75rem 1.5rem;
    border-radius: 25px;
    font-weight: 600;
    transition: all 0.3s ease;
    margin-top: 1rem;
}

.emergency-phone:hover {
    background: #b32d2d;
    color: white;
    transform: translateY(-2px);
}

/* Team Members */
.team-members {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.team-member {
    display: flex;
    align-items: center;
    gap: 1rem;
    background: white;
    padding: 1.5rem;
    border-radius: 15px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
}

.team-member:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
}

.member-avatar img {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    border: 3px solid var(--jaune-solaire);
}

.member-info h4 {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--gris-harmattan);
    margin-bottom: 0.25rem;
}

.member-info p {
    color: #6c757d;
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

.member-info a {
    color: var(--bleu-tchadien);
    text-decoration: none;
    font-size: 0.85rem;
    transition: color 0.3s ease;
}

.member-info a:hover {
    color: var(--jaune-solaire);
}

/* Button Styles */
.btn-primary-custom {
    background: linear-gradient(135deg, var(--bleu-tchadien), #0052a3);
    border: none;
    color: white;
    font-weight: 600;
    padding: 0.75rem 2rem;
    border-radius: 50px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(0, 102, 204, 0.3);
}

.btn-primary-custom:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 25px rgba(0, 102, 204, 0.4);
    color: white;
}

.btn-secondary-custom {
    background: transparent;
    border: 2px solid var(--jaune-solaire);
    color: var(--gris-harmattan);
    font-weight: 600;
    padding: 0.75rem 2rem;
    border-radius: 50px;
    transition: all 0.3s ease;
}

.btn-secondary-custom:hover {
    background: var(--jaune-solaire);
    color: var(--gris-harmattan);
    transform: translateY(-2px);
}

/* Mobile Responsiveness */
@media (max-width: 768px) {
    .hero-content h1 {
        font-size: 2.5rem;
    }
    
    .section-header h2 {
        font-size: 2rem;
    }
    
    .contact-highlights {
        flex-direction: column;
        gap: 1rem;
    }
    
    .floating-music-notes {
        font-size: 1.5rem;
    }
    
    .contact-form-container {
        padding: 2rem 1.5rem;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .team-member {
        flex-direction: column;
        text-align: center;
    }
}
</style>

<script>
// JavaScript for Contact Page
$(document).ready(function() {
    // Character counter for message textarea
    $('#message').on('input', function() {
        const length = $(this).val().length;
        $('#charCount').text(length);
        
        if (length > 900) {
            $('#charCount').css('color', '#CC3333');
        } else if (length > 700) {
            $('#charCount').css('color', '#FFD700');
        } else {
            $('#charCount').css('color', '#6c757d');
        }
    });

    // Form validation enhancement
    $('.form-control-contact').on('blur', function() {
        const $this = $(this);
        const value = $this.val().trim();
        
        if ($this.prop('required') && value === '') {
            $this.addClass('is-invalid');
        } else if ($this.attr('type') === 'email' && value !== '' && !isValidEmail(value)) {
            $this.addClass('is-invalid');
        } else {
            $this.removeClass('is-invalid').addClass('is-valid');
        }
    });

    // Auto-resize textarea
    $('#message').on('input', function() {
        this.style.height = 'auto';
        this.style.height = this.scrollHeight + 'px';
    });
});

// Contact Methods Functions
function openMap() {
    const address = "Avenue Charles de Gaulle, Quartier Klemat, N'Djamena, Tchad";
    const url = `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(address)}`;
    window.open(url, '_blank');
    showNotification('🗺️ Ouverture de la carte...');
}

function callUs() {
    window.location.href = 'tel:+23566123456';
    showNotification('📞 Appel en cours...');
}

function sendEmail() {
    window.location.href = 'mailto:contact@tchadok.td?subject=Contact depuis le site web';
    showNotification('📧 Ouverture de votre client email...');
}

// Email validation
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
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