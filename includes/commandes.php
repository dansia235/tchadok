<?php
/**
 * Commandes, factures, droits d'acces (DATA-05).
 *
 * Les tables sont le socle ; cette classe porte les quelques regles qui ne
 * peuvent pas vivre dans le schema. Le tunnel de paiement lui-meme (LOT 5 et
 * LOT 6) s'appuiera dessus.
 *
 * TROIS REGLES QUI COMPTENT
 *
 *   1. LE PRIX EST FIGE A LA VENTE. `ajouterArticle()` recopie le prix et le
 *      taux de commission dans la ligne de commande. Un changement de tarif
 *      (DATA-04) ne doit jamais reecrire l'historique : l'artiste doit
 *      retrouver ce qu'on lui avait promis le jour de la vente.
 *
 *   2. UN CALLBACK SE REJOUE. Les operateurs mobile money renvoient plusieurs
 *      fois le meme evenement. `marquerPayee()` est donc idempotente : appelee
 *      dix fois avec la meme reference, elle encaisse une fois. La garantie
 *      finale est la cle unique `gateway_ref` en base, pas ce code -- un
 *      controle applicatif perd la course entre deux requetes simultanees.
 *
 *   3. LA FACTURE NE SAUTE PAS DE NUMERO. AUTO_INCREMENT saute des valeurs des
 *      qu'une transaction echoue ; la comptabilite exige une suite continue.
 *      Le numero vient donc d'un compteur verrouille (`SELECT ... FOR UPDATE`),
 *      et n'est attribue qu'au moment de l'encaissement -- une commande
 *      abandonnee ne consomme pas de numero.
 */

declare(strict_types=1);

final class Commandes
{
    public const STATUTS = [
        'cart', 'awaiting_payment', 'paid', 'failed', 'cancelled', 'expired', 'review', 'refunded', 'disputed',
    ];

    /**
     * Etats d'ou un encaissement automatique est exclu (PAY-02) : une commande
     * en revue attend une decision humaine, une commande remboursee ou
     * contestee ne se « repaie » pas par un callback tardif.
     */
    public const ETATS_FIGES = ['review', 'refunded', 'disputed'];

    /** Quota de telechargement par defaut d'un achat. */
    public const TELECHARGEMENTS_PAR_ACHAT = 5;

    /**
     * Cree (ou retrouve) le panier ouvert d'un membre.
     */
    public static function panier(int $userId): ?int
    {
        $db = self::base();
        if (!$db) {
            return null;
        }

        try {
            $stmt = $db->prepare("SELECT id FROM orders WHERE user_id = ? AND status = 'cart' ORDER BY id DESC LIMIT 1");
            $stmt->execute([$userId]);
            $existant = $stmt->fetchColumn();

            if ($existant !== false) {
                return (int) $existant;
            }

            $db->prepare(
                "INSERT INTO orders (reference, user_id, status, currency) VALUES (?, ?, 'cart', 'XAF')"
            )->execute([self::reference(), $userId]);

            return (int) $db->lastInsertId();
        } catch (Throwable $e) {
            error_log('[Tchadok][commandes] panier impossible : ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Ajoute un article, prix et commission FIGES a cet instant.
     *
     * @return array{succes:bool, erreurs:string[]}
     */
    public static function ajouterArticle(
        int $orderId,
        string $type,
        int $itemId,
        float $prix,
        ?int $artistId = null,
        ?string $libelle = null,
        ?string $format = null
    ): array {
        if (!in_array($type, ['track', 'release', 'subscription', 'wallet_topup'], true)) {
            return ['succes' => false, 'erreurs' => ['Type d\'article inconnu.']];
        }

        // SHOP-06 : un rechargement du portefeuille n'est pas une vente d'artiste :
        // ni commission, ni part artiste. La commission se prendra sur les
        // achats payes ensuite avec ce solde.
        if ($type === 'wallet_topup') {
            $taux = 0.0;
        } else {
            $portee = $type === 'subscription' ? 'subscription' : ($type === 'release' ? 'release' : 'track');
            $taux = Tarifs::tauxCommission($portee, $format);
        }
        $commission = round($prix * $taux / 100);

        $db = self::base();
        if (!$db) {
            return ['succes' => false, 'erreurs' => ['Base de donnees indisponible.']];
        }

        try {
            $db->prepare(
                'INSERT INTO order_items
                 (order_id, item_type, item_id, artist_id, label, unit_price, commission_rate, commission, artist_net)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE unit_price = VALUES(unit_price)'
            )->execute([$orderId, $type, $itemId, $artistId, $libelle, $prix, $taux, $commission, $prix - $commission]);

            self::recalculer($orderId);
        } catch (Throwable $e) {
            error_log('[Tchadok][commandes] ajout impossible : ' . $e->getMessage());
            return ['succes' => false, 'erreurs' => ['Ajout impossible pour le moment.']];
        }

        return ['succes' => true, 'erreurs' => []];
    }

    /**
     * Recalcule les totaux d'une commande depuis ses lignes.
     */
    public static function recalculer(int $orderId): void
    {
        $db = self::base();
        if (!$db) {
            return;
        }

        try {
            $db->prepare(
                'UPDATE orders o
                 SET o.subtotal = (SELECT COALESCE(SUM(unit_price * quantity), 0) FROM order_items WHERE order_id = o.id),
                     o.platform_fee = (SELECT COALESCE(SUM(commission), 0) FROM order_items WHERE order_id = o.id),
                     o.total = (SELECT COALESCE(SUM(unit_price * quantity), 0) FROM order_items WHERE order_id = o.id) + o.gateway_fee
                 WHERE o.id = ?'
            )->execute([$orderId]);
        } catch (Throwable $e) {
            error_log('[Tchadok][commandes] recalcul impossible : ' . $e->getMessage());
        }
    }

    /**
     * Encaisse une commande. Idempotente : un callback rejoue n'encaisse pas
     * deux fois, et ne renvoie pas d'erreur -- l'operateur attend un accuse de
     * reception, pas un refus.
     *
     * @return array{succes:bool, deja:bool, erreurs:string[]}
     */
    public static function marquerPayee(int $orderId, string $gatewayRef, string $moyen, float $fraisPasserelle = 0.0): array
    {
        $db = self::base();
        if (!$db) {
            return ['succes' => false, 'deja' => false, 'erreurs' => ['Base de donnees indisponible.']];
        }

        if (trim($gatewayRef) === '') {
            return ['succes' => false, 'deja' => false, 'erreurs' => ['Reference operateur manquante.']];
        }

        try {
            $stmt = $db->prepare('SELECT id, status, gateway_ref FROM orders WHERE id = ? LIMIT 1');
            $stmt->execute([$orderId]);
            $commande = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$commande) {
                return ['succes' => false, 'deja' => false, 'erreurs' => ['Commande introuvable.']];
            }

            // Deja encaissee avec cette meme reference : rien a faire.
            if ($commande['status'] === 'paid' && (string) $commande['gateway_ref'] === $gatewayRef) {
                return ['succes' => true, 'deja' => true, 'erreurs' => []];
            }

            if (in_array($commande['status'], self::ETATS_FIGES, true)) {
                return ['succes' => false, 'deja' => false, 'erreurs' => ['Commande en revue, remboursee ou contestee : encaissement automatique exclu.']];
            }

            // La meme reference ne peut pas servir deux commandes. La base le
            // refuse ; on le dit clairement plutot que de laisser filer une
            // erreur SQL.
            $stmt = $db->prepare('SELECT id FROM orders WHERE gateway_ref = ? AND id <> ? LIMIT 1');
            $stmt->execute([$gatewayRef, $orderId]);
            if ($stmt->fetchColumn() !== false) {
                return ['succes' => false, 'deja' => false, 'erreurs' => ['Cette reference operateur appartient a une autre commande.']];
            }

            // PAY-02 : la machine a etats appelle cette methode DANS sa propre
            // transaction, pour que tentative, commande, facture et droits
            // basculent ensemble. On n'ouvre (et ne valide) une transaction que
            // si l'appelant ne l'a pas deja fait.
            $proprietaire = !$db->inTransaction();
            if ($proprietaire) {
                $db->beginTransaction();
            }
            $numero = self::attribuerNumeroFacture($db, $orderId);
            $db->prepare(
                "UPDATE orders
                 SET status = 'paid', gateway_ref = ?, payment_method = ?, gateway_fee = ?,
                     paid_at = NOW(), invoice_number = COALESCE(invoice_number, ?)
                 WHERE id = ? AND status <> 'paid'"
            )->execute([$gatewayRef, $moyen, $fraisPasserelle, $numero, $orderId]);

            self::accorderDroits($orderId);

            if ($proprietaire) {
                $db->commit();
            }
        } catch (Throwable $e) {
            if ($proprietaire ?? false) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
            } elseif ($db->inTransaction()) {
                // L'appelant tient la transaction : c'est a lui d'annuler.
                throw $e;
            }
            error_log('[Tchadok][commandes] encaissement impossible : ' . $e->getMessage());
            return ['succes' => false, 'deja' => false, 'erreurs' => ['Encaissement impossible pour le moment.']];
        }

        return ['succes' => true, 'deja' => false, 'erreurs' => []];
    }

    /**
     * Ouvre les droits d'acces correspondant aux lignes d'une commande payee.
     *
     * Cle unique du droit (membre, article, source) : un rejeu ne double pas le
     * quota de telechargement.
     *
     * Un droit REVOQUE (achat conteste ou rembourse, PAY-07) est retabli par un
     * nouvel achat paye : avec un simple INSERT IGNORE, la ligne revoquee
     * bloquait la cle unique et le client payait sans obtenir l'acces. Un
     * droit actif, lui, n'est pas touche -- ni son quota, ni sa date.
     */
    public static function accorderDroits(int $orderId): int
    {
        $db = self::base();
        if (!$db) {
            return 0;
        }

        try {
            $stmt = $db->prepare(
                "INSERT INTO entitlements
                 (user_id, item_type, item_id, order_item_id, source, max_downloads, granted_at)
                 SELECT o.user_id, oi.item_type, oi.item_id, oi.id, 'purchase', ?, NOW()
                 FROM order_items oi
                 JOIN orders o ON o.id = oi.order_id
                 WHERE oi.order_id = ? AND o.status = 'paid' AND oi.item_type IN ('track', 'release')
                 ON DUPLICATE KEY UPDATE
                     order_item_id = IF(entitlements.revoked_at IS NULL, entitlements.order_item_id, VALUES(order_item_id)),
                     downloads_used = IF(entitlements.revoked_at IS NULL, entitlements.downloads_used, 0),
                     granted_at     = IF(entitlements.revoked_at IS NULL, entitlements.granted_at, NOW()),
                     revoked_at     = NULL"
            );
            $stmt->execute([self::TELECHARGEMENTS_PAR_ACHAT, $orderId]);
            $accordes = $stmt->rowCount();

            // SHOP-04 : l'achat d'une sortie donne aussi un droit PAR TITRE.
            // Tout le reste (lecture, bibliotheque, mise hors ligne) n'a alors
            // qu'une question a poser : « ce titre est-il a moi ? ». Un titre
            // deja achete a l'unite garde son propre droit : la contestation de
            // la sortie ne le retirera pas.
            $titres = $db->prepare(
                "INSERT INTO entitlements
                 (user_id, item_type, item_id, order_item_id, source, max_downloads, granted_at)
                 SELECT o.user_id, 'track', t.id, oi.id, 'purchase', ?, NOW()
                 FROM order_items oi
                 JOIN orders o ON o.id = oi.order_id
                 JOIN tracks t ON t.release_id = oi.item_id AND t.deleted_at IS NULL
                 WHERE oi.order_id = ? AND o.status = 'paid' AND oi.item_type = 'release'
                 ON DUPLICATE KEY UPDATE
                     order_item_id = IF(entitlements.revoked_at IS NULL, entitlements.order_item_id, VALUES(order_item_id)),
                     downloads_used = IF(entitlements.revoked_at IS NULL, entitlements.downloads_used, 0),
                     granted_at     = IF(entitlements.revoked_at IS NULL, entitlements.granted_at, NOW()),
                     revoked_at     = NULL"
            );
            $titres->execute([self::TELECHARGEMENTS_PAR_ACHAT, $orderId]);

            return $accordes + $titres->rowCount();
        } catch (Throwable $e) {
            error_log('[Tchadok][commandes] droits non accordes : ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Contestation d'un paiement par carte (chargeback, PAY-07).
     *
     * L'argent est repris par la banque du porteur : l'achat n'existe plus.
     * Les droits d'acces sont REVOQUES (revoked_at), pas supprimes -- la trace
     * de ce qui avait ete accorde reste, pour le litige comme pour la
     * comptabilite. La commande passe en `disputed`, ce qui l'exclut des
     * ventes versees a l'artiste (LOT 8).
     *
     * Idempotente. A appeler dans la transaction de l'appelant si elle existe.
     */
    public static function contester(int $orderId, string $motif): bool
    {
        $db = self::base();
        if (!$db) {
            return false;
        }

        $stmt = $db->prepare("SELECT status FROM orders WHERE id = ? LIMIT 1");
        $stmt->execute([$orderId]);
        $statut = $stmt->fetchColumn();
        if ($statut === false) {
            return false;
        }
        if ($statut === 'disputed') {
            return true;
        }

        $db->prepare(
            "UPDATE orders SET status = 'disputed', refund_reason = ?, refunded_at = NOW() WHERE id = ?"
        )->execute([mb_substr($motif, 0, 500), $orderId]);

        self::revoquerDroits($orderId);

        return true;
    }

    /**
     * Revoque (sans les supprimer) les droits ouverts par une commande :
     * contestation (PAY-07) ou remboursement (SHOP-07). Un titre achete par
     * une AUTRE commande garde son droit, rattache a cette autre ligne.
     */
    public static function revoquerDroits(int $orderId): int
    {
        $db = self::base();
        if (!$db) {
            return 0;
        }
        $stmt = $db->prepare(
            'UPDATE entitlements e
               JOIN order_items oi ON oi.id = e.order_item_id
                SET e.revoked_at = NOW()
              WHERE oi.order_id = ? AND e.revoked_at IS NULL'
        );
        $stmt->execute([$orderId]);

        // LOT 7 : un abonnement rembourse ou conteste s'arrete aussi.
        if (class_exists('Abonnements')) {
            Abonnements::revoquerPourCommande($db, $orderId);
        }
        return $stmt->rowCount();
    }

    /**
     * Le membre a-t-il le droit d'acceder a cet article ?
     */
    public static function aLeDroit(int $userId, string $type, int $itemId): bool
    {
        $db = self::base();
        if (!$db) {
            return false;
        }

        try {
            $stmt = $db->prepare(
                'SELECT 1 FROM entitlements
                  WHERE user_id = ? AND item_type = ? AND item_id = ?
                    AND revoked_at IS NULL
                    AND (expires_at IS NULL OR expires_at > NOW())
                  LIMIT 1'
            );
            $stmt->execute([$userId, $type, $itemId]);

            return (bool) $stmt->fetchColumn();
        } catch (Throwable $e) {
            error_log('[Tchadok][commandes] droit illisible : ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Consomme un telechargement. Renvoie false quand le quota est epuise --
     * le decompte se fait en base, en une seule requete, pour qu'aucun
     * telechargement simultane ne passe deux fois.
     */
    public static function consommerTelechargement(int $userId, string $type, int $itemId): bool
    {
        $db = self::base();
        if (!$db) {
            return false;
        }

        try {
            $stmt = $db->prepare(
                'UPDATE entitlements
                    SET downloads_used = downloads_used + 1
                  WHERE user_id = ? AND item_type = ? AND item_id = ?
                    AND revoked_at IS NULL
                    AND (expires_at IS NULL OR expires_at > NOW())
                    AND (max_downloads = 0 OR downloads_used < max_downloads)
                  LIMIT 1'
            );
            $stmt->execute([$userId, $type, $itemId]);

            return $stmt->rowCount() === 1;
        } catch (Throwable $e) {
            error_log('[Tchadok][commandes] quota non decremente : ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Inscrit un echange avec l'operateur. La table n'accepte que des ajouts :
     * deux declencheurs refusent UPDATE et DELETE.
     */
    public static function enregistrerEvenement(array $evenement): bool
    {
        $db = self::base();
        if (!$db) {
            return false;
        }

        try {
            $db->prepare(
                'INSERT INTO payment_events
                 (order_id, intent_id, gateway, direction, event_type, http_status, payload, signature, signature_valid, ip_address)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $evenement['order_id'] ?? null,
                $evenement['intent_id'] ?? null,
                (string) ($evenement['gateway'] ?? 'inconnu'),
                (string) ($evenement['direction'] ?? 'callback'),
                $evenement['event_type'] ?? null,
                $evenement['http_status'] ?? null,
                isset($evenement['payload']) && !is_string($evenement['payload'])
                    ? json_encode($evenement['payload'], JSON_UNESCAPED_UNICODE)
                    : ($evenement['payload'] ?? null),
                $evenement['signature'] ?? null,
                isset($evenement['signature_valid']) ? (int) (bool) $evenement['signature_valid'] : null,
                $evenement['ip_address'] ?? (function_exists('clientIp') ? clientIp() : null),
            ]);

            return true;
        } catch (Throwable $e) {
            error_log('[Tchadok][commandes] evenement non journalise : ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Reference lisible d'une commande : TCHK-2026-A7F3B2C1.
     *
     * Aleatoire, et non sequentielle : une reference devinable laisserait
     * enumerer les commandes des autres. La suite continue, elle, est portee
     * par le numero de FACTURE, qui n'est attribue qu'a l'encaissement.
     */
    public static function reference(): string
    {
        return sprintf('TCHK-%s-%s', date('Y'), strtoupper(bin2hex(random_bytes(4))));
    }

    /**
     * Numero de facture suivant, sans trou.
     *
     * Le compteur est verrouille le temps de la transaction : deux
     * encaissements simultanes attendent leur tour au lieu de se donner le
     * meme numero.
     */
    private static function attribuerNumeroFacture(PDO $db, int $orderId): string
    {
        $annee = (int) date('Y');

        $db->prepare('INSERT IGNORE INTO invoice_counters (year, last_number) VALUES (?, 0)')->execute([$annee]);

        $stmt = $db->prepare('SELECT last_number FROM invoice_counters WHERE year = ? FOR UPDATE');
        $stmt->execute([$annee]);
        $dernier = (int) $stmt->fetchColumn();
        $suivant = $dernier + 1;

        $db->prepare('UPDATE invoice_counters SET last_number = ? WHERE year = ?')->execute([$suivant, $annee]);

        return sprintf('FAC-%d-%06d', $annee, $suivant);
    }

    private static function base(): ?PDO
    {
        return TchadokDatabase::getInstance()->getConnection() ?: null;
    }
}
