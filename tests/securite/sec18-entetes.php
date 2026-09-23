<?php
/**
 * Tests SEC-18 : en-tetes de securite et partage entre origines.
 *
 * Verifie qu'aucune reponse n'ouvre l'API a un site tiers par defaut, que
 * l'ouverture declaree fonctionne et reste limitee a la liste blanche, et que
 * la politique de contenu est posee avec un point de collecte utilisable.
 *
 * Usage : C:\xampp\php\php.exe tests\securite\sec18-entetes.php
 */

declare(strict_types=1);

$racine = dirname(__DIR__, 2);

if (!is_file($racine . '/.env.local')) {
    fwrite(STDERR, "Refus : .env.local absent. Ces tests ne s'executent qu'en local.\n");
    exit(1);
}

require_once $racine . '/includes/functions.php';

$base = 'http://localhost/tchadok';
$envLocal = $racine . '/.env.local';
$journalCsp = $racine . '/storage/logs/csp-report.log';
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

/**
 * @return array{code:int,entetes:string,corps:string}
 */
function requete(string $url, array $entetes = [], ?string $methode = null, ?string $corpsEnvoye = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_HTTPHEADER     => $entetes,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    if ($methode !== null) {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $methode);
    }
    if ($corpsEnvoye !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $corpsEnvoye);
    }
    $reponse = (string) curl_exec($ch);
    $taille  = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $code    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['code' => $code, 'entetes' => substr($reponse, 0, $taille), 'corps' => substr($reponse, $taille)];
}

function entete(string $entetes, string $nom): string
{
    return preg_match('/^' . preg_quote($nom, '/') . ':\s*(.+)$/mi', $entetes, $m) ? trim($m[1]) : '';
}

$envOrigine = (string) file_get_contents($envLocal);
$db = TchadokDatabase::getInstance()->getConnection();
$db->exec('DELETE FROM rate_limit_hits');

try {
    echo "\n=== A. En-tetes de securite ===\n";
    $r = requete($base . '/login.php');
    foreach ([
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options'        => 'SAMEORIGIN',
        'Referrer-Policy'        => 'strict-origin-when-cross-origin',
    ] as $nom => $attendu) {
        verif("{$nom} : {$attendu}", entete($r['entetes'], $nom) === $attendu, entete($r['entetes'], $nom));
    }
    verif('Permissions-Policy present', entete($r['entetes'], 'Permissions-Policy') !== '');
    verif('Politique de contenu posee en Report-Only', entete($r['entetes'], 'Content-Security-Policy-Report-Only') !== '');
    verif('X-XSS-Protection absent (obsolete)', entete($r['entetes'], 'X-XSS-Protection') === '');
    verif('X-Powered-By absent', entete($r['entetes'], 'X-Powered-By') === '');

    echo "\n=== B. Aucune ouverture par defaut ===\n";
    $tiers = ['Origin: https://site-tiers.example'];
    foreach ([
        '/login.php'          => 'page HTML',
        '/index.php'          => 'accueil',
        '/api/stream.php'     => 'API d\'ecoute',
        '/api/playlists.php'  => 'API playlists',
        '/api/follows.php'    => 'API abonnements',
        '/api/notifications.php' => 'API notifications',
        '/api/search.php?q=essai' => 'API de recherche (liste vide)',
        '/api/radio/metadata.php' => 'API radio (liste vide)',
    ] as $chemin => $quoi) {
        $r = requete($base . $chemin, $tiers);
        verif(
            "Pas d'Access-Control-Allow-Origin : {$quoi}",
            entete($r['entetes'], 'Access-Control-Allow-Origin') === '',
            entete($r['entetes'], 'Access-Control-Allow-Origin')
        );
    }
    $r = requete($base . '/api/stream.php', $tiers, 'OPTIONS');
    verif('Prevol sur une API fermee : refuse', in_array($r['code'], [403, 405], true), (string) $r['code']);

    echo "\n=== C. Ouverture declaree, et limitee ===\n";
    file_put_contents(
        $envLocal,
        preg_replace('/^CORS_ALLOWED_ORIGINS=.*$/m', 'CORS_ALLOWED_ORIGINS=https://site-ami.example', $envOrigine)
    );
    $ami = ['Origin: https://site-ami.example'];
    $r = requete($base . '/api/search.php?q=essai', $ami);
    verif('Origine declaree : ouverture accordee', entete($r['entetes'], 'Access-Control-Allow-Origin') === 'https://site-ami.example', entete($r['entetes'], 'Access-Control-Allow-Origin'));
    verif('Vary: Origin pose (sinon un cache partage melangerait les reponses)', stripos(entete($r['entetes'], 'Vary'), 'origin') !== false, entete($r['entetes'], 'Vary'));
    verif('Jamais de credentials avec une ouverture', entete($r['entetes'], 'Access-Control-Allow-Credentials') === '');

    $r = requete($base . '/api/search.php?q=essai', $tiers);
    verif('Autre origine : aucune ouverture', entete($r['entetes'], 'Access-Control-Allow-Origin') === '', entete($r['entetes'], 'Access-Control-Allow-Origin'));

    $r = requete($base . '/api/search.php?q=essai', $ami, 'OPTIONS');
    verif('Prevol accepte depuis l\'origine declaree', $r['code'] === 204, (string) $r['code']);
    verif('... avec les methodes autorisees', entete($r['entetes'], 'Access-Control-Allow-Methods') !== '');
    $r = requete($base . '/api/search.php?q=essai', $tiers, 'OPTIONS');
    verif('Prevol refuse depuis une autre origine', $r['code'] === 403, (string) $r['code']);

    $r = requete($base . '/api/radio/metadata.php', $ami);
    verif('La radio suit la meme regle', entete($r['entetes'], 'Access-Control-Allow-Origin') === 'https://site-ami.example', entete($r['entetes'], 'Access-Control-Allow-Origin'));

    file_put_contents($envLocal, $envOrigine);
    $r = requete($base . '/api/search.php?q=essai', $ami);
    verif('Liste remise a vide : ouverture retiree', entete($r['entetes'], 'Access-Control-Allow-Origin') === '');

    echo "\n=== D. Collecte des rapports de politique de contenu ===\n";
    $avant = is_file($journalCsp) ? (int) filesize($journalCsp) : 0;
    $rapport = json_encode(['csp-report' => [
        'document-uri'        => 'http://localhost/tchadok/essai',
        'effective-directive' => 'script-src',
        'blocked-uri'         => 'https://exemple.invalid/x.js',
    ]]);
    $r = requete($base . '/api/csp-report.php', ['Content-Type: application/csp-report'], 'POST', (string) $rapport);
    verif('Rapport accepte sans jeton, et sans reponse (204)', $r['code'] === 204, (string) $r['code']);
    clearstatcache();
    verif('Le rapport est ecrit dans le journal', is_file($journalCsp) && filesize($journalCsp) > $avant);
    $journal = (string) file_get_contents($journalCsp);
    verif('... avec la directive concernee', str_contains($journal, 'script-src'));

    $r = requete($base . '/api/csp-report.php');
    verif('Une lecture simple est refusee', $r['code'] === 405, (string) $r['code']);

    $r = requete($base . '/api/csp-report.php', ['Content-Type: application/csp-report'], 'POST', 'pas du json');
    verif('Un corps illisible ne fait rien echouer', $r['code'] === 204, (string) $r['code']);

    // Injection de fin de ligne : une seule ligne doit etre ecrite.
    $lignesAvant = count(file($journalCsp) ?: []);
    $rapport = json_encode(['csp-report' => [
        'document-uri'        => "http://localhost/\n[FAUSSE LIGNE] injection",
        'effective-directive' => "script-src\nautre",
        'blocked-uri'         => 'https://exemple.invalid/y.js',
    ]]);
    requete($base . '/api/csp-report.php', ['Content-Type: application/csp-report'], 'POST', (string) $rapport);
    $lignesApres = count(file($journalCsp) ?: []);
    verif('Une tentative d\'injection de ligne n\'en ecrit qu\'une', $lignesApres - $lignesAvant === 1, ($lignesApres - $lignesAvant) . ' ligne(s)');
    // Le texte injecte subsiste, mais aplati : ce qui compte est qu'il ne
    // forme pas une entree autonome, qui ferait mentir le journal.
    $fausses = array_filter(file($journalCsp) ?: [], static fn ($l) => str_starts_with(ltrim($l), '[FAUSSE LIGNE]'));
    verif('Aucune fausse entree fabriquee dans le journal', $fausses === []);

    echo "\n=== E. Configuration ===\n";
    foreach (['.htaccess.local', '.htaccess.production'] as $fichier) {
        $contenu = (string) file_get_contents($racine . '/' . $fichier);
        verif("{$fichier} : aucune ouverture CORS globale", !preg_match('/^\s*Header.*Access-Control-Allow-Origin/mi', $contenu));
        verif("{$fichier} : politique de contenu en Report-Only", str_contains($contenu, 'Content-Security-Policy-Report-Only'));
        verif("{$fichier} : point de collecte declare", str_contains($contenu, 'csp-report.php'));
        verif("{$fichier} : pas de X-XSS-Protection", !str_contains($contenu, 'X-XSS-Protection'));
    }

    $ouvertures = [];
    foreach (glob($racine . '/api/*.php') ?: [] as $fichier) {
        $contenu = (string) file_get_contents($fichier);
        if (preg_match("/header\(['\"]Access-Control-Allow-Origin:\s*\*/", $contenu)) {
            $ouvertures[] = basename($fichier);
        }
    }
    foreach (glob($racine . '/api/*/*.php') ?: [] as $fichier) {
        $contenu = (string) file_get_contents($fichier);
        if (preg_match("/header\(['\"]Access-Control-Allow-Origin:\s*\*/", $contenu)) {
            $ouvertures[] = basename(dirname($fichier)) . '/' . basename($fichier);
        }
    }
    verif('Plus aucune API n\'ouvre a "*"', $ouvertures === [], implode(', ', $ouvertures));
} finally {
    file_put_contents($envLocal, $envOrigine);
    $db->exec('DELETE FROM rate_limit_hits');
}

echo "\n";
echo sprintf("Resultat : %d reussi(s), %d echec(s)\n", $ok, $ko);
exit($ko === 0 ? 0 : 1);
