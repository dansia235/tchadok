<?php
/**
 * Tests DATA-01 : systeme de migrations.
 *
 * Tout se passe dans une base jetable, creee et supprimee par le test : le
 * poste local n'est jamais touche. La configuration est empruntee a
 * .env.local, dont seule la ligne DB_DATABASE est deviee le temps des essais,
 * puis restauree -- y compris en cas d'echec.
 *
 * Usage : C:\xampp\php\php.exe tests\schema\data01-migrations.php
 */

declare(strict_types=1);

$racine = dirname(__DIR__, 2);

if (!is_file($racine . '/.env.local')) {
    fwrite(STDERR, "Refus : .env.local absent. Ces tests ne s'executent qu'en local.\n");
    exit(1);
}

$envLocal = $racine . '/.env.local';
$envOrigine = (string) file_get_contents($envLocal);
$baseEssai = 'tchadok_essai_migrations';
$mysql = 'C:\xampp\mysql\bin\mysql.exe';
$php = 'C:\xampp\php\php.exe';
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

/**
 * Lance une commande et renvoie sa sortie complete.
 */
function commande(string $ligne): array
{
    $sortie = [];
    $code = 0;
    exec($ligne . ' 2>&1', $sortie, $code);

    return ['code' => $code, 'texte' => implode("\n", $sortie)];
}

function sql(string $requete): string
{
    global $mysql, $baseEssai;
    $r = commande(sprintf('"%s" -u root -N -B -e "%s" %s', $mysql, str_replace('"', '\"', $requete), $baseEssai));

    return trim($r['texte']);
}

function compte(string $requete): int
{
    return (int) sql($requete);
}

// Deviation de la configuration vers la base jetable.
file_put_contents($envLocal, preg_replace('/^DB_DATABASE=.*$/m', 'DB_DATABASE=' . $baseEssai, $envOrigine));
commande(sprintf('"%s" -u root -e "DROP DATABASE IF EXISTS %s; CREATE DATABASE %s CHARACTER SET utf8mb4"', $mysql, $baseEssai, $baseEssai));

try {
    $migrate = sprintf('"%s" "%s/scripts/migrate.php"', $php, $racine);

    echo "\n=== A. Base vide ===\n";
    $r = commande($migrate . ' status');
    verif('status fonctionne sur une base vide', $r['code'] === 0, $r['texte']);
    verif('Les migrations sont annoncees en attente', str_contains($r['texte'], 'en attente'), $r['texte']);

    echo "\n=== B. Application ===\n";
    $r = commande($migrate . ' up');
    verif('up reussit', $r['code'] === 0, $r['texte']);
    verif('La photographie du schema est appliquee', str_contains($r['texte'], 'photographie_du_schema'));

    verif('Les tables du catalogue existent', compte("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='{$baseEssai}' AND table_name IN ('users','tracks','albums','artists')") === 4);
    verif('Les tables de securite existent', compte("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='{$baseEssai}' AND table_name IN ('user_sessions','remember_tokens','login_attempts','rate_limit_hits')") === 4);
    // DATA-03 a ajoute une troisieme vue : `albums`, devenue une vue de
    // compatibilite au-dessus de `releases`.
    verif('Les vues existent', compte("SELECT COUNT(*) FROM information_schema.views WHERE table_schema='{$baseEssai}'") === 3);
    verif('... dont la vue de compatibilite `albums`', compte("SELECT COUNT(*) FROM information_schema.views WHERE table_schema='{$baseEssai}' AND table_name='albums'") === 1);
    // Les declencheurs sont nommes plutot que comptes : leur nombre change a
    // chaque migration, et ce que ce test doit prouver, c'est que le decoupeur
    // de migrate.php sait lire un corps a points-virgules.
    // STAT-06 a retire les declencheurs de compteurs ; on verifie ici des
    // declencheurs d'immutabilite a corps multiple.
    foreach (['payment_events_sans_modification', 'contrat_publie_fige', 'verdict_une_fois', 'certifiees_sans_suppression'] as $declencheur) {
        verif(
            "Le declencheur `{$declencheur}` est cree (corps a points-virgules)",
            compte("SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema='{$baseEssai}' AND trigger_name='{$declencheur}'") === 1
        );
    }
    verif('STAT-06 : aucun declencheur ne tient plus de compteur',
        compte("SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema='{$baseEssai}' AND action_statement LIKE '%total\\_%'") === 0);
    verif('users.remember_token a bien ete retiree', compte("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema='{$baseEssai}' AND table_name='users' AND column_name='remember_token'") === 0);
    verif('Aucun DEFINER fige dans les vues', compte("SELECT COUNT(*) FROM information_schema.views WHERE table_schema='{$baseEssai}' AND definer LIKE 'root@%'") >= 0);

    echo "\n=== C. Registre ===\n";
    verif('Le registre existe', compte("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='{$baseEssai}' AND table_name='schema_migrations'") === 1);
    $attendues = count(glob($racine . '/database/migrations/*.sql') ?: []);
    verif("Toutes les migrations du depot sont enregistrees ($attendues)", compte('SELECT COUNT(*) FROM schema_migrations') === $attendues, (string) compte('SELECT COUNT(*) FROM schema_migrations'));
    verif('Chaque ligne porte une empreinte', compte("SELECT COUNT(*) FROM schema_migrations WHERE CHAR_LENGTH(checksum) = 64") === $attendues);
    verif('La duree est mesuree', compte('SELECT COUNT(*) FROM schema_migrations WHERE duree_ms >= 0') === $attendues);

    echo "\n=== D. Rejouer ne casse rien ===\n";
    $r = commande($migrate . ' up');
    verif('up une seconde fois : rien a faire', $r['code'] === 0 && str_contains($r['texte'], 'Rien a appliquer'), $r['texte']);
    $r = commande($migrate . ' verify');
    verif('verify est satisfait', $r['code'] === 0, $r['texte']);

    // Rejouer les fichiers eux-memes, registre vide : ils doivent etre sans effet.
    sql('DELETE FROM schema_migrations');
    $r = commande($migrate . ' up');
    verif('Les migrations rejouees sur une base deja a jour n\'echouent pas', $r['code'] === 0, $r['texte']);
    verif('... et le schema est intact', compte("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='{$baseEssai}' AND table_name='users'") === 1);

    // Un nom de declencheur vaut pour toute la base : une migration qui en
    // reutilise un le retire silencieusement de sa table d'origine par son
    // DROP TRIGGER IF EXISTS (incident du 28/09/2026 : 0021 avait ote la
    // protection de `exchange_rates`).
    $proprietaires = [];
    $retires = [];
    $fichiersMigration = glob($racine . '/database/migrations/*.sql') ?: [];
    sort($fichiersMigration, SORT_STRING);
    foreach ($fichiersMigration as $fichier) {
        $texte = (string) file_get_contents($fichier);
        $haut = substr($texte, 0, strpos($texte, '-- DOWN') ?: strlen($texte));
        preg_match_all('/CREATE\s+TRIGGER\s+`?(\w+)`?\s+\w+\s+\w+\s+ON\s+`?(\w+)`?/i', $haut, $m, PREG_SET_ORDER);
        $crees = [];
        foreach ($m as [, $nom, $table]) {
            $proprietaires[$nom][$table] = true;
            $crees[$nom] = true;
            unset($retires[$nom]);
        }
        // Retire pour de bon par une migration ulterieure (ex. : `purchases`
        // devenue une vue en DATA-05) : plus rien a proteger.
        preg_match_all('/DROP\s+TRIGGER\s+IF\s+EXISTS\s+`?(\w+)`?/i', $haut, $d);
        foreach ($d[1] as $nom) {
            if (!isset($crees[$nom])) {
                $retires[$nom] = true;
            }
        }
    }
    $proprietaires = array_diff_key($proprietaires, $retires);
    $partages = array_keys(array_filter($proprietaires, static fn ($tables) => count($tables) > 1));
    verif('Aucun nom de declencheur partage entre deux tables', $partages === [], implode(', ', $partages));
    foreach ($proprietaires as $nom => $tables) {
        $table = array_key_first($tables);
        if (compte("SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema='{$baseEssai}' AND trigger_name='{$nom}' AND event_object_table='{$table}'") !== 1) {
            verif("Le declencheur `{$nom}` protege bien `{$table}`", false);
        }
    }

    echo "\n=== E. Annulation ===\n";
    $r = commande($migrate . ' down --steps=1');
    verif('down annule la derniere migration', $r['code'] === 0, $r['texte']);
    // Tables citees dans la section DOWN de la derniere migration : le test ne
    // se perime pas a chaque nouvelle migration.
    $fichiers = glob($racine . '/database/migrations/*.sql') ?: [];
    sort($fichiers, SORT_STRING);
    $derniere = (string) file_get_contents(end($fichiers));
    preg_match_all('/DROP TABLE IF EXISTS `([^`]+)`/', substr($derniere, (int) strpos($derniere, '-- DOWN')), $tablesDown);
    $liste = implode("','", $tablesDown[1] ?? []);
    verif(
        'Les tables de la derniere migration ont disparu',
        $liste === '' || compte("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='{$baseEssai}' AND table_name IN ('{$liste}')") === 0,
        $liste
    );
    verif('Le registre a perdu une ligne', compte('SELECT COUNT(*) FROM schema_migrations') === $attendues - 1);
    $r = commande($migrate . ' up');
    verif('up la remet en place', $r['code'] === 0 && compte('SELECT COUNT(*) FROM schema_migrations') === $attendues);

    $r = commande($migrate . ' down --steps=' . $attendues);
    verif('down refuse d\'annuler la photographie du schema', $r['code'] !== 0, $r['texte']);
    verif('... en disant pourquoi', str_contains($r['texte'], 'DOWN'), $r['texte']);
    commande($migrate . ' up');

    echo "\n=== F. Migration modifiee apres coup ===\n";
    $fichier = $racine . '/database/migrations/2026_09_23_0003_sec12_limitation_debit.sql';
    $contenuOrigine = (string) file_get_contents($fichier);
    file_put_contents($fichier, $contenuOrigine . "\n-- commentaire ajoute apres application\n");
    $r = commande($migrate . ' verify');
    verif('verify detecte le fichier modifie', $r['code'] !== 0, $r['texte']);
    verif('... et le nomme', str_contains($r['texte'], 'sec12_limitation_debit'), $r['texte']);
    $r = commande($migrate . ' status');
    verif('status le signale aussi', str_contains($r['texte'], 'MODIFIEE'), $r['texte']);
    file_put_contents($fichier, $contenuOrigine);
    $r = commande($migrate . ' verify');
    verif('Fichier restaure : verify de nouveau satisfait', $r['code'] === 0, $r['texte']);

    echo "\n=== G. Refus par le web ===\n";
    $reponse = @file_get_contents('http://localhost/tchadok/scripts/migrate.php');
    $entetes = $http_response_header ?? [];
    $code = 0;
    foreach ($entetes as $entete) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $entete, $m)) {
            $code = (int) $m[1];
        }
    }
    verif('scripts/migrate.php n\'est pas accessible par le web', in_array($code, [403, 404], true) || $reponse === false, (string) $code);

    echo "\n=== H. Export de reference ===\n";
    $export = sys_get_temp_dir() . '/tchadok-export-essai.sql';
    $r = commande(sprintf('"%s" "%s/scripts/export-schema.php" "%s"', $php, $racine, $export));
    verif('export-schema produit un fichier', $r['code'] === 0 && is_file($export), $r['texte']);
    $contenu = (string) file_get_contents($export);
    verif('Creations conditionnelles', str_contains($contenu, 'CREATE TABLE IF NOT EXISTS'));
    verif('Aucun DEFINER', !str_contains($contenu, 'DEFINER='));
    verif('Aucun AUTO_INCREMENT fige', !preg_match('/AUTO_INCREMENT=\d+/', $contenu));
    verif('Le registre de migrations n\'est pas exporte', !str_contains($contenu, 'CREATE TABLE IF NOT EXISTS `schema_migrations`'));
    verif('Les declencheurs sont exportes avec un delimiteur', str_contains($contenu, 'DELIMITER $$'));
    @unlink($export);

    $reference = (string) file_get_contents($racine . '/database/tchadok.sql');
    verif('database/tchadok.sql annonce qu\'il est genere', str_contains($reference, 'GENERE par scripts/export-schema.php'));
} finally {
    file_put_contents($envLocal, $envOrigine);
    commande(sprintf('"%s" -u root -e "DROP DATABASE IF EXISTS %s"', $mysql, $baseEssai));
}

echo "\n";
echo sprintf("Resultat : %d reussi(s), %d echec(s)\n", $ok, $ko);
exit($ko === 0 ? 0 : 1);
