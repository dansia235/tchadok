<?php
/**
 * Journal d'audit (SEC-19).
 *
 * AVANT
 *   Rien. Impossible de savoir qui avait approuve un titre, reinitialise un
 *   mot de passe, change un tarif ou supprime un compte. En cas de litige avec
 *   un artiste sur ses ventes, aucune trace a produire.
 *
 * REGLE
 *   L'application n'ecrit QUE des INSERT. Aucune fonction ici ne modifie ni ne
 *   supprime une ligne : un journal qu'on peut retoucher ne prouve rien. En
 *   production, le compte MySQL applicatif ne doit avoir ni UPDATE ni DELETE
 *   sur audit_log -- c'est ce qui rend la regle opposable.
 *
 * CE QUI EST JOURNALISE
 *   Connexion et echec d'administration, creation/modification/suppression de
 *   compte, changement de role, approbation ou rejet de contenu, modification
 *   de tarif ou de commission, preparation et execution de versement,
 *   remboursement, acces en masse a des donnees personnelles, modification de
 *   la taxonomie, arrete de classement.
 *
 * UN ECHEC D'ECRITURE N'INTERROMPT RIEN : l'action metier a deja eu lieu, et
 * refuser apres coup n'aurait aucun sens. L'incident part dans le journal
 * d'erreurs, ou l'exploitant le verra.
 */

declare(strict_types=1);

final class JournalAudit
{
    /** Actions reconnues, pour que les ecrans de consultation puissent filtrer. */
    public const ACTIONS = [
        'admin.connexion'          => 'Connexion a l\'administration',
        'admin.connexion.echec'    => 'Echec de connexion a l\'administration',
        'autorisation.refus'       => 'Acces refuse faute de droits',
        'compte.cree'              => 'Compte cree',
        'compte.modifie'           => 'Compte modifie',
        'compte.supprime'          => 'Compte supprime',
        'compte.mot-de-passe'      => 'Mot de passe reinitialise',
        'role.attribue'            => 'Role attribue',
        'role.retire'              => 'Role retire',
        'contenu.cree'             => 'Contenu ajoute au catalogue',
        'contenu.approuve'         => 'Contenu approuve',
        'contenu.rejete'           => 'Contenu rejete',
        'contenu.supprime'         => 'Contenu supprime',
        'tarif.modifie'            => 'Tarif ou commission modifie',
        'taxonomie.modifiee'       => 'Genres ou categories modifies',
        'versement.prepare'        => 'Versement prepare',
        'versement.execute'        => 'Versement execute',
        'remboursement.execute'    => 'Remboursement execute',
        'donnees.export'           => 'Export de donnees personnelles',
        'classement.arrete'        => 'Classement arrete',
    ];

    private static ?bool $tableDisponible = null;

    /**
     * Enregistre une action.
     *
     * @param array{
     *     cible_type?:string, cible_id?:int|string|null,
     *     avant?:array|null, apres?:array|null,
     *     raison?:string, acteur?:int|null
     * } $details
     */
    public static function enregistrer(string $action, array $details = []): void
    {
        $db = self::db();
        if (!$db) {
            return;
        }

        $acteur = $details['acteur'] ?? ($_SESSION['user_id'] ?? null);
        $acteur = $acteur === null ? null : (int) $acteur;

        $roles = '';
        if ($acteur !== null && class_exists('Autorisations')) {
            $roles = implode(', ', Autorisations::roles($acteur));
        }

        try {
            $db->prepare(
                'INSERT INTO audit_log
                    (actor_id, actor_role, action, target_type, target_id, before_state, after_state, reason, ip_address, user_agent)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $acteur,
                $roles !== '' ? substr($roles, 0, 120) : null,
                substr($action, 0, 80),
                isset($details['cible_type']) ? substr((string) $details['cible_type'], 0, 60) : null,
                isset($details['cible_id']) ? substr((string) $details['cible_id'], 0, 60) : null,
                self::etat($details['avant'] ?? null),
                self::etat($details['apres'] ?? null),
                isset($details['raison']) ? substr((string) $details['raison'], 0, 500) : null,
                function_exists('clientIp') ? clientIp() : ($_SERVER['REMOTE_ADDR'] ?? null),
                substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null,
            ]);
        } catch (Throwable $e) {
            // L'action metier a deja eu lieu : on ne la defait pas pour autant.
            error_log('[Tchadok][audit] ecriture impossible (' . $action . ') : ' . $e->getMessage());
        }
    }

    /**
     * Lecture filtree, pour l'ecran de consultation.
     *
     * @param array{action?:string, acteur?:int, cible_type?:string, depuis?:string, jusqu_a?:string, recherche?:string} $filtres
     * @return array{lignes:array<int,array<string,mixed>>, total:int}
     */
    public static function lire(array $filtres = [], int $page = 1, int $parPage = 50): array
    {
        $db = self::db();
        if (!$db) {
            return ['lignes' => [], 'total' => 0];
        }

        $conditions = [];
        $valeurs = [];

        if (!empty($filtres['action'])) {
            $conditions[] = 'a.action = ?';
            $valeurs[] = $filtres['action'];
        }
        if (!empty($filtres['acteur'])) {
            $conditions[] = 'a.actor_id = ?';
            $valeurs[] = (int) $filtres['acteur'];
        }
        if (!empty($filtres['cible_type'])) {
            $conditions[] = 'a.target_type = ?';
            $valeurs[] = $filtres['cible_type'];
        }
        if (!empty($filtres['depuis'])) {
            $conditions[] = 'a.created_at >= ?';
            $valeurs[] = $filtres['depuis'] . ' 00:00:00';
        }
        if (!empty($filtres['jusqu_a'])) {
            $conditions[] = 'a.created_at <= ?';
            $valeurs[] = $filtres['jusqu_a'] . ' 23:59:59';
        }

        $ou = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $parPage = max(10, min(200, $parPage));
        $decalage = max(0, ($page - 1) * $parPage);

        try {
            $stmt = $db->prepare("SELECT COUNT(*) FROM audit_log a {$ou}");
            $stmt->execute($valeurs);
            $total = (int) $stmt->fetchColumn();

            // LIMIT et OFFSET sont des entiers deja contraints ci-dessus.
            $stmt = $db->prepare(
                "SELECT a.*, u.username, u.email
                 FROM audit_log a
                 LEFT JOIN users u ON u.id = a.actor_id
                 {$ou}
                 ORDER BY a.id DESC
                 LIMIT {$parPage} OFFSET {$decalage}"
            );
            $stmt->execute($valeurs);
            $lignes = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('[Tchadok][audit] lecture impossible : ' . $e->getMessage());
            return ['lignes' => [], 'total' => 0];
        }

        return ['lignes' => $lignes, 'total' => $total];
    }

    /**
     * Actions reellement presentes dans le journal, pour alimenter un filtre.
     *
     * @return string[]
     */
    public static function actionsUtilisees(): array
    {
        $db = self::db();
        if (!$db) {
            return [];
        }

        try {
            return $db->query('SELECT DISTINCT action FROM audit_log ORDER BY action')
                      ->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    public static function libelle(string $action): string
    {
        return self::ACTIONS[$action] ?? $action;
    }

    /**
     * Etat avant ou apres, en JSON lisible. Les cles sensibles sont retirees :
     * un journal ne doit jamais devenir l'endroit ou traine un mot de passe.
     */
    private static function etat(?array $etat): ?string
    {
        if ($etat === null || $etat === []) {
            return null;
        }

        $interdits = ['password', 'password_hash', 'mot_de_passe', 'reset_token', 'remember_token', 'csrf_token', 'secret'];
        foreach ($etat as $cle => $valeur) {
            foreach ($interdits as $interdit) {
                if (stripos((string) $cle, $interdit) !== false) {
                    $etat[$cle] = '(masque)';
                }
            }
        }

        $json = json_encode($etat, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $json === false ? null : substr($json, 0, 4000);
    }

    private static function db(): ?PDO
    {
        $db = TchadokDatabase::getInstance()->getConnection();
        if (!$db) {
            return null;
        }

        if (self::$tableDisponible === null) {
            try {
                $db->query('SELECT 1 FROM audit_log LIMIT 1');
                self::$tableDisponible = true;
            } catch (Throwable $e) {
                self::$tableDisponible = false;
                error_log('[Tchadok][audit] table absente : journal inactif (migration SEC-19 non appliquee)');
            }
        }

        return self::$tableDisponible ? $db : null;
    }
}
