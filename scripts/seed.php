<?php
/**
 * Chargement des donnees de reference et de demonstration (DATA-08).
 *
 * POURQUOI
 *   Sur une installation neuve, la table `genres` etait VIDE : aucune
 *   statistique par genre n'etait possible, et les formulaires de depot
 *   invitaient les artistes a creer leurs propres genres. Le referentiel est
 *   desormais livre avec le depot et charge par une commande.
 *
 * DEUX JEUX, DEUX REGLES
 *   referentiel  database/seeds/referentiel.sql -- toute installation,
 *                production comprise. Rejouable : n'ajoute que ce qui
 *                manque, n'ecrase jamais une modification editoriale.
 *   demo         database/seeds/demo.sql -- LOCAL UNIQUEMENT. Refuse en
 *                production : il contient un super-administrateur au mot de
 *                passe documente.
 *
 * Les deux refusent de s'executer si des migrations sont en attente : les
 * donnees supposent le schema a jour.
 *
 * USAGE (ligne de commande uniquement)
 *   php scripts/seed.php status
 *   php scripts/seed.php referentiel
 *   php scripts/seed.php demo
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$racine = dirname(__DIR__);
require_once $racine . '/includes/functions.php';
require_once $racine . '/includes/decoupage-sql.php';

const JEUX = [
    'referentiel' => '/database/seeds/referentiel.sql',
    'demo'        => '/database/seeds/demo.sql',
];

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
// Controles
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

/**
 * Versions des migrations presentes dans le depot mais pas en base.
 *
 * @return string[]
 */
function migrationsEnAttente(PDO $db, string $racine): array
{
    $disponibles = [];
    foreach (glob($racine . '/database/migrations/*.sql') ?: [] as $chemin) {
        if (preg_match('/^(\d{4}_\d{2}_\d{2}_\d{4})/', basename($chemin), $m)) {
            $disponibles[] = $m[1];
        }
    }

    try {
        $appliquees = $db->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        return $disponibles;
    }

    return array_values(array_diff($disponibles, $appliquees));
}

// ---------------------------------------------------------------------
// Commandes
// ---------------------------------------------------------------------
function commandeStatus(PDO $db): void
{
    $compter = fn(string $sql): int => (int) $db->query($sql)->fetchColumn();

    ligne();
    ligne('  Base : ' . EnvLoader::get('DB_DATABASE', '?') . '   (environnement ' . EnvLoader::environment() . ')');
    ligne();
    ligne(sprintf('  categories  %3d', $compter("SELECT COUNT(*) FROM genres WHERE parent_id IS NULL AND EXISTS (SELECT 1 FROM genres e WHERE e.parent_id = genres.id)")));
    ligne(sprintf('  genres      %3d   dont %d proposables au depot',
        $compter('SELECT COUNT(*) FROM genres g WHERE NOT EXISTS (SELECT 1 FROM genres e WHERE e.parent_id = g.id)'),
        count(getGenresSelectionnables())));
    ligne(sprintf('  provinces   %3d', $compter('SELECT COUNT(*) FROM regions')));
    ligne(sprintf('  roles       %3d   (migration 0004)', $compter('SELECT COUNT(*) FROM roles')));
    ligne(sprintf('  tarifs      %3d   (migration 0008)', $compter('SELECT COUNT(*) FROM pricing_rules')));

    $sansCategorie = $compter('SELECT COUNT(*) FROM genres g WHERE g.parent_id IS NULL AND NOT EXISTS (SELECT 1 FROM genres e WHERE e.parent_id = g.id)');
    if ($sansCategorie > 0) {
        ligne();
        attention("{$sansCategorie} genre(s) sans categorie : non proposes au depot. A rattacher ou fusionner (TAXO-03).");
    }
    ligne();
}

function commandeCharger(PDO $db, string $racine, string $jeu): void
{
    if ($jeu === 'demo' && (EnvLoader::isProduction() || EnvLoader::environment() !== 'local')) {
        erreur('Le jeu de demonstration ne se charge qu\'en environnement local. Environnement actuel : '
            . EnvLoader::environment() . ' (' . basename(EnvLoader::fichierCharge()) . ').');
    }

    $attente = migrationsEnAttente($db, $racine);
    if ($attente) {
        erreur(count($attente) . ' migration(s) en attente (' . implode(', ', $attente) . ').'
            . ' Lancez d\'abord : php scripts/migrate.php up');
    }

    $chemin = $racine . JEUX[$jeu];
    if (!is_file($chemin)) {
        erreur('Fichier absent : ' . JEUX[$jeu]);
    }

    $avant = etat($db);

    ligne('  -> ' . ltrim(JEUX[$jeu], '/'));
    try {
        foreach (instructions(str_replace("\r\n", "\n", (string) file_get_contents($chemin))) as $instruction) {
            $db->exec($instruction);
            // INSERT IGNORE transforme en avertissement toute erreur de ligne,
            // pas seulement les doublons attendus (1062) : une valeur tronquee
            // passerait sinon en silence.
            foreach ($db->query('SHOW WARNINGS')->fetchAll(PDO::FETCH_ASSOC) as $avertissement) {
                if ((int) $avertissement['Code'] !== 1062) {
                    attention($avertissement['Message']);
                }
            }
        }
    } catch (Throwable $e) {
        // Les jeux ouvrent leur transaction en SQL (START TRANSACTION) : PDO
        // l'ignore, inTransaction() renverrait false. Annulation explicite.
        try {
            $db->exec('ROLLBACK');
        } catch (Throwable $ignoree) {
        }
        erreur('Chargement interrompu, rien n\'a ete conserve : ' . $e->getMessage());
    }

    $apres = etat($db);
    foreach ($apres as $table => $nombre) {
        $ajout = $nombre - $avant[$table];
        if ($ajout > 0) {
            ok(sprintf('%-8s +%d', $table, $ajout));
        }
    }
    if ($apres === $avant) {
        info('Rien a ajouter : tout etait deja charge.');
        return;
    }

    if ($jeu === 'referentiel') {
        JournalAudit::enregistrer('taxonomie.modifiee', [
            'cible_type' => 'referentiel',
            'avant'      => $avant,
            'apres'      => $apres,
            'raison'     => 'Chargement du referentiel (scripts/seed.php) par ' . get_current_user() . ' sur ' . gethostname(),
        ]);
    }
}

/**
 * @return array<string,int>
 */
function etat(PDO $db): array
{
    $etat = [];
    foreach (['genres', 'regions', 'users', 'user_roles'] as $table) {
        $etat[$table] = (int) $db->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    }

    return $etat;
}

// ---------------------------------------------------------------------
// Point d'entree
// ---------------------------------------------------------------------
$commande = $argv[1] ?? 'status';

ligne();
ligne('  Donnees de reference Tchadok');

$db = connexion();

switch ($commande) {
    case 'status':
        commandeStatus($db);
        break;

    case 'referentiel':
    case 'demo':
        commandeCharger($db, $racine, $commande);
        ligne();
        break;

    default:
        ligne();
        ligne('  Commandes : status | referentiel | demo');
        exit(1);
}

exit(0);
