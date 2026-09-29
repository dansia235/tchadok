<?php
/**
 * Tests STAT-02 : enregistrement des ecoutes securise.
 *
 * Criteres du plan :
 *   - un appel direct sans jeton de lecture est rejete ;
 *   - un jeton rejoue est refuse ;
 *   - une ecoute declaree a 5 s n'est pas comptee ;
 *   - la duree d'un titre ne peut pas etre modifiee par l'artiste apres depot.
 * Et : seuil mesure cote serveur, titres courts, deduplication horaire, jeton
 * d'un autre auditeur, jeton expire, envois simultanes, extrait sans jeton,
 * pays et ville du navigateur ignores, duree lue dans le fichier au depot.
 *
 * Necessite le serveur web local. Usage :
 *   C:\xampp\php\php.exe tests\securite\stat02-ecoutes.php
 */

declare(strict_types=1);

$racine = dirname(__DIR__, 2);
if (!is_file($racine . '/.env.local')) {
    fwrite(STDERR, "Refus : .env.local absent.\n");
    exit(1);
}
require_once $racine . '/includes/functions.php';
require_once $racine . '/includes/duree-audio.php';
require_once $racine . '/includes/controles-depot.php';
require_once $racine . '/includes/agregats.php';
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

$db = TchadokDatabase::getInstance()->getConnection();
$base = rtrim((string) EnvLoader::get('SITE_URL'), '/');

/** Un navigateur : cookies propres, jeton CSRF. */
function navigateur(string $base, string $agent = 'Essai STAT-02'): array
{
    $cookies = (string) tempnam(sys_get_temp_dir(), 'st2');
    $http = static function (string $url, ?array $post = null, bool $json = false, array $fichiers = []) use ($cookies, $agent): array {
        $h = curl_init($url);
        curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $cookies, CURLOPT_COOKIEFILE => $cookies,
            CURLOPT_TIMEOUT => 30, CURLOPT_USERAGENT => $agent, CURLOPT_HEADER => false]);
        if ($post !== null) {
            curl_setopt($h, CURLOPT_POST, true);
            if ($json) {
                curl_setopt($h, CURLOPT_POSTFIELDS, json_encode($post));
                curl_setopt($h, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'X-CSRF-Token: ' . ($post['__csrf'] ?? '')]);
            } else {
                curl_setopt($h, CURLOPT_POSTFIELDS, $fichiers + $post);
            }
        }
        $corps = (string) curl_exec($h);
        return [(int) curl_getinfo($h, CURLINFO_RESPONSE_CODE), $corps, (string) curl_getinfo($h, CURLINFO_REDIRECT_URL)];
    };
    [, $page] = $http($base . '/index.php');
    preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $page, $m);
    return ['http' => $http, 'csrf' => $m[1] ?? '', 'cookies' => $cookies];
}
function jetonPour(array $nav, string $base, int $trackId): ?array
{
    [, $corps] = ($nav['http'])($base . '/api/track.php?action=resolve&id=' . $trackId);
    return json_decode($corps, true)['data']['ecoute'] ?? null;
}
function ecouter(array $nav, string $base, array $corps): array
{
    [$code, $reponse] = ($nav['http'])($base . '/api/stream.php', $corps + ['__csrf' => $nav['csrf']], true);
    return [$code, json_decode($reponse, true) ?? []];
}
function vieillir(PDO $db, ?string $jeton, int $secondes): void
{
    $db->prepare('UPDATE listening_sessions SET issued_at = NOW(3) - INTERVAL ? SECOND WHERE token_hash = ?')->execute([$secondes, hash('sha256', (string) $jeton)]);
}

$marque = 'zzst2' . bin2hex(random_bytes(3));
$motDePasse = 'St2-' . bin2hex(random_bytes(6));
$creer = static function (string $s) use ($db, $marque, $motDePasse): int {
    $db->prepare("INSERT INTO users (username, email, password_hash, first_name, last_name, is_active, email_verified) VALUES (?, ?, ?, 'Test', 'Ecoutes', 1, 1)")
       ->execute([$marque . $s, $marque . $s . '@test.local', password_hash($motDePasse, PASSWORD_DEFAULT)]);
    return (int) $db->lastInsertId();
};
$uArtiste = $creer('a');
$uAuditeur = $creer('b');
$db->prepare('INSERT INTO artists (user_id, stage_name, is_active) VALUES (?, ?, 1)')->execute([$uArtiste, 'ZZST2 ' . $marque]);
$artiste = (int) $db->lastInsertId();
$titre = static function (string $nom, int $duree, int $gratuit, ?string $extrait = null) use ($db, $artiste): int {
    $db->prepare("INSERT INTO tracks (artist_id, title, audio_file, preview_file, duration, is_free, price, status) VALUES (?, ?, 'x.mp3', ?, ?, ?, ?, 'approved')")
       ->execute([$artiste, $nom, $extrait, $duree, $gratuit, $gratuit ? 0 : 500]);
    return (int) $db->lastInsertId();
};
$tLong = $titre('ZZST2 long', 200, 1);
$tCourt = $titre('ZZST2 court', 20, 1);
$tPayant = $titre('ZZST2 payant', 180, 0, 'p.mp3');
$fichiersDeposes = [];

try {
    $a = navigateur($base);
    echo "\n=== A. Pas d'ecoute sans jeton ===\n";
    [$code] = ecouter($a, $base, ['track_id' => $tLong, 'duration' => 60]);
    verif('Appel direct par identifiant de titre : rejete (400)', $code === 400, (string) $code);
    [$code] = ecouter($a, $base, ['jeton' => str_repeat('ab', 32), 'duree' => 60]);
    verif('Jeton invente : rejete (403)', $code === 403, (string) $code);
    verif('... aucune ecoute enregistree', (int) $db->query("SELECT COUNT(*) FROM streams WHERE track_id = {$tLong}")->fetchColumn() === 0);

    echo "\n=== B. Seuil de 30 s, mesure par le serveur ===\n";
    $e = jetonPour($a, $base, $tLong);
    verif('Titre complet : jeton d\'ecoute remis, seuil 30 s', $e !== null && (int) $e['seuil'] === 30, json_encode($e));
    [$code, $r] = ecouter($a, $base, ['jeton' => $e['jeton'] ?? '', 'duree' => 40]);
    verif('Envoye tout de suite, 40 s annoncees : non comptee (le serveur mesure)', $code === 200 && ($r['motif'] ?? '') === 'trop_courte', json_encode($r));
    [$code] = ecouter($a, $base, ['jeton' => $e['jeton'] ?? '', 'duree' => 40]);
    verif('... et le jeton est consomme (409 ensuite)', $code === 409, (string) $code);

    $e = jetonPour($a, $base, $tLong);
    vieillir($db, $e['jeton'], 40);
    [$code, $r] = ecouter($a, $base, ['jeton' => $e['jeton'], 'duree' => 5]);
    verif('Ecoute declaree a 5 s : non comptee', $code === 200 && ($r['motif'] ?? '') === 'trop_courte', json_encode($r));

    $e = jetonPour($a, $base, $tLong);
    vieillir($db, $e['jeton'], 45);
    [$code, $r] = ecouter($a, $base, ['jeton' => $e['jeton'], 'duree' => 9999, 'country' => 'Wakanda', 'city' => 'Birnin']);
    verif('45 s ecoulees : ecoute comptee (201)', $code === 201 && ($r['comptee'] ?? false) === true, json_encode($r));
    $ligne = $db->query('SELECT * FROM streams WHERE id = ' . (int) ($r['stream_id'] ?? 0))->fetch(PDO::FETCH_ASSOC) ?: [];
    verif('... duree retenue plafonnee au temps ecoule, pas aux 9 999 s annoncees', (int) ($ligne['duration_played'] ?? 0) <= 47 && (int) ($ligne['duration_played'] ?? 0) >= 45, (string) ($ligne['duration_played'] ?? ''));
    verif('... pays et ville du navigateur ignores', array_key_exists('country', $ligne) && $ligne['country'] === null && $ligne['city'] === null, json_encode($ligne));
    verif('... auditeur anonyme identifie par une empreinte, sans compte', str_starts_with((string) ($ligne['listener_key'] ?? ''), 'a:') && $ligne['user_id'] === null);
    [$code] = ecouter($a, $base, ['jeton' => $e['jeton'], 'duree' => 45]);
    verif('Jeton rejoue : refuse (409)', $code === 409, (string) $code);

    echo "\n=== C. Deduplication et auditeurs ===\n";
    $e = jetonPour($a, $base, $tLong);
    vieillir($db, $e['jeton'], 45);
    [$code, $r] = ecouter($a, $base, ['jeton' => $e['jeton'], 'duree' => 45]);
    verif('Meme auditeur, meme titre, dans l\'heure : non comptee', $code === 200 && ($r['motif'] ?? '') === 'deja_comptee', json_encode($r));
    $b = navigateur($base, 'Autre navigateur');
    $e = jetonPour($a, $base, $tLong);
    vieillir($db, $e['jeton'], 45);
    [$code, $r] = ecouter($b, $base, ['jeton' => $e['jeton'], 'duree' => 45]);
    verif('Jeton presente par un autre auditeur : refuse (403)', $code === 403 && ($r['motif'] ?? '') === 'auditeur_different', json_encode($r));
    $eb = jetonPour($b, $base, $tLong);
    vieillir($db, $eb['jeton'], 45);
    [$code] = ecouter($b, $base, ['jeton' => $eb['jeton'], 'duree' => 45]);
    verif('Autre auditeur, son propre jeton : comptee', $code === 201, (string) $code);

    $c = navigateur($base, 'Compte connecte');
    [, $page] = ($c['http'])($base . '/login.php');
    preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $page, $m);
    ($c['http'])($base . '/login.php', ['csrf_token' => $m[1] ?? '', 'email' => $marque . 'b@test.local', 'password' => $motDePasse]);
    [, $page] = ($c['http'])($base . '/index.php');
    preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $page, $m);
    $c['csrf'] = $m[1] ?? '';
    $ec = jetonPour($c, $base, $tLong);
    vieillir($db, $ec['jeton'], 45);
    [$code, $r] = ecouter($c, $base, ['jeton' => $ec['jeton'], 'duree' => 45]);
    $cle = (string) $db->query('SELECT listener_key FROM streams WHERE id = ' . (int) ($r['stream_id'] ?? 0))->fetchColumn();
    verif('Auditeur connecte : comptee, rattachee a son compte', $code === 201 && $cle === 'u:' . $uAuditeur, $cle);

    echo "\n=== D. Titres courts, extraits, expiration, simultaneite ===\n";
    $e = jetonPour($a, $base, $tCourt);
    verif('Titre de 20 s : seuil = lecture complete (20 s)', (int) ($e['seuil'] ?? 0) === 20, json_encode($e));
    vieillir($db, $e['jeton'], 21);
    [$code] = ecouter($a, $base, ['jeton' => $e['jeton'], 'duree' => 20]);
    verif('... comptee apres 20 s', $code === 201, (string) $code);
    verif('Titre payant, visiteur : extrait seulement, aucun jeton (un extrait n\'est pas une ecoute)', jetonPour($a, $base, $tPayant) === null);

    $e = jetonPour($b, $base, $tCourt);
    $db->prepare('UPDATE listening_sessions SET issued_at = NOW(3) - INTERVAL 8 HOUR, expires_at = NOW() - INTERVAL 1 HOUR WHERE token_hash = ?')->execute([hash('sha256', $e['jeton'])]);
    [$code] = ecouter($b, $base, ['jeton' => $e['jeton'], 'duree' => 20]);
    verif('Jeton expire : refuse (410)', $code === 410, (string) $code);

    $d = navigateur($base, 'Envois simultanes');
    $e = jetonPour($d, $base, $tLong);
    vieillir($db, $e['jeton'], 45);
    $multi = curl_multi_init();
    $poignees = [];
    for ($i = 0; $i < 5; $i++) {
        $h = curl_init($base . '/api/stream.php');
        curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_COOKIEFILE => $d['cookies'], CURLOPT_USERAGENT => 'Envois simultanes',
            CURLOPT_POSTFIELDS => json_encode(['jeton' => $e['jeton'], 'duree' => 45]), CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-CSRF-Token: ' . $d['csrf']]]);
        curl_multi_add_handle($multi, $h);
        $poignees[] = $h;
    }
    do {
        curl_multi_exec($multi, $actifs);
        curl_multi_select($multi);
    } while ($actifs > 0);
    $codes = array_map(fn($h) => (int) curl_getinfo($h, CURLINFO_RESPONSE_CODE), $poignees);
    sort($codes);
    verif('Cinq envois simultanes du meme jeton : un seul compte', count(array_keys($codes, 201)) === 1 && count(array_keys($codes, 409)) === 4, json_encode($codes));

    echo "\n=== E. Duree lue dans le fichier ===\n";
    $dossier = sys_get_temp_dir();
    $trame = "\xFF\xFB\x90\x00" . str_repeat("\0", 413);
    // 5 000 trames de 417 octets a 128 kbit/s : environ 130 s (estimation au
    // debit constant ; le fichier synthetique n'a pas de trames de bourrage).
    file_put_contents("{$dossier}/st2.mp3", str_repeat($trame, 5000));
    // MP3 tronque : etiquette ID3 et une seule trame. Le controle de contenu
    // (SEC-17) l'accepte comme MP3, mais il dure 0,03 s : duree inexploitable.
    file_put_contents("{$dossier}/st2-faux.mp3", 'ID3' . "\x03\x00\x00\x00\x00\x00\x0A" . str_repeat("\0", 10) . $trame);
    $dureeLue = DureeAudio::secondes("{$dossier}/st2.mp3");
    verif('Lecteur de duree : MP3 de 5 000 trames, environ 130 s', $dureeLue !== null && abs($dureeLue - 130) <= 1, (string) $dureeLue);
    verif('... MP3 tronque (une trame) : inexploitable', DureeAudio::secondes("{$dossier}/st2-faux.mp3") === null);

    // MOD-05 : le depot passe par le parcours unique (publier.php), qui appelle
    // Publication::ajouterPiste -- le meme code est exerce ici directement.
    $db->prepare("INSERT INTO releases (artist_id, title, slug, format, status, is_free) VALUES (?, 'ZZST2 sortie', ?, 'single', 'draft', 1)")->execute([$artiste, $marque . '-s']);
    $sortieEssai = (int) $db->lastInsertId();
    $deposer = static function (string $source, string $nom) use ($dossier, $sortieEssai, $artiste): array {
        $copie = $dossier . '/depot-' . bin2hex(random_bytes(4));
        copy($source, $copie);
        return Publication::ajouterPiste($sortieEssai, $artiste, ['tmp_name' => $copie, 'name' => $nom, 'error' => UPLOAD_ERR_OK, 'size' => filesize($copie)]);
    };
    $source = (string) file_get_contents($racine . '/publier.php');
    verif('Parcours de publication : aucun champ de duree', !str_contains($source, 'name="duration"') && str_contains($source, 'la duree est lue dans le fichier'));
    $r = $deposer("{$dossier}/st2.mp3", 'st2.mp3');
    $depose = $db->query("SELECT id, duration, audio_file FROM tracks WHERE release_id = {$sortieEssai} ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($depose) {
        $fichiersDeposes[] = $racine . '/' . $depose['audio_file'];
    }
    verif('Depot : duree lue dans le fichier', $r['succes'] && $depose && (int) $depose['duration'] === $dureeLue, json_encode([$r, $depose]));
    $avant = count(glob($racine . '/' . AUDIO_PATH . '*') ?: []);
    $r = $deposer("{$dossier}/st2-faux.mp3", 'faux.mp3');
    verif('Fichier illisible : depot refuse, message clair', !$r['succes'] && str_contains($r['message'], 'Fichier audio illisible'), $r['message']);
    verif('... et le fichier refuse n\'est pas garde', count(glob($racine . '/' . AUDIO_PATH . '*') ?: []) === $avant);
    file_put_contents("{$dossier}/st2.txt", 'texte');
    $r = $deposer("{$dossier}/st2.txt", 'notes.txt');
    verif('Refus du controle de depot : son message est rendu a l\'artiste (pas « erreur de notre cote »)', !$r['succes'] && str_contains($r['message'], 'Type de fichier non autorise'), $r['message']);
    @unlink("{$dossier}/st2.txt");

    // Aucun chemin du code ne modifie la duree d'un titre apres depot.
    $modifie = [];
    $iterateur = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($racine, FilesystemIterator::SKIP_DOTS));
    foreach ($iterateur as $fichier) {
        $chemin = str_replace('\\', '/', (string) $fichier);
        if (!str_ends_with($chemin, '.php') || preg_match('#/(tests|vendor|mock-gateways|database|storage)/#', $chemin)) {
            continue;
        }
        if (preg_match('/UPDATE\s+tracks\s+SET[^;]*\bduration\s*=/is', (string) file_get_contents($chemin))) {
            $modifie[] = substr($chemin, strlen(str_replace('\\', '/', $racine)) + 1);
        }
    }
    verif('Aucun code ne modifie la duree d\'un titre apres depot', $modifie === [], implode(', ', $modifie));
    $js = (string) file_get_contents($racine . '/assets/js/player.js');
    verif('Lecteur : transmet le jeton apres lecture effective, plus d\'identifiant de titre', str_contains($js, 'recordStream(ecoute.jeton') && !str_contains($js, 'track_id: trackId'));
} finally {
    $titres = implode(',', [$tLong, $tCourt, $tPayant]);
    $db->exec("DELETE FROM streams WHERE artist_id = {$artiste}");
    $db->exec("DELETE FROM listening_sessions WHERE track_id IN (SELECT id FROM tracks WHERE artist_id = {$artiste})");
    $db->exec("DELETE FROM tracks WHERE artist_id = {$artiste}");
    $db->exec("DELETE FROM releases WHERE artist_id = {$artiste}");
    foreach ($fichiersDeposes as $f) {
        @unlink($f);
    }
    $db->exec("DELETE FROM artists WHERE id = {$artiste}");
    foreach ([$uArtiste, $uAuditeur] as $u) {
        try {
            $db->exec("DELETE FROM users WHERE id = {$u}");
        } catch (Throwable $e) {
            $db->exec("UPDATE users SET is_active = 0 WHERE id = {$u}");
        }
    }
    @unlink(sys_get_temp_dir() . '/st2.mp3');
    @unlink(sys_get_temp_dir() . '/st2-faux.mp3');
}

echo "\n" . sprintf("Resultat : %d reussi(s), %d echec(s)\n", $ok, $ko);
exit($ko === 0 ? 0 : 1);
