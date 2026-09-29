<?php
/**
 * Signalements (MOD-03).
 *
 * Categories : droit d'auteur, contenu inapproprie, spam, faux profil. Les
 * revendications de droits passent EN TETE de file (priorite 1).
 *
 * DROIT D'AUTEUR
 *   Retrait provisoire IMMEDIAT du catalogue public (statut « offline », sans
 *   attendre un moderateur), artiste prevenu, delai de reponse contradictoire
 *   de 10 jours (contre-notification), puis decision motivee :
 *     maintenu  le contenu est remis en ligne (revendication non fondee) ;
 *     retire    le contenu reste hors ligne ;
 *     rejete    signalement abusif ou incomplet, contenu remis en ligne.
 *   Procedure : docs/moderation/contre-notification.md.
 *
 * Chaque decision est motivee (contrainte en base) et journalisee.
 */

declare(strict_types=1);

final class Signalements
{
    public const CATEGORIES = [
        'droit_auteur' => ['libelle' => 'Atteinte au droit d\'auteur', 'priorite' => 1],
        'faux_profil'  => ['libelle' => 'Faux profil / usurpation', 'priorite' => 2],
        'inapproprie'  => ['libelle' => 'Contenu inapproprie', 'priorite' => 2],
        'spam'         => ['libelle' => 'Spam', 'priorite' => 3],
    ];

    public const DELAI_REPONSE_JOURS = 10;

    /** @return array{succes:bool, message:string, id:?int} */
    public static function signaler(int $auteur, string $type, int $id, string $categorie, string $description, string $nom = '', string $contact = ''): array
    {
        $refus = static fn (string $m): array => ['succes' => false, 'message' => $m, 'id' => null];
        if (!in_array($type, ['track', 'release', 'artist'], true) || !isset(self::CATEGORIES[$categorie])) {
            return $refus('Signalement invalide.');
        }
        $description = trim($description);
        if (mb_strlen($description) < 20) {
            return $refus('Decrivez le probleme (20 caracteres au moins).');
        }
        if ($categorie === 'droit_auteur' && (mb_strlen(trim($nom)) < 3 || !filter_var(trim($contact), FILTER_VALIDATE_EMAIL))) {
            return $refus('Une revendication de droits indique le nom du titulaire et une adresse e-mail de contact.');
        }
        $objet = self::objet($type, $id);
        if ($objet === null) {
            return $refus('Contenu introuvable.');
        }
        $db = self::base();
        $doublon = $db->prepare("SELECT 1 FROM reports WHERE reporter_id = ? AND reported_type = ? AND reported_id = ? AND status IN ('pending', 'reviewing')");
        $doublon->execute([$auteur, $type, $id]);
        if ($doublon->fetchColumn()) {
            return $refus('Vous avez deja signale ce contenu ; il est en cours d\'examen.');
        }
        $db->prepare(
            'INSERT INTO reports (reporter_id, reported_type, reported_id, category, priority, reason, description, claimant_name, claimant_contact)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$auteur, $type, $id, $categorie, self::CATEGORIES[$categorie]['priorite'], self::CATEGORIES[$categorie]['libelle'],
            mb_substr($description, 0, 5000), mb_substr(trim($nom), 0, 150) ?: null, mb_substr(trim($contact), 0, 190) ?: null]);
        $reportId = (int) $db->lastInsertId();

        $message = 'Signalement enregistre : notre equipe l\'examine.';
        if ($categorie === 'droit_auteur' && $type !== 'artist' && $objet['status'] === 'approved') {
            if (Moderation::systeme($type, $id, 'offline', 'Retrait provisoire : revendication de droits n°' . $reportId)) {
                $db->prepare("UPDATE reports SET status = 'reviewing', takedown_at = NOW(), response_deadline = DATE_ADD(NOW(), INTERVAL ? DAY) WHERE id = ?")
                   ->execute([self::DELAI_REPONSE_JOURS, $reportId]);
                JournalAudit::enregistrer('signalement.retrait', ['cible_type' => $type === 'release' ? 'sortie' : 'titre', 'cible_id' => $id, 'apres' => ['signalement' => $reportId], 'raison' => 'revendication de droits']);
                self::prevenirArtiste($objet, 'Votre contenu « ' . $objet['titre'] . ' » fait l\'objet d\'une revendication de droits : il est retire provisoirement du catalogue. Vous pouvez y repondre sous '
                    . self::DELAI_REPONSE_JOURS . ' jours depuis votre espace : ' . SITE_URL . '/artiste-signalements.php');
                $message = 'Revendication enregistree : le contenu est retire provisoirement du catalogue pendant l\'examen.';
            }
        }
        return ['succes' => true, 'message' => $message, 'id' => $reportId];
    }

    /** Contre-notification de l'artiste concerne. @return array{succes:bool, message:string} */
    public static function repondre(int $reportId, int $userId, string $texte): array
    {
        $texte = trim($texte);
        $r = self::signalement($reportId);
        if ($r === null || !in_array($r['status'], ['pending', 'reviewing'], true)) {
            return ['succes' => false, 'message' => 'Signalement introuvable ou deja tranche.'];
        }
        $objet = self::objet((string) $r['reported_type'], (int) $r['reported_id']);
        if ($objet === null || (int) $objet['user_id'] !== $userId) {
            return ['succes' => false, 'message' => 'Ce signalement ne concerne pas vos contenus.'];
        }
        if (mb_strlen($texte) < 30) {
            return ['succes' => false, 'message' => 'Expliquez votre position et vos justificatifs (30 caracteres au moins).'];
        }
        self::base()->prepare('UPDATE reports SET counter_notice = ?, counter_notice_at = NOW() WHERE id = ?')->execute([mb_substr($texte, 0, 5000), $reportId]);
        return ['succes' => true, 'message' => 'Reponse enregistree : elle sera examinee avant toute decision.'];
    }

    /** @return array{succes:bool, message:string} */
    public static function decider(int $reportId, string $decision, string $motif, int $auteur): array
    {
        $motif = trim($motif);
        $r = self::signalement($reportId);
        if ($r === null || !in_array($r['status'], ['pending', 'reviewing'], true)) {
            return ['succes' => false, 'message' => 'Signalement introuvable ou deja tranche.'];
        }
        if (!in_array($decision, ['maintenu', 'retire', 'rejete'], true) || mb_strlen($motif) < 5) {
            return ['succes' => false, 'message' => 'Decision et motif (5 caracteres au moins) obligatoires.'];
        }
        $type = (string) $r['reported_type'];
        $id = (int) $r['reported_id'];
        if ($type !== 'artist') {
            // Le contenu suit la decision : en ligne (maintenu, rejete) ou retire.
            $objet = self::objet($type, $id);
            if ($decision === 'retire' && $objet && $objet['status'] === 'approved') {
                Moderation::systeme($type, $id, 'offline', 'Retire apres signalement n°' . $reportId . ' : ' . $motif);
            } elseif ($decision !== 'retire' && $objet && $objet['status'] === 'offline' && $r['takedown_at'] !== null) {
                Moderation::systeme($type, $id, 'approved', 'Remis en ligne apres signalement n°' . $reportId . ' : ' . $motif);
            }
        }
        self::base()->prepare("UPDATE reports SET status = ?, decision = ?, decision_reason = ?, decided_by = ?, decided_at = NOW() WHERE id = ?")
            ->execute([$decision === 'rejete' ? 'rejected' : 'resolved', $decision, mb_substr($motif, 0, 1000), $auteur, $reportId]);
        JournalAudit::enregistrer('signalement.decide', ['cible_type' => 'signalement', 'cible_id' => $reportId, 'apres' => ['decision' => $decision], 'raison' => $motif, 'acteur' => $auteur]);
        $objet = self::objet($type, $id);
        if ($objet) {
            self::prevenirArtiste($objet, 'Decision sur le signalement visant « ' . $objet['titre'] . ' » : ' . ['maintenu' => 'contenu maintenu en ligne', 'retire' => 'contenu retire', 'rejete' => 'signalement rejete'][$decision] . '. Motif : ' . $motif);
        }
        return ['succes' => true, 'message' => 'Decision enregistree et notifiee.'];
    }

    /** @return array<int,array<string,mixed>> file de traitement : droits d'auteur d'abord */
    public static function file(): array
    {
        return self::base()->query(
            "SELECT r.*, u.username AS auteur, COALESCE(t.title, s.title, a.stage_name) AS titre
               FROM reports r JOIN users u ON u.id = r.reporter_id
               LEFT JOIN tracks t ON r.reported_type = 'track' AND t.id = r.reported_id
               LEFT JOIN releases s ON r.reported_type = 'release' AND s.id = r.reported_id
               LEFT JOIN artists a ON r.reported_type = 'artist' AND a.id = r.reported_id
              WHERE r.status IN ('pending', 'reviewing') ORDER BY r.priority, r.created_at"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int,array<string,mixed>> journal des decisions */
    public static function journal(int $limite = 50): array
    {
        return self::base()->query(
            "SELECT r.*, u.username AS decideur, COALESCE(t.title, s.title, a.stage_name) AS titre
               FROM reports r LEFT JOIN users u ON u.id = r.decided_by
               LEFT JOIN tracks t ON r.reported_type = 'track' AND t.id = r.reported_id
               LEFT JOIN releases s ON r.reported_type = 'release' AND s.id = r.reported_id
               LEFT JOIN artists a ON r.reported_type = 'artist' AND a.id = r.reported_id
              WHERE r.decided_at IS NOT NULL ORDER BY r.decided_at DESC LIMIT " . max(1, $limite)
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int,array<string,mixed>> signalements visant les contenus d'un artiste */
    public static function duArtiste(int $artistId): array
    {
        $stmt = self::base()->prepare(
            "SELECT r.*, COALESCE(t.title, s.title, a.stage_name) AS titre FROM reports r
               LEFT JOIN tracks t ON r.reported_type = 'track' AND t.id = r.reported_id
               LEFT JOIN releases s ON r.reported_type = 'release' AND s.id = r.reported_id
               LEFT JOIN artists a ON r.reported_type = 'artist' AND a.id = r.reported_id
              WHERE COALESCE(t.artist_id, s.artist_id, a.id) = ? ORDER BY r.created_at DESC"
        );
        $stmt->execute([$artistId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function signalement(int $id): ?array
    {
        $stmt = self::base()->prepare('SELECT * FROM reports WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private static function objet(string $type, int $id): ?array
    {
        $sql = match ($type) {
            'track'   => 'SELECT t.status, t.title AS titre, a.user_id, a.id AS artist_id FROM tracks t JOIN artists a ON a.id = t.artist_id WHERE t.id = ? AND t.deleted_at IS NULL',
            'release' => 'SELECT s.status, s.title AS titre, a.user_id, a.id AS artist_id FROM releases s JOIN artists a ON a.id = s.artist_id WHERE s.id = ? AND s.deleted_at IS NULL',
            'artist'  => "SELECT 'approved' AS status, a.stage_name AS titre, a.user_id, a.id AS artist_id FROM artists a WHERE a.id = ? AND a.deleted_at IS NULL",
            default   => null,
        };
        if ($sql === null) {
            return null;
        }
        $stmt = self::base()->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private static function prevenirArtiste(array $objet, string $texte): void
    {
        $stmt = self::base()->prepare('SELECT email, first_name FROM users WHERE id = ?');
        $stmt->execute([$objet['user_id']]);
        if ($u = $stmt->fetch(PDO::FETCH_ASSOC)) {
            sendEmail((string) $u['email'], 'Tchadok - signalement', '<p>Bonjour ' . htmlspecialchars((string) $u['first_name'], ENT_QUOTES, 'UTF-8') . ',</p><p>' . htmlspecialchars($texte, ENT_QUOTES, 'UTF-8') . '</p>');
        }
    }

    private static function base(): PDO
    {
        return TchadokDatabase::getInstance()->getConnection();
    }
}
