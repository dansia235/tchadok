<?php
/**
 * Pages d'erreur autonomes (SEC-16).
 *
 * POURQUOI SANS AUCUNE DEPENDANCE
 *   Ces pages sont servies par Apache (ErrorDocument) au moment ou quelque
 *   chose ne va deja pas. Une page 500 qui chargerait la configuration, la
 *   session et la base de donnees echouerait precisement dans le cas ou elle
 *   est le plus utile -- et Apache n'a alors plus rien a montrer que sa page
 *   par defaut, qui annonce sa version.
 *
 *   Elles reprennent donc l'identite visuelle du site en CSS interne, sans
 *   toucher a l'application : pas de configuration, pas de session, pas de
 *   base. Le chemin du site est deduit de l'emplacement du fichier, ce qui
 *   fonctionne aussi bien en local (/tchadok/) qu'a la racine d'un domaine.
 */

declare(strict_types=1);

/**
 * Chemin de base du site, deduit sans configuration.
 */
function baseSiteErreur(): string
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/'));
    $base = rtrim(dirname($script), '/');

    return $base === '' ? '/' : $base . '/';
}

/**
 * Rend la page et arrete le script.
 *
 * @param string   $conseil phrase d'aide, facultative
 * @param string[] $liens   libelle => chemin relatif au site
 */
function pageErreur(int $code, string $titre, string $message, string $conseil = '', array $liens = []): never
{
    // Apache place le code d'origine ici quand il sert la page en
    // ErrorDocument ; en acces direct, c'est le code de la page qui vaut.
    $statut = (int) ($_SERVER['REDIRECT_STATUS'] ?? 0);
    if ($statut < 400 || $statut > 599) {
        $statut = $code;
    }

    if (!headers_sent()) {
        http_response_code($statut);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
    }

    $base = baseSiteErreur();
    if ($liens === []) {
        $liens = ['Revenir a l\'accueil' => ''];
    }

    $e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

    $boutons = '';
    $premier = true;
    foreach ($liens as $libelle => $chemin) {
        $classe = $premier ? 'principal' : 'secondaire';
        $boutons .= '<a class="' . $classe . '" href="' . $e($base . ltrim($chemin, '/')) . '">' . $e((string) $libelle) . '</a>';
        $premier = false;
    }

    echo '<!doctype html><html lang="fr"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="robots" content="noindex">'
        . '<title>' . $e($titre) . ' — Tchadok</title>'
        . '<style>'
        . ':root{color-scheme:dark}'
        . '*{box-sizing:border-box}'
        . 'body{font:16px/1.65 system-ui,-apple-system,"Segoe UI",sans-serif;margin:0;min-height:100vh;'
        . 'display:flex;align-items:center;justify-content:center;padding:2rem;color:#E6EAF2;'
        . 'background:radial-gradient(circle at top right,rgba(47,109,224,.18),transparent 28%),'
        . 'radial-gradient(circle at top left,rgba(16,185,129,.14),transparent 24%),#0B0F17}'
        . 'main{max-width:38rem;text-align:center}'
        . '.marque{display:inline-flex;align-items:center;gap:.6rem;font-weight:700;letter-spacing:.02em;'
        . 'color:#E6EAF2;text-decoration:none;margin-bottom:2rem}'
        . '.pastille{display:grid;place-items:center;width:2.4rem;height:2.4rem;border-radius:.9rem;'
        . 'background:rgba(47,109,224,.2);color:#2F6DE0;font-size:1.1rem}'
        . '.code{font-size:clamp(3.5rem,12vw,5.5rem);font-weight:800;line-height:1;margin:0;'
        . 'background:linear-gradient(120deg,#2F6DE0,#10B981);-webkit-background-clip:text;'
        . 'background-clip:text;color:transparent}'
        . 'h1{font-size:1.4rem;margin:1rem 0 .6rem}'
        . 'p{color:#A4AEC2;margin:0 0 1rem}'
        . '.liens{display:flex;flex-wrap:wrap;gap:.7rem;justify-content:center;margin-top:1.6rem}'
        . 'a.principal,a.secondaire{display:inline-block;text-decoration:none;padding:.7rem 1.4rem;'
        . 'border-radius:999px;font-weight:600;font-size:.95rem}'
        . 'a.principal{background:#2F6DE0;color:#fff}'
        . 'a.secondaire{border:1px solid rgba(255,255,255,.15);color:#E6EAF2}'
        . 'a:focus-visible{outline:3px solid #FFC107;outline-offset:3px}'
        . '</style></head><body><main>'
        . '<a class="marque" href="' . $e($base) . '"><span class="pastille">&#9835;</span> Tchadok</a>'
        . '<p class="code">' . $e((string) $statut) . '</p>'
        . '<h1>' . $e($titre) . '</h1>'
        . '<p>' . $e($message) . '</p>'
        . ($conseil !== '' ? '<p>' . $e($conseil) . '</p>' : '')
        . '<div class="liens">' . $boutons . '</div>'
        . '</main></body></html>';

    exit;
}
