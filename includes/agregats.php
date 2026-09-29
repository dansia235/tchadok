<?php
/**
 * Agregats journaliers (STAT-05) et compteurs publics (STAT-06).
 *
 * AGREGATS
 *   `daily_rollups` se calcule jour par jour depuis `streams_certified` (les
 *   seules ecoutes qui comptent) et les commandes payees. Le calcul d'un jour
 *   remplace entierement ce jour : le relancer donne exactement le meme
 *   resultat, et une reconstruction sur n'importe quelle plage aussi.
 *   Jours a recalculer chaque nuit : jamais calcules, ou touches depuis leur
 *   calcul (ecoute certifiee apres coup -- levee de quarantaine --, ecoute
 *   revoquee, commande remboursee ou contestee). Les ecoutes revoquees
 *   (`stream_revocations`) ne comptent plus.
 *
 * COMPTEURS
 *   Les colonnes total_* de tracks, releases et artists etaient tenues par
 *   trois declencheurs qui comptaient le BRUT (fraudes comprises), ne
 *   decomptaient jamais un remboursement et melangeaient unites et francs.
 *   Elles sont desormais recalculees depuis les agregats (et le catalogue) :
 *     tracks.total_streams, releases.total_streams, artists.total_streams
 *                                  ecoutes certifiees ;
 *     tracks.total_sales, releases.total_sales, artists.total_sales
 *                                  ventes en UNITES, remboursements deduits ;
 *     artists.total_earnings       part artiste nette, en FCFA ;
 *     releases.total_tracks, releases.total_duration
 *                                  titres non retires de la sortie ;
 *     tracks.total_downloads       telechargements du journal d'acces
 *                                  (0 tant que SHOP-08 n'est pas livre).
 *   La reconstruction integrale et l'incremental donnent les memes valeurs :
 *   c'est le meme calcul, restreint ou non a quelques identifiants.
 */

declare(strict_types=1);

final class Agregats
{
    /** Recalcule un jour (AAAA-MM-JJ). @return array{streams:int, sales:int} */
    public static function construireJour(string $jour): array
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $jour)) {
            throw new InvalidArgumentException('Jour invalide : ' . $jour);
        }
        $db = self::base();
        $debut = $jour . ' 00:00:00';
        $fin = date('Y-m-d', strtotime($jour . ' +1 day')) . ' 00:00:00';

        $db->beginTransaction();
        try {
            $db->prepare('DELETE FROM daily_rollups WHERE day = ?')->execute([$jour]);
            $db->prepare(
                "INSERT INTO daily_rollups (day, track_id, release_id, artist_id, genre_id, category_id, region, source, listener_type, streams, listeners, duration_seconds)
                 SELECT ?, x.track_id, x.release_id, x.artist_id, x.genre_id, x.category_id, x.region, x.source, x.listener_type,
                        COUNT(*), COUNT(DISTINCT x.listener_key), SUM(x.duration_played)
                   FROM (
                        SELECT sc.track_id, COALESCE(t.release_id, t.album_id, 0) AS release_id, sc.artist_id,
                               COALESCE(t.genre_id, 0) AS genre_id, COALESCE(g.parent_id, 0) AS category_id,
                               LEFT(COALESCE(s.country, ''), 60) AS region,
                               LEFT(COALESCE(NULLIF(sc.source, ''), 'web'), 20) AS source,
                               CASE WHEN sc.user_id IS NULL THEN 'visiteur'
                                    WHEN EXISTS (SELECT 1 FROM subscriptions su JOIN orders o ON o.id = su.order_id AND o.status = 'paid'
                                                  WHERE su.user_id = sc.user_id AND su.status IN ('active', 'cancelled', 'expired')
                                                    AND sc.listened_at >= su.start_date AND sc.listened_at < su.end_date) THEN 'abonne'
                                    ELSE 'compte' END AS listener_type,
                               COALESCE(sc.listener_key, CONCAT('s:', sc.stream_id)) AS listener_key, sc.duration_played
                          FROM streams_certified sc
                          LEFT JOIN tracks t ON t.id = sc.track_id
                          LEFT JOIN genres g ON g.id = t.genre_id
                          LEFT JOIN streams s ON s.id = sc.stream_id
                          LEFT JOIN stream_revocations rv ON rv.stream_id = sc.stream_id
                         WHERE sc.listened_at >= ? AND sc.listened_at < ? AND rv.stream_id IS NULL
                   ) x
                  GROUP BY x.track_id, x.release_id, x.artist_id, x.genre_id, x.category_id, x.region, x.source, x.listener_type"
            )->execute([$jour, $debut, $fin]);
            $db->prepare(
                "INSERT INTO daily_rollups (day, track_id, release_id, artist_id, genre_id, category_id, region, source, listener_type, sales, revenue, artist_revenue)
                 SELECT ?, y.track_id, y.release_id, y.artist_id, y.genre_id, y.category_id, '', 'vente', '-',
                        SUM(y.quantite), SUM(y.montant), SUM(y.part)
                   FROM (
                        SELECT CASE WHEN oi.item_type = 'track' THEN oi.item_id ELSE 0 END AS track_id,
                               CASE WHEN oi.item_type = 'release' THEN oi.item_id ELSE COALESCE(t.release_id, t.album_id, 0) END AS release_id,
                               COALESCE(oi.artist_id, 0) AS artist_id, COALESCE(t.genre_id, r.genre_id, 0) AS genre_id,
                               COALESCE(g.parent_id, 0) AS category_id,
                               oi.quantity AS quantite, oi.unit_price * oi.quantity AS montant, oi.artist_net AS part
                          FROM order_items oi
                          JOIN orders o ON o.id = oi.order_id AND o.status = 'paid'
                          LEFT JOIN tracks t ON oi.item_type = 'track' AND t.id = oi.item_id
                          LEFT JOIN releases r ON oi.item_type = 'release' AND r.id = oi.item_id
                          LEFT JOIN genres g ON g.id = COALESCE(t.genre_id, r.genre_id)
                         WHERE oi.item_type IN ('track', 'release') AND o.paid_at >= ? AND o.paid_at < ?
                   ) y
                  GROUP BY y.track_id, y.release_id, y.artist_id, y.genre_id, y.category_id"
            )->execute([$jour, $debut, $fin]);
            $totaux = $db->prepare('SELECT COALESCE(SUM(streams), 0), COALESCE(SUM(sales), 0) FROM daily_rollups WHERE day = ?');
            $totaux->execute([$jour]);
            [$ecoutes, $ventes] = array_map('intval', $totaux->fetch(PDO::FETCH_NUM));
            $db->prepare('INSERT INTO rollup_runs (day, built_at, streams, sales) VALUES (?, NOW(), ?, ?)
                          ON DUPLICATE KEY UPDATE built_at = NOW(), streams = VALUES(streams), sales = VALUES(sales)')
               ->execute([$jour, $ecoutes, $ventes]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
        return ['streams' => $ecoutes, 'sales' => $ventes];
    }

    /** Reconstruit une plage de jours, bornes comprises. @return int jours calcules */
    public static function construire(string $du, string $au): int
    {
        $n = 0;
        for ($j = strtotime($du); $j <= strtotime($au); $j = strtotime('+1 day', $j)) {
            self::construireJour(date('Y-m-d', $j));
            $n++;
        }
        return $n;
    }

    /**
     * Jours a (re)calculer : jamais calcules, ou touches depuis leur calcul.
     *
     * @return string[]
     */
    public static function joursARecalculer(): array
    {
        $jours = self::base()->query(
            "SELECT DISTINCT DATE(sc.listened_at) FROM streams_certified sc LEFT JOIN rollup_runs r ON r.day = DATE(sc.listened_at)
              WHERE r.day IS NULL OR sc.certified_at >= r.built_at
             UNION
             SELECT DISTINCT DATE(sc.listened_at) FROM stream_revocations rv JOIN streams_certified sc ON sc.stream_id = rv.stream_id
               LEFT JOIN rollup_runs r ON r.day = DATE(sc.listened_at)
              WHERE r.day IS NULL OR rv.revoked_at >= r.built_at
             UNION
             SELECT DISTINCT DATE(o.paid_at) FROM orders o LEFT JOIN rollup_runs r ON r.day = DATE(o.paid_at)
              WHERE o.paid_at IS NOT NULL AND (r.day IS NULL OR o.updated_at >= r.built_at)"
        )->fetchAll(PDO::FETCH_COLUMN);
        sort($jours);
        return array_values(array_filter($jours));
    }

    /**
     * Tache de nuit : recalcule les jours touches, puis les compteurs des
     * titres, sorties et artistes concernes.
     *
     * @return array{jours:int, titres:int}
     */
    public static function actualiser(): array
    {
        $db = self::base();
        $identifiants = static function (array $jours) use ($db): array {
            if ($jours === []) {
                return [];
            }
            $stmt = $db->prepare('SELECT DISTINCT track_id, release_id, artist_id FROM daily_rollups WHERE day IN (' . implode(',', array_fill(0, count($jours), '?')) . ')');
            $stmt->execute($jours);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        };

        // Identifiants presents AVANT le recalcul : un titre dont toutes les
        // ecoutes du jour ont ete revoquees disparait des agregats, et son
        // compteur doit pourtant redescendre.
        $jours = self::joursARecalculer();
        $lignes = $identifiants($jours);
        foreach ($jours as $jour) {
            self::construireJour($jour);
        }
        // Jours reconstruits a l'instant, et ceux reconstruits a la main depuis
        // le dernier comptage.
        $aCompter = array_values(array_unique(array_merge($jours,
            $db->query('SELECT day FROM rollup_runs WHERE counted_at IS NULL OR counted_at < built_at')->fetchAll(PDO::FETCH_COLUMN))));
        if ($aCompter === []) {
            return ['jours' => count($jours), 'titres' => 0];
        }
        $lignes = array_merge($lignes, $identifiants($aCompter));
        $ids = static fn (string $colonne): array => array_values(array_filter(array_unique(array_map('intval', array_column($lignes, $colonne)))));
        Compteurs::recalculer($ids('track_id'), $ids('release_id'), $ids('artist_id'));
        $stmt = $db->prepare('UPDATE rollup_runs SET counted_at = NOW() WHERE day IN (' . implode(',', array_fill(0, count($aCompter), '?')) . ')');
        $stmt->execute($aCompter);
        return ['jours' => count($jours), 'titres' => count($ids('track_id'))];
    }

    private static function base(): PDO
    {
        return TchadokDatabase::getInstance()->getConnection();
    }
}

final class Compteurs
{
    /**
     * Recalcule les compteurs publics. Sans argument : tout le catalogue
     * (reconstruction integrale). Avec des listes : seulement ces titres,
     * sorties et artistes (incremental). Meme calcul dans les deux cas.
     *
     * @param int[]|null $titres
     * @param int[]|null $sorties
     * @param int[]|null $artistes
     */
    public static function recalculer(?array $titres = null, ?array $sorties = null, ?array $artistes = null): void
    {
        $db = TchadokDatabase::getInstance()->getConnection();
        $filtre = static fn (?array $ids, string $colonne): string => $ids === null ? '1 = 1'
            : ($ids === [] ? '1 = 0' : $colonne . ' IN (' . implode(',', array_map('intval', $ids)) . ')');

        $db->exec(
            "UPDATE tracks t
               LEFT JOIN (SELECT track_id, SUM(streams) AS e, SUM(sales) AS v FROM daily_rollups WHERE track_id > 0 GROUP BY track_id) d ON d.track_id = t.id
               LEFT JOIN (SELECT track_id, COUNT(*) AS n FROM media_access_log WHERE media_type = 'download' GROUP BY track_id) m ON m.track_id = t.id
                SET t.total_streams = COALESCE(d.e, 0), t.total_sales = COALESCE(d.v, 0), t.total_downloads = COALESCE(m.n, 0)
              WHERE " . $filtre($titres, 't.id')
        );
        // Ventes d'une sortie : achats de la sortie entiere (lignes sans titre).
        $db->exec(
            "UPDATE releases r
               LEFT JOIN (SELECT release_id, SUM(streams) AS e, SUM(CASE WHEN track_id = 0 THEN sales ELSE 0 END) AS v
                            FROM daily_rollups WHERE release_id > 0 GROUP BY release_id) d ON d.release_id = r.id
               LEFT JOIN (SELECT COALESCE(release_id, album_id) AS rid, COUNT(*) AS n, COALESCE(SUM(duration), 0) AS s
                            FROM tracks WHERE deleted_at IS NULL AND COALESCE(release_id, album_id) IS NOT NULL
                           GROUP BY COALESCE(release_id, album_id)) t ON t.rid = r.id
                SET r.total_streams = COALESCE(d.e, 0), r.total_sales = COALESCE(d.v, 0),
                    r.total_tracks = COALESCE(t.n, 0), r.total_duration = COALESCE(t.s, 0)
              WHERE " . $filtre($sorties, 'r.id')
        );
        $db->exec(
            "UPDATE artists a
               LEFT JOIN (SELECT artist_id, SUM(streams) AS e, SUM(sales) AS v, SUM(artist_revenue) AS g
                            FROM daily_rollups WHERE artist_id > 0 GROUP BY artist_id) d ON d.artist_id = a.id
                SET a.total_streams = COALESCE(d.e, 0), a.total_sales = COALESCE(d.v, 0), a.total_earnings = COALESCE(d.g, 0)
              WHERE " . $filtre($artistes, 'a.id')
        );
    }

    /** Compteurs d'une sortie, a l'ajout ou au retrait d'un titre. */
    public static function sortie(?int $releaseId): void
    {
        if ($releaseId !== null && $releaseId > 0) {
            self::recalculer([], [$releaseId], []);
        }
    }

    /**
     * Controle de coherence : compteurs publics contre agregats. Renvoie les
     * ecarts (vide si tout concorde).
     *
     * @return array<int,string>
     */
    public static function controler(): array
    {
        $db = TchadokDatabase::getInstance()->getConnection();
        $ecarts = [];
        $requetes = [
            'titres (ecoutes)'   => "SELECT COUNT(*) FROM tracks t LEFT JOIN (SELECT track_id, SUM(streams) AS e FROM daily_rollups WHERE track_id > 0 GROUP BY track_id) d ON d.track_id = t.id WHERE t.total_streams <> COALESCE(d.e, 0)",
            'titres (ventes)'    => "SELECT COUNT(*) FROM tracks t LEFT JOIN (SELECT track_id, SUM(sales) AS v FROM daily_rollups WHERE track_id > 0 GROUP BY track_id) d ON d.track_id = t.id WHERE t.total_sales <> COALESCE(d.v, 0)",
            'artistes (ecoutes)' => "SELECT COUNT(*) FROM artists a LEFT JOIN (SELECT artist_id, SUM(streams) AS e FROM daily_rollups GROUP BY artist_id) d ON d.artist_id = a.id WHERE a.total_streams <> COALESCE(d.e, 0)",
            'artistes (revenus)' => "SELECT COUNT(*) FROM artists a LEFT JOIN (SELECT artist_id, SUM(artist_revenue) AS g FROM daily_rollups GROUP BY artist_id) d ON d.artist_id = a.id WHERE ABS(a.total_earnings - COALESCE(d.g, 0)) > 0.01",
        ];
        foreach ($requetes as $libelle => $sql) {
            $n = (int) $db->query($sql)->fetchColumn();
            if ($n > 0) {
                $ecarts[] = "{$n} {$libelle} en ecart avec les agregats";
            }
        }
        return $ecarts;
    }
}
