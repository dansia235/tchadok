<?php
/**
 * Parcours de publication unique (MOD-05).
 *
 * Etapes : format -> fichiers -> metadonnees -> prix -> recapitulatif ->
 * soumission. Chaque etape enregistre en base (sortie et titres en
 * brouillon) : un parcours interrompu se reprend sans perte.
 *
 * Toutes les regles sont appliquees ici, cote serveur :
 *   - composition par format (DATA-03, Sorties::validerPublication) ;
 *   - prix contre la grille (DATA-04, Tarifs::valider), prix suggere affiche ;
 *   - niveau Decouverte : gratuit uniquement (MOD-06) ;
 *   - controles de fichiers (MOD-04), duree lue dans le fichier (STAT-02) ;
 *   - metadonnees obligatoires : titre, genre, date, langue, credits ;
 *   - soumission par la machine a etats (MOD-01), jamais « approved ».
 */

declare(strict_types=1);

final class Publication
{
    public const LANGUES = ['fr' => 'Francais', 'ar' => 'Arabe tchadien', 'sara' => 'Sara', 'ngambay' => 'Ngambay', 'kanembou' => 'Kanembou', 'en' => 'Anglais', 'autre' => 'Autre / instrumental'];

    /** Sortie modifiable par cet artiste (brouillon ou refusee), sinon null. */
    public static function sortie(int $releaseId, int $artistId): ?array
    {
        $stmt = self::base()->prepare("SELECT * FROM releases WHERE id = ? AND artist_id = ? AND deleted_at IS NULL AND status IN ('draft', 'rejected')");
        $stmt->execute([$releaseId, $artistId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** @return array<int,array<string,mixed>> */
    public static function titres(int $releaseId): array
    {
        $stmt = self::base()->prepare('SELECT * FROM tracks WHERE release_id = ? AND deleted_at IS NULL ORDER BY COALESCE(track_number, 999), id');
        $stmt->execute([$releaseId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int,array<string,mixed>> brouillons et sorties refusees a reprendre */
    public static function aReprendre(int $artistId): array
    {
        $stmt = self::base()->prepare(
            "SELECT r.*, (SELECT COUNT(*) FROM tracks t WHERE t.release_id = r.id AND t.deleted_at IS NULL) AS titres
               FROM releases r WHERE r.artist_id = ? AND r.deleted_at IS NULL AND r.status IN ('draft', 'rejected') ORDER BY r.updated_at DESC"
        );
        $stmt->execute([$artistId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array{succes:bool, message:string, id:?int} */
    public static function creerSortie(int $artistId, string $format, string $titre): array
    {
        $titre = trim($titre);
        if (!Sorties::formatConnu($format)) {
            return ['succes' => false, 'message' => 'Choisissez un format.', 'id' => null];
        }
        if (mb_strlen($titre) < 1 || mb_strlen($titre) > 200) {
            return ['succes' => false, 'message' => 'Titre de la sortie obligatoire (200 caracteres au plus).', 'id' => null];
        }
        $gratuit = !DossierArtiste::peutVendre($artistId);
        self::base()->prepare("INSERT INTO releases (artist_id, title, slug, format, is_free, status) VALUES (?, ?, ?, ?, ?, 'draft')")
            ->execute([$artistId, $titre, Sorties::slug($titre, 'releases'), $format, (int) $gratuit]);
        return ['succes' => true, 'message' => 'Brouillon cree : deposez maintenant vos fichiers.', 'id' => (int) self::base()->lastInsertId()];
    }

    /**
     * Depot d'un titre : fichier controle (MOD-04), titre cree en brouillon.
     *
     * @return array{succes:bool, message:string}
     */
    public static function ajouterPiste(int $releaseId, int $artistId, array $fichier): array
    {
        $sortie = self::sortie($releaseId, $artistId);
        if ($sortie === null) {
            return ['succes' => false, 'message' => 'Sortie introuvable ou non modifiable.'];
        }
        $regle = Sorties::FORMATS[$sortie['format']];
        if ($regle['titres_max'] > 0 && count(self::titres($releaseId)) >= $regle['titres_max']) {
            return ['succes' => false, 'message' => 'Un ' . strtolower($regle['libelle']) . ' compte au plus ' . $regle['titres_max'] . ' titres.'];
        }
        try {
            $depot = uploadFile($fichier, dirname(__DIR__) . '/' . AUDIO_PATH, ALLOWED_AUDIO_TYPES, MAX_AUDIO_SIZE);
            if (!$depot['success']) {
                throw new DepotRefuse($depot['message']);
            }
            $chemin = AUDIO_PATH . $depot['filename'];
            $controle = ControlesDepot::audio(dirname(__DIR__) . '/' . $chemin);
        } catch (DepotRefuse $e) {
            return ['succes' => false, 'message' => $e->messagePourArtiste()];
        }
        $nom = trim((string) preg_replace('/\.[a-z0-9]+$/i', '', (string) ($fichier['name'] ?? ''))) ?: 'Titre ' . (count(self::titres($releaseId)) + 1);
        $nom = mb_substr(str_replace('_', ' ', $nom), 0, 200);
        self::base()->prepare(
            "INSERT INTO tracks (album_id, release_id, slug, artist_id, title, genre_id, audio_file, audio_sha256, audio_bitrate, audio_sample_rate, audio_channels,
                                 duration, track_number, price, is_free, language, release_date, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, 'draft')"
        )->execute([
            $releaseId, $releaseId, Sorties::slug($nom, 'tracks'), $artistId, $nom, $sortie['genre_id'], $chemin, $controle['sha256'], $controle['debit'],
            $controle['frequence'], $controle['canaux'], $controle['duree'], count(self::titres($releaseId)) + 1, (int) $sortie['is_free'],
            $sortie['language'], $sortie['release_date'],
        ]);
        $trackId = (int) self::base()->lastInsertId();
        ControlesDepot::enregistrer($trackId, $controle['resultats']);
        Compteurs::sortie($releaseId);
        $signal = array_filter($controle['resultats'], fn($r) => $r[1] === 'signal');
        return ['succes' => true, 'message' => sprintf('« %s » ajoute (%d:%02d).', $nom, intdiv($controle['duree'], 60), $controle['duree'] % 60)
            . ($signal ? ' A noter : ' . implode(' ; ', array_column($signal, 2)) . '.' : '')];
    }

    /** @return array{succes:bool, message:string} */
    public static function retirerPiste(int $trackId, int $artistId): array
    {
        $stmt = self::base()->prepare("SELECT t.* FROM tracks t JOIN releases r ON r.id = t.release_id WHERE t.id = ? AND t.artist_id = ? AND r.status IN ('draft', 'rejected') AND t.status IN ('draft', 'rejected')");
        $stmt->execute([$trackId, $artistId]);
        $t = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$t) {
            return ['succes' => false, 'message' => 'Titre introuvable ou deja soumis.'];
        }
        self::base()->prepare('DELETE FROM tracks WHERE id = ?')->execute([$trackId]);
        @unlink(dirname(__DIR__) . '/' . $t['audio_file']);
        Compteurs::sortie((int) $t['release_id']);
        return ['succes' => true, 'message' => 'Titre retire du brouillon.'];
    }

    /** @return array{succes:bool, message:string} */
    public static function deposerPochette(int $releaseId, int $artistId, array $fichier): array
    {
        if (self::sortie($releaseId, $artistId) === null) {
            return ['succes' => false, 'message' => 'Sortie introuvable ou non modifiable.'];
        }
        try {
            $depot = uploadFile($fichier, dirname(__DIR__) . '/' . IMAGES_PATH, ALLOWED_IMAGE_TYPES, MAX_IMAGE_SIZE);
            if (!$depot['success']) {
                throw new DepotRefuse($depot['message']);
            }
            $final = ControlesDepot::pochette(dirname(__DIR__) . '/' . IMAGES_PATH . $depot['filename']);
        } catch (DepotRefuse $e) {
            return ['succes' => false, 'message' => $e->messagePourArtiste()];
        }
        self::base()->prepare('UPDATE releases SET cover_image = ? WHERE id = ?')->execute([IMAGES_PATH . basename($final), $releaseId]);
        return ['succes' => true, 'message' => 'Pochette enregistree.'];
    }

    /**
     * Metadonnees de la sortie et de chaque titre.
     *
     * @return array{succes:bool, message:string}
     */
    public static function enregistrerMetadonnees(int $releaseId, int $artistId, array $sortie, array $titres): array
    {
        $s = self::sortie($releaseId, $artistId);
        if ($s === null) {
            return ['succes' => false, 'message' => 'Sortie introuvable ou non modifiable.'];
        }
        $genre = isset($sortie['genre_id']) && $sortie['genre_id'] !== '' ? (int) $sortie['genre_id'] : null;
        if ($genre !== null && !estGenreSelectionnable($genre)) {
            return ['succes' => false, 'message' => 'Genre invalide.'];
        }
        $langue = array_key_exists((string) ($sortie['language'] ?? ''), self::LANGUES) ? (string) $sortie['language'] : null;
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($sortie['release_date'] ?? '')) ? (string) $sortie['release_date'] : null;
        $db = self::base();
        $db->prepare('UPDATE releases SET title = ?, genre_id = ?, language = ?, release_date = ?, description = ? WHERE id = ?')
           ->execute([mb_substr(trim((string) ($sortie['title'] ?? $s['title'])), 0, 200) ?: $s['title'], $genre, $langue, $date,
               mb_substr(trim((string) ($sortie['description'] ?? '')), 0, 5000) ?: null, $releaseId]);
        $maj = $db->prepare('UPDATE tracks SET title = ?, genre_id = ?, language = ?, release_date = ?, credits = ?, explicit_content = ?, lyrics = ?, track_number = ? WHERE id = ? AND release_id = ?');
        foreach (self::titres($releaseId) as $t) {
            $v = (array) ($titres[$t['id']] ?? []);
            $g = isset($v['genre_id']) && $v['genre_id'] !== '' ? (int) $v['genre_id'] : $genre;
            if ($g !== null && !estGenreSelectionnable($g)) {
                return ['succes' => false, 'message' => 'Genre invalide pour « ' . $t['title'] . ' ».'];
            }
            $maj->execute([
                mb_substr(trim((string) ($v['title'] ?? $t['title'])), 0, 200) ?: $t['title'], $g,
                array_key_exists((string) ($v['language'] ?? ''), self::LANGUES) ? (string) $v['language'] : $langue, $date,
                mb_substr(trim((string) ($v['credits'] ?? '')), 0, 500) ?: null, !empty($v['explicit']) ? 1 : 0,
                mb_substr(trim((string) ($v['lyrics'] ?? '')), 0, 20000) ?: null, max(1, (int) ($v['track_number'] ?? $t['track_number'] ?? 1)), $t['id'], $releaseId,
            ]);
        }
        return ['succes' => true, 'message' => 'Metadonnees enregistrees.'];
    }

    /**
     * Prix : gratuit, ou prix par titre et prix de la sortie, contre la
     * grille. Niveau Decouverte : gratuit impose.
     *
     * @param array<int,string> $prixTitres
     * @return array{succes:bool, message:string}
     */
    public static function enregistrerPrix(int $releaseId, int $artistId, bool $gratuit, string $prixSortie, array $prixTitres): array
    {
        $s = self::sortie($releaseId, $artistId);
        if ($s === null) {
            return ['succes' => false, 'message' => 'Sortie introuvable ou non modifiable.'];
        }
        if (!$gratuit && !DossierArtiste::peutVendre($artistId)) {
            return ['succes' => false, 'message' => 'Niveau Decouverte : vos titres sont gratuits. La vente s\'ouvre au niveau Verifie.'];
        }
        $db = self::base();
        if ($gratuit) {
            $db->prepare('UPDATE releases SET is_free = 1, price_bundle = NULL WHERE id = ?')->execute([$releaseId]);
            $db->prepare('UPDATE tracks SET is_free = 1, price = 0 WHERE release_id = ?')->execute([$releaseId]);
            return ['succes' => true, 'message' => 'Sortie gratuite.'];
        }
        $maj = $db->prepare('UPDATE tracks SET is_free = 0, price = ? WHERE id = ? AND release_id = ?');
        foreach (self::titres($releaseId) as $t) {
            $v = Tarifs::valider($prixTitres[$t['id']] ?? '', 'track');
            if (!$v['valide'] || $v['valeur'] <= 0) {
                return ['succes' => false, 'message' => '« ' . $t['title'] . ' » : ' . ($v['message'] ?: 'prix obligatoire.')];
            }
            $maj->execute([$v['valeur'], $t['id'], $releaseId]);
        }
        $bundle = null;
        if (trim($prixSortie) !== '') {
            $v = Tarifs::valider($prixSortie, 'release', $s['format']);
            if (!$v['valide']) {
                return ['succes' => false, 'message' => 'Prix de la sortie : ' . $v['message']];
            }
            $bundle = $v['valeur'];
        }
        $db->prepare('UPDATE releases SET is_free = 0, price_bundle = ? WHERE id = ?')->execute([$bundle, $releaseId]);
        return ['succes' => true, 'message' => 'Prix enregistres.'];
    }

    /**
     * Tout ce qui empeche la soumission. Vide = soumission possible.
     *
     * @return string[]
     */
    public static function verifier(int $releaseId): array
    {
        $stmt = self::base()->prepare('SELECT * FROM releases WHERE id = ?');
        $stmt->execute([$releaseId]);
        $s = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$s) {
            return ['Sortie introuvable.'];
        }
        $erreurs = Sorties::validerPublication($releaseId);
        if (trim((string) $s['cover_image']) === '') {
            $erreurs[] = 'Pochette manquante.';
        }
        foreach (['genre_id' => 'le genre', 'language' => 'la langue', 'release_date' => 'la date de sortie'] as $c => $libelle) {
            if ($s[$c] === null || $s[$c] === '') {
                $erreurs[] = 'Sortie : renseignez ' . $libelle . '.';
            }
        }
        foreach (self::titres($releaseId) as $t) {
            foreach (['genre_id' => 'genre', 'language' => 'langue', 'credits' => 'credits'] as $c => $libelle) {
                if ($t[$c] === null || trim((string) $t[$c]) === '') {
                    $erreurs[] = '« ' . $t['title'] . ' » : ' . $libelle . ' manquant(s).';
                }
            }
            if ((int) $s['is_free'] === 0 && (float) $t['price'] <= 0) {
                $erreurs[] = '« ' . $t['title'] . ' » : prix manquant.';
            }
        }
        return array_values(array_unique($erreurs));
    }

    /** @return array{succes:bool, message:string} */
    public static function soumettre(int $releaseId, int $artistId, int $userId): array
    {
        if (self::sortie($releaseId, $artistId) === null) {
            return ['succes' => false, 'message' => 'Sortie introuvable ou deja soumise.'];
        }
        $conditions = DossierArtiste::conditionsPublication($artistId, $userId);
        if ($conditions !== []) {
            return ['succes' => false, 'message' => implode(' ', array_column($conditions, 'message'))];
        }
        $erreurs = self::verifier($releaseId);
        if ($erreurs !== []) {
            return ['succes' => false, 'message' => implode(' ', $erreurs)];
        }
        $r = Moderation::transition('release', $releaseId, 'pending', $userId, 'artiste');
        return $r['succes'] ? ['succes' => true, 'message' => 'Sortie soumise a la moderation : vous serez prevenu par e-mail de la decision.'] : $r;
    }

    private static function base(): PDO
    {
        return TchadokDatabase::getInstance()->getConnection();
    }
}
