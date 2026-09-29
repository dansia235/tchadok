<?php
/**
 * Taxonomie des genres (LOT 11).
 *
 * Hierarchie : categorie (genre sans parent) -> genre. Un titre, une sortie,
 * un artiste se classent toujours dans un GENRE, jamais dans une categorie.
 *
 * Regles :
 *   - un genre n'est JAMAIS supprime : il est archive (plus proposable, les
 *     contenus gardent leur classement) ou fusionne (contenus reaffectes) ;
 *   - le slug est stable : un renommage ne le change pas ; apres une fusion,
 *     l'ancien slug redirige vers le genre cible (resoudreSlug) ;
 *   - un artiste ne cree pas de genre : il le PROPOSE, l'administration
 *     decide (motif obligatoire) ;
 *   - chaque modification est journalisee (taxonomie.modifiee).
 */

declare(strict_types=1);

final class Taxonomie
{
    // -----------------------------------------------------------------
    // Genres des artistes (TAXO-02)
    // -----------------------------------------------------------------

    public static function genrePrincipal(int $artistId): ?array
    {
        $stmt = self::base()->prepare(
            'SELECT g.* FROM artist_genres ag JOIN genres g ON g.id = ag.genre_id WHERE ag.artist_id = ? AND ag.is_primary = 1'
        );
        $stmt->execute([$artistId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** @return int[] genres secondaires */
    public static function genresSecondaires(int $artistId): array
    {
        $stmt = self::base()->prepare('SELECT genre_id FROM artist_genres WHERE artist_id = ? AND is_primary = 0 ORDER BY genre_id');
        $stmt->execute([$artistId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Genre principal (obligatoire) et jusqu'a trois genres secondaires, tous
     * pris dans le referentiel actif.
     *
     * @param int[] $secondaires
     * @return array{succes:bool, message:string}
     */
    public static function definirGenresArtiste(int $artistId, ?int $principal, array $secondaires): array
    {
        if ($principal === null || !estGenreSelectionnable($principal)) {
            return ['succes' => false, 'message' => 'Choisissez votre genre principal dans la liste.'];
        }
        $secondaires = array_values(array_unique(array_filter(array_map('intval', $secondaires), fn($g) => $g !== $principal)));
        if (count($secondaires) > 3) {
            return ['succes' => false, 'message' => 'Trois genres secondaires au plus.'];
        }
        foreach ($secondaires as $g) {
            if (!estGenreSelectionnable($g)) {
                return ['succes' => false, 'message' => 'Genre secondaire invalide.'];
            }
        }
        $db = self::base();
        $db->beginTransaction();
        try {
            $db->prepare('DELETE FROM artist_genres WHERE artist_id = ?')->execute([$artistId]);
            $inserer = $db->prepare('INSERT INTO artist_genres (artist_id, genre_id, is_primary) VALUES (?, ?, ?)');
            $inserer->execute([$artistId, $principal, 1]);
            foreach ($secondaires as $g) {
                $inserer->execute([$artistId, $g, 0]);
            }
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
        return ['succes' => true, 'message' => 'Genres enregistres.'];
    }

    /** @return array<int,array<string,mixed>> artistes actifs sans genre principal */
    public static function artistesSansGenre(): array
    {
        return self::base()->query(
            'SELECT a.id, a.stage_name FROM artists a
              WHERE a.is_active = 1 AND a.deleted_at IS NULL
                AND NOT EXISTS (SELECT 1 FROM artist_genres ag WHERE ag.artist_id = a.id AND ag.is_primary = 1)
              ORDER BY a.stage_name'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    // -----------------------------------------------------------------
    // Administration (TAXO-03)
    // -----------------------------------------------------------------

    /** Arbre complet : categories et leurs genres, avec l'usage. */
    public static function arbre(): array
    {
        $lignes = self::base()->query(
            "SELECT g.*, (SELECT COUNT(*) FROM tracks t WHERE t.genre_id = g.id AND t.deleted_at IS NULL) AS titres,
                    (SELECT COUNT(*) FROM releases r WHERE r.genre_id = g.id AND r.deleted_at IS NULL) AS sorties,
                    (SELECT COUNT(*) FROM artist_genres ag WHERE ag.genre_id = g.id) AS artistes,
                    c.name AS cible
               FROM genres g LEFT JOIN genres c ON c.id = g.merged_into
              ORDER BY g.sort_order, g.name"
        )->fetchAll(PDO::FETCH_ASSOC);
        $categories = [];
        foreach ($lignes as $l) {
            if ($l['parent_id'] === null) {
                $categories[(int) $l['id']] = $l + ['genres' => []];
            }
        }
        foreach ($lignes as $l) {
            if ($l['parent_id'] !== null && isset($categories[(int) $l['parent_id']])) {
                $categories[(int) $l['parent_id']]['genres'][] = $l;
            }
        }
        return array_values($categories);
    }

    /** @return array{succes:bool, message:string, id:?int} */
    public static function creer(string $nom, ?int $categorie, string $description, int $auteur): array
    {
        $nom = trim($nom);
        if (mb_strlen($nom) < 2 || mb_strlen($nom) > 50) {
            return ['succes' => false, 'message' => 'Nom de 2 a 50 caracteres.', 'id' => null];
        }
        $db = self::base();
        if ($categorie !== null && !self::estCategorieActive($categorie)) {
            return ['succes' => false, 'message' => 'Categorie inconnue ou archivee.', 'id' => null];
        }
        $existe = $db->prepare('SELECT 1 FROM genres WHERE LOWER(name) = LOWER(?) OR LOWER(name_french) = LOWER(?)');
        $existe->execute([$nom, $nom]);
        if ($existe->fetchColumn()) {
            return ['succes' => false, 'message' => 'Ce nom existe deja dans le referentiel (eventuellement archive ou fusionne).', 'id' => null];
        }
        $ordre = (int) $db->query('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM genres')->fetchColumn();
        $db->prepare("INSERT INTO genres (parent_id, name, name_french, slug, description, is_active, status, sort_order) VALUES (?, ?, ?, ?, ?, 1, 'active', ?)")
           ->execute([$categorie, $nom, $nom, self::slugLibre($nom), trim($description) ?: null, $ordre]);
        $id = (int) $db->lastInsertId();
        self::tracer($id, 'creation', null, ['nom' => $nom, 'categorie' => $categorie], $auteur);
        return ['succes' => true, 'message' => ($categorie === null ? 'Categorie' : 'Genre') . ' « ' . $nom . ' » cree.', 'id' => $id];
    }

    /**
     * Renommage, description, couleur, icone, categorie, ordre. Le slug ne
     * change jamais : les liens existants restent valides.
     *
     * @return array{succes:bool, message:string}
     */
    public static function modifier(int $id, array $valeurs, int $auteur): array
    {
        $avant = self::genre($id);
        if (!$avant || $avant['status'] !== 'active') {
            return ['succes' => false, 'message' => 'Genre introuvable, archive ou fusionne.'];
        }
        $nom = trim((string) ($valeurs['nom'] ?? $avant['name_french'] ?? $avant['name']));
        $couleur = (string) ($valeurs['couleur'] ?? $avant['color'] ?? '');
        $icone = (string) ($valeurs['icone'] ?? $avant['icon'] ?? '');
        $parent = array_key_exists('categorie', $valeurs) ? ($valeurs['categorie'] === null ? null : (int) $valeurs['categorie']) : ($avant['parent_id'] !== null ? (int) $avant['parent_id'] : null);
        if (mb_strlen($nom) < 2 || mb_strlen($nom) > 50) {
            return ['succes' => false, 'message' => 'Nom de 2 a 50 caracteres.'];
        }
        if ($couleur !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $couleur)) {
            return ['succes' => false, 'message' => 'Couleur au format #RRGGBB.'];
        }
        if ($icone !== '' && !preg_match('/^fa-[a-z0-9-]{1,40}$/', $icone)) {
            return ['succes' => false, 'message' => 'Icone Font Awesome au format fa-nom.'];
        }
        // Une categorie reste une categorie, un genre reste un genre.
        if (($avant['parent_id'] === null) !== ($parent === null) || ($parent !== null && !self::estCategorieActive($parent))) {
            return ['succes' => false, 'message' => 'Rattachement impossible : un genre se rattache a une categorie active, une categorie reste une categorie.'];
        }
        $doublon = self::base()->prepare('SELECT 1 FROM genres WHERE id <> ? AND (LOWER(name) = LOWER(?) OR LOWER(name_french) = LOWER(?))');
        $doublon->execute([$id, $nom, $nom]);
        if ($doublon->fetchColumn()) {
            return ['succes' => false, 'message' => 'Ce nom est deja utilise.'];
        }
        self::base()->prepare('UPDATE genres SET name = ?, name_french = ?, description = ?, color = ?, icon = ?, parent_id = ?, sort_order = ? WHERE id = ?')
            ->execute([$nom, $nom, trim((string) ($valeurs['description'] ?? $avant['description'] ?? '')) ?: null, $couleur ?: null, $icone ?: null, $parent,
                (int) ($valeurs['ordre'] ?? $avant['sort_order']), $id]);
        self::tracer($id, 'modification', ['nom' => $avant['name_french'] ?? $avant['name'], 'categorie' => $avant['parent_id']], ['nom' => $nom, 'categorie' => $parent], $auteur);
        return ['succes' => true, 'message' => 'Modification enregistree (le lien du genre ne change pas).'];
    }

    /**
     * Fusion : tous les contenus (titres, sorties, artistes, agregats) passent
     * a la cible ; la source devient « fusionnee », son slug redirige.
     *
     * @return array{succes:bool, message:string}
     */
    public static function fusionner(int $source, int $cible, string $motif, int $auteur): array
    {
        $motif = trim($motif);
        $s = self::genre($source);
        $c = self::genre($cible);
        if (!$s || !$c || $source === $cible || $s['status'] !== 'active' || $c['status'] !== 'active' || mb_strlen($motif) < 5) {
            return ['succes' => false, 'message' => 'Deux genres actifs distincts et un motif (5 caracteres au moins) sont necessaires.'];
        }
        $categorie = $s['parent_id'] === null;
        if ($categorie !== ($c['parent_id'] === null)) {
            return ['succes' => false, 'message' => 'On fusionne un genre dans un genre, une categorie dans une categorie.'];
        }
        $db = self::base();
        $db->beginTransaction();
        try {
            if ($categorie) {
                $db->prepare('UPDATE genres SET parent_id = ? WHERE parent_id = ?')->execute([$cible, $source]);
                $db->prepare('UPDATE daily_rollups SET category_id = ? WHERE category_id = ?')->execute([$cible, $source]);
            } else {
                $n = $db->prepare('UPDATE tracks SET genre_id = ? WHERE genre_id = ?');
                $n->execute([$cible, $source]);
                $db->prepare('UPDATE releases SET genre_id = ? WHERE genre_id = ?')->execute([$cible, $source]);
                // Artistes : un artiste qui avait deja la cible garde une seule
                // ligne ; le statut « principal » suit. Ordre impose par
                // l'unicite du genre principal : retirer la source, puis
                // promouvoir la cible.
                $promus = $db->prepare(
                    'SELECT src.artist_id FROM artist_genres src JOIN artist_genres cible ON cible.artist_id = src.artist_id AND cible.genre_id = ?
                      WHERE src.genre_id = ? AND src.is_primary = 1'
                );
                $promus->execute([$cible, $source]);
                $promus = array_map('intval', $promus->fetchAll(PDO::FETCH_COLUMN));
                $db->prepare('DELETE src FROM artist_genres src JOIN artist_genres cible ON cible.artist_id = src.artist_id AND cible.genre_id = ? WHERE src.genre_id = ?')
                   ->execute([$cible, $source]);
                if ($promus !== []) {
                    $db->exec('UPDATE artist_genres SET is_primary = 1 WHERE genre_id = ' . (int) $cible . ' AND artist_id IN (' . implode(',', $promus) . ')');
                }
                $db->prepare('UPDATE artist_genres SET genre_id = ? WHERE genre_id = ?')->execute([$cible, $source]);
                $db->prepare('UPDATE daily_rollups SET genre_id = ?, category_id = ? WHERE genre_id = ?')->execute([$cible, (int) $c['parent_id'], $source]);
            }
            $db->prepare("UPDATE genres SET status = 'merged', merged_into = ?, is_active = 0 WHERE id = ?")->execute([$cible, $source]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
        self::tracer($source, 'fusion', ['genre' => $s['name']], ['fusionne_dans' => $c['name']], $auteur, $motif);
        return ['succes' => true, 'message' => sprintf('« %s » fusionne dans « %s » : contenus reaffectes, ancien lien redirige.', $s['name_french'] ?? $s['name'], $c['name_french'] ?? $c['name'])];
    }

    /** @return array{succes:bool, message:string} */
    public static function archiver(int $id, string $motif, int $auteur): array
    {
        $motif = trim($motif);
        $g = self::genre($id);
        if (!$g || $g['status'] !== 'active' || mb_strlen($motif) < 5) {
            return ['succes' => false, 'message' => 'Genre actif et motif (5 caracteres au moins) necessaires.'];
        }
        if ($g['parent_id'] === null) {
            $actifs = self::base()->prepare("SELECT COUNT(*) FROM genres WHERE parent_id = ? AND status = 'active'");
            $actifs->execute([$id]);
            if ((int) $actifs->fetchColumn() > 0) {
                return ['succes' => false, 'message' => 'Archivez, fusionnez ou deplacez d\'abord les genres de cette categorie.'];
            }
        }
        self::base()->prepare("UPDATE genres SET status = 'archived', is_active = 0 WHERE id = ?")->execute([$id]);
        self::tracer($id, 'archivage', null, ['statut' => 'archived'], $auteur, $motif);
        return ['succes' => true, 'message' => 'Archive : plus proposable, les contenus existants gardent leur classement.'];
    }

    /** Suit les fusions : genre actif correspondant a un slug, ou null. */
    public static function resoudreSlug(string $slug): ?array
    {
        $stmt = self::base()->prepare('SELECT * FROM genres WHERE slug = ?');
        $stmt->execute([$slug]);
        $g = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        for ($i = 0; $g && $g['status'] === 'merged' && $g['merged_into'] !== null && $i < 10; $i++) {
            $g = self::genre((int) $g['merged_into']);
        }
        return $g;
    }

    // -----------------------------------------------------------------
    // Propositions des artistes
    // -----------------------------------------------------------------

    /** @return array{succes:bool, message:string} */
    public static function proposer(int $artistId, string $nom, ?int $categorie, string $note): array
    {
        $nom = trim($nom);
        if (mb_strlen($nom) < 2 || mb_strlen($nom) > 50 || ($categorie !== null && !self::estCategorieActive($categorie))) {
            return ['succes' => false, 'message' => 'Nom de 2 a 50 caracteres et categorie valide.'];
        }
        $db = self::base();
        $existe = $db->prepare("SELECT name FROM genres WHERE (LOWER(name) = LOWER(?) OR LOWER(name_french) = LOWER(?)) AND status = 'active'");
        $existe->execute([$nom, $nom]);
        if ($existe->fetchColumn()) {
            return ['succes' => false, 'message' => 'Ce genre existe deja : choisissez-le dans la liste.'];
        }
        $enCours = $db->prepare("SELECT COUNT(*) FROM genre_proposals WHERE artist_id = ? AND status = 'pending'");
        $enCours->execute([$artistId]);
        if ((int) $enCours->fetchColumn() >= 3) {
            return ['succes' => false, 'message' => 'Trois propositions en attente au plus.'];
        }
        $db->prepare('INSERT INTO genre_proposals (artist_id, name, category_id, note) VALUES (?, ?, ?, ?)')
           ->execute([$artistId, $nom, $categorie, mb_substr(trim($note), 0, 500) ?: null]);
        return ['succes' => true, 'message' => 'Proposition envoyee a l\'equipe editoriale. Le genre ne sera utilisable qu\'apres validation.'];
    }

    /** @return array{succes:bool, message:string} */
    public static function deciderProposition(int $id, bool $accepter, string $motif, ?int $categorie, int $auteur): array
    {
        $motif = trim($motif);
        $stmt = self::base()->prepare("SELECT p.*, a.stage_name, u.email, u.first_name FROM genre_proposals p JOIN artists a ON a.id = p.artist_id LEFT JOIN users u ON u.id = a.user_id WHERE p.id = ? AND p.status = 'pending'");
        $stmt->execute([$id]);
        $p = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$p || mb_strlen($motif) < 5) {
            return ['succes' => false, 'message' => 'Proposition introuvable, ou motif manquant (5 caracteres au moins).'];
        }
        $genreId = null;
        if ($accepter) {
            $r = self::creer((string) $p['name'], $categorie ?? ($p['category_id'] !== null ? (int) $p['category_id'] : null), (string) ($p['note'] ?? ''), $auteur);
            if (!$r['succes'] || $r['id'] === null) {
                return ['succes' => false, 'message' => $r['message']];
            }
            $genreId = $r['id'];
        }
        self::base()->prepare('UPDATE genre_proposals SET status = ?, genre_id = ?, decided_by = ?, decided_at = NOW(), decision_reason = ? WHERE id = ?')
            ->execute([$accepter ? 'accepted' : 'rejected', $genreId, $auteur, mb_substr($motif, 0, 500), $id]);
        if (!empty($p['email'])) {
            sendEmail((string) $p['email'], 'Tchadok - votre proposition de genre', '<p>Bonjour ' . htmlspecialchars((string) $p['first_name'], ENT_QUOTES, 'UTF-8')
                . ',</p><p>Votre proposition « ' . htmlspecialchars((string) $p['name'], ENT_QUOTES, 'UTF-8') . ' » est ' . ($accepter ? 'acceptee : le genre est disponible.' : 'refusee.')
                . '</p><p>Motif : ' . htmlspecialchars($motif, ENT_QUOTES, 'UTF-8') . '</p>');
        }
        return ['succes' => true, 'message' => $accepter ? 'Proposition acceptee, genre cree.' : 'Proposition refusee, l\'artiste est prevenu.'];
    }

    /** @return array<int,array<string,mixed>> */
    public static function propositionsEnAttente(): array
    {
        return self::base()->query(
            "SELECT p.*, a.stage_name, c.name AS categorie FROM genre_proposals p JOIN artists a ON a.id = p.artist_id
               LEFT JOIN genres c ON c.id = p.category_id WHERE p.status = 'pending' ORDER BY p.created_at"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    // -----------------------------------------------------------------

    public static function genre(int $id): ?array
    {
        $stmt = self::base()->prepare('SELECT * FROM genres WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private static function estCategorieActive(int $id): bool
    {
        $stmt = self::base()->prepare("SELECT 1 FROM genres WHERE id = ? AND parent_id IS NULL AND status = 'active'");
        $stmt->execute([$id]);
        return (bool) $stmt->fetchColumn();
    }

    private static function slugLibre(string $nom): string
    {
        $base = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nom) ?: $nom)), '-') ?: 'genre';
        $slug = $base;
        $stmt = self::base()->prepare('SELECT 1 FROM genres WHERE slug = ?');
        for ($i = 2; $stmt->execute([$slug]) && $stmt->fetchColumn(); $i++) {
            $slug = $base . '-' . $i;
        }
        return $slug;
    }

    private static function tracer(int $id, string $operation, ?array $avant, ?array $apres, int $auteur, string $motif = ''): void
    {
        JournalAudit::enregistrer('taxonomie.modifiee', [
            'cible_type' => 'genre', 'cible_id' => $id, 'avant' => $avant, 'apres' => ($apres ?? []) + ['operation' => $operation],
            'raison' => $motif ?: null, 'acteur' => $auteur,
        ]);
    }

    private static function base(): PDO
    {
        return TchadokDatabase::getInstance()->getConnection();
    }
}
