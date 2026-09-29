<?php
/**
 * Certification des ecoutes (STAT-03).
 *
 * `streams` est le journal BRUT (STAT-02). Une tache planifiee donne a chaque
 * ecoute un verdict, une fois :
 *
 *   exclue       ecoute d'un artiste sur ses propres titres (STAT-01) ;
 *   quarantaine  un signal de fraude ; ni supprimee ni comptee, elle attend
 *                une decision humaine motivee (Certification::decider) ;
 *   certifiee    copiee dans `streams_certified`, le SEUL fait qui alimente
 *                classements et remuneration.
 *
 * Seules les ecoutes de plus de DELAI_OBSERVATION (2 h) sont jugees : une
 * rafale doit etre complete pour etre vue.
 *
 * Signaux (seuils reglables, variables CERTIF_*) :
 *   rafale_ip             plus de 40 ecoutes par heure depuis une adresse IP ;
 *   ratio_auditeurs       titre a plus de 50 ecoutes dans la journee et plus
 *                         de 8 ecoutes par auditeur unique ;
 *   concentration_reseau  meme titre, meme jour, plus de 80 % des ecoutes
 *                         depuis un meme reseau (/24 en IPv4, /48 en IPv6) ;
 *   concentration_agent   idem, depuis un meme navigateur (agent) ;
 *   comptes_en_rafale     au moins 10 comptes crees dans la meme heure, qui
 *                         ecoutent le meme titre ;
 *   volume_nocturne       ALERTE seulement (pas de quarantaine) : plus de 100
 *                         ecoutes d'un titre entre 0 h et 5 h (N'Djamena).
 */

declare(strict_types=1);

final class Certification
{
    public const SIGNAUX = [
        'rafale_ip'            => 'Rafale depuis une meme adresse IP',
        'ratio_auditeurs'      => 'Trop d\'ecoutes par auditeur unique',
        'concentration_reseau' => 'Ecoutes concentrees sur un meme reseau',
        'concentration_agent'  => 'Ecoutes concentrees sur un meme navigateur',
        'comptes_en_rafale'    => 'Comptes crees en rafale',
        'volume_nocturne'      => 'Volume nocturne inhabituel (alerte)',
        'sous_surveillance'    => 'Artiste ou compte sous surveillance',
        'propre_titre'         => 'Ecoute de l\'artiste sur son propre titre',
        'trop_courte'          => 'Ecoute sous le seuil (anterieure a STAT-02)',
    ];

    /** Signaux qui ne mettent pas en quarantaine (simple alerte). */
    private const ALERTES = ['volume_nocturne'];

    /**
     * Juge les ecoutes en attente de verdict.
     *
     * @return array{jugees:int, certifiees:int, exclues:int, quarantaine:int, alertes:int}
     */
    public static function traiter(int $limite = 20000): array
    {
        $db = self::base();
        $bilan = ['jugees' => 0, 'certifiees' => 0, 'exclues' => 0, 'quarantaine' => 0, 'alertes' => 0];
        $stmt = $db->prepare(
            'SELECT s.id, s.user_id, s.listener_key, s.track_id, s.artist_id, s.ip_address, s.user_agent, s.source,
                    s.duration_played, s.created_at, a.user_id AS artiste_user, t.duration AS duree_titre
               FROM streams s
               LEFT JOIN stream_verdicts v ON v.stream_id = s.id
               LEFT JOIN artists a ON a.id = s.artist_id
               LEFT JOIN tracks t ON t.id = s.track_id
              WHERE v.stream_id IS NULL AND s.created_at < NOW() - INTERVAL ? SECOND
              ORDER BY s.id LIMIT ' . max(1, $limite)
        );
        $stmt->execute([self::reglage('CERTIF_DELAI_OBSERVATION', 7200)]);
        $ecoutes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($ecoutes === []) {
            return $bilan;
        }

        $debut = min(array_column($ecoutes, 'created_at'));
        $fin = max(array_column($ecoutes, 'created_at'));
        $contexte = self::contexte($db, $debut, $fin);

        $verdict = $db->prepare('INSERT IGNORE INTO stream_verdicts (stream_id, verdict, signals) VALUES (?, ?, ?)');
        $certifier = $db->prepare(
            'INSERT IGNORE INTO streams_certified (stream_id, track_id, artist_id, user_id, listener_key, source, duration_played, listened_at, via)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'automatique\')'
        );
        $db->beginTransaction();
        try {
            foreach ($ecoutes as $e) {
                $signaux = self::signaux($e, $contexte);
                $bloquants = array_values(array_diff($signaux, self::ALERTES));
                if ($e['user_id'] !== null && $e['artiste_user'] !== null && (int) $e['user_id'] === (int) $e['artiste_user']) {
                    $etat = 'exclue';
                    $signaux = ['propre_titre'];
                } elseif ((int) $e['duration_played'] < min(30, max(1, (int) ($e['duree_titre'] ?? 30)))) {
                    // Ecoutes du journal d'avant STAT-02 (duree 0, jamais
                    // verifiee) : elles ne peuvent pas etre certifiees.
                    $etat = 'exclue';
                    $signaux = ['trop_courte'];
                } elseif ($bloquants !== []) {
                    // En revue de toute facon : les simples alertes ne
                    // scindent pas l'anomalie en plusieurs groupes.
                    $etat = 'quarantaine';
                    $signaux = $bloquants;
                } else {
                    $etat = 'certifiee';
                }
                $verdict->execute([$e['id'], $etat, $signaux === [] ? null : implode(',', $signaux)]);
                if ($verdict->rowCount() !== 1) {
                    continue; // deja juge par une autre execution
                }
                if ($etat === 'certifiee') {
                    self::copier($certifier, $e);
                }
                $bilan['jugees']++;
                $bilan[$etat === 'certifiee' ? 'certifiees' : ($etat === 'exclue' ? 'exclues' : 'quarantaine')]++;
                if (in_array('volume_nocturne', $signaux, true)) {
                    $bilan['alertes']++;
                }
            }
            $db->commit();
        } catch (Throwable $ex) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $ex;
        }
        if ($bilan['quarantaine'] > 0) {
            error_log(sprintf('[Tchadok][certification][ALERTE] %d ecoute(s) mises en quarantaine sur %d jugee(s)', $bilan['quarantaine'], $bilan['jugees']));
        }
        return $bilan;
    }

    /**
     * Decision humaine sur une anomalie : toutes les ecoutes en quarantaine
     * qui partagent ces signaux, ce titre et ce jour.
     *
     * @return array{succes:bool, message:string, nombre:int}
     */
    public static function decider(string $signaux, int $trackId, string $jour, string $decision, string $motif, int $auteur): array
    {
        $motif = trim($motif);
        if (!in_array($decision, ['valider', 'rejeter'], true) || mb_strlen($motif) < 5) {
            return ['succes' => false, 'message' => 'Decision et motif (5 caracteres au moins) obligatoires.', 'nombre' => 0];
        }
        $db = self::base();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare(
                "SELECT s.* FROM stream_verdicts v JOIN streams s ON s.id = v.stream_id
                  WHERE v.verdict = 'quarantaine' AND v.signals = ? AND s.track_id = ? AND DATE(s.created_at) = ? FOR UPDATE"
            );
            $stmt->execute([$signaux, $trackId, $jour]);
            $lignes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $maj = $db->prepare('UPDATE stream_verdicts SET verdict = ?, decided_by = ?, decided_at = NOW(), decision_reason = ? WHERE stream_id = ?');
            $certifier = $db->prepare(
                'INSERT IGNORE INTO streams_certified (stream_id, track_id, artist_id, user_id, listener_key, source, duration_played, listened_at, via)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'revue\')'
            );
            foreach ($lignes as $e) {
                $maj->execute([$decision === 'valider' ? 'certifiee' : 'rejetee', $auteur, mb_substr($motif, 0, 500), $e['id']]);
                if ($decision === 'valider') {
                    self::copier($certifier, $e);
                }
            }
            $db->commit();
        } catch (Throwable $ex) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $ex;
        }
        if ($lignes === []) {
            return ['succes' => false, 'message' => 'Anomalie introuvable ou deja instruite.', 'nombre' => 0];
        }
        JournalAudit::enregistrer($decision === 'valider' ? 'ecoute.quarantaine_levee' : 'ecoute.quarantaine_rejetee', [
            'cible_type' => 'titre', 'cible_id' => $trackId,
            'apres' => ['jour' => $jour, 'signaux' => $signaux, 'ecoutes' => count($lignes)],
            'raison' => $motif, 'acteur' => $auteur,
        ]);
        return ['succes' => true, 'message' => sprintf('%d ecoute(s) %s.', count($lignes), $decision === 'valider' ? 'certifiee(s)' : 'rejetee(s)'), 'nombre' => count($lignes)];
    }

    /**
     * Revoque des ecoutes deja certifiees (fraude decouverte apres coup) :
     * celles d'un titre, un jour donne, eventuellement d'une seule adresse IP.
     * `streams_certified` reste intact ; la revocation est une ligne de
     * journal, motivee. Le recalcul de la nuit fait baisser les compteurs.
     *
     * @return array{succes:bool, message:string, nombre:int}
     */
    public static function revoquer(int $trackId, string $jour, ?string $ip, string $motif, int $auteur): array
    {
        $motif = trim($motif);
        if ($trackId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $jour) || mb_strlen($motif) < 5) {
            return ['succes' => false, 'message' => 'Titre, jour et motif (5 caracteres au moins) obligatoires.', 'nombre' => 0];
        }
        $ip = $ip !== null && trim($ip) !== '' ? trim($ip) : null;
        $db = self::base();
        $stmt = $db->prepare(
            'INSERT IGNORE INTO stream_revocations (stream_id, reason, revoked_by)
             SELECT sc.stream_id, ?, ? FROM streams_certified sc JOIN streams s ON s.id = sc.stream_id
              WHERE sc.track_id = ? AND sc.listened_at >= ? AND sc.listened_at < ? + INTERVAL 1 DAY' . ($ip !== null ? ' AND s.ip_address = ?' : '')
        );
        $stmt->execute(array_merge([mb_substr($motif, 0, 500), $auteur, $trackId, $jour, $jour], $ip !== null ? [$ip] : []));
        $n = $stmt->rowCount();
        if ($n === 0) {
            return ['succes' => false, 'message' => 'Aucune ecoute certifiee a revoquer pour ces criteres.', 'nombre' => 0];
        }
        JournalAudit::enregistrer('ecoute.revoquee', [
            'cible_type' => 'titre', 'cible_id' => $trackId, 'apres' => ['jour' => $jour, 'ip' => $ip, 'ecoutes' => $n],
            'raison' => $motif, 'acteur' => $auteur,
        ]);
        return ['succes' => true, 'message' => sprintf('%d ecoute(s) certifiee(s) revoquee(s). Les compteurs baisseront au prochain recalcul.', $n), 'nombre' => $n];
    }

    /**
     * Anomalies en attente, regroupees par signaux, titre et jour.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function anomalies(int $limite = 100): array
    {
        return self::base()->query(
            "SELECT v.signals, s.track_id, DATE(s.created_at) AS jour, COUNT(*) AS ecoutes,
                    COUNT(DISTINCT s.ip_address) AS adresses, COUNT(DISTINCT s.listener_key) AS auditeurs,
                    MIN(s.created_at) AS premiere, MAX(s.created_at) AS derniere,
                    t.title, a.stage_name, a.id AS artist_id
               FROM stream_verdicts v JOIN streams s ON s.id = v.stream_id
               JOIN tracks t ON t.id = s.track_id JOIN artists a ON a.id = s.artist_id
              WHERE v.verdict = 'quarantaine'
              GROUP BY v.signals, s.track_id, DATE(s.created_at)
              ORDER BY COUNT(*) DESC LIMIT " . max(1, $limite)
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Gravite d'une anomalie : haute, moyenne, basse. */
    public static function gravite(array $anomalie): string
    {
        $n = (int) $anomalie['ecoutes'];
        $multiples = substr_count((string) $anomalie['signals'], ',') >= 1;
        return $n >= 1000 || ($multiples && $n >= 100) ? 'haute' : ($n >= 100 || $multiples ? 'moyenne' : 'basse');
    }

    /**
     * Indicateurs de sante sur N jours : brut, certifie, exclu, en
     * quarantaine, rejete, part en quarantaine.
     *
     * @return array<string,int|float>
     */
    public static function sante(int $jours = 30): array
    {
        $stmt = self::base()->prepare(
            "SELECT COUNT(*) AS brut,
                    SUM(v.verdict = 'certifiee') AS certifiees, SUM(v.verdict = 'exclue') AS exclues,
                    SUM(v.verdict = 'quarantaine') AS quarantaine, SUM(v.verdict = 'rejetee') AS rejetees,
                    SUM(v.stream_id IS NULL) AS en_attente
               FROM streams s LEFT JOIN stream_verdicts v ON v.stream_id = s.id
              WHERE s.created_at >= NOW() - INTERVAL ? DAY"
        );
        $stmt->execute([$jours]);
        $r = array_map('intval', $stmt->fetch(PDO::FETCH_ASSOC) ?: []);
        $jugees = max(1, $r['brut'] - $r['en_attente']);
        $r['part_quarantaine'] = round(100 * ($r['quarantaine'] + $r['rejetees']) / $jugees, 2);
        return $r;
    }

    /**
     * Met un artiste ou un compte sous surveillance : ses ecoutes passent
     * toutes en revue humaine jusqu'a la levee.
     *
     * @return array{succes:bool, message:string}
     */
    public static function surveiller(string $type, int $cible, string $motif, int $auteur): array
    {
        $motif = trim($motif);
        if (!in_array($type, ['artiste', 'compte'], true) || $cible <= 0 || mb_strlen($motif) < 5) {
            return ['succes' => false, 'message' => 'Cible et motif (5 caracteres au moins) obligatoires.'];
        }
        $db = self::base();
        $stmt = $db->prepare('SELECT 1 FROM stream_watchlist WHERE target_type = ? AND target_id = ? AND lifted_at IS NULL');
        $stmt->execute([$type, $cible]);
        if ($stmt->fetchColumn()) {
            return ['succes' => false, 'message' => 'Deja sous surveillance.'];
        }
        $db->prepare('INSERT INTO stream_watchlist (target_type, target_id, reason, created_by) VALUES (?, ?, ?, ?)')
           ->execute([$type, $cible, mb_substr($motif, 0, 300), $auteur]);
        JournalAudit::enregistrer('ecoute.surveillance', ['cible_type' => $type, 'cible_id' => $cible, 'apres' => ['surveillance' => true], 'raison' => $motif, 'acteur' => $auteur]);
        return ['succes' => true, 'message' => 'Surveillance activee : ses prochaines ecoutes passeront en revue.'];
    }

    /** @return array{succes:bool, message:string} */
    public static function leverSurveillance(int $id, string $motif, int $auteur): array
    {
        $motif = trim($motif);
        if (mb_strlen($motif) < 5) {
            return ['succes' => false, 'message' => 'Motif obligatoire (5 caracteres au moins).'];
        }
        $db = self::base();
        $stmt = $db->prepare('UPDATE stream_watchlist SET lifted_at = NOW(), lifted_by = ? WHERE id = ? AND lifted_at IS NULL');
        $stmt->execute([$auteur, $id]);
        if ($stmt->rowCount() !== 1) {
            return ['succes' => false, 'message' => 'Surveillance introuvable ou deja levee.'];
        }
        JournalAudit::enregistrer('ecoute.surveillance', ['cible_type' => 'surveillance', 'cible_id' => $id, 'apres' => ['surveillance' => false], 'raison' => $motif, 'acteur' => $auteur]);
        return ['succes' => true, 'message' => 'Surveillance levee.'];
    }

    /** @return array<int,array<string,mixed>> surveillances en cours */
    public static function surveillances(): array
    {
        return self::base()->query(
            "SELECT w.*, u.username AS auteur,
                    CASE w.target_type WHEN 'artiste' THEN (SELECT stage_name FROM artists WHERE id = w.target_id)
                                       ELSE (SELECT username FROM users WHERE id = w.target_id) END AS nom
               FROM stream_watchlist w LEFT JOIN users u ON u.id = w.created_by
              WHERE w.lifted_at IS NULL ORDER BY w.created_at DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Titres les plus concernes par la quarantaine sur N jours.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function titresConcernes(int $jours = 30, int $limite = 10): array
    {
        $stmt = self::base()->prepare(
            "SELECT t.id, t.title, a.stage_name, COUNT(*) AS ecoutes,
                    SUM(v.verdict IN ('quarantaine', 'rejetee')) AS suspectes
               FROM streams s JOIN stream_verdicts v ON v.stream_id = s.id
               JOIN tracks t ON t.id = s.track_id JOIN artists a ON a.id = s.artist_id
              WHERE s.created_at >= NOW() - INTERVAL ? DAY
              GROUP BY t.id HAVING suspectes > 0 ORDER BY suspectes DESC LIMIT " . max(1, $limite)
        );
        $stmt->execute([$jours]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Evolution quotidienne : jugees et suspectes, par jour.
     *
     * @return array<int,array{jour:string, jugees:int, suspectes:int}>
     */
    public static function evolution(int $jours = 14): array
    {
        $stmt = self::base()->prepare(
            "SELECT DATE(s.created_at) AS jour, COUNT(*) AS jugees, SUM(v.verdict IN ('quarantaine', 'rejetee')) AS suspectes
               FROM streams s JOIN stream_verdicts v ON v.stream_id = s.id
              WHERE s.created_at >= CURDATE() - INTERVAL ? DAY GROUP BY DATE(s.created_at) ORDER BY jour"
        );
        $stmt->execute([$jours]);
        return array_map(static fn ($l) => ['jour' => $l['jour'], 'jugees' => (int) $l['jugees'], 'suspectes' => (int) $l['suspectes']], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** Seuil d'alerte : part des ecoutes en quarantaine ou rejetees (en %). */
    public static function seuilAlerte(): float
    {
        return (float) EnvLoader::get('CERTIF_ALERTE_POURCENT', '5');
    }

    // -----------------------------------------------------------------

    private static function contexte(PDO $db, string $debut, string $fin): array
    {
        $c = ['ip_heure' => [], 'titre_jour' => [], 'reseau' => [], 'agent' => [], 'rafale_comptes' => [], 'nuit' => []];

        $stmt = $db->prepare(
            "SELECT ip_address, DATE_FORMAT(created_at, '%Y-%m-%d %H') AS h, COUNT(*) AS n FROM streams
              WHERE created_at BETWEEN ? - INTERVAL 1 HOUR AND ? + INTERVAL 1 HOUR GROUP BY ip_address, h"
        );
        $stmt->execute([$debut, $fin]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $l) {
            $c['ip_heure'][$l['ip_address'] . '|' . $l['h']] = (int) $l['n'];
        }

        $stmt = $db->prepare(
            'SELECT track_id, DATE(created_at) AS j, COUNT(*) AS n, COUNT(DISTINCT listener_key) AS auditeurs,
                    SUM(HOUR(created_at) < 5) AS nuit
               FROM streams WHERE created_at BETWEEN DATE(?) AND DATE(?) + INTERVAL 1 DAY GROUP BY track_id, j'
        );
        $stmt->execute([$debut, $fin]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $l) {
            $c['titre_jour'][$l['track_id'] . '|' . $l['j']] = ['n' => (int) $l['n'], 'auditeurs' => max(1, (int) $l['auditeurs']), 'nuit' => (int) $l['nuit']];
        }

        // Reseau (/24, /48) et agent dominants, par titre et par jour.
        $stmt = $db->prepare(
            'SELECT track_id, DATE(created_at) AS j, ip_address, user_agent, COUNT(*) AS n FROM streams
              WHERE created_at BETWEEN DATE(?) AND DATE(?) + INTERVAL 1 DAY GROUP BY track_id, j, ip_address, user_agent'
        );
        $stmt->execute([$debut, $fin]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $l) {
            $cle = $l['track_id'] . '|' . $l['j'];
            $reseau = self::reseau((string) $l['ip_address']);
            $c['reseau'][$cle][$reseau] = ($c['reseau'][$cle][$reseau] ?? 0) + (int) $l['n'];
            $agent = (string) $l['user_agent'];
            $c['agent'][$cle][$agent] = ($c['agent'][$cle][$agent] ?? 0) + (int) $l['n'];
        }

        // Comptes crees en rafale : au moins N comptes dans la meme heure.
        $stmt = $db->prepare(
            "SELECT id FROM users WHERE DATE_FORMAT(created_at, '%Y-%m-%d %H') IN (
                SELECT h FROM (SELECT DATE_FORMAT(created_at, '%Y-%m-%d %H') AS h FROM users
                                WHERE created_at BETWEEN ? - INTERVAL 2 DAY AND ?
                                GROUP BY h HAVING COUNT(*) >= ?) AS heures)"
        );
        $stmt->execute([$debut, $fin, self::reglage('CERTIF_COMPTES_RAFALE', 10)]);
        $c['rafale_comptes'] = array_flip(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)));

        $c['surveillance'] = ['artiste' => [], 'compte' => []];
        foreach ($db->query('SELECT target_type, target_id FROM stream_watchlist WHERE lifted_at IS NULL')->fetchAll(PDO::FETCH_ASSOC) as $l) {
            $c['surveillance'][$l['target_type']][(int) $l['target_id']] = true;
        }
        return $c;
    }

    private static function signaux(array $e, array $c): array
    {
        $signaux = [];
        $heure = substr((string) $e['created_at'], 0, 13);
        if (($c['ip_heure'][$e['ip_address'] . '|' . $heure] ?? 0) > self::reglage('CERTIF_RAFALE_IP_HEURE', 40)) {
            $signaux[] = 'rafale_ip';
        }
        $cle = $e['track_id'] . '|' . substr((string) $e['created_at'], 0, 10);
        $jour = $c['titre_jour'][$cle] ?? ['n' => 0, 'auditeurs' => 1, 'nuit' => 0];
        $volumeMin = self::reglage('CERTIF_TITRE_VOLUME_MIN', 50);
        if ($jour['n'] >= $volumeMin) {
            if ($jour['n'] / $jour['auditeurs'] > self::reglage('CERTIF_RATIO_MAX', 8)) {
                $signaux[] = 'ratio_auditeurs';
            }
            $part = self::reglage('CERTIF_CONCENTRATION_POURCENT', 80) / 100;
            if (max($c['reseau'][$cle] ?? [0]) > $part * $jour['n']) {
                $signaux[] = 'concentration_reseau';
            }
            if (max($c['agent'][$cle] ?? [0]) > $part * $jour['n']) {
                $signaux[] = 'concentration_agent';
            }
        }
        if ($e['user_id'] !== null && isset($c['rafale_comptes'][(int) $e['user_id']])) {
            $signaux[] = 'comptes_en_rafale';
        }
        if (isset($c['surveillance']['artiste'][(int) $e['artist_id']])
            || ($e['user_id'] !== null && isset($c['surveillance']['compte'][(int) $e['user_id']]))) {
            $signaux[] = 'sous_surveillance';
        }
        if ($jour['nuit'] > self::reglage('CERTIF_NUIT_VOLUME', 100) && (int) substr((string) $e['created_at'], 11, 2) < 5) {
            $signaux[] = 'volume_nocturne';
        }
        return $signaux;
    }

    private static function copier(PDOStatement $certifier, array $e): void
    {
        $certifier->execute([
            $e['id'], $e['track_id'], $e['artist_id'], $e['user_id'], $e['listener_key'], $e['source'],
            (int) $e['duration_played'], $e['created_at'],
        ]);
    }

    /** Reseau d'une adresse : /24 en IPv4, /48 en IPv6. */
    private static function reseau(string $ip): string
    {
        $b = @inet_pton($ip);
        if ($b === false) {
            return $ip;
        }
        return strlen($b) === 4 ? inet_ntop(substr($b, 0, 3) . "\0") . '/24' : inet_ntop(substr($b, 0, 6) . str_repeat("\0", 10)) . '/48';
    }

    private static function reglage(string $nom, int $defaut): int
    {
        return max(0, (int) EnvLoader::get($nom, (string) $defaut));
    }

    private static function base(): PDO
    {
        return TchadokDatabase::getInstance()->getConnection();
    }
}
