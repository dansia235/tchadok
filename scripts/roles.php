<?php
/**
 * Attribution des roles (SEC-19), en ligne de commande.
 *
 * Il n'existe pas encore d'ecran d'attribution : ce script permet de donner
 * les premiers roles sur le serveur, y compris le super-administrateur, sans
 * passer par phpMyAdmin -- et surtout, en laissant une trace dans le journal
 * d'audit, ce qu'une requete SQL manuelle ne ferait pas.
 *
 * Usage :
 *   php scripts/roles.php liste
 *   php scripts/roles.php voir <email|id>
 *   php scripts/roles.php attribuer <email|id> <role>
 *   php scripts/roles.php retirer  <email|id> <role>
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

function echouer(string $texte): never
{
    sortie('  [ERREUR] ' . $texte);
    exit(1);
}

/**
 * Retrouve un utilisateur par identifiant numerique, email ou nom.
 */
function trouverUtilisateur(PDO $db, string $reference): array
{
    $stmt = $db->prepare(
        'SELECT id, username, email FROM users WHERE id = ? OR email = ? OR username = ? LIMIT 1'
    );
    $stmt->execute([ctype_digit($reference) ? (int) $reference : 0, $reference, $reference]);
    $utilisateur = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$utilisateur) {
        echouer("Aucun compte pour « {$reference} ».");
    }

    return $utilisateur;
}

$db = TchadokDatabase::getInstance()->getConnection();
if (!$db) {
    echouer('Base de donnees injoignable.');
}

$commande = $argv[1] ?? 'liste';
$reference = $argv[2] ?? '';
$role = $argv[3] ?? '';

sortie();
sortie('  Roles Tchadok');
sortie();

switch ($commande) {
    case 'liste':
        foreach (Autorisations::rolesDisponibles() as $ligne) {
            sortie(sprintf('  %-22s %s', $ligne['slug'], $ligne['description'] ?? ''));
        }
        break;

    case 'voir':
        if ($reference === '') {
            echouer('Indiquez un compte : php scripts/roles.php voir <email|id>');
        }
        $utilisateur = trouverUtilisateur($db, $reference);
        $roles = Autorisations::roles((int) $utilisateur['id']);
        sortie(sprintf('  %s (%s)', $utilisateur['username'], $utilisateur['email']));
        sortie('  roles       : ' . ($roles ? implode(', ', $roles) : 'aucun'));
        $permissions = Autorisations::permissions((int) $utilisateur['id']);
        sortie('  permissions : ' . ($permissions ? count($permissions) . ' (' . implode(', ', array_slice($permissions, 0, 6)) . (count($permissions) > 6 ? ', ...' : '') . ')' : 'aucune'));
        break;

    case 'attribuer':
    case 'retirer':
        if ($reference === '' || $role === '') {
            echouer("Usage : php scripts/roles.php {$commande} <email|id> <role>");
        }

        $slugs = array_column(Autorisations::rolesDisponibles(), 'slug');
        if (!in_array($role, $slugs, true)) {
            echouer("Role inconnu « {$role} ». Roles disponibles : " . implode(', ', $slugs));
        }

        $utilisateur = trouverUtilisateur($db, $reference);
        $userId = (int) $utilisateur['id'];

        // L'acteur est la ligne de commande : pas de session, donc pas
        // d'utilisateur courant. Le journal le note comme tel.
        if ($commande === 'attribuer') {
            $fait = Autorisations::attribuer($userId, $role, null);
            sortie($fait
                ? "  [ok]    role « {$role} » attribue a {$utilisateur['email']}"
                : "  [info]  {$utilisateur['email']} avait deja le role « {$role} »");
        } else {
            $fait = Autorisations::retirer($userId, $role, null);
            sortie($fait
                ? "  [ok]    role « {$role} » retire a {$utilisateur['email']}"
                : "  [info]  {$utilisateur['email']} n'avait pas le role « {$role} »");
        }

        if ($fait) {
            JournalAudit::enregistrer(
                $commande === 'attribuer' ? 'role.attribue' : 'role.retire',
                [
                    'cible_type' => 'utilisateur',
                    'cible_id'   => $userId,
                    'raison'     => 'ligne de commande (scripts/roles.php)',
                ]
            );
        }

        sortie('  roles       : ' . implode(', ', Autorisations::roles($userId)));
        break;

    default:
        sortie('  Commandes : liste | voir <compte> | attribuer <compte> <role> | retirer <compte> <role>');
        exit(1);
}

sortie();
exit(0);
