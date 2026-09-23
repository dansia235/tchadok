<?php
/**
 * Gestion des erreurs (SEC-15).
 *
 * AVANT
 *   Une dizaine de points d'entree renvoyaient le message d'exception brut au
 *   visiteur : "SQLSTATE[42S22]: Column not found: 1054 Unknown column
 *   'x' in 'field list'", parfois avec le chemin complet du fichier sur le
 *   serveur. Une erreur devenait ainsi une source de renseignement sur le
 *   schema de la base et l'arborescence du serveur. Une erreur fatale, elle,
 *   donnait une page blanche : rien pour le visiteur, rien pour l'exploitant.
 *
 * APRES
 *   Chaque erreur recoit une reference courte. Le visiteur voit cette
 *   reference et rien d'autre ; le journal recoit le detail complet, trace
 *   comprise. La reference fait le lien entre les deux : la personne la
 *   signale, l'exploitant la retrouve dans storage/logs/php-errors.log.
 *
 *   En local, le detail est aussi affiche : c'est la machine du developpeur,
 *   et cacher l'erreur ferait perdre du temps.
 */

declare(strict_types=1);

require_once __DIR__ . '/reponse-refus.php';

final class GestionErreurs
{
    private static bool $installee = false;

    /**
     * Branche les gestionnaires globaux. Appelee une fois, au demarrage.
     */
    public static function installer(): void
    {
        if (self::$installee) {
            return;
        }
        self::$installee = true;

        set_exception_handler(static function (Throwable $e): void {
            self::terminer($e, 'exception non interceptee');
        });

        register_shutdown_function(static function (): void {
            $derniere = error_get_last();
            if ($derniere === null || !in_array($derniere['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                return;
            }

            self::terminer(
                new ErrorException(
                    $derniere['message'],
                    0,
                    $derniere['type'],
                    $derniere['file'],
                    $derniere['line']
                ),
                'erreur fatale'
            );
        });
    }

    /**
     * Journalise une erreur et renvoie la reference a communiquer.
     *
     * A utiliser dans un bloc catch : le message technique va au journal, la
     * reference va a l'ecran.
     */
    public static function signaler(Throwable $e, string $contexte = ''): string
    {
        $reference = self::reference();

        error_log(sprintf(
            "[Tchadok][erreur %s] %s%s : %s -- %s:%d | %s %s | ip=%s\n%s",
            $reference,
            $contexte !== '' ? $contexte . ' | ' : '',
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            ReponseRefus::chemin(),
            function_exists('clientIp') ? clientIp() : ($_SERVER['REMOTE_ADDR'] ?? '-'),
            $e->getTraceAsString()
        ));

        return $reference;
    }

    /**
     * Message affichable a la place d'un message d'exception.
     *
     * Production : une phrase neutre et la reference. Local : la reference et
     * le message technique, pour ne pas ralentir le developpement.
     *
     * @param string $contexte ce que la personne essayait de faire, en clair
     *                         ("ajout d'un titre") -- journalise, jamais affiche
     */
    public static function messagePublic(Throwable $e, string $contexte = ''): string
    {
        $reference = self::signaler($e, $contexte);

        if (self::detaille()) {
            return sprintf('Erreur (ref. %s) : %s', $reference, $e->getMessage());
        }

        return sprintf(
            'Une erreur est survenue de notre cote. Si elle se reproduit, indiquez la reference %s au support.',
            $reference
        );
    }

    /**
     * Erreur d'API : code HTTP et corps JSON.
     *
     * Les API du projet levent deux sortes d'exceptions. Celles qui portent un
     * code 4xx sont **ecrites pour le client** ("La requete de recherche doit
     * contenir au moins 2 caracteres") : leur message est utile et ne revele
     * rien, il est conserve. Toutes les autres sont des pannes : le message
     * part au journal et le client recoit une reference.
     *
     * @return array{code:int,reponse:array}
     */
    public static function erreurApi(Throwable $e, string $contexte = ''): array
    {
        $code = (int) $e->getCode();

        if ($code >= 400 && $code <= 499) {
            return [
                'code'    => $code,
                'reponse' => [
                    'success' => false,
                    'error'   => ['code' => $code, 'message' => $e->getMessage()],
                ],
            ];
        }

        $reference = self::signaler($e, $contexte);

        return [
            'code'    => 500,
            'reponse' => [
                'success' => false,
                'error'   => [
                    'code'      => 500,
                    'message'   => self::detaille() ? $e->getMessage() : 'Une erreur est survenue de notre cote.',
                    'reference' => $reference,
                ],
            ],
        ];
    }

    /**
     * Reference courte, lisible au telephone : 8 caracteres, sans ambiguite
     * visuelle (ni O ni 0, ni I ni 1).
     */
    public static function reference(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $reference = '';
        for ($i = 0; $i < 8; $i++) {
            $reference .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $reference;
    }

    private static function detaille(): bool
    {
        return defined('DEBUG_MODE') && DEBUG_MODE;
    }

    /**
     * Derniere reponse possible : journalise, puis rend une page ou un objet
     * JSON, selon ce que le client attend.
     */
    private static function terminer(Throwable $e, string $contexte): void
    {
        $reference = self::signaler($e, $contexte);

        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, sprintf("[%s] %s : %s\n", $reference, get_class($e), $e->getMessage()));
            return;
        }

        if (!headers_sent()) {
            http_response_code(500);
            header('Cache-Control: no-store');
            header(ReponseRefus::attendJson()
                ? 'Content-Type: application/json; charset=utf-8'
                : 'Content-Type: text/html; charset=utf-8');
        }

        if (ReponseRefus::attendJson()) {
            echo json_encode([
                'success' => false,
                'error'   => [
                    'code'      => 500,
                    'message'   => self::detaille() ? $e->getMessage() : 'Une erreur est survenue de notre cote.',
                    'reference' => $reference,
                ],
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo self::page($e, $reference);
    }

    private static function page(Throwable $e, string $reference): string
    {
        $detail = '';
        if (self::detaille()) {
            $detail = '<pre>' . htmlspecialchars(
                get_class($e) . ' : ' . $e->getMessage() . "\n"
                . $e->getFile() . ':' . $e->getLine() . "\n\n"
                . $e->getTraceAsString(),
                ENT_QUOTES,
                'UTF-8'
            ) . '</pre>';
        }

        $accueil = defined('SITE_URL') ? SITE_URL . '/' : '/';

        return '<!doctype html><html lang="fr"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>Une erreur est survenue</title>'
            . '<style>body{font:15px/1.6 system-ui,sans-serif;background:#0B0F17;color:#E6EAF2;margin:0;'
            . 'min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem}'
            . 'main{max-width:42rem;text-align:center}h1{font-size:1.5rem;margin:0 0 .75rem}'
            . 'p{color:#A4AEC2;margin:0 0 1.25rem}code{background:#161C28;padding:.2rem .5rem;border-radius:.4rem;'
            . 'font-size:1.05rem;letter-spacing:.08em;color:#E6EAF2}'
            . 'pre{text-align:left;overflow:auto;background:#161C28;padding:1rem;border-radius:.6rem;'
            . 'font-size:12px;color:#A4AEC2;max-height:22rem}'
            . 'a{display:inline-block;background:#2F6DE0;color:#fff;text-decoration:none;padding:.7rem 1.4rem;'
            . 'border-radius:999px;font-weight:600}'
            . 'a:focus-visible{outline:3px solid #FFC107;outline-offset:3px}</style></head><body><main>'
            . '<h1>Une erreur est survenue</h1>'
            . '<p>Le probleme vient de notre cote, pas de votre action. Si vous nous ecrivez, '
            . 'indiquez cette reference :</p>'
            . '<p><code>' . htmlspecialchars($reference, ENT_QUOTES, 'UTF-8') . '</code></p>'
            . $detail
            . '<a href="' . htmlspecialchars($accueil, ENT_QUOTES, 'UTF-8') . '">Revenir a l\'accueil</a>'
            . '</main></body></html>';
    }
}
