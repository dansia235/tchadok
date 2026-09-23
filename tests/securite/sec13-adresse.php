<?php
/**
 * Tests SEC-13 : une seule lecture de l'adresse du client.
 *
 * Deux parties :
 *   1. la fonction clientIp() elle-meme, sur des en-tetes simules ;
 *   2. son cablage reel, en interrogeant le site local : une adresse
 *      falsifiee dans un en-tete ne doit jamais atteindre la base, ni servir
 *      a repartir a zero face au verrouillage des connexions.
 *
 * Usage : C:\xampp\php\php.exe tests\securite\sec13-adresse.php
 */

declare(strict_types=1);

$racine = dirname(__DIR__, 2);

if (!is_file($racine . '/.env.local')) {
    fwrite(STDERR, "Refus : .env.local absent. Ces tests ne s'executent qu'en local.\n");
    exit(1);
}

require_once $racine . '/includes/functions.php';

$baseDb = (string) EnvLoader::get('DB_DATABASE', '');
if (!preg_match('/local|test|dev/', $baseDb)) {
    fwrite(STDERR, "Refus : base '{$baseDb}' non locale.\n");
    exit(1);
}

$base = 'http://localhost/tchadok';
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
 * Simule une requete : fixe $_SERVER puis appelle clientIp().
 */
function adressePour(array $serveur, ?array $proxys = null): string
{
    $sauvegarde = $_SERVER;
    foreach (['REMOTE_ADDR', 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'HTTP_X_REAL_IP'] as $cle) {
        unset($_SERVER[$cle]);
    }
    foreach ($serveur as $cle => $valeur) {
        $_SERVER[$cle] = $valeur;
    }
    $resultat = clientIp($proxys);
    $_SERVER = $sauvegarde;

    return $resultat;
}

/**
 * Requete HTTP avec en-tetes choisis.
 *
 * @return array{code:int,corps:string,entetes:string}
 */
function requete(string $url, array $entetes = [], ?array $post = null, ?string $cookies = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_HTTPHEADER     => $entetes,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    if ($cookies !== null) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookies);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookies);
    }
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $reponse = (string) curl_exec($ch);
    $taille  = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $code    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'code'    => $code,
        'entetes' => substr($reponse, 0, $taille),
        'corps'   => substr($reponse, $taille),
    ];
}

function jeton(string $url, string $cookies): string
{
    $r = requete($url, [], null, $cookies);

    return preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $r['corps'], $m) ? $m[1] : '';
}

$db = TchadokDatabase::getInstance()->getConnection();
$db->exec('DELETE FROM login_attempts');
$db->exec('DELETE FROM rate_limit_hits');

echo "\n=== A. Par defaut, seule l'adresse de la connexion compte ===\n";
verif(
    'Sans en-tete : REMOTE_ADDR',
    adressePour(['REMOTE_ADDR' => '41.223.10.5']) === '41.223.10.5'
);
verif(
    'X-Forwarded-For ignore quand aucun proxy n\'est declare',
    adressePour(['REMOTE_ADDR' => '41.223.10.5', 'HTTP_X_FORWARDED_FOR' => '8.8.8.8']) === '41.223.10.5'
);
verif(
    'Client-IP n\'est plus lu du tout',
    adressePour(['REMOTE_ADDR' => '41.223.10.5', 'HTTP_CLIENT_IP' => '8.8.8.8']) === '41.223.10.5'
);
verif(
    'Adresse de connexion illisible : 0.0.0.0',
    adressePour(['REMOTE_ADDR' => 'pas-une-adresse']) === '0.0.0.0'
);
verif(
    'REMOTE_ADDR absente : 0.0.0.0',
    adressePour([]) === '0.0.0.0'
);

echo "\n=== B. Derriere un proxy declare ===\n";
$proxy = ['10.0.0.1'];
verif(
    'Le client est lu dans X-Forwarded-For',
    adressePour(['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '41.223.10.5'], $proxy) === '41.223.10.5'
);
verif(
    'Chaine de proxys : on retient la premiere adresse non declaree, en partant de la droite',
    adressePour([
        'REMOTE_ADDR' => '10.0.0.1',
        'HTTP_X_FORWARDED_FOR' => '203.0.113.9, 41.223.10.5, 10.0.0.2',
    ], ['10.0.0.1', '10.0.0.2']) === '41.223.10.5'
);
verif(
    'Le client ne choisit pas son adresse en ajoutant des entrees a gauche',
    adressePour([
        'REMOTE_ADDR' => '10.0.0.1',
        'HTTP_X_FORWARDED_FOR' => '8.8.8.8, 41.223.10.5',
    ], $proxy) === '41.223.10.5'
);
verif(
    'Entree illisible : retour a l\'adresse de la connexion',
    adressePour(['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => 'bidon'], $proxy) === '10.0.0.1'
);
verif(
    'En-tete vide : adresse de la connexion',
    adressePour(['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => ''], $proxy) === '10.0.0.1'
);
verif(
    'Chaine faite uniquement de proxys declares : adresse de la connexion',
    adressePour(['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '10.0.0.2'], ['10.0.0.1', '10.0.0.2']) === '10.0.0.1'
);
verif(
    'Un proxy non declare ne donne aucun credit a l\'en-tete',
    adressePour(['REMOTE_ADDR' => '10.9.9.9', 'HTTP_X_FORWARDED_FOR' => '8.8.8.8'], $proxy) === '10.9.9.9'
);

echo "\n=== C. Plages CIDR et IPv6 ===\n";
verif('CIDR IPv4 : 10.0.0.7 dans 10.0.0.0/24', adresseDansPlage('10.0.0.7', '10.0.0.0/24'));
verif('CIDR IPv4 : 10.0.1.7 hors de 10.0.0.0/24', !adresseDansPlage('10.0.1.7', '10.0.0.0/24'));
verif('Prefixe non aligne sur un octet : 192.168.1.100 dans 192.168.1.64/26', adresseDansPlage('192.168.1.100', '192.168.1.64/26'));
verif('... et 192.168.1.200 en dehors', !adresseDansPlage('192.168.1.200', '192.168.1.64/26'));
verif('IPv6 exacte', adresseDansPlage('::1', '::1'));
verif('CIDR IPv6', adresseDansPlage('2001:db8::5', '2001:db8::/32'));
verif('Familles differentes : aucune correspondance', !adresseDansPlage('10.0.0.1', '2001:db8::/32'));
verif('Prefixe absurde ignore', !adresseDansPlage('10.0.0.1', '10.0.0.0/999'));
verif(
    'Proxy declare par une plage',
    adressePour(['REMOTE_ADDR' => '10.0.0.4', 'HTTP_X_FORWARDED_FOR' => '41.223.10.5'], ['10.0.0.0/8']) === '41.223.10.5'
);

echo "\n=== D. Cablage reel : un en-tete falsifie n'atteint pas la base ===\n";
$fauxEntetes = ['X-Forwarded-For: 8.8.8.8', 'Client-IP: 9.9.9.9', 'X-Real-IP: 7.7.7.7'];
$r = requete($base . '/api/search.php?q=essai', $fauxEntetes);
verif('Recherche traitee', $r['code'] === 200, (string) $r['code']);
$stmt = $db->query("SELECT ip_address FROM rate_limit_hits WHERE bucket = 'recherche' ORDER BY id DESC LIMIT 1");
$adresseEnregistree = (string) $stmt->fetchColumn();
verif(
    "L'adresse enregistree est celle de la connexion, pas celle de l'en-tete",
    $adresseEnregistree !== '' && !in_array($adresseEnregistree, ['8.8.8.8', '9.9.9.9', '7.7.7.7'], true),
    $adresseEnregistree
);

echo "\n=== E. Le verrouillage ne se contourne pas par l'en-tete ===\n";
$db->exec('DELETE FROM login_attempts');
$db->exec('DELETE FROM rate_limit_hits');
$cookies = tempnam(sys_get_temp_dir(), 'sec13');
$corps = '';
for ($i = 1; $i <= 6; $i++) {
    $j = jeton($base . '/login.php', $cookies);
    // Une adresse differente a chaque essai : si l'en-tete etait cru, chaque
    // tentative repartirait d'un compteur neuf.
    $r = requete(
        $base . '/login.php',
        ['X-Forwarded-For: 8.8.8.' . $i, 'Client-IP: 9.9.9.' . $i],
        ['csrf_token' => $j, 'email' => 'cible-sec13@essai.local', 'password' => 'mauvais'],
        $cookies
    );
    $corps = $r['corps'];
}
verif(
    'Verrouille au 6e essai malgre une adresse differente a chaque fois',
    str_contains($corps, 'Trop de tentatives de connexion')
);
$stmt = $db->query("SELECT COUNT(DISTINCT ip_address) FROM login_attempts WHERE identifier = 'cible-sec13@essai.local'");
$adressesDistinctes = (string) $stmt->fetchColumn();
verif(
    'Toutes les tentatives sont rattachees a une seule adresse',
    $adressesDistinctes === '1',
    $adressesDistinctes
);
@unlink($cookies);

echo "\n=== F. Avec un proxy reellement declare dans .env.local ===\n";
$envLocal = $racine . '/.env.local';
$envOrigine = (string) file_get_contents($envLocal);
try {
    $db->exec('DELETE FROM rate_limit_hits');
    file_put_contents(
        $envLocal,
        preg_replace('/^TRUSTED_PROXIES=.*$/m', 'TRUSTED_PROXIES=::1,127.0.0.1', $envOrigine)
    );
    $r = requete($base . '/api/search.php?q=essai', ['X-Forwarded-For: 41.223.10.5']);
    verif('Recherche traitee', $r['code'] === 200, (string) $r['code']);
    $stmt = $db->query("SELECT ip_address FROM rate_limit_hits WHERE bucket = 'recherche' ORDER BY id DESC LIMIT 1");
    $enregistree = (string) $stmt->fetchColumn();
    verif("L'adresse annoncee par le proxy declare est retenue", $enregistree === '41.223.10.5', $enregistree);
} finally {
    file_put_contents($envLocal, $envOrigine);
}
$db->exec('DELETE FROM rate_limit_hits');
$r = requete($base . '/api/search.php?q=essai', ['X-Forwarded-For: 41.223.10.5']);
$stmt = $db->query("SELECT ip_address FROM rate_limit_hits WHERE bucket = 'recherche' ORDER BY id DESC LIMIT 1");
$enregistree = (string) $stmt->fetchColumn();
verif('Liste remise a vide : l\'en-tete redevient sans effet', $enregistree !== '41.223.10.5', $enregistree);

echo "\n=== G. Plus qu'une seule implementation ===\n";
verif('clientIp() existe', function_exists('clientIp'));
verif('getClientIP() a disparu', !function_exists('getClientIP'));
$restes = [];
foreach (['includes/auth.php', 'includes/functions.php', 'api/stream.php', 'includes/rate-limit.php', 'includes/remember-me.php'] as $fichier) {
    $contenu = (string) file_get_contents($racine . '/' . $fichier);
    if (str_contains($contenu, 'HTTP_CLIENT_IP') && !str_contains($fichier, 'functions.php')) {
        $restes[] = $fichier;
    }
    if (preg_match('/function getClientIP/', $contenu)) {
        $restes[] = $fichier . ' (declaration)';
    }
}
verif('Aucune lecture de Client-IP ni de seconde implementation', $restes === [], implode(', ', $restes));

$db->exec('DELETE FROM login_attempts');
$db->exec('DELETE FROM rate_limit_hits');

echo "\n";
echo sprintf("Resultat : %d reussi(s), %d echec(s)\n", $ok, $ko);
exit($ko === 0 ? 0 : 1);
