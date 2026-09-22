<?php
/**
 * Protection CSRF centralisee - Tchadok Platform
 *
 * Tache SEC-09.
 *
 * AVANT
 *   22 points d'entree traitaient des requetes POST ; 4 seulement verifiaient
 *   un jeton. Quatre API annoncaient l'en-tete X-CSRF-Token dans leurs regles
 *   CORS sans jamais le verifier. Une page tierce pouvait donc faire soumettre
 *   au navigateur d'un utilisateur connecte n'importe quel formulaire du site :
 *   publier un titre, modifier un profil, lancer un paiement, agir en
 *   administrateur.
 *
 * APRES
 *   Toute requete modifiante (POST, PUT, PATCH, DELETE) est verifiee ICI, avant
 *   que le point d'entree ne s'execute. La protection est active PAR DEFAUT :
 *   un formulaire ajoute demain sans jeton echoue, il ne passe pas en silence.
 *
 * EXEMPTION
 *   Seuls les points d'entree qui ne peuvent pas porter de jeton de session --
 *   typiquement les callbacks des operateurs de paiement (PAY-04), proteges
 *   par signature HMAC -- peuvent s'en dispenser, en le declarant AVANT
 *   d'inclure includes/functions.php :
 *
 *       define('TCHADOK_CSRF_EXEMPT', 'callback Airtel Money, signe HMAC');
 *
 *   La raison est obligatoire et chaque requete exemptee est journalisee.
 *
 * CODE DE REFUS : 403
 *   Le plan prevoyait 419 (convention de Laravel, "Page Expired"). Ce code
 *   n'existe pas dans la table des statuts d'Apache, qui le transforme en
 *   500 : le refus serait alors indistinguable d'une panne. 403 est le code
 *   standard pour un refus CSRF (c'est celui de Django). Le message affiche
 *   distingue le cas d'un simple refus d'autorisation.
 *
 * SOURCES DU JETON, dans cet ordre :
 *   1. en-tete X-CSRF-Token      (appels JavaScript)
 *   2. champ POST csrf_token     (formulaires HTML, via csrfField())
 *   3. cle csrf_token d'un corps JSON
 */

declare(strict_types=1);

final class CsrfGuard
{
    private const METHODES_SURES = ['GET', 'HEAD', 'OPTIONS'];

    public static function verifier(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        $methode = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if (in_array($methode, self::METHODES_SURES, true)) {
            return;
        }

        if (defined('TCHADOK_CSRF_EXEMPT')) {
            $raison = trim((string) TCHADOK_CSRF_EXEMPT);
            if ($raison === '' || $raison === '1') {
                // Une exemption sans justification est traitee comme une erreur
                // de developpement : on refuse plutot que de laisser passer.
                error_log('[Tchadok][csrf] exemption sans raison sur ' . self::chemin() . ' : refusee');
                self::refuser(500, 'exemption CSRF non justifiee');
            }
            error_log(sprintf('[Tchadok][csrf] exemption (%s) : %s %s', $raison, $methode, self::chemin()));
            return;
        }

        // Televersement trop volumineux : PHP vide alors $_POST ET $_FILES, le
        // jeton disparait avec. Sans ce controle, l'utilisateur recevrait
        // "session expiree" au lieu de "fichier trop volumineux".
        if (self::depassePostMaxSize()) {
            self::refuserTaille();
        }

        $attendu = (string) ($_SESSION['csrf_token'] ?? '');
        $recu    = self::jetonRecu();

        if ($attendu === '' || $recu === '' || !hash_equals($attendu, $recu)) {
            error_log(sprintf(
                '[Tchadok][csrf] refus %s %s (jeton %s, ip=%s)',
                $methode,
                self::chemin(),
                $recu === '' ? 'absent' : 'invalide',
                $_SERVER['REMOTE_ADDR'] ?? '-'
            ));
            self::refuser(403, $recu === '' ? 'jeton absent' : 'jeton invalide');
        }
    }

    /**
     * Nouveau jeton apres un changement de privilege (connexion, deconnexion,
     * changement de role). Un jeton obtenu avant la connexion ne doit pas
     * rester valide apres.
     */
    public static function renouveler(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    private static function jetonRecu(): string
    {
        $entete = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if ($entete !== '') {
            return $entete;
        }

        if (isset($_POST['csrf_token']) && is_string($_POST['csrf_token'])) {
            return $_POST['csrf_token'];
        }

        $type = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
        if (str_contains($type, 'application/json')) {
            // php://input se relit : le point d'entree pourra le lire a son tour.
            $corps = json_decode((string) file_get_contents('php://input'), true);
            if (is_array($corps) && isset($corps['csrf_token']) && is_string($corps['csrf_token'])) {
                return $corps['csrf_token'];
            }
        }

        return '';
    }

    private static function depassePostMaxSize(): bool
    {
        $longueur = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($longueur <= 0 || !empty($_POST) || !empty($_FILES)) {
            return false;
        }
        $max = self::enOctets((string) ini_get('post_max_size'));
        return $max > 0 && $longueur > $max;
    }

    private static function enOctets(string $valeur): int
    {
        $valeur = trim($valeur);
        if ($valeur === '') {
            return 0;
        }
        $unite = strtolower(substr($valeur, -1));
        $nombre = (int) $valeur;
        return match ($unite) {
            'g'     => $nombre * 1024 ** 3,
            'm'     => $nombre * 1024 ** 2,
            'k'     => $nombre * 1024,
            default => $nombre,
        };
    }

    private static function chemin(): string
    {
        return (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    }

    private static function attendJson(): bool
    {
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        $type   = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
        return str_contains(self::chemin(), '/api/')
            || str_contains($accept, 'application/json')
            || str_contains($type, 'application/json')
            || strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    }

    // -----------------------------------------------------------------
    // Reponses
    // -----------------------------------------------------------------

    private static function refuserTaille(): never
    {
        $max = (string) ini_get('post_max_size');
        error_log(sprintf('[Tchadok][csrf] requete au-dela de post_max_size (%s) : %s', $max, self::chemin()));
        self::repondre(
            413,
            'Fichier trop volumineux',
            "L'envoi depasse la taille maximale autorisee ({$max}). Reduisez la taille du fichier et reessayez."
        );
    }

    /**
     * @param string $motif detail journalise ; seule la categorie "csrf" est
     *                      exposee au client, pour qu'un appel JavaScript
     *                      distingue ce refus d'un refus d'autorisation et
     *                      propose de recharger la page.
     */
    private static function refuser(int $code, string $motif): never
    {
        if ($code === 403) {
            self::repondre(
                403,
                'Session expiree',
                "Pour votre securite, ce formulaire n'a pas pu etre envoye : la page est restee ouverte "
                . "trop longtemps, ou elle provient d'un autre site. Rechargez la page et recommencez.",
                'csrf'
            );
        }
        error_log('[Tchadok][csrf] ' . $motif);
        self::repondre($code, 'Requete refusee', 'La requete n\'a pas pu etre traitee.');
    }

    private static function repondre(int $code, string $titre, string $message, ?string $raison = null): never
    {
        if (!headers_sent()) {
            http_response_code($code);
            header('Cache-Control: no-store');
        }

        if (self::attendJson()) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            $erreur = ['code' => $code, 'message' => $message];
            if ($raison !== null) {
                $erreur['reason'] = $raison;
            }
            echo json_encode(['success' => false, 'error' => $erreur], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }

        $retour = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $hote   = (string) ($_SERVER['HTTP_HOST'] ?? '');
        // Lien de retour uniquement vers le site lui-meme : un Referer tiers
        // ne doit pas devenir une redirection ouverte.
        if ($retour === '' || parse_url($retour, PHP_URL_HOST) !== parse_url('http://' . $hote, PHP_URL_HOST)) {
            $retour = defined('SITE_URL') ? SITE_URL . '/' : '/';
        }

        echo '<!doctype html><html lang="fr"><head><meta charset="utf-8">'
           . '<meta name="viewport" content="width=device-width,initial-scale=1">'
           . '<title>' . htmlspecialchars($titre, ENT_QUOTES, 'UTF-8') . '</title>'
           . '<style>body{font:15px/1.6 system-ui,sans-serif;background:#0B0F17;color:#E6EAF2;margin:0;'
           . 'min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem}'
           . 'main{max-width:34rem;text-align:center}h1{font-size:1.5rem;margin:0 0 .75rem}'
           . 'p{color:#A4AEC2;margin:0 0 1.5rem}a{display:inline-block;background:#2F6DE0;color:#fff;'
           . 'text-decoration:none;padding:.7rem 1.4rem;border-radius:999px;font-weight:600}'
           . 'a:focus-visible{outline:3px solid #FFC107;outline-offset:3px}</style></head><body><main>'
           . '<h1>' . htmlspecialchars($titre, ENT_QUOTES, 'UTF-8') . '</h1>'
           . '<p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>'
           . '<a href="' . htmlspecialchars($retour, ENT_QUOTES, 'UTF-8') . '">Revenir a la page</a>'
           . '</main></body></html>';
        exit;
    }
}
