<?php
/**
 * Contrat de distribution (PAYOUT-05).
 *
 * Mecanisme seulement : le TEXTE du contrat est a fournir par la direction et
 * son conseil. Il se publie en ligne de commande
 * (scripts/versements.php contrat-publier), une fois, et ne se modifie plus
 * (declencheur) : une evolution est une nouvelle version, que chaque artiste
 * doit accepter a nouveau.
 *
 * Tant qu'aucune version n'est publiee, rien n'est exige (developpement
 * local). En production, l'absence de contrat publie est signalee par
 * scripts/versements.php contrat-etat.
 *
 * L'acceptation garde l'empreinte SHA-256 du texte accepte, l'adresse et le
 * navigateur : elle est opposable, et immuable.
 */

declare(strict_types=1);

final class Contrats
{
    /** Version en vigueur, ou null si aucune n'est publiee. */
    public static function courant(): ?array
    {
        $db = TchadokDatabase::getInstance()->getConnection();
        if (!$db) {
            return null;
        }
        $ligne = $db->query(
            'SELECT * FROM distribution_contracts WHERE published_at IS NOT NULL AND published_at <= NOW() AND retired_at IS NULL
              ORDER BY published_at DESC, id DESC LIMIT 1'
        )->fetch(PDO::FETCH_ASSOC);
        return $ligne ?: null;
    }

    /** L'artiste doit-il accepter une version avant de publier ou d'etre paye ? */
    public static function acceptationRequise(int $artistId): bool
    {
        $contrat = self::courant();
        return $contrat !== null && !self::aAccepte($artistId, (int) $contrat['id']);
    }

    public static function aAccepte(int $artistId, int $contractId): bool
    {
        $db = TchadokDatabase::getInstance()->getConnection();
        $stmt = $db->prepare('SELECT 1 FROM contract_acceptances WHERE artist_id = ? AND contract_id = ?');
        $stmt->execute([$artistId, $contractId]);
        return (bool) $stmt->fetchColumn();
    }

    public static function accepter(int $artistId, int $contractId, int $userId): bool
    {
        $db = TchadokDatabase::getInstance()->getConnection();
        $stmt = $db->prepare('SELECT body FROM distribution_contracts WHERE id = ? AND published_at IS NOT NULL AND retired_at IS NULL');
        $stmt->execute([$contractId]);
        $texte = $stmt->fetchColumn();
        if ($texte === false || self::aAccepte($artistId, $contractId)) {
            return false;
        }
        $db->prepare(
            'INSERT INTO contract_acceptances (artist_id, contract_id, accepted_by, ip_address, user_agent, body_sha256) VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([
            $artistId, $contractId, $userId,
            function_exists('clientIp') ? clientIp() : null,
            substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'ligne de commande'), 0, 255),
            hash('sha256', (string) $texte),
        ]);
        return true;
    }

    /** @return array<int,array<string,mixed>> acceptations d'un artiste */
    public static function acceptations(int $artistId): array
    {
        $db = TchadokDatabase::getInstance()->getConnection();
        $stmt = $db->prepare(
            'SELECT a.accepted_at, c.version, c.title FROM contract_acceptances a
               JOIN distribution_contracts c ON c.id = a.contract_id WHERE a.artist_id = ? ORDER BY a.accepted_at DESC'
        );
        $stmt->execute([$artistId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
