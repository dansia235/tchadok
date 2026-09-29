<?php
/**
 * Enregistrement des ecoutes (STAT-02), selon la definition de
 * docs/methodologie/ecoute-comptabilisee.md (STAT-01).
 *
 *   1. api/track.php (acces complet) appelle Ecoutes::ouvrir() : un jeton
 *      d'ecoute, a usage unique, lie au titre et a l'auditeur.
 *   2. Apres 30 s de lecture (ou la lecture complete d'un titre plus court),
 *      le lecteur envoie le jeton a api/stream.php, qui appelle
 *      Ecoutes::enregistrer().
 *
 * Le serveur decide : le seuil se mesure depuis l'emission du jeton, le jeton
 * ne sert qu'une fois, l'auditeur qui le presente doit etre celui a qui il a
 * ete remis, une meme ecoute ne compte qu'une fois par heure. La duree
 * annoncee par le navigateur ne peut que REDUIRE ce qui est compte, jamais
 * l'augmenter. Pays et ville ne viennent jamais du navigateur.
 *
 * Ce journal est BRUT : la certification anti-fraude (STAT-03) decide ensuite
 * de ce qui compte pour les classements et la remuneration.
 */

declare(strict_types=1);

final class Ecoutes
{
    /** Lecture effective minimale, en secondes (STAT-01). */
    public const DUREE_MINIMALE = 30;

    /** Une ecoute par auditeur et par titre, par fenetre de 60 minutes. */
    public const FENETRE_DEDUPLICATION = 3600;

    /** Duree de vie d'un jeton non utilise. */
    private const VIE_JETON = 6 * 3600;

    /** Tolerance d'horloge entre le lecteur et le serveur, en secondes. */
    private const TOLERANCE = 2;

    /**
     * Emet un jeton d'ecoute pour un titre publie. A n'appeler que lorsque
     * l'acces COMPLET est accorde : un extrait n'est pas une ecoute.
     *
     * @return array{jeton:string, seuil:int}|null
     */
    public static function ouvrir(int $trackId, ?int $userId, string $source = 'web'): ?array
    {
        $db = self::base();
        if (!$db) {
            return null;
        }
        $titre = self::titre($db, $trackId);
        if ($titre === null) {
            return null;
        }
        $jeton = bin2hex(random_bytes(32));
        try {
            $db->prepare(
                'INSERT INTO listening_sessions (token_hash, track_id, user_id, listener_key, source, expires_at)
                 VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))'
            )->execute([hash('sha256', $jeton), $trackId, $userId, self::cleAuditeur($userId), self::source($source), self::VIE_JETON]);
        } catch (Throwable $e) {
            error_log('[Tchadok][ecoutes] jeton non emis : ' . $e->getMessage());
            return null;
        }
        return ['jeton' => $jeton, 'seuil' => self::seuil((int) $titre['duration'])];
    }

    /**
     * Enregistre l'ecoute presentee avec son jeton.
     *
     * @return array{code:int, comptee:bool, motif:string, message:string, stream_id:?int}
     */
    public static function enregistrer(string $jeton, int $dureeAnnoncee, ?int $userId): array
    {
        $reponse = static fn (int $code, bool $comptee, string $motif, string $message, ?int $id = null): array
            => ['code' => $code, 'comptee' => $comptee, 'motif' => $motif, 'message' => $message, 'stream_id' => $id];

        if (!preg_match('/^[a-f0-9]{64}$/', $jeton)) {
            return $reponse(400, false, 'jeton_absent', 'Jeton d\'ecoute requis.');
        }
        $db = self::base();
        if (!$db) {
            return $reponse(503, false, 'indisponible', 'Service momentanement indisponible.');
        }

        $stmt = $db->prepare(
            'SELECT s.*, TIMESTAMPDIFF(MICROSECOND, s.issued_at, NOW(3)) / 1000000 AS ecoule, s.expires_at < NOW() AS expire
               FROM listening_sessions s WHERE s.token_hash = ?'
        );
        $stmt->execute([hash('sha256', $jeton)]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$session) {
            return $reponse(403, false, 'jeton_inconnu', 'Jeton d\'ecoute invalide.');
        }
        // L'auditeur qui presente le jeton est celui a qui il a ete remis :
        // un jeton recupere ailleurs ne sert a rien.
        if ((int) ($session['user_id'] ?? 0) !== (int) ($userId ?? 0) || $session['listener_key'] !== self::cleAuditeur($userId)) {
            return $reponse(403, false, 'auditeur_different', 'Jeton d\'ecoute invalide.');
        }
        if ($session['consumed_at'] !== null) {
            return $reponse(409, false, 'jeton_utilise', 'Cette ecoute a deja ete transmise.');
        }
        if ((int) $session['expire'] === 1) {
            return $reponse(410, false, 'jeton_expire', 'Jeton d\'ecoute expire.');
        }

        $titre = self::titre($db, (int) $session['track_id']);
        $seuil = self::seuil((int) ($titre['duration'] ?? 0));
        $ecoule = (float) $session['ecoule'];
        // Duree retenue : la plus petite de l'annoncee, du temps reellement
        // ecoule depuis l'emission du jeton, et de la duree du titre.
        $dureeTitre = (int) ($titre['duration'] ?? 0);
        $duree = (int) floor(min(max(0, $dureeAnnoncee), $ecoule + self::TOLERANCE, $dureeTitre > 0 ? $dureeTitre : PHP_INT_MAX));

        $db->beginTransaction();
        try {
            // Consommation atomique : deux envois simultanes du meme jeton, un
            // seul passe.
            $stmt = $db->prepare('UPDATE listening_sessions SET consumed_at = NOW() WHERE id = ? AND consumed_at IS NULL');
            $stmt->execute([$session['id']]);
            if ($stmt->rowCount() !== 1) {
                $db->rollBack();
                return $reponse(409, false, 'jeton_utilise', 'Cette ecoute a deja ete transmise.');
            }
            $motif = null;
            if ($titre === null) {
                $motif = 'titre_indisponible';
            } elseif ($ecoule + self::TOLERANCE < $seuil || $dureeAnnoncee < $seuil) {
                $motif = 'trop_courte';
            } else {
                $stmt = $db->prepare(
                    'SELECT 1 FROM streams WHERE listener_key = ? AND track_id = ? AND created_at > NOW() - INTERVAL ? SECOND LIMIT 1'
                );
                $stmt->execute([$session['listener_key'], $session['track_id'], self::FENETRE_DEDUPLICATION]);
                if ($stmt->fetchColumn()) {
                    $motif = 'deja_comptee';
                }
            }
            if ($motif !== null) {
                $db->prepare('UPDATE listening_sessions SET outcome = ? WHERE id = ?')->execute([$motif, $session['id']]);
                $db->commit();
                $message = ['trop_courte' => 'Ecoute trop courte : elle n\'est pas comptee.', 'deja_comptee' => 'Ecoute deja comptee dans l\'heure.',
                    'titre_indisponible' => 'Titre indisponible.'][$motif];
                return $reponse(200, false, $motif, $message);
            }

            $db->prepare(
                'INSERT INTO streams (user_id, listener_key, track_id, artist_id, ip_address, user_agent, country, city, duration_played, completed, source, session_id, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NULL, NULL, ?, ?, ?, ?, NOW())'
            )->execute([
                $userId, $session['listener_key'], $session['track_id'], $titre['artist_id'],
                function_exists('clientIp') ? clientIp() : null, mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
                $duree, (int) ((int) $titre['duration'] > 0 && $duree >= (int) round((int) $titre['duration'] * 0.6)),
                $session['source'], $session['id'],
            ]);
            $streamId = (int) $db->lastInsertId();
            $db->prepare("UPDATE listening_sessions SET outcome = 'comptee', stream_id = ? WHERE id = ?")->execute([$streamId, $session['id']]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
        return $reponse(201, true, 'comptee', 'Ecoute enregistree.', $streamId);
    }

    /** Seuil d'une ecoute : 30 s, ou la duree du titre s'il est plus court. */
    public static function seuil(int $dureeTitre): int
    {
        return $dureeTitre > 0 ? min(self::DUREE_MINIMALE, $dureeTitre) : self::DUREE_MINIMALE;
    }

    /**
     * Identifiant de deduplication. Compte : « u:<id> ». Visiteur : empreinte
     * de l'IP tronquee (/24 ou /48) et du navigateur, salee par jour --
     * reconnaissable une journee, jamais d'un jour a l'autre.
     */
    public static function cleAuditeur(?int $userId): string
    {
        if ($userId !== null && $userId > 0) {
            return 'u:' . $userId;
        }
        $ip = function_exists('clientIp') ? (string) clientIp() : (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $binaire = @inet_pton($ip);
        if ($binaire !== false && strlen($binaire) === 4) {
            $ip = inet_ntop(substr($binaire, 0, 3) . "\0");
        } elseif ($binaire !== false && strlen($binaire) === 16) {
            $ip = inet_ntop(substr($binaire, 0, 6) . str_repeat("\0", 10));
        }
        $sel = hash_hmac('sha256', 'ecoutes:' . date('Y-m-d'), (string) env('APP_KEY', 'tchadok'));
        return 'a:' . substr(hash_hmac('sha256', $ip . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? ''), $sel), 0, 40);
    }

    /** Supprime les jetons expires depuis plus d'un jour (tache de nuit). */
    public static function purgerJetons(): int
    {
        $db = self::base();
        return $db ? (int) $db->exec('DELETE FROM listening_sessions WHERE expires_at < NOW() - INTERVAL 1 DAY AND stream_id IS NULL') : 0;
    }

    private static function titre(PDO $db, int $trackId): ?array
    {
        $stmt = $db->prepare("SELECT id, artist_id, duration FROM tracks WHERE id = ? AND status = 'approved' AND deleted_at IS NULL");
        $stmt->execute([$trackId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private static function source(string $source): string
    {
        return in_array($source, ['web', 'mobile', 'radio'], true) ? $source : 'web';
    }

    private static function base(): ?PDO
    {
        try {
            return TchadokDatabase::getInstance()->getConnection() ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }
}
