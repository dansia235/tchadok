<?php
/**
 * Tests DATA-07 : index du catalogue et integrite.
 *
 * Criteres du plan :
 *   - `EXPLAIN` sur les cinq requetes du catalogue public ne montre plus de
 *     parcours complet de table ;
 *   - aucune requete de production n'applique de fonction sur une colonne
 *     indexee dans un `WHERE`.
 *
 * POURQUOI CE TEST FABRIQUE DU VOLUME
 *   Sur une table de dix lignes, MySQL parcourt tout -- c'est le plan le moins
 *   cher, et `EXPLAIN` dirait « ALL » meme avec les bons index. Le test
 *   insere donc plusieurs milliers de titres avant de mesurer, puis les retire.
 *   Sans volume, cette verification ne voudrait rien dire.
 *
 * Usage : C:\xampp\php\php.exe tests\schema\data07-index.php
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
$source = static fn (string $f): string => (string) file_get_contents($GLOBALS['racine'] . '/' . $f);

/**
 * Plan d'execution d'une requete, ligne par ligne.
 *
 * @return array<int, array<string, mixed>>
 */
function plan(PDO $db, string $sql, array $parametres = []): array
{
    $stmt = $db->prepare('EXPLAIN ' . $sql);
    $stmt->execute($parametres);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** La table indiquee est-elle parcourue en entier ? */
function parcoursComplet(array $plan, string $alias): bool
{
    foreach ($plan as $etape) {
        if (($etape['table'] ?? '') !== $alias) {
            continue;
        }

        // « ALL » sans index utilise : parcours complet. Un index utilise, ou
        // un acces par cle, est acceptable.
        return ($etape['type'] ?? '') === 'ALL' && ($etape['key'] ?? null) === null;
    }

    return false;
}

function detail(array $plan): string
{
    $lignes = [];
    foreach ($plan as $etape) {
        $lignes[] = sprintf(
            '%s type=%s key=%s rows=%s',
            $etape['table'] ?? '?',
            $etape['type'] ?? '?',
            $etape['key'] ?? 'NULL',
            $etape['rows'] ?? '?'
        );
    }

    return implode(' | ', $lignes);
}

$nettoyer = static function () use ($db): void {
    // Par MARQUEUR, jamais par plage d'identifiants : les compteurs
    // auto-incrementes des vraies donnees avaient rejoint la plage fixe de ce
    // test, qui effacait alors -- ou bloquait sur -- des lignes d'autrui.
    $db->exec("DELETE FROM tracks WHERE slug LIKE 'zzdata07-titre-%'");
    $db->exec("DELETE FROM releases WHERE slug LIKE 'zzdata07-sortie-%'");
    $db->exec("DELETE FROM artists WHERE slug LIKE 'zzdata07-artiste-%'");
    $db->exec("DELETE FROM users WHERE username LIKE 'zz07\\_u%'");
    // Filtre sur le nom en plus de l'identifiant : depuis DATA-08, de vrais
    // genres peuvent occuper ces numeros, et la cle `genres_parent` (ON DELETE
    // SET NULL) detacherait en silence tous les genres d'une categorie effacee.
    $db->exec("DELETE FROM genres WHERE id BETWEEN 993 AND 997 AND name LIKE 'ZZDATA07 %'");
};

$nettoyer();

// Identifiants d'essai : a partir d'une base situee AU-DELA de tout identifiant
// existant, recalculee a chaque execution -- jamais une plage fixe que les
// vraies donnees finissent par atteindre.
$base = 20000;
foreach (['users', 'artists', 'releases', 'tracks'] as $table) {
    $base = max($base, (int) $db->query("SELECT COALESCE(MAX(id), 0) + 1 FROM {$table}")->fetchColumn());
}
$base = (int) (ceil($base / 1000) * 1000);
$fin = $base + 9999;

try {
    echo "\n=== A. Les index attendus existent ===\n";
    $index = [];
    foreach ($db->query(
        "SELECT table_name, index_name, GROUP_CONCAT(column_name ORDER BY seq_in_index) AS colonnes
         FROM information_schema.statistics WHERE table_schema = DATABASE()
         GROUP BY table_name, index_name"
    )->fetchAll(PDO::FETCH_ASSOC) as $ligne) {
        $index[$ligne['table_name'] . '.' . $ligne['index_name']] = $ligne['colonnes'];
    }

    $attendus = [
        'tracks.catalogue'            => 'status,deleted_at,created_at',
        'tracks.genre_statut'         => 'genre_id,status,deleted_at',
        'tracks.classement'           => 'status,deleted_at,total_streams',
        'releases.catalogue'          => 'status,deleted_at,release_date',
        'releases.genre_statut'       => 'genre_id,status,deleted_at',
        'artists.visible'             => 'is_active,deleted_at,total_streams',
        'order_items.artiste_commande' => 'artist_id,order_id',
        'orders.encaissees'           => 'status,paid_at',
    ];
    foreach ($attendus as $nom => $colonnes) {
        verif("Index `{$nom}` sur ({$colonnes})", ($index[$nom] ?? '') === $colonnes, $index[$nom] ?? 'absent');
    }

    echo "\n=== B. Fabrication d'un catalogue mesurable ===\n";
    $db->exec("INSERT INTO genres (id, name, is_active) VALUES (993, 'ZZDATA07 Sai', 1), (994, 'ZZDATA07 Rap', 1), (995, 'ZZDATA07 Gospel', 1)");

    $db->beginTransaction();
    $insertUser = $db->prepare(
        'INSERT INTO users (id, username, email, password_hash, first_name, last_name, is_active, email_verified)
         VALUES (?, ?, ?, ?, ?, ?, 1, 1)'
    );
    $insertArtiste = $db->prepare(
        'INSERT INTO artists (id, user_id, stage_name, slug, is_active, verified, featured, total_streams)
         VALUES (?, ?, ?, ?, ?, 1, ?, ?)'
    );
    $hash = password_hash('essai', PASSWORD_BCRYPT);
    for ($i = 0; $i < 120; $i++) {
        $id = $base + $i;
        $insertUser->execute([$id, "zz07_u{$id}", "u{$id}@essai.local", $hash, 'Essai', 'Volume']);
        $insertArtiste->execute([
            $id, $id, "ZZDATA07 Artiste {$id}", "zzdata07-artiste-{$id}",
            $i % 10 === 0 ? 0 : 1,          // un artiste sur dix est desactive
            $i % 7 === 0 ? 1 : 0,
            random_int(0, 500000),
        ]);
    }

    $insertSortie = $db->prepare(
        'INSERT INTO releases (id, artist_id, title, slug, genre_id, format, price_bundle, is_free, status, release_date, deleted_at)
         VALUES (?, ?, ?, ?, ?, ?, 3000, 0, ?, ?, ?)'
    );
    $insertTitre = $db->prepare(
        'INSERT INTO tracks (id, album_id, release_id, slug, artist_id, genre_id, title, audio_file, duration, price, is_free, status, total_streams, created_at, release_date, deleted_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 200, 500, 0, ?, ?, ?, ?, ?)'
    );

    $statuts = ['approved', 'approved', 'approved', 'approved', 'pending', 'draft', 'rejected'];
    for ($i = 0; $i < 600; $i++) {
        $id = $base + $i;
        $artiste = $base + ($i % 120);
        $statut = $statuts[$i % count($statuts)];
        // Un contenu sur vingt est retire : le filtre `deleted_at` doit servir.
        $retire = $i % 20 === 0 ? date('Y-m-d H:i:s', time() - 86400) : null;
        $genre = 993 + ($i % 3);
        $date = date('Y-m-d H:i:s', time() - $i * 3600);

        $insertSortie->execute([$id, $artiste, "ZZDATA07 Sortie {$id}", "zzdata07-sortie-{$id}", $genre,
            ['album', 'ep', 'single', 'maxi_single'][$i % 4], $statut, date('Y-m-d', time() - $i * 3600), $retire]);
    }

    for ($i = 0; $i < 3000; $i++) {
        $id = $base + $i;
        $artiste = $base + ($i % 120);
        $statut = $statuts[$i % count($statuts)];
        $retire = $i % 20 === 0 ? date('Y-m-d H:i:s', time() - 86400) : null;
        $genre = 993 + ($i % 3);
        $date = date('Y-m-d H:i:s', time() - $i * 600);
        $sortie = $base + ($i % 600);

        $insertTitre->execute([
            $id, $sortie, $sortie, "zzdata07-titre-{$id}", $artiste, $genre,
            "ZZDATA07 Titre {$id}", 'x.mp3', $statut, random_int(0, 900000), $date, substr($date, 0, 10), $retire,
        ]);
    }
    $db->commit();

    // Sans statistiques a jour, l'optimiseur raisonne sur des estimations
    // perimees et peut ignorer un index parfaitement utilisable.
    $db->query('ANALYZE TABLE tracks, releases, artists')->fetchAll();

    $titres = (int) $db->query("SELECT COUNT(*) FROM tracks WHERE id BETWEEN {$base} AND {$fin}")->fetchColumn();
    $sorties = (int) $db->query("SELECT COUNT(*) FROM releases WHERE id BETWEEN {$base} AND {$fin}")->fetchColumn();
    verif('3 000 titres inseres', $titres === 3000, (string) $titres);
    verif('600 sorties inserees', $sorties === 600, (string) $sorties);
    verif('Des contenus retires figurent dans le lot',
        (int) $db->query("SELECT COUNT(*) FROM tracks WHERE id BETWEEN {$base} AND {$fin} AND deleted_at IS NOT NULL")->fetchColumn() === 150);

    echo "\n=== C. EXPLAIN : les cinq requetes du catalogue ===\n";

    // 1. Nouveautes : filtre + tri par date.
    $p = plan($db, "SELECT t.id FROM tracks t JOIN artists ar ON t.artist_id = ar.id
                     WHERE t.status = 'approved' AND t.deleted_at IS NULL AND ar.is_active = 1 AND ar.deleted_at IS NULL
                     ORDER BY t.created_at DESC LIMIT 6");
    verif('Nouveautes : plus de parcours complet des titres', !parcoursComplet($p, 't'), detail($p));

    // 2. Tendances : filtre + tri par ecoutes.
    $p = plan($db, "SELECT t.id FROM tracks t JOIN artists ar ON t.artist_id = ar.id
                     WHERE t.status = 'approved' AND t.deleted_at IS NULL AND ar.is_active = 1 AND ar.deleted_at IS NULL
                     ORDER BY t.total_streams DESC LIMIT 6");
    verif('Tendances : plus de parcours complet des titres', !parcoursComplet($p, 't'), detail($p));

    // 3. Vue par genre du barometre.
    $p = plan($db, "SELECT t.id FROM tracks t JOIN artists ar ON t.artist_id = ar.id
                     WHERE t.genre_id = ? AND t.status = 'approved' AND t.deleted_at IS NULL
                       AND ar.is_active = 1 AND ar.deleted_at IS NULL LIMIT 10", [993]);
    verif('Par genre : plus de parcours complet des titres', !parcoursComplet($p, 't'), detail($p));

    // 4. Catalogue des sorties.
    $p = plan($db, "SELECT a.id FROM releases a JOIN artists ar ON a.artist_id = ar.id
                     WHERE a.status = 'approved' AND a.deleted_at IS NULL AND ar.is_active = 1 AND ar.deleted_at IS NULL
                     ORDER BY a.release_date DESC LIMIT 12");
    verif('Sorties : plus de parcours complet', !parcoursComplet($p, 'a'), detail($p));

    // 5. Revenus de l'artiste sur un mois (DATA-05).
    $p = plan($db, "SELECT COALESCE(SUM(oi.artist_net), 0) FROM order_items oi
                     JOIN orders o ON o.id = oi.order_id
                     WHERE oi.artist_id = ? AND o.status = 'paid' AND o.paid_at >= ? AND o.paid_at < ?",
        [$base, '2026-09-01 00:00:00', '2026-10-01 00:00:00']);
    verif('Revenus mensuels : pas de parcours complet des lignes', !parcoursComplet($p, 'oi'), detail($p));

    echo "\n=== D. Les index sont reellement choisis ===\n";
    // L'index attendu est NOMME : constater qu'un index quelconque est utilise
    // ne dirait pas si c'est le bon. Un index compose dans le mauvais ordre
    // serait « utilise » tout en forcant un tri en memoire.
    foreach ([
        ['catalogue',    "SELECT id FROM tracks WHERE status = 'approved' AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 6"],
        ['classement',   "SELECT id FROM tracks WHERE status = 'approved' AND deleted_at IS NULL ORDER BY total_streams DESC LIMIT 6"],
        ['genre_statut', "SELECT id FROM tracks WHERE genre_id = ? AND status = 'approved' AND deleted_at IS NULL LIMIT 10"],
    ] as [$attendu, $sql]) {
        $p = plan($db, $sql, str_contains($sql, '?') ? [993] : []);
        $cles = [];
        $extra = '';
        foreach ($p as $etape) {
            $cles[] = (string) ($etape['key'] ?? '');
            $extra .= (string) ($etape['Extra'] ?? '');
        }
        verif("La requete choisit bien l'index `{$attendu}`", in_array($attendu, $cles, true), detail($p));
        // « Using index » : l'index suffit, la table n'est pas ouverte.
        verif("... et n'ouvre pas la table (couverture d'index)", str_contains($extra, 'Using index'), $extra);
    }

    // Le tri doit venir de l'index, pas d'un tri en memoire sur tout le lot.
    $p = plan($db, "SELECT id FROM tracks WHERE status = 'approved' AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 6");
    $extra = '';
    foreach ($p as $etape) {
        $extra .= (string) ($etape['Extra'] ?? '');
    }
    verif('Le tri par date ne passe pas par un tri en memoire', !str_contains($extra, 'Using filesort'), $extra);

    echo "\n=== E. Les fonctions publiques restent correctes ===\n";
    // Un index mal pose ne casse pas une requete ; un index qui change le plan
    // peut reveler un defaut de requete. On verifie donc que les lectures
    // publiques rendent toujours ce qu'il faut, sur ce volume.
    $nouveautes = getNewTracks(20);
    verif('getNewTracks rend des resultats', count($nouveautes) > 0, (string) count($nouveautes));
    $retires = 0;
    $nonPublies = 0;
    foreach ($nouveautes as $ligne) {
        $id = (int) ($ligne['id'] ?? 0);
        if ($id < $base || $id > $fin) {
            continue;
        }
        $etat = $db->query("SELECT status, deleted_at FROM tracks WHERE id = {$id}")->fetch(PDO::FETCH_ASSOC);
        if ($etat['deleted_at'] !== null) {
            $retires++;
        }
        if ($etat['status'] !== 'approved') {
            $nonPublies++;
        }
    }
    verif('... aucun retire', $retires === 0, (string) $retires);
    verif('... aucun non publie', $nonPublies === 0, (string) $nonPublies);

    $tendances = getTrendingTracks(20);
    verif('getTrendingTracks rend des resultats', count($tendances) > 0);
    $ecoutes = array_map(static fn ($l): int => (int) ($l['total_streams'] ?? 0), $tendances);
    $triees = $ecoutes;
    rsort($triees);
    verif('... dans l\'ordre decroissant des ecoutes', $ecoutes === $triees);

    verif('getAlbums rend des resultats', count(getAlbums(20)) > 0);
    verif('countAlbums compte les sorties publiees', countAlbums() > 0);

    echo "\n=== F. Aucune fonction sur une colonne dans un WHERE ===\n";
    // Les COMMENTAIRES sont ecartes : une explication qui cite le motif fautif
    // n'est pas une requete fautive. Sans cette precaution, le commentaire qui
    // avertit du piege declencherait l'alerte -- ce qui s'est produit.
    $sansCommentaires = static function (string $contenu): string {
        $lignes = [];
        foreach (explode("\n", $contenu) as $ligne) {
            $nue = ltrim($ligne);
            if (str_starts_with($nue, '//') || str_starts_with($nue, '#')
                || str_starts_with($nue, '*') || str_starts_with($nue, '/*')) {
                continue;
            }
            $lignes[] = $ligne;
        }

        return implode("\n", $lignes);
    };

    // Une fonction appliquee a une COLONNE filtree interdit l'index ; appliquee
    // a NOW() ou a une constante, elle est sans effet sur le plan.
    $motif = '/(DATE_FORMAT|YEAR|MONTH|DATE)\s*\(\s*(?!NOW\(\))[\w`]+\.?[\w`]*\s*[,)]\s*[^)]*\)\s*=/i';
    $fautifs = [];
    foreach (array_merge(glob($racine . '/*.php') ?: [], glob($racine . '/includes/*.php') ?: [], glob($racine . '/api/*.php') ?: []) as $fichier) {
        $contenu = $sansCommentaires((string) file_get_contents($fichier));
        if (preg_match($motif, $contenu, $m)) {
            $fautifs[] = basename($fichier) . ' (' . trim($m[0]) . ')';
        }
    }
    verif('Aucune fonction appliquee a une colonne filtree', $fautifs === [], implode(', ', $fautifs));
    // Controle du controle : le motif doit reellement reperer la faute.
    verif('Le detecteur repere bien la faute qu\'il cherche',
        preg_match($motif, "WHERE DATE_FORMAT(o.paid_at, '%Y-%m') = ?") === 1);
    verif('... et laisse passer la forme correcte',
        preg_match($motif, "WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')") === 0);

    verif('Le tableau de bord artiste utilise un intervalle',
        str_contains($source('artist-dashboard.php'), 'o.paid_at >= ? AND o.paid_at < ?'));
    verif('... et n\'applique plus DATE_FORMAT a la colonne',
        !str_contains($source('artist-dashboard.php'), "DATE_FORMAT(o.paid_at"));
    verif('Le seul DATE_FORMAT restant porte sur NOW()',
        str_contains($source('includes/database.php'), "DATE_FORMAT(NOW(), '%Y-%m-01')"));
    verif('... et c\'est explique sur place',
        str_contains($source('includes/database.php'), 's\'applique a NOW(), PAS a la colonne'));

    echo "\n=== G. Jeu de caracteres homogene ===\n";
    $collations = [];
    foreach ($db->query(
        "SELECT table_collation, COUNT(*) AS nombre FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'
         GROUP BY table_collation"
    )->fetchAll(PDO::FETCH_ASSOC) as $ligne) {
        $collations[(string) $ligne['table_collation']] = (int) $ligne['nombre'];
    }
    verif('Une seule collation pour toutes les tables', count($collations) === 1, json_encode($collations));
    verif('... et c\'est de l\'utf8mb4', str_starts_with(array_key_first($collations) ?: '', 'utf8mb4_'), array_key_first($collations) ?: '');

    $colonnes = [];
    foreach ($db->query(
        "SELECT collation_name, COUNT(*) AS nombre FROM information_schema.columns
         WHERE table_schema = DATABASE() AND collation_name IS NOT NULL
         GROUP BY collation_name"
    )->fetchAll(PDO::FETCH_ASSOC) as $ligne) {
        $colonnes[(string) $ligne['collation_name']] = (int) $ligne['nombre'];
    }
    verif('Aucune colonne ne deroge', count($colonnes) === 1, json_encode($colonnes));

    echo "\n=== H. Migration ===\n";
    $migration = $source('database/migrations/2026_09_23_0011_data07_index_catalogue.sql');
    verif('La migration est conditionnelle (rejouable)',
        substr_count($migration, 'information_schema.statistics') >= 8);
    verif('... et sait revenir en arriere', str_contains($migration, '-- DOWN'));
    verif('... elle explique l\'ordre des colonnes', str_contains($migration, 'de gauche a droite'));

    $sortie = [];
    exec(sprintf('"C:\xampp\php\php.exe" "%s/scripts/migrate.php" status 2>&1', $racine), $sortie, $code);
    $texte = implode("\n", $sortie);
    verif('DATA-07 est appliquee', preg_match('/appliquee\s+\S*data07_index_catalogue/', $texte) === 1, $texte);
    verif('... et le schema est a jour', str_contains($texte, 'Schema a jour'), $texte);
} finally {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    $nettoyer();
    $db->query('ANALYZE TABLE tracks, releases, artists')->fetchAll();
}

echo "\n";
echo sprintf("Resultat : %d reussi(s), %d echec(s)\n", $ok, $ko);
exit($ko === 0 ? 0 : 1);
