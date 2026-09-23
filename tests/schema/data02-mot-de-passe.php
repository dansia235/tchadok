<?php
/**
 * Tests DATA-02 : une seule colonne de mot de passe.
 *
 * Verifie les trois criteres du plan :
 *   - la colonne `password` n'existe plus ;
 *   - aucun compte ne dispose de deux mots de passe valides ;
 *   - le code n'ecrit ni ne lit plus cette colonne.
 *
 * La partie « deux mots de passe » se verifie de la seule facon qui compte :
 * en essayant reellement de se connecter avec l'ancien mot de passe d'un
 * compte d'essai, sur le site en fonctionnement.
 *
 * Usage : C:\xampp\php\php.exe tests\schema\data02-mot-de-passe.php
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

function requete(string $url, ?array $post = null, ?string $cookies = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
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
    $corps = (string) curl_exec($ch);
    $code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['code' => $code, 'corps' => $corps];
}

function connexion(string $email, string $motDePasse): bool
{
    $cookies = tempnam(sys_get_temp_dir(), 'data02');
    $page = requete($GLOBALS['base'] . '/login.php', null, $cookies);
    $jeton = preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $page['corps'], $m) ? $m[1] : '';
    requete($GLOBALS['base'] . '/login.php', ['csrf_token' => $jeton, 'email' => $email, 'password' => $motDePasse], $cookies);
    $connecte = requete($GLOBALS['base'] . '/settings.php', null, $cookies)['code'] === 200;
    @unlink($cookies);

    return $connecte;
}

$db = TchadokDatabase::getInstance()->getConnection();
$db->exec('DELETE FROM login_attempts');
$db->exec('DELETE FROM rate_limit_hits');
$db->exec('DELETE FROM users WHERE id = 941');

try {
    echo "\n=== A. Le schema ===\n";
    $colonne = (int) $db->query(
        "SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'password'"
    )->fetchColumn();
    verif('La colonne `password` n\'existe plus', $colonne === 0, (string) $colonne);
    $hash = (int) $db->query(
        "SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'password_hash'"
    )->fetchColumn();
    verif('`password_hash` demeure', $hash === 1);

    echo "\n=== B. Un compte, un seul mot de passe ===\n";
    // Compte d'essai : mot de passe « ancien » remplace par un « nouveau ».
    $ancien = 'Ancien-2026!aa';
    $nouveau = 'Nouveau-2026!bb';
    $db->prepare(
        'INSERT INTO users (id, username, email, password_hash, first_name, last_name, is_active, email_verified)
         VALUES (941, ?, ?, ?, ?, ?, 1, 1)'
    )->execute(['essai_data02', 'data02@essai.local', password_hash($ancien, PASSWORD_BCRYPT), 'Essai', 'Data02']);

    verif('L\'ancien mot de passe ouvre le compte', connexion('data02@essai.local', $ancien));

    // Changement de mot de passe par le parcours reel de l'application.
    $cookies = tempnam(sys_get_temp_dir(), 'data02b');
    $page = requete($base . '/login.php', null, $cookies);
    $jeton = preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $page['corps'], $m) ? $m[1] : '';
    requete($base . '/login.php', ['csrf_token' => $jeton, 'email' => 'data02@essai.local', 'password' => $ancien], $cookies);
    $page = requete($base . '/settings.php', null, $cookies);
    $jeton = preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $page['corps'], $m) ? $m[1] : '';
    $r = requete($base . '/settings.php', [
        'csrf_token'       => $jeton,
        'action'           => 'change_password',
        'current_password' => $ancien,
        'new_password'     => $nouveau,
        'confirm_password' => $nouveau,
    ], $cookies);
    @unlink($cookies);
    verif('Le changement de mot de passe aboutit', str_contains($r['corps'], 'modifie avec succes'));

    verif('Le nouveau mot de passe ouvre le compte', connexion('data02@essai.local', $nouveau));
    verif(
        'L\'ANCIEN mot de passe ne fonctionne plus',
        !connexion('data02@essai.local', $ancien),
        'un second mot de passe valide subsiste'
    );

    echo "\n=== C. Le code ne connait plus qu'une colonne ===\n";
    $ecritures = [];
    $lectures = [];
    foreach ([
        'includes/auth.php', 'includes/database.php', 'register.php', 'settings.php',
        'security-settings.php', '2fa.php', 'admin/reset-password.php', 'scripts/create-admin.php',
    ] as $fichier) {
        foreach (explode("\n", (string) file_get_contents($racine . '/' . $fichier)) as $numero => $ligne) {
            // On cherche la colonne SQL `password`, pas les variables ni les
            // champs de formulaire, qui portent legitimement ce nom.
            if (preg_match('/(SET|,)\s*`?password`?\s*=\s*\?/', $ligne)
                || preg_match('/\(\s*username,\s*email,\s*password\s*,/', $ligne)) {
                $ecritures[] = $fichier . ':' . ($numero + 1);
            }
            if (preg_match('/SELECT[^;]*\bu?\.?password\b(?!_hash)/', $ligne)
                || preg_match('/\$(user|admin)\[.password.\]/', $ligne)) {
                $lectures[] = $fichier . ':' . ($numero + 1);
            }
        }
    }
    verif('Plus aucune ecriture de la colonne `password`', $ecritures === [], implode(', ', $ecritures));
    verif('Plus aucune lecture de la colonne `password`', $lectures === [], implode(', ', $lectures));

    echo "\n=== D. checkAdminCredentials ===\n";
    $source = (string) file_get_contents($racine . '/includes/database.php');
    $extrait = substr($source, (int) strpos($source, 'function checkAdminCredentials'), 1200);
    verif('Ne verifie plus que `password_hash`', !preg_match('/u\.password\b(?!_hash)/', $extrait));
    verif('Controle enfin `is_active`', str_contains($extrait, 'is_active = 1'));

    $db->prepare(
        'INSERT INTO users (id, username, email, password_hash, first_name, last_name, is_active, email_verified)
         VALUES (941, ?, ?, ?, ?, ?, 0, 1)
         ON DUPLICATE KEY UPDATE is_active = 0, password_hash = VALUES(password_hash)'
    )->execute(['essai_data02', 'data02@essai.local', password_hash($nouveau, PASSWORD_BCRYPT), 'Essai', 'Data02']);
    $db->prepare('INSERT IGNORE INTO admins (user_id, role, permissions) VALUES (941, ?, ?)')->execute(['admin', '[]']);
    verif(
        'Un compte desactive n\'ouvre plus l\'administration',
        checkAdminCredentials('data02@essai.local', $nouveau) === false
    );

    echo "\n=== E. Jeux de donnees ===\n";
    foreach (['database/seeds/demo.sql', 'tests/securite/sec06-fixtures.sql', 'tests/securite/sec10-fixtures.sql'] as $fichier) {
        $contenu = (string) file_get_contents($racine . '/' . $fichier);
        verif(
            basename($fichier) . " n'insere plus la colonne retiree",
            !preg_match('/`?password`?\s*,\s*`?password_hash`?/', $contenu)
        );
    }

    echo "\n=== F. Analyse et migration ===\n";
    $sortie = [];
    exec(sprintf('"C:\xampp\php\php.exe" "%s/scripts/analyser-mots-de-passe.php" 2>&1', $racine), $sortie, $code);
    $texte = implode("\n", $sortie);
    verif('Le script d\'analyse s\'execute', $code === 0, $texte);
    verif('... et constate que la migration est passee', str_contains($texte, 'n\'existe plus'), $texte);
    $migration = (string) file_get_contents($racine . '/database/migrations/2026_09_23_0006_data02_mot_de_passe_unique.sql');
    verif('La migration converge avant de supprimer', str_contains($migration, 'SET `password_hash` = `password`'));
    verif('... et sait revenir en arriere', str_contains($migration, '-- DOWN'));
} finally {
    $db->exec('DELETE FROM admins WHERE user_id = 941');
    $db->exec('DELETE FROM users WHERE id = 941');
    $db->exec('DELETE FROM login_attempts');
    $db->exec('DELETE FROM rate_limit_hits');
}

echo "\n";
echo sprintf("Resultat : %d reussi(s), %d echec(s)\n", $ok, $ko);
exit($ko === 0 ? 0 : 1);
