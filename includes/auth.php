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
            // SEC-19 : un echec sur un compte d'administration est un
            // evenement a tracer. Journalise ici, et non dans une page : la
            // console, la page publique et une future API passent toutes par
            // ce point.
            if (class_exists('Autorisations') && Autorisations::estAdministrateur((int) $user['id'])) {
                JournalAudit::enregistrer('admin.connexion.echec', [
                    'cible_type' => 'utilisateur',
                    'cible_id'   => $user['id'],
                    'raison'     => 'mot de passe incorrect',
                    'acteur'     => (int) $user['id'],
                ]);
            }

            return ['success' => false, 'error' => 'Identifiants incorrects'];
        }

        // SEC-20 : second facteur. Le mot de passe est juste : la session
        // n'est pas ouverte pour autant. L'attente est conservee cinq minutes,
        // le temps de lire un code sur un telephone.
        if (class_exists('DeuxFacteurs') && DeuxFacteurs::estActive((int) $user['id'])) {
            $_SESSION['deux_facteurs_attente'] = [
                'user_id'  => (int) $user['id'],
                'expire'   => time() + 300,
                'souvenir' => (bool) $rememberMe,
            ];

            return ['success' => false, 'deux_facteurs' => true, 'error' => ''];
        }

        // Démarrage de la session
        $this->startUserSession($user, $rememberMe);

        // Mise à jour de la dernière connexion
        $stmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);

        // SEC-19 : toute entree dans l'administration laisse une trace, quel
        // que soit le chemin emprunte (console, page publique, API a venir).
        if (class_exists('Autorisations') && Autorisations::estAdministrateur((int) $user['id'])) {
            JournalAudit::enregistrer('admin.connexion', [
                'cible_type' => 'utilisateur',
                'cible_id'   => $user['id'],
            ]);
        }

        return ['success' => true, 'user' => $user];
    }

    /**
     * Termine une connexion en attente de second facteur (SEC-20).
     *
     * Le mot de passe a deja ete verifie ; il reste a verifier le code, puis a
     * ouvrir la session. L'attente expire au bout de cinq minutes : une
     * verification laissee ouverte ne doit pas rester exploitable.
     *
     * @return array{success:bool, error:string}
     */
    public function terminerConnexionDeuxFacteurs(string $code, bool $codeDeSecours = false): array
    {
        $attente = $_SESSION['deux_facteurs_attente'] ?? null;

        if (!is_array($attente) || ($attente['expire'] ?? 0) < time()) {
            unset($_SESSION['deux_facteurs_attente']);

            return ['success' => false, 'error' => 'La verification a expire. Reprenez la connexion.'];
        }

        $userId = (int) $attente['user_id'];

        $valide = $codeDeSecours
            ? DeuxFacteurs::verifierCodeDeSecours($userId, $code)
            : DeuxFacteurs::verifierCode($userId, $code);

        if (!$valide) {
            JournalAudit::enregistrer('2fa.echec', [
                'cible_type' => 'utilisateur',
                'cible_id'   => $userId,
                'acteur'     => $userId,
                'raison'     => $codeDeSecours ? 'code de secours refuse' : 'code refuse',
            ]);

            return ['success' => false, 'error' => 'Code incorrect.'];
        }

        $stmt = $this->db->prepare(
            "SELECT u.*, a.id as artist_id, a.stage_name, ad.role as admin_role
             FROM users u
             LEFT JOIN artists a ON u.id = a.user_id
             LEFT JOIN admins ad ON u.id = ad.user_id
             WHERE u.id = ? AND u.is_active = 1
             LIMIT 1"
        );
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            unset($_SESSION['deux_facteurs_attente']);

            return ['success' => false, 'error' => 'Compte indisponible.'];
        }

        $souvenir = !empty($attente['souvenir']);
        unset($_SESSION['deux_facteurs_attente']);

        $this->startUserSession($user, $souvenir);

        $this->db->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$userId]);

        if (Autorisations::estAdministrateur($userId)) {
            JournalAudit::enregistrer('admin.connexion', [
                'cible_type' => 'utilisateur',
                'cible_id'   => $userId,
                'raison'     => $codeDeSecours ? 'second facteur : code de secours' : 'second facteur verifie',
            ]);
        }

        return ['success' => true, 'error' => ''];
    }

    /**
     * Déconnexion
     */
    public function logout() {
        try {
            $this->db->prepare('DELETE FROM user_sessions WHERE id = ?')->execute([session_id()]);
        } catch (Exception $e) {
            error_log('[Tchadok][session] ' . $e->getMessage());
        }

        // Sans cela, la connexion automatique rouvrait la session a la requete
        // suivante. SEC-11 : seul l'appareil qui se deconnecte est concerne,
        // les autres gardent la leur.
        RememberMe::oublierAppareilCourant();

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
                clientIp(),
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
            RememberMe::revoquerTout((int) $user['id']);
            RememberMe::oublierAppareilCourant();
        }
        if ($rememberMe) {
            // SEC-11 : un jeton par appareil, verificateur tournant.
            RememberMe::creer((int) $user['id']);
        }
    }

    /**
     * Vérifie la connexion automatique via cookie
     */
    public function checkRememberMe() {
        if (isLoggedIn() || !isset($_COOKIE[RememberMe::COOKIE])) {
            return false;
        }

        // SEC-11 : une lecture indexee par selecteur, une comparaison, et
        // rotation du verificateur. L'ancienne version lisait tous les comptes
        // porteurs d'un jeton et calculait un bcrypt sur chacun.
        $userId = RememberMe::verifier();
        if ($userId === null) {
            return false;
        }

        try {
            $stmt = $this->db->prepare(
                "SELECT u.*, a.id as artist_id, a.stage_name, ad.role as admin_role
                 FROM users u
                 LEFT JOIN artists a ON u.id = a.user_id
                 LEFT JOIN admins ad ON u.id = ad.user_id
                 WHERE u.id = ? AND u.is_active = 1
                 LIMIT 1"
            );
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('[Tchadok][souvenir] ' . $e->getMessage());
            return false;
        }

        // Compte desactive ou supprime entre-temps, ou devenu administrateur :
        // pas de connexion automatique, et les jetons tombent (SEC-10, le
        // delai d'inactivite de l'administration ne doit pas etre contourne).
        if (!$user || !empty($user['admin_role'])) {
            RememberMe::revoquerTout($userId);
            RememberMe::oublierAppareilCourant();
            return false;
        }

        // false : le cookie vient d'etre renouvele par la rotation, il ne faut
        // pas creer un second jeton pour le meme appareil.
        $this->startUserSession($user, false);

        // La session vient de changer d'identifiant : le jeton doit pointer
        // vers la nouvelle, sinon la fermer depuis l'ecran « Appareils
        // connectes » laisserait cet appareil revenir aussitot.
        RememberMe::associerSessionCourante();

        // SEC-14 : une connexion automatique est une connexion. Elle figure
        // dans l'historique, sinon celui-ci serait incomplet donc trompeur.
        VerrouConnexion::tracer((string) $user['email'], true);

        return true;
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
