<?php
/**
 * Dossier artiste verifie (MOD-06) et conditions de publication.
 *
 * ETAPES                                         BLOQUANT POUR
 *   1. Compte    e-mail et telephone verifies    tout
 *   2. Identite  piece d'identite, selfie        publication
 *   3. Profil    nom de scene, biographie, genre principal, photo
 *   4. Droits    declaration de titularite et d'absence de cession exclusive
 *                concurrente, contrat de distribution accepte (PAYOUT-05)
 *   5. Encaissement : compte mobile money verifie (LOT 8)  -> versement
 *   6. Fiscal    regime, identifiant                        -> versement au-dela
 *                                                              du seuil fiscal
 *   7. Validation humaine motivee (permission artiste.valider)
 *
 * NIVEAUX, fixes a la validation
 *   decouverte  publication limitee (5 titres par 30 jours), gratuite : pas de vente
 *   verifie     vente et versements actives, identite controlee, badge
 *   partenaire  comme verifie, commission negociee, mise en avant
 *
 * PIECES D'IDENTITE
 *   Chiffrees (AES-256-GCM, cle derivee d'APP_KEY) dans storage/private/pieces,
 *   hors racine web ; consultables par la seule permission artiste.valider,
 *   chaque consultation journalisee. Conservation : jusqu'a 5 ans apres la fin
 *   de la relation (obligations comptables) ; un dossier refuse ou abandonne
 *   voit ses pieces supprimees apres 6 mois (DossierArtiste::purger).
 */

declare(strict_types=1);

final class DossierArtiste
{
    public const NIVEAUX = ['aucun' => 'Non valide', 'decouverte' => 'Decouverte', 'verifie' => 'Verifie', 'partenaire' => 'Partenaire'];
    public const PLAFOND_DECOUVERTE = 5;
    public const SEUIL_FISCAL = 500000;

    public static function dossier(int $artistId): array
    {
        $db = self::base();
        $db->prepare('INSERT IGNORE INTO artist_dossiers (artist_id) VALUES (?)')->execute([$artistId]);
        $stmt = $db->prepare('SELECT d.*, a.stage_name, a.bio, a.profile_image, a.user_id FROM artist_dossiers d JOIN artists a ON a.id = d.artist_id WHERE d.artist_id = ?');
        $stmt->execute([$artistId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /** Etat de chaque etape. @return array<string,bool> */
    public static function etapes(int $artistId): array
    {
        $d = self::dossier($artistId);
        $userId = (int) $d['user_id'];
        $compte = class_exists('Versements') ? Versements::compte($artistId) : null;
        return [
            'compte'       => Comptes::emailVerifie($userId) && Comptes::telephoneVerifie($userId),
            'identite'     => $d['identity_doc'] !== null && $d['selfie_doc'] !== null,
            'profil'       => trim((string) $d['stage_name']) !== '' && mb_strlen(trim((string) $d['bio'])) >= 30
                              && trim((string) $d['profile_image']) !== '' && Taxonomie::genrePrincipal($artistId) !== null,
            'droits'       => $d['rights_declared_at'] !== null && !(class_exists('Contrats') && Contrats::acceptationRequise($artistId)),
            'encaissement' => $compte !== null && $compte['verified_at'] !== null,
            'fiscal'       => trim((string) $d['fiscal_regime']) !== '',
        ];
    }

    /** @return array{succes:bool, message:string} */
    public static function mettreAJourProfil(int $artistId, array $valeurs): array
    {
        $nom = trim((string) ($valeurs['stage_name'] ?? ''));
        $bio = trim((string) ($valeurs['bio'] ?? ''));
        if (mb_strlen($nom) < 2 || mb_strlen($nom) > 100) {
            return ['succes' => false, 'message' => 'Nom de scene de 2 a 100 caracteres.'];
        }
        if (mb_strlen($bio) < 30) {
            return ['succes' => false, 'message' => 'Biographie de 30 caracteres au moins.'];
        }
        $reseaux = [];
        foreach (['facebook', 'instagram', 'youtube', 'website'] as $r) {
            $v = trim((string) ($valeurs[$r] ?? ''));
            if ($v !== '' && !filter_var($v, FILTER_VALIDATE_URL)) {
                return ['succes' => false, 'message' => 'Lien ' . $r . ' invalide (adresse complete attendue).'];
            }
            $reseaux[$r] = $v ?: null;
        }
        $g = Taxonomie::definirGenresArtiste($artistId, isset($valeurs['genre_principal']) && $valeurs['genre_principal'] !== '' ? (int) $valeurs['genre_principal'] : null,
            array_map('intval', (array) ($valeurs['genres_secondaires'] ?? [])));
        if (!$g['succes']) {
            return $g;
        }
        self::base()->prepare('UPDATE artists SET stage_name = ?, bio = ?, facebook = ?, instagram = ?, youtube = ?, website = ? WHERE id = ?')
            ->execute([$nom, $bio, $reseaux['facebook'], $reseaux['instagram'], $reseaux['youtube'], $reseaux['website'], $artistId]);
        return ['succes' => true, 'message' => 'Profil enregistre.'];
    }

    /** @return array{succes:bool, message:string} */
    public static function declarerDroits(int $artistId, bool $titularite, bool $exclusivite): array
    {
        if (!$titularite || !$exclusivite) {
            return ['succes' => false, 'message' => 'Les deux declarations sont necessaires pour publier.'];
        }
        self::base()->prepare('UPDATE artist_dossiers SET rights_declared_at = NOW() WHERE artist_id = ?')->execute([$artistId]);
        JournalAudit::enregistrer('contrat.accepte', ['cible_type' => 'artiste', 'cible_id' => $artistId, 'apres' => ['declaration_droits' => true]]);
        return ['succes' => true, 'message' => 'Declaration de droits enregistree.'];
    }

    /** @return array{succes:bool, message:string} */
    public static function renseignerFiscal(int $artistId, string $regime, string $identifiant): array
    {
        $regime = trim($regime);
        if (!in_array($regime, ['particulier', 'entreprise_individuelle', 'societe', 'association'], true)) {
            return ['succes' => false, 'message' => 'Regime fiscal inconnu.'];
        }
        self::base()->prepare('UPDATE artist_dossiers SET fiscal_regime = ?, fiscal_id = ? WHERE artist_id = ?')->execute([$regime, mb_substr(trim($identifiant), 0, 60) ?: null, $artistId]);
        return ['succes' => true, 'message' => 'Informations fiscales enregistrees.'];
    }

    /**
     * Depot d'une piece (identite ou selfie) : chiffree, hors racine web.
     *
     * @param array $fichier entree de $_FILES
     * @return array{succes:bool, message:string}
     */
    public static function deposerPiece(int $artistId, string $type, array $fichier): array
    {
        if (!in_array($type, ['identite', 'selfie'], true)) {
            return ['succes' => false, 'message' => 'Piece inconnue.'];
        }
        $temporaire = (string) ($fichier['tmp_name'] ?? '');
        if (($fichier['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || (PHP_SAPI !== 'cli' && !is_uploaded_file($temporaire))) {
            return ['succes' => false, 'message' => 'Fichier non recu.'];
        }
        if (filesize($temporaire) > 5 * 1024 * 1024) {
            return ['succes' => false, 'message' => 'Fichier trop volumineux (5 Mo au plus).'];
        }
        $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($temporaire);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'application/pdf'], true)) {
            return ['succes' => false, 'message' => 'Format accepte : JPEG, PNG ou PDF.'];
        }
        $nom = bin2hex(random_bytes(16)) . '.bin';
        $dossier = self::dossierPieces();
        $contenu = (string) file_get_contents($temporaire);
        if (@file_put_contents($dossier . '/' . $nom, self::chiffrer($mime . "\n" . $contenu), LOCK_EX) === false) {
            return ['succes' => false, 'message' => 'Enregistrement impossible.'];
        }
        @unlink($temporaire);
        $colonne = $type === 'identite' ? 'identity_doc' : 'selfie_doc';
        $ancien = self::dossier($artistId)[$colonne];
        self::base()->prepare("UPDATE artist_dossiers SET {$colonne} = ?, identity_verified_at = NULL, identity_verified_by = NULL WHERE artist_id = ?")->execute([$nom, $artistId]);
        if ($ancien) {
            @unlink($dossier . '/' . $ancien);
        }
        return ['succes' => true, 'message' => ($type === 'identite' ? 'Piece d\'identite' : 'Selfie') . ' enregistre(e), chiffre(e).'];
    }

    /**
     * Lecture d'une piece par un validateur : dechiffree, journalisee.
     *
     * @return array{mime:string, contenu:string}|null
     */
    public static function lirePiece(int $artistId, string $type, int $lecteur): ?array
    {
        $d = self::dossier($artistId);
        $nom = $type === 'identite' ? $d['identity_doc'] : ($type === 'selfie' ? $d['selfie_doc'] : null);
        if (!$nom || !is_file(self::dossierPieces() . '/' . $nom)) {
            return null;
        }
        $clair = self::dechiffrer((string) file_get_contents(self::dossierPieces() . '/' . $nom));
        if ($clair === null) {
            return null;
        }
        JournalAudit::enregistrer('dossier.piece_consultee', ['cible_type' => 'artiste', 'cible_id' => $artistId, 'apres' => ['piece' => $type], 'acteur' => $lecteur]);
        [$mime, $contenu] = explode("\n", $clair, 2);
        return ['mime' => $mime, 'contenu' => $contenu];
    }

    /** @return array{succes:bool, message:string} */
    public static function soumettre(int $artistId): array
    {
        $e = self::etapes($artistId);
        $manque = array_keys(array_filter(['compte' => $e['compte'], 'identite' => $e['identite'], 'profil' => $e['profil'], 'droits' => $e['droits']], fn($v) => !$v));
        if ($manque !== []) {
            return ['succes' => false, 'message' => 'Etapes a completer : ' . implode(', ', $manque) . '.'];
        }
        $stmt = self::base()->prepare("UPDATE artist_dossiers SET status = 'soumis', submitted_at = NOW() WHERE artist_id = ? AND status IN ('brouillon', 'a_completer')");
        $stmt->execute([$artistId]);
        return $stmt->rowCount() === 1 ? ['succes' => true, 'message' => 'Dossier soumis : notre equipe le verifie et vous repond par e-mail.']
                                       : ['succes' => false, 'message' => 'Dossier deja soumis ou valide.'];
    }

    /**
     * Decision humaine sur un dossier soumis.
     *
     * @return array{succes:bool, message:string}
     */
    public static function decider(int $artistId, string $decision, string $niveau, string $motif, ?float $commission, int $valideur): array
    {
        $d = self::dossier($artistId);
        $motif = trim($motif);
        if ($d['status'] !== 'soumis') {
            return ['succes' => false, 'message' => 'Seul un dossier soumis se decide.'];
        }
        if (in_array($decision, ['a_completer', 'refuse'], true) && mb_strlen($motif) < 5) {
            return ['succes' => false, 'message' => 'Motif obligatoire (5 caracteres au moins), transmis a l\'artiste.'];
        }
        if ($decision === 'valide' && !in_array($niveau, ['decouverte', 'verifie', 'partenaire'], true)) {
            return ['succes' => false, 'message' => 'Niveau inconnu.'];
        }
        if ($decision === 'valide' && $niveau === 'partenaire' && ($commission === null || $commission < 0 || $commission > 50)) {
            return ['succes' => false, 'message' => 'Partenaire : commission negociee entre 0 et 50 %.'];
        }
        if (!in_array($decision, ['valide', 'a_completer', 'refuse'], true)) {
            return ['succes' => false, 'message' => 'Decision inconnue.'];
        }
        $verifie = $decision === 'valide' && in_array($niveau, ['verifie', 'partenaire'], true);
        self::base()->prepare(
            'UPDATE artist_dossiers SET status = ?, level = ?, negotiated_commission = ?, decision_reason = ?, decided_by = ?, decided_at = NOW(),
                    identity_verified_at = IF(?, NOW(), identity_verified_at), identity_verified_by = IF(?, ?, identity_verified_by) WHERE artist_id = ?'
        )->execute([$decision, $decision === 'valide' ? $niveau : 'aucun', $niveau === 'partenaire' ? $commission : null, $motif ?: null, $valideur,
            (int) $verifie, (int) $verifie, $valideur, $artistId]);
        self::base()->prepare('UPDATE artists SET verified = ? WHERE id = ?')->execute([(int) $verifie, $artistId]);
        JournalAudit::enregistrer('dossier.decide', ['cible_type' => 'artiste', 'cible_id' => $artistId, 'apres' => ['decision' => $decision, 'niveau' => $niveau], 'raison' => $motif ?: null, 'acteur' => $valideur]);
        $stmt = self::base()->prepare('SELECT u.email, u.first_name FROM users u WHERE u.id = ?');
        $stmt->execute([$d['user_id']]);
        if ($u = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $texte = match ($decision) {
                'valide'      => 'Votre dossier artiste est valide (niveau ' . self::NIVEAUX[$niveau] . '). Vous pouvez publier.',
                'a_completer' => 'Votre dossier artiste est a completer.',
                default       => 'Votre dossier artiste n\'a pas ete accepte.',
            };
            sendEmail((string) $u['email'], 'Tchadok - votre dossier artiste', '<p>Bonjour ' . htmlspecialchars((string) $u['first_name'], ENT_QUOTES, 'UTF-8') . ',</p><p>'
                . htmlspecialchars($texte, ENT_QUOTES, 'UTF-8') . '</p>' . ($motif !== '' ? '<p>Motif : ' . htmlspecialchars($motif, ENT_QUOTES, 'UTF-8') . '</p>' : ''));
        }
        return ['succes' => true, 'message' => 'Decision enregistree, l\'artiste est prevenu.'];
    }

    /** @return array<int,array<string,mixed>> dossiers soumis, plus anciens d'abord */
    public static function aTraiter(): array
    {
        return self::base()->query(
            "SELECT d.*, a.stage_name, a.bio, u.email, u.phone FROM artist_dossiers d JOIN artists a ON a.id = d.artist_id JOIN users u ON u.id = a.user_id
              WHERE d.status = 'soumis' ORDER BY d.submitted_at"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Supprime les pieces des dossiers refuses ou abandonnes depuis 6 mois. */
    public static function purger(): int
    {
        $n = 0;
        $lignes = self::base()->query(
            "SELECT artist_id, identity_doc, selfie_doc FROM artist_dossiers
              WHERE (identity_doc IS NOT NULL OR selfie_doc IS NOT NULL)
                AND ((status = 'refuse' AND decided_at < NOW() - INTERVAL 6 MONTH)
                     OR (status IN ('brouillon', 'a_completer') AND updated_at < NOW() - INTERVAL 6 MONTH))"
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($lignes as $l) {
            foreach (['identity_doc', 'selfie_doc'] as $c) {
                if ($l[$c]) {
                    @unlink(self::dossierPieces() . '/' . $l[$c]);
                }
            }
            self::base()->prepare('UPDATE artist_dossiers SET identity_doc = NULL, selfie_doc = NULL WHERE artist_id = ?')->execute([$l['artist_id']]);
            $n++;
        }
        return $n;
    }

    // -----------------------------------------------------------------
    // Conditions (publication, vente, versement)
    // -----------------------------------------------------------------

    /**
     * Ce qui manque pour publier. Vide = publication ouverte.
     *
     * @return array<string,array{message:string, lien:string}>
     */
    public static function conditionsPublication(int $artistId, int $userId): array
    {
        $manque = [];
        if (!Comptes::emailVerifie($userId)) {
            $manque['email'] = ['message' => 'Confirmez votre adresse e-mail.', 'lien' => '/verifier-email.php'];
        }
        if (class_exists('Contrats') && Contrats::acceptationRequise($artistId)) {
            $manque['contrat'] = ['message' => 'Acceptez le contrat de distribution en vigueur.', 'lien' => '/contrat.php'];
        }
        $d = self::dossier($artistId);
        if ($d['status'] !== 'valide' || $d['level'] === 'aucun') {
            $manque['dossier'] = ['message' => 'Faites valider votre dossier artiste.', 'lien' => '/artiste-dossier.php'];
        }
        if (Taxonomie::genrePrincipal($artistId) === null) {
            $manque['genre'] = ['message' => 'Choisissez votre genre principal.', 'lien' => '/artiste-dossier.php#profil'];
        }
        if ($d['level'] === 'decouverte') {
            $stmt = self::base()->prepare('SELECT COUNT(*) FROM tracks WHERE artist_id = ? AND created_at > NOW() - INTERVAL 30 DAY AND deleted_at IS NULL');
            $stmt->execute([$artistId]);
            if ((int) $stmt->fetchColumn() >= self::PLAFOND_DECOUVERTE) {
                $manque['plafond'] = ['message' => 'Niveau Decouverte : ' . self::PLAFOND_DECOUVERTE . ' titres par 30 jours au plus. Passez au niveau Verifie pour publier davantage.', 'lien' => '/artiste-dossier.php'];
            }
        }
        return $manque;
    }

    /** La vente est-elle ouverte a cet artiste ? */
    public static function peutVendre(int $artistId): bool
    {
        return in_array(self::dossier($artistId)['level'], ['verifie', 'partenaire'], true);
    }

    // -----------------------------------------------------------------

    private static function dossierPieces(): string
    {
        $d = dirname(__DIR__) . '/storage/private/pieces';
        if (!is_dir($d)) {
            @mkdir($d, 0770, true);
        }
        return $d;
    }

    private static function cle(): string
    {
        return hash_hkdf('sha256', (string) env('APP_KEY', 'tchadok-cle-de-developpement'), 32, 'tchadok-pieces-identite');
    }

    private static function chiffrer(string $clair): string
    {
        $iv = random_bytes(12);
        $chiffre = openssl_encrypt($clair, 'aes-256-gcm', self::cle(), OPENSSL_RAW_DATA, $iv, $tag);
        return 'TPI1' . $iv . $tag . $chiffre;
    }

    private static function dechiffrer(string $donnees): ?string
    {
        if (!str_starts_with($donnees, 'TPI1') || strlen($donnees) < 32) {
            return null;
        }
        $clair = openssl_decrypt(substr($donnees, 32), 'aes-256-gcm', self::cle(), OPENSSL_RAW_DATA, substr($donnees, 4, 12), substr($donnees, 16, 16));
        return $clair === false ? null : $clair;
    }

    private static function base(): PDO
    {
        return TchadokDatabase::getInstance()->getConnection();
    }
}
