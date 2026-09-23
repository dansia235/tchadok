<?php
/**
 * Creation d'un compte administrateur - Tchadok Platform
 *
 * Tache SEC-05.
 *
 * Remplace les comptes livres dans l'ancien dump database/tchadok.sql, dont
 * le mot de passe etait documente comme public, et les outils web supprimes
 * en SEC-02 (install.php, admin/execute-sql.php) qui creaient des
 * administrateurs au mot de passe fixe.
 *
 * UTILISATION INTERACTIVE
 *   php scripts/create-admin.php
 *
 * UTILISATION NON INTERACTIVE (deploiement automatise)
 *   set TCHADOK_ADMIN_PASSWORD=...        (Windows)
 *   export TCHADOK_ADMIN_PASSWORD=...     (Linux)
 *   php scripts/create-admin.php --email=a@b.td --username=alice \
 *       --first-name=Alice --last-name=Doe [--role=super_admin|admin]
 *
 * Le mot de passe n'est JAMAIS accepte en argument : il finirait dans
 * l'historique du shell et dans la liste des processus visibles par
 * tout utilisateur du serveur.
 *
 * POLITIQUE DE MOT DE PASSE
 *   production : 12 caracteres minimum, 3 familles de caracteres sur 4,
 *                hors liste des mots de passe courants, sans reprendre
 *                l'identifiant ou l'adresse. REFUS en cas d'echec.
 *   local      : 8 caracteres minimum. Un mot de passe faible est accepte
 *                avec un avertissement (identifiants locaux simples par
 *                decision du 22/09/2026).
 *
 * Execution en ligne de commande uniquement.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Ce script ne s'execute qu'en ligne de commande.\n";
    exit(1);
}

require_once dirname(__DIR__) . '/config/env.php';

// ---------------------------------------------------------------------
// Presentation
// ---------------------------------------------------------------------
function sortie(string $texte = ''): void { fwrite(STDOUT, $texte . PHP_EOL); }
function erreur(string $texte): void     { fwrite(STDERR, '  [ECHEC] ' . $texte . PHP_EOL); }
function alerte(string $texte): void     { fwrite(STDOUT, '  [!]     ' . $texte . PHP_EOL); }
function ok(string $texte): void         { fwrite(STDOUT, '  [ok]    ' . $texte . PHP_EOL); }

function interactif(): bool
{
    if (function_exists('stream_isatty')) {
        return stream_isatty(STDIN);
    }
    if (function_exists('posix_isatty')) {
        return posix_isatty(STDIN);
    }
    return false;
}

function demander(string $libelle, ?string $defaut = null): string
{
    $invite = $defaut !== null ? "{$libelle} [{$defaut}] : " : "{$libelle} : ";
    fwrite(STDOUT, $invite);
    $ligne = fgets(STDIN);
    $valeur = $ligne === false ? '' : trim($ligne);
    return ($valeur === '' && $defaut !== null) ? $defaut : $valeur;
}

/**
 * Saisie sans echo a l'ecran.
 * Windows : PowerShell Read-Host -AsSecureString.
 * Unix    : stty -echo.
 */
function demanderMasque(string $libelle): string
{
    if (DIRECTORY_SEPARATOR === '\\') {
        $commande = 'powershell -NoProfile -Command "'
            . '$s = Read-Host -AsSecureString -Prompt \'' . str_replace("'", "''", $libelle) . '\'; '
            . '$b = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($s); '
            . 'try { [Runtime.InteropServices.Marshal]::PtrToStringBSTR($b) } '
            . 'finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($b) }"';
        $valeur = shell_exec($commande);
        return $valeur === null ? '' : rtrim($valeur, "\r\n");
    }

    fwrite(STDOUT, $libelle . ' : ');
    shell_exec('stty -echo');
    $ligne = fgets(STDIN);
    shell_exec('stty echo');
    fwrite(STDOUT, PHP_EOL);
    return $ligne === false ? '' : rtrim($ligne, "\r\n");
}

function lireOptions(array $argv): array
{
    $options = [];
    foreach (array_slice($argv, 1) as $arg) {
        if (preg_match('/^--([a-z-]+)=(.*)$/', $arg, $m)) {
            $options[$m[1]] = $m[2];
        } elseif (preg_match('/^--([a-z-]+)$/', $arg, $m)) {
            $options[$m[1]] = true;
        }
    }
    return $options;
}

// ---------------------------------------------------------------------
// Politique de mot de passe
// ---------------------------------------------------------------------
const MOTS_DE_PASSE_COURANTS = [
    '12345678', '123456789', '1234567890', '12341234', '11111111', '00000000',
    'password', 'password1', 'password123', 'motdepasse', 'azerty', 'azerty123',
    'azertyuiop', 'qwerty', 'qwerty123', 'qwertyuiop', 'admin', 'admin123',
    'administrator', 'administrateur', 'root', 'toor', 'secret', 'welcome',
    'bienvenue', 'soleil', 'iloveyou', 'letmein', 'changeme', 'default',
    'tchadok', 'tchadok123', 'tchadok2024', 'tchadok2025', 'tchadok2026',
    'tchad', 'tchad123', 'ndjamena', 'dansia', 'musique', 'music',
];

/**
 * @return array{0: string[], 1: string[]} [erreurs bloquantes, avertissements]
 */
function evaluerMotDePasse(string $mdp, string $username, string $email, bool $production): array
{
    $problemes = [];

    $longueurMin = $production ? 12 : 8;
    if (mb_strlen($mdp) < $longueurMin) {
        $problemes[] = "moins de {$longueurMin} caracteres";
    }

    $familles = 0;
    $familles += preg_match('/[a-z]/', $mdp) ? 1 : 0;
    $familles += preg_match('/[A-Z]/', $mdp) ? 1 : 0;
    $familles += preg_match('/[0-9]/', $mdp) ? 1 : 0;
    $familles += preg_match('/[^a-zA-Z0-9]/', $mdp) ? 1 : 0;
    if ($familles < 3) {
        $problemes[] = "moins de 3 familles de caracteres (minuscules, majuscules, chiffres, symboles)";
    }

    $normalise = strtolower($mdp);
    if (in_array($normalise, MOTS_DE_PASSE_COURANTS, true)) {
        $problemes[] = "figure dans la liste des mots de passe courants";
    }

    $partieLocale = strtolower((string) strstr($email, '@', true));
    foreach (array_filter([strtolower($username), $partieLocale]) as $fragment) {
        if (mb_strlen($fragment) >= 4 && str_contains($normalise, $fragment)) {
            $problemes[] = "reprend l'identifiant ou l'adresse e-mail";
            break;
        }
    }

    if (preg_match('/^(.)\1+$/', $mdp)) {
        $problemes[] = "un seul caractere repete";
    }

    if ($production) {
        return [$problemes, []];
    }

    // En local : seule la longueur minimale est bloquante
    $bloquants = array_values(array_filter($problemes, fn($p) => str_starts_with($p, 'moins de 8')));
    $avertissements = array_values(array_diff($problemes, $bloquants));
    return [$bloquants, $avertissements];
}

// ---------------------------------------------------------------------
// Programme
// ---------------------------------------------------------------------
$options    = lireOptions($argv);
$production = EnvLoader::isProduction();

if (isset($options['help']) || isset($options['h'])) {
    sortie();
    sortie('create-admin - creation d\'un compte administrateur Tchadok');
    sortie();
    sortie('  php scripts/create-admin.php');
    sortie('  php scripts/create-admin.php --email=... --username=... --first-name=... --last-name=... [--role=...]');
    sortie();
    sortie('  Le mot de passe est saisi de facon masquee, ou lu dans la variable');
    sortie('  d\'environnement TCHADOK_ADMIN_PASSWORD. Jamais en argument.');
    sortie();
    exit(0);
}

sortie();
sortie('Creation d\'un compte administrateur Tchadok');
sortie('--------------------------------------------');
sortie('  Environnement : ' . EnvLoader::environment()
    . ' (' . basename(EnvLoader::fichierCharge()) . ')');
sortie('  Politique     : ' . ($production ? 'STRICTE (production)' : 'souple (local)'));
sortie();

$estInteractif = interactif();

// --- Identite ---
$username  = (string) ($options['username']   ?? ($estInteractif ? demander('Identifiant') : ''));
$email     = (string) ($options['email']      ?? ($estInteractif ? demander('Adresse e-mail') : ''));
$prenom    = (string) ($options['first-name'] ?? ($estInteractif ? demander('Prenom') : ''));
$nom       = (string) ($options['last-name']  ?? ($estInteractif ? demander('Nom') : ''));
$role      = (string) ($options['role']       ?? 'super_admin');

$erreurs = [];
if (!preg_match('/^[a-zA-Z0-9_.-]{3,50}$/', $username)) {
    $erreurs[] = "Identifiant invalide : 3 a 50 caracteres, lettres, chiffres, point, tiret, souligne.";
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) {
    $erreurs[] = "Adresse e-mail invalide.";
}
if ($prenom === '' || mb_strlen($prenom) > 50) {
    $erreurs[] = "Prenom requis (50 caracteres maximum).";
}
if ($nom === '' || mb_strlen($nom) > 50) {
    $erreurs[] = "Nom requis (50 caracteres maximum).";
}
if (!in_array($role, ['super_admin', 'admin'], true)) {
    $erreurs[] = "Role invalide : super_admin ou admin.";
}
if ($erreurs) {
    sortie();
    foreach ($erreurs as $e) {
        erreur($e);
    }
    if (!$estInteractif) {
        sortie();
        sortie('  Mode non interactif : fournir --username, --email, --first-name, --last-name.');
    }
    sortie();
    exit(1);
}

// --- Mot de passe ---
$mdp = getenv('TCHADOK_ADMIN_PASSWORD');
$depuisEnv = ($mdp !== false && $mdp !== '');

if (!$depuisEnv) {
    if (!$estInteractif) {
        erreur('Mode non interactif : definir la variable TCHADOK_ADMIN_PASSWORD.');
        exit(1);
    }
    $mdp = demanderMasque('Mot de passe');
    $confirmation = demanderMasque('Confirmation');
    if (!hash_equals($mdp, $confirmation)) {
        erreur('Les deux saisies ne correspondent pas.');
        exit(1);
    }
}

[$bloquants, $avertissements] = evaluerMotDePasse((string) $mdp, $username, $email, $production);

if ($bloquants) {
    sortie();
    erreur('Mot de passe refuse :');
    foreach ($bloquants as $b) {
        sortie('            - ' . $b);
    }
    sortie();
    exit(1);
}
foreach ($avertissements as $a) {
    alerte("Mot de passe faible : {$a}. Accepte en local ; il serait REFUSE en production.");
}

// --- Base de donnees ---
try {
    $manquantes = EnvLoader::requireKeys(['DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD']);
    if ($manquantes) {
        throw new RuntimeException('Variables manquantes : ' . implode(', ', $manquantes));
    }

    $pdo = new PDO(
        sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            env('DB_HOST', '127.0.0.1'),
            env('DB_PORT', '3306'),
            env('DB_DATABASE')
        ),
        env('DB_USERNAME'),
        env('DB_PASSWORD'),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (Throwable $e) {
    erreur('Connexion a la base impossible : ' . $e->getMessage());
    exit(1);
}

$stmt = $pdo->prepare('SELECT username, email FROM users WHERE username = ? OR email = ? LIMIT 1');
$stmt->execute([$username, $email]);
if ($existant = $stmt->fetch()) {
    erreur(sprintf(
        'Un compte existe deja avec %s.',
        strcasecmp($existant['username'], $username) === 0 ? "l'identifiant {$username}" : "l'adresse {$email}"
    ));
    exit(1);
}

$hash = password_hash((string) $mdp, PASSWORD_BCRYPT, ['cost' => 12]);
unset($mdp, $confirmation);

try {
    $pdo->beginTransaction();

    // DATA-02 : une seule colonne de mot de passe. `password` a ete retiree :
    // deux colonnes acceptees a la connexion donnaient a un compte un second
    // mot de passe valide des qu'elles divergeaient.
    $insert = $pdo->prepare(
        'INSERT INTO users
            (username, email, password_hash, first_name, last_name,
             country, email_verified, is_active, created_at)
         VALUES (?, ?, ?, ?, ?, ?, 1, 1, NOW())'
    );
    $insert->execute([$username, $email, $hash, $prenom, $nom, 'Tchad']);
    $userId = (int) $pdo->lastInsertId();

    $admin = $pdo->prepare(
        'INSERT INTO admins (user_id, role, permissions, created_at) VALUES (?, ?, ?, NOW())'
    );
    $admin->execute([$userId, $role, $role === 'super_admin' ? '["all"]' : '[]']);

    // SEC-19 : depuis les roles nommes, c'est user_roles qui fait foi. La ligne
    // `admins` est conservee tant que DATA-* ne l'a pas retiree, mais elle
    // n'ouvre plus aucun droit a elle seule : sans cette attribution, le compte
    // cree ici n'aurait acces a rien.
    $slug = $role === 'super_admin' ? 'super_admin' : 'admin_plateforme';
    $attribution = $pdo->prepare(
        'INSERT IGNORE INTO user_roles (user_id, role_id) SELECT ?, id FROM roles WHERE slug = ?'
    );
    $attribution->execute([$userId, $slug]);

    if ($attribution->rowCount() === 0) {
        throw new RuntimeException(
            "Le role « {$slug} » est introuvable : appliquez d'abord les migrations (php scripts/migrate.php up)."
        );
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    erreur('Creation impossible : ' . $e->getMessage());
    exit(1);
}

// Trace. Le journal d'audit en base arrivera avec SEC-19 ; en attendant,
// l'evenement est inscrit dans le journal applicatif.
error_log(sprintf(
    '[Tchadok][audit] Compte administrateur cree en ligne de commande : id=%d, identifiant=%s, role=%s, operateur=%s, machine=%s',
    $userId,
    $username,
    $role,
    get_current_user(),
    gethostname()
));

sortie();
ok(sprintf('Compte cree : %s (%s), identifiant %d', $username, $email, $userId));
ok("Role : {$role}");
sortie();
if ($production) {
    sortie('  Pensez a activer la double authentification des sa disponibilite (SEC-20).');
    sortie();
}
exit(0);
