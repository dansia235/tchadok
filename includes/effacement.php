<?php
/**
 * Suppression logique et droit a l'effacement (DATA-06).
 *
 * DEUX GESTES DIFFERENTS, SOUVENT CONFONDUS
 *
 *   RETIRER un contenu (`retirer()`) le fait disparaitre des pages publiques
 *   sans le sortir de la base. On peut encore repondre a « pourquoi ce titre
 *   n'est plus en vente ? » et « combien a-t-il rapporte avant son retrait ? ».
 *   C'est reversible : `retablir()`.
 *
 *   ANONYMISER un compte (`anonymiser()`) remplace ce qui designe une personne
 *   -- nom, courriel, telephone, ville, date de naissance -- et CONSERVE les
 *   ecritures comptables. C'est definitif.
 *
 * POURQUOI ON NE SUPPRIME PAS LE COMPTE
 *   Une suppression en cascade emporterait les commandes, donc la comptabilite
 *   -- que la loi impose de conserver -- et les ecoutes, donc les revenus dus
 *   aux artistes. DATA-05 l'interdit d'ailleurs deja au niveau de la base :
 *   `orders.user_id` refuse la suppression. Anonymiser satisfait le droit a
 *   l'effacement sans detruire ce qui ne designe plus personne.
 *
 * CE QUI SURVIT A L'ANONYMISATION
 *   Les commandes et leurs lignes, les evenements de paiement, les versements,
 *   le journal d'audit et les ecoutes -- ces dernieres detachees de leur
 *   auteur. Plus rien n'y renvoie a une personne identifiable.
 */

declare(strict_types=1);

final class Effacement
{
    /** Tables portant une suppression logique. */
    public const TABLES = ['users', 'artists', 'tracks', 'releases', 'playlists'];

    /**
     * Retire un contenu de l'affichage sans le supprimer.
     *
     * @return array{succes:bool, erreurs:string[]}
     */
    public static function retirer(string $table, int $id, ?int $parQui = null, string $motif = ''): array
    {
        if (!in_array($table, self::TABLES, true)) {
            return ['succes' => false, 'erreurs' => ['Cette table ne connait pas la suppression logique.']];
        }

        $db = self::base();
        if (!$db) {
            return ['succes' => false, 'erreurs' => ['Base de donnees indisponible.']];
        }

        try {
            $stmt = $db->prepare("UPDATE `{$table}` SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL");
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) {
                return ['succes' => false, 'erreurs' => ['Element introuvable ou deja retire.']];
            }
        } catch (Throwable $e) {
            error_log('[Tchadok][effacement] retrait impossible : ' . $e->getMessage());
            return ['succes' => false, 'erreurs' => ['Retrait impossible pour le moment.']];
        }

        self::tracer('contenu.supprime', $table, $id, $parQui, $motif);

        return ['succes' => true, 'erreurs' => []];
    }

    /**
     * Remet en ligne un contenu retire. C'est tout l'interet d'une suppression
     * logique : une erreur se repare.
     *
     * @return array{succes:bool, erreurs:string[]}
     */
    public static function retablir(string $table, int $id, ?int $parQui = null): array
    {
        if (!in_array($table, self::TABLES, true)) {
            return ['succes' => false, 'erreurs' => ['Cette table ne connait pas la suppression logique.']];
        }

        $db = self::base();
        if (!$db) {
            return ['succes' => false, 'erreurs' => ['Base de donnees indisponible.']];
        }

        try {
            $stmt = $db->prepare("UPDATE `{$table}` SET deleted_at = NULL WHERE id = ? AND deleted_at IS NOT NULL");
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) {
                return ['succes' => false, 'erreurs' => ['Element introuvable ou deja en ligne.']];
            }
        } catch (Throwable $e) {
            error_log('[Tchadok][effacement] retablissement impossible : ' . $e->getMessage());
            return ['succes' => false, 'erreurs' => ['Retablissement impossible pour le moment.']];
        }

        self::tracer('contenu.restaure', $table, $id, $parQui, '');

        return ['succes' => true, 'erreurs' => []];
    }

    public static function estRetire(string $table, int $id): bool
    {
        if (!in_array($table, self::TABLES, true)) {
            return false;
        }

        $db = self::base();
        if (!$db) {
            return false;
        }

        try {
            $stmt = $db->prepare("SELECT deleted_at FROM `{$table}` WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);

            return $stmt->fetchColumn() !== null;
        } catch (Throwable $e) {
            error_log('[Tchadok][effacement] lecture impossible : ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Droit a l'effacement : les donnees personnelles du compte sont
     * remplacees, les ecritures comptables demeurent.
     *
     * L'operation est DEFINITIVE et se fait en une transaction : un
     * effacement a moitie fait laisserait un compte a la fois identifiable et
     * inutilisable.
     *
     * @return array{succes:bool, erreurs:string[], pseudonyme:string}
     */
    public static function anonymiser(int $userId, ?int $parQui = null, string $motif = ''): array
    {
        $db = self::base();
        if (!$db) {
            return ['succes' => false, 'erreurs' => ['Base de donnees indisponible.'], 'pseudonyme' => ''];
        }

        try {
            $stmt = $db->prepare('SELECT id, anonymized_at FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$userId]);
            $compte = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$compte) {
                return ['succes' => false, 'erreurs' => ['Compte introuvable.'], 'pseudonyme' => ''];
            }

            if ($compte['anonymized_at'] !== null) {
                return ['succes' => false, 'erreurs' => ['Ce compte est deja anonymise.'], 'pseudonyme' => ''];
            }

            // Pseudonyme stable et sans lien avec l'identite d'origine : il
            // sert seulement a satisfaire les contraintes d'unicite.
            $pseudonyme = 'anonyme_' . $userId . '_' . substr(bin2hex(random_bytes(4)), 0, 6);

            $db->beginTransaction();

            $db->prepare(
                "UPDATE users SET
                    username = ?,
                    email = ?,
                    first_name = 'Compte',
                    last_name = 'anonymise',
                    phone = NULL,
                    city = NULL,
                    country = NULL,
                    date_of_birth = NULL,
                    gender = NULL,
                    profile_image = NULL,
                    verification_token = NULL,
                    reset_token = NULL,
                    reset_expires = NULL,
                    -- Le mot de passe devient inutilisable : aucune chaine ne
                    -- produit ce hash, et password_verify() le refuse.
                    password_hash = ?,
                    is_active = 0,
                    deleted_at = COALESCE(deleted_at, NOW()),
                    anonymized_at = NOW()
                 WHERE id = ?"
            )->execute([$pseudonyme, $pseudonyme . '@anonyme.invalid', '*inutilisable*', $userId]);

            // Les appareils connectes et les moyens de revenir tombent avec le
            // compte : sinon une session ouverte survivrait a l'effacement.
            foreach ([
                'DELETE FROM user_sessions WHERE user_id = ?',
                'DELETE FROM remember_tokens WHERE user_id = ?',
                'DELETE FROM user_2fa_settings WHERE user_id = ?',
                'DELETE FROM user_backup_codes WHERE user_id = ?',
                'DELETE FROM notifications WHERE user_id = ?',
            ] as $requete) {
                try {
                    $db->prepare($requete)->execute([$userId]);
                } catch (Throwable $e) {
                    // Table absente sur une installation partielle : sans
                    // consequence, l'anonymisation continue.
                    error_log('[Tchadok][effacement] nettoyage partiel : ' . $e->getMessage());
                }
            }

            // Les ecoutes restent, sans leur auteur : la statistique par genre
            // et les revenus des artistes ne doivent pas disparaitre parce
            // qu'un auditeur s'en va.
            try {
                $db->prepare('UPDATE streams SET user_id = NULL WHERE user_id = ?')->execute([$userId]);
            } catch (Throwable $e) {
                error_log('[Tchadok][effacement] ecoutes non detachees : ' . $e->getMessage());
            }

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('[Tchadok][effacement] anonymisation impossible : ' . $e->getMessage());
            return ['succes' => false, 'erreurs' => ['Anonymisation impossible pour le moment.'], 'pseudonyme' => ''];
        }

        // Le journal d'audit conserve l'identifiant, pas le nom : il faut
        // pouvoir prouver que la demande a ete honoree.
        self::tracer('compte.anonymise', 'users', $userId, $parQui, $motif !== '' ? $motif : 'droit a l\'effacement');

        return ['succes' => true, 'erreurs' => [], 'pseudonyme' => $pseudonyme];
    }

    /**
     * Ce qui subsiste d'un compte anonymise, pour le prouver.
     *
     * @return array<string,int>
     */
    public static function tracesComptables(int $userId): array
    {
        $db = self::base();
        if (!$db) {
            return [];
        }

        $comptes = [];
        foreach (['orders' => 'user_id', 'entitlements' => 'user_id', 'streams' => 'user_id'] as $table => $colonne) {
            try {
                $stmt = $db->prepare("SELECT COUNT(*) FROM `{$table}` WHERE `{$colonne}` = ?");
                $stmt->execute([$userId]);
                $comptes[$table] = (int) $stmt->fetchColumn();
            } catch (Throwable $e) {
                $comptes[$table] = 0;
            }
        }

        return $comptes;
    }

    private static function tracer(string $action, string $table, int $id, ?int $parQui, string $motif): void
    {
        if (!class_exists('JournalAudit')) {
            return;
        }

        JournalAudit::enregistrer($action, [
            'cible_type' => $table,
            'cible_id'   => $id,
            'raison'     => $motif !== '' ? $motif : null,
            'acteur'     => $parQui,
        ]);
    }

    private static function base(): ?PDO
    {
        return TchadokDatabase::getInstance()->getConnection() ?: null;
    }
}
