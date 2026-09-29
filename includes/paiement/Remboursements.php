<?php
/**
 * Remboursements et reclamations (SHOP-07).
 *
 * UN REMBOURSEMENT
 *   - se decide avec un motif, par une personne habilitee
 *     (finance.remboursement.executer), et s'inscrit au journal d'audit ;
 *   - n'efface rien : la commande et ses lignes restent, une ecriture
 *     d'annulation (refunds, refund_items) porte le montant, la commission et
 *     la part artiste reprises ;
 *   - retire l'acces aux contenus (droits revoques, pas supprimes) ;
 *   - passe par l'operateur quand il le permet ; sinon il est marque « manuel »
 *     et reste suivi jusqu'a sa cloture.
 *
 * Le client, lui, peut RECLAMER depuis sa bibliotheque : la demande attend une
 * decision, annoncee sous DELAI_REPONSE.
 *
 * Portee actuelle : remboursement TOTAL d'une commande. Le remboursement
 * partiel (une ligne sur plusieurs) viendra si le besoin se confirme.
 */

declare(strict_types=1);

final class Remboursements
{
    public const DELAI_REPONSE = '3 jours ouvres';

    public const MOTIFS_CLIENT = [
        'non_recu'          => 'J\'ai paye mais je n\'ai pas acces a mon achat',
        'ne_fonctionne_pas' => 'Le titre ne se lit pas ou est defectueux',
        'doublon'           => 'J\'ai ete debite deux fois',
        'achat_par_erreur'  => 'Achat par erreur',
        'autre'             => 'Autre raison',
    ];

    public const LIBELLES = [
        'demande'  => 'Demande en attente',
        'refusee'  => 'Refusee',
        'en_cours' => 'Remboursement en cours',
        'effectue' => 'Rembourse',
        'manuel'   => 'A rembourser manuellement',
        'echoue'   => 'Echec, a reprendre',
    ];

    /** Etats d'un dossier encore ouvert. */
    private const OUVERTS = ['demande', 'en_cours', 'manuel', 'echoue'];

    // -----------------------------------------------------------------
    // Cote client
    // -----------------------------------------------------------------

    /**
     * @return array{succes:bool, message:string}
     */
    public static function demander(int $userId, string $reference, string $motif, string $message): array
    {
        $db = self::base();
        if (!$db) {
            return ['succes' => false, 'message' => 'Service momentanement indisponible.'];
        }
        if (!isset(self::MOTIFS_CLIENT[$motif])) {
            return ['succes' => false, 'message' => 'Choisissez un motif.'];
        }

        $stmt = $db->prepare('SELECT id, status, total FROM orders WHERE reference = ? AND user_id = ?');
        $stmt->execute([$reference, $userId]);
        $commande = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$commande || !in_array($commande['status'], ['paid', 'review'], true)) {
            return ['succes' => false, 'message' => 'Cette commande ne peut pas faire l\'objet d\'une reclamation.'];
        }
        if (self::dossierOuvert($db, (int) $commande['id']) !== null) {
            return ['succes' => false, 'message' => 'Une reclamation est deja en cours pour cette commande.'];
        }

        $db->prepare(
            "INSERT INTO refunds (order_id, status, amount_xaf, customer_reason, customer_message, requested_by)
             VALUES (?, 'demande', ?, ?, ?, ?)"
        )->execute([$commande['id'], $commande['total'], $motif, mb_substr(trim($message), 0, 1000) ?: null, $userId]);

        error_log(sprintf('[Tchadok][remboursement] reclamation deposee : commande %s, motif %s', $reference, $motif));

        return ['succes' => true, 'message' => 'Votre reclamation est enregistree. Reponse sous ' . self::DELAI_REPONSE . '.'];
    }

    /** @return array<string,mixed>|null dernier dossier de la commande */
    public static function dossier(int $orderId): ?array
    {
        $db = self::base();
        if (!$db) {
            return null;
        }
        $stmt = $db->prepare('SELECT * FROM refunds WHERE order_id = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$orderId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // -----------------------------------------------------------------
    // Cote finance
    // -----------------------------------------------------------------

    /**
     * Rembourse integralement une commande.
     *
     * @return array{succes:bool, statut:?string, message:string}
     */
    public static function rembourser(int $orderId, string $motif, ?int $auteur): array
    {
        $motif = trim($motif);
        if (mb_strlen($motif) < 5) {
            return ['succes' => false, 'statut' => null, 'message' => 'Motif obligatoire (5 caracteres au moins).'];
        }
        $db = self::base();
        if (!$db) {
            return ['succes' => false, 'statut' => null, 'message' => 'Base indisponible.'];
        }

        $db->beginTransaction();
        try {
            $stmt = $db->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
            $stmt->execute([$orderId]);
            $commande = $stmt->fetch(PDO::FETCH_ASSOC);

            // Payee, ou en revue : l'argent a ete recu dans les deux cas.
            if (!$commande || !in_array($commande['status'], ['paid', 'review'], true)) {
                $db->rollBack();
                return ['succes' => false, 'statut' => null, 'message' => 'Seule une commande payee (ou en revue) se rembourse.'];
            }
            // SHOP-06 : un rechargement deja credite a pu etre depense. Il se
            // rembourse par un ajustement motive du portefeuille, pas ici.
            if (class_exists('Portefeuille') && Portefeuille::estRecharge($db, $orderId)) {
                $db->rollBack();
                return ['succes' => false, 'statut' => null, 'message' => 'Un rechargement du portefeuille se regle par un ajustement du solde (scripts/portefeuille.php).'];
            }

            $stmt = $db->prepare(
                "SELECT * FROM payment_intents
                  WHERE order_id = ? AND status IN ('succeeded', 'review')
                  ORDER BY (gateway_ref = ?) DESC, id DESC LIMIT 1"
            );
            $stmt->execute([$orderId, (string) $commande['gateway_ref']]);
            $intent = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

            // Une reclamation en attente devient ce remboursement.
            $dossier = self::dossierOuvert($db, $orderId);
            if ($dossier !== null && $dossier['status'] !== 'demande') {
                $db->rollBack();
                return ['succes' => false, 'statut' => $dossier['status'], 'message' => 'Un remboursement est deja engage pour cette commande.'];
            }

            $valeurs = [
                $intent['id'] ?? null, (float) $commande['total'],
                $intent !== null ? (float) $intent['amount'] : (float) $commande['total'],
                $intent['currency'] ?? 'XAF', $intent['gateway'] ?? $commande['payment_method'],
                mb_substr($motif, 0, 500), $auteur,
            ];
            if ($dossier !== null) {
                $db->prepare(
                    "UPDATE refunds SET intent_id = ?, amount_xaf = ?, amount = ?, currency = ?, gateway = ?,
                            decision_reason = ?, decided_by = ?, decided_at = NOW(), status = 'en_cours'
                      WHERE id = ?"
                )->execute(array_merge($valeurs, [$dossier['id']]));
                $refundId = (int) $dossier['id'];
            } else {
                $db->prepare(
                    "INSERT INTO refunds (order_id, intent_id, amount_xaf, amount, currency, gateway, decision_reason, decided_by, decided_at, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'en_cours')"
                )->execute(array_merge([$orderId], $valeurs));
                $refundId = (int) $db->lastInsertId();
            }

            // Ecriture d'annulation, ligne par ligne, avec la part artiste.
            $stmt = $db->prepare('SELECT * FROM order_items WHERE order_id = ?');
            $stmt->execute([$orderId]);
            $apresVersement = $db->prepare(
                "SELECT 1 FROM payouts WHERE artist_id = ? AND status = 'paid' AND ? BETWEEN period_start AND period_end LIMIT 1"
            );
            $inserer = $db->prepare(
                'INSERT INTO refund_items (refund_id, order_item_id, artist_id, amount, commission_reversed, artist_net_reversed, after_payout)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $ligne) {
                $dejaVerse = false;
                if ($ligne['artist_id'] !== null && $commande['paid_at'] !== null) {
                    $apresVersement->execute([$ligne['artist_id'], substr((string) $commande['paid_at'], 0, 10)]);
                    $dejaVerse = (bool) $apresVersement->fetchColumn();
                }
                $inserer->execute([
                    $refundId, $ligne['id'], $ligne['artist_id'],
                    (float) $ligne['unit_price'] * (int) $ligne['quantity'],
                    $ligne['commission'], $ligne['artist_net'], (int) $dejaVerse,
                ]);
            }

            $db->prepare("UPDATE orders SET status = 'refunded', refunded_at = NOW(), refund_reason = ? WHERE id = ?")
               ->execute([mb_substr($motif, 0, 500), $orderId]);
            $revoques = Commandes::revoquerDroits($orderId);

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('[Tchadok][remboursement] impossible : ' . $e->getMessage());
            return ['succes' => false, 'statut' => null, 'message' => 'Remboursement impossible pour le moment.'];
        }

        // L'argent, HORS transaction : on ne garde pas de verrou pendant un
        // echange reseau.
        $statut = self::rendreArgent($refundId, $intent, (string) $commande['payment_method'], $orderId);

        JournalAudit::enregistrer('remboursement.execute', [
            'cible_type' => 'commande',
            'cible_id'   => $commande['reference'],
            'avant'      => ['statut' => $commande['status'], 'total' => $commande['total']],
            'apres'      => ['statut' => 'refunded', 'remboursement' => $statut, 'droits_revoques' => $revoques],
            'raison'     => $motif,
            'acteur'     => $auteur,
        ]);
        self::prevenir($orderId, $statut);

        return ['succes' => true, 'statut' => $statut, 'message' => match ($statut) {
            'effectue' => 'Remboursement effectue par l\'operateur.',
            'en_cours' => 'Remboursement demande a l\'operateur ; confirmation attendue.',
            'manuel'   => 'L\'operateur n\'a pas pu rembourser en ligne : remboursement a effectuer manuellement.',
            default    => 'Remboursement enregistre.',
        }];
    }

    /**
     * @return array{succes:bool, message:string}
     */
    public static function refuser(int $refundId, string $motif, ?int $auteur): array
    {
        $motif = trim($motif);
        if (mb_strlen($motif) < 5) {
            return ['succes' => false, 'message' => 'Motif obligatoire (5 caracteres au moins) : il sera communique au client.'];
        }
        $db = self::base();
        $stmt = $db->prepare("UPDATE refunds SET status = 'refusee', decision_reason = ?, decided_by = ?, decided_at = NOW() WHERE id = ? AND status = 'demande'");
        $stmt->execute([mb_substr($motif, 0, 500), $auteur, $refundId]);
        if ($stmt->rowCount() !== 1) {
            return ['succes' => false, 'message' => 'Reclamation introuvable ou deja traitee.'];
        }

        $stmt = $db->prepare('SELECT order_id FROM refunds WHERE id = ?');
        $stmt->execute([$refundId]);
        $orderId = (int) $stmt->fetchColumn();
        JournalAudit::enregistrer('remboursement.refuse', [
            'cible_type' => 'reclamation', 'cible_id' => $refundId, 'raison' => $motif, 'acteur' => $auteur,
        ]);
        self::prevenir($orderId, 'refusee', $motif);

        return ['succes' => true, 'message' => 'Reclamation refusee ; le client est prevenu.'];
    }

    /**
     * Cloture un remboursement fait a la main (virement, mobile money depuis
     * le compte marchand), ou reprend un echec.
     */
    public static function cloturerManuel(int $refundId, string $note, ?int $auteur): bool
    {
        $note = trim($note);
        if (mb_strlen($note) < 5) {
            return false;
        }
        $db = self::base();
        $stmt = $db->prepare(
            "UPDATE refunds SET status = 'effectue', completed_at = NOW(),
                    decision_reason = CONCAT(decision_reason, ' | Cloture manuelle : ', ?)
              WHERE id = ? AND status IN ('manuel', 'echoue', 'en_cours')"
        );
        $stmt->execute([mb_substr($note, 0, 200), $refundId]);
        if ($stmt->rowCount() !== 1) {
            return false;
        }
        JournalAudit::enregistrer('remboursement.execute', [
            'cible_type' => 'remboursement', 'cible_id' => $refundId, 'apres' => ['statut' => 'effectue', 'cloture' => 'manuelle'],
            'raison' => $note, 'acteur' => $auteur,
        ]);
        return true;
    }

    /**
     * Callback refund.succeeded de l'operateur : confirme le remboursement.
     */
    public static function confirmer(string $gateway, string $referencePaiement): bool
    {
        $db = self::base();
        if (!$db) {
            return false;
        }
        $stmt = $db->prepare(
            "UPDATE refunds r JOIN payment_intents pi ON pi.id = r.intent_id
                SET r.status = 'effectue', r.completed_at = COALESCE(r.completed_at, NOW())
              WHERE pi.gateway = ? AND pi.gateway_ref = ? AND r.status IN ('en_cours', 'echoue')"
        );
        $stmt->execute([$gateway, $referencePaiement]);
        return $stmt->rowCount() > 0;
    }

    /** @return array<int,array<string,mixed>> */
    public static function aTraiter(int $limite = 100): array
    {
        $db = self::base();
        if (!$db) {
            return [];
        }
        return $db->query(
            // SUB-04 : les reclamations des abonnes Premium passent en tete.
            "SELECT r.*, o.reference, o.status AS order_status, o.total, o.payment_method, u.email, u.first_name, u.last_name,
                    EXISTS(SELECT 1 FROM subscriptions s WHERE s.user_id = u.id AND s.status = 'active'
                            AND s.start_date <= NOW() AND s.end_date > NOW()) AS prioritaire
               FROM refunds r JOIN orders o ON o.id = r.order_id JOIN users u ON u.id = o.user_id
              WHERE r.status IN ('demande', 'en_cours', 'manuel', 'echoue')
              ORDER BY FIELD(r.status, 'demande', 'manuel', 'echoue', 'en_cours'), prioritaire DESC, r.created_at LIMIT " . max(1, $limite)
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    // -----------------------------------------------------------------
    // Interne
    // -----------------------------------------------------------------

    /**
     * Demande le remboursement a l'operateur et range le dossier selon sa
     * reponse. Sans tentative d'origine (paiement introuvable) : manuel.
     */
    private static function rendreArgent(int $refundId, ?array $intent, string $moyen, int $orderId): string
    {
        $db = self::base();
        $ranger = static function (string $statut, ?string $ref, ?string $echec) use ($db, $refundId): string {
            $db->prepare(
                "UPDATE refunds SET status = ?, gateway_refund_ref = COALESCE(?, gateway_refund_ref), failure_message = ?,
                        completed_at = IF(? = 'effectue', NOW(), completed_at)
                  WHERE id = ?"
            )->execute([$statut, $ref, $echec !== null ? mb_substr($echec, 0, 500) : null, $statut, $refundId]);
            return $statut;
        };

        // SHOP-06 : achat regle par le portefeuille, recredite sur le champ.
        if ($moyen === 'wallet') {
            return class_exists('Portefeuille') && Portefeuille::rembourserAchat($orderId)
                ? $ranger('effectue', 'WAL-' . $orderId, null)
                : $ranger('manuel', null, 'Recredit du portefeuille impossible : a corriger par un ajustement.');
        }

        if ($intent === null || empty($intent['gateway_ref']) || !FabriquePasserelles::configuree((string) $intent['gateway'])) {
            return $ranger('manuel', null, 'Paiement d\'origine introuvable chez un operateur configure.');
        }

        $passerelle = FabriquePasserelles::obtenir((string) $intent['gateway']);
        $passerelle->journaliserAvec(static function (array $e) use ($intent): void {
            Commandes::enregistrerEvenement($e + ['gateway' => $intent['gateway'], 'order_id' => $intent['order_id'], 'intent_id' => $intent['id'], 'ip_address' => '']);
        });
        $reponse = $passerelle->rembourser(
            (string) $intent['gateway_ref'],
            Devises::enUnitesMineures((float) $intent['amount'], (string) $intent['currency']),
            'Remboursement Tchadok'
        );

        if ($reponse->echange && in_array($reponse->statut, ['succeeded', 'refunded'], true)) {
            return $ranger('effectue', $reponse->reference, null);
        }
        if (!$reponse->echange && $reponse->codeErreur === 'reseau') {
            return $ranger('en_cours', null, 'Reponse de l\'operateur non recue : verifier, puis relancer ou cloturer.');
        }
        return $ranger('manuel', null, trim(($reponse->codeErreur ?? '') . ' ' . ($reponse->messageErreur ?? '')));
    }

    private static function prevenir(int $orderId, string $statut, ?string $motif = null): void
    {
        $db = self::base();
        $stmt = $db->prepare('SELECT o.reference, o.total, u.email, u.first_name, u.anonymized_at FROM orders o JOIN users u ON u.id = o.user_id WHERE o.id = ?');
        $stmt->execute([$orderId]);
        $c = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$c || empty($c['email']) || $c['anonymized_at'] !== null) {
            return;
        }
        $e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $montant = number_format((float) $c['total'], 0, ',', ' ') . ' FCFA';
        $corps = '<p>Bonjour ' . $e($c['first_name']) . ',</p>' . ($statut === 'refusee'
            ? '<p>Votre reclamation concernant la commande ' . $e($c['reference']) . ' n\'a pas ete acceptee.</p><p>Motif : ' . $e($motif) . '</p>'
            : '<p>La commande ' . $e($c['reference']) . ' est remboursee (' . $e($montant) . '). '
              . ($statut === 'effectue' ? 'Le montant est reverse sur le moyen de paiement utilise.' : 'Le reversement est en cours ; notre equipe vous tiendra informe(e).')
              . '</p><p>Les contenus de cette commande ne sont plus accessibles.</p>');
        sendEmail((string) $c['email'], 'Tchadok - commande ' . $c['reference'], $corps);
    }

    private static function dossierOuvert(PDO $db, int $orderId): ?array
    {
        $marqueurs = implode(',', array_fill(0, count(self::OUVERTS), '?'));
        $stmt = $db->prepare("SELECT * FROM refunds WHERE order_id = ? AND status IN ({$marqueurs}) ORDER BY id DESC LIMIT 1");
        $stmt->execute(array_merge([$orderId], self::OUVERTS));
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private static function base(): ?PDO
    {
        return TchadokDatabase::getInstance()->getConnection() ?: null;
    }
}
