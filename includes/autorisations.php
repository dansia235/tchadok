<?php
/**
 * Roles et permissions (SEC-19).
 *
 * AVANT
 *   isAdmin() renvoyait vrai ou faux. La colonne admins.permissions contenait
 *   '["all"]' et n'etait jamais lue. Un moderateur de catalogue pouvait donc
 *   atteindre les ecrans financiers, et un stagiaire charge du blog pouvait
 *   supprimer des comptes.
 *
 * APRES
 *   Des permissions nommees, portees par des roles : peut('finance.versement.executer').
 *   Le controle est fait cote serveur, a l'entree de l'ecran -- masquer un lien
 *   de menu ne protege rien.
 *
 * SUPER-ADMINISTRATEUR
 *   Ce role recoit toutes les permissions en base, ET le code lui accorde
 *   celles ajoutees plus tard. Sans cette regle, creer une permission la
 *   rendrait inaccessible a tout le monde -- y compris au seul role cense
 *   pouvoir tout faire -- jusqu'a ce que quelqu'un pense a l'attribuer.
 *
 * SEPARATION DES POUVOIRS SUR L'ARGENT
 *   Preparer un versement et l'executer sont deux permissions distinctes, et
 *   verifierSeparationVersement() refuse en plus qu'une meme personne fasse
 *   les deux sur le meme versement. Un role qui cumule les deux permissions
 *   (responsable finance) reste donc soumis a la regle.
 */

declare(strict_types=1);

require_once __DIR__ . '/reponse-refus.php';

final class Autorisations
{
    public const SUPER_ADMIN = 'super_admin';

    /** @var array<int,string[]> roles par utilisateur, pour la requete en cours */
    private static array $rolesParUtilisateur = [];

    /** @var array<int,string[]> permissions par utilisateur, pour la requete en cours */
    private static array $permissionsParUtilisateur = [];

    private static ?bool $tablesDisponibles = null;

    /**
     * Roles (slugs) d'un utilisateur. Par defaut, la personne connectee.
     *
     * @return string[]
     */
    public static function roles(?int $userId = null): array
    {
        $userId = $userId ?? self::utilisateurCourant();
        if ($userId === null) {
            return [];
        }

        if (isset(self::$rolesParUtilisateur[$userId])) {
            return self::$rolesParUtilisateur[$userId];
        }

        $db = self::db();
        if (!$db) {
            return self::$rolesParUtilisateur[$userId] = self::rolesDeRepli($userId);
        }

        try {
            $stmt = $db->prepare(
                'SELECT r.slug FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ?'
            );
            $stmt->execute([$userId]);
            $roles = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (Throwable $e) {
            error_log('[Tchadok][roles] lecture impossible : ' . $e->getMessage());
            $roles = self::rolesDeRepli($userId);
        }

        return self::$rolesParUtilisateur[$userId] = array_values(array_unique($roles));
    }

    /**
     * Permissions effectives d'un utilisateur.
     *
     * @return string[]
     */
    public static function permissions(?int $userId = null): array
    {
        $userId = $userId ?? self::utilisateurCourant();
        if ($userId === null) {
            return [];
        }

        if (isset(self::$permissionsParUtilisateur[$userId])) {
            return self::$permissionsParUtilisateur[$userId];
        }

        $db = self::db();
        if (!$db) {
            return self::$permissionsParUtilisateur[$userId] = [];
        }

        try {
            $stmt = $db->prepare(
                'SELECT DISTINCT p.slug
                 FROM user_roles ur
                 JOIN role_permissions rp ON rp.role_id = ur.role_id
                 JOIN permissions p ON p.id = rp.permission_id
                 WHERE ur.user_id = ?'
            );
            $stmt->execute([$userId]);
            $permissions = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (Throwable $e) {
            error_log('[Tchadok][roles] lecture des permissions impossible : ' . $e->getMessage());
            $permissions = [];
        }

        return self::$permissionsParUtilisateur[$userId] = $permissions;
    }

    /**
     * La personne dispose-t-elle de cette permission ?
     */
    public static function peut(string $permission, ?int $userId = null): bool
    {
        $userId = $userId ?? self::utilisateurCourant();
        if ($userId === null) {
            return false;
        }

        if (in_array(self::SUPER_ADMIN, self::roles($userId), true)) {
            return true;
        }

        return in_array($permission, self::permissions($userId), true);
    }

    /**
     * Exige la permission, ou refuse la requete en 403.
     *
     * A appeler a l'entree de l'ecran, avant tout traitement : masquer un lien
     * de menu n'empeche personne de taper l'adresse.
     */
    public static function exiger(string $permission): void
    {
        if (self::peut($permission)) {
            // SEC-20 : un droit d'ecriture en administration suppose un second
            // facteur. Sans lui, la personne est renvoyee vers l'activation --
            // bloquer sans proposer la sortie serait un cul-de-sac.
            if (class_exists('DeuxFacteurs') && DeuxFacteurs::exigee() && !DeuxFacteurs::estActive()) {
                if (class_exists('JournalAudit')) {
                    JournalAudit::enregistrer('autorisation.refus', [
                        'cible_type' => 'permission',
                        'cible_id'   => $permission,
                        'raison'     => 'double authentification obligatoire et non activee',
                    ]);
                }

                if (!ReponseRefus::attendJson() && !headers_sent()) {
                    header('Location: ' . (defined('SITE_URL') ? SITE_URL : '') . '/2fa.php');
                    exit;
                }

                ReponseRefus::envoyer(
                    403,
                    'Double authentification requise',
                    "Votre role impose un second facteur. Activez-le depuis votre espace securite.",
                    'deux-facteurs'
                );
            }

            return;
        }

        $userId = self::utilisateurCourant();

        // Un refus est un evenement interessant : il signale soit un droit mal
        // attribue, soit quelqu'un qui cherche ou il ne devrait pas.
        if (class_exists('JournalAudit')) {
            JournalAudit::enregistrer('autorisation.refus', [
                'cible_type' => 'permission',
                'cible_id'   => $permission,
                'raison'     => 'acces refuse sur ' . ReponseRefus::chemin(),
            ]);
        }

        error_log(sprintf(
            '[Tchadok][roles] refus de %s pour l\'utilisateur %s sur %s',
            $permission,
            $userId === null ? 'anonyme' : (string) $userId,
            ReponseRefus::chemin()
        ));

        if ($userId === null) {
            // Personne non connectee : la page de connexion est plus utile
            // qu'un refus sec.
            if (!headers_sent() && !ReponseRefus::attendJson()) {
                header('Location: ' . (defined('SITE_URL') ? SITE_URL : '') . '/login.php');
                exit;
            }
        }

        ReponseRefus::envoyer(
            403,
            'Acces refuse',
            "Votre compte n'a pas les droits necessaires pour cette action.",
            'autorisation'
        );
    }

    /**
     * Dispose d'au moins un role d'administration.
     */
    public static function estAdministrateur(?int $userId = null): bool
    {
        return self::roles($userId) !== [];
    }

    /**
     * Separation des pouvoirs sur l'argent : celui qui a prepare un versement
     * ne peut pas l'executer.
     *
     * Regle appelee par le lot versements (LOT 8) au moment de l'execution.
     * Elle est ecrite ici, avec les autorisations, pour qu'elle ne depende pas
     * d'un ecran : un futur script ou une future API la trouvera aussi.
     */
    public static function verifierSeparationVersement(?int $createurId, ?int $executantId): bool
    {
        if ($createurId === null || $executantId === null) {
            return false;
        }

        return $createurId !== $executantId;
    }

    /**
     * Roles disponibles, pour les ecrans d'attribution.
     *
     * @return array<int,array{id:int,slug:string,nom:string,description:?string}>
     */
    public static function rolesDisponibles(): array
    {
        $db = self::db();
        if (!$db) {
            return [];
        }

        try {
            return $db->query('SELECT id, slug, nom, description FROM roles ORDER BY id')
                      ->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('[Tchadok][roles] ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Attribue un role. Renvoie true si l'attribution a eu lieu.
     */
    public static function attribuer(int $userId, string $slug, ?int $parQui = null): bool
    {
        $db = self::db();
        if (!$db) {
            return false;
        }

        try {
            $stmt = $db->prepare(
                'INSERT IGNORE INTO user_roles (user_id, role_id, attribue_par)
                 SELECT ?, id, ? FROM roles WHERE slug = ?'
            );
            $stmt->execute([$userId, $parQui, $slug]);
            $fait = $stmt->rowCount() > 0;
        } catch (Throwable $e) {
            error_log('[Tchadok][roles] attribution impossible : ' . $e->getMessage());
            return false;
        }

        unset(self::$rolesParUtilisateur[$userId], self::$permissionsParUtilisateur[$userId]);

        if ($fait && class_exists('JournalAudit')) {
            JournalAudit::enregistrer('role.attribue', [
                'cible_type' => 'utilisateur',
                'cible_id'   => $userId,
                'apres'      => ['role' => $slug],
                'acteur'     => $parQui,
            ]);
        }

        return $fait;
    }

    /**
     * Retire un role. Renvoie true si le role etait attribue.
     */
    public static function retirer(int $userId, string $slug, ?int $parQui = null): bool
    {
        $db = self::db();
        if (!$db) {
            return false;
        }

        try {
            $stmt = $db->prepare(
                'DELETE ur FROM user_roles ur JOIN roles r ON r.id = ur.role_id
                 WHERE ur.user_id = ? AND r.slug = ?'
            );
            $stmt->execute([$userId, $slug]);
            $fait = $stmt->rowCount() > 0;
        } catch (Throwable $e) {
            error_log('[Tchadok][roles] retrait impossible : ' . $e->getMessage());
            return false;
        }

        unset(self::$rolesParUtilisateur[$userId], self::$permissionsParUtilisateur[$userId]);

        if ($fait && class_exists('JournalAudit')) {
            JournalAudit::enregistrer('role.retire', [
                'cible_type' => 'utilisateur',
                'cible_id'   => $userId,
                'avant'      => ['role' => $slug],
                'acteur'     => $parQui,
            ]);
        }

        return $fait;
    }

    /**
     * Vide le cache de la requete en cours (apres un changement de role).
     */
    public static function oublierCache(?int $userId = null): void
    {
        if ($userId === null) {
            self::$rolesParUtilisateur = [];
            self::$permissionsParUtilisateur = [];
            return;
        }

        unset(self::$rolesParUtilisateur[$userId], self::$permissionsParUtilisateur[$userId]);
    }

    // ------------------------------------------------------------------
    // Interne
    // ------------------------------------------------------------------

    private static function utilisateurCourant(): ?int
    {
        $id = $_SESSION['user_id'] ?? null;

        return $id === null ? null : (int) $id;
    }

    /**
     * Tant que la migration SEC-19 n'est pas appliquee, l'ancienne table
     * `admins` fait foi : un serveur non migre reste administrable, et le
     * defaut est journalise.
     *
     * @return string[]
     */
    private static function rolesDeRepli(int $userId): array
    {
        $db = TchadokDatabase::getInstance()->getConnection();
        if (!$db) {
            return [];
        }

        try {
            $stmt = $db->prepare('SELECT role FROM admins WHERE user_id = ? LIMIT 1');
            $stmt->execute([$userId]);
            $role = $stmt->fetchColumn();
        } catch (Throwable $e) {
            return [];
        }

        if ($role === false) {
            return [];
        }

        return [match ($role) {
            'super_admin' => 'super_admin',
            'moderator'   => 'moderateur_catalogue',
            default       => 'admin_plateforme',
        }];
    }

    private static function db(): ?PDO
    {
        $db = TchadokDatabase::getInstance()->getConnection();
        if (!$db) {
            return null;
        }

        if (self::$tablesDisponibles === null) {
            try {
                $db->query('SELECT 1 FROM user_roles LIMIT 1');
                self::$tablesDisponibles = true;
            } catch (Throwable $e) {
                self::$tablesDisponibles = false;
                error_log('[Tchadok][roles] tables absentes : repli sur la table admins (migration SEC-19 non appliquee)');
            }
        }

        return self::$tablesDisponibles ? $db : null;
    }
}
