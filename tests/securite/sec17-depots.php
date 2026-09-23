<?php
/**
 * Tests SEC-17 : depots de fichiers et publication par URL.
 *
 * Verifie que le contenu depose est reellement du type annonce, qu'une image
 * ne peut plus transporter de charge utile, qu'aucun formulaire n'accepte
 * plus une URL arbitraire comme chemin de media, et que les prix sont bornes.
 *
 * Usage : C:\xampp\php\php.exe tests\securite\sec17-depots.php
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

$base = 'http://localhost/tchadok';
$bac = sys_get_temp_dir() . '/tchadok-sec17/';
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
 * Simule un depot et renvoie le resultat de uploadFile().
 */
function depot(string $nom, string $contenu, array $types, int $max = 5242880, ?string $dossier = null): array
{
    global $bac;
    $dossier ??= $bac;
    $temporaire = tempnam(sys_get_temp_dir(), 'sec17');
    file_put_contents($temporaire, $contenu);

    $resultat = uploadFile(
        ['name' => $nom, 'tmp_name' => $temporaire, 'error' => UPLOAD_ERR_OK, 'size' => strlen($contenu)],
        $dossier,
        $types,
        $max
    );

    @unlink($temporaire);

    return $resultat;
}

function imageEssai(int $largeur = 40, int $hauteur = 40): string
{
    $image = imagecreatetruecolor($largeur, $hauteur);
    imagefilledrectangle($image, 0, 0, $largeur, $hauteur, imagecolorallocate($image, 20, 120, 200));
    ob_start();
    imagejpeg($image, null, 90);
    $donnees = (string) ob_get_clean();
    imagedestroy($image);

    return $donnees;
}

function mp3Minimal(): string
{
    // En-tete ID3v2 suivi de trames MPEG : de quoi etre reconnu comme audio.
    return "ID3\x03\x00\x00\x00\x00\x00\x00" . str_repeat("\xFF\xFB\x90\x00" . str_repeat("\x00", 100), 40);
}

function requete(string $url, ?array $post = null, ?string $cookies = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    if ($cookies !== null) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookies);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookies);
    }
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $corps = (string) curl_exec($ch);
    $code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['code' => $code, 'corps' => $corps];
}

@mkdir($bac, 0777, true);
$db = TchadokDatabase::getInstance()->getConnection();
$db->exec('DELETE FROM login_attempts');
$db->exec('DELETE FROM rate_limit_hits');
$cookies = tempnam(sys_get_temp_dir(), 'sec17c');

try {
    echo "\n=== A. Le contenu doit correspondre a l'extension ===\n";
    $charge = '<' . '?php system($_GET["c"]); ?' . '>';
    foreach ([
        ['piege.mp3', $charge, ['mp3'], 'du PHP renomme en .mp3'],
        ['piege.jpg', $charge, ['jpg'], 'du PHP renomme en .jpg'],
        ['piege.png', $charge, ['png'], 'du PHP renomme en .png'],
        ['faux.mp3', str_repeat('texte ordinaire ', 40), ['mp3'], 'du texte renomme en .mp3'],
        ['faux.wav', mp3Minimal(), ['wav'], 'un MP3 renomme en .wav'],
        ['faux.png', imageEssai(), ['png'], 'un JPEG renomme en .png'],
    ] as [$nom, $contenu, $types, $quoi]) {
        $r = depot($nom, $contenu, $types);
        verif("Refuse : {$quoi}", empty($r['success']), (string) ($r['message'] ?? ''));
    }

    foreach (['logo.svg' => ['jpg', 'png'], 'script.php' => ['jpg'], 'archive.zip' => ['mp3']] as $nom => $types) {
        $r = depot($nom, 'contenu', $types);
        verif("Refuse une extension hors liste : {$nom}", empty($r['success']));
    }

    echo "\n=== B. Les fichiers legitimes passent ===\n";
    $r = depot('chanson.mp3', mp3Minimal(), ['mp3', 'wav', 'flac', 'm4a']);
    verif('MP3 valide accepte', !empty($r['success']), (string) ($r['message'] ?? ''));
    verif('Nom de stockage aleatoire', (bool) preg_match('/^[0-9a-f]{32}\.mp3$/', (string) ($r['filename'] ?? '')), (string) ($r['filename'] ?? ''));
    $premier = (string) ($r['filename'] ?? '');
    $r2 = depot('chanson.mp3', mp3Minimal(), ['mp3']);
    verif('Deux depots ne portent jamais le meme nom', $premier !== (string) ($r2['filename'] ?? ''));

    $r = depot('pochette.jpeg', imageEssai(), ['jpg', 'jpeg']);
    verif('JPEG valide accepte', !empty($r['success']), (string) ($r['message'] ?? ''));
    verif('Extension de stockage normalisee en .jpg', str_ends_with((string) ($r['filename'] ?? ''), '.jpg'), (string) ($r['filename'] ?? ''));

    echo "\n=== C. Une image ne transporte plus de charge utile ===\n";
    $r = depot('avec-charge.jpg', imageEssai() . $charge, ['jpg']);
    verif('Image acceptee', !empty($r['success']), (string) ($r['message'] ?? ''));
    $enregistre = (string) file_get_contents((string) $r['path']);
    verif('La charge PHP ne survit pas au re-encodage', !str_contains($enregistre, '<' . '?php'));
    verif('Le fichier reste une image lisible', (bool) @getimagesize((string) $r['path']));

    echo "\n=== D. Taille, vide, et repertoire ===\n";
    $r = depot('gros.mp3', mp3Minimal(), ['mp3'], 1024);
    verif('Fichier au-dela de la limite refuse', empty($r['success']));
    verif('... avec une limite lisible dans le message', str_contains((string) $r['message'], 'Ko') || str_contains((string) $r['message'], 'Mo'), (string) $r['message']);

    $r = depot('vide.mp3', '', ['mp3']);
    verif('Fichier vide refuse', empty($r['success']));

    // Repertoire impossible a creer : un fichier occupe deja le chemin.
    $obstacle = $bac . 'obstacle';
    file_put_contents($obstacle, 'x');
    $r = depot('chanson.mp3', mp3Minimal(), ['mp3'], 5242880, $obstacle . '/sous-dossier/');
    verif('Repertoire impossible : echec propre, sans erreur fatale', empty($r['success']), (string) ($r['message'] ?? ''));
    verif('... avec un message comprehensible', !str_contains((string) $r['message'], 'mkdir'), (string) $r['message']);

    echo "\n=== E. Prix bornes ===\n";
    foreach ([
        ['-500', false, 'un prix negatif'],
        ['-0.01', false, 'un prix negatif minuscule'],
        ['999999999', false, 'un prix absurde'],
        ['175', false, 'un prix hors grille'],
        ['abc', false, 'un prix non numerique'],
        ['1500', true, 'un prix correct'],
        ['0', true, 'la gratuite'],
        ['', true, 'un champ vide'],
        ['2 500', true, 'un prix avec espace'],
    ] as [$valeur, $attendu, $quoi]) {
        $r = validerPrix($valeur);
        verif(($attendu ? 'Accepte' : 'Refuse') . " : {$quoi}", $r['valide'] === $attendu, (string) $r['message']);
    }
    verif('Le prix accepte est bien converti', validerPrix('2 500')['valeur'] === 2500.0);

    echo "\n=== F. Plus aucune publication par URL ===\n";
    $champs = ['audio_file_url', 'preview_file_url', 'cover_image_url', 'featured_image_url'];
    $restes = [];
    foreach ([
        'artist-add-song.php', 'artist-add-album.php', 'admin-add-song.php', 'admin-add-album.php',
        'admin-podcasts.php', 'admin-manage-radio.php', 'admin-playlists.php', 'create-playlist.php',
        'admin-blog.php', 'upload.php', 'includes/blog-manager.php',
    ] as $fichier) {
        $contenu = (string) file_get_contents($racine . '/' . $fichier);
        foreach ($champs as $champ) {
            // Une mention en commentaire est admise ; un usage, non.
            if (preg_match('/\$_(POST|REQUEST)\[[\'"]' . $champ . '|name="' . $champ . '"|\$payload\[[\'"]' . $champ . '/', $contenu)) {
                $restes[] = "{$fichier} ({$champ})";
            }
        }
    }
    verif('Aucun formulaire ni traitement n\'accepte plus d\'URL de media', $restes === [], implode(', ', $restes));

    echo "\n=== G. Bout en bout : l'URL envoyee de force est ignoree ===\n";
    $page = requete($base . '/login.php', null, $cookies);
    $jeton = preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $page['corps'], $m) ? $m[1] : '';
    requete($base . '/login.php', ['csrf_token' => $jeton, 'email' => 'admin@tchadok.td', 'password' => 'tchadok2026'], $cookies);
    $page = requete($base . '/admin-add-song.php', null, $cookies);
    verif('Prealable : console accessible', $page['code'] === 200, (string) $page['code']);
    $jeton = preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $page['corps'], $m) ? $m[1] : '';
    verif('Le formulaire ne propose plus de champ URL', !str_contains($page['corps'], 'audio_file_url'));

    $titre = 'Essai SEC-17 ' . bin2hex(random_bytes(3));
    $r = requete($base . '/admin-add-song.php', [
        'csrf_token'     => $jeton,
        'title'          => $titre,
        'artist_id'      => '1',
        'audio_file_url' => 'https://exemple.invalid/piege.mp3',
        'price'          => '500',
    ], $cookies);
    $stmt = $db->prepare('SELECT COUNT(*) FROM tracks WHERE title = ?');
    $stmt->execute([$titre]);
    verif('Aucun titre cree a partir d\'une URL', (int) $stmt->fetchColumn() === 0);
    verif('Le formulaire redemande un fichier', str_contains($r['corps'], 'audio') || str_contains($r['corps'], 'requis'));

    $stmt = $db->query("SELECT COUNT(*) FROM tracks WHERE audio_file LIKE 'http%' OR preview_file LIKE 'http%'");
    verif('Aucun media du catalogue ne pointe vers une adresse externe', (int) $stmt->fetchColumn() === 0);
} finally {
    @unlink($cookies);
    foreach (glob($bac . '*') ?: [] as $fichier) {
        @unlink($fichier);
    }
    @rmdir($bac);
    $db->exec('DELETE FROM login_attempts');
    $db->exec('DELETE FROM rate_limit_hits');
}

echo "\n";
echo sprintf("Resultat : %d reussi(s), %d echec(s)\n", $ok, $ko);
exit($ko === 0 ? 0 : 1);
