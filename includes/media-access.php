<?php
/**
 * Controle d'acces aux medias - Tchadok Platform
 *
 * Tache SEC-06.
 *
 * AVANT
 *   L'API api/track.php renvoyait le chemin du fichier audio pour TOUS les
 *   titres, payants compris. La decision "gratuit / Premium / payant" etait
 *   prise dans le navigateur, par assets/js/player.js. Il suffisait de lire
 *   la reponse de l'API -- ou les outils de developpement -- pour obtenir le
 *   chemin du fichier et le telecharger. Le paywall etait cosmetique.
 *
 * APRES
 *   - La decision d'acces est prise ICI, cote serveur, a partir de la base.
 *   - Le client ne recoit jamais de chemin de fichier : seulement une URL
 *     signee vers media.php, emise uniquement si l'acces est accorde.
 *   - media.php reverifie la signature ET l'acces avant de servir.
 *
 * L'URL signee est liee a la session : copiee dans un autre navigateur, ou
 * partagee, elle ne fonctionne pas.
 */

declare(strict_types=1);

final class MediaAccess
{
    public const ACCES_COMPLET = 'full';
    public const ACCES_EXTRAIT = 'preview';
    public const ACCES_AUCUN   = 'none';

    public const TYPE_AUDIO   = 'audio';
    public const TYPE_EXTRAIT = 'preview';

    /**
     * Duree de validite d'une URL signee.
     *
     * Le navigateur emet plusieurs requetes partielles (Range) tout au long
     * de la lecture, et a chaque deplacement dans le titre. Une validite de
     * 5 minutes casserait l'avance rapide sur un titre long. La protection
     * contre le partage repose sur le lien a la session, pas sur la duree.
     */
    private const VALIDITE_SECONDES = 3 * 3600;

    // -----------------------------------------------------------------
    // Decision d'acces
    // -----------------------------------------------------------------

    /**
     * Decide de l'acces d'un visiteur a un titre.
     *
     * @param array    $titre   ligne tracks (id, status, is_free, artist_id, album_id, preview_file, audio_file)
     * @param int|null $userId  utilisateur connecte, null pour un visiteur anonyme
     * @return array{acces: string, motif: string}
     */
    public static function decider(PDO $db, array $titre, ?int $userId): array
    {
        $approuve = ($titre['status'] ?? '') === 'approved';
        $aExtrait = !empty($titre['preview_file']);

        // Proprietaire du titre et administrateurs : acces complet, y compris
        // aux titres non encore publies (necessaire a la moderation et a la
        // verification par l'artiste).
        if ($userId !== null) {
            if (self::estAdministrateur($db, $userId)) {
                return ['acces' => self::ACCES_COMPLET, 'motif' => 'administrateur'];
            }
            if (self::estProprietaire($db, $userId, (int) ($titre['artist_id'] ?? 0))) {
                return ['acces' => self::ACCES_COMPLET, 'motif' => 'proprietaire'];
            }
        }

        // Un titre non approuve n'est servi a personne d'autre.
        if (!$approuve) {
            return ['acces' => self::ACCES_AUCUN, 'motif' => 'non_publie'];
        }

        if ((int) ($titre['is_free'] ?? 0) === 1) {
            return ['acces' => self::ACCES_COMPLET, 'motif' => 'gratuit'];
        }

        if ($userId !== null) {
            if (self::aAchete($db, $userId, $titre)) {
                return ['acces' => self::ACCES_COMPLET, 'motif' => 'achete'];
            }
            if (self::estPremiumActif($db, $userId)) {
                return ['acces' => self::ACCES_COMPLET, 'motif' => 'premium'];
            }
        }

        if ($aExtrait) {
            return ['acces' => self::ACCES_EXTRAIT, 'motif' => $userId === null ? 'anonyme' : 'payant'];
        }

        return ['acces' => self::ACCES_AUCUN, 'motif' => $userId === null ? 'anonyme' : 'payant'];
    }

    private static function estAdministrateur(PDO $db, int $userId): bool
    {
        $stmt = $db->prepare('SELECT 1 FROM admins WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        return (bool) $stmt->fetchColumn();
    }

    private static function estProprietaire(PDO $db, int $userId, int $artistId): bool
    {
        if ($artistId <= 0) {
            return false;
        }
        $stmt = $db->prepare('SELECT 1 FROM artists WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$artistId, $userId]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Statut Premium lu en base a chaque requete, expiration comprise.
     *
     * L'ancien controle lisait $_SESSION['premium_status'], fige a la
     * connexion : un abonnement expire restait actif jusqu'a la deconnexion.
     */
    private static function estPremiumActif(PDO $db, int $userId): bool
    {
        $stmt = $db->prepare(
            'SELECT 1 FROM users
              WHERE id = ? AND premium_status = 1
                AND (premium_expires_at IS NULL OR premium_expires_at > NOW())
              LIMIT 1'
        );
        $stmt->execute([$userId]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Achat du titre, ou de l'album qui le contient, avec paiement confirme.
     *
     * S'appuie sur la table purchases existante. La table entitlements
     * (SHOP-04) la remplacera ; seule cette methode sera a modifier.
     */
    private static function aAchete(PDO $db, int $userId, array $titre): bool
    {
        $trackId = (int) ($titre['id'] ?? 0);
        $albumId = (int) ($titre['album_id'] ?? 0);

        $stmt = $db->prepare(
            "SELECT 1 FROM purchases
              WHERE user_id = ?
                AND payment_status = 'completed'
                AND ( (item_type = 'track' AND item_id = ?)
                   OR (item_type = 'album' AND item_id = ? AND ? > 0) )
              LIMIT 1"
        );
        $stmt->execute([$userId, $trackId, $albumId, $albumId]);
        return (bool) $stmt->fetchColumn();
    }

    // -----------------------------------------------------------------
    // URL signees
    // -----------------------------------------------------------------

    /**
     * Construit une URL signee vers media.php.
     */
    public static function urlSignee(int $trackId, string $type): string
    {
        $expiration = time() + self::VALIDITE_SECONDES;
        $signature  = self::signer($trackId, $type, $expiration);

        return rtrim((string) SITE_URL, '/') . '/media.php?' . http_build_query([
            'id'  => $trackId,
            't'   => $type,
            'exp' => $expiration,
            'sig' => $signature,
        ]);
    }

    /**
     * Verifie une signature recue par media.php.
     *
     * @return string|null message d'erreur, ou null si la signature est valide
     */
    public static function verifier(int $trackId, string $type, int $expiration, string $signature): ?string
    {
        if (!in_array($type, [self::TYPE_AUDIO, self::TYPE_EXTRAIT], true)) {
            return 'type invalide';
        }
        if ($expiration < time()) {
            return 'lien expire';
        }
        if ($expiration > time() + self::VALIDITE_SECONDES + 60) {
            return 'expiration incoherente';
        }

        $attendue = self::signer($trackId, $type, $expiration);
        if (!hash_equals($attendue, $signature)) {
            return 'signature invalide';
        }

        return null;
    }

    /**
     * HMAC-SHA256 sur (titre, type, expiration, liaison de session).
     *
     * La liaison a la session rend l'URL inutilisable hors du navigateur qui
     * l'a obtenue : c'est ce qui empeche le partage d'un lien de telechargement.
     */
    private static function signer(int $trackId, string $type, int $expiration): string
    {
        $cle = (string) env('APP_KEY', '');
        if ($cle === '') {
            throw new RuntimeException('APP_KEY absente : impossible de signer les URL de media.');
        }

        $liaison = hash('sha256', session_id() !== '' ? session_id() : 'sans-session');
        $donnees = implode('|', [$trackId, $type, $expiration, $liaison]);

        return rtrim(strtr(base64_encode(hash_hmac('sha256', $donnees, $cle, true)), '+/', '-_'), '=');
    }

    // -----------------------------------------------------------------
    // Resolution du fichier
    // -----------------------------------------------------------------

    /**
     * Resout le chemin disque d'un media a partir de la valeur stockee en base.
     *
     * Refuse :
     *   - les URL externes (http, https, //, data:, javascript:...) : servir
     *     un contenu tiers sous le domaine de la plateforme serait une faille,
     *     et le relayer depuis le serveur une porte ouverte aux requetes
     *     forgees vers le reseau interne ;
     *   - tout chemin sortant des repertoires de medias autorises
     *     (traversee de repertoire par ../, liens symboliques, etc.).
     *
     * @return string|null chemin absolu du fichier, ou null si refuse
     */
    public static function resoudreFichier(string $valeurStockee): ?string
    {
        $valeur = trim($valeurStockee);
        if ($valeur === '') {
            return null;
        }

        if (preg_match('#^([a-z][a-z0-9+.-]*:|//)#i', $valeur)) {
            return null; // URL externe ou schema quelconque
        }

        $racine = dirname(__DIR__);
        $candidat = realpath($racine . DIRECTORY_SEPARATOR . ltrim(str_replace('\\', '/', $valeur), '/'));
        if ($candidat === false || !is_file($candidat)) {
            return null;
        }

        foreach (self::repertoiresAutorises() as $autorise) {
            $autorise = realpath($autorise);
            if ($autorise !== false
                && str_starts_with($candidat, $autorise . DIRECTORY_SEPARATOR)) {
                return $candidat;
            }
        }

        return null;
    }

    /**
     * Repertoires dont les fichiers peuvent etre servis par media.php.
     * storage/uploads/audio : emplacement desormais utilise pour tout depot.
     * uploads/audio : emplacement historique, conserve pour les titres existants.
     *
     * @return string[]
     */
    private static function repertoiresAutorises(): array
    {
        $racine = dirname(__DIR__);
        return [
            $racine . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'audio',
            $racine . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'audio',
        ];
    }

    /** Type MIME d'un fichier audio, determine sur son contenu. */
    public static function typeMime(string $chemin): string
    {
        $mime = null;
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = finfo_file($finfo, $chemin) ?: null;
                finfo_close($finfo);
            }
        }

        $autorises = ['audio/mpeg', 'audio/mp4', 'audio/x-m4a', 'audio/aac', 'audio/wav',
                      'audio/x-wav', 'audio/ogg', 'audio/flac', 'audio/x-flac', 'audio/webm'];

        return in_array($mime, $autorises, true) ? $mime : 'application/octet-stream';
    }

    /** Message affichable au visiteur selon le motif de refus. */
    public static function messageRefus(string $motif): string
    {
        return match ($motif) {
            'anonyme'    => 'Connectez-vous pour ecouter ce titre.',
            'payant'     => 'Ce titre est payant. Achetez-le ou passez Premium pour l\'ecouter en entier.',
            'non_publie' => 'Ce titre n\'est pas encore disponible.',
            default      => 'Ce titre n\'est pas disponible a l\'ecoute.',
        };
    }
}
