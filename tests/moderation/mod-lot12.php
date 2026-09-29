<?php
/**
 * Tests LOT 12 : moderation et onboarding artiste (MOD-01 a MOD-07), par les
 * vraies pages, du bout en bout :
 *   inscription -> e-mail a confirmer -> dossier artiste -> validation ->
 *   publication -> controles de fichiers -> moderation -> catalogue public ->
 *   signalement pour droits d'auteur -> contre-notification -> decision.
 *
 * Criteres du plan verifies :
 *   MOD-01 transition non autorisee refusee cote serveur (et en base) ;
 *   MOD-02 soumission en file aussitot, refus notifie avec motif, delai mesurable ;
 *   MOD-03 revendication : retrait du catalogue public immediat, decisions motivees ;
 *   MOD-04 64 kbit/s refuse avec message explicite, doublon exact signale, duree reelle ;
 *   MOD-05 un seul ecran de publication, regles cote serveur, brouillon repris ;
 *   MOD-06 artiste non valide ne publie pas, pieces reservees et journalisees ;
 *   MOD-07 compte non verifie : ni achat ni publication ; lien qui expire et se renvoie.
 *   + transport SMTP authentifie (QA-02) sur un faux serveur.
 *
 * Usage : C:\xampp\php\php.exe tests\moderation\mod-lot12.php
 */

declare(strict_types=1);

$racine = dirname(__DIR__, 2);
if (!is_file($racine . '/.env.local')) {
    fwrite(STDERR, "Refus : .env.local absent.\n");
    exit(1);
}
require_once $racine . '/includes/functions.php';
require_once $racine . '/includes/courriel.php';
require_once $racine . '/includes/moderation.php';
require_once $racine . '/includes/signalements.php';
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
    $cookies = (string) tempnam(sys_get_temp_dir(), 'm12');
    return ['cookies' => $cookies, 'http' => static function (string $url, array|string|null $post = null, array $fichiers = [], array $entetes = []) use ($cookies): array {
        $h = curl_init($url);
        curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $cookies, CURLOPT_COOKIEFILE => $cookies, CURLOPT_TIMEOUT => 60, CURLOPT_HTTPHEADER => $entetes]);
        if ($post !== null) {
            curl_setopt_array($h, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $fichiers !== [] ? $fichiers + $post : (is_string($post) ? $post : http_build_query($post))]);
        }
        $corps = (string) curl_exec($h);
        return [(int) curl_getinfo($h, CURLINFO_RESPONSE_CODE), $corps, (string) curl_getinfo($h, CURLINFO_REDIRECT_URL)];
    }];
}
function jeton(array $nav, string $url): string
{
    [, $page] = ($nav['http'])($url);
    preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $page, $m);
    return $m[1] ?? '';
}
function poster(array $nav, string $page, array $champs, array $fichiers = []): array
{
    // SEC-12 : les envois repetes depuis la meme adresse declenchent la question
    // anti-robot ; les compteurs d'essai sont remis a zero, comme dans sec12.
    TchadokDatabase::getInstance()->getConnection()->exec("DELETE FROM rate_limit_hits WHERE bucket IN ('inscription', 'connexion', 'formulaire')");
    $base = rtrim((string) EnvLoader::get('SITE_URL'), '/');
    $cible = str_starts_with($page, 'http') ? $page : $base . '/' . ltrim($page, '/');
    return ($nav['http'])($cible, ['csrf_token' => jeton($nav, $cible)] + $champs, $fichiers);
}
function courrielsDepuis(int $position): string
{
    $journal = dirname(__DIR__, 2) . '/storage/logs/mail.log';
    return is_file($journal) ? (string) file_get_contents($journal, false, null, $position) : '';
}
function tailleJournal(string $nom): int
{
    $f = dirname(__DIR__, 2) . '/storage/logs/' . $nom;
    clearstatcache();
    return is_file($f) ? (int) filesize($f) : 0;
}

$db = TchadokDatabase::getInstance()->getConnection();
$base = rtrim((string) EnvLoader::get('SITE_URL'), '/');
$marque = 'zzm12' . bin2hex(random_bytes(3));
$motDePasse = 'M12-' . bin2hex(random_bytes(6)) . 'Aa9';
$tmp = sys_get_temp_dir() . '/' . $marque;
@mkdir($tmp);
$fichiersCrees = [];
$userId = 0;
$artistId = 0;
$releaseId = 0;

// Fichiers d'essai : MP3 a debit constant (128 et 64 kbit/s), images.
$mp3 = static function (string $nom, int $trames, string $entete, int $taille) use ($tmp): string {
    file_put_contents("{$tmp}/{$nom}", str_repeat($entete . str_repeat("\0", $taille - 4), $trames));
    return "{$tmp}/{$nom}";
};
$bon = $mp3('bon.mp3', 5000, "\xFF\xFB\x90\x00", 417);       // 128 kbit/s, ~130 s
$faible = $mp3('faible.mp3', 5000, "\xFF\xFB\x50\x00", 208);  // 64 kbit/s
$image = static function (string $nom, int $cote) use ($tmp): string {
    $im = imagecreatetruecolor($cote, $cote);
    imagefilledrectangle($im, 0, 0, $cote, $cote, imagecolorallocate($im, 30, 90, 200));
    imagepng($im, "{$tmp}/{$nom}");
    return "{$tmp}/{$nom}";
};
$petite = $image('petite.png', 800);
$grande = $image('grande.png', 1500);
$piece = $image('piece.png', 300);

$capture = $tmp . '/smtp.jsonl';
$smtp = proc_open([PHP_BINARY, $racine . '/tests/outils/faux-smtp.php', '2527', $capture], [['pipe', 'r'], ['file', 'NUL', 'w'], ['file', 'NUL', 'w']], $t);
usleep(500000);

try {
    echo "\n=== A. Transport SMTP authentifie (QA-02) ===\n";
    $config = ['hote' => '127.0.0.1', 'port' => 2527, 'utilisateur' => 'essai', 'mot_de_passe' => 'secret', 'chiffrement' => ''];
    verif('Envoi SMTP authentifie : accepte', Courriel::envoyerSmtp('dest@test.local', 'Accents éàç — sujet', '<p>Corps é</p>', $config), Courriel::$derniereErreur);
    $m = json_decode((string) strtok((string) @file_get_contents($capture), "\n"), true) ?: [];
    preg_match('/Subject: (.+)/', (string) ($m['donnees'] ?? ''), $s);
    verif('... sujet UTF-8 intact, destinataire correct', iconv_mime_decode(trim($s[1] ?? ''), 0, 'UTF-8') === 'Accents éàç — sujet' && str_contains((string) ($m['a'] ?? ''), 'dest@test.local'));
    verif('Mauvais mot de passe : refuse, sans divulguer le secret', !Courriel::envoyerSmtp('dest@test.local', 'x', 'y', ['mot_de_passe' => 'faux'] + $config)
        && Courriel::$derniereErreur === 'authentification SMTP refusee');

    echo "\n=== B. Inscription et verification de l'e-mail (MOD-07) ===\n";
    $artisteNav = navigateur();
    $position = tailleJournal('mail.log');
    $email = $marque . '@test.local';
    poster($artisteNav, 'register.php', ['first_name' => 'Adoum', 'last_name' => 'Essai', 'username' => $marque, 'email' => $email, 'password' => $motDePasse,
        'confirm_password' => $motDePasse, 'user_type' => 'artist', 'stage_name' => 'ZZM12 ' . $marque, 'terms' => '1', 'accept_terms' => '1']);
    $userId = (int) $db->query('SELECT id FROM users WHERE email = ' . $db->quote($email))->fetchColumn();
    $artistId = (int) $db->query("SELECT id FROM artists WHERE user_id = {$userId}")->fetchColumn();
    verif('Inscription artiste : compte cree, non verifie, dossier en brouillon', $userId > 0 && $artistId > 0
        && (int) $db->query("SELECT email_verified FROM users WHERE id = {$userId}")->fetchColumn() === 0
        && $db->query("SELECT status FROM artist_dossiers WHERE artist_id = {$artistId}")->fetchColumn() === 'brouillon', "{$userId}/{$artistId}");
    $courriel = courrielsDepuis($position);
    preg_match_all('#verifier-email\.php\?jeton=([a-f0-9]{64})#', $courriel, $liens);
    $lien = [1 => end($liens[1]) ?: null];
    verif('Lien de confirmation envoye par e-mail', isset($lien[1]));
    verif('... le jeton n\'est garde qu\'en empreinte', $db->query("SELECT verification_token FROM users WHERE id = {$userId}")->fetchColumn() === hash('sha256', $lien[1] ?? ''));

    $db->exec("DELETE FROM login_attempts WHERE identifier = " . $db->quote($email));
    poster($artisteNav, 'login.php', ['email' => $email, 'password' => $motDePasse]);
    [$code, $corps] = ($artisteNav['http'])($base . '/api/payments/initier.php', json_encode(['commande' => 'TCHK-2026-ABCDEF12', 'passerelle' => 'airtel_money']), [],
        ['Content-Type: application/json', 'X-CSRF-Token: ' . jeton($artisteNav, $base . '/verifier-email.php')]);
    verif('Compte non verifie : achat refuse (403)', $code === 403 && str_contains($corps, 'Confirmez'), "HTTP {$code} {$corps}");
    [$code, $page] = ($artisteNav['http'])($base . '/publier.php');
    verif('Compte non verifie : publication bloquee', str_contains($page, 'data-condition="email"'));
    [, $page] = poster($artisteNav, 'verifier-email.php', []);
    verif('Renvoi immediat du lien : refuse (2 minutes)', str_contains($page, 'Patientez deux minutes'));
    $db->exec("UPDATE users SET verification_expires_at = NOW() - INTERVAL 1 MINUTE WHERE id = {$userId}");
    [, $page] = ($artisteNav['http'])($base . '/verifier-email.php?jeton=' . ($lien[1] ?? ''));
    verif('Lien expire : refuse', str_contains($page, 'expire'));
    $db->exec("UPDATE users SET verification_sent_at = NOW() - INTERVAL 5 MINUTE WHERE id = {$userId}");
    $position = tailleJournal('mail.log');
    [, $page] = poster($artisteNav, 'verifier-email.php', []);
    preg_match('#verifier-email\.php\?jeton=([a-f0-9]{64})#', courrielsDepuis($position), $lien2);
    verif('Nouveau lien envoye', isset($lien2[1]) && $lien2[1] !== ($lien[1] ?? ''));
    [, $page] = ($artisteNav['http'])($base . '/verifier-email.php?jeton=' . ($lien2[1] ?? ''));
    verif('Adresse confirmee', str_contains($page, 'data-verification="ok"') && (int) $db->query("SELECT email_verified FROM users WHERE id = {$userId}")->fetchColumn() === 1);
    [, $page] = ($artisteNav['http'])($base . '/verifier-email.php?jeton=' . ($lien2[1] ?? ''));
    verif('Lien deja utilise : refuse', str_contains($page, 'data-verification="refus"'));

    echo "\n=== C. Dossier artiste (MOD-06) ===\n";
    [, $page] = ($artisteNav['http'])($base . '/publier.php');
    verif('Artiste non valide : publication bloquee (dossier, genre)', str_contains($page, 'data-condition="dossier"') && str_contains($page, 'data-condition="genre"'));
    verif('Soumission du dossier incomplet : refusee', !str_contains(poster($artisteNav, 'artiste-dossier.php', ['action' => 'soumettre'])[1], 'Dossier soumis'));
    $position = tailleJournal('sms.log');
    poster($artisteNav, 'artiste-dossier.php', ['action' => 'telephone', 'numero' => '66 12 34 56']);
    preg_match('/code de verification est (\d{6})/', (string) file_get_contents($racine . '/storage/logs/sms.log', false, null, $position), $code6);
    verif('Code SMS envoye (transport de developpement)', isset($code6[1]));
    poster($artisteNav, 'artiste-dossier.php', ['action' => 'code', 'code' => '000000']);
    poster($artisteNav, 'artiste-dossier.php', ['action' => 'code', 'code' => $code6[1] ?? '']);
    verif('Telephone verifie avec le bon code', $db->query("SELECT phone_verified_at IS NOT NULL FROM users WHERE id = {$userId}")->fetchColumn() == 1);
    foreach (['identite', 'selfie'] as $type) {
        poster($artisteNav, 'artiste-dossier.php', ['action' => $type], ['piece' => new CURLFile($piece, 'image/png', 'piece.png')]);
    }
    $dossier = $db->query("SELECT * FROM artist_dossiers WHERE artist_id = {$artistId}")->fetch(PDO::FETCH_ASSOC);
    $cheminPiece = $racine . '/storage/private/pieces/' . $dossier['identity_doc'];
    $fichiersCrees[] = $cheminPiece;
    $fichiersCrees[] = $racine . '/storage/private/pieces/' . $dossier['selfie_doc'];
    verif('Pieces deposees, chiffrees hors racine web (aucun contenu en clair)', is_file($cheminPiece) && str_starts_with((string) file_get_contents($cheminPiece), 'TPI1')
        && !str_contains((string) file_get_contents($cheminPiece), substr((string) file_get_contents($piece), 0, 16)));
    $genre = (int) $db->query("SELECT g.id FROM genres g JOIN genres c ON c.id = g.parent_id WHERE g.status = 'active' ORDER BY g.sort_order LIMIT 1")->fetchColumn();
    poster($artisteNav, 'artiste-dossier.php', ['action' => 'profil', 'stage_name' => 'ZZM12 ' . $marque, 'bio' => str_repeat('Artiste de N\'Djamena, musique urbaine. ', 2), 'genre_principal' => $genre]);
    poster($artisteNav, 'artiste-dossier.php', ['action' => 'photo'], ['photo' => new CURLFile($grande, 'image/png', 'photo.png')]);
    poster($artisteNav, 'artiste-dossier.php', ['action' => 'droits', 'titularite' => '1', 'exclusivite' => '1']);
    [, $page] = poster($artisteNav, 'artiste-dossier.php', ['action' => 'soumettre']);
    verif('Dossier complet soumis', $db->query("SELECT status FROM artist_dossiers WHERE artist_id = {$artistId}")->fetchColumn() === 'soumis', $page === '' ? '' : strip_tags(substr($page, 0, 0)));

    [$code] = ($artisteNav['http'])($base . '/admin/dossiers-artistes.php?artiste=' . $artistId . '&piece=identite');
    verif('Pieces inaccessibles a l\'artiste lui-meme par l\'administration', $code !== 200);
    $adm = navigateur();
    $db->exec("DELETE FROM login_attempts WHERE identifier = 'admin@tchadok.td'");
    poster($adm, 'login.php', ['email' => 'admin@tchadok.td', 'password' => 'tchadok2026']);
    $admin = (int) $db->query("SELECT id FROM users WHERE email = 'admin@tchadok.td'")->fetchColumn();
    $auditAvant = (int) $db->query("SELECT COUNT(*) FROM audit_log WHERE action = 'dossier.piece_consultee'")->fetchColumn();
    [$code, $contenu] = ($adm['http'])($base . '/admin/dossiers-artistes.php?artiste=' . $artistId . '&piece=identite');
    verif('Validateur : piece dechiffree a l\'identique', $code === 200 && $contenu === file_get_contents($piece));
    verif('... et la consultation est journalisee', (int) $db->query("SELECT COUNT(*) FROM audit_log WHERE action = 'dossier.piece_consultee'")->fetchColumn() === $auditAvant + 1);
    [, $page] = poster($adm, 'admin/dossiers-artistes.php', ['artiste' => $artistId, 'decision' => 'a_completer', 'niveau' => 'decouverte', 'motif' => '']);
    verif('Complement demande sans motif : refuse', str_contains($page, 'Motif obligatoire'));
    poster($adm, 'admin/dossiers-artistes.php', ['artiste' => $artistId, 'decision' => 'valide', 'niveau' => 'decouverte', 'motif' => '']);
    verif('Dossier valide au niveau Decouverte', $db->query("SELECT CONCAT(status, '/', level) FROM artist_dossiers WHERE artist_id = {$artistId}")->fetchColumn() === 'valide/decouverte');

    echo "\n=== D. Publication unique et controles (MOD-05, MOD-04) ===\n";
    [$code, , $vers] = ($artisteNav['http'])($base . '/artist-add-song.php');
    verif('Anciens parcours : redirection vers le parcours unique', in_array($code, [301, 302], true) && str_contains($vers, '/publier.php'), "{$code} {$vers}");
    [, $page] = ($artisteNav['http'])($base . '/publier.php');
    verif('Conditions remplies : publication ouverte', !str_contains($page, 'data-conditions-publication') && str_contains($page, 'Nouvelle sortie'));
    poster($artisteNav, 'publier.php', ['action' => 'creer', 'format' => 'single', 'titre' => 'ZZM12 Single ' . $marque]);
    $releaseId = (int) $db->query("SELECT id FROM releases WHERE artist_id = {$artistId} ORDER BY id DESC LIMIT 1")->fetchColumn();
    verif('Brouillon de sortie cree', $releaseId > 0 && $db->query("SELECT status FROM releases WHERE id = {$releaseId}")->fetchColumn() === 'draft');
    $etape = $base . '/publier.php?sortie=' . $releaseId . '&etape=fichiers';
    [, $page] = poster($artisteNav, $etape, ['action' => 'piste', 'sortie' => $releaseId], ['audio' => new CURLFile($faible, 'audio/mpeg', 'faible.mp3')]);
    verif('Fichier a 64 kbit/s : refuse avec un message explicite', str_contains($page, '64 kbit/s') && str_contains($page, '128'), strip_tags(substr($page, strpos($page, 'data-erreur') ?: 0, 300)));
    poster($artisteNav, $etape, ['action' => 'piste', 'sortie' => $releaseId], ['audio' => new CURLFile($bon, 'audio/mpeg', 'Mon_titre.mp3')]);
    $t1 = $db->query("SELECT * FROM tracks WHERE release_id = {$releaseId} ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
    if ($t1) {
        $fichiersCrees[] = $racine . '/' . $t1['audio_file'];
    }
    verif('Titre depose : duree reelle lue dans le fichier, debit et frequence', $t1 && abs((int) $t1['duration'] - 130) <= 1 && (int) $t1['audio_bitrate'] === 128 && (int) $t1['audio_sample_rate'] === 44100, json_encode($t1 ? [$t1['duration'], $t1['audio_bitrate'], $t1['audio_sample_rate']] : []));
    [, $page] = poster($artisteNav, $etape, ['action' => 'piste', 'sortie' => $releaseId], ['audio' => new CURLFile($bon, 'audio/mpeg', 'copie.mp3')]);
    $t2 = $db->query("SELECT * FROM tracks WHERE release_id = {$releaseId} ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
    if ($t2 && (int) $t2['id'] !== (int) ($t1['id'] ?? 0)) {
        $fichiersCrees[] = $racine . '/' . $t2['audio_file'];
    }
    $doublon = $db->query('SELECT detail FROM content_checks WHERE track_id = ' . (int) ($t2['id'] ?? 0) . " AND check_code = 'doublon' AND result = 'signal'")->fetchColumn();
    verif('Doublon exact : signale au moderateur avec le titre concerne', $doublon && str_contains((string) $doublon, 'Mon titre'), (string) $doublon);
    poster($artisteNav, $etape, ['action' => 'retirer', 'sortie' => $releaseId, 'titre_id' => (int) ($t2['id'] ?? 0)]);
    verif('Titre retire du brouillon', !$db->query('SELECT 1 FROM tracks WHERE id = ' . (int) ($t2['id'] ?? 0))->fetchColumn());
    [, $page] = poster($artisteNav, $etape, ['action' => 'pochette', 'sortie' => $releaseId], ['pochette' => new CURLFile($petite, 'image/png', 'petite.png')]);
    verif('Pochette 800 x 800 : refusee (1400 minimum)', str_contains($page, '800 x 800'));
    poster($artisteNav, $etape, ['action' => 'pochette', 'sortie' => $releaseId], ['pochette' => new CURLFile($grande, 'image/png', 'grande.png')]);
    $pochette = (string) $db->query("SELECT cover_image FROM releases WHERE id = {$releaseId}")->fetchColumn();
    $fichiersCrees[] = $racine . '/' . $pochette;
    verif('Pochette 1500 x 1500 : acceptee et re-encodee en JPEG', str_ends_with($pochette, '.jpg') && @getimagesize($racine . '/' . $pochette)[2] === IMAGETYPE_JPEG);

    [, $page] = ($artisteNav['http'])($base . '/publier.php?sortie=' . $releaseId . '&etape=recapitulatif');
    verif('Recapitulatif : ce qui manque est liste, soumission impossible', str_contains($page, 'data-manques') && str_contains($page, 'langue'));
    [, $page] = ($artisteNav['http'])($base . '/publier.php');
    verif('Brouillon interrompu : repris depuis « A reprendre »', str_contains($page, 'ZZM12 Single ' . $marque) && str_contains($page, 'sortie=' . $releaseId));
    $page = poster($artisteNav, $base . '/publier.php?sortie=' . $releaseId . '&etape=metadonnees', ['action' => 'metadonnees', 'sortie' => $releaseId,
        'sortie_meta' => ['title' => 'ZZM12 Single ' . $marque, 'genre_id' => $genre, 'language' => 'fr', 'release_date' => date('Y-m-d')],
        'titres' => [(int) $t1['id'] => ['title' => 'Mon titre', 'credits' => 'Paroles et musique : Adoum Essai', 'track_number' => 1]]])[1];
    [, $page] = poster($artisteNav, $base . '/publier.php?sortie=' . $releaseId . '&etape=prix', ['action' => 'prix', 'sortie' => $releaseId, 'prix' => [(int) $t1['id'] => '300']]);
    verif('Niveau Decouverte : vente refusee cote serveur', str_contains($page, 'Niveau Decouverte'));
    poster($artisteNav, $base . '/publier.php?sortie=' . $releaseId . '&etape=prix', ['action' => 'prix', 'sortie' => $releaseId, 'gratuit' => '1']);

    echo "\n=== E. Machine a etats et moderation (MOD-01, MOD-02) ===\n";
    verif('Brouillon publie directement : refuse par la base', refuse($db, "UPDATE releases SET status = 'approved' WHERE id = {$releaseId}"));
    verif('L\'artiste ne peut pas s\'approuver (role)', !Moderation::transition('release', $releaseId, 'approved', $userId, 'artiste')['succes']);
    $avantFile = time();
    poster($artisteNav, $base . '/publier.php?sortie=' . $releaseId . '&etape=recapitulatif', ['action' => 'soumettre', 'sortie' => $releaseId]);
    $revue = $db->query("SELECT * FROM moderation_reviews WHERE content_type = 'release' AND content_id = {$releaseId} ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    verif('Soumission : sortie et titre en attente, en file aussitot', $revue && strtotime((string) $revue['submitted_at']) >= $avantFile - 5
        && $db->query("SELECT status FROM releases WHERE id = {$releaseId}")->fetchColumn() === 'pending' && $db->query("SELECT status FROM tracks WHERE id = {$t1['id']}")->fetchColumn() === 'pending');
    verif('Contenu en attente : absent du catalogue public', searchContent('ZZM12', 20)['tracks'] === []);
    [, $page] = ($adm['http'])($base . '/admin/moderation.php');
    verif('File de moderation : la soumission y figure', str_contains($page, 'data-revue="' . $revue['id'] . '"') && str_contains($page, 'ZZM12 Single'));
    [, $page] = ($adm['http'])($base . '/admin/moderation.php?revue=' . $revue['id']);
    verif('Fiche de revue : ecoute, controles automatiques, droits declares', str_contains($page, '<audio') && str_contains($page, 'data-controle="debit"') && str_contains($page, 'droits declares le'));
    verif('Un autre moderateur ne peut pas prendre un dossier deja pris', Moderation::assigner((int) $revue['id'], $admin)['succes'] && !Moderation::assigner((int) $revue['id'], $userId)['succes']);
    verif('Approbation sans grille complete : refusee', !Moderation::decider((int) $revue['id'], 'approved', '', '', ['qualite_audio'], $admin)['succes']);
    $position = tailleJournal('mail.log');
    poster($adm, 'admin/moderation.php?revue=' . $revue['id'], ['action' => 'decider', 'revue' => $revue['id'], 'decision' => 'correction', 'motif_code' => 'metadonnees', 'motif' => 'Precisez le compositeur dans les credits']);
    verif('Correction demandee : sortie refusee, motif enregistre', $db->query("SELECT status FROM releases WHERE id = {$releaseId}")->fetchColumn() === 'rejected');
    verif('... l\'artiste est notifie avec le motif', str_contains(courrielsDepuis($position), 'Precisez le compositeur'));
    [, $page] = ($artisteNav['http'])($base . '/publier.php?sortie=' . $releaseId);
    verif('... et voit le retour dans le parcours de publication', str_contains($page, 'Precisez le compositeur'));
    poster($artisteNav, $base . '/publier.php?sortie=' . $releaseId . '&etape=recapitulatif', ['action' => 'soumettre', 'sortie' => $releaseId]);
    $revue2 = (int) $db->query("SELECT id FROM moderation_reviews WHERE content_type = 'release' AND content_id = {$releaseId} AND decision IS NULL")->fetchColumn();
    verif('Correction puis nouvelle soumission : nouvelle revue', $revue2 > (int) $revue['id']);
    $r = Moderation::decider($revue2, 'approved', '', '', array_keys(Moderation::GRILLE), $admin);
    verif('Approbation avec la grille complete : publie', $r['succes'] && $db->query("SELECT status FROM tracks WHERE id = {$t1['id']}")->fetchColumn() === 'approved');
    verif('Contenu approuve : visible au catalogue public', in_array((int) $t1['id'], array_map('intval', array_column(searchContent('Mon titre', 50)['tracks'], 'id')), true));
    $transitions = $db->query("SELECT CONCAT(from_status, '>', to_status) FROM content_transitions WHERE content_type = 'release' AND content_id = {$releaseId} ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    verif('Chaque transition journalisee (auteur, motif)', $transitions === ['draft>pending', 'pending>rejected', 'rejected>pending', 'pending>approved'], json_encode($transitions));
    verif('Journal des transitions immuable (base)', refuse($db, "UPDATE content_transitions SET reason = 'x' WHERE content_id = {$releaseId}"));
    $ind = Moderation::indicateurs();
    verif('Delai de traitement mesurable', $ind['delai_moyen_heures'] !== null && $ind['decisions'] >= 2, json_encode($ind));

    echo "\n=== F. Signalements (MOD-03) ===\n";
    $fan = navigateur();
    $emailFan = $marque . 'f@test.local';
    $db->prepare("INSERT INTO users (username, email, password_hash, first_name, last_name, is_active, email_verified) VALUES (?, ?, ?, 'Fan', 'Essai', 1, 1)")
       ->execute([$marque . 'f', $emailFan, password_hash($motDePasse, PASSWORD_DEFAULT)]);
    $fanId = (int) $db->lastInsertId();
    poster($fan, 'login.php', ['email' => $emailFan, 'password' => $motDePasse]);
    [, $page] = poster($fan, 'signaler.php?type=track&id=' . $t1['id'], ['type' => 'track', 'id' => $t1['id'], 'categorie' => 'droit_auteur', 'description' => 'Cette oeuvre est la mienne, deposee en 2019.', 'titulaire' => '', 'contact' => '']);
    verif('Revendication incomplete (titulaire, contact) : refusee', str_contains($page, 'nom du titulaire'));
    $t0 = microtime(true);
    poster($fan, 'signaler.php?type=track&id=' . $t1['id'], ['type' => 'track', 'id' => $t1['id'], 'categorie' => 'droit_auteur', 'description' => 'Cette oeuvre est la mienne, deposee en 2019.', 'titulaire' => 'Ayant droit Essai', 'contact' => 'droits@test.local']);
    $signalement = $db->query("SELECT * FROM reports WHERE reported_type = 'track' AND reported_id = {$t1['id']} ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    verif(sprintf('Revendication de droits : retrait du catalogue public immediat (%.1f s)', microtime(true) - $t0),
        $signalement && $db->query("SELECT status FROM tracks WHERE id = {$t1['id']}")->fetchColumn() === 'offline'
        && !in_array((int) $t1['id'], array_map('intval', array_column(searchContent('Mon titre', 50)['tracks'], 'id')), true) && microtime(true) - $t0 < 60);
    verif('... priorite 1, delai de reponse fixe', (int) $signalement['priority'] === 1 && $signalement['response_deadline'] !== null);
    [, $page] = poster($artisteNav, 'artiste-signalements.php', ['signalement' => $signalement['id'], 'reponse' => 'Titre compose en 2024, contrat de production joint, aucune reprise.']);
    verif('Contre-notification de l\'artiste enregistree', $db->query("SELECT counter_notice FROM reports WHERE id = {$signalement['id']}")->fetchColumn() !== null);
    verif('Decision sans motif : refusee (et en base)', !Signalements::decider((int) $signalement['id'], 'maintenu', '', $admin)['succes']
        && refuse($db, "UPDATE reports SET decision = 'retire', decision_reason = NULL WHERE id = {$signalement['id']}"));
    [, $page] = ($adm['http'])($base . '/admin/signalements.php');
    verif('File des signalements : la revendication en tete', str_contains($page, 'data-signalement="' . $signalement['id'] . '"'));
    poster($adm, 'admin/signalements.php', ['signalement' => $signalement['id'], 'decision' => 'maintenu', 'motif' => 'Antériorite de l\'artiste etablie par le contrat']);
    verif('Revendication non fondee : contenu remis en ligne, decision motivee', $db->query("SELECT status FROM tracks WHERE id = {$t1['id']}")->fetchColumn() === 'approved'
        && $db->query("SELECT decision FROM reports WHERE id = {$signalement['id']}")->fetchColumn() === 'maintenu');
    Signalements::signaler($fanId, 'track', (int) $t1['id'], 'inapproprie', 'Propos injurieux dans le deuxieme couplet.');
    $s2 = (int) $db->query("SELECT id FROM reports WHERE reported_id = {$t1['id']} AND category = 'inapproprie'")->fetchColumn();
    verif('Signalement ordinaire : pas de retrait avant decision', $db->query("SELECT status FROM tracks WHERE id = {$t1['id']}")->fetchColumn() === 'approved');
    Signalements::decider($s2, 'retire', 'Propos injurieux confirmes a l\'ecoute', $admin);
    verif('Decision de retrait : contenu hors ligne', $db->query("SELECT status FROM tracks WHERE id = {$t1['id']}")->fetchColumn() === 'offline');
    verif('Journal des decisions consultable', count(array_filter(Signalements::journal(), fn($j) => (int) $j['reported_id'] === (int) $t1['id'])) === 2);
    [, $page] = ($adm['http'])($base . '/admin-dashboard.php');
    verif('Console : moderation, dossiers artistes, signalements', str_contains($page, '/admin/moderation.php') && str_contains($page, '/admin/dossiers-artistes.php') && str_contains($page, '/admin/signalements.php'));
    foreach ([$artisteNav, $adm, $fan] as $n) {
        @unlink($n['cookies']);
    }
} finally {
    if (is_resource($smtp)) {
        exec('taskkill /PID ' . (int) proc_get_status($smtp)['pid'] . ' /T /F >NUL 2>&1');
    }
    if ($artistId > 0) {
        $db->exec("DELETE FROM reports WHERE reported_type = 'track' AND reported_id IN (SELECT id FROM tracks WHERE artist_id = {$artistId})");
        $db->exec("DELETE FROM moderation_reviews WHERE artist_id = {$artistId}");
        $db->exec("DELETE FROM streams WHERE artist_id = {$artistId}");
        $db->exec("DELETE FROM tracks WHERE artist_id = {$artistId}");
        $db->exec("DELETE FROM releases WHERE artist_id = {$artistId}");
        $db->exec("DELETE FROM content_transitions WHERE content_type IN ('release', 'track') AND content_id NOT IN (SELECT id FROM releases) AND content_id NOT IN (SELECT id FROM tracks)");
        $db->exec("DELETE FROM artist_payout_accounts WHERE artist_id = {$artistId}");
        $db->exec("DELETE FROM artists WHERE id = {$artistId}");
    }
    foreach ([$userId, (int) ($fanId ?? 0)] as $u) {
        if ($u > 0) {
            try {
                $db->exec("DELETE FROM users WHERE id = {$u}");
            } catch (Throwable $e) {
                $db->exec("UPDATE users SET is_active = 0 WHERE id = {$u}");
            }
        }
    }
    foreach ($fichiersCrees as $f) {
        @unlink($f);
    }
    foreach (glob($tmp . '/*') ?: [] as $f) {
        @unlink($f);
    }
    @rmdir($tmp);
}

echo "\n" . sprintf("Resultat : %d reussi(s), %d echec(s)\n", $ok, $ko);
exit($ko === 0 ? 0 : 1);
