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

require_once __DIR__ . '/reponse-refus.php';

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
                function_exists('clientIp') ? clientIp() : ($_SERVER['REMOTE_ADDR'] ?? '-')
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
        return ReponseRefus::chemin();
    }

    private static function refuserTaille(): never
    {
        $max = (string) ini_get('post_max_size');
        error_log(sprintf('[Tchadok][csrf] requete au-dela de post_max_size (%s) : %s', $max, self::chemin()));
        ReponseRefus::envoyer(
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
            ReponseRefus::envoyer(
                403,
                'Session expiree',
                "Pour votre securite, ce formulaire n'a pas pu etre envoye : la page est restee ouverte "
                . "trop longtemps, ou elle provient d'un autre site. Rechargez la page et recommencez.",
                'csrf'
            );
        }
        error_log('[Tchadok][csrf] ' . $motif);
        ReponseRefus::envoyer($code, 'Requete refusee', 'La requete n\'a pas pu etre traitee.');
    }
}
