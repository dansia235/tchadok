<?php
/**
 * Connexion automatique « se souvenir de moi » (SEC-11).
 *
 * Le cookie porte deux valeurs separees par « : » :
 *
 *   selecteur : identifiant public, indexe, qui designe la ligne ;
 *   verificateur : secret de 32 octets, dont seule l'empreinte est stockee.
 *
 * Retrouver le porteur d'un cookie demande donc UNE lecture par cle unique,
 * puis UNE comparaison. L'ancien mecanisme lisait tous les comptes munis d'un
 * jeton et calculait un bcrypt sur chacun : une requete anonyme repetee
 * suffisait a saturer le serveur.
 *
 * Le verificateur tourne a chaque usage : un cookie copie cesse d'ouvrir une
 * session des que la personne legitime revient. Si un verificateur perime est
 * presente au-dela du delai de tolerance, c'est qu'il a ete copie : tous les
 * jetons du compte sont alors revoques.
 */

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/database.php';

final class RememberMe
{
    /** Nom du cookie. */
    public const COOKIE = 'remember_token';

    /**
     * Tolerance apres une rotation, en secondes.
     *
     * Un navigateur emet souvent plusieurs requetes en parallele avec le meme
     * cookie ; une seule reponse fixe le nouveau. Sans cette tolerance, les
     * requetes suivantes presenteraient un verificateur deja remplace et
     * seraient prises pour un vol.
     */
    private const TOLERANCE_ROTATION = 120;

    /** Duree de vie par defaut : 30 jours. */
    private const DUREE_DEFAUT = 2592000;

    private static ?bool $tableDisponible = null;

    /** Selecteur du jeton reconnu pendant cette requete, s'il y en a eu un. */
    private static ?string $selecteurReconnu = null;

    /**
     * Duree de validite du cookie, en secondes (REMEMBER_LIFETIME).
     * Bornee a un jour minimum et 180 jours maximum.
     */
    public static function duree(): int
    {
        $duree = class_exists('EnvLoader')
            ? EnvLoader::int('REMEMBER_LIFETIME', self::DUREE_DEFAUT)
            : self::DUREE_DEFAUT;

        return max(86400, min(15552000, $duree));
    }

    /**
     * Cree un jeton pour l'appareil courant et pose le cookie.
     */
    public static function creer(int $userId): bool
    {
        $db = self::db();
        if (!$db) {
            return false;
        }

        $selecteur = bin2hex(random_bytes(16));
        $verificateur = bin2hex(random_bytes(32));
        $expire = time() + self::duree();

        try {
            $db->prepare(
                'INSERT INTO remember_tokens
                    (user_id, selector, validator_hash, device_label, ip_address, user_agent, session_id, last_used_at, expires_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)'
            )->execute([
                $userId,
                $selecteur,
                hash('sha256', $verificateur),
                etiquetteAppareil($_SERVER['HTTP_USER_AGENT'] ?? ''),
                self::adresseClient(),
                substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                session_id() !== '' ? session_id() : null,
                date('Y-m-d H:i:s', $expire),
            ]);
        } catch (Throwable $e) {
            self::signaler('creation impossible : ' . $e->getMessage());
            return false;
        }

        self::poserCookie($selecteur . ':' . $verificateur, $expire);
        self::nettoyer();

        return true;
    }

    /**
     * Identifie le porteur du cookie et fait tourner le verificateur.
     *
     * @return int|null identifiant de l'utilisateur, ou null si le cookie est
     *                  absent, illisible, inconnu, revoque ou expire
     */
    public static function verifier(): ?int
    {
        $couple = self::lireCookie();
        if ($couple === null) {
            return null;
        }
        [$selecteur, $verificateur] = $couple;

        $db = self::db();
        if (!$db) {
            return null;
        }

        try {
            $stmt = $db->prepare(
                'SELECT id, user_id, validator_hash, previous_validator_hash, rotated_at, expires_at
                 FROM remember_tokens
                 WHERE selector = ? AND revoked_at IS NULL
                 LIMIT 1'
            );
            $stmt->execute([$selecteur]);
            $ligne = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            self::signaler('lecture impossible : ' . $e->getMessage());
            return null;
        }

        // Selecteur inconnu : cookie d'un jeton supprime, revoque, ou invente.
        if (!$ligne) {
            self::supprimerCookie();
            return null;
        }

        if (strtotime((string) $ligne['expires_at']) <= time()) {
            self::revoquerLigne((int) $ligne['id'], 'expire');
            self::supprimerCookie();
            return null;
        }

        $empreinte = hash('sha256', $verificateur);
        $tourneIlYA = $ligne['rotated_at'] ? time() - strtotime((string) $ligne['rotated_at']) : PHP_INT_MAX;

        if (hash_equals((string) $ligne['validator_hash'], $empreinte)) {
            self::$selecteurReconnu = $selecteur;
            self::tourner((int) $ligne['id'], $selecteur, (string) $ligne['validator_hash']);
            return (int) $ligne['user_id'];
        }

        // Verificateur precedent, juste apres une rotation : requetes menees en
        // parallele par le meme navigateur. Accepte sans nouvelle rotation.
        if (!empty($ligne['previous_validator_hash'])
            && $tourneIlYA <= self::TOLERANCE_ROTATION
            && hash_equals((string) $ligne['previous_validator_hash'], $empreinte)) {
            self::$selecteurReconnu = $selecteur;
            self::marquerUsage((int) $ligne['id']);
            return (int) $ligne['user_id'];
        }

        // Encore dans la fenetre de tolerance : valeur depassee par deux
        // rotations successives. Refus, sans accuser personne.
        if ($tourneIlYA <= self::TOLERANCE_ROTATION) {
            return null;
        }

        // Le selecteur existe, mais le verificateur est perime : le cookie a
        // ete copie, et la personne legitime s'est reconnectee depuis. Tous
        // les jetons du compte tombent.
        $revoques = self::revoquerTout((int) $ligne['user_id']);
        self::supprimerCookie();
        self::signaler(sprintf(
            'verificateur perime pour l\'utilisateur %d (selecteur %s) : %d jeton(s) revoque(s)',
            (int) $ligne['user_id'],
            substr($selecteur, 0, 8) . '...',
            $revoques
        ));

        return null;
    }

    /**
     * Rattache le jeton reconnu a la session qui vient d'etre ouverte.
     *
     * A appeler apres une connexion automatique : la session a change
     * d'identifiant depuis la verification du cookie. Sans ce rattachement,
     * fermer la session depuis l'ecran « Appareils connectes » laisserait
     * l'appareil se reconnecter a la requete suivante.
     */
    public static function associerSessionCourante(): void
    {
        if (self::$selecteurReconnu === null || session_id() === '') {
            return;
        }

        $db = self::db();
        if (!$db) {
            return;
        }

        try {
            $db->prepare('UPDATE remember_tokens SET session_id = ? WHERE selector = ?')
               ->execute([session_id(), self::$selecteurReconnu]);
        } catch (Throwable $e) {
            self::signaler('association impossible : ' . $e->getMessage());
        }
    }

    /**
     * Revoque les jetons rattaches a une session, designee par l'empreinte
     * SHA-256 de son identifiant (ecran « Appareils connectes »).
     */
    public static function revoquerParSession(string $empreinteSession, int $userId): int
    {
        $db = self::db();
        if (!$db || !ctype_xdigit($empreinteSession) || strlen($empreinteSession) !== 64) {
            return 0;
        }

        try {
            $stmt = $db->prepare(
                'UPDATE remember_tokens SET revoked_at = NOW()
                 WHERE user_id = ? AND revoked_at IS NULL AND session_id IS NOT NULL AND SHA2(session_id, 256) = ?'
            );
            $stmt->execute([$userId, $empreinteSession]);

            return $stmt->rowCount();
        } catch (Throwable $e) {
            self::signaler('revocation par session impossible : ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Revoque le jeton de l'appareil courant et retire le cookie.
     * Utilise a la deconnexion : les autres appareils ne sont pas touches.
     */
    public static function oublierAppareilCourant(): void
    {
        $couple = self::lireCookie();
        if ($couple !== null) {
            $db = self::db();
            if ($db) {
                try {
                    $db->prepare('UPDATE remember_tokens SET revoked_at = NOW() WHERE selector = ? AND revoked_at IS NULL')
                       ->execute([$couple[0]]);
                } catch (Throwable $e) {
                    self::signaler('revocation impossible : ' . $e->getMessage());
                }
            }
        }

        if (isset($_COOKIE[self::COOKIE])) {
            self::supprimerCookie();
        }
    }

    /**
     * Revoque tous les jetons d'un compte. Renvoie le nombre de jetons touches.
     */
    public static function revoquerTout(int $userId): int
    {
        $db = self::db();
        if (!$db) {
            return 0;
        }

        try {
            $stmt = $db->prepare('UPDATE remember_tokens SET revoked_at = NOW() WHERE user_id = ? AND revoked_at IS NULL');
            $stmt->execute([$userId]);

            return $stmt->rowCount();
        } catch (Throwable $e) {
            self::signaler('revocation globale impossible : ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Revoque un jeton precis du compte indique (ecran « Appareils »).
     */
    public static function revoquerUn(int $tokenId, int $userId): bool
    {
        $db = self::db();
        if (!$db) {
            return false;
        }

        try {
            $stmt = $db->prepare('UPDATE remember_tokens SET revoked_at = NOW() WHERE id = ? AND user_id = ? AND revoked_at IS NULL');
            $stmt->execute([$tokenId, $userId]);

            return $stmt->rowCount() > 0;
        } catch (Throwable $e) {
            self::signaler('revocation impossible : ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Jetons valides d'un compte, du plus recemment utilise au plus ancien.
     * La cle « courant » indique l'appareil depuis lequel la page est vue.
     */
    public static function lister(int $userId): array
    {
        $db = self::db();
        if (!$db) {
            return [];
        }

        $couple = self::lireCookie();
        $selecteurCourant = $couple !== null ? $couple[0] : null;

        try {
            $stmt = $db->prepare(
                'SELECT id, selector, device_label, ip_address, created_at, last_used_at, expires_at
                 FROM remember_tokens
                 WHERE user_id = ? AND revoked_at IS NULL AND expires_at > NOW()
                 ORDER BY last_used_at DESC, created_at DESC'
            );
            $stmt->execute([$userId]);
            $lignes = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            self::signaler('lecture impossible : ' . $e->getMessage());
            return [];
        }

        foreach ($lignes as &$ligne) {
            $ligne['courant'] = $selecteurCourant !== null && hash_equals((string) $ligne['selector'], $selecteurCourant);
            unset($ligne['selector']);
        }

        return $lignes;
    }

    // ------------------------------------------------------------------
    // Interne
    // ------------------------------------------------------------------

    /**
     * Nouveau verificateur, l'ancien restant accepte quelques secondes.
     */
    private static function tourner(int $id, string $selecteur, string $ancienneEmpreinte): void
    {
        $db = self::db();
        if (!$db) {
            return;
        }

        $verificateur = bin2hex(random_bytes(32));
        $expire = time() + self::duree();

        try {
            $db->prepare(
                'UPDATE remember_tokens
                 SET validator_hash = ?, previous_validator_hash = ?, rotated_at = NOW(),
                     last_used_at = NOW(), expires_at = ?, ip_address = ?
                 WHERE id = ?'
            )->execute([
                hash('sha256', $verificateur),
                $ancienneEmpreinte,
                date('Y-m-d H:i:s', $expire),
                self::adresseClient(),
                $id,
            ]);
        } catch (Throwable $e) {
            self::signaler('rotation impossible : ' . $e->getMessage());
            return;
        }

        self::poserCookie($selecteur . ':' . $verificateur, $expire);
    }

    private static function marquerUsage(int $id): void
    {
        $db = self::db();
        if (!$db) {
            return;
        }

        try {
            $db->prepare('UPDATE remember_tokens SET last_used_at = NOW() WHERE id = ?')->execute([$id]);
        } catch (Throwable $e) {
            self::signaler('mise a jour impossible : ' . $e->getMessage());
        }
    }

    private static function revoquerLigne(int $id, string $motif): void
    {
        $db = self::db();
        if (!$db) {
            return;
        }

        try {
            $db->prepare('UPDATE remember_tokens SET revoked_at = NOW() WHERE id = ?')->execute([$id]);
        } catch (Throwable $e) {
            self::signaler("revocation ($motif) impossible : " . $e->getMessage());
        }
    }

    /**
     * Retire les jetons expires ou revoques depuis plus de trente jours.
     * Appele a la creation d'un jeton, pas a chaque requete.
     */
    private static function nettoyer(): void
    {
        $db = self::db();
        if (!$db) {
            return;
        }

        try {
            $db->exec(
                'DELETE FROM remember_tokens
                 WHERE expires_at < (NOW() - INTERVAL 30 DAY)
                    OR (revoked_at IS NOT NULL AND revoked_at < (NOW() - INTERVAL 30 DAY))'
            );
        } catch (Throwable $e) {
            self::signaler('nettoyage impossible : ' . $e->getMessage());
        }
    }

    /**
     * @return array{0:string,1:string}|null selecteur et verificateur
     */
    private static function lireCookie(): ?array
    {
        $valeur = $_COOKIE[self::COOKIE] ?? '';
        if (!is_string($valeur) || strlen($valeur) !== 97 || substr_count($valeur, ':') !== 1) {
            return null;
        }

        [$selecteur, $verificateur] = explode(':', $valeur, 2);
        if (!ctype_xdigit($selecteur) || !ctype_xdigit($verificateur)
            || strlen($selecteur) !== 32 || strlen($verificateur) !== 64) {
            return null;
        }

        return [$selecteur, $verificateur];
    }

    private static function poserCookie(string $valeur, int $expire): void
    {
        if (headers_sent()) {
            return;
        }

        setcookie(self::COOKIE, $valeur, [
            'expires'  => $expire,
            'path'     => '/',
            'secure'   => class_exists('EnvLoader') ? EnvLoader::bool('SESSION_SECURE', true) : true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[self::COOKIE] = $valeur;
    }

    private static function supprimerCookie(): void
    {
        unset($_COOKIE[self::COOKIE]);

        if (headers_sent()) {
            return;
        }

        setcookie(self::COOKIE, '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => class_exists('EnvLoader') ? EnvLoader::bool('SESSION_SECURE', true) : true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private static function db(): ?PDO
    {
        $db = TchadokDatabase::getInstance()->getConnection();
        if (!$db) {
            return null;
        }

        // Tant que la migration SEC-11 n'est pas appliquee, la connexion
        // automatique est simplement inactive : mieux vaut demander un mot de
        // passe qu'echouer sur chaque page.
        if (self::$tableDisponible === null) {
            try {
                $db->query('SELECT 1 FROM remember_tokens LIMIT 1');
                self::$tableDisponible = true;
            } catch (Throwable $e) {
                self::$tableDisponible = false;
                self::signaler('table remember_tokens absente : connexion automatique inactive');
            }
        }

        return self::$tableDisponible ? $db : null;
    }

    private static function adresseClient(): string
    {
        // SEC-13 : clientIp() n'accorde de credit a X-Forwarded-For que
        // derriere un proxy declare. Ailleurs, REMOTE_ADDR seule.
        return substr(function_exists('clientIp') ? clientIp() : (string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    }

    private static function signaler(string $message): void
    {
        error_log('[Tchadok][souvenir] ' . $message);
    }
}
