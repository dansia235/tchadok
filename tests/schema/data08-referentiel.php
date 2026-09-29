<?php
/**
 * Tests DATA-08 : donnees de reference.
 *
 * Criteres du plan :
 *   - une installation neuve dispose d'un referentiel de genres exploitable ;
 *   - le jeu de demonstration ne peut pas etre importe en production.
 *
 * Et ce qui en decoule :
 *   - le chargement est rejouable et n'ecrase pas une modification editoriale ;
 *   - une CATEGORIE n'est jamais proposee ni acceptee comme genre d'un titre ;
 *   - les formulaires ne creent plus de genres (P1-10).
 *
 * Suppose le referentiel charge : php scripts/seed.php referentiel
 *
 * Usage : C:\xampp\php\php.exe tests\schema\data08-referentiel.php
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

$db = TchadokDatabase::getInstance()->getConnection();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$source = static fn (string $f): string => (string) file_get_contents($GLOBALS['racine'] . '/' . $f);
$compter = static fn (string $sql): int => (int) $db->query($sql)->fetchColumn();

function seed(string $commande): array
{
    $sortie = [];
    exec(sprintf('"C:\xampp\php\php.exe" "%s/scripts/seed.php" %s 2>&1', $GLOBALS['racine'], $commande), $sortie, $code);

    return [$code, implode("\n", $sortie)];
}

$idGenre = static fn (string $slug): int => (int) $db->query(
    'SELECT id FROM genres WHERE slug = ' . $db->quote($slug)
)->fetchColumn();

$saiAvant = $db->query("SELECT color, status FROM genres WHERE slug = 'sai'")->fetch(PDO::FETCH_ASSOC) ?: null;
$migrationFactice = $racine . '/database/migrations/9999_12_31_9999_zz_test_data08.sql';

try {
    echo "\n=== A. Referentiel charge ===\n";
    $categories = $compter('SELECT COUNT(*) FROM genres WHERE parent_id IS NULL AND EXISTS (SELECT 1 FROM genres e WHERE e.parent_id = genres.id)');
    $genres = $compter('SELECT COUNT(*) FROM genres WHERE parent_id IS NOT NULL');
    verif('6 categories', $categories === 6, (string) $categories);
    verif('31 genres rattaches a une categorie', $genres === 31, (string) $genres);
    verif('23 provinces', $compter('SELECT COUNT(*) FROM regions') === 23);
    verif('Chaque genre a un slug', $compter('SELECT COUNT(*) FROM genres WHERE slug IS NULL OR slug = \'\'') === 0);
    verif('Hierarchie a deux niveaux seulement (pas de genre sous un genre)',
        $compter('SELECT COUNT(*) FROM genres g JOIN genres p ON p.id = g.parent_id WHERE p.parent_id IS NOT NULL') === 0);
    verif('Accents conserves (Saï, Ouaddaï)',
        $compter("SELECT COUNT(*) FROM genres WHERE slug = 'sai' AND name = 'Saï'") === 1
        && $compter("SELECT COUNT(*) FROM regions WHERE slug = 'ouaddai' AND name = 'Ouaddaï'") === 1);
    verif("N'Djamena (apostrophe) correctement chargee",
        $compter("SELECT COUNT(*) FROM regions WHERE slug = 'ndjamena' AND name = 'N''Djamena'") === 1);
    // Controle sur le source et non sur la table : sec19 et sec20 vident
    // audit_log pour s'isoler, l'entree du premier chargement n'y survit pas.
    verif('Le chargement est trace au journal d\'audit',
        str_contains($source('scripts/seed.php'), "JournalAudit::enregistrer('taxonomie.modifiee'"));

    echo "\n=== B. Rejouable, sans ecraser l'editorial ===\n";
    $db->exec("UPDATE genres SET color = '#123456' WHERE slug = 'sai'");
    [$code, $texte] = seed('referentiel');
    verif('Second chargement sans erreur', $code === 0, $texte);
    verif('... n\'ajoute rien', str_contains($texte, 'Rien a ajouter'), $texte);
    verif('... et respecte une couleur modifiee par l\'equipe',
        $db->query("SELECT color FROM genres WHERE slug = 'sai'")->fetchColumn() === '#123456');
    verif('Nombre de genres inchange', $compter('SELECT COUNT(*) FROM genres WHERE parent_id IS NOT NULL') === 31);

    echo "\n=== C. Une categorie n'est pas un genre ===\n";
    $proposables = getGenresSelectionnables();
    $idsProposables = array_map(fn($g) => (int) $g['id'], $proposables);
    $urbaines = $idGenre('musiques-urbaines');
    $rap = $idGenre('rap-tchadien');
    verif('31 genres proposes au depot', count($proposables) === 31, (string) count($proposables));
    verif('Aucune categorie proposee', !in_array($urbaines, $idsProposables, true));
    verif('Chaque genre propose porte sa categorie', $proposables !== [] && !in_array('', array_column($proposables, 'categorie'), true));
    verif('estGenreSelectionnable : genre accepte', estGenreSelectionnable($rap));
    verif('estGenreSelectionnable : categorie refusee', !estGenreSelectionnable($urbaines));
    verif('estGenreSelectionnable : identifiant inconnu refuse', !estGenreSelectionnable(987654321));
    verif('estGenreSelectionnable : vide refuse', !estGenreSelectionnable(null) && !estGenreSelectionnable(0));

    $db->exec("UPDATE genres SET status = 'archived' WHERE slug = 'sai'");
    verif('Un genre archive n\'est plus propose', !estGenreSelectionnable($idGenre('sai')));
    $db->exec("UPDATE genres SET status = 'active' WHERE slug = 'sai'");

    $html = optionsGenres($proposables, $rap);
    verif('Options groupees par categorie', substr_count($html, '<optgroup') === 6, (string) substr_count($html, '<optgroup'));
    verif('... groupes correctement fermes', substr_count($html, '</optgroup>') === 6);
    verif('... et genre courant preselectionne', str_contains($html, 'value="' . $rap . '" selected'));
    verif('Libelles echappes (« & » des categories)', str_contains($html, 'label="Variété &amp; world"'));

    $publics = array_map(fn($g) => (int) $g['id'], getGenres());
    verif('getGenres : aucune categorie', !in_array($urbaines, $publics, true) && in_array($rap, $publics, true));
    $avecStats = array_map(fn($g) => (int) $g['id'], getGenresWithStats());
    verif('getGenresWithStats : aucune categorie', !in_array($urbaines, $avecStats, true) && in_array($rap, $avecStats, true));

    echo "\n=== D. Formulaires ===\n";
    // MOD-05 : les artistes publient par publier.php (includes/publication.php) ;
    // upload.php, artist-add-song.php et artist-add-album.php redirigent.
    foreach (['admin-add-song.php', 'admin-add-album.php'] as $f) {
        $code = $source($f);
        verif("{$f} : liste issue du referentiel", str_contains($code, 'getGenresSelectionnables()'));
        verif("{$f} : genre revalide cote serveur", str_contains($code, 'estGenreSelectionnable($genreId)'));
    }
    verif('publier.php : liste issue du referentiel', str_contains($source('publier.php'), 'getGenresSelectionnables()'));
    verif('Parcours artiste : genre de la sortie et de chaque titre revalide cote serveur',
        substr_count($source('includes/publication.php'), 'estGenreSelectionnable(') >= 2);
    foreach (['upload.php', 'artist-add-song.php', 'artist-add-album.php'] as $f) {
        verif("{$f} : redirige vers le parcours unique", str_contains($source($f), 'publier.php') && !str_contains($source($f), 'INSERT INTO'));
    }
    verif('upload.php ne cree plus de genre (P1-10)', !preg_match('/INSERT\s+INTO\s+genres/i', $source('upload.php')));
    verif('... ni ne propose de saisie libre', !str_contains($source('upload.php'), 'name="genre_name"'));

    echo "\n=== E. Garde-fous du chargement ===\n";
    $script = $source('scripts/seed.php');
    verif('demo refuse hors environnement local',
        str_contains($script, "\$jeu === 'demo' && (EnvLoader::isProduction() || EnvLoader::environment() !== 'local')"));
    verif('Execution en ligne de commande uniquement', str_contains($script, "PHP_SAPI !== 'cli'"));

    $reponse = @file_get_contents('http://localhost/tchadok/scripts/seed.php');
    $codeHttp = isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m) ? (int) $m[1] : 0;
    verif('scripts/seed.php n\'est pas accessible par le web', $reponse === false || in_array($codeHttp, [403, 404], true), (string) $codeHttp);
    $reponse = @file_get_contents('http://localhost/tchadok/database/seeds/demo.sql');
    verif('database/seeds/demo.sql n\'est pas telechargeable', $reponse === false);

    file_put_contents($migrationFactice, "-- UP\nDO 0;\n");
    [$code, $texte] = seed('referentiel');
    verif('Chargement refuse si une migration est en attente', $code !== 0 && str_contains($texte, 'migration(s) en attente'), $texte);
    [$code, $texte] = seed('demo');
    verif('... demo aussi', $code !== 0 && str_contains($texte, 'migrate.php up'), $texte);
    unlink($migrationFactice);

    echo "\n=== F. Jeu de demonstration ===\n";
    verif('demo.sql attribue le role (SEC-19)', str_contains($source('database/seeds/demo.sql'), 'INSERT IGNORE INTO `user_roles`'));
    [$code, $texte] = seed('demo');
    verif('Chargement local accepte', $code === 0, $texte);
    verif('Le compte de demonstration est super-administrateur',
        $compter("SELECT COUNT(*) FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = 1 AND r.slug = 'super_admin'") === 1);
    [$code, $texte] = seed('demo');
    verif('... et rejouable', $code === 0, $texte);
} finally {
    if (is_file($migrationFactice)) {
        unlink($migrationFactice);
    }
    if ($saiAvant) {
        $db->prepare("UPDATE genres SET color = ?, status = ? WHERE slug = 'sai'")
           ->execute([$saiAvant['color'], $saiAvant['status']]);
    }
}

echo "\n";
echo sprintf("Resultat : %d reussi(s), %d echec(s)\n", $ok, $ko);
exit($ko === 0 ? 0 : 1);
