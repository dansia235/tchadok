<?php
/**
 * Politique des secrets de production - Tchadok Platform
 *
 * Tache CFG-05.
 *
 * Regle unique, partagee par :
 *   - scripts/env-switch.php        (refus de bascule)
 *   - includes/environment-guard.php (arret a l'execution)
 *
 * Elle est isolee ici, sans aucune dependance, pour deux raisons :
 * env-switch doit pouvoir s'executer meme quand la configuration est
 * cassee, et deux listes maintenues separement finissent toujours par
 * diverger.
 *
 * PRINCIPE
 *
 * Sur le poste de developpement, les identifiants sont simples et connus :
 * c'est un choix assume. Cette politique garantit qu'ils ne peuvent pas
 * atteindre la production -- y compris par simple recopie de .env.local
 * dans .env.production.
 */

declare(strict_types=1);

final class ProductionSecretPolicy
{
    /** Valeurs de modele : le secret n'a jamais ete renseigne. */
    public const MOTIFS_MODELE = [
        'REMPLACER', 'CHANGE-ME', 'CHANGEZ', 'YOUR_', 'votre-',
        'A_DEFINIR', 'mock-', 'hackme', 'change-me', 'a-changer',
    ];

    /**
     * Valeurs utilisees sur les postes de developpement.
     * Leur presence en production signifie qu'une configuration locale a ete
     * recopiee. Comparaison insensible a la casse, sur la valeur entiere.
     */
    public const VALEURS_LOCALES_CONNUES = [
        'dansia',
        'tchadok2026',
        'tchadok',
        'tchadok_local',
        'password',
        'password123',
        '12345678',
        'admin',
        'secret',
    ];

    /**
     * Fragments trahissant une valeur locale, recherches a l'interieur de
     * la valeur (cles locales du modele .env.local.example).
     */
    public const FRAGMENTS_LOCAUX = [
        'cle-locale',
        'secret-session-local',
        'non-secrete',
        'your-local',
    ];

    /**
     * Longueur minimale en production, par cle.
     * 16 caracteres aleatoires ~ 95 bits : hors de portee d'une attaque
     * par force brute en ligne. 32 pour les cles cryptographiques.
     */
    public const LONGUEURS_MINIMALES = [
        'DB_PASSWORD'            => 16,
        'BACKUP_DB_PASSWORD'     => 16,
        'APP_KEY'                => 32,
        'SESSION_SECRET'         => 32,
        'MAIL_PASSWORD'          => 12,
        'ICECAST_ADMIN_PASSWORD' => 16,
        'AIRTEL_WEBHOOK_SECRET'  => 24,
        'MOOV_WEBHOOK_SECRET'    => 24,
        'VISA_WEBHOOK_SECRET'    => 24,
        'GIMAC_WEBHOOK_SECRET'  => 24,
    ];

    /** Cles controlees. Les identifiants de compte sont inclus. */
    public const CLES_SENSIBLES = [
        'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD',
        'BACKUP_DB_USERNAME', 'BACKUP_DB_PASSWORD',
        'APP_KEY', 'SESSION_SECRET',
        'MAIL_PASSWORD',
        'AIRTEL_API_KEY', 'AIRTEL_WEBHOOK_SECRET',
        'MOOV_API_KEY', 'MOOV_WEBHOOK_SECRET',
        'VISA_API_KEY', 'VISA_WEBHOOK_SECRET',
        'GIMAC_API_KEY', 'GIMAC_WEBHOOK_SECRET',
        'ICECAST_ADMIN_PASSWORD',
    ];

    /** Comptes MySQL interdits comme compte applicatif en production. */
    public const COMPTES_INTERDITS = ['root', 'admin', 'dansia', 'mysql'];

    /** Reglages imposes en production. */
    public const REGLAGES_IMPOSES = [
        'APP_DEBUG'       => 'false',
        'PAYMENT_DRIVER'  => 'live',
        'ALLOW_DEV_TOOLS' => 'false',
        'SESSION_SECURE'  => 'true',
    ];

    /**
     * Retourne la liste des violations. Tableau vide = conforme.
     *
     * Les messages ne contiennent JAMAIS la valeur du secret : ils sont
     * journalises, et un journal ne doit pas devenir une fuite.
     *
     * @param array<string,string|null> $vars
     * @return string[]
     */
    public static function violations(array $vars): array
    {
        $violations = [];

        foreach (self::CLES_SENSIBLES as $cle) {
            if (!array_key_exists($cle, $vars)) {
                // Absence : signalee par le controle de presence, pas ici,
                // pour ne pas doubler les messages.
                continue;
            }

            $valeur = trim((string) $vars[$cle]);

            if ($valeur === '') {
                $violations[] = "{$cle} est vide.";
                continue;
            }

            if (self::estValeurModele($valeur)) {
                $violations[] = "{$cle} porte encore une valeur de modele.";
                continue;
            }

            if (self::estValeurLocale($valeur)) {
                $violations[] = "{$cle} porte une valeur de developpement connue : "
                    . "la configuration locale a ete recopiee en production.";
                continue;
            }

            if (isset(self::LONGUEURS_MINIMALES[$cle])) {
                $min = self::LONGUEURS_MINIMALES[$cle];
                $len = mb_strlen($valeur);
                if ($len < $min) {
                    $violations[] = "{$cle} est trop court ({$len} caracteres, {$min} minimum).";
                }
            }
        }

        // Compte applicatif privilegie
        foreach (['DB_USERNAME', 'BACKUP_DB_USERNAME'] as $cle) {
            $compte = strtolower(trim((string) ($vars[$cle] ?? '')));
            if ($compte !== '' && in_array($compte, self::COMPTES_INTERDITS, true)) {
                $violations[] = "{$cle} utilise un compte interdit en production ('{$compte}'). "
                    . "Creer un compte dedie aux droits limites.";
            }
        }

        // Compte applicatif et compte de sauvegarde identiques
        $app    = strtolower(trim((string) ($vars['DB_USERNAME'] ?? '')));
        $backup = strtolower(trim((string) ($vars['BACKUP_DB_USERNAME'] ?? '')));
        if ($app !== '' && $backup !== '' && $app === $backup) {
            $violations[] = "BACKUP_DB_USERNAME est identique a DB_USERNAME. En production, le compte "
                . "de sauvegarde doit etre distinct et en lecture seule.";
        }

        // Cles reutilisees entre elles
        $appKey  = (string) ($vars['APP_KEY'] ?? '');
        $session = (string) ($vars['SESSION_SECRET'] ?? '');
        if ($appKey !== '' && $appKey === $session) {
            $violations[] = "APP_KEY et SESSION_SECRET sont identiques.";
        }

        // Reglages imposes
        foreach (self::REGLAGES_IMPOSES as $cle => $attendu) {
            $actuel = strtolower(trim((string) ($vars[$cle] ?? '')));
            if ($actuel !== $attendu) {
                $violations[] = sprintf(
                    "%s doit valoir '%s' en production (valeur actuelle : '%s').",
                    $cle,
                    $attendu,
                    $actuel === '' ? 'absente' : $actuel
                );
            }
        }

        // HTTPS
        foreach (['APP_URL', 'SITE_URL', 'PAYMENT_CALLBACK_BASE'] as $cle) {
            $url = trim((string) ($vars[$cle] ?? ''));
            if ($url !== '' && !str_starts_with($url, 'https://')) {
                $violations[] = "{$cle} doit etre en HTTPS en production.";
            }
            if ($url !== '' && (str_contains($url, 'localhost') || str_contains($url, '127.0.0.1'))) {
                $violations[] = "{$cle} pointe vers une adresse locale.";
            }
        }

        // Passerelles de paiement pointant vers les simulateurs
        foreach (['AIRTEL', 'MOOV', 'VISA', 'GIMAC'] as $passerelle) {
            $url = (string) ($vars[$passerelle . '_BASE_URL'] ?? '');
            if (str_contains($url, '127.0.0.1') || str_contains($url, 'localhost')) {
                $violations[] = "{$passerelle}_BASE_URL pointe vers un simulateur local.";
            }
        }

        return $violations;
    }

    public static function estValeurModele(string $valeur): bool
    {
        foreach (self::MOTIFS_MODELE as $motif) {
            if (stripos($valeur, $motif) !== false) {
                return true;
            }
        }
        return false;
    }

    public static function estValeurLocale(string $valeur): bool
    {
        $v = strtolower(trim($valeur));

        if (in_array($v, self::VALEURS_LOCALES_CONNUES, true)) {
            return true;
        }

        foreach (self::FRAGMENTS_LOCAUX as $fragment) {
            if (str_contains($v, $fragment)) {
                return true;
            }
        }

        return false;
    }
}
