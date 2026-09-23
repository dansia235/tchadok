<?php
/**
 * Authentification a deux facteurs (SEC-20).
 *
 * TOTP, RFC 6238 : HMAC-SHA1 sur un pas de trente secondes, six chiffres.
 * Compatible avec les applications courantes (Google Authenticator, Authy,
 * FreeOTP, Aegis), sans service tiers.
 *
 * PAS DE QR CODE POUR L'INSTANT, ET C'EST DELIBERE
 *   L'ecran retire en SEC-14 faisait fabriquer le QR code par api.qrserver.com
 *   -- en transmettant le secret a un tiers, ce qui annule l'interet du second
 *   facteur. Aucun encodeur QR n'est disponible localement (pas de gestionnaire
 *   de dependances sur ce projet). La cle est donc affichee en clair, par
 *   groupes de quatre, avec l'URI otpauth:// : toutes les applications
 *   acceptent la saisie manuelle. Le QR reviendra avec un encodeur servi par
 *   le site lui-meme.
 *
 * SECRET CHIFFRE
 *   AES-256-GCM, cle derivee de APP_KEY par HKDF. Une base volee ne doit pas
 *   livrer les seconds facteurs : sinon, le vol de la base suffirait a se
 *   faire passer pour n'importe quel administrateur.
 *
 * REJEU
 *   Un code vaut trente secondes ; rien n'empeche de le rejouer dans cet
 *   intervalle. Le dernier pas accepte est memorise, et tout pas inferieur ou
 *   egal est refuse : un code capte ne sert qu'une fois.
 */

declare(strict_types=1);

final class DeuxFacteurs
{
    /** Longueur du secret, en caracteres base32 (160 bits). */
    private const LONGUEUR_SECRET = 32;

    /** Duree d'un pas, en secondes. */
    private const PAS = 30;

    /** Nombre de chiffres du code. */
    private const CHIFFRES = 6;

    /** Pas acceptes de part et d'autre : tolere une horloge un peu decalee. */
    private const TOLERANCE = 1;

    /** Nombre de codes de secours remis a l'activation. */
    public const CODES_DE_SECOURS = 10;

    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Permissions considerees comme des ecritures en administration. Un role
     * qui en detient une exige un second facteur (point 5 du plan).
     */
    private const PERMISSIONS_ECRITURE = [
        'configuration.gerer', 'role.gerer',
        'catalogue.moderer', 'catalogue.editer', 'catalogue.supprimer',
        'signalement.traiter', 'taxonomie.gerer', 'tarif.modifier',
        'editorial.gerer', 'editorial.mise-en-avant',
        'finance.versement.creer', 'finance.versement.executer',
        'finance.remboursement.executer',
        'compte.modifier', 'compte.supprimer', 'compte.reinitialiser-mot-de-passe',
        'ticket.traiter',
    ];

    private static ?bool $tablesDisponibles = null;

    // ------------------------------------------------------------------
    // Etat
    // ------------------------------------------------------------------

    public static function estActive(?int $userId = null): bool
    {
        $userId = $userId ?? (int) ($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            return false;
        }

        $reglages = self::reglages($userId);

        return $reglages !== null && !empty($reglages['enabled_at']);
    }

    /**
     * Le second facteur est-il exige pour ce compte ?
     *
     * Vrai si le compte detient une permission d'ecriture en administration,
     * et si l'exigence est activee (ADMIN_2FA_REQUIRED). En local, elle est
     * laissee a false pour ne pas imposer un telephone a chaque essai ; en
     * production, elle doit etre a true.
     */
    public static function exigee(?int $userId = null): bool
    {
        if (!self::exigenceActivee()) {
            return false;
        }

        $userId = $userId ?? (int) ($_SESSION['user_id'] ?? 0);
        if ($userId <= 0 || !class_exists('Autorisations')) {
            return false;
        }

        foreach (self::PERMISSIONS_ECRITURE as $permission) {
            if (Autorisations::peut($permission, $userId)) {
                return true;
            }
        }

        return false;
    }

    public static function exigenceActivee(): bool
    {
        return class_exists('EnvLoader') && EnvLoader::bool('ADMIN_2FA_REQUIRED', false);
    }

    /**
     * Codes de secours restants.
     */
    public static function codesDeSecoursRestants(int $userId): int
    {
        $db = self::db();
        if (!$db) {
            return 0;
        }

        try {
            $stmt = $db->prepare('SELECT COUNT(*) FROM user_backup_codes WHERE user_id = ? AND used_at IS NULL');
            $stmt->execute([$userId]);

            return (int) $stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }

    // ------------------------------------------------------------------
    // Activation
    // ------------------------------------------------------------------

    /**
     * Nouveau secret, a proposer pendant l'inscription du second facteur.
     */
    public static function genererSecret(): string
    {
        $secret = '';
        for ($i = 0; $i < self::LONGUEUR_SECRET; $i++) {
            $secret .= self::ALPHABET[random_int(0, 31)];
        }

        return $secret;
    }

    /**
     * URI otpauth:// a saisir dans l'application d'authentification.
     */
    public static function uriOtpauth(string $secret, string $compte): string
    {
        $editeur = defined('APP_NAME') ? APP_NAME : 'Tchadok';

        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=%d&period=%d',
            rawurlencode($editeur),
            rawurlencode($compte),
            $secret,
            rawurlencode($editeur),
            self::CHIFFRES,
            self::PAS
        );
    }

    /**
     * Secret presente par groupes de quatre : une cle de 32 caracteres se
     * recopie mal d'un bloc.
     */
    public static function secretLisible(string $secret): string
    {
        return trim(chunk_split($secret, 4, ' '));
    }

    /**
     * Active le second facteur apres verification d'un premier code.
     *
     * @return array{succes:bool, message:string, codes:string[]}
     */
    public static function activer(int $userId, string $secret, string $code): array
    {
        $db = self::db();
        if (!$db) {
            return ['succes' => false, 'message' => 'Fonction indisponible pour le moment.', 'codes' => []];
        }

        if (!preg_match('/^[A-Z2-7]{' . self::LONGUEUR_SECRET . '}$/', $secret)) {
            return ['succes' => false, 'message' => 'Cle invalide. Recommencez la configuration.', 'codes' => []];
        }

        // Le premier code prouve que l'application est bien configuree : sans
        // cette verification, on activerait une protection que la personne ne
        // pourrait pas franchir.
        if (self::pasCorrespondant($secret, $code) === null) {
            return ['succes' => false, 'message' => 'Code incorrect. Verifiez l\'heure de votre telephone et reessayez.', 'codes' => []];
        }

        try {
            $db->prepare(
                'REPLACE INTO user_2fa_settings (user_id, method, secret_chiffre, enabled_at, last_step)
                 VALUES (?, ?, ?, NOW(), NULL)'
            )->execute([$userId, 'totp', self::chiffrer($secret)]);

            $db->prepare('DELETE FROM user_backup_codes WHERE user_id = ?')->execute([$userId]);

            $codes = [];
            $insertion = $db->prepare('INSERT INTO user_backup_codes (user_id, code_hash) VALUES (?, ?)');
            for ($i = 0; $i < self::CODES_DE_SECOURS; $i++) {
                $codeSecours = self::genererCodeDeSecours();
                $codes[] = $codeSecours;
                $insertion->execute([$userId, self::empreinte($codeSecours)]);
            }
        } catch (Throwable $e) {
            error_log('[Tchadok][2fa] activation impossible : ' . $e->getMessage());
            return ['succes' => false, 'message' => 'Activation impossible pour le moment.', 'codes' => []];
        }

        if (class_exists('JournalAudit')) {
            JournalAudit::enregistrer('2fa.active', [
                'cible_type' => 'utilisateur',
                'cible_id'   => $userId,
                'acteur'     => $userId,
            ]);
        }

        return ['succes' => true, 'message' => 'Double authentification activee.', 'codes' => $codes];
    }

    /**
     * Desactive le second facteur.
     *
     * @param int|null $parQui auteur, si ce n'est pas la personne elle-meme
     *                         (recuperation par un super-administrateur)
     */
    public static function desactiver(int $userId, ?int $parQui = null, string $raison = ''): bool
    {
        $db = self::db();
        if (!$db) {
            return false;
        }

        try {
            $db->prepare('DELETE FROM user_2fa_settings WHERE user_id = ?')->execute([$userId]);
            $db->prepare('DELETE FROM user_backup_codes WHERE user_id = ?')->execute([$userId]);
        } catch (Throwable $e) {
            error_log('[Tchadok][2fa] desactivation impossible : ' . $e->getMessage());
            return false;
        }

        if (class_exists('JournalAudit')) {
            JournalAudit::enregistrer('2fa.desactive', [
                'cible_type' => 'utilisateur',
                'cible_id'   => $userId,
                'acteur'     => $parQui ?? $userId,
                'raison'     => $raison !== '' ? $raison : null,
            ]);
        }

        return true;
    }

    // ------------------------------------------------------------------
    // Verification
    // ------------------------------------------------------------------

    /**
     * Verifie un code TOTP et le consomme.
     */
    public static function verifierCode(int $userId, string $code): bool
    {
        $reglages = self::reglages($userId);
        if ($reglages === null || empty($reglages['enabled_at'])) {
            return false;
        }

        $secret = self::dechiffrer((string) $reglages['secret_chiffre']);
        if ($secret === null) {
            error_log('[Tchadok][2fa] secret illisible pour l\'utilisateur ' . $userId);
            return false;
        }

        $pas = self::pasCorrespondant($secret, $code);
        if ($pas === null) {
            return false;
        }

        // Protection contre le rejeu : un pas deja utilise ne revaut plus rien.
        $dernier = $reglages['last_step'] === null ? null : (int) $reglages['last_step'];
        if ($dernier !== null && $pas <= $dernier) {
            error_log('[Tchadok][2fa] code rejoue refuse pour l\'utilisateur ' . $userId);
            return false;
        }

        $db = self::db();
        if ($db) {
            try {
                $db->prepare('UPDATE user_2fa_settings SET last_step = ?, last_used_at = NOW() WHERE user_id = ?')
                   ->execute([$pas, $userId]);
            } catch (Throwable $e) {
                error_log('[Tchadok][2fa] ' . $e->getMessage());
            }
        }

        return true;
    }

    /**
     * Verifie un code de secours et le consomme definitivement.
     */
    public static function verifierCodeDeSecours(int $userId, string $code): bool
    {
        $db = self::db();
        if (!$db) {
            return false;
        }

        $empreinte = self::empreinte($code);

        try {
            $stmt = $db->prepare(
                'SELECT id FROM user_backup_codes WHERE user_id = ? AND code_hash = ? AND used_at IS NULL LIMIT 1'
            );
            $stmt->execute([$userId, $empreinte]);
            $id = $stmt->fetchColumn();

            if ($id === false) {
                return false;
            }

            // La condition sur used_at rejoue la verification : deux requetes
            // simultanees avec le meme code n'en consomment qu'une.
            $consommation = $db->prepare('UPDATE user_backup_codes SET used_at = NOW() WHERE id = ? AND used_at IS NULL');
            $consommation->execute([$id]);

            if ($consommation->rowCount() === 0) {
                return false;
            }
        } catch (Throwable $e) {
            error_log('[Tchadok][2fa] ' . $e->getMessage());
            return false;
        }

        if (class_exists('JournalAudit')) {
            JournalAudit::enregistrer('2fa.code-secours', [
                'cible_type' => 'utilisateur',
                'cible_id'   => $userId,
                'acteur'     => $userId,
                'raison'     => 'connexion par code de secours',
            ]);
        }

        return true;
    }

    // ------------------------------------------------------------------
    // TOTP
    // ------------------------------------------------------------------

    /**
     * Pas de temps correspondant au code, ou null si aucun ne correspond.
     */
    private static function pasCorrespondant(string $secret, string $code): ?int
    {
        $code = preg_replace('/\D/', '', $code) ?? '';
        if (strlen($code) !== self::CHIFFRES) {
            return null;
        }

        $pasCourant = intdiv(time(), self::PAS);

        for ($decalage = -self::TOLERANCE; $decalage <= self::TOLERANCE; $decalage++) {
            $pas = $pasCourant + $decalage;
            if (hash_equals(self::code($secret, $pas), $code)) {
                return $pas;
            }
        }

        return null;
    }

    /**
     * Code attendu pour un pas donne (RFC 6238).
     */
    public static function code(string $secret, int $pas): string
    {
        $cle = self::base32Decode($secret);
        $compteur = pack('N*', 0, $pas);          // entier 64 bits, gros-boutiste
        $empreinte = hash_hmac('sha1', $compteur, $cle, true);

        $decalage = ord($empreinte[19]) & 0x0F;
        $tronque = (
            ((ord($empreinte[$decalage])     & 0x7F) << 24) |
            ((ord($empreinte[$decalage + 1]) & 0xFF) << 16) |
            ((ord($empreinte[$decalage + 2]) & 0xFF) << 8)  |
             (ord($empreinte[$decalage + 3]) & 0xFF)
        );

        return str_pad((string) ($tronque % (10 ** self::CHIFFRES)), self::CHIFFRES, '0', STR_PAD_LEFT);
    }

    private static function base32Decode(string $secret): string
    {
        $secret = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $secret) ?? '');
        $bits = '';
        foreach (str_split($secret) as $caractere) {
            $position = strpos(self::ALPHABET, $caractere);
            if ($position === false) {
                continue;
            }
            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }

        $octets = '';
        foreach (str_split($bits, 8) as $morceau) {
            if (strlen($morceau) === 8) {
                $octets .= chr((int) bindec($morceau));
            }
        }

        return $octets;
    }

    // ------------------------------------------------------------------
    // Codes de secours et chiffrement
    // ------------------------------------------------------------------

    /**
     * Code de secours : dix caracteres sans ambiguite visuelle, en deux
     * groupes de cinq. Il sera recopie a la main, souvent depuis un papier.
     */
    private static function genererCodeDeSecours(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < 10; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return substr($code, 0, 5) . '-' . substr($code, 5);
    }

    private static function empreinte(string $code): string
    {
        $normalise = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');

        return hash('sha256', $normalise);
    }

    private static function cle(): string
    {
        $base = class_exists('EnvLoader') ? (string) EnvLoader::get('APP_KEY', '') : '';
        if ($base === '') {
            $base = (string) EnvLoader::get('SESSION_SECRET', 'tchadok');
        }

        return hash_hkdf('sha256', $base, 32, 'tchadok-2fa');
    }

    private static function chiffrer(string $secret): string
    {
        $iv = random_bytes(12);
        $marque = '';
        $chiffre = openssl_encrypt($secret, 'aes-256-gcm', self::cle(), OPENSSL_RAW_DATA, $iv, $marque);

        if ($chiffre === false) {
            throw new RuntimeException('chiffrement du secret impossible');
        }

        return base64_encode($iv . $marque . $chiffre);
    }

    private static function dechiffrer(string $stocke): ?string
    {
        $brut = base64_decode($stocke, true);
        if ($brut === false || strlen($brut) < 29) {
            return null;
        }

        $iv = substr($brut, 0, 12);
        $marque = substr($brut, 12, 16);
        $chiffre = substr($brut, 28);

        $secret = openssl_decrypt($chiffre, 'aes-256-gcm', self::cle(), OPENSSL_RAW_DATA, $iv, $marque);

        return $secret === false ? null : $secret;
    }

    // ------------------------------------------------------------------
    // Acces base
    // ------------------------------------------------------------------

    /**
     * @return array<string,mixed>|null
     */
    private static function reglages(int $userId): ?array
    {
        $db = self::db();
        if (!$db) {
            return null;
        }

        try {
            $stmt = $db->prepare('SELECT * FROM user_2fa_settings WHERE user_id = ? LIMIT 1');
            $stmt->execute([$userId]);
            $ligne = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('[Tchadok][2fa] lecture impossible : ' . $e->getMessage());
            return null;
        }

        return $ligne === false ? null : $ligne;
    }

    private static function db(): ?PDO
    {
        $db = TchadokDatabase::getInstance()->getConnection();
        if (!$db) {
            return null;
        }

        if (self::$tablesDisponibles === null) {
            try {
                $db->query('SELECT 1 FROM user_2fa_settings LIMIT 1');
                self::$tablesDisponibles = true;
            } catch (Throwable $e) {
                self::$tablesDisponibles = false;
                error_log('[Tchadok][2fa] tables absentes : double authentification inactive (migration SEC-20 non appliquee)');
            }
        }

        return self::$tablesDisponibles ? $db : null;
    }
}
