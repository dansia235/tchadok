<?php
/**
 * Verification des comptes : e-mail (MOD-07) et telephone (MOD-06, etape 1).
 *
 * E-MAIL
 *   A l'inscription, un lien a usage unique, valable 48 h. Le jeton n'est
 *   conserve qu'en empreinte. Renvoi possible, pas plus d'une fois toutes les
 *   2 minutes. Un compte non verifie ne peut ni acheter, ni publier, ni
 *   commenter (Comptes::exigerEmailVerifie, appele par ces pages).
 *
 * TELEPHONE
 *   Code a 6 chiffres, valable 10 minutes, conserve en empreinte. Transport :
 *   SMS_DRIVER=log (developpement, storage/logs/sms.log) ; le fournisseur SMS
 *   de production reste a choisir (SMS_DRIVER=aucun refuse l'envoi).
 */

declare(strict_types=1);

final class Comptes
{
    private const VIE_LIEN = 48 * 3600;
    private const DELAI_RENVOI = 120;
    private const VIE_CODE = 600;

    // -----------------------------------------------------------------
    // E-mail
    // -----------------------------------------------------------------

    public static function emailVerifie(int $userId): bool
    {
        $stmt = self::base()->prepare('SELECT email_verified FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn() === 1;
    }

    /**
     * Envoie (ou renvoie) le lien de verification.
     *
     * @return array{succes:bool, message:string}
     */
    public static function envoyerVerification(int $userId): array
    {
        $db = self::base();
        $stmt = $db->prepare('SELECT email, first_name, email_verified, verification_sent_at FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$u) {
            return ['succes' => false, 'message' => 'Compte introuvable.'];
        }
        if ((int) $u['email_verified'] === 1) {
            return ['succes' => false, 'message' => 'Votre adresse est deja verifiee.'];
        }
        if ($u['verification_sent_at'] !== null && strtotime((string) $u['verification_sent_at']) > time() - self::DELAI_RENVOI) {
            return ['succes' => false, 'message' => 'Un lien vient d\'etre envoye. Patientez deux minutes avant d\'en demander un autre.'];
        }
        $jeton = bin2hex(random_bytes(32));
        $db->prepare('UPDATE users SET verification_token = ?, verification_expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND), verification_sent_at = NOW() WHERE id = ?')
           ->execute([hash('sha256', $jeton), self::VIE_LIEN, $userId]);
        $lien = SITE_URL . '/verifier-email.php?jeton=' . $jeton;
        $envoye = sendEmail((string) $u['email'], 'Tchadok - confirmez votre adresse e-mail',
            '<p>Bonjour ' . htmlspecialchars((string) $u['first_name'], ENT_QUOTES, 'UTF-8') . ',</p>'
            . '<p>Confirmez votre adresse pour acheter, publier et commenter sur Tchadok :</p>'
            . '<p><a href="' . htmlspecialchars($lien, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($lien, ENT_QUOTES, 'UTF-8') . '</a></p>'
            . '<p>Ce lien est valable 48 heures. Si vous n\'avez pas cree de compte, ignorez ce message.</p>');
        return $envoye ? ['succes' => true, 'message' => 'Un lien de confirmation vient d\'etre envoye a ' . $u['email'] . '.']
                       : ['succes' => false, 'message' => 'L\'envoi a echoue. Reessayez dans quelques minutes.'];
    }

    /** @return array{succes:bool, message:string, user_id:?int} */
    public static function verifier(string $jeton): array
    {
        $refus = ['succes' => false, 'message' => 'Lien invalide ou deja utilise.', 'user_id' => null];
        if (!preg_match('/^[a-f0-9]{64}$/', $jeton)) {
            return $refus;
        }
        $db = self::base();
        $stmt = $db->prepare('SELECT id, verification_expires_at < NOW() AS expire FROM users WHERE verification_token = ?');
        $stmt->execute([hash('sha256', $jeton)]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$u) {
            return $refus;
        }
        if ((int) $u['expire'] === 1) {
            return ['succes' => false, 'message' => 'Ce lien a expire. Demandez-en un nouveau depuis votre compte.', 'user_id' => (int) $u['id']];
        }
        $db->prepare('UPDATE users SET email_verified = 1, email_verified_at = NOW(), verification_token = NULL, verification_expires_at = NULL WHERE id = ?')
           ->execute([$u['id']]);
        return ['succes' => true, 'message' => 'Adresse e-mail confirmee. Merci !', 'user_id' => (int) $u['id']];
    }

    /**
     * A appeler en tete des pages d'achat, de publication et de commentaire :
     * un compte non verifie est renvoye vers la page de verification.
     */
    public static function exigerEmailVerifie(string $retour): void
    {
        if (isset($_SESSION['user_id']) && !self::emailVerifie((int) $_SESSION['user_id'])) {
            redirect(SITE_URL . '/verifier-email.php?retour=' . urlencode($retour));
        }
    }

    // -----------------------------------------------------------------
    // Telephone
    // -----------------------------------------------------------------

    public static function telephoneVerifie(int $userId): bool
    {
        $stmt = self::base()->prepare('SELECT phone_verified_at IS NOT NULL FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        return (bool) $stmt->fetchColumn();
    }

    /** @return array{succes:bool, message:string} */
    public static function envoyerCodeTelephone(int $userId, string $numero): array
    {
        $chiffres = preg_replace('/\D/', '', $numero) ?? '';
        if (str_starts_with($chiffres, '235')) {
            $chiffres = substr($chiffres, 3);
        }
        if (!preg_match('/^[679]\d{7}$/', $chiffres)) {
            return ['succes' => false, 'message' => 'Numero tchadien invalide (8 chiffres).'];
        }
        $db = self::base();
        $stmt = $db->prepare('SELECT phone_code_expires_at FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $expire = $stmt->fetchColumn();
        if ($expire && strtotime((string) $expire) - self::VIE_CODE > time() - 60) {
            return ['succes' => false, 'message' => 'Un code vient d\'etre envoye. Patientez une minute.'];
        }
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $db->prepare('UPDATE users SET phone = ?, phone_verified_at = NULL, phone_code_hash = ?, phone_code_expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND) WHERE id = ?')
           ->execute(['+235' . $chiffres, hash_hmac('sha256', $code, (string) env('APP_KEY', 'tchadok')), self::VIE_CODE, $userId]);
        if (!self::envoyerSms('+235' . $chiffres, 'Tchadok : votre code de verification est ' . $code . '. Il expire dans 10 minutes.')) {
            return ['succes' => false, 'message' => 'Envoi du SMS impossible pour le moment.'];
        }
        return ['succes' => true, 'message' => 'Code envoye par SMS au +235 ' . $chiffres . '.'];
    }

    /** @return array{succes:bool, message:string} */
    public static function verifierCodeTelephone(int $userId, string $code): array
    {
        $stmt = self::base()->prepare('SELECT phone_code_hash, phone_code_expires_at < NOW() AS expire FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$u || $u['phone_code_hash'] === null) {
            return ['succes' => false, 'message' => 'Demandez d\'abord un code.'];
        }
        if ((int) $u['expire'] === 1) {
            return ['succes' => false, 'message' => 'Code expire : demandez-en un nouveau.'];
        }
        if (!hash_equals((string) $u['phone_code_hash'], hash_hmac('sha256', trim($code), (string) env('APP_KEY', 'tchadok')))) {
            return ['succes' => false, 'message' => 'Code incorrect.'];
        }
        self::base()->prepare('UPDATE users SET phone_verified_at = NOW(), phone_code_hash = NULL, phone_code_expires_at = NULL WHERE id = ?')->execute([$userId]);
        return ['succes' => true, 'message' => 'Telephone verifie.'];
    }

    private static function envoyerSms(string $numero, string $texte): bool
    {
        $pilote = strtolower((string) EnvLoader::get('SMS_DRIVER', 'log'));
        if ($pilote === 'log') {
            $dossier = dirname(__DIR__) . '/storage/logs';
            @mkdir($dossier, 0775, true);
            return @file_put_contents($dossier . '/sms.log', sprintf("==== %s ====\nA : %s\n%s\n\n", date('c'), $numero, $texte), FILE_APPEND | LOCK_EX) !== false;
        }
        error_log('[Tchadok][sms] aucun fournisseur SMS configure (SMS_DRIVER=' . $pilote . ')');
        return false;
    }

    private static function base(): PDO
    {
        return TchadokDatabase::getInstance()->getConnection();
    }
}
