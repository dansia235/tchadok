<?php
/**
 * Envoi des courriels (QA-02, prealable a MOD-07).
 *
 * MAIL_DRIVER :
 *   log   developpement : le message est ecrit dans storage/logs/mail.log ;
 *   smtp  production : transport SMTP authentifie, identifiants fournis par
 *         MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD, MAIL_ENCRYPTION
 *         (tls = STARTTLS, port 587 ; ssl = port 465 ; vide = aucun, a
 *         reserver aux tests) ;
 *   mail  fonction mail() de PHP (deconseille : ni authentification, ni
 *         chiffrement garantis).
 *
 * Le message part en multipart/alternative (texte brut + HTML), encode en
 * base64, sujet encode en UTF-8 : les accents arrivent intacts. Le mot de
 * passe SMTP n'apparait jamais dans les journaux.
 */

declare(strict_types=1);

final class Courriel
{
    /** Derniere erreur (sans secret), pour le diagnostic. */
    public static string $derniereErreur = '';

    public static function envoyer(string $a, string $sujet, string $html): bool
    {
        $pilote = strtolower((string) EnvLoader::get('MAIL_DRIVER', 'mail'));
        if ($pilote === 'log') {
            return self::journaliser($a, $sujet, $html);
        }
        if ($pilote === 'smtp') {
            return self::envoyerSmtp($a, $sujet, $html, [
                'hote' => (string) EnvLoader::get('MAIL_HOST', ''),
                'port' => (int) EnvLoader::get('MAIL_PORT', '587'),
                'utilisateur' => (string) EnvLoader::get('MAIL_USERNAME', ''),
                'mot_de_passe' => (string) EnvLoader::get('MAIL_PASSWORD', ''),
                'chiffrement' => strtolower((string) EnvLoader::get('MAIL_ENCRYPTION', 'tls')),
            ]);
        }
        [$entetes, $corps] = self::composer($a, $sujet, $html, false);
        return mail($a, self::sujetEncode($sujet), $corps, $entetes);
    }

    /**
     * @param array{hote:string, port:int, utilisateur:string, mot_de_passe:string, chiffrement:string} $config
     */
    public static function envoyerSmtp(string $a, string $sujet, string $html, array $config): bool
    {
        self::$derniereErreur = '';
        if ($config['hote'] === '' || !filter_var($a, FILTER_VALIDATE_EMAIL)) {
            return self::echec('configuration SMTP incomplete ou destinataire invalide');
        }
        $contexte = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        $adresse = ($config['chiffrement'] === 'ssl' ? 'ssl://' : 'tcp://') . $config['hote'] . ':' . $config['port'];
        $flux = @stream_socket_client($adresse, $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $contexte);
        if (!$flux) {
            return self::echec("connexion impossible a {$config['hote']}:{$config['port']} ({$errstr})");
        }
        stream_set_timeout($flux, 15);
        try {
            self::attendre($flux, 220);
            $domaine = parse_url((string) (defined('SITE_URL') ? SITE_URL : 'http://localhost'), PHP_URL_HOST) ?: 'localhost';
            self::commande($flux, 'EHLO ' . $domaine, 250);
            if ($config['chiffrement'] === 'tls') {
                self::commande($flux, 'STARTTLS', 220);
                if (!stream_socket_enable_crypto($flux, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                    throw new RuntimeException('negociation TLS impossible');
                }
                self::commande($flux, 'EHLO ' . $domaine, 250);
            }
            if ($config['utilisateur'] !== '') {
                self::commande($flux, 'AUTH LOGIN', 334);
                self::commande($flux, base64_encode($config['utilisateur']), 334);
                self::commande($flux, base64_encode($config['mot_de_passe']), 235, true);
            }
            $expediteur = (string) EnvLoader::get('MAIL_FROM_ADDRESS', defined('SITE_EMAIL') ? SITE_EMAIL : 'noreply@localhost');
            self::commande($flux, 'MAIL FROM:<' . $expediteur . '>', 250);
            self::commande($flux, 'RCPT TO:<' . $a . '>', [250, 251]);
            self::commande($flux, 'DATA', 354);
            [$entetes, $corps] = self::composer($a, $sujet, $html, true);
            // Un point seul en debut de ligne terminerait le message.
            $donnees = preg_replace('/^\./m', '..', $entetes . "\r\n\r\n" . $corps);
            fwrite($flux, $donnees . "\r\n.\r\n");
            self::attendre($flux, 250);
            self::commande($flux, 'QUIT', 221);
            return true;
        } catch (Throwable $e) {
            return self::echec($e->getMessage());
        } finally {
            fclose($flux);
        }
    }

    /** @return array{0:string, 1:string} entetes et corps */
    private static function composer(string $a, string $sujet, string $html, bool $avecSujetEtDestinataire): array
    {
        $frontiere = 'tchadok-' . bin2hex(random_bytes(12));
        $nom = (string) EnvLoader::get('MAIL_FROM_NAME', defined('SITE_NAME') ? SITE_NAME : 'Tchadok');
        $adresse = (string) EnvLoader::get('MAIL_FROM_ADDRESS', defined('SITE_EMAIL') ? SITE_EMAIL : 'noreply@localhost');
        $domaine = substr((string) strrchr($adresse, '@'), 1) ?: 'localhost';
        $entetes = [
            'From: =?UTF-8?B?' . base64_encode($nom) . '?= <' . $adresse . '>',
            'Reply-To: ' . $adresse,
            'Date: ' . date('r'),
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $domaine . '>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $frontiere . '"',
        ];
        if ($avecSujetEtDestinataire) {
            array_unshift($entetes, 'To: <' . $a . '>', 'Subject: ' . self::sujetEncode($sujet));
        }
        $texte = trim(html_entity_decode(strip_tags((string) preg_replace('/<(br|\/p|\/li|\/h\d)\s*\/?>/i', "\n", $html)), ENT_QUOTES, 'UTF-8'));
        $corps = "--{$frontiere}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . rtrim(chunk_split(base64_encode($texte))) . "\r\n"
            . "--{$frontiere}\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . rtrim(chunk_split(base64_encode($html))) . "\r\n--{$frontiere}--";
        return [implode("\r\n", $entetes), $corps];
    }

    private static function sujetEncode(string $sujet): string
    {
        return '=?UTF-8?B?' . base64_encode($sujet) . '?=';
    }

    /** @param resource $flux */
    private static function commande($flux, string $ligne, int|array $attendu, bool $secret = false): string
    {
        fwrite($flux, $ligne . "\r\n");
        try {
            return self::attendre($flux, $attendu);
        } catch (RuntimeException $e) {
            // Jamais le mot de passe (meme encode) dans un message d'erreur.
            throw new RuntimeException($secret ? 'authentification SMTP refusee' : $e->getMessage());
        }
    }

    /** @param resource $flux */
    private static function attendre($flux, int|array $attendu): string
    {
        $reponse = '';
        while (($ligne = fgets($flux, 1024)) !== false) {
            $reponse .= $ligne;
            if (strlen($ligne) < 4 || $ligne[3] !== '-') {
                break;
            }
        }
        $code = (int) substr($reponse, 0, 3);
        if (!in_array($code, (array) $attendu, true)) {
            throw new RuntimeException('reponse SMTP inattendue : ' . trim(substr($reponse, 0, 200)));
        }
        return $reponse;
    }

    private static function journaliser(string $a, string $sujet, string $html): bool
    {
        $dossier = dirname(__DIR__) . '/storage/logs';
        if (!is_dir($dossier)) {
            @mkdir($dossier, 0775, true);
        }
        $entree = sprintf("==== %s ====\nA : %s\nObjet : %s\n\n%s\n\n", date('c'), $a, $sujet, $html);
        return @file_put_contents($dossier . '/mail.log', $entree, FILE_APPEND | LOCK_EX) !== false;
    }

    private static function echec(string $raison): bool
    {
        self::$derniereErreur = $raison;
        error_log('[Tchadok][courriel] envoi impossible : ' . $raison);
        return false;
    }
}
