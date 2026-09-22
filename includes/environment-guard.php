<?php
/**
 * Garde-fou de coherence d'environnement - Tchadok Platform
 *
 * Tache CFG-05.
 *
 * Une erreur de deploiement ne doit jamais etre silencieuse.
 *
 * Le cas redoute : le code part en production, mais .htaccess n'a pas ete
 * regenere depuis .htaccess.production. Apache ne lit que le fichier nomme
 * exactement ".htaccess" -- en son absence, AUCUNE regle ne s'applique :
 * ni blocage des fichiers sensibles, ni en-tetes de securite, ni HTTPS.
 * Le site repond normalement, et rien ne signale le probleme.
 *
 * Ce garde transforme ce genre de situation en erreur bruyante.
 *
 * En PRODUCTION : une anomalie critique arrete l'application (503).
 * En LOCAL      : elle s'affiche en bandeau, sans jamais bloquer le travail.
 *
 * Cout : quelques appels a is_file() et des comparaisons de chaines.
 * Aucun acces reseau, aucune requete base.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/production-secret-policy.php';

final class EnvironmentGuard
{
    /** Presents en production = anomalie. */
    private const FICHIERS_DEVELOPPEMENT = [
        '.env.local'              => 'configuration de developpement',
        '.htaccess.local'         => 'source Apache de developpement',
        'mock-gateways'           => 'simulateurs de paiement',
        'tests'                   => 'jeu de tests',
        'database/seeds/demo.sql' => 'jeu de demonstration',
    ];

    private static bool  $execute = false;
    private static array $critiques = [];
    private static array $avertissements = [];
    private static bool  $infraProduction = false;

    public static function verifier(): void
    {
        if (self::$execute) {
            return;
        }
        self::$execute = true;

        $racine     = dirname(__DIR__);
        $production = EnvLoader::isProduction();

        // L'INFRASTRUCTURE ressemble-t-elle a de la production ?
        //
        // Distinct de l'environnement charge, et c'est le point essentiel :
        // si .env.local est oublie sur un serveur de production, il prend la
        // priorite sur .env.production et l'application demarre en mode
        // developpement -- debug actif, paiements simules -- sans qu'aucun
        // controle "production" ne se declenche, puisqu'elle se croit locale.
        //
        // On se fie donc aussi a des indices d'infrastructure, qu'un fichier
        // d'environnement oublie ne peut pas masquer.
        self::$infraProduction = self::detecterInfraProduction($racine);

        self::controlerHtaccess($racine, $production);
        self::controlerIncoherencesEnv();

        if ($production) {
            self::controlerProduction($racine);
        } else {
            self::controlerLocal();
            if (self::$infraProduction) {
                self::controlerLocalSurInfraProduction();
            }
        }

        self::controlerStockage($racine);

        foreach (array_merge(self::$critiques, self::$avertissements) as $m) {
            error_log('[Tchadok][guard] ' . $m);
        }

        if (($production || self::$infraProduction) && self::$critiques !== []) {
            self::arreter();
        }
    }

    /**
     * Indices qu'on tourne sur une infrastructure de production, quelle que
     * soit la configuration chargee.
     */
    private static function detecterInfraProduction(string $racine): bool
    {
        // 1. Le .htaccess en place a-t-il ete genere depuis la source production ?
        $htaccess = $racine . '/.htaccess';
        $source   = $racine . '/.htaccess.production';
        if (is_file($htaccess) && is_file($source)
            && self::empreinte($htaccess) === self::empreinte($source)) {
            return true;
        }

        // 2. Le marqueur ecrit par scripts/env-switch.php
        $marqueur = $racine . '/storage/.environment';
        if (is_file($marqueur)) {
            $donnees = json_decode((string) file_get_contents($marqueur), true);
            if (is_array($donnees) && ($donnees['environnement'] ?? '') === 'production') {
                return true;
            }
        }

        return false;
    }

    /**
     * Cas le plus dangereux : infrastructure de production, configuration de
     * developpement. L'application se croit locale et desactive ses propres
     * garde-fous.
     */
    private static function controlerLocalSurInfraProduction(): void
    {
        self::$critiques[] =
            "Configuration de DEVELOPPEMENT chargee sur une infrastructure de PRODUCTION. "
            . "Le fichier " . basename(EnvLoader::fichierCharge()) . " a pris la priorite sur "
            . ".env.production. L'application tournerait avec APP_DEBUG actif et des paiements "
            . "simules. Retirer .env.local du serveur.";
    }

    // -----------------------------------------------------------------
    // Controles
    // -----------------------------------------------------------------

    private static function controlerHtaccess(string $racine, bool $production): void
    {
        $htaccess = $racine . '/.htaccess';

        if (!is_file($htaccess)) {
            self::$critiques[] =
                "Le fichier .htaccess est ABSENT. Apache n'applique aucune regle de securite : "
                . "les fichiers sensibles sont accessibles, les en-tetes de securite ne sont pas poses, "
                . "et la redirection HTTPS ne s'applique pas. "
                . "Corriger avec : php scripts/env-switch.php " . EnvLoader::environment();
            return;
        }

        // Le .htaccess correspond-il a l'environnement charge ?
        $attendu  = $racine . ($production ? '/.htaccess.production' : '/.htaccess.local');
        $autre    = $racine . ($production ? '/.htaccess.local' : '/.htaccess.production');

        $empreinteActuelle = self::empreinte($htaccess);

        if (is_file($attendu) && self::empreinte($attendu) === $empreinteActuelle) {
            return; // coherent
        }

        if (is_file($autre) && self::empreinte($autre) === $empreinteActuelle) {
            $message = sprintf(
                "Le fichier .htaccess a ete genere depuis %s alors que la configuration chargee est '%s'. "
                . "Les regles Apache ne correspondent pas a l'environnement. "
                . "Corriger avec : php scripts/env-switch.php %s",
                basename($autre),
                EnvLoader::environment(),
                EnvLoader::environment()
            );
            // En production, servir avec les regles de developpement est critique
            if ($production) {
                self::$critiques[] = $message;
            } else {
                self::$avertissements[] = $message;
            }
            return;
        }

        self::$avertissements[] =
            ".htaccess ne correspond a aucune source connue (.htaccess.local ni .htaccess.production). "
            . "Il a probablement ete modifie a la main : reportez vos modifications dans la source, "
            . "sinon elles seront perdues a la prochaine bascule.";
    }

    private static function controlerIncoherencesEnv(): void
    {
        foreach (EnvLoader::incoherences() as $i) {
            self::$avertissements[] = $i;
        }
    }

    private static function controlerProduction(string $racine): void
    {
        // 1 a 4. Secrets, valeurs locales recopiees, longueurs minimales,
        // reglages imposes (APP_DEBUG, PAYMENT_DRIVER, ALLOW_DEV_TOOLS,
        // SESSION_SECURE), HTTPS, passerelles pointant vers les simulateurs.
        //
        // La regle est dans includes/production-secret-policy.php, partagee
        // avec scripts/env-switch.php : ce qui est refuse a la bascule l'est
        // aussi a l'execution, sans risque de divergence entre deux listes.
        $violations = ProductionSecretPolicy::violations(EnvLoader::all());

        if ($violations !== []) {
            self::$critiques[] = sprintf(
                "Configuration de production non conforme (%d point(s)) : %s",
                count($violations),
                implode(' | ', $violations)
            );
        }

        // 5. Fichiers de developpement presents sur le serveur
        foreach (self::FICHIERS_DEVELOPPEMENT as $relatif => $description) {
            if (file_exists($racine . '/' . $relatif)) {
                // .env.local est critique : il prendrait la priorite sur .env.production
                if ($relatif === '.env.local') {
                    self::$critiques[] =
                        ".env.local est present sur un serveur de production. Il prend la priorite "
                        . "sur .env.production : l'application tournerait avec la configuration "
                        . "de developpement.";
                } else {
                    self::$avertissements[] = sprintf(
                        "%s (%s) est present en production et devrait etre retire.",
                        $relatif,
                        $description
                    );
                }
            }
        }
    }

    private static function controlerLocal(): void
    {
        // Informatif dans les deux sens : un pilote 'live' en local enverrait
        // de vraies requetes aux operateurs, avec de vrais debits.
        $pilote = strtolower(trim((string) EnvLoader::get('PAYMENT_DRIVER', '')));
        if ($pilote === 'live') {
            self::$avertissements[] =
                "PAYMENT_DRIVER vaut 'live' en local : les appels partiraient vers les vraies "
                . "passerelles de paiement. Utilisez 'mock' avec les simulateurs.";
        }

        if (strtolower(trim((string) EnvLoader::get('MAIL_DRIVER', ''))) === 'smtp') {
            self::$avertissements[] =
                "MAIL_DRIVER vaut 'smtp' en local : des courriels de test pourraient partir "
                . "vers de vraies adresses. Utilisez 'log'.";
        }
    }

    private static function controlerStockage(string $racine): void
    {
        $storage = $racine . '/storage';
        if (!is_dir($storage)) {
            return;
        }

        // Le repertoire est-il sous la racine web ET sans protection ?
        // Controle par systeme de fichiers uniquement : aucun appel reseau.
        $protegeParHtaccess = is_file($storage . '/.htaccess');
        $bloqueParRegleGlobale = false;

        $htaccess = $racine . '/.htaccess';
        if (is_file($htaccess)) {
            $contenu = (string) file_get_contents($htaccess);
            $bloqueParRegleGlobale = str_contains($contenu, 'storage');
        }

        if (!$protegeParHtaccess && !$bloqueParRegleGlobale) {
            self::$avertissements[] =
                "Le repertoire storage/ est sous la racine web et n'est protege ni par un "
                . ".htaccess local, ni par une regle globale. Les fichiers deposes et les "
                . "journaux pourraient etre telecharges directement (tache SEC-06).";
        }
    }

    // -----------------------------------------------------------------
    // Utilitaires
    // -----------------------------------------------------------------

    private static function empreinte(string $chemin): string
    {
        $h = @hash_file('sha256', $chemin);
        return $h === false ? '' : $h;
    }

    // -----------------------------------------------------------------
    // Restitution
    // -----------------------------------------------------------------

    /** @return string[] */
    public static function critiques(): array
    {
        return self::$critiques;
    }

    /** @return string[] */
    public static function avertissements(): array
    {
        return self::$avertissements;
    }

    public static function aDesAnomalies(): bool
    {
        return self::$critiques !== [] || self::$avertissements !== [];
    }

    /**
     * Bandeau discret, affiche uniquement en local et uniquement aux pages
     * HTML. Ne bloque jamais le travail.
     */
    public static function bandeau(): string
    {
        if (EnvLoader::isProduction() || !self::aDesAnomalies()) {
            return '';
        }

        $lignes = '';
        foreach (array_merge(self::$critiques, self::$avertissements) as $m) {
            $lignes .= '<li>' . htmlspecialchars($m, ENT_QUOTES, 'UTF-8') . '</li>';
        }

        return '<div style="position:fixed;bottom:0;left:0;right:0;z-index:99999;'
             . 'background:#3b2308;border-top:2px solid #f59e0b;color:#fde68a;'
             . 'font:12px/1.5 system-ui,sans-serif;padding:.6rem 1rem;max-height:30vh;overflow:auto">'
             . '<strong style="color:#fbbf24">Environnement local &mdash; anomalies de configuration</strong>'
             . '<ul style="margin:.4rem 0 0;padding-left:1.2rem">' . $lignes . '</ul>'
             . '</div>';
    }

    /**
     * Arret en production : page 503 sans aucun detail technique.
     * Les anomalies sont dans les journaux, pas a l'ecran.
     */
    private static function arreter(): void
    {
        $reference = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        error_log(sprintf(
            '[Tchadok][guard] ARRET (reference %s) : %d anomalie(s) critique(s).',
            $reference,
            count(self::$critiques)
        ));

        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, "\n[Tchadok] Configuration de production invalide.\n\n");
            foreach (self::$critiques as $i => $m) {
                fwrite(STDERR, sprintf("  %d. %s\n\n", $i + 1, $m));
            }
            exit(1);
        }

        if (!headers_sent()) {
            http_response_code(503);
            header('Content-Type: text/html; charset=utf-8');
            header('Retry-After: 600');
            header('Cache-Control: no-store');
        }

        echo '<!doctype html><html lang="fr"><head><meta charset="utf-8">'
           . '<meta name="viewport" content="width=device-width,initial-scale=1">'
           . '<title>Service indisponible</title>'
           . '<style>body{font:15px/1.6 system-ui,sans-serif;background:#0B0F17;color:#E6EAF2;'
           . 'margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem}'
           . 'main{max-width:34rem;text-align:center}h1{font-size:1.5rem;margin:0 0 .75rem}'
           . 'p{color:#A4AEC2;margin:0 0 1rem}code{background:#141A26;border:1px solid #222B3B;'
           . 'border-radius:6px;padding:.2rem .5rem;font-size:.9em}</style></head><body><main>'
           . '<h1>Service momentanement indisponible</h1>'
           . '<p>La plateforme est en cours de maintenance. Merci de reessayer dans quelques minutes.</p>'
           . '<p>Reference : <code>' . $reference . '</code></p>'
           . '</main></body></html>';
        exit(1);
    }
}

EnvironmentGuard::verifier();
