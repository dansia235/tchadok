<?php
/**
 * Tests SEC-16 : pages d'erreur.
 *
 * Verifie que chaque situation d'erreur produit un vrai code HTTP, une page
 * habillee, et aucune information technique -- y compris quand la base de
 * donnees est injoignable, cas ou une page d'erreur qui depend de
 * l'application echouerait a son tour.
 *
 * Usage : C:\xampp\php\php.exe tests\securite\sec16-pages-erreur.php
 */

declare(strict_types=1);

$racine = dirname(__DIR__, 2);

if (!is_file($racine . '/.env.local')) {
    fwrite(STDERR, "Refus : .env.local absent. Ces tests ne s'executent qu'en local.\n");
    exit(1);
}

$base = 'http://localhost/tchadok';
$envLocal = $racine . '/.env.local';
$ok = 0;
$ko = 0;

function verif(string $libelle, bool $condition, string $detail = ''): void
{
    global $ok, $ko;
    if ($condition) {
        $ok++;
        echo "  OK  {$libelle}\n";
    } else {
        $ko++;
        echo "  !!  {$libelle}" . ($detail !== '' ? "  -> {$detail}" : '') . "\n";
    }
}

function requete(string $url): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    $reponse = (string) curl_exec($ch);
    $taille  = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $code    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['code' => $code, 'entetes' => substr($reponse, 0, $taille), 'corps' => substr($reponse, $taille)];
}

$envOrigine = (string) file_get_contents($envLocal);

try {
    echo "\n=== A. Les quatre pages existent et repondent ===\n";
    $attendus = ['403.php' => 403, '404.php' => 404, '429.php' => 429, '500.php' => 500];
    foreach ($attendus as $fichier => $code) {
        verif("{$fichier} present", is_file($racine . '/' . $fichier));
        $r = requete($base . '/' . $fichier);
        verif("{$fichier} renvoie un vrai {$code}", $r['code'] === $code, (string) $r['code']);
        verif("{$fichier} est habillee aux couleurs du site", str_contains($r['corps'], 'Tchadok'));
        verif("{$fichier} n'est pas indexable", str_contains($r['corps'], 'name="robots" content="noindex"'));
        verif("{$fichier} : reponse non mise en cache", (bool) preg_match('/Cache-Control:\s*no-store/i', $r['entetes']));
    }

    echo "\n=== B. Situations reelles ===\n";
    $r = requete($base . '/cette-page-nexiste-pas-' . bin2hex(random_bytes(4)));
    verif('Une URL inexistante : 404', $r['code'] === 404, (string) $r['code']);
    verif('... avec la page habillee', str_contains($r['corps'], 'Page introuvable'));
    verif('... en HTML', (bool) preg_match('#Content-Type:\s*text/html#i', $r['entetes']));

    foreach (['/includes/functions.php', '/config/env.php', '/storage/logs/php-errors.log', '/database/tchadok.sql'] as $chemin) {
        $r = requete($base . $chemin);
        verif("{$chemin} : 403", $r['code'] === 403, (string) $r['code']);
        verif("{$chemin} : page habillee, pas la page d'Apache", str_contains($r['corps'], 'Acces refuse'));
    }

    echo "\n=== C. Aucune information technique ===\n";
    foreach (['/404.php', '/403.php', '/500.php', '/429.php', '/cette-page-nexiste-pas'] as $chemin) {
        $corps = requete($base . $chemin)['corps'];
        $fuites = [];
        foreach ([
            'Apache/'        => 'version du serveur',
            'Server at'      => 'signature Apache',
            'C:\\xampp'      => 'chemin serveur',
            '/var/www'       => 'chemin serveur',
            'PHP/8'          => 'version de PHP',
            'Fatal error'    => 'erreur PHP',
            'SQLSTATE'       => 'erreur SQL',
        ] as $motif => $quoi) {
            if (str_contains($corps, $motif)) {
                $fuites[] = $quoi;
            }
        }
        verif("{$chemin} : rien de technique", $fuites === [], implode(', ', $fuites));
    }

    echo "\n=== D. Les pages d'erreur ne dependent pas de l'application ===\n";
    foreach (['403.php', '404.php', '429.php', '500.php', 'includes/page-erreur.php'] as $fichier) {
        $contenu = (string) file_get_contents($racine . '/' . $fichier);
        $dependances = [];
        foreach (['functions.php', 'database.php', 'env.php', 'auth.php', 'header-tailwind.php'] as $depend) {
            if (preg_match('#(require|include)[^;\n]*' . preg_quote($depend, '#') . '#', $contenu)) {
                $dependances[] = $depend;
            }
        }
        verif("{$fichier} ne charge rien de l'application", $dependances === [], implode(', ', $dependances));
    }

    echo "\n=== E. Base de donnees injoignable : la page reste servie ===\n";
    file_put_contents(
        $envLocal,
        preg_replace('/^DB_HOST=.*$/m', 'DB_HOST=hote-inexistant.invalid', $envOrigine)
    );
    $r = requete($base . '/cette-page-nexiste-pas-non-plus');
    verif('404 toujours servi', $r['code'] === 404, (string) $r['code']);
    verif('... et toujours habille', str_contains($r['corps'], 'Page introuvable'));
    $r = requete($base . '/500.php');
    verif('500.php toujours servi', $r['code'] === 500, (string) $r['code']);
    verif('... sans fuite technique', !str_contains($r['corps'], 'SQLSTATE') && !str_contains($r['corps'], 'Fatal'));
    file_put_contents($envLocal, $envOrigine);
    $r = requete($base . '/login.php');
    verif('Configuration restauree : le site repond normalement', $r['code'] === 200, (string) $r['code']);

    echo "\n=== F. show404() et declarations Apache ===\n";
    $fonctions = (string) file_get_contents($racine . '/includes/functions.php');
    verif('show404() ne pointe plus vers pages/404.php', !str_contains($fonctions, "include 'pages/404.php'"));
    verif('show404() utilise la page reelle', (bool) preg_match('#show404.*?404\.php#s', $fonctions));
    $restes = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($racine)) as $fichier) {
        $chemin = str_replace('\\', '/', $fichier->getPathname());
        if (!str_ends_with($chemin, '.php') || preg_match('#/(vendor|node_modules|tests)/#', $chemin)) {
            continue;
        }
        // Une inclusion, pas une simple mention : functions.php cite l'ancien
        // chemin dans le commentaire qui explique la correction.
        if (preg_match('#(require|include)[^;\n]*pages/404\.php#', (string) file_get_contents($chemin))) {
            $restes[] = basename($chemin);
        }
    }
    verif('Plus aucune reference a pages/404.php', $restes === [], implode(', ', $restes));

    foreach (['.htaccess.local' => '/tchadok', '.htaccess.production' => ''] as $fichier => $prefixe) {
        $contenu = (string) file_get_contents($racine . '/' . $fichier);
        foreach ([403, 404, 429, 500] as $code) {
            verif(
                "{$fichier} declare ErrorDocument {$code}",
                str_contains($contenu, "ErrorDocument {$code} {$prefixe}/{$code}.php")
            );
        }
        verif("{$fichier} ne declare pas de page 419", !str_contains($contenu, 'ErrorDocument 419'));
    }
    verif('Pas de fichier 419.php (le refus CSRF repond 403)', !is_file($racine . '/419.php'));
} finally {
    file_put_contents($envLocal, $envOrigine);
}

echo "\n";
echo sprintf("Resultat : %d reussi(s), %d echec(s)\n", $ok, $ko);
exit($ko === 0 ? 0 : 1);
