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
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $userType = sanitizeInput($_POST['user_type'] ?? USER_TYPE_FAN);
    $terms = isset($_POST['terms']);
    $stageName = sanitizeInput($_POST['stage_name'] ?? '');

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
        // Créer le compte
        try {
            $dbInstance = TchadokDatabase::getInstance();
            $db = $dbInstance->getConnection();

            if (!$db) {
                throw new Exception('Erreur de connexion à la base de données.');
            }

            // Générer un username si non fourni
            if (empty($username)) {
                $username = strtolower($firstName . '_' . substr($lastName, 0, 1) . rand(100, 999));
            }

            // Vérifier si l'email ou username existe déjà
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
            $stmt->execute([$email, $username]);

            if ($stmt->fetch()) {
                $error = 'Cet email ou nom d\'utilisateur est déjà utilisé.';
            } else {
                // Hasher le mot de passe
                $passwordHash = hashPassword($password);

                // Démarrer une transaction
                $db->beginTransaction();

                // Insérer l'utilisateur avec les DEUX colonnes password
                $stmt = $db->prepare("
                    INSERT INTO users (
                        username, email, password, password_hash,
                        first_name, last_name, country,
                        email_verified, is_active, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");

                $stmt->execute([
                    $username,
                    $email,
                    $passwordHash,  // Colonne password
                    $passwordHash,  // Colonne password_hash
                    $firstName,
                    $lastName,
                    'Tchad',
                    0,  // email_verified
                    1   // is_active
                ]);

                $userId = $db->lastInsertId();

                // Si c'est un artiste, créer le profil artiste
                if ($userType === USER_TYPE_ARTIST) {
                    $artistStageName = !empty($stageName) ? $stageName : "$firstName $lastName";

                    $stmt = $db->prepare("
                        INSERT INTO artists (
                            user_id, stage_name, real_name,
                            is_active, created_at
                        ) VALUES (?, ?, ?, 1, NOW())
                    ");

                    $stmt->execute([
                        $userId,
                        $artistStageName,
                        "$firstName $lastName"
                    ]);
                }

                // Valider la transaction
                $db->commit();

                // Succès !
                $success = '✅ Inscription réussie ! Vous pouvez maintenant vous connecter avec votre email : <strong>' . htmlspecialchars($email) . '</strong>';

                // Vider les champs du formulaire
                $_POST = [];
            }

        } catch (Exception $e) {
            // Annuler la transaction en cas d'erreur
            if ($db && $db->inTransaction()) {
                $db->rollBack();
            }
            $error = 'Erreur lors de l\'inscription : ' . $e->getMessage();
        }
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
            <div class="col-lg-9 col-md-10">
                <div class="register-card">
                    <div class="register-header text-center">
                        <svg class="register-logo" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
                            <circle cx="50" cy="50" r="45" fill="#0066CC"/>
                            <path d="M30 45 L30 55 L40 60 L40 40 Z M45 35 L45 65 L55 70 L55 30 Z M60 40 L60 60 L70 55 L70 45 Z" fill="#FFD700"/>
                        </svg>
                        <h2>Rejoignez Tchadok</h2>
                        <p>Découvrez l'univers musical tchadien</p>
                    </div>

                    <div class="register-body">
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

                    <!-- Progress Indicator -->
                    <div class="form-progress">
                        <div class="progress-bar" id="formProgress"></div>
                    </div>
                    <p class="text-center text-muted mb-4">
                        <small>Remplissez le formulaire pour rejoindre notre communauté</small>
                    </p>

                    <form method="POST" class="needs-validation" novalidate id="registerForm">
                        <!-- Informations personnelles -->
                        <div class="form-section">
                            <div class="section-title">
                                <i class="fas fa-user-circle me-2"></i>
                                <span>Informations personnelles</span>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-4">
                                        <label for="first_name" class="form-label">
                                            <i class="fas fa-user me-2"></i>Prénom *
                                        </label>
                                        <div class="input-wrapper">
                                            <input type="text"
                                                   class="form-control-register"
                                                   id="first_name"
                                                   name="first_name"
                                                   value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>"
                                                   placeholder="Jean"
                                                   required>
                                            <span class="input-icon"><i class="fas fa-check-circle"></i></span>
                                        </div>
                                        <div class="invalid-feedback">
                                            Veuillez entrer votre prénom.
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group mb-4">
                                        <label for="last_name" class="form-label">
                                            <i class="fas fa-user me-2"></i>Nom *
                                        </label>
                                        <div class="input-wrapper">
                                            <input type="text"
                                                   class="form-control-register"
                                                   id="last_name"
                                                   name="last_name"
                                                   value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>"
                                                   placeholder="Dupont"
                                                   required>
                                            <span class="input-icon"><i class="fas fa-check-circle"></i></span>
                                        </div>
                                        <div class="invalid-feedback">
                                            Veuillez entrer votre nom.
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group mb-4">
                                <label for="email" class="form-label">
                                    <i class="fas fa-envelope me-2"></i>Adresse email *
                                </label>
                                <div class="input-wrapper">
                                    <input type="email"
                                           class="form-control-register"
                                           id="email"
                                           name="email"
                                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                           placeholder="exemple@tchadok.td"
                                           required>
                                    <span class="input-icon"><i class="fas fa-check-circle"></i></span>
                                </div>
                                <div class="invalid-feedback">
                                    Veuillez entrer une adresse email valide.
                                </div>
                            </div>

                            <div class="form-group mb-4">
                                <label for="username" class="form-label">
                                    <i class="fas fa-at me-2"></i>Nom d'utilisateur
                                </label>
                                <div class="input-wrapper">
                                    <input type="text"
                                           class="form-control-register"
                                           id="username"
                                           name="username"
                                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                                           placeholder="john_doe (optionnel, généré automatiquement)">
                                    <span class="input-icon"><i class="fas fa-check-circle"></i></span>
                                </div>
                                <small class="text-muted">Laissez vide pour générer automatiquement</small>
                            </div>
                        </div>

                        <!-- Sécurité -->
                        <div class="form-section">
                            <div class="section-title">
                                <i class="fas fa-shield-alt me-2"></i>
                                <span>Sécurité</span>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-4">
                                        <label for="password" class="form-label">
                                            <i class="fas fa-lock me-2"></i>Mot de passe *
                                        </label>
                                        <div class="input-wrapper">
                                            <input type="password"
                                                   class="form-control-register password-input"
                                                   id="password"
                                                   name="password"
                                                   placeholder="Minimum 8 caractères"
                                                   minlength="8"
                                                   required>
                                            <button type="button" class="password-toggle" onclick="togglePassword('password', 'passwordToggle1')">
                                                <i class="fas fa-eye" id="passwordToggle1"></i>
                                            </button>
                                        </div>
                                        <div class="password-strength" id="passwordStrength">
                                            <div class="password-strength-bar" id="passwordStrengthBar"></div>
                                        </div>
                                        <small class="password-strength-text" id="passwordStrengthText"></small>
                                        <div class="invalid-feedback">
                                            Le mot de passe doit contenir au moins 8 caractères.
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group mb-4">
                                        <label for="confirm_password" class="form-label">
                                            <i class="fas fa-lock me-2"></i>Confirmer le mot de passe *
                                        </label>
                                        <div class="input-wrapper">
                                            <input type="password"
                                                   class="form-control-register password-input"
                                                   id="confirm_password"
                                                   name="confirm_password"
                                                   placeholder="Répétez le mot de passe"
                                                   required>
                                            <button type="button" class="password-toggle" onclick="togglePassword('confirm_password', 'passwordToggle2')">
                                                <i class="fas fa-eye" id="passwordToggle2"></i>
                                            </button>
                                        </div>
                                        <div class="invalid-feedback">
                                            Les mots de passe ne correspondent pas.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Type de compte -->
                        <div class="form-section">
                            <div class="section-title">
                                <i class="fas fa-users me-2"></i>
                                <span>Type de compte</span>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <input type="radio" class="btn-check" name="user_type"
                                           id="fan" value="<?php echo USER_TYPE_FAN; ?>" checked>
                                    <label class="account-type-card" for="fan">
                                        <div class="account-type-icon">
                                            <i class="fas fa-heart"></i>
                                        </div>
                                        <div class="account-type-content">
                                            <h5>Mélomane</h5>
                                            <p>Écoutez et découvrez la musique tchadienne</p>
                                            <ul class="features-list">
                                                <li><i class="fas fa-check me-2"></i>Écoute illimitée</li>
                                                <li><i class="fas fa-check me-2"></i>Playlists personnalisées</li>
                                                <li><i class="fas fa-check me-2"></i>Radio en direct</li>
                                            </ul>
                                        </div>
                                        <div class="check-badge">
                                            <i class="fas fa-check"></i>
                                        </div>
                                    </label>
                                </div>

                                <div class="col-md-6">
                                    <input type="radio" class="btn-check" name="user_type"
                                           id="artist" value="<?php echo USER_TYPE_ARTIST; ?>">
                                    <label class="account-type-card" for="artist">
                                        <div class="account-type-icon artist">
                                            <i class="fas fa-music"></i>
                                        </div>
                                        <div class="account-type-content">
                                            <h5>Artiste</h5>
                                            <p>Partagez votre musique avec le monde</p>
                                            <ul class="features-list">
                                                <li><i class="fas fa-check me-2"></i>Upload de musique</li>
                                                <li><i class="fas fa-check me-2"></i>Statistiques détaillées</li>
                                                <li><i class="fas fa-check me-2"></i>Revenus de streaming</li>
                                            </ul>
                                        </div>
                                        <div class="check-badge">
                                            <i class="fas fa-check"></i>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <!-- Champ nom de scène pour les artistes (caché par défaut) -->
                            <div class="form-group mb-4 mt-4" id="stagename_field" style="display: none;">
                                <label for="stage_name" class="form-label">
                                    <i class="fas fa-microphone-alt me-2"></i>Nom de scène
                                </label>
                                <div class="input-wrapper">
                                    <input type="text"
                                           class="form-control-register"
                                           id="stage_name"
                                           name="stage_name"
                                           value="<?php echo htmlspecialchars($_POST['stage_name'] ?? ''); ?>"
                                           placeholder="Votre nom d'artiste">
                                    <span class="input-icon"><i class="fas fa-check-circle"></i></span>
                                </div>
                                <small class="text-muted">Le nom sous lequel vous serez connu sur la plateforme</small>
                            </div>
                        </div>

                        <!-- Conditions -->
                        <div class="form-group mb-4">
                            <div class="form-check terms-check">
                                <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                                <label class="form-check-label" for="terms">
                                    J'accepte les <a href="#" class="link-primary">conditions d'utilisation</a>
                                    et la <a href="#" class="link-primary">politique de confidentialité</a> *
                                </label>
                                <div class="invalid-feedback">
                                    Vous devez accepter les conditions d'utilisation.
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary-custom w-100">
                            <span class="btn-text">
                                <i class="fas fa-user-plus me-2"></i>
                                Créer mon compte gratuitement
                            </span>
                            <span class="btn-loader">
                                <i class="fas fa-spinner fa-spin"></i>
                            </span>
                        </button>

                        <div class="text-center mt-4">
                            <p class="text-muted mb-0">
                                <i class="fas fa-lock me-2"></i>
                                Vos données sont sécurisées et protégées
                            </p>
                        </div>
                    </form>
                </div>

                    <div class="register-footer text-center">
                        <p class="mb-0">
                            Déjà membre de Tchadok ?
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
    background: white;
    border-radius: 25px;
    overflow: hidden;
    box-shadow: 0 25px 80px rgba(0, 0, 0, 0.3);
    position: relative;
    z-index: 2;
}

.register-header {
    background: linear-gradient(135deg, var(--bleu-tchadien), #0052a3);
    color: white;
    padding: 3rem 2rem;
    position: relative;
    overflow: hidden;
}

.register-header::before {
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

.register-logo {
    width: 100px;
    height: 100px;
    margin-bottom: 1.5rem;
    animation: logoFloat 3s ease-in-out infinite, fadeInUp 1s ease;
}

@keyframes logoFloat {
    0%, 100% { transform: translateY(0) rotate(0deg); }
    50% { transform: translateY(-10px) rotate(5deg); }
}

.register-header h2 {
    font-family: 'Montserrat', sans-serif;
    font-weight: 900;
    font-size: 3rem;
    color: white;
    margin-bottom: 0.75rem;
    text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
    animation: fadeInUp 1s ease;
}

.register-header p {
    font-size: 1.3rem;
    color: white;
    opacity: 0.95;
    margin-bottom: 0;
    animation: fadeInUp 1.2s ease;
}

.register-body {
    padding: 3rem 2.5rem;
}

/* Progress Bar */
.form-progress {
    height: 6px;
    background: #e9ecef;
    border-radius: 3px;
    overflow: hidden;
    margin-bottom: 1rem;
}

.progress-bar {
    height: 100%;
    background: linear-gradient(to right, var(--bleu-tchadien), var(--jaune-solaire));
    width: 0;
    transition: width 0.3s ease;
}

/* Form Sections */
.form-section {
    margin-bottom: 2.5rem;
    padding-bottom: 2rem;
    border-bottom: 1px solid #e9ecef;
}

.form-section:last-of-type {
    border-bottom: none;
}

.section-title {
    display: flex;
    align-items: center;
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--gris-harmattan);
    margin-bottom: 1.5rem;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid var(--bleu-tchadien);
}

.section-title i {
    color: var(--bleu-tchadien);
}

/* Form Controls */
.form-label {
    font-weight: 600;
    color: var(--gris-harmattan);
    margin-bottom: 0.75rem;
    font-size: 0.95rem;
}

.input-wrapper {
    position: relative;
}

.form-control-register {
    width: 100%;
    border: 2px solid #e9ecef;
    border-radius: 12px;
    padding: 0.875rem 1rem;
    font-size: 1rem;
    transition: all 0.3s ease;
    background: white;
}

.form-control-register:focus {
    border-color: var(--bleu-tchadien);
    box-shadow: 0 0 0 0.2rem rgba(0, 102, 204, 0.15);
    outline: none;
}

.form-control-register:valid {
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

.form-control-register:valid ~ .input-icon {
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

/* Password Strength */
.password-strength {
    height: 4px;
    background: #e9ecef;
    border-radius: 2px;
    margin-top: 0.5rem;
    overflow: hidden;
}

.password-strength-bar {
    height: 100%;
    width: 0;
    transition: width 0.3s ease, background 0.3s ease;
}

.password-strength-text {
    display: block;
    margin-top: 0.25rem;
    font-size: 0.85rem;
    font-weight: 600;
}

/* Account Type Cards */
.account-type-card {
    display: block;
    padding: 2rem;
    border: 3px solid #e9ecef;
    border-radius: 20px;
    cursor: pointer;
    transition: all 0.3s ease;
    background: white;
    position: relative;
    height: 100%;
    overflow: hidden;
}

.account-type-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, rgba(0, 102, 204, 0.05), rgba(255, 215, 0, 0.05));
    opacity: 0;
    transition: opacity 0.3s ease;
}

.account-type-card:hover {
    border-color: var(--bleu-tchadien);
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0, 102, 204, 0.2);
}

.account-type-card:hover::before {
    opacity: 1;
}

.btn-check:checked + .account-type-card {
    border-color: var(--jaune-solaire);
    background: linear-gradient(135deg, rgba(255, 215, 0, 0.1), rgba(0, 102, 204, 0.05));
    box-shadow: 0 10px 40px rgba(255, 215, 0, 0.3);
}

.account-type-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, var(--bleu-tchadien), #0052a3);
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    color: white;
    margin-bottom: 1.5rem;
    transition: all 0.3s ease;
}

.account-type-icon.artist {
    background: linear-gradient(135deg, var(--jaune-solaire), #e6c200);
    color: var(--gris-harmattan);
}

.btn-check:checked + .account-type-card .account-type-icon {
    transform: scale(1.1) rotate(5deg);
}

.account-type-content h5 {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--gris-harmattan);
    margin-bottom: 0.5rem;
}

.account-type-content p {
    color: #6c757d;
    margin-bottom: 1rem;
    font-size: 0.95rem;
}

.features-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.features-list li {
    color: var(--gris-harmattan);
    padding: 0.4rem 0;
    font-size: 0.9rem;
}

.features-list i {
    color: var(--vert-savane);
}

.check-badge {
    position: absolute;
    top: 1rem;
    right: 1rem;
    width: 35px;
    height: 35px;
    background: var(--vert-savane);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    opacity: 0;
    transform: scale(0);
    transition: all 0.3s ease;
}

.btn-check:checked + .account-type-card .check-badge {
    opacity: 1;
    transform: scale(1);
}

/* Terms */
.terms-check {
    background: rgba(0, 102, 204, 0.05);
    padding: 1.25rem;
    border-radius: 12px;
    border: 2px solid rgba(0, 102, 204, 0.1);
}

.form-check-input[type="checkbox"] {
    width: 1.3rem;
    height: 1.3rem;
    border-radius: 6px;
    border: 2px solid #dee2e6;
    transition: all 0.3s ease;
    cursor: pointer;
}

.form-check-input[type="checkbox"]:checked {
    background: var(--vert-savane);
    border-color: var(--vert-savane);
}

.form-check-label {
    cursor: pointer;
    user-select: none;
    padding-left: 0.5rem;
}

.link-primary {
    color: var(--bleu-tchadien);
    text-decoration: none;
    font-weight: 600;
    transition: color 0.3s ease;
}

.link-primary:hover {
    color: var(--jaune-solaire);
}

/* Submit Button */
.btn-primary-custom {
    background: linear-gradient(135deg, var(--bleu-tchadien), #0052a3);
    border: none;
    color: white;
    font-weight: 700;
    padding: 1rem 2rem;
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

/* Footer */
.register-footer {
    background: linear-gradient(to bottom, rgba(248, 249, 250, 0.5), rgba(248, 249, 250, 0.8));
    padding: 2rem;
    border-top: 1px solid rgba(0, 0, 0, 0.05);
}

.register-link {
    color: var(--bleu-tchadien);
    font-weight: 700;
    text-decoration: none;
    transition: all 0.3s ease;
    position: relative;
}

.register-link::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 0;
    width: 0;
    height: 2px;
    background: var(--jaune-solaire);
    transition: width 0.3s ease;
}

.register-link:hover {
    color: var(--jaune-solaire);
}

.register-link:hover::after {
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

/* Validation States */
.was-validated .form-control-register:invalid {
    border-color: var(--rouge-terre);
    box-shadow: 0 0 0 0.2rem rgba(204, 51, 51, 0.25);
}

.was-validated .form-control-register:valid {
    border-color: var(--vert-savane);
    box-shadow: 0 0 0 0.2rem rgba(34, 139, 34, 0.25);
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

/* Mobile Responsiveness */
@media (max-width: 991px) {
    .register-header h2 {
        font-size: 2.5rem;
    }
}

@media (max-width: 768px) {
    .register-section {
        padding: 1rem 0;
    }

    .register-header {
        padding: 2rem 1.5rem;
    }

    .register-header h2 {
        font-size: 2rem;
    }

    .register-header p {
        font-size: 1.1rem;
    }

    .register-logo {
        width: 70px;
        height: 70px;
    }

    .register-body {
        padding: 2rem 1.5rem;
    }

    .floating-music-notes {
        font-size: 1.5rem;
    }

    .section-title {
        font-size: 1.1rem;
    }

    .account-type-card {
        margin-bottom: 1rem;
    }
}
</style>

<script>
// Password Toggle
function togglePassword(fieldId, iconId) {
    const passwordField = document.getElementById(fieldId);
    const toggleIcon = document.getElementById(iconId);

    if (passwordField.type === 'password') {
        passwordField.type = 'text';
        toggleIcon.className = 'fas fa-eye-slash';
    } else {
        passwordField.type = 'password';
        toggleIcon.className = 'fas fa-eye';
    }
}

// Toggle stage name field for artists
document.addEventListener("DOMContentLoaded", function() {
    const artistRadio = document.getElementById("artist");
    const fanRadio = document.getElementById("fan");
    const stagenameField = document.getElementById("stagename_field");

    function toggleStagenameField() {
        if (artistRadio && artistRadio.checked) {
            stagenameField.style.display = "block";
        } else {
            stagenameField.style.display = "none";
        }
    }

    // Initial check
    toggleStagenameField();

    // Listen for changes
    if (artistRadio) {
        artistRadio.addEventListener("change", toggleStagenameField);
    }
    if (fanRadio) {
        fanRadio.addEventListener("change", toggleStagenameField);
    }
});

// Password Strength Indicator
document.getElementById('password').addEventListener('input', function() {
    const password = this.value;
    const strengthBar = document.getElementById('passwordStrengthBar');
    const strengthText = document.getElementById('passwordStrengthText');

    let strength = 0;
    if (password.length >= 8) strength += 25;
    if (password.match(/[a-z]/)) strength += 25;
    if (password.match(/[A-Z]/)) strength += 25;
    if (password.match(/[0-9]/) || password.match(/[^a-zA-Z0-9]/)) strength += 25;

    strengthBar.style.width = strength + '%';

    if (strength === 0) {
        strengthBar.style.background = '#e9ecef';
        strengthText.textContent = '';
        strengthText.style.color = '';
    } else if (strength <= 25) {
        strengthBar.style.background = 'var(--rouge-terre)';
        strengthText.textContent = 'Faible';
        strengthText.style.color = 'var(--rouge-terre)';
    } else if (strength <= 50) {
        strengthBar.style.background = '#FFA500';
        strengthText.textContent = 'Moyen';
        strengthText.style.color = '#FFA500';
    } else if (strength <= 75) {
        strengthBar.style.background = 'var(--jaune-solaire)';
        strengthText.textContent = 'Bon';
        strengthText.style.color = 'var(--jaune-solaire)';
    } else {
        strengthBar.style.background = 'var(--vert-savane)';
        strengthText.textContent = 'Excellent';
        strengthText.style.color = 'var(--vert-savane)';
    }
});

// Confirm Password Validation
document.getElementById('confirm_password').addEventListener('input', function() {
    const password = document.getElementById('password').value;
    const confirmPassword = this.value;

    if (password !== confirmPassword) {
        this.setCustomValidity('Les mots de passe ne correspondent pas');
    } else {
        this.setCustomValidity('');
    }
});

// Form Progress Indicator
const form = document.getElementById('registerForm');
const progressBar = document.getElementById('formProgress');
const inputs = form.querySelectorAll('input[required]');

function updateProgress() {
    let filled = 0;
    inputs.forEach(input => {
        if (input.type === 'checkbox' || input.type === 'radio') {
            const name = input.name;
            if (form.querySelector(`[name="${name}"]:checked`)) {
                filled++;
            }
        } else if (input.value.trim() !== '') {
            filled++;
        }
    });

    const progress = (filled / inputs.length) * 100;
    progressBar.style.width = progress + '%';
}

inputs.forEach(input => {
    input.addEventListener('input', updateProgress);
    input.addEventListener('change', updateProgress);
});

// Form Validation
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
                    // Add loading state
                    const submitBtn = form.querySelector('.btn-primary-custom');
                    submitBtn.classList.add('loading');
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();

// Focus Effects
document.addEventListener('DOMContentLoaded', function() {
    const inputs = document.querySelectorAll('.form-control-register');

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
