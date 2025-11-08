<?php
/**
 * Système d'authentification - Tchadok Platform
 * @author Tchadok Team
 * @version 1.0
 */

require_once 'functions.php';

class Auth {
    private $db;
    
    public function __construct() {
        global $db;
        $this->db = $db;
    }
    
    /**
     * Inscription d'un nouvel utilisateur
     */
    public function register($userData) {
        // Validation des données
        $errors = $this->validateUserData($userData);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }
        
        // Vérification si l'utilisateur existe déjà
        if ($this->userExists($userData['email'], $userData['username'])) {
            return ['success' => false, 'errors' => ['Email ou nom d\'utilisateur déjà utilisé']];
        }
        
        // Hachage du mot de passe
        $userData['password'] = hashPassword($userData['password']);
        
        // Génération du token de vérification
        $verificationToken = generateSecureToken();
        $userData['verification_token'] = $verificationToken;
        
        try {
            $this->db->beginTransaction();
            
            // Insertion de l'utilisateur
            $userId = $this->db->insert('users', $userData);
            
            if (!$userId) {
                throw new Exception('Erreur lors de la création du compte');
            }
            
            // Si c'est un artiste, créer le profil artiste
            if (isset($userData['user_type']) && $userData['user_type'] === USER_TYPE_ARTIST) {
                $artistData = [
                    'user_id' => $userId,
                    'stage_name' => $userData['stage_name'] ?? $userData['first_name'] . ' ' . $userData['last_name'],
                    'real_name' => $userData['first_name'] . ' ' . $userData['last_name']
                ];
                
                $artistId = $this->db->insert('artists', $artistData);
                if (!$artistId) {
                    throw new Exception('Erreur lors de la création du profil artiste');
                }
            }
            
            // Si c'est un admin, créer le profil admin
            if (isset($userData['user_type']) && $userData['user_type'] === USER_TYPE_ADMIN) {
                $adminData = [
                    'user_id' => $userId,
                    'role' => $userData['admin_role'] ?? 'admin',
                    'permissions' => json_encode($userData['permissions'] ?? [])
                ];
                
                $adminId = $this->db->insert('admins', $adminData);
                if (!$adminId) {
                    throw new Exception('Erreur lors de la création du profil admin');
                }
            }
            
            $this->db->commit();
            
            // Envoi de l'email de vérification
            $this->sendVerificationEmail($userData['email'], $verificationToken);
            
            logActivity(LOG_LEVEL_INFO, "Nouvel utilisateur inscrit", ['user_id' => $userId, 'email' => $userData['email']]);
            
            return ['success' => true, 'user_id' => $userId, 'message' => 'Compte créé avec succès. Vérifiez votre email.'];
            
        } catch (Exception $e) {
            $this->db->rollback();
            logActivity(LOG_LEVEL_ERROR, "Erreur inscription: " . $e->getMessage(), $userData);
            return ['success' => false, 'errors' => [$e->getMessage()]];
        }
    }
    
    /**
     * Connexion d'un utilisateur
     */
    public function login($identifier, $password, $rememberMe = false) {
        // Recherche de l'utilisateur par email ou username
        $user = $this->db->fetchOne(
            "SELECT u.*, a.id as artist_id, ad.role as admin_role 
             FROM users u 
             LEFT JOIN artists a ON u.id = a.user_id 
             LEFT JOIN admins ad ON u.id = ad.user_id 
             WHERE (u.email = ? OR u.username = ?) AND u.is_active = 1",
            [$identifier, $identifier]
        );
        
        if (!$user || !verifyPassword($password, $user['password'])) {
            logActivity(LOG_LEVEL_WARNING, "Tentative de connexion échouée", ['identifier' => $identifier, 'ip' => getClientIP()]);
            return ['success' => false, 'error' => 'Identifiants incorrects'];
        }
        
        if (!$user['email_verified']) {
            return ['success' => false, 'error' => 'Veuillez vérifier votre email avant de vous connecter'];
        }
        
        // Démarrage de la session
        $this->startUserSession($user, $rememberMe);
        
        // Mise à jour de la dernière connexion
        $this->db->update('users', 
            ['last_login' => date('Y-m-d H:i:s')], 
            'id = ?', 
            [$user['id']]
        );
        
        logActivity(LOG_LEVEL_INFO, "Connexion réussie", ['user_id' => $user['id'], 'ip' => getClientIP()]);
        
        return ['success' => true, 'user' => $user];
    }
    
    /**
     * Déconnexion
     */
    public function logout() {
        if (isLoggedIn()) {
            logActivity(LOG_LEVEL_INFO, "Déconnexion", ['user_id' => $_SESSION['user_id']]);
            
            // Suppression de la session en base
            if (isset($_SESSION['session_id'])) {
                $this->db->delete('user_sessions', 'id = ?', [$_SESSION['session_id']]);
            }
        }
        
        // Destruction de la session
        session_destroy();
        
        // Suppression des cookies de connexion automatique
        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/');
        }
        
        return true;
    }
    
    /**
     * Vérification de l'email
     */
    public function verifyEmail($token) {
        $user = $this->db->fetchOne(
            "SELECT * FROM users WHERE verification_token = ? AND email_verified = 0",
            [$token]
        );
        
        if (!$user) {
            return ['success' => false, 'error' => 'Token de vérification invalide'];
        }
        
        $result = $this->db->update('users', 
            ['email_verified' => 1, 'verification_token' => null], 
            'id = ?', 
            [$user['id']]
        );
        
        if ($result) {
            logActivity(LOG_LEVEL_INFO, "Email vérifié", ['user_id' => $user['id']]);
            return ['success' => true, 'message' => 'Email vérifié avec succès'];
        }
        
        return ['success' => false, 'error' => 'Erreur lors de la vérification'];
    }
    
    /**
     * Demande de réinitialisation du mot de passe
     */
    public function requestPasswordReset($email) {
        $user = $this->db->fetchOne("SELECT * FROM users WHERE email = ?", [$email]);
        
        if (!$user) {
            // Pour des raisons de sécurité, on retourne toujours un succès
            return ['success' => true, 'message' => 'Si cet email existe, vous recevrez un lien de réinitialisation'];
        }
        
        $resetToken = generateSecureToken();
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $result = $this->db->update('users', 
            ['reset_token' => $resetToken, 'reset_expires' => $expiresAt], 
            'id = ?', 
            [$user['id']]
        );
        
        if ($result) {
            $this->sendPasswordResetEmail($email, $resetToken);
            logActivity(LOG_LEVEL_INFO, "Demande de réinitialisation mot de passe", ['user_id' => $user['id']]);
        }
        
        return ['success' => true, 'message' => 'Si cet email existe, vous recevrez un lien de réinitialisation'];
    }
    
    /**
     * Réinitialisation du mot de passe
     */
    public function resetPassword($token, $newPassword) {
        $user = $this->db->fetchOne(
            "SELECT * FROM users WHERE reset_token = ? AND reset_expires > NOW()",
            [$token]
        );
        
        if (!$user) {
            return ['success' => false, 'error' => 'Token de réinitialisation invalide ou expiré'];
        }
        
        if (strlen($newPassword) < MIN_PASSWORD_LENGTH) {
            return ['success' => false, 'error' => 'Le mot de passe doit contenir au moins ' . MIN_PASSWORD_LENGTH . ' caractères'];
        }
        
        $hashedPassword = hashPassword($newPassword);
        
        $result = $this->db->update('users', 
            ['password' => $hashedPassword, 'reset_token' => null, 'reset_expires' => null], 
            'id = ?', 
            [$user['id']]
        );
        
        if ($result) {
            logActivity(LOG_LEVEL_INFO, "Mot de passe réinitialisé", ['user_id' => $user['id']]);
            return ['success' => true, 'message' => 'Mot de passe réinitialisé avec succès'];
        }
        
        return ['success' => false, 'error' => 'Erreur lors de la réinitialisation'];
    }
    
    /**
     * Validation des données utilisateur
     */
    private function validateUserData($data) {
        $errors = [];
        
        // Validation des champs obligatoires
        $required = ['username', 'email', 'password', 'first_name', 'last_name'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $errors[] = "Le champ {$field} est obligatoire";
            }
        }
        
        // Validation de l'email
        if (!empty($data['email']) && !validateEmail($data['email'])) {
            $errors[] = "Format d'email invalide";
        }
        
        // Validation du mot de passe
        if (!empty($data['password']) && strlen($data['password']) < MIN_PASSWORD_LENGTH) {
            $errors[] = "Le mot de passe doit contenir au moins " . MIN_PASSWORD_LENGTH . " caractères";
        }
        
        // Validation du nom d'utilisateur
        if (!empty($data['username']) && !preg_match('/^[a-zA-Z0-9_]{3,30}$/', $data['username'])) {
            $errors[] = "Le nom d'utilisateur doit contenir entre 3 et 30 caractères alphanumériques";
        }
        
        // Validation du téléphone si fourni
        if (!empty($data['phone']) && !validateTchadianPhone($data['phone'])) {
            $errors[] = "Format de numéro de téléphone invalide";
        }
        
        return $errors;
    }
    
    /**
     * Vérifie si un utilisateur existe déjà
     */
    private function userExists($email, $username) {
        $user = $this->db->fetchOne(
            "SELECT id FROM users WHERE email = ? OR username = ?",
            [$email, $username]
        );
        return $user !== null;
    }
    
    /**
     * Démarre une session utilisateur
     */
    private function startUserSession($user, $rememberMe = false) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name'] = $user['last_name'];
        $_SESSION['premium_status'] = $user['premium_status'];
        
        // Détermination du type d'utilisateur
        if ($user['admin_role']) {
            $_SESSION['user_type'] = USER_TYPE_ADMIN;
            $_SESSION['admin_role'] = $user['admin_role'];
        } elseif ($user['artist_id']) {
            $_SESSION['user_type'] = USER_TYPE_ARTIST;
            $_SESSION['artist_id'] = $user['artist_id'];
        } else {
            $_SESSION['user_type'] = USER_TYPE_FAN;
        }
        
        // Enregistrement de la session en base
        $sessionId = session_id();
        $sessionData = [
            'id' => $sessionId,
            'user_id' => $user['id'],
            'ip_address' => getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'data' => json_encode($_SESSION)
        ];
        
        $this->db->query("REPLACE INTO user_sessions (id, user_id, ip_address, user_agent, data) VALUES (?, ?, ?, ?, ?)",
            [$sessionId, $user['id'], $sessionData['ip_address'], $sessionData['user_agent'], $sessionData['data']]);
        
        $_SESSION['session_id'] = $sessionId;
        
        // Cookie de connexion automatique si demandé
        if ($rememberMe) {
            $rememberToken = generateSecureToken();
            setcookie('remember_token', $rememberToken, time() + (30 * 24 * 60 * 60), '/', '', true, true); // 30 jours
            
            $this->db->update('users', 
                ['remember_token' => hashPassword($rememberToken)], 
                'id = ?', 
                [$user['id']]
            );
        }
    }
    
    /**
     * Envoie un email de vérification
     */
    private function sendVerificationEmail($email, $token) {
        $verificationUrl = SITE_URL . "/verify-email.php?token=" . $token;
        
        $subject = "Vérification de votre compte Tchadok";
        $message = "
        <h2>Bienvenue sur Tchadok !</h2>
        <p>Merci de vous être inscrit sur la plateforme musicale de référence du Tchad.</p>
        <p>Pour activer votre compte, veuillez cliquer sur le lien suivant :</p>
        <a href='{$verificationUrl}' style='background: #0066CC; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Vérifier mon email</a>
        <p>Ce lien expire dans 24 heures.</p>
        <p>Si vous n'avez pas créé ce compte, ignorez cet email.</p>
        <br>
        <p>L'équipe Tchadok</p>
        ";
        
        return sendEmail($email, $subject, $message);
    }
    
    /**
     * Envoie un email de réinitialisation de mot de passe
     */
    private function sendPasswordResetEmail($email, $token) {
        $resetUrl = SITE_URL . "/reset-password.php?token=" . $token;
        
        $subject = "Réinitialisation de votre mot de passe Tchadok";
        $message = "
        <h2>Réinitialisation de mot de passe</h2>
        <p>Vous avez demandé la réinitialisation de votre mot de passe Tchadok.</p>
        <p>Cliquez sur le lien suivant pour créer un nouveau mot de passe :</p>
        <a href='{$resetUrl}' style='background: #0066CC; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Réinitialiser mon mot de passe</a>
        <p>Ce lien expire dans 1 heure.</p>
        <p>Si vous n'avez pas fait cette demande, ignorez cet email.</p>
        <br>
        <p>L'équipe Tchadok</p>
        ";
        
        return sendEmail($email, $subject, $message);
    }
    
    /**
     * Vérifie la connexion automatique via cookie
     */
    public function checkRememberMe() {
        if (!isLoggedIn() && isset($_COOKIE['remember_token'])) {
            $token = $_COOKIE['remember_token'];
            
            $user = $this->db->fetchOne(
                "SELECT u.*, a.id as artist_id, ad.role as admin_role 
                 FROM users u 
                 LEFT JOIN artists a ON u.id = a.user_id 
                 LEFT JOIN admins ad ON u.id = ad.user_id 
                 WHERE u.remember_token IS NOT NULL AND u.is_active = 1"
            );
            
            if ($user && verifyPassword($token, $user['remember_token'])) {
                $this->startUserSession($user, true);
                return true;
            } else {
                // Token invalide, on le supprime
                setcookie('remember_token', '', time() - 3600, '/');
            }
        }
        
        return false;
    }
}

// Instance globale d'authentification
$auth = new Auth();

// Vérification de la connexion automatique
$auth->checkRememberMe();
?>