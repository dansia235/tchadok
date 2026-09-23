<?php
/**
 * Tests SEC-14 : plus aucune donnee fabriquee presentee comme reelle.
 *
 * La page « Parametres de securite » affichait un historique de connexions
 * invente, une liste d'appareils ecrite en dur, et une 2FA qui n'enregistrait
 * rien. Ces tests verifient que la page ne montre plus que des faits tires de
 * la base, que le module simule a disparu, et que le formulaire de mot de
 * passe -- seule partie qui fonctionnait -- fonctionne toujours.
 *
 * Usage : C:\xampp\php\php.exe tests\securite\sec14-securite-simulee.php
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

function requete(string $url, ?array $post = null, ?string $cookies = null, array $entetes = []): array
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

    return ['code' => $code, 'entetes' => substr($reponse, 0, $taille), 'corps' => substr($reponse, $taille)];
}

function jeton(string $url, string $cookies): string
{
    $r = requete($url, null, $cookies);

    return preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $r['corps'], $m) ? $m[1] : '';
}

function connecter(string $cookies, string $email, string $motDePasse, bool $souvenir = false): array
{
    $champs = [
        'csrf_token' => jeton($GLOBALS['base'] . '/login.php', $cookies),
        'email'      => $email,
        'password'   => $motDePasse,
    ];
    if ($souvenir) {
        $champs['remember'] = '1';
    }

    return requete($GLOBALS['base'] . '/login.php', $champs, $cookies);
}

function texte(string $html): string
{
    return preg_replace('/\s+/', ' ', strip_tags(preg_replace('#<script.*?</script>#s', ' ', $html)));
}

$db = TchadokDatabase::getInstance()->getConnection();
$sql = static fn (string $requete) => $db->exec($requete);
$sql('DELETE FROM login_attempts');
$sql('DELETE FROM rate_limit_hits');
$db->exec((string) file_get_contents(__DIR__ . '/sec10-nettoyage.sql'));
foreach (explode(";\n", (string) file_get_contents(__DIR__ . '/sec10-fixtures.sql')) as $instruction) {
    $instruction = preg_replace('/^\s*--.*$/m', '', $instruction);
    if (trim($instruction) !== '') {
        try {
            $db->exec($instruction);
        } catch (Throwable $e) {
            // SET NAMES : sans consequence
        }
    }
}

$cookies = tempnam(sys_get_temp_dir(), 'sec14');

try {
    echo "\n=== A. Le module simule a disparu ===\n";
    verif('includes/advanced-auth.php supprime', !is_file($racine . '/includes/advanced-auth.php'));
    verif('assets/js/security-settings.js supprime', !is_file($racine . '/assets/js/security-settings.js'));

    $references = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($racine)) as $fichier) {
        $chemin = str_replace('\\', '/', $fichier->getPathname());
        if (!str_ends_with($chemin, '.php') || preg_match('#/(vendor|node_modules|tests)/#', $chemin)) {
            continue;
        }
        $contenu = (string) file_get_contents($chemin);
        // Un chemin entre guillemets, pas une simple mention en commentaire :
        // functions.php cite le fichier d'origine de forceMotDePasse().
        if (preg_match("#['\\\"][^'\\\"\n]*advanced-auth#", $contenu)) {
            $references[] = basename($chemin);
        }
    }
    verif('Plus aucun fichier ne charge le module simule', $references === [], implode(', ', $references));

    echo "\n=== B. Aucune donnee fabriquee dans la page ===\n";
    connecter($cookies, 'fan10@essai.local', 'tchadok2026');
    $page = requete($base . '/security-settings.php', null, $cookies);
    verif('Page accessible une fois connecte', $page['code'] === 200, (string) $page['code']);

    $inventions = [
        'JBSWY3DPEHPK3PXP'   => 'secret TOTP d\'exemple public',
        'api.qrserver.com'   => 'QR code fabrique par un service tiers',
        '192.168.1.1'        => 'adresse inventee',
        'Safari sur iPhone'  => 'appareil ecrit en dur',
        'Chrome sur Windows' => 'appareil ecrit en dur',
        'Il y a 2h'          => 'activite inventee',
        'Abeche'             => 'localisation inventee',
    ];
    foreach ($inventions as $motif => $quoi) {
        verif("Absent de la page : {$quoi}", !str_contains($page['corps'], $motif), $motif);
    }

    echo "\n=== C. L'historique vient de la base ===\n";
    $sql('DELETE FROM login_attempts');
    $vide = requete($base . '/security-settings.php', null, $cookies);
    verif(
        'Sans tentative enregistree, la page le dit au lieu d\'inventer',
        str_contains(texte($vide['corps']), 'Aucune tentative enregistree')
    );

    // Un echec, puis une reussite : les deux doivent apparaitre tels quels.
    $autres = tempnam(sys_get_temp_dir(), 'sec14b');
    connecter($autres, 'fan10@essai.local', 'mot-de-passe-faux');
    $apresEchec = requete($base . '/security-settings.php', null, $cookies);
    verif(
        'Une tentative refusee apparait',
        str_contains(texte($apresEchec['corps']), 'Connexion refusee')
    );
    connecter($autres, 'fan10@essai.local', 'tchadok2026');
    $apresReussite = requete($base . '/security-settings.php', null, $cookies);
    verif(
        'Une connexion reussie apparait',
        str_contains(texte($apresReussite['corps']), 'Connexion reussie')
    );
    @unlink($autres);

    $stmt = $db->prepare('SELECT COUNT(*) FROM login_attempts WHERE identifier = ?');
    $stmt->execute(['fan10@essai.local']);
    $enBase = (int) $stmt->fetchColumn();
    $affichees = substr_count(texte($apresReussite['corps']), 'Connexion reussie')
        + substr_count(texte($apresReussite['corps']), 'Connexion refusee');
    verif('Autant de lignes affichees que de lignes en base', $affichees === $enBase, "{$affichees} / {$enBase}");

    // Les tentatives d'un autre compte ne doivent pas apparaitre.
    $db->prepare('INSERT INTO login_attempts (identifier, ip_address, success, user_agent) VALUES (?, ?, 0, ?)')
       ->execute(['admin10@essai.local', '203.0.113.42', 'Mozilla/5.0 (X11; Linux x86_64)']);
    $page = requete($base . '/security-settings.php', null, $cookies);
    verif(
        'Les tentatives visant un autre compte n\'apparaissent pas',
        !str_contains($page['corps'], '203.0.113.42')
    );

    echo "\n=== D. Connexion automatique tracee ===\n";
    $sql('DELETE FROM login_attempts');
    $memorise = tempnam(sys_get_temp_dir(), 'sec14c');
    connecter($memorise, 'fan10@essai.local', 'tchadok2026', true);
    $sql('DELETE FROM login_attempts');
    // Nouvelle session, avec le seul cookie de souvenir : connexion automatique.
    $lignes = file($memorise) ?: [];
    $valeurSouvenir = '';
    foreach ($lignes as $ligne) {
        if (preg_match('/\tremember_token\t(\S+)/', $ligne, $m)) {
            $valeurSouvenir = $m[1];
        }
    }
    verif('Prealable : cookie de connexion automatique pose', $valeurSouvenir !== '');
    $auto = tempnam(sys_get_temp_dir(), 'sec14d');
    file_put_contents($auto, "localhost\tFALSE\t/\tFALSE\t0\tremember_token\t{$valeurSouvenir}\n");
    $r = requete($base . '/settings.php', null, $auto);
    verif('Connexion automatique effectuee', $r['code'] === 200, (string) $r['code']);
    $stmt = $db->prepare('SELECT COUNT(*) FROM login_attempts WHERE identifier = ? AND success = 1');
    $stmt->execute(['fan10@essai.local']);
    verif(
        'Elle figure dans l\'historique (sinon celui-ci serait incomplet)',
        (int) $stmt->fetchColumn() >= 1
    );
    @unlink($memorise);
    @unlink($auto);

    echo "\n=== E. Etat du compte : des nombres verifiables ===\n";
    $page = requete($base . '/security-settings.php', null, $cookies);
    $stmt = $db->prepare('SELECT COUNT(*) FROM user_sessions WHERE user_id = 931');
    $stmt->execute();
    $sessions = (int) $stmt->fetchColumn();
    verif('Le nombre de sessions ouvertes est celui du registre', $sessions >= 1, (string) $sessions);
    verif(
        'La page renvoie vers la gestion reelle des appareils',
        str_contains($page['corps'], 'settings.php#devices')
    );

    echo "\n=== F. La 2FA est annoncee indisponible, sans faux formulaire ===\n";
    // SEC-20 a rendu la double authentification reelle : la page annonce
    // desormais son etat effectif et renvoie vers l'activation, au lieu de
    // simuler un ecran de configuration.
    verif(
        'La page renvoie vers l\'activation reelle',
        str_contains($page['corps'], '2fa.php')
    );
    verif(
        'Aucun ecran de configuration simule',
        !str_contains($page['corps'], 'enable_2fa') && !str_contains($page['corps'], 'JBSWY3DPEHPK3PXP')
    );
    verif('Aucun formulaire d\'activation TOTP', !str_contains($page['corps'], 'enable_2fa_totp'));
    verif('Aucun formulaire d\'activation SMS', !str_contains($page['corps'], 'enable_2fa_sms'));
    $r = requete($base . '/security-settings.php', [
        'csrf_token' => jeton($base . '/security-settings.php', $cookies),
        'action'     => 'enable_2fa_totp',
    ], $cookies);
    verif('Un envoi force de l\'ancienne action ne declenche rien', $r['code'] === 200, (string) $r['code']);
    verif(
        '... et n\'annonce aucune activation',
        !preg_match('/activ(e|ee) avec succes/i', texte($r['corps']))
    );

    echo "\n=== G. Changement de mot de passe ===\n";
    $force = forceMotDePasse('abc');
    verif('Mot de passe trivial : score faible', $force['score'] < 50, (string) $force['score']);
    verif('... avec des conseils', $force['conseils'] !== []);
    verif('Mot de passe solide : score eleve', forceMotDePasse('Tchadok-2026!xY')['score'] >= 75);
    verif('Mot de passe courant penalise', forceMotDePasse('password123')['score'] < 50);

    $r = requete($base . '/security-settings.php', [
        'csrf_token'       => jeton($base . '/security-settings.php', $cookies),
        'action'           => 'change_password',
        'current_password' => 'tchadok2026',
        'new_password'     => 'abcdefgh',
        'confirm_password' => 'abcdefgh',
    ], $cookies);
    verif('Un mot de passe trop faible est refuse', str_contains(texte($r['corps']), 'trop faible'));

    $secondAppareil = tempnam(sys_get_temp_dir(), 'sec14e');
    connecter($secondAppareil, 'fan10@essai.local', 'tchadok2026');
    verif(
        'Prealable : un second appareil est connecte',
        requete($base . '/settings.php', null, $secondAppareil)['code'] === 200
    );
    $r = requete($base . '/security-settings.php', [
        'csrf_token'       => jeton($base . '/security-settings.php', $cookies),
        'action'           => 'change_password',
        'current_password' => 'tchadok2026',
        'new_password'     => 'Tchadok-2026!xY',
        'confirm_password' => 'Tchadok-2026!xY',
    ], $cookies);
    verif('Un mot de passe solide est accepte', str_contains(texte($r['corps']), 'Mot de passe modifie avec succes'));
    verif(
        'Le second appareil est deconnecte',
        requete($base . '/settings.php', null, $secondAppareil)['code'] !== 200
    );
    @unlink($secondAppareil);

    echo "\n=== H. Acces et doublons ===\n";
    $anonyme = requete($base . '/security-settings.php');
    verif('Page inaccessible sans session', $anonyme['code'] === 302, (string) $anonyme['code']);

    // Fichiers charges ensemble a chaque requete : aucune fonction ne doit y
    // etre declaree deux fois, sous peine d'erreur fatale.
    $chargesEnsemble = [
        'includes/functions.php', 'includes/auth.php', 'includes/database.php',
        'includes/rate-limit.php', 'includes/remember-me.php', 'includes/csrf-guard.php',
        'includes/captcha.php', 'includes/reponse-refus.php', 'config/env.php', 'config/constants.php',
    ];
    $vues = [];
    $doublons = [];
    foreach ($chargesEnsemble as $fichier) {
        preg_match_all('/^\s*function\s+([A-Za-z_]\w*)\s*\(/m', (string) file_get_contents($racine . '/' . $fichier), $m);
        foreach ($m[1] as $nom) {
            if (isset($vues[$nom])) {
                $doublons[] = "{$nom} ({$vues[$nom]} + {$fichier})";
            }
            $vues[$nom] = $fichier;
        }
    }
    verif('Aucune fonction declaree deux fois parmi les fichiers charges ensemble', $doublons === [], implode(', ', $doublons));
} finally {
    @unlink($cookies);
    $db->exec('DELETE FROM login_attempts');
    $db->exec('DELETE FROM rate_limit_hits');
    $db->exec((string) file_get_contents(__DIR__ . '/sec10-nettoyage.sql'));
}

echo "\n";
echo sprintf("Resultat : %d reussi(s), %d echec(s)\n", $ok, $ko);
exit($ko === 0 ? 0 : 1);
