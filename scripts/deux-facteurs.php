<?php
/**
 * Second facteur : etat et recuperation (SEC-20, point 6).
 *
 * Un administrateur qui perd son telephone ET ses codes de secours ne peut
 * plus entrer. La sortie de secours passe par le serveur, en ligne de
 * commande : quelqu'un qui a acces au VPS retire le second facteur du compte,
 * qui redevient accessible au seul mot de passe, puis doit le reconfigurer.
 *
 * L'operation est inscrite au journal d'audit, avec un motif obligatoire.
 * C'est la contrepartie : personne ne retire un second facteur sans laisser de
 * trace ni dire pourquoi.
 *
 * Usage :
 *   php scripts/deux-facteurs.php etat <email|id>
 *   php scripts/deux-facteurs.php reinitialiser <email|id> --raison="ticket 412, telephone vole"
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

$db = TchadokDatabase::getInstance()->getConnection();
if (!$db) {
    echouer('Base de donnees injoignable.');
}

$commande = $argv[1] ?? 'etat';
$reference = $argv[2] ?? '';
$raison = '';
foreach (array_slice($argv, 2) as $argument) {
    if (preg_match('/^--raison=(.+)$/', $argument, $m)) {
        $raison = trim($m[1], "\"' ");
    }
}

if ($reference === '' || str_starts_with($reference, '--')) {
    echouer('Indiquez un compte : php scripts/deux-facteurs.php ' . $commande . ' <email|id>');
}

$stmt = $db->prepare('SELECT id, username, email FROM users WHERE id = ? OR email = ? OR username = ? LIMIT 1');
$stmt->execute([ctype_digit($reference) ? (int) $reference : 0, $reference, $reference]);
$utilisateur = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$utilisateur) {
    echouer("Aucun compte pour « {$reference} ».");
}

$userId = (int) $utilisateur['id'];

sortie();
sortie('  Second facteur Tchadok');
sortie();
sortie(sprintf('  Compte : %s (%s)', $utilisateur['username'], $utilisateur['email']));

switch ($commande) {
    case 'etat':
        $active = DeuxFacteurs::estActive($userId);
        sortie('  Etat   : ' . ($active ? 'actif' : 'inactif'));
        sortie('  Exige  : ' . (DeuxFacteurs::exigee($userId) ? 'oui (role avec droits d\'ecriture)' : 'non'));
        if ($active) {
            sortie('  Codes de secours restants : ' . DeuxFacteurs::codesDeSecoursRestants($userId));
        }
        break;

    case 'reinitialiser':
        if ($raison === '') {
            echouer('Motif obligatoire : --raison="ticket 412, telephone vole"');
        }

        if (!DeuxFacteurs::estActive($userId)) {
            sortie('  [info]  Ce compte n\'avait pas de second facteur actif.');
        }

        DeuxFacteurs::desactiver($userId, null, 'recuperation en ligne de commande : ' . $raison);

        sortie('  [ok]    Second facteur retire.');
        sortie('  [info]  Le compte se connecte de nouveau avec son seul mot de passe.');
        sortie('  [info]  Demandez a la personne de le reconfigurer immediatement (/2fa.php).');
        sortie('  [info]  L\'operation figure au journal d\'audit.');
        break;

    default:
        sortie('  Commandes : etat <compte> | reinitialiser <compte> --raison="..."');
        exit(1);
}

sortie();
exit(0);
