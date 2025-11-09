<?php
/**
 * Système d'authentification - Tchadok Platform
 * @author Tchadok Team
 * @version 2.0 - Mise à jour pour utiliser .env et PDO
 */

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';

class Auth {
    private $db;

    public function __construct() {
        // Utiliser TchadokDatabase au lieu de $db global
        $dbInstance = TchadokDatabase::getInstance();
        $this->db = $dbInstance->getConnection();

        if (!$this->db) {
            throw new Exception("Impossible de se connecter à la base de données. Vérifiez votre configuration .env");
        }
    }

    /**
     * Connexion d'un utilisateur
     */
    public function login($identifier, $password, $rememberMe = false) {
        // Recherche de l'utilisateur par email ou username
        $stmt = $this->db->prepare(
            "SELECT u.*, a.id as artist_id, a.stage_name, ad.role as admin_role
             FROM users u
             LEFT JOIN artists a ON u.id = a.user_id
             LEFT JOIN admins ad ON u.id = ad.user_id
             WHERE (u.email = ? OR u.username = ?) AND u.is_active = 1"
        );
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return ['success' => false, 'error' => 'Identifiants incorrects'];
        }

        // Vérifier le mot de passe avec les deux colonnes (password ET password_hash)
        // La table users a les deux colonnes pour compatibilité
        $passwordValid = false;
        if (!empty($user['password_hash']) && verifyPassword($password, $user['password_hash'])) {
            $passwordValid = true;
        } elseif (!empty($user['password']) && verifyPassword($password, $user['password'])) {
            $passwordValid = true;
        }

        if (!$passwordValid) {
            return ['success' => false, 'error' => 'Identifiants incorrects'];
        }

        // Démarrage de la session
        $this->startUserSession($user, $rememberMe);

        // Mise à jour de la dernière connexion
        $stmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);

        return ['success' => true, 'user' => $user];
    }

    /**
     * Déconnexion
     */
    public function logout() {
        if (isLoggedIn()) {
            // Suppression de la session en base si elle existe
            if (isset($_SESSION['session_id'])) {
                try {
                    $stmt = $this->db->prepare("DELETE FROM user_sessions WHERE id = ?");
                    $stmt->execute([$_SESSION['session_id']]);
                } catch (Exception $e) {
                    // Ignorer l'erreur silencieusement
                }
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
            if (!empty($user['stage_name'])) {
                $_SESSION['stage_name'] = $user['stage_name'];
            }
        } else {
            $_SESSION['user_type'] = USER_TYPE_FAN;
        }

        // Enregistrement de la session en base (si la table existe)
        try {
            $sessionId = session_id();
            $stmt = $this->db->prepare(
                "REPLACE INTO user_sessions (id, user_id, ip_address, user_agent, data, last_activity)
                 VALUES (?, ?, ?, ?, ?, NOW())"
            );
            $stmt->execute([
                $sessionId,
                $user['id'],
                $this->getClientIP(),
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                json_encode($_SESSION)
            ]);
            $_SESSION['session_id'] = $sessionId;
        } catch (Exception $e) {
            // Si la table user_sessions n'existe pas, continuer sans erreur
        }

        // Cookie de connexion automatique si demandé
        if ($rememberMe) {
            $rememberToken = generateSecureToken();
            setcookie('remember_token', $rememberToken, time() + (30 * 24 * 60 * 60), '/', '', false, true); // 30 jours

            try {
                $stmt = $this->db->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
                $stmt->execute([hashPassword($rememberToken), $user['id']]);
            } catch (Exception $e) {
                // Ignorer l'erreur silencieusement
            }
        }
    }

    /**
     * Obtient l'adresse IP du client
     */
    private function getClientIP() {
        $ip = '';
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }
        return $ip;
    }

    /**
     * Vérifie la connexion automatique via cookie
     */
    public function checkRememberMe() {
        if (!isLoggedIn() && isset($_COOKIE['remember_token'])) {
            $token = $_COOKIE['remember_token'];

            try {
                $stmt = $this->db->prepare(
                    "SELECT u.*, a.id as artist_id, a.stage_name, ad.role as admin_role
                     FROM users u
                     LEFT JOIN artists a ON u.id = a.user_id
                     LEFT JOIN admins ad ON u.id = ad.user_id
                     WHERE u.remember_token IS NOT NULL AND u.is_active = 1"
                );
                $stmt->execute();

                while ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    if (verifyPassword($token, $user['remember_token'])) {
                        $this->startUserSession($user, true);
                        return true;
                    }
                }
            } catch (Exception $e) {
                // Ignorer l'erreur silencieusement
            }

            // Token invalide, on le supprime
            setcookie('remember_token', '', time() - 3600, '/');
        }

        return false;
    }
}

// Instance globale d'authentification
try {
    $auth = new Auth();
    // Vérification de la connexion automatique
    $auth->checkRememberMe();
} catch (Exception $e) {
    // En cas d'erreur de connexion DB, continuer sans authentification
    $auth = null;
}

/**
 * Vérifie si l'utilisateur est un administrateur
 */
function isAdmin() {
    return isLoggedIn() && isset($_SESSION['user_type']) && $_SESSION['user_type'] === USER_TYPE_ADMIN;
}

/**
 * Vérifie si l'utilisateur est un artiste
 */
function isArtist() {
    return isLoggedIn() && isset($_SESSION['user_type']) && $_SESSION['user_type'] === USER_TYPE_ARTIST;
}

/**
 * Vérifie si l'utilisateur est un fan (utilisateur normal)
 */
function isFan() {
    return isLoggedIn() && isset($_SESSION['user_type']) && $_SESSION['user_type'] === USER_TYPE_FAN;
}

/**
 * Redirige vers le dashboard approprié selon le type d'utilisateur
 */
function redirectToDashboard() {
    if (!isLoggedIn()) {
        header('Location: ' . SITE_URL . '/login.php');
        exit();
    }

    if (isAdmin()) {
        header('Location: ' . SITE_URL . '/admin-dashboard.php');
        exit();
    } elseif (isArtist()) {
        header('Location: ' . SITE_URL . '/artist-dashboard.php');
        exit();
    } else {
        header('Location: ' . SITE_URL . '/user-dashboard.php');
        exit();
    }
}
?>
