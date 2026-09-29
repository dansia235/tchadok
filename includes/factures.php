<?php
/**
 * Factures (SHOP-03).
 *
 * Le numero est attribue a l'encaissement, sous verrou, sans trou
 * (Commandes::marquerPayee, DATA-05) : FAC-2026-000123. La facture se
 * reconstruit a tout moment depuis la commande, dont les lignes figent prix et
 * commission : elle reste donc identique et consultable apres coup,
 * y compris si le compte a ete anonymise (DATA-06).
 *
 * Mentions de l'editeur : variables FACTURE_* du fichier d'environnement.
 * Elles sont a completer par la direction (raison sociale, adresse, RCCM,
 * NIF, regime de TVA) : la plateforme ne les invente pas.
 */

declare(strict_types=1);

final class Factures
{
    /**
     * @return array<string,mixed>|null null si la commande n'est pas facturee
     */
    public static function donnees(int $orderId): ?array
    {
        $db = TchadokDatabase::getInstance()->getConnection();
        if (!$db) {
            return null;
        }

        $stmt = $db->prepare(
            'SELECT o.*, u.first_name, u.last_name, u.email, u.anonymized_at
               FROM orders o JOIN users u ON u.id = o.user_id
              WHERE o.id = ? AND o.invoice_number IS NOT NULL'
        );
        $stmt->execute([$orderId]);
        $commande = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$commande) {
            return null;
        }

        $stmt = $db->prepare('SELECT oi.*, a.stage_name FROM order_items oi LEFT JOIN artists a ON a.id = oi.artist_id WHERE oi.order_id = ? ORDER BY oi.id');
        $stmt->execute([$orderId]);
        $lignes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Le reglement : la tentative qui a encaisse la commande.
        $stmt = $db->prepare("SELECT gateway, gateway_ref, amount, currency, exchange_rate, completed_at FROM payment_intents WHERE order_id = ? AND gateway_ref = ? LIMIT 1");
        $stmt->execute([$orderId, (string) $commande['gateway_ref']]);
        $reglement = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        return [
            'commande'  => $commande,
            'lignes'    => $lignes,
            'reglement' => $reglement,
            'editeur'   => [
                'nom'          => (string) EnvLoader::get('FACTURE_EDITEUR', 'Tchadok'),
                'adresse'      => (string) EnvLoader::get('FACTURE_ADRESSE', 'N\'Djamena, Tchad'),
                'identifiants' => (string) EnvLoader::get('FACTURE_IDENTIFIANTS', ''),
                'tva'          => (string) EnvLoader::get('FACTURE_TVA_MENTION', 'TVA non applicable'),
                'contact'      => (string) EnvLoader::get('MAIL_FROM_ADDRESS', ''),
            ],
        ];
    }

    public static function libelleMoyen(?string $code): string
    {
        return [
            'airtel_money' => 'Airtel Money',
            'moov_money'   => 'Moov Money',
            'visa'         => 'Carte VISA',
            'gimac'        => 'GIMAC',
            'wallet'       => 'Portefeuille Tchadok',
        ][(string) $code] ?? (string) $code;
    }

    /**
     * Previent le client que son paiement est confirme, avec le lien de sa
     * facture. Appele APRES la validation de la transaction : un message ne
     * part jamais pour un encaissement annule.
     */
    public static function envoyer(int $orderId): bool
    {
        $f = self::donnees($orderId);
        if ($f === null || empty($f['commande']['email']) || $f['commande']['anonymized_at'] !== null) {
            return false;
        }
        $c = $f['commande'];
        $base = rtrim((string) EnvLoader::get('SITE_URL', ''), '/');
        $e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        $lignes = '';
        foreach ($f['lignes'] as $l) {
            $lignes .= '<li>' . $e($l['label']) . ' — ' . $e(number_format((float) $l['unit_price'], 0, ',', ' ')) . ' FCFA</li>';
        }

        $corps = '<p>Bonjour ' . $e($c['first_name']) . ',</p>'
            . '<p>Votre paiement de <strong>' . $e(number_format((float) $c['total'], 0, ',', ' ')) . ' FCFA</strong> a bien ete recu. Merci de soutenir la musique tchadienne !</p>'
            . '<ul>' . $lignes . '</ul>'
            . '<p>Vos achats sont disponibles dans <a href="' . $e($base . '/bibliotheque.php') . '">votre bibliotheque</a>.</p>'
            . '<p>Facture ' . $e($c['invoice_number']) . ' : <a href="' . $e($base . '/facture.php?commande=' . rawurlencode((string) $c['reference'])) . '">consulter</a>.</p>';

        return (bool) sendEmail((string) $c['email'], 'Tchadok - paiement confirme, facture ' . $c['invoice_number'], $corps);
    }
}
