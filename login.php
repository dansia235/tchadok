<?php
/**
 * Page de connexion - Tchadok Platform
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';

$pageTitle = 'Connexion';
$pageDescription = 'Connectez-vous à votre compte Tchadok pour accéder à votre musique préférée.';

// Redirection si déjà connecté
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
        // Simulation de connexion pour les comptes de démo
        if ($email === 'demo@tchadok.td' && $password === 'demo123') {
            // Connexion réussie pour le compte démo
            $_SESSION['user_id'] = 1;
            $_SESSION['user_type'] = USER_TYPE_FAN;
            $_SESSION['first_name'] = 'Utilisateur';
            $_SESSION['last_name'] = 'Démo';
            $_SESSION['email'] = $email;
            $_SESSION['premium_status'] = false;
            
            setFlashMessage(FLASH_SUCCESS, 'Connexion réussie ! Bienvenue sur Tchadok');
            redirect(SITE_URL . '/');
        } else {
            $error = 'Email ou mot de passe incorrect. Utilisez demo@tchadok.td / demo123 pour tester.';
        }
    }
}

include 'includes/header.php';
?>

<!-- Login Section with gradient background -->
<section class="login-section">
    <div class="floating-music-notes" style="top: 15%; left: 8%;">♪</div>
    <div class="floating-music-notes" style="top: 70%; right: 12%; animation-delay: 3s;">♫</div>
    <div class="floating-music-notes" style="bottom: 20%; left: 15%; animation-delay: 1.5s;">♪</div>
    
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-5 col-md-7">
                <div class="login-card">
                    <div class="login-header text-center">
                        <svg class="login-logo" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
                            <circle cx="50" cy="50" r="45" fill="#0066CC"/>
                            <path d="M30 45 L30 55 L40 60 L40 40 Z M45 35 L45 65 L55 70 L55 30 Z M60 40 L60 60 L70 55 L70 45 Z" fill="#FFD700"/>
                        </svg>
                        <h2 class="mb-0">Connexion</h2>
                        <p class="text-muted">Accédez à votre univers musical tchadien</p>
                    </div>
                
                    <div class="login-body">
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
                        <div class="form-group mb-3">
                            <label for="email" class="form-label">
                                <i class="fas fa-envelope me-2"></i>Email
                            </label>
                            <input type="email" 
                                   class="form-control-login" 
                                   id="email" 
                                   name="email" 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                   placeholder="votre@email.com" 
                                   required>
                            <div class="invalid-feedback">
                                Veuillez entrer un email valide.
                            </div>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="password" class="form-label">
                                <i class="fas fa-lock me-2"></i>Mot de passe
                            </label>
                            <div class="input-group">
                                <input type="password" 
                                       class="form-control-login" 
                                       id="password" 
                                       name="password" 
                                       placeholder="Votre mot de passe" 
                                       required>
                                <button type="button" class="btn btn-outline-secondary" onclick="togglePassword()">
                                    <i class="fas fa-eye" id="passwordToggle"></i>
                                </button>
                            </div>
                            <div class="invalid-feedback">
                                Veuillez entrer votre mot de passe.
                            </div>
                        </div>
                        
                        <div class="form-group mb-4">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="remember" name="remember">
                                <label class="form-check-label" for="remember">
                                    Se souvenir de moi
                                </label>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary-custom w-100 mb-3">
                            <i class="fas fa-sign-in-alt me-2"></i>
                            Se connecter
                        </button>
                        
                        <div class="text-center">
                            <a href="#" class="text-decoration-none">
                                Mot de passe oublié ?
                            </a>
                        </div>
                    </form>
                </div>
                
                    <div class="login-footer text-center">
                        <p class="mb-0">
                            Pas encore de compte ? 
                            <a href="<?php echo SITE_URL; ?>/register-new.php" class="login-link">
                                S'inscrire gratuitement
                            </a>
                        </p>
                    </div>
                </div>
                
                <!-- Connexion rapide pour test -->
                <div class="demo-card mt-4">
                    <div class="demo-body text-center">
                        <h6 class="mb-3">🎵 Connexion de démo</h6>
                        <div class="demo-info">
                            <small>
                                <strong>Email:</strong> demo@tchadok.td<br>
                                <strong>Mot de passe:</strong> demo123
                            </small>
                        </div>
                        <button class="btn btn-secondary-custom btn-sm mt-3" onclick="fillDemo()">
                            <i class="fas fa-user me-1"></i>Remplir automatiquement
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
function togglePassword() {
    const passwordField = document.getElementById('password');
    const toggleIcon = document.getElementById('passwordToggle');
    
    if (passwordField.type === 'password') {
        passwordField.type = 'text';
        toggleIcon.className = 'fas fa-eye-slash';
    } else {
        passwordField.type = 'password';
        toggleIcon.className = 'fas fa-eye';
    }
}

function fillDemo() {
    document.getElementById('email').value = 'demo@tchadok.td';
    document.getElementById('password').value = 'demo123';
}

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
/* Login page custom styles matching tchadok theme */
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

.login-section {
    background: linear-gradient(135deg, rgba(0, 102, 204, 0.8), rgba(255, 215, 0, 0.6)), 
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
    font-size: 2rem;
    color: rgba(255, 255, 255, 0.2);
    animation: float 6s ease-in-out infinite;
    z-index: 1;
}

@keyframes float {
    0%, 100% { transform: translateY(0) rotate(0deg); }
    50% { transform: translateY(-20px) rotate(10deg); }
}

.login-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    overflow: hidden;
    position: relative;
    z-index: 2;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.login-header {
    background: linear-gradient(135deg, var(--bleu-tchadien), #0052a3);
    color: white;
    padding: 2rem;
    position: relative;
}

.login-header::before {
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

.login-logo {
    width: 80px;
    height: 80px;
    margin-bottom: 1rem;
    animation: logoFloat 3s ease-in-out infinite;
}

@keyframes logoFloat {
    0%, 100% { transform: translateY(0) rotate(0deg); }
    50% { transform: translateY(-5px) rotate(2deg); }
}

.login-header h2 {
    font-family: 'Montserrat', sans-serif;
    font-weight: 900;
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
    text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
}

.login-header p {
    font-size: 1.1rem;
    opacity: 0.9;
}

.login-body {
    padding: 2.5rem;
}

.form-label {
    font-weight: 600;
    color: var(--gris-harmattan);
    margin-bottom: 0.5rem;
    font-size: 0.95rem;
}

.form-control-login {
    border: 2px solid #e9ecef;
    border-radius: 12px;
    padding: 0.75rem 1rem;
    font-size: 1rem;
    transition: all 0.3s ease;
    background: rgba(255, 255, 255, 0.9);
}

.form-control-login:focus {
    border-color: var(--bleu-tchadien);
    box-shadow: 0 0 0 0.2rem rgba(0, 102, 204, 0.25);
    background: white;
}

.input-group .btn {
    border-radius: 0 12px 12px 0;
    border: 2px solid #e9ecef;
    border-left: none;
    background: rgba(255, 255, 255, 0.9);
    color: var(--gris-harmattan);
    transition: all 0.3s ease;
}

.input-group .btn:hover {
    background: var(--bleu-tchadien);
    color: white;
    border-color: var(--bleu-tchadien);
}

.form-check-input:checked {
    background-color: var(--bleu-tchadien);
    border-color: var(--bleu-tchadien);
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

.login-footer {
    background: rgba(248, 249, 250, 0.8);
    padding: 1.5rem;
    border-top: 1px solid rgba(0, 0, 0, 0.1);
}

.login-link {
    color: var(--bleu-tchadien);
    font-weight: 700;
    text-decoration: none;
    transition: all 0.3s ease;
}

.login-link:hover {
    color: var(--jaune-solaire);
    text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.1);
}

.demo-card {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.3);
    position: relative;
    z-index: 2;
}

.demo-body {
    padding: 1.5rem;
}

.demo-body h6 {
    color: var(--gris-harmattan);
    font-weight: 600;
    font-size: 1.1rem;
}

.demo-info {
    background: rgba(0, 102, 204, 0.1);
    border: 1px solid rgba(0, 102, 204, 0.2);
    border-radius: 10px;
    padding: 1rem;
    margin: 1rem 0;
}

.btn-secondary-custom {
    background: transparent;
    border: 2px solid var(--jaune-solaire);
    color: var(--gris-harmattan);
    font-weight: 600;
    padding: 0.5rem 1rem;
    border-radius: 12px;
    transition: all 0.3s ease;
}

.btn-secondary-custom:hover {
    background: var(--jaune-solaire);
    color: var(--gris-harmattan);
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(255, 215, 0, 0.4);
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

/* Mobile responsiveness */
@media (max-width: 768px) {
    .login-section {
        padding: 1rem 0;
    }
    
    .login-header h2 {
        font-size: 2rem;
    }
    
    .login-logo {
        width: 60px;
        height: 60px;
    }
    
    .login-body {
        padding: 1.5rem;
    }
    
    .floating-music-notes {
        font-size: 1.5rem;
    }
}
</style>

<?php include 'includes/footer.php'; ?>