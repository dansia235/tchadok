<?php
/**
 * Portefeuille prepaye (SHOP-06).
 *
 * On recharge une fois par une passerelle (Airtel Money, Moov Money, GIMAC,
 * carte), puis on achete en un clic, sans frais ni attente de confirmation
 * sur le telephone.
 *
 * REGLES
 *   1. Le journal (`wallet_transactions`, ajouts seulement) fait foi ;
 *      `users.wallet_balance` n'est qu'un cache, recalculable.
 *   2. Tout mouvement se fait sous VERROU de la ligne du membre
 *      (`SELECT ... FOR UPDATE`) : deux achats simultanes sur un solde
 *      insuffisant n'en laissent passer qu'un. Le solde ne devient jamais
 *      negatif (contrainte en base).
 *   3. Chaque mouvement porte une reference unique : un callback de recharge
 *      rejoue ne credite pas deux fois.
 *   4. Un rechargement echoue ne credite rien : le credit n'a lieu qu'a
 *      l'encaissement confirme par l'operateur.
 */

declare(strict_types=1);

final class Portefeuille
{
    /** Paliers de rechargement proposes, en FCFA. */
    public const PALIERS = [1000, 2500, 5000, 10000];

    public const LIBELLES = [
        'topup'      => 'Rechargement',
        'purchase'   => 'Achat',
        'refund'     => 'Remboursement',
        'adjustment' => 'Correction',
    ];

    public static function solde(int $userId): float
    {
        $db = self::base();
        if (!$db) {
            return 0.0;
        }
        $stmt = $db->prepare('SELECT wallet_balance FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        return (float) $stmt->fetchColumn();
    }

    /** @return array<int,array<string,mixed>> */
    public static function mouvements(int $userId, int $limite = 50): array
    {
        $db = self::base();
        if (!$db) {
            return [];
        }
        $stmt = $db->prepare(
            'SELECT w.*, o.reference AS order_reference FROM wallet_transactions w
               LEFT JOIN orders o ON o.id = w.order_id
              WHERE w.user_id = ? ORDER BY w.id DESC LIMIT ' . max(1, $limite)
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cree la commande de rechargement, a regler par une passerelle.
     *
     * @return array{succes:bool, reference:?string, erreur:?string}
     */
    public static function creerRecharge(int $userId, int $montant): array
    {
        if (!in_array($montant, self::PALIERS, true)) {
            return ['succes' => false, 'reference' => null, 'erreur' => 'Montant de rechargement non propose.'];
        }
        $db = self::base();
        if (!$db) {
            return ['succes' => false, 'reference' => null, 'erreur' => 'Service indisponible.'];
        }

        $reference = Commandes::reference();
        $db->prepare("INSERT INTO orders (reference, user_id, status, currency) VALUES (?, ?, 'awaiting_payment', 'XAF')")
           ->execute([$reference, $userId]);
        $orderId = (int) $db->lastInsertId();
        $ajout = Commandes::ajouterArticle($orderId, 'wallet_topup', 0, (float) $montant, null, 'Rechargement du portefeuille');
        if (!$ajout['succes']) {
            return ['succes' => false, 'reference' => null, 'erreur' => 'Rechargement impossible pour le moment.'];
        }

        return ['succes' => true, 'reference' => $reference, 'erreur' => null];
    }

    /** La commande est-elle un rechargement (a ne pas regler... par le portefeuille) ? */
    public static function estRecharge(PDO $db, int $orderId): bool
    {
        $stmt = $db->prepare("SELECT 1 FROM order_items WHERE order_id = ? AND item_type = 'wallet_topup' LIMIT 1");
        $stmt->execute([$orderId]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Credite un rechargement paye. Appele par la machine a etats DANS sa
     * transaction, a l'encaissement. Idempotent (reference unique).
     */
    public static function crediterRecharge(PDO $db, int $orderId): bool
    {
        $stmt = $db->prepare(
            "SELECT o.user_id, SUM(oi.unit_price * oi.quantity) AS montant
               FROM orders o JOIN order_items oi ON oi.order_id = o.id
              WHERE o.id = ? AND o.status = 'paid' AND oi.item_type = 'wallet_topup'
              GROUP BY o.user_id"
        );
        $stmt->execute([$orderId]);
        $ligne = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$ligne || (float) $ligne['montant'] <= 0) {
            return false;
        }
        return self::mouvement($db, (int) $ligne['user_id'], 'topup', (float) $ligne['montant'], $orderId, 'recharge:' . $orderId, null, null);
    }

    /**
     * Regle une commande avec le solde.
     *
     * @return array{succes:bool, erreur:?string}
     */
    public static function payer(string $referenceCommande, int $userId): array
    {
        $db = self::base();
        if (!$db) {
            return ['succes' => false, 'erreur' => 'Service indisponible.'];
        }

        $db->beginTransaction();
        try {
            // Verrou du membre D'ABORD : c'est lui qui serialise les achats
            // concurrents sur un meme solde.
            $stmt = $db->prepare('SELECT wallet_balance FROM users WHERE id = ? FOR UPDATE');
            $stmt->execute([$userId]);
            $solde = (float) $stmt->fetchColumn();

            $stmt = $db->prepare('SELECT * FROM orders WHERE reference = ? AND user_id = ? FOR UPDATE');
            $stmt->execute([$referenceCommande, $userId]);
            $commande = $stmt->fetch(PDO::FETCH_ASSOC);

            $erreur = null;
            if (!$commande || !in_array($commande['status'], ['cart', 'awaiting_payment', 'failed', 'cancelled', 'expired'], true)) {
                $erreur = 'Cette commande ne peut pas etre reglee.';
            } elseif (self::estRecharge($db, (int) $commande['id'])) {
                $erreur = 'Un rechargement se regle par Airtel Money, Moov Money, GIMAC ou carte.';
            } elseif ((float) $commande['total'] <= 0) {
                $erreur = 'Commande vide.';
            } elseif ($solde < (float) $commande['total']) {
                $erreur = sprintf('Solde insuffisant : %s FCFA disponibles pour %s FCFA. Rechargez votre portefeuille.',
                    number_format($solde, 0, ',', ' '), number_format((float) $commande['total'], 0, ',', ' '));
            } else {
                $stmt = $db->prepare("SELECT 1 FROM payment_intents WHERE order_id = ? AND status IN ('created', 'pending') AND expires_at > NOW() LIMIT 1");
                $stmt->execute([$commande['id']]);
                if ($stmt->fetchColumn()) {
                    $erreur = 'Un paiement est deja en attente de confirmation pour cette commande.';
                }
            }
            if ($erreur !== null) {
                $db->rollBack();
                return ['succes' => false, 'erreur' => $erreur];
            }

            self::mouvement($db, $userId, 'purchase', -(float) $commande['total'], (int) $commande['id'], 'achat:' . $commande['id'], null, null);
            $encaissement = Commandes::marquerPayee((int) $commande['id'], 'WAL-' . $commande['id'], 'wallet');
            if (!$encaissement['succes']) {
                throw new RuntimeException(implode(' ', $encaissement['erreurs']));
            }
            // LOT 7 : un abonnement regle par le solde s'active aussi.
            if (class_exists('Abonnements')) {
                Abonnements::activer($db, (int) $commande['id']);
            }
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('[Tchadok][portefeuille] achat impossible : ' . $e->getMessage());
            return ['succes' => false, 'erreur' => 'Paiement impossible pour le moment.'];
        }

        try {
            Factures::envoyer((int) $commande['id']);
        } catch (Throwable $e) {
            error_log('[Tchadok][portefeuille] confirmation non envoyee : ' . $e->getMessage());
        }
        return ['succes' => true, 'erreur' => null];
    }

    /**
     * Rembourse sur le portefeuille un achat qui avait ete regle avec lui
     * (SHOP-07). Idempotent.
     */
    public static function rembourserAchat(int $orderId): bool
    {
        $db = self::base();
        if (!$db) {
            return false;
        }
        $stmt = $db->prepare("SELECT user_id, -amount AS montant FROM wallet_transactions WHERE order_id = ? AND type = 'purchase' LIMIT 1");
        $stmt->execute([$orderId]);
        $achat = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$achat) {
            return false;
        }

        $db->beginTransaction();
        try {
            self::mouvement($db, (int) $achat['user_id'], 'refund', (float) $achat['montant'], $orderId, 'remboursement:' . $orderId, null, null);
            $db->commit();
            return true;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('[Tchadok][portefeuille] remboursement impossible : ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Correction par l'equipe finance, motivee et tracee.
     */
    public static function ajuster(int $userId, float $montant, string $motif, ?int $auteur): bool
    {
        $motif = trim($motif);
        $db = self::base();
        if (!$db || $montant == 0.0 || mb_strlen($motif) < 5) {
            return false;
        }
        $db->beginTransaction();
        try {
            $ok = self::mouvement($db, $userId, 'adjustment', $montant, null, 'ajustement:' . bin2hex(random_bytes(8)), $motif, $auteur);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return false;
        }
        JournalAudit::enregistrer('portefeuille.ajuste', [
            'cible_type' => 'portefeuille', 'cible_id' => $userId, 'apres' => ['montant' => $montant], 'raison' => $motif, 'acteur' => $auteur,
        ]);
        return $ok;
    }

    /**
     * Compare le cache au journal. Tableau vide = tout concorde.
     *
     * @return array<int,array{user_id:int, cache:float, journal:float}>
     */
    public static function verifier(?int $userId = null): array
    {
        $db = self::base();
        if (!$db) {
            return [];
        }
        $sql = 'SELECT u.id, u.wallet_balance AS cache, COALESCE(SUM(w.amount), 0) AS journal
                  FROM users u LEFT JOIN wallet_transactions w ON w.user_id = u.id'
            . ($userId !== null ? ' WHERE u.id = ' . (int) $userId : '')
            . ' GROUP BY u.id HAVING ABS(cache - journal) > 0.001';
        $ecarts = [];
        foreach ($db->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $l) {
            $ecarts[] = ['user_id' => (int) $l['id'], 'cache' => (float) $l['cache'], 'journal' => (float) $l['journal']];
        }
        return $ecarts;
    }

    /**
     * Un mouvement, dans la transaction de l'appelant. Verrouille la ligne du
     * membre, refuse un solde negatif, ignore un doublon de reference.
     */
    private static function mouvement(PDO $db, int $userId, string $type, float $montant, ?int $orderId, string $reference, ?string $note, ?int $auteur): bool
    {
        $stmt = $db->prepare('SELECT 1 FROM wallet_transactions WHERE reference = ?');
        $stmt->execute([$reference]);
        if ($stmt->fetchColumn()) {
            return false;
        }

        $stmt = $db->prepare('SELECT wallet_balance FROM users WHERE id = ? FOR UPDATE');
        $stmt->execute([$userId]);
        $nouveau = round((float) $stmt->fetchColumn() + $montant, 2);
        if ($nouveau < 0) {
            throw new RuntimeException('Solde insuffisant.');
        }

        $db->prepare(
            'INSERT INTO wallet_transactions (user_id, type, amount, balance_after, order_id, reference, note, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$userId, $type, $montant, $nouveau, $orderId, $reference, $note !== null ? mb_substr($note, 0, 300) : null, $auteur]);
        $db->prepare('UPDATE users SET wallet_balance = ? WHERE id = ?')->execute([$nouveau, $userId]);

        return true;
    }

    private static function base(): ?PDO
    {
        return TchadokDatabase::getInstance()->getConnection() ?: null;
    }
}
