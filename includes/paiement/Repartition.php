<?php
/**
 * Part des artistes dans les abonnements Premium (decision du 28/09/2026).
 *
 * LE TAUX
 *   Un pourcentage du revenu d'abonnement, regle depuis la console
 *   (admin/remuneration.php). Propose au depart : 70 %. Chaque changement est
 *   une nouvelle ligne datee (premier jour d'un mois), motivee, attribuee ;
 *   l'ancien taux reste lisible. Une BAISSE ne peut pas prendre effet le mois
 *   en cours (preavis) ; aucun changement ne touche un mois deja reparti.
 *
 * LE REVENU D'UN MOIS
 *   Chaque abonnement PAYE (commande encaissee, ni remboursee ni contestee) est
 *   etale sur sa periode : un annuel de 20 000 FCFA apporte environ 1 667 FCFA
 *   par mois, au prorata des jours.
 *
 * LA REPARTITION, CENTREE SUR L'ABONNE
 *   La part d'un abonne (son revenu du mois x taux) va aux artistes qu'IL a
 *   ecoutes, au prorata de SES ecoutes. Consequence voulue : une ecoute
 *   fabriquee ne detourne que l'abonnement de celui qui la fabrique.
 *   Ecoute retenue : CERTIFIEE (STAT-03, `streams_certified`), d'au moins
 *   REPARTITION_ECOUTE_MIN_SECONDES (30 s), par un
 *   abonne alors couvert, d'un titre qui n'est pas le sien.
 *   La part d'un abonne qui n'a rien ecoute reste a la plateforme ; elle est
 *   affichee (« non attribue »).
 *
 * LA CLOTURE
 *   Par la finance, mois echu seulement, une fois. Chaque artiste recoit un
 *   ajustement de solde (artist_adjustments) : l'argent suit ensuite le
 *   circuit des versements (LOT 8). Tout est fige en base.
 */

declare(strict_types=1);

final class RepartitionAbonnements
{
    public const TAUX_PROPOSE = 70.0;

    // -----------------------------------------------------------------
    // Taux
    // -----------------------------------------------------------------

    /** Taux artistes (en %) en vigueur pour un mois « AAAA-MM » (par defaut : ce mois-ci). */
    public static function taux(?string $mois = null): float
    {
        $debut = ($mois ?? date('Y-m')) . '-01';
        try {
            $stmt = self::base()->prepare(
                "SELECT artist_rate FROM revenue_share_settings WHERE scope = 'subscription' AND effective_from <= ?
                  ORDER BY effective_from DESC, id DESC LIMIT 1"
            );
            $stmt->execute([$debut]);
            $taux = $stmt->fetchColumn();
            return $taux === false ? 0.0 : (float) $taux;
        } catch (Throwable $e) {
            error_log('[Tchadok][repartition] taux illisible : ' . $e->getMessage());
            return 0.0;
        }
    }

    /** @return array<int,array<string,mixed>> historique, plus recent d'abord */
    public static function historique(): array
    {
        return self::base()->query(
            "SELECT r.*, u.username FROM revenue_share_settings r LEFT JOIN users u ON u.id = r.created_by
              WHERE r.scope = 'subscription' ORDER BY r.effective_from DESC, r.id DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Premier mois encore modifiable : ni passe, ni deja reparti. */
    public static function premierMoisModifiable(): string
    {
        $dernier = self::base()->query('SELECT MAX(period) FROM subscription_distributions')->fetchColumn();
        $mois = date('Y-m');
        if ($dernier && $dernier >= $mois) {
            $mois = date('Y-m', strtotime($dernier . '-01 +1 month'));
        }
        return $mois;
    }

    /**
     * Programme un nouveau taux a partir d'un mois « AAAA-MM ».
     *
     * @return array{succes:bool, message:string}
     */
    public static function definirTaux(float $taux, string $mois, string $motif, ?int $auteur): array
    {
        $motif = trim($motif);
        $refus = static fn (string $m): array => ['succes' => false, 'message' => $m];
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mois)) {
            return $refus('Mois d\'effet invalide.');
        }
        if ($taux < 0 || $taux > 100 || round($taux, 2) !== $taux) {
            return $refus('Le pourcentage doit etre compris entre 0 et 100 (deux decimales au plus).');
        }
        if (mb_strlen($motif) < 5) {
            return $refus('Motif obligatoire (5 caracteres au moins) : il sera communique aux artistes.');
        }
        $premier = self::premierMoisModifiable();
        if ($mois < $premier) {
            return $refus('Un taux ne peut pas changer un mois passe ou deja reparti : premier mois possible ' . $premier . '.');
        }
        $actuel = self::taux($mois);
        if ($taux < $actuel && $mois <= date('Y-m')) {
            return $refus('Une baisse de la part des artistes exige un preavis : elle prend effet au plus tot le mois prochain.');
        }

        self::base()->prepare(
            "INSERT INTO revenue_share_settings (scope, artist_rate, effective_from, reason, created_by) VALUES ('subscription', ?, ?, ?, ?)"
        )->execute([$taux, $mois . '-01', mb_substr($motif, 0, 300), $auteur]);
        JournalAudit::enregistrer('remuneration.taux_modifie', [
            'cible_type' => 'part_artistes_abonnements', 'cible_id' => $mois,
            'avant' => ['taux' => $actuel], 'apres' => ['taux' => $taux, 'effet' => $mois . '-01'],
            'raison' => $motif, 'acteur' => $auteur,
        ]);
        self::prevenirArtistes($actuel, $taux, $mois, $motif);

        return ['succes' => true, 'message' => sprintf(
            'Part des artistes fixee a %s %% a partir de %s. Les artistes sont prevenus.', self::pourcent($taux), self::libelleMois($mois)
        )];
    }

    // -----------------------------------------------------------------
    // Calcul
    // -----------------------------------------------------------------

    /**
     * Calcul d'un mois « AAAA-MM », sans rien ecrire.
     *
     * @return array{mois:string, taux:float, revenu:float, part:float, reparti:float, non_attribue:float,
     *               abonnes:int, auditeurs:int, ecoutes:int, lignes:array<int,array{artist_id:int, stage_name:string, ecoutes:int, montant:float}>}
     */
    public static function calculer(string $mois): array
    {
        $db = self::base();
        $debut = $mois . '-01 00:00:00';
        $fin = date('Y-m-d H:i:s', strtotime($debut . ' +1 month'));
        $taux = self::taux($mois);

        // Revenu du mois par abonne, abonnements payes seulement.
        $stmt = $db->prepare(
            "SELECT s.user_id, s.amount, s.start_date, s.end_date
               FROM subscriptions s JOIN orders o ON o.id = s.order_id AND o.status = 'paid'
              WHERE s.status IN ('active', 'cancelled', 'expired') AND s.start_date < ? AND s.end_date > ?"
        );
        $stmt->execute([$fin, $debut]);
        $revenus = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $s) {
            $a = strtotime((string) $s['start_date']);
            $b = strtotime((string) $s['end_date']);
            if ($b <= $a) {
                continue;
            }
            $recouvrement = min($b, strtotime($fin)) - max($a, strtotime($debut));
            $revenus[(int) $s['user_id']] = ($revenus[(int) $s['user_id']] ?? 0.0) + (float) $s['amount'] * $recouvrement / ($b - $a);
        }

        // Ecoutes retenues (certifiees seulement), par abonne et par artiste.
        $stmt = $db->prepare(
            "SELECT st.user_id, st.artist_id, COUNT(*) AS n
               FROM streams_certified st JOIN artists a ON a.id = st.artist_id
               LEFT JOIN stream_revocations rv ON rv.stream_id = st.stream_id
              WHERE st.listened_at >= ? AND st.listened_at < ? AND st.user_id IS NOT NULL AND rv.stream_id IS NULL
                AND st.duration_played >= ? AND a.user_id <> st.user_id
                AND EXISTS (SELECT 1 FROM subscriptions s JOIN orders o ON o.id = s.order_id AND o.status = 'paid'
                             WHERE s.user_id = st.user_id AND s.status IN ('active', 'cancelled', 'expired')
                               AND st.listened_at >= s.start_date AND st.listened_at < s.end_date)
              GROUP BY st.user_id, st.artist_id"
        );
        $stmt->execute([$debut, $fin, self::ecouteMinimale()]);
        $ecoutes = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $l) {
            $ecoutes[(int) $l['user_id']][(int) $l['artist_id']] = (int) $l['n'];
        }

        $partBrute = 0.0;
        $parArtiste = [];
        $ecoutesArtiste = [];
        foreach ($revenus as $userId => $revenu) {
            $part = $revenu * $taux / 100;
            $partBrute += $part;
            $total = array_sum($ecoutes[$userId] ?? []);
            foreach ($ecoutes[$userId] ?? [] as $artistId => $n) {
                $parArtiste[$artistId] = ($parArtiste[$artistId] ?? 0.0) + $part * $n / $total;
                $ecoutesArtiste[$artistId] = ($ecoutesArtiste[$artistId] ?? 0) + $n;
            }
        }

        // Arrondi au franc sans perdre ni creer un franc : partie entiere pour
        // chacun, puis les francs restants aux plus fortes parties decimales.
        $part = round($partBrute);
        $reparti = min($part, round(array_sum($parArtiste)));
        $montants = array_map('floor', $parArtiste);
        $reste = (int) ($reparti - array_sum($montants));
        $decimales = [];
        foreach ($parArtiste as $artistId => $v) {
            $decimales[$artistId] = $v - floor($v);
        }
        arsort($decimales);
        foreach (array_keys($decimales) as $artistId) {
            if ($reste <= 0) {
                break;
            }
            $montants[$artistId]++;
            $reste--;
        }

        $noms = [];
        if ($montants !== []) {
            $ids = implode(',', array_map('intval', array_keys($montants)));
            $noms = $db->query("SELECT id, stage_name FROM artists WHERE id IN ({$ids})")->fetchAll(PDO::FETCH_KEY_PAIR);
        }
        $lignes = [];
        foreach ($montants as $artistId => $montant) {
            $lignes[] = ['artist_id' => $artistId, 'stage_name' => (string) ($noms[$artistId] ?? '#' . $artistId), 'ecoutes' => $ecoutesArtiste[$artistId], 'montant' => (float) $montant];
        }
        usort($lignes, static fn ($x, $y) => $y['montant'] <=> $x['montant'] ?: $x['artist_id'] <=> $y['artist_id']);

        return [
            'mois'         => $mois,
            'taux'         => $taux,
            'revenu'       => round(array_sum($revenus), 2),
            'part'         => $part,
            'reparti'      => (float) $reparti,
            'non_attribue' => (float) ($part - $reparti),
            'abonnes'      => count($revenus),
            'auditeurs'    => count(array_intersect_key($ecoutes, $revenus)),
            'ecoutes'      => (int) array_sum($ecoutesArtiste),
            'lignes'       => $lignes,
        ];
    }

    /**
     * Cloture d'un mois echu : fige la repartition et credite chaque artiste.
     *
     * @return array{succes:bool, message:string}
     */
    public static function cloturer(string $mois, int $auteur): array
    {
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mois)) {
            return ['succes' => false, 'message' => 'Mois invalide.'];
        }
        if ($mois >= date('Y-m')) {
            return ['succes' => false, 'message' => 'Seul un mois termine se repartit : les ecoutes du mois en cours ne sont pas encore connues.'];
        }
        $db = self::base();
        $db->beginTransaction();
        try {
            // Verrou : deux clotures simultanees du meme mois ne passent pas
            // (et l'index unique `periode` l'interdit de toute facon).
            $stmt = $db->prepare('SELECT 1 FROM subscription_distributions WHERE period = ? FOR UPDATE');
            $stmt->execute([$mois]);
            if ($stmt->fetchColumn()) {
                $db->rollBack();
                return ['succes' => false, 'message' => 'Ce mois est deja reparti.'];
            }
            $c = self::calculer($mois);
            $db->prepare(
                'INSERT INTO subscription_distributions (period, artist_rate, revenue, pool, distributed, unallocated, subscribers, listeners, streams, closed_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([$mois, $c['taux'], $c['revenu'], $c['part'], $c['reparti'], $c['non_attribue'], $c['abonnes'], $c['auditeurs'], $c['ecoutes'], $auteur]);
            $distributionId = (int) $db->lastInsertId();

            $ligne = $db->prepare('INSERT INTO subscription_distribution_lines (distribution_id, artist_id, streams, amount) VALUES (?, ?, ?, ?)');
            $ajustement = $db->prepare('INSERT INTO artist_adjustments (artist_id, amount, reason, created_by) VALUES (?, ?, ?, ?)');
            $lier = $db->prepare('UPDATE subscription_distribution_lines SET adjustment_id = ? WHERE id = ?');
            foreach ($c['lignes'] as $l) {
                $ligne->execute([$distributionId, $l['artist_id'], $l['ecoutes'], $l['montant']]);
                $ligneId = (int) $db->lastInsertId();
                if ($l['montant'] > 0) {
                    $ajustement->execute([
                        $l['artist_id'], $l['montant'],
                        sprintf('Abonnements Premium %s : %d ecoute(s), part artistes %s %%', self::libelleMois($mois), $l['ecoutes'], self::pourcent($c['taux'])),
                        $auteur,
                    ]);
                    $lier->execute([(int) $db->lastInsertId(), $ligneId]);
                }
            }
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('[Tchadok][repartition] cloture impossible : ' . $e->getMessage());
            return ['succes' => false, 'message' => 'Cloture impossible pour le moment.'];
        }

        JournalAudit::enregistrer('remuneration.repartition_close', [
            'cible_type' => 'repartition_abonnements', 'cible_id' => $mois,
            'apres' => ['taux' => $c['taux'], 'part' => $c['part'], 'reparti' => $c['reparti'], 'artistes' => count($c['lignes'])],
            'acteur' => $auteur,
        ]);
        return ['succes' => true, 'message' => sprintf(
            '%s reparti : %s FCFA verses au solde de %d artiste(s).', self::libelleMois($mois), number_format($c['reparti'], 0, ',', ' '), count($c['lignes'])
        )];
    }

    /**
     * Repartition cloturee d'un mois, telle que figee, au format de calculer() ;
     * null si le mois n'est pas cloture.
     */
    public static function cloturee(string $mois): ?array
    {
        $db = self::base();
        $stmt = $db->prepare('SELECT * FROM subscription_distributions WHERE period = ?');
        $stmt->execute([$mois]);
        $d = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$d) {
            return null;
        }
        $stmt = $db->prepare(
            'SELECT l.artist_id, a.stage_name, l.streams AS ecoutes, l.amount AS montant FROM subscription_distribution_lines l
               JOIN artists a ON a.id = l.artist_id WHERE l.distribution_id = ? ORDER BY l.amount DESC, l.artist_id'
        );
        $stmt->execute([$d['id']]);
        return [
            'mois' => $mois, 'taux' => (float) $d['artist_rate'], 'revenu' => (float) $d['revenue'], 'part' => (float) $d['pool'],
            'reparti' => (float) $d['distributed'], 'non_attribue' => (float) $d['unallocated'], 'abonnes' => (int) $d['subscribers'],
            'auditeurs' => (int) $d['listeners'], 'ecoutes' => (int) $d['streams'], 'lignes' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public static function repartitions(int $limite = 24): array
    {
        return self::base()->query(
            'SELECT d.*, u.username FROM subscription_distributions d LEFT JOIN users u ON u.id = d.closed_by ORDER BY d.period DESC LIMIT ' . max(1, $limite)
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Mois echus non encore repartis ayant des abonnements payes (les 12 derniers). */
    public static function moisEnAttente(): array
    {
        $attente = [];
        $clos = array_flip(self::base()->query('SELECT period FROM subscription_distributions')->fetchAll(PDO::FETCH_COLUMN));
        for ($i = 12; $i >= 1; $i--) {
            $mois = date('Y-m', strtotime(date('Y-m-01') . " -{$i} month"));
            if (!isset($clos[$mois]) && $mois >= '2026-09') {
                $attente[] = $mois;
            }
        }
        return $attente;
    }

    public static function ecouteMinimale(): int
    {
        return max(0, (int) EnvLoader::get('REPARTITION_ECOUTE_MIN_SECONDES', '30'));
    }

    public static function libelleMois(string $mois): string
    {
        $noms = ['janvier', 'fevrier', 'mars', 'avril', 'mai', 'juin', 'juillet', 'aout', 'septembre', 'octobre', 'novembre', 'decembre'];
        [$a, $m] = array_map('intval', explode('-', $mois));
        return ($noms[$m - 1] ?? '?') . ' ' . $a;
    }

    public static function pourcent(float $taux): string
    {
        return rtrim(rtrim(number_format($taux, 2, ',', ' '), '0'), ',');
    }

    private static function prevenirArtistes(float $avant, float $apres, string $mois, string $motif): void
    {
        if (abs($avant - $apres) < 0.001) {
            return;
        }
        $texte = sprintf(
            'A partir de %s, la part des abonnements Premium reversee aux artistes passe de %s %% a %s %%. Motif : %s',
            self::libelleMois($mois), self::pourcent($avant), self::pourcent($apres), $motif
        );
        $destinataires = self::base()->query(
            'SELECT u.email, u.first_name FROM artists a JOIN users u ON u.id = a.user_id
              WHERE a.deleted_at IS NULL AND a.is_active = 1 AND u.is_active = 1 AND u.email IS NOT NULL'
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($destinataires as $d) {
            sendEmail((string) $d['email'], 'Tchadok - part des abonnements', '<p>Bonjour ' . htmlspecialchars((string) $d['first_name'], ENT_QUOTES, 'UTF-8')
                . ',</p><p>' . htmlspecialchars($texte, ENT_QUOTES, 'UTF-8') . '</p>');
        }
    }

    private static function base(): PDO
    {
        return TchadokDatabase::getInstance()->getConnection();
    }
}
