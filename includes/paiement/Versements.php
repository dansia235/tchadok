<?php
/**
 * Versements aux artistes (LOT 8).
 *
 * DECISION DU 28/09/2026 : la plateforme encaisse, puis reverse a l'artiste
 * QUAND IL LE DEMANDE.
 *
 * LE SOLDE (PAYOUT-01)
 *   Il se lit sur `order_items.artist_net`, fige a la vente, des commandes
 *   PAYEES -- jamais sur la grille courante :
 *
 *     disponible = ventes payees depuis plus de RETENTION jours
 *                + ajustements (corrections, avances, retenues)
 *                - deja verse - deja demande (en cours de traitement)
 *
 *   Une vente remboursee ou contestee quitte d'elle-meme ce calcul. Si elle
 *   avait deja ete versee, le disponible passe en negatif : la reprise se
 *   compense sur les ventes suivantes, sans jamais reclamer d'argent a
 *   l'artiste.
 *
 * LA SEPARATION DES POUVOIRS (PAYOUT-02)
 *   L'artiste demande, la finance valide, une AUTRE personne execute. La base
 *   refuse qu'un meme compte fasse deux de ces gestes (contraintes CHECK) : le
 *   code ne peut pas s'en dispenser par erreur.
 *
 * L'ARGENT NE PART QU'UNE FOIS (PAYOUT-03)
 *   Chaque essai aupres de l'operateur a sa cle d'idempotence
 *   (`payout_attempts`). Un echec revient en file ; un nouvel essai n'est
 *   possible qu'une fois le precedent definitivement echoue.
 */

declare(strict_types=1);

final class Versements
{
    public const LIBELLES = [
        'draft'      => 'Demande en attente de validation',
        'approved'   => 'Valide, en attente d\'execution',
        'processing' => 'Envoi en cours',
        'paid'       => 'Verse',
        'failed'     => 'Echec, a reprendre',
        'on_hold'    => 'Suspendu',
        'rejected'   => 'Refuse',
    ];

    /** Etats qui engagent deja une somme (ni payee, ni refusee). */
    private const ENGAGES = ['draft', 'approved', 'processing', 'on_hold', 'failed'];

    public static function retention(): int
    {
        return max(0, (int) EnvLoader::get('PAYOUT_RETENTION_DAYS', '30'));
    }

    public static function seuil(): float
    {
        return max(0.0, (float) EnvLoader::get('PAYOUT_MINIMUM', '10000'));
    }

    // -----------------------------------------------------------------
    // Solde (PAYOUT-01)
    // -----------------------------------------------------------------

    /**
     * @return array{brut:float, commission:float, net:float, en_retention:float, eligible:float, ajustements:float,
     *               verse:float, engage:float, disponible:float, reprises:float}
     */
    public static function solde(int $artistId): array
    {
        $db = self::base();
        $stmt = $db->prepare(
            'SELECT COALESCE(SUM(oi.unit_price * oi.quantity), 0) AS brut, COALESCE(SUM(oi.commission), 0) AS commission,
                    COALESCE(SUM(oi.artist_net), 0) AS net,
                    COALESCE(SUM(CASE WHEN o.paid_at <= NOW() - INTERVAL ? DAY THEN oi.artist_net ELSE 0 END), 0) AS eligible
               FROM order_items oi JOIN orders o ON o.id = oi.order_id
              WHERE oi.artist_id = ? AND o.status = \'paid\''
        );
        $stmt->execute([self::retention(), $artistId]);
        $v = $stmt->fetch(PDO::FETCH_ASSOC);

        $somme = static function (string $sql, array $p) use ($db): float {
            $s = $db->prepare($sql);
            $s->execute($p);
            return (float) $s->fetchColumn();
        };
        $marqueurs = implode(',', array_fill(0, count(self::ENGAGES), '?'));
        $ajustements = $somme('SELECT COALESCE(SUM(amount), 0) FROM artist_adjustments WHERE artist_id = ?', [$artistId]);
        $verse = $somme("SELECT COALESCE(SUM(net), 0) FROM payouts WHERE artist_id = ? AND status = 'paid'", [$artistId]);
        $engage = $somme("SELECT COALESCE(SUM(net), 0) FROM payouts WHERE artist_id = ? AND status IN ({$marqueurs})", array_merge([$artistId], self::ENGAGES));
        $reprises = $somme('SELECT COALESCE(SUM(artist_net_reversed), 0) FROM refund_items WHERE artist_id = ? AND after_payout = 1', [$artistId]);

        return [
            'brut'         => (float) $v['brut'],
            'commission'   => (float) $v['commission'],
            'net'          => (float) $v['net'],
            'en_retention' => (float) $v['net'] - (float) $v['eligible'],
            'eligible'     => (float) $v['eligible'],
            'ajustements'  => $ajustements,
            'verse'        => $verse,
            'engage'       => $engage,
            'disponible'   => round((float) $v['eligible'] + $ajustements - $verse - $engage, 2),
            'reprises'     => $reprises,
        ];
    }

    /**
     * Correction, avance ou retenue : motivee, tracee, immuable.
     */
    public static function ajuster(int $artistId, float $montant, string $motif, int $auteur): bool
    {
        $motif = trim($motif);
        if ($montant == 0.0 || mb_strlen($motif) < 5) {
            return false;
        }
        self::base()->prepare('INSERT INTO artist_adjustments (artist_id, amount, reason, created_by) VALUES (?, ?, ?, ?)')
            ->execute([$artistId, $montant, mb_substr($motif, 0, 300), $auteur]);
        JournalAudit::enregistrer('versement.ajustement', [
            'cible_type' => 'artiste', 'cible_id' => $artistId, 'apres' => ['montant' => $montant], 'raison' => $motif, 'acteur' => $auteur,
        ]);
        return true;
    }

    // -----------------------------------------------------------------
    // Compte de versement
    // -----------------------------------------------------------------

    public static function compte(int $artistId): ?array
    {
        $stmt = self::base()->prepare('SELECT * FROM artist_payout_accounts WHERE artist_id = ?');
        $stmt->execute([$artistId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Declaration par l'artiste. Tout changement de numero ANNULE la
     * verification : un compte pirate ne doit pas pouvoir detourner les
     * versements en changeant le numero.
     *
     * @return array{succes:bool, message:string}
     */
    public static function enregistrerCompte(int $artistId, string $methode, string $numero, string $titulaire): array
    {
        if (!in_array($methode, ['airtel_money', 'moov_money'], true)) {
            return ['succes' => false, 'message' => 'Operateur non pris en charge.'];
        }
        $msisdn = FabriquePasserelles::obtenir($methode)->normaliserNumero($numero);
        $titulaire = trim($titulaire);
        if ($msisdn === null || mb_strlen($titulaire) < 3) {
            return ['succes' => false, 'message' => 'Numero tchadien ou nom du titulaire invalide.'];
        }
        $actuel = self::compte($artistId);
        if ($actuel && $actuel['method'] === $methode && $actuel['msisdn'] === $msisdn && $actuel['holder_name'] === $titulaire) {
            return ['succes' => true, 'message' => 'Compte inchange.'];
        }
        self::base()->prepare(
            'INSERT INTO artist_payout_accounts (artist_id, method, msisdn, holder_name) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE method = VALUES(method), msisdn = VALUES(msisdn), holder_name = VALUES(holder_name),
                                     verified_at = NULL, verified_by = NULL, verification_note = NULL'
        )->execute([$artistId, $methode, $msisdn, mb_substr($titulaire, 0, 120)]);
        return ['succes' => true, 'message' => 'Compte enregistre. Il sera verifie par notre equipe avant le premier versement.'];
    }

    public static function verifierCompte(int $artistId, string $note, int $auteur): bool
    {
        $note = trim($note);
        if (mb_strlen($note) < 5) {
            return false;
        }
        $stmt = self::base()->prepare('UPDATE artist_payout_accounts SET verified_at = NOW(), verified_by = ?, verification_note = ? WHERE artist_id = ? AND verified_at IS NULL');
        $stmt->execute([$auteur, mb_substr($note, 0, 300), $artistId]);
        if ($stmt->rowCount() !== 1) {
            return false;
        }
        JournalAudit::enregistrer('versement.compte_verifie', ['cible_type' => 'artiste', 'cible_id' => $artistId, 'raison' => $note, 'acteur' => $auteur]);
        return true;
    }

    // -----------------------------------------------------------------
    // Demande et validation (PAYOUT-02)
    // -----------------------------------------------------------------

    /**
     * @return array{succes:bool, message:string, payout_id:?int}
     */
    public static function demander(int $artistId, int $userId): array
    {
        $db = self::base();
        $refus = static fn (string $m): array => ['succes' => false, 'message' => $m, 'payout_id' => null];

        $compte = self::compte($artistId);
        if ($compte === null) {
            return $refus('Declarez d\'abord le compte mobile money qui recevra vos versements.');
        }
        if ($compte['verified_at'] === null) {
            return $refus('Votre compte de versement est en cours de verification par notre equipe.');
        }
        if (class_exists('Contrats') && Contrats::acceptationRequise($artistId)) {
            return $refus('Acceptez la version en vigueur du contrat de distribution avant de demander un versement.');
        }

        $db->beginTransaction();
        try {
            // Verrou de l'artiste : deux demandes simultanees ne peuvent pas
            // engager deux fois la meme somme.
            $db->prepare('SELECT id FROM artists WHERE id = ? FOR UPDATE')->execute([$artistId]);
            $stmt = $db->prepare("SELECT COUNT(*) FROM payouts WHERE artist_id = ? AND status IN ('draft', 'approved', 'processing', 'on_hold', 'failed')");
            $stmt->execute([$artistId]);
            if ((int) $stmt->fetchColumn() > 0) {
                $db->rollBack();
                return $refus('Une demande de versement est deja en cours.');
            }
            $solde = self::solde($artistId);
            if ($solde['disponible'] < self::seuil()) {
                $db->rollBack();
                return $refus(sprintf(
                    'Montant disponible : %s FCFA. Le versement est possible a partir de %s FCFA : il manque %s FCFA.',
                    number_format(max(0, $solde['disponible']), 0, ',', ' '), number_format(self::seuil(), 0, ',', ' '),
                    number_format(self::seuil() - max(0, $solde['disponible']), 0, ',', ' ')
                ));
            }

            $stmt = $db->prepare("SELECT MAX(period_end) FROM payouts WHERE artist_id = ? AND status <> 'rejected'");
            $stmt->execute([$artistId]);
            $precedente = $stmt->fetchColumn();
            $debut = $precedente ? date('Y-m-d', strtotime($precedente . ' +1 day')) : '2000-01-01';
            $fin = date('Y-m-d', strtotime('-' . self::retention() . ' days'));

            $stmt = $db->prepare(
                "SELECT COALESCE(SUM(oi.unit_price * oi.quantity), 0), COALESCE(SUM(oi.commission), 0), COALESCE(SUM(oi.artist_net), 0)
                   FROM order_items oi JOIN orders o ON o.id = oi.order_id
                  WHERE oi.artist_id = ? AND o.status = 'paid' AND o.paid_at >= ? AND o.paid_at < ? + INTERVAL 1 DAY"
            );
            $stmt->execute([$artistId, $debut, $fin]);
            [$brut, $commission, $netPeriode] = array_map('floatval', $stmt->fetch(PDO::FETCH_NUM));

            $db->prepare(
                "INSERT INTO payouts (artist_id, period_start, period_end, gross, commission, adjustments, net, currency, method, destination, status, created_by, requested_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'XAF', ?, ?, 'draft', ?, NOW())"
            )->execute([
                $artistId, $debut, $fin, $brut, $commission, round($solde['disponible'] - $netPeriode, 2), $solde['disponible'],
                $compte['method'], $compte['msisdn'], $userId,
            ]);
            $payoutId = (int) $db->lastInsertId();
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('[Tchadok][versement] demande impossible : ' . $e->getMessage());
            return $refus('Demande impossible pour le moment.');
        }

        JournalAudit::enregistrer('versement.prepare', ['cible_type' => 'versement', 'cible_id' => $payoutId, 'apres' => ['net' => $solde['disponible']], 'acteur' => $userId]);
        return ['succes' => true, 'message' => sprintf('Demande de versement de %s FCFA enregistree.', number_format($solde['disponible'], 0, ',', ' ')), 'payout_id' => $payoutId];
    }

    /** @return array{succes:bool, message:string} */
    public static function approuver(int $payoutId, int $auteur): array
    {
        try {
            $stmt = self::base()->prepare("UPDATE payouts SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ? AND status IN ('draft', 'on_hold')");
            $stmt->execute([$auteur, $payoutId]);
        } catch (PDOException $e) {
            // Contrainte `double_validation` : on ne valide pas sa propre demande.
            return ['succes' => false, 'message' => 'Refuse : une demande ne peut pas etre validee par la personne qui l\'a faite.'];
        }
        if ($stmt->rowCount() !== 1) {
            return ['succes' => false, 'message' => 'Versement introuvable ou deja traite.'];
        }
        JournalAudit::enregistrer('versement.approuve', ['cible_type' => 'versement', 'cible_id' => $payoutId, 'acteur' => $auteur]);
        self::prevenir($payoutId);
        return ['succes' => true, 'message' => 'Versement valide. Il doit maintenant etre execute par une autre personne.'];
    }

    /** @return array{succes:bool, message:string} */
    public static function decider(int $payoutId, string $etat, string $motif, int $auteur): array
    {
        $motif = trim($motif);
        $depuis = ['rejected' => ['draft', 'on_hold', 'failed'], 'on_hold' => ['draft', 'approved', 'failed']][$etat] ?? null;
        if ($depuis === null || mb_strlen($motif) < 5) {
            return ['succes' => false, 'message' => 'Motif obligatoire (5 caracteres au moins).'];
        }
        $marqueurs = implode(',', array_fill(0, count($depuis), '?'));
        $stmt = self::base()->prepare("UPDATE payouts SET status = ?, decision_reason = ? WHERE id = ? AND status IN ({$marqueurs})");
        $stmt->execute(array_merge([$etat, mb_substr($motif, 0, 500), $payoutId], $depuis));
        if ($stmt->rowCount() !== 1) {
            return ['succes' => false, 'message' => 'Versement introuvable ou dans un etat qui ne le permet pas.'];
        }
        JournalAudit::enregistrer($etat === 'rejected' ? 'versement.rejete' : 'versement.suspendu', [
            'cible_type' => 'versement', 'cible_id' => $payoutId, 'raison' => $motif, 'acteur' => $auteur,
        ]);
        self::prevenir($payoutId);
        return ['succes' => true, 'message' => $etat === 'rejected' ? 'Demande refusee ; le montant redevient disponible.' : 'Versement suspendu.'];
    }

    // -----------------------------------------------------------------
    // Execution (PAYOUT-03)
    // -----------------------------------------------------------------

    /** @return array{succes:bool, statut:?string, message:string} */
    public static function executer(int $payoutId, int $auteur): array
    {
        $db = self::base();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare('SELECT * FROM payouts WHERE id = ? FOR UPDATE');
            $stmt->execute([$payoutId]);
            $p = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$p || !in_array($p['status'], ['approved', 'failed', 'processing'], true)) {
                $db->rollBack();
                return ['succes' => false, 'statut' => null, 'message' => 'Seul un versement valide (ou en echec) s\'execute.'];
            }
            // Un essai encore en cours (ou reussi) interdit un nouvel envoi :
            // on relance le MEME essai, avec la meme cle d'idempotence.
            $stmt = $db->prepare("SELECT * FROM payout_attempts WHERE payout_id = ? AND status IN ('pending', 'succeeded') ORDER BY attempt DESC LIMIT 1");
            $stmt->execute([$payoutId]);
            $essai = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($essai !== null && ($essai['status'] === 'succeeded' || $essai['gateway_ref'] !== null)) {
                $db->rollBack();
                return ['succes' => false, 'statut' => $p['status'], 'message' => 'Un envoi est deja en cours ou effectue pour ce versement.'];
            }

            try {
                $db->prepare("UPDATE payouts SET status = 'processing', executed_by = ?, executed_at = NOW(), failure_reason = NULL WHERE id = ?")
                   ->execute([$auteur, $payoutId]);
            } catch (PDOException $e) {
                $db->rollBack();
                return ['succes' => false, 'statut' => null, 'message' => 'Refuse : la personne qui a valide (ou demande) ce versement ne peut pas l\'executer.'];
            }

            if ($essai === null) {
                $stmt = $db->prepare('SELECT COALESCE(MAX(attempt), 0) + 1 FROM payout_attempts WHERE payout_id = ?');
                $stmt->execute([$payoutId]);
                $numero = (int) $stmt->fetchColumn();
                $cle = bin2hex(random_bytes(16));
                $db->prepare('INSERT INTO payout_attempts (payout_id, attempt, gateway, idempotency_key, amount) VALUES (?, ?, ?, ?, ?)')
                   ->execute([$payoutId, $numero, $p['method'], $cle, $p['net']]);
                $essai = ['id' => (int) $db->lastInsertId(), 'attempt' => $numero, 'idempotency_key' => $cle];
            }
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('[Tchadok][versement] execution impossible : ' . $e->getMessage());
            return ['succes' => false, 'statut' => null, 'message' => 'Execution impossible pour le moment.'];
        }

        JournalAudit::enregistrer('versement.execute', ['cible_type' => 'versement', 'cible_id' => $payoutId, 'apres' => ['essai' => $essai['attempt']], 'acteur' => $auteur]);

        if (!FabriquePasserelles::configuree((string) $p['method'])) {
            self::conclure($payoutId, (int) $essai['id'], 'failed', null, 'operateur_non_configure');
            return ['succes' => false, 'statut' => 'failed', 'message' => 'Operateur de versement non configure.'];
        }
        $passerelle = FabriquePasserelles::obtenir((string) $p['method']);
        $passerelle->journaliserAvec(static function (array $e) use ($p): void {
            Commandes::enregistrerEvenement($e + ['gateway' => $p['method'], 'ip_address' => '']);
        });
        $reponse = $passerelle->verser(
            'VRS-' . $payoutId . '-' . $essai['attempt'],
            Devises::enUnitesMineures((float) $p['net'], 'XAF'),
            (string) $p['destination'],
            rtrim((string) EnvLoader::get('PAYMENT_CALLBACK_BASE', ''), '/') . '/api/payments/callback.php?passerelle=' . rawurlencode((string) $p['method']),
            (string) $essai['idempotency_key']
        );

        if ($reponse->echange && $reponse->reference !== null) {
            self::base()->prepare('UPDATE payout_attempts SET gateway_ref = ? WHERE id = ? AND gateway_ref IS NULL')->execute([$reponse->reference, $essai['id']]);
            if (in_array($reponse->statut, ['succeeded', 'failed'], true)) {
                self::appliquerCallback((string) $p['method'], $reponse->reference, $reponse->statut, $reponse->montant, $reponse->codeErreur);
            }
            return ['succes' => true, 'statut' => 'processing', 'message' => 'Versement envoye a l\'operateur ; confirmation attendue.'];
        }
        if ($reponse->codeErreur === 'reseau') {
            return ['succes' => false, 'statut' => 'processing', 'message' => 'Reponse de l\'operateur non recue : relancez l\'execution, le meme envoi sera repris sans double paiement.'];
        }
        self::conclure($payoutId, (int) $essai['id'], 'failed', null, (string) $reponse->codeErreur);
        return ['succes' => false, 'statut' => 'failed', 'message' => 'L\'operateur a refuse le versement (' . $reponse->codeErreur . '). Le versement revient en file.'];
    }

    /**
     * Notification de l'operateur (disbursement.succeeded / failed), ou
     * reponse a une consultation. Idempotent.
     */
    public static function appliquerCallback(string $gateway, string $reference, string $statut, ?int $montant, ?string $code): string
    {
        $db = self::base();
        $stmt = $db->prepare('SELECT a.*, p.net, p.status AS payout_status FROM payout_attempts a JOIN payouts p ON p.id = a.payout_id WHERE a.gateway = ? AND a.gateway_ref = ?');
        $stmt->execute([$gateway, $reference]);
        $essai = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$essai) {
            error_log(sprintf('[Tchadok][versement][ALERTE] notification pour un versement inconnu (%s %s)', $gateway, $reference));
            return 'versement_inconnu';
        }
        if ($essai['status'] !== 'pending') {
            return 'doublon';
        }
        if ($statut === 'succeeded' && $montant !== null && $montant !== Devises::enUnitesMineures((float) $essai['net'], 'XAF')) {
            $db->prepare("UPDATE payouts SET status = 'on_hold', decision_reason = ? WHERE id = ?")
               ->execute(['Montant verse different du montant demande (' . $montant . ') : a verifier', $essai['payout_id']]);
            error_log('[Tchadok][versement][ALERTE] montant divergent sur le versement ' . $essai['payout_id']);
            return 'revue';
        }
        return self::conclure((int) $essai['payout_id'], (int) $essai['id'], $statut === 'succeeded' ? 'succeeded' : 'failed', $reference, $code);
    }

    /** Consulte les envois sans reponse depuis plus d'une minute. */
    public static function verifierEnCours(): int
    {
        $db = self::base();
        $n = 0;
        foreach ($db->query("SELECT gateway, gateway_ref FROM payout_attempts WHERE status = 'pending' AND gateway_ref IS NOT NULL AND created_at < NOW() - INTERVAL 1 MINUTE")->fetchAll(PDO::FETCH_ASSOC) as $a) {
            if (!FabriquePasserelles::configuree((string) $a['gateway'])) {
                continue;
            }
            $r = FabriquePasserelles::obtenir((string) $a['gateway'])->statutVersement((string) $a['gateway_ref']);
            if ($r->echange && in_array($r->statut, ['succeeded', 'failed'], true)) {
                self::appliquerCallback((string) $a['gateway'], (string) $a['gateway_ref'], $r->statut, $r->montant, $r->codeErreur);
                $n++;
            }
        }
        return $n;
    }

    private static function conclure(int $payoutId, int $essaiId, string $resultat, ?string $reference, ?string $code): string
    {
        $db = self::base();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("UPDATE payout_attempts SET status = ?, failure_code = ?, completed_at = NOW() WHERE id = ? AND status = 'pending'");
            $stmt->execute([$resultat, $code, $essaiId]);
            if ($stmt->rowCount() !== 1) {
                $db->rollBack();
                return 'doublon';
            }
            if ($resultat === 'succeeded') {
                $db->prepare("UPDATE payouts SET status = 'paid', paid_at = NOW() WHERE id = ?")->execute([$payoutId]);
            } else {
                $db->prepare("UPDATE payouts SET status = 'failed', failure_reason = ? WHERE id = ?")->execute([$code ?: 'echec', $payoutId]);
            }
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
        self::prevenir($payoutId);
        return $resultat === 'succeeded' ? 'verse' : 'echec';
    }

    // -----------------------------------------------------------------
    // Releve (PAYOUT-03)
    // -----------------------------------------------------------------

    /**
     * Detail d'un versement : ventes de la periode ligne a ligne, ajustements,
     * et regularisations (ventes deja versees puis remboursees ou contestees).
     * Les composantes additionnees donnent exactement le net verse.
     *
     * @return array<string,mixed>|null
     */
    public static function releve(int $payoutId): ?array
    {
        $db = self::base();
        $stmt = $db->prepare('SELECT p.*, a.stage_name FROM payouts p JOIN artists a ON a.id = p.artist_id WHERE p.id = ?');
        $stmt->execute([$payoutId]);
        $p = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$p) {
            return null;
        }
        $stmt = $db->prepare(
            "SELECT o.reference, o.paid_at, oi.label, oi.item_type, oi.unit_price, oi.quantity, oi.commission_rate, oi.commission, oi.artist_net
               FROM order_items oi JOIN orders o ON o.id = oi.order_id
              WHERE oi.artist_id = ? AND o.status = 'paid' AND o.paid_at >= ? AND o.paid_at < ? + INTERVAL 1 DAY
              ORDER BY o.paid_at, oi.id"
        );
        $stmt->execute([$p['artist_id'], $p['period_start'], $p['period_end']]);
        $ventes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $db->prepare(
            "SELECT MAX(requested_at) FROM payouts WHERE artist_id = ? AND id <> ? AND status <> 'rejected' AND requested_at < ?"
        );
        $stmt->execute([$p['artist_id'], $payoutId, $p['requested_at']]);
        $depuis = $stmt->fetchColumn() ?: '2000-01-01';
        $stmt = $db->prepare('SELECT created_at, amount, reason FROM artist_adjustments WHERE artist_id = ? AND created_at > ? AND created_at <= ? ORDER BY created_at');
        $stmt->execute([$p['artist_id'], $depuis, $p['requested_at']]);
        $ajustements = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $netVentes = array_sum(array_map(fn($v) => (float) $v['artist_net'], $ventes));
        $netAjustements = array_sum(array_map(fn($a) => (float) $a['amount'], $ajustements));

        return [
            'versement'      => $p,
            'ventes'         => $ventes,
            'ajustements'    => $ajustements,
            'total_ventes'   => $netVentes,
            'total_brut'     => array_sum(array_map(fn($v) => (float) $v['unit_price'] * (int) $v['quantity'], $ventes)),
            'total_commission' => array_sum(array_map(fn($v) => (float) $v['commission'], $ventes)),
            'total_ajustements' => $netAjustements,
            // Ce qui reste : reprises de ventes deja versees puis remboursees
            // ou contestees, et ventes anterieures a la periode encore dues.
            'regularisation' => round((float) $p['net'] - $netVentes - $netAjustements, 2),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public static function historique(int $artistId): array
    {
        $stmt = self::base()->prepare('SELECT * FROM payouts WHERE artist_id = ? ORDER BY id DESC');
        $stmt->execute([$artistId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int,array<string,mixed>> */
    public static function aTraiter(): array
    {
        return self::base()->query(
            "SELECT p.*, a.stage_name, ac.holder_name, ac.verified_at,
                    u1.username AS demandeur, u2.username AS validateur
               FROM payouts p JOIN artists a ON a.id = p.artist_id
               LEFT JOIN artist_payout_accounts ac ON ac.artist_id = p.artist_id
               LEFT JOIN users u1 ON u1.id = p.created_by LEFT JOIN users u2 ON u2.id = p.approved_by
              WHERE p.status IN ('draft', 'approved', 'processing', 'failed', 'on_hold')
              ORDER BY FIELD(p.status, 'draft', 'approved', 'failed', 'on_hold', 'processing'), p.requested_at"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function prevenir(int $payoutId): void
    {
        $stmt = self::base()->prepare(
            'SELECT p.status, p.net, p.decision_reason, p.failure_reason, u.email, u.first_name
               FROM payouts p JOIN artists a ON a.id = p.artist_id JOIN users u ON u.id = a.user_id WHERE p.id = ?'
        );
        $stmt->execute([$payoutId]);
        $p = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$p || empty($p['email'])) {
            return;
        }
        $montant = number_format((float) $p['net'], 0, ',', ' ') . ' FCFA';
        $detail = match ($p['status']) {
            'approved' => 'Votre demande de versement de ' . $montant . ' est validee ; elle sera envoyee prochainement.',
            'paid'     => 'Votre versement de ' . $montant . ' a ete envoye sur votre compte mobile money.',
            'failed'   => 'L\'envoi de votre versement de ' . $montant . ' a echoue (' . $p['failure_reason'] . '). Notre equipe le reprend.',
            'rejected' => 'Votre demande de versement a ete refusee. Motif : ' . $p['decision_reason'],
            'on_hold'  => 'Votre versement est suspendu. Motif : ' . $p['decision_reason'],
            default    => null,
        };
        if ($detail !== null) {
            sendEmail((string) $p['email'], 'Tchadok - votre versement', '<p>Bonjour ' . htmlspecialchars((string) $p['first_name'], ENT_QUOTES, 'UTF-8') . ',</p><p>'
                . htmlspecialchars($detail, ENT_QUOTES, 'UTF-8') . '</p><p>Detail : espace artiste, rubrique « Revenus et versements ».</p>');
        }
    }

    private static function base(): PDO
    {
        return TchadokDatabase::getInstance()->getConnection();
    }
}
