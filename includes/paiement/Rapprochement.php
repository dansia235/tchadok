<?php
/**
 * Rapprochement quotidien avec les releves des operateurs (PAY-10).
 *
 * Chaque jour, pour chaque passerelle, on compare ce que l'operateur declare
 * avoir encaisse a ce que Tchadok a enregistre. Un ecart non vu est un litige
 * a venir : un client debite sans avoir recu son achat, ou une vente livree
 * sans avoir ete payee, ou un artiste a qui l'on doit de l'argent introuvable.
 *
 * Les ecarts sont conserves jusqu'a leur cloture, qui exige un motif et un
 * auteur (contrainte en base, et journal d'audit). Relancer le rapprochement
 * d'un meme jour ne duplique pas un ecart deja ouvert.
 *
 * Planification : une fois par jour, sur la veille --
 *   php scripts/rapprochement.php
 */

declare(strict_types=1);

final class Rapprochement
{
    public const TYPES = [
        'operateur_sans_commande'    => 'Argent recu sans tentative Tchadok',
        'commande_sans_encaissement' => 'Paiement enregistre, absent du releve de l\'operateur',
        'ecart_montant'              => 'Montant ou devise differents',
        'statut_divergent'           => 'Etats divergents entre l\'operateur et Tchadok',
        'doublon'                    => 'Commande encaissee plusieurs fois',
    ];

    /**
     * Rapproche toutes les passerelles configurees pour une journee.
     *
     * @return array<string,array{statut:string, operateur:int, plateforme:int, ecarts:int, erreur:?string}>
     */
    public static function executerTout(string $date): array
    {
        $bilans = [];
        foreach (array_keys(FabriquePasserelles::disponibles()) as $code) {
            $bilans[$code] = self::executer($code, $date);
        }
        return $bilans;
    }

    /**
     * @return array{statut:string, operateur:int, plateforme:int, ecarts:int, erreur:?string}
     */
    public static function executer(string $codePasserelle, string $date): array
    {
        $db = TchadokDatabase::getInstance()->getConnection();
        if (!$db || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !FabriquePasserelles::configuree($codePasserelle)) {
            return ['statut' => 'erreur', 'operateur' => 0, 'plateforme' => 0, 'ecarts' => 0, 'erreur' => 'parametres invalides'];
        }

        $db->prepare("INSERT INTO reconciliation_runs (gateway, statement_date, status, started_at) VALUES (?, ?, 'erreur', NOW())")
           ->execute([$codePasserelle, $date]);
        $runId = (int) $db->lastInsertId();

        $passerelle = FabriquePasserelles::obtenir($codePasserelle);
        $passerelle->journaliserAvec(static function (array $echange) use ($codePasserelle): void {
            Commandes::enregistrerEvenement($echange + ['gateway' => $codePasserelle, 'ip_address' => '']);
        });
        $releve = $passerelle->releve($date);

        if ($releve === null) {
            $message = 'Releve indisponible chez l\'operateur';
            $db->prepare("UPDATE reconciliation_runs SET error_message = ?, finished_at = NOW() WHERE id = ?")->execute([$message, $runId]);
            error_log(sprintf('[Tchadok][rapprochement][ALERTE] %s : releve du %s indisponible', $codePasserelle, $date));
            return ['statut' => 'erreur', 'operateur' => 0, 'plateforme' => 0, 'ecarts' => 0, 'erreur' => $message];
        }

        // Tentatives Tchadok : celles du jour, plus celles citees par le releve
        // (une tentative peut dater de la veille et avoir abouti ce jour-la).
        $stmt = $db->prepare(
            "SELECT pi.*, o.reference AS order_reference, o.status AS order_status
               FROM payment_intents pi JOIN orders o ON o.id = pi.order_id
              WHERE pi.gateway = ? AND pi.created_at >= ? AND pi.created_at < DATE_ADD(?, INTERVAL 1 DAY)"
        );
        $stmt->execute([$codePasserelle, $date, $date]);
        $parReference = [];
        $parTentative = [];
        $parId = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $t) {
            self::indexer($t, $parReference, $parTentative, $parId);
        }
        $manquantes = array_values(array_diff(array_column($releve, 'reference'), array_keys($parReference)));
        if ($manquantes) {
            $marqueurs = implode(',', array_fill(0, count($manquantes), '?'));
            $stmt = $db->prepare(
                "SELECT pi.*, o.reference AS order_reference, o.status AS order_status
                   FROM payment_intents pi JOIN orders o ON o.id = pi.order_id
                  WHERE pi.gateway = ? AND pi.gateway_ref IN ({$marqueurs})"
            );
            $stmt->execute(array_merge([$codePasserelle], $manquantes));
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $t) {
                self::indexer($t, $parReference, $parTentative, $parId);
            }
        }

        $ecarts = [];
        $vues = [];
        $encaissesParCommande = [];

        foreach ($releve as $ligne) {
            $t = $parReference[$ligne['reference']] ?? ($ligne['tentative'] !== null ? ($parTentative[$ligne['tentative']] ?? null) : null);

            if ($t === null) {
                $ecarts[] = self::ecart('operateur_sans_commande', $ligne['reference'], null, $ligne['montant'], null, $ligne['devise'],
                    'Reference Tchadok annoncee : ' . ($ligne['tentative'] ?? 'aucune'));
                continue;
            }
            $vues[(int) $t['id']] = true;
            $attendu = Devises::enUnitesMineures((float) $t['amount'], (string) $t['currency']);

            if ($ligne['statut'] === 'succeeded') {
                $encaissesParCommande[(int) $t['order_id']][] = $ligne['reference'];

                if ($ligne['montant'] !== $attendu || $ligne['devise'] !== strtoupper((string) $t['currency'])) {
                    $ecarts[] = self::ecart('ecart_montant', $ligne['reference'], $t, $ligne['montant'], $attendu, $ligne['devise'],
                        sprintf('Operateur : %d %s ; Tchadok : %d %s', $ligne['montant'], $ligne['devise'], $attendu, $t['currency']));
                }
                if ($t['status'] !== 'succeeded') {
                    $ecarts[] = self::ecart('statut_divergent', $ligne['reference'], $t, $ligne['montant'], $attendu, $ligne['devise'],
                        sprintf('Encaisse par l\'operateur ; tentative Tchadok « %s »%s, commande « %s »', $t['status'],
                            $t['error_code'] ? ' (' . $t['error_code'] . ')' : '', $t['order_status']));
                } elseif (!in_array($t['order_status'], ['paid', 'refunded', 'disputed'], true)) {
                    $ecarts[] = self::ecart('statut_divergent', $ligne['reference'], $t, $ligne['montant'], $attendu, $ligne['devise'],
                        'Tentative reussie mais commande « ' . $t['order_status'] . ' » : achat non livre');
                }
            } elseif ($ligne['statut'] === 'disputed' && $t['order_status'] !== 'disputed') {
                $ecarts[] = self::ecart('statut_divergent', $ligne['reference'], $t, $ligne['montant'], $attendu, $ligne['devise'],
                    'Contestee chez l\'operateur ; commande « ' . $t['order_status'] . ' » : droits d\'acces encore ouverts');
            } elseif ($ligne['statut'] === 'refunded' && $t['order_status'] !== 'refunded') {
                $ecarts[] = self::ecart('statut_divergent', $ligne['reference'], $t, $ligne['montant'], $attendu, $ligne['devise'],
                    'Remboursee chez l\'operateur ; commande « ' . $t['order_status'] . ' »');
            }
        }

        // Une commande encaissee plusieurs fois.
        foreach ($encaissesParCommande as $references) {
            foreach (array_slice($references, 1) as $reference) {
                $t = $parReference[$reference] ?? null;
                $ecarts[] = self::ecart('doublon', $reference, $t, null, null, $t['currency'] ?? null,
                    'Commande ' . ($t['order_reference'] ?? '?') . ' encaissee ' . count($references) . ' fois : rembourser le surplus');
            }
        }

        // Paiements enregistres chez Tchadok, absents du releve.
        foreach ($parId as $t) {
            if (!isset($vues[(int) $t['id']]) && $t['status'] === 'succeeded'
                && substr((string) $t['created_at'], 0, 10) === $date) {
                $vues[(int) $t['id']] = true;
                $ecarts[] = self::ecart('commande_sans_encaissement', (string) $t['gateway_ref'], $t, null,
                    Devises::enUnitesMineures((float) $t['amount'], (string) $t['currency']), (string) $t['currency'],
                    'Commande ' . $t['order_reference'] . ' livree, paiement introuvable chez l\'operateur');
            }
        }

        $inserer = $db->prepare(
            'INSERT INTO reconciliation_discrepancies
                (run_id, gateway, statement_date, type, gateway_ref, intent_id, order_id, operator_amount, platform_amount, currency, detail)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $existe = $db->prepare(
            'SELECT 1 FROM reconciliation_discrepancies
              WHERE gateway = ? AND statement_date = ? AND type = ? AND gateway_ref <=> ? AND resolved_at IS NULL LIMIT 1'
        );
        foreach ($ecarts as $e) {
            $existe->execute([$codePasserelle, $date, $e['type'], $e['reference']]);
            if ($existe->fetchColumn() !== false) {
                continue;
            }
            $inserer->execute([$runId, $codePasserelle, $date, $e['type'], $e['reference'], $e['intent_id'], $e['order_id'],
                $e['operateur'], $e['plateforme'], $e['devise'], mb_substr($e['detail'], 0, 500)]);
        }

        $plateforme = count(array_filter($parId, fn($t) => $t['status'] === 'succeeded'));
        $statut = $ecarts === [] ? 'ok' : 'ecarts';
        $db->prepare(
            'UPDATE reconciliation_runs SET status = ?, operator_lines = ?, platform_lines = ?, discrepancies = ?, finished_at = NOW() WHERE id = ?'
        )->execute([$statut, count($releve), $plateforme, count($ecarts), $runId]);

        $seuil = max(0.0, (float) EnvLoader::get('RECONCILIATION_ALERT_RATE', '1'));
        $taux = count($ecarts) * 100 / max(1, count($releve));
        if ($ecarts !== [] && $taux > $seuil) {
            error_log(sprintf('[Tchadok][rapprochement][ALERTE] %s, %s : %d ecart(s) pour %d ligne(s) de releve (%.1f %% > seuil %.1f %%)',
                $codePasserelle, $date, count($ecarts), count($releve), $taux, $seuil));
        }

        return ['statut' => $statut, 'operateur' => count($releve), 'plateforme' => $plateforme, 'ecarts' => count($ecarts), 'erreur' => null];
    }

    /**
     * Clot un ecart. Motif obligatoire (5 caracteres au moins, verifie aussi
     * par la base) ; l'operation est tracee au journal d'audit.
     */
    public static function cloturer(int $ecartId, string $motif, ?int $auteur): bool
    {
        $motif = trim($motif);
        $db = TchadokDatabase::getInstance()->getConnection();
        if (!$db || mb_strlen($motif) < 5) {
            return false;
        }

        $stmt = $db->prepare('SELECT * FROM reconciliation_discrepancies WHERE id = ? AND resolved_at IS NULL');
        $stmt->execute([$ecartId]);
        $ecart = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$ecart) {
            return false;
        }

        $db->prepare('UPDATE reconciliation_discrepancies SET resolved_at = NOW(), resolved_by = ?, resolution = ? WHERE id = ? AND resolved_at IS NULL')
           ->execute([$auteur, mb_substr($motif, 0, 500), $ecartId]);

        JournalAudit::enregistrer('rapprochement.ecart_clos', [
            'cible_type' => 'ecart_rapprochement',
            'cible_id'   => $ecartId,
            'avant'      => ['type' => $ecart['type'], 'passerelle' => $ecart['gateway'], 'reference' => $ecart['gateway_ref'], 'jour' => $ecart['statement_date']],
            'raison'     => $motif,
            'acteur'     => $auteur,
        ]);

        return true;
    }

    /** @return array<int,array<string,mixed>> */
    public static function ouverts(int $limite = 200): array
    {
        $db = TchadokDatabase::getInstance()->getConnection();
        if (!$db) {
            return [];
        }
        return $db->query(
            'SELECT d.*, o.reference AS order_reference FROM reconciliation_discrepancies d
               LEFT JOIN orders o ON o.id = d.order_id
              WHERE d.resolved_at IS NULL ORDER BY d.statement_date DESC, d.id DESC LIMIT ' . max(1, $limite)
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function indexer(array $t, array &$parReference, array &$parTentative, array &$parId): void
    {
        $parId[(int) $t['id']] = $t;
        if (!empty($t['gateway_ref'])) {
            $parReference[(string) $t['gateway_ref']] = $t;
        }
        $parTentative[$t['order_reference'] . '-' . $t['attempt']] = $t;
    }

    /** @return array{type:string, reference:?string, intent_id:?int, order_id:?int, operateur:?int, plateforme:?int, devise:?string, detail:string} */
    private static function ecart(string $type, ?string $reference, ?array $t, ?int $operateur, ?int $plateforme, ?string $devise, string $detail): array
    {
        return [
            'type'       => $type,
            'reference'  => $reference,
            'intent_id'  => $t !== null ? (int) $t['id'] : null,
            'order_id'   => $t !== null ? (int) $t['order_id'] : null,
            'operateur'  => $operateur,
            'plateforme' => $plateforme,
            'devise'     => $devise,
            'detail'     => $detail,
        ];
    }
}
