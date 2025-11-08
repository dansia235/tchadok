<?php
/**
 * Chargeur de variables d'environnement
 * Tchadok Platform
 *
 * Charge les variables depuis le fichier .env
 */

class EnvLoader {
    private static $loaded = false;
    private static $vars = [];

    /**
     * Charge le fichier .env
     */
    public static function load($path = null) {
        if (self::$loaded) {
            return;
        }

        if ($path === null) {
            $path = dirname(__DIR__) . '/.env';
        }

        if (!file_exists($path)) {
            // Si .env n'existe pas, essayer .env.production
            $productionPath = dirname(__DIR__) . '/.env.production';
            if (file_exists($productionPath)) {
                $path = $productionPath;
            } else {
                throw new Exception("Fichier .env introuvable. Veuillez copier .env.production en .env et configurer vos variables.");
            }
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            // Ignorer les commentaires
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // Parser la ligne KEY=VALUE
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);

                $key = trim($key);
                $value = trim($value);

                // Supprimer les guillemets
                $value = trim($value, '"\'');

                // Stocker dans $_ENV, $_SERVER et notre tableau
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
                self::$vars[$key] = $value;

                // Définir aussi comme constante si pas déjà définie
                if (!defined($key)) {
                    define($key, $value);
                }
            }
        }

        self::$loaded = true;
    }

    /**
     * Récupère une variable d'environnement
     */
    public static function get($key, $default = null) {
        if (!self::$loaded) {
            self::load();
        }

        if (isset(self::$vars[$key])) {
            return self::$vars[$key];
        }

        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }

        if (isset($_SERVER[$key])) {
            return $_SERVER[$key];
        }

        return $default;
    }

    /**
     * Vérifie si une variable existe
     */
    public static function has($key) {
        if (!self::$loaded) {
            self::load();
        }

        return isset(self::$vars[$key]) || isset($_ENV[$key]) || isset($_SERVER[$key]);
    }

    /**
     * Récupère toutes les variables
     */
    public static function all() {
        if (!self::$loaded) {
            self::load();
        }

        return self::$vars;
    }

    /**
     * Vérifie si on est en mode développement
     */
    public static function isDevelopment() {
        $env = self::get('APP_ENV', 'production');
        return $env === 'development' || $env === 'dev' || $env === 'local';
    }

    /**
     * Vérifie si on est en mode production
     */
    public static function isProduction() {
        return !self::isDevelopment();
    }

    /**
     * Récupère l'URL du site
     */
    public static function getSiteUrl() {
        return self::get('SITE_URL', self::get('APP_URL', 'http://localhost'));
    }
}

// Charger automatiquement les variables d'environnement
EnvLoader::load();

// Fonction helper pour accéder facilement aux variables
if (!function_exists('env')) {
    function env($key, $default = null) {
        return EnvLoader::get($key, $default);
    }
}

// Définir des constantes utiles si pas déjà définies
// Note: SITE_URL, SESSION_LIFETIME et CACHE_LIFETIME sont gérés par constants.php
if (!defined('APP_ENV')) {
    define('APP_ENV', env('APP_ENV', 'production'));
}

if (!defined('APP_DEBUG')) {
    define('APP_DEBUG', env('APP_DEBUG', 'false') === 'true');
}

// Configuration du reporting d'erreurs selon l'environnement
if (EnvLoader::isDevelopment()) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

// Définir le timezone
date_default_timezone_set(env('APP_TIMEZONE', 'Africa/Ndjamena'));
