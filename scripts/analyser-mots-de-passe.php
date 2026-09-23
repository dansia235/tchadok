<?php
/**
 * DATA-02, premiere etape : etat des lieux avant la fusion des deux colonnes
 * de mot de passe.
 *
 * A LANCER AVANT LA MIGRATION, sur le serveur. Le rapport dit combien de
 * comptes ont deux mots de passe valides distincts -- c'est-a-dire combien de
 * personnes disposent, sans le savoir, d'un second mot de passe qui continuera
 * d'ouvrir leur compte tant que la colonne existe.
 *
 * Aucune ecriture par defaut : le rapport est produit, rien n'est modifie.
 *
 * Usage :
 *   php scripts/analyser-mots-de-passe.php
 *   php scripts/analyser-mots-de-passe.php --reinitialiser-conflits
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$racine = dirname(__DIR__);
require_once $racine . '/includes/functions.php';

function sortie(string $texte = ''): void
{
    echo $texte, PHP_EOL;
}

$db = TchadokDatabase::getInstance()->getConnection();
if (!$db) {
    sortie('  [ERREUR] Base de donnees injoignable.');
    exit(1);
}

$colonne = $db->query(
    "SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'password'"
)->fetchColumn();

sortie();
sortie('  Mots de passe Tchadok — etat des lieux (DATA-02)');
sortie();

if ((int) $colonne === 0) {
    sortie('  [ok]    La colonne `password` n\'existe plus : la migration est deja passee.');
    sortie('          Un seul mot de passe par compte, dans `password_hash`.');
    sortie();
    exit(0);
}

$lignes = $db->query(
    "SELECT
        COUNT(*) AS total,
        SUM(password = password_hash) AS identiques,
        SUM(password <> password_hash) AS divergents,
        SUM(password IS NULL OR password = '') AS password_vide,
        SUM(password_hash IS NULL OR password_hash = '') AS hash_vide,
        SUM(password NOT LIKE '\$2y\$%' AND password <> '') AS password_non_bcrypt,
        SUM(password_hash NOT LIKE '\$2y\$%' AND password_hash <> '') AS hash_non_bcrypt
     FROM users"
)->fetch(PDO::FETCH_ASSOC);

foreach ([
    'total'               => 'comptes',
    'identiques'          => 'les deux colonnes portent la meme valeur',
    'divergents'          => 'les deux colonnes different',
    'password_vide'       => 'colonne password vide',
    'hash_vide'           => 'colonne password_hash vide',
    'password_non_bcrypt' => 'password n\'est pas un hash bcrypt',
    'hash_non_bcrypt'     => 'password_hash n\'est pas un hash bcrypt',
] as $cle => $libelle) {
    sortie(sprintf('  %-6s  %s', (string) (int) $lignes[$cle], $libelle));
}

// Comptes reellement porteurs de deux mots de passe utilisables.
$conflits = $db->query(
    "SELECT id, username, email
     FROM users
     WHERE password <> password_hash
       AND password LIKE '\$2y\$%'
       AND password_hash LIKE '\$2y\$%'
     ORDER BY id"
)->fetchAll(PDO::FETCH_ASSOC);

sortie();
if (!$conflits) {
    sortie('  [ok]    Aucun compte ne dispose de deux mots de passe valides distincts.');
    sortie('          La migration peut etre appliquee sans precaution particuliere :');
    sortie('          php scripts/migrate.php up');
    sortie();
    exit(0);
}

sortie(sprintf('  [!]     %d compte(s) disposent de DEUX mots de passe valides :', count($conflits)));
foreach ($conflits as $compte) {
    sortie(sprintf('            #%d  %s  <%s>', $compte['id'], $compte['username'], $compte['email']));
}
sortie();
sortie('  La migration retient `password_hash` : sur ces comptes, l\'ancien mot de');
sortie('  passe cessera de fonctionner. C\'est le but -- mais la personne doit etre');
sortie('  prevenue, sinon elle constatera seulement que « ca ne marche plus ».');
sortie();

$reinitialiser = in_array('--reinitialiser-conflits', array_slice($argv, 1), true);

if (!$reinitialiser) {
    sortie('  Deux facons de proceder :');
    sortie('    1. prevenir ces personnes, puis appliquer la migration ;');
    sortie('    2. forcer la reinitialisation maintenant :');
    sortie('       php scripts/analyser-mots-de-passe.php --reinitialiser-conflits');
    sortie('       Les comptes concernes ne pourront plus se connecter avant d\'avoir');
    sortie('       choisi un nouveau mot de passe.');
    sortie();
    exit(0);
}

// Reinitialisation : un hash aleatoire que personne ne connait. Le compte ne
// s'ouvre plus qu'apres passage par la recuperation.
$mise = $db->prepare('UPDATE users SET password = ?, password_hash = ? WHERE id = ?');
foreach ($conflits as $compte) {
    $inutilisable = password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT);
    $mise->execute([$inutilisable, $inutilisable, $compte['id']]);

    revoquerSessionsUtilisateur((int) $compte['id'], false);

    JournalAudit::enregistrer('compte.mot-de-passe', [
        'cible_type' => 'utilisateur',
        'cible_id'   => $compte['id'],
        'raison'     => 'DATA-02 : deux mots de passe valides, reinitialisation forcee',
    ]);

    sortie(sprintf('  [ok]    #%d %s : mot de passe reinitialise, sessions fermees', $compte['id'], $compte['email']));
}

sortie();
sortie('  Prevenez ces personnes : leur compte attend une recuperation de mot de passe.');
sortie();
exit(0);
