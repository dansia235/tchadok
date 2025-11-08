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
        // Utiliser le système d'authentification réel
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

include 'includes/header.php';
?>

<!-- Login Section with gradient background -->
<section class="login-section">
    <div class="floating-music-notes" style="top: 15%; left: 8%;">♪</div>
    <div class="floating-music-notes" style="top: 70%; right: 12%; animation-delay: 3s;">♫</div>
    <div class="floating-music-notes" style="bottom: 20%; left: 15%; animation-delay: 1.5s;">♪</div>
    <div class="floating-music-notes" style="top: 40%; right: 25%; animation-delay: 4.5s;">♪</div>
    <div class="floating-music-notes" style="bottom: 45%; left: 22%; animation-delay: 2.5s;">♫</div>

    <div class="container py-5">
        <div class="row justify-content-center align-items-center">
            <div class="col-lg-10">
                <div class="row g-0 login-wrapper">
                    <!-- Left side - Branding & Info -->
                    <div class="col-lg-6 d-none d-lg-block">
                        <div class="login-branding">
                            <div class="branding-content">
                                <div class="brand-logo-large">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="120" height="120">
                                        <circle cx="50" cy="50" r="45" fill="#0066CC"/>
                                        <path d="M30 45 L30 55 L40 60 L40 40 Z M45 35 L45 65 L55 70 L55 30 Z M60 40 L60 60 L70 55 L70 45 Z" fill="#FFD700"/>
                                    </svg>
                                </div>
                                <h1 class="brand-title">Bienvenue sur Tchadok</h1>
                                <p class="brand-subtitle">La plateforme musicale tchadienne par excellence</p>

                                <div class="features-list">
                                    <div class="feature-item">
                                        <div class="feature-icon">
                                            <i class="fas fa-music"></i>
                                        </div>
                                        <div class="feature-text">
                                            <h6>Musique illimitée</h6>
                                            <p>Accédez à des milliers de titres tchadiens</p>
                                        </div>
                                    </div>

                                    <div class="feature-item">
                                        <div class="feature-icon">
                                            <i class="fas fa-broadcast-tower"></i>
                                        </div>
                                        <div class="feature-text">
                                            <h6>Radio en direct</h6>
                                            <p>Écoutez la radio 24/7 gratuitement</p>
                                        </div>
                                    </div>

                                    <div class="feature-item">
                                        <div class="feature-icon">
                                            <i class="fas fa-heart"></i>
                                        </div>
                                        <div class="feature-text">
                                            <h6>Playlists personnalisées</h6>
                                            <p>Créez vos propres collections musicales</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right side - Login Form -->
                    <div class="col-lg-6">
                        <div class="login-card">
                            <div class="login-header text-center">
                                <div class="login-logo-mobile d-lg-none">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="80" height="80">
                                        <circle cx="50" cy="50" r="45" fill="#0066CC"/>
                                        <path d="M30 45 L30 55 L40 60 L40 40 Z M45 35 L45 65 L55 70 L55 30 Z M60 40 L60 60 L70 55 L70 45 Z" fill="#FFD700"/>
                                    </svg>
                                </div>
                                <h2>Connexion</h2>
                                <p>Accédez à votre univers musical tchadien</p>
                            </div>

                            <div class="login-body">
                            <?php if ($error): ?>
                            <div class="alert alert-danger alert-modern">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?php echo $error; ?>
                            </div>
                            <?php endif; ?>

                            <?php if ($success): ?>
                            <div class="alert alert-success alert-modern">
                                <i class="fas fa-check-circle me-2"></i>
                                <?php echo $success; ?>
                            </div>
                            <?php endif; ?>

                            <form method="POST" class="needs-validation" novalidate>
                                <div class="form-group mb-4">
                                    <label for="email" class="form-label">
                                        <i class="fas fa-envelope me-2"></i>Adresse email
                                    </label>
                                    <div class="input-wrapper">
                                        <input type="email"
                                               class="form-control-login"
                                               id="email"
                                               name="email"
                                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                               placeholder="exemple@tchadok.td"
                                               required>
                                        <span class="input-icon"><i class="fas fa-check-circle"></i></span>
                                    </div>
                                    <div class="invalid-feedback">
                                        Veuillez entrer un email valide.
                                    </div>
                                </div>

                                <div class="form-group mb-4">
                                    <label for="password" class="form-label">
                                        <i class="fas fa-lock me-2"></i>Mot de passe
                                    </label>
                                    <div class="input-wrapper">
                                        <input type="password"
                                               class="form-control-login password-input"
                                               id="password"
                                               name="password"
                                               placeholder="Votre mot de passe"
                                               required>
                                        <button type="button" class="password-toggle" onclick="togglePassword()">
                                            <i class="fas fa-eye" id="passwordToggle"></i>
                                        </button>
                                    </div>
                                    <div class="invalid-feedback">
                                        Veuillez entrer votre mot de passe.
                                    </div>
                                </div>

                                <div class="form-group mb-4">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="remember" name="remember">
                                            <label class="form-check-label" for="remember">
                                                Se souvenir de moi
                                            </label>
                                        </div>
                                        <a href="#" class="forgot-link">
                                            Mot de passe oublié ?
                                        </a>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary-custom w-100 mb-3">
                                    <span class="btn-text">
                                        <i class="fas fa-sign-in-alt me-2"></i>
                                        Se connecter
                                    </span>
                                    <span class="btn-loader">
                                        <i class="fas fa-spinner fa-spin"></i>
                                    </span>
                                </button>

                                <div class="divider">
                                    <span>ou</span>
                                </div>

                                <div class="social-login">
                                    <button type="button" class="btn-social btn-social-google" onclick="showNotification('🚧 Connexion Google bientôt disponible')">
                                        <i class="fab fa-google me-2"></i>
                                        Google
                                    </button>
                                    <button type="button" class="btn-social btn-social-facebook" onclick="showNotification('🚧 Connexion Facebook bientôt disponible')">
                                        <i class="fab fa-facebook-f me-2"></i>
                                        Facebook
                                    </button>
                                </div>
                            </form>
                        </div>

                            <div class="login-footer text-center">
                                <p class="mb-0">
                                    Pas encore de compte ?
                                    <a href="<?php echo SITE_URL; ?>/register.php" class="login-link">
                                        Créer un compte gratuitement
                                    </a>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

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

/* Login Wrapper */
.login-wrapper {
    background: white;
    border-radius: 25px;
    overflow: hidden;
    box-shadow: 0 25px 80px rgba(0, 0, 0, 0.3);
    position: relative;
    z-index: 2;
}

/* Left Side - Branding */
.login-branding {
    background: linear-gradient(135deg, var(--bleu-tchadien), #0052a3);
    height: 100%;
    padding: 3rem;
    display: flex;
    align-items: center;
    position: relative;
    overflow: hidden;
}

.login-branding::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255, 215, 0, 0.15) 0%, transparent 70%);
    animation: pulse 4s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(0.8) rotate(0deg); opacity: 0.5; }
    50% { transform: scale(1.2) rotate(180deg); opacity: 0.8; }
}

.branding-content {
    position: relative;
    z-index: 2;
    color: white;
}

.brand-logo-large {
    margin-bottom: 2rem;
    animation: logoFloat 3s ease-in-out infinite;
}

@keyframes logoFloat {
    0%, 100% { transform: translateY(0) rotate(0deg); }
    50% { transform: translateY(-10px) rotate(5deg); }
}

.brand-title {
    font-size: 2.5rem;
    font-weight: 900;
    color: white;
    margin-bottom: 1rem;
    text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
    animation: fadeInUp 1s ease;
}

.brand-subtitle {
    font-size: 1.2rem;
    color: white;
    opacity: 0.9;
    margin-bottom: 3rem;
    animation: fadeInUp 1.2s ease;
}

.features-list {
    animation: fadeInUp 1.4s ease;
}

.feature-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 2rem;
    padding: 1rem;
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border-radius: 15px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    transition: all 0.3s ease;
}

.feature-item:hover {
    background: rgba(255, 255, 255, 0.15);
    transform: translateX(10px);
}

.feature-icon {
    width: 50px;
    height: 50px;
    background: var(--jaune-solaire);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: var(--gris-harmattan);
    flex-shrink: 0;
}

.feature-text h6 {
    font-size: 1rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
    color: white;
}

.feature-text p {
    margin: 0;
    font-size: 0.9rem;
    opacity: 0.9;
    color: white;
}

/* Right Side - Login Card */
.login-card {
    background: white;
    height: 100%;
    display: flex;
    flex-direction: column;
}

.login-header {
    padding: 2.5rem 2.5rem 1.5rem;
    background: linear-gradient(to bottom, rgba(0, 102, 204, 0.05), transparent);
}

.login-logo-mobile {
    margin-bottom: 1rem;
    animation: logoFloat 3s ease-in-out infinite;
}

.login-header h2 {
    font-family: 'Montserrat', sans-serif;
    font-weight: 900;
    font-size: 2.5rem;
    color: var(--gris-harmattan);
    margin-bottom: 0.5rem;
    animation: fadeInUp 1s ease;
}

.login-header p {
    font-size: 1.1rem;
    color: #6c757d;
    margin-bottom: 0;
    animation: fadeInUp 1.2s ease;
}

.login-body {
    padding: 2rem 2.5rem;
    flex: 1;
}

.form-label {
    font-weight: 600;
    color: var(--gris-harmattan);
    margin-bottom: 0.75rem;
    font-size: 0.95rem;
}

.input-wrapper {
    position: relative;
}

.form-control-login {
    width: 100%;
    border: 2px solid #e9ecef;
    border-radius: 12px;
    padding: 0.875rem 1rem;
    font-size: 1rem;
    transition: all 0.3s ease;
    background: white;
}

.form-control-login:focus {
    border-color: var(--bleu-tchadien);
    box-shadow: 0 0 0 0.2rem rgba(0, 102, 204, 0.15);
    outline: none;
}

.form-control-login:valid {
    border-color: var(--vert-savane);
}

.input-icon {
    position: absolute;
    right: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--vert-savane);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.form-control-login:valid ~ .input-icon {
    opacity: 1;
}

.password-input {
    padding-right: 3rem;
}

.password-toggle {
    position: absolute;
    right: 1rem;
    top: 50%;
    transform: translateY(-50%);
    background: transparent;
    border: none;
    color: #6c757d;
    cursor: pointer;
    transition: all 0.3s ease;
    padding: 0.5rem;
}

.password-toggle:hover {
    color: var(--bleu-tchadien);
}

.form-check-input {
    border: 2px solid #e9ecef;
    border-radius: 6px;
    cursor: pointer;
}

.form-check-input:checked {
    background-color: var(--bleu-tchadien);
    border-color: var(--bleu-tchadien);
}

.form-check-label {
    cursor: pointer;
    user-select: none;
}

.forgot-link {
    color: var(--bleu-tchadien);
    text-decoration: none;
    font-size: 0.9rem;
    font-weight: 600;
    transition: all 0.3s ease;
}

.forgot-link:hover {
    color: var(--jaune-solaire);
}

.btn-primary-custom {
    background: linear-gradient(135deg, var(--bleu-tchadien), #0052a3);
    border: none;
    color: white;
    font-weight: 700;
    padding: 0.875rem 1.5rem;
    border-radius: 12px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(0, 102, 204, 0.3);
    font-size: 1.1rem;
    position: relative;
    overflow: hidden;
}

.btn-primary-custom::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transition: left 0.5s ease;
}

.btn-primary-custom:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 102, 204, 0.4);
    color: white;
}

.btn-primary-custom:hover::before {
    left: 100%;
}

.btn-loader {
    display: none;
}

.btn-primary-custom.loading .btn-text {
    display: none;
}

.btn-primary-custom.loading .btn-loader {
    display: inline-block;
}

.divider {
    text-align: center;
    position: relative;
    margin: 2rem 0;
}

.divider::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    height: 1px;
    background: #e9ecef;
}

.divider span {
    background: white;
    padding: 0 1rem;
    position: relative;
    color: #6c757d;
    font-size: 0.9rem;
    font-weight: 600;
}

.social-login {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.btn-social {
    border: 2px solid #e9ecef;
    background: white;
    padding: 0.75rem 1rem;
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.btn-social-google {
    color: #DB4437;
}

.btn-social-google:hover {
    background: #DB4437;
    color: white;
    border-color: #DB4437;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(219, 68, 55, 0.3);
}

.btn-social-facebook {
    color: #1877F2;
}

.btn-social-facebook:hover {
    background: #1877F2;
    color: white;
    border-color: #1877F2;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(24, 119, 242, 0.3);
}

.login-footer {
    background: rgba(248, 249, 250, 0.5);
    padding: 1.5rem 2.5rem;
    border-top: 1px solid rgba(0, 0, 0, 0.05);
}

.login-link {
    color: var(--bleu-tchadien);
    font-weight: 700;
    text-decoration: none;
    transition: all 0.3s ease;
    position: relative;
}

.login-link::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 0;
    width: 0;
    height: 2px;
    background: var(--jaune-solaire);
    transition: width 0.3s ease;
}

.login-link:hover {
    color: var(--jaune-solaire);
}

.login-link:hover::after {
    width: 100%;
}

/* Alerts */
.alert-modern {
    border-radius: 12px;
    border: none;
    font-weight: 500;
    padding: 1rem 1.25rem;
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
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

/* Mobile responsiveness */
@media (max-width: 991px) {
    .login-wrapper {
        border-radius: 20px;
    }

    .login-header h2 {
        font-size: 2rem;
    }

    .login-body {
        padding: 1.5rem;
    }

    .login-footer {
        padding: 1.5rem;
    }
}

@media (max-width: 768px) {
    .login-section {
        padding: 1rem 0;
    }

    .login-header {
        padding: 2rem 1.5rem 1rem;
    }

    .login-header h2 {
        font-size: 1.75rem;
    }

    .login-header p {
        font-size: 1rem;
    }

    .login-body {
        padding: 1rem 1.5rem;
    }

    .social-login {
        grid-template-columns: 1fr;
    }

    .floating-music-notes {
        font-size: 1.5rem;
    }
}
</style>

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

function showNotification(message) {
    const notification = document.createElement('div');
    notification.innerHTML = message;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: linear-gradient(135deg, #0066CC, #0052a3);
        color: white;
        padding: 1rem 1.5rem;
        border-radius: 12px;
        z-index: 10000;
        box-shadow: 0 8px 25px rgba(0, 102, 204, 0.4);
        max-width: 350px;
        animation: slideIn 0.3s ease;
        font-weight: 600;
    `;
    document.body.appendChild(notification);
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Add slide animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
`;
document.head.appendChild(style);

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
                } else {
                    // Add loading state to button
                    const submitBtn = form.querySelector('.btn-primary-custom');
                    submitBtn.classList.add('loading');
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();

// Add focus effects
document.addEventListener('DOMContentLoaded', function() {
    const inputs = document.querySelectorAll('.form-control-login');

    inputs.forEach(input => {
        input.addEventListener('focus', function() {
            this.parentElement.style.transform = 'scale(1.02)';
            this.parentElement.style.transition = 'transform 0.3s ease';
        });

        input.addEventListener('blur', function() {
            this.parentElement.style.transform = 'scale(1)';
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>
