<?php
/**
 * Page d'inscription - Tchadok Platform
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';

$pageTitle = 'Inscription';
$pageDescription = 'Rejoignez la communauté Tchadok et découvrez la musique tchadienne.';

// Redirection si déjà connecté
if (isLoggedIn()) {
    redirect(SITE_URL . '/');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = sanitizeInput($_POST['first_name'] ?? '');
    $lastName = sanitizeInput($_POST['last_name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $userType = sanitizeInput($_POST['user_type'] ?? USER_TYPE_FAN);
    $terms = isset($_POST['terms']);
    
    // Validation
    if (empty($firstName) || empty($lastName) || empty($email) || empty($password)) {
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
        $success = 'Inscription simulée réussie ! Utilisez login.php pour vous connecter avec demo@tchadok.td / demo123';
    }
}

include 'includes/header.php';
?>

<!-- Register Section with gradient background -->
<section class="register-section">
    <div class="floating-music-notes" style="top: 12%; left: 6%;">♪</div>
    <div class="floating-music-notes" style="top: 75%; right: 8%; animation-delay: 3s;">♫</div>
    <div class="floating-music-notes" style="bottom: 18%; left: 12%; animation-delay: 1.5s;">♪</div>
    <div class="floating-music-notes" style="top: 35%; right: 25%; animation-delay: 4s;">♫</div>
    <div class="floating-music-notes" style="bottom: 45%; right: 18%; animation-delay: 2.5s;">♪</div>
    
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-md-10">
                <div class="register-card">
                    <div class="register-header text-center">
                        <svg class="register-logo" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
                            <circle cx="50" cy="50" r="45" fill="#0066CC"/>
                            <path d="M30 45 L30 55 L40 60 L40 40 Z M45 35 L45 65 L55 70 L55 30 Z M60 40 L60 60 L70 55 L70 45 Z" fill="#FFD700"/>
                        </svg>
                        <h2 class="mb-0">Rejoignez Tchadok</h2>
                        <p class="text-muted">Découvrez l'univers musical tchadien</p>
                    </div>
                
                    <div class="register-body">
                    <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo $error; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo $success; ?>
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST" class="needs-validation" novalidate>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="first_name" class="form-label">
                                        <i class="fas fa-user me-2"></i>Prénom *
                                    </label>
                                    <input type="text" 
                                           class="form-control-register" 
                                           id="first_name" 
                                           name="first_name" 
                                           value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>"
                                           placeholder="Votre prénom" 
                                           required>
                                    <div class="invalid-feedback">
                                        Veuillez entrer votre prénom.
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="last_name" class="form-label">
                                        <i class="fas fa-user me-2"></i>Nom *
                                    </label>
                                    <input type="text" 
                                           class="form-control-register" 
                                           id="last_name" 
                                           name="last_name" 
                                           value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>"
                                           placeholder="Votre nom" 
                                           required>
                                    <div class="invalid-feedback">
                                        Veuillez entrer votre nom.
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="email" class="form-label">
                                <i class="fas fa-envelope me-2"></i>Email *
                            </label>
                            <input type="email" 
                                   class="form-control-register" 
                                   id="email" 
                                   name="email" 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                   placeholder="votre@email.com" 
                                   required>
                            <div class="invalid-feedback">
                                Veuillez entrer un email valide.
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="password" class="form-label">
                                        <i class="fas fa-lock me-2"></i>Mot de passe *
                                    </label>
                                    <input type="password" 
                                           class="form-control-register" 
                                           id="password" 
                                           name="password" 
                                           placeholder="Minimum 8 caractères" 
                                           minlength="8"
                                           required>
                                    <div class="invalid-feedback">
                                        Le mot de passe doit contenir au moins 8 caractères.
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="confirm_password" class="form-label">
                                        <i class="fas fa-lock me-2"></i>Confirmer *
                                    </label>
                                    <input type="password" 
                                           class="form-control-register" 
                                           id="confirm_password" 
                                           name="confirm_password" 
                                           placeholder="Répétez le mot de passe" 
                                           required>
                                    <div class="invalid-feedback">
                                        Les mots de passe ne correspondent pas.
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group mb-4">
                            <label class="form-label">
                                <i class="fas fa-users me-2"></i>Type de compte
                            </label>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-check card p-3">
                                        <input class="form-check-input" type="radio" name="user_type" 
                                               id="fan" value="<?php echo USER_TYPE_FAN; ?>" checked>
                                        <label class="form-check-label w-100" for="fan">
                                            <div class="d-flex align-items-center">
                                                <img src="<?php echo SITE_URL; ?>/assets/images/icon-heart.svg" 
                                                     alt="Fan" style="width: 24px; height: 24px;" class="me-2">
                                                <div>
                                                    <strong>Mélomane</strong>
                                                    <br><small class="text-muted">Écouter et acheter de la musique</small>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-check card p-3">
                                        <input class="form-check-input" type="radio" name="user_type" 
                                               id="artist" value="<?php echo USER_TYPE_ARTIST; ?>">
                                        <label class="form-check-label w-100" for="artist">
                                            <div class="d-flex align-items-center">
                                                <img src="<?php echo SITE_URL; ?>/assets/images/icon-music.svg" 
                                                     alt="Artiste" style="width: 24px; height: 24px;" class="me-2">
                                                <div>
                                                    <strong>Artiste</strong>
                                                    <br><small class="text-muted">Partager et vendre ma musique</small>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group mb-4">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                                <label class="form-check-label" for="terms">
                                    J'accepte les <a href="#" class="text-decoration-none">conditions d'utilisation</a> 
                                    et la <a href="#" class="text-decoration-none">politique de confidentialité</a> *
                                </label>
                                <div class="invalid-feedback">
                                    Vous devez accepter les conditions d'utilisation.
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary-custom w-100 mb-3">
                            <i class="fas fa-user-plus me-2"></i>
                            Créer mon compte
                        </button>
                    </form>
                </div>
                
                    <div class="register-footer text-center">
                        <p class="mb-0">
                            Déjà un compte ? 
                            <a href="<?php echo SITE_URL; ?>/login.php" class="register-link">
                                Se connecter
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
// Validation des mots de passe
document.getElementById('confirm_password').addEventListener('input', function() {
    const password = document.getElementById('password').value;
    const confirmPassword = this.value;
    
    if (password !== confirmPassword) {
        this.setCustomValidity('Les mots de passe ne correspondent pas');
    } else {
        this.setCustomValidity('');
    }
});

// Validation Bootstrap
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();
</script>

<style>
/* Register page custom styles matching tchadok theme */
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

.register-section {
    background: linear-gradient(135deg, rgba(0, 102, 204, 0.9), rgba(255, 215, 0, 0.7)),
                url('data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"%3E%3Cpath fill="%230066CC" fill-opacity="0.1" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,122.7C672,117,768,139,864,133.3C960,128,1056,96,1152,90.7C1248,85,1344,107,1392,117.3L1440,128L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"%3E%3C/path%3E%3C/svg%3E');
    background-size: cover;
    background-position: center;
    min-height: 100vh;
    display: flex;
    align-items: center;
    position: relative;
    overflow: hidden;
    padding: 2rem 0;
}

.floating-music-notes {
    position: absolute;
    font-size: 2.5rem;
    color: rgba(255, 255, 255, 0.15);
    animation: float 8s ease-in-out infinite;
    z-index: 1;
}

@keyframes float {
    0%, 100% { transform: translateY(0) rotate(0deg); }
    50% { transform: translateY(-30px) rotate(15deg); }
}

.register-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    overflow: hidden;
    position: relative;
    z-index: 2;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.register-header {
    background: linear-gradient(135deg, var(--bleu-tchadien), #0052a3);
    color: white;
    padding: 2rem;
    position: relative;
}

.register-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255, 215, 0, 0.1) 0%, transparent 70%);
    animation: pulse 4s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(0.8); opacity: 0.5; }
    50% { transform: scale(1.2); opacity: 0.8; }
}

.register-logo {
    width: 80px;
    height: 80px;
    margin-bottom: 1rem;
    animation: logoFloat 3s ease-in-out infinite, fadeInUp 1s ease;
}

@keyframes logoFloat {
    0%, 100% { transform: translateY(0) rotate(0deg); }
    50% { transform: translateY(-5px) rotate(2deg); }
}

.register-header h2 {
    font-family: 'Montserrat', sans-serif;
    font-weight: 900;
    font-size: 2.5rem;
    color: white;
    margin-bottom: 0.5rem;
    text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
    animation: fadeInUp 1s ease;
}

.register-header p {
    font-size: 1.1rem;
    color: white;
    opacity: 0.9;
    animation: fadeInUp 1.2s ease;
}

.register-body {
    padding: 2.5rem;
}

.form-label {
    font-weight: 600;
    color: var(--gris-harmattan);
    margin-bottom: 0.5rem;
    font-size: 0.95rem;
}

.form-control-register {
    border: 2px solid #e9ecef;
    border-radius: 12px;
    padding: 0.75rem 1rem;
    font-size: 1rem;
    transition: all 0.3s ease;
    background: rgba(255, 255, 255, 0.9);
}

.form-control-register:focus {
    border-color: var(--bleu-tchadien);
    box-shadow: 0 0 0 0.2rem rgba(0, 102, 204, 0.25);
    background: white;
}

.form-check-input:checked {
    background-color: var(--bleu-tchadien);
    border-color: var(--bleu-tchadien);
}

.form-check.card {
    border: 2px solid #e9ecef;
    border-radius: 15px;
    transition: all 0.3s ease;
    cursor: pointer;
    position: relative;
    overflow: hidden;
}

.form-check.card::before {
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

.form-check.card:hover {
    border-color: var(--bleu-tchadien);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0, 102, 204, 0.2);
}

.form-check.card:hover::before {
    opacity: 1;
}

.form-check-input[type="radio"]:checked + .form-check-label .card,
.form-check.card:has(.form-check-input:checked) {
    border-color: var(--jaune-solaire);
    background: linear-gradient(135deg, rgba(255, 215, 0, 0.1), rgba(0, 102, 204, 0.05));
    box-shadow: 0 5px 20px rgba(255, 215, 0, 0.3);
}

.btn-primary-custom {
    background: linear-gradient(135deg, var(--bleu-tchadien), #0052a3);
    border: none;
    color: white;
    font-weight: 600;
    padding: 0.75rem 1.5rem;
    border-radius: 12px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(0, 102, 204, 0.3);
    font-size: 1.1rem;
}

.btn-primary-custom:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 25px rgba(0, 102, 204, 0.4);
    color: white;
}

.register-footer {
    background: rgba(248, 249, 250, 0.8);
    padding: 1.5rem;
    border-top: 1px solid rgba(0, 0, 0, 0.1);
}

.register-link {
    color: var(--bleu-tchadien);
    font-weight: 700;
    text-decoration: none;
    transition: all 0.3s ease;
}

.register-link:hover {
    color: var(--jaune-solaire);
    text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.1);
}

.alert {
    border-radius: 12px;
    border: none;
    font-weight: 500;
}

.alert-danger {
    background: linear-gradient(135deg, rgba(204, 51, 51, 0.1), rgba(204, 51, 51, 0.05));
    color: var(--rouge-terre);
    border-left: 4px solid var(--rouge-terre);
}

.alert-success {
    background: linear-gradient(135deg, rgba(34, 139, 34, 0.1), rgba(34, 139, 34, 0.05));
    color: var(--vert-savane);
    border-left: 4px solid var(--vert-savane);
}

/* User type selection enhancement */
.form-check-label {
    cursor: pointer;
}

.form-check.card .form-check-label strong {
    color: var(--gris-harmattan);
    font-weight: 700;
    font-size: 1.1rem;
}

.form-check.card .form-check-label small {
    color: #6c757d;
    font-size: 0.9rem;
}

/* Terms checkbox styling */
.form-check-input[type="checkbox"] {
    width: 1.2rem;
    height: 1.2rem;
    border-radius: 6px;
    border: 2px solid #dee2e6;
    transition: all 0.3s ease;
}

.form-check-input[type="checkbox"]:checked {
    background: var(--vert-savane);
    border-color: var(--vert-savane);
}

.form-check-input[type="checkbox"]:focus {
    box-shadow: 0 0 0 0.2rem rgba(34, 139, 34, 0.25);
}

/* Links styling */
.register-footer a:not(.register-link),
.register-body a {
    color: var(--bleu-tchadien);
    transition: color 0.3s ease;
}

.register-footer a:not(.register-link):hover,
.register-body a:hover {
    color: var(--jaune-solaire);
}

/* Password strength indicator */
.password-strength {
    height: 4px;
    background: #e9ecef;
    border-radius: 2px;
    margin-top: 0.5rem;
    overflow: hidden;
}

.password-strength-bar {
    height: 100%;
    background: linear-gradient(to right, var(--rouge-terre), var(--jaune-solaire), var(--vert-savane));
    width: 0;
    transition: width 0.3s ease;
}

/* Mobile responsiveness */
@media (max-width: 768px) {
    .register-section {
        padding: 1rem 0;
    }
    
    .register-header h2 {
        font-size: 2rem;
    }
    
    .register-logo {
        width: 60px;
        height: 60px;
    }
    
    .register-body {
        padding: 1.5rem;
    }
    
    .floating-music-notes {
        font-size: 1.5rem;
    }
    
    .form-check.card {
        margin-bottom: 1rem;
    }
}

/* Animation for form validation */
.was-validated .form-control-register:invalid {
    border-color: var(--rouge-terre);
    box-shadow: 0 0 0 0.2rem rgba(204, 51, 51, 0.25);
}

.was-validated .form-control-register:valid {
    border-color: var(--vert-savane);
    box-shadow: 0 0 0 0.2rem rgba(34, 139, 34, 0.25);
}

/* Loading state for form submission */
.btn-primary-custom:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none;
}

.btn-primary-custom.loading::after {
    content: '';
    display: inline-block;
    width: 1rem;
    height: 1rem;
    border: 2px solid transparent;
    border-top: 2px solid white;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-left: 0.5rem;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
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
</style>

<?php include 'includes/footer.php'; ?>