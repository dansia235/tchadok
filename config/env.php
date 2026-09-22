<?php
/**
 * Chargeur de variables d'environnement - Tchadok Platform
 *
 * Tache CFG-01.
 *
 * ORDRE DE RESOLUTION, STRICT :
 *   1. .env.local       present  -> environnement LOCAL
 *   2. .env.production  present  -> environnement PRODUCTION
 *   3. aucun des deux            -> erreur fatale explicite
 *
 * Le comportement precedent cherchait .env puis se rabattait silencieusement
 * sur .env.production : un poste de developpement sans .env chargeait donc la
 * configuration de production sans le moindre avertissement.
 *
 * L'environnement actif est deduit du FICHIER EFFECTIVEMENT CHARGE, et non
 * d'une variable APP_ENV que l'on peut oublier de changer. Une incoherence
 * entre les deux est journalisee.
 *
 * Aucune valeur de repli n'est fournie pour un secret : une variable
 * obligatoire absente doit echouer bruyamment.
 */

class EnvLoader
{
    public const ENV_LOCAL      = 'local';
    public const ENV_PRODUCTION = 'production';

    /** Fichiers candidats, par ordre de priorite. */
    private const FICHIERS = [
        '.env.local'      => self::ENV_LOCAL,
        '.env.production' => self::ENV_PRODUCTION,
    ];

    /**
     * CFG-01 / SEC-03 : seules ces cles deviennent des constantes PHP globales.
     *
     * Auparavant, CHAQUE ligne du fichier d'environnement etait promue en
     * constante : DB_PASSWORD, APP_KEY, SESSION_SECRET et
     * ICECAST_ADMIN_PASSWORD etaient donc lisibles depuis n'importe quel point
     * du code, et visibles dans un get_defined_constants() ou une trace
     * d'erreur. Les secrets restent desormais accessibles uniquement via env().
     */
    private const CONSTANTES_PUBLIQUES = [
        'APP_NAME',
        'APP_TIMEZONE',
        'APP_URL',
    ];

    private static bool   $charge      = false;
    private static array  $vars        = [];
    private static string $fichier     = '';
    private static string $environment = '';
    private static array  $incoherences = [];

    // -----------------------------------------------------------------
    // Chargement
    // -----------------------------------------------------------------

    public static function load(?string $chemin = null): void
    {
        if (self::$charge) {
            return;
        }

        $racine = dirname(__DIR__);

        if ($chemin !== null) {
            if (!is_readable($chemin)) {
                self::echouer("Fichier d'environnement illisible : {$chemin}");
            }
            self::$fichier     = $chemin;
            self::$environment = self::ENV_LOCAL;
        } else {
            foreach (self::FICHIERS as $nom => $env) {
                $candidat = $racine . DIRECTORY_SEPARATOR . $nom;
                if (is_readable($candidat)) {
                    self::$fichier     = $candidat;
                    self::$environment = $env;
                    break;
                }
            }
        }

        if (self::$fichier === '') {
            self::echouer(
                "Aucun fichier d'environnement trouve.\n\n"
                . "Tchadok attend, dans cet ordre :\n"
                . "  1. {$racine}" . DIRECTORY_SEPARATOR . ".env.local       (poste de developpement)\n"
                . "  2. {$racine}" . DIRECTORY_SEPARATOR . ".env.production  (serveur)\n\n"
                . "En local : copiez .env.local.example vers .env.local, puis renseignez\n"
                . "les valeurs. Voir docs/exploitation/pre-requis.md."
            );
        }

        self::$vars  = self::parser(self::$fichier);
        self::$charge = true;

        self::promouvoirConstantes();
        self::controlerCoherence();
        self::appliquerReglages();
    }

    /**
     * Analyse un fichier au format KEY=VALUE.
     *
     * Gere : commentaires en debut de ligne et en fin de ligne, valeurs entre
     * guillemets simples ou doubles, valeurs vides, espaces superflus, et
     * valeurs multi-lignes delimitees par des guillemets doubles.
     */
    private static function parser(string $chemin): array
    {
        $contenu = file_get_contents($chemin);
        if ($contenu === false) {
            self::echouer("Lecture impossible : {$chemin}");
        }

        $contenu = str_replace(["\r\n", "\r"], "\n", $contenu);
        $lignes  = explode("\n", $contenu);
        $vars    = [];

        $cleEnCours     = null;
        $valeurEnCours  = '';

        foreach ($lignes as $numero => $ligne) {
            // Poursuite d'une valeur multi-lignes
            if ($cleEnCours !== null) {
                $fin = strpos($ligne, '"');
                if ($fin === false) {
                    $valeurEnCours .= "\n" . $ligne;
                    continue;
                }
                $valeurEnCours .= "\n" . substr($ligne, 0, $fin);
                $vars[$cleEnCours] = $valeurEnCours;
                $cleEnCours    = null;
                $valeurEnCours = '';
                continue;
            }

            $brute = trim($ligne);
            if ($brute === '' || $brute[0] === '#') {
                continue;
            }

            // Tolere la forme "export CLE=valeur"
            if (str_starts_with($brute, 'export ')) {
                $brute = trim(substr($brute, 7));
            }

            $pos = strpos($brute, '=');
            if ($pos === false || $pos === 0) {
                continue;
            }

            $cle = trim(substr($brute, 0, $pos));
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_.]*$/', $cle)) {
                continue;
            }

            $valeur = ltrim(substr($brute, $pos + 1));

            if ($valeur !== '' && ($valeur[0] === '"' || $valeur[0] === "'")) {
                $guillemet = $valeur[0];
                $reste     = substr($valeur, 1);
                $fin       = strpos($reste, $guillemet);

                if ($fin === false && $guillemet === '"') {
                    // Valeur multi-lignes : on poursuit sur les lignes suivantes
                    $cleEnCours    = $cle;
                    $valeurEnCours = $reste;
                    continue;
                }

                $valeur = $fin === false ? $reste : substr($reste, 0, $fin);
            } else {
                // Commentaire de fin de ligne, uniquement s'il est precede d'un espace.
                // Evite de tronquer un mot de passe contenant un caractere '#'.
                if (preg_match('/\s+#/', $valeur, $m, PREG_OFFSET_CAPTURE)) {
                    $valeur = substr($valeur, 0, $m[0][1]);
                }
                $valeur = rtrim($valeur);
            }

            $vars[$cle]   = $valeur;
            $_ENV[$cle]    = $valeur;
            $_SERVER[$cle] = $valeur;
        }

        if ($cleEnCours !== null) {
            self::echouer(
                "Valeur multi-lignes non fermee pour la cle '{$cleEnCours}' dans "
                . basename($chemin) . '. Guillemet fermant manquant.'
            );
        }

        return $vars;
    }

    private static function promouvoirConstantes(): void
    {
        foreach (self::CONSTANTES_PUBLIQUES as $cle) {
            if (isset(self::$vars[$cle]) && !defined($cle)) {
                define($cle, self::$vars[$cle]);
            }
        }

        // APP_ENV et APP_DEBUG sont derives de l'environnement reel, pas de la
        // valeur brute du fichier : le fichier charge fait foi.
        if (!defined('APP_ENV')) {
            define('APP_ENV', self::$environment);
        }
        if (!defined('APP_DEBUG')) {
            define('APP_DEBUG', self::isDevelopment() && self::bool('APP_DEBUG', true));
        }
    }

    /**
     * Signale les ecarts entre ce que le fichier declare et ce qu'il est.
     * N'interrompt pas : le garde-fou d'environnement (CFG-05) decide des
     * consequences.
     */
    private static function controlerCoherence(): void
    {
        $declare = strtolower(trim((string) self::get('APP_ENV', '')));

        $equivalents = [
            'local'       => self::ENV_LOCAL,
            'dev'         => self::ENV_LOCAL,
            'development' => self::ENV_LOCAL,
            'production'  => self::ENV_PRODUCTION,
            'prod'        => self::ENV_PRODUCTION,
        ];

        if ($declare !== '' && isset($equivalents[$declare]) && $equivalents[$declare] !== self::$environment) {
            $message = sprintf(
                "Incoherence d'environnement : le fichier %s a ete charge (environnement '%s') "
                . "mais il declare APP_ENV=%s. C'est le fichier charge qui fait foi.",
                basename(self::$fichier),
                self::$environment,
                $declare
            );
            self::$incoherences[] = $message;
            error_log('[Tchadok][env] ' . $message);
        }
    }

    private static function appliquerReglages(): void
    {
        date_default_timezone_set(self::get('APP_TIMEZONE', 'Africa/Ndjamena'));

        if (self::isDevelopment()) {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
            ini_set('display_startup_errors', '1');
        } else {
            error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
            ini_set('display_errors', '0');
            ini_set('display_startup_errors', '0');
        }

        ini_set('log_errors', '1');

        $journal = dirname(__DIR__) . '/storage/logs/php-errors.log';
        if (is_dir(dirname($journal)) && is_writable(dirname($journal))) {
            ini_set('error_log', $journal);
        }
    }

    // -----------------------------------------------------------------
    // Lecture
    // -----------------------------------------------------------------

    public static function get(string $cle, $defaut = null)
    {
        if (!self::$charge) {
            self::load();
        }

        if (array_key_exists($cle, self::$vars)) {
            return self::$vars[$cle];
        }

        // Variables reellement issues du processus (conteneur, vhost...)
        $depuisProcessus = getenv($cle);
        if ($depuisProcessus !== false) {
            return $depuisProcessus;
        }

        return $defaut;
    }

    /**
     * Valeur obligatoire. Leve une exception si absente ou vide.
     * A utiliser pour tout secret : jamais de valeur de repli.
     */
    public static function require(string $cle): string
    {
        $valeur = self::get($cle);

        if ($valeur === null || trim((string) $valeur) === '') {
            throw new RuntimeException(sprintf(
                "Variable d'environnement obligatoire absente : %s (fichier : %s)",
                $cle,
                self::$fichier !== '' ? basename(self::$fichier) : 'aucun'
            ));
        }

        return (string) $valeur;
    }

    /**
     * Verifie la presence de plusieurs cles et les signale TOUTES en une fois.
     * Corriger dix variables manquantes une par une est une perte de temps.
     *
     * @param string[] $cles
     * @return string[] les cles manquantes (tableau vide si tout est present)
     */
    public static function requireKeys(array $cles): array
    {
        if (!self::$charge) {
            self::load();
        }

        $manquantes = [];
        foreach ($cles as $cle) {
            $valeur = self::get($cle);
            if ($valeur === null || trim((string) $valeur) === '') {
                $manquantes[] = $cle;
            }
        }

        return $manquantes;
    }

    public static function bool(string $cle, bool $defaut = false): bool
    {
        $valeur = self::get($cle);
        if ($valeur === null || $valeur === '') {
            return $defaut;
        }

        return in_array(strtolower(trim((string) $valeur)), ['1', 'true', 'yes', 'on', 'oui'], true);
    }

    public static function int(string $cle, int $defaut = 0): int
    {
        $valeur = self::get($cle);
        return ($valeur === null || $valeur === '') ? $defaut : (int) $valeur;
    }

    public static function has(string $cle): bool
    {
        if (!self::$charge) {
            self::load();
        }
        return array_key_exists($cle, self::$vars) || getenv($cle) !== false;
    }

    /** Toutes les variables. Ne jamais afficher ce tableau : il contient les secrets. */
    public static function all(): array
    {
        if (!self::$charge) {
            self::load();
        }
        return self::$vars;
    }

    // -----------------------------------------------------------------
    // Environnement
    // -----------------------------------------------------------------

    /** 'local' ou 'production', deduit du fichier effectivement charge. */
    public static function environment(): string
    {
        if (!self::$charge) {
            self::load();
        }
        return self::$environment;
    }

    /** Chemin du fichier charge. Utilise par le garde-fou CFG-05. */
    public static function fichierCharge(): string
    {
        if (!self::$charge) {
            self::load();
        }
        return self::$fichier;
    }

    /** @return string[] */
    public static function incoherences(): array
    {
        if (!self::$charge) {
            self::load();
        }
        return self::$incoherences;
    }

    public static function isDevelopment(): bool
    {
        return self::environment() === self::ENV_LOCAL;
    }

    public static function isProduction(): bool
    {
        return self::environment() === self::ENV_PRODUCTION;
    }

    public static function getSiteUrl(): string
    {
        return rtrim((string) self::get('SITE_URL', self::get('APP_URL', 'http://localhost')), '/');
    }

    // -----------------------------------------------------------------
    // Erreur de demarrage
    // -----------------------------------------------------------------

    /**
     * Echec de configuration : l'application ne peut pas demarrer.
     * Affiche un message lisible plutot qu'une page blanche.
     */
    private static function echouer(string $message): void
    {
        error_log('[Tchadok][env] ' . $message);

        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, "\n[Tchadok] Erreur de configuration\n\n" . $message . "\n\n");
            exit(1);
        }

        if (!headers_sent()) {
            http_response_code(503);
            header('Content-Type: text/html; charset=utf-8');
            header('Retry-After: 300');
        }

        echo '<!doctype html><html lang="fr"><head><meta charset="utf-8">'
           . '<title>Configuration incomplete</title>'
           . '<style>body{font:14px/1.6 system-ui,sans-serif;background:#0B0F17;color:#E6EAF2;'
           . 'margin:0;padding:3rem 1.5rem;display:flex;justify-content:center}'
           . 'main{max-width:46rem}h1{font-size:1.4rem;margin:0 0 1rem}'
           . 'pre{background:#141A26;border:1px solid #222B3B;border-radius:12px;padding:1rem;'
           . 'overflow-x:auto;white-space:pre-wrap;color:#A4AEC2}</style></head><body><main>'
           . '<h1>Configuration incomplete</h1>'
           . '<p>L\'application ne peut pas demarrer.</p>'
           . '<pre>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</pre>'
           . '</main></body></html>';
        exit(1);
    }
}

// Chargement automatique
EnvLoader::load();

if (!function_exists('env')) {
    /** Lecture d'une variable d'environnement. */
    function env(string $cle, $defaut = null)
    {
        return EnvLoader::get($cle, $defaut);
    }
}

if (!function_exists('env_require')) {
    /** Lecture d'une variable obligatoire. A utiliser pour tout secret. */
    function env_require(string $cle): string
    {
        return EnvLoader::require($cle);
    }
}
