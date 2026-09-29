<?php
/**
 * Tests LOT 11 : genres et categories (TAXO-01 a TAXO-03).
 *
 * Criteres du plan :
 *   TAXO-01 - referentiel charge, hierarchise, chaque genre porte un slug
 *             stable ;
 *   TAXO-02 - la colonne artists.genres n'existe plus ; aucun artiste actif
 *             ne publie sans genre principal ; les statistiques par genre
 *             reposent sur le seul referentiel ;
 *   TAXO-03 - la fusion reaffecte tous les contenus et conserve les anciens
 *             slugs en redirection ; un artiste ne peut plus creer de genre
 *             actif ; chaque modification est journalisee.
 *
 * Usage : C:\xampp\php\php.exe tests\taxonomie\taxo-genres.php
 */

declare(strict_types=1);

$racine = dirname(__DIR__, 2);
if (!is_file($racine . '/.env.local')) {
    fwrite(STDERR, "Refus : .env.local absent.\n");
    exit(1);
}
require_once $racine . '/includes/functions.php';
require_once $racine . '/includes/taxonomie.php';
require_once $racine . '/includes/publication.php';
if (!preg_match('/local|test|dev/', (string) EnvLoader::get('DB_DATABASE', ''))) {
    fwrite(STDERR, "Refus : base non locale.\n");
    exit(1);
}

$ok = 0;
$ko = 0;
function verif(string $libelle, bool $condition, string $detail = ''): void
{
    global $ok, $ko;
    $condition ? $ok++ : $ko++;
    echo $condition ? "  OK  {$libelle}\n" : "  !!  {$libelle}" . ($detail !== '' ? "  -> {$detail}" : '') . "\n";
}
function refuse(PDO $db, string $sql): bool
{
    try {
        $db->exec($sql);
        return false;
    } catch (Throwable $e) {
        return true;
    }
}
function navigateur(): array
{
    $cookies = (string) tempnam(sys_get_temp_dir(), 'taxo');
    return ['cookies' => $cookies, 'http' => static function (string $url, ?array $post = null) use ($cookies): array {
        $h = curl_init($url);
        curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $cookies, CURLOPT_COOKIEFILE => $cookies, CURLOPT_TIMEOUT => 30]);
        if ($post !== null) {
            curl_setopt_array($h, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($post)]);
        }
        $corps = (string) curl_exec($h);
        return [(int) curl_getinfo($h, CURLINFO_RESPONSE_CODE), $corps, (string) curl_getinfo($h, CURLINFO_REDIRECT_URL)];
    }];
}
function connecter(array $nav, string $base, string $email, string $mdp): void
{
    TchadokDatabase::getInstance()->getConnection()->prepare('DELETE FROM login_attempts WHERE identifier = ?')->execute([$email]);
    [, $page] = ($nav['http'])($base . '/login.php');
    preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $page, $m);
    ($nav['http'])($base . '/login.php', ['csrf_token' => $m[1] ?? '', 'email' => $email, 'password' => $mdp]);
}
function jeton(array $nav, string $url): string
{
    [, $page] = ($nav['http'])($url);
    preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $page, $m);
    return $m[1] ?? '';
}

$db = TchadokDatabase::getInstance()->getConnection();
$base = rtrim((string) EnvLoader::get('SITE_URL'), '/');
$marque = 'zztaxo' . bin2hex(random_bytes(3));
$motDePasse = 'Taxo-' . bin2hex(random_bytes(6));
$db->prepare("INSERT INTO users (username, email, password_hash, first_name, last_name, is_active, email_verified) VALUES (?, ?, ?, 'Test', 'Taxo', 1, 1)")
   ->execute([$marque, $marque . '@test.local', password_hash($motDePasse, PASSWORD_DEFAULT)]);
$uArtiste = (int) $db->lastInsertId();
$db->prepare('INSERT INTO artists (user_id, stage_name, is_active) VALUES (?, ?, 1)')->execute([$uArtiste, 'ZZTAXO ' . $marque]);
$artiste = (int) $db->lastInsertId();
$admin = (int) $db->query("SELECT id FROM users WHERE email = 'admin@tchadok.td'")->fetchColumn();
$genresCrees = [];
$titres = [];
$sortie = null;
$auditAvant = (int) $db->query("SELECT COUNT(*) FROM audit_log WHERE action = 'taxonomie.modifiee'")->fetchColumn();

try {
    echo "\n=== A. Referentiel (TAXO-01) ===\n";
    $categories = (int) $db->query("SELECT COUNT(*) FROM genres WHERE parent_id IS NULL AND status = 'active'")->fetchColumn();
    $genres = (int) $db->query("SELECT COUNT(*) FROM genres g JOIN genres c ON c.id = g.parent_id WHERE g.status = 'active'")->fetchColumn();
    verif('Referentiel hierarchise : 6 categories, au moins 31 genres', $categories >= 6 && $genres >= 31, "{$categories} / {$genres}");
    verif('Chaque genre porte un slug unique', (int) $db->query('SELECT COUNT(*) FROM genres WHERE slug IS NULL OR slug = \'\'')->fetchColumn() === 0
        && (int) $db->query('SELECT COUNT(*) - COUNT(DISTINCT slug) FROM genres')->fetchColumn() === 0);
    $categorie = (int) $db->query("SELECT id FROM genres WHERE parent_id IS NULL AND status = 'active' ORDER BY sort_order LIMIT 1")->fetchColumn();
    $autreCategorie = (int) $db->query("SELECT id FROM genres WHERE parent_id IS NULL AND status = 'active' ORDER BY sort_order LIMIT 1 OFFSET 1")->fetchColumn();

    echo "\n=== B. Genres des artistes (TAXO-02) ===\n";
    verif('La colonne artists.genres n\'existe plus', (int) $db->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'artists' AND column_name = 'genres'")->fetchColumn() === 0);
    $sansCode = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($racine, FilesystemIterator::SKIP_DOTS)) as $f) {
        $chemin = str_replace('\\', '/', (string) $f);
        if (str_ends_with($chemin, '.php') && !preg_match('#/(tests|vendor|database)/#', $chemin) && preg_match('/\b(a|ar|artists)\.genres\b|\$artist\[\'genres\'\]/', (string) file_get_contents($chemin))) {
            $sansCode[] = basename($chemin);
        }
    }
    verif('Plus aucune lecture du texte libre artists.genres dans le code', $sansCode === [], implode(', ', $sansCode));
    $g = $db->query("SELECT g.id FROM genres g JOIN genres c ON c.id = g.parent_id WHERE g.status = 'active' ORDER BY g.sort_order LIMIT 5")->fetchAll(PDO::FETCH_COLUMN);
    verif('Une categorie ne peut pas etre un genre principal', !Taxonomie::definirGenresArtiste($artiste, $categorie, [])['succes']);
    verif('Au plus trois genres secondaires', !Taxonomie::definirGenresArtiste($artiste, (int) $g[0], array_slice($g, 1, 4))['succes']);
    verif('Artiste actif sans genre principal : liste de controle', in_array($artiste, array_map('intval', array_column(Taxonomie::artistesSansGenre(), 'id')), true));
    verif('Genre principal et secondaires enregistres', Taxonomie::definirGenresArtiste($artiste, (int) $g[0], [(int) $g[1]])['succes']
        && (int) Taxonomie::genrePrincipal($artiste)['id'] === (int) $g[0] && Taxonomie::genresSecondaires($artiste) === [(int) $g[1]]);
    verif('Un seul genre principal par artiste (base)', refuse($db, "INSERT INTO artist_genres (artist_id, genre_id, is_primary) VALUES ({$artiste}, {$g[2]}, 1)"));

    $nav = navigateur();
    connecter($nav, $base, $marque . '@test.local', $motDePasse);
    // MOD-05 : parcours unique. Une sortie sans genre ne peut pas etre soumise.
    $db->prepare("INSERT INTO releases (artist_id, title, slug, format, status, is_free) VALUES (?, 'ZZTAXO sans genre', ?, 'single', 'draft', 1)")->execute([$artiste, $marque . '-sg']);
    $sansGenre = (int) $db->lastInsertId();
    verif('Publication sans genre : refusee cote serveur', in_array('Sortie : renseignez le genre.', Publication::verifier($sansGenre), true), json_encode(Publication::verifier($sansGenre)));
    $db->exec("DELETE FROM releases WHERE id = {$sansGenre}");
    verif('... avec un lien pour proposer un genre dans le parcours', str_contains((string) file_get_contents($racine . '/publier.php'), '/proposer-genre.php'));

    echo "\n=== C. Administration (TAXO-03) ===\n";
    $r = Taxonomie::creer('ZZTAXO Source ' . $marque, $categorie, 'Genre de test', $admin);
    $genresCrees[] = $source = (int) $r['id'];
    $r2 = Taxonomie::creer('ZZTAXO Cible ' . $marque, $categorie, '', $admin);
    $genresCrees[] = $cible = (int) $r2['id'];
    verif('Creation de genres', $r['succes'] && $r2['succes'] && estGenreSelectionnable($source));
    verif('Nom deja present : refuse', !Taxonomie::creer('ZZTAXO Source ' . $marque, $categorie, '', $admin)['succes']);
    $slugSource = (string) Taxonomie::genre($source)['slug'];
    $r = Taxonomie::modifier($source, ['nom' => 'ZZTAXO Source renomme ' . $marque, 'couleur' => '#12ab34', 'icone' => 'fa-drum', 'categorie' => $autreCategorie], $admin);
    $apres = Taxonomie::genre($source);
    verif('Renommage, couleur, icone, changement de categorie : le slug ne change pas', $r['succes'] && $apres['slug'] === $slugSource
        && $apres['name'] === 'ZZTAXO Source renomme ' . $marque && $apres['color'] === '#12ab34' && (int) $apres['parent_id'] === $autreCategorie, json_encode($r));
    verif('Un genre ne devient pas categorie (et inversement)', !Taxonomie::modifier($source, ['categorie' => null], $admin)['succes']);
    verif('Couleur invalide : refusee', !Taxonomie::modifier($source, ['couleur' => 'rouge'], $admin)['succes']);

    // Contenus sur la source : titre, sortie, artiste (principal sur la source,
    // secondaire deja sur la cible), agregats.
    $db->prepare("INSERT INTO releases (artist_id, title, slug, format, status, genre_id) VALUES (?, 'ZZTAXO sortie', ?, 'single', 'approved', ?)")->execute([$artiste, $marque . '-s', $source]);
    $sortie = (int) $db->lastInsertId();
    $db->prepare("INSERT INTO tracks (artist_id, release_id, album_id, genre_id, title, audio_file, duration, status) VALUES (?, ?, ?, ?, 'ZZTAXO titre', 'x.mp3', 200, 'approved')")->execute([$artiste, $sortie, $sortie, $source]);
    $titres[] = $titre = (int) $db->lastInsertId();
    $db->exec("DELETE FROM artist_genres WHERE artist_id = {$artiste}");
    $db->exec("INSERT INTO artist_genres (artist_id, genre_id, is_primary) VALUES ({$artiste}, {$source}, 1), ({$artiste}, {$cible}, 0)");
    $db->exec("INSERT INTO daily_rollups (day, track_id, artist_id, genre_id, category_id, source, listener_type, streams) VALUES ('1995-01-01', {$titre}, {$artiste}, {$source}, {$autreCategorie}, 'web', 'visiteur', 7)");

    verif('Fusion sans motif : refusee', !Taxonomie::fusionner($source, $cible, '', $admin)['succes']);
    verif('Un genre ne fusionne pas dans une categorie', !Taxonomie::fusionner($source, $categorie, 'Motif valable', $admin)['succes']);
    $r = Taxonomie::fusionner($source, $cible, 'Doublon du genre cible', $admin);
    verif('Fusion : reussie', $r['succes'], json_encode($r));
    verif('... titre et sortie reaffectes', (int) $db->query("SELECT genre_id FROM tracks WHERE id = {$titre}")->fetchColumn() === $cible
        && (int) $db->query("SELECT genre_id FROM releases WHERE id = {$sortie}")->fetchColumn() === $cible);
    verif('... artiste : une seule ligne, qui reste son genre principal', (int) Taxonomie::genrePrincipal($artiste)['id'] === $cible
        && (int) $db->query("SELECT COUNT(*) FROM artist_genres WHERE artist_id = {$artiste}")->fetchColumn() === 1);
    verif('... agregats reaffectes (les statistiques suivent)', (int) $db->query("SELECT genre_id FROM daily_rollups WHERE day = '1995-01-01' AND track_id = {$titre}")->fetchColumn() === $cible);
    verif('... source marquee fusionnee, plus proposable', Taxonomie::genre($source)['status'] === 'merged' && (int) Taxonomie::genre($source)['merged_into'] === $cible && !estGenreSelectionnable($source));
    [$code, , $vers] = ($nav['http'])($base . '/genres.php?genre=' . $slugSource);
    verif('Ancien slug : redirection permanente vers le genre cible', $code === 301 && str_contains($vers, 'genre=' . Taxonomie::genre($cible)['slug']), "HTTP {$code} {$vers}");
    [$code, $page] = ($nav['http'])($base . '/genres.php?genre=' . Taxonomie::genre($cible)['slug']);
    verif('Page du genre cible, avec le titre reaffecte', $code === 200 && str_contains($page, 'ZZTAXO titre'));
    [$code] = ($nav['http'])($base . '/genres.php?genre=zz-inexistant-' . $marque);
    verif('Slug inconnu : 404', $code === 404);

    verif('Archivage sans motif : refuse', !Taxonomie::archiver($cible, 'x', $admin)['succes']);
    verif('Archivage : plus proposable, le titre garde son genre', Taxonomie::archiver($cible, 'Genre retire du referentiel', $admin)['succes']
        && !estGenreSelectionnable($cible) && (int) $db->query("SELECT genre_id FROM tracks WHERE id = {$titre}")->fetchColumn() === $cible);
    verif('Categorie avec des genres actifs : archivage refuse', !Taxonomie::archiver($categorie, 'Categorie a retirer', $admin)['succes']);
    verif('Chaque modification est journalisee', (int) $db->query("SELECT COUNT(*) FROM audit_log WHERE action = 'taxonomie.modifiee'")->fetchColumn() - $auditAvant >= 5);

    echo "\n=== D. Propositions des artistes ===\n";
    $existant = (string) $db->query("SELECT name FROM genres WHERE status = 'active' AND parent_id IS NOT NULL LIMIT 1")->fetchColumn();
    verif('Proposer un genre existant : refuse, choisir dans la liste', !Taxonomie::proposer($artiste, $existant, $categorie, '')['succes']);
    $j = jeton($nav, $base . '/proposer-genre.php');
    ($nav['http'])($base . '/proposer-genre.php', ['csrf_token' => $j, 'nom' => 'ZZTAXO Propose ' . $marque, 'categorie' => $categorie, 'note' => 'Style local']);
    $prop = $db->query("SELECT * FROM genre_proposals WHERE artist_id = {$artiste} ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    verif('Proposition par la page artiste : en attente, AUCUN genre cree', $prop && $prop['status'] === 'pending'
        && !$db->query('SELECT 1 FROM genres WHERE name = ' . $db->quote('ZZTAXO Propose ' . $marque))->fetchColumn());
    verif('Refus sans motif : impossible (base)', refuse($db, "UPDATE genre_proposals SET status = 'rejected' WHERE id = {$prop['id']}"));
    $journal = $racine . '/storage/logs/mail.log';
    $taille = is_file($journal) ? filesize($journal) : 0;
    $r = Taxonomie::deciderProposition((int) $prop['id'], true, 'Genre pertinent, accepte', null, $admin);
    $nouveau = (int) $db->query("SELECT genre_id FROM genre_proposals WHERE id = {$prop['id']}")->fetchColumn();
    $genresCrees[] = $nouveau;
    verif('Acceptation : genre cree et proposable, artiste prevenu', $r['succes'] && estGenreSelectionnable($nouveau)
        && str_contains((string) file_get_contents($journal, false, null, $taille), 'acceptee'), json_encode($r));

    echo "\n=== E. Ecrans ===\n";
    [$code] = ($nav['http'])($base . '/admin/taxonomie.php');
    verif('Artiste : pas d\'acces a l\'administration de la taxonomie', $code !== 200, (string) $code);
    $adm = navigateur();
    connecter($adm, $base, 'admin@tchadok.td', 'tchadok2026');
    [$code, $page] = ($adm['http'])($base . '/admin/taxonomie.php');
    verif('Administration : referentiel, statuts et propositions', $code === 200 && str_contains($page, 'data-genre="' . $slugSource . '"') && str_contains($page, 'fusionne dans'));
    [, $page] = ($adm['http'])($base . '/admin-dashboard.php');
    verif('Entree « Genres et categories » dans la console', str_contains($page, '/admin/taxonomie.php'));
    [, $page] = ($nav['http'])($base . '/artist-dashboard.php');
    verif('Tableau de bord artiste : genre principal affiche', str_contains($page, 'ZZTAXO Cible ' . $marque));
    @unlink($nav['cookies']);
    @unlink($adm['cookies']);
} finally {
    $db->exec("DELETE FROM daily_rollups WHERE day = '1995-01-01' AND artist_id = {$artiste}");
    if ($titres) {
        $db->exec('DELETE FROM tracks WHERE id IN (' . implode(',', $titres) . ')');
    }
    if ($sortie) {
        $db->exec("DELETE FROM releases WHERE id = {$sortie}");
    }
    $db->exec("DELETE FROM genre_proposals WHERE artist_id = {$artiste}");
    $db->exec("DELETE FROM artist_genres WHERE artist_id = {$artiste}");
    $db->exec("DELETE FROM artists WHERE id = {$artiste}");
    $db->exec("DELETE FROM users WHERE id = {$uArtiste}");
    // Genres d'essai uniquement (la regle « jamais supprime » vaut pour le
    // referentiel reel).
    foreach (array_reverse(array_filter($genresCrees)) as $id) {
        $db->exec("UPDATE genres SET merged_into = NULL WHERE merged_into = {$id}");
        $db->exec("DELETE FROM genres WHERE id = {$id} AND name LIKE 'ZZTAXO %'");
    }
}

echo "\n" . sprintf("Resultat : %d reussi(s), %d echec(s)\n", $ok, $ko);
exit($ko === 0 ? 0 : 1);
