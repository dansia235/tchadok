<?php
/**
 * Reponse commune aux refus de la couche de protection (SEC-09, SEC-12).
 *
 * Un refus doit etre lisible par les deux publics du site : une page pour un
 * navigateur, un objet JSON pour un appel JavaScript. Le meme rendu sert a la
 * garde CSRF et a la limitation de debit, pour qu'un refus ait toujours la
 * meme forme.
 */

declare(strict_types=1);

final class ReponseRefus
{
    /**
     * Le client attend-il du JSON plutot qu'une page ?
     */
    public static function attendJson(): bool
    {
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        $type   = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));

        return str_contains(self::chemin(), '/api/')
            || str_contains($accept, 'application/json')
            || str_contains($type, 'application/json')
            || strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    }

    public static function chemin(): string
    {
        return (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    }

    /**
     * @param string|null $raison categorie exposee au client ("csrf", "debit"),
     *                            pour qu'un appel JavaScript distingue ce refus
     *                            d'un refus d'autorisation
     * @param int|null    $retenteDans secondes avant de reessayer (Retry-After)
     */
    public static function envoyer(
        int $code,
        string $titre,
        string $message,
        ?string $raison = null,
        ?int $retenteDans = null
    ): never {
        if (!headers_sent()) {
            http_response_code($code);
            header('Cache-Control: no-store');
            if ($retenteDans !== null && $retenteDans > 0) {
                header('Retry-After: ' . $retenteDans);
            }
        }

        if (self::attendJson()) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            $erreur = ['code' => $code, 'message' => $message];
            if ($raison !== null) {
                $erreur['reason'] = $raison;
            }
            if ($retenteDans !== null && $retenteDans > 0) {
                $erreur['retry_after'] = $retenteDans;
            }
            echo json_encode(['success' => false, 'error' => $erreur], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }

        $retour = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $hote   = (string) ($_SERVER['HTTP_HOST'] ?? '');
        // Lien de retour uniquement vers le site lui-meme : un Referer tiers
        // ne doit pas devenir une redirection ouverte.
        if ($retour === '' || parse_url($retour, PHP_URL_HOST) !== parse_url('http://' . $hote, PHP_URL_HOST)) {
            $retour = defined('SITE_URL') ? SITE_URL . '/' : '/';
        }

        echo '<!doctype html><html lang="fr"><head><meta charset="utf-8">'
           . '<meta name="viewport" content="width=device-width,initial-scale=1">'
           . '<title>' . htmlspecialchars($titre, ENT_QUOTES, 'UTF-8') . '</title>'
           . '<style>body{font:15px/1.6 system-ui,sans-serif;background:#0B0F17;color:#E6EAF2;margin:0;'
           . 'min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem}'
           . 'main{max-width:34rem;text-align:center}h1{font-size:1.5rem;margin:0 0 .75rem}'
           . 'p{color:#A4AEC2;margin:0 0 1.5rem}a{display:inline-block;background:#2F6DE0;color:#fff;'
           . 'text-decoration:none;padding:.7rem 1.4rem;border-radius:999px;font-weight:600}'
           . 'a:focus-visible{outline:3px solid #FFC107;outline-offset:3px}</style></head><body><main>'
           . '<h1>' . htmlspecialchars($titre, ENT_QUOTES, 'UTF-8') . '</h1>'
           . '<p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>'
           . '<a href="' . htmlspecialchars($retour, ENT_QUOTES, 'UTF-8') . '">Revenir a la page</a>'
           . '</main></body></html>';
        exit;
    }
}
