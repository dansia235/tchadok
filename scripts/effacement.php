<?php
/**
 * DATA-06 : retrait de contenu et droit a l'effacement, en ligne de commande.
 *
 * Ces operations n'ont pas d'ecran : elles sont rares, sensibles, et doivent
 * etre faites par quelqu'un qui sait ce qu'il fait. Elles sont toutes tracees
 * au journal d'audit.
 *
 * Usage :
 *   php scripts/effacement.php etat      <table> <id>
 *   php scripts/effacement.php retirer   <table> <id> [motif]
 *   php scripts/effacement.php retablir  <table> <id>
 *   php scripts/effacement.php anonymiser <courriel|id> [motif]
 *
 * Tables reconnues : users, artists, tracks, releases, playlists.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/functions.php';

$db = TchadokDatabase::getInstance()->getConnection();
if (!$db) {
    fwrite(STDERR, "Base de donnees injoignable.\n");
    exit(1);
}

$commande = $argv[1] ?? 'aide';
$arguments = array_slice($argv, 2);

function sortie(string $texte, int $code = 0): never
{
    echo $texte . PHP_EOL;
    exit($code);
}

function aide(): never
{
    echo PHP_EOL;
    echo '  Effacement Tchadok' . PHP_EOL . PHP_EOL;
    echo '    etat       <table> <id>            l\'element est-il retire ?' . PHP_EOL;
    echo '    retirer    <table> <id> [motif]    le retire de l\'affichage' . PHP_EOL;
    echo '    retablir   <table> <id>            le remet en ligne' . PHP_EOL;
    echo '    anonymiser <courriel|id> [motif]   droit a l\'effacement (DEFINITIF)' . PHP_EOL . PHP_EOL;
    echo '  Tables : ' . implode(', ', Effacement::TABLES) . PHP_EOL . PHP_EOL;
    exit(0);
}

switch ($commande) {
    case 'etat':
        [$table, $id] = [$arguments[0] ?? '', (int) ($arguments[1] ?? 0)];
        if ($table === '' || $id <= 0) {
            aide();
        }
        sortie(Effacement::estRetire($table, $id)
            ? "  {$table} #{$id} : RETIRE"
            : "  {$table} #{$id} : en ligne");

    case 'retirer':
        [$table, $id] = [$arguments[0] ?? '', (int) ($arguments[1] ?? 0)];
        if ($table === '' || $id <= 0) {
            aide();
        }
        $resultat = Effacement::retirer($table, $id, null, $arguments[2] ?? '');
        sortie($resultat['succes'] ? "  {$table} #{$id} retire." : '  ' . implode(' ', $resultat['erreurs']),
            $resultat['succes'] ? 0 : 1);

    case 'retablir':
        [$table, $id] = [$arguments[0] ?? '', (int) ($arguments[1] ?? 0)];
        if ($table === '' || $id <= 0) {
            aide();
        }
        $resultat = Effacement::retablir($table, $id);
        sortie($resultat['succes'] ? "  {$table} #{$id} remis en ligne." : '  ' . implode(' ', $resultat['erreurs']),
            $resultat['succes'] ? 0 : 1);

    case 'anonymiser':
        $cible = $arguments[0] ?? '';
        if ($cible === '') {
            aide();
        }

        if (ctype_digit($cible)) {
            $userId = (int) $cible;
        } else {
            $stmt = $db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$cible]);
            $userId = (int) $stmt->fetchColumn();
        }

        if ($userId <= 0) {
            sortie('  Compte introuvable.', 1);
        }

        // Ce qui va survivre, annonce AVANT d'agir : l'operateur doit savoir ce
        // qu'il conserve, et pouvoir le justifier a la personne qui demande.
        $traces = Effacement::tracesComptables($userId);
        echo PHP_EOL . "  Compte #{$userId}" . PHP_EOL;
        echo '  Seront conserves, sans lien avec une personne :' . PHP_EOL;
        foreach ($traces as $table => $nombre) {
            printf('    %-14s %d%s', $table, $nombre, PHP_EOL);
        }
        echo PHP_EOL;

        $resultat = Effacement::anonymiser($userId, null, $arguments[1] ?? '');
        if (!$resultat['succes']) {
            sortie('  ' . implode(' ', $resultat['erreurs']), 1);
        }

        sortie("  Compte #{$userId} anonymise sous « {$resultat['pseudonyme']} ». Operation definitive.");

    default:
        aide();
}
