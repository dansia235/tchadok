<?php
/**
 * Moderation du catalogue (MOD-01, MOD-02).
 *
 * MACHINE A ETATS (imposee aussi par la base, migration 0031)
 *
 *   draft --(artiste soumet)--> pending --(moderateur)--> approved --(admin)--> offline
 *                                  ^    \-> rejected --(artiste corrige)--^      |
 *                                  |                                             |
 *   pending --(artiste retire)--> draft            offline --(admin)--> approved
 *
 *   L'artiste n'ecrit JAMAIS « approved ». Le catalogue public ne lit que
 *   « approved ». Chaque transition est journalisee (content_transitions) avec
 *   son auteur, sa date et son motif ; un refus est toujours motive, et le
 *   motif est transmis a l'artiste.
 *
 * FILE DE MODERATION
 *   Une revue par soumission (moderation_reviews) : anciennete, affectation a
 *   un moderateur (pas de double revue), grille (qualite audio, metadonnees,
 *   droits, contenu explicite, pochette), decision : approuver, refuser,
 *   demander une correction. Indicateurs : volume, delai moyen, refus par
 *   motif. Une sortie entraine ses titres.
 */

declare(strict_types=1);

final class Moderation
{
    /** [role][depuis] => vers autorises. */
    private const DROITS = [
        'artiste'    => ['draft' => ['pending'], 'pending' => ['draft'], 'rejected' => ['pending']],
        'moderateur' => ['pending' => ['approved', 'rejected']],
        'admin'      => ['approved' => ['offline'], 'offline' => ['approved']],
    ];

    public const GRILLE = [
        'qualite_audio'   => 'Qualite audio correcte',
        'metadonnees'     => 'Metadonnees exactes (titre, artiste, genre, credits)',
        'droits'          => 'Droits declares plausibles',
        'explicite'       => 'Contenu explicite correctement signale',
        'pochette'        => 'Pochette conforme (qualite, sans texte promotionnel)',
    ];

    public const MOTIFS = [
        'qualite_audio' => 'Qualite audio insuffisante',
        'metadonnees'   => 'Metadonnees inexactes ou incompletes',
        'droits'        => 'Droits non etablis',
        'explicite'     => 'Contenu explicite non signale',
        'pochette'      => 'Pochette non conforme',
        'doublon'       => 'Doublon d\'un contenu existant',
        'autre'         => 'Autre',
    ];

    /**
     * Change le statut d'une sortie ou d'un titre, si le role y a droit.
     * Une sortie emmene ses titres.
     *
     * @return array{succes:bool, message:string}
     */
    public static function transition(string $type, int $id, string $vers, int $acteur, string $role, string $motif = ''): array
    {
        $refus = static fn (string $m): array => ['succes' => false, 'message' => $m];
        if (!in_array($type, ['track', 'release'], true) || !isset(self::DROITS[$role])) {
            return $refus('Demande invalide.');
        }
        $contenu = self::contenu($type, $id);
        if ($contenu === null) {
            return $refus('Contenu introuvable.');
        }
        $depuis = (string) $contenu['status'];
        if (!in_array($vers, self::DROITS[$role][$depuis] ?? [], true)) {
            return $refus(sprintf('Transition « %s -> %s » non autorisee.', $depuis, $vers));
        }
        if ($role === 'artiste' && (int) $contenu['artiste_user'] !== $acteur) {
            return $refus('Ce contenu ne vous appartient pas.');
        }
        $motif = trim($motif);
        if (in_array($vers, ['rejected', 'offline'], true) && mb_strlen($motif) < 5) {
            return $refus('Motif obligatoire (5 caracteres au moins) : il est transmis a l\'artiste.');
        }
        if ($type === 'release' && $vers === 'pending') {
            $erreurs = Sorties::validerPublication($id);
            if ($erreurs !== []) {
                return $refus(implode(' ', $erreurs));
            }
        }

        $db = self::base();
        $db->beginTransaction();
        try {
            self::changer($db, $type, $id, $depuis, $vers, $acteur, $motif);
            if ($type === 'release') {
                // Les titres suivent la sortie, s'ils peuvent faire la meme
                // transition (un titre deja refuse seul ne repasse pas en ligne).
                $stmt = $db->prepare('SELECT id, status FROM tracks WHERE release_id = ? AND deleted_at IS NULL');
                $stmt->execute([$id]);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $t) {
                    if ($t['status'] === $depuis) {
                        self::changer($db, 'track', (int) $t['id'], $depuis, $vers, $acteur, $motif);
                    }
                }
            }
            if ($vers === 'pending') {
                $db->prepare('INSERT INTO moderation_reviews (content_type, content_id, artist_id, submitted_at) VALUES (?, ?, ?, NOW())')
                   ->execute([$type, $id, $contenu['artist_id']]);
            }
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('[Tchadok][moderation] transition impossible : ' . $e->getMessage());
            return $refus('Transition refusee.');
        }
        JournalAudit::enregistrer(match ($vers) {
            'approved' => $depuis === 'offline' ? 'contenu.restaure' : 'contenu.approuve',
            'rejected' => 'contenu.rejete',
            'offline'  => 'contenu.supprime',
            default    => 'contenu.cree',
        }, ['cible_type' => $type === 'release' ? 'sortie' : 'titre', 'cible_id' => $id, 'avant' => ['statut' => $depuis], 'apres' => ['statut' => $vers], 'raison' => $motif ?: null, 'acteur' => $acteur]);
        return ['succes' => true, 'message' => self::messageTransition($vers)];
    }

    /**
     * Transition decidee par le systeme, sans acteur humain : retrait
     * provisoire sur revendication de droits (MOD-03) et remise en ligne.
     * Seules ces deux transitions sont possibles par ce chemin.
     */
    public static function systeme(string $type, int $id, string $vers, string $motif): bool
    {
        $contenu = self::contenu($type, $id);
        if ($contenu === null || !in_array([$contenu['status'], $vers], [['approved', 'offline'], ['offline', 'approved']], true)) {
            return false;
        }
        $db = self::base();
        $db->beginTransaction();
        try {
            self::changer($db, $type, $id, (string) $contenu['status'], $vers, null, $motif);
            if ($type === 'release') {
                $stmt = $db->prepare('SELECT id FROM tracks WHERE release_id = ? AND status = ? AND deleted_at IS NULL');
                $stmt->execute([$id, $contenu['status']]);
                foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $t) {
                    self::changer($db, 'track', (int) $t, (string) $contenu['status'], $vers, null, $motif);
                }
            }
            $db->commit();
            return true;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('[Tchadok][moderation] transition systeme impossible : ' . $e->getMessage());
            return false;
        }
    }

    // -----------------------------------------------------------------
    // File de moderation (MOD-02)
    // -----------------------------------------------------------------

    /** @return array<int,array<string,mixed>> revues ouvertes, plus anciennes d'abord */
    public static function fileAttente(): array
    {
        return self::base()->query(
            "SELECT r.*, a.stage_name, u.username AS moderateur,
                    COALESCE(s.title, t.title) AS titre, s.format, COALESCE(gs.name, gt.name) AS genre,
                    TIMESTAMPDIFF(HOUR, r.submitted_at, NOW()) AS anciennete_heures,
                    (SELECT COUNT(*) FROM tracks tt WHERE tt.release_id = s.id) AS titres,
                    (SELECT COUNT(*) FROM moderation_reviews h WHERE h.artist_id = r.artist_id AND h.decision = 'approved') AS approuves,
                    (SELECT COUNT(*) FROM moderation_reviews h WHERE h.artist_id = r.artist_id AND h.decision IN ('rejected', 'correction')) AS refuses
               FROM moderation_reviews r
               JOIN artists a ON a.id = r.artist_id
               LEFT JOIN users u ON u.id = r.assigned_to
               LEFT JOIN releases s ON r.content_type = 'release' AND s.id = r.content_id
               LEFT JOIN tracks t ON r.content_type = 'track' AND t.id = r.content_id
               LEFT JOIN genres gs ON gs.id = s.genre_id LEFT JOIN genres gt ON gt.id = t.genre_id
              WHERE r.decision IS NULL
              ORDER BY r.submitted_at"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array{succes:bool, message:string} */
    public static function assigner(int $reviewId, int $moderateur): array
    {
        // Affectation seulement si personne ne l'a prise : pas de double revue.
        $stmt = self::base()->prepare('UPDATE moderation_reviews SET assigned_to = ?, assigned_at = NOW() WHERE id = ? AND decision IS NULL AND (assigned_to IS NULL OR assigned_to = ?)');
        $stmt->execute([$moderateur, $reviewId, $moderateur]);
        return $stmt->rowCount() === 1 || self::revue($reviewId)['assigned_to'] == $moderateur
            ? ['succes' => true, 'message' => 'Dossier pris en charge.']
            : ['succes' => false, 'message' => 'Dossier deja pris en charge par un autre moderateur.'];
    }

    /**
     * Decision sur une revue : approuver, refuser, demander une correction.
     *
     * @param string[] $grille points de la grille coches
     * @return array{succes:bool, message:string}
     */
    public static function decider(int $reviewId, string $decision, string $motifCode, string $motif, array $grille, int $moderateur): array
    {
        $r = self::revue($reviewId);
        if ($r === null || $r['decision'] !== null) {
            return ['succes' => false, 'message' => 'Revue introuvable ou deja tranchee.'];
        }
        if ($r['assigned_to'] !== null && (int) $r['assigned_to'] !== $moderateur) {
            return ['succes' => false, 'message' => 'Ce dossier est affecte a un autre moderateur.'];
        }
        if (!in_array($decision, ['approved', 'rejected', 'correction'], true)) {
            return ['succes' => false, 'message' => 'Decision inconnue.'];
        }
        $grille = array_values(array_intersect(array_keys(self::GRILLE), $grille));
        if ($decision === 'approved' && count($grille) !== count(self::GRILLE)) {
            return ['succes' => false, 'message' => 'Approbation : tous les points de la grille doivent etre verifies.'];
        }
        if ($decision !== 'approved' && (!isset(self::MOTIFS[$motifCode]) || mb_strlen(trim($motif)) < 5)) {
            return ['succes' => false, 'message' => 'Refus ou correction : motif et explication (5 caracteres au moins) obligatoires.'];
        }
        $explication = $decision === 'approved' ? '' : (self::MOTIFS[$motifCode] . ' : ' . trim($motif));
        $t = self::transition((string) $r['content_type'], (int) $r['content_id'], $decision === 'approved' ? 'approved' : 'rejected', $moderateur, 'moderateur', $explication);
        if (!$t['succes']) {
            return $t;
        }
        self::base()->prepare(
            'UPDATE moderation_reviews SET decision = ?, reason_code = ?, reason = ?, checklist = ?, decided_by = ?, decided_at = NOW(),
                    assigned_to = COALESCE(assigned_to, ?), assigned_at = COALESCE(assigned_at, NOW()) WHERE id = ?'
        )->execute([$decision, $decision === 'approved' ? null : $motifCode, $explication ?: null, implode(',', $grille), $moderateur, $moderateur, $reviewId]);
        self::prevenirArtiste($r, $decision, $explication);
        return ['succes' => true, 'message' => ['approved' => 'Contenu approuve et publie.', 'rejected' => 'Contenu refuse, l\'artiste est prevenu.', 'correction' => 'Correction demandee a l\'artiste.'][$decision]];
    }

    /** @return array{en_attente:int, delai_moyen_heures:?float, decisions:int, refus_par_motif:array<string,int>} */
    public static function indicateurs(int $jours = 30): array
    {
        $db = self::base();
        $enAttente = (int) $db->query('SELECT COUNT(*) FROM moderation_reviews WHERE decision IS NULL')->fetchColumn();
        $stmt = $db->prepare('SELECT COUNT(*), AVG(TIMESTAMPDIFF(MINUTE, submitted_at, decided_at)) / 60 FROM moderation_reviews WHERE decided_at >= NOW() - INTERVAL ? DAY');
        $stmt->execute([$jours]);
        [$decisions, $delai] = $stmt->fetch(PDO::FETCH_NUM);
        $stmt = $db->prepare("SELECT reason_code, COUNT(*) FROM moderation_reviews WHERE decision IN ('rejected', 'correction') AND decided_at >= NOW() - INTERVAL ? DAY GROUP BY reason_code");
        $stmt->execute([$jours]);
        return [
            'en_attente' => $enAttente, 'delai_moyen_heures' => $delai !== null ? round((float) $delai, 1) : null,
            'decisions' => (int) $decisions, 'refus_par_motif' => array_map('intval', $stmt->fetchAll(PDO::FETCH_KEY_PAIR)),
        ];
    }

    public static function revue(int $id): ?array
    {
        $stmt = self::base()->prepare('SELECT * FROM moderation_reviews WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** @return array<int,array<string,mixed>> historique des transitions d'un contenu */
    public static function historique(string $type, int $id): array
    {
        $stmt = self::base()->prepare('SELECT c.*, u.username FROM content_transitions c LEFT JOIN users u ON u.id = c.actor_id WHERE c.content_type = ? AND c.content_id = ? ORDER BY c.id');
        $stmt->execute([$type, $id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // -----------------------------------------------------------------

    private static function changer(PDO $db, string $type, int $id, string $depuis, string $vers, ?int $acteur, string $motif): void
    {
        $table = $type === 'release' ? 'releases' : 'tracks';
        $colonnes = $type === 'release' ? ', rejected_reason = ?, reviewed_by = ?, reviewed_at = NOW()' : '';
        $params = $type === 'release' ? [$vers, $vers === 'rejected' ? ($motif ?: null) : null, $acteur, $id, $depuis] : [$vers, $id, $depuis];
        $stmt = $db->prepare("UPDATE {$table} SET status = ?{$colonnes} WHERE id = ? AND status = ?");
        $stmt->execute($params);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('statut modifie entre-temps');
        }
        $db->prepare('INSERT INTO content_transitions (content_type, content_id, from_status, to_status, actor_id, reason) VALUES (?, ?, ?, ?, ?, ?)')
           ->execute([$type, $id, $depuis, $vers, $acteur, $motif ?: null]);
    }

    private static function contenu(string $type, int $id): ?array
    {
        $table = $type === 'release' ? 'releases' : 'tracks';
        $stmt = self::base()->prepare("SELECT c.id, c.status, c.artist_id, c.title, a.user_id AS artiste_user FROM {$table} c JOIN artists a ON a.id = c.artist_id WHERE c.id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private static function prevenirArtiste(array $revue, string $decision, string $explication): void
    {
        $contenu = self::contenu((string) $revue['content_type'], (int) $revue['content_id']);
        $stmt = self::base()->prepare('SELECT u.email, u.first_name FROM artists a JOIN users u ON u.id = a.user_id WHERE a.id = ?');
        $stmt->execute([$revue['artist_id']]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$u || empty($u['email']) || !$contenu) {
            return;
        }
        $texte = match ($decision) {
            'approved'   => 'est approuve et publie sur Tchadok.',
            'correction' => 'demande une correction avant publication.',
            default      => 'n\'a pas ete accepte.',
        };
        sendEmail((string) $u['email'], 'Tchadok - moderation de « ' . $contenu['title'] . ' »', '<p>Bonjour ' . htmlspecialchars((string) $u['first_name'], ENT_QUOTES, 'UTF-8')
            . ',</p><p>Votre contenu « ' . htmlspecialchars((string) $contenu['title'], ENT_QUOTES, 'UTF-8') . ' » ' . $texte . '</p>'
            . ($explication !== '' ? '<p>Motif : ' . htmlspecialchars($explication, ENT_QUOTES, 'UTF-8') . '</p><p>Vous pouvez le corriger puis le soumettre a nouveau depuis votre espace de publication.</p>' : ''));
    }

    private static function messageTransition(string $vers): string
    {
        return ['pending' => 'Soumis a la moderation.', 'draft' => 'Soumission retiree : de nouveau en brouillon.', 'approved' => 'Publie.',
            'rejected' => 'Refuse.', 'offline' => 'Retire du catalogue public.'][$vers] ?? 'Statut modifie.';
    }

    private static function base(): PDO
    {
        return TchadokDatabase::getInstance()->getConnection();
    }
}
