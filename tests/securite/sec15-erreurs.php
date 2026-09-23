<?php
/**
 * Tests SEC-15 : les erreurs ne renseignent plus l'attaquant.
 *
 * Le script se declare en mode production (DEBUG_MODE = false) AVANT de
 * charger l'application : il verifie ainsi le comportement reel du serveur de
 * production, tout en s'executant sur le poste local.
 *
 * Pour les essais de bout en bout, une page qui echoue volontairement est
 * deposee a la racine du site, puis supprimee a la fin -- y compris en cas
 * d'echec. Elle refuse de s'executer si .env.local est absent.
 *
 * Usage : C:\xampp\php\php.exe tests\securite\sec15-erreurs.php
 */

declare(strict_types=1);

// Comportement de production pour les verifications en memoire.
define('DEBUG_MODE', false);

$racine = dirname(__DIR__, 2);

if (!is_file($racine . '/.env.local')) {
    fwrite(STDERR, "Refus : .env.local absent. Ces tests ne s'executent qu'en local.\n");
    exit(1);
}

require_once $racine . '/includes/functions.php';

$base = 'http://localhost/tchadok';
$journal = $racine . '/storage/logs/php-errors.log';
$pageTemporaire = $racine . '/sec15-panne-temporaire.php';
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

function requete(string $url, array $entetes = []): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_HTTPHEADER     => $entetes,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    $reponse = (string) curl_exec($ch);
    $taille  = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $code    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['code' => $code, 'entetes' => substr($reponse, 0, $taille), 'corps' => substr($reponse, $taille)];
}

/**
 * Reference affichee. Cherchee d'abord dans la balise <code> de la page ou
 * dans le champ JSON ; le repli sur le texte brut ne peut pas s'appuyer sur
 * des limites de mots, strip_tags collant la reference au mot suivant.
 */
function reference(string $corps): string
{
    if (preg_match('#<code>([A-HJ-NP-Z2-9]{8})</code>#', $corps, $m)) {
        return $m[1];
    }
    if (preg_match('/"reference"\s*:\s*"([A-HJ-NP-Z2-9]{8})"/', $corps, $m)) {
        return $m[1];
    }

    return preg_match('/(?<![A-Z0-9])([A-HJ-NP-Z2-9]{8})(?![A-Z0-9])/', strip_tags($corps), $m) ? $m[1] : '';
}

// Page qui echoue volontairement, deposee le temps du test.
file_put_contents($pageTemporaire, <<<'PHP'
<?php
// Fichier temporaire cree par tests/securite/sec15-erreurs.php.
// Il se supprime a la fin du test ; il refuse de s'executer hors du poste local.
if (!is_file(__DIR__ . '/.env.local')) {
    http_response_code(404);
    exit;
}

if (($_GET['mode'] ?? '') === 'production') {
    define('DEBUG_MODE', false);
}

require_once __DIR__ . '/includes/functions.php';

if (($_GET['type'] ?? '') === 'fatale') {
    fonctionQuiNexistePas();
}

throw new RuntimeException("SQLSTATE[42S22]: Column not found: 1054 Unknown column 'secret_interne' in 'field list'");
PHP);

try {
    echo "\n=== A. Reference de correlation ===\n";
    $r = GestionErreurs::reference();
    verif('Huit caracteres', strlen($r) === 8, $r);
    verif('Aucun caractere ambigu (0, O, 1, I)', !preg_match('/[01OI]/', $r), $r);
    $references = [];
    for ($i = 0; $i < 50; $i++) {
        $references[] = GestionErreurs::reference();
    }
    verif('Deux references ne se repetent pas', count(array_unique($references)) === 50);

    echo "\n=== B. Message affiche a la place de l'exception (mode production) ===\n";
    $exception = new RuntimeException("SQLSTATE[42S22]: Unknown column 'x' in 'field list'");
    $message = GestionErreurs::messagePublic($exception, 'essai automatise');
    verif('Le message technique n\'apparait pas', !str_contains($message, 'SQLSTATE'), $message);
    verif('Le chemin du fichier n\'apparait pas', !str_contains($message, __FILE__));
    $ref = reference($message);
    verif('Une reference est proposee', $ref !== '', $message);
    clearstatcache();
    $contenu = (string) file_get_contents($journal);
    verif('La reference permet de retrouver la trace complete dans le journal', str_contains($contenu, $ref), $ref);
    verif('... avec le message technique', str_contains($contenu, 'Unknown column'));
    verif('... et le contexte fourni par l\'appelant', str_contains($contenu, 'essai automatise'));

    echo "\n=== C. Erreurs d'API ===\n";
    $client = GestionErreurs::erreurApi(new Exception('La requete doit contenir au moins 2 caracteres', 400), 'api/essai');
    verif('Une erreur 4xx garde son code', $client['code'] === 400, (string) $client['code']);
    verif('... et son message, ecrit pour le client', $client['reponse']['error']['message'] === 'La requete doit contenir au moins 2 caracteres');
    verif('... sans reference (ce n\'est pas une panne)', !isset($client['reponse']['error']['reference']));

    $panne = GestionErreurs::erreurApi(new PDOException('SQLSTATE[HY000] Connexion refusee'), 'api/essai');
    verif('Une panne devient un 500', $panne['code'] === 500, (string) $panne['code']);
    verif('... sans detail technique', !str_contains($panne['reponse']['error']['message'], 'SQLSTATE'));
    verif('... avec une reference', !empty($panne['reponse']['error']['reference']));

    echo "\n=== D. Exception non interceptee, vue du visiteur (production) ===\n";
    $r = requete($base . '/sec15-panne-temporaire.php?mode=production');
    verif('Reponse 500', $r['code'] === 500, (string) $r['code']);
    verif('Aucun message SQL', !str_contains($r['corps'], 'SQLSTATE'));
    verif('Aucun nom de colonne interne', !str_contains($r['corps'], 'secret_interne'));
    verif('Aucun chemin serveur', !str_contains($r['corps'], 'C:\\xampp') && !str_contains($r['corps'], '/var/www'));
    verif('Aucune trace d\'execution', !str_contains($r['corps'], '#0 '));
    $refPage = reference($r['corps']);
    verif('Une reference est affichee', $refPage !== '', substr(strip_tags($r['corps']), 0, 120));
    clearstatcache();
    verif(
        'Cette reference retrouve la trace complete dans le journal',
        str_contains((string) file_get_contents($journal), $refPage),
        $refPage
    );

    echo "\n=== E. Erreur fatale : plus de page blanche ===\n";
    $r = requete($base . '/sec15-panne-temporaire.php?mode=production&type=fatale');
    verif('Reponse 500', $r['code'] === 500, (string) $r['code']);
    $refFatale = reference($r['corps']);
    verif('Une reference est affichee', $refFatale !== '');
    verif('Aucun nom de fonction interne', !str_contains($r['corps'], 'fonctionQuiNexistePas'));
    clearstatcache();
    verif(
        'La trace est au journal',
        str_contains((string) file_get_contents($journal), $refFatale),
        $refFatale
    );

    echo "\n=== F. Appel JavaScript : reponse JSON ===\n";
    $r = requete($base . '/sec15-panne-temporaire.php?mode=production', ['Accept: application/json']);
    verif('Reponse 500', $r['code'] === 500, (string) $r['code']);
    verif('Type JSON', str_contains(strtolower($r['entetes']), 'application/json'));
    $json = json_decode($r['corps'], true);
    verif('Corps JSON exploitable', is_array($json) && isset($json['error']['reference']), $r['corps']);
    verif('Aucun detail technique', is_array($json) && !str_contains((string) $json['error']['message'], 'SQLSTATE'));

    echo "\n=== G. En local, le developpeur garde le detail ===\n";
    $r = requete($base . '/sec15-panne-temporaire.php');
    verif('Reponse 500', $r['code'] === 500, (string) $r['code']);
    verif('Le message technique est affiche', str_contains($r['corps'], 'SQLSTATE'));
    verif('La trace est affichee', str_contains($r['corps'], '#0 '));
    verif('La reference est affichee aussi', reference($r['corps']) !== '');

    echo "\n=== H. Les API existantes gardent leurs messages utiles ===\n";
    $r = requete($base . '/api/search.php?q=a');
    $json = json_decode($r['corps'], true);
    verif('Recherche trop courte : 400', $r['code'] === 400, (string) $r['code']);
    verif(
        'Le message de validation est conserve',
        is_array($json) && str_contains((string) ($json['error']['message'] ?? ''), '2 caract'),
        $r['corps']
    );
    verif('Aucune reference sur une erreur de saisie', is_array($json) && !isset($json['error']['reference']));

    echo "\n=== I. Plus de message d'exception renvoye au visiteur ===\n";
    $exposes = [];
    foreach ([
        'register.php', 'upload.php', 'premium-payment.php', 'artist-add-song.php', 'artist-add-album.php',
        'admin-add-song.php', 'admin-add-album.php', 'admin-playlists.php', 'admin-podcasts.php',
        'admin-manage-radio.php', 'includes/blog-manager.php',
        'api/search.php', 'api/stream.php', 'api/notifications.php', 'api/follows.php', 'api/playlists.php',
    ] as $fichier) {
        foreach (explode("\n", (string) file_get_contents($racine . '/' . $fichier)) as $numero => $ligne) {
            if (!str_contains($ligne, 'getMessage()')) {
                continue;
            }
            // Les usages legitimes journalisent, ils n'affichent pas.
            if (preg_match('/error_log|logActivity|signaler/', $ligne)) {
                continue;
            }
            $exposes[] = $fichier . ':' . ($numero + 1);
        }
    }
    verif('Aucun getMessage() affiche a l\'utilisateur', $exposes === [], implode(', ', $exposes));

    echo "\n=== J. Script de diagnostic retire ===\n";
    verif('admin/test-db-connection.php supprime', !is_file($racine . '/admin/test-db-connection.php'));
    $r = requete($base . '/admin/test-db-connection.php');
    verif('... et introuvable par le web', $r['code'] === 404, (string) $r['code']);

    echo "\n=== K. Reglages d'environnement ===\n";
    verif('ENVIRONMENT derive du fichier charge', ENVIRONMENT === EnvLoader::environment(), ENVIRONMENT);
    $production = (string) file_get_contents($racine . '/.htaccess.production');
    verif('.htaccess.production : display_errors Off', (bool) preg_match('/php_flag\s+display_errors\s+Off/i', $production));
    verif('.htaccess.production : log_errors On', (bool) preg_match('/php_flag\s+log_errors\s+On/i', $production));
    verif('Plus de bloc mod_php7', !str_contains($production, 'mod_php7.c'));
    $local = (string) file_get_contents($racine . '/.htaccess.local');
    verif('.htaccess.local : plus de bloc mod_php7', !str_contains($local, 'mod_php7.c'));
} finally {
    @unlink($pageTemporaire);
}

echo "\n";
verif('Page d\'essai retiree du site', !is_file($pageTemporaire));
echo sprintf("Resultat : %d reussi(s), %d echec(s)\n", $ok, $ko);
exit($ko === 0 ? 0 : 1);
