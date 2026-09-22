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
        $userId = isLoggedIn() ? (int) $_SESSION['user_id'] : null;

        try {
            $this->db->prepare('DELETE FROM user_sessions WHERE id = ?')->execute([session_id()]);
            if ($userId !== null) {
                // Sans cela, checkRememberMe() reconnectait l'utilisateur.
                $this->db->prepare('UPDATE users SET remember_token = NULL WHERE id = ?')->execute([$userId]);
            }
        } catch (Exception $e) {
            error_log('[Tchadok][session] ' . $e->getMessage());
        }

        // SEC-10 : on vide la session et on emet un nouvel identifiant, au lieu
        // de detruire la session sans en rouvrir une. L'appelant (admin/login.php
        // notamment) continue d'afficher une page : sans session active, le
        // jeton CSRF genere pour cette page n'etait stocke nulle part, et le
        // formulaire suivant echouait.
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', [
                'expires'  => time() - 3600,
                'path'     => '/',
                'secure'   => EnvLoader::bool('SESSION_SECURE', true),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        return true;
    }

    /**
     * Démarre une session utilisateur
     */
    private function startUserSession($user, $rememberMe = false) {
        // SEC-10 : nouvel identifiant de session a la connexion.
        // Sans cela, l'identifiant de l'etat anonyme etait conserve apres
        // l'authentification : un attaquant qui l'avait obtenu (ou impose,
        // avant le mode strict) heritait de la session connectee, y compris
        // administrateur. Les donnees de l'ancienne session sont abandonnees.
        $ancienId = session_id();
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        if ($ancienId !== '') {
            try {
                $this->db->prepare('DELETE FROM user_sessions WHERE id = ?')->execute([$ancienId]);
            } catch (Exception $e) {
                // Registre indisponible : sans consequence ici.
            }
        }

        // SEC-09 : nouveau jeton CSRF a la connexion. Un jeton obtenu avant
        // l'authentification ne doit pas rester valide apres.
        if (class_exists('CsrfGuard')) {
            CsrfGuard::renouveler();
        }

        $_SESSION['connexion_le'] = time();
        $_SESSION['derniere_activite'] = time();
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

        // Inscription au registre des sessions (SEC-10).
        //
        // Le registre fait foi : validerSessionCourante() ferme toute session
        // qui n'y figure plus. La colonne data recevait auparavant la
        // serialisation complete de $_SESSION, jeton CSRF compris ; elle ne
        // recoit plus rien -- l'adresse, le navigateur et l'activite ont leurs
        // propres colonnes.
        try {
            $sessionId = session_id();
            $stmt = $this->db->prepare(
                "REPLACE INTO user_sessions (id, user_id, ip_address, user_agent, data, last_activity)
                 VALUES (?, ?, ?, ?, NULL, NOW())"
            );
            $stmt->execute([
                $sessionId,
                $user['id'],
                $this->getClientIP(),
                substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
            ]);
            $_SESSION['session_id'] = $sessionId;
            $_SESSION['session_enregistree'] = true;
            $_SESSION['registre_maj'] = time();

            // Nettoyage opportuniste des sessions abandonnees sans deconnexion.
            $plusLongue = max(dureeSessionOrdinaire(), dureeSessionAdministration());
            $this->db->prepare('DELETE FROM user_sessions WHERE last_activity < (NOW() - INTERVAL ? SECOND)')
                     ->execute([$plusLongue]);
        } catch (Exception $e) {
            // Registre indisponible : la session fonctionne, sans revocation
            // possible. Signale pour que l'exploitant le sache.
            error_log('[Tchadok][session] inscription au registre impossible : ' . $e->getMessage());
        }

        // Cookie de connexion automatique si demande.
        //
        // SEC-10 : jamais pour un administrateur. La connexion automatique
        // contournerait le delai d'inactivite de l'administration : une
        // session fermee apres 15 minutes serait rouverte d'elle-meme.
        if ($rememberMe && !empty($user['admin_role'])) {
            $rememberMe = false;
            try {
                $this->db->prepare('UPDATE users SET remember_token = NULL WHERE id = ?')->execute([$user['id']]);
            } catch (Exception $e) {
                // sans consequence
            }
        }
        if ($rememberMe) {
            $rememberToken = generateSecureToken();
            // SEC-10 : secure etait force a false -- le cookie de connexion
            // automatique circulait donc aussi en HTTP clair en production.
            // Il suit desormais SESSION_SECURE, avec SameSite=Lax.
            setcookie('remember_token', $rememberToken, [
                'expires'  => time() + (30 * 24 * 60 * 60),
                'path'     => '/',
                'secure'   => EnvLoader::bool('SESSION_SECURE', true),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);

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
                        // SEC-10 : pas de connexion automatique pour un
                        // administrateur (delai d'inactivite de 15 minutes).
                        if (!empty($user['admin_role'])) {
                            break;
                        }
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
?>
