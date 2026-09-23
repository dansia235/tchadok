<?php
/**
 * Limitation de debit et verrouillage de connexion (SEC-12).
 *
 * AVANT
 *   Aucune limite. Un mot de passe pouvait etre essaye des millions de fois,
 *   et chaque page publique acceptait autant de requetes qu'on voulait lui en
 *   envoyer.
 *
 * DEUX MECANISMES DISTINCTS
 *
 *   LimiteDebit      combien de requetes une adresse peut adresser a une
 *                    action donnee, sur une fenetre glissante. Repond 429.
 *
 *   VerrouConnexion  combien d'echecs d'authentification sont tolerés avant
 *                    de bloquer temporairement. Le verrou porte sur le couple
 *                    identifiant + adresse : verrouiller sur le seul
 *                    identifiant permettrait de bloquer le compte de n'importe
 *                    qui a distance, en se trompant de mot de passe a sa
 *                    place. Une garde par adresse seule, plus large, arrete
 *                    celui qui essaie beaucoup de comptes depuis un meme poste.
 *
 * Message volontairement identique que le compte existe ou non : un message
 * different renseignerait sur l'existence du compte.
 *
 * POURQUOI LA CONNEXION N'EST PAS LIMITEE AU NOMBRE DE REQUETES
 *   Seuls les ECHECS sont comptes. Compter aussi les connexions reussies
 *   punirait les adresses partagees -- un cybercafe, un operateur mobile qui
 *   place ses abonnes derriere une meme adresse -- ou des dizaines de
 *   personnes legitimes se connectent depuis la meme adresse.
 */

declare(strict_types=1);

require_once __DIR__ . '/reponse-refus.php';

final class LimiteDebit
{
    /**
     * Regles par action : [requetes autorisees, fenetre en secondes].
     *
     * Valeurs choisies pour ne jamais gener un usage normal : elles laissent
     * passer plusieurs dizaines de fois ce qu'une personne fait reellement,
     * tout en arretant une boucle automatisee.
     */
    private const REGLES = [
        'inscription'  => [10, 3600],
        'contact'      => [5, 600],
        'mot-de-passe' => [5, 900],    // demande et reinitialisation
        'recherche'    => [60, 60],
        'ecoute'       => [60, 60],
    ];

    private static ?bool $tableDisponible = null;

    public static function active(): bool
    {
        return !class_exists('EnvLoader') || EnvLoader::bool('RATE_LIMIT_ENABLED', true);
    }

    /**
     * Compte les requetes deja enregistrees pour cette action et cette adresse
     * dans la fenetre courante.
     */
    public static function compte(string $action, ?string $ip = null): int
    {
        [, $fenetre] = self::regle($action);
        $db = self::db();
        if (!$db) {
            return 0;
        }

        try {
            $stmt = $db->prepare(
                'SELECT COUNT(*) FROM rate_limit_hits
                 WHERE bucket = ? AND ip_address = ? AND created_at > (NOW() - INTERVAL ? SECOND)'
            );
            $stmt->execute([$action, $ip ?? self::adresse(), $fenetre]);

            return (int) $stmt->fetchColumn();
        } catch (Throwable $e) {
            self::signaler('comptage impossible : ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Enregistre une requete pour cette action.
     */
    public static function enregistrer(string $action, ?string $ip = null): void
    {
        $db = self::db();
        if (!$db) {
            return;
        }

        try {
            $db->prepare('INSERT INTO rate_limit_hits (bucket, ip_address) VALUES (?, ?)')
               ->execute([$action, $ip ?? self::adresse()]);
        } catch (Throwable $e) {
            self::signaler('enregistrement impossible : ' . $e->getMessage());
        }

        self::purgerParfois();
    }

    /**
     * Compte cette requete et refuse en 429 si l'action est saturee.
     *
     * La requete refusee n'est PAS enregistree : sinon, insister suffirait a
     * allonger indefiniment la punition, et a remplir la table.
     */
    public static function appliquer(string $action): void
    {
        if (!self::active() || PHP_SAPI === 'cli') {
            return;
        }

        [$max, $fenetre] = self::regle($action);
        if (self::compte($action) >= $max) {
            self::refuser($action, $fenetre);
        }

        self::enregistrer($action);
    }

    /**
     * Refus 429, avec le delai avant nouvelle tentative.
     */
    public static function refuser(string $action, int $fenetre): never
    {
        error_log(sprintf(
            '[Tchadok][debit] action "%s" saturee depuis %s sur %s',
            $action,
            self::adresse(),
            ReponseRefus::chemin()
        ));

        ReponseRefus::envoyer(
            429,
            'Trop de requetes',
            'Trop de requetes ont ete envoyees depuis cet appareil. Patientez '
            . self::enMinutes($fenetre) . ' avant de reessayer.',
            'debit',
            $fenetre
        );
    }

    /**
     * Secondes restantes avant de pouvoir reessayer, 0 si l'action est ouverte.
     */
    public static function attente(string $action, ?string $ip = null): int
    {
        if (!self::active()) {
            return 0;
        }

        [$max, $fenetre] = self::regle($action);
        if (self::compte($action, $ip) < $max) {
            return 0;
        }

        $db = self::db();
        if (!$db) {
            return 0;
        }

        try {
            $stmt = $db->prepare(
                'SELECT TIMESTAMPDIFF(SECOND, NOW(), MIN(created_at) + INTERVAL ? SECOND)
                 FROM rate_limit_hits
                 WHERE bucket = ? AND ip_address = ? AND created_at > (NOW() - INTERVAL ? SECOND)'
            );
            $stmt->execute([$fenetre, $action, $ip ?? self::adresse(), $fenetre]);

            return max(0, (int) $stmt->fetchColumn());
        } catch (Throwable $e) {
            self::signaler('calcul du delai impossible : ' . $e->getMessage());
            return 0;
        }
    }

    public static function enMinutes(int $secondes): string
    {
        if ($secondes < 60) {
            return 'quelques secondes';
        }
        $minutes = (int) ceil($secondes / 60);

        return $minutes <= 1 ? 'une minute' : ($minutes >= 60 ? 'une heure' : "{$minutes} minutes");
    }

    /**
     * @return array{0:int,1:int} [requetes autorisees, fenetre en secondes]
     */
    private static function regle(string $action): array
    {
        return self::REGLES[$action] ?? [60, 60];
    }

    /**
     * Adresse du client.
     *
     * Passe par clientIp() (SEC-13) : REMOTE_ADDR, sauf derriere un proxy
     * declare dans TRUSTED_PROXIES. Un en-tete ajoute par le client ne permet
     * donc pas de repartir a zero a chaque requete.
     */
    public static function adresse(): string
    {
        return substr(function_exists('clientIp') ? clientIp() : (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
    }

    /**
     * Purge des lignes hors de toute fenetre. Une requete sur cinquante, pour
     * ne pas payer un DELETE a chaque appel.
     */
    private static function purgerParfois(): void
    {
        if (random_int(1, 50) !== 1) {
            return;
        }

        self::purger();
    }

    /**
     * Retire les lignes hors de toute fenetre. Publique pour qu'une tache
     * planifiee ou un test puisse la declencher sans attendre le hasard.
     */
    public static function purger(): void
    {
        $db = self::db();
        if (!$db) {
            return;
        }

        try {
            $db->exec('DELETE FROM rate_limit_hits WHERE created_at < (NOW() - INTERVAL 1 DAY)');
            // Les tentatives de connexion servent aussi de piste d'audit :
            // conservees 90 jours, comme prevu au plan.
            $db->exec('DELETE FROM login_attempts WHERE created_at < (NOW() - INTERVAL 90 DAY)');
        } catch (Throwable $e) {
            self::signaler('purge impossible : ' . $e->getMessage());
        }
    }

    public static function db(): ?PDO
    {
        $db = TchadokDatabase::getInstance()->getConnection();
        if (!$db) {
            return null;
        }

        // Tant que la migration SEC-12 n'est pas appliquee, la limitation est
        // inactive : mieux vaut un site ouvert qu'un site en panne. L'absence
        // est journalisee pour que l'exploitant la voie.
        if (self::$tableDisponible === null) {
            try {
                $db->query('SELECT 1 FROM rate_limit_hits LIMIT 1');
                self::$tableDisponible = true;
            } catch (Throwable $e) {
                self::$tableDisponible = false;
                self::signaler('tables absentes : limitation de debit inactive');
            }
        }

        return self::$tableDisponible ? $db : null;
    }

    private static function signaler(string $message): void
    {
        error_log('[Tchadok][debit] ' . $message);
    }
}

final class VerrouConnexion
{
    /** Echecs toleres pour un couple identifiant + adresse, puis blocage. */
    private const SEUIL_COURT   = 5;
    private const BLOCAGE_COURT = 900;    // 15 minutes
    private const SEUIL_LONG    = 10;
    private const BLOCAGE_LONG  = 3600;   // 1 heure

    /** Fenetre d'observation des echecs. */
    private const FENETRE = 3600;

    /** Echecs toleres pour une adresse, tous identifiants confondus. */
    private const SEUIL_ADRESSE   = 20;
    private const FENETRE_ADRESSE = 900;

    /**
     * Secondes restantes avant de pouvoir retenter, 0 si l'acces est ouvert.
     */
    public static function attente(string $identifiant, ?string $ip = null): int
    {
        if (!LimiteDebit::active()) {
            return 0;
        }

        $db = LimiteDebit::db();
        if (!$db) {
            return 0;
        }

        $ip = $ip ?? LimiteDebit::adresse();

        try {
            // Echecs du couple depuis la derniere reussite : une connexion
            // reussie remet le compteur a zero.
            $stmt = $db->prepare(
                'SELECT COUNT(*) AS echecs, TIMESTAMPDIFF(SECOND, MAX(created_at), NOW()) AS depuis
                 FROM login_attempts
                 WHERE identifier = ? AND ip_address = ? AND success = 0
                   AND created_at > (NOW() - INTERVAL ? SECOND)'
            );
            $stmt->execute([self::normaliser($identifiant), $ip, self::FENETRE]);
            $couple = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['echecs' => 0, 'depuis' => 0];

            $echecs = (int) $couple['echecs'];
            $depuis = (int) $couple['depuis'];

            if ($echecs >= self::SEUIL_LONG) {
                return max(0, self::BLOCAGE_LONG - $depuis);
            }
            if ($echecs >= self::SEUIL_COURT) {
                return max(0, self::BLOCAGE_COURT - $depuis);
            }

            // Garde par adresse : beaucoup de comptes essayes depuis un meme
            // poste, ce qu'aucun usage normal ne produit.
            $stmt = $db->prepare(
                'SELECT COUNT(*) AS echecs, TIMESTAMPDIFF(SECOND, MAX(created_at), NOW()) AS depuis
                 FROM login_attempts
                 WHERE ip_address = ? AND success = 0 AND created_at > (NOW() - INTERVAL ? SECOND)'
            );
            $stmt->execute([$ip, self::FENETRE_ADRESSE]);
            $adresse = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['echecs' => 0, 'depuis' => 0];

            if ((int) $adresse['echecs'] >= self::SEUIL_ADRESSE) {
                return max(0, self::FENETRE_ADRESSE - (int) $adresse['depuis']);
            }
        } catch (Throwable $e) {
            error_log('[Tchadok][verrou] verification impossible : ' . $e->getMessage());
            return 0;
        }

        return 0;
    }

    /**
     * Message affiche pendant un verrouillage. Volontairement identique que le
     * compte existe ou non.
     */
    public static function message(int $secondes): string
    {
        return 'Trop de tentatives de connexion. Reessayez dans '
            . LimiteDebit::enMinutes($secondes) . '.';
    }

    public static function echec(string $identifiant): void
    {
        self::enregistrer($identifiant, false);
    }

    /**
     * Trace une connexion sans toucher au compteur d'echecs.
     *
     * Utilisee pour les connexions automatiques par cookie (SEC-11) : elles
     * doivent figurer dans l'historique presente a l'utilisateur (SEC-14),
     * mais un cookie vole ne doit pas suffire a lever un verrou en cours.
     */
    public static function tracer(string $identifiant, bool $reussite): void
    {
        self::enregistrer($identifiant, $reussite);
    }

    /**
     * Connexion reussie : la trace est conservee, et les echecs du couple sont
     * effaces pour que le compteur reparte de zero.
     */
    public static function reussite(string $identifiant): void
    {
        self::enregistrer($identifiant, true);

        $db = LimiteDebit::db();
        if (!$db) {
            return;
        }

        try {
            $db->prepare('DELETE FROM login_attempts WHERE identifier = ? AND ip_address = ? AND success = 0')
               ->execute([self::normaliser($identifiant), LimiteDebit::adresse()]);
        } catch (Throwable $e) {
            error_log('[Tchadok][verrou] remise a zero impossible : ' . $e->getMessage());
        }
    }

    private static function enregistrer(string $identifiant, bool $reussite): void
    {
        $db = LimiteDebit::db();
        if (!$db) {
            return;
        }

        try {
            $db->prepare(
                'INSERT INTO login_attempts (identifier, ip_address, success, user_agent) VALUES (?, ?, ?, ?)'
            )->execute([
                self::normaliser($identifiant),
                LimiteDebit::adresse(),
                $reussite ? 1 : 0,
                substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            ]);
        } catch (Throwable $e) {
            error_log('[Tchadok][verrou] enregistrement impossible : ' . $e->getMessage());
        }
    }

    private static function normaliser(string $identifiant): string
    {
        return substr(mb_strtolower(trim($identifiant)), 0, 190);
    }
}
