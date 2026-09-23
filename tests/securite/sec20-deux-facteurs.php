<?php
/**
 * Tests SEC-20 : authentification a deux facteurs.
 *
 * Verifie les trois criteres du plan :
 *   - un administrateur sans second facteur n'atteint pas l'administration ;
 *   - un code TOTP deja utilise est refuse ;
 *   - un code de secours ne fonctionne qu'une fois.
 *
 * Le test active reellement le second facteur sur un compte d'essai, en
 * calculant les codes comme le ferait une application d'authentification.
 *
 * Usage : C:\xampp\php\php.exe tests\securite\sec20-deux-facteurs.php
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
$envLocal = $racine . '/.env.local';
$envOrigine = (string) file_get_contents($envLocal);
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

function requete(string $url, ?array $post = null, ?string $cookies = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
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
        'code'     => $code,
        'entetes'  => substr($reponse, 0, $taille),
        'corps'    => substr($reponse, $taille),
    ];
}

function jeton(string $url, string $cookies): string
{
    $r = requete($url, null, $cookies);

    return preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $r['corps'], $m) ? $m[1] : '';
}

function redirection(string $entetes): string
{
    return preg_match('/^Location:\s*(.+)$/mi', $entetes, $m) ? trim($m[1]) : '';
}

function codeMaintenant(string $secret, int $decalagePas = 0): string
{
    return DeuxFacteurs::code($secret, intdiv(time(), 30) + $decalagePas);
}

$db = TchadokDatabase::getInstance()->getConnection();
$sqlFichier = static function (string $chemin) use ($db): void {
    foreach (explode(";\n", (string) file_get_contents($chemin)) as $instruction) {
        // Les lignes de commentaire sont retirees AVANT le test : une
        // instruction precedee d'un commentaire serait sinon ignoree.
        $instruction = preg_replace('/^\s*--.*$/m', '', $instruction);
        if (trim($instruction) !== '') {
            try { $db->exec($instruction); } catch (Throwable $e) { /* sans consequence */ }
        }
    }
};

$db->exec('DELETE FROM login_attempts');
$db->exec('DELETE FROM rate_limit_hits');
$sqlFichier(__DIR__ . '/sec10-nettoyage.sql');
$sqlFichier(__DIR__ . '/sec10-fixtures.sql');

$cookies = [];

try {
    echo "\n=== A. TOTP conforme a la RFC 6238 ===\n";
    // Vecteurs officiels, cle "12345678901234567890" en base32.
    $cle = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
    foreach ([59 => '287082', 1111111109 => '081804', 1234567890 => '005924', 2000000000 => '279037'] as $temps => $attendu) {
        $obtenu = DeuxFacteurs::code($cle, intdiv($temps, 30));
        verif("Vecteur T={$temps} : {$attendu}", $obtenu === $attendu, $obtenu);
    }
    $secret = DeuxFacteurs::genererSecret();
    verif('Un secret fait 32 caracteres base32', (bool) preg_match('/^[A-Z2-7]{32}$/', $secret), $secret);
    verif('Deux secrets different', DeuxFacteurs::genererSecret() !== DeuxFacteurs::genererSecret());
    verif('L\'URI otpauth est formee', str_starts_with(DeuxFacteurs::uriOtpauth($secret, 'a@b.c'), 'otpauth://totp/'));

    echo "\n=== B. Activation ===\n";
    $cookies['fan'] = tempnam(sys_get_temp_dir(), 'sec20');
    $j = jeton($base . '/login.php', $cookies['fan']);
    requete($base . '/login.php', ['csrf_token' => $j, 'email' => 'fan10@essai.local', 'password' => 'tchadok2026'], $cookies['fan']);

    $page = requete($base . '/2fa.php', null, $cookies['fan']);
    verif('La page d\'activation s\'ouvre', $page['code'] === 200, (string) $page['code']);
    verif('Aucun service tiers n\'est sollicite', !str_contains($page['corps'], 'qrserver') && !str_contains($page['corps'], 'chart.googleapis'));
    verif('La cle est proposee en saisie manuelle', str_contains($page['corps'], 'saisir une cle de configuration'));

    $secret = DeuxFacteurs::genererSecret();
    $j = jeton($base . '/2fa.php', $cookies['fan']);
    $r = requete($base . '/2fa.php', ['csrf_token' => $j, 'action' => 'activer', 'secret' => $secret, 'code' => '000000'], $cookies['fan']);
    verif('Un code faux n\'active rien', str_contains($r['corps'], 'Code incorrect'));
    verif('... et rien n\'est enregistre', !DeuxFacteurs::estActive(931));

    $j = jeton($base . '/2fa.php', $cookies['fan']);
    $r = requete($base . '/2fa.php', ['csrf_token' => $j, 'action' => 'activer', 'secret' => $secret, 'code' => codeMaintenant($secret)], $cookies['fan']);
    verif('Le bon code active la double authentification', str_contains($r['corps'], 'activee'), '');
    verif('L\'etat est enregistre', DeuxFacteurs::estActive(931));
    verif('Dix codes de secours sont remis', substr_count($r['corps'], 'rounded-xl bg-black/20') === 10, (string) substr_count($r['corps'], 'rounded-xl bg-black/20'));
    verif('... et comptes en base', DeuxFacteurs::codesDeSecoursRestants(931) === 10, (string) DeuxFacteurs::codesDeSecoursRestants(931));

    $stocke = (string) $db->query('SELECT secret_chiffre FROM user_2fa_settings WHERE user_id = 931')->fetchColumn();
    verif('Le secret n\'est pas stocke en clair', !str_contains($stocke, $secret), substr($stocke, 0, 24) . '...');
    verif('... et la base ne contient pas non plus les codes de secours en clair',
        (int) $db->query("SELECT COUNT(*) FROM user_backup_codes WHERE user_id = 931 AND CHAR_LENGTH(code_hash) = 64")->fetchColumn() === 10);

    // Codes de secours affiches, pour la suite des essais.
    preg_match_all('#rounded-xl bg-black/20[^>]*>([A-Z0-9-]{11})<#', $r['corps'], $trouves);
    $codesDeSecours = $trouves[1] ?? [];
    verif('Les codes sont lisibles dans la page', count($codesDeSecours) === 10, (string) count($codesDeSecours));

    echo "\n=== C. Connexion en deux temps ===\n";
    $cookies['connexion'] = tempnam(sys_get_temp_dir(), 'sec20b');
    $j = jeton($base . '/login.php', $cookies['connexion']);
    $r = requete($base . '/login.php', ['csrf_token' => $j, 'email' => 'fan10@essai.local', 'password' => 'tchadok2026'], $cookies['connexion']);
    verif('Le bon mot de passe n\'ouvre plus la session seul', str_contains(redirection($r['entetes']), '2fa-verification'), redirection($r['entetes']));
    $r = requete($base . '/settings.php', null, $cookies['connexion']);
    verif('... et les pages protegees restent fermees', $r['code'] !== 200, (string) $r['code']);

    $j = jeton($base . '/2fa-verification.php', $cookies['connexion']);
    $r = requete($base . '/2fa-verification.php', ['csrf_token' => $j, 'code' => '111111'], $cookies['connexion']);
    verif('Un code faux est refuse', str_contains($r['corps'], 'Code incorrect'));
    $echecs = (int) $db->query("SELECT COUNT(*) FROM audit_log WHERE action = '2fa.echec'")->fetchColumn();
    verif('... et journalise', $echecs >= 1, (string) $echecs);

    $codeValide = codeMaintenant($secret);
    $j = jeton($base . '/2fa-verification.php', $cookies['connexion']);
    $r = requete($base . '/2fa-verification.php', ['csrf_token' => $j, 'code' => $codeValide], $cookies['connexion']);
    verif('Le bon code ouvre la session', str_contains(redirection($r['entetes']), 'localhost/tchadok'), redirection($r['entetes']));
    verif('... et les pages protegees s\'ouvrent', requete($base . '/settings.php', null, $cookies['connexion'])['code'] === 200);

    echo "\n=== D. Rejeu ===\n";
    verif('Le meme code ne vaut plus rien, meme dans sa fenetre', !DeuxFacteurs::verifierCode(931, $codeValide));
    $cookies['rejeu'] = tempnam(sys_get_temp_dir(), 'sec20c');
    $j = jeton($base . '/login.php', $cookies['rejeu']);
    requete($base . '/login.php', ['csrf_token' => $j, 'email' => 'fan10@essai.local', 'password' => 'tchadok2026'], $cookies['rejeu']);
    $j = jeton($base . '/2fa-verification.php', $cookies['rejeu']);
    $r = requete($base . '/2fa-verification.php', ['csrf_token' => $j, 'code' => $codeValide], $cookies['rejeu']);
    verif('Rejouer le code d\'une connexion precedente est refuse', str_contains($r['corps'], 'Code incorrect'));
    verif('... et la session reste fermee', requete($base . '/settings.php', null, $cookies['rejeu'])['code'] !== 200);

    echo "\n=== E. Codes de secours ===\n";
    $premier = $codesDeSecours[0] ?? '';
    verif('Prealable : un code de secours a ete remis', $premier !== '');
    $j = jeton($base . '/2fa-verification.php', $cookies['rejeu']);
    $r = requete($base . '/2fa-verification.php', ['csrf_token' => $j, 'code' => $premier, 'code_secours' => '1'], $cookies['rejeu']);
    verif('Un code de secours ouvre la session', str_contains(redirection($r['entetes']), 'localhost/tchadok'), redirection($r['entetes']));
    verif('Il est marque comme consomme', DeuxFacteurs::codesDeSecoursRestants(931) === 9, (string) DeuxFacteurs::codesDeSecoursRestants(931));
    verif('Le meme code ne fonctionne plus', !DeuxFacteurs::verifierCodeDeSecours(931, $premier));
    $trace = (int) $db->query("SELECT COUNT(*) FROM audit_log WHERE action = '2fa.code-secours'")->fetchColumn();
    verif('L\'usage est journalise', $trace >= 1, (string) $trace);

    echo "\n=== F. Obligation pour les roles d'ecriture ===\n";
    verif('Sans l\'interrupteur, rien n\'est impose', !DeuxFacteurs::exigee(932));
    file_put_contents($envLocal, preg_replace('/^ADMIN_2FA_REQUIRED=.*$/m', 'ADMIN_2FA_REQUIRED=true', $envOrigine));

    $cookies['admin'] = tempnam(sys_get_temp_dir(), 'sec20d');
    $j = jeton($base . '/login.php', $cookies['admin']);
    requete($base . '/login.php', ['csrf_token' => $j, 'email' => 'admin10@essai.local', 'password' => 'tchadok2026'], $cookies['admin']);
    $r = requete($base . '/admin-dashboard.php', null, $cookies['admin']);
    verif('Un administrateur sans second facteur est renvoye vers l\'activation', str_contains(redirection($r['entetes']), '2fa.php'), redirection($r['entetes']) ?: (string) $r['code']);
    $r = requete($base . '/admin-blog.php', null, $cookies['admin']);
    verif('... sur tous les ecrans d\'administration', str_contains(redirection($r['entetes']), '2fa.php'), redirection($r['entetes']) ?: (string) $r['code']);

    $secretAdmin = DeuxFacteurs::genererSecret();
    $j = jeton($base . '/2fa.php', $cookies['admin']);
    requete($base . '/2fa.php', ['csrf_token' => $j, 'action' => 'activer', 'secret' => $secretAdmin, 'code' => codeMaintenant($secretAdmin)], $cookies['admin']);
    verif('Apres activation, la console s\'ouvre', requete($base . '/admin-dashboard.php', null, $cookies['admin'])['code'] === 200);

    $j = jeton($base . '/2fa.php', $cookies['admin']);
    $r = requete($base . '/2fa.php', ['csrf_token' => $j, 'action' => 'desactiver', 'mot_de_passe' => 'tchadok2026'], $cookies['admin']);
    verif('Un role soumis a l\'obligation ne peut pas la retirer', str_contains($r['corps'], 'ne peut pas etre retiree'));
    verif('... et elle reste active', DeuxFacteurs::estActive(932));

    file_put_contents($envLocal, $envOrigine);

    echo "\n=== G. Recuperation par le serveur ===\n";
    $sortie = [];
    exec(sprintf('"C:\xampp\php\php.exe" "%s/scripts/deux-facteurs.php" reinitialiser admin10@essai.local 2>&1', $racine), $sortie, $codeSortie);
    verif('Sans motif, la reinitialisation est refusee', $codeSortie !== 0 && str_contains(implode("\n", $sortie), 'Motif obligatoire'));

    $sortie = [];
    exec(sprintf('"C:\xampp\php\php.exe" "%s/scripts/deux-facteurs.php" reinitialiser admin10@essai.local --raison="essai automatise" 2>&1', $racine), $sortie, $codeSortie);
    verif('Avec un motif, le second facteur est retire', $codeSortie === 0 && !DeuxFacteurs::estActive(932), implode(' ', $sortie));
    $trace = $db->query("SELECT reason FROM audit_log WHERE action = '2fa.desactive' ORDER BY id DESC LIMIT 1")->fetchColumn();
    verif('L\'operation est journalisee avec son motif', is_string($trace) && str_contains($trace, 'essai automatise'), (string) $trace);

    $sortie = [];
    exec(sprintf('"C:\xampp\php\php.exe" "%s/scripts/deux-facteurs.php" etat fan10@essai.local 2>&1', $racine), $sortie);
    verif('La commande d\'etat renseigne', str_contains(implode("\n", $sortie), 'actif'), implode(' ', $sortie));

    echo "\n=== H. Details ===\n";
    $r = requete($base . '/2fa-verification.php');
    verif('La page de verification refuse sans attente en cours', $r['code'] === 302, (string) $r['code']);
    $reglages = (string) file_get_contents($racine . '/includes/deux-facteurs.php');
    verif('Le secret est chiffre en AES-256-GCM', str_contains($reglages, 'aes-256-gcm'));
    verif('La cle est derivee, pas utilisee telle quelle', str_contains($reglages, 'hash_hkdf'));
    verif('Le second facteur est limite en debit', str_contains((string) file_get_contents($racine . '/includes/rate-limit.php'), "'second-facteur'"));
} finally {
    file_put_contents($envLocal, $envOrigine);
    foreach ($cookies as $fichier) {
        @unlink($fichier);
    }
    $db->exec('DELETE FROM audit_log');
    $db->exec('DELETE FROM login_attempts');
    $db->exec('DELETE FROM rate_limit_hits');
    $sqlFichier(__DIR__ . '/sec10-nettoyage.sql');
}

echo "\n";
echo sprintf("Resultat : %d reussi(s), %d echec(s)\n", $ok, $ko);
exit($ko === 0 ? 0 : 1);
