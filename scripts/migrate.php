<?php
/**
 * Migrations de schema (DATA-01).
 *
 * POURQUOI
 *   Jusqu'ici, le schema vivait dans un export unique (database/tchadok.sql)
 *   et les corrections se rattrapaient a la main. Deux installations
 *   divergeaient sans qu'on puisse le savoir, et personne ne pouvait dire ce
 *   qui avait ete applique ou non sur le serveur.
 *
 * PRINCIPE
 *   Chaque changement de schema est un fichier date et numerote, applique une
 *   seule fois, enregistre dans la table schema_migrations avec l'empreinte du
 *   fichier. Une migration deja appliquee et modifiee depuis est signalee :
 *   c'est la seule facon de detecter qu'un serveur ne porte pas ce que le
 *   depot croit lui avoir donne.
 *
 * FORMAT D'UN FICHIER
 *   -- UP      instructions d'application (obligatoire)
 *   -- DOWN    instructions d'annulation (facultatif mais recommande)
 *   Les migrations doivent etre IDEMPOTENTES : CREATE TABLE IF NOT EXISTS,
 *   et pour les ALTER, un test sur information_schema.
 *
 * USAGE (ligne de commande uniquement)
 *   php scripts/migrate.php status
 *   php scripts/migrate.php up
 *   php scripts/migrate.php down --steps=1
 *   php scripts/migrate.php verify
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$racine = dirname(__DIR__);
require_once $racine . '/config/env.php';
require_once $racine . '/includes/database.php';
require_once $racine . '/includes/decoupage-sql.php';

const REPERTOIRE_MIGRATIONS = '/database/migrations/';

// ---------------------------------------------------------------------
// Sortie
// ---------------------------------------------------------------------
function ligne(string $texte = ''): void
{
    echo $texte, PHP_EOL;
}

function ok(string $texte): void
{
    ligne('  [ok]    ' . $texte);
}

function info(string $texte): void
{
    ligne('  [info]  ' . $texte);
}

function attention(string $texte): void
{
    ligne('  [!]     ' . $texte);
}

function erreur(string $texte): never
{
    ligne('  [ERREUR] ' . $texte);
    exit(1);
}

// ---------------------------------------------------------------------
// Base
// ---------------------------------------------------------------------
function connexion(): PDO
{
    $db = TchadokDatabase::getInstance()->getConnection();
    if (!$db) {
        erreur('Base de donnees injoignable. Verifiez la configuration chargee : ' . EnvLoader::fichierCharge());
    }
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    return $db;
}

function creerRegistre(PDO $db): void
{
    $db->exec(
        'CREATE TABLE IF NOT EXISTS schema_migrations (
            version varchar(100) NOT NULL,
            nom varchar(190) NOT NULL,
            applique_le timestamp NOT NULL DEFAULT current_timestamp(),
            duree_ms int(11) NOT NULL DEFAULT 0,
            checksum char(64) NOT NULL,
            PRIMARY KEY (version)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
    );
}

/**
 * @return array<string,array{version:string,nom:string,applique_le:string,checksum:string}>
 */
function migrationsAppliquees(PDO $db): array
{
    $lignes = $db->query('SELECT version, nom, applique_le, duree_ms, checksum FROM schema_migrations ORDER BY version')
                 ->fetchAll(PDO::FETCH_ASSOC);

    $indexees = [];
    foreach ($lignes as $ligne) {
        $indexees[$ligne['version']] = $ligne;
    }

    return $indexees;
}

// ---------------------------------------------------------------------
// Fichiers de migration
// ---------------------------------------------------------------------
/**
 * @return array<string,array{version:string,nom:string,chemin:string,checksum:string}>
 */
function migrationsDisponibles(string $racine): array
{
    $fichiers = glob($racine . REPERTOIRE_MIGRATIONS . '*.sql') ?: [];
    sort($fichiers, SORT_STRING);

    $migrations = [];
    foreach ($fichiers as $chemin) {
        $nom = basename($chemin, '.sql');
        // Version = partie horodatee en tete de nom : 2026_09_23_0001
        $version = preg_match('/^(\d{4}_\d{2}_\d{2}_\d{4})/', $nom, $m) ? $m[1] : $nom;
        $migrations[$version] = [
            'version'  => $version,
            'nom'      => $nom,
            'chemin'   => $chemin,
            'checksum' => hash('sha256', str_replace("\r\n", "\n", (string) file_get_contents($chemin))),
        ];
    }

    ksort($migrations, SORT_STRING);

    return $migrations;
}

/**
 * Extrait la section demandee (UP ou DOWN) d'un fichier de migration.
 */
function section(string $chemin, string $nom): string
{
    $contenu = str_replace("\r\n", "\n", (string) file_get_contents($chemin));
    $lignes = explode("\n", $contenu);

    $courante = 'UP'; // tout ce qui precede un marqueur appartient a UP
    $sections = ['UP' => [], 'DOWN' => []];

    foreach ($lignes as $ligne) {
        if (preg_match('/^\s*--\s*(UP|DOWN)\s*$/i', $ligne, $m)) {
            $courante = strtoupper($m[1]);
            continue;
        }
        $sections[$courante][] = $ligne;
    }

    return trim(implode("\n", $sections[strtoupper($nom)] ?? []));
}

/**
 * Applique une section, une instruction a la fois.
 *
 * Une transaction est ouverte quand c'est possible, mais MySQL valide
 * implicitement a chaque CREATE ou ALTER TABLE : la transaction ne protege
 * donc pas une migration de schema. C'est pourquoi chaque migration doit etre
 * idempotente -- la relancer apres un echec doit etre sans danger.
 */
function executer(PDO $db, string $sql): int
{
    $debut = microtime(true);
    foreach (instructions($sql) as $instruction) {
        $db->exec($instruction);
    }

    return (int) round((microtime(true) - $debut) * 1000);
}

// ---------------------------------------------------------------------
// Commandes
// ---------------------------------------------------------------------
function commandeStatus(PDO $db, string $racine): void
{
    $disponibles = migrationsDisponibles($racine);
    $appliquees  = migrationsAppliquees($db);

    ligne();
    ligne('  Base : ' . EnvLoader::get('DB_DATABASE', '?') . '   (' . EnvLoader::fichierCharge() . ')');
    ligne();

    if (!$disponibles) {
        info('Aucune migration dans database/migrations/.');
        return;
    }

    foreach ($disponibles as $version => $migration) {
        if (!isset($appliquees[$version])) {
            ligne(sprintf('  en attente  %s', $migration['nom']));
            continue;
        }
        $etat = $appliquees[$version]['checksum'] === $migration['checksum'] ? 'appliquee ' : 'MODIFIEE  ';
        ligne(sprintf('  %s  %s   (%s)', $etat, $migration['nom'], $appliquees[$version]['applique_le']));
    }

    foreach ($appliquees as $version => $ligneAppliquee) {
        if (!isset($disponibles[$version])) {
            attention('Appliquee mais absente du depot : ' . $ligneAppliquee['nom']);
        }
    }

    $attente = count(array_diff_key($disponibles, $appliquees));
    ligne();
    info($attente === 0 ? 'Schema a jour.' : $attente . ' migration(s) en attente.');
}

function commandeUp(PDO $db, string $racine): void
{
    $disponibles = migrationsDisponibles($racine);
    $appliquees  = migrationsAppliquees($db);
    $aFaire = array_diff_key($disponibles, $appliquees);

    if (!$aFaire) {
        info('Rien a appliquer : le schema est a jour.');
        return;
    }

    foreach ($aFaire as $version => $migration) {
        $sql = section($migration['chemin'], 'UP');
        if ($sql === '') {
            erreur('Section UP vide : ' . $migration['nom']);
        }

        ligne('  -> ' . $migration['nom']);
        try {
            $duree = executer($db, $sql);
        } catch (Throwable $e) {
            erreur($migration['nom'] . ' : ' . $e->getMessage());
        }

        $db->prepare('INSERT INTO schema_migrations (version, nom, duree_ms, checksum) VALUES (?, ?, ?, ?)')
           ->execute([$version, $migration['nom'], $duree, $migration['checksum']]);

        ok(sprintf('appliquee en %d ms', $duree));
    }
}

function commandeDown(PDO $db, string $racine, int $pas): void
{
    $disponibles = migrationsDisponibles($racine);
    $appliquees  = migrationsAppliquees($db);

    if (!$appliquees) {
        info('Aucune migration appliquee.');
        return;
    }

    $versions = array_keys($appliquees);
    rsort($versions, SORT_STRING);
    $versions = array_slice($versions, 0, max(1, $pas));

    foreach ($versions as $version) {
        $migration = $disponibles[$version] ?? null;
        if ($migration === null) {
            erreur('Fichier introuvable pour ' . $version . ' : annulation impossible.');
        }

        $sql = section($migration['chemin'], 'DOWN');
        if ($sql === '') {
            erreur($migration['nom'] . " n'a pas de section DOWN : annulation impossible.");
        }

        ligne('  <- ' . $migration['nom']);
        try {
            executer($db, $sql);
        } catch (Throwable $e) {
            erreur($migration['nom'] . ' : ' . $e->getMessage());
        }

        $db->prepare('DELETE FROM schema_migrations WHERE version = ?')->execute([$version]);
        ok('annulee');
    }
}

function commandeVerify(PDO $db, string $racine): int
{
    $disponibles = migrationsDisponibles($racine);
    $appliquees  = migrationsAppliquees($db);
    $problemes = 0;

    foreach ($appliquees as $version => $ligneAppliquee) {
        if (!isset($disponibles[$version])) {
            attention('Appliquee mais absente du depot : ' . $ligneAppliquee['nom']);
            $problemes++;
            continue;
        }
        if ($disponibles[$version]['checksum'] !== $ligneAppliquee['checksum']) {
            attention('Modifiee apres application : ' . $ligneAppliquee['nom']);
            $problemes++;
        }
    }

    if ($problemes === 0) {
        ok('Toutes les migrations appliquees correspondent aux fichiers du depot.');
    } else {
        ligne();
        info("Une migration modifiee apres coup signifie que ce serveur ne porte pas ce que le depot decrit.");
        info('Corriger en ajoutant une NOUVELLE migration, jamais en editant une migration deja appliquee.');
    }

    return $problemes;
}

// ---------------------------------------------------------------------
// Point d'entree
// ---------------------------------------------------------------------
$commande = $argv[1] ?? 'status';
$pas = 1;
foreach (array_slice($argv, 2) as $argument) {
    if (preg_match('/^--steps=(\d+)$/', $argument, $m)) {
        $pas = (int) $m[1];
    }
}

ligne();
ligne('  Migrations Tchadok');

$db = connexion();
creerRegistre($db);

switch ($commande) {
    case 'status':
        commandeStatus($db, $racine);
        break;

    case 'up':
        commandeUp($db, $racine);
        break;

    case 'down':
        commandeDown($db, $racine, $pas);
        break;

    case 'verify':
        exit(commandeVerify($db, $racine) === 0 ? 0 : 1);

    default:
        ligne();
        ligne('  Commandes : status | up | down --steps=N | verify');
        exit(1);
}

ligne();
exit(0);
