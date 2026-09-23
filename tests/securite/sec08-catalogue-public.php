<?php
/**
 * Tests SEC-08 : seul le contenu publie apparait au catalogue public.
 *
 * Execute chaque fonction publique de includes/database.php contre la base
 * locale, avec des titres et albums dans chaque statut (approved, draft,
 * pending, rejected) et un artiste desactive.
 *
 * Ce test existe parce que "php -l" ne valide pas le SQL contenu dans des
 * chaines : lors de SEC-08, une premiere correction par expression reguliere
 * avait produit "WHEREa.status = 'approved'ORDER BY" -- syntaxiquement valide
 * pour PHP, invalide pour MySQL. Seule l'execution reelle le revele.
 *
 * Usage : php tests/securite/sec08-catalogue-public.php
 * Environnement local uniquement.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$racine = dirname(__DIR__, 2);
if (!is_file($racine . '/.env.local')) {
    fwrite(STDERR, "Refus : .env.local absent. Test reserve a l'environnement local.\n");
    exit(1);
}

chdir($racine);
require $racine . '/includes/functions.php';

if (!EnvLoader::isDevelopment()) {
    fwrite(STDERR, "Refus : environnement non local.\n");
    exit(1);
}

$pdo = TchadokDatabase::getInstance()->getConnection();
if (!$pdo) {
    fwrite(STDERR, "Base indisponible.\n");
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

/** Identifiants des elements d'essai presents dans un resultat. */
function ids(array $lignes, string $cle = 'id'): array
{
    return array_values(array_filter(array_map(
        fn($l) => isset($l[$cle]) ? (int) $l[$cle] : null,
        $lignes
    ), fn($v) => $v !== null && $v >= 9100 && $v < 9200 || ($v >= 911 && $v <= 912)));
}

function nettoyer(PDO $pdo): void
{
    $pdo->exec('DELETE FROM streams WHERE track_id BETWEEN 9101 AND 9199');
    $pdo->exec('DELETE FROM tracks  WHERE id BETWEEN 9101 AND 9199');
    $pdo->exec('DELETE FROM albums  WHERE id BETWEEN 9101 AND 9199');
    $pdo->exec('DELETE FROM artists WHERE id IN (911, 912)');
    $pdo->exec('DELETE FROM users   WHERE id IN (911, 912)');
    $pdo->exec('DELETE FROM genres  WHERE id = 991');
}

// ---------------------------------------------------------------------
// Jeu d'essai
// ---------------------------------------------------------------------
nettoyer($pdo);
$hash = '$2y$10$abcdefghijklmnopqrstuuJ7x1Yw3m0Z5eZkQeXQkqG9o8x7a6b5C';

$pdo->exec("INSERT INTO genres (id, name, is_active) VALUES (991, 'ZZSEC08 Genre', 1)");
$pdo->exec("INSERT INTO users (id, username, email, password_hash, first_name, last_name, is_active)
            VALUES (911, 'zzsec08_actif', 'actif@sec08.local', '$hash', 'A', 'A', 1),
                   (912, 'zzsec08_inactif', 'inactif@sec08.local', '$hash', 'I', 'I', 1)");
$pdo->exec("INSERT INTO artists (id, user_id, stage_name, is_active, featured, verified, genres)
            VALUES (911, 911, 'ZZSEC08 Actif', 1, 1, 1, 'ZZSEC08 Genre'),
                   (912, 912, 'ZZSEC08 Inactif', 0, 1, 1, 'ZZSEC08 Genre')");

$pdo->exec("INSERT INTO albums (id, artist_id, title, genre_id, type, status, release_date) VALUES
    (9101, 911, 'ZZSEC08 Album publie',   991, 'album', 'approved', '2026-09-01'),
    (9102, 911, 'ZZSEC08 Album brouillon', 991, 'ep',   'draft',    '2026-09-02'),
    (9103, 911, 'ZZSEC08 Album attente',   991, 'single','pending', '2026-09-03')");

$pdo->exec("INSERT INTO tracks (id, artist_id, album_id, genre_id, title, audio_file, duration, is_free, status, total_streams, release_date) VALUES
    (9101, 911, 9101, 991, 'ZZSEC08 Titre publie',   'x.mp3', 200, 1, 'approved', 50, '2026-09-01'),
    (9102, 911, 9102, 991, 'ZZSEC08 Titre brouillon', 'x.mp3', 200, 1, 'draft',    90, '2026-09-02'),
    (9103, 911, 9103, 991, 'ZZSEC08 Titre attente',   'x.mp3', 200, 1, 'pending',  80, '2026-09-03'),
    (9104, 911, NULL, 991, 'ZZSEC08 Titre rejete',    'x.mp3', 200, 1, 'rejected', 70, '2026-09-04'),
    (9105, 912, NULL, 991, 'ZZSEC08 Titre artiste inactif', 'x.mp3', 200, 1, 'approved', 60, '2026-09-05')");

$pdo->exec("INSERT INTO streams (track_id, artist_id, duration_played, completed) VALUES
    (9101, 911, 60, 1), (9102, 911, 60, 1)");

$NON_PUBLIES = [9102, 9103, 9104];

// Les fonctions de includes/database.php interceptent les erreurs SQL et
// renvoient un tableau vide. Un test purement negatif ("aucun element non
// publie") passerait donc a tort sur une requete cassee. D'ou deux parades :
// des controles positifs, et la surveillance du journal d'erreurs.
$journal = ini_get('error_log');
$tailleJournalAvant = ($journal && is_file($journal)) ? filesize($journal) : 0;

try {
    echo "\n=== Recherche publique ===\n";
    $r = searchContent('ZZSEC08', 50);
    $t = ids($r['tracks']);
    verif('Titre publie trouve', in_array(9101, $t, true), json_encode($t));
    verif('Aucun titre non publie (brouillon, attente, rejete)', array_intersect($NON_PUBLIES, $t) === [], json_encode($t));
    verif('Aucun titre d\'artiste desactive', !in_array(9105, $t, true), json_encode($t));
    $a = ids($r['albums']);
    verif('Album publie trouve', in_array(9101, $a, true), json_encode($a));
    verif('Aucun album non publie', array_intersect([9102, 9103], $a) === [], json_encode($a));
    $ar = ids($r['artists']);
    verif('Artiste actif trouve', in_array(911, $ar, true), json_encode($ar));
    verif('Artiste desactive absent', !in_array(912, $ar, true), json_encode($ar));

    echo "\n=== Listes de titres ===\n";
    foreach (['getTrendingTracks', 'getNewTracks', 'getClassicTracks'] as $fn) {
        $brut = $fn(200);
        $l = ids($brut);
        verif("{$fn} : s'execute et renvoie des titres", count($brut) > 0, 'resultat vide : requete cassee ?');
        verif("{$fn} : aucun titre non publie", array_intersect($NON_PUBLIES, $l) === [], json_encode($l));
    }
    verif('getTrendingTracks : titre publie present', in_array(9101, ids(getTrendingTracks(200)), true));
    verif('getNewTracks : titre publie present', in_array(9101, ids(getNewTracks(200)), true));
    foreach (['getTrendingTracks', 'getNewTracks', 'getClassicTracks'] as $fn) {
        verif("{$fn} : titre d'artiste desactive exclu", !in_array(9105, ids($fn(200)), true));
    }

    echo "\n=== Albums ===\n";
    $l = ids(getNewReleases(200));
    verif('getNewReleases : album publie present', in_array(9101, $l, true), json_encode($l));
    verif('getNewReleases : aucun album non publie', array_intersect([9102, 9103], $l) === [], json_encode($l));
    $l = ids(getAlbums(200, 0));
    verif('getAlbums : aucun album non publie', array_intersect([9102, 9103], $l) === [], json_encode($l));
    $total = countAlbums();
    $attendu = (int) $pdo->query("SELECT COUNT(*) FROM albums a JOIN artists ar ON a.artist_id = ar.id
                                  WHERE a.status = 'approved' AND ar.is_active = 1")->fetchColumn();
    verif('countAlbums coherent avec les albums publies', $total === $attendu, "{$total} vs {$attendu}");
    verif('getAlbumTypes s\'execute', is_array(getAlbumTypes()));

    echo "\n=== Statistiques et compteurs publics ===\n";
    $stats = getPlatformStats();
    $publies = (int) $pdo->query("SELECT COUNT(*) FROM tracks t JOIN artists ar ON ar.id = t.artist_id
                                  WHERE t.status = 'approved' AND ar.is_active = 1")->fetchColumn();
    verif('getPlatformStats : total_tracks = titres publies d\'artistes actifs', $stats['total_tracks'] === $publies, "{$stats['total_tracks']} vs {$publies}");

    foreach (['getRisingArtists', 'getFeaturedArtists'] as $fn) {
        $ligne = array_values(array_filter($fn(500), fn($x) => (int) $x['id'] === 911))[0] ?? null;
        verif("{$fn} : tracks_count de l'artiste = 1 (publies seulement)", $ligne !== null && (int) $ligne['tracks_count'] === 1,
              $ligne === null ? 'artiste absent' : (string) $ligne['tracks_count']);
    }
    $ligne = array_values(array_filter(getAllArtists(500, 0), fn($x) => (int) $x['id'] === 911))[0] ?? null;
    verif('getAllArtists : tracks_count = 1', $ligne !== null && (int) $ligne['tracks_count'] === 1,
          $ligne === null ? 'artiste absent' : (string) $ligne['tracks_count']);

    $top = getTopArtistsByGenre(991, 10);
    $idsTop = array_map(fn($x) => (int) $x['id'], $top);
    $l911 = array_values(array_filter($top, fn($x) => (int) $x['id'] === 911))[0] ?? null;
    verif('getTopArtistsByGenre : artiste actif, 1 titre publie', $l911 !== null && (int) $l911['track_count'] === 1,
          $l911 === null ? 'absent' : (string) $l911['track_count']);
    verif('getTopArtistsByGenre : artiste desactive exclu', !in_array(912, $idsTop, true));

    $genre = array_values(array_filter(getGenresWithStats(), fn($g) => (int) $g['id'] === 991))[0] ?? null;
    // 5 titres dans le genre : 1 publie d'artiste actif (9101), 3 non publies,
    // 1 publie d'artiste desactive (9105). Seul le premier doit compter.
    verif('getGenresWithStats : 1 seul titre compte (publie, artiste actif)', $genre !== null && (int) ($genre['track_count'] ?? -1) === 1,
          $genre === null ? 'genre absent' : (string) ($genre['track_count'] ?? '?'));

    echo "\n=== Ecoutes ===\n";
    $recent = array_map(fn($x) => (int) $x['track_id'], getRecentStreams(200));
    verif('getRecentStreams : ecoute du titre publie presente', in_array(9101, $recent, true), json_encode($recent));
    verif('getRecentStreams : ecoute du brouillon absente', !in_array(9102, $recent, true), json_encode($recent));

    // api/stream.php : aucune ecoute enregistree sur un titre non publie.
    // Necessite le serveur web local.
    //
    // Depuis SEC-09, tout POST exige le jeton CSRF de la session : on ouvre
    // d'abord une session par un GET, comme le ferait un navigateur, puis on
    // envoie le cookie et le jeton.
    $site = rtrim((string) SITE_URL, '/');
    $page = @file_get_contents($site . '/login.php', false, stream_context_create(['http' => ['timeout' => 15]]));
    $cookie = '';
    foreach ($http_response_header ?? [] as $entete) {
        if (preg_match('/^Set-Cookie:\s*(TCHADOKSESSID=[^;]+)/i', $entete, $m)) {
            $cookie = $m[1];
        }
    }
    $jeton = ($page !== false && preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $page, $m)) ? $m[1] : '';

    $appel = function (int $trackId) use ($site, $cookie, $jeton): int {
        $ctx = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n"
                      . "Cookie: {$cookie}\r\n"
                      . "X-CSRF-Token: {$jeton}\r\n",
            'content' => json_encode(['track_id' => $trackId, 'duration' => 60]),
            'ignore_errors' => true,
            'timeout' => 15,
        ]]);
        @file_get_contents($site . '/api/stream.php', false, $ctx);
        $statut = $http_response_header[0] ?? '';
        return preg_match('/\s(\d{3})\s/', $statut, $m) ? (int) $m[1] : 0;
    };
    $avant = (int) $pdo->query('SELECT COUNT(*) FROM streams WHERE track_id = 9102')->fetchColumn();
    $code = $appel(9102);
    $apres = (int) $pdo->query('SELECT COUNT(*) FROM streams WHERE track_id = 9102')->fetchColumn();
    if ($code === 0) {
        echo "  --  api/stream.php non teste : serveur web local injoignable\n";
    } else {
        verif('api/stream.php : ecoute refusee sur un brouillon (404)', $code === 404, (string) $code);
        verif('api/stream.php : aucune ligne ajoutee pour le brouillon', $apres === $avant, "{$avant} -> {$apres}");
        verif('api/stream.php : ecoute acceptee sur un titre publie (201)', $appel(9101) === 201);
    }
    echo "\n=== Journal d'erreurs ===\n";
    clearstatcache();
    $nouvelles = '';
    if ($journal && is_file($journal) && filesize($journal) > $tailleJournalAvant) {
        $h = fopen($journal, 'rb');
        fseek($h, $tailleJournalAvant);
        $nouvelles = (string) stream_get_contents($h);
        fclose($h);
    }
    $erreursSql = preg_match_all('/(Error fetching|Error searching|SQLSTATE|syntax)/i', $nouvelles);
    verif('Aucune erreur SQL journalisee pendant le test', $erreursSql === 0,
          trim(substr($nouvelles, 0, 300)));
} catch (Throwable $e) {
    $ko++;
    echo "  !!  Exception : " . $e->getMessage() . "\n";
} finally {
    nettoyer($pdo);
}

echo "\nResultat : {$ok} reussi(s), {$ko} echec(s)\n";
exit($ko === 0 ? 0 : 1);
