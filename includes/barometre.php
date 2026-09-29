<?php
/**
 * Barometre Tchadok : classements arretes (CHART-01) et vues analytiques
 * (CHART-02).
 *
 * ARRETE
 *   Une edition couvre une semaine (lundi 00:00 - dimanche 23:59), un mois ou
 *   une annee, heure de N'Djamena. Elle s'arrete 48 h apres la fin de sa
 *   periode (delai de consolidation, STAT-01), puis ne change plus.
 *   Ecoutes comptees : ecoutes CERTIFIEES (STAT-03), non revoquees, de la
 *   periode, certifiees avant l'arrete. Une ecoute certifiee APRES l'arrete de
 *   sa periode (quarantaine levee plus tard) compte pour l'edition suivante,
 *   jamais pour une edition deja publiee.
 *   Ventes comptees : commandes payees dans la periode et non remboursees au
 *   moment de l'arrete.
 *
 * CLASSEMENTS (separes : un single ne concourt pas contre un album, les ventes
 * ne se melangent pas aux ecoutes)
 *   titres (Top 50), titres_ventes, sorties_<format>, sorties_ventes,
 *   artistes (Top 20). Egalite : auditeurs uniques, puis anteriorite.
 */

declare(strict_types=1);

final class Barometre
{
    public const VERSION_METHODOLOGIE = '1';

    /** Delai de consolidation avant l'arrete, en heures. */
    public const CONSOLIDATION = 48;

    public const CLASSEMENTS = [
        'titres'              => ['libelle' => 'Top titres (ecoutes)', 'taille' => 50, 'objet' => 'track', 'mesure' => 'ecoutes'],
        'titres_ventes'       => ['libelle' => 'Top titres (ventes)', 'taille' => 20, 'objet' => 'track', 'mesure' => 'ventes'],
        'sorties_album'       => ['libelle' => 'Top albums', 'taille' => 20, 'objet' => 'release', 'mesure' => 'ecoutes', 'format' => 'album'],
        'sorties_ep'          => ['libelle' => 'Top EP', 'taille' => 20, 'objet' => 'release', 'mesure' => 'ecoutes', 'format' => 'ep'],
        'sorties_single'      => ['libelle' => 'Top singles', 'taille' => 20, 'objet' => 'release', 'mesure' => 'ecoutes', 'format' => 'single'],
        'sorties_maxi_single' => ['libelle' => 'Top maxi-singles', 'taille' => 20, 'objet' => 'release', 'mesure' => 'ecoutes', 'format' => 'maxi_single'],
        'sorties_compilation' => ['libelle' => 'Top compilations', 'taille' => 20, 'objet' => 'release', 'mesure' => 'ecoutes', 'format' => 'compilation'],
        'sorties_ventes'      => ['libelle' => 'Top sorties (ventes)', 'taille' => 20, 'objet' => 'release', 'mesure' => 'ventes'],
        'artistes'            => ['libelle' => 'Top artistes', 'taille' => 20, 'objet' => 'artist', 'mesure' => 'ecoutes'],
    ];

    public const PERIODES = ['weekly' => 'Hebdomadaire', 'monthly' => 'Mensuel', 'yearly' => 'Annuel'];

    // -----------------------------------------------------------------
    // Periodes
    // -----------------------------------------------------------------

    /** Periode contenant une date : [debut, fin, slug]. */
    public static function periode(string $type, string $date): array
    {
        $t = strtotime($date);
        return match ($type) {
            'weekly'  => [date('Y-m-d', strtotime('monday this week', $t)), date('Y-m-d', strtotime('sunday this week', $t)), date('o', $t) . '-S' . date('W', $t)],
            'monthly' => [date('Y-m-01', $t), date('Y-m-t', $t), date('Y-m', $t)],
            'yearly'  => [date('Y-01-01', $t), date('Y-12-31', $t), date('Y', $t)],
            default   => throw new InvalidArgumentException('Periode inconnue : ' . $type),
        };
    }

    /** Heure a partir de laquelle une periode peut etre arretee. */
    public static function heureArrete(string $finPeriode): int
    {
        return strtotime($finPeriode . ' +1 day') + self::CONSOLIDATION * 3600;
    }

    // -----------------------------------------------------------------
    // Arrete (CHART-01)
    // -----------------------------------------------------------------

    /**
     * Arrete les editions echues et manquantes, dans l'ordre chronologique
     * (les rangs precedents en dependent).
     *
     * @return array<int,string> slugs arretes
     */
    public static function arreterEchues(int $maximum = 60): array
    {
        $db = self::base();
        $premier = $db->query(
            "SELECT LEAST(COALESCE((SELECT MIN(listened_at) FROM streams_certified), '9999-12-31'),
                          COALESCE((SELECT MIN(paid_at) FROM orders WHERE status = 'paid'), '9999-12-31'))"
        )->fetchColumn();
        if (!$premier || str_starts_with((string) $premier, '9999')) {
            return [];
        }
        $arretees = [];
        foreach (array_keys(self::PERIODES) as $type) {
            [$debut] = self::periode($type, (string) $premier);
            while (count($arretees) < $maximum) {
                [$d, $f] = self::periode($type, $debut);
                if (self::heureArrete($f) > time()) {
                    break;
                }
                $existe = $db->prepare('SELECT 1 FROM chart_editions WHERE period_type = ? AND period_start = ?');
                $existe->execute([$type, $d]);
                if (!$existe->fetchColumn()) {
                    $arretees[] = self::arreter($type, $d)['slug'];
                }
                $debut = date('Y-m-d', strtotime($f . ' +1 day'));
            }
        }
        return $arretees;
    }

    /**
     * Arrete une edition. Refuse une periode non echue ou deja arretee.
     *
     * @return array{id:int, slug:string, ecoutes:int, ventes:int}
     */
    public static function arreter(string $type, string $date): array
    {
        [$debut, $fin, $slug] = self::periode($type, $date);
        if (self::heureArrete($fin) > time()) {
            throw new RuntimeException("Periode {$slug} non consolidee : arrete possible le " . date('d/m/Y H:i', self::heureArrete($fin)) . '.');
        }
        $db = self::base();
        $db->beginTransaction();
        try {
            $existe = $db->prepare('SELECT id FROM chart_editions WHERE period_type = ? AND period_start = ? FOR UPDATE');
            $existe->execute([$type, $debut]);
            if ($existe->fetchColumn()) {
                throw new RuntimeException("Edition {$slug} deja arretee.");
            }
            $maintenant = date('Y-m-d H:i:s');
            // Arrete precedent du meme type : les ecoutes certifiees depuis,
            // mais ecoutees avant cette periode, comptent ici.
            $stmt = $db->prepare('SELECT arrete_at FROM chart_editions WHERE period_type = ? AND period_start < ? ORDER BY period_start DESC LIMIT 1');
            $stmt->execute([$type, $debut]);
            $arretePrecedent = $stmt->fetchColumn() ?: null;

            $ecoutes = self::ecoutesDeLaPeriode($db, $debut, $fin, $maintenant, $arretePrecedent);
            $ventes = self::ventesDeLaPeriode($db, $debut, $fin);
            $totalEcoutes = array_sum(array_map(fn($l) => (int) $l['n'], $ecoutes));
            $totalVentes = array_sum(array_map(fn($l) => (int) $l['n'], $ventes));

            $db->prepare(
                'INSERT INTO chart_editions (period_type, period_start, period_end, slug, methodology_version, streams_total, sales_total, arrete_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([$type, $debut, $fin, $slug, self::VERSION_METHODOLOGIE, $totalEcoutes, $totalVentes, $maintenant]);
            $editionId = (int) $db->lastInsertId();

            $precedente = $db->prepare('SELECT id FROM chart_editions WHERE period_type = ? AND period_start = ?');
            [$debutPrecedent] = self::periode($type, date('Y-m-d', strtotime($debut . ' -1 day')));
            $precedente->execute([$type, $debutPrecedent]);
            $precedenteId = (int) ($precedente->fetchColumn() ?: 0);

            foreach (self::CLASSEMENTS as $cle => $def) {
                $lignes = self::classer($db, $def, $ecoutes, $ventes);
                self::enregistrer($db, $editionId, $precedenteId, $type, $cle, $def, $lignes);
            }
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
        return ['id' => $editionId, 'slug' => $slug, 'ecoutes' => $totalEcoutes, 'ventes' => $totalVentes];
    }

    private static function ecoutesDeLaPeriode(PDO $db, string $debut, string $fin, string $arrete, ?string $arretePrecedent): array
    {
        $stmt = $db->prepare(
            "SELECT sc.track_id, COALESCE(t.release_id, t.album_id) AS release_id, sc.artist_id,
                    COUNT(*) AS n, COUNT(DISTINCT COALESCE(sc.listener_key, sc.stream_id)) AS auditeurs, MIN(sc.listened_at) AS premiere
               FROM streams_certified sc
               JOIN tracks t ON t.id = sc.track_id
               LEFT JOIN stream_revocations rv ON rv.stream_id = sc.stream_id
              WHERE rv.stream_id IS NULL AND sc.certified_at <= ?
                AND ((sc.listened_at >= ? AND sc.listened_at < ? + INTERVAL 1 DAY)
                     OR (? IS NOT NULL AND sc.listened_at < ? AND sc.certified_at > ?))
              GROUP BY sc.track_id, COALESCE(t.release_id, t.album_id), sc.artist_id"
        );
        $stmt->execute([$arrete, $debut, $fin, $arretePrecedent, $debut, $arretePrecedent]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function ventesDeLaPeriode(PDO $db, string $debut, string $fin): array
    {
        $stmt = $db->prepare(
            "SELECT oi.item_type, oi.item_id, oi.artist_id, SUM(oi.quantity) AS n, COUNT(DISTINCT o.user_id) AS auditeurs, MIN(o.paid_at) AS premiere
               FROM order_items oi JOIN orders o ON o.id = oi.order_id AND o.status = 'paid'
              WHERE oi.item_type IN ('track', 'release') AND o.paid_at >= ? AND o.paid_at < ? + INTERVAL 1 DAY
              GROUP BY oi.item_type, oi.item_id, oi.artist_id"
        );
        $stmt->execute([$debut, $fin]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int,array{id:int, valeur:int, auditeurs:int, premiere:string}> tries */
    private static function classer(PDO $db, array $def, array $ecoutes, array $ventes): array
    {
        $cumul = [];
        $ajouter = static function (int $id, int $n, int $auditeurs, string $premiere) use (&$cumul): void {
            if ($id <= 0) {
                return;
            }
            $cumul[$id] ??= ['id' => $id, 'valeur' => 0, 'auditeurs' => 0, 'premiere' => $premiere];
            $cumul[$id]['valeur'] += $n;
            $cumul[$id]['auditeurs'] += $auditeurs;
            $cumul[$id]['premiere'] = min($cumul[$id]['premiere'], $premiere);
        };
        if ($def['mesure'] === 'ecoutes') {
            $colonne = ['track' => 'track_id', 'release' => 'release_id', 'artist' => 'artist_id'][$def['objet']];
            foreach ($ecoutes as $l) {
                $ajouter((int) $l[$colonne], (int) $l['n'], (int) $l['auditeurs'], (string) $l['premiere']);
            }
        } else {
            foreach ($ventes as $l) {
                if ($l['item_type'] === $def['objet']) {
                    $ajouter((int) $l['item_id'], (int) $l['n'], (int) $l['auditeurs'], (string) $l['premiere']);
                }
            }
        }
        // Seuls les objets publies et en ligne concourent.
        $ids = array_keys($cumul);
        if ($ids === []) {
            return [];
        }
        $liste = implode(',', array_map('intval', $ids));
        $valides = match ($def['objet']) {
            'track'   => $db->query("SELECT id FROM tracks WHERE id IN ({$liste}) AND status = 'approved' AND deleted_at IS NULL")->fetchAll(PDO::FETCH_COLUMN),
            'release' => $db->query("SELECT id FROM releases WHERE id IN ({$liste}) AND status = 'approved' AND deleted_at IS NULL"
                . (isset($def['format']) ? ' AND format = ' . $db->quote($def['format']) : ''))->fetchAll(PDO::FETCH_COLUMN),
            'artist'  => $db->query("SELECT id FROM artists WHERE id IN ({$liste}) AND is_active = 1 AND deleted_at IS NULL")->fetchAll(PDO::FETCH_COLUMN),
        };
        $cumul = array_intersect_key($cumul, array_flip(array_map('intval', $valides)));
        usort($cumul, static fn ($a, $b) => [$b['valeur'], $b['auditeurs'], $a['premiere'], $a['id']] <=> [$a['valeur'], $a['auditeurs'], $b['premiere'], $b['id']]);
        return array_slice(array_values($cumul), 0, $def['taille']);
    }

    private static function enregistrer(PDO $db, int $editionId, int $precedenteId, string $type, string $cle, array $def, array $lignes): void
    {
        $rangPrecedent = $db->prepare('SELECT `rank` FROM chart_entries WHERE edition_id = ? AND chart = ? AND item_id = ?');
        $historique = $db->prepare(
            'SELECT COUNT(*) AS n, MIN(e.`rank`) AS meilleur FROM chart_entries e JOIN chart_editions ed ON ed.id = e.edition_id
              WHERE ed.period_type = ? AND e.chart = ? AND e.item_id = ? AND e.edition_id <> ?'
        );
        $inserer = $db->prepare(
            'INSERT INTO chart_entries (edition_id, chart, `rank`, item_type, item_id, value, listeners, previous_rank, peak_rank, periods_on_chart, is_new)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($lignes as $i => $l) {
            $rang = $i + 1;
            $precedent = null;
            if ($precedenteId > 0) {
                $rangPrecedent->execute([$precedenteId, $cle, $l['id']]);
                $precedent = ($r = $rangPrecedent->fetchColumn()) !== false ? (int) $r : null;
            }
            $historique->execute([$type, $cle, $l['id'], $editionId]);
            $h = $historique->fetch(PDO::FETCH_ASSOC);
            $inserer->execute([
                $editionId, $cle, $rang, $def['objet'], $l['id'], $l['valeur'], $l['auditeurs'], $precedent,
                $h['meilleur'] !== null ? min($rang, (int) $h['meilleur']) : $rang, (int) $h['n'] + 1, (int) $h['n'] === 0 ? 1 : 0,
            ]);
        }
    }

    // -----------------------------------------------------------------
    // Lecture
    // -----------------------------------------------------------------

    public static function edition(?string $slug = null, string $type = 'weekly'): ?array
    {
        $db = self::base();
        if ($slug !== null && $slug !== '') {
            $stmt = $db->prepare('SELECT * FROM chart_editions WHERE slug = ?');
            $stmt->execute([$slug]);
        } else {
            $stmt = $db->prepare('SELECT * FROM chart_editions WHERE period_type = ? ORDER BY period_start DESC LIMIT 1');
            $stmt->execute([$type]);
        }
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** @return array<int,array<string,mixed>> editions d'un type, plus recente d'abord */
    public static function archives(string $type, int $limite = 60): array
    {
        $stmt = self::base()->prepare('SELECT * FROM chart_editions WHERE period_type = ? ORDER BY period_start DESC LIMIT ' . max(1, $limite));
        $stmt->execute([$type]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Entrees d'un classement, avec les libelles. Filtres facultatifs par
     * genre ou categorie : le rang reste le rang NATIONAL.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function entrees(int $editionId, string $classement, ?int $genre = null, ?int $categorie = null): array
    {
        $def = self::CLASSEMENTS[$classement] ?? null;
        if ($def === null) {
            return [];
        }
        $db = self::base();
        [$jointure, $libelle, $genreCol] = match ($def['objet']) {
            'track'   => ['JOIN tracks o ON o.id = e.item_id JOIN artists a ON a.id = o.artist_id', 'o.title AS titre, a.stage_name AS artiste, a.id AS artist_id', 'o.genre_id'],
            'release' => ['JOIN releases o ON o.id = e.item_id JOIN artists a ON a.id = o.artist_id', 'o.title AS titre, a.stage_name AS artiste, a.id AS artist_id', 'o.genre_id'],
            'artist'  => ['JOIN artists o ON o.id = e.item_id JOIN artists a ON a.id = o.id', 'o.stage_name AS titre, NULL AS artiste, a.id AS artist_id', 'NULL'],
        };
        $filtre = '';
        $params = [$editionId, $classement];
        if ($genre !== null && $genreCol !== 'NULL') {
            $filtre = " AND {$genreCol} = ?";
            $params[] = $genre;
        } elseif ($categorie !== null && $genreCol !== 'NULL') {
            $filtre = " AND {$genreCol} IN (SELECT id FROM genres WHERE parent_id = ?)";
            $params[] = $categorie;
        }
        $stmt = $db->prepare("SELECT e.*, {$libelle} FROM chart_entries e {$jointure} WHERE e.edition_id = ? AND e.chart = ?{$filtre} ORDER BY e.`rank`");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Evolution affichable : « nouveau », « = », « +3 », « -2 », « retour ». */
    public static function evolution(array $entree): string
    {
        if ((int) $entree['is_new'] === 1) {
            return 'nouveau';
        }
        if ($entree['previous_rank'] === null) {
            return 'retour';
        }
        $d = (int) $entree['previous_rank'] - (int) $entree['rank'];
        return $d === 0 ? '=' : ($d > 0 ? '+' . $d : (string) $d);
    }

    public static function libellePeriode(array $edition): string
    {
        $debut = strtotime((string) $edition['period_start']);
        $fin = strtotime((string) $edition['period_end']);
        return match ($edition['period_type']) {
            'weekly'  => sprintf('Semaine %s (du %s au %s)', (int) date('W', $debut), date('d/m', $debut), date('d/m/Y', $fin)),
            'monthly' => RepartitionMois::libelle(date('Y-m', $debut)),
            default   => 'Annee ' . date('Y', $debut),
        };
    }

    // -----------------------------------------------------------------
    // Vues analytiques (CHART-02), depuis les agregats certifies
    // -----------------------------------------------------------------

    /**
     * @return array{total:array{ecoutes:int, ventes:int, revenu:float}, lignes:array<int,array<string,mixed>>}
     */
    public static function vue(string $dimension, string $debut, string $fin): array
    {
        $db = self::base();
        $total = self::totaux($db, $debut, $fin);
        [$precedentDebut, $precedentFin] = self::periodePrecedente($debut, $fin);
        $lignes = match ($dimension) {
            'genre'     => self::parColonne($db, 'r.genre_id', "COALESCE(g.name_french, g.name, 'Sans genre')", 'LEFT JOIN genres g ON g.id = r.genre_id', $debut, $fin, $precedentDebut, $precedentFin),
            'categorie' => self::parColonne($db, 'r.category_id', "COALESCE(c.name_french, c.name, 'Sans categorie')", 'LEFT JOIN genres c ON c.id = r.category_id', $debut, $fin, $precedentDebut, $precedentFin),
            'format'    => self::parColonne($db, "COALESCE(s.format, 'hors sortie')", "COALESCE(s.format, 'hors sortie')", 'LEFT JOIN releases s ON s.id = r.release_id', $debut, $fin, $precedentDebut, $precedentFin),
            'region'    => self::parColonne($db, "NULLIF(r.region, '')", "COALESCE(NULLIF(r.region, ''), 'Non localise')", '', $debut, $fin, $precedentDebut, $precedentFin),
            default     => throw new InvalidArgumentException('Dimension inconnue : ' . $dimension),
        };
        foreach ($lignes as &$l) {
            $l['part_ecoutes'] = $total['ecoutes'] > 0 ? round(100 * $l['ecoutes'] / $total['ecoutes'], 1) : 0.0;
            $l['part_ventes'] = $total['ventes'] > 0 ? round(100 * $l['ventes'] / $total['ventes'], 1) : 0.0;
            $l['croissance'] = $l['ecoutes_precedentes'] > 0 ? round(100 * ($l['ecoutes'] - $l['ecoutes_precedentes']) / $l['ecoutes_precedentes'], 1) : null;
            $l['revenu_par_titre'] = $l['titres'] > 0 ? round($l['revenu'] / $l['titres']) : 0.0;
        }
        unset($l);
        return ['total' => $total, 'lignes' => $lignes];
    }

    /**
     * Indice de decouverte : part des ecoutes allant a des artistes arrives
     * sur la plateforme depuis moins de 12 mois (premier titre depose).
     */
    public static function indiceDecouverte(string $debut, string $fin): float
    {
        $stmt = self::base()->prepare(
            "SELECT COALESCE(SUM(r.streams), 0) AS total,
                    COALESCE(SUM(CASE WHEN p.premier > ? - INTERVAL 12 MONTH AND p.premier <= ? + INTERVAL 1 DAY THEN r.streams ELSE 0 END), 0) AS nouveaux
               FROM daily_rollups r
               LEFT JOIN (SELECT artist_id, MIN(created_at) AS premier FROM tracks GROUP BY artist_id) p ON p.artist_id = r.artist_id
              WHERE r.day BETWEEN ? AND ? AND r.source <> 'vente'"
        );
        $stmt->execute([$fin, $fin, $debut, $fin]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) $r['total'] > 0 ? round(100 * (int) $r['nouveaux'] / (int) $r['total'], 1) : 0.0;
    }

    private static function totaux(PDO $db, string $debut, string $fin): array
    {
        $stmt = $db->prepare("SELECT COALESCE(SUM(streams), 0), COALESCE(SUM(sales), 0), COALESCE(SUM(revenue), 0) FROM daily_rollups WHERE day BETWEEN ? AND ?");
        $stmt->execute([$debut, $fin]);
        [$e, $v, $r] = $stmt->fetch(PDO::FETCH_NUM);
        return ['ecoutes' => (int) $e, 'ventes' => (int) $v, 'revenu' => (float) $r];
    }

    private static function parColonne(PDO $db, string $cle, string $libelle, string $jointure, string $debut, string $fin, string $pDebut, string $pFin): array
    {
        $stmt = $db->prepare(
            "SELECT {$cle} AS cle, {$libelle} AS libelle,
                    SUM(CASE WHEN r.day BETWEEN ? AND ? THEN r.streams ELSE 0 END) AS ecoutes,
                    SUM(CASE WHEN r.day BETWEEN ? AND ? THEN r.sales ELSE 0 END) AS ventes,
                    SUM(CASE WHEN r.day BETWEEN ? AND ? THEN r.revenue ELSE 0 END) AS revenu,
                    SUM(CASE WHEN r.day BETWEEN ? AND ? THEN r.streams ELSE 0 END) AS ecoutes_precedentes,
                    COUNT(DISTINCT CASE WHEN r.day BETWEEN ? AND ? AND r.streams > 0 THEN r.artist_id END) AS artistes_actifs,
                    COUNT(DISTINCT CASE WHEN r.day BETWEEN ? AND ? AND r.track_id > 0 THEN r.track_id END) AS titres
               FROM daily_rollups r {$jointure}
              WHERE r.day BETWEEN ? AND ?
              GROUP BY cle, libelle
             HAVING ecoutes > 0 OR ventes > 0
              ORDER BY ecoutes DESC, ventes DESC"
        );
        $stmt->execute([$debut, $fin, $debut, $fin, $debut, $fin, $pDebut, $pFin, $debut, $fin, $debut, $fin, min($debut, $pDebut), max($fin, $pFin)]);
        return array_map(static fn ($l) => [
            'cle' => $l['cle'], 'libelle' => $l['libelle'], 'ecoutes' => (int) $l['ecoutes'], 'ventes' => (int) $l['ventes'],
            'revenu' => (float) $l['revenu'], 'ecoutes_precedentes' => (int) $l['ecoutes_precedentes'],
            'artistes_actifs' => (int) $l['artistes_actifs'], 'titres' => (int) $l['titres'],
        ], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** Periode de meme longueur, juste avant. */
    private static function periodePrecedente(string $debut, string $fin): array
    {
        $jours = (int) round((strtotime($fin) - strtotime($debut)) / 86400) + 1;
        return [date('Y-m-d', strtotime($debut . " -{$jours} days")), date('Y-m-d', strtotime($debut . ' -1 day'))];
    }

    private static function base(): PDO
    {
        return TchadokDatabase::getInstance()->getConnection();
    }
}

/** Libelle d'un mois en francais, sans dependance a la configuration locale. */
final class RepartitionMois
{
    public static function libelle(string $mois): string
    {
        $noms = ['janvier', 'fevrier', 'mars', 'avril', 'mai', 'juin', 'juillet', 'aout', 'septembre', 'octobre', 'novembre', 'decembre'];
        [$a, $m] = array_map('intval', explode('-', $mois));
        return ucfirst($noms[$m - 1] ?? '?') . ' ' . $a;
    }
}
